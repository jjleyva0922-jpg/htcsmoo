<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Notification;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRequestExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
    }

    public function test_personal_metrics_are_current_states_and_later_edits_do_not_become_approval_events(): void
    {
        $employee = User::factory()->create(['name' => 'Faculty Owner']);
        $other = User::factory()->create();
        $this->requisition($employee, 'pending');
        $clarification = $this->requisition($employee, 'for_clarification');
        $pickup = $this->requisition($employee, 'ready_for_pickup', ['created_at' => now()->subMonth()]);
        $approved = $this->requisition($employee, 'approved_vp');
        $log = ApprovalLog::create(['requisition_id' => $approved->id, 'action' => 'Approved by VP Finance', 'role' => 'vp_finance']);
        $log->forceFill(['created_at' => now()->subWeek()])->saveQuietly();
        $completed = $this->requisition($employee, 'completed', ['closed_at' => now()->subMonth()]);
        $completed->update(['remarks' => 'An unrelated edit today']);
        $approved->update(['remarks' => 'An unrelated edit to an old approval']);
        $this->requisition($employee, 'rejected');
        $private = $this->requisition($other, 'ready_for_pickup');
        $this->requisition($other, 'for_clarification');

        $response = $this->actingAs($employee)->get(route('dashboard'))->assertOk()
            ->assertViewHas('activeCount', 4)->assertViewHas('actionCount', 2)
            ->assertSee($clarification->tracking_code)->assertSee($pickup->tracking_code)
            ->assertDontSee($private->tracking_code)->assertDontSee($completed->tracking_code)
            ->assertDontSee('Approved and ready')->assertDontSee('Last 24 hours')
            ->assertDontSee('Operational Analytics')->assertDontSee('Total requisitions');
        $this->assertEquals(1, $response->viewData('personalCounts')->pickup);
        $this->assertEquals(1, $response->viewData('personalCounts')->clarification);
        $this->get(route('requests.show', $private))->assertForbidden();
        $this->get(route('requests.download', $private))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_preview_prioritizes_actions_then_deadlines_and_discloses_limits(): void
    {
        $employee = User::factory()->create();
        foreach (range(1, 10) as $n) {
            $this->requisition($employee, 'pending');
        }
        $near = $this->requisition($employee, 'processing', [
            'sla_warning_at' => now(), 'due_at' => now()->addDay(), 'submitted_at' => now()->subDays(2),
        ]);
        $overdue = $this->requisition($employee, 'approved_dept', [
            'sla_warning_at' => now()->subDay(), 'due_at' => now(), 'submitted_at' => now()->subDays(3),
        ]);
        $pickup = $this->requisition($employee, 'ready_for_pickup', ['submitted_at' => now()->subWeek()]);
        $clarification = $this->requisition($employee, 'for_clarification', ['submitted_at' => now()->subWeek()]);
        $response = $this->actingAs($employee)->get(route('dashboard'))->assertOk()
            ->assertSee('Showing 8 of 14 active requests.')
            ->assertSee('At or past SLA deadline')->assertSee('Nearing SLA deadline')
            ->assertSee('target, not a guaranteed pickup date')
            ->assertSee(route('requests.index', ['status' => 'action_required']))
            ->assertSee(route('requests.archive'));
        $this->assertSame(
            [$clarification->id, $pickup->id, $overdue->id, $near->id],
            $response->viewData('recentRequests')->take(4)->modelKeys(),
        );
        foreach (range(1, 4) as $n) {
            $this->requisition($employee, 'for_clarification');
        }
        $this->get(route('dashboard'))->assertOk()->assertViewHas('actionCount', 6)
            ->assertViewHas('actionRequests', fn ($requests) => $requests->count() === 4)
            ->assertSee('Showing 4 of 6 action requests. View all.');
    }

    public function test_tier_aware_stages_are_consistent_on_dashboard_list_and_details(): void
    {
        $employee = User::factory()->create();
        $cases = [
            ['approved_dept', 'minor', 'VP Finance review'],
            ['approved_dept', 'major', 'VP Administration review'],
            ['approved_vp', 'minor', 'Awaiting SMO processing'],
            ['approved_vp', 'major', 'Provost review'],
            ['approved_provost', 'major', 'President review'],
            ['approved_president', 'major', 'Awaiting SMO processing'],
            ['processing', 'minor', 'SMO processing'],
        ];
        $requests = collect($cases)->map(fn ($case) => $this->requisition($employee, $case[0], ['request_type' => $case[1]]));
        $this->actingAs($employee);
        foreach (['dashboard', 'requests.index'] as $route) {
            $response = $this->get(route($route))->assertOk();
            foreach ($cases as $case) {
                $response->assertSee($case[2]);
            }
            $response->assertDontSee('>approved vp</span>', false)->assertDontSee('>approved dept</span>', false);
        }
        foreach ($requests as $index => $request) {
            $this->get(route('requests.show', $request))->assertOk()
                ->assertSee($cases[$index][2])->assertSee('Your next step')->assertSee('Requested value');
        }
    }

    public function test_clarification_uses_latest_clarification_log_and_shows_safe_remarks_and_next_steps(): void
    {
        $employee = User::factory()->create();
        $request = $this->requisition($employee, 'for_clarification', ['remarks' => 'Stale header remarks', 'request_type' => 'major']);
        ApprovalLog::create(['requisition_id' => $request->id, 'action' => 'Flagged for Office Clarification', 'role' => 'vp_admin', 'remarks' => 'Old inquiry']);
        $remarks = 'Confirm the laboratory specification <script>alert(1)</script>';
        ApprovalLog::create(['requisition_id' => $request->id, 'action' => 'Flagged for Office Clarification', 'role' => 'provost', 'remarks' => $remarks]);
        ApprovalLog::create(['requisition_id' => $request->id, 'action' => 'Administrative note', 'role' => 'dept_head', 'remarks' => 'Not a clarification']);
        $this->actingAs($employee);
        foreach (['dashboard', 'requests.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Clarification required')
                ->assertSee('Confirm the laboratory specification')
                ->assertDontSee('Stale header remarks')->assertDontSee('Old inquiry')
                ->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->get(route('requests.show', $request))->assertOk()->assertSee($remarks)
            ->assertSee('Provost inquiry')->assertSee('Clarification remarks and next step')
            ->assertSee('reviewing office and SMO')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_notifications_are_personal_request_related_and_keep_authorized_open_and_unread_behavior(): void
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->requisition($employee, 'ready_for_pickup');
        $private = $this->requisition($other, 'pending');
        $note = $this->notification($employee, 'Your supplies are ready', $own->id);
        $foreign = $this->notification($other, 'Private employee notification', $private->id);
        $this->notification($employee, 'Misaddressed private request', $private->id);
        $missing = $this->notification($employee, 'Old request is unavailable', 999999);
        Notification::create(['user_id' => $employee->id, 'title' => 'Account-only update', 'message' => 'Not a request notification', 'event_type' => 'account.updated', 'is_read' => false]);
        $this->actingAs($employee)->get(route('dashboard'))->assertOk()
            ->assertSee('Your supplies are ready')->assertSee('Unread')
            ->assertSee(route('notifications.open', $note))->assertSee('Old request is unavailable')
            ->assertDontSee(route('notifications.open', $missing))
            ->assertDontSee('Private employee notification')->assertDontSee('Misaddressed private request')
            ->assertDontSee('Account-only update');
        $this->assertFalse($note->fresh()->is_read);
        $this->post(route('notifications.open', $note))->assertRedirect(route('requests.show', $own));
        $this->assertTrue($note->fresh()->is_read);
        $this->post(route('notifications.open', $foreign))->assertForbidden();
        $this->assertFalse($foreign->fresh()->is_read);
    }

    public function test_action_filters_pagination_and_archive_separation_remain_personal(): void
    {
        $employee = User::factory()->create();
        foreach (range(1, 17) as $n) {
            $this->requisition($employee, 'ready_for_pickup');
        }
        $this->requisition($employee, 'for_clarification');
        $this->requisition($employee, 'processing');
        $completed = $this->requisition($employee, 'completed');
        $private = $this->requisition(User::factory()->create(), 'ready_for_pickup');
        $parameters = ['status' => 'ready_for_pickup', 'order' => 'asc'];
        $this->actingAs($employee)->get(route('requests.index', $parameters))->assertOk()
            ->assertViewHas('activeRequests', fn ($requests) => $requests->total() === 17 && $requests->count() === 15)
            ->assertSee('status=ready_for_pickup', false)->assertSee('page=2', false)
            ->assertDontSee($completed->tracking_code)->assertDontSee($private->tracking_code);
        $this->get(route('requests.index', [...$parameters, 'page' => 2]))->assertOk()
            ->assertViewHas('activeRequests', fn ($requests) => $requests->count() === 2);
        $this->get(route('requests.index', ['status' => 'action_required']))->assertOk()
            ->assertViewHas('activeRequests', fn ($requests) => $requests->total() === 18);
        $this->get(route('requests.archive'))->assertOk()->assertSee($completed->tracking_code)
            ->assertDontSee($private->tracking_code)->assertSee('View my active requests');
    }

    public function test_personal_date_filters_use_submission_and_validate_boundaries(): void
    {
        $employee = User::factory()->create();
        $first = $this->requisition($employee, 'pending', ['submitted_at' => '2026-10-01 00:00:00']);
        $last = $this->requisition($employee, 'pending', ['submitted_at' => '2026-10-02 23:59:59']);
        $this->requisition($employee, 'pending', ['submitted_at' => '2026-09-30 23:59:59']);
        $this->requisition($employee, 'pending', ['submitted_at' => '2026-10-03 00:00:00']);
        $this->actingAs($employee)->get(route('requests.index', [
            'date_from' => '2026-10-01', 'date_to' => '2026-10-02', 'order' => 'asc',
        ]))->assertOk()->assertViewHas('activeRequests', fn ($requests) => $requests->modelKeys() === [$first->id, $last->id]);
        foreach ([
            ['date_from' => '2026-10-02', 'date_to' => '2026-10-01'],
            ['date_from' => 'invalid'], ['status' => 'completed'], ['order' => 'invalid'],
        ] as $invalid) {
            $this->getJson(route('requests.index', $invalid))->assertUnprocessable();
        }
    }

    public function test_empty_states_offer_creation_or_clearing_filters_and_completion_never_falls_back_to_edit_time(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee)->get(route('dashboard'))->assertOk()->assertViewHas('activeCount', 0)
            ->assertSee('No action required from you right now')->assertSee('Create requisition')->assertSee('No request updates yet');
        $this->get(route('requests.index', ['search' => '']))->assertOk()->assertSee('Create requisition');
        $this->get(route('requests.index', ['search' => 'no match']))->assertOk()
            ->assertSee('No requests match these filters')->assertSee('Clear filters');
        $this->get(route('requests.archive', ['search' => 'no match']))->assertOk()
            ->assertSee('No requests match these filters')->assertSee('Clear filters');
        $completed = $this->requisition($employee, 'completed', ['closed_at' => null]);
        $completed->update(['remarks' => 'Edited today']);
        $this->get(route('requests.show', $completed))->assertOk()->assertSee('Completion date unavailable');
        $completed->forceFill(['closed_at' => '2026-09-01 11:00:00'])->saveQuietly();
        $this->get(route('requests.show', $completed))->assertOk()->assertSee('Sep 01, 2026 · 11:00 AM');
    }

    private function requisition(User $owner, string $status, array $attributes = []): Requisition
    {
        $request = Requisition::create(['user_id' => $owner->id, 'request_type' => 'minor', 'status' => $status, 'purpose' => 'Employee workflow test', 'grand_total' => 500]);
        $request->forceFill($attributes)->saveQuietly();

        return $request;
    }

    private function notification(User $owner, string $title, int $requestId): Notification
    {
        return Notification::create(['user_id' => $owner->id, 'title' => $title, 'message' => 'A request update', 'icon' => 'package-check', 'type' => 'success', 'event_type' => 'requisition.ready_for_pickup', 'action_type' => 'requisition', 'action_id' => $requestId, 'is_read' => false]);
    }
}
