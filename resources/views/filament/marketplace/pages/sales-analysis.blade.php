<x-filament-panels::page>
@if(!$marketplace)
    <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
        <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found.</p>
    </div>
@else
<div class="space-y-5" x-data="{ activeTab: @entangle('activeTab') }">
    {{-- Header --}}
    <div class="p-5 text-white shadow-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 rounded-2xl">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold">Sales Analysis</h2>
                <p class="mt-1 text-sm text-indigo-200">In-depth marketplace performance intelligence</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                {{-- Date Range --}}
                <select wire:model.live="dateRange" class="px-3 py-1.5 text-sm bg-white/20 backdrop-blur border-0 rounded-lg text-white focus:ring-2 focus:ring-white/50 [&>option]:text-gray-900">
                    <option value="30d">30 zile</option>
                    <option value="90d">90 zile</option>
                    <option value="6m">6 luni</option>
                    <option value="1y">1 an</option>
                    <option value="all">Tot</option>
                </select>
                {{-- Category --}}
                <select wire:model.live="categoryFilter" class="px-3 py-1.5 text-sm bg-white/20 backdrop-blur border-0 rounded-lg text-white focus:ring-2 focus:ring-white/50 [&>option]:text-gray-900">
                    <option value="">Toate categoriile</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Revenue</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $currencySymbol }}{{ number_format($kpis['total_revenue'], 0) }}</div>
            @if($kpis['revenue_change'] != 0)
                <div class="flex items-center gap-1 mt-1 text-xs {{ $kpis['revenue_change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    <x-dynamic-component :component="$kpis['revenue_change'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" class="w-3 h-3" />
                    {{ abs($kpis['revenue_change']) }}%
                </div>
            @endif
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Comenzi</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($kpis['total_orders']) }}</div>
            @if($kpis['orders_change'] != 0)
                <div class="flex items-center gap-1 mt-1 text-xs {{ $kpis['orders_change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    <x-dynamic-component :component="$kpis['orders_change'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" class="w-3 h-3" />
                    {{ abs($kpis['orders_change']) }}%
                </div>
            @endif
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Bilete</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($kpis['total_tickets']) }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">AOV</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $currencySymbol }}{{ number_format($kpis['avg_order_value'], 0) }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Repeat Rate</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $kpis['repeat_rate'] }}%</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Cea mai buna zi</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $kpis['best_day'] }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700 lg:col-span-1 col-span-2">
            <div class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Perioada</div>
            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ match($dateRange) { '30d' => '30 zile', '90d' => '90 zile', '6m' => '6 luni', '1y' => '1 an', default => 'Tot' } }}</div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="overflow-x-auto border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-1 -mb-px">
            @foreach([
                'patterns' => ['Patterns', 'heroicon-o-chart-bar'],
                'predictions' => ['Predictii', 'heroicon-o-sparkles'],
                'optimization' => ['Optimizare', 'heroicon-o-currency-dollar'],
                'audience' => ['Audienta', 'heroicon-o-user-group'],
                'operational' => ['Operational', 'heroicon-o-cog-6-tooth'],
            ] as $key => [$label, $icon])
                <button
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                        'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $activeTab === $key,
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== $key,
                    ])
                >
                    <x-dynamic-component :component="$icon" class="w-4 h-4" />
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- AI Insights --}}
    @if(!empty($insights))
    <div class="p-4 border border-indigo-200 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-800 rounded-xl">
        <div class="flex items-start gap-3">
            <x-heroicon-o-light-bulb class="w-5 h-5 mt-0.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
            <div class="text-sm text-indigo-800 dark:text-indigo-200">
                <span class="font-semibold">Insights:</span>
                <ul class="mt-1 space-y-1">
                    @foreach($insights as $insight)
                        <li>{{ $insight }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab Content --}}
    <div wire:key="tab-{{ $activeTab }}-{{ $dateRange }}-{{ $categoryFilter }}">
        @if($activeTab === 'patterns')
            @include('filament.marketplace.pages.sales-analysis.tab-patterns', ['data' => $tabData, 'currencySymbol' => $currencySymbol])
        @elseif($activeTab === 'predictions')
            @include('filament.marketplace.pages.sales-analysis.tab-predictions', ['data' => $tabData, 'currencySymbol' => $currencySymbol])
        @elseif($activeTab === 'optimization')
            @include('filament.marketplace.pages.sales-analysis.tab-optimization', ['data' => $tabData, 'currencySymbol' => $currencySymbol])
        @elseif($activeTab === 'audience')
            @include('filament.marketplace.pages.sales-analysis.tab-audience', ['data' => $tabData, 'currencySymbol' => $currencySymbol])
        @elseif($activeTab === 'operational')
            @include('filament.marketplace.pages.sales-analysis.tab-operational', ['data' => $tabData, 'currencySymbol' => $currencySymbol])
        @endif
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</x-filament-panels::page>
