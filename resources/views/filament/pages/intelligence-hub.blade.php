<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header with Tenant Selector --}}
        <div class="p-6 text-white shadow-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="mb-2 text-2xl font-bold">Intelligence Hub</h2>
                    <p class="max-w-2xl text-sm text-emerald-100">
                        AI-powered insights for recommendations, win-back campaigns, demand forecasting, and customer journey analytics.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 backdrop-blur rounded-lg text-sm">
                        <x-heroicon-o-cpu-chip class="w-4 h-4" />
                        AI Enabled
                    </div>
                    <select wire:model.live="tenantId"
                            class="px-4 py-2 text-sm text-gray-900 bg-white border-0 rounded-lg shadow-sm focus:ring-2 focus:ring-emerald-500">
                        <option value="">All Tenants (Platform View)</option>
                        @foreach($tenants as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if(!$tenantId)
            {{-- Platform Overview --}}
            <div class="grid grid-cols-4 gap-4">
                <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-red-100 rounded-lg dark:bg-red-900/30">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($platformStats['total_at_risk'] ?? 0) }}
                            </div>
                            <div class="text-sm text-gray-500">At-Risk Customers</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-orange-100 rounded-lg dark:bg-orange-900/30">
                            <x-heroicon-o-bell-alert class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($platformStats['total_alerts'] ?? 0) }}
                            </div>
                            <div class="text-sm text-gray-500">Pending Alerts</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-purple-100 rounded-lg dark:bg-purple-900/30">
                            <x-heroicon-o-building-storefront class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ count($tenants) }}
                            </div>
                            <div class="text-sm text-gray-500">Active Tenants</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-green-100 rounded-lg dark:bg-green-900/30">
                            <x-heroicon-o-chart-bar class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($platformStats['high_risk_events'] ?? 0) }}
                            </div>
                            <div class="text-sm text-gray-500">High-Risk Events</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border border-blue-200 rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                <div class="flex gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm">
                        <p class="font-medium text-blue-900 dark:text-blue-100">Select a tenant to view detailed intelligence</p>
                        <p class="mt-1 text-blue-700 dark:text-blue-300">
                            Use the dropdown above to select a specific tenant and view their alerts, win-back candidates, journey analytics, and demand forecasts.
                        </p>
                    </div>
                </div>
            </div>
        @else
            {{-- Tenant-Specific Data --}}
            <div class="grid grid-cols-12 gap-6">
                {{-- Alerts Panel --}}
                <div class="col-span-12 lg:col-span-6">
                    <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <x-heroicon-o-bell-alert class="w-5 h-5 text-red-500" />
                                Priority Alerts
                            </h3>
                            <span class="px-2 py-1 text-xs font-medium text-red-600 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-400">
                                {{ count($alerts) }} pending
                            </span>
                        </div>

                        @if(empty($alerts))
                            <div class="py-8 text-center">
                                <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-3 text-green-500" />
                                <p class="text-gray-500 dark:text-gray-400">No pending alerts</p>
                            </div>
                        @else
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                @foreach($alerts as $alert)
                                    @php
                                        $priorityColors = [
                                            'critical' => 'border-red-500 bg-red-50 dark:bg-red-900/20',
                                            'high' => 'border-orange-500 bg-orange-50 dark:bg-orange-900/20',
                                            'medium' => 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20',
                                            'info' => 'border-blue-500 bg-blue-50 dark:bg-blue-900/20',
                                        ];
                                        $color = $priorityColors[$alert['priority'] ?? 'info'] ?? $priorityColors['info'];
                                    @endphp
                                    <div class="p-4 border-l-4 rounded-lg {{ $color }}">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-medium uppercase text-gray-500">{{ str_replace('_', ' ', $alert['type'] ?? 'Alert') }}</span>
                                                    <span class="px-1.5 py-0.5 text-xs rounded {{ ($alert['priority'] ?? '') === 'critical' ? 'bg-red-500 text-white' : 'bg-gray-200 dark:bg-gray-700' }}">
                                                        {{ ucfirst($alert['priority'] ?? 'info') }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                    @if(($alert['type'] ?? '') === 'high_value_cart_abandon')
                                                        Cart abandoned: {{ number_format($alert['data']['cart_value'] ?? 0, 2) }} RON
                                                    @elseif(($alert['type'] ?? '') === 'vip_churn_risk')
                                                        VIP customer at risk (LTV: {{ number_format($alert['data']['ltv'] ?? 0, 2) }} RON)
                                                    @else
                                                        {{ str_replace('_', ' ', ucfirst($alert['type'] ?? 'Alert')) }}
                                                    @endif
                                                </p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Person #{{ $alert['person_id'] ?? 'N/A' }}
                                                </p>
                                            </div>
                                            <button wire:click="handleAlert('{{ $alert['id'] ?? '' }}', 'dismissed')"
                                                    class="p-1 text-gray-400 hover:text-gray-600">
                                                <x-heroicon-o-x-mark class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Win-Back Actions --}}
                <div class="col-span-12 lg:col-span-6">
                    <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <x-heroicon-o-arrow-path class="w-5 h-5 text-purple-500" />
                                Win-Back Campaigns
                            </h3>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20">
                                <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                    {{ number_format($winBackStats['at_risk_customers'] ?? 0) }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">At-Risk</div>
                            </div>
                            <div class="p-4 rounded-xl bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20">
                                <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                                    {{ number_format($winBackStats['lapsed_customers'] ?? 0) }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Lapsed</div>
                            </div>
                            <div class="p-4 rounded-xl bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20">
                                <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                    {{ number_format($winBackStats['recently_won_back'] ?? 0) }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Won Back (30d)</div>
                            </div>
                            <div class="p-4 rounded-xl bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20">
                                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                    {{ number_format($winBackStats['potential_revenue_at_risk'] ?? 0, 0) }} <span class="text-sm">RON</span>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Revenue at Risk</div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="launchWinBack('early_warning')"
                                    wire:confirm="Send early warning emails to customers inactive 30-60 days?"
                                    class="flex-1 px-4 py-2 text-sm font-medium text-yellow-700 bg-yellow-100 rounded-lg hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Early Warning
                            </button>
                            <button wire:click="launchWinBack('win_back')"
                                    wire:confirm="Send win-back emails with discounts to customers inactive 91-180 days?"
                                    class="flex-1 px-4 py-2 text-sm font-medium text-red-700 bg-red-100 rounded-lg hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400">
                                Win-Back
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Customer Journey Funnel --}}
                <div class="col-span-12 lg:col-span-8">
                    <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-blue-500" />
                                Customer Journey Funnel
                            </h3>
                        </div>

                        @php
                            $funnel = $journeyAnalytics['transition_funnel'] ?? [];
                            $maxValue = max(1, max($funnel ?: [1]));
                            $stages = ['aware', 'interested', 'considering', 'converted', 'retained', 'loyal'];
                            $stageLabels = [
                                'aware' => 'Aware',
                                'interested' => 'Interested',
                                'considering' => 'Considering',
                                'converted' => 'Converted',
                                'retained' => 'Retained',
                                'loyal' => 'Loyal'
                            ];
                            $stageColors = [
                                'aware' => 'bg-blue-500',
                                'interested' => 'bg-cyan-500',
                                'considering' => 'bg-teal-500',
                                'converted' => 'bg-green-500',
                                'retained' => 'bg-emerald-500',
                                'loyal' => 'bg-purple-500'
                            ];
                        @endphp

                        <div class="space-y-4">
                            @foreach($stages as $stage)
                                @php
                                    $count = $funnel[$stage] ?? 0;
                                    $percentage = $maxValue > 0 ? ($count / $maxValue) * 100 : 0;
                                @endphp
                                <div class="flex items-center gap-4">
                                    <div class="w-24 text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {{ $stageLabels[$stage] }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="h-8 overflow-hidden bg-gray-100 rounded-lg dark:bg-gray-700">
                                            <div class="h-full transition-all duration-500 {{ $stageColors[$stage] }}"
                                                 style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="w-20 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($count) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Demand Forecast Summary --}}
                <div class="col-span-12 lg:col-span-4">
                    <div class="p-6 bg-white shadow-lg dark:bg-gray-800 rounded-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <x-heroicon-o-chart-bar class="w-5 h-5 text-orange-500" />
                                Sellout Risk (60d)
                            </h3>
                        </div>

                        @php
                            $riskLabels = [
                                'very_high' => ['label' => 'Very High', 'color' => 'bg-red-500', 'text' => 'text-red-600'],
                                'high' => ['label' => 'High', 'color' => 'bg-orange-500', 'text' => 'text-orange-600'],
                                'medium' => ['label' => 'Medium', 'color' => 'bg-yellow-500', 'text' => 'text-yellow-600'],
                                'low' => ['label' => 'Low', 'color' => 'bg-green-500', 'text' => 'text-green-600'],
                            ];
                            $byRisk = $demandForecasts['by_risk'] ?? [];
                        @endphp

                        <div class="space-y-3">
                            @foreach($riskLabels as $risk => $config)
                                @php $count = count($byRisk[$risk] ?? []); @endphp
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-3 h-3 rounded-full {{ $config['color'] }}"></div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $config['label'] }} Risk</span>
                                    </div>
                                    <span class="text-lg font-bold {{ $config['text'] }} dark:opacity-80">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-center text-sm text-gray-500">
                            {{ $demandForecasts['total_events'] ?? 0 }} upcoming events analyzed
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
