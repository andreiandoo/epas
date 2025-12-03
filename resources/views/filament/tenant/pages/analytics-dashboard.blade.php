<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $salesData = $this->getSalesData();
        $topEvents = $this->getTopEvents();
        $realtimeData = $this->getRealtimeData();
        $trafficSources = $this->getTrafficSources();
        $pageViews = $this->getTopPages();
        $geoData = $this->getGeographicData();
    @endphp

    <div class="space-y-6">
        {{-- Top Bar with Date Range --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-lg text-sm font-medium">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Real-time
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Last updated: {{ now()->format('H:i:s') }}</span>
            </div>
            <div class="w-48">
                {{ $this->form }}
            </div>
        </div>

        {{-- Real-time Overview (Google Analytics Style) --}}
        <div class="grid grid-cols-12 gap-6">
            {{-- Left Panel - Real-time Stats --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-white shadow-xl" wire:poll.5s="refreshRealtime">
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-blue-100 text-sm font-medium uppercase tracking-wider">Real-time</span>
                        <span class="flex items-center text-xs text-blue-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 mr-1.5 animate-pulse"></span>
                            LIVE
                        </span>
                    </div>

                    <div class="text-center mb-8">
                        <div class="text-6xl font-bold mb-2">{{ $realtimeData['active_users'] }}</div>
                        <div class="text-blue-200 text-sm">active users right now</div>
                    </div>

                    {{-- Activity by Time (Last 30 minutes) --}}
                    <div class="mb-6">
                        <div class="text-xs text-blue-200 uppercase tracking-wider mb-3">Users per minute (last 30 min)</div>
                        <div class="flex items-end justify-between h-16 gap-0.5">
                            @foreach($realtimeData['users_per_minute'] as $count)
                                <div class="flex-1 bg-white/20 rounded-t transition-all hover:bg-white/30" style="height: {{ max(4, ($count / max(1, max($realtimeData['users_per_minute']))) * 100) }}%"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-xs text-blue-200 mt-2">
                            <span>30 min ago</span>
                            <span>Now</span>
                        </div>
                    </div>

                    {{-- Top Active Pages --}}
                    <div>
                        <div class="text-xs text-blue-200 uppercase tracking-wider mb-3">Top Active Pages</div>
                        <div class="space-y-2">
                            @foreach($realtimeData['active_pages'] as $page)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="truncate flex-1 text-white/90">{{ $page['path'] }}</span>
                                    <span class="font-semibold ml-2">{{ $page['users'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Real-time Events --}}
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Live Activity</h3>
                        <span class="text-xs text-gray-500">Auto-refresh</span>
                    </div>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        @foreach($realtimeData['recent_events'] as $event)
                            <div class="flex items-start gap-3 text-sm animate-fade-in">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                                    {{ $event['type'] === 'purchase' ? 'bg-green-100 dark:bg-green-900/50' : '' }}
                                    {{ $event['type'] === 'view' ? 'bg-blue-100 dark:bg-blue-900/50' : '' }}
                                    {{ $event['type'] === 'cart' ? 'bg-orange-100 dark:bg-orange-900/50' : '' }}
                                ">
                                    @if($event['type'] === 'purchase')
                                        <x-heroicon-s-currency-euro class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    @elseif($event['type'] === 'view')
                                        <x-heroicon-s-eye class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    @elseif($event['type'] === 'cart')
                                        <x-heroicon-s-shopping-cart class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-900 dark:text-white font-medium truncate">{{ $event['description'] }}</p>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs">{{ $event['location'] }} · {{ $event['time'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right Panel - Main Dashboard --}}
            <div class="col-span-12 lg:col-span-8 space-y-6">
                {{-- Key Metrics Row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {{-- Users --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Users</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['total_users']) }}</div>
                        <div class="flex items-center mt-2 text-xs">
                            @if($realtimeData['users_change'] >= 0)
                                <span class="text-green-600 flex items-center">
                                    <x-heroicon-s-arrow-trending-up class="w-3 h-3 mr-0.5" />
                                    {{ number_format($realtimeData['users_change'], 1) }}%
                                </span>
                            @else
                                <span class="text-red-600 flex items-center">
                                    <x-heroicon-s-arrow-trending-down class="w-3 h-3 mr-0.5" />
                                    {{ number_format(abs($realtimeData['users_change']), 1) }}%
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Sessions --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['total_sessions']) }}</div>
                        <div class="flex items-center mt-2 text-xs">
                            <span class="text-green-600 flex items-center">
                                <x-heroicon-s-arrow-trending-up class="w-3 h-3 mr-0.5" />
                                {{ number_format($realtimeData['sessions_change'], 1) }}%
                            </span>
                        </div>
                    </div>

                    {{-- Bounce Rate --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bounce Rate</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['bounce_rate'], 1) }}%</div>
                        <div class="flex items-center mt-2 text-xs">
                            <span class="text-green-600 flex items-center">
                                <x-heroicon-s-arrow-trending-down class="w-3 h-3 mr-0.5" />
                                2.3%
                            </span>
                        </div>
                    </div>

                    {{-- Avg Session Duration --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Duration</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $realtimeData['avg_duration'] }}</div>
                        <div class="flex items-center mt-2 text-xs">
                            <span class="text-green-600 flex items-center">
                                <x-heroicon-s-arrow-trending-up class="w-3 h-3 mr-0.5" />
                                8.5%
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Revenue Stats --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-4 text-white shadow-lg">
                        <div class="text-xs text-green-100 uppercase tracking-wider">Total Revenue</div>
                        <div class="text-2xl font-bold mt-1">€{{ number_format($metrics['total_revenue'], 0) }}</div>
                        @if($metrics['revenue_change'] != 0)
                            <div class="text-xs text-green-100 mt-2">
                                {{ $metrics['revenue_change'] > 0 ? '+' : '' }}{{ number_format($metrics['revenue_change'], 1) }}% vs prev
                            </div>
                        @endif
                    </div>
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl p-4 text-white shadow-lg">
                        <div class="text-xs text-blue-100 uppercase tracking-wider">Orders</div>
                        <div class="text-2xl font-bold mt-1">{{ number_format($metrics['total_orders']) }}</div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl p-4 text-white shadow-lg">
                        <div class="text-xs text-orange-100 uppercase tracking-wider">Tickets Sold</div>
                        <div class="text-2xl font-bold mt-1">{{ number_format($metrics['total_tickets']) }}</div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl p-4 text-white shadow-lg">
                        <div class="text-xs text-purple-100 uppercase tracking-wider">Avg Order</div>
                        <div class="text-2xl font-bold mt-1">€{{ number_format($metrics['avg_order_value'], 0) }}</div>
                    </div>
                </div>

                {{-- Charts Row --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Users Over Time --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Users Over Time</h3>
                            <div class="flex gap-2">
                                <span class="inline-flex items-center text-xs">
                                    <span class="w-2 h-2 rounded-full bg-blue-500 mr-1"></span>
                                    Users
                                </span>
                                <span class="inline-flex items-center text-xs">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500 mr-1"></span>
                                    Sessions
                                </span>
                            </div>
                        </div>
                        <div class="h-48">
                            <canvas id="usersChart"></canvas>
                        </div>
                    </div>

                    {{-- Revenue Over Time --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Revenue</h3>
                        </div>
                        <div class="h-48">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Bottom Row --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Traffic Sources --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Traffic Sources</h3>
                        <div class="space-y-3">
                            @foreach($trafficSources as $source)
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $source['name'] }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $source['percentage'] }}%</span>
                                    </div>
                                    <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $source['color'] }}" style="width: {{ $source['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Top Pages --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Top Pages</h3>
                        <div class="space-y-3">
                            @foreach($pageViews as $page)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700 dark:text-gray-300 truncate flex-1">{{ $page['path'] }}</span>
                                    <span class="font-medium text-gray-900 dark:text-white ml-2">{{ number_format($page['views']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Top Events --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Top Events</h3>
                        <div class="space-y-3">
                            @forelse($topEvents as $event)
                                <div class="flex items-center justify-between text-sm">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-gray-700 dark:text-gray-300 truncate">{{ $event['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $event['orders'] }} orders</p>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white ml-2">€{{ number_format($event['revenue'], 0) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 text-center py-4">No data available</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Geographic Distribution --}}
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Users by Country</h3>
                        <span class="text-xs text-gray-500">Last 30 days</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        @foreach($geoData as $country)
                            <div class="text-center">
                                <div class="text-2xl mb-1">{{ $country['flag'] }}</div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($country['users']) }}</div>
                                <div class="text-xs text-gray-500">{{ $country['name'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Device & Browser Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Devices</h3>
                <div class="h-48">
                    <canvas id="devicesChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Browsers</h3>
                <div class="h-48">
                    <canvas id="browsersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartColors = {
                blue: 'rgb(59, 130, 246)',
                indigo: 'rgb(99, 102, 241)',
                green: 'rgb(34, 197, 94)',
                orange: 'rgb(249, 115, 22)',
                purple: 'rgb(168, 85, 247)',
            };

            const gridColor = document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';

            // Users Chart
            const usersCtx = document.getElementById('usersChart');
            if (usersCtx) {
                new Chart(usersCtx, {
                    type: 'line',
                    data: {
                        labels: @json($salesData['labels']),
                        datasets: [{
                            label: 'Users',
                            data: @json($salesData['orders']),
                            borderColor: chartColors.blue,
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: gridColor } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($salesData['labels']),
                        datasets: [{
                            label: 'Revenue (EUR)',
                            data: @json($salesData['revenue']),
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: gridColor } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Devices Chart
            const devicesCtx = document.getElementById('devicesChart');
            if (devicesCtx) {
                new Chart(devicesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Desktop', 'Mobile', 'Tablet'],
                        datasets: [{
                            data: [58, 35, 7],
                            backgroundColor: [chartColors.blue, chartColors.green, chartColors.orange],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' }
                        }
                    }
                });
            }

            // Browsers Chart
            const browsersCtx = document.getElementById('browsersChart');
            if (browsersCtx) {
                new Chart(browsersCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Chrome', 'Safari', 'Firefox', 'Edge', 'Other'],
                        datasets: [{
                            data: [64, 18, 8, 6, 4],
                            backgroundColor: [chartColors.blue, chartColors.orange, chartColors.purple, chartColors.green, chartColors.indigo],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' }
                        }
                    }
                });
            }
        });
    </script>
    @endpush

    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
    </style>
</x-filament-panels::page>
