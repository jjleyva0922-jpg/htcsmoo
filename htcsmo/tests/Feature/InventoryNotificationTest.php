<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_smo_users_are_warned_when_stock_crosses_the_threshold(): void
    {
        $firstSmo = User::factory()->create(['role' => 'smo']);
        $secondSmo = User::factory()->create(['role' => 'smo']);
        $employee = User::factory()->create(['role' => 'employee']);
        $unapprovedSmo = User::factory()->create(['role' => 'smo', 'is_approved' => false]);
        $supply = $this->supply(5);

        app(InventoryAlertService::class)->notifyIfThresholdCrossed($supply, false, false);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $firstSmo->id,
            'title' => 'Low Stock Warning: Bond Paper',
            'type' => 'warning',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $secondSmo->id,
            'icon' => 'triangle-alert',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $unapprovedSmo->id]);

        app(InventoryAlertService::class)->notifyIfThresholdCrossed($supply, true, false);
        $this->assertSame(2, Notification::count(), 'Remaining below the threshold must not create duplicate alerts.');
    }

    public function test_notification_polling_returns_only_newer_unread_notifications(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $old = Notification::create(['user_id' => $smo->id, 'title' => 'Old', 'message' => 'Old alert']);
        $new = Notification::create(['user_id' => $smo->id, 'title' => 'New', 'message' => 'New alert']);

        $this->actingAs($smo)
            ->getJson('/api/latest-notification?after_id='.$old->id)
            ->assertOk()
            ->assertJsonPath('notification.id', $new->id)
            ->assertJsonPath('latest_id', $new->id);
    }

    public function test_critical_deadline_center_links_to_every_at_risk_request(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['role' => 'employee']);

        $readyMinor = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'approved_vp',
            'grand_total' => 500,
            'updated_at' => now()->subDays(3),
        ]);
        $readyMinor->forceFill([
            'submitted_at' => now()->subDays(4),
            'sla_warning_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'stage_started_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();
        $unfinishedMajor = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'major',
            'status' => 'approved_vp',
            'grand_total' => 25000,
            'updated_at' => now()->subDays(3),
        ]);
        $unfinishedMajor->forceFill([
            'submitted_at' => now()->subDays(4),
            'sla_warning_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'stage_started_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();

        $notifications = $this->actingAs($smo)->get(route('notifications'));

        $notifications->assertOk()
            ->assertSee(route('requests.show', $readyMinor), false)
            ->assertSee(route('requests.show', $unfinishedMajor), false);

        $this->actingAs($smo)
            ->get(route('admin.approvals', ['request' => $readyMinor->id]))
            ->assertOk()
            ->assertSee('const focusedApprovalRequest =', false)
            ->assertSee('data-focused-request-id="'.$readyMinor->id.'"', false);
    }

    private function supply(int $quantity): Supply
    {
        return Supply::create([
            'item_name' => 'Bond Paper',
            'brand' => 'HTC Test',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'A4 80 GSM',
            'quantity' => $quantity,
            'unit' => 'Ream(s)',
            'unit_price' => 200,
            'min_stock_level' => 5,
        ]);
    }
}
