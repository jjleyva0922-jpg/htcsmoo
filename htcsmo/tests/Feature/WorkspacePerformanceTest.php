<?php

namespace Tests\Feature;

use App\Jobs\SendRequisitionEmail;
use App\Mail\RequisitionAlertMail;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use App\Services\InventoryForecastService;
use App\Services\WorkspaceVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkspacePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_idle_runtime_poll_does_not_query_business_tables(): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->getJson(route('api.runtime-state'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(0, $queries->filter(fn ($sql) => preg_match('/from ["`]?(requisitions|supplies|approval_logs)["`]?\b/i', $sql)));
        $this->assertLessThanOrEqual(3, $queries->count());
    }

    public function test_inventory_changes_do_not_invalidate_request_workspaces(): void
    {
        $versions = app(WorkspaceVersionService::class);
        $before = $versions->versions();
        $this->supply();
        $after = $versions->versions();

        $this->assertNotSame($before['inventory'], $after['inventory']);
        $this->assertSame($before['requests'], $after['requests']);
        $this->assertNotSame($before['all'], $after['all']);
    }

    public function test_rolled_back_changes_do_not_invalidate_cached_workspaces(): void
    {
        $versions = app(WorkspaceVersionService::class);
        $before = $versions->version();
        DB::beginTransaction();
        $this->supply();
        $this->assertSame($before, $versions->version());
        DB::rollBack();
        $this->assertSame($before, $versions->version());
    }

    public function test_background_refresh_omits_layout_scripts_and_preserves_authorization(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $full = $this->actingAs($smo)->get(route('dashboard'))->assertOk();
        $fragment = $this->actingAs($smo)->withHeader('X-Workspace-Refresh', 'true')
            ->get(route('dashboard'))->assertOk()
            ->assertSee('data-live-region="workspace"', false)
            ->assertDontSee('function notificationCenter()', false)
            ->assertDontSee('id="appSidebar"', false);

        $this->assertLessThan(strlen($full->getContent()) / 2, strlen($fragment->getContent()));
        $employee = User::factory()->create();
        $this->actingAs($employee)->get(route('inventory.index'))->assertRedirect(route('dashboard'));
    }

    public function test_forecast_cache_avoids_reloading_history_and_invalidates_on_release(): void
    {
        $supply = $this->supply();
        $user = User::factory()->create(['role' => 'smo']);
        $service = app(InventoryForecastService::class);
        $service->build(collect([$supply]));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $cached = $service->build(collect([$supply]));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(0, $cached['release_events']);

        SupplyInventoryLog::create([
            'supply_id' => $supply->id, 'user_id' => $user->id,
            'transaction_type' => 'release_deduction', 'quantity_change' => -6,
            'balance_after' => 4, 'remarks' => 'Cache invalidation test',
        ]);
        $fresh = $service->build(collect([$supply]));
        $this->assertSame(1, $fresh['release_events']);
        $this->assertGreaterThan(0, $fresh['all']->first()['forecast_demand']);
        $supply->decrement('quantity', 2);
        $this->assertSame(8, $service->build(collect([$supply]))['all']->first()['supply']->quantity);
    }

    public function test_approval_queue_is_paginated_without_losing_notification_deep_links(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $first = null;
        foreach (range(1, 17) as $number) {
            $record = Requisition::create([
                'user_id' => $owner->id, 'request_type' => 'minor', 'status' => 'approved_vp',
                'purpose' => 'Performance fixture '.$number, 'grand_total' => 50,
            ]);
            $first ??= $record;
        }
        $response = $this->actingAs($smo)->get(route('admin.approvals', ['request' => $first->id]))->assertOk();
        $this->assertSame(17, $response->viewData('pendingMinor')->total());
        $this->assertSame(15, $response->viewData('pendingMinor')->count());
        $this->assertSame($first->id, $response->viewData('focusedRequest')->id);
        $this->assertStringContainsString('tab=minor', $response->viewData('pendingMinor')->url(2));
        $this->assertStringNotContainsString('request=', $response->viewData('pendingMinor')->url(2));
    }

    public function test_report_preview_is_paginated_and_export_includes_all_matches(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        User::factory()->count(52)->create();
        $parameters = ['report_type' => 'users', 'period' => 'all'];
        $response = $this->actingAs($smo)->get(route('admin.reports.builder', $parameters))->assertOk();
        $this->assertSame(53, $response->viewData('pagination')->total());
        $this->assertCount(50, $response->viewData('rows'));
        $response->assertSee('This page:')->assertSee('exports include all matches');
        $export = $this->actingAs($smo)->get(route('admin.reports.builder', [...$parameters, 'export' => 'csv']))->assertOk();
        $this->assertCount(54, array_filter(explode("\n", trim($export->streamedContent()))));
    }

    public function test_email_is_queued_instead_of_blocking_approval_and_logs_delivery_in_the_job(): void
    {
        Queue::fake();
        Mail::fake();
        $owner = User::factory()->create();
        $approver = User::factory()->create(['role' => 'vp_finance']);
        $requisition = Requisition::create([
            'user_id' => $owner->id, 'request_type' => 'minor', 'status' => 'approved_dept',
            'purpose' => 'Email performance fixture', 'grand_total' => 50,
        ]);
        $this->actingAs($approver)->post(route('admin.status.update', $requisition), ['status' => 'approved'])->assertRedirect();
        Queue::assertPushedOn('mail', SendRequisitionEmail::class);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_logs', 0);
        $job = Queue::pushed(SendRequisitionEmail::class)->first();
        $job->handle();
        Mail::assertSent(RequisitionAlertMail::class);
        $this->assertDatabaseHas('email_logs', ['recipient_email' => $owner->email, 'status' => 'sent']);
    }

    private function supply(): Supply
    {
        return Supply::create([
            'item_name' => 'Performance Test Paper', 'category' => 'Office Supplies',
            'requisition_tier' => 'minor', 'quantity' => 10, 'unit' => 'Ream(s)',
            'unit_price' => 50, 'min_stock_level' => 2,
        ]);
    }

    public function test_exhausted_email_job_records_a_failure(): void
    {
        $job = new SendRequisitionEmail('recipient@example.test', null, 123, 'Delivery test', 'Test', 'Test message', '#185b3b');
        $job->failed(new \RuntimeException('SMTP unavailable'));

        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'recipient@example.test', 'status' => 'failed',
            'error_message' => 'SMTP unavailable',
        ]);
    }
}
