<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificare Bilet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
        }
        .badge {
            padding: 32px 24px;
            text-align: center;
        }
        .badge-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .badge-text {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .badge-valid { background: #d1fae5; color: #065f46; }
        .badge-used { background: #fef3c7; color: #92400e; }
        .badge-cancelled, .badge-refunded, .badge-invalid { background: #fee2e2; color: #991b1b; }
        .badge-not_found { background: #f3f4f6; color: #6b7280; }
        .details {
            padding: 24px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; }
        .detail-value { color: #1f2937; font-weight: 500; text-align: right; }
        .footer {
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($status === 'valid')
            <div class="badge badge-valid">
                <div class="badge-icon">&#10003;</div>
                <div class="badge-text">Bilet Valid</div>
            </div>
        @elseif($status === 'used')
            <div class="badge badge-used">
                <div class="badge-icon">&#9888;</div>
                <div class="badge-text">Bilet Folosit</div>
            </div>
        @elseif($status === 'cancelled' || $status === 'refunded')
            <div class="badge badge-cancelled">
                <div class="badge-icon">&#10007;</div>
                <div class="badge-text">Bilet Anulat</div>
            </div>
        @elseif($status === 'not_found')
            <div class="badge badge-not_found">
                <div class="badge-icon">?</div>
                <div class="badge-text">Bilet Negasit</div>
            </div>
        @else
            <div class="badge badge-invalid">
                <div class="badge-icon">&#10007;</div>
                <div class="badge-text">Bilet Invalid</div>
            </div>
        @endif

        @if($status !== 'not_found')
        <div class="details">
            @if(!empty($eventTitle))
            <div class="detail-row">
                <span class="detail-label">Eveniment</span>
                <span class="detail-value">{{ $eventTitle }}</span>
            </div>
            @endif

            @if(!empty($eventDate))
            <div class="detail-row">
                <span class="detail-label">Data</span>
                <span class="detail-value">{{ $eventDate->format('d.m.Y') }}</span>
            </div>
            @endif

            @if(!empty($ticketType))
            <div class="detail-row">
                <span class="detail-label">Tip bilet</span>
                <span class="detail-value">{{ $ticketType }}</span>
            </div>
            @endif

            @if(!empty($seatLabel))
            <div class="detail-row">
                <span class="detail-label">Loc</span>
                <span class="detail-value">{{ $seatLabel }}</span>
            </div>
            @endif

            @if($status === 'used' && !empty($checkedInAt))
            <div class="detail-row">
                <span class="detail-label">Check-in</span>
                <span class="detail-value">{{ $checkedInAt->format('d.m.Y H:i') }}</span>
            </div>
            @endif

            @if(!empty($code))
            <div class="detail-row">
                <span class="detail-label">Cod</span>
                <span class="detail-value" style="font-family: monospace; letter-spacing: 1px;">{{ $code }}</span>
            </div>
            @endif
        </div>
        @endif

        <div class="footer">
            @if(!empty($marketplaceName))
                Verificat pe {{ $marketplaceName }}
            @else
                Powered by Tixello
            @endif
        </div>
    </div>
</body>
</html>
