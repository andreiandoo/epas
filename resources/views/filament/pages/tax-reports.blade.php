<x-filament-panels::page>
    {{-- Period Selector --}}
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
            <button wire:click="setPeriod('week')"
                class="px-4 py-2 text-sm font-medium {{ $period === 'week' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Week
            </button>
            <button wire:click="setPeriod('month')"
                class="px-4 py-2 text-sm font-medium border-l border-gray-300 dark:border-gray-600 {{ $period === 'month' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Month
            </button>
            <button wire:click="setPeriod('quarter')"
                class="px-4 py-2 text-sm font-medium border-l border-gray-300 dark:border-gray-600 {{ $period === 'quarter' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Quarter
            </button>
            <button wire:click="setPeriod('year')"
                class="px-4 py-2 text-sm font-medium border-l border-gray-300 dark:border-gray-600 {{ $period === 'year' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Year
            </button>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            {{ \Carbon\Carbon::parse($startDate)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M j, Y') }}
        </div>
    </div>

    @php
        $summary = $this->summary;
        $current = $summary['current'];
        $trends = $summary['trends'];
    @endphp

    {{-- Stats Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Total Collected --}}
        <x-filament::section>
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tax Collected</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($current['total_collected'], 2) }}
                    </p>
                </div>
                <div class="p-3 rounded-lg {{ $trends['total_collected'] >= 0 ? 'bg-green-100 dark:bg-green-900/20' : 'bg-red-100 dark:bg-red-900/20' }}">
                    @if($trends['total_collected'] >= 0)
                        <x-heroicon-o-arrow-trending-up class="w-6 h-6 text-green-600 dark:text-green-400" />
                    @else
                        <x-heroicon-o-arrow-trending-down class="w-6 h-6 text-red-600 dark:text-red-400" />
                    @endif
                </div>
            </div>
            <p class="text-sm mt-2 {{ $trends['total_collected'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $trends['total_collected'] >= 0 ? '+' : '' }}{{ $trends['total_collected'] }}% vs previous period
            </p>
        </x-filament::section>

        {{-- Transactions --}}
        <x-filament::section>
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Transactions</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($current['transaction_count']) }}
                    </p>
                </div>
                <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/20">
                    <x-heroicon-o-receipt-percent class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
            <p class="text-sm mt-2 {{ $trends['transaction_count'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $trends['transaction_count'] >= 0 ? '+' : '' }}{{ $trends['transaction_count'] }}% vs previous period
            </p>
        </x-filament::section>

        {{-- Average Rate --}}
        <x-filament::section>
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Tax Rate</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $current['average_rate'] }}%
                    </p>
                </div>
                <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/20">
                    <x-heroicon-o-calculator class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
            <p class="text-sm mt-2 text-gray-500 dark:text-gray-400">
                {{ $trends['average_rate'] >= 0 ? '+' : '' }}{{ $trends['average_rate'] }}pp change
            </p>
        </x-filament::section>

        {{-- Exemptions --}}
        <x-filament::section>
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Exemptions Applied</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($current['exemptions']['count']) }}
                    </p>
                </div>
                <div class="p-3 rounded-lg bg-orange-100 dark:bg-orange-900/20">
                    <x-heroicon-o-shield-check class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
            <p class="text-sm mt-2 text-gray-500 dark:text-gray-400">
                {{ number_format($current['exemptions']['amount_saved'], 2) }} saved
            </p>
        </x-filament::section>
    </div>

    {{-- Type Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <x-filament::section>
            <x-slot name="heading">Tax Collection by Type</x-slot>
            <div class="space-y-4">
                @php
                    $total = $current['by_type']['general'] + $current['by_type']['local'];
                    $generalPct = $total > 0 ? ($current['by_type']['general'] / $total) * 100 : 0;
                    $localPct = $total > 0 ? ($current['by_type']['local'] / $total) * 100 : 0;
                @endphp
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">General Taxes</span>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ number_format($current['by_type']['general'], 2) }} ({{ number_format($generalPct, 1) }}%)
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="bg-blue-500 h-3 rounded-full" style="width: {{ $generalPct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Local Taxes</span>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ number_format($current['by_type']['local'], 2) }} ({{ number_format($localPct, 1) }}%)
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full" style="width: {{ $localPct }}%"></div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Collection by Country</x-slot>
            @php $byCountry = $this->byCountry; @endphp
            @if(count($byCountry) > 0)
                <div class="space-y-3">
                    @foreach(array_slice($byCountry, 0, 5) as $country)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $country['country'] }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $country['count'] }} txn)</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format($country['total'], 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No country data available.</p>
            @endif
        </x-filament::section>
    </div>

    {{-- Top Taxes Table --}}
    <x-filament::section>
        <x-slot name="heading">Top Taxes by Collection</x-slot>
        @php $topTaxes = $this->topTaxes; @endphp
        @if(count($topTaxes) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tax Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Transactions</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Collected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($topTaxes as $tax)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $tax['name'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                        {{ $tax['type'] === 'general' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' : 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' }}">
                                        {{ ucfirst($tax['type']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $tax['avg_rate'] }}%</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($tax['count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format($tax['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No tax collection data for this period.</p>
        @endif
    </x-filament::section>

    {{-- Exemptions Report --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Exemptions Report</x-slot>
        @php $exemptions = $this->exemptions; @endphp
        @if(count($exemptions['exemptions']) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Exemption</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Times Used</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Original Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actual Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Savings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($exemptions['exemptions'] as $exemption)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $exemption['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($exemption['usage_count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($exemption['original_total'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($exemption['actual_total'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">{{ number_format($exemption['savings'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300">Total</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-700 dark:text-gray-300">{{ number_format($exemptions['totals']['usage_count']) }}</td>
                            <td colspan="2"></td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ number_format($exemptions['totals']['savings'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No exemptions applied during this period.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
