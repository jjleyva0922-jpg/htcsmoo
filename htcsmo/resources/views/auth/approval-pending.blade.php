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

            <section class="col-md-7 auth-form-panel" aria-labelledby="approval-heading">
                <div class="auth-form-wrap">
                    <div class="mb-4 text-success" aria-hidden="true">
                        <i data-lucide="clock-3" width="38" height="38"></i>
                    </div>
                    @php($isSuspended = session('account_status') === \App\Models\User::ACCOUNT_SUSPENDED)
                    <header class="auth-form-heading">
                        <h1 id="approval-heading">{{ $isSuspended ? 'Your account access is suspended' : 'Your account is awaiting approval' }}</h1>
                        <p>{{ $isSuspended ? 'Your sign-in details are correct, but this account is currently inactive.' : 'Your sign-in details are correct, but access has not been granted yet.' }}</p>
                    </header>

                    <div class="auth-note mb-4" role="status">
                        <i data-lucide="info" width="17" height="17" aria-hidden="true"></i>
                        <span>{{ $isSuspended ? 'Please contact the Supply Management Office if you believe access should be restored.' : 'The Supply Management Office must approve your account before you can access the system. Please try again after approval.' }}</span>
                    </div>

                    <a href="{{ route('login') }}" class="btn auth-primary-button w-100">
                        <i data-lucide="arrow-left" width="17" height="17" aria-hidden="true"></i>
                        <span>Return to sign in</span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-guest-layout>
