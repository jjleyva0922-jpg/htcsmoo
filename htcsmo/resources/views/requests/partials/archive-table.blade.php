<div class="card request-history-card" data-filter-links>
    <div class="table-responsive">
        <table class="table workflow-responsive-table request-history-table archive-results-table">
            <caption class="visually-hidden">{{ $institutional ? 'Institutional' : 'My' }} archived requests matching the selected filters.</caption>
            <thead><tr><th scope="col">Tracking number</th>@if($institutional)<th scope="col">Requestor</th>@endif<th scope="col">Requested item</th><th scope="col">Tier</th><th scope="col">Submitted</th><th scope="col">Closed</th><th scope="col" class="text-end">Requested value</th><th scope="col">Final outcome</th><th scope="col" class="archive-action-column text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($requisitions as $req)
                    <tr>
                        <td data-label="Tracking number"><strong class="history-request-code">{{ $req->tracking_code }}</strong></td>
                        @if($institutional)<td data-label="Requestor">{{ $req->user?->name ?? 'Former or unavailable user' }}<small>{{ $req->user?->department ?: 'Unassigned department' }}</small></td>@endif
                        <td data-label="Requested item"><div class="history-item"><div><b>{{ $req->items->first()?->item_name ?? 'Supply requisition' }}</b><small>{{ $req->items->count() }} {{ Str::plural('item line', $req->items->count()) }}</small></div></div></td>
                        <td data-label="Tier"><span class="history-tier {{ $req->request_type }}">{{ ucfirst($req->request_type) }}</span></td>
                        <td data-label="Submitted"><time datetime="{{ ($req->submitted_at ?? $req->created_at)?->toIso8601String() }}">{{ ($req->submitted_at ?? $req->created_at)?->format('M d, Y') ?? 'Unavailable' }}</time></td>
                        <td data-label="Closed">@if($req->closed_at)<time datetime="{{ $req->closed_at->toIso8601String() }}">{{ $req->closed_at->format('M d, Y') }}</time>@else<span>Closure date unavailable</span>@endif</td>
                        <td data-label="Requested value" class="text-end"><strong class="history-value">&#8369;{{ number_format((float) $req->grand_total, 2) }}</strong></td>
                        <td data-label="Final outcome">
                            <span class="history-status {{ in_array($req->status, App\Models\Requisition::FULFILLED_STATUSES, true) ? 'released' : $req->status }}">{{ $req->archiveStatusLabel() }}</span>
                            @if($req->latestClosureLog)<small>{{ $req->latestClosureLog->action }}@if($req->latestClosureLog->actor) &middot; {{ $req->latestClosureLog->actor->name }}@endif</small>@endif
                            @if(filled($req->closureRemarks()))
                                @if(request()->boolean('print'))
                                    <p class="archive-remarks">{{ $req->closureRemarks() }}</p>
                                @else
                                    <details class="archive-remarks"><summary>View {{ $req->status === 'withdrawn' ? 'withdrawal' : 'decision' }} remarks<span class="visually-hidden"> for {{ $req->tracking_code }}</span></summary><p>{{ $req->closureRemarks() }}</p></details>
                                @endif
                            @endif
                        </td>
                        <td data-label="Actions" class="archive-action-column text-end"><div class="request-actions"><a href="{{ route('requests.show', [$req->id, ...$archiveContext]) }}" class="history-action details">Details<span class="visually-hidden"> for {{ $req->tracking_code }}</span></a><a href="{{ route('requests.download', $req) }}" class="history-action" download>PDF<span class="visually-hidden"> for {{ $req->tracking_code }}</span></a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $institutional ? 9 : 8 }}"><div class="request-empty"><span><i data-lucide="archive" aria-hidden="true"></i></span><strong>{{ $hasArchiveFilters ? 'No requests match these filters' : 'No archived requisitions found' }}</strong><p>{{ $hasArchiveFilters ? 'Try changing the category or clearing all filters.' : 'Fulfilled, rejected, and withdrawn requests will appear here. Their history is kept.' }}</p>@if($hasArchiveFilters)<a class="btn btn-htc" href="{{ route($archiveRoute) }}">Clear filters</a>@endif<a href="{{ route('requests.index') }}">{{ $institutional ? 'View active requests' : 'View my active requests' }}</a></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
