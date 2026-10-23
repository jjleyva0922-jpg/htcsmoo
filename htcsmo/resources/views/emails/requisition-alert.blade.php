<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>HTC SMO Institutional Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; color: #334155; }
        .mail-card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .mail-header { background-color: {{ $accentColor }}; padding: 25px; text-align: center; color: white; }
        .mail-body { padding: 30px; }
        .tracking-pill { display: inline-block; background: #f1f5f9; padding: 6px 14px; border-radius: 20px; font-family: monospace; font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 15px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        .info-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .info-label { color: #64748b; width: 35%; font-weight: bold; }
        .btn-action { display: inline-block; background-color: {{ $accentColor }}; color: white !important; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; font-size: 13px; text-align: center; margin-top: 20px; }
        .mail-footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="mail-card">
        <!-- HEADER -->
        <div class="mail-header">
            <h3 style="margin: 0; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">Holy Trinity College</h3>
            <div style="font-size: 11px; opacity: 0.85; margin-top: 3px;">Supply Management Office (SMO) Notification Engine</div>
        </div>

        <!-- BODY -->
        <div class="mail-body">
            <div class="tracking-pill">{{ $requisition->tracking_code }}</div>
            <h2 style="margin: 0 0 10px 0; font-size: 18px; color: #0f172a;">{{ $headline }}</h2>
            <p style="font-size: 14px; line-height: 1.5; color: #475569; margin: 0 0 20px 0;">
                {{ $actionMessage }}
            </p>

            <!-- SUMMARY DETAILS -->
            <table class="info-table">
                <tr>
                    <td class="info-label">Requestor:</td>
                    <td><strong>{{ $requisition->user->name }}</strong> ({{ $requisition->user->department }})</td>
                </tr>
                <tr>
                    <td class="info-label">Requisition Tier:</td>
                    <td><strong style="text-transform: uppercase;">{{ $requisition->request_type }} TIER</strong></td>
                </tr>
                <tr>
                    <td class="info-label">Primary Items:</td>
                    <td>
                        {{ $requisition->items->first()->item_name ?? 'Supplies' }}
                        @if($requisition->items->count() > 1)
                            <span style="color: #64748b;">(+{{ $requisition->items->count() - 1 }} others)</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="info-label">Current Status:</td>
                    <td><strong style="text-transform: uppercase; color: {{ $accentColor }};">{{ str_replace('_', ' ', $requisition->status) }}</strong></td>
                </tr>
                <tr>
                    <td class="info-label">Total Value:</td>
                    <td><strong>₱{{ number_format($requisition->grand_total, 2) }}</strong></td>
                </tr>
            </table>

            <div style="text-align: center;">
                <a href="{{ url('/requests/' . $requisition->id) }}" class="btn-action">
                    View Live Requisition in System →
                </a>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="mail-footer">
            This is an automated transmission from the HTC Supply Management Office Request Tracking System.<br>
            Holy Trinity College of General Santos City • Fiscal Daproza Avenue, General Santos City
        </div>
    </div>
</body>
</html>
