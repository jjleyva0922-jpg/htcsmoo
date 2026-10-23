<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('data-auth-progress="login"', false)
            ->assertSee('id="authProgressDialog"', false)
            ->assertSee('aria-labelledby="authProgressTitle"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_every_active_role_can_log_in_and_render_its_dashboard(): void
    {
        foreach (User::ROLES as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->post('/login', [
                'login' => $user->username,
                'password' => 'password',
            ])->assertRedirect(route('dashboard', absolute: false));

            $this->assertAuthenticatedAs($user);
            $this->get(route('dashboard'))->assertOk();

            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_password_reset_requests_use_a_neutral_response_and_email_registered_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $confirmation = 'If an account with that email exists, we have sent a password reset link.';

        $knownEmailResponse = $this->post(route('password.email'), ['email' => $user->email]);
        $unknownEmailResponse = $this->post(route('password.email'), ['email' => 'unknown@example.com']);

        $knownEmailResponse->assertRedirect()->assertSessionHas('status', $confirmation);
        $unknownEmailResponse->assertRedirect()->assertSessionHas('status', $confirmation);
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_recovery_screens_render_with_the_auth_layout(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('auth-brand-panel', false);

        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'user@example.com']))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('auth-brand-panel', false);
    }

    public function test_unapproved_users_are_redirected_to_the_approval_pending_screen(): void
    {
        $user = User::factory()->create(['is_approved' => false]);

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('approval.pending'));

        $this->get(route('approval.pending'))
            ->assertOk()
            ->assertSee('Your account is awaiting approval');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
