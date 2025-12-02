<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Date Filter --}}
        <div class="bg-gray-800 rounded-lg shadow p-3">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-300">From</label>
                    <input type="date" wire:model.live="startDate" class="rounded-md border-gray-600 bg-gray-700 text-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 py-1.5">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-300">To</label>
                    <input type="date" wire:model.live="endDate" class="rounded-md border-gray-600 bg-gray-700 text-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 py-1.5">
                </div>
                <span class="text-xs text-gray-500">{{ $filteredRevenue['start'] }} - {{ $filteredRevenue['end'] }}</span>
            </div>
        </div>

        {{-- Key Metrics Row --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            {{-- MRR --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">MRR</p>
                <p class="text-lg font-bold text-white">€{{ number_format($metrics['mrr'], 0) }}</p>
                @if($metrics['mrr_growth'] != 0)
                    <p class="text-xs {{ $metrics['mrr_growth'] > 0 ? 'text-green-400' : 'text-red-400' }}">
                        {{ $metrics['mrr_growth'] > 0 ? '↑' : '↓' }} {{ abs(number_format($metrics['mrr_growth'], 1)) }}%
                    </p>
                @endif
            </div>
            {{-- ARR --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">ARR</p>
                <p class="text-lg font-bold text-white">€{{ number_format($metrics['arr'], 0) }}</p>
            </div>
            {{-- Net Profit --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">Net Profit/mo</p>
                <p class="text-lg font-bold {{ $metrics['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                    €{{ number_format($metrics['net_profit'], 0) }}
                </p>
                <p class="text-xs text-gray-500">{{ number_format($metrics['profit_margin'], 1) }}% margin</p>
            </div>
            {{-- Costs --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">Costs/mo</p>
                <p class="text-lg font-bold text-red-400">€{{ number_format($metrics['monthly_costs'], 0) }}</p>
            </div>
            {{-- Filtered Gross --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">Period Gross</p>
                <p class="text-lg font-bold text-white">€{{ number_format($filteredRevenue['gross_sales'], 0) }}</p>
            </div>
            {{-- Commission --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">Commission</p>
                <p class="text-lg font-bold text-blue-400">€{{ number_format($filteredRevenue['commission'], 0) }}</p>
            </div>
            {{-- Recurring MS --}}
            <div class="bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-400">Recurring MS</p>
                <p class="text-lg font-bold text-emerald-400">€{{ number_format($filteredRevenue['recurring_microservices'], 0) }}</p>
            </div>
            {{-- Total Period --}}
            <div class="bg-gray-800 rounded-lg p-3 border border-primary-600">
                <p class="text-xs text-gray-400">Period Total</p>
                <p class="text-lg font-bold text-primary-400">€{{ number_format($filteredRevenue['total'], 0) }}</p>
            </div>
        </div>

        {{-- SaaS Metrics + Unit Economics + Financial Health - Combined Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            {{-- SaaS Health --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>SaaS Health
                </h3>
                <div class="grid grid-cols-3 gap-2">
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">NRR</p>
                        <p class="text-sm font-bold {{ ($saasMetrics['nrr'] ?? 100) >= 100 ? 'text-green-400' : 'text-amber-400' }}">{{ number_format($saasMetrics['nrr'] ?? 100, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Churn</p>
                        <p class="text-sm font-bold {{ ($saasMetrics['churn_rate'] ?? 0) <= 5 ? 'text-green-400' : 'text-red-400' }}">{{ number_format($saasMetrics['churn_rate'] ?? 0, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Retention</p>
                        <p class="text-sm font-bold {{ ($saasMetrics['retention_rate'] ?? 100) >= 95 ? 'text-green-400' : 'text-amber-400' }}">{{ number_format($saasMetrics['retention_rate'] ?? 100, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Growth</p>
                        <p class="text-sm font-bold {{ ($saasMetrics['growth_rate'] ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">{{ ($saasMetrics['growth_rate'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($saasMetrics['growth_rate'] ?? 0, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Stickiness</p>
                        <p class="text-sm font-bold {{ ($saasMetrics['stickiness'] ?? 0) >= 20 ? 'text-green-400' : 'text-amber-400' }}">{{ number_format($saasMetrics['stickiness'] ?? 0, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Churned</p>
                        <p class="text-sm font-bold text-gray-300">{{ $saasMetrics['churned_tenants'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            {{-- Unit Economics --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>Unit Economics
                </h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">LTV</p>
                        <p class="text-sm font-bold text-indigo-400">€{{ number_format($unitEconomics['ltv'] ?? 0, 0) }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($unitEconomics['avg_lifespan_months'] ?? 0, 0) }}mo avg</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">CAC</p>
                        <p class="text-sm font-bold text-rose-400">€{{ number_format($unitEconomics['cac'] ?? 0, 0) }}</p>
                        <p class="text-xs text-gray-500">{{ $unitEconomics['new_tenants_this_month'] ?? 0 }} new</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">LTV:CAC</p>
                        <p class="text-sm font-bold {{ ($unitEconomics['ltv_cac_ratio'] ?? 0) >= 3 ? 'text-green-400' : (($unitEconomics['ltv_cac_ratio'] ?? 0) >= 1 ? 'text-amber-400' : 'text-red-400') }}">{{ number_format($unitEconomics['ltv_cac_ratio'] ?? 0, 1) }}x</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">ARPU</p>
                        <p class="text-sm font-bold text-cyan-400">€{{ number_format($unitEconomics['arpu'] ?? 0, 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Financial Health --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>Financial Health
                </h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">EBITDA</p>
                        <p class="text-sm font-bold {{ ($financialHealth['ebitda'] ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">€{{ number_format($financialHealth['ebitda'] ?? 0, 0) }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($financialHealth['ebitda_margin'] ?? 0, 1) }}%</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Burn Multiple</p>
                        <p class="text-sm font-bold {{ ($financialHealth['burn_multiple'] ?? 0) <= 1 ? 'text-green-400' : (($financialHealth['burn_multiple'] ?? 0) <= 2 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ ($financialHealth['is_profitable'] ?? false) ? 'N/A' : number_format($financialHealth['burn_multiple'] ?? 0, 1) . 'x' }}
                        </p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Cash Flow</p>
                        <p class="text-sm font-bold {{ ($financialHealth['operating_cash_flow'] ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">€{{ number_format($financialHealth['operating_cash_flow'] ?? 0, 0) }}</p>
                    </div>
                    <div class="text-center p-2 bg-gray-700/50 rounded">
                        <p class="text-xs text-gray-400">Runway</p>
                        <p class="text-sm font-bold {{ ($financialHealth['runway_months'] ?? 0) >= 18 ? 'text-green-400' : (($financialHealth['runway_months'] ?? 0) >= 6 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ ($financialHealth['runway_months'] ?? 0) >= 999 ? '∞' : number_format($financialHealth['runway_months'] ?? 0, 0) . 'mo' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue Breakdown + Costs Side by Side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            {{-- Revenue Sources --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Revenue Sources</h3>
                <div class="space-y-2">
                    @foreach($revenueBreakdown as $source)
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $source['color'] }}"></div>
                            <span class="text-xs text-gray-400 flex-1">{{ $source['label'] }}</span>
                            <span class="text-sm font-medium text-white">€{{ number_format($source['value'], 0) }}</span>
                            @php
                                $total = array_sum(array_column($revenueBreakdown, 'value'));
                                $percent = $total > 0 ? ($source['value'] / $total) * 100 : 0;
                            @endphp
                            <span class="text-xs text-gray-500 w-12 text-right">{{ number_format($percent, 0) }}%</span>
                        </div>
                    @endforeach
                    <div class="pt-2 border-t border-gray-700 flex justify-between">
                        <span class="text-sm font-medium text-gray-300">Total MRR</span>
                        <span class="text-sm font-bold text-white">€{{ number_format($metrics['mrr'], 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- Cost Breakdown --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-300">Cost Breakdown</h3>
                    <a href="{{ route('filament.admin.resources.costs.platform-costs.index') }}" class="text-xs text-primary-400 hover:underline">Manage →</a>
                </div>
                @if(count($costBreakdown) > 0)
                    <div class="space-y-2">
                        @foreach($costBreakdown as $cost)
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-400">{{ $cost['label'] }}</span>
                                <span class="text-sm font-medium text-gray-300">€{{ number_format($cost['value'], 0) }}</span>
                            </div>
                        @endforeach
                        <div class="pt-2 border-t border-gray-700 flex justify-between">
                            <span class="text-sm font-medium text-gray-300">Total Costs</span>
                            <span class="text-sm font-bold text-red-400">€{{ number_format($metrics['monthly_costs'], 0) }}</span>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500 text-xs">No costs configured.</p>
                @endif
            </div>
        </div>

        {{-- Projections Chart --}}
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">Revenue Projections</h3>
            <div class="h-64">
                <canvas id="projectionsChart"></canvas>
            </div>
        </div>

        {{-- Projections Table + Microservice Table Side by Side --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
            {{-- Projections Table --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Projection Details</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-2 font-medium text-gray-400">Period</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-400">MRR</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-400">Costs</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-400">Net</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-400">Cumulative</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projections as $months => $projection)
                                <tr class="border-b border-gray-700/50">
                                    <td class="py-2 px-2 text-gray-300">{{ $projection['label'] }}</td>
                                    <td class="py-2 px-2 text-right text-white">€{{ number_format($projection['mrr'], 0) }}</td>
                                    <td class="py-2 px-2 text-right text-red-400">€{{ number_format($projection['costs'], 0) }}</td>
                                    <td class="py-2 px-2 text-right {{ $projection['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' }}">€{{ number_format($projection['net_profit'], 0) }}</td>
                                    <td class="py-2 px-2 text-right text-gray-300">€{{ number_format($projection['cumulative_revenue'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Microservice Revenue --}}
            <div class="bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Revenue by Microservice</h3>
                @if(count($microserviceBreakdown) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left py-2 px-2 font-medium text-gray-400">Service</th>
                                    <th class="text-right py-2 px-2 font-medium text-gray-400">Price</th>
                                    <th class="text-center py-2 px-2 font-medium text-gray-400">Type</th>
                                    <th class="text-right py-2 px-2 font-medium text-gray-400">Active</th>
                                    <th class="text-right py-2 px-2 font-medium text-gray-400">Monthly</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($microserviceBreakdown as $ms)
                                    <tr class="border-b border-gray-700/50">
                                        <td class="py-2 px-2 text-gray-300 truncate max-w-[120px]">{{ is_array($ms['name']) ? ($ms['name']['en'] ?? '') : $ms['name'] }}</td>
                                        <td class="py-2 px-2 text-right text-white">€{{ number_format($ms['price'], 0) }}</td>
                                        <td class="py-2 px-2 text-center">
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs {{ $ms['is_recurring'] ? 'bg-green-900/50 text-green-400' : 'bg-amber-900/50 text-amber-400' }}">
                                                {{ $ms['is_recurring'] ? 'Rec' : 'Once' }}
                                            </span>
                                        </td>
                                        <td class="py-2 px-2 text-right text-gray-300">{{ $ms['active_tenants'] }}</td>
                                        <td class="py-2 px-2 text-right font-medium {{ $ms['is_recurring'] ? 'text-green-400' : 'text-amber-400' }}">€{{ number_format($ms['monthly_revenue'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-600">
                                    <td colspan="4" class="py-2 px-2 font-medium text-gray-300">Total</td>
                                    <td class="py-2 px-2 text-right font-bold text-white">€{{ number_format($metrics['total_microservice_revenue'], 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-xs">No active microservices.</p>
                @endif
            </div>
        </div>

        {{-- Revenue History --}}
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">Revenue History (Last 12 Months)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-2 font-medium text-gray-400">Month</th>
                            <th class="text-right py-2 px-2 font-medium text-gray-400">Gross Revenue</th>
                            <th class="text-right py-2 px-2 font-medium text-gray-400">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyData as $data)
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-2 text-gray-300">{{ $data['month'] }}</td>
                                <td class="py-2 px-2 text-right text-white">€{{ number_format($data['revenue'], 0) }}</td>
                                <td class="py-2 px-2 text-right text-green-400">€{{ number_format($data['commission'], 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Investor Summary --}}
        <div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-white mb-3">Investor Summary</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-primary-200 text-xs">Current ARR</p>
                    <p class="text-2xl font-bold text-white">€{{ number_format($metrics['arr'], 0) }}</p>
                </div>
                <div>
                    <p class="text-primary-200 text-xs">12mo Projected ARR</p>
                    <p class="text-2xl font-bold text-white">€{{ number_format($projections[12]['arr'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-primary-200 text-xs">Profit Margin</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($metrics['profit_margin'], 1) }}%</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-primary-200 border-t border-primary-500 pt-2">
                Commission ({{ number_format($metrics['avg_commission_rate'], 1) }}% avg) + Recurring MS (€{{ number_format($metrics['recurring_microservice_revenue'], 0) }}/mo) + One-time (€{{ number_format($metrics['fixed_microservice_revenue'], 0) }})
            </p>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('projectionsChart').getContext('2d');
            const chartData = @json($chartData);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.projections.labels,
                    datasets: [
                        {
                            label: 'MRR',
                            data: chartData.projections.mrr,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            fill: true,
                            tension: 0.4,
                        },
                        {
                            label: 'Costs',
                            data: chartData.projections.costs,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            tension: 0.4,
                        },
                        {
                            label: 'Net Profit',
                            data: chartData.projections.profit,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: '#9ca3af', font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': €' + context.parsed.y.toFixed(0);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(75, 85, 99, 0.3)' },
                            ticks: {
                                color: '#9ca3af',
                                callback: function(value) { return '€' + value; }
                            }
                        },
                        x: {
                            grid: { color: 'rgba(75, 85, 99, 0.3)' },
                            ticks: { color: '#9ca3af' }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
