<x-filament-panels::page>
    <div class="space-y-6">
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
                <a href="{{ route('filament.admin.resources.platform-costs.index') }}" class="mt-2 text-sm text-primary-600 hover:underline">
                    Manage costs →
                </a>
            </div>
        </div>

        {{-- Revenue Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Revenue Sources --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Sources</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Commission from Sales</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($metrics['commission_revenue'], 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @php
                                $total = $metrics['commission_revenue'] + $metrics['microservice_revenue'];
                                $commissionPercent = $total > 0 ? ($metrics['commission_revenue'] / $total) * 100 : 0;
                            @endphp
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $commissionPercent }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Avg commission rate: {{ number_format($metrics['avg_commission_rate'], 1) }}% • {{ $metrics['active_tenants'] }} active tenants
                        </p>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Microservice Subscriptions</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">€{{ number_format($metrics['microservice_revenue'], 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @php
                                $microPercent = $total > 0 ? ($metrics['microservice_revenue'] / $total) * 100 : 0;
                            @endphp
                            <div class="bg-green-600 h-2 rounded-full" style="width: {{ $microPercent }}%"></div>
                        </div>
                    </div>
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
                    <a href="{{ route('filament.admin.resources.platform-costs.create') }}" class="mt-2 inline-block text-sm text-primary-600 hover:underline">
                        Add your first cost →
                    </a>
                @endif
            </div>
        </div>

        {{-- Projections --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Projections</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Based on current MRR and conservative growth estimates
            </p>
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
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $months }} months</td>
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
                    Revenue model: Platform commission ({{ number_format($metrics['avg_commission_rate'], 1) }}% avg) + Microservice subscriptions
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
