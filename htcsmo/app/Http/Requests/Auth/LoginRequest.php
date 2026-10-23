<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        // 1. Find user by either Email OR School ID OR Username
        $user = User::where('username', $this->login)
            ->orWhere('school_id', $this->login)
            ->first();

        // 2. Check if user exists and if password matches
        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if ($user && ! $user->hasActiveAccess()) {
            Auth::logout();
            $this->session()->flash('account_status', $user->account_status);

            return false;
        }

        // 3. If everything is correct, log the user in
        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());

        // Security Audit Trail (Activity Logging)
        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => "User Authenticated ({$user->role})",
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        // FIX: Use 'login' instead of 'email' so rate limiting tracks by the entered username or school_id
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
