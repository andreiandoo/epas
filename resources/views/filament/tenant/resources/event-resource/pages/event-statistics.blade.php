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
    @endphp

    {{-- Event Title --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold dark:text-white">
            {{ $this->record->getTranslation('title', app()->getLocale()) ?? $this->record->title }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if($this->record->event_date)
                {{ $this->record->event_date->format('d M Y') }}
            @elseif($this->record->range_start_date)
                {{ $this->record->range_start_date->format('d M Y') }} - {{ $this->record->range_end_date?->format('d M Y') }}
            @endif
            @if($this->record->venue)
                &bull; {{ $this->record->venue->getTranslation('name', app()->getLocale()) }}
            @endif
        </p>
    </div>

    {{-- Stats Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Revenue --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="rounded-lg bg-success-50 p-3 dark:bg-success-400/10">
                    <x-heroicon-o-banknotes class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($totalRevenue, 2) }} {{ $currency }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Tickets Sold --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="rounded-lg bg-primary-50 p-3 dark:bg-primary-400/10">
                    <x-heroicon-o-ticket class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tickets Sold</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($totalSold) }} / {{ number_format($totalCapacity) }}
                    </p>
                    @if($totalCapacity > 0)
                        <div class="mt-1 h-2 w-full bg-gray-200 rounded-full dark:bg-gray-700">
                            <div class="h-2 bg-primary-600 rounded-full" style="width: {{ min(100, ($totalSold / $totalCapacity) * 100) }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Total Orders --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="rounded-lg bg-info-50 p-3 dark:bg-info-400/10">
                    <x-heroicon-o-shopping-cart class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($orderStats['total']) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Completed Orders --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="rounded-lg bg-success-50 p-3 dark:bg-success-400/10">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($orderStats['paid']) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Pending: {{ $orderStats['pending'] }} &bull;
                        Cancelled: {{ $orderStats['cancelled'] }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Ticket Types Table --}}
        <div class="lg:col-span-2">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <x-heroicon-o-ticket class="h-5 w-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Ticket Types</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Price</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Sold</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Available</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Revenue</th>
                                <th class="px-6 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($ticketTypes as $type)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                        {{ $type['name'] }}
                                        @if($type['status'] !== 'active')
                                            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $type['status'] === 'hidden' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400' }}">
                                                {{ ucfirst($type['status']) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $type['price'] }} {{ $type['currency'] }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($type['sold']) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($type['available']) }} / {{ number_format($type['total']) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-success-600 dark:text-success-400">
                                        {{ number_format($type['revenue'], 2) }} {{ $type['currency'] }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="w-20 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                                <div class="h-2 rounded-full {{ $type['percentage'] >= 80 ? 'bg-success-500' : ($type['percentage'] >= 50 ? 'bg-warning-500' : 'bg-primary-500') }}"
                                                     style="width: {{ $type['percentage'] }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 w-10">{{ $type['percentage'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No ticket types configured for this event.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($ticketTypes) > 0)
                            <tfoot class="bg-gray-50 dark:bg-white/5 font-semibold">
                                <tr>
                                    <td class="px-6 py-3 text-gray-900 dark:text-white">Total</td>
                                    <td class="px-6 py-3"></td>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-white">{{ number_format($totalSold) }}</td>
                                    <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($totalCapacity - $totalSold) }} / {{ number_format($totalCapacity) }}</td>
                                    <td class="px-6 py-3 text-right text-success-600 dark:text-success-400">{{ number_format($totalRevenue, 2) }} {{ $currency }}</td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Order Status Breakdown --}}
        <div>
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <x-heroicon-o-shopping-cart class="h-5 w-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Order Status</h3>
                </div>
                <div class="p-6 space-y-4">
                    @php
                        $statusData = [
                            ['label' => 'Paid / Confirmed', 'value' => $orderStats['paid'], 'color' => 'success'],
                            ['label' => 'Pending', 'value' => $orderStats['pending'], 'color' => 'warning'],
                            ['label' => 'Cancelled', 'value' => $orderStats['cancelled'], 'color' => 'danger'],
                            ['label' => 'Refunded', 'value' => $orderStats['refunded'], 'color' => 'gray'],
                        ];
                    @endphp
                    @foreach($statusData as $status)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-{{ $status['color'] }}-500"></span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $status['label'] }}</span>
                            </div>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $status['value'] }}</span>
                        </div>
                    @endforeach
                    <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Total Orders</span>
                            <span class="font-bold text-lg text-gray-900 dark:text-white">{{ $orderStats['total'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sales Chart --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
            <x-heroicon-o-chart-bar class="h-5 w-5 text-gray-400" />
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Daily Sales (Last 30 Days)</h3>
        </div>
        <div class="p-6">
            <div style="height: 300px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Page Analytics (if available) --}}
    @if($analytics['available'] ?? false)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                <x-heroicon-o-eye class="h-5 w-5 text-gray-400" />
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Page Analytics</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Page Views</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($analytics['total_views'] ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Unique Visitors</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($analytics['unique_sessions'] ?? 0) }}</p>
                    </div>
                    @if(!empty($analytics['top_referrers']))
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Top Referrers</p>
                            <ul class="space-y-1">
                                @foreach($analytics['top_referrers'] as $referrer => $count)
                                    <li class="flex justify-between text-sm">
                                        <span class="text-gray-700 dark:text-gray-300 truncate max-w-[150px]">{{ $referrer ?: 'Direct' }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($analytics['top_countries']))
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Top Countries</p>
                            <ul class="space-y-1">
                                @foreach($analytics['top_countries'] as $country => $count)
                                    <li class="flex justify-between text-sm">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $country }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Chart.js Script --}}
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
                            position: 'top',
                            labels: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                usePointStyle: true,
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: {
                                color: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
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
