<x-filament-panels::page>
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
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Attribution Model</label>
                <select wire:model.live="attributionModel" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                    <option value="first_touch">First Touch</option>
                    <option value="last_touch">Last Touch</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Conversions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_conversions'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($summary['total_revenue'] ?? 0, 2) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Order Value</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($summary['avg_order_value'] ?? 0, 2) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Period</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['period_days'] ?? 0 }} days</div>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- First Touch Attribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">First Touch Attribution</h3>
                <p class="text-sm text-gray-500">Credit goes to the channel that first brought the customer</p>
            </div>
            <div class="p-4">
                @forelse($firstTouchAttribution as $item)
                    <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $item['channel'] }}</span>
                            <span class="text-xs text-gray-500 block">{{ number_format($item['conversions']) }} conversions</span>
                        </div>
                        <span class="text-lg font-bold text-green-600 dark:text-green-400">${{ number_format($item['revenue'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">No data available</p>
                @endforelse
            </div>
        </div>

        {{-- Last Touch Attribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Last Touch Attribution</h3>
                <p class="text-sm text-gray-500">Credit goes to the last channel before conversion</p>
            </div>
            <div class="p-4">
                @forelse($lastTouchAttribution as $item)
                    <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $item['channel'] }}</span>
                            <span class="text-xs text-gray-500 block">{{ number_format($item['conversions']) }} conversions</span>
                        </div>
                        <span class="text-lg font-bold text-green-600 dark:text-green-400">${{ number_format($item['revenue'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">No data available</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Channel Comparison --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Channel Comparison</h3>
            <p class="text-sm text-gray-500">Compare first-touch vs last-touch performance by channel</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Channel</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">First Touch Conv.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">First Touch Rev.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Last Touch Conv.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Last Touch Rev.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Diff</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($channelComparison as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['channel'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($row['first_touch_conversions']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($row['first_touch_revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($row['last_touch_conversions']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($row['last_touch_revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-right {{ $row['difference'] > 0 ? 'text-green-600' : ($row['difference'] < 0 ? 'text-red-600' : 'text-gray-500') }}">
                                {{ $row['difference'] > 0 ? '+' : '' }}{{ number_format($row['difference']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Second Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Conversion Paths --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Conversion Paths</h3>
            </div>
            <div class="p-4">
                @forelse($conversionPaths as $path)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $path['path'] }}</span>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($path['count']) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No paths found</p>
                @endforelse
            </div>
        </div>

        {{-- Assisted Conversions --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assisted Conversions</h3>
                <p class="text-xs text-gray-500">Channels that helped but didn't get last-touch credit</p>
            </div>
            <div class="p-4">
                @forelse($assistedConversions as $assist)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $assist['channel'] }}</span>
                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ number_format($assist['assisted_conversions']) }} assists</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No assisted conversions</p>
                @endforelse
            </div>
        </div>

        {{-- Time to Conversion --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Time to Conversion</h3>
            </div>
            <div class="p-4">
                @forelse($timeToConversion as $time)
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $time['time_range'] }}</span>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($time['count']) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No data</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Touchpoint Analysis --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Touchpoint Analysis</h3>
            <p class="text-sm text-gray-500">Average engagement before conversion</p>
        </div>
        <div class="p-4 grid grid-cols-2 gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($touchpointAnalysis['avg_sessions'] ?? 0, 1) }}</div>
                <div class="text-sm text-gray-500">Avg Sessions Before Purchase</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($touchpointAnalysis['avg_page_views'] ?? 0, 1) }}</div>
                <div class="text-sm text-gray-500">Avg Page Views Before Purchase</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
