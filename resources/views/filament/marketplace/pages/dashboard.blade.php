<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found. Please contact support.</p>
        </div>
    @else
        @if($isSuperAdmin ?? false)
        <!-- Pending Review Events -->
        @if(isset($pendingReviewEvents) && $pendingReviewEvents->count() > 0)
        <div class="mb-5 overflow-hidden bg-white border shadow-sm dark:bg-gray-800 rounded-xl border-amber-300 dark:border-amber-700">
            <div class="flex items-center justify-between px-4 py-3 border-b bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500" />
                    <h3 class="font-semibold text-amber-800 dark:text-amber-200">Evenimente de revizuit</h3>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold text-white bg-amber-500 rounded-full">{{ $pendingReviewEvents->count() }}</span>
                </div>
                <a href="{{ route('filament.marketplace.resources.events.index') }}?tableFilters[is_published][value]=0" class="text-xs text-amber-600 dark:text-amber-400 hover:underline">
                    Vezi toate
                </a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($pendingReviewEvents->take(10) as $event)
                <div class="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="text-sm font-medium text-gray-900 truncate dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
                                {{ $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') }}
                            </a>
                            @if($event->suggested_venue_name)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 rounded">
                                    <x-heroicon-o-map-pin class="w-3 h-3" /> {{ $event->suggested_venue_name }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($event->marketplaceOrganizer)
                                <span>{{ $event->marketplaceOrganizer->name }}</span>
                            @endif
                            @if($event->event_date)
                                <span>{{ $event->event_date->format('d.m.Y') }}</span>
                            @endif
                            @if($event->venue)
                                <span>{{ $event->venue->getTranslation('name', 'ro') }}</span>
                            @endif
                            <span class="text-gray-400">Trimis {{ $event->submitted_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 dark:text-amber-300 dark:bg-amber-900/40 dark:hover:bg-amber-900/60 rounded-lg transition-colors">
                        <x-heroicon-o-eye class="w-3.5 h-3.5" />
                        Revizuieste
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Today Stats -->
        @if(isset($todayStats))
        <div class="mb-5">
            <h3 class="flex items-center gap-2 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                <x-heroicon-o-sun class="w-4 h-4" />
                Azi — {{ $todayStats['date_label'] }}
            </h3>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comenzi azi</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($todayStats['total_orders']) }}</p>
                    @if(($todayStats['total_orders'] ?? 0) > 0)
                    <p class="mt-0.5 text-[10px] text-gray-400">{{ $todayStats['paid_orders'] ?? 0 }} plătite · {{ $todayStats['pending_orders'] ?? 0 }} pending · {{ $todayStats['failed_orders'] ?? 0 }} eșuate · {{ $todayStats['cancelled_orders'] ?? 0 }} anulate · {{ $todayStats['expired_orders'] ?? 0 }} expirate · {{ $todayStats['refunded_orders'] ?? 0 }} rambursate</p>
                    @endif
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comisioane azi</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($todayStats['commission'] ?? 0, 2) }} <span class="text-sm font-normal text-gray-400">RON</span></p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Încasări azi</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($todayStats['revenue'], 0) }} <span class="text-sm font-normal text-gray-400">RON</span></p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bilete vândute</p>
                    <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($todayStats['tickets_sold']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Evenimente publicate azi</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($todayStats['events_published'] ?? 0) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Clienți noi</p>
                    <p class="mt-1 text-2xl font-bold text-cyan-600 dark:text-cyan-400">{{ number_format($todayStats['new_customers']) }}</p>
                    <p class="mt-0.5 text-[10px] text-gray-400">{{ $todayStats['registered_customers'] ?? 0 }} registered · {{ $todayStats['guest_customers'] ?? 0 }} guest</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Combined Chart: Sales + Tickets -->
        <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700" wire:key="charts-{{ $chartPeriod }}">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-4">
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Vânzări & Bilete</h3>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-indigo-500 rounded"></span> Vânzări (RON)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-purple-500/70"></span> Bilete</span>
                        @if($chartPeriod === 'month')
                        <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-yellow-500/50 rounded" style="border-top: 1.5px dashed rgba(202,138,4,0.5);"></span> Anul trecut</span>
                        @endif
                    </div>
                </div>
                <select
                    wire:model.live="chartPeriod"
                    class="py-1 text-xs border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                >
                    <option value="month">Luna în curs</option>
                    <option value="7">7 zile</option>
                    <option value="15">15 zile</option>
                    <option value="30">30 zile</option>
                    <option value="60">60 zile</option>
                    <option value="90">90 zile</option>
                </select>
            </div>
            <div class="h-64">
                <canvas id="combinedChart" data-sales='@json($chartData)' data-tickets='@json($ticketChartData)' data-prev-sales='@json($prevYearChartData ?? null)' data-prev-tickets='@json($prevYearTicketChartData ?? null)' data-currency="RON"></canvas>
            </div>
        </div>

        <!-- Monthly Stats -->
        @if(isset($monthStats))
        <div class="mb-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    <x-heroicon-o-calendar-days class="w-4 h-4" />
                    {{ $monthStats['month_label'] }}
                </h3>
                <input type="month"
                    wire:model.live="selectedMonth"
                    class="py-1 text-xs border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                    max="{{ now()->format('Y-m') }}"
                />
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Organizatori noi</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($monthStats['new_organizers']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Evenimente live acum</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($monthStats['live_events']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Evenimente încheiate</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($monthStats['ended_events']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total vânzări</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($monthStats['total_sales'], 0) }} <span class="text-sm font-normal text-gray-400">{{ $monthStats['currency'] }}</span></p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Venituri brute</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($monthStats['total_commission'], 0) }} <span class="text-sm font-normal text-gray-400">{{ $monthStats['currency'] }}</span></p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bilete vândute</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($monthStats['tickets_sold']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Clienți noi</p>
                    <p class="mt-1 text-2xl font-bold text-cyan-600 dark:text-cyan-400">{{ number_format($monthStats['new_customers']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comenzi</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($monthStats['month_orders']) }}</p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Deconturi în așteptare</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($monthStats['payouts_pending'], 0) }} <span class="text-sm font-normal text-gray-400">{{ $monthStats['currency'] }}</span></p>
                </div>
                <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Deconturi achitate</p>
                    <p class="mt-1 text-2xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($monthStats['payouts_paid'], 0) }} <span class="text-sm font-normal text-gray-400">{{ $monthStats['currency'] }}</span></p>
                </div>
            </div>
        </div>
        @endif

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
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_incasari'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
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
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['all_time_commissions'] + $stats['service_orders_total'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
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
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['completed_payouts_value'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
                        <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Deconturi Achitate</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">{{ number_format($stats['pending_payouts_value'], 2) }} in asteptare</p>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Tixello Monthly Billing -->
        @if(isset($billing))
        <a href="{{ route('filament.marketplace.pages.billing-breakdown') }}" class="block p-5 mb-5 transition-colors bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700 hover:border-rose-300 dark:hover:border-rose-700 group">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-rose-100 dark:bg-rose-900/30">
                        <x-heroicon-o-document-currency-dollar class="w-5 h-5 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">De plată către Tixello</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $billing['month_label'] }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($billing['grand_total'], 2) }} <span class="text-sm font-medium">{{ $billing['currency'] }}</span></p>
                    </div>
                    <x-heroicon-o-arrow-right class="w-5 h-5 text-gray-400 transition-colors group-hover:text-rose-500" />
                </div>
            </div>

            <div class="space-y-2">
                {{-- Ticketing commission --}}
                <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Comision ticketing ({{ $billing['commission_rate'] }}% din {{ number_format($billing['order_revenue'], 2) }})</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($billing['ticketing_commission'], 2) }} {{ $billing['currency'] }}</span>
                </div>

                {{-- Service breakdown - always show all --}}
                @foreach($billing['services'] as $service)
                <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                    <div class="flex items-center gap-2">
                        @switch($service['type'])
                            @case('featuring')
                                <x-heroicon-o-star class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                @break
                            @case('email')
                                <x-heroicon-o-envelope class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                @break
                            @case('tracking')
                                <x-heroicon-o-chart-bar class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                @break
                            @case('campaign')
                                <x-heroicon-o-megaphone class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                @break
                            @default
                                <x-heroicon-o-cog-6-tooth class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        @endswitch
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $service['label'] }}</span>
                    </div>
                    <span class="text-sm font-semibold {{ $service['amount'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">{{ number_format($service['amount'], 2) }} {{ $billing['currency'] }}</span>
                </div>
                @endforeach

                <div class="flex items-center justify-between px-3 py-2 mt-1 border-t border-gray-200 dark:border-gray-600">
                    <span class="text-xs text-gray-500 uppercase dark:text-gray-400">Subtotal servicii</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ number_format($billing['services_total'], 2) }} {{ $billing['currency'] }}</span>
                </div>
            </div>
        </a>
        @endif
        @endif {{-- end isSuperAdmin --}}

        <!-- Tables: Top Organizers + Top Live Events side by side -->
        <div class="grid grid-cols-1 gap-5 mb-5 md:grid-cols-2">
            {{-- Top Organizers --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <h3 class="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Top Organizatori</h3>
                @if($topOrganizers && $topOrganizers->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Organizator</th>
                                    <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Încasări</th>
                                    <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bilete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topOrganizers as $organizer)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[180px]">{{ $organizer->company_name ?? $organizer->name }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-sm font-medium text-right text-gray-900 dark:text-white whitespace-nowrap">
                                        {{ number_format($organizer->total_revenue ?? 0, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-gray-600 dark:text-gray-300">
                                        {{ number_format($organizer->total_tickets_sold ?? 0) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Niciun organizator activ.</p>
                @endif
            </div>

            {{-- Top Live Events --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <h3 class="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Top Evenimente Live</h3>
                @if($topLiveEvents && $topLiveEvents->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Eveniment</th>
                                    <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Încasări</th>
                                    <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bilete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topLiveEvents as $event)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[180px]">{{ $event->name }}</p>
                                        <p class="text-xs text-gray-400">
                                            @if($event->duration_mode === 'single_day' && $event->event_date)
                                                {{ \Carbon\Carbon::parse($event->event_date)->format('d M Y') }}
                                            @elseif($event->range_start_date)
                                                {{ \Carbon\Carbon::parse($event->range_start_date)->format('d M') }} - {{ \Carbon\Carbon::parse($event->range_end_date)->format('d M Y') }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-sm font-medium text-right text-gray-900 dark:text-white whitespace-nowrap">
                                        {{ number_format($event->event_revenue ?? 0, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-gray-600 dark:text-gray-300">
                                        {{ number_format($event->sold_tickets_count ?? 0) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Niciun eveniment live.</p>
                @endif
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() { initCombinedChart(); });
        document.addEventListener('livewire:navigated', function() { initCombinedChart(); });
        document.addEventListener('charts-updated', function() { setTimeout(() => initCombinedChart(), 100); });
        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector && el.querySelector('#combinedChart')) {
                setTimeout(() => initCombinedChart(), 100);
            }
        });

        function initCombinedChart() {
            const ctx = document.getElementById('combinedChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            const isDark = document.documentElement.classList.contains('dark');

            const salesStr = ctx.getAttribute('data-sales');
            const ticketsStr = ctx.getAttribute('data-tickets');
            const prevSalesStr = ctx.getAttribute('data-prev-sales');
            const prevTicketsStr = ctx.getAttribute('data-prev-tickets');
            if (!salesStr || !ticketsStr) return;

            const salesData = JSON.parse(salesStr);
            const ticketData = JSON.parse(ticketsStr);
            const prevSalesData = prevSalesStr && prevSalesStr !== 'null' ? JSON.parse(prevSalesStr) : null;
            const prevTicketsData = prevTicketsStr && prevTicketsStr !== 'null' ? JSON.parse(prevTicketsStr) : null;
            const currency = ctx.getAttribute('data-currency') || 'RON';

            const datasets = [
                {
                    type: 'line',
                    label: 'Vânzări (RON)',
                    data: salesData.data,
                    borderColor: isDark ? '#818cf8' : '#6366f1',
                    backgroundColor: isDark ? 'rgba(129, 140, 248, 0.08)' : 'rgba(99, 102, 241, 0.08)',
                    borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2, pointHoverRadius: 4,
                    yAxisID: 'y',
                    order: 1,
                },
                {
                    type: 'bar',
                    label: 'Bilete',
                    data: ticketData.data,
                    backgroundColor: isDark ? 'rgba(168, 85, 247, 0.6)' : 'rgba(147, 51, 234, 0.6)',
                    borderColor: isDark ? '#a855f7' : '#9333ea',
                    borderWidth: 1, borderRadius: 3,
                    yAxisID: 'y1',
                    order: 2,
                }
            ];

            // Previous year comparison (only for month view)
            if (prevSalesData) {
                datasets.push({
                    type: 'line',
                    label: 'Anul trecut (RON)',
                    data: prevSalesData.data,
                    borderColor: isDark ? 'rgba(234, 179, 8, 0.5)' : 'rgba(202, 138, 4, 0.5)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5, borderDash: [4, 4], fill: false, tension: 0.3, pointRadius: 0, pointHoverRadius: 3,
                    yAxisID: 'y',
                    order: 0,
                });
            }
            if (prevTicketsData) {
                datasets.push({
                    type: 'bar',
                    label: 'Bilete anul trecut',
                    data: prevTicketsData.data,
                    backgroundColor: isDark ? 'rgba(234, 179, 8, 0.15)' : 'rgba(202, 138, 4, 0.15)',
                    borderColor: isDark ? 'rgba(234, 179, 8, 0.3)' : 'rgba(202, 138, 4, 0.3)',
                    borderWidth: 1, borderRadius: 3,
                    yAxisID: 'y1',
                    order: 3,
                });
            }

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: salesData.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1, padding: 10, displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.yAxisID === 'y') {
                                        return 'Vânzări: ' + new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(context.parsed.y) + ' ' + currency;
                                    }
                                    return 'Bilete: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: isDark ? '#9ca3af' : '#6b7280', maxRotation: 45, font: { size: 10 } } },
                        y: {
                            type: 'linear', position: 'left', beginAtZero: true,
                            grid: { color: isDark ? '#374151' : '#f3f4f6' },
                            ticks: { color: isDark ? '#818cf8' : '#6366f1', font: { size: 10 }, callback: (v) => new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(v) }
                        },
                        y1: {
                            type: 'linear', position: 'right', beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { color: isDark ? '#a855f7' : '#9333ea', font: { size: 10 }, stepSize: 1, callback: (v) => Number.isInteger(v) ? v : '' }
                        }
                    }
                }
            });
        }
    </script>
    @endpush
</x-filament-panels::page>
