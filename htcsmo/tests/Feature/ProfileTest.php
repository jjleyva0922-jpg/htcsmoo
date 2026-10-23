<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk()
            ->assertSee('Full Name')
            ->assertDontSee('Full Name (Read-only)')
            ->assertSee('Email Address (Read-only)');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $name = $document->getElementById('name');
        $this->assertNotNull($name);
        $this->assertFalse($name->hasAttribute('readonly'));
        $this->assertSame('name', $name->getAttribute('name'));
        $this->assertSame($user->name, $name->getAttribute('value'));
        $email = $document->getElementById('email');
        $this->assertNotNull($email);
        $this->assertTrue($email->hasAttribute('readonly'));
        $this->assertFalse($email->hasAttribute('name'));
        $this->assertSame($user->email, $email->getAttribute('value'));
    }

    public function test_full_name_and_username_can_be_updated_without_changing_email_or_department(): void
    {
        $department = Department::where('dept_code', 'CAS')->firstOrFail();
        $user = User::factory()->create();
        $originalDepartment = $user->department;
        $originalDepartmentId = $user->department_id;
        $originalEmail = $user->email;
        $originalVerification = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'updated_user',
                'department' => $department->dept_code,
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('updated_user', $user->username);
        $this->assertSame($originalDepartment, $user->department);
        $this->assertSame($originalDepartmentId, $user->department_id);
        $this->assertSame($originalEmail, $user->email);
        $this->assertTrue($originalVerification->equalTo($user->email_verified_at));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_username_must_be_unique(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $otherUser->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasErrors('username')
            ->assertRedirect('/profile');
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }

    public function test_name_can_be_updated_with_an_existing_short_username(): void
    {
        $user = User::factory()->create(['role' => 'smo', 'username' => 'smo']);
        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => 'Updated SMO Name', 'username' => 'smo', 'email' => 'changed@example.com',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-updated')->assertRedirect('/profile');
        $this->assertSame('Updated SMO Name', $user->fresh()->name);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame('smo', $user->fresh()->username);
        $this->get('/profile')->assertOk()->assertSee('Updated SMO Name');
    }

    public function test_replacing_a_username_still_requires_at_least_four_characters(): void
    {
        $user = User::factory()->create(['username' => 'smo']);
        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => 'New Name', 'username' => 'abc', 'email' => $user->email,
        ])->assertSessionHasErrors('username')->assertRedirect('/profile');
        $this->assertSame('smo', $user->fresh()->username);
        $this->assertNotSame('New Name', $user->fresh()->name);
    }

    public function test_malformed_profile_values_fail_validation_instead_of_becoming_array_strings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => ['Invalid'], 'username' => ['Invalid'], 'email' => ['Invalid'],
        ])->assertSessionHasErrors(['name', 'username'])->assertRedirect('/profile');
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_failed_profile_validation_preserves_the_old_photo_and_shows_errors(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/old.png', 'old photo');
        $user = User::factory()->create(['profile_photo' => 'profile-photos/old.png']);
        $this->actingAs($user)->from('/profile')->post('/profile', [
            '_method' => 'PATCH', 'username' => '',
            'profile_photo' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasErrors('username')->assertRedirect('/profile');
        Storage::disk('public')->assertExists('profile-photos/old.png');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo);
        $this->get('/profile')->assertSee('Please check the highlighted fields.');
    }

    public function test_profile_photo_is_saved_without_submitting_read_only_fields(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/old.png', 'old photo');
        $user = User::factory()->create(['username' => 'smo', 'profile_photo' => 'profile-photos/old.png']);
        $this->actingAs($user)->from('/profile')->post('/profile', [
            '_method' => 'PATCH', 'username' => $user->username,
            'profile_photo' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $updated = $user->fresh();
        $this->assertSame($user->name, $updated->name);
        $this->assertSame($user->email, $updated->email);
        Storage::disk('public')->assertExists($updated->profile_photo);
        Storage::disk('public')->assertMissing('profile-photos/old.png');
    }

    public function test_failed_database_save_keeps_the_old_photo_and_removes_the_new_upload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/old.png', 'old photo');
        $user = User::factory()->create(['profile_photo' => 'profile-photos/old.png']);
        $originalName = $user->name;
        Event::listen('eloquent.saving: '.User::class, function (User $record) {
            if ($record->username === 'simulated_failure') {
                throw new \RuntimeException('Save failed');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->from('/profile')->post('/profile', [
                '_method' => 'PATCH', 'username' => 'simulated_failure',
                'profile_photo' => UploadedFile::fake()->image('replacement.png'),
            ]);
            $this->fail('The simulated save failure should be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Save failed', $exception->getMessage());
        }
        $this->assertSame($originalName, $user->fresh()->name);
        $this->assertSame($user->username, $user->fresh()->username);
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo);
        Storage::disk('public')->assertExists('profile-photos/old.png');
        $this->assertCount(1, Storage::disk('public')->allFiles('profile-photos'));
    }

    public function test_storage_failure_shows_a_photo_error_and_does_not_change_the_profile(): void
    {
        $user = User::factory()->create(['profile_photo' => 'profile-photos/old.png']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->actingAs($user)->from('/profile')->post('/profile', [
            '_method' => 'PATCH', 'name' => 'Upload failure', 'username' => $user->username, 'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasErrors('profile_photo')->assertRedirect('/profile');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo);
        $this->assertNotSame('Upload failure', $user->fresh()->name);
    }

    public function test_profile_save_cannot_change_privileged_or_institutional_fields(): void
    {
        $user = User::factory()->create();
        $original = $user->only(['email', 'school_id', 'department_id', 'role', 'account_status', 'account_version']);
        $this->actingAs($user)->patch('/profile', [
            'name' => 'Profile Only', 'username' => 'profile_only', 'email' => 'changed@example.com',
            'role' => 'president', 'school_id' => '99999-9999', 'department_id' => 999,
            'account_status' => 'suspended', 'account_version' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertSame($original, $user->fresh()->only(array_keys($original)));
        $this->assertSame('Profile Only', $user->fresh()->name);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $user->id, 'action' => 'Updated own profile: name, username']);
    }

    #[DataProvider('profileRoles')]
    public function test_all_roles_can_edit_their_own_full_name(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $other = User::factory()->create();
        $name = "María O'Connor-Santos";

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $name,
            'username' => $user->username,
            'user_id' => $other->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $this->assertSame($name, $user->fresh()->name);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $user->id, 'action' => 'Updated own profile: name']);
    }

    public static function profileRoles(): array
    {
        return array_map(fn (string $role) => [$role], User::ROLES);
    }

    public function test_invalid_full_names_are_rejected_without_saving_other_changes(): void
    {
        $user = User::factory()->create();

        foreach (['', '   ', null, ['Invalid'], str_repeat('A', 256)] as $invalid) {
            $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
                'name' => $invalid, 'username' => 'new_username',
            ])->assertSessionHasErrors('name')->assertRedirect(route('profile.edit'));
            $this->assertSame($user->name, $user->fresh()->name);
            $this->assertSame($user->username, $user->fresh()->username);
        }
        $this->assertDatabaseCount('user_activity_logs', 0);
    }
}
