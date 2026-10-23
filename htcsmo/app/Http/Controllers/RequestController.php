<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActiveRequestIndexRequest;
use App\Http\Requests\ApprovalQueueRequest;
use App\Http\Requests\ArchiveIndexRequest;
use App\Jobs\SendRequisitionEmail;
use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\DigitalSignature;
use App\Models\Notification;
use App\Models\Requisition;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use App\Services\ArchiveService;
use App\Services\DeadlineNotificationService;
use App\Services\FulfillmentInventoryService;
use App\Services\InventoryAlertService;
use App\Services\NotificationActionResolver;
use App\Services\NotificationService;
use App\Services\WorkspaceVersionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    /**
     * Lightweight fingerprint used by open pages to detect requisition activity.
     */
    public function systemVersion()
    {
        return response()->json(['version' => $this->runtimeVersion()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * One compact response replaces the former notification and refresh pollers.
     */
    public function runtimeState(Request $request, NotificationActionResolver $actionResolver)
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'scope' => ['nullable', 'in:all,dashboard,inventory,requests,approvals,users,notifications,none'],
        ]);
        $user = $request->user();
        $afterId = (int) ($validated['after_id'] ?? 0);
        $notificationState = Notification::query()->forUser($user)
            ->selectRaw(
                'COALESCE(MAX(id), 0) as latest_id, COALESCE(SUM(CASE WHEN is_read = ? THEN 1 ELSE 0 END), 0) as unread_count',
                [false]
            )
            ->first();
        $notifications = Notification::query()->forUser($user)->unread()
            ->where('id', '>', $afterId)->oldest('id')->limit(10)->get();
        $resolvedActions = $actionResolver->resolve($notifications, $user);
        $payloads = $notifications->map(
            fn (Notification $notification) => $notification->pollPayload(
                $user,
                $resolvedActions[$notification->id] ?? null,
                true,
            )
        )->values();
        $latestId = (int) $notificationState->latest_id;
        $versions = app(WorkspaceVersionService::class)->versions();
        $versions['dashboard'] = app(WorkspaceVersionService::class)->dashboardVersion($versions['all'], $latestId);

        return response()->json([
            'version' => $versions[$validated['scope'] ?? 'all'],
            'versions' => $versions,
            'account_id' => $user->id,
            'latest_id' => $latestId,
            'cursor' => (int) ($notifications->last()?->id ?? $latestId),
            'unread_count' => (int) $notificationState->unread_count,
            'notifications' => $payloads,
            'notification' => $payloads->first(),
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
    }

    private function runtimeVersion(): string
    {
        return app(WorkspaceVersionService::class)->version();
    }

    /**
     * Active Requisitions (Pending, In-Progress, Approved)
     */
    public function index(ActiveRequestIndexRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        $scopeQuery = Requisition::query()
            ->whereNotIn('status', Requisition::ARCHIVED_STATUSES);

        if ($user->role !== 'smo') {
            $scopeQuery->where('user_id', $user->id);
        }

        $requestStats = [
            'total' => (clone $scopeQuery)->count(),
            'pending' => (clone $scopeQuery)->where('status', 'pending')->count(),
            'clarification' => (clone $scopeQuery)->where('status', 'for_clarification')->count(),
            'ready' => (clone $scopeQuery)->where(function ($ready) {
                $ready->where(function ($minor) {
                    $minor->where('request_type', 'minor')->where('status', 'approved_vp');
                })->orWhere(function ($major) {
                    $major->where('request_type', 'major')->where('status', 'approved_president');
                });
            })->count(),
            'deadline' => (clone $scopeQuery)->deadlineRisks()->count(),
        ];

        $query = (clone $scopeQuery)->with(['items', 'user.academicDepartment']);
        if ($user->role === 'employee') {
            $query->with('latestClarificationLog');
            $requestStats['pickup'] = (clone $scopeQuery)->where('status', 'ready_for_pickup')->count();
        }

        $query->search($validated['search'] ?? null);
        if ($request->filled('status')) {
            if ($user->role === 'employee' && $request->status === 'action_required') {
                $query->needingRequestorAction();
            } else {
                $request->status === 'deadline'
                    ? $query->deadlineRisks()
                    : $query->where('status', $request->status);
            }
        }
        if ($user->role == 'smo' && $request->filled('dept')) {
            $query->whereHas('user', fn ($q) => $q->inDepartment($request->dept));
        }
        if ($request->filled('date_from')) {
            $user->role === 'employee'
                ? $query->whereRaw('COALESCE(submitted_at, created_at) >= ?', [Carbon::parse($request->date_from)->startOfDay()])
                : $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $user->role === 'employee'
                ? $query->whereRaw('COALESCE(submitted_at, created_at) < ?', [Carbon::parse($request->date_to)->startOfDay()->addDay()])
                : $query->whereDate('created_at', '<=', $request->date_to);
        }

        $order = $request->get('order') === 'asc' ? 'asc' : 'desc';
        $user->role === 'employee'
            ? $query->orderByRaw('COALESCE(submitted_at, created_at) '.$order)->orderBy('id', $order)
            : $query->orderBy('created_at', $order);
        $activeRequests = $query->paginate(15)->withQueryString();
        $departments = $user->role === 'smo'
            ? Department::query()->whereHas('users')->orderBy('dept_code')->pluck('dept_code')
            : collect();

        $approvalDeadlineRequests = collect();
        if ($user->role === 'smo' || in_array($user->role, ['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'], true)) {
            $deadlineQuery = Requisition::query()
                ->with(['items', 'user.academicDepartment'])
                ->approvalDeadlineRisks();

            if ($user->role !== 'smo') {
                $deadlineQuery->assignedToReviewer($user);
            }

            $approvalDeadlineRequests = $deadlineQuery
                ->oldest('due_at')
                ->take(8)
                ->get();
        }

        return view('requests.index', compact('activeRequests', 'requestStats', 'departments', 'approvalDeadlineRequests'));
    }

    public function sendApprovalDeadlineReminder(Request $request, Requisition $requisition, DeadlineNotificationService $deadlineNotifications)
    {
        abort_unless($request->user()->role === 'smo', 403);

        $result = $deadlineNotifications->notify($requisition);

        if (! $result['eligible']) {
            return back()->with('error', 'This requisition is no longer at a deadline risk.');
        }

        if ($result['delivered'] < $result['expected']) {
            return back()->with('error', 'Some deadline reminders could not be delivered. Please try again; recipients already notified will not receive duplicates.');
        }

        if ($result['reviewer_missing']) {
            return back()->with('error', 'No active current owner is assigned to this requisition. Check the signatory account and department assignment. Other active recipients have been notified.');
        }

        return back()->with(
            'success',
            $result['created'] > 0
                ? 'Deadline reminders have been delivered in-app to all active recipients, including the current owner.'
                : 'All active deadline recipients were already notified in-app about this requisition at its current stage today.'
        );
    }

    /**
     * Archived requisitions, scoped to their owner unless the viewer is SMO.
     */
    public function archive(ArchiveIndexRequest $request, ArchiveService $archives)
    {
        return $archives->index($request);
    }

    /**
     * Admin/Signatory Approval Queue
     */
    public function adminIndex(ApprovalQueueRequest $request, FulfillmentInventoryService $inventory)
    {
        $user = Auth::user();
        $relations = ['items', 'user.academicDepartment', 'logs.actor'];
        if ($user->role === 'smo') {
            $relations[] = 'items.supply';
        }
        $query = Requisition::with($relations);
        $user->role === 'smo'
            ? $query->where(fn ($q) => $q->awaitingFulfillment()->orWhereIn('status', ['processing', 'ready_for_pickup']))
            : $query->assignedToReviewer($user);

        $clarifications = Requisition::with($relations)->where('status', 'for_clarification');
        if ($user->role !== 'smo') {
            $clarifications->whereRaw(
                '(SELECT role FROM approval_logs WHERE requisition_id = requisitions.id AND action LIKE ? ORDER BY id DESC LIMIT 1) = ?',
                ['%Clarification%', $user->role],
            );
            if ($user->role === 'dept_head') {
                $clarifications->whereHas('user', fn ($u) => $u->where('department_id', $user->department_id));
            }
        }

        // Notification links remain role-scoped even when current filters hide the row.
        $focusedRequestId = $request->integer('request');
        $focusedRequest = $focusedRequestId > 0
            ? ((clone $query)->find($focusedRequestId) ?? (clone $clarifications)->find($focusedRequestId))
            : null;

        foreach ([$query, $clarifications] as $filtered) {
            $filtered->search($request->input('search'));
            if ($request->filled('dept')) {
                $filtered->whereHas('user', fn ($u) => $u->inDepartment($request->dept));
            }
            if ($request->filled('tier')) {
                $filtered->where('request_type', $request->tier);
            }
            if ($request->filled('status')) {
                $filtered->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $filtered->whereRaw('COALESCE(submitted_at, created_at) >= ?', [Carbon::parse($request->date_from)->startOfDay()]);
                $filtered->whereRaw('COALESCE(submitted_at, created_at) < ?', [Carbon::parse($request->date_to)->startOfDay()->addDay()]);
            }
            if ($request->input('sort', 'priority') === 'priority' && $user->role === 'smo') {
                $filtered->prioritizeExecutiveRequestors();
            } elseif ($request->input('sort') === 'deadline') {
                $filtered->orderByRaw('CASE WHEN due_at <= ? THEN 0 WHEN sla_warning_at <= ? THEN 1 WHEN due_at IS NULL THEN 3 ELSE 2 END', [now(), now()])
                    ->orderBy('due_at');
            }
            $direction = $request->input('sort') === 'oldest' ? 'asc' : 'desc';
            $filtered->orderByRaw('COALESCE(submitted_at, created_at) '.$direction)->orderBy('id', $direction);
        }

        $paginate = function ($q, $page, $tab) {
            $results = $q->paginate(15, ['*'], $page);
            if ($results->total() > 0 && $results->currentPage() > $results->lastPage()) {
                $results = $q->paginate(15, ['*'], $page, $results->lastPage());
            }

            return $results->withQueryString()->appends(['tab' => $tab, 'request' => null]);
        };
        $clarificationRequests = $paginate($clarifications, 'inquiry_page', 'clarification');
        $data = compact('clarificationRequests', 'focusedRequest');

        if ($user->role === 'smo') {
            $data['pendingMinor'] = $paginate((clone $query)->awaitingFulfillment()->where('request_type', 'minor'), 'minor_page', 'minor');
            $data['pendingMajor'] = $paginate((clone $query)->awaitingFulfillment()->where('request_type', 'major'), 'major_page', 'major');
            $data['activeWork'] = $paginate((clone $query)->whereIn('status', ['processing', 'ready_for_pickup']), 'work_page', 'work');
        } else {
            $data['pendingRequests'] = $paginate($query, 'page', 'pending');
        }

        foreach ($data as $value) {
            foreach ($value instanceof Requisition ? [$value] : ($value ?? []) as $requisition) {
                $requisition->setAttribute('tracking_code', $requisition->tracking_code);
                $requisition->setAttribute('can_start_processing', $requisition->canStartProcessing());
                $requisition->setAttribute('queue_requestor', ['name' => $requisition->user?->name ?? 'Unknown requestor', 'department' => $requisition->user?->department ?? 'Unassigned']);
                $requisition->setAttribute('queue_status', $requisition->requestorStatus()['label']);
                if ($user->role === 'smo') {
                    $plan = $inventory->plan($requisition);
                    $requisition->setAttribute('stock_errors', $plan['errors']);
                    foreach ($requisition->items as $item) {
                        $item->setAttribute('stock_readiness', $plan['lines'][$item->id]);
                    }
                }
            }
        }
        $data['departments'] = Department::orderBy('dept_code')->pluck('dept_code');
        $allowedTabs = $user->role === 'smo' ? ['minor', 'major', 'work', 'clarification'] : ['pending', 'clarification'];
        $defaultTab = $focusedRequest?->status === 'for_clarification' || $request->has('inquiry_page') ? 'clarification'
            : ($request->has('work_page') || in_array($focusedRequest?->status, ['processing', 'ready_for_pickup'], true) ? 'work'
                : ($request->has('major_page') || $focusedRequest?->request_type === 'major' ? 'major' : $allowedTabs[0]));
        $data['selectedTab'] = in_array($request->input('tab', $defaultTab), $allowedTabs, true) ? $request->input('tab', $defaultTab) : $allowedTabs[0];

        return view('admin.approvals', $data);
    }

    private function approvalQueueRedirect(Request $request)
    {
        $filters = [];
        $query = $request->input('queue_query');
        if (is_string($query) && strlen($query) <= 4000) {
            parse_str($query, $filters);
            $validator = Validator::make($filters, ApprovalQueueRequest::filterRules());
            $filters = $validator->passes() ? $validator->validated() : [];
        }
        unset($filters['request']);

        return redirect()->to(route('admin.approvals', $filters).($filters ? '#approval-results' : ''));
    }

    private function fulfillmentTransaction(Closure $operation): array
    {
        try {
            return DB::transaction($operation);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return ['error' => 'The fulfillment action could not be confirmed. Reload the requisition and check its status before retrying.'];
        }
    }

    /**
     * Update Approval Status (Signatories)
     */
    public function updateStatus(Request $request, int $id, NotificationService $notifications)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,clarification',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $role = Auth::user()->role;

        DB::beginTransaction();

        try {
            // Lock the workflow row so two reviewers cannot advance the same
            // stage concurrently and generate contradictory notifications.
            $sr = Requisition::with(['items', 'user.academicDepartment'])->lockForUpdate()->findOrFail($id);

            abort_unless(
                $this->canReviewRequisition($sr, Auth::user()),
                403,
                'This requisition is not assigned to your approval stage.'
            );

            $roleTitles = [
                'dept_head' => 'Department Head',
                'vp_finance' => 'VP for Finance',
                'vp_admin' => 'VP for Administration',
                'provost' => 'School Provost',
                'president' => 'School President',
                'smo' => 'SMO In-Charge',
            ];
            $position = $roleTitles[$role] ?? ucwords(str_replace('_', ' ', $role));

            $sr->remarks = $request->remarks;

            // 1. REJECTED
            if ($request->status === 'rejected') {
                $sr->status = 'rejected';
                $actionText = 'Rejected by '.$position;

                $this->sendAlert(
                    $sr->user_id,
                    'Requisition Disapproved',
                    "Your request {$sr->tracking_code} was rejected by {$position}. Reason: {$request->remarks}",
                    'x-circle',
                    'danger',
                    'requisition.rejected',
                    'requisition',
                    $sr->id,
                    "requisition:{$sr->id}:rejected:{$role}"
                );
            }
            // 2. QUESTIONABLE / OFFICE CLARIFICATION
            elseif ($request->status === 'clarification') {
                $sr->status = 'for_clarification';
                $actionText = 'Flagged for Office Clarification by '.$position;

                // Notify Requestor on Screen
                $this->sendAlert(
                    $sr->user_id,
                    '⚠️ Office Appearance Requested',
                    "Your request {$sr->tracking_code} requires clarification. Please proceed to the office of {$position} together with SMO. Note: {$request->remarks}",
                    'help-circle',
                    'warning',
                    'requisition.clarification_requested',
                    'requisition',
                    $sr->id,
                    "requisition:{$sr->id}:clarification:{$role}"
                );

                // Notify SMO In-Charge on Screen
                $smoUsers = User::where('role', 'smo')->where('account_status', User::ACCOUNT_ACTIVE)->get();
                foreach ($smoUsers as $smo) {
                    $this->sendAlert(
                        $smo->id,
                        "⚠️ Inquiry on Req {$sr->tracking_code}",
                        "{$position} requested a meeting regarding {$sr->tracking_code} ({$sr->user->name}). Please coordinate with their office.",
                        'users',
                        'warning',
                        'requisition.clarification_coordination',
                        'requisition',
                        $sr->id,
                        "requisition:{$sr->id}:clarification-smo:{$role}"
                    );
                }
            }
            // 3. APPROVED (Normal Hierarchy)
            else {
                if ($role === 'dept_head') {
                    $sr->status = 'approved_dept';
                } elseif ($role === 'vp_finance' || $role === 'vp_admin') {
                    $sr->status = 'approved_vp';
                } elseif ($role === 'provost') {
                    $sr->status = 'approved_provost';
                } elseif ($role === 'president') {
                    $sr->status = 'approved_president';
                }
                $actionText = 'Approved by '.$position;

                $this->sendAlert(
                    $sr->user_id,
                    'Requisition Endorsed',
                    "Your request {$sr->tracking_code} has been endorsed by {$position}.",
                    'check-circle',
                    'success',
                    'requisition.endorsed',
                    'requisition',
                    $sr->id,
                    "requisition:{$sr->id}:endorsed:{$role}"
                );
            }

            $sr->save();

            // 4. Log the Decision
            $log = ApprovalLog::create([
                'requisition_id' => $sr->id,
                'acted_by_user_id' => Auth::id(),
                'action' => $actionText,
                'role' => $role,
                'remarks' => $request->remarks,
            ]);

            if ($request->status === 'approved') {
                $nextRole = $sr->nextReviewerRole();

                if ($nextRole) {
                    $notifications->sendToRole($nextRole, 'requisition.awaiting_approval', 'Requisition Ready for Action',
                        "Requisition {$sr->tracking_code} is ready for your review.", [
                            'icon' => 'file-text',
                            'type' => $nextRole === 'smo' ? 'warning' : 'info',
                            'action_type' => 'approval_queue',
                            'action_id' => $sr->id,
                            'dedupe_key' => "requisition:{$sr->id}:log:{$log->id}:next:{$nextRole}",
                        ]);
                }
            }

            // 5. Generate an internal approval audit token.
            if ($request->status === 'approved') {
                $rawPayload = "REQ-{$sr->id}|USER-".Auth::id()."|ROLE-{$role}|TIME-".now()->timestamp.'|SECRET-'.config('app.key');
                $signatureToken = hash('sha256', $rawPayload);

                DigitalSignature::create([
                    'approval_log_id' => $log->id,
                    'signature_token' => $signatureToken,
                    'signer_name' => Auth::user()->name,
                    'signer_role' => $position,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'signed_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        // 6. TRIGGER OUTBOUND EMAIL NOTIFICATION FOR ALL 3 DECISIONS
        $accent = match ($request->status) {
            'approved' => '#185b3b', // HTC Green
            'clarification' => '#fd7e14', // Warning Amber
            default => '#dc3545'  // Danger Red
        };

        $headline = match ($request->status) {
            'approved' => "Requisition Endorsed by {$position}",
            'clarification' => "⚠️ Office Appearance Required: {$position}",
            default => "Requisition Disapproved by {$position}"
        };

        $emailMsg = match ($request->status) {
            'approved' => "Your requisition has been reviewed and endorsed by {$position}. It will now proceed to the next stage.",
            'clarification' => "The {$position} has requested a consultation regarding this request. Please report to their office together with the SMO In-Charge. Notes: ".($request->remarks ?? 'None'),
            default => "Your requisition was disapproved by {$position}. Reason: ".($request->remarks ?? 'Not specified')
        };

        $this->sendEmailNotification(
            $sr->user->email,
            $sr->user,
            $sr,
            $headline,
            $headline,
            $emailMsg,
            $accent
        );

        return $this->approvalQueueRedirect($request)->with('success', "Requisition #{$sr->id} status updated.");
    }

    /**
     * Confirm that the signed-in signatory owns the requisition's current stage.
     */
    private function canReviewRequisition(Requisition $requisition, User $user): bool
    {
        return $user->role !== 'smo' && $requisition->canBeReviewedBy($user);
    }

    /**
     * Move a fully approved requisition into active SMO processing.
     */
    public function startProcessing(Request $request, int $id, NotificationService $notifications)
    {
        if (Auth::user()->role !== 'smo') {
            return back()->with('error', 'Unauthorized. Only the SMO In-Charge can process supplies.');
        }

        $outcome = $this->fulfillmentTransaction(function () use ($id, $notifications) {
            $requisition = Requisition::with(['items', 'user.academicDepartment'])->lockForUpdate()->findOrFail($id);
            if (! $requisition->canStartProcessing()) {
                return ['error' => 'This requisition has not completed its required approval route.'];
            }

            $requisition->update(['status' => 'processing']);
            ApprovalLog::create([
                'requisition_id' => $requisition->id,
                'acted_by_user_id' => Auth::id(),
                'action' => 'Processing Started by SMO In-Charge',
                'role' => 'smo',
                'remarks' => 'The approved requisition entered SMO processing.',
            ]);

            $notifications->send($requisition->user, 'requisition.processing', 'SMO Processing Started',
                "Your requisition {$requisition->tracking_code} is now being processed by the SMO.", [
                    'icon' => 'package-open', 'type' => 'info', 'action_type' => 'requisition',
                    'action_id' => $requisition->id,
                    'dedupe_key' => "requisition:{$requisition->id}:processing",
                ]);

            return ['requisition' => $requisition];
        });

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        return $this->approvalQueueRedirect($request)->with('success', "Requisition #{$outcome['requisition']->id} is now being processed.");
    }

    /**
     * Deduct prepared stock and notify the requestor that pickup is available.
     */
    public function markReadyForPickup(Request $request, int $id, InventoryAlertService $alertService, FulfillmentInventoryService $inventory)
    {
        // Only SMO can release supplies.
        if (Auth::user()->role !== 'smo') {
            return back()->with('error', 'Unauthorized. Only the SMO In-Charge can release supplies.');
        }

        $outcome = $this->fulfillmentTransaction(function () use ($id, $alertService, $inventory) {
            $sr = Requisition::with(['items', 'user.academicDepartment'])->lockForUpdate()->findOrFail($id);

            if (in_array($sr->status, ['ready_for_pickup', 'completed', 'released'], true)) {
                return ['error' => 'This requisition has already been prepared for pickup.'];
            }

            if ($sr->status !== 'processing') {
                return ['error' => 'This requisition must enter SMO processing before it can be prepared for pickup.'];
            }

            $deductedItems = [];

            // Lock and aggregate every catalog item before changing stock. This
            // prevents concurrent releases and avoids partial deductions when
            // a later line is unavailable or duplicate cart lines share stock.
            $inventoryPlan = $inventory->plan($sr, lock: true);
            if ($inventoryPlan['errors']) {
                return ['error' => implode(' ', $inventoryPlan['errors'])];
            }

            foreach ($inventoryPlan['plans'] as $plan) {
                $inventoryItem = $plan['supply'];
                $requestedQuantity = $plan['quantity'];
                $wasOut = $inventoryItem->quantity <= 0;
                $wasLow = $inventoryItem->quantity <= $inventoryItem->min_stock_level;
                $inventoryItem->decrement('quantity', $requestedQuantity);
                $inventoryItem->refresh();
                $alertService->notifyIfThresholdCrossed($inventoryItem, $wasLow, $wasOut);

                SupplyInventoryLog::create([
                    'supply_id' => $inventoryItem->id,
                    'user_id' => Auth::id(),
                    'requisition_id' => $sr->id,
                    'transaction_type' => 'release_deduction',
                    'quantity_change' => -$requestedQuantity,
                    'balance_after' => $inventoryItem->quantity,
                    'remarks' => "Fulfillment of {$sr->tracking_code} for {$sr->user->name}",
                ]);

                $deductedItems[] = "{$inventoryItem->item_name} (-{$requestedQuantity})";
            }

            // Keep pickup readiness distinct from final completion.
            $sr->status = 'ready_for_pickup';
            $sr->save();

            // Create detailed audit log
            $logRemarks = count($deductedItems) > 0
                ? 'Prepared for pickup by SMO. Stock deducted: '.implode(', ', $deductedItems)
                : 'Prepared for pickup by SMO. Special requisition processed.';

            $log = ApprovalLog::create([
                'requisition_id' => $sr->id,
                'acted_by_user_id' => Auth::id(),
                'action' => 'Marked Ready for Pickup by SMO In-Charge',
                'role' => 'smo',
                'remarks' => $logRemarks,
            ]);

            // Generate an internal fulfillment audit token.
            $rawPayload = "READY-{$sr->id}|USER-".Auth::id().'|TIME-'.now()->timestamp.'|SECRET-'.config('app.key');
            $signatureToken = hash('sha256', $rawPayload);

            DigitalSignature::create([
                'approval_log_id' => $log->id,
                'signature_token' => $signatureToken,
                'signer_name' => Auth::user()->name,
                'signer_role' => 'SMO In-Charge',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'signed_at' => now(),
            ]);

            // Notify Requester
            $this->sendAlert(
                $sr->user_id,
                'Supplies Ready for Pickup',
                "Your requisition #{$sr->id} has been fulfilled by the SMO and is ready for pickup.",
                'package-check',
                'success',
                'requisition.ready_for_pickup',
                'requisition',
                $sr->id,
                "requisition:{$sr->id}:ready-for-pickup"
            );

            return ['requisition' => $sr];
        });

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        $sr = $outcome['requisition'];
        $this->sendEmailNotification(
            $sr->user->email,
            $sr->user,
            $sr,
            'Supplies Fulfilled and Ready for Pickup',
            'Your Supplies Are Ready!',
            "All requested items under {$sr->tracking_code} have been verified, deducted from inventory, and prepared by the SMO.",
            '#185b3b'
        );

        return $this->approvalQueueRedirect($request)->with('success', "Requisition #{$sr->id} is ready for pickup.");
    }

    /**
     * Close the requisition after the requestor receives the prepared items.
     */
    public function completeRequest(Request $request, int $id, NotificationService $notifications)
    {
        if (Auth::user()->role !== 'smo') {
            return back()->with('error', 'Unauthorized. Only the SMO In-Charge can complete requests.');
        }

        $outcome = $this->fulfillmentTransaction(function () use ($id, $notifications) {
            $requisition = Requisition::with('user')->lockForUpdate()->findOrFail($id);

            if ($requisition->status !== 'ready_for_pickup') {
                return ['error' => 'Only a requisition that is ready for pickup can be completed.'];
            }

            $requisition->update(['status' => 'completed']);
            ApprovalLog::create([
                'requisition_id' => $requisition->id,
                'acted_by_user_id' => Auth::id(),
                'action' => 'Completed by SMO In-Charge',
                'role' => 'smo',
                'remarks' => 'The prepared supplies were released to the requestor.',
            ]);

            $notifications->send($requisition->user, 'requisition.completed', 'Requisition Completed',
                "Your requisition {$requisition->tracking_code} has been released and marked complete.", [
                    'icon' => 'circle-check', 'type' => 'success', 'action_type' => 'requisition',
                    'action_id' => $requisition->id,
                    'dedupe_key' => "requisition:{$requisition->id}:completed",
                ]);

            return ['requisition' => $requisition];
        });

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        return $this->approvalQueueRedirect($request)->with('success', "Requisition #{$outcome['requisition']->id} has been completed.");
    }

    public function show(Request $httpRequest, int $id)
    {
        $request = Requisition::with(['user', 'items', 'logs.digitalSignature', 'logs.actor', 'latestClosureLog.actor'])->findOrFail($id);
        abort_unless($request->canBeViewedBy($httpRequest->user()), 403);
        $archiveReturnUrl = app(ArchiveService::class)->returnUrl($httpRequest);
        $activeReturnUrl = null;
        if ($httpRequest->boolean('from_list') && is_array($httpRequest->query('list_filters', []))) {
            $filters = Validator::make($httpRequest->query('list_filters', []), ActiveRequestIndexRequest::filterRules($httpRequest->user()));
            if ($filters->passes()) {
                $activeReturnUrl = route('requests.index', $filters->validated()).'#request-results';
            }
        }

        return view('requests.show', compact('request', 'archiveReturnUrl', 'activeReturnUrl'));
    }

    public function downloadPDF(Request $httpRequest, int $id)
    {
        $request = Requisition::with(['user', 'items', 'logs.digitalSignature'])->findOrFail($id);
        abort_unless($request->canBeViewedBy($httpRequest->user()), 403);

        $pdf = Pdf::loadView('requests.pdf', compact('request'))->setPaper('a4', 'portrait');

        return $pdf->download('HTC-Requisition-'.$request->tracking_code.'.pdf');
    }

    private function sendAlert(
        int $userId,
        string $title,
        string $message,
        string $icon = 'bell',
        string $type = 'info',
        string $eventType = 'system.notice',
        ?string $actionType = null,
        ?int $actionId = null,
        ?string $dedupeKey = null,
    ): void {
        app(NotificationService::class)->send($userId, $eventType, $title, $message, [
            'icon' => $icon,
            'type' => $type,
            'action_type' => $actionType,
            'action_id' => $actionId,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /**
     * Send Outbound Email and Record in email_logs Table (Thesis Notification Engine)
     */
    private function sendEmailNotification(
        string $recipientEmail,
        ?User $user,
        Requisition $requisition,
        string $subject,
        string $headline,
        string $message,
        string $accentColor = '#185b3b',
    ): void {
        try {
            SendRequisitionEmail::dispatch(
                $recipientEmail, $user?->id, $requisition->id,
                $subject, $headline, $message, $accentColor,
            );
        } catch (\Exception $e) {
            Log::error('Could not queue requisition email.', [
                'requisition_id' => $requisition->id, 'exception' => $e->getMessage(),
            ]);
        }
    }
}
