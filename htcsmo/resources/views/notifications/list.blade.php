<div class="notification-feed-list">
    @forelse($notes as $note)
        @php
            $action = isset($resolvedActions) && array_key_exists($note->id, $resolvedActions)
                ? $resolvedActions[$note->id]
                : $note->actionFor(Auth::user());
            $eventGroup = match (true) {
                str_starts_with((string) $note->event_type, 'requisition.') => 'Requisition',
                str_starts_with((string) $note->event_type, 'deadline.') => 'Deadline',
                str_starts_with((string) $note->event_type, 'inventory.') => 'Inventory',
                str_starts_with((string) $note->event_type, 'account.') => 'Account',
                default => 'System',
            };
        @endphp
        <article class="notification-feed-item type-{{ $note->safeType() }} {{ $note->is_read ? 'is-read' : 'is-unread' }}" data-notification-id="{{ $note->id }}">
            <div class="notification-feed-icon" aria-hidden="true"><i data-lucide="{{ $note->safeIcon() }}"></i></div>
            <div class="notification-feed-content">
                <div class="notification-feed-meta">
                    <span>{{ $eventGroup }}</span>
                    <time datetime="{{ $note->created_at?->toIso8601String() }}" title="{{ $note->created_at?->format('F d, Y · g:i A') }}">{{ $note->created_at?->diffForHumans() }}</time>
                </div>
                <div class="notification-feed-title-row">
                    <h3>{{ $note->title }}</h3>
                    @unless($note->is_read)<span class="notification-unread-indicator" aria-label="Unread notification"></span>@endunless
                </div>
                <p>{{ $note->message }}</p>
                @if(! $action && $note->action_type)
                    <p class="notification-action-unavailable">Related record unavailable</p>
                @endif
            </div>
            @if($action)
                <form method="POST" action="{{ route('notifications.open', $note) }}">
                    @csrf
                    <button type="submit" class="notification-card-action" aria-label="{{ $action['label'] }}: {{ $note->title }}"></button>
                </form>
            @elseif(! $note->is_read)
                <form method="POST" action="{{ route('notifications.read', $note) }}" data-notification-read-form>
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="notification-card-action" aria-label="Mark as read: {{ $note->title }}"></button>
                </form>
            @endif
        </article>
    @empty
        <div class="notification-empty-state">
            <span><i data-lucide="inbox"></i></span>
            <h3>No notifications here</h3>
            <p>This category is clear. New activity will appear here when it happens.</p>
        </div>
    @endforelse
</div>
