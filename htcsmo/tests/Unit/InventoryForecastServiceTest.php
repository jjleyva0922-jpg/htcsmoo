<?php

namespace Tests\Unit;

use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_combines_seasonal_and_recent_release_demand(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $smo = User::factory()->create(['role' => 'smo']);
        $supply = Supply::create([
            'item_name' => 'Bond Paper',
            'brand' => 'HTC Test',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'A4 80 GSM',
            'quantity' => 4,
            'unit' => 'Ream(s)',
            'unit_price' => 200,
            'min_stock_level' => 5,
        ]);

        $this->release($supply, $smo, -30, '2025-10-10 09:00:00');
        $this->release($supply, $smo, -6, '2026-08-10 09:00:00');
        $this->release($supply, $smo, -9, '2026-09-10 09:00:00');

        $forecast = app(InventoryForecastService::class)->build(collect([$supply]), Carbon::now());
        $prediction = $forecast['recommendations']->first();

        $this->assertSame('October 2026', $forecast['target_month']->format('F Y'));
        $this->assertSame(22, $prediction['forecast_demand']);
        $this->assertSame(24, $prediction['recommended_order']);
        $this->assertSame('October', $prediction['peak_month']);
        $this->assertSame('high', $prediction['confidence']);
        $this->assertStringContainsString('historically consumes', $prediction['reason']);
    }

    public function test_it_generates_a_three_month_quarterly_forecast(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $smo = User::factory()->create(['role' => 'smo']);
        $supply = Supply::create([
            'item_name' => 'Quarterly Bond Paper',
            'brand' => 'HTC Test',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'A4 80 GSM',
            'quantity' => 4,
            'unit' => 'Ream(s)',
            'unit_price' => 200,
            'min_stock_level' => 5,
        ]);

        $this->release($supply, $smo, -30, '2025-10-10 09:00:00');
        $this->release($supply, $smo, -6, '2026-08-10 09:00:00');
        $this->release($supply, $smo, -9, '2026-09-10 09:00:00');

        $forecast = app(InventoryForecastService::class)->build(collect([$supply]), Carbon::now(), 'quarterly');
        $prediction = $forecast['recommendations']->first();

        $this->assertSame('quarterly', $forecast['period']);
        $this->assertSame('Oct 2026 - Dec 2026', $forecast['period_label']);
        $this->assertCount(3, $prediction['monthly_projections']);
        $this->assertSame(26, $prediction['forecast_demand']);
        $this->assertSame(29, $prediction['recommended_order']);
        $this->assertSame(5800.0, $forecast['estimated_budget']);
    }

    private function release(Supply $supply, User $user, int $quantity, string $createdAt): void
    {
        DB::table('supply_inventory_logs')->insert([
            'supply_id' => $supply->id,
            'user_id' => $user->id,
            'requisition_id' => null,
            'transaction_type' => 'release_deduction',
            'quantity_change' => $quantity,
            'balance_after' => $supply->quantity,
            'remarks' => 'Forecast test release',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
