<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .info-box {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }
        .info-row {
            margin: 10px 0;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 150px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔴 Microservice Auto-Suspended</h1>
    </div>

    <div class="content">
        <p>A microservice subscription has been automatically suspended due to expiration.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Tenant ID:</span>
                <span>{{ $tenantId }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Microservice:</span>
                <span>{{ $subscription['microservice_id'] ?? 'Unknown' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Expired At:</span>
                <span>{{ $subscription['expires_at'] ?? 'Unknown' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span style="color: #dc3545; font-weight: bold;">Suspended</span>
            </div>
        </div>

        <p><strong>Impact:</strong></p>
        <ul>
            <li>The tenant can no longer use this microservice</li>
            <li>API calls will return errors</li>
            <li>Tenant has been notified about the suspension</li>
        </ul>

        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Contact the tenant to discuss renewal</li>
            <li>Reactivate the subscription once payment is received</li>
        </ul>

        <div class="footer">
            <p>This is an automated alert from {{ config('app.name') }}.</p>
        </div>
    </div>
</body>
</html>
