<x-app-layout>
    <x-slot name="header">
        {{ __('Account Settings') }}
    </x-slot>

    <div class="container-fluid py-2 account-settings-page">
        <div class="row g-4">
            <!-- Left Side: Navigation/Context -->
            <div class="col-lg-4">
                <div class="account-summary-card card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 100px;">
                    <div class="text-center mb-4">
                        @if($user->profile_photo)
                            <img src="{{ asset('storage/' . $user->profile_photo) }}" class="rounded-circle mx-auto mb-3" style="width: 80px; height: 80px; object-fit: cover;" alt="{{ $user->name }} profile photo">
                        @else
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px; color: var(--htc-green);">
                            <i data-lucide="user" style="width: 40px; height: 40px;"></i>
                        </div>
                        @endif
                        <h5 class="fw-bold mb-1">{{ Auth::user()->name }}</h5>
                        <p class="text-muted small mb-0">{{ Auth::user()->school_id }}</p>
                        <span class="badge bg-success-subtle text-success mt-2 px-3 rounded-pill text-uppercase" style="font-size: var(--text-caption);">
                            {{ str_replace('_', ' ', Auth::user()->role) }}
                        </span>
                    </div>

                    <div class="list-group list-group-flush small">
                        <a href="#profile-info" class="list-group-item list-group-item-action border-0 rounded-3 mb-1">
                            <i data-lucide="info" class="me-2" style="width:16px;"></i> Profile Information
                        </a>
                        <a href="#password-update" class="list-group-item list-group-item-action border-0 rounded-3 mb-1">
                            <i data-lucide="lock" class="me-2" style="width:16px;"></i> Update Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Side: Forms -->
            <div class="col-lg-8">
                <!-- Profile Information Card -->
                <div id="profile-info" class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex align-items-center mb-4">
                        <i data-lucide="user-cog" class="text-success me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Profile Information</h6>
                            <small class="text-muted">Update your full name, username, and profile photo. Your email address is read-only.</small>
                        </div>
                    </div>
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Password Update Card -->
                <div id="password-update" class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex align-items-center mb-4">
                        <i data-lucide="shield-check" class="text-success me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Security Update</h6>
                            <small class="text-muted">Ensure your account is using a long, random password to stay secure.</small>
                        </div>
                    </div>
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>

    @pushOnce('styles', 'page-styles:profile/edit.blade.php')
    <style>
        @media (max-width: 991.98px) {
            .account-summary-card { position: static !important; }
            .account-settings-page > .row { --bs-gutter-y: var(--space-16); }
            .account-summary-card .list-group { flex-direction: row; gap: var(--space-8); }
            .account-summary-card .list-group-item { flex: 1; margin-bottom: 0 !important; text-align: center; }
        }
        @media (max-width: 575.98px) {
            .account-settings-page .card { padding: var(--space-16) !important; }
            .account-summary-card .list-group-item { min-height: 42px; display: flex; align-items: center; justify-content: center; }
            .account-settings-page input,
            .account-settings-page select { min-height: 44px; font-size: var(--text-body) !important; }
            .profile-photo-row { align-items: flex-start !important; flex-direction: column; }
            .profile-photo-row > .ms-4 { width: 100%; margin-top: var(--space-16); margin-left: 0 !important; }
            .profile-form-action { align-items: stretch !important; flex-direction: column; }
            .profile-form-action .btn { width: 100%; min-height: 44px; }
            .profile-form-action p { margin-left: 0 !important; text-align: center; }
        }
    </style>
    @endPushOnce
</x-app-layout>
