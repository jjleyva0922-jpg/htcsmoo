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

            <section class="col-md-7 auth-form-panel" aria-labelledby="reset-password-heading">
                <div class="auth-form-wrap">
                    <div class="mb-4 text-success" aria-hidden="true">
                        <i data-lucide="key-round" width="38" height="38"></i>
                    </div>
                    <header class="auth-form-heading">
                        <h1 id="reset-password-heading">Choose a new password</h1>
                        <p>Set a new password for your Supply and Requisition System account.</p>
                    </header>

                    <form method="POST" action="{{ route('password.store') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="mb-3">
                            <label for="email" class="auth-form-label">Email address</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="mail" width="17" height="17"></i></span>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email', $request->email) }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    autocomplete="email"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="auth-form-label">New password</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="lock-keyhole" width="17" height="17"></i></span>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    autocomplete="new-password"
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

                        <div class="mb-4">
                            <label for="password_confirmation" class="auth-form-label">Confirm new password</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="lock-keyhole" width="17" height="17"></i></span>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >
                                <button type="button" class="btn password-toggle" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                                    <span data-password-show-icon><i data-lucide="eye" width="17" height="17"></i></span>
                                    <span data-password-hide-icon hidden><i data-lucide="eye-off" width="17" height="17"></i></span>
                                </button>
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn auth-primary-button w-100">
                            <span>Reset password</span>
                            <i data-lucide="arrow-right" width="17" height="17" aria-hidden="true"></i>
                        </button>

                        <p class="small text-secondary text-center mt-4 mb-0">
                            <a href="{{ route('login') }}" class="auth-link">Return to sign in</a>
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
