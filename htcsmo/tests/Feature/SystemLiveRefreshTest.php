<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLiveRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_version_changes_when_a_requisition_is_submitted(): void
    {
        $user = User::factory()->create();

        $before = $this->actingAs($user)
            ->getJson(route('api.system-version'))
            ->assertOk()
            ->json('version');

        Requisition::create([
            'user_id' => $user->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'For testing live workspace updates.',
            'grand_total' => 100,
        ]);

        $after = $this->actingAs($user)
            ->getJson(route('api.system-version'))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->json('version');

        $this->assertNotSame($before, $after);
    }

    public function test_system_version_requires_authentication(): void
    {
        $this->getJson(route('api.system-version'))->assertUnauthorized();
    }

    public function test_runtime_state_combines_refresh_and_notification_data(): void
    {
        $user = User::factory()->create();
        $seen = Notification::create([
            'user_id' => $user->id,
            'title' => 'Earlier update',
            'message' => 'Already known by this browser tab.',
            'icon' => 'bell',
            'type' => 'info',
            'is_read' => false,
        ]);
        $new = Notification::create([
            'user_id' => $user->id,
            'title' => 'New update',
            'message' => 'This should be returned by the combined poll.',
            'icon' => 'bell',
            'type' => 'success',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.runtime-state', ['after_id' => $seen->id]))
            ->assertOk()
            ->assertJsonPath('latest_id', $new->id)
            ->assertJsonPath('notification.id', $new->id)
            ->assertJsonStructure(['version', 'latest_id', 'notification']);
    }

    public function test_runtime_state_requires_authentication(): void
    {
        $this->getJson(route('api.runtime-state'))->assertUnauthorized();
    }

    public function test_runtime_polling_has_headroom_for_multiple_tabs_and_remains_rate_limited_per_account(): void
    {
        $user = User::factory()->create();
        $url = route('api.runtime-state');

        // Three tabs without browser coordination make 60 polls per minute.
        // Navigation and manual retries must not exhaust the normal allowance.
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->actingAs($user)->getJson($url)->assertOk();
        }
        $this->getJson($url)->assertStatus(429)->assertHeader('Retry-After');

        $other = User::factory()->create();
        $this->actingAs($other)->getJson($url)->assertOk();
        $this->travel(61)->seconds();
        $this->actingAs($user)->getJson($url)->assertOk();
    }
}
