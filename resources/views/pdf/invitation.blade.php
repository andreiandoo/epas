<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invitation - {{ $invite->invite_code }}</title>
    <style>
        @page {
            margin: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: DejaVu Sans, Arial, sans-serif;
        }

        body {
            background-color: #ffffff;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.4;
        }

        .invitation-card {
            border: 3px solid #4f46e5;
            border-radius: 8px;
            padding: 30px;
            max-width: 550px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .watermark {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 4px;
            color: #4f46e5;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .event-title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .event-subtitle {
            font-size: 14px;
            color: #6b7280;
        }

        /* Recipient Section */
        .recipient-section {
            text-align: center;
            background-color: #f3f4f6;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .invited-label {
            font-size: 10px;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .recipient-name {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .recipient-company {
            font-size: 12px;
            color: #6b7280;
        }

        /* Details Table */
        .details-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .details-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .details-table .label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 80px;
        }

        .details-table .value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 500;
        }

        /* Seat Section */
        .seat-section {
            text-align: center;
            background-color: #4f46e5;
            color: #ffffff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .seat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .seat-value {
            font-size: 22px;
            font-weight: bold;
        }

        /* Value Section */
        .value-section {
            text-align: center;
            margin-bottom: 20px;
        }

        .value-badge {
            display: inline-block;
            background-color: #10b981;
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        /* QR Section */
        .qr-section {
            text-align: center;
            margin-bottom: 20px;
        }

        .qr-container {
            display: inline-block;
            background-color: #ffffff;
            border: 2px solid #e5e7eb;
            padding: 10px;
            border-radius: 8px;
        }

        .qr-code {
            width: 120px;
            height: 120px;
        }

        .invite-code {
            font-family: DejaVu Sans Mono, Courier New, monospace;
            font-size: 12px;
            color: #4f46e5;
            letter-spacing: 1px;
            margin-top: 10px;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            text-align: center;
            border-top: 2px solid #e5e7eb;
            padding-top: 15px;
        }

        .footer-main {
            font-size: 11px;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .footer-note {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
            margin-bottom: 10px;
        }

        .powered-by {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 10px;
        }

        .powered-by a {
            color: #4f46e5;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="invitation-card">
        <!-- Header -->
        <div class="header">
            <div class="watermark">{{ $watermark }}</div>
            <h1 class="event-title">{{ $eventTitle }}</h1>
            @if($eventSubtitle)
                <p class="event-subtitle">{{ $eventSubtitle }}</p>
            @endif
        </div>

        <!-- Recipient -->
        <div class="recipient-section">
            <div class="invited-label">You Are Cordially Invited</div>
            <div class="recipient-name">{{ $invite->getRecipientName() }}</div>
            @if($invite->getRecipientCompany())
                <div class="recipient-company">{{ $invite->getRecipientCompany() }}</div>
            @endif
        </div>

        <!-- Event Details -->
        <table class="details-table">
            <tr>
                <td class="label">Date</td>
                <td class="value">{{ $eventDate }}</td>
            </tr>
            @if($eventTime)
            <tr>
                <td class="label">Time</td>
                <td class="value">{{ $eventTime }}</td>
            </tr>
            @endif
            @if($venueName)
            <tr>
                <td class="label">Venue</td>
                <td class="value">{{ $venueName }}</td>
            </tr>
            @endif
        </table>

        <!-- Seat (if assigned) -->
        @if($invite->seat_ref)
        <div class="seat-section">
            <div class="seat-label">Your Assigned Seat</div>
            <div class="seat-value">{{ $invite->seat_ref }}</div>
        </div>
        @endif

        <!-- Value Badge -->
        <div class="value-section">
            <span class="value-badge">COMPLIMENTARY - Value: 0 RON</span>
        </div>

        <!-- QR Code -->
        <div class="qr-section">
            <div class="qr-container">
                @if($qrCode)
                    <img class="qr-code" src="data:image/png;base64,{{ $qrCode }}" alt="QR Code">
                @else
                    <div class="qr-code" style="background-color: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                        <span style="color: #6b7280; font-size: 10px;">QR</span>
                    </div>
                @endif
            </div>
            <div class="invite-code">{{ $invite->invite_code }}</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-main">Please present this invitation at the entrance</p>
            <p class="footer-note">This invitation is personal and non-transferable</p>
            <p class="powered-by">Generated by <a href="https://tixello.com">Tixello.com</a></p>
        </div>
    </div>
</body>
</html>
