<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'departments' => Department::query()->orderBy('dept_name')->get(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        // 1. Sanitize & Normalize Inputs
        $request->merge([
            'name' => preg_replace('/\s+/', ' ', trim(strip_tags($request->name))),
            'username' => trim(strtolower(strip_tags($request->username))),
            'school_id' => trim(strip_tags($request->school_id)),
            'email' => trim(strtolower(strip_tags($request->email))),
            'department' => trim(strip_tags($request->department)),
        ]);

        // 2. Ultra-Strict Validation Rules
        $validated = $request->validate([
            // Full Name: only letters, spaces, dots, and hyphens (prevent code/special character injections)
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-zA-Z\s\.\,\-]+$/',
            ],

            // Username: 4 to 30 chars, lowercase alphanumeric, dashes, or underscores only
            'username' => [
                'required',
                'string',
                'min:4',
                'max:30',
                'alpha_dash:ascii',
                'unique:users,username',
            ],

            // Strict School ID Format: 00000-0000 (e.g., 20260-0001)
            'school_id' => [
                'required',
                'string',
                'regex:/^\d{5}-\d{4}$/',
                'unique:users,school_id',
            ],

            // Department: Restricted to official HTC colleges only
            'department' => ['required', 'string', Rule::exists('departments', 'dept_code')],

            // Strict Institutional Email: Must end specifically with @online.htcgsc.edu.ph
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:100',
                'regex:/^[a-zA-Z0-9._%+-]+@online\.htcgsc\.edu\.ph$/i',
                'unique:users,email',
            ],

            // Password: min 8 chars, must contain letters and numbers
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'max:64',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            // Custom friendly institutional error messages
            'name.regex' => 'Full name can only contain letters, spaces, and hyphens.',
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes, and underscores.',
            'username.unique' => 'This username is already taken.',
            'school_id.regex' => 'School ID must follow the exact format: 00000-0000 (e.g. 20260-0001).',
            'school_id.unique' => 'This School ID is already registered.',
            'department.in' => 'Please select a valid HTC academic department.',
            'email.regex' => 'You must use your official HTC email ending in @online.htcgsc.edu.ph.',
            'email.unique' => 'This institutional email is already in use.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        // 3. Create User in Database
        $department = Department::where('dept_code', $validated['department'])->firstOrFail();
        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'school_id' => $validated['school_id'],
            'department_id' => $department->id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'employee',
            'account_status' => User::ACCOUNT_PENDING,
        ]);

        // 4. Send persistent notification to SMO In-Charge
        $notifications->sendToRole('smo', 'account.access_requested', 'New User Access Request',
            "{$user->name} ({$user->school_id}) from {$user->department} has registered and awaits verification.", [
                'icon' => 'user-plus',
                'type' => 'warning',
                'action_type' => 'pending_users',
                'action_id' => $user->id,
                'dedupe_key' => "account:access-requested:{$user->id}",
            ]);

        event(new Registered($user));

        return redirect()->route('login')->with('status', 'Registration successful! Your account is pending review by the SMO In-Charge.');
    }
}
