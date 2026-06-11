<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Report - {{ $event->title_translated ?? $event->title }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 5px 0;
            color: #1f2937;
        }
        .header .subtitle {
            color: #6b7280;
            font-size: 14px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .stat-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
        }
        .stat-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .stat-change {
            font-size: 10px;
            margin-top: 3px;
        }
        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #ef4444; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            font-size: 12px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .progress-bar {
            background: #e5e7eb;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }
        .progress-fill {
            background: #3b82f6;
            height: 100%;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
            text-align: center;
        }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $event->title_translated ?? $event->title }}</h1>
        <div class="subtitle">
            Analytics Report | {{ $date_range['start']->format('M d, Y') }} - {{ $date_range['end']->format('M d, Y') }}
        </div>
    </div>

    @if(in_array('overview', $sections) && isset($overview))
    <div class="section">
        <div class="section-title">Overview</div>
        <div class="stat-grid">
            <div class="stat-box">
                <div class="stat-value">{{ number_format($overview['revenue']['total'] ?? 0, 2) }}</div>
                <div class="stat-label">Revenue ({{ $event->currency ?? 'EUR' }})</div>
                @if(isset($comparison['changes']['revenue']))
                <div class="stat-change {{ $comparison['changes']['revenue'] >= 0 ? 'positive' : 'negative' }}">
                    {{ $comparison['changes']['revenue'] >= 0 ? '+' : '' }}{{ $comparison['changes']['revenue'] }}% vs prev
                </div>
                @endif
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ number_format($overview['tickets']['sold'] ?? 0) }}</div>
                <div class="stat-label">Tickets Sold</div>
                <div class="stat-change">of {{ number_format($overview['tickets']['capacity'] ?? 0) }} capacity</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ number_format($overview['visits']['unique'] ?? 0) }}</div>
                <div class="stat-label">Unique Visitors</div>
                @if(isset($comparison['changes']['visitors']))
                <div class="stat-change {{ $comparison['changes']['visitors'] >= 0 ? 'positive' : 'negative' }}">
                    {{ $comparison['changes']['visitors'] >= 0 ? '+' : '' }}{{ $comparison['changes']['visitors'] }}% vs prev
                </div>
                @endif
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $overview['conversion']['rate'] ?? 0 }}%</div>
                <div class="stat-label">Conversion Rate</div>
            </div>
        </div>
    </div>
    @endif

    @if(in_array('goals', $sections) && isset($goals) && $goals->count() > 0)
    <div class="section">
        <div class="section-title">Goals</div>
        <table>
            <thead>
                <tr>
                    <th>Goal</th>
                    <th class="text-right">Target</th>
                    <th class="text-right">Current</th>
                    <th style="width: 150px;">Progress</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($goals as $goal)
                <tr>
                    <td>
                        <strong>{{ $goal->type_label }}</strong>
                        @if($goal->name)
                        <br><small>{{ $goal->name }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{ $goal->formatted_target }}</td>
                    <td class="text-right">{{ $goal->formatted_current }}</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: {{ min($goal->progress_percent, 100) }}%"></div>
                        </div>
                        <small>{{ number_format($goal->progress_percent, 1) }}%</small>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-{{ $goal->progress_status }}">
                            {{ ucfirst($goal->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('traffic', $sections) && isset($traffic_sources) && count($traffic_sources) > 0)
    <div class="section">
        <div class="section-title">Traffic Sources</div>
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th class="text-right">Visitors</th>
                    <th class="text-right">Share</th>
                    <th class="text-right">Conversions</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($traffic_sources as $source)
                <tr>
                    <td>{{ $source['name'] }}</td>
                    <td class="text-right">{{ number_format($source['visitors']) }}</td>
                    <td class="text-right">{{ $source['percent'] }}%</td>
                    <td class="text-right">{{ $source['conversions'] }}</td>
                    <td class="text-right">{{ number_format($source['revenue'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('milestones', $sections) && isset($milestones) && count($milestones) > 0)
    <div class="section">
        <div class="section-title">Campaigns & Milestones</div>
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th class="text-right">Budget</th>
                    <th class="text-right">Attributed Revenue</th>
                    <th class="text-right">ROI</th>
                    <th class="text-right">CAC</th>
                </tr>
            </thead>
            <tbody>
                @foreach($milestones as $milestone)
                <tr>
                    <td>
                        <strong>{{ $milestone['title'] }}</strong>
                        <br><small>{{ $milestone['label'] }} | {{ $milestone['start_date'] }}</small>
                    </td>
                    <td class="text-right">{{ number_format($milestone['budget'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($milestone['attributed_revenue'] ?? 0, 2) }}</td>
                    <td class="text-right">
                        @if($milestone['roi'])
                        <span class="{{ $milestone['roi'] >= 0 ? 'positive' : 'negative' }}">
                            {{ $milestone['roi'] >= 0 ? '+' : '' }}{{ number_format($milestone['roi'], 1) }}%
                        </span>
                        @else
                        -
                        @endif
                    </td>
                    <td class="text-right">{{ $milestone['cac'] ? number_format($milestone['cac'], 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('top_locations', $sections) && isset($top_locations) && count($top_locations) > 0)
    <div class="section">
        <div class="section-title">Top Locations</div>
        <table>
            <thead>
                <tr>
                    <th>City</th>
                    <th>Country</th>
                    <th class="text-right">Tickets</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top_locations as $location)
                <tr>
                    <td>{{ $location['city'] }}</td>
                    <td>{{ $location['country'] }}</td>
                    <td class="text-right">{{ $location['tickets'] }}</td>
                    <td class="text-right">{{ number_format($location['revenue'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('funnel', $sections) && isset($funnel))
    <div class="section">
        <div class="section-title">Conversion Funnel</div>
        <table>
            <thead>
                <tr>
                    <th>Stage</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Conversion</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Page Views</td>
                    <td class="text-right">{{ number_format($funnel['page_views']) }}</td>
                    <td class="text-right">100%</td>
                </tr>
                <tr>
                    <td>Unique Visitors</td>
                    <td class="text-right">{{ number_format($funnel['unique_visitors']) }}</td>
                    <td class="text-right">-</td>
                </tr>
                <tr>
                    <td>Add to Cart</td>
                    <td class="text-right">{{ number_format($funnel['add_to_cart']) }}</td>
                    <td class="text-right">{{ $funnel['view_to_cart_rate'] }}%</td>
                </tr>
                <tr>
                    <td>Checkout Started</td>
                    <td class="text-right">{{ number_format($funnel['checkout_started']) }}</td>
                    <td class="text-right">{{ $funnel['cart_to_checkout_rate'] }}%</td>
                </tr>
                <tr>
                    <td>Purchases</td>
                    <td class="text-right">{{ number_format($funnel['purchases']) }}</td>
                    <td class="text-right">{{ $funnel['checkout_to_purchase_rate'] }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Generated on {{ $generated_at->format('F d, Y \a\t H:i') }} | {{ config('app.name') }}
    </div>
</body>
</html>
