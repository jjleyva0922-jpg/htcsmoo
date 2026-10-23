<section>
    <form method="post" action="{{ route('profile.update') }}" class="mt-2" enctype="multipart/form-data" x-data="{ edited: false }" @input="edited = true" data-confirm="Save your full name, username, and profile photo changes?" data-confirm-title="Update account information?" data-confirm-accept="Save changes">
        @csrf
        @method('patch')

        @if($errors->getBag('default')->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" autofocus>
                <p class="fw-bold mb-1">Please check the highlighted fields.</p>
                <ul class="mb-0 small">
                    @foreach($errors->getBag('default')->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="profile-photo-row d-flex align-items-center mb-4">
            <div class="position-relative">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" class="rounded-circle shadow-sm border" style="width: 80px; height: 80px; object-fit: cover;" alt="{{ $user->name }} profile photo">
                @else
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width: 80px; height: 80px; color: var(--htc-green);">
                        <i data-lucide="user" style="width: 40px;"></i>
                    </div>
                @endif
            </div>

            <div class="ms-4 flex-grow-1">
                <label for="profile_photo" class="form-label small fw-bold text-muted text-uppercase d-block">Change Profile Photo</label>
                <input id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help" class="form-control form-control-sm border-0 bg-light shadow-none @error('profile_photo') is-invalid @enderror">
                <small id="profile-photo-help" class="text-muted" style="font-size: var(--text-caption);">Square JPG, PNG, or WebP, max 2MB. Choose the image, then save your changes.</small>
                @error('profile_photo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label small fw-bold text-muted text-uppercase">Full Name</label>
                <input id="name" name="name" type="text" class="form-control border-0 bg-light py-2 px-3 rounded-3 shadow-none @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="255" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="username" class="form-label small fw-bold text-muted text-uppercase">Username</label>
                <input id="username" name="username" type="text" class="form-control border-0 bg-light py-2 px-3 rounded-3 shadow-none @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" autocomplete="username" aria-describedby="username-help" maxlength="30" required>
                <small id="username-help" class="text-muted">You can keep your current username. A new username needs 4–30 letters, numbers, underscores, or dashes.</small>
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label small fw-bold text-muted text-uppercase">Email Address (Read-only)</label>
                <input id="email" type="email" class="form-control border-0 bg-light py-2 px-3 rounded-3 shadow-none" value="{{ $user->email }}" autocomplete="email" readonly>
            </div>

            <div class="col-md-6">
                <label for="department" class="form-label small fw-bold text-muted text-uppercase">Department / College</label>
                <input id="department" type="text" class="form-control border-0 bg-light py-2 px-3 rounded-3 shadow-none"
                    value="{{ $user->academicDepartment?->dept_name ?? $user->department ?? 'Administrative Office / Not Assigned' }}" readonly>
                <small class="text-muted">Department assignments are maintained through Personnel Management.</small>
            </div>

            <div class="col-12">
                <label for="school_id" class="form-label small fw-bold text-muted text-uppercase">School ID (Read-only)</label>
                <input id="school_id" type="text" class="form-control border-0 bg-light py-2 px-3 rounded-3" value="{{ $user->school_id }}" readonly style="cursor: not-allowed;">
            </div>
        </div>

        <div class="profile-form-action mt-4 d-flex align-items-center gap-3">
            <button type="submit" class="btn fw-bold px-4 py-2 text-white" style="background-color: var(--htc-green); border-radius: 10px;">
                Save Changes
            </button>

            @if (session('status') === 'profile-updated')
                <p class="text-success small ms-3 mb-0 fw-bold" role="status" x-show="!edited" tabindex="-1" autofocus>Saved successfully!</p>
            @endif
        </div>
    </form>
</section>
