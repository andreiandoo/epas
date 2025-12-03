<x-filament-panels::page>
    {{-- Header --}}
    <div class="mb-6">
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-pink-500/20 flex items-center justify-center">
                    <x-heroicon-o-chart-bar-square class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Analytics Dashboard
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Track your sales performance, ticket sales, and revenue metrics in real-time.
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Live
                </span>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    @php
        $metrics = $this->getMetrics();
        $salesData = $this->getSalesData();
        $topEvents = $this->getTopEvents();
    @endphp

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Revenue --}}
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($metrics['total_revenue'], 2) }} EUR
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 flex items-center justify-center">
                    <x-heroicon-o-currency-euro class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
            @if($metrics['revenue_change'] != 0)
                <div class="mt-2 flex items-center text-sm">
                    @if($metrics['revenue_change'] > 0)
                        <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-green-500 mr-1" />
                        <span class="text-green-600">+{{ number_format($metrics['revenue_change'], 1) }}%</span>
                    @else
                        <x-heroicon-o-arrow-trending-down class="w-4 h-4 text-red-500 mr-1" />
                        <span class="text-red-600">{{ number_format($metrics['revenue_change'], 1) }}%</span>
                    @endif
                    <span class="text-gray-500 ml-1">vs previous period</span>
                </div>
            @endif
        </div>

        {{-- Total Orders --}}
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($metrics['total_orders']) }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                    <x-heroicon-o-shopping-bag class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        {{-- Tickets Sold --}}
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tickets Sold</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($metrics['total_tickets']) }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 flex items-center justify-center">
                    <x-heroicon-o-ticket class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </div>

        {{-- Avg Order Value --}}
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg Order Value</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($metrics['avg_order_value'], 2) }} EUR
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-violet-500/20 flex items-center justify-center">
                    <x-heroicon-o-calculator class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Revenue Chart --}}
        <div class="lg:col-span-2 backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Over Time</h3>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        {{-- Top Events --}}
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Events</h3>
            <div class="space-y-4">
                @forelse($topEvents as $event)
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $event['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $event['orders'] }} orders</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($event['revenue'], 2) }} EUR
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No events data available</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('revenueChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($salesData['labels']),
                        datasets: [{
                            label: 'Revenue (EUR)',
                            data: @json($salesData['revenue']),
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            fill: true,
                            tension: 0.4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
