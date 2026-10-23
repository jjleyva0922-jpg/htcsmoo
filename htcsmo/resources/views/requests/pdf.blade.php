<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HTC Request Form - {{ $request->tracking_code }}</title>
    <style>
        @page { margin: 20px 24px 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.2; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px; border: 1px solid #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .header-table td { border: 0; vertical-align: middle; }
        .school-name { font-size: 13px; font-weight: bold; }
        .office-name { margin-top: 2px; font-size: 9px; }
        .form-title { margin-top: 6px; font-size: 11px; font-weight: bold; }
        .form-subtitle { margin-top: 2px; font-size: 7px; font-style: italic; }
        .control-box { width: 100%; border: 2px solid #000; }
        .control-box td { padding: 3px 4px; border-width: 0 0 1px; font-size: 7px; }
        .control-box tr:last-child td { border-bottom: 0; }
        .control-box .control-value { font-size: 8px; font-weight: bold; overflow-wrap: anywhere; }
        .info-label { width: 13%; font-size: 8px; vertical-align: top; }
        .info-value { width: 37%; font-weight: bold; }
        .digital-stamp { color: #185b3b; font-size: 7px; font-weight: bold; font-style: italic; }
        .items-table { margin-top: 9px; table-layout: fixed; }
        .items-table thead { display: table-header-group; }
        .items-table tr { page-break-inside: avoid; }
        .items-table th { padding: 4px 3px; background: #f0f0f0; font-size: 8px; text-transform: uppercase; }
        .items-table td { height: 18px; padding: 3px; vertical-align: top; }
        .signature-table { margin-top: 9px; page-break-inside: avoid; }
        .signature-heading { padding: 2px; background: #f0f0f0; font-size: 8px; font-weight: bold; text-align: center; text-transform: uppercase; }
        .signature-cell { height: 58px; vertical-align: bottom; }
        .signature-mark { height: 17px; text-align: center; }
        .signature-name { border-top: 1px solid #000; padding-top: 2px; font-weight: bold; text-align: center; text-transform: uppercase; }
        .signature-role { margin-top: 1px; font-size: 7px; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -17px; left: 0; font-size: 7px; }
        .footer-left { float: left; font-weight: bold; }
        .footer-right { float: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $isMajor = $request->request_type === 'major';
        $documentId = $isMajor ? 'QR-HTSM-040' : 'QR-HTSM-041';
        $statusLabel = strtoupper(str_replace('_', ' ', $request->status));
        $submittedAt = $request->submitted_at ?? $request->created_at;
        $signedLogs = $request->logs
            ->filter(fn ($log) => $log->digitalSignature !== null)
            ->sortBy('created_at')
            ->keyBy('role');

        $signature = function (string $role) use ($signedLogs): array {
            $log = $signedLogs->get($role);
            $digitalSignature = $log?->digitalSignature;

            return [
                'name' => $digitalSignature?->signer_name,
                'signed_at' => $digitalSignature?->signed_at,
            ];
        };

        $departmentHead = $signature('dept_head');
        $vicePresident = $signature($isMajor ? 'vp_admin' : 'vp_finance');
        $provost = $signature('provost');
        $president = $signature('president');
        $smo = $signature('smo');
        $maximumFormRows = $isMajor ? 9 : 14;
        $remainingRows = max(0, $maximumFormRows - $request->items->count());
    @endphp

    <div class="footer">
        <span class="footer-left">Document ID: {{ $documentId }}</span>
        <span class="footer-right">{{ $request->tracking_code }} | Page <span class="page-number"></span></span>
    </div>

    <table class="header-table">
        <tr>
            <td style="width: 13%;">
                @if(file_exists(public_path('images/android-chrome-512x5122.png')))
                    <img src="{{ public_path('images/android-chrome-512x5122.png') }}" width="62" alt="HTC logo">
                @endif
            </td>
            <td class="text-center" style="width: 59%;">
                <div class="school-name">HOLY TRINITY COLLEGE OF GENERAL SANTOS CITY</div>
                <div class="office-name">Supply Management Office</div>
                <div class="form-title">SUPPLY AND EQUIPMENT {{ $isMajor ? 'REQUEST' : 'MINOR REQUEST' }} FORM</div>
                <div class="form-subtitle">(For requests {{ $isMajor ? 'more than PHP 1,000.00' : 'not more than PHP 1,000.00' }})</div>
            </td>
            <td style="width: 28%; padding-left: 8px;">
                <table class="control-box">
                    <tr><td>Control No.</td><td class="control-value">{{ $request->tracking_code }}</td></tr>
                    <tr><td>Submitted</td><td class="control-value">{{ $submittedAt->format('m/d/Y h:i A') }}</td></tr>
                    <tr><td>Deadline</td><td class="control-value">{{ $request->due_at?->format('m/d/Y h:i A') ?? '—' }}</td></tr>
                    <tr><td>Status</td><td class="control-value">{{ $statusLabel }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 9px;">
        <tr>
            <td class="info-label">Signature:</td>
            <td class="info-value"><span class="digital-stamp">SYSTEM-AUTHENTICATED REQUEST</span></td>
            <td class="info-label">Signature:</td>
            <td class="info-value">
                @if($departmentHead['name'])
                    <span class="digital-stamp">DIGITALLY SIGNED {{ $departmentHead['signed_at']?->format('m/d/Y') }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">Requested by:</td>
            <td class="info-value">{{ $request->user?->name ?? 'Unknown requestor' }}</td>
            <td class="info-label">Department Head:</td>
            <td class="info-value">{{ $departmentHead['name'] ?? 'Pending approval' }}</td>
        </tr>
        <tr>
            <td class="info-label">Department:</td>
            <td class="info-value">{{ $request->user?->department ?: 'Unassigned' }}</td>
            <td class="info-label">Date:</td>
            <td class="info-value">{{ $departmentHead['signed_at']?->format('m/d/Y') ?? '—' }}</td>
        </tr>
    </table>

    <table style="margin-top: -1px;">
        <tr>
            <td class="info-label" style="height: 34px;">Purpose:</td>
            <td style="vertical-align: top;">{{ $request->purpose ?: ($request->remarks ?: 'Office/departmental use') }}</td>
            <td class="text-center" style="width: 27%;">
                <div style="font-size: 8px;">Request:</div>
                <div style="margin-top: 3px; font-size: 8px;">[ X ] Supply&nbsp;&nbsp;&nbsp;[ ] Monetary</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 16%;">Item</th>
                <th style="width: 34%;">Specifications</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 9%;">Unit</th>
                <th style="width: 13%;">Unit Price</th>
                <th style="width: 15%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($request->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td>{{ $item->specifications ?: '—' }}</td>
                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                    <td class="text-center">{{ $item->unit ?: '—' }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No requested items recorded.</td></tr>
            @endforelse

            @for($row = 0; $row < $remainingRows; $row++)
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            @endfor

            <tr class="fw-bold">
                <td colspan="6" class="text-right">TOTAL:</td>
                <td class="text-right">PHP {{ number_format((float) $request->grand_total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($isMajor)
        <table class="signature-table">
            <tr><td class="signature-heading">Processed by</td><td class="signature-heading">Recommending Approval</td></tr>
            <tr>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $smo['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $smo['name'] ?? ' ' }}</div>
                    <div class="signature-role">SMO In-charge</div>
                </td>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $provost['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $provost['name'] ?? ' ' }}</div>
                    <div class="signature-role">Provost</div>
                </td>
            </tr>
            <tr><td class="signature-heading">Noted by</td><td class="signature-heading">Approved by</td></tr>
            <tr>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $vicePresident['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $vicePresident['name'] ?? ' ' }}</div>
                    <div class="signature-role">VP for Administration</div>
                </td>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $president['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $president['name'] ?? ' ' }}</div>
                    <div class="signature-role">President</div>
                </td>
            </tr>
        </table>
    @else
        <table class="signature-table">
            <tr><td class="signature-heading">Processed by</td><td class="signature-heading">Approved by</td></tr>
            <tr>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $smo['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $smo['name'] ?? ' ' }}</div>
                    <div class="signature-role">SMO In-charge</div>
                </td>
                <td class="signature-cell">
                    <div class="signature-mark"><span class="digital-stamp">{{ $vicePresident['name'] ? 'DIGITALLY SIGNED' : '' }}</span></div>
                    <div class="signature-name">{{ $vicePresident['name'] ?? ' ' }}</div>
                    <div class="signature-role">VP for Finance</div>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
