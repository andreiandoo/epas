<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found. Please contact support.</p>
        </div>
    @else
        <div class="mb-4">
            <a href="{{ route('filament.marketplace.pages.dashboard') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Înapoi la Dashboard
            </a>
        </div>

        <!-- All Time Stats -->
        <div class="mb-5">
        <h3 class="flex items-center gap-2 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
            <x-heroicon-o-chart-bar-square class="w-4 h-4" />
            All Time
        </h3>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {{-- 1. Evenimente --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/30 shrink-0">
                        <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_events']) }}</p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Evenimente</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">{{ $stats['active_events'] }} active</p>
                    </div>
                </div>
            </div>

            {{-- 2. Clienți --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 shrink-0">
                        <x-heroicon-o-users class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_customers']) }}</p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Clienți</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ number_format($stats['registered_customers']) }} înregistrați · {{ number_format($stats['guest_customers']) }} oaspeți
                        </p>
                    </div>
                </div>
            </div>

            {{-- 3. Comenzi --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg dark:bg-indigo-900/30 shrink-0">
                        <x-heroicon-o-shopping-cart class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_orders']) }}</p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Comenzi</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <span class="text-green-600 dark:text-green-400">{{ number_format($stats['paid_orders']) }} valide</span>
                            @if($stats['other_orders'] > 0)
                                · <span class="text-gray-400">{{ number_format($stats['other_orders']) }} altele</span>
                            @endif
                            @if($stats['today_orders'] > 0)
                                · <span class="text-blue-600 dark:text-blue-400">+{{ $stats['today_orders'] }} azi</span>
                            @endif
                            @if(($stats['external_orders'] ?? 0) > 0)
                                · <span class="text-indigo-400">🌐 {{ number_format($stats['external_orders']) }} import</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 4. Încasări --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/30 shrink-0">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_incasari'], 2) }} <span class="text-sm font-medium text-gray-400">{{ $currency }}</span></p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Încasări</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Comenzi: {{ number_format($stats['order_revenue'], 2) }}
                            @if($stats['service_revenue'] > 0)
                                · Servicii: {{ number_format($stats['service_revenue'], 2) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 5. Venituri (comisioane + servicii) --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 shrink-0">
                        <x-heroicon-o-currency-euro class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['all_time_commissions'] + $stats['service_orders_total'], 2) }} <span class="text-sm font-medium text-gray-400">{{ $currency }}</span></p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Venituri Marketplace</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Comisioane: {{ number_format($stats['all_time_commissions'], 2) }}
                            @if($stats['service_orders_total'] > 0)
                                · Servicii: {{ number_format($stats['service_orders_total'], 2) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 6. Bilete vândute --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-100 rounded-lg dark:bg-purple-900/30 shrink-0">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_tickets_db']) }}</p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Bilete Total</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ number_format($stats['total_tickets']) }} valide
                            @if($stats['today_tickets'] > 0)
                                · <span class="text-blue-600 dark:text-blue-400">+{{ $stats['today_tickets'] }} azi</span>
                            @endif
                            @if(($stats['external_tickets'] ?? 0) > 0)
                                · <span class="text-indigo-400">🌐 {{ number_format($stats['external_tickets']) }} import</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 7. Organizatori --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 shrink-0">
                        <x-heroicon-o-user-group class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_organizers']) }}</p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Organizatori</p>
                        <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">{{ $stats['active_organizers'] }} activi</p>
                    </div>
                </div>
            </div>

            {{-- 8. Payouts --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-teal-100 rounded-lg dark:bg-teal-900/30 shrink-0">
                        <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['completed_payouts_value'], 2) }} <span class="text-sm font-medium text-gray-400">{{ $currency }}</span></p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Deconturi Achitate</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">{{ number_format($stats['pending_payouts_value'], 2) }} in asteptare</p>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Legacy (importat) vs Tixello breakdown -->
        @php
            $imp = $breakdown['imported'] ?? [];
            $tix = $breakdown['tixello'] ?? [];
            $tot = $breakdown['total'] ?? [];
            $rows = [
                ['Comenzi', number_format($imp['orders'] ?? 0), number_format($tix['orders'] ?? 0), number_format($tot['orders'] ?? 0)],
                ['Bilete', number_format($imp['tickets'] ?? 0), number_format($tix['tickets'] ?? 0), number_format($tot['tickets'] ?? 0)],
                ['Clienți', number_format($imp['customers'] ?? 0), number_format($tix['customers'] ?? 0), number_format($tot['customers'] ?? 0)],
                ['Încasări ('.$currency.')', number_format($imp['revenue'] ?? 0, 2), number_format($tix['revenue'] ?? 0, 2), number_format($tot['revenue'] ?? 0, 2)],
            ];
        @endphp
        <div class="mb-5">
            <h3 class="flex items-center gap-2 mb-1 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                <x-heroicon-o-scale class="w-4 h-4" />
                Importat (legacy) vs Procesat prin Tixello
            </h3>
            <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">
                Coloana <strong>Total</strong> = exact cifrele din cardurile de mai sus (Importat + Tixello = Total).
                „Importat” = partea provenită din migrare (surse <code>external_import</code> / <code>legacy_import</code>);
                „Procesat prin Tixello” = restul (Total − Importat). Notă: la Încasări, importul e doar cel legacy — importurile externe nu sunt contorizate în încasări (nici în carduri).
            </p>
            <div class="overflow-x-auto bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Metrică</th>
                            <th class="px-4 py-3 font-semibold text-right">
                                <span class="inline-flex items-center gap-1"><span class="text-indigo-400">🌐</span> Importat (legacy)</span>
                            </th>
                            <th class="px-4 py-3 font-semibold text-right">
                                <span class="inline-flex items-center gap-1"><span class="text-emerald-500">⚡</span> Procesat prin Tixello</span>
                            </th>
                            <th class="px-4 py-3 font-semibold text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach($rows as $r)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $r[0] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-indigo-600 dark:text-indigo-400">{{ $r[1] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-400">{{ $r[2] }}</td>
                            <td class="px-4 py-3 font-semibold text-right tabular-nums text-gray-900 dark:text-white">{{ $r[3] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
