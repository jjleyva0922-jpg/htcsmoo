<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_approved_accounts_for_the_complete_approval_workflow(): void
    {
        $this->seed([
            DepartmentSeeder::class,
            AccountSeeder::class,
        ]);

        $this->assertDatabaseCount('users', 10);

        foreach (['smo', 'president', 'provost', 'vp_admin', 'vp_finance'] as $role) {
            $this->assertDatabaseHas('users', [
                'role' => $role,
                'account_status' => User::ACCOUNT_ACTIVE,
            ]);
        }

        $this->assertSame(5, User::where('role', 'dept_head')->count());
        $this->assertTrue(User::where('role', 'dept_head')->get()->every(
            fn (User $user) => $user->department_id !== null,
        ));
        $this->assertTrue(Hash::check('Password123!', User::where('username', 'smo')->firstOrFail()->password));
    }

    public function test_it_can_be_run_more_than_once_without_creating_duplicates(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(AccountSeeder::class);
        $this->seed(AccountSeeder::class);

        $this->assertDatabaseCount('users', 10);
    }
}
