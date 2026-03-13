<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Bilete - {{ $eventName }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 15px;
            background: #f5f3ff;
            color: #1f2937;
        }
        .ticket {
            background: #ffffff;
            border: 2px solid #d1d5db;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .ticket-header {
            background: {{ $primaryColor ?? '#1a1a2e' }};
            color: #ffffff;
            padding: 14px 16px;
            text-align: center;
        }
        .ticket-header h1 {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }
        .ticket-header .event-details {
            font-size: 10px;
            opacity: 0.9;
            margin: 0;
            line-height: 1.5;
        }
        .ticket-separator {
            border: none;
            border-top: 2px dashed #d1d5db;
            margin: 0 12px;
        }
        .ticket-body {
            padding: 12px 16px;
        }
        .ticket-type {
            font-size: 12px;
            font-weight: bold;
            color: {{ $primaryColor ?? '#1a1a2e' }};
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }
        .ticket-label {
            font-size: 13px;
            font-weight: bold;
            color: {{ $primaryColor ?? '#1a1a2e' }};
            text-align: center;
            margin: 0 0 10px 0;
            letter-spacing: 0.5px;
        }
        .qr-section {
            text-align: center;
            margin: 8px 0;
        }
        .qr-code {
            width: 120px;
            height: 120px;
        }
        .ticket-code-box {
            background: #f5f3ff;
            border: 2px solid #e9e0ff;
            border-radius: 8px;
            padding: 8px 10px;
            margin: 8px auto;
            max-width: 240px;
            text-align: center;
        }
        .ticket-code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 16px;
            font-weight: bold;
            color: {{ $primaryColor ?? '#1a1a2e' }};
            letter-spacing: 2px;
            margin: 0;
        }
        .ticket-info {
            margin: 10px 0;
            padding: 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 0;
            font-size: 10px;
            vertical-align: top;
        }
        .info-label {
            color: #6b7280;
            width: 35%;
            font-weight: normal;
        }
        .info-value {
            color: #1f2937;
            font-weight: 600;
        }
        .ticket-instruction {
            font-size: 9px;
            color: #9ca3af;
            margin: 6px 0 0 0;
            text-align: center;
        }
        .seat-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 6px 10px;
            margin: 6px 0;
            text-align: center;
            font-size: 10px;
            color: #166534;
            font-weight: 600;
        }
        .ticket-footer {
            padding: 6px 16px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
        }
        .ticket-footer-text {
            font-size: 8px;
            color: #d1d5db;
            margin: 0;
        }
        .order-info {
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="order-info">
        Comanda: <strong>{{ $order->order_number }}</strong>
        @if($tickets->count() > 1)
            &nbsp;&middot;&nbsp; {{ $tickets->count() }} bilete
        @endif
    </div>

    @foreach($tickets as $index => $ticket)
        @php
            $event = $ticket->marketplaceEvent;
            $eventTitle = $event->name ?? $eventName ?? 'Eveniment';
            $eventDate = $event?->starts_at?->format('d.m.Y') ?? '';
            $eventTime = $event?->starts_at?->format('H:i') ?? '';
            $doorsOpen = $event?->doors_open_at?->format('H:i') ?? '';
            $venue = implode(', ', array_filter([$event->venue_name ?? '', $event->venue_city ?? '']));
            $ticketCode = $ticket->code ?? $ticket->barcode ?? '';
            $attendeeName = $ticket->attendee_name ?? $order->customer_name ?? '';
            $ticketTypeName = $ticket->marketplaceTicketType?->name ?? 'Bilet';
            $ticketPrice = number_format($ticket->price ?? 0, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');
            $seatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
            $verifyUrl = method_exists($ticket, 'getVerifyUrl') ? $ticket->getVerifyUrl() : $ticketCode;
        @endphp

        <div class="ticket">
            <div class="ticket-header">
                <h1>{{ $eventTitle }}</h1>
                <p class="event-details">
                    @if($eventDate){{ $eventDate }}@endif
                    @if($doorsOpen) &middot; Deschidere porți: {{ $doorsOpen }}@endif
                    @if($eventTime) &middot; Ora: {{ $eventTime }}@endif
                    @if($venue)<br>{{ $venue }}@endif
                </p>
            </div>

            <hr class="ticket-separator">

            <div class="ticket-body">
                <p class="ticket-label">Bilet acces</p>
                <p class="ticket-type">{{ $ticketTypeName }}</p>

                @if($seatDetails)
                    <div class="seat-info">
                        @if(!empty($seatDetails['section_name'])){{ $seatDetails['section_name'] }} &middot; @endif
                        @if(!empty($seatDetails['row_label']))Rând {{ $seatDetails['row_label'] }} &middot; @endif
                        @if(!empty($seatDetails['seat_number']))Loc {{ $seatDetails['seat_number'] }}@endif
                    </div>
                @elseif($ticket->seat_label)
                    <div class="seat-info">{{ $ticket->seat_label }}</div>
                @endif

                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verifyUrl) }}&color=1a1a2e&margin=1" alt="QR" class="qr-code">
                </div>

                <div class="ticket-code-box">
                    <p class="ticket-code">{{ $ticketCode }}</p>
                </div>

                <div class="ticket-info">
                    <table class="info-table">
                        @if($attendeeName)
                        <tr>
                            <td class="info-label">Beneficiar:</td>
                            <td class="info-value">{{ $attendeeName }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="info-label">Suma achitată:</td>
                            <td class="info-value">{{ $ticketPrice }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Încasare:</td>
                            <td class="info-value">Online</td>
                        </tr>
                    </table>
                </div>

                <p class="ticket-instruction">Prezintă acest cod QR sau codul biletului la intrare</p>
            </div>

            <div class="ticket-footer">
                <p class="ticket-footer-text">
                    @if($tickets->count() > 1)
                        Bilet {{ $index + 1 }} din {{ $tickets->count() }} &nbsp;&middot;&nbsp;
                    @endif
                    {{ $marketplaceName }}
                </p>
            </div>
        </div>
    @endforeach
</body>
</html>
