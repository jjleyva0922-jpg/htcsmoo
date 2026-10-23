<form id="approvalFilters" method="GET" action="{{ route('admin.approvals') }}#approval-results" data-dynamic-search class="card border-0 shadow-sm rounded-4 p-3 mb-3">
    <input type="hidden" name="tab" value="{{ $selectedTab }}">
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <label for="queueSearch" class="form-label">Search requisitions</label>
            <input id="queueSearch" name="search" type="search" maxlength="100" value="{{ request('search') }}" class="form-control" placeholder="Tracking number, requestor, item or purpose">
        </div>
        <div class="col-6 col-lg-2">
            <label for="queueDepartment" class="form-label">Department</label>
            <select id="queueDepartment" name="dept" class="form-select"><option value="">All departments</option>
                @foreach($departments as $department)<option value="{{ $department }}" @selected(request('dept') === $department)>{{ $department }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="queueTier" class="form-label">Tier</label>
            <select id="queueTier" name="tier" class="form-select"><option value="">All tiers</option><option value="minor" @selected(request('tier') === 'minor')>Minor</option><option value="major" @selected(request('tier') === 'major')>Major</option></select>
        </div>
        <div class="col-12 col-lg-4">
            <label for="queueStatus" class="form-label">Status</label>
            <select id="queueStatus" name="status" class="form-select"><option value="">All assigned statuses</option>
                @foreach(['approved_vp' => 'VP approved', 'approved_president' => 'Executive route approved', 'processing' => 'SMO processing', 'ready_for_pickup' => 'Ready for pickup', 'for_clarification' => 'Clarification required'] + (Auth::user()->role !== 'smo' ? ['pending' => 'Department review', 'approved_dept' => 'VP review', 'approved_provost' => 'President review'] : []) as $status => $label)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-3"><label for="queueFrom" class="form-label">Submitted from</label><input id="queueFrom" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-6 col-lg-3"><label for="queueTo" class="form-label">Submitted through</label><input id="queueTo" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-12 col-lg-3"><label for="queueSort" class="form-label">Sort</label>
            <select id="queueSort" name="sort" class="form-select">
                @foreach(['priority' => 'Priority, then newest', 'oldest' => 'Oldest waiting', 'deadline' => 'Deadline risk', 'newest' => 'Newest submitted'] as $sort => $label)<option value="{{ $sort }}" @selected(request('sort', 'priority') === $sort)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-lg-3 d-flex align-items-end gap-2"><x-filter-submit /><a href="{{ route('admin.approvals', ['tab' => $selectedTab]) }}#approval-results" data-filter-link class="btn btn-outline-secondary">Clear filters</a></div>
    </div>
    <small class="text-muted mt-2">Filters update automatically. Date ranges require both dates. Counts show matching requests in each assigned group.</small>
</form>
