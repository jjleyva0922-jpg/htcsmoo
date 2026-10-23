<?php

namespace App\Services;

use App\Models\SupplyInventoryLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryForecastService
{
    /**
     * Build an upcoming monthly or three-month forecast from 24 months of releases.
     */
    public function build(Collection $supplies, ?Carbon $asOf = null, string $period = 'monthly'): array
    {
        if ($asOf !== null) {
            return $this->calculate($supplies, $asOf, $period);
        }

        $key = 'inventory-forecast:'.hash('sha256', implode(':', [
            app(WorkspaceVersionService::class)->version('inventory'),
            now()->format('Y-m'), $period, $supplies->pluck('id')->implode(','),
        ]));

        // A stock edit or release changes the version after its transaction commits.
        return Cache::remember($key, 300, fn () => $this->calculate($supplies, null, $period));
    }

    private function calculate(Collection $supplies, ?Carbon $asOf, string $period): array
    {
        $period = $period === 'quarterly' ? 'quarterly' : 'monthly';
        $currentMonth = ($asOf ?? now())->copy()->startOfMonth();
        $targetMonth = $currentMonth->copy()->addMonth();
        $periodMonths = $period === 'quarterly' ? 3 : 1;
        $targetMonths = collect(range(0, $periodMonths - 1))
            ->map(fn (int $offset) => $targetMonth->copy()->addMonths($offset));
        $targetEnd = $targetMonths->last()->copy()->endOfMonth();
        $historyStart = $currentMonth->copy()->subMonths(23);
        $monthExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            'sqlsrv' => "FORMAT(created_at, 'yyyy-MM')",
            default => "strftime('%Y-%m', created_at)",
        };

        $logs = SupplyInventoryLog::query()
            ->select('supply_id')
            ->selectRaw($monthExpression.' as usage_month')
            ->selectRaw('SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) as used_quantity, COUNT(*) as event_count')
            ->whereIn('supply_id', $supplies->pluck('id'))
            ->where('transaction_type', 'release_deduction')
            ->where('created_at', '>=', $historyStart)
            ->groupBy('supply_id')->groupByRaw($monthExpression)
            ->get()
            ->groupBy('supply_id');

        $forecasts = $supplies->map(function ($supply) use ($logs, $currentMonth, $targetMonth, $targetMonths, $historyStart, $period) {
            $itemLogs = $logs->get($supply->id, collect());
            $monthlyUsage = [];

            foreach ($itemLogs as $log) {
                $monthlyUsage[$log->usage_month] = (int) $log->used_quantity;
            }

            $earliestMonth = $itemLogs->isNotEmpty()
                ? Carbon::parse($itemLogs->min('usage_month').'-01')->max($historyStart)
                : $currentMonth->copy();
            $observedMonths = max(1, $earliestMonth->diffInMonths($currentMonth) + 1);
            $recentMonthCount = min(3, $observedMonths);
            $recentUsage = 0;

            for ($offset = 0; $offset < $recentMonthCount; $offset++) {
                $recentUsage += $monthlyUsage[$currentMonth->copy()->subMonths($offset)->format('Y-m')] ?? 0;
            }

            $recentAverage = $recentUsage / $recentMonthCount;
            $totalUsage = array_sum($monthlyUsage);
            $overallAverage = $totalUsage / $observedMonths;
            $calendarMonthTotals = [];
            $calendarMonthCounts = [];

            for ($offset = 0; $offset < $observedMonths; $offset++) {
                $month = $currentMonth->copy()->subMonths($offset);
                $monthNumber = $month->month;
                $usage = $monthlyUsage[$month->format('Y-m')] ?? 0;
                $calendarMonthTotals[$monthNumber] = ($calendarMonthTotals[$monthNumber] ?? 0) + $usage;
                $calendarMonthCounts[$monthNumber] = ($calendarMonthCounts[$monthNumber] ?? 0) + 1;
            }

            $monthlyProjections = $targetMonths->map(function (Carbon $month) use ($calendarMonthTotals, $calendarMonthCounts, $recentAverage, $overallAverage) {
                $occurrences = $calendarMonthCounts[$month->month] ?? 0;
                $seasonalAverage = $occurrences > 0
                    ? ($calendarMonthTotals[$month->month] ?? 0) / $occurrences
                    : null;
                $base = $seasonalAverage !== null
                    ? ($seasonalAverage * 0.65) + ($recentAverage * 0.35)
                    : ($recentAverage > 0 ? $recentAverage : $overallAverage);

                return [
                    'month' => $month,
                    'demand' => (int) ceil($base),
                    'base' => $base,
                    'seasonality_index' => $seasonalAverage !== null && $overallAverage > 0
                        ? $seasonalAverage / $overallAverage
                        : null,
                ];
            });

            $forecastDemand = $monthlyProjections->sum('demand');
            $baseForecast = $monthlyProjections->sum('base');
            $safetyStock = max((int) $supply->min_stock_level, (int) ceil($baseForecast * 0.25));
            $targetStock = $forecastDemand + $safetyStock;
            $recommendedOrder = max(0, $targetStock - (int) $supply->quantity);
            $strongestSeason = $monthlyProjections
                ->sortByDesc(fn (array $projection) => $projection['seasonality_index'] ?? -1)
                ->first();
            $seasonalityIndex = $strongestSeason['seasonality_index'] ?? null;

            $calendarMonthAverages = collect($calendarMonthTotals)
                ->map(fn ($total, $month) => $total / $calendarMonthCounts[$month]);
            $peakMonthNumber = $calendarMonthAverages->isNotEmpty()
                ? (int) $calendarMonthAverages->sortDesc()->keys()->first()
                : null;

            $confidence = match (true) {
                $observedMonths >= 12 && $monthlyProjections->contains(fn (array $projection) => ($projection['seasonality_index'] ?? 0) > 0) => 'high',
                $observedMonths >= 6 || $itemLogs->sum('event_count') >= 4 => 'medium',
                default => 'low',
            };

            return [
                'supply' => $supply,
                'forecast_demand' => $forecastDemand,
                'recommended_order' => $recommendedOrder,
                'target_stock' => $targetStock,
                'safety_stock' => $safetyStock,
                'recent_average' => round($recentAverage, 1),
                'seasonality_percent' => $seasonalityIndex !== null ? (int) round(($seasonalityIndex - 1) * 100) : null,
                'peak_month' => $peakMonthNumber ? Carbon::create(2000, $peakMonthNumber, 1)->format('F') : null,
                'months_observed' => $observedMonths,
                'release_events' => (int) $itemLogs->sum('event_count'),
                'confidence' => $confidence,
                'reason' => $this->reason(
                    $strongestSeason['month'] ?? $targetMonth,
                    $seasonalityIndex,
                    $recentAverage,
                    $overallAverage,
                    $itemLogs->isNotEmpty(),
                    $period
                ),
                'monthly_projections' => $monthlyProjections,
                'urgency' => $supply->quantity <= 0 ? 'critical' : ($forecastDemand >= $supply->quantity ? 'buy_now' : 'plan'),
            ];
        });

        $recommendations = $forecasts
            ->filter(fn ($forecast) => $forecast['recommended_order'] > 0)
            ->sortByDesc(fn ($forecast) => match ($forecast['urgency']) {
                'critical' => 300000 + $forecast['recommended_order'],
                'buy_now' => 200000 + $forecast['recommended_order'],
                default => 100000 + $forecast['recommended_order'],
            })
            ->values();

        return [
            'period' => $period,
            'period_label' => $period === 'quarterly'
                ? $targetMonth->format('M Y').' - '.$targetEnd->format('M Y')
                : $targetMonth->format('F Y'),
            'target_month' => $targetMonth,
            'target_end' => $targetEnd,
            'months' => $targetMonths,
            'recommendations' => $recommendations,
            'all' => $forecasts,
            'items_with_history' => $forecasts->where('release_events', '>', 0)->count(),
            'release_events' => $forecasts->sum('release_events'),
            'estimated_budget' => $recommendations->sum(
                fn (array $forecast) => $forecast['recommended_order'] * (float) $forecast['supply']->unit_price
            ),
        ];
    }

    private function reason(
        Carbon $targetMonth,
        ?float $seasonalityIndex,
        float $recentAverage,
        float $overallAverage,
        bool $hasHistory,
        string $period
    ): string {
        if (! $hasHistory) {
            return 'No release history yet; recommendation is based on the configured safety threshold.';
        }

        if ($seasonalityIndex !== null && $seasonalityIndex >= 1.2) {
            return sprintf(
                '%s historically consumes about %d%% more than the monthly average.',
                $targetMonth->format('F'),
                round(($seasonalityIndex - 1) * 100)
            );
        }

        if ($overallAverage > 0 && $recentAverage >= $overallAverage * 1.2) {
            return 'Recent monthly consumption is above this item\'s historical average.';
        }

        return $period === 'quarterly'
            ? 'Three-month demand is projected from recent release volume plus safety stock.'
            : 'Suggested from recent release volume plus safety stock.';
    }
}
