<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invitation - {{ $invite->invite_code }}</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            color: #ffffff;
            min-height: 100%;
            padding: 40px;
        }

        .invitation-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .watermark {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #a78bfa;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .event-title {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .event-subtitle {
            font-size: 16px;
            color: #c4b5fd;
            margin-bottom: 30px;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #a78bfa, transparent);
            margin: 30px 0;
        }

        .recipient-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .you-are-invited {
            font-size: 14px;
            color: #a78bfa;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 15px;
        }

        .recipient-name {
            font-size: 28px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .recipient-company {
            font-size: 16px;
            color: #c4b5fd;
        }

        .details-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .detail-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-icon {
            display: table-cell;
            width: 30px;
            vertical-align: middle;
            color: #a78bfa;
            font-size: 16px;
        }

        .detail-content {
            display: table-cell;
            vertical-align: middle;
        }

        .detail-label {
            font-size: 11px;
            color: #a78bfa;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 16px;
            color: #ffffff;
        }

        @if($invite->seat_ref)
        .seat-section {
            text-align: center;
            background: linear-gradient(135deg, #7c3aed, #a78bfa);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .seat-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .seat-value {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        @endif

        .qr-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .qr-code {
            width: 180px;
            height: 180px;
            margin: 0 auto 15px;
            background: #ffffff;
            padding: 10px;
            border-radius: 10px;
        }

        .qr-code img {
            width: 100%;
            height: 100%;
        }

        .invite-code {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #a78bfa;
            letter-spacing: 2px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #8b5cf6;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer p {
            margin-bottom: 5px;
        }

        .note {
            font-size: 11px;
            color: #a78bfa;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="invitation-container">
        <div class="header">
            <div class="watermark">{{ $watermark }}</div>
            <h1 class="event-title">{{ $eventTitle }}</h1>
            @if($eventSubtitle)
                <p class="event-subtitle">{{ $eventSubtitle }}</p>
            @endif
        </div>

        <div class="divider"></div>

        <div class="recipient-section">
            <p class="you-are-invited">You Are Cordially Invited</p>
            <h2 class="recipient-name">{{ $invite->getRecipientName() }}</h2>
            @if($invite->getRecipientCompany())
                <p class="recipient-company">{{ $invite->getRecipientCompany() }}</p>
            @endif
        </div>

        <div class="details-section">
            <div class="detail-row">
                <div class="detail-icon">📅</div>
                <div class="detail-content">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">{{ $eventDate }}</div>
                </div>
            </div>

            @if($eventTime)
            <div class="detail-row">
                <div class="detail-icon">🕐</div>
                <div class="detail-content">
                    <div class="detail-label">Time</div>
                    <div class="detail-value">{{ $eventTime }}</div>
                </div>
            </div>
            @endif

            @if($venueName)
            <div class="detail-row">
                <div class="detail-icon">📍</div>
                <div class="detail-content">
                    <div class="detail-label">Venue</div>
                    <div class="detail-value">{{ $venueName }}</div>
                </div>
            </div>
            @endif
        </div>

        @if($invite->seat_ref)
        <div class="seat-section">
            <div class="seat-label">Your Assigned Seat</div>
            <div class="seat-value">{{ $invite->seat_ref }}</div>
        </div>
        @endif

        <div class="qr-section">
            <div class="qr-code">
                <img src="data:image/png;base64,{{ $qrCode }}" alt="QR Code">
            </div>
            <p class="invite-code">{{ $invite->invite_code }}</p>
        </div>

        <div class="footer">
            <p>Please present this invitation at the entrance</p>
            <p class="note">This invitation is personal and non-transferable</p>
        </div>
    </div>
</body>
</html>
