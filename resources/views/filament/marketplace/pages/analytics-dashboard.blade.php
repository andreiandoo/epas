<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $salesData = $this->getSalesData();
        $topEvents = $this->getTopEvents();
        $realtimeData = $this->getRealtimeData();
        $trafficSources = $this->getTrafficSources();
        $pageViews = $this->getTopPages();
        $geoData = $this->getGeographicData();
        $deviceStats = $this->getDeviceStats();
        $browserStats = $this->getBrowserStats();
        $currencySymbol = $this->getCurrencySymbol();
        $hasTrackingData = $this->hasTrackingData();
    @endphp

    <div class="space-y-6">
        {{-- Header with Info Banner --}}
        <div class="p-6 text-white shadow-xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 rounded-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="mb-2 text-2xl font-bold">Analytics Dashboard</h2>
                    <p class="max-w-2xl text-sm text-indigo-100">
                        Track your sales performance, ticket revenue, and visitor engagement in real-time.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 backdrop-blur rounded-lg text-sm">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        Live
                    </div>
                    <div class="w-40">
                        {{ $this->form }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Note --}}
        @if(!$hasTrackingData)
        <div class="p-4 border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 rounded-xl">
            <div class="flex gap-3">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium text-blue-900 dark:text-blue-100">Tracking not yet active</p>
                    <p class="mt-1 text-blue-700 dark:text-blue-300">
                        <strong>Revenue & Orders:</strong> Shows real data from your platform sales.<br>
                        <strong>Traffic & Visitors:</strong> Will show real data once visitors start using your website.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Revenue Stats --}}
        <div>
            <h3 class="flex items-center gap-2 mb-3 text-sm font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                <x-heroicon-s-check-badge class="w-4 h-4 text-green-500" />
                Real Platform Data
            </h3>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="p-5 text-white shadow-lg bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
                            <x-heroicon-s-banknotes class="w-5 h-5" />
                        </div>
                        <span class="text-xs tracking-wider uppercase text-emerald-100">Revenue</span>
                    </div>
                    <div class="text-3xl font-black">{{ $currencySymbol }}{{ number_format($metrics['total_revenue'], 0) }}</div>
                </div>
                <div class="p-5 text-white shadow-lg bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
                            <x-heroicon-s-shopping-bag class="w-5 h-5" />
                        </div>
                        <span class="text-xs tracking-wider text-blue-100 uppercase">Orders</span>
                    </div>
                    <div class="text-3xl font-black">{{ number_format($metrics['total_orders']) }}</div>
                </div>
                <div class="p-5 text-white shadow-lg bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
                            <x-heroicon-s-ticket class="w-5 h-5" />
                        </div>
                        <span class="text-xs tracking-wider text-orange-100 uppercase">Tickets</span>
                    </div>
                    <div class="text-3xl font-black">{{ number_format($metrics['total_tickets']) }}</div>
                </div>
                <div class="p-5 text-white shadow-lg bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
                            <x-heroicon-s-calculator class="w-5 h-5" />
                        </div>
                        <span class="text-xs tracking-wider text-purple-100 uppercase">Avg Order</span>
                    </div>
                    <div class="text-3xl font-black">{{ $currencySymbol }}{{ number_format($metrics['avg_order_value'], 0) }}</div>
                </div>
            </div>
        </div>

        {{-- Top Events --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900 dark:text-white">Top Events</h3>
                <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
            </div>
            <div class="space-y-3">
                @forelse($topEvents as $event)
                    <div class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate dark:text-white">{{ $event['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $event['orders'] }} orders</p>
                        </div>
                        <span class="ml-2 font-bold text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($event['revenue'], 0) }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-center text-gray-500">No sales data yet</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</x-filament-panels::page>
