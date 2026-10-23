<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_submission_notifies_owner_and_matching_department_heads_only(): void
    {
        $owner = User::factory()->create(['department' => 'CETE']);
        $matchingHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $otherHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CAS']);
        $supply = $this->supply();

        $this->actingAs($owner)->post(route('requisitions.store'), [
            'request_type' => 'minor',
            'purpose' => 'Department supplies for classroom instruction.',
            'items' => [['supply_id' => $supply->id, 'qty' => 1]],
        ])->assertRedirect(route('dashboard'));

        $requisition = Requisition::firstOrFail();
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'event_type' => 'requisition.submitted', 'action_id' => $requisition->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $matchingHead->id, 'event_type' => 'requisition.awaiting_approval', 'action_id' => $requisition->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherHead->id, 'action_id' => $requisition->id]);
    }

    public function test_dedupe_key_prevents_retried_event_from_creating_a_second_notification(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);
        $options = ['dedupe_key' => 'same-event', 'icon' => 'not-allowed', 'type' => 'not-allowed'];

        $first = $service->send($user, 'test.event', '<b>Safe title</b>', '<script>alert(1)</script>Message', $options);
        $second = $service->send($user, 'test.event', 'Changed', 'Changed', $options);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Notification::where('user_id', $user->id)->count());
        $this->assertSame('bell', $first->icon);
        $this->assertSame('info', $first->type);
        $this->assertSame('Safe title', $first->title);
    }

    public function test_center_does_not_mark_notifications_read_and_read_actions_are_user_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::create(['user_id' => $owner->id, 'title' => 'Unread', 'message' => 'Keep unread', 'is_read' => false]);
        $otherNotification = Notification::create(['user_id' => $other->id, 'title' => 'Other', 'message' => 'Private', 'is_read' => false]);

        $this->actingAs($owner)->get(route('notifications'))->assertOk()
            ->assertSee('Mark as read')
            ->assertSee('Dismiss toast');
        $this->assertFalse($notification->fresh()->is_read);

        $this->actingAs($other)->patch(route('notifications.read', $notification))->assertForbidden();
        $this->assertFalse($notification->fresh()->is_read);

        $this->actingAs($owner)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($owner)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertFalse($otherNotification->fresh()->is_read);
    }

    public function test_poll_returns_multiple_unread_notifications_for_only_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::create(['user_id' => $other->id, 'title' => 'Private', 'message' => 'Do not expose']);
        $first = Notification::create(['user_id' => $user->id, 'title' => 'First', 'message' => 'One']);
        $second = Notification::create(['user_id' => $user->id, 'title' => 'Second', 'message' => 'Two']);

        $this->actingAs($user)->getJson(route('api.runtime-state', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.id', $first->id)
            ->assertJsonPath('notifications.1.id', $second->id)
            ->assertJsonMissing(['title' => 'Private'])
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('account_id', $user->id);
    }

    public function test_action_destination_is_rechecked_and_missing_or_unauthorized_targets_fall_back_safely(): void
    {
        $requestor = User::factory()->create();
        $recipient = User::factory()->create();
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Authorization test requisition.',
            'grand_total' => 100,
        ]);
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'title' => 'Forged action',
            'message' => 'Recipient cannot view this request.',
            'action_type' => 'requisition',
            'action_id' => $requisition->id,
        ]);

        $this->actingAs($recipient)->post(route('notifications.open', $notification))
            ->assertRedirect(route('notifications'))
            ->assertSessionHas('warning');
        $this->assertTrue($notification->fresh()->is_read);

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('notifications.open', $notification))->assertForbidden();
        $this->actingAs($recipient)->get(route('notifications.open', $notification))->assertMethodNotAllowed();
    }

    public function test_notification_center_paginates_and_escapes_content(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 20) as $number) {
            Notification::create(['user_id' => $user->id, 'title' => "Notice {$number}", 'message' => 'Message']);
        }
        Notification::create(['user_id' => $user->id, 'title' => '<script>alert(1)</script>', 'message' => '<b>message</b>', 'icon' => 'bad-icon', 'type' => 'bad-type']);

        $this->actingAs($user)->get(route('notifications'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('data-lucide="bell"', false)
            ->assertSee('page=2', false);
    }

    public function test_notification_categories_can_update_without_a_full_page_reload(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Approval activity',
            'message' => 'A requisition changed.',
            'event_type' => 'requisition.approved',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Account activity',
            'message' => 'Your account changed.',
            'event_type' => 'account.updated',
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications', ['category' => 'accounts']))
            ->assertOk()
            ->assertJsonPath('category', 'accounts')
            ->assertJsonPath('category_counts.all', 2)
            ->assertJsonPath('category_counts.accounts', 1)
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Account activity')
                && ! str_contains($html, 'Approval activity'));

        $this->actingAs($user)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('data-notification-category="accounts"', false)
            ->assertSee('data-notification-feed', false);
    }

    public function test_ajax_feed_resolves_requisition_actions_in_one_batch(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $number) {
            $requisition = Requisition::create([
                'user_id' => $user->id,
                'request_type' => 'minor',
                'status' => 'pending',
                'purpose' => "Batch action test {$number}",
                'grand_total' => 100,
            ]);
            Notification::create([
                'user_id' => $user->id,
                'title' => "Request {$number}",
                'message' => 'Open this request.',
                'event_type' => 'requisition.submitted',
                'action_type' => 'requisition',
                'action_id' => $requisition->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson(route('notifications'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $requisitionQueries = collect($queries)->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'from "requisitions"')
                || str_contains(strtolower($query['query']), 'from `requisitions`')
        );
        $this->assertCount(1, $requisitionQueries);
    }

    public function test_deadline_sidebar_limits_the_preview_and_keeps_the_total_count(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $risks = collect();
        foreach (range(1, 7) as $number) {
            $risks->push($this->deadlineRequisition($owner, ['due_at' => now()->subDays($number)]));
        }
        foreach (Requisition::ARCHIVED_STATUSES as $status) {
            $this->deadlineRequisition($owner, ['status' => $status]);
        }
        $this->deadlineRequisition($owner, ['sla_warning_at' => now()->addDay(), 'due_at' => now()->addDays(2)]);

        $expected = $risks->reverse()->take(5)->pluck('id')->values()->all();
        $response = $this->actingAs($smo)->get(route('notifications'));
        $response->assertOk()
            ->assertViewHas('criticalRequestCount', 7)
            ->assertViewHas('criticalRequests', fn ($requests) => $requests->pluck('id')->all() === $expected)
            ->assertSee('View all 7 deadline risks')
            ->assertSee(route('requests.index', ['status' => 'deadline']), false);
        $this->assertSame(5, substr_count($response->getContent(), '<a class="notification-deadline-item '));

        $this->get(route('requests.index', ['status' => 'deadline']))->assertOk()
            ->assertViewHas('activeRequests', fn ($requests) => $requests->total() === 7);
    }

    public function test_deadline_sidebar_distinguishes_warnings_from_overdue_requests(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $warning = $this->deadlineRequisition($owner, ['sla_warning_at' => now(), 'due_at' => now()->addDay()]);
        $overdue = $this->deadlineRequisition($owner, ['due_at' => now()]);

        $this->actingAs($smo)->get(route('notifications'))->assertOk()
            ->assertViewHas('criticalRequestCount', 2)
            ->assertSeeInOrder([
                'notification-deadline-item is-overdue', 'Overdue', $overdue->tracking_code,
                'This requisition has reached its processing deadline.',
                'Oct 23, 2026 10:00 AM',
                'notification-deadline-item is-warning', 'Due soon', $warning->tracking_code,
                'This requisition is approaching its processing deadline.',
                'Oct 24, 2026 10:00 AM',
            ], false)
            ->assertSee(route('requests.show', $warning), false)
            ->assertSee(route('requests.show', $overdue), false);
    }

    public function test_deadline_sidebar_handles_no_risks_and_remains_smo_only(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create();
        $this->actingAs($smo)->get(route('notifications'))->assertOk()
            ->assertViewHas('criticalRequestCount', 0)
            ->assertSee('No critical request deadlines.')
            ->assertDontSee('View all 0 deadline risks');

        $risk = $this->deadlineRequisition($owner);
        $this->actingAs($owner)->get(route('notifications'))->assertOk()
            ->assertViewHas('criticalRequests', fn ($requests) => $requests->isEmpty())
            ->assertDontSee('Critical deadlines')
            ->assertDontSee($risk->tracking_code);
    }

    public function test_pruning_removes_only_expired_read_notifications(): void
    {
        $user = User::factory()->create();
        $expired = Notification::create(['user_id' => $user->id, 'title' => 'Old read', 'message' => 'Old', 'is_read' => true, 'read_at' => now()->subDays(400)]);
        $recent = Notification::create(['user_id' => $user->id, 'title' => 'Recent read', 'message' => 'Recent', 'is_read' => true, 'read_at' => now()->subDays(10)]);
        $unread = Notification::create(['user_id' => $user->id, 'title' => 'Old unread', 'message' => 'Unread', 'is_read' => false]);
        $unread->forceFill(['created_at' => now()->subDays(500), 'updated_at' => now()->subDays(500)])->saveQuietly();

        $this->artisan('notifications:prune --days=365')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $expired->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unread->id]);
    }

    private function deadlineRequisition(User $owner, array $attributes = []): Requisition
    {
        return Requisition::create(array_merge([
            'user_id' => $owner->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Deadline sidebar regression.',
            'grand_total' => 100,
            'submitted_at' => now()->subDays(10),
            'sla_warning_at' => now()->subDays(8),
            'due_at' => now()->subDay(),
        ], $attributes));
    }

    private function supply(): Supply
    {
        return Supply::create([
            'item_name' => 'Test Paper', 'brand' => 'Test', 'category' => 'Office Supplies',
            'requisition_tier' => 'minor', 'quantity' => 10, 'unit' => 'Ream(s)',
            'unit_price' => 100, 'min_stock_level' => 2,
        ]);
    }
}
