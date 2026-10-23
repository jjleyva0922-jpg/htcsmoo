<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }}</title>
    <style>
        @page { margin: 25px 28px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #29372f; font-family: DejaVu Sans, sans-serif; font-size: 7px; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .report-header { width: 100%; margin-bottom: 8px; table-layout: fixed; border-collapse: collapse; border-bottom: 2px solid #185b3b; }
        .report-header td { vertical-align: top; }
        .logo-cell { width: 7%; padding: 2px 10px 8px 0; }
        .logo { width: 48px; height: 48px; }
        .header-copy { width: 59%; padding: 2px 14px 8px 0; }
        .institution { color: #185b3b; font-size: 11px; font-weight: bold; letter-spacing: .2px; white-space: nowrap; }
        .office { margin-top: 1px; color: #65736a; font-size: 6.5px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .report-title { margin: 6px 0 1px; color: #1f2d25; font-size: 14px; font-weight: bold; }
        .report-period { color: #65736a; font-size: 7.5px; }
        .control-label { width: 10%; padding: 2.5px 5px; border: 1px solid #d6e0da; border-right: 0; color: #748178; font-size: 5.2px; font-weight: bold; letter-spacing: .2px; line-height: 1.2; text-transform: uppercase; }
        .control-value { width: 24%; padding: 2.5px 5px; border: 1px solid #d6e0da; color: #2c3a31; font-size: 5.7px; font-weight: bold; line-height: 1.2; overflow-wrap: anywhere; }
        .section { margin-top: 10px; page-break-inside: avoid; }
        .section-title { margin: 0 0 5px; padding-bottom: 3px; border-bottom: 1px solid #ccd7d0; color: #185b3b; font-size: 7.5px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .purpose { margin: 0; color: #48574e; font-size: 7px; }
        .scope-table { margin-top: 6px; border: 1px solid #d7e0da; table-layout: fixed; }
        .scope-table td { padding: 5px 7px; border-right: 1px solid #e2e8e4; vertical-align: top; }
        .scope-table td:last-child { border-right: 0; }
        .scope-table span, .metric span { display: block; margin-bottom: 2px; color: #748178; font-size: 5.4px; font-weight: bold; letter-spacing: .35px; text-transform: uppercase; }
        .scope-table strong { color: #2d3b32; font-size: 6.7px; }
        .filter-line { margin-top: 5px; padding: 5px 7px; border-left: 3px solid #739c82; background: #f5f8f6; color: #536159; }
        .filter-line strong { color: #33443a; }
        .metric-table { table-layout: fixed; }
        .metric-table td { padding-right: 6px; vertical-align: top; }
        .metric-table td:last-child { padding-right: 0; }
        .metric { min-height: 40px; padding: 7px 8px; border: 1px solid #d7e0da; background: #f8faf9; }
        .metric strong { display: block; margin-top: 3px; color: #185b3b; font-size: 10.5px; }
        .breakdown-table { table-layout: fixed; }
        .breakdown-table td { padding: 5px 6px; border: 1px solid #d7e0da; vertical-align: top; }
        .breakdown-name { color: #1f5c3b; font-weight: bold; }
        .breakdown-detail { margin-top: 2px; color: #6c7971; font-size: 5.8px; }
        .detail-title { margin: 11px 0 5px; color: #185b3b; font-size: 7.5px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .data-table { table-layout: auto; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .data-table th { padding: 4px 3px; border: 1px solid #b9c8be; color: #fff; background: #315f43; font-size: 5.2px; font-weight: bold; letter-spacing: .15px; text-align: left; text-transform: uppercase; vertical-align: middle; }
        .data-table td { padding: 3.5px 3px; border: 1px solid #d6dfd9; color: #344139; font-size: 5.7px; vertical-align: top; overflow-wrap: anywhere; }
        .data-table tbody tr:nth-child(even) td { background: #f7f9f8; }
        .data-table tfoot td { padding: 4px; border: 1px solid #b9c7be; background: #edf3ef; font-weight: bold; }
        .item-name { color: #26372d; font-weight: bold; }
        .item-spec { margin-top: 1px; color: #718078; font-size: 5.1px; line-height: 1.25; }
        .timeline-table th, .timeline-table td { font-size: 5.1px; }
        .timeline-table th:nth-child(1) { width: 13%; }
        .request-table th:nth-child(1) { width: 15%; }
        .request-table th:nth-child(2) { width: 24%; }
        .request-table th:nth-child(3) { width: 30%; }
        .request-table th:nth-child(4) { width: 17%; }
        .request-table th:nth-child(5) { width: 14%; }
        .register-table th:nth-child(1) { width: 13%; }
        .register-table th:nth-child(2) { width: 13%; }
        .register-table th:nth-child(3) { width: 35%; }
        .register-table th:nth-child(4) { width: 6%; }
        .register-table th:nth-child(5) { width: 8%; }
        .register-table th:nth-child(6), .register-table th:nth-child(7) { width: 12.5%; }
        .text-right { text-align: right !important; }
        .empty { padding: 20px !important; color: #708078 !important; text-align: center; }
        .notes { margin: 0; padding-left: 14px; color: #536159; }
        .notes li { margin-bottom: 2px; }
        .source-note { margin-top: 5px; padding: 5px 7px; border: 1px solid #dce4df; background: #fafcfb; color: #66746c; font-size: 6px; }
        .signoff { margin-top: 18px; page-break-inside: avoid; table-layout: fixed; }
        .signoff td { width: 33.33%; padding-right: 28px; border: 0; vertical-align: bottom; }
        .signoff td:last-child { padding-right: 0; }
        .signature-line { padding-top: 15px; border-bottom: 1px solid #536159; }
        .signature-label { margin-top: 3px; color: #728078; font-size: 5.5px; text-align: center; text-transform: uppercase; }
        .prepared-name { text-align: center; font-size: 6.5px; font-weight: bold; }
        .footer { position: fixed; right: 0; bottom: -27px; left: 0; padding-top: 5px; border-top: 1px solid #cfd8d2; color: #748178; font-size: 5.5px; }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <span class="footer-left">HTC Supply Management System | {{ $classification }}</span>
        <span class="footer-right">{{ $reportReference }} | Page <span class="page-number"></span></span>
    </div>

    <table class="report-header">
        <tbody>
            <tr>
                <td class="logo-cell" rowspan="6">
                    @if(file_exists(public_path('images/android-chrome-512x5122.png')))
                        <img class="logo" src="{{ public_path('images/android-chrome-512x5122.png') }}" alt="HTC">
                    @endif
                </td>
                <td class="header-copy" rowspan="6">
                    <div class="institution">HOLY TRINITY COLLEGE OF GENERAL SANTOS CITY</div>
                    <div class="office">Supply Management Office</div>
                    <div class="report-title">{{ $documentTitle }}</div>
                    <div class="report-period">Reporting period: {{ $periodLabel }}</div>
                </td>
                <td class="control-label">Reference</td>
                <td class="control-value">{{ $reportReference }}</td>
            </tr>
            <tr><td class="control-label">Generated</td><td class="control-value">{{ $generatedDate }}</td></tr>
            <tr><td class="control-label">Prepared by</td><td class="control-value">{{ $generatedBy }}</td></tr>
            <tr><td class="control-label">Period</td><td class="control-value">{{ $periodLabel }}</td></tr>
            <tr><td class="control-label">Filters</td><td class="control-value">{{ $appliedFilters ?: 'None' }}</td></tr>
            <tr><td class="control-label">Classification</td><td class="control-value">{{ $classification }}</td></tr>
        </tbody>
    </table>

    @if($requestRows->isNotEmpty())
        <div class="detail-title">Requisition Details</div>
        <table class="data-table request-table">
            <thead><tr>
                @foreach($requestColumns as $column)
                    <th class="{{ in_array($column['type'], ['money', 'number']) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach($requestRows as $row)
                    <tr>
                        @foreach($requestColumns as $column)
                            @php($value = $row[$column['key']] ?? null)
                            <td class="{{ in_array($column['type'], ['money', 'number']) ? 'text-right' : '' }}">
                                @if($column['type'] === 'money')
                                    PHP {{ number_format((float) $value, 2) }}
                                @elseif($column['type'] === 'number')
                                    {{ number_format((float) $value) }}
                                @else
                                    {{ filled($value) ? $value : '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="detail-title">Workflow Dates</div>
        <table class="data-table timeline-table">
            <thead><tr>
                @foreach($timelineColumns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach($requestRows as $row)
                    <tr>
                        @foreach($timelineColumns as $column)
                            @php($value = $row[$column['key']] ?? null)
                            <td>{{ filled($value) ? $value : '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="detail-title">{{ $detailTitle }}</div>
    <table class="data-table {{ $reportType === 'requisitions' ? 'register-table' : '' }}">
        <thead><tr>
            @foreach($detailColumns as $column)
                <th class="{{ in_array($column['type'], ['money', 'number']) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($detailColumns as $column)
                        @php($value = $row[$column['key']] ?? null)
                        <td class="{{ in_array($column['type'], ['money', 'number']) ? 'text-right' : '' }}">
                            @if($column['key'] === 'item')
                                <div class="item-name">{{ filled($value) ? $value : '-' }}</div>
                                @if(filled($row['specifications'] ?? null) && $row['specifications'] !== 'Not specified')
                                    <div class="item-spec">{{ $row['specifications'] }}</div>
                                @endif
                            @elseif($column['type'] === 'money')
                                PHP {{ number_format((float) $value, 2) }}
                            @elseif($column['type'] === 'number')
                                {{ number_format((float) $value) }}
                            @else
                                {{ filled($value) ? $value : '-' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($detailColumns) }}" class="empty">No records matched the selected period and filters.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot><tr>
                <td colspan="{{ count($detailColumns) }}" class="text-right">
                    {{ $summaryLabel }}:
                    {{ $summaryType === 'money' ? 'PHP '.number_format((float) $summaryValue, 2) : number_format((float) $summaryValue) }}
                </td>
            </tr></tfoot>
        @endif
    </table>

</body>
</html>
