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

            <section class="col-md-7 auth-form-panel" aria-labelledby="reset-request-heading">
                <div class="auth-form-wrap">
                    <div class="mb-4 text-success" aria-hidden="true">
                        <i data-lucide="mail-question" width="38" height="38"></i>
                    </div>
                    <header class="auth-form-heading">
                        <h1 id="reset-request-heading">Reset your password</h1>
                        <p>Enter the email address associated with your account. We’ll send a secure reset link if it matches an account.</p>
                    </header>

                    <x-auth-session-status class="alert alert-success py-2 small mb-3" :status="session('status')" />

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="auth-form-label">Email address</label>
                            <div class="input-group auth-input">
                                <span class="input-group-text" aria-hidden="true"><i data-lucide="mail" width="17" height="17"></i></span>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="name@example.com"
                                    autocomplete="email"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn auth-primary-button w-100">
                            <span>Email reset link</span>
                            <i data-lucide="arrow-right" width="17" height="17" aria-hidden="true"></i>
                        </button>

                        <p class="small text-secondary text-center mt-4 mb-0">
                            Remembered your password?
                            <a href="{{ route('login') }}" class="auth-link">Return to sign in</a>
                        </p>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-guest-layout>
