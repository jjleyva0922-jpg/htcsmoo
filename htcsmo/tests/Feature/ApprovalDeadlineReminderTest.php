<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Notification;
use App\Models\Requisition;
use App\Models\User;
use App\Services\DeadlineNotificationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApprovalDeadlineReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_smo_notifies_requestor_smo_and_only_the_current_department_approver_once_per_day(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['role' => 'employee', 'department' => 'CETE']);
        $assignedHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $otherHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CTE']);
        $vpAdmin = User::factory()->create(['role' => 'vp_admin']);
        $requisition = $this->deadlineRequest($requestor, 'pending');
        $routineRequisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Routine request outside the deadline window.',
            'grand_total' => 100,
        ]);

        $this->actingAs($smo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Approvals needing follow-up')
            ->assertSee($requisition->tracking_code)
            ->assertSee('See all deadline requests')
            ->assertSee('Details')
            ->assertSee('View full request')
            ->assertSee(route('admin.requests.deadline-reminder', $requisition), false);

        $this->actingAs($smo)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee($requisition->tracking_code)
            ->assertSee('Notify');

        $this->actingAs($smo)
            ->get(route('requests.index', ['status' => 'deadline']))
            ->assertOk()
            ->assertSee($requisition->tracking_code)
            ->assertDontSee($routineRequisition->tracking_code)
            ->assertSee('value="deadline" selected', false);

        $this->actingAs($smo)
            ->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('success');
        $this->actingAs($smo)
            ->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('success');

        $this->assertSame(1, Notification::where([
            'user_id' => $assignedHead->id,
            'event_type' => 'deadline.nearing',
            'action_id' => $requisition->id,
        ])->count());
        $this->assertSame(1, Notification::where([
            'user_id' => $requestor->id,
            'event_type' => 'deadline.nearing',
            'action_id' => $requisition->id,
        ])->count());
        $this->assertSame(1, Notification::where([
            'user_id' => $smo->id,
            'event_type' => 'deadline.nearing',
            'action_id' => $requisition->id,
        ])->count());
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherHead->id,
            'event_type' => 'deadline.nearing',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $vpAdmin->id,
            'event_type' => 'deadline.nearing',
        ]);
    }

    public function test_smo_deadline_reminder_is_available_to_the_presidents_live_popup_feed(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $president = User::factory()->create(['role' => 'president']);
        $requestor = User::factory()->create();
        $requisition = $this->deadlineRequest($requestor, 'approved_provost');

        $this->actingAs($smo)
            ->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('success');

        $notification = Notification::where('user_id', $president->id)
            ->where('event_type', 'deadline.nearing')
            ->sole();

        // Opening the recipient's page must leave the reminder eligible for a popup.
        $this->actingAs($president)->get(route('dashboard'))->assertOk();
        $this->actingAs($president)
            ->getJson(route('api.runtime-state', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonPath('account_id', $president->id)
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.type', 'warning')
            ->assertJsonPath('notifications.0.event_type', 'deadline.nearing')
            ->assertJsonPath('notifications.0.action_url', route('notifications.open', $notification))
            ->assertJsonPath('notifications.0.action_label', 'Review requisition');
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_approvers_see_only_deadline_risks_assigned_to_their_stage_and_department(): void
    {
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $ownRequestor = User::factory()->create(['department' => 'CETE']);
        $otherRequestor = User::factory()->create(['department' => 'CTE']);
        $assigned = $this->deadlineRequest($ownRequestor, 'pending');
        $notAssigned = $this->deadlineRequest($otherRequestor, 'pending');

        $this->actingAs($head)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requests requiring your attention')
            ->assertSee($assigned->tracking_code)
            ->assertDontSee($notAssigned->tracking_code)
            ->assertSee('Review now');

        $this->actingAs($head)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee($assigned->tracking_code)
            ->assertDontSee($notAssigned->tracking_code);
    }

    public function test_reminder_rejects_non_deadline_requests_and_non_smo_users(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Routine classroom supply request.',
            'grand_total' => 100,
        ]);

        $this->actingAs($smo)
            ->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error');

        $this->actingAs($head)
            ->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertRedirect(route('dashboard'));
    }

    public function test_stage_timestamp_resets_without_changing_the_submission_deadline(): void
    {
        $requestor = User::factory()->create();
        $requisition = $this->deadlineRequest($requestor, 'pending');
        $submittedAt = $requisition->submitted_at->copy();
        $dueAt = $requisition->due_at->copy();

        $requisition->update(['status' => 'approved_dept']);

        $this->assertTrue($requisition->fresh()->stage_started_at->greaterThan(now()->subMinute()));
        $this->assertTrue($requisition->fresh()->submitted_at->equalTo($submittedAt));
        $this->assertTrue($requisition->fresh()->due_at->equalTo($dueAt));
    }

    #[DataProvider('workflowStages')]
    public function test_reminders_reach_all_active_current_owners_at_each_workflow_stage(string $tier, string $status, string $role): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $secondSmo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $reviewers = collect(['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'])
            ->mapWithKeys(fn ($reviewerRole) => [$reviewerRole => User::factory()->create([
                'role' => $reviewerRole, 'department' => 'CETE',
            ])]);
        $otherHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CTE']);
        $inactive = User::factory()->create(['role' => $role, 'department' => 'CETE', 'account_status' => User::ACCOUNT_SUSPENDED]);
        $requisition = $this->deadlineRequest($requestor, $status);
        $requisition->update(['request_type' => $tier]);

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('success')->assertSessionMissing('error');

        $expected = collect([$smo->id, $secondSmo->id, $requestor->id]);
        if ($role !== 'smo') {
            $expected->push($reviewers[$role]->id);
        }
        $this->assertSame($expected->sort()->values()->all(), Notification::query()
            ->where('action_id', $requisition->id)->pluck('user_id')->sort()->values()->all());
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherHead->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $inactive->id]);

        $recipient = $role === 'smo' ? $smo : $reviewers[$role];
        $notification = Notification::where('user_id', $recipient->id)->sole();
        $destination = $role === 'smo'
            ? route('requests.show', $requisition)
            : route('admin.approvals', ['request' => $requisition->id]);
        $this->actingAs($recipient)->post(route('notifications.open', $notification))->assertRedirect($destination);
        $this->get($destination)->assertOk()->assertSee($requisition->tracking_code);
        $this->assertTrue($notification->fresh()->is_read);
    }

    public static function workflowStages(): array
    {
        return [
            'minor department' => ['minor', 'pending', 'dept_head'],
            'minor finance' => ['minor', 'approved_dept', 'vp_finance'],
            'minor fulfillment' => ['minor', 'approved_vp', 'smo'],
            'major department' => ['major', 'pending', 'dept_head'],
            'major administration' => ['major', 'approved_dept', 'vp_admin'],
            'major provost' => ['major', 'approved_vp', 'provost'],
            'major president' => ['major', 'approved_provost', 'president'],
            'major fulfillment' => ['major', 'approved_president', 'smo'],
            'processing' => ['major', 'processing', 'smo'],
            'pickup' => ['major', 'ready_for_pickup', 'smo'],
        ];
    }

    public function test_missing_or_inactive_signatories_never_produce_a_success_message(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        User::factory()->create(['role' => 'dept_head', 'department' => 'CTE']);
        User::factory()->create(['role' => 'dept_head', 'department' => 'CETE', 'account_status' => User::ACCOUNT_PENDING]);
        User::factory()->create(['role' => 'dept_head', 'department' => 'CETE', 'account_status' => User::ACCOUNT_SUSPENDED]);
        $requisition = $this->deadlineRequest($requestor, 'pending');

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'No active current owner'))
            ->assertSessionMissing('success');
        $this->assertSame(2, Notification::count());

        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $this->post(route('admin.requests.deadline-reminder', $requisition))->assertSessionHas('success');
        $this->assertSame(3, Notification::count());
        $this->assertDatabaseHas('notifications', ['user_id' => $head->id]);
    }

    public function test_unassigned_departments_do_not_send_to_unassigned_heads(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department_id' => null]);
        $unassignedHead = User::factory()->create(['role' => 'dept_head', 'department_id' => null]);
        $requisition = $this->deadlineRequest($requestor, 'pending');

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertDatabaseMissing('notifications', ['user_id' => $unassignedHead->id]);
    }

    public function test_inactive_requestors_are_excluded_from_reminder_delivery(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['account_status' => User::ACCOUNT_SUSPENDED]);
        $requisition = $this->deadlineRequest($requestor, 'processing');

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))->assertSessionHas('success');
        $this->assertDatabaseMissing('notifications', ['user_id' => $requestor->id]);
        $this->assertSame(1, Notification::count());
    }

    public function test_partial_delivery_is_reported_and_retry_sends_only_the_missing_reminder(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $requisition = $this->deadlineRequest($requestor, 'pending');
        $realNotifications = new NotificationService;
        $failOnce = true;
        $this->mock(NotificationService::class)->shouldReceive('send')->andReturnUsing(
            function ($recipient, $event, $title, $message, $options) use ($head, $realNotifications, &$failOnce) {
                if ($recipient->is($head) && $failOnce) {
                    $failOnce = false;

                    return null;
                }

                return $realNotifications->send($recipient, $event, $title, $message, $options);
            }
        );

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Some deadline reminders could not be delivered'))
            ->assertSessionMissing('success');
        $this->assertSame(2, Notification::count());
        $this->assertDatabaseMissing('notifications', ['user_id' => $head->id]);

        $this->post(route('admin.requests.deadline-reminder', $requisition))->assertSessionHas('success');
        $this->assertSame(3, Notification::count());
        $this->assertDatabaseHas('notifications', ['user_id' => $head->id]);
    }

    public function test_service_reloads_the_stage_and_does_not_notify_a_closed_request_from_a_stale_snapshot(): void
    {
        User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create();
        $requisition = $this->deadlineRequest($requestor, 'pending');
        Requisition::findOrFail($requisition->id)->update(['status' => 'completed']);

        $result = app(DeadlineNotificationService::class)->notify($requisition);

        $this->assertFalse($result['eligible']);
        $this->assertSame(0, Notification::count());
    }

    public function test_stage_changes_generate_current_reminders_for_shared_recipients_on_the_same_day(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $vp = User::factory()->create(['role' => 'vp_admin']);
        $requisition = $this->deadlineRequest($requestor, 'pending');
        $service = app(DeadlineNotificationService::class);
        $service->notify($requisition);
        Requisition::findOrFail($requisition->id)->update(['status' => 'approved_dept']);

        // The caller still holds the old pending model, as a scheduled scan can.
        $result = $service->notify($requisition);

        $this->assertSame(3, $result['created']);
        foreach ([$smo, $requestor] as $recipient) {
            $this->assertSame(2, Notification::where('user_id', $recipient->id)->count());
            $this->assertStringContainsString('VP Administration approval', Notification::where('user_id', $recipient->id)->latest('id')->first()->message);
        }
        $this->assertSame(1, Notification::where('user_id', $head->id)->count());
        $this->assertSame(1, Notification::where('user_id', $vp->id)->count());
        $oldReminder = Notification::where('user_id', $head->id)->sole();
        $this->actingAs($head)->post(route('notifications.open', $oldReminder))
            ->assertRedirect(route('notifications'))->assertSessionHas('warning');
    }

    public function test_daily_retry_preserves_read_state_and_allows_a_new_reminder_the_next_day(): void
    {
        $this->travelTo(now()->setTime(23, 59));
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->deadlineRequest(User::factory()->create(), 'processing');
        $service = app(DeadlineNotificationService::class);
        $service->notify($requisition);
        $notification = Notification::where('user_id', $smo->id)->sole();
        $notification->markAsRead();

        $this->assertSame(0, $service->notify($requisition)['created']);
        $this->assertTrue($notification->fresh()->is_read);
        $this->travel(2)->minutes();
        $this->assertSame(2, $service->notify($requisition)['created']);
        $this->assertSame(2, Notification::where('user_id', $smo->id)->count());
    }

    public function test_warning_and_overdue_boundaries_and_scheduler_share_duplicate_protection(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->deadlineRequest(User::factory()->create(), 'processing');
        $requisition->forceFill(['sla_warning_at' => now()->addMinute(), 'due_at' => now()->addHour()])->saveQuietly();
        $service = app(DeadlineNotificationService::class);
        $this->assertFalse($service->notify($requisition)['eligible']);

        $this->travel(1)->minutes();
        $this->assertSame(2, $service->notify($requisition)['created']);
        $this->artisan('smo:check-deadlines')->assertSuccessful();
        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'already notified'));
        $this->assertSame(2, Notification::count());

        $this->travel(59)->minutes();
        $this->assertSame(2, $service->notify($requisition)['created']);
        $this->assertSame(2, Notification::where('event_type', 'deadline.overdue')->where('type', 'danger')->count());
        $this->assertSame(0, $service->notify($requisition)['created']);
    }

    public function test_clarification_reminders_target_the_latest_questioning_office(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create();
        $previousReviewer = User::factory()->create(['role' => 'vp_admin']);
        $currentReviewer = User::factory()->create(['role' => 'provost']);
        $requisition = $this->deadlineRequest($requestor, 'for_clarification');
        foreach ([$previousReviewer, $currentReviewer] as $reviewer) {
            ApprovalLog::create([
                'requisition_id' => $requisition->id,
                'acted_by_user_id' => $reviewer->id,
                'role' => $reviewer->role,
                'action' => 'Flagged for Office Clarification',
            ]);
        }

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))->assertSessionHas('success');
        $notification = Notification::where('user_id', $currentReviewer->id)->sole();
        $this->assertDatabaseMissing('notifications', ['user_id' => $previousReviewer->id]);
        $this->actingAs($currentReviewer)->post(route('notifications.open', $notification))
            ->assertRedirect(route('admin.approvals', ['request' => $requisition->id]));
    }

    public function test_clarification_without_an_identifiable_owner_reports_the_assignment_problem(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->deadlineRequest(User::factory()->create(), 'for_clarification');

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'No active current owner'))
            ->assertSessionMissing('success');
    }

    public function test_closed_requests_cannot_receive_reminders(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create();
        foreach (Requisition::ARCHIVED_STATUSES as $status) {
            $requisition = $this->deadlineRequest($requestor, $status);
            $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
                ->assertSessionHas('error')->assertSessionMissing('success');
        }
        $this->assertSame(0, Notification::count());
    }

    public function test_failed_delivery_never_reports_success(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->deadlineRequest(User::factory()->create(), 'processing');
        $this->mock(NotificationService::class)->shouldReceive('send')->twice()->andReturnNull();

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))
            ->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertSame(0, Notification::count());
    }

    public function test_a_requestor_who_is_also_smo_receives_only_one_reminder(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->deadlineRequest($smo, 'processing');

        $this->actingAs($smo)->post(route('admin.requests.deadline-reminder', $requisition))->assertSessionHas('success');
        $this->assertSame(1, Notification::count());
    }

    public function test_only_active_smo_accounts_can_trigger_reminders(): void
    {
        $requestor = User::factory()->create();
        $requisition = $this->deadlineRequest($requestor, 'processing');
        $url = route('admin.requests.deadline-reminder', $requisition);
        $this->postJson($url)->assertUnauthorized();

        foreach (['employee', 'dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post($url)
                ->assertRedirect(route('dashboard'));
        }
        foreach ([User::ACCOUNT_PENDING, User::ACCOUNT_SUSPENDED] as $status) {
            $this->actingAs(User::factory()->create(['role' => 'smo', 'account_status' => $status]))->post($url)
                ->assertRedirect(route('approval.pending'));
        }
        $this->assertSame(0, Notification::count());
    }

    public function test_scheduled_scan_signals_missing_signatories_and_can_recover_after_assignment(): void
    {
        User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $requisition = $this->deadlineRequest($requestor, 'pending');

        $this->artisan('smo:check-deadlines')
            ->expectsOutputToContain($requisition->tracking_code.': reminder delivery is incomplete')
            ->assertFailed();
        $this->assertSame(2, Notification::count());

        User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $this->artisan('smo:check-deadlines')->assertSuccessful();
        $this->assertSame(3, Notification::count());
    }

    private function deadlineRequest(User $requestor, string $status): Requisition
    {
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'major',
            'status' => $status,
            'purpose' => 'Deadline reminder feature verification.',
            'grand_total' => 1500,
        ]);
        $requisition->forceFill([
            'submitted_at' => now()->subDays(2),
            'sla_warning_at' => now()->subHour(),
            'due_at' => now()->addDay(),
            'stage_started_at' => now()->subHour(),
        ])->saveQuietly();

        return $requisition->fresh();
    }
}
