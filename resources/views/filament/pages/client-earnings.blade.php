<x-filament-panels::page>
    {{-- Back to Billing Overview --}}
    <div class="mb-4">
        <a href="{{ \App\Filament\Pages\BillingOverview::getUrl() }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back to Billing Overview
        </a>
    </div>

    {{-- Client Info Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-3 {{ $type === 'marketplace' ? 'bg-purple-100 dark:bg-purple-900/30' : 'bg-blue-100 dark:bg-blue-900/30' }} rounded-lg">
                    @if($type === 'marketplace')
                        <x-heroicon-o-building-storefront class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    @else
                        <x-heroicon-o-building-office class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $client['name'] ?? 'Unknown' }}</h2>
                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ $type === 'marketplace' ? 'Marketplace Client' : 'Tenant' }}</span>
                        @if(!empty($client['domain']))
                            <span>·</span>
                            <span>{{ $client['domain'] }}</span>
                        @endif
                        <span>·</span>
                        <span>Commission: {{ $client['commission_rate'] ?? 0 }}%</span>
                        <span>·</span>
                        <span>Currency: {{ $client['currency'] ?? 'RON' }}</span>
                    </div>
                </div>
            </div>
            @if(!empty($client['has_billing']) && !empty($client['next_billing_date']))
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Next Billing</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $client['next_billing_date']->format('M d, Y') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Date Filter Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Period:</span>

            {{-- Preset Buttons --}}
            <button wire:click="setPeriod('day')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $period === 'day' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                Today
            </button>
            <button wire:click="setPeriod('week')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $period === 'week' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                This Week
            </button>
            <button wire:click="setPeriod('month')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $period === 'month' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                This Month
            </button>
            <button wire:click="setPeriod('last_month')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $period === 'last_month' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                Last Month
            </button>
            @if(!empty($client['has_billing']))
                <button wire:click="setPeriod('billing_period')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $period === 'billing_period' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                    Billing Period
                </button>
            @endif

            <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>

            {{-- Custom Date Inputs --}}
            <div class="flex items-center gap-2">
                <input type="date" wire:model="dateFrom"
                       class="px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
                <span class="text-xs text-gray-500">to</span>
                <input type="date" wire:model="dateTo"
                       class="px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
                <button wire:click="applyCustomDates"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700">
                    Apply
                </button>
            </div>
        </div>
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Showing: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        {{-- Total Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($totalRevenue, 2) }}
                <span class="text-sm font-normal text-gray-500">{{ $client['currency'] ?? 'RON' }}</span>
            </p>
        </div>

        {{-- Commission --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Commission</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ number_format($totalCommission, 2) }}
                <span class="text-sm font-normal text-gray-500">{{ $client['currency'] ?? 'RON' }}</span>
            </p>
        </div>

        {{-- Orders --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Orders</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $orderCount }}</p>
        </div>

        {{-- Generate Invoice --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center justify-center">
            <button wire:click="generateInvoiceForPeriod"
                    wire:loading.attr="disabled"
                    wire:target="generateInvoiceForPeriod"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                    {{ $totalCommission <= 0 ? 'disabled' : '' }}>
                <x-heroicon-o-document-plus class="w-4 h-4" />
                <span wire:loading.remove wire:target="generateInvoiceForPeriod">Generate Invoice</span>
                <span wire:loading wire:target="generateInvoiceForPeriod">Generating...</span>
            </button>
        </div>
    </div>

    {{-- Daily Breakdown --}}
    @if($dailyBreakdown->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Breakdown</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Orders</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Revenue</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($dailyBreakdown as $day)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($day['date'])->format('D, M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                    {{ $day['orders'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format($day['revenue'], 2) }} <span class="text-gray-500">{{ $client['currency'] ?? 'RON' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format($day['commission'], 2) }} <span class="text-gray-500 font-normal">{{ $client['currency'] ?? 'RON' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">{{ $orderCount }}</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                {{ number_format($totalRevenue, 2) }} <span class="text-gray-500 font-normal">{{ $client['currency'] ?? 'RON' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-green-600 dark:text-green-400">
                                {{ number_format($totalCommission, 2) }} <span class="text-gray-500 font-normal">{{ $client['currency'] ?? 'RON' }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- Orders Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Orders</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $orders->count() }} orders</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Revenue</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($orders as $order)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $order['order_number'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $order['date']->format('M d, H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $order['customer'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                {{ number_format($order['revenue'], 2) }} <span class="text-gray-500">{{ $order['currency'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-green-600 dark:text-green-400">
                                {{ number_format($order['commission'], 2) }} <span class="text-gray-500 font-normal">{{ $order['currency'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $order['status'] === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' : '' }}
                                    {{ $order['status'] === 'confirmed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' : '' }}
                                ">
                                    {{ ucfirst($order['status']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                                <p class="font-medium">No orders found</p>
                                <p class="text-sm">Try adjusting the date range.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
