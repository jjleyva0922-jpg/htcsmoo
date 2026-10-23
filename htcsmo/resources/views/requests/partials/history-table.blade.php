@php
    $isPersonalEmployee = Auth::user()->role === 'employee';
    $listContext = request()->routeIs('requests.index')
        ? ['from_list' => 1, 'list_filters' => request()->only('search', 'status', 'dept', 'date_from', 'date_to', 'order', 'page')]
        : [];
@endphp
@if($type === 'archive')
    @include('requests.partials.archive-table')
@else
<div class="card request-history-card">
    <div class="table-responsive">
        <table class="table workflow-responsive-table request-history-table">
            <thead>
                <tr>
                    <th>Request</th>
                    @if(Auth::user()->role === 'smo')<th>Requestor</th>@endif
                    <th>Requested item</th>
                    <th>Tier</th>
                    <th class="text-end">{{ $isPersonalEmployee ? 'Requested value' : 'Value' }}</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requisitions as $req)
                    @php
                        $isOverdue = method_exists($req, 'isOverdue') && $req->isOverdue();
                        $isNear = method_exists($req, 'isNearingDeadline') && $req->isNearingDeadline();
                        if (! $isPersonalEmployee) {
                            $statusTone = match($req->status) {
                                'completed', 'released' => 'released',
                                'rejected' => 'rejected',
                                'for_clarification' => 'clarification',
                                'approved_vp', 'approved_president', 'ready_for_pickup' => 'ready',
                                default => 'progress',
                            };
                            $stageLabel = match($req->status) {
                                'pending' => 'Department review',
                                'approved_dept' => $req->request_type === 'minor' ? 'VP Finance review' : 'VP Administration review',
                                'approved_vp' => $req->request_type === 'minor' ? 'Awaiting SMO processing' : 'Provost review',
                                'approved_provost' => 'Presidential review',
                                'approved_president' => 'Awaiting SMO processing',
                                'processing' => 'SMO processing',
                                'ready_for_pickup' => 'Ready for requestor pickup',
                                'completed' => 'Pickup completed',
                                'for_clarification' => 'Requestor clarification required',
                                'released' => 'Released (legacy)',
                                'rejected' => 'Request disapproved',
                                default => str_replace('_', ' ', $req->status),
                            };
                        } else {
                            $personalStage = $req->requestorStatus();
                            $stageLabel = $personalStage['next_step'];
                            $statusTone = match($personalStage['tone']) {
                                'ready' => 'ready', 'clarification' => 'clarification',
                                'success' => 'released', 'danger' => 'rejected', default => 'progress',
                            };
                        }
                    @endphp
                    <tr class="{{ $isOverdue ? 'request-overdue' : ($isNear ? 'request-near' : '') }}">
                        <td data-label="Request">
                            <div class="history-request-code">{{ $req->tracking_code }}</div>
                            <small><i data-lucide="calendar"></i>{{ $type === 'archive' ? 'Closed '.($req->closed_at?->format('M d, Y') ?? 'date unavailable') : ($isPersonalEmployee ? ($req->submitted_at ?? $req->created_at)?->format('M d, Y') : $req->created_at?->format('M d, Y')) }}</small>
                        </td>

                        @if(Auth::user()->role === 'smo')
                            <td data-label="Requestor">
                                <div class="request-person"><span>{{ strtoupper(substr($req->user?->name ?? '?', 0, 1)) }}</span><div><b>{{ $req->user?->name ?? 'Former or unavailable user' }}</b><small>{{ $req->user?->department ?: 'Unassigned' }}</small></div></div>
                            </td>
                        @endif

                        <td data-label="Requested item">
                            <div class="history-item">
                                <span><i data-lucide="package"></i></span>
                                <div>
                                    <b>{{ $req->items->first()->item_name ?? 'Supply requisition' }}</b>
                                    <small>
                                        {{ $req->items->count() }} item line{{ $req->items->count() === 1 ? '' : 's' }}
                                        @if($req->items->count() > 1)
                                            · +{{ $req->items->count() - 1 }} more
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </td>

                        <td data-label="Tier"><span class="history-tier {{ $req->request_type }}">{{ $req->request_type }}</span></td>
                        <td class="text-end" data-label="{{ $isPersonalEmployee ? 'Requested value' : 'Value' }}"><strong class="history-value">₱{{ number_format($req->grand_total, 2) }}</strong></td>
                        <td data-label="Status">
                            <span class="history-status {{ $statusTone }}">{{ $isPersonalEmployee ? $personalStage['label'] : str_replace('_', ' ', $req->status) }}</span>
                            <small class="history-stage">{{ $stageLabel }}</small>
                            @if($isPersonalEmployee && $req->status === 'for_clarification')
                                <a href="{{ route('requests.show', $req) }}#employee-next-step" class="history-stage">Read clarification remarks</a>
                                @if($req->clarificationRemarks())<small class="history-stage">{{ Str::limit($req->clarificationRemarks(), 120) }}</small>@endif
                            @endif
                            @if($isOverdue && !in_array($req->status, App\Models\Requisition::ARCHIVED_STATUSES, true))
                                <span class="history-deadline overdue"><i data-lucide="siren"></i> Overdue</span>
                            @elseif($isNear && !in_array($req->status, App\Models\Requisition::ARCHIVED_STATUSES, true))
                                <span class="history-deadline near"><i data-lucide="clock-alert"></i> {{ $isPersonalEmployee ? 'Nearing SLA deadline' : 'Day 2 warning' }}</span>
                            @endif
                        </td>
                        <td class="text-end" data-label="Actions">
                            <div class="request-actions">
                                <a href="{{ route('requests.show', [$req->id, ...$listContext]) }}" class="history-action details" aria-label="View details for {{ $req->tracking_code }}" title="View request details"><i data-lucide="arrow-up-right"></i><span>{{ $isPersonalEmployee && $req->status === 'ready_for_pickup' ? 'View pickup status' : 'Details' }}</span></a>
                                @if($type === 'archive')
                                    <a href="{{ route('requests.download', $req->id) }}" class="history-action" title="Download requisition PDF" download><i data-lucide="file-down"></i><span>PDF</span></a>
                                @endif
                                @if($type === 'active' && Auth::id() === $req->user_id && $req->status === 'pending')
                                    <a href="{{ route('requisitions.edit', $req->id) }}" class="history-action edit" title="Edit pending request"><i data-lucide="pencil"></i></a>
                                    <details class="history-withdrawal"><summary class="history-action cancel">Withdraw<span class="visually-hidden"> {{ $req->tracking_code }}</span></summary>
                                    <form action="{{ route('requisitions.destroy', $req->id) }}" method="POST" data-confirm="Withdraw pending requisition {{ $req->tracking_code }}? Its items and history will remain in the archive. It cannot be resumed." data-confirm-title="Withdraw this request?" data-confirm-accept="Withdraw request" data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <label for="withdrawalReason{{ $req->id }}">Reason (optional)</label>
                                        <textarea id="withdrawalReason{{ $req->id }}" name="withdrawal_reason" rows="2" maxlength="500" class="form-control"></textarea>
                                        <button type="submit" class="history-action cancel">Withdraw request</button>
                                    </form>
                                    </details>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ Auth::user()->role === 'smo' ? '7' : '6' }}">
                            <div class="request-empty">
                                <span><i data-lucide="{{ $type === 'active' ? 'inbox' : 'archive' }}"></i></span>
                                <strong>{{ $isPersonalEmployee && request()->anyFilled(['search', 'status', 'date_from', 'date_to']) ? 'No requests match these filters' : ($type === 'active' ? 'No active requisitions found' : 'No archived requisitions found') }}</strong>
                                <p>{{ request()->hasAny(['search', 'status', 'dept', 'date_from', 'date_to']) ? 'Try changing or clearing the current filters.' : ($type === 'active' ? 'New requests will appear here after submission.' : 'Completed and rejected requests will appear here.') }}</p>
                                @if($isPersonalEmployee && request()->anyFilled(['search', 'status', 'date_from', 'date_to']))
                                    <a href="{{ route($type === 'archive' ? 'requests.archive' : 'requests.index') }}" class="btn btn-htc">Clear filters</a>
                                @elseif($isPersonalEmployee && $type === 'active')
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#globalRequestModal">Create requisition</button>
                                @elseif($type === 'active' && !request()->hasAny(['search', 'status', 'dept', 'date_from', 'date_to']))
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#globalRequestModal">Create requisition</button>
                                @endif
                                @if($isPersonalEmployee && $type === 'archive')<a href="{{ route('requests.index') }}">View my active requests</a>@endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@pushOnce('styles', 'page-styles:requests/partials/history-table.blade.php')
