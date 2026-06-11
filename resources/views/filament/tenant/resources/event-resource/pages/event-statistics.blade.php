<x-filament-panels::page>
    @php
        $ticketTypes = $this->getTicketTypesData();
        $totalRevenue = $this->getTotalRevenue();
        $totalSold = $this->getTotalTicketsSold();
        $totalCapacity = $this->getTotalCapacity();
        $orderStats = $this->getOrderStats();
        $dailySales = $this->getDailySalesData();
        $analytics = $this->getPageAnalytics();
        $currency = $ticketTypes[0]['currency'] ?? 'RON';
        $soldPercentage = $totalCapacity > 0 ? round(($totalSold / $totalCapacity) * 100, 1) : 0;
    @endphp

    {{-- Event Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ $this->record->getTranslation('title', app()->getLocale()) ?? $this->record->title }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            @if($this->record->event_date)
                {{ $this->record->event_date->format('d M Y') }}
            @elseif($this->record->range_start_date)
                {{ $this->record->range_start_date->format('d M Y') }} - {{ $this->record->range_end_date?->format('d M Y') }}
            @endif
            @if($this->record->venue)
                · {{ $this->record->venue->getTranslation('name', app()->getLocale()) }}
            @endif
        </p>
    </div>

    {{-- Key Metrics Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {{-- Revenue --}}
        <div class="p-5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Revenue</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalRevenue, 0) }} <span class="text-base font-normal text-gray-500">{{ $currency }}</span></p>
        </div>

        {{-- Tickets Sold --}}
        <div class="p-5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tickets Sold</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalSold) }}</p>
            <div class="mt-2 flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $soldPercentage }}%"></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $soldPercentage }}%</span>
            </div>
        </div>

        {{-- Orders --}}
        <div class="p-5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Orders</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($orderStats['total']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $orderStats['paid'] }} completed</p>
        </div>

        {{-- Capacity --}}
        <div class="p-5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Capacity</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalCapacity) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($totalCapacity - $totalSold) }} available</p>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Ticket Types --}}
        <div class="lg:col-span-2 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-sm font-medium text-gray-900 dark:text-white">Ticket Types</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Price</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sold</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Revenue</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ticketTypes as $type)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                <td class="px-5 py-4">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $type['name'] }}</span>
                                    @if($type['status'] !== 'active')
                                        <span class="ml-2 px-1.5 py-0.5 text-xs rounded {{ $type['status'] === 'hidden' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' }}">{{ ucfirst($type['status']) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right text-gray-600 dark:text-gray-300">{{ $type['price'] }} {{ $type['currency'] }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $type['sold'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">/ {{ $type['total'] }}</span>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-emerald-600 dark:text-emerald-400">{{ number_format($type['revenue'], 0) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $type['percentage'] >= 80 ? 'bg-emerald-500' : ($type['percentage'] >= 50 ? 'bg-amber-500' : 'bg-blue-500') }}" style="width: {{ $type['percentage'] }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-8 text-right">{{ $type['percentage'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No ticket types configured</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Order Breakdown --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-sm font-medium text-gray-900 dark:text-white">Order Status</h2>
            </div>
            <div class="p-5 space-y-4">
                @php
                    $statuses = [
                        ['label' => 'Completed', 'value' => $orderStats['paid'], 'color' => 'emerald'],
                        ['label' => 'Pending', 'value' => $orderStats['pending'], 'color' => 'amber'],
                        ['label' => 'Cancelled', 'value' => $orderStats['cancelled'], 'color' => 'red'],
                        ['label' => 'Refunded', 'value' => $orderStats['refunded'], 'color' => 'gray'],
                    ];
                @endphp
                @foreach($statuses as $status)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-{{ $status['color'] }}-500"></span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $status['label'] }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $status['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Sales Chart --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <h2 class="text-sm font-medium text-gray-900 dark:text-white">Sales Over Time</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Last 30 days</p>
        </div>
        <div class="p-5">
            <div style="height: 280px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Analytics Section --}}
    @if($analytics['available'] ?? false)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-sm font-medium text-gray-900 dark:text-white">Page Analytics</h2>
            </div>
            <div class="p-5">
                {{-- Main Stats --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Page Views</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ number_format($analytics['total_views'] ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Unique Visitors</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ number_format($analytics['unique_sessions'] ?? 0) }}</p>
                    </div>
                    @if(!empty($analytics['top_sources']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Traffic Sources</p>
                            @foreach(array_slice($analytics['top_sources'], 0, 3, true) as $source => $count)
                                <div class="flex justify-between text-sm py-0.5">
                                    <span class="text-gray-600 dark:text-gray-300 truncate max-w-[120px]">{{ $source }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($analytics['devices']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Devices</p>
                            @foreach(array_slice($analytics['devices'], 0, 3, true) as $device => $count)
                                <div class="flex justify-between text-sm py-0.5">
                                    <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($device ?? 'Unknown') }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Additional Stats Row --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    @if(!empty($analytics['top_referrers']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Top Referrers</p>
                            @foreach(array_slice($analytics['top_referrers'], 0, 3, true) as $referrer => $count)
                                <div class="flex justify-between text-sm py-0.5">
                                    <span class="text-gray-600 dark:text-gray-300 truncate max-w-[120px]">{{ parse_url($referrer, PHP_URL_HOST) ?: $referrer ?: 'Direct' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($analytics['top_countries']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Top Countries</p>
                            @foreach(array_slice($analytics['top_countries'], 0, 3, true) as $country => $count)
                                <div class="flex justify-between text-sm py-0.5">
                                    <span class="text-gray-600 dark:text-gray-300">{{ $country }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-sm font-medium text-gray-900 dark:text-white">Page Analytics</h2>
            </div>
            <div class="p-5 text-center text-gray-500 dark:text-gray-400">
                <p>No analytics data available yet.</p>
                <p class="text-sm mt-1">Page view tracking will appear here once visitors view this event page.</p>
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;

            const chartData = @json($dailySales);
            const isDark = document.documentElement.classList.contains('dark');

            new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                color: isDark ? '#6b7280' : '#9ca3af',
                                font: { size: 10 }
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: {
                                color: isDark ? '#374151' : '#f3f4f6',
                            },
                            border: { display: false },
                            ticks: {
                                color: isDark ? '#6b7280' : '#9ca3af',
                                font: { size: 10 },
                                precision: 0,
                            }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
