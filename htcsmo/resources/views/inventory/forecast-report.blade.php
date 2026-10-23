<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Forecast Report</title>
    <style>
        @page { margin: 24px 28px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #25342b; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { padding-bottom: 13px; border-bottom: 3px solid #176b3a; }
        .header-table, .summary, .report-table { width: 100%; border-collapse: collapse; }
        .school { color: #176b3a; font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 3px 0; font-size: 20px; }
        .subtitle { color: #65756b; }
        .meta { text-align: right; color: #6f7e75; line-height: 1.6; }
        .summary { margin: 14px 0; table-layout: fixed; }
        .summary td { padding-right: 8px; vertical-align: top; }
        .summary td:last-child { padding-right: 0; }
        .metric { padding: 9px 10px; border: 1px solid #dce6df; border-radius: 6px; background: #f7faf8; }
        .metric span { display: block; color: #77867c; font-size: 7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .metric strong { display: block; margin-top: 3px; color: #174e30; font-size: 14px; }
        .method { margin-bottom: 12px; padding: 8px 10px; border-left: 3px solid #4b8e65; background: #eff7f2; color: #52645a; line-height: 1.45; }
        .method strong { color: #24583a; }
        h2 { margin: 12px 0 6px; font-size: 12px; }
        .report-table th { padding: 7px 5px; color: #fff; background: #245d3b; font-size: 7px; letter-spacing: .3px; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 7px 5px; border-bottom: 1px solid #e0e7e2; vertical-align: top; }
        .report-table tr:nth-child(even) td { background: #f8faf9; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .item { font-weight: bold; }
        .muted { margin-top: 2px; color: #7d8b82; font-size: 7px; }
        .priority { font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .critical { color: #b1262f; }
        .buy_now { color: #a26308; }
        .plan { color: #35704b; }
        .projection { white-space: nowrap; }
        .reason { max-width: 165px; color: #586960; font-size: 7.5px; line-height: 1.35; }
        .empty { padding: 28px; border: 1px dashed #aebdb4; background: #f8faf9; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -22px; left: 0; padding-top: 6px; border-top: 1px solid #dfe6e1; color: #7d8982; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">HTC Supply Management System &bull; Forecasts are planning estimates and require SMO review before purchasing.</div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="school">SMO Inventory Control</div>
                    <h1>{{ ucfirst($forecast['period']) }} Inventory Forecast</h1>
                    <div class="subtitle">Purchase planning period: {{ $forecast['period_label'] }}</div>
                </td>
                <td class="meta">
                    Generated {{ $generatedAt->format('F d, Y - h:i A') }}<br>
                    Prepared by {{ $generatedBy }}
                </td>
            </tr>
        </table>
    </div>

    @php
        $recommendations = $forecast['recommendations'];
        $criticalCount = $recommendations->where('urgency', 'critical')->count();
        $buyNowCount = $recommendations->where('urgency', 'buy_now')->count();
        $totalUnits = $recommendations->sum('recommended_order');
    @endphp

    <table class="summary">
        <tr>
            <td><div class="metric"><span>Items to purchase</span><strong>{{ $recommendations->count() }}</strong></div></td>
            <td><div class="metric"><span>Recommended units</span><strong>{{ number_format($totalUnits) }}</strong></div></td>
            <td><div class="metric"><span>Critical / buy now</span><strong>{{ $criticalCount }} / {{ $buyNowCount }}</strong></div></td>
            <td><div class="metric"><span>Estimated budget</span><strong>PHP {{ number_format($forecast['estimated_budget'], 2) }}</strong></div></td>
            <td><div class="metric"><span>Historical records</span><strong>{{ number_format($forecast['release_events']) }}</strong></div></td>
        </tr>
    </table>

    <div class="method">
        <strong>Forecast basis:</strong> the engine blends recent average releases with matching calendar-month history, adds configured or statistical safety stock, and subtracts current inventory. Quarterly demand combines three upcoming monthly projections while applying safety stock once.
    </div>

    <h2>Recommended purchasing plan</h2>
    @if($recommendations->isEmpty())
        <div class="empty"><strong>No projected purchasing gap.</strong><br>Current inventory covers forecast demand and safety stock for {{ $forecast['period_label'] }}.</div>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 18%">Item</th>
                    <th style="width: 8%">Priority</th>
                    <th class="right" style="width: 7%">On hand</th>
                    <th class="right" style="width: 8%">Demand</th>
                    <th class="right" style="width: 8%">Safety</th>
                    <th class="right" style="width: 9%">Buy</th>
                    <th class="right" style="width: 10%">Est. cost</th>
                    <th style="width: 15%">Monthly projection</th>
                    <th style="width: 17%">Planning rationale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recommendations as $prediction)
                    @php($item = $prediction['supply'])
                    <tr>
                        <td>
                            <div class="item">{{ $item->item_name }}</div>
                            <div class="muted">{{ strtoupper($item->requisition_tier ?? 'minor') }} &bull; {{ $item->category }} &bull; {{ $item->unit }}</div>
                        </td>
                        <td><span class="priority {{ $prediction['urgency'] }}">{{ str_replace('_', ' ', $prediction['urgency']) }}</span><div class="muted">{{ ucfirst($prediction['confidence']) }} confidence</div></td>
                        <td class="right">{{ number_format($item->quantity) }}</td>
                        <td class="right">{{ number_format($prediction['forecast_demand']) }}</td>
                        <td class="right">{{ number_format($prediction['safety_stock']) }}</td>
                        <td class="right"><strong>{{ number_format($prediction['recommended_order']) }}</strong></td>
                        <td class="right">PHP {{ number_format($prediction['recommended_order'] * (float) $item->unit_price, 2) }}</td>
                        <td class="projection">
                            @foreach($prediction['monthly_projections'] as $projection)
                                {{ $projection['month']->format('M') }}: {{ $projection['demand'] }}@if(! $loop->last)<br>@endif
                            @endforeach
                        </td>
                        <td class="reason">{{ $prediction['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
