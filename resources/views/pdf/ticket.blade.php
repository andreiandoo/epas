<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $ticket->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
        }
        .ticket-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #181622;
        }
        .header h1 {
            font-size: 24px;
            color: #181622;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
            font-size: 11px;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .qr-code {
            width: 180px;
            height: 180px;
            margin: 0 auto 10px;
        }
        .ticket-code {
            font-family: monospace;
            font-size: 18px;
            font-weight: bold;
            color: #181622;
            letter-spacing: 2px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-valid { background: #d1fae5; color: #065f46; }
        .status-used { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 40%;
            padding: 5px 0;
            color: #6b7280;
            font-size: 11px;
        }
        .info-value {
            display: table-cell;
            width: 60%;
            padding: 5px 0;
            font-weight: 500;
        }
        .event-title {
            font-size: 16px;
            font-weight: bold;
            color: #181622;
            margin-bottom: 10px;
        }
        .event-details {
            color: #4b5563;
            font-size: 11px;
            line-height: 1.6;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
        .terms {
            margin-top: 20px;
            padding: 15px;
            background: #fefce8;
            border-radius: 8px;
            font-size: 10px;
            color: #713f12;
        }
        .terms-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="header">
            <h1>{{ $eventTitle }}</h1>
            <p>E-Ticket</p>
        </div>

        <div class="qr-section">
            <img src="{{ $qrCodeDataUri ?? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($ticket->code) . '&color=181622&margin=0' }}" alt="QR Code" class="qr-code">
            <div class="ticket-code">{{ $ticket->code }}</div>
            <span class="status-badge status-{{ $ticket->status }}">
                @if($ticket->status === 'valid') ✓ VALID
                @elseif($ticket->status === 'used') ✓ USED
                @elseif($ticket->status === 'cancelled') ✗ CANCELLED
                @else {{ strtoupper($ticket->status) }}
                @endif
            </span>
        </div>

        <div class="section">
            <div class="section-title">Event Details</div>
            <div class="event-title">{{ $eventTitle }}</div>
            <div class="event-details">
                @if($event)
                    📅 {{ $event->event_date ? $event->event_date->format('l, d F Y') : 'TBA' }}<br>
                    @if($event->start_time)
                        🕐 {{ $event->start_time }}<br>
                    @endif
                    @if($venue)
                        📍 {{ $venueName }}{{ $venue->city ? ', ' . $venue->city : '' }}<br>
                        @if($venue->address)
                            {{ $venue->address }}
                        @endif
                    @endif
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Ticket Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Ticket Type</div>
                    <div class="info-value">{{ $ticket->ticketType?->name ?? 'N/A' }}</div>
                </div>
                @if($ticket->seat_label)
                <div class="info-row">
                    <div class="info-label">Seat</div>
                    <div class="info-value">{{ $ticket->seat_label }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Price</div>
                    <div class="info-value">{{ number_format(($ticket->ticketType?->price_cents ?? 0) / 100, 2) }} {{ $ticket->ticketType?->currency ?? 'RON' }}</div>
                </div>
                @if($ticket->order)
                <div class="info-row">
                    <div class="info-label">Order #</div>
                    <div class="info-value">#{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}</div>
                </div>
                @endif
            </div>
        </div>

        @if($beneficiary)
        <div class="section">
            <div class="section-title">Ticket Holder</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Name</div>
                    <div class="info-value">{{ $beneficiary['name'] ?? 'N/A' }}</div>
                </div>
                @if($beneficiary['email'] ?? null)
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{ $beneficiary['email'] }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($ticketTerms)
        <div class="terms">
            <div class="terms-title">Terms & Conditions</div>
            {!! strip_tags($ticketTerms, '<br><p>') !!}
        </div>
        @endif

        <div class="footer">
            <p>This ticket was generated on {{ now()->format('d M Y, H:i') }}</p>
            <p>Present this QR code at the entrance for scanning</p>
            @if($tenant)
                <p style="margin-top: 10px;">{{ $tenant->public_name ?? $tenant->name }}</p>
            @endif
        </div>
    </div>
</body>
</html>
