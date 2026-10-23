<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmoDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_value_uses_completion_date_and_preserves_centavos_on_initial_render_and_refresh(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();

        foreach ([
            ['completed', 1250.75, '2026-10-01 00:00:00', '2026-11-01 00:00:00'],
            ['released', 50.50, '2026-10-21 09:00:00', '2026-10-21 09:00:00'],
            ['completed', 900, '2026-09-30 23:59:59', '2026-10-21 09:00:00'],
            ['completed', 800, '2026-11-01 00:00:00', '2026-10-21 09:00:00'],
            ['rejected', 700, '2026-10-10 09:00:00', '2026-10-10 09:00:00'],
            ['ready_for_pickup', 600, null, '2026-10-21 09:00:00'],
        ] as [$status, $value, $closedAt, $updatedAt]) {
            $this->requisition($owner, $status, [
                'grand_total' => $value, 'closed_at' => $closedAt, 'updated_at' => $updatedAt,
            ]);
        }

        foreach ([[], ['X-Workspace-Refresh' => 'true']] as $headers) {
            $response = $this->actingAs($smo)->get(route('dashboard'), $headers)->assertOk();
            $this->assertEquals(1301.25, $response->viewData('stats')['monthly_value']);
            $response->assertSee('>1,301.25</span>', false);
        }
    }

    public function test_approval_followups_exclude_fulfillment_but_the_deadline_monitor_includes_every_active_stage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $riskDates = [
            'submitted_at' => now()->subDays(7), 'sla_warning_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
        ];
        $approvals = collect();
        foreach (range(1, 7) as $number) {
            $approvals->push($this->requisition($owner, 'pending', $riskDates));
        }
        $majorVp = $this->requisition($owner, 'approved_vp', [...$riskDates, 'request_type' => 'major']);
        $fulfillment = $this->requisition($owner, 'processing', [...$riskDates, 'due_at' => now()->subDays(2)]);
        $this->requisition($owner, 'ready_for_pickup', $riskDates);
        $this->requisition($owner, 'approved_vp', $riskDates);
        $this->requisition($owner, 'for_clarification', $riskDates);
        $this->requisition($owner, 'completed', $riskDates);
        $this->requisition($owner, 'rejected', $riskDates);

        $response = $this->actingAs($smo)->get(route('dashboard'))->assertOk()
            ->assertViewHas('approvalDeadlineCount', 8)
            ->assertViewHas('approvalDeadlineRequests', fn ($requests) => $requests->modelKeys() === $approvals->take(6)->pluck('id')->all())
            ->assertViewHas('deadlineRequests', fn ($requests) => $requests->count() === 4 && $requests->first()->is($fulfillment))
            ->assertSee('Showing the earliest 6 of 8 approval follow-ups.')
            ->assertSee('Showing 4 of 12 deadline risks')
            ->assertDontSee(route('admin.requests.deadline-reminder', $fulfillment), false)
            ->assertSee(route('requests.index', ['status' => 'deadline']), false)
            ->assertSee('working days since submission')
            ->assertDontSee('in this stage');

        $this->assertSame(12, $response->viewData('stats')['urgent']);
        $this->assertTrue($majorVp->isAwaitingAdministrativeApproval());
    }

    public function test_active_preview_has_deterministic_order_and_tier_specific_status_labels(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo', 'name' => 'Dashboard Officer']);
        $owner = User::factory()->create(['name' => 'Érica Requestor']);
        $first = null;
        foreach (range(1, 11) as $number) {
            $record = $this->requisition($owner, $number === 11 ? 'approved_vp' : 'pending', [
                'request_type' => $number === 11 ? 'major' : 'minor',
            ]);
            $first ??= $record;
        }
        $minor = $this->requisition($owner, 'approved_vp');
        $archived = $this->requisition($owner, 'completed');

        $response = $this->actingAs($smo)->get(route('dashboard'))->assertOk()
            ->assertViewHas('allStaffRequests', fn ($requests) => $requests->count() === 10 && $requests->first()->is($minor))
            ->assertSee('Latest 10 of 12 active requests.')
            ->assertSee('Welcome back, Dashboard.')
            ->assertSee('Provost review')
            ->assertSee('Awaiting SMO processing')
            ->assertSee('class="status-pill pending">Provost review', false)
            ->assertDontSee($first->tracking_code)
            ->assertDontSee($archived->tracking_code);

        $this->assertSame(13, $response->viewData('stats')['total']);
    }

    public function test_stock_warnings_include_threshold_equality_prioritize_empty_stock_and_exclude_deleted_items(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $empty = $this->supply('Empty inventory', 0, 0);
        $this->supply('Threshold equality', 4, 4);
        foreach (range(1, 4) as $number) {
            $this->supply('Low inventory '.$number, $number, 10);
        }
        $this->supply('Healthy inventory', 20, 10);
        $deleted = $this->supply('Deleted inventory', 0, 50);
        $deleted->delete();

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->actingAs($smo)->get(route('dashboard'))->assertOk()
                ->assertViewHas('lowStockSupplies', fn ($supplies) => $supplies->count() === 4 && $supplies->first()->is($empty))
                ->assertSee('1 out of stock')
                ->assertSee('Showing 4 of 6 stock warnings')
                ->assertDontSee('Deleted inventory');
            $unitQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "supply_units"'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(6, $response->viewData('stats')['low_stock']);
        $this->assertCount(1, $unitQueries, 'Units should be loaded in one query for the stock preview.');
    }

    public function test_empty_dashboard_is_healthy_and_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $smo = User::factory()->create(['role' => 'smo']);
        $response = $this->actingAs($smo)->get(route('dashboard'))->assertOk()
            ->assertSee('No active requisitions')
            ->assertSee('No approval deadlines need attention')
            ->assertSee('No low-stock items')
            ->assertSee('No recent alerts')
            ->assertSee('>0.00</span>', false);

        $this->assertSame(0, $response->viewData('stats')['urgent']);
    }

    public function test_dashboard_refresh_version_tracks_time_and_own_notifications_without_refreshing_other_workspaces(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $other = User::factory()->create(['role' => 'smo']);
        $this->actingAs($smo);
        $before = $this->getJson(route('api.runtime-state', ['scope' => 'dashboard']))->assertOk()->json();
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-runtime-scope="dashboard"', false)
            ->assertSee('data-runtime-version="'.$before['version'].'"', false);

        Notification::create(['user_id' => $other->id, 'title' => 'Private other alert', 'message' => 'Other officer only', 'is_read' => false]);
        $this->getJson(route('api.runtime-state', ['scope' => 'dashboard']))->assertOk()->assertJsonPath('version', $before['version']);

        $notification = Notification::create([
            'user_id' => $smo->id, 'title' => 'Dashboard alert', 'message' => 'Refresh the activity feed',
            'icon' => 'invalid-icon', 'type' => 'invalid-type', 'is_read' => false,
        ]);
        $after = $this->getJson(route('api.runtime-state', ['scope' => 'dashboard']))->assertOk()->json();
        $this->assertNotSame($before['version'], $after['version']);
        $this->assertSame($before['versions']['requests'], $after['versions']['requests']);
        $this->get(route('dashboard'), ['X-Workspace-Refresh' => 'true'])->assertOk()
            ->assertSee('Dashboard alert')->assertDontSee('Private other alert')
            ->assertSee('class="activity-icon info"', false)->assertDontSee('data-lucide="invalid-icon"', false);
        $this->assertFalse($notification->fresh()->is_read);

        $this->travel(1)->minutes();
        $later = $this->getJson(route('api.runtime-state', ['scope' => 'dashboard']))->assertOk()->json();
        $this->assertNotSame($after['version'], $later['version']);
        $this->assertSame($after['versions']['inventory'], $later['versions']['inventory']);
    }

    private function requisition(User $owner, string $status, array $attributes = []): Requisition
    {
        $requisition = Requisition::create([
            'user_id' => $owner->id, 'request_type' => 'minor', 'status' => $status,
            'purpose' => 'SMO dashboard regression fixture', 'grand_total' => 100,
        ]);
        $requisition->forceFill($attributes)->saveQuietly();

        return $requisition;
    }

    private function supply(string $name, int $quantity, int $minimum): Supply
    {
        return Supply::create([
            'item_name' => $name, 'category' => 'Office Supplies', 'requisition_tier' => 'minor',
            'quantity' => $quantity, 'min_stock_level' => $minimum, 'unit' => 'Piece', 'unit_price' => 1,
        ]);
    }
}
