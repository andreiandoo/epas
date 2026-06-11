<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px 20px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .priority-urgent {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .priority-high {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .priority-medium {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .priority-low {
            background-color: #E5E7EB;
            color: #374151;
        }
        .action-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            font-weight: 600;
        }
        .action-button:hover {
            background-color: #4338CA;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">{{ $notification->title }}</h1>
        <span class="priority-badge priority-{{ $notification->priority }}">
            {{ strtoupper($notification->priority) }} Priority
        </span>
    </div>

    <div class="content">
        <p style="font-size: 16px; margin-bottom: 20px;">
            {{ $notification->message }}
        </p>

        @if($notification->action_url && $notification->action_text)
            <a href="{{ $notification->action_url }}" class="action-button">
                {{ $notification->action_text }}
            </a>
        @endif

        @if($notification->data)
            <div style="margin-top: 30px; padding: 15px; background-color: white; border-radius: 6px;">
                <h3 style="margin-top: 0; font-size: 14px; color: #6b7280;">Additional Details:</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($notification->data as $key => $value)
                        <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>
            This is an automated notification from your EPAS system.<br>
            Notification ID: {{ $notification->id }}<br>
            Sent at: {{ $notification->created_at->format('d M Y H:i:s') }}
        </p>
        <p>
            If you believe this notification was sent in error, please contact support.
        </p>
    </div>
</body>
</html>
