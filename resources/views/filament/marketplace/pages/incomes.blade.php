<x-filament-panels::page>
    @if(!$marketplace)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found. Please contact support.</p>
        </div>
    @else

    {{-- Filters Row --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            {{-- Period Quick Buttons --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Perioada</label>
                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                    @foreach(['7d' => '7 Zile', '30d' => '30 Zile', '90d' => '90 Zile'] as $key => $label)
                        <button
                            wire:click="$set('period', '{{ $key }}')"
                            class="px-3.5 py-2 text-sm font-medium transition-colors
                                {{ $period === $key
                                    ? 'bg-emerald-600 text-white'
                                    : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                    <button
                        wire:click="$set('period', 'custom')"
                        class="px-3.5 py-2 text-sm font-medium transition-colors
                            {{ $period === 'custom'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                    >
                        Custom
                    </button>
                </div>
            </div>

            {{-- Custom Date Range --}}
            @if($period === 'custom')
            <div class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">De la</label>
                    <input
                        type="date"
                        wire:model.live="customFrom"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Pana la</label>
                    <input
                        type="date"
                        wire:model.live="customTo"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
            </div>
            @endif

            {{-- Organizer Filter --}}
            <div class="ml-auto">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Organizator</label>
                <select
                    wire:model.live="organizerId"
                    class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 min-w-[200px]"
                >
                    <option value="">Toti organizatorii</option>
                    @foreach($organizers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($selectedOrganizerName)
        <div class="mt-3 flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                <x-heroicon-s-user class="w-3.5 h-3.5" />
                {{ $selectedOrganizerName }}
                <button wire:click="$set('organizerId', '')" class="ml-1 hover:text-emerald-900 dark:hover:text-emerald-200">
                    <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                </button>
            </span>
        </div>
        @endif
    </div>

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        {{-- Vanzari totale (Gross Sales) --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-shopping-cart class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ number_format($stats['total_sales'], 2) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RON</span></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Vanzari Totale</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ number_format($stats['total_orders']) }} comenzi</p>
                </div>
            </div>
        </div>

        {{-- Total Commissions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                    <x-heroicon-o-receipt-percent class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ number_format($stats['total_commissions'], 2) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RON</span></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Comisioane</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">rata: {{ number_format($stats['effective_commission_rate'], 1) }}%</p>
                </div>
            </div>
        </div>

        {{-- Gift Card Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-pink-100 dark:bg-pink-900/30 rounded-lg">
                    <x-heroicon-o-gift class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ number_format($stats['gift_card_revenue'], 2) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RON</span></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Carduri Cadou</p>
                </div>
            </div>
        </div>

        {{-- Services Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-violet-100 dark:bg-violet-900/30 rounded-lg">
                    <x-heroicon-o-bolt class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ number_format($stats['services_revenue'], 2) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RON</span></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Servicii Extra</p>
                </div>
            </div>
        </div>

        {{-- Grand Total Revenue --}}
        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl shadow-sm border border-emerald-200 dark:border-emerald-800 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-emerald-100 dark:bg-emerald-900/40 rounded-lg">
                    <x-heroicon-o-currency-dollar class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400 truncate">{{ number_format($stats['grand_total'], 2) }} <span class="text-sm font-medium">RON</span></p>
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-500 uppercase tracking-wide">Venit Total Marketplace</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Averages Row --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Medie Zilnica Vanzari</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($stats['avg_daily_sales'], 2) }} <span class="text-xs text-gray-400">RON</span></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Medie Zilnica Comisioane</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($stats['avg_daily_commissions'], 2) }} <span class="text-xs text-gray-400">RON</span></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Medie Zilnica Venit Total</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($stats['avg_daily_revenue'], 2) }} <span class="text-xs text-gray-400">RON</span></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Valoare Medie Comanda</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($stats['avg_order_value'], 2) }} <span class="text-xs text-gray-400">RON</span></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Comision Mediu / Comanda</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($stats['avg_commission_per_order'], 2) }} <span class="text-xs text-gray-400">RON</span></p>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" wire:key="charts-{{ $period }}-{{ $organizerId }}-{{ $customFrom }}-{{ $customTo }}">
        {{-- Sales & Commissions Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Vanzari vs Comisioane</h3>
            <div class="h-72">
                <canvas id="salesCommissionsChart" data-chart='@json($chartData)'></canvas>
            </div>
        </div>

        {{-- Revenue Breakdown Donut --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Structura Venituri Marketplace</h3>
            <div class="h-72 flex items-center justify-center">
                <canvas id="revenueDonutChart" data-breakdown='@json([
                    ["Comisioane", $stats["total_commissions"]],
                    ["Taxa Refund", $stats["refund_fee_revenue"]],
                    ["Carduri Cadou", $stats["gift_card_revenue"]],
                    ["Servicii Extra", $stats["services_revenue"]],
                ])'></canvas>
            </div>
        </div>
    </div>

    {{-- Revenue Breakdown Detail --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Breakdown by Type --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Breakdown Venituri</h3>
            <div class="space-y-3">
                @foreach($breakdown as $item)
                    @php
                        $percentage = $stats['grand_total'] > 0 ? ($item['value'] / $stats['grand_total']) * 100 : 0;
                    @endphp
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <div class="p-2 rounded-lg bg-{{ $item['color'] }}-100 dark:bg-{{ $item['color'] }}-900/30">
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5 text-{{ $item['color'] }}-600 dark:text-{{ $item['color'] }}-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($item['value'], 2) }} RON</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                                <div class="bg-{{ $item['color'] }}-500 h-1.5 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $item['detail'] }} &middot; {{ number_format($percentage, 1) }}% din total</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Services Breakdown --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Detaliu Servicii Extra</h3>
            @if(count($servicesByType) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2.5 px-3 font-medium text-gray-500 dark:text-gray-400">Tip Serviciu</th>
                                <th class="text-right py-2.5 px-3 font-medium text-gray-500 dark:text-gray-400">Comenzi</th>
                                <th class="text-right py-2.5 px-3 font-medium text-gray-500 dark:text-gray-400">Venit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($servicesByType as $svc)
                            <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-white">{{ $svc['label'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-600 dark:text-gray-300">{{ $svc['count'] }}</td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-900 dark:text-white">{{ number_format($svc['revenue'], 2) }} RON</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white">Total</td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900 dark:text-white">{{ array_sum(array_column($servicesByType, 'count')) }}</td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900 dark:text-white">{{ number_format($stats['services_revenue'], 2) }} RON</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-bolt class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">Niciun serviciu extra in aceasta perioada.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Top Organizers Table --}}
    @if(!$organizerId && count($topOrganizers) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Organizatori (dupa vanzari in perioada selectata)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">#</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Organizator</th>
                        <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Comenzi</th>
                        <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Vanzari</th>
                        <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Comisioane</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Actiune</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topOrganizers as $index => $org)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="py-3 px-4 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                        {{ strtoupper(substr($org['name'], 0, 1)) }}
                                    </span>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $org['name'] }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-300">{{ number_format($org['order_count']) }}</td>
                        <td class="py-3 px-4 text-right font-medium text-gray-900 dark:text-white">{{ number_format($org['total_sales'], 2) }} RON</td>
                        <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400">{{ number_format($org['total_commissions'], 2) }} RON</td>
                        <td class="py-3 px-4 text-center">
                            <button
                                wire:click="$set('organizerId', '{{ $org['id'] }}')"
                                class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 underline"
                            >
                                Filtreaza
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Period Info Footer --}}
    <div class="text-center text-xs text-gray-400 dark:text-gray-500">
        Date afisate pentru {{ $daysInRange }} zile
        @if($period === 'custom' && $customFrom && $customTo)
            ({{ $customFrom }} &mdash; {{ $customTo }})
        @endif
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initIncomesCharts();
        });

        document.addEventListener('livewire:navigated', function() {
            initIncomesCharts();
        });

        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector && (el.querySelector('#salesCommissionsChart') || el.querySelector('#revenueDonutChart'))) {
                setTimeout(() => initIncomesCharts(), 100);
            }
        });

        function initIncomesCharts() {
            initSalesCommissionsChart();
            initRevenueDonutChart();
        }

        function initSalesCommissionsChart() {
            const ctx = document.getElementById('salesCommissionsChart');
            if (!ctx) return;

            const existingChart = Chart.getChart(ctx);
            if (existingChart) existingChart.destroy();

            const isDark = document.documentElement.classList.contains('dark');
            const chartDataStr = ctx.getAttribute('data-chart');
            if (!chartDataStr) return;

            const chartData = JSON.parse(chartDataStr);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Vanzari',
                            data: chartData.sales,
                            borderColor: isDark ? '#60a5fa' : '#3b82f6',
                            backgroundColor: isDark ? 'rgba(96, 165, 250, 0.08)' : 'rgba(59, 130, 246, 0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        },
                        {
                            label: 'Comisioane',
                            data: chartData.commissions,
                            borderColor: isDark ? '#34d399' : '#10b981',
                            backgroundColor: isDark ? 'rgba(52, 211, 153, 0.08)' : 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        },
                        {
                            label: 'Servicii',
                            data: chartData.services,
                            borderColor: isDark ? '#a78bfa' : '#8b5cf6',
                            backgroundColor: isDark ? 'rgba(167, 139, 250, 0.08)' : 'rgba(139, 92, 246, 0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: isDark ? '#d1d5db' : '#374151',
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 16,
                                font: { size: 12 },
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + new Intl.NumberFormat('ro-RO', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }).format(context.parsed.y) + ' RON';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                maxRotation: 45,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: isDark ? '#374151' : '#f3f4f6' },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                callback: function(value) {
                                    return new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function initRevenueDonutChart() {
            const ctx = document.getElementById('revenueDonutChart');
            if (!ctx) return;

            const existingChart = Chart.getChart(ctx);
            if (existingChart) existingChart.destroy();

            const isDark = document.documentElement.classList.contains('dark');
            const breakdownStr = ctx.getAttribute('data-breakdown');
            if (!breakdownStr) return;

            const breakdown = JSON.parse(breakdownStr);
            const labels = breakdown.map(b => b[0]);
            const values = breakdown.map(b => b[1]);
            const total = values.reduce((a, b) => a + b, 0);

            const colors = ['#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
            const hoverColors = ['#059669', '#d97706', '#db2777', '#7c3aed'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        hoverBackgroundColor: hoverColors,
                        borderWidth: 0,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: isDark ? '#d1d5db' : '#374151',
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 12,
                                font: { size: 12 },
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    return data.labels.map(function(label, i) {
                                        const value = data.datasets[0].data[i];
                                        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return {
                                            text: label + ' (' + pct + '%)',
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: 'transparent',
                                            lineWidth: 0,
                                            hidden: false,
                                            index: i,
                                            pointStyle: 'circle',
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    return context.label + ': ' + new Intl.NumberFormat('ro-RO', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }).format(value) + ' RON (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
    @endpush

    @endif
</x-filament-panels::page>
