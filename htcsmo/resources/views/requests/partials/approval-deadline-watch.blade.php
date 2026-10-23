@php
    $isSmoDeadlineView = Auth::user()->role === 'smo';
    $reviewerLabels = [
        'dept_head' => 'Department Head / Dean',
        'vp_finance' => 'VP for Finance',
        'vp_admin' => 'VP for Administration',
        'provost' => 'Provost',
        'president' => 'President',
        'smo' => 'SMO In-Charge',
    ];
@endphp

<section class="approval-deadline-watch" aria-labelledby="approvalDeadlineHeading">
    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-3" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-3" role="alert">{{ session('error') }}</div>
    @endif

    <div class="approval-deadline-heading">
        <div>
            <span class="approval-deadline-eyebrow">Approval deadline watch</span>
            <h3 id="approvalDeadlineHeading">
                {{ $isSmoDeadlineView ? 'Approvals needing follow-up' : 'Requests requiring your attention' }}
                <em>{{ $approvalDeadlineCount ?? $approvalDeadlineRequests->count() }}</em>
            </h3>
        </div>
        <div class="approval-deadline-heading__actions">
            <a href="{{ $isSmoDeadlineView ? route('requests.index', ['status' => 'deadline']) : route('admin.approvals') }}">
                See all deadline requests <i data-lucide="arrow-up-right"></i>
            </a>
            <span class="approval-deadline-heading__icon"><i data-lucide="alarm-clock"></i></span>
        </div>
    </div>

    <div class="approval-deadline-list">
        @forelse($approvalDeadlineRequests as $deadlineRequest)
            @php
                $daysElapsed = $deadlineRequest->approvalDeadlineDaysElapsed();
                $isOverdue = $deadlineRequest->isApprovalDeadlineOverdue();
                $reviewerRole = $deadlineRequest->currentReviewerRole();
            @endphp
            <article class="approval-deadline-item {{ $isOverdue ? 'overdue' : 'near' }}" x-data="{ expanded: false }" :data-dirty="expanded ? 'true' : null">
                <span class="approval-deadline-state">
                    <i data-lucide="{{ $isOverdue ? 'siren' : 'clock-alert' }}"></i>
                    <span class="approval-deadline-state-label">{{ $isOverdue ? 'At deadline' : 'Nearing deadline' }}</span>
                </span>
                <div class="approval-deadline-request">
                    <strong>{{ $deadlineRequest->tracking_code }}</strong>
                    <span>{{ $deadlineRequest->user?->name ?? 'Unavailable requestor' }} · {{ $deadlineRequest->user?->department ?: 'General' }}</span>
                </div>
                <div class="approval-deadline-owner">
                    <small>Current owner</small>
                    <strong>{{ $reviewerLabels[$reviewerRole] ?? 'Assigned administrator' }}</strong>
                </div>
                <div class="approval-deadline-age">
                    <strong>Day {{ $daysElapsed }}</strong>
                    <span>working days since submission</span>
                </div>
                <div class="approval-deadline-action">
                    <button type="button" class="deadline-detail-toggle" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="deadline-details-{{ $deadlineRequest->id }}">
                        <i data-lucide="panel-bottom-open"></i>
                        <span x-text="expanded ? 'Hide details' : 'Details'">Details</span>
                    </button>
                    @if($isSmoDeadlineView)
                        <form method="POST" action="{{ route('admin.requests.deadline-reminder', $deadlineRequest) }}" data-confirm="Send an in-app deadline reminder to active recipients: the current owner, requestor, and SMO for {{ $deadlineRequest->tracking_code }}? Each recipient is notified once per stage and deadline status per day." data-confirm-title="Send a deadline reminder?" data-confirm-accept="Send reminder">
                            @csrf
                            <button type="submit" data-loading-text="Notifying…">
                                <i data-lucide="send"></i> Notify
                            </button>
                        </form>
                    @else
                        <a href="{{ route('admin.approvals', ['request' => $deadlineRequest->id]) }}">
                            Review now <i data-lucide="arrow-right"></i>
                        </a>
                    @endif
                </div>
                <div id="deadline-details-{{ $deadlineRequest->id }}" class="approval-deadline-details" x-show="expanded" x-cloak>
                    <div class="deadline-detail-block purpose">
                        <small>Purpose</small>
                        <strong>{{ $deadlineRequest->purpose }}</strong>
                    </div>
                    <div class="deadline-detail-block">
                        <small>Requested items</small>
                        <strong>{{ $deadlineRequest->items->sum('quantity') }} total</strong>
                        <span>
                            {{ $deadlineRequest->items->pluck('item_name')->take(3)->implode(', ') ?: 'No item details' }}
                            @if($deadlineRequest->items->count() > 3)
                                +{{ $deadlineRequest->items->count() - 3 }} more
                            @endif
                        </span>
                    </div>
                    <div class="deadline-detail-block">
                        <small>Submitted</small>
                        <strong>{{ ($deadlineRequest->submitted_at ?? $deadlineRequest->created_at)->format('M d, Y · g:i A') }}</strong>
                        <span>Request deadline: {{ $deadlineRequest->due_at?->format('M d, Y · g:i A') ?? 'Not set' }}</span>
                    </div>
                    <div class="deadline-detail-block">
                        <small>Request value</small>
                        <strong>₱{{ number_format((float) $deadlineRequest->grand_total, 2) }}</strong>
                        <span>{{ ucfirst($deadlineRequest->request_type) }} requisition</span>
                    </div>
                    <a class="deadline-full-details" href="{{ $isSmoDeadlineView ? route('requests.show', $deadlineRequest) : route('admin.approvals', ['request' => $deadlineRequest->id]) }}">
                        View full request <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </article>
        @empty
            <div class="approval-deadline-empty">
                <span><i data-lucide="circle-check-big"></i></span>
                <div>
                    <strong>No approval deadlines need attention</strong>
                    <p>{{ $isSmoDeadlineView ? 'No administrator currently needs an SMO reminder.' : 'Your assigned approval queue is within its target.' }}</p>
                </div>
            </div>
        @endforelse
    </div>
