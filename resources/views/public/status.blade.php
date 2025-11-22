<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Tixello</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .status-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">System Status</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Last updated: {{ $lastUpdated->format('d M Y, H:i:s') }} UTC
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @php
                        $overallStatus = $health['status'] ?? 'unknown';
                        $statusColor = match($overallStatus) {
                            'healthy' => 'bg-green-500',
                            'degraded' => 'bg-yellow-500',
                            'unhealthy' => 'bg-red-500',
                            default => 'bg-gray-500',
                        };
                        $statusText = match($overallStatus) {
                            'healthy' => 'All Systems Operational',
                            'degraded' => 'Partial System Outage',
                            'unhealthy' => 'Major System Outage',
                            default => 'Status Unknown',
                        };
                    @endphp
                    <span class="status-dot w-3 h-3 rounded-full {{ $statusColor }}"></span>
                    <span class="text-lg font-semibold {{ $overallStatus === 'healthy' ? 'text-green-600' : ($overallStatus === 'degraded' ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $statusText }}
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($services as $service)
                @php
                    $isOnline = $service['is_online'];
                    $statusColor = $isOnline ? 'bg-green-500' : 'bg-red-500';
                    $bgColor = $isOnline ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
                    $textColor = $isOnline ? 'text-green-700' : 'text-red-700';
                @endphp
                <div class="bg-white rounded-lg shadow-sm border {{ $bgColor }} p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full {{ $statusColor }}"></span>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $service['display_name'] }}</h3>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ ucfirst($service['type']) }}
                            </p>
                        </div>
                        <span class="text-xs font-medium {{ $textColor }} px-2 py-1 rounded-full {{ $isOnline ? 'bg-green-100' : 'bg-red-100' }}">
                            {{ $isOnline ? 'Operational' : 'Down' }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Version</span>
                            <span class="font-mono text-gray-700">{{ $service['version'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Uptime (30d)</span>
                            <span class="font-semibold {{ $service['uptime'] >= 99 ? 'text-green-600' : ($service['uptime'] >= 95 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($service['uptime'], 2) }}%
                            </span>
                        </div>
                        @if($service['response_time'] > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Response Time</span>
                                <span class="text-gray-700">{{ $service['response_time'] }}ms</span>
                            </div>
                        @endif
                    </div>

                    <!-- Mini Chart -->
                    <div class="mt-4 h-12">
                        <canvas id="chart-{{ $service['name'] }}" class="w-full h-full"></canvas>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- 30-Day History Chart -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">30-Day Uptime History</h2>
            <div class="h-64">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <!-- Incident History -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Incidents</h2>
            <div class="space-y-4">
                <p class="text-gray-500 text-sm">No incidents reported in the last 30 days.</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    &copy; {{ date('Y') }} Tixello. All rights reserved.
                </p>
                <a href="/" class="text-sm text-blue-600 hover:text-blue-800">
                    Back to Home
                </a>
            </div>
        </div>
    </footer>

    <script>
        const chartData = @json($chartData);

        // Mini charts for each service
        @foreach($services as $service)
        (function() {
            const ctx = document.getElementById('chart-{{ $service['name'] }}').getContext('2d');
            const data = chartData['{{ $service['name'] }}'] || [];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => ''),
                    datasets: [{
                        data: data.map(d => d.uptime),
                        backgroundColor: data.map(d => d.uptime >= 99 ? '#22c55e' : (d.uptime >= 95 ? '#eab308' : '#ef4444')),
                        borderWidth: 0,
                        borderRadius: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => data[items[0].dataIndex]?.date || '',
                                label: (item) => `${item.raw}% uptime`
                            }
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false, min: 0, max: 100 }
                    }
                }
            });
        })();
        @endforeach

        // Main chart
        (function() {
            const ctx = document.getElementById('mainChart').getContext('2d');
            const coreData = chartData['core'] || [];

            const datasets = [];
            const colors = {
                'core': { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' },
                'database': { border: '#10b981', bg: 'rgba(16, 185, 129, 0.1)' },
                'api': { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.1)' },
            };

            let colorIndex = 0;
            const defaultColors = [
                { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' },
                { border: '#ec4899', bg: 'rgba(236, 72, 153, 0.1)' },
                { border: '#06b6d4', bg: 'rgba(6, 182, 212, 0.1)' },
            ];

            Object.keys(chartData).forEach(serviceName => {
                const data = chartData[serviceName];
                const color = colors[serviceName] || defaultColors[colorIndex++ % defaultColors.length];

                datasets.push({
                    label: serviceName.charAt(0).toUpperCase() + serviceName.slice(1),
                    data: data.map(d => d.uptime),
                    borderColor: color.border,
                    backgroundColor: color.bg,
                    fill: false,
                    tension: 0.4,
                    borderWidth: 2,
                });
            });

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: coreData.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: (item) => `${item.dataset.label}: ${item.raw}% uptime`
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 90,
                            max: 100,
                            ticks: {
                                callback: (value) => value + '%'
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>
