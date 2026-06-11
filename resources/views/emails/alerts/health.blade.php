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
            background: {{ $status === 'unhealthy' ? '#dc3545' : '#ffc107' }};
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
        .check-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #dee2e6;
        }
        .check-item.healthy {
            border-left-color: #28a745;
        }
        .check-item.degraded {
            border-left-color: #ffc107;
        }
        .check-item.unhealthy {
            border-left-color: #dc3545;
        }
        .check-name {
            font-weight: bold;
            text-transform: capitalize;
            margin-bottom: 5px;
        }
        .check-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-healthy {
            background: #d4edda;
            color: #155724;
        }
        .status-degraded {
            background: #fff3cd;
            color: #856404;
        }
        .status-unhealthy {
            background: #f8d7da;
            color: #721c24;
        }
        .timestamp {
            color: #6c757d;
            font-size: 14px;
            margin-top: 20px;
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
        <h1>{{ $status === 'unhealthy' ? '🔴 System Health Critical' : '⚠️ System Health Degraded' }}</h1>
    </div>

    <div class="content">
        <p>The system health check has detected issues that require attention.</p>

        <h2>Health Check Results</h2>

        @foreach ($health['checks'] ?? [] as $checkName => $checkResult)
            @php
                $checkStatus = $checkResult['status'] ?? 'unknown';
            @endphp
            <div class="check-item {{ $checkStatus }}">
                <div class="check-name">{{ ucfirst($checkName) }}</div>
                <div>
                    <span class="check-status status-{{ $checkStatus }}">
                        {{ $checkStatus }}
                    </span>
                </div>
                @if (isset($checkResult['message']))
                    <div style="margin-top: 10px; font-size: 14px; color: #6c757d;">
                        {{ $checkResult['message'] }}
                    </div>
                @endif
                @if (isset($checkResult['details']))
                    <div style="margin-top: 10px; font-size: 14px;">
                        @foreach ($checkResult['details'] as $key => $value)
                            <div><strong>{{ ucfirst($key) }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <div class="timestamp">
            <strong>Timestamp:</strong> {{ $health['timestamp'] ?? 'Unknown' }}
        </div>

        <div class="footer">
            <p>This is an automated alert from {{ config('app.name') }}.</p>
            <p>Please investigate and resolve any issues as soon as possible.</p>
        </div>
    </div>
</body>
</html>
