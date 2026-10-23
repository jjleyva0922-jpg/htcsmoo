<section>
    <form method="post" action="{{ route('password.update') }}" class="mt-2" x-data="{ edited: false }" @input="edited = true" data-confirm="Replace your current password? Use the new password the next time you sign in." data-confirm-title="Change your password?" data-confirm-accept="Change password">
        @csrf
        @method('put')

        @if($errors->updatePassword->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" autofocus>
                <p class="fw-bold mb-1">Your password was not changed.</p>
                <ul class="mb-0 small">
                    @foreach($errors->updatePassword->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <p id="password-help" class="small text-muted">Use a new password with 8–64 characters and confirm it below.</p>

        <div class="row g-4">
            <!-- Current Password -->
            <div class="col-md-12">
                <label for="update_password_current_password" class="form-label small fw-bold text-muted text-uppercase label-letter-spacing" style="font-size: var(--text-secondary);">Current Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i data-lucide="lock" style="width: 14px;"></i></span>
                    <input id="update_password_current_password" name="current_password" type="password"
                        class="form-control border-0 bg-light py-2 px-3 rounded-end-3 shadow-none @error('current_password', 'updatePassword') is-invalid @enderror"
                        style="font-size: var(--text-body);" autocomplete="current-password" placeholder="Enter current password" required>
                </div>
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <!-- New Password -->
            <div class="col-md-6">
                <label for="update_password_password" class="form-label small fw-bold text-muted text-uppercase label-letter-spacing" style="font-size: var(--text-secondary);">New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i data-lucide="key-round" style="width: 14px;"></i></span>
                    <input id="update_password_password" name="password" type="password"
                        class="form-control border-0 bg-light py-2 px-3 rounded-end-3 shadow-none @error('password', 'updatePassword') is-invalid @enderror"
                        style="font-size: var(--text-body);" autocomplete="new-password" placeholder="Enter new password" aria-describedby="password-help" minlength="8" maxlength="64" required>
                </div>
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <!-- Confirm New Password -->
            <div class="col-md-6">
                <label for="update_password_password_confirmation" class="form-label small fw-bold text-muted text-uppercase label-letter-spacing" style="font-size: var(--text-secondary);">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i data-lucide="shield-check" style="width: 14px;"></i></span>
                    <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                        class="form-control border-0 bg-light py-2 px-3 rounded-end-3 shadow-none @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                        style="font-size: var(--text-body);" autocomplete="new-password" placeholder="Repeat new password" minlength="8" maxlength="64" required>
                </div>
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="profile-form-action mt-4 d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-sm px-4 py-2 text-white fw-bold shadow-sm" style="background-color: var(--htc-green); border-radius: 8px;">
                Update Security
            </button>

            @if (session('status') === 'password-updated')
                <p role="status" x-show.important="!edited" tabindex="-1" autofocus class="text-success small mb-0 fw-bold d-flex align-items-center">
                    <i data-lucide="check-circle" class="me-1" style="width: 14px;"></i> {{ __('Password updated.') }}
                </p>
            @endif
        </div>
    </form>
</section>
