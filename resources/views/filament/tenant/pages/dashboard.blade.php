<x-filament-panels::page>
    @if(!$tenant)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
            <p class="text-yellow-800 dark:text-yellow-200">No tenant account found. Please contact support.</p>
        </div>
    @else
        <!-- Welcome Section with Account Info -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $tenant->public_name ?? $tenant->name }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($tenant->company_name)
                            <span>{{ $tenant->company_name }}</span>
                        @endif
                        @if($tenant->cui)
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>CUI: {{ $tenant->cui }}</span>
                        @endif
                        @if($tenant->plan)
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                {{ ucfirst(str_replace('percent', '%', $tenant->plan)) }}
                            </span>
                        @endif
                    </div>
                </div>
                <a href="{{ route('filament.tenant.pages.settings') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                    Settings
                </a>
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

            <!-- Total Sales -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_sales'], 2) }} <span class="text-base font-medium text-gray-500 dark:text-gray-400">{{ $tenant->currency ?? 'EUR' }}</span></p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Sales</p>
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

            <!-- Customers -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                        <x-heroicon-o-users class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_customers']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Customers</p>
                    </div>
                </div>
            </div>

            <!-- Unpaid Invoices Value -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 {{ $stats['unpaid_invoices_value'] > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }} rounded-lg">
                        <x-heroicon-o-document-text class="w-5 h-5 {{ $stats['unpaid_invoices_value'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $stats['unpaid_invoices_value'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($stats['unpaid_invoices_value'], 2) }} <span class="text-base font-medium {{ $stats['unpaid_invoices_value'] > 0 ? 'text-red-400 dark:text-red-500' : 'text-gray-500 dark:text-gray-400' }}">{{ $tenant->currency ?? 'EUR' }}</span>
                        </p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unpaid Invoices</p>
                    </div>
                </div>
            </div>
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
                    <canvas id="salesChart" data-chart='@json($chartData)' data-currency="{{ $tenant->currency ?? 'EUR' }}"></canvas>
                </div>
            </div>

            <!-- Tickets Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tickets Sold</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Hover for event details</span>
                </div>

                <div class="h-64">
                    <canvas id="ticketsChart" data-chart='@json($ticketChartData)'></canvas>
                </div>
            </div>
        </div>
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

        // Listen for custom event from Livewire when period changes
        document.addEventListener('charts-updated', function() {
            setTimeout(() => initCharts(), 100);
        });

        // Also listen for Livewire navigation
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

            // Destroy existing chart
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');

            // Read data from data attribute
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

            // Destroy existing chart
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');

            // Read data from data attribute
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
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    const total = context.parsed.y;
                                    return 'Total: ' + total + ' bilet' + (total !== 1 ? 'e' : '');
                                },
                                afterBody: function(context) {
                                    const index = context[0].dataIndex;
                                    const events = ticketData.tooltipData[index] || {};
                                    const lines = [];

                                    if (Object.keys(events).length > 0) {
                                        lines.push('');
                                        lines.push('Per event:');
                                        for (const [event, count] of Object.entries(events)) {
                                            // Truncate event name if too long
                                            const name = event.length > 30 ? event.substring(0, 27) + '...' : event;
                                            lines.push('  ' + name + ': ' + count);
                                        }
                                    }

                                    return lines;
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
