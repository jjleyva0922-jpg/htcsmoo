<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Department;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $newPhoto = null;
        $oldPhoto = null;
        try {
            if ($request->hasFile('profile_photo')) {
                try {
                    $newPhoto = $request->file('profile_photo')->store('profile-photos', 'public');
                } catch (\Throwable $exception) {
                    Log::warning('Profile photo upload failed.', ['exception' => $exception->getMessage()]);
                    throw ValidationException::withMessages(['profile_photo' => 'The photo could not be uploaded. Please try again.']);
                }
                if (! $newPhoto) {
                    throw ValidationException::withMessages(['profile_photo' => 'The photo could not be uploaded. Please try again.']);
                }
            }

            DB::transaction(function () use ($request, $newPhoto, &$oldPhoto) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $user->fill($request->safe()->only(['name', 'username']));
                if ($newPhoto) {
                    $oldPhoto = $user->profile_photo;
                    $user->profile_photo = $newPhoto;
                }
                $changed = array_keys($user->getDirty());
                if ($changed !== []) {
                    $user->save();
                    $this->recordAccountAudit($user, $request, 'Updated own profile: '.implode(', ', $changed));
                }
            });
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                $this->deleteProfilePhoto($newPhoto);
            }
            throw $exception;
        }

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto) {
            $this->deleteProfilePhoto($oldPhoto);
        }
        $request->user()->refresh();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    private function deleteProfilePhoto(string $path): void
    {
        if (! str_starts_with($path, 'profile-photos/')) {
            return;
        }
        try {
            if (! Storage::disk('public')->delete($path)) {
                Log::warning('Could not remove an unused profile photo.');
            }
        } catch (\Throwable $exception) {
            Log::warning('Could not remove an unused profile photo.', ['exception' => $exception->getMessage()]);
        }
    }

    public function pendingUsers(Request $request)
    {
        abort_unless($request->user()->role === 'smo', 403);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $users = User::query()
            ->with('academicDepartment')
            ->withAccountStatus(User::ACCOUNT_PENDING)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($matches) use ($search) {
                    $matches->where('name', 'like', "%{$search}%")
                        ->orWhere('school_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->oldest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pending-users', compact('users'));
    }

    public function approveUser(Request $request, int $id, NotificationService $notifications)
    {
        $manager = $request->user();
        abort_unless($manager->role === 'smo', 403);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:0']]);

        $user = DB::transaction(function () use ($id, $manager, $request, $validated) {
            $manager = User::query()->lockForUpdate()->findOrFail($manager->id);
            abort_unless($manager->hasActiveAccess() && $manager->role === 'smo', 403);
            $user = User::query()->lockForUpdate()->findOrFail($id);
            abort_if($manager->is($user), 403, 'You cannot approve your own account.');

            if ($user->account_status !== User::ACCOUNT_PENDING) {
                throw ValidationException::withMessages(['account' => 'This access request has already been processed.']);
            }
            if ($user->account_version !== (int) $validated['version']) {
                throw ValidationException::withMessages(['account' => 'This request changed while you were reviewing it. Refresh and try again.']);
            }

            $department = $user->academicDepartment;
            if (! $department) {
                throw ValidationException::withMessages(['account' => 'This registration has an invalid or missing department and cannot be approved.']);
            }

            $user->forceFill([
                'role' => 'employee',
                'department_id' => $department->id,
                'account_status' => User::ACCOUNT_ACTIVE,
                'account_version' => $user->account_version + 1,
            ])->save();

            $this->recordAccountAudit($manager, $request, "Approved pending account #{$user->id} as employee");

            return $user;
        }, 3);

        $notifications->send($user, 'account.activated', 'Account Activated',
            'Your account has been verified as employee. You now have system access.', [
                'icon' => 'shield-check', 'type' => 'success', 'action_type' => 'profile',
                'action_id' => $user->id, 'dedupe_key' => "account:{$user->id}:activated",
            ]);

        return back()->with('success', "Access granted to {$user->name} as Faculty / Staff.");
    }

    public function manageUsers(Request $request)
    {
        $manager = $request->user();
        $this->ensureCanManageUsers($manager);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['admins', 'heads', 'staff'])],
        ]);
        $search = trim($validated['search'] ?? '');
        $category = $validated['category'] ?? null;
        $scope = User::query()->where('account_status', '!=', User::ACCOUNT_PENDING);

        if ($manager->role === 'dept_head') {
            abort_if(blank($manager->department), 403, 'A department assignment is required to manage personnel.');

            $scope->where('department_id', $manager->department_id)
                ->where('role', 'employee');
        }

        $stats = [
            'total' => (clone $scope)->count(),
            'active' => (clone $scope)->where('account_status', User::ACCOUNT_ACTIVE)->count(),
            'suspended' => (clone $scope)->where('account_status', User::ACCOUNT_SUSPENDED)->count(),
        ];

        $categoryRoles = [
            'admins' => ['smo', 'president', 'provost', 'vp_admin', 'vp_finance'],
            'heads' => ['dept_head'],
            'staff' => ['employee'],
        ];
        $categoryCounts = [
            'all' => $stats['total'],
            'admins' => (clone $scope)->whereIn('role', $categoryRoles['admins'])->count(),
            'heads' => (clone $scope)->whereIn('role', $categoryRoles['heads'])->count(),
            'staff' => (clone $scope)->whereIn('role', $categoryRoles['staff'])->count(),
        ];

        $query = (clone $scope)->with('academicDepartment');
        if ($category !== null) {
            $query->whereIn('role', $categoryRoles[$category]);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('school_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('department_id')->orderBy('name')->paginate(20)->withQueryString();
        $departments = Department::orderBy('dept_name')->get();
        $manageableUserIds = $users
            ->filter(fn (User $user) => $this->canManageTarget($manager, $user))
            ->pluck('id')
            ->all();

        return view('admin.manage-users', [
            'users' => $users,
            'stats' => $stats,
            'category' => $category,
            'categoryCounts' => $categoryCounts,
            'departments' => $departments,
            'manageableUserIds' => $manageableUserIds,
            'manageableRoles' => $this->assignableRolesFor($manager),
            'canChangeDepartment' => $manager->role !== 'dept_head',
            'scopeLabel' => $manager->role === 'dept_head'
                ? $manager->department.' Department'
                : 'Institution-wide',
        ]);
    }

    public function updateManagedUser(Request $request, User $user, NotificationService $notifications): RedirectResponse
    {
        $manager = $request->user();
        $this->ensureCanManageUsers($manager);

        abort_unless($this->canManageTarget($manager, $user), 403, 'You cannot manage this user account.');

        $validated = $request->validate([
            'role' => ['required', Rule::in($this->assignableRolesFor($manager))],
            'department' => ['nullable', 'required_if:role,dept_head', Rule::exists('departments', 'dept_code')],
            'account_status' => ['required', Rule::in([User::ACCOUNT_ACTIVE, User::ACCOUNT_SUSPENDED])],
            'change_reason' => ['nullable', 'required_if:account_status,'.User::ACCOUNT_SUSPENDED, 'string', 'max:500'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $result = DB::transaction(function () use ($user, $manager, $request, $validated) {
            $manager = User::query()->lockForUpdate()->findOrFail($manager->id);
            abort_unless($manager->hasActiveAccess(), 403);
            $this->ensureCanManageUsers($manager);
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->canManageTarget($manager, $lockedUser), 403, 'You cannot manage this user account.');
            abort_if($lockedUser->account_status === User::ACCOUNT_PENDING, 409, 'Pending registrations must be handled through Access Requests.');

            abort_unless(in_array($validated['role'], $this->assignableRolesFor($manager), true), 403, 'You cannot assign this role.');
            if ($manager->role === 'dept_head') {
                $validated['role'] = 'employee';
                $validated['department'] = $manager->department;
            }

            if ($lockedUser->account_version !== (int) $validated['version']) {
                throw ValidationException::withMessages(['account' => 'This personnel record changed while you were editing it. Refresh and try again.']);
            }

            $department = filled($validated['department'] ?? null)
                ? Department::where('dept_code', $validated['department'])->firstOrFail()
                : null;
            $before = $lockedUser->only(['role', 'department', 'account_status']);
            $after = [
                'role' => $validated['role'],
                'department' => $department?->dept_code,
                'account_status' => $validated['account_status'],
            ];

            if ($before === $after) {
                return ['user' => $lockedUser, 'changed' => false, 'changes' => []];
            }

            $lockedUser->forceFill([
                'role' => $after['role'],
                'account_status' => $after['account_status'],
                'department_id' => $department?->id,
                'account_version' => $lockedUser->account_version + 1,
            ])->save();

            $changes = collect($after)->filter(fn ($value, $field) => $before[$field] !== $value)
                ->map(fn ($value, $field) => "{$field}: ".($before[$field] ?? 'none').' -> '.($value ?? 'none'))
                ->implode('; ');
            $reason = filled($validated['change_reason'] ?? null) ? '; reason: '.trim($validated['change_reason']) : '';
            $this->recordAccountAudit($manager, $request, "Updated account #{$lockedUser->id}: {$changes}{$reason}");

            return ['user' => $lockedUser, 'changed' => true, 'changes' => array_keys(array_diff_assoc($after, $before))];
        }, 3);

        if (! $result['changed']) {
            return back()->with('success', 'No personnel changes were needed.');
        }

        $user = $result['user'];
        $statusChanged = in_array('account_status', $result['changes'], true);
        $event = $user->account_status === User::ACCOUNT_SUSPENDED
            ? 'account.suspended'
            : ($statusChanged ? 'account.reactivated' : 'account.assignment_updated');
        $title = $user->account_status === User::ACCOUNT_SUSPENDED
            ? 'Account Access Suspended'
            : ($statusChanged ? 'Account Access Restored' : 'Personnel Record Updated');
        $message = $user->account_status === User::ACCOUNT_SUSPENDED
            ? 'Your system access has been suspended. Contact the Supply Management Office for assistance.'
            : ($statusChanged ? 'Your system access has been restored.' : 'Your account assignment was updated.');
        $notifications->send($user, $event, $title, $message, [
            'icon' => $statusChanged ? 'user-check' : 'user-cog',
            'type' => $user->account_status === User::ACCOUNT_SUSPENDED ? 'warning' : ($statusChanged ? 'success' : 'info'),
            'action_type' => 'profile',
            'action_id' => $user->id,
            'dedupe_key' => "account:{$user->id}:change:{$user->account_version}",
        ]);

        return back()->with('success', "{$user->name}'s personnel record was updated.");
    }

    private function ensureCanManageUsers(User $manager): void
    {
        abort_unless(
            in_array($manager->role, ['smo', 'president', 'provost', 'vp_admin', 'dept_head'], true),
            403,
        );
    }

    private function canManageTarget(User $manager, User $target): bool
    {
        if ($manager->is($target)) {
            return false;
        }

        if ($manager->role === 'smo') {
            return true;
        }

        if ($manager->role === 'dept_head') {
            return $target->role === 'employee'
                && filled($manager->department)
                && $target->department === $manager->department;
        }

        return in_array($manager->role, ['president', 'provost', 'vp_admin'], true)
            && in_array($target->role, ['employee', 'dept_head'], true);
    }

    /**
     * @return array<int, string>
     */
    private function assignableRolesFor(User $manager): array
    {
        if ($manager->role === 'smo') {
            return $this->allAssignableRoles();
        }

        if ($manager->role === 'dept_head') {
            return ['employee'];
        }

        return ['employee', 'dept_head'];
    }

    /**
     * @return array<int, string>
     */
    private function allAssignableRoles(): array
    {
        return User::ROLES;
    }

    private function recordAccountAudit(User $manager, Request $request, string $action): void
    {
        UserActivityLog::create([
            'user_id' => $manager->id,
            'action' => mb_substr($action, 0, 255),
            'ip_address' => $request->ip() ?? 'unknown',
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
