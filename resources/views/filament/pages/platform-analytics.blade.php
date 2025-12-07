<x-filament-panels::page>
    {{-- Enable real-time polling for active visitors and recent conversions --}}
    <div wire:poll.30s="refreshRealTimeData"></div>

    {{-- Filters --}}
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="date" wire:model.live="endDate" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tenant</label>
                <select wire:model.live="tenantId" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                    @foreach($this->getTenantOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <div class="text-right w-full">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        {{ $activeVisitors }} active now
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Overview Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sessions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($overview['total_sessions'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique Visitors</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($overview['total_visitors'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Page Views</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($overview['total_page_views'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Conversions</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($overview['total_purchases'] ?? 0) }}</div>
            <div class="text-xs text-gray-500">{{ $overview['conversion_rate'] ?? 0 }}% rate</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($overview['total_revenue'] ?? 0, 2) }}</div>
            <div class="text-xs text-gray-500">AOV: ${{ number_format($overview['avg_order_value'] ?? 0, 2) }}</div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Traffic Sources --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Traffic Sources</h3>
            </div>
            <div class="p-4">
                @forelse($trafficSources as $source)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $source['source'] }}</span>
                            <span class="text-xs text-gray-500 ml-2">{{ number_format($source['visitors']) }} visitors</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($source['sessions']) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No traffic data</p>
                @endforelse
            </div>
        </div>

        {{-- Conversion by Source --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue by Source</h3>
            </div>
            <div class="p-4">
                @forelse($conversionStats as $stat)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $stat['source'] }}</span>
                            <span class="text-xs text-gray-500 ml-2">{{ number_format($stat['conversions']) }} sales</span>
                        </div>
                        <span class="text-sm font-semibold text-green-600 dark:text-green-400">${{ number_format($stat['revenue'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No conversion data</p>
                @endforelse
            </div>
        </div>

        {{-- Customer Metrics --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Metrics</h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Total Customers</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($customerMetrics['total'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Identified (with email)</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($customerMetrics['with_email'] ?? 0) }} ({{ $customerMetrics['identification_rate'] ?? 0 }}%)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Purchasers</span>
                    <span class="font-semibold text-green-600 dark:text-green-400">{{ number_format($customerMetrics['purchasers'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Repeat Buyers</span>
                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ number_format($customerMetrics['repeat_buyers'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">High Value ($500+)</span>
                    <span class="font-semibold text-purple-600 dark:text-purple-400">{{ number_format($customerMetrics['high_value'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Avg RFM Score</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($customerMetrics['avg_rfm_score'] ?? 0, 1) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400">Total Lifetime Value</span>
                    <span class="font-bold text-green-600 dark:text-green-400">${{ number_format($customerMetrics['total_lifetime_value'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Second Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Ad Platform Stats --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Platform Ad Accounts</h3>
            </div>
            <div class="p-4">
                @forelse($adPlatformStats as $account)
                    <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $account['platform'] }}</span>
                            <span class="text-xs text-gray-500 block">{{ $account['account_name'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block font-semibold text-gray-900 dark:text-white">{{ number_format($account['conversions']) }} conversions</span>
                            <span class="text-sm text-green-600 dark:text-green-400">${{ number_format($account['revenue'], 2) }}</span>
                        </div>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $account['token_status'] === 'valid' ? 'bg-green-100 text-green-800' : ($account['token_status'] === 'expiring' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($account['token_status']) }}
                        </span>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <p class="text-gray-500 dark:text-gray-400 mb-2">No ad accounts configured</p>
                        <a href="{{ route('filament.admin.resources.platform-ad-accounts.create') }}" class="text-primary-600 hover:text-primary-800 text-sm font-medium">
                            + Add Ad Account
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Conversions --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Conversions</h3>
            </div>
            <div class="p-4">
                @forelse($recentConversions as $conversion)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">${{ number_format($conversion['value'], 2) }}</span>
                            <span class="text-xs text-gray-500 block">{{ $conversion['customer'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $conversion['source'] }}</span>
                            <span class="text-xs text-gray-500 block">{{ $conversion['time_ago'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent conversions</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Third Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        {{-- Tenant Breakdown (only shown when viewing all tenants) --}}
        @if(empty($tenantId) && !empty($tenantBreakdown))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue by Tenant</h3>
                </div>
                <div class="p-4">
                    @foreach($tenantBreakdown as $tenant)
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $tenant['tenant_name'] }}</span>
                                <span class="text-xs text-gray-500 ml-2">{{ number_format($tenant['purchases']) }} sales</span>
                            </div>
                            <span class="text-sm font-semibold text-green-600 dark:text-green-400">${{ number_format($tenant['revenue'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Device Breakdown --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Device Distribution</h3>
            </div>
            <div class="p-4">
                @forelse($deviceData as $device)
                    @php
                        $total = collect($deviceData)->sum('count');
                        $percentage = $total > 0 ? round(($device['count'] / $total) * 100, 1) : 0;
                        $icon = match($device['device_type']) {
                            'mobile' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
                            'tablet' => 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                            default => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                        };
                    @endphp
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white capitalize">{{ $device['device_type'] ?? 'Unknown' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($device['count']) }}</span>
                            <span class="text-xs text-gray-500 ml-1">({{ $percentage }}%)</span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No device data</p>
                @endforelse
            </div>
        </div>

        {{-- Geographic Distribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Countries</h3>
            </div>
            <div class="p-4">
                @forelse($geoData as $geo)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="flex items-center">
                            <span class="text-lg mr-2">{{ country_flag($geo['country_code'] ?? '') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $geo['country_name'] ?? $geo['country_code'] }}</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($geo['visitors']) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No geo data</p>
                @endforelse
            </div>
        </div>

        {{-- Top Pages --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow {{ empty($tenantId) && !empty($tenantBreakdown) ? '' : 'lg:col-span-1' }}">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Pages</h3>
            </div>
            <div class="p-4">
                @forelse($topPages as $page)
                    <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[200px]" title="{{ $page['page_url'] }}">
                                {{ $page['page_title'] ?: $page['page_url'] }}
                            </span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($page['views']) }}</span>
                        </div>
                        <span class="text-xs text-gray-500 truncate block max-w-full">{{ $page['page_url'] }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No page data</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
