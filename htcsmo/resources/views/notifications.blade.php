<x-app-layout>
    <x-slot name="header">{{ __('Notification Center') }}</x-slot>

    @php
        $categoryLabels = [
            'all' => 'All activity',
            'approvals' => 'Requisitions',
            'deadlines' => 'Deadlines',
            'accounts' => 'Accounts',
            'inventory' => 'Inventory',
        ];
    @endphp

    <div class="container-fluid notification-center-page" data-filter-workspace="notifications" data-notification-center data-notification-endpoint="{{ route('notifications') }}" data-notification-account="{{ Auth::id() }}">
        <div class="notification-toolbar" data-filter-region="toolbar">
            <nav class="notification-category-nav" aria-label="Notification categories" data-filter-links>
                @foreach(['all', 'approvals', 'deadlines', 'accounts'] as $key)
                    <a href="{{ route('notifications', ['category' => $key]) }}#notification-results"
                       class="notification-category-link {{ $category === $key ? 'is-active' : '' }}"
                       data-notification-category="{{ $key }}" data-category-label="{{ $categoryLabels[$key] }}"
                       aria-label="{{ $categoryLabels[$key] }}" title="{{ $categoryLabels[$key] }}"
                       @if($category === $key) aria-current="page" @endif>
                        <i aria-hidden="true" data-lucide="{{ match($key) { 'all' => 'layout-list', 'approvals' => 'clipboard-check', 'deadlines' => 'alarm-clock', 'accounts' => 'users', default => 'bell' } }}"></i>
                        <span>{{ $categoryLabels[$key] }}</span>
                        <b data-category-count>{{ $categoryCounts[$key] }}</b>
                    </a>
                @endforeach
                @if(Auth::user()->role === 'smo')
                    <a href="{{ route('notifications', ['category' => 'inventory']) }}#notification-results"
                       class="notification-category-link {{ $category === 'inventory' ? 'is-active' : '' }}"
                       data-notification-category="inventory" data-category-label="Inventory"
                       aria-label="Inventory" title="Inventory"
                       @if($category === 'inventory') aria-current="page" @endif>
                        <i data-lucide="package-search" aria-hidden="true"></i>
                        <span>{{ $categoryLabels['inventory'] }}</span>
                        <b data-category-count>{{ $categoryCounts['inventory'] }}</b>
                    </a>
                @endif
            </nav>
            <div class="notification-toolbar-actions">
                <div class="notification-unread-count" title="Unread notifications">
                    <i data-lucide="mail" aria-hidden="true"></i>
                    <span class="visually-hidden">Unread notifications:</span>
                    <strong data-unread-count aria-live="polite" aria-atomic="true">{{ $unreadCount }}</strong>
                </div>
                <form method="POST" action="{{ route('notifications.read-all') }}" data-mark-all-read-form @if($unreadCount < 1) hidden @endif>
                    @csrf
                    <button type="submit" class="notification-icon-control" aria-label="Mark all read" title="Mark all read"><i data-lucide="check-check" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>

        <div class="notification-center-grid">
            <main class="notification-activity-panel" id="notification-results" data-filter-results data-filter-region="results" data-result-count="{{ $notes->total() }}" tabindex="-1" aria-label="Matching notifications">
                <div class="notification-panel-heading">
                    <div><span>Activity feed</span><h2 data-current-category>{{ $categoryLabels[$category] }}</h2></div>
                    <span class="notification-live-label" role="img" aria-label="Live updates" title="Live updates"><i data-lucide="radio" aria-hidden="true"></i></span>
                </div>
                <div class="notification-feed-container" data-notification-feed aria-live="polite">
                    @include('notifications.feed', ['notes' => $notes, 'resolvedActions' => $resolvedActions])
                </div>
            </main>

            <aside class="notification-insights-column">
                @if(Auth::user()->role === 'smo')
                    <section class="notification-insight-card inventory-health {{ $lowStockSupplies->isNotEmpty() ? 'has-alerts' : '' }}">
                        <div class="notification-insight-heading">
                            <div><span>Inventory health</span><h2>{{ $lowStockSupplies->count() }} {{ Str::plural('item', $lowStockSupplies->count()) }} need attention</h2></div>
                            <i data-lucide="package-search"></i>
                        </div>
                        <div class="notification-stock-list">
                            @forelse($lowStockSupplies->take(5) as $supply)
                                <a href="{{ route('inventory.edit', $supply->id) }}" class="notification-stock-row">
                                    <span class="stock-state {{ $supply->quantity <= 0 ? 'is-out' : 'is-low' }}"></span>
                                    <span><strong>{{ $supply->item_name }}</strong><small>{{ $supply->quantity <= 0 ? 'Out of stock' : $supply->quantity.' '.$supply->unit.' remaining' }}</small></span>
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            @empty
                                <div class="notification-insight-empty"><i data-lucide="circle-check"></i><span>Inventory levels are healthy.</span></div>
                            @endforelse
                        </div>
                        <a href="{{ route('inventory.index') }}" class="notification-insight-link" aria-label="Open inventory forecast" title="Open inventory forecast"><i data-lucide="arrow-right" aria-hidden="true"></i></a>
                    </section>

                    <section class="notification-insight-card deadline-watch">
                        <div class="notification-insight-heading">
                            <div><span>Request deadlines</span><h2>Critical deadlines</h2></div>
                            <b>{{ $criticalRequestCount }}</b>
                        </div>
                        <div class="notification-deadline-list">
                            @forelse($criticalRequests as $req)
                                @php($isOverdue = $req->isDeadlineOverdue())
                                <a class="notification-deadline-item {{ $isOverdue ? 'is-overdue' : 'is-warning' }}" href="{{ route('requests.show', $req) }}" aria-label="View request {{ $req->tracking_code }}">
                                    <div><span>{{ $isOverdue ? 'Overdue' : 'Due soon' }}</span><small>{{ $req->tracking_code }}</small></div>
                                    <h3>{{ $req->items->first()->item_name ?? 'Multiple requested items' }}</h3>
                                    <p>{{ $isOverdue ? 'This requisition has reached its processing deadline.' : 'This requisition is approaching its processing deadline.' }}</p>
                                    @if($req->due_at)
                                        <p>Due <time datetime="{{ $req->due_at->toIso8601String() }}">{{ $req->due_at->format('M j, Y g:i A') }}</time></p>
                                    @endif
                                </a>
                            @empty
                                <div class="notification-insight-empty"><i data-lucide="shield-check"></i><span>No critical request deadlines.</span></div>
                            @endforelse
                        </div>
                        @if($criticalRequestCount > 0)
                            <a href="{{ route('requests.index', ['status' => 'deadline']) }}" class="notification-insight-link">View all {{ $criticalRequestCount }} deadline risks <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </section>
                @else
                    <section class="notification-insight-card personal-summary">
                        <div class="notification-insight-heading"><div><span>Your requisitions</span><h2>Request summary</h2></div><i data-lucide="chart-no-axes-column"></i></div>
                        <div class="notification-stat-grid">
                            <div><strong>{{ $personalStats['total'] }}</strong><span>Total</span></div>
                            <div><strong>{{ $personalStats['pending'] }}</strong><span>Pending</span></div>
                            <div><strong>{{ $personalStats['released'] }}</strong><span>Completed</span></div>
                        </div>
                    </section>
                    <section class="notification-guide-card">
                        <span class="notification-guide-icon"><i data-lucide="route"></i></span>
                        <span class="notification-eyebrow">Procurement guide</span>
                        <h2>Know what happens next</h2>
                        <ul>
                            <li><i data-lucide="check-circle"></i><span><strong>Minor:</strong> Department Head and VP Finance approval.</span></li>
                            <li><i data-lucide="check-circle"></i><span><strong>Major:</strong> VP Admin, Provost, and President approval.</span></li>
                            <li><i data-lucide="package-check"></i><span><strong>Fulfillment:</strong> Visit SMO only after the request is marked ready for pickup.</span></li>
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>

    @pushOnce('styles', 'page-styles:notifications.blade.php')
    <style>
        .notification-center-page { --note-ink:#173226; --note-muted:#708178; --note-line:#dfe8e2; padding:var(--space-12) 0 var(--space-24); }
        .notification-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-8); margin-bottom:var(--space-16); padding:var(--space-8); border:1px solid var(--note-line); border-radius:14px; background:#fff; box-shadow:0 5px 16px rgba(24,70,43,.04); }
        .notification-toolbar-actions { display:flex; align-items:center; gap:var(--space-8); margin-left:auto; }
        .notification-toolbar-actions form { margin:0; }
        .notification-unread-count { min-height:44px; display:inline-flex; align-items:center; gap:var(--space-8); padding:var(--space-8) var(--space-12); border-radius:10px; background:#edf7f1; color:#155936; }
        .notification-unread-count svg { width:18px; height:18px; }
        .notification-unread-count strong { font-size:var(--text-secondary); font-variant-numeric:tabular-nums; }
        .notification-icon-control { width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--note-line); border-radius:10px; background:#fff; color:#155936; text-decoration:none; cursor:pointer; }
        .notification-icon-control:hover { background:#edf7f1; border-color:#b8d6c3; }
        .notification-icon-control:disabled { opacity:.5; cursor:wait; }
        .notification-icon-control svg { width:18px; height:18px; }
        .notification-icon-control:focus-visible,.notification-category-link:focus-visible,.notification-insight-link:focus-visible { outline:2px solid var(--htc-green); outline-offset:2px; }
        .notification-eyebrow { color:#a9e8bf; font-size:var(--text-caption); font-weight:800; letter-spacing:var(--tracking-label); text-transform:uppercase; }
        .notification-category-nav { display:flex; flex-wrap:wrap; gap:var(--space-4); }
        .notification-category-link { min-height:44px; display:inline-flex; align-items:center; gap:var(--space-8); padding:var(--space-8) var(--space-12); border-radius:10px; color:#677970; font-size:var(--text-caption); font-weight:800; text-decoration:none; white-space:nowrap; transition:.18s ease; }
        .notification-category-link:hover { background:#f0f6f2; color:var(--htc-green); }
        .notification-category-link.is-active { background:var(--htc-green); color:#fff; box-shadow:0 5px 13px rgba(24,91,59,.2); }
        .notification-category-link svg { width:18px; height:18px; }
        .notification-category-link b { min-width:21px; padding:.15rem var(--space-4); border-radius:999px; background:#eef3f0; color:#5e7166; font-size:var(--text-caption); text-align:center; }
        .notification-category-link.is-active b { background:rgba(255,255,255,.18); color:#fff; }
        .notification-center-grid { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(280px,.72fr); gap:var(--space-16); align-items:start; }
        .notification-activity-panel,.notification-insight-card { overflow:hidden; border:1px solid var(--note-line); border-radius:16px; background:#fff; box-shadow:0 7px 20px rgba(24,70,43,.05); }
        .notification-panel-heading,.notification-insight-heading { display:flex; align-items:center; justify-content:space-between; gap:var(--space-16); }
        .notification-panel-heading { padding:var(--space-16) var(--space-16); border-bottom:1px solid #e7eee9; background:linear-gradient(135deg,#fbfdfc,#f4f8f5); }
        .notification-panel-heading > div > span,.notification-insight-heading > div > span { color:#87978e; font-size:var(--text-caption); font-weight:800; letter-spacing:var(--tracking-label); text-transform:uppercase; }
        .notification-panel-heading h2,.notification-insight-heading h2 { margin:.12rem 0 0; color:var(--note-ink); font-size:var(--text-heading-sm); font-weight:800; }
        .notification-live-label { display:inline-flex; align-items:center; gap:var(--space-4); color:#3c7655; font-size:var(--text-secondary); font-weight:800; text-transform:uppercase; }
        .notification-live-label svg { width:13px; height:13px; }
        .notification-feed-container { position:relative; min-height:260px; transition:opacity .15s ease; }
        .notification-center-page.is-loading .notification-feed-container { opacity:.38; pointer-events:none; }
        .notification-center-page.is-loading .notification-activity-panel { cursor:progress; }
        .notification-feed-list { display:grid; gap:var(--space-12); padding:var(--space-12); background:#f6f9f7; }
        .notification-feed-item { position:relative; display:grid; grid-template-columns:42px minmax(0,1fr); gap:var(--space-12); padding:var(--space-16); border:1px solid #e1e9e4; border-radius:13px; background:#fff; transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease; }
        .notification-feed-item:hover { border-color:#bfd2c5; transform:translateY(-1px); box-shadow:0 7px 16px rgba(24,70,43,.06); }
        .notification-feed-item > form { position:absolute; inset:0; margin:0; border-radius:inherit; }
        .notification-card-action { position:absolute; inset:0; z-index:1; width:100%; height:100%; padding:0; border:0; border-radius:inherit; background:transparent; cursor:pointer; }
        .notification-card-action:focus-visible { outline:2px solid var(--htc-green); outline-offset:2px; }
        .notification-card-action:disabled { cursor:wait; }
        .notification-feed-item.is-unread { border-left:4px solid var(--htc-green); background:linear-gradient(100deg,#f1faf4,#fff 28%); }
        .notification-feed-item.type-warning.is-unread { border-left-color:#dfa316; }
        .notification-feed-item.type-danger.is-unread { border-left-color:#cf424b; }
        .notification-feed-icon { width:42px; height:42px; display:grid; place-items:center; border-radius:12px; background:#e6f3ea; color:#28704a; }
        .notification-feed-icon svg { width:19px; height:19px; }
        .type-warning .notification-feed-icon { background:#fff1d2; color:#96630d; }
        .type-danger .notification-feed-icon { background:#fde8ea; color:#b52d36; }
        .type-success .notification-feed-icon { background:#e1f3e7; color:#17643d; }
        .notification-feed-content { min-width:0; }
        .notification-feed-meta,.notification-feed-title-row { display:flex; align-items:center; }
        .notification-feed-meta { justify-content:space-between; gap:var(--space-12); margin-bottom:var(--space-4); }
        .notification-feed-meta span { color:#4f755f; font-size:var(--text-caption); font-weight:800; letter-spacing:var(--tracking-label); text-transform:uppercase; }
        .notification-feed-meta time { color:#96a39c; font-size:var(--text-caption); }
        .notification-feed-title-row { gap:var(--space-8); }
        .notification-feed-title-row h3 { margin:0; color:var(--note-ink); font-size:var(--text-body); font-weight:800; overflow-wrap:anywhere; }
        .notification-unread-indicator { width:7px; height:7px; flex:0 0 7px; border-radius:50%; background:#e14952; box-shadow:0 0 0 3px rgba(225,73,82,.1); }
        .notification-feed-content > p { margin:var(--space-4) 0 0; color:var(--note-muted); font-size:var(--text-secondary); line-height:var(--line-body); }
        .notification-feed-content > .notification-action-unavailable { color:#8d9a93; font-size:var(--text-caption); }
        .notification-empty-state { min-height:300px; display:flex; align-items:center; justify-content:center; flex-direction:column; padding:var(--space-32); text-align:center; }
        .notification-empty-state > span { width:54px; height:54px; display:grid; place-items:center; margin-bottom:var(--space-12); border-radius:16px; background:#eaf3ed; color:#6d8978; }
        .notification-empty-state svg { width:25px; height:25px; }
        .notification-empty-state h3 { margin:0; color:var(--note-ink); font-size:var(--text-heading-sm); }
        .notification-empty-state p { max-width:320px; margin:var(--space-4) 0 0; color:var(--note-muted); font-size:var(--text-secondary); }
        .notification-feed-pagination { padding:var(--space-12) var(--space-16); border-top:1px solid var(--note-line); background:#fff; }
        .notification-insights-column { display:grid; gap:var(--space-16); }
        .notification-insight-card { padding:var(--space-16); }
        .notification-insight-heading { margin-bottom:var(--space-12); }
        .notification-insight-heading > svg { width:34px; height:34px; padding:var(--space-8); border-radius:10px; background:#e8f3ec; color:#276d48; }
        .notification-insight-heading > b { min-width:28px; height:28px; display:grid; place-items:center; border-radius:9px; background:#fde8ea; color:#b52d36; font-size:var(--text-caption); }
        .notification-stock-row { display:grid; grid-template-columns:8px minmax(0,1fr) 14px; align-items:center; gap:var(--space-12); padding:var(--space-12) 0; border-bottom:1px solid #edf1ef; color:inherit; text-decoration:none; }
        .notification-stock-row > span:nth-child(2) { min-width:0; }
        .notification-stock-row strong,.notification-stock-row small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .notification-stock-row strong { color:#2f4438; font-size:var(--text-secondary); }
        .notification-stock-row small { color:#89978f; font-size:var(--text-caption); }
        .notification-stock-row > svg { width:14px; color:#9ba79f; }
        .stock-state { width:8px; height:8px; border-radius:50%; background:#e1a124; box-shadow:0 0 0 4px rgba(225,161,36,.11); }
        .stock-state.is-out { background:#d4424b; box-shadow:0 0 0 4px rgba(212,66,75,.11); }
        .notification-insight-link { min-height:38px; display:flex; align-items:center; justify-content:center; gap:var(--space-8); margin-top:var(--space-12); border-radius:9px; background:#edf7f1; color:#1f6b43; font-size:var(--text-caption); font-weight:800; text-decoration:none; }
        .notification-insight-link svg { width:14px; }
        .notification-deadline-list { display:grid; gap:var(--space-8); }
        .notification-deadline-item { display:block; padding:var(--space-12); border:1px solid #efd3d5; border-radius:11px; background:#fff8f8; color:inherit; text-decoration:none; }
        .notification-deadline-item:hover { box-shadow:0 3px 10px rgba(24,70,43,.12); }
        .notification-deadline-item:hover h3 { text-decoration:underline; }
        .notification-deadline-item:focus-visible { outline:2px solid var(--htc-green); outline-offset:-3px; }
        .notification-deadline-item.is-warning { border-color:#ecd9ae; background:#fffbf1; }
        .notification-deadline-item.is-warning > div span { color:#8a5b0a; }
        .notification-deadline-item > div { display:flex; justify-content:space-between; gap:var(--space-8); }
        .notification-deadline-item > div span { color:#b63139; font-size:var(--text-caption); font-weight:900; text-transform:uppercase; }
        .notification-deadline-item small { min-width:0; overflow-wrap:anywhere; color:#9a8587; font-size:var(--text-caption); text-align:right; }
        .notification-deadline-item h3 { margin:var(--space-4) 0 var(--space-4); color:#432e30; font-size:var(--text-body); font-weight:800; }
        .notification-deadline-item p { margin:0 0 var(--space-8); color:#806c6e; font-size:var(--text-secondary); }
        .notification-deadline-item p:last-child { margin-bottom:0; }
        .notification-insight-empty { min-height:90px; display:flex; align-items:center; justify-content:center; gap:var(--space-8); color:#7b8b82; font-size:var(--text-caption); text-align:center; }
        .notification-insight-empty svg { width:18px; color:#45815e; }
        .notification-stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-8); }
        .notification-stat-grid div { padding:var(--space-12) var(--space-8); border-radius:10px; background:#f3f7f4; text-align:center; }
        .notification-stat-grid strong,.notification-stat-grid span { display:block; }
        .notification-stat-grid strong { color:var(--note-ink); font-size:var(--text-body); }
        .notification-stat-grid span { color:#829087; font-size:var(--text-caption); font-weight:800; text-transform:uppercase; }
        .notification-guide-card { padding:var(--space-16); border-radius:16px; background:linear-gradient(145deg,#103f29,#1b7147); color:#fff; box-shadow:0 10px 24px rgba(14,67,39,.14); }
        .notification-guide-icon { width:38px; height:38px; display:grid; place-items:center; margin-bottom:var(--space-12); border-radius:11px; background:rgba(255,255,255,.12); }
        .notification-guide-icon svg { width:18px; }
        .notification-guide-card h2 { margin:.15rem 0 var(--space-12); color:#fff; font-size:var(--text-heading-sm); }
        .notification-guide-card ul { display:grid; gap:var(--space-12); margin:0; padding:0; list-style:none; }
        .notification-guide-card li { display:grid; grid-template-columns:15px minmax(0,1fr); gap:var(--space-8); color:#d4ebdc; font-size:var(--text-secondary); line-height:var(--line-body); }
        .notification-guide-card li svg { width:14px; height:14px; color:#8fe0ab; }
        @media(max-width:991.98px){.notification-center-grid{grid-template-columns:1fr}.notification-insights-column{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:575.98px){.notification-center-page{padding-top:var(--space-4)}.notification-toolbar{gap:var(--space-4)}.notification-category-nav{gap:var(--space-4)}.notification-category-link{padding:var(--space-8)}.notification-center-grid{gap:var(--space-12)}.notification-insights-column{grid-template-columns:1fr}.notification-feed-item{grid-template-columns:36px minmax(0,1fr);padding:var(--space-12)}.notification-feed-icon{width:36px;height:36px}.notification-feed-meta{align-items:flex-start;flex-direction:column;gap:.1rem}.notification-panel-heading{padding:var(--space-12)}.notification-live-label{display:none}}
        @media(prefers-reduced-motion:reduce){.notification-feed-item,.notification-feed-container{transition:none}}
    </style>
    @endPushOnce

    <script>
        (() => {
            const center = document.querySelector('[data-notification-center]');
            if (!center) return;



            const unreadSyncKey = `htc-notification-unread-sync-${center.dataset.notificationAccount}`;


            const refreshIcons = () => window.lucide?.createIcons();
            const updateUnreadCount = (count, broadcast = false) => {
                const normalized = Math.max(0, Number(count) || 0);
                center.querySelectorAll('[data-unread-count]').forEach(element => element.textContent = normalized);
                const sidebarBadge = document.getElementById('notificationUnreadCount');
                if (sidebarBadge) {
                    sidebarBadge.hidden = normalized < 1;
                    sidebarBadge.textContent = normalized > 99 ? '99+' : String(normalized);
                }
                const markAllForm = center.querySelector('[data-mark-all-read-form]');
                if (markAllForm) markAllForm.hidden = normalized < 1;
                if (broadcast) {
                    try {
                        localStorage.setItem(unreadSyncKey, JSON.stringify({count: normalized, at: Date.now()}));
                    } catch (error) {
                        // Cross-tab synchronization is optional.
                    }
                }
            };

            const loadFeed = (url, pushState = true) => window.htcFilters?.refresh(center, url, {
                history: pushState ? 'push' : 'none', orient: pushState, background: !pushState,
            });
            center.addEventListener('submit', async event => {
                const readForm = event.target.closest('[data-notification-read-form]');
                const readAllForm = event.target.closest('[data-mark-all-read-form]');
                if (!readForm && !readAllForm) return;
                event.preventDefault();

                const form = readForm || readAllForm;
                if (form.dataset.submitting === 'true') return;
                form.dataset.submitting = 'true';
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) submitButton.disabled = true;
                const token = form.querySelector('input[name="_token"]')?.value;
                try {
                    const response = await fetch(form.action, {
                        method: readForm ? 'PATCH' : 'POST',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token},
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error(`Read action failed with HTTP ${response.status}`);
                    const data = await response.json();

                    if (readAllForm) {
                        center.querySelectorAll('.notification-feed-item.is-unread').forEach(item => {
                            item.classList.remove('is-unread');
                            item.classList.add('is-read');
                            item.querySelector('.notification-unread-indicator')?.remove();
                            item.querySelector('[data-notification-read-form]')?.remove();
                        });
                        updateUnreadCount(data.unread_count, true);
                    } else {
                        const item = readForm.closest('.notification-feed-item');
                        item?.classList.remove('is-unread');
                        item?.classList.add('is-read');
                        item?.querySelector('.notification-unread-indicator')?.remove();
                        readForm.remove();
                        updateUnreadCount(data.unread_count, true);
                    }
                    refreshIcons();
                } catch (error) {
                    if (error.name === 'SessionExpiredError') return;
                    // A failed response may follow a completed write. Never replay it.
                    let status = center.querySelector('[data-notification-action-error]');
                    if (!status) {
                        status = document.createElement('p');
                        status.dataset.notificationActionError = '';
                        status.setAttribute('role', 'alert');
                        center.prepend(status);
                    }
                    status.textContent = 'We could not confirm this action. Refresh the notification list to check its status before trying again.';
                } finally {
                    delete form.dataset.submitting;
                    if (submitButton?.isConnected) submitButton.disabled = false;
                }
            });

            window.addEventListener('storage', event => {
                if (event.key !== unreadSyncKey || !event.newValue) return;
                try {
                    updateUnreadCount(JSON.parse(event.newValue).count);
                    loadFeed(window.location.href, false);
                } catch (error) {
                    // Ignore malformed or unavailable cross-tab state.
                }
            });

            let lastRuntimeSignature = null;
            document.addEventListener('htc:runtime-state', event => {
                const data = event.detail;
                if (!data || Number(data.account_id) !== Number(center.dataset.notificationAccount)) return;
                updateUnreadCount(data.unread_count);
                const signature = `${data.latest_id}:${data.unread_count}`;
                if (signature !== lastRuntimeSignature && Array.isArray(data.notifications) && data.notifications.length) {
                    loadFeed(window.location.href, false);
                }
                lastRuntimeSignature = signature;
            });
        })();
    </script>
</x-app-layout>
