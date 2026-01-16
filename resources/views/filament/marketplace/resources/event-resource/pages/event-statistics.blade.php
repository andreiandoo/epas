<x-filament-panels::page>
    @php
        $eventContext = $this->getEventContext();
        $ticketTypesData = $this->getTicketTypesData();
        $totalRevenue = $this->getTotalRevenue();
        $totalTicketsSold = $this->getTotalTicketsSold();
        $totalCapacity = $this->getTotalCapacity();
        $orderStats = $this->getOrderStats();
        $dailySalesData = $this->getDailySalesData();
        $pageAnalytics = $this->getPageAnalytics();

        $occupancy = $totalCapacity > 0 ? round(($totalTicketsSold / $totalCapacity) * 100, 1) : 0;
        $totalViews = $pageAnalytics['total_views'] ?? 0;
    @endphp

    <div class="space-y-6">
        {{-- Event Header --}}
        <div class="p-6 rounded-lg bg-gradient-to-r from-primary-500/10 to-purple-500/10 border border-primary-200 dark:border-primary-800">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $eventContext['title'] }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                        @if($eventContext['venue_name'] || $eventContext['city_name'])
                            <div class="flex items-center gap-1">
                                <x-heroicon-o-map-pin class="w-4 h-4" />
                                <span>
                                    @if($eventContext['venue_name']){{ $eventContext['venue_name'] }}@endif
                                    @if($eventContext['venue_name'] && $eventContext['city_name']), @endif
                                    @if($eventContext['city_name']){{ $eventContext['city_name'] }}@endif
                                </span>
                            </div>
                        @endif
                        @if($eventContext['event_date'])
                            <div class="flex items-center gap-1">
                                <x-heroicon-o-calendar class="w-4 h-4" />
                                <span>{{ $eventContext['event_date'] }}</span>
                            </div>
                        @endif
                    </div>
                    @if(count($eventContext['artists']) > 0)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($eventContext['artists'] as $artist)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-800/30 dark:text-purple-300">
                                    {{ $artist }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex flex-col items-end gap-2">
                    <span class="px-3 py-1 text-sm font-medium rounded-full {{ $eventContext['is_cancelled'] ? 'bg-red-100 text-red-800 dark:bg-red-800/30 dark:text-red-300' : ($eventContext['is_sold_out'] ? 'bg-orange-100 text-orange-800 dark:bg-orange-800/30 dark:text-orange-300' : 'bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-300') }}">
                        @if($eventContext['is_cancelled'])
                            Anulat
                        @elseif($eventContext['is_sold_out'])
                            Sold Out
                        @else
                            {{ $eventContext['status'] }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($totalTicketsSold) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Bilete vândute</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600">{{ number_format($totalRevenue, 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Încasări totale</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-blue-600">{{ $occupancy }}%</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Grad ocupare</div>
                <div class="mt-1 h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                    <div class="h-full {{ $occupancy >= 80 ? 'bg-green-500' : ($occupancy >= 50 ? 'bg-yellow-500' : 'bg-blue-500') }}" style="width: {{ min($occupancy, 100) }}%"></div>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600">{{ number_format($orderStats['total']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Comenzi totale</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-cyan-600">{{ number_format($totalViews) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Vizualizări</div>
            </div>
        </div>

        {{-- Order Status Breakdown --}}
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Status comenzi</h2>

            <div class="grid grid-cols-3 gap-4 md:grid-cols-7">
                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                    <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $orderStats['total'] }}</div>
                    <div class="text-xs text-gray-500">Total</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <div class="text-xl font-bold text-green-600">{{ $orderStats['paid'] }}</div>
                    <div class="text-xs text-gray-500">Finalizate</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                    <div class="text-xl font-bold text-yellow-600">{{ $orderStats['pending'] }}</div>
                    <div class="text-xs text-gray-500">În așteptare</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <div class="text-xl font-bold text-red-600">{{ $orderStats['cancelled'] }}</div>
                    <div class="text-xs text-gray-500">Anulate</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-orange-50 dark:bg-orange-900/20">
                    <div class="text-xl font-bold text-orange-600">{{ $orderStats['refunded'] }}</div>
                    <div class="text-xs text-gray-500">Returnate</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20">
                    <div class="text-xl font-bold text-rose-600">{{ $orderStats['failed'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Eșuate</div>
                </div>
                <div class="text-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                    <div class="text-xl font-bold text-slate-600">{{ $orderStats['expired'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Expirate</div>
                </div>
            </div>

            @if($orderStats['total'] > 0)
                @php
                    $completedRate = round(($orderStats['paid'] / $orderStats['total']) * 100, 1);
                    $notCompletedCount = $orderStats['total'] - $orderStats['paid'];
                @endphp
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Rată de finalizare</span>
                        <span class="font-semibold {{ $completedRate >= 70 ? 'text-green-600' : ($completedRate >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $completedRate }}% ({{ $orderStats['paid'] }} din {{ $orderStats['total'] }})
                        </span>
                    </div>
                    <div class="mt-2 h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                        <div class="h-full {{ $completedRate >= 70 ? 'bg-green-500' : ($completedRate >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $completedRate }}%"></div>
                    </div>
                </div>
            @endif
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
