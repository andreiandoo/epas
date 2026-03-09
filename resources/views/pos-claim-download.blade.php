<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Bilete - {{ $claim->event_name }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f3ff;
            color: #1f2937;
        }
        .ticket {
            background: #ffffff;
            border: 2px solid #d1d5db;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .ticket-header {
            background: #7c3aed;
            color: #ffffff;
            padding: 18px 20px;
            text-align: center;
        }
        .ticket-header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 6px 0;
        }
        .ticket-header .event-details {
            font-size: 11px;
            opacity: 0.9;
            margin: 0;
            line-height: 1.5;
        }
        .ticket-separator {
            border: none;
            border-top: 2px dashed #d1d5db;
            margin: 0 16px;
        }
        .ticket-body {
            padding: 18px 20px;
            text-align: center;
        }
        .ticket-type {
            font-size: 13px;
            font-weight: bold;
            color: #6b7280;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ticket-code-box {
            background: #f5f3ff;
            border: 2px solid #e9e0ff;
            border-radius: 8px;
            padding: 14px 10px;
            margin: 0 auto 10px auto;
            max-width: 280px;
        }
        .ticket-code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 22px;
            font-weight: bold;
            color: #7c3aed;
            letter-spacing: 2px;
            margin: 0;
        }
        .ticket-instruction {
            font-size: 10px;
            color: #9ca3af;
            margin: 8px 0 0 0;
        }
        .ticket-footer {
            padding: 8px 20px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
        }
        .ticket-footer-text {
            font-size: 9px;
            color: #d1d5db;
            margin: 0;
        }
        .order-info {
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="order-info">
        Comanda: <strong>{{ $order->order_number }}</strong>
        @if($order->tickets->count() > 1)
            &nbsp;&middot;&nbsp; {{ $order->tickets->count() }} bilete
        @endif
    </div>

    @foreach($order->tickets as $index => $ticket)
        <div class="ticket">
            <div class="ticket-header">
                <h1>{{ $claim->event_name }}</h1>
                <p class="event-details">
                    @if($claim->event_date){{ $claim->event_date }}@endif
                    @if($claim->venue_name) &mdash; {{ $claim->venue_name }}@endif
                </p>
            </div>

            <hr class="ticket-separator">

            <div class="ticket-body">
                <p class="ticket-type">{{ $ticket->ticketType?->name ?? 'Bilet' }}</p>

                <div class="ticket-code-box">
                    <p class="ticket-code">{{ $ticket->code }}</p>
                </div>

                <p class="ticket-instruction">Prezinta acest cod la intrare</p>
            </div>

            <div class="ticket-footer">
                <p class="ticket-footer-text">
                    @if($order->tickets->count() > 1)
                        Bilet {{ $index + 1 }} din {{ $order->tickets->count() }} &nbsp;&middot;&nbsp;
                    @endif
                    ambilet.ro
                </p>
            </div>
        </div>
    @endforeach
</body>
</html>
