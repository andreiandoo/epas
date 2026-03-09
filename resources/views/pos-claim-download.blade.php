<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Biletele tale - {{ $claim->event_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f3ff;
            color: #1f2937;
            min-height: 100vh;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: #fff;
            padding: 24px 20px;
            text-align: center;
        }
        .card-header h1 { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
        .card-header .event-info { font-size: 13px; opacity: 0.9; margin-top: 8px; line-height: 1.4; }
        .card-body { padding: 24px 20px; }
        .ticket-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
        }
        .ticket-type {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .ticket-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: 700;
            color: #7c3aed;
            letter-spacing: 1px;
            text-align: center;
            padding: 12px;
            background: #f5f3ff;
            border-radius: 8px;
        }
        .ticket-note {
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
            margin-top: 6px;
        }
        .order-info {
            font-size: 13px;
            color: #6b7280;
            text-align: center;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 14px;
            margin-top: 20px;
            text-align: center;
        }
        .info-box p {
            font-size: 13px;
            color: #166534;
            line-height: 1.5;
        }
        .info-box strong { color: #15803d; }
        .screenshot-hint {
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
            margin-top: 16px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h1>🎫 Biletele tale</h1>
        <div class="event-info">
            <strong>{{ $claim->event_name }}</strong>
            @if($claim->event_date)
                <br>{{ $claim->event_date }}
            @endif
            @if($claim->venue_name)
                — {{ $claim->venue_name }}
            @endif
        </div>
    </div>

    <div class="card-body">
        <div class="order-info">
            Comanda: <strong>{{ $order->order_number }}</strong>
        </div>

        @foreach($order->tickets as $ticket)
            <div class="ticket-card">
                <div class="ticket-type">{{ $ticket->ticketType?->name ?? 'Bilet' }}</div>
                <div class="ticket-code">{{ $ticket->code }}</div>
                <div class="ticket-note">Prezintă acest cod la intrare</div>
            </div>
        @endforeach

        <div class="info-box">
            <p>Salvează această pagină sau fă un screenshot.<br>Prezintă codurile la intrare.</p>
        </div>

        <p class="screenshot-hint">Poți reveni oricând la această pagină accesând același link.</p>
    </div>
</div>

</body>
</html>
