<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\Supply;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class OperationalAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
    }

    public function test_fulfillment_uses_closed_date_across_totals_departments_and_zero_filled_months(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => 'CETE']);
        $old = $this->requisition($owner, 'completed', '2026-07-31 23:59:59', 900);
        $this->requisition($owner, 'completed', '2026-08-01 00:00:00', 125.75);
        $this->requisition($owner, 'released', '2026-12-31 23:59:59', 50.25);
        $this->requisition($owner, 'completed', '2027-01-01 00:00:00', 800);
        $this->requisition($owner, 'rejected', '2026-10-01 10:00:00', 700);
        $this->requisition($owner, 'withdrawn', '2026-10-01 10:00:00', 5000);
        $this->requisition($owner, 'ready_for_pickup', null, 600);
        $old->update(['remarks' => 'Edited in October, but completed in July']);

        $response = $this->actingAs($smo)->get(route('admin.reports.index'))->assertOk()
            ->assertSee('First Semester AY 2026-2027')->assertSee('₱176.00')
            ->assertSee('Monthly figures and request links');
        $this->assertEquals(2, $response->viewData('periodTotals')->requests);
        $this->assertEquals(176, $response->viewData('periodTotals')->value);
        $this->assertEquals(88, $response->viewData('periodTotals')->average);
        $this->assertSame([1, 0, 0, 0, 1], $response->viewData('months')->pluck('count')->all());
        $this->assertSame(['Aug 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026', 'Dec 2026'], $response->viewData('months')->pluck('label')->all());
        $this->assertEquals(176, $response->viewData('departments')->sum('value'));
        $this->assertEquals(1, $response->viewData('pipeline')->active);
    }

    public function test_custom_range_is_inclusive_and_preserved_in_report_links_and_return_navigation(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $this->requisition($owner, 'completed', '2026-09-14 23:59:59', 10);
        $this->requisition($owner, 'completed', '2026-09-15 00:00:00', 20);
        $this->requisition($owner, 'completed', '2026-10-02 23:59:59', 30);
        $this->requisition($owner, 'completed', '2026-10-03 00:00:00', 40);
        $period = ['period' => 'custom', 'date_from' => '2026-09-15', 'date_to' => '2026-10-02'];
        $response = $this->actingAs($smo)->get(route('admin.reports.index', $period))->assertOk()
            ->assertSee(route('admin.reports.builder', [...$period, 'report_type' => 'requisitions', 'date_basis' => 'fulfilled']));
        $this->assertEquals(50, $response->viewData('periodTotals')->value);
        $this->assertSame(['2026-09-15', '2026-10-01'], $response->viewData('months')->pluck('from')->all());
        $this->assertSame(['2026-09-30', '2026-10-02'], $response->viewData('months')->pluck('to')->all());

        $this->get(route('admin.reports.builder', [...$period, 'report_type' => 'dept_spending']))->assertOk()
            ->assertSee(route('admin.reports.index', $period));
    }

    public function test_month_and_year_periods_and_cross_year_empty_months(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $this->requisition($owner, 'completed', '2026-01-01 00:00:00', 10);
        $this->requisition($owner, 'completed', '2026-10-01 00:00:00', 20);
        $this->requisition($owner, 'completed', '2026-11-01 00:00:00', 30);
        $this->requisition($owner, 'completed', '2027-01-01 00:00:00', 40);
        $this->actingAs($smo);
        $month = $this->get(route('admin.reports.index', ['period' => 'this_month']))->assertOk();
        $this->assertEquals(20, $month->viewData('periodTotals')->value);
        $year = $this->get(route('admin.reports.index', ['period' => 'this_year']))->assertOk();
        $this->assertEquals(60, $year->viewData('periodTotals')->value);
        $this->assertCount(12, $year->viewData('months'));
        $cross = $this->get(route('admin.reports.index', [
            'period' => 'custom', 'date_from' => '2025-12-01', 'date_to' => '2026-02-28',
        ]))->assertOk();
        $this->assertSame(['Dec 2025', 'Jan 2026', 'Feb 2026'], $cross->viewData('months')->pluck('label')->all());
        $this->assertSame([0, 1, 0], $cross->viewData('months')->pluck('count')->all());
    }

    public function test_top_items_use_signed_release_ledger_including_pickup_and_deleted_catalog_entries(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $pickup = $this->requisition($owner, 'ready_for_pickup', null, 100);
        $paper = $this->supply('Ledger Paper', 8, 5);
        $deleted = $this->supply('Retired catalog item', 0, 0);
        $this->movement($smo, $paper, $pickup, -5, '2026-10-01 00:00:00');
        $this->movement($smo, $paper, $pickup, 2, '2026-10-02 00:00:00');
        $this->movement($smo, $paper, $pickup, -99, '2026-09-30 23:59:59');
        $this->movement($smo, $paper, $pickup, -99, '2026-11-01 00:00:00');
        $this->movement($smo, $paper, $pickup, 100, '2026-10-02 00:00:00', 'restock_delivery');
        $this->movement($smo, $paper, $pickup, -10, '2026-10-02 00:00:00', 'manual_adjustment');
        $this->movement($smo, $deleted, $pickup, -1, '2026-10-31 23:59:59');
        $deleted->delete();
        $pickup->items()->create(['supply_id' => $paper->id, 'item_name' => 'Requested paper', 'quantity' => 999, 'unit' => 'Piece', 'unit_price' => 1, 'subtotal' => 999]);

        $response = $this->actingAs($smo)->get(route('admin.reports.index', ['period' => 'this_month']))->assertOk()
            ->assertSee('Retired catalog item')->assertDontSee('Requested paper');
        $this->assertSame([3, 1], $response->viewData('topItems')->pluck('quantity')->map(fn ($n) => (int) $n)->all());
        $this->assertEquals(0, $response->viewData('periodTotals')->requests);
        $this->assertEquals(1, $response->viewData('inventory')->items);
        $this->assertEquals(16, $response->viewData('inventory')->estimated_value);
        $response->assertSee(route('admin.reports.builder', [
            'period' => 'this_month', 'report_type' => 'inventory_movements', 'transaction_type' => 'release_deduction', 'supply_id' => $paper->id,
        ]));
        $ledger = $this->get(route('admin.reports.builder', [
            'period' => 'this_month', 'report_type' => 'inventory_movements', 'transaction_type' => 'release_deduction', 'supply_id' => $paper->id,
        ]))->assertOk();
        $this->assertEquals(-3, $ledger->viewData('rows')->sum('change'));
    }

    public function test_snapshots_are_independent_of_period_and_stage_counts_distinguish_major_vp_approval(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $this->requisition($owner, 'pending', null, 10)->forceFill([
            'sla_warning_at' => now()->subDay(), 'due_at' => now(),
        ])->saveQuietly();
        $this->requisition($owner, 'processing', null, 10)->forceFill([
            'sla_warning_at' => now(), 'due_at' => now()->addDay(),
        ])->saveQuietly();
        $this->requisition($owner, 'approved_vp', null, 10)->forceFill(['request_type' => 'major'])->saveQuietly();
        $this->requisition($owner, 'approved_vp', null, 10);
        $this->supply('Empty', 0, 0);
        $this->supply('At minimum', 5, 5);
        $response = $this->actingAs($smo)->get(route('admin.reports.index', [
            'period' => 'custom', 'date_from' => '2025-01-01', 'date_to' => '2025-01-31',
        ]))->assertOk();
        $this->assertEquals(4, $response->viewData('pipeline')->active);
        $this->assertEquals(1, $response->viewData('pipeline')->overdue);
        $this->assertEquals(1, $response->viewData('pipeline')->nearing);
        $this->assertEquals(1, $response->viewData('inventory')->out_of_stock);
        $this->assertEquals(1, $response->viewData('inventory')->low);
        $this->assertSame(1, $response->viewData('workflow')->get('Provost review'));
        $this->assertSame(1, $response->viewData('workflow')->get('Awaiting SMO processing'));
        $this->assertEquals(0, $response->viewData('periodTotals')->requests);
    }

    public function test_aligned_detailed_reports_and_csv_use_completion_date_and_explicit_labels(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => null]);
        $match = $this->requisition($owner, 'completed', '2026-10-01 00:00:00', 25);
        $old = $this->requisition($owner, 'released', '2026-09-30 23:59:59', 900);
        $withdrawn = $this->requisition($owner, 'withdrawn', '2026-10-01 10:00:00', 5000);
        foreach ([$match, $old, $withdrawn] as $request) {
            $request->items()->create(['item_name' => $request->is($match) ? 'Matching manual item' : 'Old manual item', 'quantity' => 5, 'unit' => 'Piece', 'unit_price' => 5, 'subtotal' => 25]);
        }
        $old->update(['remarks' => 'Later October edit']);
        $this->actingAs($smo);
        foreach (['dept_spending', 'item_consumption', 'requisitions'] as $type) {
            $query = ['report_type' => $type, 'period' => 'this_month', 'dept' => '__unassigned', 'date_basis' => 'fulfilled'];
            $preview = $this->get(route('admin.reports.builder', $query))->assertOk()
                ->assertSee('completion date (closed_at)', false);
            $this->assertEquals(25, $preview->viewData('summaryValue'));
            $csv = $this->get(route('admin.reports.builder', [...$query, 'export' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('Old manual item', $csv);
            $this->assertStringNotContainsString('900', $csv);
            $this->assertStringContainsString($type === 'dept_spending' ? 'Recorded Fulfilled Value' : 'Requested Qty', $csv);
        }
        $overview = $this->get(route('admin.reports.index', ['period' => 'this_month']))->assertOk()->assertSee('Unassigned');
        $this->assertEquals(25, $overview->viewData('departments')->first()->value);
    }

    public function test_requisition_submission_basis_and_pdf_fulfillment_basis_share_the_selected_filters(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $match = $this->requisition($owner, 'completed', '2026-10-02 12:00:00', 45);
        $match->forceFill(['submitted_at' => '2026-09-10 10:00:00', 'created_at' => '2026-10-01 09:00:00'])->saveQuietly();
        $query = ['period' => 'this_month', 'report_type' => 'requisitions'];
        $this->actingAs($smo);
        $this->get(route('admin.reports.builder', $query))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('PDF fixture'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($match) {
            $this->assertSame('admin.reports.pdf_template', $view);
            $this->assertSame([$match->tracking_code], $data['rows']->pluck('tracking')->all());
            $this->assertEquals(45, $data['summaryValue']);
            $this->assertStringContainsString('closed_at', $data['reportBasis']);
            $this->assertSame('October 2026', $data['periodLabel']);

            return true;
        })->andReturn($pdf);
        $this->get(route('admin.reports.builder', [...$query, 'date_basis' => 'fulfilled', 'download_pdf' => 1]))->assertOk();
    }

    public function test_validation_access_control_and_empty_analytics(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        foreach (['employee', 'dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.reports.index'))->assertRedirect(route('dashboard'));
            $this->get(route('admin.reports.builder'))->assertRedirect(route('dashboard'));
        }
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        foreach ([
            ['period' => 'invalid'],
            ['period' => 'custom'],
            ['period' => 'custom', 'date_from' => '2026-02-30', 'date_to' => '2026-03-01'],
            ['period' => 'custom', 'date_from' => '2026-10-02', 'date_to' => '2026-10-01'],
            ['period' => 'custom', 'date_from' => '2000-01-01', 'date_to' => '2026-01-01'],
        ] as $invalid) {
            $this->getJson(route('admin.reports.index', $invalid))->assertUnprocessable();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->get(route('admin.reports.index'))->assertOk()
                ->assertSee('No fulfilled requests in this period')
                ->assertSee('No stock release deductions recorded')
                ->assertSee('₱0.00')
                ->assertDontSee('chart.js');
            $queries = collect(DB::getQueryLog())->pluck('query');
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(5, $response->viewData('months'));
        $this->assertFalse($queries->contains(fn ($sql) => preg_match('/select \* from ["`]?requisitions/i', $sql) === 1));
    }

    private function requisition(User $owner, string $status, ?string $closed, float $value): Requisition
    {
        $request = Requisition::create(['user_id' => $owner->id, 'request_type' => 'minor', 'status' => $status, 'purpose' => 'Analytics regression', 'grand_total' => $value]);
        $request->forceFill(['closed_at' => $closed])->saveQuietly();

        return $request;
    }

    private function supply(string $name, int $quantity, int $minimum): Supply
    {
        return Supply::create(['item_name' => $name, 'category' => 'Office Supplies', 'unit' => 'Piece', 'unit_price' => 2, 'quantity' => $quantity, 'min_stock_level' => $minimum, 'requisition_tier' => 'minor']);
    }

    private function movement(User $smo, Supply $supply, Requisition $request, int $quantity, string $date, string $type = 'release_deduction'): void
    {
        $log = SupplyInventoryLog::create(['supply_id' => $supply->id, 'user_id' => $smo->id, 'requisition_id' => $request->id, 'transaction_type' => $type, 'quantity_change' => $quantity, 'balance_after' => 0]);
        $log->forceFill(['created_at' => $date])->saveQuietly();
    }
}
