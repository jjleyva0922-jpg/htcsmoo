@php
    $roleLabels = [
        'employee' => 'Faculty / Staff',
        'dept_head' => 'Department Head / Dean',
        'vp_finance' => 'VP for Finance',
        'vp_admin' => 'VP for Administration',
        'provost' => 'School Provost',
        'president' => 'School President',
        'smo' => 'SMO In-Charge',
    ];
    $personnelCategories = [
        'all' => ['label' => 'All Personnel', 'description' => 'Everyone within your management scope', 'icon' => 'users'],
        'admins' => ['label' => 'Administrators', 'description' => 'SMO and institutional administrators', 'icon' => 'shield-check'],
        'heads' => ['label' => 'Deans & Heads', 'description' => 'Department heads and academic deans', 'icon' => 'landmark'],
        'staff' => ['label' => 'Faculty & Staff', 'description' => 'Faculty members and support personnel', 'icon' => 'graduation-cap'],
    ];
    $activeCategory = $category ?? 'all';
@endphp

<x-app-layout>
    <x-slot name="header">Personnel Management</x-slot>

    <div class="container-fluid py-2 personnel-page" data-filter-workspace="personnel" x-data="{
        editModal: null,
        selectedUser: { id: '', name: '', role: '', department: '', account_status: 'active', version: 0 },
        openEdit(user) {
            this.selectedUser = {
                ...user,
                department: user.department || '',
                account_status: user.account_status,
                version: user.version
            };
            this.editModal ??= new bootstrap.Modal(document.getElementById('editPersonnelModal'));
            this.editModal.show();
        }
    }">
        <div class="personnel-toolbar d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <h4 class="fw-black text-dark mb-1"><b>{{ $scopeLabel }} Personnel</b></h4>
                <p class="text-muted small mb-0">
                    @if(Auth::user()->role === 'dept_head')
                        Manage faculty and staff accounts assigned to your department.
                    @else
                        Review personnel assignments, access status, roles, and departments.
                    @endif
                </p>
            </div>

            <form id="personnelFilters" action="{{ route('admin.users.manage') }}#personnel-results" method="GET" class="personnel-search d-flex align-items-center gap-2" data-dynamic-search>
                @if($category)
                    <input type="hidden" name="category" value="{{ $category }}">
                @endif
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill text-muted" aria-hidden="true">
                        <i data-lucide="search" style="width: 14px;"></i>
                    </span>
                    <input type="search" name="search" value="{{ request('search') }}"
                        class="form-control border-start-0 rounded-end-pill bg-white shadow-none"
                        maxlength="100" placeholder="Search name, ID, or email" aria-label="Search personnel" style="min-width: 230px;">
                </div>
                <a href="{{ route('admin.users.manage') }}#personnel-results" data-filter-link class="btn btn-sm btn-link text-danger text-decoration-none">Clear filters</a>
                <x-filter-submit />
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center mb-4">
                <i data-lucide="check-circle" class="me-2" style="width: 18px;"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                <div class="fw-bold mb-1">The personnel record could not be updated.</div>
                <ul class="small mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: var(--text-caption);">Visible Personnel</small>
                    <h3 class="fw-black mb-0 mt-2">{{ $stats['total'] }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-success border-4">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: var(--text-caption);">Approved Accounts</small>
                    <h3 class="fw-black text-success mb-0 mt-2">{{ $stats['active'] }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-warning border-4">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: var(--text-caption);">Suspended Accounts</small>
                    <h3 class="fw-black text-warning mb-0 mt-2">{{ $stats['suspended'] }}</h3>
                </div>
            </div>
        </div>

        <section id="personnel-results" data-filter-results data-filter-region="results" data-result-count="{{ $users->total() }}" tabindex="-1" aria-label="Matching personnel">
        <nav class="personnel-categories mb-4" aria-label="Personnel categories" data-filter-links>
            @foreach($personnelCategories as $categoryKey => $categoryDetails)
                @if($categoryKey === 'all' || ($categoryCounts[$categoryKey] ?? 0) > 0 || $activeCategory === $categoryKey)
                    @php
                        $categoryUrl = route('admin.users.manage', array_filter([
                            'category' => $categoryKey === 'all' ? null : $categoryKey,
                            'search' => request('search'),
                        ]));
                    @endphp
                    <a href="{{ $categoryUrl }}#personnel-results"
                        class="personnel-category {{ $activeCategory === $categoryKey ? 'active' : '' }}"
                        @if($activeCategory === $categoryKey) aria-current="page" @endif>
                        <span class="personnel-category__icon"><i data-lucide="{{ $categoryDetails['icon'] }}"></i></span>
                        <span class="personnel-category__copy">
                            <strong>{{ $categoryDetails['label'] }}</strong>
                            <small>{{ $categoryDetails['description'] }}</small>
                        </span>
                        <span class="personnel-category__count">{{ $categoryCounts[$categoryKey] ?? 0 }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white border-0 p-4 pb-2">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">{{ $personnelCategories[$activeCategory]['label'] }}</h6>
                        <small class="text-muted">{{ $personnelCategories[$activeCategory]['description'] }}. Actions remain limited by your role and department.</small>
                    </div>
                    @if($activeCategory !== 'all')
                        <a href="{{ route('admin.users.manage', array_filter(['search' => request('search')])) }}#personnel-results" data-filter-link class="btn btn-sm btn-light border rounded-pill px-3">
                            View all
                        </a>
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 workflow-responsive-table personnel-table">
                    <thead class="bg-light small text-muted text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Personnel</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Access Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td class="ps-4" data-label="Personnel">
                                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                                    <small class="text-muted">{{ $user->school_id }} • {{ $user->email }}</small>
                                </td>
                                <td data-label="Department">
                                    <span class="badge bg-light text-dark border rounded-pill px-3">
                                        {{ $user->academicDepartment?->dept_code ?? ($user->department ?: 'Administrative Office') }}
                                    </span>
                                </td>
                                <td data-label="Designation">
                                    <span class="small fw-semibold">{{ $roleLabels[$user->role] ?? ucwords(str_replace('_', ' ', $user->role)) }}</span>
                                </td>
                                <td data-label="Access">
                                    @if($user->account_status === \App\Models\User::ACCOUNT_ACTIVE)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-3">Suspended</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4" data-label="Action">
                                    @if(in_array($user->id, $manageableUserIds, true))
                                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold"
                                            @click="openEdit({{ json_encode([
                                                'id' => $user->id,
                                                'name' => $user->name,
                                                'role' => $user->role,
                                                'department' => $user->department,
                                                'account_status' => $user->account_status,
                                                'version' => $user->account_version,
                                            ]) }})">
                                            Manage
                                        </button>
                                    @else
                                        <span class="small text-muted">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="personnel-empty-icon mx-auto mb-2"><i data-lucide="users-round"></i></div>
                                    <div class="fw-bold text-dark">No {{ strtolower($personnelCategories[$activeCategory]['label']) }} found</div>
                                    <small class="text-muted">Try clearing the search or selecting another category.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="card-footer bg-white border-0 p-3" data-filter-pagination>{{ $users->fragment('personnel-results')->links() }}</div>
            @endif
        </div>

        </section>
        <div class="modal fade" id="editPersonnelModal" tabindex="-1" aria-hidden="true" aria-labelledby="editPersonnelModalTitle">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 bg-light p-4 pb-3">
                        <div>
                            <h6 class="modal-title fw-bold mb-0" id="editPersonnelModalTitle">Manage Personnel Record</h6>
                            <small class="text-muted" x-text="selectedUser.name"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form :action="'{{ url('/admin/users') }}/' + selectedUser.id" method="POST" :data-confirm="selectedUser.account_status === 'suspended' ? 'Suspend access for ' + selectedUser.name + '? They will be signed out and unable to sign in until access is restored.' : 'Save the designation, department, and account access settings for ' + selectedUser.name + '?'" :data-confirm-title="selectedUser.account_status === 'suspended' ? 'Suspend this account?' : 'Update personnel record?'" :data-confirm-tone="selectedUser.account_status === 'suspended' ? 'danger' : 'default'" data-confirm-accept="Save personnel record">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="version" :value="selectedUser.version">

                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="managed_role" class="form-label mini-label">Designation</label>
                                <select id="managed_role" name="role" class="form-select bg-light border-0 shadow-none" x-model="selectedUser.role" required>
                                    @foreach($manageableRoles as $role)
                                        <option value="{{ $role }}">{{ $roleLabels[$role] ?? ucwords(str_replace('_', ' ', $role)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($canChangeDepartment)
                                <div class="mb-3">
                                    <label for="managed_department" class="form-label mini-label">Department</label>
                                    <select id="managed_department" name="department" class="form-select bg-light border-0 shadow-none" x-model="selectedUser.department">
                                        <option value="">Administrative Office / Not Assigned</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->dept_code }}">{{ $department->dept_name }} ({{ $department->dept_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="department" value="{{ Auth::user()->department }}">
                                <div class="mb-3">
                                    <label class="form-label mini-label">Department Scope</label>
                                    <input type="text" class="form-control bg-light border-0" value="{{ Auth::user()->department }}" readonly>
                                </div>
                            @endif

                            <div>
                                <label for="managed_status" class="form-label mini-label">Account Access</label>
                                <select id="managed_status" name="account_status" class="form-select bg-light border-0 shadow-none" x-model="selectedUser.account_status" required>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                <small class="text-muted d-block mt-2">Suspended accounts are signed out and cannot sign in until access is restored.</small>
                            </div>

                            <div class="mt-3" x-show="selectedUser.account_status === 'suspended'" x-cloak>
                                <label for="change_reason" class="form-label mini-label">Suspension reason</label>
                                <textarea id="change_reason" name="change_reason" class="form-control bg-light border-0 shadow-none" rows="3" maxlength="500" :required="selectedUser.account_status === 'suspended'" placeholder="Record a concise administrative reason"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-htc rounded-pill px-4 fw-bold">Save Personnel Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @pushOnce('styles', 'page-styles:admin/manage-users.blade.php')
    <style>
        .mini-label { font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: var(--tracking-label); }
        .btn-htc { background-color: var(--htc-green); color: white; border: none; }
        .btn-htc:hover { background-color: #0d2e16; color: white; }
        .personnel-categories { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-12); }
        .personnel-category { min-width: 0; display: flex; align-items: center; gap: var(--space-12); padding: var(--space-16); border: 1px solid #dfe8e2; border-radius: 15px; color: #526258; background: #fff; text-decoration: none; box-shadow: 0 5px 16px rgba(20, 62, 37, .045); transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
        .personnel-category:hover { border-color: #91b9a0; color: #214a31; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(20, 62, 37, .08); }
        .personnel-category.active { border-color: #27764b; color: #174d2e; background: #edf8f1; box-shadow: inset 0 0 0 1px #27764b; }
        .personnel-category__icon { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center; border-radius: 11px; color: #27764b; background: #e8f3ec; }
        .personnel-category.active .personnel-category__icon { color: #fff; background: #27764b; }
        .personnel-category__icon svg { width: 18px; height: 18px; }
        .personnel-category__copy { min-width: 0; flex: 1; }
        .personnel-category__copy strong, .personnel-category__copy small { display: block; }
        .personnel-category__copy strong { font-size: var(--text-secondary); }
        .personnel-category__copy small { margin-top: .12rem; overflow: hidden; color: #7b8980; font-size: var(--text-caption); line-height: var(--line-heading); text-overflow: ellipsis; white-space: nowrap; }
        .personnel-category__count { min-width: 28px; padding: var(--space-4) var(--space-8); border-radius: 999px; color: #53675a; background: #eef2ef; font-size: var(--text-secondary); font-weight: 800; text-align: center; }
        .personnel-category.active .personnel-category__count { color: #19603a; background: #d7ecdf; }
        .personnel-empty-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 12px; color: #678071; background: #edf3ef; }
        .personnel-empty-icon svg { width: 20px; height: 20px; }
        @media (max-width: 1199.98px) {
            .personnel-categories { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 767.98px) {
            .personnel-toolbar { align-items: stretch !important; flex-direction: column; }
            .personnel-search { width: 100%; }
            .personnel-search .input-group { flex: 1; }
            .personnel-search input { min-width: 0 !important; min-height: 42px; font-size: var(--text-body); }
            .personnel-page > .row.g-3 { --bs-gutter-y: var(--space-12); }
            .personnel-page > .card > .card-header { padding: var(--space-16) !important; }
            .personnel-categories { display: flex; gap: var(--space-8); margin-right: calc(-1 * var(--space-12)); padding-right: var(--space-12); overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: thin; }
            .personnel-category { min-width: 230px; scroll-snap-align: start; }
        }
        @media (max-width: 575.98px) {
            #editPersonnelModal .modal-dialog { width: 100%; max-width: none; height: 100%; min-height: 100%; margin: 0; }
            #editPersonnelModal .modal-content { height: 100dvh; max-height: 100dvh; border-radius: 0 !important; }
            #editPersonnelModal .modal-header,
            #editPersonnelModal .modal-body,
            #editPersonnelModal .modal-footer { padding: var(--space-16) !important; }
            #editPersonnelModal select,
            #editPersonnelModal input { min-height: 44px; font-size: var(--text-body); }
            #editPersonnelModal .modal-footer { align-items: stretch; flex-direction: column-reverse; }
            #editPersonnelModal .modal-footer .btn { width: 100%; min-height: 44px; margin: 0; }
        }
    </style>
    @endPushOnce
</x-app-layout>
