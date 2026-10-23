<x-app-layout>
    <x-slot name="header">
        <span style="color: white; font-weight: 800;">USER ACCESS VERIFICATION</span>
    </x-slot>

    <div class="container-fluid py-2 pending-users-page" data-filter-workspace="registrations">
        <div class="mb-4">
            <h4 class="fw-black text-dark mb-1">Pending Registrations</h4>
            <p class="text-muted small mb-3">Verify institutional identity before activating a new Faculty / Staff account.</p>
            <form id="registrationFilters" action="{{ route('admin.users.pending') }}#registration-results" method="GET" class="d-flex flex-wrap gap-2" role="search" data-dynamic-search>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" maxlength="100" placeholder="Search name, school ID, or email" aria-label="Search access requests">
                <x-filter-submit />
                <a class="btn btn-outline-secondary" data-filter-link href="{{ route('admin.users.pending') }}#registration-results">Clear</a>
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>The request could not be processed.</strong>
                <ul class="small mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-9" id="registration-results" data-filter-results data-filter-region="results" data-result-count="{{ $users->total() }}" tabindex="-1" aria-label="Matching registrations">
                @forelse($users as $u)
                    <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                        <div class="row g-0">
                            <!-- User Identity Section -->
                            <div class="pending-user-identity col-md-5 p-4 bg-light border-end d-flex align-items-center">
                                <div class="pending-user-avatar rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px; color: var(--htc-green);">
                                    <i data-lucide="user" style="width: 30px; height: 30px;"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ $u->name }}</h6>
                                    <div class="text-muted small mb-1">ID: {{ $u->school_id }}</div>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3" style="font-size: var(--text-caption);">
                                        {{ $u->academicDepartment?->dept_code ?? $u->department ?? 'UNASSIGNED' }} DEPARTMENT
                                    </span>
                                </div>
                            </div>

                            <!-- Role Assignment Sectionnnnnn -->
                            <div class="pending-user-role col-md-7 p-4 bg-white">
                                <form action="{{ route('admin.users.approve', $u->id) }}" method="POST" data-confirm="Grant Faculty / Staff access to {{ $u->name }} ({{ $u->school_id }})? Confirm that you have verified their institutional identity." data-confirm-title="Grant account access?" data-confirm-accept="Grant access">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $u->account_version }}">
                                    <div class="row align-items-center">
                                        <div class="col-sm-7">
                                            <label class="form-label mini-label">Initial Access Role</label>
                                            <div class="d-flex align-items-center gap-2 rounded-3 bg-light p-2 fw-bold small">
                                                <i data-lucide="user-check" style="width:16px"></i>
                                                Faculty / Staff
                                            </div>
                                        </div>
                                        <div class="col-sm-5 text-end mt-3 mt-sm-0">
                                            <button type="submit" class="btn btn-htc w-100 py-2 rounded-3 fw-bold shadow-sm">
                                                GRANT ACCESS
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <!-- EMPTY STATE -->
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                        <div class="p-4 rounded-circle bg-light d-inline-block mb-3">
                            <i data-lucide="user-check" class="text-muted" style="width: 48px; height: 48px;"></i>
                        </div>
                        <h5 class="fw-bold text-dark">{{ request()->filled('search') ? 'No matching registrations' : 'Registry is Clean' }}</h5>
                        <p class="text-muted small mx-auto" style="max-width: 300px;">{{ request()->filled('search') ? 'Try another search or clear the filter.' : 'All registration attempts have been processed. No users are currently awaiting verification.' }}</p>
                    </div>
                @endforelse
                @if($users->hasPages())
                    <div class="mt-3" data-filter-pagination>{{ $users->fragment('registration-results')->links() }}</div>
                @endif
            </div>

            <!-- RIGHT SIDEBAR: REQUIREMENTS REMINDER -->
            <div class="col-xl-3 d-none d-xl-block">
                <div class="card border-0 shadow-sm rounded-4 bg-dark text-white p-4 sticky-top" style="top: 100px;">
                    <h6 class="fw-bold mb-3 d-flex align-items-center">
                        <i data-lucide="info" class="me-2 text-info" style="width:18px"></i> SMO Protocol
                    </h6>
                    <ul class="list-unstyled small opacity-75 mb-0">
                        <li class="mb-3">
                            <strong class="d-block text-white">1. Identity Match</strong>
                            Check if the School ID matches the name provided in the registrar records.
                        </li>
                        <li class="mb-3">
                            <strong class="d-block text-white">2. Dept. Verification</strong>
                            Ensure the user is assigned to the correct academic or administrative office.
                        </li>
                        <li>
                            <strong class="d-block text-white">3. Role Authority</strong>
                            VPs and Deans should only be approved after official designation.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @pushOnce('styles', 'page-styles:admin/pending-users.blade.php')
    <style>
        .mini-label { font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: var(--tracking-label); margin-bottom: var(--space-4); }
        .bg-success-subtle { background-color: #f0fdf4 !important; }
        .btn-htc { background-color: var(--htc-green); color: white; border: none; }
        .btn-htc:hover { background-color: #0d2e16; color: white; transform: translateY(-1px); }
        .pending-user-avatar { flex: 0 0 60px; }
        @media (max-width: 767.98px) {
            .pending-user-identity { padding: var(--space-16) !important; border-right: 0 !important; border-bottom: 1px solid #e2e8f0; }
            .pending-user-role { padding: var(--space-16) !important; }
            .pending-users-page select,
            .pending-users-page button[type="submit"] { min-height: 44px; font-size: var(--text-body); }
        }
    </style>
    @endPushOnce
</x-app-layout>
