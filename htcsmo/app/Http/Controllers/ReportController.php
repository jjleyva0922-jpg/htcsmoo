<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\OperationalAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    private const EXPORT_CHUNK_SIZE = 200;

    public const PDF_ROW_LIMIT = 500;

    private const REPORT_TYPES = [
        'requisitions',
        'inventory',
        'dept_spending',
        'users',
        'item_consumption',
        'approval_audit',
        'inventory_movements',
        'user_activity',
    ];

    /**
     * Display the operational analytics dashboard.
     */
    public function index(Request $request, OperationalAnalyticsService $analytics)
    {
        $request->validate([
            'period' => ['nullable', 'in:current_semester,this_month,this_year,custom'],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        [$dateFrom, $dateTo, $periodLabel] = $this->resolvePeriod($request);
        if ($dateFrom->diffInMonths($dateTo) > 120) {
            throw ValidationException::withMessages([
                'date_to' => 'Choose a reporting range of ten years or less.',
            ]);
        }
        $periodQuery = ['period' => $request->input('period') ?: 'current_semester'];
        if ($periodQuery['period'] === 'custom') {
            $periodQuery += $request->only('date_from', 'date_to');
        }

        return view('admin.reports', [
            ...$analytics->build($dateFrom, $dateTo),
            'periodLabel' => $periodLabel,
            'periodQuery' => $periodQuery,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * Display the report builder and handle its preview/export requests.
     */
    public function builder(Request $request)
    {
        $request->validate([
            'report_type' => ['nullable', 'in:'.implode(',', self::REPORT_TYPES)],
            'period' => ['nullable', 'in:current_semester,this_month,this_year,all,custom'],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'account_status' => ['nullable', 'in:pending,active,suspended'],
            'date_basis' => ['nullable', 'in:submitted,fulfilled'],
            'supply_id' => ['nullable', 'integer', 'min:1'],
            'dept' => ['nullable', 'string', 'max:100'],
            'tier' => ['nullable', 'in:minor,major'],
            'status' => ['nullable', 'in:pending,pending_dept,approved_dept,approved_vp,approved_provost,approved_president,for_clarification,processing,ready_for_pickup,completed,released,rejected,withdrawn'],
            'role' => ['nullable', 'in:'.implode(',', User::ROLES)],
            'category' => ['nullable', 'string', 'max:100'],
            'stock_filter' => ['nullable', 'in:low,out'],
            'transaction_type' => ['nullable', 'in:release_deduction,restock_delivery,manual_adjustment'],
            'page' => ['nullable', 'integer', 'min:1'],
            'export' => ['nullable', 'in:csv'],
            'download_pdf' => ['nullable', 'boolean'],
        ]);

        $report = $this->buildReport($request);

        if ($request->get('export') === 'csv') {
            return $this->downloadCsv($report);
        }

        if ($request->boolean('download_pdf')) {
            // Check again after hydration in case records changed since preflight.
            if ($report['rows']->count() > self::PDF_ROW_LIMIT) {
                $this->rejectOversizedPdf($request);
            }

            $pdf = Pdf::loadView('admin.reports.pdf_template', [
                ...$report,
                ...$this->pdfContext($report, $request),
                'generatedBy' => Auth::user()->name,
                'generatedDate' => now()->format('F d, Y - h:i A'),
            ])->setPaper('a4', 'landscape');

            return $pdf->download('HTC_SMO_'.$report['reportType'].'_'.date('Ymd_His').'.pdf');
        }

        return view('admin.reports.builder', $report);
    }

    /**
     * Preserve the old report-generation endpoint.
     */
    public function generate(Request $request)
    {
        return $this->builder($request);
    }

    /**
     * Build a normalized tabular report for previews and exports.
     *
     * @return array<string, mixed>
     */
    private function buildReport(Request $request): array
    {
        $reportType = $request->get('report_type', 'requisitions');
        [$dateFrom, $dateTo, $periodLabel] = $this->resolvePeriod($request);

        $report = match ($reportType) {
            'inventory' => $this->inventoryReport($request, $dateFrom, $dateTo),
            'dept_spending' => $this->departmentSpendingReport($request, $dateFrom, $dateTo),
            'users' => $this->userReport($request, $dateFrom, $dateTo),
            'item_consumption' => $this->itemConsumptionReport($request, $dateFrom, $dateTo),
            'approval_audit' => $this->approvalAuditReport($request, $dateFrom, $dateTo),
            'inventory_movements' => $this->inventoryMovementReport($request, $dateFrom, $dateTo),
            'user_activity' => $this->userActivityReport($request, $dateFrom, $dateTo),
            default => $this->requisitionReport($request, $dateFrom, $dateTo),
        };

        if ($reportType === 'inventory') {
            $periodLabel = 'Current Inventory Snapshot';
        }

        return [
            'reportType' => $reportType,
            'pdfRowLimit' => self::PDF_ROW_LIMIT,
            'periodLabel' => $periodLabel,
            'departmentOptions' => Department::orderBy('dept_code')->pluck('dept_code'),
            'basisLabel' => match ($reportType) {
                'requisitions' => $request->input('date_basis') === 'fulfilled'
                    ? 'Period: completion date (closed_at); completed and legacy released requests only. Values are recorded request amounts, not audited expenditure.'
                    : 'Period: submission date (submitted_at, falling back to creation date). Quantities and values are requested amounts, regardless of fulfillment.',
                'dept_spending' => 'Period: completion date (closed_at); completed and legacy released requests only. Recorded request value, not audited accounting expenditure.',
                'item_consumption' => 'Period: completion date (closed_at). Requested quantities and prices on fulfilled requests; these are not actual stock deductions. Use Inventory Movements for the release ledger.',
                'inventory' => 'Current snapshot: estimated on-hand value = current quantity × current catalog price. This is not historical movement value or audited expenditure.',
                'inventory_movements' => 'Period: ledger transaction date. Signed quantity changes record additions and deductions; release deductions may precede request completion.',
                default => null,
            },
            ...$report,
            'pagination' => $request->attributes->get('report_pagination'),
            'summaryLabel' => $request->attributes->has('report_pagination')
                ? 'This page: '.$report['summaryLabel'] : $report['summaryLabel'],
        ];
    }

    private function records(Request $request, Builder|QueryBuilder $query): Collection|LazyCollection
    {
        if ($request->get('export') === 'csv') {
            // Unlike cursor(), lazy() also bounds PDO's buffered results and
            // eager-loads relationships separately for each chunk.
            return $query->lazy(self::EXPORT_CHUNK_SIZE);
        }

        if ($request->boolean('download_pdf')) {
            // Dompdf builds the entire document in memory. Count up to the
            // supported limit before loading models and their relationships.
            $probe = $query instanceof Builder ? (clone $query)->toBase() : clone $query;
            if (($request->input('report_type') ?: 'requisitions') === 'requisitions') {
                // A request without items still prints one row; other requests
                // print one row per item, rather than one row per parent.
                $probe = DB::query()->fromSub($probe->reorder(), 'pdf_requests')
                    ->leftJoin('requisition_items as pdf_items', 'pdf_items.requisition_id', '=', 'pdf_requests.id')
                    ->select('pdf_requests.id');
            }
            if ($probe->limit(self::PDF_ROW_LIMIT + 1)->get()->count() > self::PDF_ROW_LIMIT) {
                $this->rejectOversizedPdf($request);
            }

            return $query->limit(self::PDF_ROW_LIMIT + 1)->get();
        }

        $pagination = $query->paginate(50)->withQueryString();
        $request->attributes->set('report_pagination', $pagination);

        return $pagination->getCollection();
    }

    private function rejectOversizedPdf(Request $request): never
    {
        $message = 'PDF reports support up to '.self::PDF_ROW_LIMIT.' rows (including requisition line items). Narrow the filters or export CSV for all matching records.';

        if ($request->ajax() || $request->expectsJson()) {
            throw new HttpResponseException(response()->json(['message' => $message], 422));
        }

        // Keep the selected filters on the server-rendered fallback as well.
        throw new HttpResponseException(redirect()->route('admin.reports.builder', $request->except('download_pdf', 'export'))
            ->withErrors(['download_pdf' => $message]));
    }

    private function requisitionReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $isCsv = $request->get('export') === 'csv';
        $query = Requisition::with($isCsv
            ? ['user.academicDepartment']
            : ['user.academicDepartment', 'items.supply.supplyCategory', 'items.supply.supplyUnit', 'items.supply.options.values', 'logs']);

        if ($request->filled('dept')) {
            $request->dept === '__unassigned'
                ? $query->whereHas('user', fn (Builder $users) => $users->whereNull('department_id'))
                : $query->whereHas('user', fn (Builder $userQuery) => $userQuery->inDepartment($request->dept));
        }
        if ($request->filled('tier')) {
            $query->where('request_type', $request->tier);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $isFulfilled = $request->input('date_basis') === 'fulfilled';
        if ($isFulfilled) {
            $query->whereIn('status', Requisition::FULFILLED_STATUSES);
        }
        $dateColumn = $isFulfilled ? 'closed_at' : DB::raw('COALESCE(submitted_at, created_at)');
        $this->applyDateRange($query, $dateColumn, $dateFrom, $dateTo);

        $records = $this->records($request, $query->latest('created_at')->orderByDesc('id'));
        // One row per line item prevents specifications, quantities, and prices
        // from being hidden inside a comma-separated requisition summary.
        $rows = $records->flatMap(function (Requisition $requisition) use ($isCsv) {
            $items = $isCsv
                ? new LazyCollection(function () use ($requisition) {
                    $hasItems = false;
                    foreach ($requisition->items()->with('supply.supplyCategory')->orderBy('id')->lazy(self::EXPORT_CHUNK_SIZE) as $item) {
                        $hasItems = true;
                        yield $item;
                    }
                    if (! $hasItems) {
                        yield null;
                    }
                })
                : ($requisition->items->isEmpty() ? collect([null]) : $requisition->items);

            $formatTimestamp = static fn ($timestamp) => $timestamp
                ? Carbon::parse($timestamp)->format('M d, Y h:i A')
                : '-';
            // Workflow timestamps are PDF-only; CSV must not load all logs.
            $processingLog = $isCsv ? null : $requisition->logs->firstWhere('action', 'Processing Started by SMO In-Charge');
            $readyLog = $isCsv ? null : $requisition->logs->firstWhere('action', 'Marked Ready for Pickup by SMO In-Charge');

            return $items->map(fn ($item) => [
                'tracking' => $requisition->tracking_code,
                'submitted' => ($requisition->submitted_at ?? $requisition->created_at)->format('Y-m-d H:i'),
                'fulfilled_date' => $requisition->closed_at?->format('Y-m-d H:i') ?? '-',
                'request_date' => $requisition->created_at->format('M d, Y'),
                'submitted_at' => $formatTimestamp($requisition->submitted_at ?? $requisition->created_at),
                'sla_warning_at' => $formatTimestamp($requisition->sla_warning_at),
                'deadline_at' => $formatTimestamp($requisition->due_at),
                'stage_started_at' => $formatTimestamp($requisition->stage_started_at),
                'processing_at' => $formatTimestamp($processingLog?->created_at),
                'ready_at' => $formatTimestamp($readyLog?->created_at),
                'closed_at' => $formatTimestamp($requisition->closed_at),
                'updated_at' => $formatTimestamp($requisition->updated_at),
                'requestor' => $requisition->user?->name ?? 'Unknown',
                'department' => $requisition->user?->department ?? 'Unassigned',
                'tier' => strtoupper($requisition->request_type),
                'purpose' => $requisition->purpose ?: 'Not specified',
                'request_total' => (float) $requisition->grand_total,
                'category' => $item?->supply?->category ?: 'Uncategorized / Manual Entry',
                'item' => $item?->item_name ?? 'No line item',
                'specifications' => $item?->specifications ?: 'Not specified',
                'quantity' => (int) ($item?->quantity ?? 0),
                'unit' => $item?->unit ?: '-',
                'unit_price' => (float) ($item?->unit_price ?? 0),
                'line_total' => (float) ($item?->subtotal ?? 0),
                'status' => ucwords(str_replace('_', ' ', $requisition->status)),
            ]);
        })->values();

        return $this->reportResult(
            'Requisition Transactions',
            $rows,
            [
                $this->column('tracking', 'Tracking No.'),
                $this->column('submitted', 'Submitted'),
                ...($isFulfilled ? [$this->column('fulfilled_date', 'Completed / Released At')] : []),
                $this->column('requestor', 'Requestor'),
                $this->column('department', 'Department'),
                $this->column('tier', 'Tier'),
                $this->column('purpose', 'Purpose'),
                $this->column('item', 'Item'),
                $this->column('specifications', 'Specifications'),
                $this->column('quantity', 'Requested Qty', 'number'),
                $this->column('unit', 'Unit'),
                $this->column('unit_price', 'Unit Price', 'money'),
                $this->column('line_total', 'Line Total', 'money'),
                $this->column('status', 'Status'),
            ],
            $isFulfilled ? 'Recorded Fulfilled Value' : 'Total Requested Value',
            fn () => $records->sum('grand_total'),
            'money',
        );
    }

    private function inventoryReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = Supply::query()->with(['supplyCategory', 'supplyUnit', 'options.values']);

        if ($request->filled('category')) {
            $query->inCategory($request->category);
        }
        if ($request->get('stock_filter') === 'low') {
            $query->whereRaw('quantity <= min_stock_level AND quantity > 0');
        } elseif ($request->get('stock_filter') === 'out') {
            $query->where('quantity', '<=', 0);
        }

        $records = $this->records($request, $query->orderBy(SupplyCategory::select('category_name')
            ->whereColumn('supply_categories.id', 'supplies.supply_category_id'))
            ->orderBy('item_name')->orderBy('id'));
        $rows = $records->map(fn (Supply $supply) => [
            'item' => $supply->item_name,
            'brand' => $supply->brand ?: '-',
            'model' => $supply->model_number ?: '-',
            'category' => $supply->category,
            'tier' => strtoupper($supply->requisition_tier ?: 'minor'),
            'specifications' => $supply->specifications ?: 'Not specified',
            'stock' => $supply->quantity,
            'unit' => $supply->unit,
            'minimum' => $supply->min_stock_level,
            'unit_price' => (float) $supply->unit_price,
            'asset_value' => (float) ($supply->quantity * $supply->unit_price),
            'health' => $supply->quantity <= 0 ? 'Out of stock' : ($supply->quantity <= $supply->min_stock_level ? 'Low stock' : 'Healthy'),
        ]);

        return $this->reportResult(
            'Inventory and Estimated On-hand Value',
            $rows,
            [
                $this->column('item', 'Item'),
                $this->column('brand', 'Brand'),
                $this->column('model', 'Model No.'),
                $this->column('category', 'Category'),
                $this->column('tier', 'Tier'),
                $this->column('specifications', 'Specifications'),
                $this->column('stock', 'Stock', 'number'),
                $this->column('unit', 'Unit'),
                $this->column('minimum', 'Minimum', 'number'),
                $this->column('unit_price', 'Unit Price', 'money'),
                $this->column('asset_value', 'Estimated On-hand Value', 'money'),
                $this->column('health', 'Stock Health'),
            ],
            'Estimated On-hand Value',
            fn () => $rows->sum('asset_value'),
            'money',
        );
    }

    private function departmentSpendingReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = Requisition::leftJoin('users', 'requisitions.user_id', '=', 'users.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->select(
                DB::raw('departments.dept_code as department'),
                DB::raw('COUNT(requisitions.id) as total_requests'),
                DB::raw('COUNT(DISTINCT requisitions.user_id) as active_requestors'),
                DB::raw('SUM(requisitions.grand_total) as total_spent'),
                DB::raw('AVG(requisitions.grand_total) as avg_cost'),
            )
            ->whereIn('requisitions.status', Requisition::FULFILLED_STATUSES);

        if ($request->filled('dept')) {
            $request->dept === '__unassigned'
                ? $query->whereNull('departments.id')
                : $query->where('departments.dept_code', $request->dept);
        }

        $this->applyDateRange($query, 'requisitions.closed_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query->groupBy('departments.dept_code')->orderByDesc('total_spent')->orderBy('departments.dept_code'));
        $rows = $records->map(fn ($department) => [
            'department' => $department->department ?: 'Unassigned',
            'requests' => (int) $department->total_requests,
            'requestors' => (int) $department->active_requestors,
            'average' => (float) $department->avg_cost,
            'total' => (float) $department->total_spent,
        ]);

        return $this->reportResult(
            'Department Fulfillment Value',
            $rows,
            [
                $this->column('department', 'Department'),
                $this->column('requests', 'Fulfilled Requests (Completion Date)', 'number'),
                $this->column('requestors', 'Active Requestors', 'number'),
                $this->column('average', 'Average Recorded Value', 'money'),
                $this->column('total', 'Recorded Fulfilled Value', 'money'),
            ],
            'Recorded Fulfilled Value',
            fn () => $rows->sum('total'),
            'money',
        );
    }

    private function userReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = User::query()->with('academicDepartment');

        if ($request->filled('dept')) {
            $query->inDepartment($request->dept);
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('account_status')) {
            $query->where('account_status', $request->account_status);
        }

        $this->applyDateRange($query, 'created_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query->orderBy('department_id')->orderBy('name')->orderBy('id'));
        $rows = $records->map(fn (User $user) => [
            'school_id' => $user->school_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'department' => $user->department ?: 'Administrative Office',
            'role' => strtoupper(str_replace('_', ' ', $user->role)),
            'status' => ucfirst($user->account_status),
            'registered' => $user->created_at->format('Y-m-d'),
        ]);

        return $this->reportResult(
            'User Accounts and Personnel',
            $rows,
            [
                $this->column('school_id', 'Institution ID'),
                $this->column('name', 'Name'),
                $this->column('username', 'Username'),
                $this->column('email', 'Email'),
                $this->column('department', 'Department'),
                $this->column('role', 'Role'),
                $this->column('status', 'Account Status'),
                $this->column('registered', 'Registered'),
            ],
            'Matching User Accounts',
            fn () => $rows->count(),
            'number',
        );
    }

    private function itemConsumptionReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = RequisitionItem::join('requisitions', 'requisition_items.requisition_id', '=', 'requisitions.id')
            ->join('users', 'requisitions.user_id', '=', 'users.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('supplies', 'requisition_items.supply_id', '=', 'supplies.id')
            ->leftJoin('supply_categories', 'supplies.supply_category_id', '=', 'supply_categories.id')
            ->select(
                'requisition_items.item_name',
                'requisition_items.unit',
                DB::raw("COALESCE(supply_categories.category_name, 'Uncategorized') as category"),
                DB::raw('COUNT(DISTINCT requisitions.id) as request_count'),
                DB::raw('SUM(requisition_items.quantity) as total_quantity'),
                DB::raw('SUM(requisition_items.subtotal) as total_cost'),
                DB::raw('AVG(requisition_items.unit_price) as average_unit_price'),
            )
            ->whereIn('requisitions.status', Requisition::FULFILLED_STATUSES);

        if ($request->filled('dept')) {
            $request->dept === '__unassigned'
                ? $query->whereNull('departments.id')
                : $query->where('departments.dept_code', $request->dept);
        }

        $this->applyDateRange($query, 'requisitions.closed_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query
            ->groupBy('requisition_items.item_name', 'requisition_items.unit', 'supply_categories.category_name')
            ->orderByDesc('total_quantity')
            ->orderBy('requisition_items.item_name')->orderBy('requisition_items.unit')->orderBy('supply_categories.category_name')
        );

        $rows = $records->map(fn ($item) => [
            'item' => $item->item_name,
            'category' => $item->category,
            'requests' => (int) $item->request_count,
            'quantity' => (int) $item->total_quantity,
            'unit' => $item->unit,
            'average_price' => (float) $item->average_unit_price,
            'cost' => (float) $item->total_cost,
        ]);

        return $this->reportResult(
            'Fulfilled Request Items',
            $rows,
            [
                $this->column('item', 'Item'),
                $this->column('category', 'Category'),
                $this->column('requests', 'Requisitions', 'number'),
                $this->column('quantity', 'Requested Qty on Fulfilled Requests', 'number'),
                $this->column('unit', 'Unit'),
                $this->column('average_price', 'Average Unit Price', 'money'),
                $this->column('cost', 'Recorded Line Value', 'money'),
            ],
            'Recorded Fulfilled Line Value',
            fn () => $rows->sum('cost'),
            'money',
        );
    }

    private function approvalAuditReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = ApprovalLog::with(['requisition.user.academicDepartment', 'digitalSignature']);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('dept')) {
            $query->whereHas('requisition.user', fn (Builder $userQuery) => $userQuery->inDepartment($request->dept));
        }

        $this->applyDateRange($query, 'created_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query->latest()->orderByDesc('id'));
        $rows = $records->map(fn (ApprovalLog $log) => [
            'date' => $log->created_at->format('Y-m-d H:i'),
            'tracking' => $log->requisition?->tracking_code ?? '-',
            'requestor' => $log->requisition?->user?->name ?? 'Unknown',
            'department' => $log->requisition?->user?->department ?? 'Unassigned',
            'role' => strtoupper(str_replace('_', ' ', $log->role)),
            'action' => $log->action,
            'remarks' => $log->remarks ?: '-',
            'signer' => $log->digitalSignature?->signer_name ?? 'Not digitally signed',
            'signed_at' => $log->digitalSignature?->signed_at
                ? Carbon::parse($log->digitalSignature->signed_at)->format('Y-m-d H:i')
                : '-',
            'verification' => $log->digitalSignature?->signature_token
                ? strtoupper(substr($log->digitalSignature->signature_token, 0, 12))
                : '-',
        ]);

        return $this->reportResult(
            'Approval and Signatory Audit Trail',
            $rows,
            [
                $this->column('date', 'Action Date'),
                $this->column('tracking', 'Tracking No.'),
                $this->column('requestor', 'Requestor'),
                $this->column('department', 'Department'),
                $this->column('role', 'Acting Role'),
                $this->column('action', 'Action'),
                $this->column('remarks', 'Remarks'),
                $this->column('signer', 'Digital Signer'),
                $this->column('signed_at', 'Signed At'),
                $this->column('verification', 'Verification Ref.'),
            ],
            'Recorded Approval Actions',
            fn () => $rows->count(),
            'number',
        );
    }

    private function inventoryMovementReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = SupplyInventoryLog::with(['supply', 'user', 'requisition']);

        if ($request->filled('supply_id')) {
            $query->where('supply_id', $request->integer('supply_id'));
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->filled('category')) {
            $query->whereHas('supply', fn (Builder $supplyQuery) => $supplyQuery->inCategory($request->category));
        }

        $this->applyDateRange($query, 'created_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query->latest()->orderByDesc('id'));
        $rows = $records->map(fn (SupplyInventoryLog $log) => [
            'date' => $log->created_at->format('Y-m-d H:i'),
            'item' => $log->supply?->item_name ?? 'Deleted item',
            'type' => strtoupper(str_replace('_', ' ', $log->transaction_type)),
            'change' => (int) $log->quantity_change,
            'balance' => (int) $log->balance_after,
            'officer' => $log->user?->name ?? 'Unknown',
            'tracking' => $log->requisition?->tracking_code ?? '-',
            'remarks' => $log->remarks ?: '-',
        ]);

        return $this->reportResult(
            'Inventory Movement Ledger',
            $rows,
            [
                $this->column('date', 'Transaction Date'),
                $this->column('item', 'Supply Item'),
                $this->column('type', 'Transaction Type'),
                $this->column('change', 'Quantity Change', 'number'),
                $this->column('balance', 'Balance After', 'number'),
                $this->column('officer', 'SMO Officer'),
                $this->column('tracking', 'Related Requisition'),
                $this->column('remarks', 'Remarks'),
            ],
            'Inventory Transactions',
            fn () => $rows->count(),
            'number',
        );
    }

    private function userActivityReport(Request $request, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = UserActivityLog::with('user.academicDepartment');

        if ($request->filled('role')) {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('role', $request->role));
        }
        if ($request->filled('dept')) {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->inDepartment($request->dept));
        }

        $this->applyDateRange($query, 'created_at', $dateFrom, $dateTo);

        $records = $this->records($request, $query->latest('created_at')->orderByDesc('id'));
        $rows = $records->map(fn (UserActivityLog $log) => [
            'date' => Carbon::parse($log->created_at)->format('Y-m-d H:i'),
            'user' => $log->user?->name ?? 'Deleted user',
            'school_id' => $log->user?->school_id ?? '-',
            'department' => $log->user?->department ?? 'Unassigned',
            'role' => strtoupper(str_replace('_', ' ', $log->user?->role ?? 'unknown')),
            'action' => $log->action,
            'ip' => $log->ip_address,
            'device' => $log->user_agent ?: '-',
        ]);

        return $this->reportResult(
            'User Activity and Security Log',
            $rows,
            [
                $this->column('date', 'Activity Date'),
                $this->column('user', 'User'),
                $this->column('school_id', 'Institution ID'),
                $this->column('department', 'Department'),
                $this->column('role', 'Role'),
                $this->column('action', 'Activity'),
                $this->column('ip', 'IP Address'),
                $this->column('device', 'Browser / Device'),
            ],
            'Recorded User Activities',
            fn () => $rows->count(),
            'number',
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>|LazyCollection<int, array<string, mixed>>  $rows
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @return array<string, mixed>
     */
    private function reportResult(
        string $title,
        Collection|LazyCollection $rows,
        array $columns,
        string $summaryLabel,
        Closure $summary,
        string $summaryType,
    ): array {
        // CSV only contains headers and data, so do not traverse the lazy
        // export to calculate a summary that will never be written.
        $summaryValue = $rows instanceof LazyCollection ? null : $summary();

        return compact('title', 'rows', 'columns', 'summaryLabel', 'summaryValue', 'summaryType');
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    private function column(string $key, string $label, string $type = 'text'): array
    {
        return compact('key', 'label', 'type');
    }

    /**
     * Add the document-control, categorization, and interpretation data used by
     * the PDF. Keeping this separate from the tabular result also keeps CSV
     * exports clean and machine-readable.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function pdfContext(array $report, Request $request): array
    {
        $type = $report['reportType'];
        $rows = $report['rows'];

        $definitions = [
            'requisitions' => [
                'purpose' => 'Provides a line-by-line register of submitted requisitions, including requestor, purpose, item specifications, quantities, pricing, and current workflow status.',
                'basis' => $request->input('date_basis') === 'fulfilled' ? 'Completion date (closed_at); completed / released only' : 'Submission date (submitted_at, falling back to created_at)',
                'source' => 'Requisitions, requisition line items, and requestor account records. Amounts represent submitted values and may include requests that were not released.',
                'group' => ['status', 'Workflow Status', 'line_total', 'Line Value', 'money'],
            ],
            'inventory' => [
                'purpose' => 'Documents the current catalog, stock position, reorder threshold, and estimated on-hand value at current catalog prices.',
                'basis' => 'Current inventory snapshot',
                'source' => 'Current quantity multiplied by current catalog price, not historical cost or audited expenditure. Historical quantities are available in the Inventory Movement Ledger.',
                'group' => ['category', 'Supply Category', 'asset_value', 'Estimated On-hand Value', 'money'],
            ],
            'dept_spending' => [
                'purpose' => 'Summarizes released requisition value, transaction volume, and participation by requesting department.',
                'basis' => 'Completion date (closed_at)',
                'source' => 'Recorded grand totals of completed and legacy released requisitions, attributed to the requestor current department. Operational value, not audited accounting expenditure.',
                'group' => ['department', 'Department', 'total', 'Recorded Fulfilled Value', 'money'],
            ],
            'users' => [
                'purpose' => 'Lists personnel accounts and their institutional identifiers, departments, roles, approval state, and registration dates.',
                'basis' => 'Account registration date',
                'source' => 'User account registry. Passwords, reset tokens, and other authentication secrets are never included.',
                'group' => ['role', 'Personnel Role', null, null, 'number'],
            ],
            'item_consumption' => [
                'purpose' => 'Summarizes requested line quantities and recorded prices on fulfilled requisitions.',
                'basis' => 'Completion date (closed_at)',
                'source' => 'Requested line items on completed and legacy released requests. These quantities are not stock deductions. Use the Inventory Movement Ledger for actual release_deduction transactions.',
                'group' => ['category', 'Supply Category', 'cost', 'Recorded Line Value', 'money'],
            ],
            'approval_audit' => [
                'purpose' => 'Presents the complete decision trail for requisitions, including acting role, remarks, signer identity, signing time, and verification reference.',
                'basis' => 'Approval action date',
                'source' => 'Approval logs and linked digital-signature records. The displayed verification reference is the first 12 characters of the stored SHA-256 token.',
                'group' => ['role', 'Acting Role', null, null, 'number'],
            ],
            'inventory_movements' => [
                'purpose' => 'Provides an auditable ledger of releases, restocks, and manual adjustments with resulting balances and responsible officers.',
                'basis' => 'Inventory transaction date',
                'source' => 'Immutable inventory transaction logs. Quantity change is signed: positive adds stock and negative removes stock.',
                'group' => ['type', 'Transaction Type', 'change', 'Net Quantity Change', 'number'],
            ],
            'user_activity' => [
                'purpose' => 'Records user security and application activity with account, department, role, network address, and client information.',
                'basis' => 'Activity timestamp',
                'source' => 'User activity audit logs. This confidential report may contain IP addresses and browser/device identifiers.',
                'group' => ['role', 'Personnel Role', null, null, 'number'],
            ],
        ];

        $definition = $definitions[$type];
        [$groupKey, $groupLabel, $valueKey, $valueLabel, $valueType] = $definition['group'];

        $breakdown = $rows
            ->groupBy(fn (array $row) => filled($row[$groupKey] ?? null) ? (string) $row[$groupKey] : 'Unassigned')
            ->map(function (Collection $groupRows, string $label) use ($valueKey, $valueLabel, $valueType) {
                return [
                    'label' => $label,
                    'records' => $groupRows->count(),
                    'valueLabel' => $valueLabel,
                    'value' => $valueKey ? $groupRows->sum($valueKey) : null,
                    'type' => $valueType,
                ];
            })
            ->sortByDesc(fn (array $group) => $group['value'] ?? $group['records'])
            ->values();

        // Keep the SMO workbook's compact item-and-cost register while placing
        // request ownership and workflow dates in separate readable tables.
        $detailColumns = $type === 'requisitions'
            ? [
                $this->column('category', 'Category'),
                $this->column('tracking', 'Tracking No.'),
                $this->column('item', 'Item / Specification'),
                $this->column('quantity', 'Qty', 'number'),
                $this->column('unit', 'Unit'),
                $this->column('unit_price', 'Unit Price', 'money'),
                $this->column('line_total', 'Total', 'money'),
            ]
            : $report['columns'];

        $requestColumns = $type === 'requisitions'
            ? [
                $this->column('tracking', 'Tracking No.'),
                $this->column('requestor_details', 'Requested By / Department'),
                $this->column('purpose', 'Purpose'),
                $this->column('workflow', 'Tier / Status'),
                $this->column('request_total', 'Request Total', 'money'),
            ]
            : [];

        $timelineColumns = $type === 'requisitions'
            ? [
                $this->column('tracking', 'Tracking No.'),
                $this->column('submitted_at', 'Submitted'),
                $this->column('sla_warning_at', 'SLA Warning'),
                $this->column('deadline_at', 'Deadline'),
                $this->column('stage_started_at', 'Current Stage Since'),
                $this->column('processing_at', 'SMO Processing'),
                $this->column('ready_at', 'Ready for Pickup'),
                $this->column('closed_at', 'Closed'),
                $this->column('updated_at', 'Last Updated'),
            ]
            : [];

        $requestRows = $type === 'requisitions'
            ? $rows
                ->unique('tracking')
                ->map(fn (array $row) => [
                    ...$row,
                    'requestor_details' => $row['requestor'].' / '.$row['department'],
                    'workflow' => $row['tier'].' / '.$row['status'],
                ])
                ->values()
            : collect();

        return [
            'purpose' => $definition['purpose'],
            'reportBasis' => $definition['basis'],
            'sourceNote' => $definition['source'],
            'classification' => $type === 'user_activity' ? 'Confidential - Internal Use' : 'Internal Administrative Use',
            'reportReference' => 'HTC-SMO-'.strtoupper(str_replace('_', '-', $type)).'-'.now()->format('Ymd-His'),
            'dataAsOf' => now()->format('F d, Y - h:i A'),
            'appliedFilters' => $this->appliedFilterSummary($request),
            'metrics' => $this->reportMetrics($type, $rows, $report),
            'breakdownTitle' => $groupLabel,
            'breakdown' => $breakdown,
            'detailColumns' => $detailColumns,
            'requestColumns' => $requestColumns,
            'timelineColumns' => $timelineColumns,
            'requestRows' => $requestRows,
            'documentTitle' => $type === 'requisitions'
                ? match ($request->input('tier')) {
                    'minor' => 'Minor Request Register',
                    'major' => 'Major Request Register',
                    default => 'Requisition Request Register',
                }
                : $report['title'],
            'detailTitle' => $type === 'requisitions'
                ? 'Requested Items and Costs'
                : 'Detailed Records',
            'notes' => $this->reportNotes($type, $rows, $report),
        ];
    }

    /** @return array<int, array{label: string, value: mixed, type: string}> */
    private function reportMetrics(string $type, Collection $rows, array $report): array
    {
        $metrics = match ($type) {
            'requisitions' => [
                ['label' => 'Requisitions', 'value' => $rows->pluck('tracking')->unique()->count(), 'type' => 'number'],
                ['label' => 'Line Items', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Units Requested', 'value' => $rows->sum('quantity'), 'type' => 'number'],
            ],
            'inventory' => [
                ['label' => 'Catalog Items', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Units on Hand', 'value' => $rows->sum('stock'), 'type' => 'number'],
                ['label' => 'Low / Out of Stock', 'value' => $rows->whereIn('health', ['Low stock', 'Out of stock'])->count(), 'type' => 'number'],
            ],
            'dept_spending' => [
                ['label' => 'Departments', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Fulfilled Requests', 'value' => $rows->sum('requests'), 'type' => 'number'],
                ['label' => 'Active Requestors', 'value' => $rows->sum('requestors'), 'type' => 'number'],
            ],
            'users' => [
                ['label' => 'Accounts', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Approved', 'value' => $rows->where('status', 'Approved')->count(), 'type' => 'number'],
                ['label' => 'Pending', 'value' => $rows->where('status', 'Pending')->count(), 'type' => 'number'],
            ],
            'item_consumption' => [
                ['label' => 'Distinct Item Groups', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Requested Units on Fulfilled Requests', 'value' => $rows->sum('quantity'), 'type' => 'number'],
                ['label' => 'Requisition Links', 'value' => $rows->sum('requests'), 'type' => 'number'],
            ],
            'approval_audit' => [
                ['label' => 'Approval Actions', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Digitally Signed', 'value' => $rows->where('verification', '!=', '-')->count(), 'type' => 'number'],
                ['label' => 'Unsigned Actions', 'value' => $rows->where('verification', '-')->count(), 'type' => 'number'],
            ],
            'inventory_movements' => [
                ['label' => 'Transactions', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Stock Added', 'value' => $rows->where('change', '>', 0)->sum('change'), 'type' => 'number'],
                ['label' => 'Stock Removed', 'value' => abs($rows->where('change', '<', 0)->sum('change')), 'type' => 'number'],
            ],
            default => [
                ['label' => 'Activities', 'value' => $rows->count(), 'type' => 'number'],
                ['label' => 'Distinct Users', 'value' => $rows->pluck('user')->unique()->count(), 'type' => 'number'],
                ['label' => 'Distinct IP Addresses', 'value' => $rows->pluck('ip')->filter()->unique()->count(), 'type' => 'number'],
            ],
        };

        $metrics[] = [
            'label' => $report['summaryLabel'],
            'value' => $report['summaryValue'],
            'type' => $report['summaryType'],
        ];

        return $metrics;
    }

    /** @return array<int, string> */
    private function reportNotes(string $type, Collection $rows, array $report): array
    {
        $notes = [
            'The detailed table contains '.number_format($rows->count()).' matching row(s) after the selected period and filters were applied.',
            $report['summaryLabel'].' is '.($report['summaryType'] === 'money'
                ? 'PHP '.number_format((float) $report['summaryValue'], 2)
                : number_format((float) $report['summaryValue']).'.'),
        ];

        $notes[] = match ($type) {
            'requisitions' => 'Items are grouped by catalog category and printed one per row; the same tracking number may therefore appear more than once.',
            'inventory' => 'Stock health is based on the current quantity compared with the configured minimum stock level.',
            'dept_spending' => 'Only completed and legacy released requisitions contribute, filtered by closed_at. Recorded request values are not audited expenditure.',
            'item_consumption' => 'These are requested quantities on fulfilled requests, filtered by closed_at. Actual stock deductions are recorded separately by ledger transaction date.',
            'approval_audit' => 'Actions without a digital signature remain visible and are explicitly identified as unsigned.',
            'inventory_movements' => 'Review the signed quantity change together with the balance after each transaction.',
            'user_activity' => 'Handle this report according to institutional privacy and access-control policies.',
            default => 'Account status reflects the approval state recorded when this report was generated.',
        };

        return $notes;
    }

    private function appliedFilterSummary(Request $request): string
    {
        $labels = [
            'dept' => 'Department', 'tier' => 'Tier', 'status' => 'Status',
            'role' => 'Role', 'account_status' => 'Account Status',
            'category' => 'Category', 'stock_filter' => 'Stock Health',
            'transaction_type' => 'Transaction Type',
            'supply_id' => 'Catalog Item ID', 'date_basis' => 'Requisition Date Basis',
        ];

        return collect($labels)
            ->filter(fn (string $label, string $key) => $request->filled($key))
            ->map(fn (string $label, string $key) => $label.': '.ucwords(str_replace('_', ' ', (string) $request->input($key))))
            ->values()
            ->join('; ');
    }

    private function applyDateRange(Builder|QueryBuilder $query, string|Expression $column, ?Carbon $dateFrom, ?Carbon $dateTo): void
    {
        if ($dateFrom) {
            $query->where($column, '>=', $dateFrom->copy()->startOfDay());
        }
        if ($dateTo) {
            $query->where($column, '<', $dateTo->copy()->startOfDay()->addDay());
        }
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null, 2: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->get('period', 'current_semester');

        return match ($period) {
            'all' => [null, null, 'All Available Records'],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), now()->format('F Y')],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), 'Calendar Year '.now()->year],
            'custom' => [
                $request->filled('date_from') ? Carbon::parse($request->date_from) : null,
                $request->filled('date_to') ? Carbon::parse($request->date_to) : null,
                $this->customPeriodLabel($request),
            ],
            default => $this->currentSemester(),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function currentSemester(): array
    {
        $year = now()->year;
        $month = now()->month;

        if ($month <= 5) {
            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 5, 31)->endOfDay(),
                'Second Semester AY '.($year - 1).'-'.$year,
            ];
        }

        if ($month <= 7) {
            return [
                Carbon::create($year, 6, 1)->startOfDay(),
                Carbon::create($year, 7, 31)->endOfDay(),
                'Summer Term '.$year,
            ];
        }

        return [
            Carbon::create($year, 8, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
            'First Semester AY '.$year.'-'.($year + 1),
        ];
    }

    private function customPeriodLabel(Request $request): string
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->format('M d, Y') : 'Beginning';
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->format('M d, Y') : 'Present';

        return $from.' to '.$to;
    }

    /**
     * Download an Excel-compatible CSV without requiring an additional package.
     *
     * @param  array<string, mixed>  $report
     */
    private function downloadCsv(array $report)
    {
        $filename = 'HTC_SMO_'.$report['reportType'].'_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_column($report['columns'], 'label'));

            foreach ($report['rows'] as $row) {
                $values = collect($report['columns'])->map(function (array $column) use ($row) {
                    $value = (string) ($row[$column['key']] ?? '');

                    return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
                })->all();

                fputcsv($output, $values);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
