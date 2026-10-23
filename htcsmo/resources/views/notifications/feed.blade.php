<div class="notification-feed-results" data-notification-feed-results>
    @include('notifications.list', ['notes' => $notes, 'resolvedActions' => $resolvedActions ?? null])

    @if($notes->hasPages())
        <div class="notification-feed-pagination" data-notification-pagination data-filter-pagination>
            {{ $notes->fragment('notification-results')->links() }}
        </div>
    @endif
</div>
