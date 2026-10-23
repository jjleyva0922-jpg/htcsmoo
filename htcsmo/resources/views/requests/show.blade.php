<x-app-layout>
    <x-slot name="header">
        <span style="color: white; font-weight: 800;">REQUISITION DETAILS</span>
    </x-slot>

    @php
        $lastLog = $request->logs->sortByDesc('created_at')->first();
        $questioningRole = $request->status === 'for_clarification' ? $lastLog?->role : null;
        $isEmployee = Auth::user()->role === 'employee';
        if ($isEmployee && $request->status === 'for_clarification') {
            $questioningRole = $request->currentReviewerRole();
        }
        $vpTitle = $request->request_type === 'minor' ? 'VP Finance' : 'VP Admin';

        $statusMeta = match ($request->status) {
            'pending' => ['label' => 'Awaiting department head', 'tone' => 'warning', 'icon' => 'clock-3'],
            'approved_dept' => ['label' => "Awaiting {$vpTitle}", 'tone' => 'progress', 'icon' => 'hourglass'],
            'approved_vp' => $request->request_type === 'minor'
                ? ['label' => 'Awaiting SMO processing', 'tone' => 'ready', 'icon' => 'package-check']
                : ['label' => 'Awaiting Provost', 'tone' => 'progress', 'icon' => 'hourglass'],
            'approved_provost' => ['label' => 'Awaiting President', 'tone' => 'progress', 'icon' => 'hourglass'],
            'approved_president' => ['label' => 'Awaiting SMO processing', 'tone' => 'ready', 'icon' => 'package-check'],
            'processing' => ['label' => 'Processing', 'tone' => 'progress', 'icon' => 'package-open'],
            'ready_for_pickup' => ['label' => 'Ready for pickup', 'tone' => 'ready', 'icon' => 'package-check'],
            'completed' => ['label' => 'Completed', 'tone' => 'success', 'icon' => 'badge-check'],
            'released' => ['label' => 'Released (legacy)', 'tone' => 'success', 'icon' => 'badge-check'],
            'rejected' => ['label' => 'Rejected', 'tone' => 'danger', 'icon' => 'circle-x'],
            'withdrawn' => ['label' => 'Withdrawn by requestor', 'tone' => 'neutral', 'icon' => 'circle-minus'],
            'for_clarification' => ['label' => 'Clarification requested', 'tone' => 'clarification', 'icon' => 'message-circle-question'],
            default => ['label' => ucwords(str_replace('_', ' ', $request->status)), 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };

        $fulfillmentStatuses = ['processing', 'ready_for_pickup', 'completed', 'released'];
        if ($isEmployee) {
            $statusMeta = $request->requestorStatus();
        }
        $isDeptDone = in_array($request->status, ['approved_dept', 'approved_vp', 'approved_provost', 'approved_president', ...$fulfillmentStatuses], true)
            || ($request->status === 'for_clarification' && in_array($questioningRole, ['vp_finance', 'vp_admin', 'provost', 'president'], true));
        $isDeptQuestioning = $request->status === 'for_clarification' && ($questioningRole === 'dept_head' || is_null($questioningRole));
        $isVPDone = in_array($request->status, ['approved_vp', 'approved_provost', 'approved_president', ...$fulfillmentStatuses], true)
            || ($request->status === 'for_clarification' && in_array($questioningRole, ['provost', 'president'], true));
        $isVPQuestioning = $request->status === 'for_clarification' && in_array($questioningRole, ['vp_finance', 'vp_admin'], true);

        $steps = [
            ['label' => 'Submitted', 'icon' => 'send', 'state' => 'completed'],
            [
                'label' => $isDeptQuestioning ? 'Dept Head inquiry' : 'Dept Head',
                'icon' => $isDeptQuestioning ? 'help-circle' : 'user-check',
                'state' => $isDeptDone ? 'completed' : ($isDeptQuestioning ? 'questioning' : ($request->status === 'pending' ? 'active' : 'pending')),
            ],
            [
                'label' => $isVPQuestioning ? "{$vpTitle} inquiry" : $vpTitle,
                'icon' => $isVPQuestioning ? 'help-circle' : 'shield-check',
                'state' => $isVPDone ? 'completed' : ($isVPQuestioning ? 'questioning' : ($request->status === 'approved_dept' ? 'active' : 'pending')),
            ],
        ];

        if ($request->request_type === 'major') {
            $isProvostDone = in_array($request->status, ['approved_provost', 'approved_president', ...$fulfillmentStatuses], true)
                || ($request->status === 'for_clarification' && $questioningRole === 'president');
            $isProvostQuestioning = $request->status === 'for_clarification' && $questioningRole === 'provost';
            $steps[] = [
                'label' => $isProvostQuestioning ? 'Provost inquiry' : 'Provost',
                'icon' => $isProvostQuestioning ? 'help-circle' : 'graduation-cap',
                'state' => $isProvostDone ? 'completed' : ($isProvostQuestioning ? 'questioning' : ($request->status === 'approved_vp' ? 'active' : 'pending')),
            ];

            $isPresidentQuestioning = $request->status === 'for_clarification' && $questioningRole === 'president';
            $steps[] = [
                'label' => $isPresidentQuestioning ? 'President inquiry' : 'President',
                'icon' => $isPresidentQuestioning ? 'help-circle' : 'award',
                'state' => in_array($request->status, ['approved_president', ...$fulfillmentStatuses], true)
                    ? 'completed'
                    : ($isPresidentQuestioning ? 'questioning' : ($request->status === 'approved_provost' ? 'active' : 'pending')),
            ];
        }

        $readyForProcessing = ($request->request_type === 'minor' && $request->status === 'approved_vp')
            || ($request->request_type === 'major' && $request->status === 'approved_president');
        $steps[] = [
            'label' => 'SMO Processing',
            'icon' => 'package-open',
            'state' => in_array($request->status, ['ready_for_pickup', 'completed', 'released'], true) ? 'completed' : ($request->status === 'processing' || $readyForProcessing ? 'active' : 'pending'),
        ];
        $steps[] = [
            'label' => 'Ready for Pickup',
            'icon' => 'package-check',
            'state' => in_array($request->status, ['completed', 'released'], true) ? 'completed' : ($request->status === 'ready_for_pickup' ? 'active' : 'pending'),
        ];
        $steps[] = [
            'label' => 'Completed',
            'icon' => 'circle-check',
            'state' => in_array($request->status, ['completed', 'released'], true) ? 'completed' : 'pending',
        ];
    @endphp

    <div class="container-fluid request-details-page">
        <nav class="request-detail-nav" aria-label="Requisition navigation">
            <a href="{{ $archiveReturnUrl ?? $activeReturnUrl ?? route('dashboard') }}" class="request-back-link"><i data-lucide="arrow-left"></i><span>{{ $archiveReturnUrl ? 'Back to archive results' : ($activeReturnUrl ? 'Back to active results' : 'Back to dashboard') }}</span></a>
            <a href="{{ route('requests.download', $request->id) }}" class="request-pdf-action btn btn-htc" download><i data-lucide="file-down"></i><span>Export official PDF</span></a>
        </nav>

        <header class="request-hero">
            <div class="request-hero-main">
                <div class="request-hero-icon"><i data-lucide="clipboard-list"></i></div>
                <div class="min-w-0">
                    <div class="request-kicker">{{ strtoupper($request->request_type) }} REQUISITION</div>
                    <h1>{{ $request->tracking_code }}</h1>
                    <div class="request-status request-status--{{ $statusMeta['tone'] }}"><i data-lucide="{{ $statusMeta['icon'] }}"></i><span>{{ $statusMeta['label'] }}</span></div>
                </div>
            </div>
            <div class="request-hero-stats">
                <div><span>{{ $isEmployee ? 'Requested value' : 'Total value' }}</span><strong>₱{{ number_format($request->grand_total, 2) }}</strong></div>
                <div><span>{{ $isEmployee ? 'Item lines' : 'Items' }}</span><strong>{{ $isEmployee ? $request->items->count() : $request->items->sum('quantity') }}</strong></div>
                <div><span>Submitted</span><strong>{{ ($request->submitted_at ?? $request->created_at)->format('M d, Y') }}</strong></div>
                @if(in_array($request->status, \App\Models\Requisition::ARCHIVED_STATUSES, true))
                    <div><span>Closed</span><strong>{{ $request->closed_at?->format('M d, Y') ?? 'Date unavailable' }}</strong></div>
                @else
                    <div><span>SLA deadline</span><strong>{{ $request->due_at?->format('M d, Y') ?? 'Not set' }}</strong></div>
                @endif
            </div>
        </header>
        @if($isEmployee) @include('requests.partials.personal-guidance') @endif

        <div class="request-detail-grid">
            <main class="request-detail-main">
                <section class="request-panel request-overview-panel">
                    <div class="request-section-heading">
                        <div><span>Request overview</span><h2>Requester and purpose</h2></div>
                        <span class="request-tier-pill">{{ ucfirst($request->request_type) }} tier</span>
                    </div>
                    <div class="request-overview-grid">
                        <div class="requester-block">
                            <div class="requester-avatar"><i data-lucide="user-round"></i></div>
                            <div class="min-w-0">
                                <span class="request-field-label">Official requester</span>
                                <strong>{{ $request->user?->name ?? 'Former or unavailable user' }}</strong>
                                <small>{{ $request->user?->department ?: 'Unassigned department' }} · {{ $request->user?->school_id ?? 'No ID available' }}</small>
                            </div>
                        </div>
                        <div class="request-date-block"><span class="request-field-label">Last updated</span><strong>{{ $request->updated_at->format('M d, Y') }}</strong><small>{{ $request->updated_at->format('h:i A') }}</small></div>
                    </div>
                    <div class="request-purpose-block">
                        <div class="request-purpose-icon"><i data-lucide="message-square-text"></i></div>
                        <div class="request-purpose-copy"><span class="request-field-label">Purpose and justification</span><p>{{ $request->purpose ?: 'No reason was recorded for this requisition.' }}</p></div>
                    </div>
                </section>

                @if(in_array($request->status, \App\Models\Requisition::ARCHIVED_STATUSES, true))
                    <section class="request-panel" aria-labelledby="final-outcome-heading">
                        <div class="request-section-heading"><div><span>Archived outcome</span><h2 id="final-outcome-heading">{{ $request->archiveStatusLabel() }}</h2></div></div>
                        <div class="p-3">
                            @if($request->closureRemarks())<p class="mb-2">{{ $request->closureRemarks() }}</p>@endif
                            @if($request->status === 'withdrawn')
                                <p class="small text-muted mb-0">This request was withdrawn before approval. Its items and history are retained. Submit a new request if you still need supplies.</p>
                            @elseif($request->status === 'released')
                                <p class="small text-muted mb-0">Legacy released record. This older status does not separately confirm pickup in the current workflow.</p>
                            @elseif($request->status === 'rejected')
                                <p class="small text-muted mb-0">This request is closed and will not continue through approval.</p>
                            @else
                                <p class="small text-muted mb-0">Pickup was confirmed by SMO. Requested value is not an expenditure report.</p>
                            @endif
                        </div>
                    </section>
                @else
                <section class="request-panel request-approval-panel">
                    <div class="request-section-heading"><div><span>Approval route</span><h2>Requisition progress</h2></div><small>Live workflow status</small></div>
                    <div class="request-progress-scroll" tabindex="0" aria-label="Approval route; scroll horizontally to see every step">
                        <ol class="request-progress-list" style="--request-step-count: {{ count($steps) }}">
                            @foreach($steps as $index => $step)
                                <li class="request-progress-step is-{{ $step['state'] }}">
                                    <div class="request-step-marker"><i data-lucide="{{ $step['icon'] }}"></i></div>
                                    <span>Step {{ $index + 1 }}</span><strong>{{ $step['label'] }}</strong>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>
                @endif

                <section class="request-panel request-items-panel">
                    <div class="request-section-heading request-items-heading"><div><span>Order contents</span><h2>Requested items</h2></div><span class="request-item-count">{{ $request->items->count() }} {{ Str::plural('line item', $request->items->count()) }}</span></div>
                    <div class="table-responsive request-items-wrap">
                        <table class="table align-middle mb-0 workflow-responsive-table request-items-table">
                            <thead><tr><th>Item and specifications</th><th class="text-center">Quantity</th><th class="text-end">Unit price</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                                @forelse($request->items as $item)
                                    <tr>
                                        <td data-label="Item"><div class="request-item-name">{{ $item->item_name }}</div><small>{{ $item->specifications ?: 'No additional specifications' }}</small></td>
                                        <td class="text-center" data-label="Quantity"><span class="request-qty-pill">{{ $item->quantity }} {{ $item->unit }}</span></td>
                                        <td class="text-end" data-label="Unit price">₱{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end request-line-total" data-label="Subtotal">₱{{ number_format($item->subtotal, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-5">No line items were recorded.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot><tr><td colspan="3">Total requisition value</td><td class="text-end">₱{{ number_format($request->grand_total, 2) }}</td></tr></tfoot>
                        </table>
                    </div>
                </section>
            </main>

            <aside class="request-audit-column">
                <section class="request-panel request-audit-panel">
                    <div class="request-section-heading"><div><span>Accountability</span><h2>Activity audit trail</h2></div><i data-lucide="history"></i></div>
                    <div class="request-audit-stream">
                        <article class="request-audit-entry is-created">
                            <div class="request-audit-dot"><i data-lucide="plus"></i></div>
                            <div><time>{{ $request->created_at->format('M d, Y · h:i A') }}</time><h3>Requisition created</h3><p>Submitted by {{ $request->user?->name ?? 'Former or unavailable user' }}</p></div>
                        </article>
                        @foreach($request->logs ?? [] as $log)
                            <article class="request-audit-entry">
                                <div class="request-audit-dot"><i data-lucide="check"></i></div>
                                <div>
                                    <time>{{ $log->created_at->format('M d, Y · h:i A') }}</time><h3>{{ $log->action }}</h3>
                                    <p>{{ $log->actor?->name ?? 'Actor not recorded' }} · {{ str_replace('_', ' ', $log->role ?? 'Role not recorded') }}</p>
                                    @if($log->remarks)<blockquote>{{ $log->remarks }}</blockquote>@endif
                                    @if($log->digitalSignature)<span class="request-signature-badge"><i data-lucide="badge-check"></i> Digitally signed by {{ $log->digitalSignature->signer_name }}</span>@endif
                                </div>
                            </article>
                        @endforeach
                        @if(in_array($request->status, ['completed', 'released'], true))
                            <article class="request-audit-entry is-released">
                                <div class="request-audit-dot"><i data-lucide="package-check"></i></div>
                                <div><time>{{ $request->closed_at?->format('M d, Y · h:i A') ?? 'Completion date unavailable' }}</time><h3>{{ $request->status === 'released' ? 'Legacy release recorded' : 'Fulfillment completed' }}</h3><p>{{ $request->status === 'released' ? 'Historical release status; separate pickup confirmation was not recorded.' : 'The requestor received the prepared supplies.' }}</p></div>
                            </article>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>

    @pushOnce('styles', 'page-styles:requests/show.blade.php')
    <style>
        .request-details-page { --request-ink: #173226; --request-muted: #6f8177; --request-line: #dfe8e2; padding: var(--space-12) 0 var(--space-24); }
        .request-detail-nav { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); margin-bottom: var(--space-12); }
        .request-back-link { display: inline-flex; align-items: center; gap: var(--space-8); color: #607168; font-size: var(--text-caption); font-weight: 800; text-decoration: none; text-transform: uppercase; letter-spacing: var(--tracking-label); }
        .request-back-link:hover { color: var(--htc-green); }
        .request-back-link svg, .request-pdf-action svg { width: 16px; height: 16px; }
        .request-pdf-action { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-8); min-height: 40px; padding: var(--space-8) var(--space-16); border-radius: 11px; }
        .request-hero { position: relative; display: flex; align-items: center; justify-content: space-between; gap: var(--space-32); overflow: hidden; margin-bottom: var(--space-16); padding: var(--space-24) var(--space-24); border: 1px solid rgba(255,255,255,.14); border-radius: 18px; background: radial-gradient(circle at 84% -40%, rgba(110,231,155,.25), transparent 20rem), linear-gradient(125deg, #0b3d25, #17633e 60%, #1c7549); color: #fff; box-shadow: 0 14px 30px rgba(14, 67, 39, .16); }
        .request-hero::after { content: ''; position: absolute; right: -4rem; bottom: -7rem; width: 17rem; height: 17rem; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; }
        .request-hero-main, .request-hero-stats { position: relative; z-index: 1; display: flex; align-items: center; }
        .request-hero-main { min-width: 0; gap: var(--space-16); }
        .request-hero-icon { width: 52px; height: 52px; flex: 0 0 52px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.17); border-radius: 15px; background: rgba(255,255,255,.12); }
        .request-hero-icon svg { width: 25px; height: 25px; }
        .request-kicker { margin-bottom: var(--space-4); color: #a9e8bf; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); }
        .request-hero h1 { margin: 0 0 var(--space-8); overflow-wrap: anywhere; color: #fff; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: var(--text-heading-lg); font-weight: 800; }
        .request-status { width: fit-content; display: inline-flex; align-items: center; gap: var(--space-4); padding: var(--space-4) var(--space-8); border-radius: 999px; font-size: var(--text-caption); font-weight: 800; }
        .request-status svg { width: 13px; height: 13px; }
        .request-status--warning, .request-status--progress { background: #fff3cd; color: #785500; }
        .request-status--ready, .request-status--success { background: #dff7e8; color: #11623a; }
        .request-status--danger { background: #ffe1e3; color: #9d2930; }
        .request-status--clarification { background: #e6edff; color: #3159a8; }
        .request-status--neutral { background: rgba(255,255,255,.15); color: #fff; }
        .request-hero-stats { flex: 0 0 auto; gap: var(--space-8); }
        .request-hero-stats > div { min-width: 112px; padding: var(--space-12) var(--space-12); border: 1px solid rgba(255,255,255,.13); border-radius: 12px; background: rgba(5,38,21,.18); }
        .request-hero-stats span { display: block; margin-bottom: var(--space-4); color: #bfe6cc; font-size: var(--text-caption); font-weight: 700; text-transform: uppercase; letter-spacing: var(--tracking-label); }
        .request-hero-stats strong { color: #fff; font-size: var(--text-secondary); }
        .request-detail-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(280px, .8fr); gap: var(--space-16); align-items: start; }
        .request-detail-main { min-width: 0; display: grid; gap: var(--space-16); }
        .request-panel { overflow: hidden; border: 1px solid var(--request-line); border-radius: 16px; background: #fff; box-shadow: 0 7px 20px rgba(24, 70, 43, .05); }
        .request-section-heading { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-16) var(--space-16); border-bottom: 1px solid #e7eee9; background: linear-gradient(135deg, #fbfdfc, #f4f8f5); }
        .request-section-heading > div > span, .request-field-label { display: block; color: #85958c; font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: var(--tracking-label); }
        .request-section-heading h2 { margin: .15rem 0 0; color: var(--request-ink); font-size: var(--text-heading-md); font-weight: 800; }
        .request-section-heading > small { color: var(--request-muted); font-size: var(--text-caption); }
        .request-tier-pill, .request-item-count { padding: var(--space-4) var(--space-8); border: 1px solid #cfe1d6; border-radius: 999px; background: #edf7f1; color: #246443; font-size: var(--text-caption); font-weight: 800; white-space: nowrap; }
        .request-overview-grid { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: var(--space-24); padding: var(--space-16); }
        .requester-block { min-width: 0; display: flex; align-items: center; gap: var(--space-12); }
        .requester-avatar, .request-purpose-icon { display: grid; place-items: center; color: var(--htc-green); background: #e4f3e9; }
        .requester-avatar { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 13px; }
        .requester-avatar svg { width: 20px; height: 20px; }
        .requester-block strong, .request-date-block strong { display: block; margin: .15rem 0; color: var(--request-ink); font-size: var(--text-secondary); }
        .requester-block small, .request-date-block small { display: block; color: var(--request-muted); font-size: var(--text-caption); }
        .request-date-block { min-width: 120px; padding-left: var(--space-24); border-left: 1px solid #e7eee9; }
        .request-purpose-block { position: relative; display: flex; align-items: flex-start; gap: var(--space-16); margin: 0 var(--space-16) var(--space-16); padding: var(--space-16) var(--space-16); overflow: hidden; border: 1px solid #b9d9c5; border-left: 5px solid var(--htc-green); border-radius: 13px; background: linear-gradient(135deg, #edf8f1, #f9fcfa); box-shadow: 0 7px 18px rgba(24, 91, 59, .08); }
        /* Decorative quotation glyph; this is not reading text. */
        .request-purpose-block::after { content: '“'; position: absolute; right: 1rem; top: -.55rem; color: rgba(24, 91, 59, .08); font-family: Georgia, serif; font-size: 5.5rem; line-height: 1; pointer-events: none; }
        .request-purpose-icon { width: 42px; height: 42px; flex: 0 0 42px; border: 1px solid #b9ddc6; border-radius: 12px; background: #d9f0e1; }
        .request-purpose-icon svg { width: 20px; height: 20px; }
        .request-purpose-copy { position: relative; z-index: 1; min-width: 0; }
        .request-purpose-copy .request-field-label { color: #267048; font-size: var(--text-secondary); }
        .request-purpose-block p { margin: var(--space-4) 0 0; color: #1f3e2e; font-size: var(--text-body); font-weight: 600; line-height: var(--line-prose); white-space: pre-line; overflow-wrap: anywhere; }
        .request-progress-scroll { overflow-x: auto; padding: var(--space-16) var(--space-16) var(--space-24); scrollbar-width: thin; }
        .request-progress-list { min-width: calc(var(--request-step-count) * 116px); display: grid; grid-template-columns: repeat(var(--request-step-count), minmax(100px, 1fr)); margin: 0; padding: 0; list-style: none; }
        .request-progress-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .request-progress-step::after { content: ''; position: absolute; z-index: 0; top: 18px; left: 50%; width: 100%; height: 2px; background: #dfe7e2; }
        .request-progress-step:last-child::after { display: none; }
        .request-progress-step.is-completed::after { background: var(--htc-green); }
        .request-step-marker { position: relative; z-index: 1; width: 38px; height: 38px; display: grid; place-items: center; margin-bottom: var(--space-8); border: 2px solid #d9e3dd; border-radius: 50%; background: #fff; color: #91a097; }
        .request-step-marker svg { width: 16px; height: 16px; }
        .request-progress-step > span { color: #9aa8a0; font-size: var(--text-caption); font-weight: 800; text-transform: uppercase; letter-spacing: var(--tracking-label); }
        .request-progress-step > strong { max-width: 110px; margin-top: .15rem; color: #68796f; font-size: var(--text-caption); line-height: var(--line-heading); }
        .request-progress-step.is-completed .request-step-marker { border-color: var(--htc-green); background: var(--htc-green); color: #fff; }
        .request-progress-step.is-completed > strong { color: var(--htc-green); }
        .request-progress-step.is-active .request-step-marker { border-color: #e2a900; color: #a87900; box-shadow: 0 0 0 4px #fff5cf; }
        .request-progress-step.is-active > strong { color: #8a6500; }
        .request-progress-step.is-questioning .request-step-marker { border-color: #4571c5; color: #3159a8; box-shadow: 0 0 0 4px #eaf0ff; }
        .request-progress-step.is-questioning > strong { color: #3159a8; }
        .request-items-wrap { border: 0 !important; border-radius: 0 !important; }
        .request-items-table thead th { padding: var(--space-12) var(--space-16) !important; background: #f7faf8 !important; color: #718178 !important; font-size: var(--text-caption) !important; }
        .request-items-table tbody td { padding: var(--space-16) var(--space-16) !important; }
        .request-item-name { margin-bottom: var(--space-4); color: var(--request-ink); font-size: var(--text-caption); font-weight: 800; }
        .request-items-table tbody small { color: var(--request-muted); font-size: var(--text-caption); }
        .request-qty-pill { display: inline-flex; padding: var(--space-4) var(--space-8); border-radius: 7px; background: #f0f4f2; color: #40554a; font-size: var(--text-caption); font-weight: 700; }
        .request-line-total { color: var(--htc-green) !important; font-weight: 800; }
        .request-items-table tfoot td { padding: var(--space-12) var(--space-16) !important; border: 0; background: #e8f4ec; color: #315341; font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; }
        .request-items-table tfoot td:last-child { color: var(--htc-green); font-size: var(--text-body); }
        .request-audit-column { min-width: 0; }
        .request-audit-panel { position: sticky; top: 88px; }
        .request-audit-panel > .request-section-heading > svg { width: 18px; height: 18px; color: #7c8e84; }
        .request-audit-stream { max-height: calc(100dvh - 260px); overflow-y: auto; padding: var(--space-16) var(--space-16); overscroll-behavior: contain; }
        .request-audit-entry { position: relative; display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: var(--space-12); padding-bottom: var(--space-24); }
        .request-audit-entry:not(:last-child)::after { content: ''; position: absolute; top: 26px; bottom: 0; left: 13px; border-left: 1px dashed #cfdcd4; }
        .request-audit-dot { position: relative; z-index: 1; width: 28px; height: 28px; display: grid; place-items: center; border: 1px solid #cbdad1; border-radius: 50%; background: #f2f7f4; color: #4c725d; }
        .request-audit-dot svg { width: 12px; height: 12px; }
        .request-audit-entry.is-created .request-audit-dot, .request-audit-entry.is-released .request-audit-dot { border-color: #7eb996; background: #e5f5eb; color: #17633e; }
        .request-audit-entry time { display: block; margin-bottom: .15rem; color: #91a097; font-size: var(--text-caption); font-weight: 700; text-transform: uppercase; }
        .request-audit-entry h3 { margin: 0; color: var(--request-ink); font-size: var(--text-heading-sm); font-weight: 800; line-height: var(--line-body); }
        .request-audit-entry p { margin: var(--space-4) 0 0; color: var(--request-muted); font-size: var(--text-body); line-height: var(--line-body); }
        .request-audit-entry blockquote { margin: var(--space-8) 0 0; padding: var(--space-8) var(--space-12); border-left: 3px solid #9cc4aa; border-radius: 0 8px 8px 0; background: #f4f8f5; color: #586b60; font-size: var(--text-body); font-style: italic; }
        .request-signature-badge { display: inline-flex; align-items: center; gap: var(--space-4); margin-top: var(--space-8); color: #27724a; font-size: var(--text-caption); font-weight: 800; }
        .request-signature-badge svg { width: 12px; height: 12px; }
        @media (max-width: 991.98px) {
            .request-hero { align-items: flex-start; flex-direction: column; }
            .request-hero-stats { width: 100%; }
            .request-hero-stats > div { flex: 1; min-width: 0; }
            .request-detail-grid { grid-template-columns: 1fr; }
            .request-audit-panel { position: static; }
            .request-audit-stream { max-height: none; }
        }
        @media (max-width: 575.98px) {
            .request-details-page { padding-top: var(--space-4); }
            .request-detail-nav { align-items: stretch; flex-direction: column; }
            .request-pdf-action { width: 100%; }
            .request-hero { padding: var(--space-16); border-radius: 15px; }
            .request-hero-icon { width: 44px; height: 44px; flex-basis: 44px; }
            .request-hero h1 { font-size: var(--text-heading-lg); }
            .request-hero-stats { display: grid; grid-template-columns: 1fr 1fr; }
            .request-hero-stats > div:first-child { grid-column: 1 / -1; }
            .request-overview-grid { grid-template-columns: 1fr; }
            .request-date-block { padding: var(--space-12) 0 0; border-top: 1px solid #e7eee9; border-left: 0; }
            .request-section-heading { align-items: flex-start; padding: var(--space-12); }
            .request-purpose-block { margin: 0 var(--space-12) var(--space-12); }
            .request-progress-scroll { padding-right: var(--space-12); padding-left: var(--space-12); }
            .request-items-heading { align-items: center; }
        }
    </style>
    @endPushOnce
</x-app-layout>
