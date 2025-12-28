<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Invitation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .event-details {
            background-color: #f9fafb;
            border-left: 4px solid #4F46E5;
            padding: 20px;
            margin: 20px 0;
        }
        .event-details h2 {
            margin-top: 0;
            color: #4F46E5;
        }
        .detail-row {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
        }
        .detail-label {
            font-weight: 600;
            color: #6b7280;
        }
        .detail-value {
            color: #111827;
        }
        .download-button {
            display: inline-block;
            padding: 15px 30px;
            background-color: #4F46E5;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            text-align: center;
        }
        .download-button:hover {
            background-color: #4338CA;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .invitation-code {
            background-color: #FEF3C7;
            padding: 10px 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 16px;
            text-align: center;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>You're Invited!</h1>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {{ $name }},
            </div>

            <p>
                We're delighted to invite you to <strong>{{ $event->name ?? 'our event' }}</strong>.
                {{ $batch->name ?? '' }}
            </p>

            @if($event)
                <div class="event-details">
                    <h2>Event Details</h2>
                    
                    @if($event->starts_at)
                        <div class="detail-row">
                            <span class="detail-label">Date & Time:</span>
                            <span class="detail-value">{{ \Carbon\Carbon::parse($event->starts_at)->format('l, F j, Y \a\t g:i A') }}</span>
                        </div>
                    @endif

                    @if($event->venue_name)
                        <div class="detail-row">
                            <span class="detail-label">Venue:</span>
                            <span class="detail-value">{{ $event->venue_name }}</span>
                        </div>
                    @endif

                    @if(!empty($recipientData['seat_ref']))
                        <div class="detail-row">
                            <span class="detail-label">Seat:</span>
                            <span class="detail-value">{{ $recipientData['seat_ref'] }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <p>
                Please download your invitation using the button below. 
                You'll need to present this invitation (either printed or on your mobile device) at the entrance.
            </p>

            <div style="text-align: center;">
                <a href="{{ $downloadUrl }}" class="download-button">
                    Download Your Invitation
                </a>
            </div>

            <div class="invitation-code">
                <strong>Invitation Code:</strong> {{ $invitation->invite_code }}
            </div>

            <p style="font-size: 14px; color: #6b7280;">
                <strong>Note:</strong> This is a personal invitation and cannot be transferred. 
                Please bring a valid ID to the event.
            </p>

            @if(!empty($tenantSettings['footer_text']))
                <p style="margin-top: 30px;">
                    {{ $tenantSettings['footer_text'] }}
                </p>
            @endif
        </div>

        <div class="footer">
            <p>
                This invitation was sent to {{ $recipientData['email'] ?? 'you' }}<br>
                If you have any questions, please contact the event organizer.
            </p>
            <p style="margin-top: 10px;">
                <small>Invitation ID: {{ $invitation->id }}</small>
            </p>
        </div>
    </div>

    <!-- Tracking pixel (GDPR compliant - only if consent given) -->
    @if(config('app.enable_email_tracking', false))
        <img src="{{ route('invitation.track-open', ['code' => $invitation->invite_code]) }}" width="1" height="1" alt="" style="display:none;" />
    @endif
</body>
</html>
