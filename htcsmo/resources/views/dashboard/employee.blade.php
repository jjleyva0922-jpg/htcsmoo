<div class="personal-workspace">
    <section class="personal-summary" aria-label="My current request totals">
        <a href="{{ route('requests.index') }}"><div class="personal-summary-top"><span class="personal-summary-icon"><i data-lucide="layers-3" aria-hidden="true"></i></span><span>Current total</span><i data-lucide="arrow-up-right" aria-hidden="true"></i></div><strong x-data="countUp({{ $activeCount }})" x-text="current">{{ $activeCount }}</strong><h3>My active requisitions</h3><p>Follow every request in progress.</p></a>
        <a href="{{ route('requests.index', ['status' => 'for_clarification']) }}" class="personal-summary-clarification"><div class="personal-summary-top"><span class="personal-summary-icon"><i data-lucide="message-circle-question" aria-hidden="true"></i></span><span>Needs your response</span><i data-lucide="arrow-up-right" aria-hidden="true"></i></div><strong>{{ $personalCounts->clarification }}</strong><h3>Needs clarification</h3><p>Review the remarks and next steps.</p></a>
        <a href="{{ route('requests.index', ['status' => 'ready_for_pickup']) }}" class="personal-summary-pickup"><div class="personal-summary-top"><span class="personal-summary-icon"><i data-lucide="package-check" aria-hidden="true"></i></span><span>Pickup status</span><i data-lucide="arrow-up-right" aria-hidden="true"></i></div><strong>{{ $personalCounts->pickup }}</strong><h3>Ready for pickup</h3><p>View requests marked ready by SMO.</p></a>
    </section>

    <div class="personal-columns">
    <span data-employee-notifications-anchor hidden></span>
    <aside class="personal-side-stack" data-employee-notifications aria-labelledby="personalUpdatesHeading" tabindex="0">
        <section class="personal-panel personal-notifications" aria-labelledby="personalUpdatesHeading">
            <header><div><h2 id="personalUpdatesHeading">Notifications</h2></div><span class="personal-section-icon"><i data-lucide="bell" aria-hidden="true"></i></span></header>
            <ul class="personal-updates">
                @forelse($personalNotifications as $notification)
                    <li class="{{ $notification->is_read ? '' : 'is-unread' }}">
                        <div class="personal-update-heading"><i data-lucide="{{ $notification->safeIcon() }}" aria-hidden="true"></i><h3>{{ $notification->title }}</h3>@unless($notification->is_read)<span class="personal-unread">Unread</span>@endunless</div>
                        <p>{{ Str::limit($notification->message, 180) }}</p>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                        @if($personalNotificationActions[$notification->id] ?? null)
                            <form class="personal-update-form" method="POST" action="{{ route('notifications.open', $notification) }}">@csrf<button type="submit" class="personal-update-open" aria-label="Open notification: {{ $notification->title }}"></button></form>
                        @else
                            <a class="personal-update-open" href="{{ route('notifications') }}" aria-label="View in notification center: {{ $notification->title }}"></a>
                        @endif
                    </li>
                @empty
                    <li class="personal-empty"><p>No request updates yet. Approval, clarification, and pickup notifications will appear here.</p></li>
                @endforelse
            </ul>
            <footer><a href="{{ route('notifications') }}">View all notifications <i data-lucide="arrow-right" aria-hidden="true"></i></a></footer>
        </section>
    </aside>
    <div class="personal-main-stack">
    <section class="personal-panel personal-attention-panel" aria-labelledby="personalActionHeading">
        <header><div><h2 id="personalActionHeading">Needs your attention <span>{{ $actionCount }}</span></h2><p>A quick look at requests waiting on you.</p></div><a href="{{ route('requests.index', ['status' => 'action_required']) }}">View action requests <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
        <div class="personal-action-grid">
            @forelse($actionRequests as $requisition)
                @php($stage = $requisition->requestorStatus())
                <article class="personal-action-card is-{{ $stage['tone'] }}">
                    <div class="personal-action-heading"><span class="personal-action-icon"><i data-lucide="{{ $stage['icon'] }}" aria-hidden="true"></i></span><span class="personal-status is-{{ $stage['tone'] }}">{{ $stage['label'] }}</span></div>
                    <h3>{{ $requisition->items->first()->item_name ?? 'Supply requisition' }}</h3>
                    <p class="personal-reference">{{ $requisition->tracking_code }} <span>· {{ ucfirst($requisition->request_type) }}</span></p>
                    @if($requisition->status === 'for_clarification')
                        <blockquote>{{ Str::limit($requisition->clarificationRemarks() ?? 'No clarification remarks were recorded. Open the request history for context.', 180) }}</blockquote>
                    @else
                        <p>{{ $stage['next_step'] }}</p>
                    @endif
                    <a class="personal-button" href="{{ route('requests.show', $requisition) }}#employee-next-step">{{ $requisition->status === 'for_clarification' ? 'Read clarification' : 'View pickup status' }} <span class="visually-hidden">for {{ $requisition->tracking_code }}</span><i data-lucide="arrow-right" aria-hidden="true"></i></a>
                </article>
            @empty
                <div class="personal-empty personal-all-clear"><span class="personal-empty-icon"><i data-lucide="circle-check" aria-hidden="true"></i></span><div><strong>No action required from you right now.</strong><p>Updates to your active requests appear below.</p></div></div>
            @endforelse
        </div>
        @if($actionCount > $actionRequests->count())<footer><a href="{{ route('requests.index', ['status' => 'action_required']) }}">Showing {{ $actionRequests->count() }} of {{ $actionCount }} action requests. View all.</a></footer>@endif
    </section>

        <section class="personal-panel" aria-labelledby="personalRequestsHeading">
            <header><div><h2 id="personalRequestsHeading">My procurement activity</h2></div><a href="{{ route('requests.index') }}">View all requests <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></header>
            <div class="employee-table-wrap">
            <table class="table employee-request-table" aria-labelledby="personalRequestsHeading">
                <caption class="visually-hidden">Your active requisitions, current status, submission date, requested value, and available actions.</caption>
                <thead><tr><th scope="col">Request / item</th><th scope="col">Status &amp; next step</th><th scope="col">Submitted</th><th scope="col" class="employee-value">Requested value</th><th scope="col" class="employee-actions-heading">Actions</th></tr></thead>
                <tbody>
                @forelse($recentRequests as $requisition)
                    @php($stage = $requisition->requestorStatus())
                    <tr class="employee-request-row is-{{ $stage['tone'] }}">
                        <th scope="row" class="employee-request-cell">
                            <a class="employee-request-code" href="{{ route('requests.show', $requisition) }}">{{ $requisition->tracking_code }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                            <span class="employee-item-name">{{ $requisition->items->first()->item_name ?? 'Supply requisition' }}</span>
                            <span class="employee-item-meta">{{ ucfirst($requisition->request_type) }}@if($requisition->items->count() > 1) &middot; +{{ $requisition->items->count() - 1 }} more item lines @endif</span>
                        </th>
                        <td data-label="Status" class="employee-status-cell">
                            <span class="personal-status is-{{ $stage['tone'] }}"><i data-lucide="{{ $stage['icon'] }}" aria-hidden="true"></i>{{ $stage['label'] }}</span>
                            @if($requisition->isDeadlineOverdue())<span class="employee-deadline is-overdue"><i data-lucide="alarm-clock" aria-hidden="true"></i>At or past SLA deadline @if($requisition->due_at)<time datetime="{{ $requisition->due_at->toIso8601String() }}">{{ $requisition->due_at->format('M d, Y · g:i A') }}</time>@endif</span>@elseif($requisition->isDeadlineNearing())<span class="employee-deadline"><i data-lucide="clock-alert" aria-hidden="true"></i>Nearing SLA deadline <span>{{ $requisition->due_at?->format('M d, Y · g:i A') ?? 'Date unavailable' }}</span></span>@endif
                            <details class="employee-next-step"><summary>View next step<span class="visually-hidden"> for {{ $requisition->tracking_code }}</span></summary><p>{{ $stage['next_step'] }}</p></details>
                        </td>
                        <td data-label="Submitted" class="employee-date"><time datetime="{{ ($requisition->submitted_at ?? $requisition->created_at)->toDateString() }}">{{ ($requisition->submitted_at ?? $requisition->created_at)->format('M d, Y') }}</time></td>
                        <td data-label="Requested value" class="employee-value">&#8369;{{ number_format((float) $requisition->grand_total, 2) }}</td>
                        <td data-label="Actions" class="employee-actions-cell"><div class="employee-row-actions"><a class="employee-track-link" href="{{ route('requests.show', $requisition) }}">{{ $requisition->status === 'for_clarification' ? 'Review' : ($requisition->status === 'ready_for_pickup' ? 'Pickup details' : 'Track') }}<span class="visually-hidden"> {{ $requisition->tracking_code }}</span><i data-lucide="arrow-right" aria-hidden="true"></i></a>@if($requisition->status === 'pending')<a class="employee-edit-link" href="{{ route('requisitions.edit', $requisition) }}">Edit<span class="visually-hidden"> pending request {{ $requisition->tracking_code }}</span></a>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="personal-empty personal-start-state"><span class="personal-empty-icon"><i data-lucide="package-open" aria-hidden="true"></i></span><h3>No active requisitions</h3><p>Submit a requisition when you need supplies. Closed requests remain in your archive.</p><button type="button" class="personal-button" data-bs-toggle="modal" data-bs-target="#globalRequestModal">Create requisition</button><a href="{{ route('requests.archive') }}">View my archive</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
    </div>
    </div>
</div>
@include('requests.partials.personal-styles')
@include('dashboard.partials.employee-styles')
@include('dashboard.partials.employee-notifications')
