<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPAS System Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .overall-status {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .status-indicator {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .status-healthy {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .status-degraded {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .status-unhealthy {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .overall-status h2 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }

        .overall-status p {
            color: #666;
            font-size: 16px;
        }

        .timestamp {
            color: #999;
            font-size: 14px;
            margin-top: 10px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .service-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-healthy {
            background: #d4edda;
            color: #155724;
        }

        .badge-degraded {
            background: #fff3cd;
            color: #856404;
        }

        .badge-unhealthy {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-disabled {
            background: #e2e3e5;
            color: #6c757d;
        }

        .service-details {
            margin-top: 12px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            opacity: 0.8;
        }

        .refresh-button {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .refresh-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 32px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 EPAS System Status</h1>
            <p>Real-time monitoring of all services and infrastructure</p>
        </div>

        <div class="overall-status">
            <div class="status-indicator status-{{ $health['status'] }}">
                @if($health['status'] === 'healthy')
                    ✓
                @elseif($health['status'] === 'degraded')
                    ⚠
                @else
                    ✗
                @endif
            </div>
            <h2>
                @if($health['status'] === 'healthy')
                    All Systems Operational
                @elseif($health['status'] === 'degraded')
                    Degraded Performance
                @else
                    System Issues Detected
                @endif
            </h2>
            <p>
                @if($health['status'] === 'healthy')
                    All services are running smoothly
                @elseif($health['status'] === 'degraded')
                    Some services experiencing reduced performance
                @else
                    Critical issues affecting system availability
                @endif
            </p>
            <p class="timestamp">Last updated: {{ $health['timestamp'] }}</p>
            <button class="refresh-button" onclick="location.reload()">Refresh Status</button>
        </div>

        <div class="services-grid">
            @foreach($health['checks'] as $service => $check)
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-name">
                            {{ ucfirst($service) }}
                        </div>
                        <span class="status-badge badge-{{ $check['status'] }}">
                            {{ $check['status'] }}
                        </span>
                    </div>

                    @if(isset($check['error']))
                        <div class="error-message">
                            {{ $check['error'] }}
                        </div>
                    @else
                        <div class="service-details">
                            @foreach($check as $key => $value)
                                @if($key !== 'status' && $key !== 'error' && $key !== 'message')
                                    <div class="detail-row">
                                        <span class="detail-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                        <span class="detail-value">
                                            @if(is_bool($value))
                                                {{ $value ? 'Yes' : 'No' }}
                                            @elseif(is_numeric($value))
                                                {{ is_float($value) ? number_format($value, 2) : $value }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            @endforeach

                            @if(isset($check['message']))
                                <div class="detail-row">
                                    <span class="detail-label">Info:</span>
                                    <span class="detail-value">{{ $check['message'] }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="footer">
            <p>EPAS Event Ticketing Platform &copy; {{ date('Y') }}</p>
            <p>For support, contact: support@epas.ro</p>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
