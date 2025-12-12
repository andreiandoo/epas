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
                        Make sure your website is properly connected and visitors are browsing your site.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Main Grid --}}
        <div class="grid grid-cols-12 gap-6">
            {{-- Left Panel - Real-time Stats --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="p-6 text-white shadow-xl bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-700 rounded-2xl" wire:poll.10s="refreshRealtime">
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-sm font-medium tracking-wider text-purple-100 uppercase">Real-time</span>
                        @if($hasTrackingData)
                            <span class="flex items-center px-2 py-1 text-xs text-white rounded-full bg-green-500/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>
                                LIVE
                            </span>
                        @else
                            <span class="flex items-center px-2 py-1 text-xs text-purple-200 rounded-full bg-white/10">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 mr-1.5"></span>
                                No data
                            </span>
                        @endif
                    </div>

                    <div class="mb-8 text-center">
                        <div class="mb-2 font-black text-transparent text-7xl bg-gradient-to-r from-white to-purple-200 bg-clip-text">
                            {{ $realtimeData['active_users'] }}
                        </div>
                        <div class="text-sm text-purple-200">visitors right now</div>
                    </div>

                    {{-- Activity by Time (Last 30 minutes) --}}
                    <div class="mb-6">
                        <div class="mb-3 text-xs tracking-wider text-purple-200 uppercase">Activity (last 30 min)</div>
                        <div class="flex items-end justify-between h-16 gap-0.5">
                            @foreach($realtimeData['users_per_minute'] as $count)
                                @php $height = max(8, ($count / max(1, max($realtimeData['users_per_minute']))) * 100); @endphp
                                <div class="flex-1 transition-all rounded-t bg-white/30 hover:bg-white/50"
                                     style="height: {{ $height }}%"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between mt-2 text-xs text-purple-200">
                            <span>30 min ago</span>
                            <span>Now</span>
                        </div>
                    </div>

                    {{-- Top Active Pages --}}
                    <div>
                        <div class="mb-3 text-xs tracking-wider text-purple-200 uppercase">Top Active Pages</div>
                        <div class="space-y-2">
                            @foreach($realtimeData['active_pages'] as $page)
                                <div class="flex items-center justify-between px-3 py-2 text-sm rounded-lg bg-white/10">
                                    <span class="flex-1 truncate text-white/90">{{ $page['path'] }}</span>
                                    <span class="font-bold ml-2 bg-white/20 px-2 py-0.5 rounded text-xs">{{ $page['users'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Real-time Events --}}
                <div class="p-6 mt-6 bg-white border border-gray-200 shadow-lg dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Live Activity</h3>
                        @if($hasTrackingData)
                            <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded dark:bg-green-900/50 dark:text-green-300">Live</span>
                        @else
                            <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded dark:bg-gray-700">Awaiting data</span>
                        @endif
                    </div>
                    <div class="space-y-3 overflow-y-auto max-h-64">
                        @foreach($realtimeData['recent_events'] as $event)
                            <div class="flex items-start gap-3 p-2 text-sm transition-colors rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
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
                                    <p class="font-medium text-gray-900 truncate dark:text-white">{{ $event['description'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event['location'] }} · {{ $event['time'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Device & Browser Stats --}}
                <div class="grid grid-cols-1 gap-6">
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Devices</h3>
                            @if($deviceStats['hasData'])
                                <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                            @else
                                <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                            @endif
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="devicesChart"></canvas>
                        </div>
                    </div>
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Browsers</h3>
                            @if($browserStats['hasData'])
                                <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                            @else
                                <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                            @endif
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="browsersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Panel - Main Dashboard --}}
            <div class="col-span-12 space-y-6 lg:col-span-8">
                {{-- Revenue Stats (Real Data) --}}
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
                            @if($metrics['revenue_change'] != 0)
                                <div class="flex items-center gap-1 mt-2 text-xs text-emerald-100">
                                    @if($metrics['revenue_change'] > 0)
                                        <x-heroicon-s-arrow-trending-up class="w-3 h-3" />
                                    @else
                                        <x-heroicon-s-arrow-trending-down class="w-3 h-3" />
                                    @endif
                                    {{ $metrics['revenue_change'] > 0 ? '+' : '' }}{{ number_format($metrics['revenue_change'], 1) }}%
                                </div>
                            @endif
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

                {{-- Traffic Metrics --}}
                <div>
                    <h3 class="flex items-center gap-2 mb-3 text-sm font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        @if($hasTrackingData)
                            <x-heroicon-s-check-badge class="w-4 h-4 text-green-500" />
                            Traffic Data
                        @else
                            <x-heroicon-s-sparkles class="w-4 h-4 text-purple-500" />
                            Traffic Data (awaiting visitors)
                        @endif
                    </h3>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                            <div class="text-xs tracking-wider text-gray-500 uppercase dark:text-gray-400">Users Today</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($realtimeData['total_users']) }}</div>
                            @if($realtimeData['users_change'] != 0)
                                <div class="flex items-center mt-2 text-xs">
                                    <span class="{{ $realtimeData['users_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} flex items-center">
                                        @if($realtimeData['users_change'] >= 0)
                                            <x-heroicon-s-arrow-trending-up class="w-3 h-3 mr-0.5" />
                                        @else
                                            <x-heroicon-s-arrow-trending-down class="w-3 h-3 mr-0.5" />
                                        @endif
                                        {{ $realtimeData['users_change'] >= 0 ? '+' : '' }}{{ number_format($realtimeData['users_change'], 1) }}% vs yesterday
                                    </span>
                                </div>
                            @else
                                <div class="mt-2 text-xs text-gray-500">vs yesterday</div>
                            @endif
                        </div>
                        <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                            <div class="text-xs tracking-wider text-gray-500 uppercase dark:text-gray-400">Sessions Today</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($realtimeData['total_sessions']) }}</div>
                            @if($realtimeData['sessions_change'] != 0)
                                <div class="flex items-center mt-2 text-xs">
                                    <span class="{{ $realtimeData['sessions_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} flex items-center">
                                        @if($realtimeData['sessions_change'] >= 0)
                                            <x-heroicon-s-arrow-trending-up class="w-3 h-3 mr-0.5" />
                                        @else
                                            <x-heroicon-s-arrow-trending-down class="w-3 h-3 mr-0.5" />
                                        @endif
                                        {{ $realtimeData['sessions_change'] >= 0 ? '+' : '' }}{{ number_format($realtimeData['sessions_change'], 1) }}% vs yesterday
                                    </span>
                                </div>
                            @else
                                <div class="mt-2 text-xs text-gray-500">vs yesterday</div>
                            @endif
                        </div>
                        <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                            <div class="text-xs tracking-wider text-gray-500 uppercase dark:text-gray-400">Bounce Rate</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($realtimeData['bounce_rate'], 1) }}%</div>
                            <div class="mt-2 text-xs text-gray-500">today's sessions</div>
                        </div>
                        <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                            <div class="text-xs tracking-wider text-gray-500 uppercase dark:text-gray-400">Avg Duration</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $realtimeData['avg_duration'] }}</div>
                            <div class="mt-2 text-xs text-gray-500">today's sessions</div>
                        </div>
                    </div>
                </div>

                {{-- Charts Row --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Revenue Chart --}}
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Revenue Trend</h3>
                                <p class="text-xs text-gray-500">Based on completed orders</p>
                            </div>
                            <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real Data</span>
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    {{-- Orders Chart --}}
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Orders Over Time</h3>
                                <p class="text-xs text-gray-500">Daily order volume</p>
                            </div>
                            <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real Data</span>
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="ordersChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Bottom Row --}}
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    {{-- Top Events (Real) --}}
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

                    {{-- Traffic Sources --}}
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Traffic Sources</h3>
                            @if($hasTrackingData && count($trafficSources) > 0)
                                <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                            @else
                                <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                            @endif
                        </div>
                        <div class="space-y-3">
                            @foreach($trafficSources as $source)
                                <div>
                                    <div class="flex items-center justify-between mb-1 text-sm">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $source['name'] }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $source['percentage'] }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
                                        <div class="h-full rounded-full {{ $source['color'] }}" style="width: {{ $source['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Geographic --}}
                    <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Visitors by Country</h3>
                            @if($hasTrackingData && count($geoData) > 0)
                                <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                            @else
                                <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                            @endif
                        </div>
                        <div class="space-y-2">
                            @foreach($geoData as $country)
                                <div class="flex items-center justify-between p-2 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">{{ $country['flag'] }}</span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ $country['name'] }}</span>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($country['users']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        

        {{-- Pages Section --}}
        @php
            $landingPages = $this->getLandingPages();
            $exitPages = $this->getExitPages();
        @endphp
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            {{-- Top Pages Visited --}}
            <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Top Pages</h3>
                    @if(count($pageViews) > 0)
                        <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                    @else
                        <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                    @endif
                </div>
                <div class="space-y-2 overflow-y-auto max-h-64">
                    @forelse($pageViews as $page)
                        <div class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <span class="flex-1 text-gray-700 truncate dark:text-gray-300" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ number_format($page['views']) }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-center text-gray-500 dark:text-gray-400">No page view data yet</p>
                    @endforelse
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                {{-- Landing Pages (Entry Pages) --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Landing Pages</h3>
                        @if(count($landingPages) > 0)
                            <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                        @else
                            <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                        @endif
                    </div>
                    <div class="space-y-2 overflow-y-auto max-h-64">
                        @forelse($landingPages as $page)
                            <div class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <span class="flex-1 text-gray-700 truncate dark:text-gray-300" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ number_format($page['sessions']) }}</span>
                            </div>
                        @empty
                            <p class="py-4 text-sm text-center text-gray-500 dark:text-gray-400">No landing page data yet</p>
                        @endforelse
                    </div>
                </div>

                {{-- Exit Pages --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Exit Pages</h3>
                        @if(count($exitPages) > 0)
                            <span class="px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">Real</span>
                        @else
                            <span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700">No data</span>
                        @endif
                    </div>
                    <div class="space-y-2 overflow-y-auto max-h-64">
                        @forelse($exitPages as $page)
                            <div class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <span class="flex-1 text-gray-700 truncate dark:text-gray-300" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ number_format($page['sessions']) }}</span>
                            </div>
                        @empty
                            <p class="py-4 text-sm text-center text-gray-500 dark:text-gray-400">No exit page data yet</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js Script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            // Store chart instances to prevent duplicates
            window.analyticsDashboardCharts = window.analyticsDashboardCharts || {};

            function initCharts() {
                const isDark = document.documentElement.classList.contains('dark');
                const textColor = isDark ? '#9CA3AF' : '#6B7280';
                const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';

                // Revenue Chart
                const revenueCtx = document.getElementById('revenueChart');
                if (revenueCtx && !window.analyticsDashboardCharts.revenue) {
                    window.analyticsDashboardCharts.revenue = new Chart(revenueCtx, {
                        type: 'line',
                        data: {
                            labels: @json($salesData['labels']),
                            datasets: [{
                                label: 'Revenue ({{ $currencySymbol }})',
                                data: @json($salesData['revenue']),
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor } }
                            }
                        }
                    });
                }

                // Orders Chart
                const ordersCtx = document.getElementById('ordersChart');
                if (ordersCtx && !window.analyticsDashboardCharts.orders) {
                    window.analyticsDashboardCharts.orders = new Chart(ordersCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($salesData['labels']),
                            datasets: [{
                                label: 'Orders',
                                data: @json($salesData['orders']),
                                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor } }
                            }
                        }
                    });
                }

                // Devices Chart
                const devicesCtx = document.getElementById('devicesChart');
                if (devicesCtx && !window.analyticsDashboardCharts.devices) {
                    window.analyticsDashboardCharts.devices = new Chart(devicesCtx, {
                        type: 'doughnut',
                        data: {
                            labels: @json($deviceStats['labels']),
                            datasets: [{
                                data: @json($deviceStats['data']),
                                backgroundColor: @json($deviceStats['colors']),
                                borderWidth: 0,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { color: textColor } }
                            }
                        }
                    });
                }

                // Browsers Chart
                const browsersCtx = document.getElementById('browsersChart');
                if (browsersCtx && !window.analyticsDashboardCharts.browsers) {
                    window.analyticsDashboardCharts.browsers = new Chart(browsersCtx, {
                        type: 'doughnut',
                        data: {
                            labels: @json($browserStats['labels']),
                            datasets: [{
                                data: @json($browserStats['data']),
                                backgroundColor: @json($browserStats['colors']),
                                borderWidth: 0,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { color: textColor } }
                            }
                        }
                    });
                }
            }

            // Initialize charts when DOM is ready or immediately if already loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCharts);
            } else {
                // Small delay to ensure canvases are in the DOM
                setTimeout(initCharts, 100);
            }

            // Clean up charts when navigating away (for SPA navigation)
            document.addEventListener('livewire:navigating', function() {
                Object.keys(window.analyticsDashboardCharts).forEach(function(key) {
                    if (window.analyticsDashboardCharts[key]) {
                        window.analyticsDashboardCharts[key].destroy();
                    }
                });
                window.analyticsDashboardCharts = {};
            });
        })();
    </script>
</x-filament-panels::page>
