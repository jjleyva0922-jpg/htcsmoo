<x-app-layout>
    <x-slot name="header">{{ __('Active Requisitions') }}</x-slot>

    @php($isEmployee = Auth::user()->role === 'employee')
    <div class="request-workspace {{ $isEmployee ? 'personal-list' : '' }}" data-filter-workspace="requests">
        @if($isEmployee)
            <header class="personal-list-heading">
                <div><h2>My active requests</h2><p>A clear view of your progress and next steps.</p></div>
                <nav class="personal-navigation" aria-label="My requisitions">
                    <button type="button" class="btn btn-htc" data-bs-toggle="modal" data-bs-target="#globalRequestModal"><i data-lucide="plus" aria-hidden="true"></i>New requisition</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-light border">My dashboard</a>
                    <a href="{{ route('requests.archive') }}" class="btn btn-light border"><i data-lucide="archive" aria-hidden="true"></i>View my archive</a>
                </nav>
            </header>
            @if($errors->any())<div class="alert alert-warning" role="alert"><strong>Request filters were not applied.</strong> {{ $errors->first() }} <a href="{{ route('requests.index') }}">Clear filters</a></div>@endif
        @endif
        <section class="request-metrics" data-filter-region="metrics">
            <article class="request-metric total"><span><i data-lucide="layers-3"></i></span><div><strong>{{ $requestStats['total'] }}</strong><h3>All active</h3><p>Currently in the pipeline</p></div></article>
            <article class="request-metric pending"><span><i data-lucide="clock-3"></i></span><div><strong>{{ $requestStats['pending'] }}</strong><h3>New submissions</h3><p>Awaiting first review</p></div></article>
            <article class="request-metric clarification"><span><i data-lucide="message-circle-warning"></i></span><div><strong>{{ $requestStats['clarification'] }}</strong><h3>Needs clarification</h3><p>Requires requestor action</p></div></article>
            <article class="request-metric ready"><span><i data-lucide="package-check"></i></span><div><strong>{{ $isEmployee ? $requestStats['pickup'] : $requestStats['ready'] }}</strong><h3>{{ $isEmployee ? 'Ready for pickup' : 'Ready for release' }}</h3><p>{{ $isEmployee ? 'Marked ready by SMO' : 'Fully approved requests' }}</p></div></article>
            <article class="request-metric deadline {{ $requestStats['deadline'] > 0 ? 'attention' : '' }}"><span><i data-lucide="siren"></i></span><div><strong>{{ $requestStats['deadline'] }}</strong><h3>Deadline watch</h3><p>Day 2 or already overdue</p></div></article>
        </section>
        @if($isEmployee)
            <p class="small text-muted">The three-working-day target runs from submission and excludes weekends and institutional holidays. It is not a guaranteed pickup date.</p>
            @php($requestTabFilters = request()->only('search', 'date_from', 'date_to', 'order'))
            <nav class="personal-filter-tabs" aria-label="Personal request filters" data-filter-region="tabs" data-filter-links>
                <a href="{{ route('requests.index', $requestTabFilters) }}#request-results" @if(!request('status')) aria-current="page" @endif>All requests <span>{{ $requestStats['total'] }}</span></a>
                <a href="{{ route('requests.index', [...$requestTabFilters, 'status' => 'action_required']) }}#request-results" @if(request('status') === 'action_required') aria-current="page" @endif>Action required <span>{{ $requestStats['clarification'] + $requestStats['pickup'] }}</span></a>
                <a href="{{ route('requests.index', [...$requestTabFilters, 'status' => 'ready_for_pickup']) }}#request-results" @if(request('status') === 'ready_for_pickup') aria-current="page" @endif>Ready for pickup <span>{{ $requestStats['pickup'] }}</span></a>
                <a href="{{ route('requests.index', [...$requestTabFilters, 'status' => 'for_clarification']) }}#request-results" @if(request('status') === 'for_clarification') aria-current="page" @endif>Clarification required <span>{{ $requestStats['clarification'] }}</span></a>
            </nav>
        @endif

        @if(in_array(Auth::user()->role, ['smo', 'dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'], true))
            @include('requests.partials.approval-deadline-watch', ['approvalDeadlineRequests' => $approvalDeadlineRequests])
        @endif

        <section class="request-panel">
            <div class="request-panel-heading" data-filter-region="heading">
                <div>
                    <span class="request-eyebrow">Current workflow</span>
                    <h3>In-progress requests <em>{{ $activeRequests->total() }}</em></h3>
                    <p>Search, filter, and open any active requisition.</p>
                </div>
                @if(request()->anyFilled(['search', 'status', 'dept', 'date_from', 'date_to', 'order']))
                    <a href="{{ route('requests.index') }}#request-results" class="clear-request-filters" data-filter-link><i data-lucide="rotate-ccw"></i> Clear filters</a>
                @endif
            </div>

            <form id="requestFilters" method="GET" action="{{ route('requests.index') }}#request-results" class="request-filters" data-dynamic-search>
                <label class="request-search">
                    <span>Search requests</span>
                    <div><i data-lucide="search"></i><input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Code, item, purpose, requestor, or department"></div>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">All active stages</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending Department Head</option>
                        <option value="approved_dept" @selected(request('status') === 'approved_dept')>{{ $isEmployee ? 'VP review (Finance / Administration)' : 'Department approved' }}</option>
                        <option value="approved_vp" @selected(request('status') === 'approved_vp')>{{ $isEmployee ? 'SMO processing queue (minor) / Provost review (major)' : 'VP approved' }}</option>
                        <option value="approved_provost" @selected(request('status') === 'approved_provost')>{{ $isEmployee ? 'President review' : 'Provost approved' }}</option>
                        <option value="approved_president" @selected(request('status') === 'approved_president')>{{ $isEmployee ? 'SMO processing queue (presidential approval)' : 'President approved' }}</option>
                        @if($isEmployee)
                            <option value="processing" @selected(request('status') === 'processing')>SMO processing</option>
                            <option value="ready_for_pickup" @selected(request('status') === 'ready_for_pickup')>Ready for pickup</option>
                            <option value="action_required" @selected(request('status') === 'action_required')>Action required (clarification / pickup)</option>
                        @endif
                        <option value="for_clarification" @selected(request('status') === 'for_clarification')>Needs clarification</option>
                        <option value="deadline" @selected(request('status') === 'deadline')>Deadline risks</option>
                    </select>
                </label>
                @if(Auth::user()->role === 'smo')
                    <label>
                        <span>Department</span>
                        <select name="dept">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department }}" @selected(request('dept') === $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label><span>{{ $isEmployee ? 'Submitted from' : 'From' }}</span><input type="date" name="date_from" value="{{ request('date_from') }}"></label>
                <label><span>{{ $isEmployee ? 'Submitted through' : 'To' }}</span><input type="date" name="date_to" value="{{ request('date_to') }}"></label>
                <label><span>Sort</span><select name="order"><option value="desc" @selected(request('order', 'desc') === 'desc')>Newest first</option><option value="asc" @selected(request('order') === 'asc')>Oldest first</option></select></label>
                <x-filter-submit />
            </form>

            <div id="request-results" data-filter-results data-filter-region="results" data-result-count="{{ $activeRequests->total() }}" tabindex="-1" aria-label="Matching active requests">
            @include('requests.partials.history-table', ['requisitions' => $activeRequests, 'type' => 'active'])

            @if($activeRequests->hasPages())
                <div class="request-pagination" data-filter-pagination>{{ $activeRequests->fragment('request-results')->links() }}</div>
            @endif
            </div>
        </section>
    </div>
    @if($isEmployee) @include('requests.partials.personal-styles') @endif

    @pushOnce('styles', 'page-styles:requests/index.blade.php')
    <style>
        .request-workspace { padding: var(--space-4); color: #293a30; }.request-hero { min-height: 175px; display: flex; align-items: center; justify-content: space-between; gap: var(--space-32); margin-bottom: var(--space-16); padding: var(--space-24) var(--space-32); overflow: hidden; border-radius: 20px; background: radial-gradient(circle at 10% -80%,rgba(74,222,128,.25),transparent 22rem),radial-gradient(circle at 95% 150%,rgba(255,255,255,.12),transparent 16rem),linear-gradient(125deg,#052e16,#14532d 58%,#17713d); color: #fff; box-shadow: 0 16px 38px rgba(12,68,35,.18); }.request-eyebrow { display: block; margin-bottom: var(--space-8); color: #8ee5aa; font-size: var(--text-caption); font-weight: 900; letter-spacing: var(--tracking-label); text-transform: uppercase; }.request-hero h2 { margin: 0 0 var(--space-8); color: #fff; font-size: var(--text-heading-md); font-weight: 800; letter-spacing: var(--tracking-normal); }.request-hero p { max-width: 600px; margin: 0; color: rgba(239,251,243,.72); font-size: var(--text-secondary); line-height: var(--line-body); }.request-hero-actions { min-width: 160px; display: flex; flex-direction: column; gap: var(--space-8); }.request-hero-actions :is(button,a) { min-height: 41px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-8); padding: var(--space-8) var(--space-12); border: 1px solid rgba(255,255,255,.19); border-radius: 10px; font-size: var(--text-caption); font-weight: 800; text-decoration: none; transition: transform .18s ease; }.request-hero-actions button { background: #fff; color: #14532d; }.request-hero-actions a { background: rgba(255,255,255,.09); color: #fff; }.request-hero-actions :is(button,a):hover { transform: translateY(-2px); }.request-hero-actions svg { width: 15px; }
        .request-metrics { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: var(--space-12); margin-bottom: var(--space-16); }.request-metric { --tone:#51645a;--soft:#eef3f0; min-width: 0; display: flex; align-items: center; gap: var(--space-12); padding: var(--space-16); border: 1px solid #dfe8e2; border-radius: 15px; background: #fff; box-shadow: 0 6px 18px rgba(22,65,39,.05); }.request-metric > span { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center; border-radius: 11px; color: var(--tone); background: var(--soft); }.request-metric svg { width: 18px; }.request-metric strong { display: block; color: var(--tone); font-size: var(--text-heading-sm); line-height: var(--line-heading); }.request-metric h3 { margin: var(--space-4) 0 .08rem; color: #35473c; font-size: var(--text-heading-sm); font-weight: 800; }.request-metric p { margin: 0; color: #8b978f; font-size: var(--text-secondary); white-space: nowrap; }.request-metric.pending { --tone:#956109;--soft:#fff1d2; }.request-metric.clarification { --tone:#a55b0a;--soft:#ffead2; }.request-metric.ready { --tone:#176c42;--soft:#e1f3e7; }.request-metric.deadline { --tone:#637169;--soft:#eef2ef; }.request-metric.deadline.attention { --tone:#b82f38;--soft:#fde4e6; border-color:#ecc3c6; background:linear-gradient(145deg,#fff,#fff7f7); animation:requestAlert 2.8s ease-in-out infinite; }
        .request-panel { overflow: hidden; border: 1px solid #dfe8e2; border-radius: 18px; background: #fff; box-shadow: 0 8px 26px rgba(23,65,40,.06); }.request-panel-heading { min-height: 86px; display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-16) var(--space-24); border-bottom: 1px solid #e5ece7; }.request-panel-heading .request-eyebrow { margin-bottom: var(--space-4); color: #368258; }.request-panel-heading h3 { margin: 0; color: #293d31; font-size: var(--text-heading-sm); font-weight: 800; }.request-panel-heading h3 em { display: inline-grid; min-width: 23px; height: 23px; margin-left: var(--space-4); place-items: center; border-radius: 999px; background: #e5f3e9; color: #226a43; font-size: var(--text-caption); font-style: normal; }.request-panel-heading p { margin: var(--space-4) 0 0; color: #87938b; font-size: var(--text-secondary); }.clear-request-filters { display: inline-flex; align-items: center; gap: var(--space-4); color: #a4383f; font-size: var(--text-caption); font-weight: 800; text-decoration: none; }.clear-request-filters svg { width: 13px; }
        .request-filters { display: grid; grid-template-columns: minmax(220px,1.7fr) repeat(5,minmax(105px,.75fr)) auto; gap: var(--space-8); align-items: end; padding: var(--space-12) var(--space-16); border-bottom: 1px solid #e7ede9; background: #fafcfb; }.request-filters label { min-width: 0; }.request-filters label > span { display: block; margin: 0 0 var(--space-4) .12rem; color: #78867d; font-size: var(--text-secondary); font-weight: 900; letter-spacing: var(--tracking-label); text-transform: uppercase; }.request-filters :is(input,select) { width: 100%; min-height: 37px; padding: var(--space-8) var(--space-8); border: 1px solid #d8e4dc; border-radius: 8px; outline: 0; color: #46584d; background: #fff; font-size: var(--text-body); }.request-filters :is(input,select):focus { border-color: #2f8558; box-shadow: 0 0 0 3px rgba(34,126,76,.1); }.request-search > div { position: relative; }.request-search svg { position: absolute; top: 50%; left: .65rem; width: 14px; color: #87948c; transform: translateY(-50%); }.request-search input { padding-left: var(--space-32); }.request-filters > button { min-height: 37px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-4); padding: var(--space-8) var(--space-12); border: 0; border-radius: 8px; background: #1b7045; color: #fff; font-size: var(--text-caption); font-weight: 800; }.request-filters > button svg { width: 13px; }
        .request-panel > .request-history-card { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }.request-panel .table-responsive { border: 0 !important; border-radius: 0 !important; }.request-pagination { padding: var(--space-12) var(--space-16); border-top: 1px solid #e5ece7; }.request-empty { min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--space-32); text-align: center; }.request-empty > span { width: 52px; height: 52px; display: grid; place-items: center; margin-bottom: var(--space-12); border-radius: 16px; background: #e8f4ec; color: #277048; }.request-empty svg { width: 24px; }.request-empty strong { color: #3e5145; font-size: var(--text-caption); }.request-empty p { margin: var(--space-4) 0 var(--space-12); color: #8b978f; font-size: var(--text-secondary); }.request-empty button { padding: var(--space-8) var(--space-12); border: 0; border-radius: 8px; color: #fff; background: #1b7045; font-size: var(--text-caption); font-weight: 800; }
        .request-metric > div { min-width: 0; }
        .request-metric h3, .request-metric p { overflow-wrap: anywhere; white-space: normal; }
        .request-metric p { font-size: var(--text-secondary); }
        @media(max-width:1599.98px) { .request-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); } }
        @keyframes requestAlert { 0%,100%{box-shadow:0 6px 18px rgba(184,47,56,.06)}50%{box-shadow:0 8px 24px rgba(184,47,56,.18)} }
        @media(max-width:1399.98px){.request-filters{grid-template-columns:repeat(4,minmax(0,1fr))}.request-search{grid-column:span 2}.request-filters > button{align-self:end}}
        @media(max-width:991.98px){.request-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.request-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.request-search{grid-column:1/-1}}
        @media(max-width:767.98px){.request-workspace{padding:0}.request-hero{min-height:auto;align-items:stretch;flex-direction:column;padding:var(--space-24);border-radius:17px}.request-hero-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%}.request-panel-heading{align-items:flex-start;flex-direction:column;padding:var(--space-16)}.request-filters{padding:var(--space-12)}.request-panel .table-responsive{overflow:visible!important}.workflow-responsive-table{display:block;min-width:0!important;background:#f5f8f6}.workflow-responsive-table thead{display:none}.workflow-responsive-table tbody{display:grid;gap:var(--space-12);padding:var(--space-12)}.workflow-responsive-table tr{display:block;overflow:hidden;border:1px solid #e0e9e3;border-radius:13px;background:#fff;box-shadow:0 3px 12px rgba(24,58,37,.05)}.workflow-responsive-table td{display:grid;grid-template-columns:minmax(82px,32%) minmax(0,1fr);align-items:center;gap:var(--space-12);width:100%;padding:var(--space-12) var(--space-12)!important;border-color:#edf1ee;text-align:right!important;overflow-wrap:anywhere}.workflow-responsive-table td::before{content:attr(data-label);color:#819087;font-size:var(--text-caption);font-weight:800;text-align:left;text-transform:uppercase}.workflow-responsive-table td > *{justify-self:end}.workflow-responsive-table td[colspan]{display:block;text-align:center!important}.workflow-responsive-table td[colspan]::before{display:none}.request-person{text-align:left}.request-actions{justify-content:flex-end}}
        @media(max-width:480px){.request-metrics,.request-hero-actions,.request-filters{grid-template-columns:1fr}.request-search{grid-column:auto}.request-metric p{white-space:normal}.workflow-responsive-table td{grid-template-columns:76px minmax(0,1fr)}}
        @media(prefers-reduced-motion:reduce){.request-metric.deadline.attention{animation:none}}
    </style>
    @endPushOnce
</x-app-layout>
