@php
    $reportOptions = [
        'requisitions' => ['Requisition Transactions', 'Every request, item, status, and amount', 'file-text'],
        'inventory' => ['Inventory Valuation', 'Stock levels, pricing, value, and health', 'boxes'],
        'dept_spending' => ['Department Fulfillment', 'Recorded fulfilled value by department', 'building-2'],
        'users' => ['Users & Personnel', 'Accounts by department, role, and status', 'users'],
        'item_consumption' => ['Fulfilled Request Items', 'Requested quantities on fulfilled requests', 'package-check'],
        'approval_audit' => ['Approval Audit', 'Signatory actions and approval history', 'stamp'],
        'inventory_movements' => ['Inventory Movements', 'Restocks, releases, and adjustments', 'arrow-left-right'],
        'user_activity' => ['User Activity', 'Authentication and security activity log', 'shield-check'],
    ];

    $departments = $departmentOptions;
    $roles = [
        'employee' => 'Employee',
        'dept_head' => 'Department Head',
        'vp_finance' => 'VP for Finance',
        'vp_admin' => 'VP for Administration',
        'provost' => 'Provost',
        'president' => 'President',
        'smo' => 'SMO In-Charge',
    ];
@endphp

    <form action="{{ route('admin.reports.builder') }}#report-preview" method="GET" id="reportForm" class="report-control-panel no-print" data-dynamic-search>
        @if(request('supply_id'))<input type="hidden" name="supply_id" value="{{ request('supply_id') }}">@endif
        <div class="report-section-heading">
            <span>1</span>
            <div><h5>Select a report</h5><p>Choose the institutional dataset you want to review.</p></div>
        </div>
        <div class="report-type-grid row g-3 mb-4">
            @foreach($reportOptions as $value => [$label, $description, $icon])
                <div class="report-type-option col-xl-3 col-md-6">
                    <label class="report-type-card {{ $reportType === $value ? 'is-selected' : '' }}">
                        <input type="radio" name="report_type" value="{{ $value }}" class="visually-hidden" @checked($reportType === $value) data-filter-navigate>
                        <span class="report-type-card__icon"><i data-lucide="{{ $icon }}"></i></span>
                        <span class="report-type-card__copy">
                            <strong>{{ $label }}</strong>
                            <small>{{ $description }}</small>
                        </span>
                        <span class="report-type-card__check" aria-hidden="true"><i data-lucide="check"></i></span>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="report-filter-card mb-4">
            <div class="report-filter-card__header">
                <div class="report-section-heading mb-0">
                    <span>2</span>
                    <div><h5>Refine the report</h5><p>Use only the filters needed for this export.</p></div>
                </div>
                <span class="report-current-type"><i data-lucide="database"></i>{{ $reportOptions[$reportType][0] }}</span>
            </div>

            <div class="report-filter-card__body">
                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <label for="period" class="form-label mini-label">Reporting Period</label>
                        <select id="period" name="period" class="form-select border-0 bg-light rounded-3 shadow-none">
                            <option value="current_semester" @selected(request('period', 'current_semester') === 'current_semester')>Current Semester</option>
                            <option value="this_month" @selected(request('period') === 'this_month')>This Month</option>
                            <option value="this_year" @selected(request('period') === 'this_year')>This Calendar Year</option>
                            <option value="all" @selected(request('period') === 'all')>All Available Records</option>
                            <option value="custom" @selected(request('period') === 'custom')>Custom Date Range</option>
                        </select>
                    </div>

                    <div class="col-lg-4 custom-date-field" data-custom-date-field @if(request('period') !== 'custom') hidden @endif>
                        <label for="date_from" class="form-label mini-label">From</label>
                        <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control border-0 bg-light rounded-3 shadow-none" @disabled(request('period') !== 'custom')>
                    </div>

                    <div class="col-lg-4 custom-date-field" data-custom-date-field @if(request('period') !== 'custom') hidden @endif>
                        <label for="date_to" class="form-label mini-label">To</label>
                        <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control border-0 bg-light rounded-3 shadow-none" @disabled(request('period') !== 'custom')>
                    </div>
                </div>

                <x-filter-date-fallback :from="request('date_from')" :to="request('date_to')" />
                @if(in_array($reportType, ['requisitions', 'dept_spending', 'users', 'item_consumption', 'approval_audit', 'user_activity']))
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="dept" class="form-label mini-label">Department</label>
                            <select id="dept" name="dept" class="form-select border-0 bg-light rounded-3 shadow-none">
                                <option value="">All Departments</option>
                                @if(in_array($reportType, ['requisitions', 'dept_spending', 'item_consumption']))
                                    <option value="__unassigned" @selected(request('dept') === '__unassigned')>Unassigned</option>
                                @endif
                                @foreach($departments as $department)
                                    <option value="{{ $department }}" @selected(request('dept') === $department)>{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if(in_array($reportType, ['users', 'approval_audit', 'user_activity']))
                            <div class="col-md-4">
                                <label for="role" class="form-label mini-label">Role</label>
                                <select id="role" name="role" class="form-select border-0 bg-light rounded-3 shadow-none">
                                    <option value="">All Roles</option>
                                    @foreach($roles as $value => $label)
                                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if($reportType === 'users')
                            <div class="col-md-4">
                                <label for="account_status" class="form-label mini-label">Account Status</label>
                                <select id="account_status" name="account_status" class="form-select border-0 bg-light rounded-3 shadow-none">
                                    <option value="">All Accounts</option>
                                    <option value="active" @selected(request('account_status') === 'active')>Active</option>
                                    <option value="pending" @selected(request('account_status') === 'pending')>Pending Approval</option>
                                    <option value="suspended" @selected(request('account_status') === 'suspended')>Suspended</option>
                                </select>
                            </div>
                        @endif
                    </div>
                @endif

                @if($reportType === 'requisitions')
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="date_basis" class="form-label mini-label">Date basis</label>
                            <select id="date_basis" name="date_basis" class="form-select border-0 bg-light rounded-3 shadow-none">
                                <option value="submitted" @selected(request('date_basis', 'submitted') === 'submitted')>Submitted requests</option>
                                <option value="fulfilled" @selected(request('date_basis') === 'fulfilled')>Completed / released requests</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tier" class="form-label mini-label">Requisition Tier</label>
                            <select id="tier" name="tier" class="form-select border-0 bg-light rounded-3 shadow-none">
                                <option value="">All Tiers</option>
                                <option value="minor" @selected(request('tier') === 'minor')>Minor</option>
                                <option value="major" @selected(request('tier') === 'major')>Major</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label mini-label">Request Status</label>
                            <select id="status" name="status" class="form-select border-0 bg-light rounded-3 shadow-none">
                                <option value="">All Statuses</option>
                                @foreach(['pending', 'pending_dept', 'approved_dept', 'approved_vp', 'approved_provost', 'approved_president', 'for_clarification', 'processing', 'ready_for_pickup', 'completed', 'released', 'rejected', 'withdrawn'] as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if(in_array($reportType, ['inventory', 'inventory_movements']))
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label mini-label">Supply Category</label>
                            <select id="category" name="category" class="form-select border-0 bg-light rounded-3 shadow-none">
                                <option value="">All Categories</option>
                                @foreach(['Cleaning Supplies', 'Construction Supplies', 'Drugs and Medicines', 'Maintenance Supplies', 'Medical Supplies', 'Non-Medical Supplies', 'Office Supplies'] as $category)
                                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($reportType === 'inventory')
                            <div class="col-md-6">
                                <label for="stock_filter" class="form-label mini-label">Stock Health</label>
                                <select id="stock_filter" name="stock_filter" class="form-select border-0 bg-light rounded-3 shadow-none">
                                    <option value="">All Stock Levels</option>
                                    <option value="low" @selected(request('stock_filter') === 'low')>Low Stock</option>
                                    <option value="out" @selected(request('stock_filter') === 'out')>Out of Stock</option>
                                </select>
                            </div>
                        @else
                            <div class="col-md-6">
                                <label for="transaction_type" class="form-label mini-label">Transaction Type</label>
                                <select id="transaction_type" name="transaction_type" class="form-select border-0 bg-light rounded-3 shadow-none">
                                    <option value="">All Transactions</option>
                                    <option value="release_deduction" @selected(request('transaction_type') === 'release_deduction')>Release Deduction</option>
                                    <option value="restock_delivery" @selected(request('transaction_type') === 'restock_delivery')>Restock Delivery</option>
                                    <option value="manual_adjustment" @selected(request('transaction_type') === 'manual_adjustment')>Manual Adjustment</option>
                                </select>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="report-actions">
                <div class="report-section-heading mb-0">
                    <span>3</span>
                    <div><h5>Preview or export</h5><p>The preview updates as you change filters. Downloads start only when selected.</p></div>
                </div>
                <div class="report-export-actions">
                    <x-filter-submit />
                    <a href="{{ route('admin.reports.builder', ['report_type' => $reportType]) }}#report-preview" data-filter-link class="btn btn-outline-dark rounded-pill">Clear filters</a>
                    <button type="submit" name="export" value="csv" class="btn btn-outline-success rounded-pill fw-bold px-4" data-download>
                        <i data-lucide="sheet" class="me-1" style="width: 16px;"></i> Export Excel CSV
                    </button>
                    <button type="button" id="downloadPdfButton" class="btn btn-htc rounded-pill fw-bold px-4">
                        <i data-lucide="file-down" class="me-1" style="width: 16px;"></i> Generate PDF
                    </button>
                    <noscript><style>#downloadPdfButton { display:none; }</style><button type="submit" name="download_pdf" value="1" class="btn btn-htc">Generate PDF</button></noscript>
                </div>
            </div>
        </div>
    </form>

    <div id="report-preview" class="report-preview-card" data-filter-results data-filter-region="preview" data-result-count="{{ $pagination?->total() ?? $rows->count() }}" tabindex="-1" aria-label="Matching report records">
        <div class="report-preview-header">
            <div>
                <span class="report-preview-kicker"><i data-lucide="eye"></i> Live preview</span>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h5 class="fw-black mb-0">{{ $title }}</h5>
                    <span class="badge bg-success-subtle text-success rounded-pill">{{ $periodLabel }}</span>
                </div>
                <small class="text-muted">{{ number_format($pagination?->total() ?? $rows->count()) }} matching records</small>
                @if($pagination)
                    <small class="text-muted d-block">Table and summary show this page. CSV exports include all matches. PDFs support up to {{ number_format($pdfRowLimit) }} rows, including requisition line items.</small>
                @endif
            </div>

            <div class="report-preview-summary d-flex align-items-center gap-4">
                <div class="text-end">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: var(--text-caption);">{{ $summaryLabel }}</small>
                    <h5 class="fw-black text-success mb-0">
                        @if($summaryType === 'money')
                            ₱{{ number_format($summaryValue, 2) }}
                        @else
                            {{ number_format($summaryValue) }}
                        @endif
                    </h5>
                </div>
                <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 no-print" onclick="window.print()">
                    <i data-lucide="printer" class="me-1" style="width: 14px;"></i> Print
                </button>
            </div>
        </div>

        @if($pagination)
            <div class="px-3 py-2" data-filter-pagination>{{ $pagination->fragment('report-preview')->links() }}</div>
        @endif
        <div class="table-responsive report-table-wrap">
            <table class="table table-hover align-middle mb-0 report-table report-responsive-table" style="font-size: var(--text-secondary);">
                <thead class="bg-light small text-muted text-uppercase">
                    <tr>
                        @foreach($columns as $column)
                            <th class="py-3 {{ $column['type'] === 'money' || $column['type'] === 'number' ? 'text-end' : '' }}">
                                {{ $column['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach($columns as $column)
                                <td data-label="{{ $column['label'] }}" class="{{ $column['type'] === 'money' || $column['type'] === 'number' ? 'text-end' : '' }}">
                                    @if($column['type'] === 'money')
                                        ₱{{ number_format((float) ($row[$column['key']] ?? 0), 2) }}
                                    @elseif($column['type'] === 'number')
                                        {{ number_format((float) ($row[$column['key']] ?? 0)) }}
                                    @else
                                        {{ $row[$column['key']] ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-center py-5 text-muted">
                                No records match the selected report filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="pdfGenerationModal" tabindex="-1" aria-hidden="true" aria-labelledby="pdfGenerationTitle" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content pdf-generation-modal border-0">
                <div class="modal-body text-center">
                    <div id="pdfGenerationSpinner" class="pdf-generation-spinner" aria-hidden="true"></div>
                    <div id="pdfGenerationErrorIcon" class="pdf-generation-error" hidden aria-hidden="true"><i data-lucide="file-warning"></i></div>
                    <span class="pdf-generation-eyebrow">Report export</span>
                    <h5 id="pdfGenerationTitle">Generating PDF report…</h5>
                    <p id="pdfGenerationMessage">Preparing the selected records and formatting your document.</p>
                    <div class="pdf-generation-progress" id="pdfGenerationProgress"><span></span></div>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" id="cancelPdfGeneration" class="btn btn-light border rounded-pill px-4">Cancel</button>
                        <button type="button" id="retryPdfGeneration" class="btn btn-htc rounded-pill px-4" hidden>Try again</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@pushOnce('styles', 'page-styles:admin/reports/index.blade.php')
<style>
    .report-builder { max-width: 1500px; margin: 0 auto; }
    .report-center-hero { position: relative; isolation: isolate; display: flex; align-items: center; justify-content: space-between; gap: var(--space-24); overflow: hidden; padding: var(--space-24) var(--space-32); border-radius: 22px; background: linear-gradient(135deg, #0d4329 0%, #17633e 56%, #238654 100%); color: #fff; box-shadow: 0 18px 45px rgba(13, 67, 41, .2); }
    .report-center-hero::after { content: ''; position: absolute; z-index: -1; width: 260px; height: 260px; top: -145px; right: 18%; border: 45px solid rgba(255,255,255,.055); border-radius: 50%; }
    .report-center-hero__content { display: flex; align-items: center; gap: var(--space-16); min-width: 0; }
    .report-center-hero__icon { width: 58px; height: 58px; flex: 0 0 58px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.16); border-radius: 17px; background: rgba(255,255,255,.12); }
    .report-center-hero__icon svg { width: 28px; height: 28px; }
    .report-center-eyebrow { display: block; margin-bottom: var(--space-4); color: #bde7cc; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
    .report-center-hero h3 { color: #fff; font-size: var(--text-heading-sm); font-weight: 800; }
    .report-center-hero p { color: rgba(255,255,255,.72); font-size: var(--text-secondary); }
    .report-center-hero__actions { display: flex; flex: 0 0 auto; gap: var(--space-8); }
    .report-center-hero__actions .btn { display: inline-flex; align-items: center; min-height: 40px; font-size: var(--text-caption); font-weight: 700; }

    .report-control-panel { padding: var(--space-24); border: 1px solid #dfe9e3; border-radius: 22px; background: #f8fbf9; box-shadow: 0 10px 30px rgba(17, 62, 38, .06); }
    .report-section-heading { display: flex; align-items: center; gap: var(--space-12); margin-bottom: var(--space-16); }
    .report-section-heading > span { width: 30px; height: 30px; flex: 0 0 30px; display: grid; place-items: center; border-radius: 10px; background: #dff2e6; color: #17633e; font-size: var(--text-caption); font-weight: 800; }
    .report-section-heading h5 { margin: 0; color: #1c3025; font-size: var(--text-heading-sm); font-weight: 800; }
    .report-section-heading p { margin: .12rem 0 0; color: #76847c; font-size: var(--text-secondary); }

    .report-type-card { position: relative; display: flex; align-items: flex-start; gap: var(--space-12); width: 100%; height: 100%; min-height: 108px; padding: var(--space-16); overflow: hidden; border: 1px solid #dfe8e3; border-radius: 15px; background: #fff; cursor: pointer; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .report-type-card:hover { border-color: #7bb795; transform: translateY(-2px); box-shadow: 0 9px 22px rgba(20, 83, 45, .09); }
    .report-type-card.is-selected { border-color: #218653; background: linear-gradient(145deg, #f4fbf7, #eaf7ef); box-shadow: 0 0 0 2px rgba(33, 134, 83, .1); }
    .report-type-card__icon { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center; border-radius: 11px; background: #edf5f0; color: #17633e; }
    .report-type-card.is-selected .report-type-card__icon { background: #17633e; color: #fff; }
    .report-type-card__icon svg { width: 18px; height: 18px; }
    .report-type-card__copy { min-width: 0; padding-right: var(--space-8); }
    .report-type-card__copy strong { display: block; margin-bottom: var(--space-4); color: #22352a; font-size: var(--text-caption); }
    .report-type-card__copy small { display: block; color: #78867e; font-size: var(--text-caption); line-height: var(--line-body); }
    .report-type-card__check { position: absolute; top: .65rem; right: .65rem; width: 19px; height: 19px; display: none; place-items: center; border-radius: 50%; background: #218653; color: #fff; }
    .report-type-card.is-selected .report-type-card__check { display: grid; }
    .report-type-card__check svg { width: 11px; height: 11px; stroke-width: 3; }

    .report-filter-card { overflow: hidden; border: 1px solid #dfe8e3; border-radius: 18px; background: #fff; }
    .report-filter-card__header { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-16) var(--space-16); border-bottom: 1px solid #e7eee9; }
    .report-filter-card__body { padding: var(--space-16); }
    .report-current-type { display: inline-flex; align-items: center; gap: var(--space-8); padding: var(--space-8) var(--space-12); border-radius: 999px; background: #eef7f1; color: #17633e; font-size: var(--text-caption); font-weight: 800; white-space: nowrap; }
    .report-current-type svg { width: 13px; height: 13px; }
    #reportForm .form-select, #reportForm .form-control { min-height: 44px; border: 1px solid #dde7e1 !important; background-color: #f8faf9 !important; }
    #reportForm .form-select:focus, #reportForm .form-control:focus { border-color: #3a9b66 !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(33, 134, 83, .1) !important; }
    .report-actions { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-16) var(--space-16); border-top: 1px solid #e3ebe6; background: #f6f9f7; }
    .report-export-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-8); }
    .report-export-actions .btn { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; }

    .report-preview-card { margin-top: var(--space-24); overflow: hidden; border: 1px solid #dfe8e3; border-radius: 20px; background: #fff; box-shadow: 0 12px 32px rgba(17, 62, 38, .07); }
    .report-preview-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-24) var(--space-24); border-bottom: 1px solid #e5ece7; background: linear-gradient(180deg, #fff, #fbfdfc); }
    .report-preview-kicker { display: inline-flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-8); color: #218653; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
    .report-preview-kicker svg { width: 13px; height: 13px; }
    .report-table-wrap { max-height: 620px; }
    .report-table thead { position: sticky; top: 0; z-index: 2; }
    .report-table th { border-bottom: 1px solid #dce6e0; background: #f3f7f4; color: #65756c; font-size: var(--text-caption); letter-spacing: var(--tracking-label); }
    .report-table td { padding-top: var(--space-12); padding-bottom: var(--space-12); border-color: #edf1ef; color: #34473c; }

    .pdf-generation-modal { border-radius: 22px !important; }
    .pdf-generation-modal .modal-body { padding: var(--space-32) var(--space-24); }
    .pdf-generation-spinner { width: 56px; height: 56px; margin: 0 auto var(--space-16); border: 5px solid #dcebe2; border-top-color: #187044; border-radius: 50%; animation: pdf-generation-spin .72s linear infinite; }
    .pdf-generation-error { width: 56px; height: 56px; display: grid; place-items: center; margin: 0 auto var(--space-16); border-radius: 17px; background: #feeceb; color: #b42318; }
    .pdf-generation-error svg { width: 27px; height: 27px; }
    .pdf-generation-eyebrow { color: #218653; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
    .pdf-generation-modal h5 { margin: var(--space-4) 0 var(--space-8); color: #1d3025; font-weight: 800; }
    .pdf-generation-modal p { margin: 0; color: #6d7d74; font-size: var(--text-body); line-height: var(--line-body); }
    .pdf-generation-progress { height: 4px; margin-top: var(--space-24); overflow: hidden; border-radius: 999px; background: #e4eee8; }
    .pdf-generation-progress span { display: block; width: 42%; height: 100%; border-radius: inherit; background: #218653; animation: pdf-generation-progress 1.2s ease-in-out infinite; }
    @keyframes pdf-generation-spin { to { transform: rotate(360deg); } }
    @keyframes pdf-generation-progress { 0% { transform: translateX(-120%); } 100% { transform: translateX(340%); } }

    @media screen and (max-width: 767.98px) {
        .report-center-hero { align-items: stretch; flex-direction: column; padding: var(--space-16); border-radius: 17px; }
        .report-center-hero__content { align-items: flex-start; }
        .report-center-hero__icon { width: 46px; height: 46px; flex-basis: 46px; border-radius: 13px; }
        .report-center-hero h3 { font-size: var(--text-heading-sm); }
        .report-center-hero__actions { display: grid; grid-template-columns: 1fr 1fr; }
        .report-center-hero__actions .btn { justify-content: center; padding-inline: var(--space-12) !important; font-size: var(--text-caption); }
        .report-control-panel { padding: var(--space-16); border-radius: 17px; }
        .reports-workspace > .row.g-3 { --bs-gutter-x: var(--space-12); --bs-gutter-y: var(--space-12); }
        .reports-workspace > .row.g-3 .card { padding: var(--space-12) !important; }
        .reports-workspace > .row.g-3 h4 { font-size: var(--text-heading-sm); overflow-wrap: anywhere; }
        .reports-workspace > .row.g-4 { --bs-gutter-y: var(--space-16); }
        .reports-workspace > .row.g-4 .card.p-4 { padding: var(--space-16) !important; min-height: 280px; }
        .reports-workspace canvas { max-height: 245px !important; }
        .report-builder { margin-top: var(--space-24) !important; padding-top: var(--space-24) !important; }
        .report-type-grid {
            display: grid;
            grid-template-columns: none;
            grid-auto-flow: column;
            grid-auto-columns: minmax(255px, 82vw);
            margin-right: calc(-1 * var(--space-12));
            margin-left: calc(-1 * var(--space-12));
            padding: 0 var(--space-12) var(--space-8);
            overflow-x: auto;
            scroll-snap-type: x proximity;
            scrollbar-width: thin;
        }
        .report-type-option { width: auto; padding-right: 0; padding-left: 0; scroll-snap-align: start; }
        .report-type-card { min-height: 105px; }
        .report-filter-card__header { align-items: flex-start; flex-direction: column; }
        .report-current-type { white-space: normal; }
        #reportForm .card-header,
        #reportForm .card-body,
        #reportForm .card-footer { padding: var(--space-16) !important; }
        #reportForm select,
        #reportForm input { min-height: 44px; font-size: var(--text-body); }
        .report-actions, .report-export-actions { align-items: stretch; flex-direction: column; width: 100%; }
        .report-actions .btn, .report-export-actions .btn { width: 100%; min-height: 44px; }
        .report-preview-header { align-items: flex-start !important; flex-direction: column; padding: var(--space-16) !important; }
        .report-preview-header > div:first-child .d-flex { align-items: flex-start !important; flex-direction: column; }
        .report-preview-summary { width: 100%; justify-content: space-between; }
        .report-preview-summary .text-end { text-align: left !important; }

        .table-responsive:has(> .report-responsive-table) { overflow: visible; padding: var(--space-12) !important; background: #f5f8f6; }
        .report-responsive-table { display: block; min-width: 0 !important; }
        .report-responsive-table thead { display: none; }
        .report-responsive-table tbody { display: grid; gap: var(--space-12); }
        .report-responsive-table tbody tr { display: block; overflow: hidden; border: 1px solid #e0e8e3; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(24, 58, 37, .05); }
        .report-responsive-table tbody td {
            display: grid;
            grid-template-columns: minmax(90px, 36%) minmax(0, 1fr);
            align-items: center;
            gap: var(--space-12);
            width: 100%;
            padding: var(--space-12) var(--space-12) !important;
            text-align: right !important;
            overflow-wrap: anywhere;
        }
        .report-responsive-table tbody td::before { content: attr(data-label); color: #7b8981; font-size: var(--text-caption); font-weight: 800; text-align: left; text-transform: uppercase; }
        .report-responsive-table tbody td > * { justify-self: end; max-width: 100%; }
        .report-responsive-table tbody td[colspan] { display: block; text-align: center !important; }
        .report-responsive-table tbody td[colspan]::before { display: none; }
        .report-responsive-table .progress { width: min(150px, 100%) !important; }
    }

    @media print {
        body * { visibility: hidden !important; }
        #report-preview, #report-preview * { visibility: visible !important; }
        #report-preview {
            position: absolute;
            inset: 0;
            width: 100%;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        #report-preview .table-responsive { overflow: visible !important; }
        #report-preview .report-table { font-size: 9px !important; }
        #report-preview .report-table :is(th, td, small, .badge) { font-size: 9px !important; line-height: 1.35; }
        .no-print { display: none !important; }
    }
</style>
@endPushOnce

<script>
    const initializeReportBuilder = () => {
        const reportForm = document.getElementById('reportForm');
        const pdfButton = document.getElementById('downloadPdfButton');
        const pdfModalElement = document.getElementById('pdfGenerationModal');
        const cancelPdfButton = document.getElementById('cancelPdfGeneration');
        const retryPdfButton = document.getElementById('retryPdfGeneration');
        const pdfTitle = document.getElementById('pdfGenerationTitle');
        const pdfMessage = document.getElementById('pdfGenerationMessage');
        const pdfSpinner = document.getElementById('pdfGenerationSpinner');
        const pdfErrorIcon = document.getElementById('pdfGenerationErrorIcon');
        const pdfProgress = document.getElementById('pdfGenerationProgress');
        let pdfRequest = null;
        let cancelPending = false;

        if (!reportForm || !pdfButton || !pdfModalElement || !window.bootstrap?.Modal) return;

        document.body.appendChild(pdfModalElement);
        const pdfModal = bootstrap.Modal.getOrCreateInstance(pdfModalElement);

        const setPdfState = (state, message = '') => {
            const failed = state === 'error';
            pdfTitle.textContent = failed ? 'PDF generation failed' : 'Generating PDF report…';
            pdfMessage.textContent = failed
                ? (message || 'The report could not be generated. Check your connection and try again.')
                : 'Preparing the selected records and formatting your document.';
            pdfSpinner.hidden = failed;
            pdfErrorIcon.hidden = !failed;
            pdfProgress.hidden = failed;
            retryPdfButton.hidden = !failed;
            cancelPdfButton.textContent = failed ? 'Close' : 'Cancel';
            window.lucide?.createIcons();
        };

        const closePdfModal = () => {
            pdfModal.hide();
            pdfButton.disabled = false;
        };

        pdfModalElement.addEventListener('shown.bs.modal', () => {
            // Bootstrap ignores hide() calls made while a modal is still in its
            // opening transition. Retry as soon as that transition completes.
            if (cancelPending) closePdfModal();
        });

        pdfModalElement.addEventListener('hidden.bs.modal', () => {
            cancelPending = false;
            pdfButton.disabled = false;
        });

        const filenameFrom = (response) => {
            const disposition = response.headers.get('Content-Disposition') || '';
            const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const plain = disposition.match(/filename="?([^";]+)"?/i)?.[1];
            return decodeURIComponent(encoded || plain || `HTC_SMO_Report_${Date.now()}.pdf`);
        };

        const generatePdf = async () => {
            if (pdfRequest) return;
            if (!window.htcFilters?.validate(reportForm, reportForm.closest('[data-filter-workspace]'))) return;

            cancelPending = false;
            setPdfState('loading');
            pdfButton.disabled = true;
            const controller = new AbortController();
            pdfRequest = controller;
            pdfModal.show();

            const query = new URLSearchParams(new FormData(reportForm));
            query.delete('export');
            query.set('download_pdf', '1');
            const url = new URL(reportForm.action, window.location.href);
            url.search = query.toString();

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {Accept: 'application/pdf', 'X-Requested-With': 'XMLHttpRequest'},
                    signal: controller.signal,
                });
                if (!response.ok) {
                    const error = new Error('The report could not be generated. Check your connection and try again.');
                    if (response.status === 422 && (response.headers.get('Content-Type') || '').includes('application/json')) {
                        const details = await response.json();
                        error.message = details.message || error.message;
                    }
                    throw error;
                }
                if (!(response.headers.get('Content-Type') || '').toLowerCase().includes('application/pdf')) {
                    throw new Error('The server did not return a PDF document.');
                }

                const filename = filenameFrom(response);
                const blob = await response.blob();
                if (window.htcSession?.expired) {
                    closePdfModal();
                    return;
                }
                if (controller.signal.aborted) return;

                const objectUrl = URL.createObjectURL(blob);
                const download = document.createElement('a');
                download.href = objectUrl;
                download.download = filename;
                document.body.appendChild(download);

                closePdfModal();
                download.click();
                download.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            } catch (error) {
                if (error.name === 'SessionExpiredError') {
                    closePdfModal();
                    return;
                }
                if (error.name === 'AbortError') {
                    closePdfModal();
                } else {
                    setPdfState('error', error.message);
                }
            } finally {
                if (pdfRequest === controller) pdfRequest = null;
                pdfButton.disabled = false;
            }
        };

        pdfButton.addEventListener('click', generatePdf);
        retryPdfButton.addEventListener('click', () => {
            pdfRequest = null;
            generatePdf();
        });
        cancelPdfButton.addEventListener('click', () => {
            cancelPending = true;
            pdfRequest?.abort();
            pdfButton.disabled = false;
            closePdfModal();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeReportBuilder, {once: true});
    } else {
        initializeReportBuilder();
    }
</script>
