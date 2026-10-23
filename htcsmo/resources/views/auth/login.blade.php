<x-guest-layout>
    <div class="auth-page auth-login-page">
        <div class="row g-0">
            <aside class="col-md-5 auth-brand-panel">
                <div class="auth-brand-main">
                    <img src="{{ asset('images/android-chrome-512x5122.png') }}" alt="Holy Trinity College seal" class="auth-brand-logo">
                    <p class="auth-brand-eyebrow">Holy Trinity College</p>
                    <h2 class="auth-brand-title">Supply and Requisition System</h2>
                    <p class="auth-brand-description">
                        A simpler way to request, monitor, and manage school supplies in one secure workspace.
                    </p>
                </div>
                <p class="auth-brand-footer mb-0">Authorized faculty and staff access only</p>
            </aside>

            <section class="col-md-7 auth-form-panel" aria-labelledby="login-heading">
                <div class="auth-form-wrap">
                    <header class="auth-form-heading">
                        <h1 id="login-heading">Welcome back</h1>
                        <p>Sign in with your username or school ID to continue.</p>
                    </header>

                    <x-auth-session-status class="alert alert-success py-2 small mb-3" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" data-auth-progress="login">
                        @csrf

                        <div class="mb-3">
                            <label for="login" class="auth-form-label">Username or School ID</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="user" width="17" height="17"></i></span>
                                <input
                                    id="login"
                                    type="text"
                                    name="login"
                                    value="{{ old('login') }}"
                                    class="form-control @error('login') is-invalid @enderror"
                                    placeholder="Username or school ID"
                                    autocomplete="username"
                                    inputmode="text"
                                    required
                                    autofocus
                                >
                                @error('login')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <label for="password" class="auth-form-label">Password</label>
                                @if (Route::has('password.request'))
                                    <a class="auth-link small mb-1" href="{{ route('password.request') }}">Forgot password?</a>
                                @endif
                            </div>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="lock-keyhole" width="17" height="17"></i></span>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Your password"
                                    autocomplete="current-password"
                                    required
                                >
                                <button type="button" class="btn password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                    <span data-password-show-icon><i data-lucide="eye" width="17" height="17"></i></span>
                                    <span data-password-hide-icon hidden><i data-lucide="eye-off" width="17" height="17"></i></span>
                                </button>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input id="remember_me" class="form-check-input" type="checkbox" name="remember">
                            <label class="form-check-label small text-secondary" for="remember_me">Keep me signed in on this device</label>
                        </div>

                        <button type="submit" class="btn auth-primary-button w-100">
                            <span>Sign in</span>
                            <i data-lucide="arrow-right" width="17" height="17" aria-hidden="true"></i>
                        </button>

                        @if (Route::has('register'))
                            <p class="small text-secondary text-center mt-4 mb-0">
                                Need an account?
                                <a href="{{ route('register') }}" class="auth-link">Create one</a>
                            </p>
                        @endif

                        <p class="auth-legal-copy text-center mt-3 mb-0">
                            Use of this system is subject to the
                            <a href="{{ route('legal.terms') }}">Terms of Service</a>
                            and <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
                        </p>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.getElementById(button.dataset.passwordToggle);
                    const reveal = input.type === 'password';

                    input.type = reveal ? 'text' : 'password';
                    button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                    button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                    button.querySelector('[data-password-show-icon]').hidden = reveal;
                    button.querySelector('[data-password-hide-icon]').hidden = !reveal;
                });
            });
        });
    </script>
</x-guest-layout>
