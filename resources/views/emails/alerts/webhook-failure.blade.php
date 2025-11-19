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
        .code {
            background: #f1f3f5;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
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
        <h1>🔴 Webhook Delivery Failed</h1>
    </div>

    <div class="content">
        <p>A webhook delivery has failed after exhausting all retry attempts.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Tenant ID:</span>
                <span>{{ $tenantId }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Event Type:</span>
                <span>{{ $webhookDelivery['event_type'] ?? 'Unknown' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Webhook URL:</span>
                <span>{{ $webhookDelivery['webhook_url'] ?? 'Unknown' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Attempts:</span>
                <span>{{ $webhookDelivery['attempts'] ?? 0 }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Last HTTP Status:</span>
                <span>{{ $webhookDelivery['last_http_status'] ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Created At:</span>
                <span>{{ $webhookDelivery['created_at'] ?? 'Unknown' }}</span>
            </div>
        </div>

        @if (isset($webhookDelivery['last_error']))
        <div>
            <strong>Last Error:</strong>
            <div class="code">{{ $webhookDelivery['last_error'] }}</div>
        </div>
        @endif

        @if (isset($webhookDelivery['payload']))
        <div style="margin-top: 15px;">
            <strong>Payload:</strong>
            <div class="code">{{ is_string($webhookDelivery['payload']) ? $webhookDelivery['payload'] : json_encode($webhookDelivery['payload'], JSON_PRETTY_PRINT) }}</div>
        </div>
        @endif

        <div style="margin-top: 20px;">
            <p><strong>Action Required:</strong></p>
            <ul>
                <li>Verify the webhook URL is correct and accessible</li>
                <li>Check if the tenant's server is experiencing issues</li>
                <li>Contact the tenant to resolve the delivery issue</li>
                <li>Consider manual retry or updating the webhook configuration</li>
            </ul>
        </div>

        <div class="footer">
            <p>This is an automated alert from {{ config('app.name') }}.</p>
        </div>
    </div>
</body>
</html>
