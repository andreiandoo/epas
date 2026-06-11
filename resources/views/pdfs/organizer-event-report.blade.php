<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Eveniment - {{ $eventTitle }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
        .container { padding: 30px; max-width: 800px; margin: 0 auto; }

        /* Header */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .report-title { font-size: 22px; font-weight: bold; margin-bottom: 5px; }
        .report-subtitle { font-size: 14px; color: #666; }
        .generated-at { font-size: 11px; color: #999; margin-top: 10px; }

        /* Event Info */
        .event-info {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        .event-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #333; }
        .event-details { font-size: 12px; color: #666; }
        .event-details span { margin-right: 15px; }

        /* Stats Grid */
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 15px 10px;
            background: #f5f5f5;
            border-right: 1px solid #ddd;
        }
        .stat-box:last-child { border-right: none; }
        .stat-value { font-size: 24px; font-weight: bold; color: #333; }
        .stat-label { font-size: 11px; color: #666; text-transform: uppercase; margin-top: 5px; }

        /* Section Title */
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
            text-transform: uppercase;
        }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th {
            background: #f0f0f0;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 2px solid #ddd;
        }
        .data-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table tfoot td { font-weight: bold; background: #f5f5f5; border-top: 2px solid #ddd; }

        /* Progress bar */
        .progress-bar {
            background: #e0e0e0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            background: #4CAF50;
            height: 100%;
        }

        /* Organizer Info */
        .organizer-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #666;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="report-header">
            <div class="report-title">Raport Eveniment</div>
            <div class="report-subtitle">Statistici vânzări și participanți</div>
            <div class="generated-at">Generat la: {{ $generated_at }}</div>
        </div>

        <!-- Event Info -->
        <div class="event-info">
            <div class="event-name">{{ $eventTitle }}</div>
            <div class="event-details">
                @if($event->event_date)
                    <span><strong>Data:</strong> {{ \Carbon\Carbon::parse($event->event_date)->format('d.m.Y') }}</span>
                @endif
                @if($event->start_time)
                    <span><strong>Ora:</strong> {{ $event->start_time }}</span>
                @endif
                @if($event->venue)
                    <span><strong>Locație:</strong> {{ $event->venue->name }}</span>
                @elseif($event->venue_name)
                    <span><strong>Locație:</strong> {{ $event->venue_name }}</span>
                @endif
                @if($event->marketplaceCity)
                    <span><strong>Oraș:</strong> {{ $event->marketplaceCity->name }}</span>
                @endif
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">{{ number_format($stats['total_tickets_sold']) }}</div>
                <div class="stat-label">Bilete vândute</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ number_format($stats['total_orders']) }}</div>
                <div class="stat-label">Comenzi</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ number_format($stats['total_revenue'], 2) }} RON</div>
                <div class="stat-label">Încasări totale</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $stats['check_in_rate'] }}%</div>
                <div class="stat-label">Rată check-in</div>
            </div>
        </div>

        <!-- Check-in Details -->
        <div style="margin-bottom: 25px;">
            <div style="font-size: 12px; margin-bottom: 5px;">
                <strong>Participanți validați:</strong> {{ $stats['checked_in'] }} din {{ $stats['total_tickets_sold'] }}
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {{ $stats['check_in_rate'] }}%;"></div>
            </div>
        </div>

        <!-- Ticket Types Breakdown -->
        <div class="section-title">Detalii pe tip de bilet</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tip bilet</th>
                    <th class="text-right">Preț</th>
                    <th class="text-center">Disponibile</th>
                    <th class="text-center">Vândute</th>
                    <th class="text-right">Încasări</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ticket_types as $tt)
                <tr>
                    <td>{{ $tt['name'] }}</td>
                    <td class="text-right">{{ number_format($tt['price'], 2) }} RON</td>
                    <td class="text-center">{{ $tt['quota'] ?: '∞' }}</td>
                    <td class="text-center">{{ $tt['sold'] }}</td>
                    <td class="text-right">{{ number_format($tt['revenue'], 2) }} RON</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Total</strong></td>
                    <td class="text-center"><strong>{{ $stats['total_tickets_sold'] }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($stats['total_revenue'], 2) }} RON</strong></td>
                </tr>
            </tfoot>
        </table>

        @if($daily_sales->count() > 0)
        <!-- Daily Sales -->
        <div class="section-title">Vânzări pe zile (ultimele 30 zile)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th class="text-center">Comenzi</th>
                    <th class="text-center">Bilete</th>
                    <th class="text-right">Încasări</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daily_sales as $day)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($day['date'])->format('d.m.Y') }}</td>
                    <td class="text-center">{{ $day['orders'] }}</td>
                    <td class="text-center">{{ $day['tickets'] }}</td>
                    <td class="text-right">{{ number_format($day['revenue'], 2) }} RON</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Organizer Info -->
        <div class="organizer-info">
            <strong>Organizator:</strong> {{ $organizer->company_name ?? $organizer->name ?? 'N/A' }}
            @if($organizer->email)
                | <strong>Email:</strong> {{ $organizer->email }}
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            Acest raport a fost generat automat de platforma de ticketing.
        </div>
    </div>
</body>
</html>