</section>

@pushOnce('styles', 'page-styles:requests/partials/approval-deadline-watch.blade.php')
<style>
    .approval-deadline-watch { container: approval-deadline / inline-size; margin-bottom: var(--space-16); overflow: hidden; border: 1px solid #e7d8b4; border-radius: 18px; background: #fff; box-shadow: 0 8px 26px rgba(70,52,20,.06); }
    .approval-deadline-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-16); padding: var(--space-16) var(--space-24); border-bottom: 1px solid #eee4cc; background: linear-gradient(135deg,#fffdf7,#fff8e8); }
    .approval-deadline-eyebrow { display: block; margin-bottom: var(--space-4); color: #9a6811; font-size: var(--text-caption); font-weight: 900; letter-spacing: var(--tracking-label); text-transform: uppercase; }
    .approval-deadline-heading h3 { margin: 0; color: #493b24; font-size: var(--text-heading-sm); font-weight: 850; }
    .approval-deadline-heading h3 em { display: inline-grid; min-width: 24px; height: 24px; margin-left: var(--space-4); place-items: center; border-radius: 999px; color: #8a5c0b; background: #f7e4b8; font-size: var(--text-caption); font-style: normal; }
    .approval-deadline-heading p { margin: var(--space-4) 0 0; color: #87775d; font-size: var(--text-secondary); }
    .approval-deadline-heading__actions { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-8); min-width: 0; }
    .approval-deadline-heading .approval-deadline-heading__actions > a { max-width: 100%; white-space: normal; }
    .approval-deadline-heading__actions > a { min-height: 36px; display: inline-flex; align-items: center; gap: var(--space-4); padding: var(--space-8) var(--space-12); border: 1px solid #e3c98e; border-radius: 9px; color: #805207; background: rgba(255,255,255,.7); font-size: var(--text-caption); font-weight: 850; text-decoration: none; white-space: nowrap; }
    .approval-deadline-heading__actions > a:hover { border-color: #9a6811; color: #fff; background: #9a6811; }
    .approval-deadline-heading__actions > a svg { width: 13px; height: 13px; }
    .approval-deadline-heading__icon { width: 40px; height: 40px; flex: 0 0 40px; display: grid; place-items: center; border-radius: 12px; color: #9a6811; background: #f8e7bd; }
    .approval-deadline-heading__icon svg { width: 19px; }
    .approval-deadline-list { padding: var(--space-4) var(--space-16); }
    .approval-deadline-item { display: grid; grid-template-columns: max-content minmax(0,1.3fr) minmax(0,1fr) minmax(80px,.6fr) max-content; grid-template-areas: "state request owner age action"; align-items: center; gap: var(--space-16); padding: var(--space-12) var(--space-4); border-bottom: 1px solid #edf0ed; }
    .approval-deadline-item > * { min-width: 0; }
    .approval-deadline-state { grid-area: state; }
    .approval-deadline-request { grid-area: request; overflow-wrap: anywhere; }
    .approval-deadline-owner { grid-area: owner; overflow-wrap: anywhere; }
    .approval-deadline-age { grid-area: age; }
    .approval-deadline-action { grid-area: action; }
    .approval-deadline-item:last-child { border-bottom: 0; }
    .approval-deadline-state { display: inline-flex; width: fit-content; align-items: center; gap: var(--space-8); padding: var(--space-4) var(--space-12); border-radius: 999px; font-size: var(--text-caption); font-weight: 900; line-height: var(--line-heading); text-transform: uppercase; }
    .approval-deadline-state svg { width: 12px; height: 12px; flex: 0 0 12px; }
    .approval-deadline-state-label { white-space: nowrap; }
    .approval-deadline-item.near .approval-deadline-state { color: #8b5a08; background: #fff0c9; }
    .approval-deadline-item.overdue .approval-deadline-state { color: #b32c36; background: #fde3e5; }
    .approval-deadline-request strong, .approval-deadline-request span, .approval-deadline-owner small, .approval-deadline-owner strong, .approval-deadline-age strong, .approval-deadline-age span { display: block; }
    .approval-deadline-request strong { color: #2f4938; font-family: ui-monospace,SFMono-Regular,Menlo,monospace; font-size: var(--text-caption); }
    .approval-deadline-request span, .approval-deadline-owner small, .approval-deadline-age span { margin-top: .12rem; color: #8a958e; font-size: var(--text-caption); }
    .approval-deadline-owner strong, .approval-deadline-age strong { color: #526359; font-size: var(--text-caption); }
    .approval-deadline-action { display: flex; justify-content: flex-end; gap: var(--space-4); text-align: right; }
    .approval-deadline-action form { margin: 0; }
    .approval-deadline-action :is(button,a) { min-height: 34px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-4); padding: var(--space-8) var(--space-12); border: 1px solid #d6a84e; border-radius: 9px; color: #805207; background: #fff9ea; font-size: var(--text-caption); font-weight: 850; text-decoration: none; white-space: nowrap; }
    .approval-deadline-action :is(button,a):hover { color: #fff; background: #9a6811; }
    .approval-deadline-action .deadline-detail-toggle { border-color: #dce5df; color: #526359; background: #f8faf9; }
    .approval-deadline-action .deadline-detail-toggle:hover { border-color: #526359; color: #fff; background: #526359; }
    .approval-deadline-action svg { width: 13px; height: 13px; }
    .approval-deadline-details { grid-column: 1/-1; display: grid; grid-template-columns: minmax(180px,1.4fr) repeat(3,minmax(130px,.8fr)) auto; gap: var(--space-12); align-items: stretch; margin: .15rem 0 var(--space-4); padding: var(--space-12); border: 1px solid #eadfc5; border-radius: 12px; background: linear-gradient(135deg,#fffdf8,#fbfaf7); }
    .deadline-detail-block { min-width: 0; padding-right: var(--space-12); border-right: 1px solid #ebe5d8; }
    .deadline-detail-block small,.deadline-detail-block strong,.deadline-detail-block span { display: block; }
    .deadline-detail-block small { margin-bottom: var(--space-4); color: #998b70; font-size: var(--text-caption); font-weight: 850; letter-spacing: var(--tracking-label); text-transform: uppercase; }
    .deadline-detail-block strong { overflow-wrap: anywhere; color: #3f5046; font-size: var(--text-caption); line-height: var(--line-body); }
    .deadline-detail-block span { margin-top: .12rem; color: #849087; font-size: var(--text-caption); line-height: var(--line-body); }
    .deadline-full-details { min-height: 36px; align-self: center; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-4); padding: var(--space-8) var(--space-12); border-radius: 9px; color: #fff; background: #28714a; font-size: var(--text-caption); font-weight: 850; text-decoration: none; white-space: nowrap; }
    .deadline-full-details:hover { color: #fff; background: #185b3b; }
    .deadline-full-details svg { width: 13px; height: 13px; }
    .approval-deadline-empty { min-height: 92px; display: flex; align-items: center; justify-content: center; gap: var(--space-12); padding: var(--space-16); text-align: left; }
    .approval-deadline-empty > span { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; color: #277048; background: #e6f3ea; }
    .approval-deadline-empty svg { width: 18px; }
    .approval-deadline-empty strong { color: #46594d; font-size: var(--text-caption); }
    .approval-deadline-empty p { margin: .15rem 0 0; color: #8b978f; font-size: var(--text-secondary); }
    @container approval-deadline (max-width: 68rem) { .approval-deadline-details { grid-template-columns: repeat(2,minmax(0,1fr)); }.deadline-full-details { align-self: stretch; }.deadline-detail-block:nth-child(2) { border-right: 0; } }
    @container approval-deadline (max-width: 55rem) { .approval-deadline-item { grid-template-columns: max-content minmax(0,1fr) minmax(0,1fr); grid-template-areas: "state request owner" ". age action"; align-items: start; }.approval-deadline-action { justify-content: flex-start; } }
    @container approval-deadline (max-width: 38rem) { .approval-deadline-list { display: grid; gap: var(--space-12); padding: var(--space-12); }.approval-deadline-item { grid-template-columns: repeat(2,minmax(0,1fr)); grid-template-areas: "state state" "request owner" "age age" "action action"; gap: var(--space-12); padding: var(--space-12); border: 1px solid #e8e2d3!important; border-radius: 13px; }.approval-deadline-action { flex-wrap: wrap; text-align: left; }.approval-deadline-details { grid-template-columns: 1fr; }.deadline-detail-block { padding: 0 0 var(--space-8); border-right: 0; border-bottom: 1px solid #ebe5d8; }.approval-deadline-heading { flex-direction: column; padding: var(--space-16); }.approval-deadline-heading__actions { align-items: flex-start; } }
    @container approval-deadline (max-width: 24rem) { .approval-deadline-item { grid-template-columns: minmax(0,1fr); grid-template-areas: "state" "request" "owner" "age" "action"; }.approval-deadline-heading { align-items: stretch; }.approval-deadline-heading__actions > a { justify-content: center; }.approval-deadline-heading__icon { display: none; } }
</style>
@endPushOnce
