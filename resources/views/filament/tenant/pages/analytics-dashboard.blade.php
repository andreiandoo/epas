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
        <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold mb-2">Analytics Dashboard</h2>
                    <p class="text-indigo-100 text-sm max-w-2xl">
                        Track your sales performance, ticket revenue, and visitor engagement in real-time.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 backdrop-blur rounded-lg text-sm">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
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
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex gap-3">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium text-blue-900 dark:text-blue-100">Tracking not yet active</p>
                    <p class="text-blue-700 dark:text-blue-300 mt-1">
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
                <div class="bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-700 rounded-2xl p-6 text-white shadow-xl" wire:poll.10s="refreshRealtime">
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-purple-100 text-sm font-medium uppercase tracking-wider">Real-time</span>
                        @if($hasTrackingData)
                            <span class="flex items-center text-xs text-white bg-green-500/80 px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>
                                LIVE
                            </span>
                        @else
                            <span class="flex items-center text-xs text-purple-200 bg-white/10 px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 mr-1.5"></span>
                                No data
                            </span>
                        @endif
                    </div>

                    <div class="text-center mb-8">
                        <div class="text-7xl font-black mb-2 bg-gradient-to-r from-white to-purple-200 bg-clip-text text-transparent">
                            {{ $realtimeData['active_users'] }}
                        </div>
                        <div class="text-purple-200 text-sm">visitors right now</div>
                    </div>

                    {{-- Activity by Time (Last 30 minutes) --}}
                    <div class="mb-6">
                        <div class="text-xs text-purple-200 uppercase tracking-wider mb-3">Activity (last 30 min)</div>
                        <div class="flex items-end justify-between h-16 gap-0.5">
                            @foreach($realtimeData['users_per_minute'] as $count)
                                @php $height = max(8, ($count / max(1, max($realtimeData['users_per_minute']))) * 100); @endphp
                                <div class="flex-1 bg-white/30 rounded-t transition-all hover:bg-white/50"
                                     style="height: {{ $height }}%"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-xs text-purple-200 mt-2">
                            <span>30 min ago</span>
                            <span>Now</span>
                        </div>
                    </div>

                    {{-- Top Active Pages --}}
                    <div>
                        <div class="text-xs text-purple-200 uppercase tracking-wider mb-3">Top Active Pages</div>
                        <div class="space-y-2">
                            @foreach($realtimeData['active_pages'] as $page)
                                <div class="flex items-center justify-between text-sm bg-white/10 rounded-lg px-3 py-2">
                                    <span class="truncate flex-1 text-white/90">{{ $page['path'] }}</span>
                                    <span class="font-bold ml-2 bg-white/20 px-2 py-0.5 rounded text-xs">{{ $page['users'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Real-time Events --}}
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Live Activity</h3>
                        @if($hasTrackingData)
                            <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded">Live</span>
                        @else
                            <span class="text-xs text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Awaiting data</span>
                        @endif
                    </div>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        @foreach($realtimeData['recent_events'] as $event)
                            <div class="flex items-start gap-3 text-sm p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
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
                {{-- Revenue Stats (Real Data) --}}
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <x-heroicon-s-check-badge class="w-4 h-4 text-green-500" />
                        Real Platform Data
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl p-5 text-white shadow-lg">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                                    <x-heroicon-s-banknotes class="w-5 h-5" />
                                </div>
                                <span class="text-xs text-emerald-100 uppercase tracking-wider">Revenue</span>
                            </div>
                            <div class="text-3xl font-black">{{ $currencySymbol }}{{ number_format($metrics['total_revenue'], 0) }}</div>
                            @if($metrics['revenue_change'] != 0)
                                <div class="text-xs text-emerald-100 mt-2 flex items-center gap-1">
                                    @if($metrics['revenue_change'] > 0)
                                        <x-heroicon-s-arrow-trending-up class="w-3 h-3" />
                                    @else
                                        <x-heroicon-s-arrow-trending-down class="w-3 h-3" />
                                    @endif
                                    {{ $metrics['revenue_change'] > 0 ? '+' : '' }}{{ number_format($metrics['revenue_change'], 1) }}%
                                </div>
                            @endif
                        </div>
                        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-5 text-white shadow-lg">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                                    <x-heroicon-s-shopping-bag class="w-5 h-5" />
                                </div>
                                <span class="text-xs text-blue-100 uppercase tracking-wider">Orders</span>
                            </div>
                            <div class="text-3xl font-black">{{ number_format($metrics['total_orders']) }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl p-5 text-white shadow-lg">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                                    <x-heroicon-s-ticket class="w-5 h-5" />
                                </div>
                                <span class="text-xs text-orange-100 uppercase tracking-wider">Tickets</span>
                            </div>
                            <div class="text-3xl font-black">{{ number_format($metrics['total_tickets']) }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl p-5 text-white shadow-lg">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                                    <x-heroicon-s-calculator class="w-5 h-5" />
                                </div>
                                <span class="text-xs text-purple-100 uppercase tracking-wider">Avg Order</span>
                            </div>
                            <div class="text-3xl font-black">{{ $currencySymbol }}{{ number_format($metrics['avg_order_value'], 0) }}</div>
                        </div>
                    </div>
                </div>

                {{-- Traffic Metrics --}}
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        @if($hasTrackingData)
                            <x-heroicon-s-check-badge class="w-4 h-4 text-green-500" />
                            Traffic Data
                        @else
                            <x-heroicon-s-sparkles class="w-4 h-4 text-purple-500" />
                            Traffic Data (awaiting visitors)
                        @endif
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Users Today</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['total_users']) }}</div>
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
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions Today</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['total_sessions']) }}</div>
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
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bounce Rate</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($realtimeData['bounce_rate'], 1) }}%</div>
                            <div class="mt-2 text-xs text-gray-500">today's sessions</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Duration</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $realtimeData['avg_duration'] }}</div>
                            <div class="mt-2 text-xs text-gray-500">today's sessions</div>
                        </div>
                    </div>
                </div>

                {{-- Charts Row --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Revenue Chart --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Revenue Trend</h3>
                                <p class="text-xs text-gray-500">Based on completed orders</p>
                            </div>
                            <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real Data</span>
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    {{-- Orders Chart --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Orders Over Time</h3>
                                <p class="text-xs text-gray-500">Daily order volume</p>
                            </div>
                            <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real Data</span>
                        </div>
                        <div class="h-48" wire:ignore>
                            <canvas id="ordersChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Bottom Row --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Top Events (Real) --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Top Events</h3>
                            <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                        </div>
                        <div class="space-y-3">
                            @forelse($topEvents as $event)
                                <div class="flex items-center justify-between text-sm p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-gray-900 dark:text-white font-medium truncate">{{ $event['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $event['orders'] }} orders</p>
                                    </div>
                                    <span class="font-bold text-green-600 dark:text-green-400 ml-2">{{ $currencySymbol }}{{ number_format($event['revenue'], 0) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 text-center py-4">No sales data yet</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Traffic Sources --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Traffic Sources</h3>
                            @if($hasTrackingData && count($trafficSources) > 0)
                                <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                            @else
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                            @endif
                        </div>
                        <div class="space-y-3">
                            @foreach($trafficSources as $source)
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $source['name'] }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $source['percentage'] }}%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $source['color'] }}" style="width: {{ $source['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Geographic --}}
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Visitors by Country</h3>
                            @if($hasTrackingData && count($geoData) > 0)
                                <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                            @else
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                            @endif
                        </div>
                        <div class="space-y-2">
                            @foreach($geoData as $country)
                                <div class="flex items-center justify-between text-sm p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
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

        {{-- Device & Browser Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Devices</h3>
                    @if($deviceStats['hasData'])
                        <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                    @else
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                    @endif
                </div>
                <div class="h-48" wire:ignore>
                    <canvas id="devicesChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Browsers</h3>
                    @if($browserStats['hasData'])
                        <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                    @else
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                    @endif
                </div>
                <div class="h-48" wire:ignore>
                    <canvas id="browsersChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Pages Section --}}
        @php
            $landingPages = $this->getLandingPages();
            $exitPages = $this->getExitPages();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Top Pages Visited --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Top Pages</h3>
                    @if(count($pageViews) > 0)
                        <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                    @else
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                    @endif
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @forelse($pageViews as $page)
                        <div class="flex items-center justify-between text-sm p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <span class="text-gray-700 dark:text-gray-300 truncate flex-1" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                            <span class="font-medium text-gray-900 dark:text-white ml-2">{{ number_format($page['views']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No page view data yet</p>
                    @endforelse
                </div>
            </div>

            {{-- Landing Pages (Entry Pages) --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Landing Pages</h3>
                    @if(count($landingPages) > 0)
                        <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                    @else
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                    @endif
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @forelse($landingPages as $page)
                        <div class="flex items-center justify-between text-sm p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <span class="text-gray-700 dark:text-gray-300 truncate flex-1" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                            <span class="font-medium text-gray-900 dark:text-white ml-2">{{ number_format($page['sessions']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No landing page data yet</p>
                    @endforelse
                </div>
            </div>

            {{-- Exit Pages --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Exit Pages</h3>
                    @if(count($exitPages) > 0)
                        <span class="text-xs bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-2 py-1 rounded-full">Real</span>
                    @else
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded-full">No data</span>
                    @endif
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @forelse($exitPages as $page)
                        <div class="flex items-center justify-between text-sm p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <span class="text-gray-700 dark:text-gray-300 truncate flex-1" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                            <span class="font-medium text-gray-900 dark:text-white ml-2">{{ number_format($page['sessions']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No exit page data yet</p>
                    @endforelse
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
