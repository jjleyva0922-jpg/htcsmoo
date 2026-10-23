<x-guest-layout>
    <div class="mb-4 small text-body-secondary">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 fw-medium small text-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" data-confirm="End your current session and return to the sign-in page?" data-confirm-title="Sign out?" data-confirm-accept="Sign out">
            @csrf

            <button type="submit" class="btn btn-link btn-sm text-body-secondary">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
