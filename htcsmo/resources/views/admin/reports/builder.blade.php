<x-app-layout>
    <x-slot name="header">Comprehensive Report Center</x-slot>

    <div class="container-fluid py-2 reports-workspace" data-filter-workspace="reports">
        @php
            $overviewPeriod = request()->only('period', 'date_from', 'date_to');
            if (request('period') === 'all' || (request('period') === 'custom' && (!request('date_from') || !request('date_to')))) {
                $overviewPeriod = ['period' => 'current_semester'];
            }
        @endphp
        <a href="{{ route('admin.reports.index', $overviewPeriod) }}" class="btn btn-light border rounded-pill mb-3">Back to Operational Analytics</a>
        <div data-filter-region="context">
        @error('download_pdf')<div class="alert alert-warning" role="alert">{{ $message }}</div>@enderror
        @if($basisLabel)<p class="text-muted small" role="note">{{ $basisLabel }}</p>@endif
        @if($reportType === 'inventory_movements' && request('supply_id'))
            <div class="alert alert-light border d-flex flex-wrap justify-content-between gap-2">
                <span>Item filter: <strong>{{ $rows->first()['item'] ?? 'Selected catalog item' }}</strong></span>
                <a href="{{ route('admin.reports.builder', request()->except('supply_id', 'page')) }}#report-preview" data-filter-link>Show all items</a>
            </div>
        @endif
        </div>
        @include('admin.reports.index')
    </div>
</x-app-layout>
