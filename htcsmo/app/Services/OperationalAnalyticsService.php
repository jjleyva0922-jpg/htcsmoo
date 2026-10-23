<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalAnalyticsService
{
    public function build(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $until = $to->copy()->startOfDay()->addDay();
        $fulfilled = Requisition::query()->whereIn('status', Requisition::FULFILLED_STATUSES)
            ->where('closed_at', '>=', $from)->where('closed_at', '<', $until);
        $periodTotals = (clone $fulfilled)->selectRaw(
            'COUNT(*) as requests, COALESCE(SUM(grand_total), 0) as value, COALESCE(AVG(grand_total), 0) as average'
        )->first();

        $active = Requisition::query()->whereNotIn('status', Requisition::ARCHIVED_STATUSES);
        $pipeline = (clone $active)->selectRaw(
            'COUNT(*) as active, COALESCE(SUM(CASE WHEN due_at <= ? THEN 1 ELSE 0 END), 0) as overdue,
             COALESCE(SUM(CASE WHEN sla_warning_at <= ? AND (due_at > ? OR due_at IS NULL) THEN 1 ELSE 0 END), 0) as nearing',
            [now(), now(), now()]
        )->first();
        $inventory = Supply::query()->selectRaw(
            'COUNT(*) as items, COALESCE(SUM(quantity * unit_price), 0) as estimated_value,
             COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
             COALESCE(SUM(CASE WHEN quantity > 0 AND quantity <= min_stock_level THEN 1 ELSE 0 END), 0) as low'
        )->first();

        $monthExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(closed_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(closed_at, 'YYYY-MM')",
            'sqlsrv' => "FORMAT(closed_at, 'yyyy-MM')",
            default => "strftime('%Y-%m', closed_at)",
        };
        $monthlyTotals = (clone $fulfilled)->selectRaw(
            $monthExpression.' as month_key, COUNT(*) as requests, SUM(grand_total) as value'
        )->groupByRaw($monthExpression)->get()->keyBy('month_key');
        $months = collect();
        for ($month = $from->copy()->startOfMonth(); $month->lt($until); $month->addMonth()) {
            $row = $monthlyTotals->get($month->format('Y-m'));
            $months->push([
                'key' => $month->format('Y-m'), 'label' => $month->format('M Y'),
                'from' => $month->copy()->max($from)->toDateString(),
                'to' => $month->copy()->endOfMonth()->min($to)->toDateString(),
                'count' => (int) ($row?->requests ?? 0), 'value' => (float) ($row?->value ?? 0),
            ]);
        }

        $departments = (clone $fulfilled)
            ->leftJoin('users', 'requisitions.user_id', '=', 'users.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->selectRaw("COALESCE(departments.dept_code, 'Unassigned') as department, COUNT(*) as requests, SUM(requisitions.grand_total) as value")
            ->groupBy('departments.dept_code')->orderByDesc('value')->orderBy('department')->get();

        $topItems = DB::table('supply_inventory_logs as movements')
            ->leftJoin('supplies', 'movements.supply_id', '=', 'supplies.id')
            ->leftJoin('supply_units', 'supplies.supply_unit_id', '=', 'supply_units.id')
            ->where('movements.transaction_type', 'release_deduction')
            ->where('movements.created_at', '>=', $from)->where('movements.created_at', '<', $until)
            ->selectRaw('movements.supply_id, supplies.item_name, supply_units.unit_name as unit, -SUM(movements.quantity_change) as quantity')
            ->groupBy('movements.supply_id', 'supplies.item_name', 'supply_units.unit_name')
            ->havingRaw('-SUM(movements.quantity_change) > 0')->orderByDesc('quantity')
            ->orderBy('movements.supply_id')->limit(5)->get();

        $workflow = (clone $active)->select('status', 'request_type')->selectRaw('COUNT(*) as total')
            ->groupBy('status', 'request_type')->get()->groupBy(fn ($row) => match ($row->status) {
                'pending' => 'Department review',
                'approved_dept' => 'VP review',
                'approved_vp' => $row->request_type === 'major' ? 'Provost review' : 'Awaiting SMO processing',
                'approved_provost' => 'President review',
                'approved_president' => 'Awaiting SMO processing',
                'processing' => 'SMO processing',
                'ready_for_pickup' => 'Ready for pickup',
                'for_clarification' => 'For clarification',
                default => 'Other active stages',
            })->map(fn ($rows) => (int) $rows->sum('total'));
        $workflow = collect([
            'Department review', 'VP review', 'Provost review', 'President review',
            'For clarification', 'Awaiting SMO processing', 'SMO processing', 'Ready for pickup', 'Other active stages',
        ])->mapWithKeys(fn ($label) => [$label => $workflow->get($label, 0)]);

        return compact('periodTotals', 'pipeline', 'inventory', 'months', 'departments', 'topItems', 'workflow');
    }
}
