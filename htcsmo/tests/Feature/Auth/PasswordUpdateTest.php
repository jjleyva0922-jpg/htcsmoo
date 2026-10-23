<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_password_change_rotates_the_remember_token_and_records_an_audit_without_password_values(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->remember_token;
        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password', 'password' => 'updated-password', 'password_confirmation' => 'updated-password',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated')->assertRedirect('/profile');
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('updated-password', $user->fresh()->password));
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $user->id, 'action' => 'Changed account password']);
        $this->get('/profile')->assertSee('Password updated.')->assertDontSee('setTimeout(() => show = false, 2000)', false);
    }

    public function test_password_confirmation_errors_are_visible_without_changing_the_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password', 'password' => 'updated-password', 'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->get('/profile')->assertSee('Your password was not changed.')->assertDontSee('Please check the highlighted fields.');
        $this->assertFalse(session()->hasOldInput('current_password'));
        $this->assertFalse(session()->hasOldInput('password'));
    }

    public function test_reusing_the_current_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertDatabaseMissing('user_activity_logs', ['user_id' => $user->id, 'action' => 'Changed account password']);
    }
}
