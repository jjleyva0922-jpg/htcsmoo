<x-guest-layout>
    <div class="auth-page auth-register-page">
        <div class="row g-0">
            <aside class="col-md-5 auth-brand-panel">
                <div class="auth-brand-main">
                    <img src="{{ asset('images/android-chrome-512x5122.png') }}" alt="Holy Trinity College seal" class="auth-brand-logo">
                    <p class="auth-brand-eyebrow">Holy Trinity College</p>
                    <h2 class="auth-brand-title">Request system access</h2>
                    <p class="auth-brand-description">
                        Register with your institutional information. Your account will be reviewed by the SMO In-Charge before access is granted.
                    </p>
                </div>
                <p class="auth-brand-footer mb-0">General Santos City</p>
            </aside>

            <section class="col-md-7 auth-form-panel" aria-labelledby="register-heading">
                <div class="auth-form-wrap">
                    <header class="auth-form-heading">
                        <h1 id="register-heading">Create your account</h1>
                        <p>Enter your official HTC details below.</p>
                    </header>

                    <form method="POST" action="{{ route('register') }}" data-auth-progress="register">
                        @csrf

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="auth-form-label">Full name</label>
                                <div class="input-group auth-input">
                                    <span class="input-group-text" aria-hidden="true"><i data-lucide="user" width="17" height="17"></i></span>
                                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Juan Dela Cruz" autocomplete="name" required autofocus>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="department" class="auth-form-label">Department / College</label>
                                <div class="input-group auth-input">
                                    <span class="input-group-text" aria-hidden="true"><i data-lucide="building-2" width="17" height="17"></i></span>
                                    <select id="department" name="department" class="form-select @error('department') is-invalid @enderror" required>
                                        <option value="" disabled {{ old('department') ? '' : 'selected' }}>Select your department</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->dept_code }}" {{ old('department') === $department->dept_code ? 'selected' : '' }}>
                                                {{ $department->dept_name }} ({{ $department->dept_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="school_id" class="auth-form-label">School ID</label>
                                <div class="input-group auth-input">
                                    <span class="input-group-text" aria-hidden="true"><i data-lucide="badge" width="17" height="17"></i></span>
                                    <input id="school_id" type="text" name="school_id" value="{{ old('school_id') }}" class="form-control @error('school_id') is-invalid @enderror" placeholder="00000-0000" maxlength="10" inputmode="numeric" pattern="\d{5}-\d{4}" title="Use the format 00000-0000" autocomplete="off" required>
                                    @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="username" class="auth-form-label">Username</label>
                                <div class="input-group auth-input">
                                    <span class="input-group-text" aria-hidden="true"><i data-lucide="at-sign" width="17" height="17"></i></span>
                                    <input id="username" type="text" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="juan123" autocomplete="username" minlength="4" maxlength="30" required>
                                    @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="email" class="auth-form-label">Institutional email</label>
                                <div class="input-group auth-input">
                                    <span class="input-group-text" aria-hidden="true"><i data-lucide="mail" width="17" height="17"></i></span>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="you@online.htcgsc.edu.ph" pattern=".+@online\.htcgsc\.edu\.ph" title="Must end with @online.htcgsc.edu.ph" autocomplete="email" required>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="password" class="auth-form-label">Password</label>
                                <div class="input-group auth-input">
                                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                                    <button type="button" class="btn password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                        <span data-password-show-icon><i data-lucide="eye" width="17" height="17"></i></span>
                                        <span data-password-hide-icon hidden><i data-lucide="eye-off" width="17" height="17"></i></span>
                                    </button>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="auth-help mt-1">Use at least one letter and one number.</div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="password_confirmation" class="auth-form-label">Confirm password</label>
                                <div class="input-group auth-input">
                                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" autocomplete="new-password" minlength="8" required>
                                    <button type="button" class="btn password-toggle" data-password-toggle="password_confirmation" aria-label="Show confirmation password" aria-pressed="false">
                                        <span data-password-show-icon><i data-lucide="eye" width="17" height="17"></i></span>
                                        <span data-password-hide-icon hidden><i data-lucide="eye-off" width="17" height="17"></i></span>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="auth-note">
                                    <i data-lucide="shield-check" width="17" height="17" aria-hidden="true"></i>
                                    <span>After registration, the SMO In-Charge will verify your information before activating your account.</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn auth-primary-button w-100">
                                    <span>Request account</span>
                                    <i data-lucide="arrow-right" width="17" height="17" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <p class="small text-secondary text-center mt-4 mb-0">
                            Already registered?
                            <a href="{{ route('login') }}" class="auth-link">Sign in</a>
                        </p>
                        <p class="auth-legal-copy text-center mt-3 mb-0">
                            By requesting an account, you acknowledge the
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

            const schoolIdInput = document.getElementById('school_id');
            if (schoolIdInput) {
                schoolIdInput.addEventListener('input', function () {
                    const digits = this.value.replace(/\D/g, '').slice(0, 9);
                    this.value = digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;
                });
            }
        });
    </script>
</x-guest-layout>
