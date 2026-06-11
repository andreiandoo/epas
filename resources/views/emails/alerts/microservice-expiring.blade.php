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
            background: #ffc107;
            color: #333;
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
            border-left: 4px solid #ffc107;
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
        <h1>⚠️ Microservice Subscription Expiring Soon</h1>
    </div>

    <div class="content">
        <p>A microservice subscription is expiring soon and requires attention.</p>

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
                <span class="info-label">Expires At:</span>
                <span>{{ $subscription['expires_at'] ?? 'Unknown' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span>{{ $subscription['status'] ?? 'Unknown' }}</span>
            </div>
        </div>

        <p><strong>Action Required:</strong></p>
        <ul>
            <li>Contact the tenant to renew their subscription</li>
            <li>If not renewed, the microservice will be automatically suspended after expiration</li>
        </ul>

        <div class="footer">
            <p>This is an automated alert from {{ config('app.name') }}.</p>
        </div>
    </div>
</body>
</html>
