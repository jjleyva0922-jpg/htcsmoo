<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Services\InventoryAlertService;
use App\Services\InventoryForecastService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplyController extends Controller
{
    public function requisitionCatalog()
    {
        $supplies = Supply::query()
            ->with(['supplyCategory', 'supplyUnit', 'options.values'])
            ->select([
                'id', 'supply_category_id', 'supply_unit_id', 'item_name', 'requisition_tier',
                'model_number', 'specifications', 'unit_price', 'quantity',
            ])
            ->where('quantity', '>', 0)
            ->orderBy('requisition_tier')
            ->orderBy('item_name')
            ->get();

        return response()->json($supplies->map(fn (Supply $supply) => [
            'id' => $supply->id,
            'name' => $supply->item_name,
            'brand' => $supply->brand,
            'category' => $supply->category,
            'model' => $supply->model_number,
            'tier' => $supply->requisition_tier ?? 'minor',
            'specs' => $supply->specifications,
            'unit' => $supply->unit,
            'price' => (float) $supply->unit_price,
            'stock' => $supply->quantity,
            'options' => $supply->requestOptions(),
        ])->values());
    }

    // List all inventory items
    public function index(Request $request, InventoryForecastService $forecastService)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', 'in:all,attention,out,low,healthy'],
            'tier' => ['nullable', 'in:minor,major'],
            'forecast_period' => ['nullable', 'in:monthly,quarterly'],
        ]);
        $period = in_array($request->query('forecast_period'), ['monthly', 'quarterly'], true)
            ? $request->query('forecast_period')
            : 'monthly';

        // Operational priority: out of stock, low stock, then healthy stock.
        $supplies = $this->orderedSupplies();

        $forecast = $forecastService->build($supplies, period: $period);
        $search = mb_strtolower(trim($filters['search'] ?? ''));
        $stock = $filters['stock'] ?? 'all';
        $catalogSupplies = $supplies->filter(function (Supply $supply) use ($search, $stock) {
            $text = mb_strtolower(implode(' ', [$supply->item_name, $supply->brand, $supply->model_number, $supply->category, $supply->specifications]));
            $state = $supply->quantity <= 0 ? 'out' : ($supply->quantity <= $supply->min_stock_level ? 'low' : 'healthy');

            return ($search === '' || str_contains($text, $search))
                && ($stock === 'all' || $stock === $state || ($stock === 'attention' && in_array($state, ['out', 'low'], true)));
        })->values();

        return view('inventory.index', compact('supplies', 'forecast', 'catalogSupplies'));
    }

    /**
     * Generate the SMO purchasing forecast as a printable PDF report.
     */
    public function forecastReport(Request $request, InventoryForecastService $forecastService)
    {
        $validated = $request->validate([
            'period' => ['required', 'in:monthly,quarterly'],
        ]);
        $forecast = $forecastService->build($this->orderedSupplies(), period: $validated['period']);

        $pdf = Pdf::loadView('inventory.forecast-report', [
            'forecast' => $forecast,
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download(
            'HTC_Inventory_Forecast_'.ucfirst($validated['period']).'_'.now()->format('Ymd_His').'.pdf'
        );
    }

    // Show the form to add a new item
    public function create()
    {
        return view('inventory.create');
    }

    // Save a new item to the database
    public function store(Request $request, InventoryAlertService $alertService)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255|unique:supplies,item_name',
            'category' => 'required|in:Cleaning Supplies,Construction Supplies,Drugs and Medicines,Maintenance Supplies,Medical Supplies,Non-Medical Supplies,Office Supplies',
            'requisition_tier' => 'required|in:minor,major',
            'specifications' => 'required|string|min:3',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0.01',
            'min_stock_level' => 'required|integer|min:0',
            'model_number' => 'nullable|string|max:100',
            'option_names' => 'nullable|array|max:5',
            'option_names.*' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _-]+$/'],
            'option_values' => 'nullable|array|max:5',
            'option_values.*' => 'nullable|string|max:500',
        ]);

        $validated['configurable_options'] = $this->normalizeOptions($validated);
        $validated['brand'] = $this->brandSummary($validated['configurable_options']);
        unset($validated['option_names'], $validated['option_values']);

        $validated['supply_category_id'] = SupplyCategory::where('category_name', $validated['category'])->value('id');
        unset($validated['category']);

        $supply = Supply::create($validated);
        $alertService->notifyIfThresholdCrossed($supply, false, false);

        return redirect()->route('inventory.index')->with('success', "Item '{$validated['item_name']}' has been registered into inventory.");
    }

    /**
     * NEW: Show the form to edit an existing item
     */
    public function edit(int $id)
    {
        $item = Supply::findOrFail($id);

        return view('inventory.edit', compact('item'));
    }

    /**
     * Save changes to an existing item
     */
    public function update(Request $request, int $id, InventoryAlertService $alertService)
    {
        $item = Supply::findOrFail($id);
        $wasOut = $item->quantity <= 0;
        $wasLow = $item->quantity <= $item->min_stock_level;

        $validated = $request->validate([
            'item_name' => 'required|string|max:255|unique:supplies,item_name,'.$id,
            'category' => 'required|in:Cleaning Supplies,Construction Supplies,Drugs and Medicines,Maintenance Supplies,Medical Supplies,Non-Medical Supplies,Office Supplies',
            'requisition_tier' => 'required|in:minor,major',
            'specifications' => 'required|string|min:3',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0.01',
            'min_stock_level' => 'required|integer|min:0',
            'model_number' => 'nullable|string|max:100',
            'option_names' => 'nullable|array|max:5',
            'option_names.*' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _-]+$/'],
            'option_values' => 'nullable|array|max:5',
            'option_values.*' => 'nullable|string|max:500',
        ]);

        $validated['configurable_options'] = $this->normalizeOptions($validated);
        $validated['brand'] = $this->brandSummary($validated['configurable_options']);
        unset($validated['option_names'], $validated['option_values']);

        $validated['supply_category_id'] = SupplyCategory::where('category_name', $validated['category'])->value('id');
        unset($validated['category']);

        $item->update($validated);
        $alertService->notifyIfThresholdCrossed($item->fresh(), $wasLow, $wasOut);

        return redirect()->route('inventory.index')->with('success', "Record for '{$item->item_name}' has been updated.");
    }

    /**
     * NEW: Remove an item from inventory
     */
    public function destroy(int $id)
    {
        $item = Supply::findOrFail($id);
        $item->delete();

        return redirect()->route('inventory.index')->with('success', 'Item has been removed from inventory.');
    }

    /**
     * Remove multiple selected catalog items in one inventory action.
     */
    public function destroyMany(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:supplies,id'],
        ]);

        $deleted = Supply::destroy($validated['ids']);

        return redirect()
            ->route('inventory.index')
            ->with('success', $deleted.' inventory '.($deleted === 1 ? 'item has' : 'items have').' been removed.');
    }

    private function normalizeOptions(array $validated): array
    {
        $groups = [];
        $names = $validated['option_names'] ?? [];
        $values = $validated['option_values'] ?? [];

        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if (strcasecmp($name, 'brand') === 0) {
                $name = 'Brand';
            }
            $choices = collect(explode(',', (string) ($values[$index] ?? '')))
                ->map(fn ($choice) => trim($choice))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($name !== '' && $choices !== []) {
                $groups[$name] = $choices;
            }
        }

        if (empty($groups['Brand'])) {
            throw ValidationException::withMessages([
                'option_values' => 'Add at least one Brand under Requestor Choices.',
            ]);
        }

        $brand = ['Brand' => $groups['Brand']];
        unset($groups['Brand']);

        return $brand + $groups;
    }

    private function brandSummary(array $options): string
    {
        $summary = collect($options['Brand'] ?? [])->implode(', ');

        if (mb_strlen($summary) > 255) {
            throw ValidationException::withMessages([
                'option_values' => 'The combined Brand choices must not exceed 255 characters.',
            ]);
        }

        return $summary;
    }

    private function orderedSupplies()
    {
        return Supply::with(['supplyCategory', 'supplyUnit', 'options.values'])
            ->orderByRaw('CASE WHEN quantity <= 0 THEN 0 WHEN quantity <= min_stock_level THEN 1 ELSE 2 END')
            ->orderBy('item_name')
            ->get();
    }
}
