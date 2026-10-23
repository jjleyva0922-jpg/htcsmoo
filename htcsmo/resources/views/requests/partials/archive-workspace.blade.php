@php
    $archiveFilters = Illuminate\Support\Arr::except($filters, ['page', 'print', 'export']);
    $hasArchiveFilters = collect($archiveFilters)->contains(fn ($value) => filled($value));
    $dateBasisLabel = ($filters['date_basis'] ?? 'closed') === 'submitted' ? 'Submission date' : 'Closure date';
    $categoryLabels = ['all' => 'All outcomes', 'fulfilled' => 'Fulfilled', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'];
@endphp
<div class="container-fluid py-2 archive-workspace {{ $institutional ? 'institutional-archive-page' : 'personal-list' }}" data-filter-workspace="archive">
    <header class="archive-heading" data-filter-region="heading">
        <div><span class="archive-eyebrow">{{ $institutional ? 'Institutional records' : 'My request history' }}</span><h1>{{ $institutional ? 'Institutional Archive' : 'My requisition archive' }}</h1><p>Find fulfilled, rejected, and withdrawn requests. Submitted records and their history are retained.</p></div>
        <div class="archive-heading-actions archive-screen-only">
            <a class="btn btn-light border" href="{{ route($archiveRoute, [...$archiveFilters, 'export' => 'csv']) }}" download data-download><i data-lucide="file-down" aria-hidden="true"></i>Export filtered CSV</a>
            <a class="btn btn-htc" href="{{ route($archiveRoute, [...$archiveFilters, 'print' => 1]) }}" target="_blank" rel="noopener"><i data-lucide="printer" aria-hidden="true"></i>Print filtered archive</a>
        </div>
    </header>
    @if(session('success'))<p class="alert alert-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="alert alert-warning" role="alert"><strong>Archive filters were not applied.</strong> {{ $errors->first() }}</p>@endif

    <nav class="archive-categories archive-screen-only" aria-label="Archive outcomes" data-filter-region="categories" data-filter-links>
        @foreach($categoryLabels as $key => $label)
            @php($categoryQuery = Illuminate\Support\Arr::except($archiveFilters, ['category', 'status']))
            <a href="{{ route($archiveRoute, $key === 'all' ? $categoryQuery : [...$categoryQuery, 'category' => $key]) }}#archive-results" @if(($filters['category'] ?? 'all') === $key) aria-current="page" @endif>{{ $label }} <span>{{ $categoryCounts[$key] }}</span></a>
        @endforeach
    </nav>
    <p class="archive-help archive-screen-only">Category counts follow search, date, and department filters. Changing category clears the final-status filter.</p>

    <form id="archiveFilters" action="{{ route($archiveRoute) }}#archive-results" method="GET" class="archive-filters archive-filter-card archive-screen-only" data-dynamic-search>
        @if(filled($filters['category'] ?? null))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
        <div class="archive-search"><label for="archiveSearch">Search</label><input id="archiveSearch" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="form-control" placeholder="{{ $institutional ? 'Tracking number, item, requestor, or remarks' : 'Tracking number, item, or remarks' }}"></div>
        <div><label for="archiveStatus">Final status</label><select id="archiveStatus" name="status" class="form-select"><option value="">All final statuses</option>@foreach(['completed' => 'Completed (pickup confirmed)', 'released' => 'Released (legacy)', 'rejected' => 'Rejected / disapproved', 'withdrawn' => 'Withdrawn by requestor'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @if($institutional)<div><label for="archiveDepartment">Department</label><select id="archiveDepartment" name="dept" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(($filters['dept'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></div>@endif
        <div><label for="archiveDateBasis">Date basis</label><select id="archiveDateBasis" name="date_basis" class="form-select"><option value="closed" @selected(($filters['date_basis'] ?? 'closed') === 'closed')>Closure date</option><option value="submitted" @selected(($filters['date_basis'] ?? '') === 'submitted')>Submission date</option></select></div>
        <div><label for="archiveFrom">From (inclusive)</label><input id="archiveFrom" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
        <div><label for="archiveTo">To (inclusive)</label><input id="archiveTo" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
        <div><label for="archiveOrder">Sort selected date</label><select id="archiveOrder" name="order" class="form-select"><option value="desc" @selected(($filters['order'] ?? 'desc') === 'desc')>Newest first</option><option value="asc" @selected(($filters['order'] ?? '') === 'asc')>Oldest first</option></select></div>
        <div class="archive-filter-actions"><x-filter-submit /><a class="btn btn-light border" data-filter-link href="{{ route($archiveRoute) }}#archive-results">Clear all filters</a></div>
    </form>

    <div id="archive-results" data-filter-region="results" data-filter-results data-result-count="{{ $summary->total }}" tabindex="-1" aria-label="Matching archived requests">
    <section class="archive-result-summary" aria-label="Filtered archive summary">
        <div><span>Matching requests</span><strong>{{ $summary->total }}</strong></div>
        <div><span>Fulfilled</span><strong>{{ $summary->fulfilled }}</strong></div>
        <div><span>Rejected</span><strong>{{ $summary->rejected }}</strong></div>
        <div><span>Withdrawn</span><strong>{{ $summary->withdrawn }}</strong></div>
        <div><span>Requested value of matches</span><strong>&#8369;{{ number_format((float) $summary->requested_value, 2) }}</strong></div>
    </section>

    @include('requests.partials.history-table', ['requisitions' => $records, 'type' => 'archive'])
    @if(method_exists($records, 'hasPages') && $records->hasPages())<nav class="archive-pagination archive-screen-only mt-3" aria-label="Archive pages" data-filter-pagination>{{ $records->fragment('archive-results')->links() }}</nav>@endif
    </div>
</div>
@if(request()->boolean('print'))<script>window.addEventListener('load', () => window.print());</script>@endif
@pushOnce('styles', 'page-styles:requests/partials/archive-workspace.blade.php')
<style>
    .archive-heading { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-16); margin-bottom:var(--space-24); }
    .archive-heading h1 { margin:var(--space-4) 0; font-size:var(--text-heading-lg); font-weight:750; color:#254734; }
    .archive-heading p,.archive-help { margin:0; color:#607469; font-size:var(--text-secondary); }
    .archive-eyebrow { color:#5b7b66; text-transform:uppercase; letter-spacing:var(--tracking-label); font-size:var(--text-caption); font-weight:700; }
    .archive-heading-actions,.archive-filter-actions { display:flex; flex-wrap:wrap; gap:var(--space-8); align-items:center; }
    .archive-heading-actions svg { width:16px; height:16px; margin-right:var(--space-4); }
    .archive-categories { display:flex; flex-wrap:wrap; gap:var(--space-8); margin-bottom:var(--space-8); }
    .archive-categories a { display:inline-flex; align-items:center; gap:var(--space-8); min-height:40px; padding:var(--space-8) var(--space-12); border:1px solid #dce7df; border-radius:8px; background:#fff; color:#365a43; font-size:var(--text-secondary); font-weight:650; text-decoration:none; }
    .archive-categories a[aria-current] { border-color:#33744c; background:#eaf4ed; color:#1d5833; }
    .archive-categories span { padding:.1rem var(--space-8); border-radius:5px; background:#f0f4f1; font-variant-numeric:tabular-nums; }
    .archive-help { margin-bottom:var(--space-16); font-size:var(--text-caption); }
    .archive-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:var(--space-12); padding:var(--space-16); border:1px solid #dfe8e2; border-radius:12px; background:#fff; margin-bottom:var(--space-16); }
    .archive-filters > * { min-width:0; }.archive-search { grid-column:span 2; }
    .archive-filters label { display:block; margin-bottom:var(--space-8); color:#45614d; font-size:var(--text-secondary); font-weight:650; }
    .archive-result-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)) minmax(190px,1.5fr); gap:var(--space-8); margin:var(--space-16) 0; }
    .archive-result-summary > div { padding:var(--space-12); border:1px solid #e1e9e4; border-radius:9px; background:#f6faf7; }
    .archive-result-summary span { display:block; font-size:var(--text-caption); color:#5b7262; }.archive-result-summary strong { display:block; margin-top:var(--space-4); color:#254b33; font-size:var(--text-heading-sm); font-variant-numeric:tabular-nums; }
    .archive-report-context { margin:var(--space-16) 0; color:#5d7164; font-size:var(--text-caption); line-height:var(--line-body); }.archive-report-context p { margin:var(--space-4) 0; }
    .archive-workspace :is(a,button,input,select,summary):focus-visible { outline:3px solid #b38328; outline-offset:3px; }
    .archive-workspace .archive-results-table thead th { font-size:var(--text-caption) !important; color:#526b5b !important; }
    .archive-workspace .archive-results-table tbody td { padding:var(--space-12) var(--space-12) !important; font-size:var(--text-secondary); }
    .archive-results-table :is(.history-request-code,.history-item b,.history-value) { font-size:var(--text-secondary); }
    .archive-results-table :is(.history-tier,.history-status) { font-size:var(--text-caption); }
    .archive-results-table .history-status.withdrawn { color:#53677a; background:#eaf0f5; }
    .archive-results-table small { display:block; margin-top:var(--space-4); color:#607469; font-size:var(--text-caption); line-height:var(--line-body); }
    .archive-results-table .archive-remarks { min-width:150px; max-width:280px; margin-top:var(--space-8); font-size:var(--text-secondary); color:#586e60; }
    .archive-remarks summary { min-height:24px; cursor:pointer; }.archive-remarks p { margin:var(--space-4) 0; white-space:pre-wrap; overflow-wrap:anywhere; }
    @media(max-width:991px) { .archive-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }.archive-result-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }.archive-result-summary > :last-child { grid-column:1/-1; } }
    @media(max-width:575px) { .archive-filters { grid-template-columns:minmax(0,1fr); }.archive-search { grid-column:auto; }.archive-heading-actions { width:100%; }.archive-heading-actions a { flex:1; }.archive-results-table .archive-remarks { min-width:0; }.archive-results-table .history-status { white-space:normal; } }
    @media print {
        .sidebar,.top-bar,.archive-screen-only,.archive-results-table .archive-action-column { display:none !important; }
        .main-content { width:100% !important; margin:0 !important; }.main-content-inner { padding:0 !important; border:0 !important; box-shadow:none !important; }
        .archive-results-table { min-width:0 !important; }.archive-results-table thead { display:table-header-group; }.archive-results-table tr { break-inside:avoid; }
        .archive-workspace .table-responsive { overflow:visible !important; }
        .archive-results-table tbody { display:table-row-group !important; padding:0 !important; }
        .archive-results-table tbody tr { display:table-row !important; }
        .archive-results-table tbody td { display:table-cell !important; text-align:left; }
        .archive-results-table tbody td::before { display:none !important; }
        .archive-results-table .archive-remarks > :not(summary) { display:block !important; }.archive-results-table summary { display:none; }
        .archive-results-table .history-status { color:#000 !important; background:none !important; }
        .archive-results-table { font-size:11px; line-height:1.35; }
        .archive-results-table th { font-size:9px !important; }
        .archive-results-table td, .archive-results-table td * { font-size:11px !important; }
        @page { size:landscape; margin:12mm; }
    }
</style>
@endPushOnce
