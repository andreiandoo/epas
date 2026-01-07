<x-filament-panels::page>
    @php
        $ticketTypesData = $this->getTicketTypesData();
        $totalRevenue = $this->getTotalRevenue();
        $totalTicketsSold = $this->getTotalTicketsSold();
        $totalCapacity = $this->getTotalCapacity();
        $orderStats = $this->getOrderStats();
        $dailySalesData = $this->getDailySalesData();
        $pageAnalytics = $this->getPageAnalytics();

        $occupancy = $totalCapacity > 0 ? round(($totalTicketsSold / $totalCapacity) * 100, 1) : 0;
    @endphp

    <div class="space-y-6">
        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($totalTicketsSold) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tickets Sold</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600">{{ number_format($totalRevenue, 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-blue-600">{{ $occupancy }}%</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Occupancy</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600">{{ number_format($orderStats['paid']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Paid Orders</div>
            </div>
        </div>

        {{-- Order Status Breakdown --}}
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Order Status</h2>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $orderStats['total'] }}</div>
                    <div class="text-sm text-gray-500">Total</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-yellow-600">{{ $orderStats['pending'] }}</div>
                    <div class="text-sm text-gray-500">Pending</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-green-600">{{ $orderStats['paid'] }}</div>
                    <div class="text-sm text-gray-500">Paid</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-red-600">{{ $orderStats['cancelled'] }}</div>
                    <div class="text-sm text-gray-500">Cancelled</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-orange-600">{{ $orderStats['refunded'] }}</div>
                    <div class="text-sm text-gray-500">Refunded</div>
                </div>
            </div>
        </div>

        {{-- Ticket Types Performance --}}
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Ticket Types Performance</h2>

            @if(count($ticketTypesData) > 0)
                <div class="space-y-4">
                    @foreach($ticketTypesData as $ticket)
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $ticket['name'] }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ number_format($ticket['price']) }} {{ $ticket['currency'] }}
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="mb-2 h-3 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                <div
                                    class="h-full {{ $ticket['percentage'] >= 80 ? 'bg-red-500' : ($ticket['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                    style="width: {{ min($ticket['percentage'], 100) }}%"
                                ></div>
                            </div>

                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ $ticket['sold'] }} / {{ $ticket['total'] }} sold ({{ $ticket['percentage'] }}%)
                                </span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ number_format($ticket['revenue'], 2) }} {{ $ticket['currency'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No ticket types configured.</p>
            @endif
        </div>

        {{-- Sales Chart --}}
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Daily Sales (Last 30 Days)</h2>

            <div style="height: 300px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        {{-- Page Analytics --}}
        @if($pageAnalytics['available'] ?? false)
            <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Page Analytics</h2>

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 mb-6">
                    <div class="text-center">
                        <div class="text-xl font-bold text-primary-600">{{ number_format($pageAnalytics['total_views'] ?? 0) }}</div>
                        <div class="text-sm text-gray-500">Total Views</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-blue-600">{{ number_format($pageAnalytics['unique_sessions'] ?? 0) }}</div>
                        <div class="text-sm text-gray-500">Unique Sessions</div>
                    </div>
                </div>

                @if(!empty($pageAnalytics['top_sources']))
                    <div class="mt-4">
                        <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase">Traffic Sources</h3>
                        <div class="space-y-2">
                            @foreach($pageAnalytics['top_sources'] as $source => $count)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $source }}</span>
                                    <span class="text-gray-500">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('salesChart');
                if (ctx) {
                    const chartData = @json($dailySalesData);

                    new Chart(ctx, {
                        type: 'bar',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            });
        </script>
    @endpush
</x-filament-panels::page>
