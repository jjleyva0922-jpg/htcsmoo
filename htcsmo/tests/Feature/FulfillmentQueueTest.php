<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\DigitalSignature;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FulfillmentQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    }

    public static function routingPaths(): array
    {
        $cases = [];
        foreach (['employee' => 'pending', 'smo' => 'pending', 'dept_head' => 'approved_dept', 'vp_admin' => 'approved_vp', 'vp_finance' => 'approved_vp', 'provost' => 'approved_president', 'president' => 'approved_president'] as $role => $status) {
            foreach (['minor', 'major'] as $tier) {
                $eligible = $status === 'approved_president' || ($status === 'approved_vp' && $tier === 'minor');
                $cases[$role.' '.$tier] = [$role, $tier, $status, $eligible];
            }
        }

        return $cases;
    }

    #[DataProvider('routingPaths')]
    public function test_initial_routing_queue_visibility_ui_and_processing_agree(string $role, string $tier, string $status, bool $eligible): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['role' => $role]);
        $supply = $this->supply(['requisition_tier' => $tier, 'unit_price' => $tier === 'major' ? 1500 : 50]);
        $this->actingAs($owner)->post(route('requisitions.store'), [
            'request_type' => $tier, 'purpose' => 'Supplies for official institutional operations.',
            'items' => [['supply_id' => $supply->id, 'qty' => 1]],
        ])->assertRedirect(route('dashboard'));
        $requisition = Requisition::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame($status, $requisition->status);
        $this->assertSame($eligible, $requisition->canStartProcessing());
        $this->assertSame($eligible, $requisition->canBeReviewedBy($smo));
        $page = $this->actingAs($smo)->get(route('admin.approvals', ['request' => $requisition->id]))->assertOk();
        $this->assertSame($eligible ? 1 : 0, $page->viewData($tier === 'minor' ? 'pendingMinor' : 'pendingMajor')->total());
        $this->assertSame($eligible, $page->viewData('focusedRequest') !== null);
        if ($eligible) {
            $this->assertTrue($page->viewData('focusedRequest')->can_start_processing);
            $this->assertDatabaseHas('notifications', ['user_id' => $smo->id, 'event_type' => 'requisition.ready_for_release', 'action_id' => $requisition->id]);
        }
        $this->post(route('admin.requests.processing', $requisition))->assertSessionHas($eligible ? 'success' : 'error');
        $this->assertSame($eligible ? 'processing' : $status, $requisition->fresh()->status);
        $this->assertSame(10, $supply->fresh()->quantity, 'Starting processing must not deduct stock.');
        $this->assertSame($eligible ? 1 : 0, ApprovalLog::where('requisition_id', $requisition->id)->count());
    }

    public function test_groups_filters_counts_pagination_and_notification_focus_are_consistent(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => 'CETE', 'name' => 'Needle Requestor']);
        $first = null;
        foreach (range(1, 19) as $i) {
            $first ??= $this->requisition($owner);
            if ($i > 1) {
                $this->requisition($owner);
            }
        }
        $major = $this->requisition($owner, ['request_type' => 'major', 'status' => 'approved_president']);
        $this->requisition($owner, ['status' => 'processing']);
        $this->requisition($owner, ['request_type' => 'major', 'status' => 'ready_for_pickup']);
        $this->requisition($owner, ['request_type' => 'major', 'status' => 'approved_vp']); // Not fully approved.
        $this->requisition($owner, ['status' => 'completed']);
        $clarification = $this->requisition($owner, ['status' => 'for_clarification']);
        ApprovalLog::create(['requisition_id' => $clarification->id, 'action' => 'Flagged for Clarification', 'role' => 'vp_admin', 'remarks' => 'Please explain the purpose.']);
        $this->requisition(User::factory()->create(['department' => 'CTE']), ['purpose' => 'Other department']);
        $this->actingAs($smo);
        $filters = ['search' => 'Needle', 'dept' => 'CETE', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'sort' => 'oldest', 'tab' => 'major'];
        $response = $this->get(route('admin.approvals', [...$filters, 'request' => $major->id]))->assertOk()
            ->assertSee('Requested amount')->assertSee('Overdue')->assertSee('data-dynamic-search', false);
        $this->assertSame(19, $response->viewData('pendingMinor')->total());
        $this->assertCount(15, $response->viewData('pendingMinor'));
        $this->assertSame(1, $response->viewData('pendingMajor')->total());
        $this->assertSame(2, $response->viewData('activeWork')->total());
        $this->assertSame(1, $response->viewData('clarificationRequests')->total());
        $this->assertSame('major', $response->viewData('selectedTab'));
        $this->assertSame($major->id, $response->viewData('focusedRequest')->id);
        parse_str(parse_url($response->viewData('pendingMinor')->url(2), PHP_URL_QUERY), $next);
        $this->assertSame('Needle', $next['search']);
        $this->assertSame('minor', $next['tab']);
        $this->assertArrayNotHasKey('request', $next);
        $this->get(route('admin.approvals', $next))->assertOk()->assertViewHas('pendingMinor', fn ($rows) => $rows->count() === 4);
        $this->get(route('admin.approvals', [...$next, 'minor_page' => 999]))->assertOk()
            ->assertViewHas('pendingMinor', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 4);
        $this->get(route('admin.approvals', [...$filters, 'status' => 'processing', 'tab' => 'work']))->assertOk()
            ->assertViewHas('pendingMinor', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('activeWork', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.approvals', [...$filters, 'tier' => 'major']))->assertOk()
            ->assertViewHas('pendingMinor', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('pendingMajor', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('activeWork', fn ($rows) => $rows->total() === 1);
        $this->line($first, null, 1, 'Searchable queue item');
        $this->get(route('admin.approvals', ['search' => 'Searchable queue item']))->assertOk()
            ->assertViewHas('pendingMinor', fn ($rows) => $rows->pluck('id')->all() === [$first->id]);
        $this->get(route('admin.approvals', ['search' => $first->tracking_code]))->assertOk()->assertViewHas('pendingMinor', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.approvals', ['search' => 'no matching record']))->assertOk()->assertSee('No requisitions match this group');
    }

    public function test_sort_uses_submission_date_and_deadline_risk_with_stable_ties(): void
    {
        $owner = User::factory()->create();
        $old = $this->requisition($owner, ['submitted_at' => '2026-10-01 08:00:00']);
        $new = $this->requisition($owner, ['submitted_at' => '2026-10-02 08:00:00']);
        $risk = $this->requisition($owner, ['submitted_at' => '2026-10-03 08:00:00']);
        $risk->forceFill(['sla_warning_at' => '2026-10-01 08:00:00', 'due_at' => '2026-10-01 09:00:00'])->saveQuietly();
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        foreach (['oldest' => [$old->id, $new->id, $risk->id], 'newest' => [$risk->id, $new->id, $old->id], 'deadline' => [$risk->id, $old->id, $new->id]] as $sort => $ids) {
            $page = $this->get(route('admin.approvals', ['sort' => $sort]))->assertOk();
            $this->assertSame($ids, $page->viewData('pendingMinor')->pluck('id')->all());
        }
    }

    public function test_filter_validation_and_signatory_department_isolation_also_apply_to_clarification(): void
    {
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $own = User::factory()->create(['department' => 'CETE']);
        $other = User::factory()->create(['department' => 'CTE']);
        $this->requisition($own, ['status' => 'pending']);
        $hidden = $this->requisition($other, ['status' => 'pending']);
        foreach ([$own, $other] as $owner) {
            $inquiry = $this->requisition($owner, ['status' => 'for_clarification']);
            ApprovalLog::create(['requisition_id' => $inquiry->id, 'action' => 'Flagged for Clarification', 'role' => 'dept_head']);
        }
        $this->actingAs($head)->get(route('admin.approvals', ['request' => $hidden->id]))->assertOk()
            ->assertViewHas('pendingRequests', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('clarificationRequests', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('focusedRequest', null);
        $this->post(route('admin.status.update', $inquiry), ['status' => 'approved'])->assertForbidden();
        $this->assertSame('for_clarification', $inquiry->fresh()->status);
        foreach ([['date_from' => '2026-10-01'], ['date_from' => '2026-10-02', 'date_to' => '2026-10-01'], ['sort' => 'invalid'], ['minor_page' => 0]] as $invalid) {
            $this->getJson(route('admin.approvals', $invalid))->assertUnprocessable();
        }
        $this->get(route('admin.approvals', ['date_to' => '2026-10-01']), ['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest', 'X-Filter-Request' => 'true'])->assertUnprocessable()->assertJsonValidationErrors('date_from');
    }

    public function test_review_reports_combined_shortages_and_server_rechecks_changed_inventory(): void
    {
        $supply = $this->supply(['quantity' => 6]);
        $request = $this->requisition(User::factory()->create(), ['status' => 'processing']);
        $this->line($request, $supply, 3);
        $this->line($request, $supply, 3);
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $page = $this->get(route('admin.approvals', ['request' => $request->id]))->assertOk();
        $review = $page->viewData('focusedRequest');
        $this->assertSame([], $review->stock_errors);
        $this->assertSame(6, $review->items->first()->stock_readiness['requested_total']);
        $supply->update(['quantity' => 5]);
        $page = $this->get(route('admin.approvals', ['request' => $request->id]))->assertOk();
        $this->assertTrue($page->viewData('focusedRequest')->items->first()->stock_readiness['shortage']);
        $this->from(route('admin.approvals', ['tab' => 'work']))->post(route('admin.requests.ready-for-pickup', $request))
            ->assertSessionHas('error', "Insufficient stock for 'Queue paper'. Available: 5, Requested: 6.");
        $this->assertSame(5, $supply->fresh()->quantity);
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertDatabaseMissing('supply_inventory_logs', ['requisition_id' => $request->id]);
    }

    public function test_duplicate_lines_deduct_once_and_pickup_records_officer_timestamp_and_notification(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $request = $this->requisition($owner, ['status' => 'processing']);
        $supply = $this->supply();
        $this->line($request, $supply, 2);
        $this->line($request, $supply, 3);
        $this->actingAs($smo)->post(route('admin.requests.ready-for-pickup', $request))->assertSessionHas('success');
        $this->assertSame(5, $supply->fresh()->quantity);
        $this->assertDatabaseHas('supply_inventory_logs', ['requisition_id' => $request->id, 'quantity_change' => -5, 'balance_after' => 5, 'user_id' => $smo->id]);
        $this->assertSame(1, SupplyInventoryLog::where('requisition_id', $request->id)->count());
        $ready = ApprovalLog::where('requisition_id', $request->id)->firstOrFail();
        $this->assertDatabaseHas('digital_signatures', ['approval_log_id' => $ready->id, 'signer_name' => $smo->name]);
        $this->post(route('admin.requests.ready-for-pickup', $request))->assertSessionHas('error');
        $this->post(route('admin.requests.complete', $request))->assertSessionHas('success');
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertTrue($request->fresh()->closed_at->equalTo(now()));
        $completed = ApprovalLog::where('requisition_id', $request->id)->where('action', 'Completed by SMO In-Charge')->firstOrFail();
        $this->assertSame($smo->id, $completed->acted_by_user_id);
        $this->assertTrue($completed->created_at->equalTo(now()));
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'event_type' => 'requisition.completed', 'action_id' => $request->id]);
        $this->post(route('admin.requests.complete', $request))->assertSessionHas('error');
        $this->assertSame(5, $supply->fresh()->quantity);
        $this->assertSame(2, ApprovalLog::where('requisition_id', $request->id)->count());
    }

    public function test_missing_deleted_empty_and_unmatched_legacy_items_cannot_be_silently_skipped(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $owner = User::factory()->create();
        $supply = $this->supply();
        $deleted = $this->supply(['item_name' => 'Deleted item']);
        $deleted->delete();
        foreach (['deleted', 'unmatched', 'empty'] as $case) {
            $request = $this->requisition($owner, ['status' => 'processing']);
            if ($case !== 'empty') {
                $this->line($request, $supply, 2);
                $this->line($request, $case === 'deleted' ? $deleted : null, 1, 'Unknown legacy item');
            }
            $this->post(route('admin.requests.ready-for-pickup', $request))->assertSessionHas('error');
            $this->assertSame('processing', $request->fresh()->status);
            $this->assertSame(10, $supply->fresh()->quantity);
            $this->assertDatabaseMissing('supply_inventory_logs', ['requisition_id' => $request->id]);
            $this->assertDatabaseMissing('approval_logs', ['requisition_id' => $request->id]);
        }
        // Exact legacy name matching still supports older catalog-linked data.
        $legacy = $this->requisition($owner, ['status' => 'processing']);
        $this->line($legacy, null, 2, 'QUEUE PAPER');
        $this->post(route('admin.requests.ready-for-pickup', $legacy))->assertSessionHas('success');
        $this->assertSame(8, $supply->fresh()->quantity);
    }

    public function test_invalid_transitions_and_non_smo_actions_never_change_inventory_or_status(): void
    {
        $owner = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $request = $this->requisition($owner, ['request_type' => 'major', 'status' => 'approved_vp']);
        $this->actingAs($smo);
        foreach (['processing', 'ready-for-pickup', 'complete'] as $action) {
            $this->post(route('admin.requests.'.$action, $request))->assertSessionHas('error');
        }
        $this->assertSame('approved_vp', $request->fresh()->status);
        foreach (['pending', 'approved_dept', 'approved_provost', 'for_clarification', 'rejected', 'withdrawn', 'completed', 'released'] as $status) {
            $invalid = $this->requisition($owner, ['status' => $status]);
            foreach (['processing', 'ready-for-pickup', 'complete'] as $action) {
                $this->post(route('admin.requests.'.$action, $invalid))->assertSessionHas('error');
            }
            $this->assertSame($status, $invalid->fresh()->status);
        }
        foreach (['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['processing', 'ready-for-pickup', 'complete'] as $action) {
                $this->post(route('admin.requests.'.$action, $request))->assertSessionHas('error');
            }
        }
        $this->actingAs($owner)->get(route('admin.approvals'))->assertRedirect(route('dashboard'));
        $this->post(route('admin.requests.processing', $request))->assertRedirect(route('dashboard'));
        $this->assertSame('approved_vp', $request->fresh()->status);
        $this->assertDatabaseCount('approval_logs', 0);
        $this->assertDatabaseCount('supply_inventory_logs', 0);
    }

    public function test_audit_or_movement_failure_rolls_back_all_fulfillment_writes_and_reports_failure(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $supply = $this->supply();
        foreach ([SupplyInventoryLog::class, ApprovalLog::class, DigitalSignature::class] as $model) {
            $request = $this->requisition(User::factory()->create(), ['status' => 'processing']);
            $this->line($request, $supply, 2);
            $events = $model::getEventDispatcher();
            $model::setEventDispatcher(clone $events);
            $model::creating(fn () => throw new \RuntimeException('Simulated audit failure'));
            try {
                $this->post(route('admin.requests.ready-for-pickup', $request))->assertSessionHas('error')->assertSessionMissing('success');
            } finally {
                $model::setEventDispatcher($events);
            }
            $this->assertSame(10, $supply->fresh()->quantity);
            $this->assertSame('processing', $request->fresh()->status);
            $this->assertDatabaseMissing('supply_inventory_logs', ['requisition_id' => $request->id]);
            $this->assertDatabaseMissing('approval_logs', ['requisition_id' => $request->id]);
            $this->assertDatabaseMissing('notifications', ['action_id' => $request->id, 'event_type' => 'requisition.ready_for_pickup']);
        }
    }

    public function test_review_serializes_special_characters_safely_and_actions_preserve_validated_queue_navigation(): void
    {
        $department = Department::create(['dept_code' => 'R&D "O\'Neil"', 'dept_name' => 'Special department']);
        $owner = User::factory()->create(['name' => 'O\'Neil "<script>" & José', 'department_id' => $department->id]);
        $request = $this->requisition($owner);
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $page = $this->get(route('admin.approvals', ['request' => $request->id]))->assertOk()->assertSee($owner->name);
        $this->assertSame($department->dept_code, $page->viewData('focusedRequest')->queue_requestor['department']);
        $this->assertStringContainsString((string) Js::from($page->viewData('focusedRequest')->toArray()), $page->getContent());
        $this->assertStringNotContainsString('addslashes', file_get_contents(resource_path('views/admin/partials/approvals-table.blade.php')));
        $filters = ['search' => 'Queue', 'dept' => $department->dept_code, 'tab' => 'minor', 'minor_page' => 2, 'sort' => 'oldest'];
        $redirect = $this->post(route('admin.requests.processing', $request), ['queue_query' => http_build_query($filters)])->assertRedirect();
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $returnedFilters);
        $this->assertEquals($filters, $returnedFilters);
        $this->assertSame('approval-results', parse_url($redirect->headers->get('Location'), PHP_URL_FRAGMENT));
        $request->fresh()->update(['status' => 'approved_vp']);
        $this->post(route('admin.requests.processing', $request), ['queue_query' => 'tab=minor&return=https://evil.test'])
            ->assertRedirect(route('admin.approvals', ['tab' => 'minor']).'#approval-results');
    }

    private function requisition(User $owner, array $attributes = []): Requisition
    {
        return Requisition::create([...[
            'user_id' => $owner->id, 'request_type' => 'minor', 'status' => 'approved_vp',
            'purpose' => 'Queue test supplies', 'grand_total' => 100, 'submitted_at' => '2026-10-01 08:00:00',
        ], ...$attributes]);
    }

    private function supply(array $attributes = []): Supply
    {
        return Supply::create([...[
            'item_name' => 'Queue paper', 'requisition_tier' => 'minor', 'quantity' => 10,
            'unit' => 'Ream', 'unit_price' => 50, 'min_stock_level' => 1,
        ], ...$attributes]);
    }

    private function line(Requisition $request, ?Supply $supply, int $quantity, string $name = 'Legacy item'): void
    {
        $request->items()->create(['supply_id' => $supply?->id, 'item_name' => $supply?->item_name ?? $name, 'quantity' => $quantity, 'unit' => 'Ream', 'unit_price' => 50, 'subtotal' => 50 * $quantity]);
    }
}
