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
            background: #7c3aed;
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
            color: #7c3aed;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }
        .ticket-label {
            font-size: 13px;
            font-weight: bold;
            color: #7c3aed;
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
            color: #7c3aed;
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
        .organizer-info {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
        }
        .organizer-info td {
            font-size: 9px;
        }
        .terms-section {
            margin-top: 8px;
            padding: 8px 10px;
            background: #fefce8;
            border-radius: 6px;
            font-size: 8px;
            color: #713f12;
            line-height: 1.4;
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
                    @if($eventDateFormatted){{ $eventDateFormatted }}@endif
                    @if($accessTime) &middot; Ora acces: {{ $accessTime }}@endif
                    @if($venueLocation)<br>{{ $venueLocation }}@endif
                </p>
            </div>

            <hr class="ticket-separator">

            <div class="ticket-body">
                <p class="ticket-label">Bilet acces</p>
                <p class="ticket-type">{{ $ticket->ticketType?->name ?? 'Bilet' }}</p>

                @php
                    $seatDetails = $ticket->getSeatDetails();
                @endphp
                @if($seatDetails)
                    <div class="seat-info">
                        @if($seatDetails['section_name']){{ $seatDetails['section_name'] }} &middot; @endif
                        @if($seatDetails['row_label'])Rand {{ $seatDetails['row_label'] }} &middot; @endif
                        @if($seatDetails['seat_number'])Loc {{ $seatDetails['seat_number'] }}@endif
                    </div>
                @elseif($ticket->seat_label)
                    <div class="seat-info">{{ $ticket->seat_label }}</div>
                @endif

                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($ticket->code) }}&color=7c3aed&margin=1" alt="QR" class="qr-code">
                </div>

                <div class="ticket-code-box">
                    <p class="ticket-code">{{ $ticket->code }}</p>
                </div>

                <div class="ticket-info">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Suma achitata:</td>
                            <td class="info-value">{{ number_format($ticket->price ?? (($ticket->ticketType?->price_cents ?? 0) / 100), 2, ',', '.') }} {{ $order->currency ?? 'RON' }}</td>
                        </tr>
                        @if($eventDateFormatted)
                        <tr>
                            <td class="info-label">Data eveniment:</td>
                            <td class="info-value">{{ $eventDateFormatted }}</td>
                        </tr>
                        @endif
                        @if($venueLocation)
                        <tr>
                            <td class="info-label">Locatie:</td>
                            <td class="info-value">{{ $venueLocation }}</td>
                        </tr>
                        @endif
                        @if($accessTime)
                        <tr>
                            <td class="info-label">Ora acces:</td>
                            <td class="info-value">{{ $accessTime }}</td>
                        </tr>
                        @endif
                    </table>

                    @if($organizerName || $organizerCui)
                    <div class="organizer-info">
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Organizator:</td>
                                <td class="info-value">{{ $organizerName ?? '-' }}</td>
                            </tr>
                            @if($organizerCui)
                            <tr>
                                <td class="info-label">CIF/CUI:</td>
                                <td class="info-value">{{ $organizerCui }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    @endif
                </div>

                <div class="terms-section">
                    Cumparatorul de bilet a fost de acord cu Termenii si Conditiile Evenimentului prezentate la intrarea in eveniment si pe pagina de prezentare a evenimentului.
                </div>

                <p class="ticket-instruction">Prezinta acest cod QR sau codul biletului la intrare</p>
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
