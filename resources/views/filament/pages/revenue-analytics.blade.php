<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date Filter --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                    <input type="date" wire:model.live="endDate" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Filtered period: {{ $filteredRevenue['start'] }} - {{ $filteredRevenue['end'] }}
                </div>
            </div>
        </div>

        {{-- Filtered Period Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue for Selected Period</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gross Sales</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">€{{ number_format($filteredRevenue['gross_sales'], 2) }}</p>
                </div>
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Commission Earned</p>
                    <p class="text-xl font-bold text-blue-600">€{{ number_format($filteredRevenue['commission'], 2) }}</p>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Recurring Microservices</p>
                    <p class="text-xl font-bold text-green-600">€{{ number_format($filteredRevenue['recurring_microservices'], 2) }}</p>
                </div>
                <div class="text-center p-4 bg-primary-50 dark:bg-primary-900/30 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-xl font-bold text-primary-600">€{{ number_format($filteredRevenue['total'], 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Key Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- MRR --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Recurring Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">€{{ number_format($metrics['mrr'], 2) }}</p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                        <x-heroicon-o-currency-euro class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                @if($metrics['mrr_growth'] != 0)
                    <p class="mt-2 text-sm {{ $metrics['mrr_growth'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['mrr_growth'] > 0 ? '↑' : '↓' }} {{ abs(number_format($metrics['mrr_growth'], 1)) }}% vs last month
                    </p>
                @endif
            </div>

            {{-- ARR --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Annual Recurring Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">€{{ number_format($metrics['arr'], 2) }}</p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                        <x-heroicon-o-chart-bar class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            {{-- Net Profit --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Net Profit</p>
                        <p class="text-2xl font-bold {{ $metrics['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            €{{ number_format($metrics['net_profit'], 2) }}
                        </p>
                    </div>
                    <div class="p-3 {{ $metrics['net_profit'] >= 0 ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900' }} rounded-full">
                        <x-heroicon-o-banknotes class="w-6 h-6 {{ $metrics['net_profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($metrics['profit_margin'], 1) }}% margin
                </p>
            </div>

            {{-- Monthly Costs --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Costs</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">€{{ number_format($metrics['monthly_costs'], 2) }}</p>
                    </div>
                    <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
                        <x-heroicon-o-calculator class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                </div>
                <a href="{{ route('filament.admin.resources.costs.platform-costs.index') }}" class="mt-2 text-sm text-primary-600 hover:underline">
                    Manage costs →
                </a>
            </div>
        </div>

        {{-- Revenue Breakdown (3 parts) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Revenue Sources with Chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Sources (Current Month)</h3>
                <div class="space-y-4">
                    @foreach($revenueBreakdown as $source)
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $source['label'] }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($source['value'], 2) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                @php
                                    $total = array_sum(array_column($revenueBreakdown, 'value'));
                                    $percent = $total > 0 ? ($source['value'] / $total) * 100 : 0;
                                @endphp
                                <div class="h-2 rounded-full" style="width: {{ $percent }}%; background-color: {{ $source['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">Total MRR</span>
                            <span class="font-bold text-gray-900 dark:text-white">€{{ number_format($metrics['mrr'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cost Breakdown --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cost Breakdown</h3>
                @if(count($costBreakdown) > 0)
                    <div class="space-y-3">
                        @foreach($costBreakdown as $cost)
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $cost['label'] }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($cost['value'], 2) }}</span>
                            </div>
                        @endforeach
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-900 dark:text-white">Total Monthly Costs</span>
                                <span class="font-bold text-red-600">€{{ number_format($metrics['monthly_costs'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No costs configured yet.</p>
                    <a href="{{ route('filament.admin.resources.costs.platform-costs.create') }}" class="mt-2 inline-block text-sm text-primary-600 hover:underline">
                        Add your first cost →
                    </a>
                @endif
            </div>
        </div>

        {{-- Projections Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Projections</h3>
            <div class="h-80">
                <canvas id="projectionsChart"></canvas>
            </div>
        </div>

        {{-- Projections Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Projection Details</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Period</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Projected MRR</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Projected ARR</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Est. Costs</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Net Profit</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Cumulative Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projections as $months => $projection)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $projection['label'] }}</td>
                                <td class="py-3 px-4 text-right text-gray-900 dark:text-white">€{{ number_format($projection['mrr'], 2) }}</td>
                                <td class="py-3 px-4 text-right text-gray-900 dark:text-white">€{{ number_format($projection['arr'], 2) }}</td>
                                <td class="py-3 px-4 text-right text-red-600">€{{ number_format($projection['costs'], 2) }}</td>
                                <td class="py-3 px-4 text-right {{ $projection['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    €{{ number_format($projection['net_profit'], 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-medium text-gray-900 dark:text-white">
                                    €{{ number_format($projection['cumulative_revenue'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Microservice Revenue Breakdown --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue by Microservice</h3>
            @if(count($microserviceBreakdown) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Microservice</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Price</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Type</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Active</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Monthly</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">3mo</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">6mo</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">12mo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($microserviceBreakdown as $ms)
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ is_array($ms['name']) ? ($ms['name']['en'] ?? '') : $ms['name'] }}</td>
                                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">€{{ number_format($ms['price'], 2) }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $ms['is_recurring'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' }}">
                                            {{ ucfirst($ms['billing_cycle']) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ $ms['active_tenants'] }}</td>
                                    <td class="py-3 px-4 text-right font-medium {{ $ms['is_recurring'] ? 'text-green-600' : 'text-amber-600' }}">
                                        €{{ number_format($ms['monthly_revenue'], 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">€{{ number_format($ms['projections'][3], 2) }}</td>
                                    <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">€{{ number_format($ms['projections'][6], 2) }}</td>
                                    <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">€{{ number_format($ms['projections'][12], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                                <td colspan="4" class="py-3 px-4 font-bold text-gray-900 dark:text-white">Total</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">
                                    €{{ number_format($metrics['total_microservice_revenue'], 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">
                                    €{{ number_format($metrics['total_microservice_revenue'] * 3, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">
                                    €{{ number_format($metrics['total_microservice_revenue'] * 6, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 dark:text-white">
                                    €{{ number_format($metrics['total_microservice_revenue'] * 12, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No active microservices found.</p>
            @endif
        </div>

        {{-- Historical Data --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue History (Last 12 Months)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Month</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Gross Revenue</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Commission Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyData as $data)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $data['month'] }}</td>
                                <td class="py-3 px-4 text-right text-gray-900 dark:text-white">€{{ number_format($data['revenue'], 2) }}</td>
                                <td class="py-3 px-4 text-right text-green-600">€{{ number_format($data['commission'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Investor Summary --}}
        <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-lg shadow p-6 text-white">
            <h3 class="text-lg font-semibold mb-4">Investor Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-primary-100 text-sm">Current ARR</p>
                    <p class="text-3xl font-bold">€{{ number_format($metrics['arr'], 0) }}</p>
                </div>
                <div>
                    <p class="text-primary-100 text-sm">12-Month Projected ARR</p>
                    <p class="text-3xl font-bold">€{{ number_format($projections[12]['arr'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-primary-100 text-sm">Profit Margin</p>
                    <p class="text-3xl font-bold">{{ number_format($metrics['profit_margin'], 1) }}%</p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-primary-400">
                <p class="text-sm text-primary-100">
                    Revenue model: Platform commission ({{ number_format($metrics['avg_commission_rate'], 1) }}% avg) + Recurring microservices (€{{ number_format($metrics['recurring_microservice_revenue'], 2) }}/mo) + One-time (€{{ number_format($metrics['fixed_microservice_revenue'], 2) }})
                </p>
            </div>
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
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': €' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '€' + value;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
