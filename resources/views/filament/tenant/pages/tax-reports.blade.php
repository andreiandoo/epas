<x-filament-panels::page>
    @php
        $report = $this->getViewData()['report'] ?? null;
        $tenant = $this->getViewData()['tenant'] ?? null;
        $filteredEvents = $this->getViewData()['filteredEvents'] ?? [];
        $filteredTotals = $this->getViewData()['filteredTotals'] ?? [];
        $upcomingDeadlines = $this->getViewData()['upcomingDeadlines'] ?? [];
        $overduePayments = $this->getViewData()['overduePayments'] ?? [];
        $taxSummary = $this->getViewData()['taxSummary'] ?? [];
    @endphp

    @if(!$report)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-12 text-center">
            <x-heroicon-o-calculator class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No tax data available</h3>
            <p class="text-gray-600 dark:text-gray-400">Tax reports will appear here once you have events.</p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Header with Tenant Info --}}
            <div class="p-6 text-white shadow-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="mb-2 text-2xl font-bold">Tax Report</h2>
                        <p class="text-sm text-emerald-100">{{ $tenant->public_name ?? $tenant->name }}</p>
                        <div class="flex items-center gap-4 mt-3">
                            <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $tenant->vat_payer ? 'bg-green-500/30 text-green-100' : 'bg-yellow-500/30 text-yellow-100' }}">
                                @if($tenant->vat_payer)
                                    <x-heroicon-s-check-circle class="w-4 h-4 mr-1" />
                                    Platitor TVA
                                @else
                                    <x-heroicon-s-minus-circle class="w-4 h-4 mr-1" />
                                    Neplatitor TVA
                                @endif
                            </span>
                            @if($tenant->cui)
                                <span class="text-sm text-emerald-100">CUI: {{ $tenant->cui }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-emerald-100">Generated</div>
                        <div class="text-lg font-semibold">{{ now()->format('d M Y H:i') }}</div>
                    </div>
                </div>
            </div>

            {{-- Alert for Overdue Payments --}}
            @if(count($overduePayments) > 0)
                <div class="p-4 border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 rounded-xl">
                    <div class="flex gap-3">
                        <x-heroicon-s-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" />
                        <div class="flex-1">
                            <h3 class="font-semibold text-red-900 dark:text-red-100">{{ count($overduePayments) }} plati restante</h3>
                            <div class="mt-2 space-y-2">
                                @foreach($overduePayments as $payment)
                                    <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded-lg text-sm">
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $payment['tax_name'] }}</span>
                                            <span class="text-gray-500 dark:text-gray-400">- {{ $payment['event'] }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-bold text-red-600">{{ number_format($payment['amount'], 2) }} RON</span>
                                            <span class="block text-xs text-red-500">Scadent: {{ $payment['deadline'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Filters --}}
            <div class="flex flex-wrap gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</label>
                    <select wire:model.live="filterStatus" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="all">Toate</option>
                        <option value="upcoming">Viitoare</option>
                        <option value="past">Trecute</option>
                        <option value="cancelled">Anulate</option>
                        <option value="postponed">Amanate</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Perioada:</label>
                    <select wire:model.live="filterPeriod" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="all">Toate</option>
                        <option value="upcoming">Viitoare</option>
                        <option value="this_month">Luna aceasta</option>
                        <option value="this_quarter">Trimestrul acesta</option>
                        <option value="this_year">Anul acesta</option>
                        <option value="past">Trecute</option>
                    </select>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                            <x-heroicon-s-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Evenimente</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $filteredTotals['event_count'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/50 flex items-center justify-center">
                            <x-heroicon-s-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Venit estimat</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($filteredTotals['total_revenue'] ?? 0, 0) }} <span class="text-sm font-normal">RON</span></div>
                        </div>
                    </div>
                </div>
                <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                            <x-heroicon-s-receipt-percent class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Total taxe</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($filteredTotals['total_tax'] ?? 0, 0) }} <span class="text-sm font-normal">RON</span></div>
                        </div>
                    </div>
                </div>
                <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center">
                            <x-heroicon-s-chart-pie class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Rata efectiva</div>
                            @php
                                $effectiveRate = ($filteredTotals['total_revenue'] ?? 0) > 0
                                    ? (($filteredTotals['total_tax'] ?? 0) / ($filteredTotals['total_revenue'] ?? 1)) * 100
                                    : 0;
                            @endphp
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($effectiveRate, 1) }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Content - Events List --}}
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-document-chart-bar class="w-5 h-5 text-gray-400" />
                        Tax Breakdown per Event
                    </h3>

                    @forelse($filteredEvents as $eventReport)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                            {{-- Event Header --}}
                            <div class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900 dark:text-white text-lg">
                                            {{ $eventReport['event']['title'] }}
                                        </h4>
                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-calendar class="w-4 h-4" />
                                                {{ $eventReport['event']['date'] ?? 'TBD' }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-map-pin class="w-4 h-4" />
                                                {{ $eventReport['event']['venue'] }}
                                            </span>
                                        </div>
                                        @if(!empty($eventReport['event_types']))
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach($eventReport['event_types'] as $type)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                                        {{ $type['name'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        @php
                                            $statusColors = [
                                                'upcoming' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                                'past' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                                'postponed' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                                            ];
                                            $statusLabels = [
                                                'upcoming' => 'Viitor',
                                                'past' => 'Trecut',
                                                'cancelled' => 'Anulat',
                                                'postponed' => 'Amanat',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$eventReport['event']['status']] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statusLabels[$eventReport['event']['status']] ?? $eventReport['event']['status'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Financial Summary --}}
                            <div class="grid grid-cols-4 divide-x divide-gray-100 dark:divide-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/50 dark:to-gray-800">
                                <div class="p-3 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Venit</div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($eventReport['estimated_revenue'], 0) }}</div>
                                </div>
                                <div class="p-3 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Taxe</div>
                                    <div class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format($eventReport['total_tax'], 0) }}</div>
                                </div>
                                <div class="p-3 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Rata</div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $eventReport['effective_tax_rate'] }}%</div>
                                </div>
                                <div class="p-3 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net</div>
                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($eventReport['net_revenue'], 0) }}</div>
                                </div>
                            </div>

                            {{-- Tax Breakdown --}}
                            @if(!empty($eventReport['taxes']))
                                <div class="p-4">
                                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Detalii taxe aplicabile:</h5>
                                    <div class="space-y-3">
                                        @foreach($eventReport['taxes'] as $tax)
                                            <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-medium text-gray-900 dark:text-white">{{ $tax['name'] }}</span>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $tax['type'] === 'general' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300' }}">
                                                                {{ $tax['type'] === 'general' ? 'General' : 'Local' }}
                                                            </span>
                                                            @if($tax['is_added_to_price'])
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300">
                                                                    + pret
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                            Rata: {{ $tax['formatted_value'] }}
                                                        </div>
                                                        @if(!empty($tax['explanation']))
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                                                {{ \Illuminate\Support\Str::limit($tax['explanation'], 100) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                            {{ number_format($tax['amount'], 2) }} <span class="text-xs font-normal">RON</span>
                                                        </div>
                                                        @if(!empty($tax['payment_deadline']))
                                                            <div class="text-xs {{ $tax['payment_deadline']['is_overdue'] ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                                                @if($tax['payment_deadline']['is_overdue'])
                                                                    <x-heroicon-s-exclamation-circle class="w-3 h-3 inline" />
                                                                    RESTANT
                                                                @else
                                                                    Scadent: {{ $tax['payment_deadline']['date'] }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- Payment Info Accordion --}}
                                                @if(!empty($tax['payment_info']))
                                                    <details class="mt-3 group">
                                                        <summary class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400 cursor-pointer hover:text-emerald-700">
                                                            <x-heroicon-o-information-circle class="w-4 h-4" />
                                                            Informatii plata
                                                            <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform group-open:rotate-180" />
                                                        </summary>
                                                        <div class="mt-2 p-3 bg-white dark:bg-gray-800 rounded-lg text-sm space-y-2 border border-gray-200 dark:border-gray-600">
                                                            @if($tax['payment_info']['beneficiary'])
                                                                <div class="flex">
                                                                    <span class="w-24 text-gray-500 dark:text-gray-400 flex-shrink-0">Beneficiar:</span>
                                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $tax['payment_info']['beneficiary'] }}</span>
                                                                </div>
                                                            @endif
                                                            @if($tax['payment_info']['iban'])
                                                                <div class="flex">
                                                                    <span class="w-24 text-gray-500 dark:text-gray-400 flex-shrink-0">IBAN:</span>
                                                                    <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $tax['payment_info']['iban'] }}</span>
                                                                </div>
                                                            @endif
                                                            @if($tax['payment_info']['address'])
                                                                <div class="flex">
                                                                    <span class="w-24 text-gray-500 dark:text-gray-400 flex-shrink-0">Adresa:</span>
                                                                    <span class="text-gray-900 dark:text-white">{{ $tax['payment_info']['address'] }}</span>
                                                                </div>
                                                            @endif
                                                            @if($tax['payment_info']['where_to_pay'])
                                                                <div class="flex">
                                                                    <span class="w-24 text-gray-500 dark:text-gray-400 flex-shrink-0">Unde:</span>
                                                                    <span class="text-gray-900 dark:text-white">{{ $tax['payment_info']['where_to_pay'] }}</span>
                                                                </div>
                                                            @endif
                                                            @if($tax['payment_deadline'])
                                                                <div class="flex">
                                                                    <span class="w-24 text-gray-500 dark:text-gray-400 flex-shrink-0">Termen:</span>
                                                                    <span class="text-gray-900 dark:text-white">{{ $tax['payment_deadline']['description'] }}</span>
                                                                </div>
                                                            @endif
                                                            @if($tax['legal_basis'])
                                                                <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
                                                                    <span class="text-xs text-gray-500 dark:text-gray-400">Temei legal: {{ $tax['legal_basis'] }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </details>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-check-circle class="w-8 h-8 mx-auto mb-2 text-green-500" />
                                    Nicio taxa aplicabila pentru acest eveniment
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                            <p class="text-gray-500 dark:text-gray-400">Niciun eveniment gasit cu filtrele selectate</p>
                        </div>
                    @endforelse
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">
                    {{-- Upcoming Deadlines --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20">
                            <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-s-clock class="w-5 h-5 text-amber-500" />
                                Termene apropiate
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-80 overflow-y-auto">
                            @forelse($upcomingDeadlines as $deadline)
                                <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium text-sm text-gray-900 dark:text-white">{{ $deadline['tax_name'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $deadline['event'] }}</div>
                                        </div>
                                        <span class="font-semibold text-amber-600 dark:text-amber-400 text-sm">{{ number_format($deadline['amount'], 0) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $deadline['days_remaining'] <= 7 ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $deadline['deadline'] }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            ({{ $deadline['days_remaining'] }} zile)
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                    <x-heroicon-o-check-circle class="w-6 h-6 mx-auto mb-1 text-green-500" />
                                    Niciun termen in urmatoarele 30 zile
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Tax Summary by Type --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20">
                            <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-s-chart-bar class="w-5 h-5 text-indigo-500" />
                                Sumar pe tip taxa
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($taxSummary as $summary)
                                <div class="p-3">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full {{ $summary['type'] === 'general' ? 'bg-indigo-500' : 'bg-teal-500' }}"></span>
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $summary['name'] }}</span>
                                        </div>
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_amount'], 0) }} RON</span>
                                    </div>
                                    @if(!empty($summary['payment_info']['beneficiary']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-4">
                                            {{ $summary['payment_info']['beneficiary'] }}
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                    Niciun sumar disponibil
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-gray-200 dark:border-gray-600">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-bolt class="w-5 h-5 text-yellow-500" />
                            Informatii utile
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-600 dark:text-gray-300">Taxele sunt estimate pe baza tipului de eveniment si locatie.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <x-heroicon-o-calculator class="w-4 h-4 text-purple-500 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-600 dark:text-gray-300">Venitul este estimat la 50% din capacitatea biletelor.</span>
                            </div>
                            @if(!$tenant->vat_payer)
                                <div class="flex items-start gap-2">
                                    <x-heroicon-o-exclamation-circle class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                                    <span class="text-gray-600 dark:text-gray-300">Taxele TVA nu sunt incluse (nu sunteti platitor TVA).</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
