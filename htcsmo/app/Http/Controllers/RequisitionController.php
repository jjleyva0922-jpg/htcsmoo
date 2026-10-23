<?php

namespace App\Http\Controllers;

use App\Jobs\SendRequisitionEmail;
use App\Models\ApprovalLog;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RequisitionController extends Controller
{
    public function store(Request $request, NotificationService $notifications)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'request_type' => 'required|in:minor,major',
            'purpose' => 'required|string|min:10|max:500',
            'items' => 'required|array|min:1',
            'items.*.supply_id' => 'required|integer|exists:supplies,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.options' => 'nullable|array',
            'items.*.options.*' => 'nullable|string|max:100',
        ]);

        $preparedItems = $this->prepareCatalogItems($validated['items'], $validated['request_type']);

        $calculatedGrandTotal = $preparedItems->sum('subtotal');
        if ($validated['request_type'] === 'minor' && $calculatedGrandTotal > 1000) {
            throw ValidationException::withMessages([
                'items' => 'Minor requisitions cannot exceed ₱1,000. Please choose the Major tier instead.',
            ]);
        }

        if ($validated['request_type'] === 'major' && $calculatedGrandTotal <= 1000) {
            throw ValidationException::withMessages([
                'items' => 'Major requisitions must exceed PHP 1,000. Requests totaling PHP 1,000 or less are Minor.',
            ]);
        }

        // Start after the requestor's own approval office so nobody approves
        // their own requisition or sends it backward to a lower office.
        $initialStatus = Requisition::initialStatusFor($user);

        // 3. CREATE THE HEADER
        $requisition = DB::transaction(function () use ($user, $validated, $initialStatus, $preparedItems, $calculatedGrandTotal) {
            $requisition = Requisition::create([
                'user_id' => $user->id,
                'request_type' => $validated['request_type'],
                'status' => $initialStatus,
                'purpose' => $validated['purpose'],
                'grand_total' => $calculatedGrandTotal,
            ]);

            foreach ($preparedItems as $item) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'supply_id' => $item['supply']->id,
                    'item_name' => $item['supply']->item_name,
                    'specifications' => $item['specifications'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['supply']->unit,
                    'unit_price' => $item['supply']->unit_price,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $requisition;
        });

        $this->sendSubmissionEmails($requisition, $user);

        $notifications->send($user, 'requisition.submitted', 'Requisition Submitted',
            'Your request for '.count($validated['items']).' item(s) has been submitted.', [
                'icon' => 'send', 'type' => 'info', 'action_type' => 'requisition',
                'action_id' => $requisition->id,
                'dedupe_key' => "requisition:{$requisition->id}:submitted:owner",
            ]);

        if ($initialStatus === 'pending') {
            User::query()->where('role', 'dept_head')->where('department_id', $user->department_id)
                ->where('account_status', User::ACCOUNT_ACTIVE)->eachById(fn (User $head) => $notifications->send(
                    $head, 'requisition.awaiting_approval', 'Requisition Awaiting Review',
                    "{$user->name}'s requisition {$requisition->tracking_code} is ready for department review.", [
                        'icon' => 'file-text', 'type' => 'info', 'action_type' => 'approval_queue',
                        'action_id' => $requisition->id,
                        'dedupe_key' => "requisition:{$requisition->id}:awaiting:dept-head",
                    ]
                ));
        } elseif ($nextRole = $requisition->nextReviewerRole()) {
            $isExecutivePriority = in_array($user->role, ['president', 'provost'], true);
            $notifications->sendToRole(
                $nextRole,
                $nextRole === 'smo' ? 'requisition.ready_for_release' : 'requisition.awaiting_approval',
                $isExecutivePriority ? 'Priority Requisition Ready' : 'Requisition Ready for Action',
                $isExecutivePriority
                    ? "A direct requisition from the {$user->role} ({$user->name}) is ready for release."
                    : "Requisition {$requisition->tracking_code} from {$user->name} is ready for your review.",
                [
                    'icon' => $isExecutivePriority ? 'zap' : 'file-text',
                    'type' => $isExecutivePriority ? 'danger' : ($nextRole === 'smo' ? 'warning' : 'info'),
                    'action_type' => 'approval_queue',
                    'action_id' => $requisition->id,
                    'dedupe_key' => "requisition:{$requisition->id}:initial-route:{$nextRole}",
                ]
            );
        }

        return redirect()->route('dashboard')->with('success', 'Requisition processed successfully.');
    }

    private function sendSubmissionEmails(Requisition $requisition, User $requestor): void
    {
        $recipients = User::query()
            ->where('role', 'smo')
            ->where('account_status', User::ACCOUNT_ACTIVE)
            ->where('id', '!=', $requestor->id)
            ->get()
            ->prepend($requestor);

        foreach ($recipients as $recipient) {
            $isRequestor = $recipient->is($requestor);

            try {
                SendRequisitionEmail::dispatch(
                    $recipient->email,
                    $recipient->id,
                    $requisition->id,
                    $isRequestor ? 'Requisition Submitted' : 'New Requisition Submitted',
                    $isRequestor ? 'Your Requisition Has Been Submitted' : 'New Requisition Received',
                    $isRequestor
                        ? "Your requisition {$requisition->tracking_code} has been submitted successfully. You can view its current status in the system."
                        : "{$requestor->name} has submitted requisition {$requisition->tracking_code}. You can view its details and current approval status in the system.",
                    '#185b3b',
                );
            } catch (\Throwable $exception) {
                Log::error('Could not queue requisition submission email.', [
                    'requisition_id' => $requisition->id,
                    'user_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function validateSelectedOptions(Supply $supply, array $selected): array
    {
        $configured = $supply->requestOptions();
        $clean = [];

        foreach ($configured as $label => $choices) {
            $value = $selected[$label] ?? (count($choices) === 1 ? $choices[0] : null);
            if (! is_string($value) || ! in_array($value, $choices, true)) {
                throw ValidationException::withMessages([
                    'items' => "Please select a valid {$label} for {$supply->item_name}.",
                ]);
            }
            $clean[$label] = $value;
        }

        return $clean;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function prepareCatalogItems(array $submittedItems, string $requestType): Collection
    {
        $catalogItems = Supply::whereIn('id', collect($submittedItems)->pluck('supply_id'))
            ->get()
            ->keyBy('id');

        $preparedItems = collect($submittedItems)->map(function (array $itemData) use ($catalogItems, $requestType) {
            $supply = $catalogItems->get($itemData['supply_id']);

            if (! $supply || $supply->requisition_tier !== $requestType) {
                throw ValidationException::withMessages([
                    'items' => 'One or more selected items do not belong to this requisition tier.',
                ]);
            }
            if ($itemData['qty'] > $supply->quantity) {
                throw ValidationException::withMessages([
                    'items' => "Only {$supply->quantity} {$supply->unit} of {$supply->item_name} are currently available.",
                ]);
            }

            $selectedOptions = $this->validateSelectedOptions($supply, $itemData['options'] ?? []);
            $optionSummary = collect($selectedOptions)
                ->map(fn ($value, $label) => "{$label}: {$value}")
                ->implode('; ');
            $specifications = collect([$supply->specifications, $optionSummary])->filter()->implode(' | ');

            return [
                'supply' => $supply,
                'specifications' => $specifications ?: null,
                'quantity' => (int) $itemData['qty'],
                'subtotal' => (int) $itemData['qty'] * (float) $supply->unit_price,
            ];
        });

        foreach ($preparedItems->groupBy(fn ($item) => $item['supply']->id) as $itemsForSupply) {
            $supply = $itemsForSupply->first()['supply'];
            if ($itemsForSupply->sum('quantity') > $supply->quantity) {
                throw ValidationException::withMessages([
                    'items' => "Only {$supply->quantity} {$supply->unit} of {$supply->item_name} are available across all selected options.",
                ]);
            }
        }

        return $preparedItems;
    }

    private function catalogPayload(Supply $supply): array
    {
        return [
            'id' => $supply->id,
            'name' => $supply->item_name,
            'brand' => $supply->brand,
            'category' => $supply->category,
            'tier' => $supply->requisition_tier ?? 'minor',
            'specs' => $supply->specifications,
            'unit' => $supply->unit,
            'price' => (float) $supply->unit_price,
            'stock' => (int) $supply->quantity,
            'options' => $supply->requestOptions(),
        ];
    }

    private function selectedOptionsFromSpecifications(Supply $supply, ?string $specifications): array
    {
        return collect($supply->requestOptions())
            ->mapWithKeys(function (array $choices, string $label) use ($specifications) {
                $selected = collect($choices)->first(
                    fn (string $choice) => str_contains((string) $specifications, "{$label}: {$choice}"),
                );
                $selected ??= count($choices) === 1 ? $choices[0] : null;

                return $selected === null ? [] : [$label => $selected];
            })
            ->all();
    }

    // 1. Show Edit Form
    public function edit(int $id)
    {
        $requisition = Requisition::with('items.supply')->where('user_id', Auth::id())->findOrFail($id);

        // Security Guardrail: Only allow editing if still pending
        if ($requisition->status !== 'pending') {
            return redirect()->route('dashboard')->with('error', 'Cannot edit: Requisition is already in the approval process.');
        }

        $catalog = Supply::query()
            ->with(['supplyCategory', 'supplyUnit', 'options.values'])
            ->where('requisition_tier', $requisition->request_type)
            ->orderBy(SupplyCategory::select('category_name')
                ->whereColumn('supply_categories.id', 'supplies.supply_category_id'))
            ->orderBy('item_name')
            ->get()
            ->toBase()
            ->map(fn (Supply $supply) => $this->catalogPayload($supply))
            ->values();

        $catalogById = $catalog->keyBy('id');
        $initialItems = $requisition->items->map(function (RequisitionItem $item) use ($catalogById) {
            $catalogItem = $catalogById->get($item->supply_id);

            return [
                ...($catalogItem ?? [
                    'id' => $item->supply_id,
                    'name' => $item->item_name,
                    'brand' => null,
                    'category' => 'Unavailable catalog item',
                    'tier' => null,
                    'specs' => $item->specifications,
                    'unit' => $item->unit,
                    'price' => (float) $item->unit_price,
                    'stock' => 0,
                    'options' => [],
                ]),
                'qty' => (int) $item->quantity,
                'selected_options' => $item->supply
                    ? $this->selectedOptionsFromSpecifications($item->supply, $item->specifications)
                    : [],
            ];
        })->values();

        return view('requests.edit', compact('requisition', 'catalog', 'initialItems'));
    }

    // 2. Save Changes
    public function update(Request $request, int $id)
    {
        $requisition = Requisition::where('user_id', Auth::id())->findOrFail($id);

        if ($requisition->status !== 'pending') {
            return back()->with('error', 'Access denied: Approval has already started.');
        }

        $validated = $request->validate([
            'purpose' => 'required|string|min:10|max:500',
            'items' => 'required|array|min:1',
            'items.*.supply_id' => 'required|integer|exists:supplies,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.options' => 'nullable|array',
            'items.*.options.*' => 'nullable|string|max:100',
        ]);

        $preparedItems = $this->prepareCatalogItems($validated['items'], $requisition->request_type);
        $grandTotal = $preparedItems->sum('subtotal');

        if ($requisition->request_type === 'minor' && $grandTotal > 1000) {
            throw ValidationException::withMessages(['items' => 'Minor requisitions cannot exceed ₱1,000.']);
        }

        if ($requisition->request_type === 'major' && $grandTotal <= 1000) {
            throw ValidationException::withMessages(['items' => 'Major requisitions must exceed PHP 1,000.']);
        }

        DB::transaction(function () use ($preparedItems, $requisition, $grandTotal, $validated) {
            $lockedRequisition = Requisition::query()
                ->whereKey($requisition->id)
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequisition->status !== 'pending') {
                throw ValidationException::withMessages([
                    'items' => 'Approval has already started. This requisition can no longer be changed.',
                ]);
            }

            $lockedRequisition->items()->delete();
            foreach ($preparedItems as $item) {
                $lockedRequisition->items()->create([
                    'supply_id' => $item['supply']->id,
                    'item_name' => $item['supply']->item_name,
                    'specifications' => $item['specifications'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['supply']->unit,
                    'unit_price' => $item['supply']->unit_price,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $lockedRequisition->update([
                'purpose' => $validated['purpose'],
                'grand_total' => $grandTotal,
            ]);
        });

        return redirect()->route('requests.index')->with('success', 'Requisition updated successfully.');
    }

    // Keep the existing DELETE URL compatible, but retain the submitted record.
    public function destroy(Request $request, int $id)
    {
        $validated = $request->validate(['withdrawal_reason' => ['nullable', 'string', 'max:500']]);
        $withdrawn = DB::transaction(function () use ($id, $validated, $request) {
            $requisition = Requisition::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            if ($requisition->status !== 'pending') {
                return false;
            }
            $closedAt = now();
            $requisition->update(['status' => 'withdrawn', 'closed_at' => $closedAt]);
            $log = new ApprovalLog([
                'requisition_id' => $requisition->id, 'acted_by_user_id' => $request->user()->id,
                'action' => 'Withdrawn by requestor', 'role' => $request->user()->role,
                'remarks' => filled($validated['withdrawal_reason'] ?? null)
                    ? trim($validated['withdrawal_reason']) : 'Withdrawn by the requestor before approval.',
            ]);
            $log->created_at = $closedAt;
            $log->save();

            return true;
        });

        if (! $withdrawn) {
            return back()->with('error', 'Only your pending requests can be withdrawn. This request has already moved on or is closed.');
        }

        return redirect()->route('requests.archive', ['category' => 'withdrawn'])
            ->with('success', 'Request withdrawn. Its items and history have been kept in your archive.');
    }
}
