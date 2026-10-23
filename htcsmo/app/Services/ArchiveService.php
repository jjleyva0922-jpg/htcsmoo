<?php

namespace App\Services;

use App\Http\Requests\ArchiveIndexRequest;
use App\Models\Department;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class ArchiveService
{
    public function index(ArchiveIndexRequest $request, bool $institutionalRoute = false)
    {
        $institutional = $request->user()->role === 'smo';
        abort_if($institutionalRoute && ! $institutional, 403);
        $filters = $request->validated();
        $base = Requisition::query()->archived()->visibleTo($request->user());
        $query = (clone $base)->withArchiveFilters($filters, allowDepartment: $institutional);
        $summary = (clone $query)->selectRaw("COUNT(*) as total, COALESCE(SUM(grand_total), 0) as requested_value,
            COALESCE(SUM(CASE WHEN status IN ('completed', 'released') THEN 1 ELSE 0 END), 0) as fulfilled,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected,
            COALESCE(SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END), 0) as withdrawn,
            COALESCE(SUM(CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END), 0) as undated")->first();
        $categoryCounts = (clone $base)->withArchiveFilters(Arr::except($filters, ['category', 'status']), allowDepartment: $institutional)
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $categoryCounts = [
            'all' => (int) $categoryCounts->sum(),
            'fulfilled' => (int) ($categoryCounts['completed'] ?? 0) + (int) ($categoryCounts['released'] ?? 0),
            'rejected' => (int) ($categoryCounts['rejected'] ?? 0),
            'withdrawn' => (int) ($categoryCounts['withdrawn'] ?? 0),
        ];
        $dateExpression = ($filters['date_basis'] ?? 'closed') === 'submitted' ? 'COALESCE(submitted_at, created_at)' : 'closed_at';
        $order = $filters['order'] ?? 'desc';
        $query->with(['items', 'user.academicDepartment', 'latestClosureLog.actor'])
            ->orderByRaw("CASE WHEN {$dateExpression} IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw("{$dateExpression} {$order}")->orderBy('id', $order);

        if (($filters['export'] ?? null) === 'csv') {
            return $this->csv($query, $institutional);
        }

        $records = $request->boolean('print') ? $query->get() : $query->paginate(15)->withQueryString();
        $departments = $institutional ? Department::query()->whereHas('users')->orderBy('dept_code')->pluck('dept_code') : collect();
        $archiveRoute = $institutionalRoute ? 'admin.archive' : 'requests.archive';
        $archiveContext = ['archive' => $institutionalRoute ? 'institutional' : 'personal', 'archive_filters' => Arr::except($filters, ['print', 'export'])];
        $data = compact('records', 'departments', 'filters', 'institutional', 'summary', 'categoryCounts', 'archiveRoute', 'archiveContext');

        return response()->view($institutionalRoute ? 'admin.archive' : 'requests.archive', [
            ...$data, 'archivedRequests' => $records, 'archives' => $records,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function returnUrl(Request $request): ?string
    {
        $source = $request->query('archive');
        $filters = $request->query('archive_filters', []);
        if (! in_array($source, ['personal', 'institutional'], true) || ! is_array($filters)
            || ($source === 'institutional' && $request->user()->role !== 'smo')) {
            return null;
        }
        $validation = Validator::make($filters, ArchiveIndexRequest::filterRules($request->user()));
        if ($validation->fails()) {
            return null;
        }

        return route($source === 'institutional' ? 'admin.archive' : 'requests.archive', Arr::except($validation->validated(), ['print', 'export'])).'#archive-results';
    }

    private function csv($query, bool $institutional)
    {
        $zone = config('app.timezone');

        return response()->streamDownload(function () use ($query, $institutional, $zone) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Tracking number', ...($institutional ? ['Requestor', 'Department'] : []), 'Primary item', 'Item lines', 'Tier',
                "Submitted ({$zone})", "Closed ({$zone})", 'Requested value (PHP; not expenditure)', 'Final status', 'Status code',
                'Final audit event', 'Recorded actor', 'Decision / withdrawal remarks',
            ]);
            foreach ($query->lazy(200) as $record) {
                $values = [
                    $record->tracking_code,
                    ...($institutional ? [$record->user?->name ?? 'Unavailable user', $record->user?->department ?? 'Unassigned'] : []),
                    $record->items->first()?->item_name ?? 'Supply requisition', $record->items->count(), ucfirst($record->request_type),
                    ($record->submitted_at ?? $record->created_at)?->format('Y-m-d H:i:s'), $record->closed_at?->format('Y-m-d H:i:s') ?? 'Unavailable',
                    number_format((float) $record->grand_total, 2, '.', ''), $record->archiveStatusLabel(), $record->status,
                    $record->latestClosureLog?->action ?? 'Not recorded', $record->latestClosureLog?->actor?->name ?? 'Not recorded',
                    $record->closureRemarks() ?? '',
                ];
                // Neutralize spreadsheet formulas, including leading whitespace.
                fputcsv($output, array_map(fn ($value) => preg_match('/^[\x00-\x20]*[=+\-@]/u', (string) $value) ? "'".$value : $value, $values));
            }
            fclose($output);
        }, 'HTC_'.($institutional ? 'Institutional' : 'My').'_Archive_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private',
        ]);
    }
}
