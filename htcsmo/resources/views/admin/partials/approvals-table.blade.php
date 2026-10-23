<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 workflow-responsive-table fulfillment-table">
        <thead class="bg-light text-muted small text-uppercase">
            <tr>
                <th scope="col" class="ps-4 py-3">Tracking / tier</th>
                <th scope="col" class="py-3">Requestor / department</th>
                <th scope="col" class="py-3">Items</th>
                <th scope="col" class="py-3">Status</th>
                <th scope="col" class="py-3">Submitted / deadline</th>
                <th scope="col" class="py-3">Requested amount</th>
                <th scope="col" class="pe-4 py-3 text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $req)
                @php
                    $submitted = $req->submitted_at ?? $req->created_at;
                    $overdue = $req->isDeadlineOverdue();
                    $nearing = $req->isDeadlineNearing();
                @endphp
                <tr class="{{ $overdue ? 'table-danger' : ($nearing ? 'table-warning' : '') }}">
                    <td class="ps-4" data-label="Tracking / tier">
                        <div class="font-monospace fw-semibold">{{ $req->tracking_code }}</div>
                        <span class="badge bg-light text-dark border">{{ ucfirst($req->request_type) }}</span>
                    </td>
                    <td data-label="Requestor / department">
                        <div class="fw-bold text-dark">{{ $req->user?->name ?? 'Unknown requestor' }}</div>
                        <div class="small text-muted">{{ $req->user?->department ?? 'Unassigned' }}</div>
                        @if(in_array($req->user?->role, ['president', 'provost'], true))
                            <span class="badge bg-dark">Executive priority</span>
                        @endif
                    </td>
                    <td data-label="Items">
                        <div class="fw-semibold text-dark">{{ $req->items->take(2)->pluck('item_name')->join(', ') ?: 'No line items' }}</div>
                        <small class="text-muted">{{ $req->items->count() }} line item(s){{ $req->items->count() > 2 ? ' · +'.($req->items->count() - 2).' more' : '' }}</small>
                    </td>
                    <td data-label="Status">{{ $req->queue_status }}</td>
                    <td data-label="Submitted / deadline">
                        <time datetime="{{ $submitted?->toIso8601String() }}">{{ $submitted?->format('M d, Y h:i A') ?? 'Not recorded' }}</time>
                        <div class="small">{{ $submitted?->diffForHumans() }}</div>
                        <div class="small fw-semibold {{ $overdue ? 'text-danger' : ($nearing ? 'text-dark' : 'text-muted') }}">
                            <i data-lucide="{{ $overdue || $nearing ? 'triangle-alert' : 'clock' }}" aria-hidden="true" style="width:14px;height:14px"></i>
                            {{ $overdue ? 'Overdue' : ($nearing ? 'Deadline nearing' : ($req->due_at ? 'Within target' : 'Deadline not recorded')) }}
                        </div>
                        @if($req->due_at)<small>Due {{ $req->due_at->format('M d, Y h:i A') }}</small>@endif
                    </td>
                    <td class="fw-bold" data-label="Requested amount">₱{{ number_format($req->grand_total, 2) }}</td>
                    <td class="pe-4 text-end" data-label="Action">
                        <button type="button" data-review-request="{{ $req->id }}" class="btn btn-sm btn-primary px-3 rounded-pill"
                                aria-label="Review {{ $req->tracking_code }}"
                                @click="openDetails({{ Illuminate\Support\Js::from($req->toArray()) }})">Review</button>
                        @if(Auth::user()->role === 'smo')
                            <small class="d-block mt-1">{{ $req->can_start_processing ? 'Start processing' : match($req->status) { 'processing' => 'Mark ready for pickup', 'ready_for_pickup' => 'Confirm pickup', default => 'Coordinate clarification' } }}</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-muted">No requisitions match this group and the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="p-3" data-filter-pagination>{{ $requests->fragment('approval-results')->links() }}</div>
