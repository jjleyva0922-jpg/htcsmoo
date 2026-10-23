<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => ['bail', 'required', 'string', Password::defaults(), 'max:64', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            // Recheck under the lock in case another request changed the password.
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Your password changed while this form was open. Enter your current password and try again.',
                ])->errorBag('updatePassword');
            }
            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();
            UserActivityLog::create([
                'user_id' => $user->id, 'action' => 'Changed account password',
                'ip_address' => $request->ip() ?? 'unknown', 'user_agent' => $request->userAgent(), 'created_at' => now(),
            ]);
        });
        $request->user()->refresh();
        $request->session()->migrate(true);

        return back()->with('status', 'password-updated');
    }
}