<style>
    .history-withdrawal { text-align:left; }.history-withdrawal summary { cursor:pointer; }.history-withdrawal[open] { max-width:250px; }.history-withdrawal form { display:grid; gap:var(--space-8); padding:var(--space-12); border:1px solid #eadcdc; border-radius:8px; background:#fff; }.history-withdrawal label { font-size:var(--text-secondary); color:#5a6b60; }.history-withdrawal textarea { min-width:180px; }.history-withdrawal :is(summary,textarea):focus-visible { outline:3px solid #b38328; outline-offset:3px; }
    .request-history-table { margin: 0; font-size: var(--text-secondary); }.request-history-table thead th { padding: var(--space-12) var(--space-16) !important; background: #f4f8f5 !important; color: #839088 !important; font-size: var(--text-caption) !important; font-weight: 900 !important; }.request-history-table tbody td { padding: var(--space-12) var(--space-16) !important; }.history-request-code { color: #294c37; font-family: ui-monospace,SFMono-Regular,Menlo,monospace; font-size: var(--text-caption); font-weight: 900; }.request-history-table td > small { display: flex; align-items: center; gap: var(--space-4); margin-top: var(--space-4); color: #909b94; font-size: var(--text-caption); }.request-history-table td > small svg { width: 11px; height: 11px; }.request-person,.history-item { display: flex; align-items: center; gap: var(--space-8); }.request-person > span,.history-item > span { width: 32px; height: 32px; flex: 0 0 32px; display: grid; place-items: center; border-radius: 9px; background: #e8f4ec; color: #216b42; font-size: var(--text-secondary); font-weight: 900; }.history-item > span { background: #f1f4f2; color: #687a6f; }.history-item svg { width: 15px; }.request-person b,.request-person small,.history-item b,.history-item small { display: block; }.request-person b,.history-item b { color: #35473c; font-size: var(--text-secondary); }.request-person small,.history-item small { margin-top: .1rem; color: #8b978f; font-size: var(--text-caption); }.history-tier,.history-status { display: inline-block; padding: var(--space-4) var(--space-8); border-radius: 999px; font-size: var(--text-caption); font-weight: 900; letter-spacing: var(--tracking-label); text-transform: uppercase; white-space: nowrap; }.history-tier.minor { background: #e3f3e9; color: #226b44; }.history-tier.major { background: #fff0cd; color: #8f5c08; }.history-value { color: #33473a; font-size: var(--text-caption); white-space: nowrap; }.history-status.progress { background: #edf2ef; color: #5a6b61; }.history-status.ready,.history-status.released { background: #ddf2e5; color: #17683f; }.history-status.clarification { background: #fff0c9; color: #8d5b08; }.history-status.rejected { background: #fde3e5; color: #b22e36; }.history-stage { display: block!important; color: #8b978f!important; line-height: var(--line-heading); }.history-deadline { display: inline-flex; align-items: center; gap: var(--space-4); margin-top: var(--space-4); font-size: var(--text-caption); font-weight: 900; text-transform: uppercase; }.history-deadline svg { width: 11px; }.history-deadline.overdue { color: #b82f38; }.history-deadline.near { color: #a56808; }.request-overdue { box-shadow: inset 3px 0 #d33e47; }.request-near { box-shadow: inset 3px 0 #e1a021; }.request-actions { display: inline-flex; align-items: center; justify-content: flex-end; gap: var(--space-4); }.request-actions form { margin: 0; }.history-action { min-height: 32px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-4); padding: var(--space-8) var(--space-8); border: 1px solid #dbe5df; border-radius: 8px; color: #52665a; background: #fff; font-size: var(--text-caption); font-weight: 800; text-decoration: none; }.history-action svg { width: 13px; height: 13px; }.history-action.details { color: #236c45; background: #f2f8f4; }.history-action.edit:hover { border-color: #9bae9f; color: #314b3a; background: #f1f5f2; }.history-action.cancel { color: #b33840; background: #fff8f8; }.history-action:hover { border-color: #237149; color: #fff; background: #237149; }
</style>
@endPushOnce
