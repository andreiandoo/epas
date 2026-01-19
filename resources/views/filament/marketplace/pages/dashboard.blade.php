<x-filament-panels::page>
    @if(!$marketplace)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found. Please contact support.</p>
        </div>
    @else
        <!-- Welcome Section with Account Info -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $marketplace->name }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($marketplace->domain)
                            <span>{{ $marketplace->domain }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <!-- Active Events -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['active_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Active Events</p>
                    </div>
                </div>
            </div>

            <!-- Total Events -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                        <x-heroicon-o-calendar-days class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Events</p>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_revenue'], 2) }} <span class="text-base font-medium text-gray-500 dark:text-gray-400">EUR</span></p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Tickets Sold -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_tickets']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tickets Sold</p>
                    </div>
                </div>
            </div>

            <!-- Total Organizers -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                        <x-heroicon-o-user-group class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_organizers']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Organizers</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <!-- Pending Organizers -->
            @if($stats['pending_organizers'] > 0)
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-sm border border-orange-200 dark:border-orange-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <x-heroicon-o-clock class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($stats['pending_organizers']) }}</p>
                        <p class="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wide">Pending Organizers</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Total Customers -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg">
                        <x-heroicon-o-users class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_customers']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Customers</p>
                    </div>
                </div>
            </div>

            <!-- Pending Payouts -->
            @if($stats['pending_payouts'] > 0)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl shadow-sm border border-yellow-200 dark:border-yellow-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_payouts'] }}</p>
                        <p class="text-xs font-medium text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">Pending Payouts</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Pending Payouts Value -->
            @if($stats['pending_payouts_value'] > 0)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl shadow-sm border border-yellow-200 dark:border-yellow-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <x-heroicon-o-currency-euro class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($stats['pending_payouts_value'], 2) }} <span class="text-base">EUR</span></p>
                        <p class="text-xs font-medium text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">Payout Value</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" wire:key="charts-{{ $chartPeriod }}">
            <!-- Sales Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Sales Overview</h3>
                    <div class="flex items-center gap-2">
                        <select
                            wire:model.live="chartPeriod"
                            class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                        >
                            <option value="7">Last 7 days</option>
                            <option value="15">Last 15 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="60">Last 60 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                </div>

                <div class="h-64">
                    <canvas id="salesChart" data-chart='@json($chartData)' data-currency="EUR"></canvas>
                </div>
            </div>

            <!-- Tickets Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tickets Sold</h3>
                </div>

                <div class="h-64">
                    <canvas id="ticketsChart" data-chart='@json($ticketChartData)'></canvas>
                </div>
            </div>
        </div>

        <!-- Top Organizers -->
        @if($topOrganizers && $topOrganizers->count() > 0)
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Organizers</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Organizer</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topOrganizers as $organizer)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                            {{ strtoupper(substr($organizer->name ?? $organizer->company_name ?? 'O', 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $organizer->company_name ?? $organizer->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $organizer->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-gray-900 dark:text-white">
                                {{ number_format($organizer->total_revenue ?? 0, 2) }} EUR
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });

        document.addEventListener('livewire:navigated', function() {
            initCharts();
        });

        document.addEventListener('charts-updated', function() {
            setTimeout(() => initCharts(), 100);
        });

        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector && (el.querySelector('#salesChart') || el.querySelector('#ticketsChart'))) {
                setTimeout(() => initCharts(), 100);
            }
        });

        function initCharts() {
            initSalesChart();
            initTicketsChart();
        }

        function initSalesChart() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;

            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');

            const chartDataStr = ctx.getAttribute('data-chart');
            if (!chartDataStr) return;

            const chartData = JSON.parse(chartDataStr);
            const currency = ctx.getAttribute('data-currency') || 'EUR';

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Sales',
                        data: chartData.data,
                        borderColor: isDark ? '#818cf8' : '#6366f1',
                        backgroundColor: isDark ? 'rgba(129, 140, 248, 0.1)' : 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }]
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
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: ' + new Intl.NumberFormat('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }).format(context.parsed.y) + ' ' + currency;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                maxRotation: 45,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: isDark ? '#374151' : '#f3f4f6',
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                callback: function(value) {
                                    return new Intl.NumberFormat('en-US', {
                                        notation: 'compact',
                                        maximumFractionDigits: 1
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function initTicketsChart() {
            const ctx = document.getElementById('ticketsChart');
            if (!ctx) return;

            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');

            const ticketDataStr = ctx.getAttribute('data-chart');
            if (!ticketDataStr) return;

            const ticketData = JSON.parse(ticketDataStr);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ticketData.labels,
                    datasets: [{
                        label: 'Tickets',
                        data: ticketData.data,
                        backgroundColor: isDark ? 'rgba(168, 85, 247, 0.7)' : 'rgba(147, 51, 234, 0.7)',
                        borderColor: isDark ? '#a855f7' : '#9333ea',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
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
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const total = context.parsed.y;
                                    return 'Total: ' + total + ' ticket' + (total !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                maxRotation: 45,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: isDark ? '#374151' : '#f3f4f6',
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                stepSize: 1,
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
    @endpush
</x-filament-panels::page>
