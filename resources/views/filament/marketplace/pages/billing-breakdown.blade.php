<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found.</p>
        </div>
    @else
        @php $d = $data; @endphp

        <!-- Month Navigation -->
        <div class="flex items-center justify-between p-4 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <button wire:click="previousMonth" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                Luna anterioară
            </button>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $d['month_label'] }}</h2>
            @if(!$d['is_current_month'])
            <button wire:click="nextMonth" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Luna următoare
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </button>
            @else
            <div></div>
            @endif
        </div>

        <!-- Summary Card -->
        <div class="grid grid-cols-1 gap-3 mb-5 lg:grid-cols-4">
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Total de plată</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($d['grand_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Comision ticketing ({{ $d['commission_rate'] }}%)</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Servicii extra</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['services_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Încasări totale</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['revenue_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
        </div>

        <!-- Revenue + Tickets Breakdown (with commission-mode split) -->
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="flex items-center gap-2 mb-1">
                <x-heroicon-o-chart-bar-square class="w-5 h-5 text-indigo-500" />
                <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Breakdown încasări &amp; bilete</h3>
            </div>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Split pe modul de comisionare al biletelor: <strong>comision inclus în preț</strong> vs <strong>comision peste preț</strong>. Baza pentru cei {{ $d['commission_rate'] }}% Tixello se ia din valoarea biletelor (regula: inclus → preț integral cu tot cu comision; peste → doar valoarea nominală a biletului, fără comisionul de deasupra).
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                <!-- Vânzări online -->
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/30">
                    <div class="flex items-center gap-2 mb-1">
                        <x-heroicon-o-globe-alt class="w-4 h-4 text-emerald-500" />
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Vânzări online <span class="text-[10px] normal-case text-gray-400">(doar canal online)</span></p>
                    </div>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($d['online_revenue'], 2) }} {{ $d['currency'] }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($d['online_orders']) }} {{ $d['online_orders'] === 1 ? 'comandă' : 'comenzi' }}
                    </p>
                    <div class="pt-2 mt-2 space-y-0.5 border-t border-gray-200 dark:border-gray-700 text-xs">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Bilete cu comision <em>inclus</em></span>
                            <span class="font-medium">{{ number_format($d['online_sold']['included_value'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Bilete cu comision <em>peste</em></span>
                            <span class="font-medium">{{ number_format($d['online_sold']['on_top_value'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Vânzări POS -->
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/30">
                    <div class="flex items-center gap-2 mb-1">
                        <x-heroicon-o-device-phone-mobile class="w-4 h-4 text-sky-500" />
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Vânzări POS <span class="text-[10px] normal-case text-gray-400">(doar canal POS)</span></p>
                    </div>
                    <p class="text-xl font-bold text-sky-600 dark:text-sky-400">
                        {{ number_format($d['pos_revenue'], 2) }} {{ $d['currency'] }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($d['pos_orders']) }} {{ $d['pos_orders'] === 1 ? 'comandă' : 'comenzi' }}
                    </p>
                    <div class="pt-2 mt-2 space-y-0.5 border-t border-gray-200 dark:border-gray-700 text-xs">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Bilete cu comision <em>inclus</em></span>
                            <span class="font-medium">{{ number_format($d['pos_sold']['included_value'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Bilete cu comision <em>peste</em></span>
                            <span class="font-medium">{{ number_format($d['pos_sold']['on_top_value'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Bilete vândute -->
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/30">
                    <div class="flex items-center gap-2 mb-1">
                        <x-heroicon-o-ticket class="w-4 h-4 text-indigo-500" />
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Bilete vândute <span class="text-[10px] normal-case text-gray-400">(online + POS)</span></p>
                    </div>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($d['sold_ticket_count']) }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        ({{ number_format($d['sold_ticket_value'], 2) }} {{ $d['currency'] }})
                    </p>
                    <div class="pt-2 mt-2 space-y-0.5 border-t border-gray-200 dark:border-gray-700 text-xs">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span><em>Inclus</em></span>
                            <span class="font-medium">{{ number_format($d['online_sold']['included_count'] + $d['pos_sold']['included_count']) }} · {{ number_format($d['online_sold']['included_value'] + $d['pos_sold']['included_value'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span><em>Peste</em></span>
                            <span class="font-medium">{{ number_format($d['online_sold']['on_top_count'] + $d['pos_sold']['on_top_count']) }} · {{ number_format($d['online_sold']['on_top_value'] + $d['pos_sold']['on_top_value'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Bilete returnate -->
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/30">
                    <div class="flex items-center gap-2 mb-1">
                        <x-heroicon-o-arrow-uturn-left class="w-4 h-4 text-rose-500" />
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Bilete returnate <span class="text-[10px] normal-case text-gray-400">(online + POS)</span></p>
                    </div>
                    <p class="text-xl font-bold text-rose-600 dark:text-rose-400">
                        {{ number_format($d['refunded_ticket_count']) }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        ({{ number_format($d['refunded_ticket_value'], 2) }} {{ $d['currency'] }})
                    </p>
                    <div class="pt-2 mt-2 space-y-0.5 border-t border-gray-200 dark:border-gray-700 text-xs">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span><em>Inclus</em></span>
                            <span class="font-medium">{{ number_format($d['online_refunded']['included_count'] + $d['pos_refunded']['included_count']) }} · {{ number_format($d['online_refunded']['included_value'] + $d['pos_refunded']['included_value'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span><em>Peste</em></span>
                            <span class="font-medium">{{ number_format($d['online_refunded']['on_top_count'] + $d['pos_refunded']['on_top_count']) }} · {{ number_format($d['online_refunded']['on_top_value'] + $d['pos_refunded']['on_top_value'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Extra: invitations + free tickets + combined ratio -->
            @php
                $invitations = (int) $d['invitation_count'];
                $freeSold = (int) $d['free_ticket_count'];
                $sold = (int) $d['sold_ticket_count'];
                // Denominator = all tickets that "reached hands": paid + free
                // (already in `sold`) + invitations (which have no order).
                $ticketTotalForRatio = max(1, $sold + $invitations);
                $invitationPct = ($invitations / $ticketTotalForRatio) * 100;
                $freePct = ($freeSold / $ticketTotalForRatio) * 100;
                $gratisPct = (($invitations + $freeSold) / $ticketTotalForRatio) * 100;
            @endphp
            <div class="grid grid-cols-3 gap-3 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 p-3 rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800">
                    <x-heroicon-o-envelope-open class="w-5 h-5 text-violet-500 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-violet-700 dark:text-violet-300">Invitații emise în lună</p>
                        <p class="text-lg font-bold text-violet-900 dark:text-violet-100">
                            {{ number_format($invitations) }}
                            <span class="text-xs font-normal text-violet-600 dark:text-violet-400">({{ number_format($invitationPct, 2) }}%)</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <x-heroicon-o-gift class="w-5 h-5 text-amber-500 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-amber-700 dark:text-amber-300">Bilete cu valoare 0 vândute</p>
                        <p class="text-lg font-bold text-amber-900 dark:text-amber-100">
                            {{ number_format($freeSold) }}
                            <span class="text-xs font-normal text-amber-600 dark:text-amber-400">({{ number_format($freePct, 2) }}%)</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                    <x-heroicon-o-scale class="w-5 h-5 text-rose-500 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-rose-700 dark:text-rose-300">Gratis din total (invitații + bilete 0)</p>
                        <p class="text-lg font-bold text-rose-900 dark:text-rose-100">
                            {{ number_format($invitations + $freeSold) }} / {{ number_format($ticketTotalForRatio) }}
                            <span class="text-xs font-normal text-rose-600 dark:text-rose-400">({{ number_format($gratisPct, 2) }}%)</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Derivare comision Tixello (transparent, breakdown pe buckets) -->
        <div class="p-5 mb-5 bg-white border-2 border-rose-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-rose-800">
            <div class="flex items-center gap-2 mb-1">
                <x-heroicon-o-calculator class="w-5 h-5 text-rose-500" />
                <h3 class="text-sm font-semibold tracking-wide text-rose-800 uppercase dark:text-rose-200">Derivare comision Tixello ({{ $d['commission_rate'] }}%)</h3>
            </div>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Baza de calcul = suma valorilor de bilet (sold + returnat, incluse și cele returnate, per regula marketplace). Se aplică {{ $d['commission_rate'] }}% pe fiecare bucket și se însumează. Exclude: comenzi test, importuri externe și importuri legacy.
            </p>

            @php
                $cb = $d['commission_by_bucket'];
                $rows = [
                    ['Online — vândute — comision inclus',   $d['online_sold']['included_value'],    $cb['online_sold_included']],
                    ['Online — vândute — comision peste',    $d['online_sold']['on_top_value'],      $cb['online_sold_on_top']],
                    ['POS — vândute — comision inclus',      $d['pos_sold']['included_value'],       $cb['pos_sold_included']],
                    ['POS — vândute — comision peste',       $d['pos_sold']['on_top_value'],         $cb['pos_sold_on_top']],
                    ['Online — returnate — comision inclus', $d['online_refunded']['included_value'],$cb['online_refunded_included']],
                    ['Online — returnate — comision peste',  $d['online_refunded']['on_top_value'],  $cb['online_refunded_on_top']],
                    ['POS — returnate — comision inclus',    $d['pos_refunded']['included_value'],   $cb['pos_refunded_included']],
                    ['POS — returnate — comision peste',     $d['pos_refunded']['on_top_value'],     $cb['pos_refunded_on_top']],
                ];
            @endphp

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Bucket</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bază ({{ $d['currency'] }})</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">× {{ $d['commission_rate'] }}%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as [$label, $base, $comm])
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 {{ $base == 0 ? 'opacity-50' : '' }}">
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $label }}</td>
                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($base, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-rose-600 dark:text-rose-400 tabular-nums">{{ number_format($comm, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-rose-300 dark:border-rose-700 bg-rose-50/50 dark:bg-rose-900/20">
                            <td class="px-3 py-3 font-semibold text-gray-900 dark:text-white">TOTAL</td>
                            <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-white tabular-nums">{{ number_format($d['ticket_base_total'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-3 text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Ticketing Breakdown per Event -->
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4">
                <x-heroicon-o-ticket class="w-5 h-5 text-indigo-500" />
                <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Comision ticketing per eveniment ({{ $d['commission_rate'] }}% din încasări)</h3>
            </div>

            @if(count($d['events']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Eveniment</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Data</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Locație</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comenzi</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bilete</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Încasări</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comision marketplace</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comision Tixello</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($d['events'] as $event)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2.5">
                                @if($event['event_id'])
                                <a href="{{ route('filament.marketplace.resources.events.edit', $event['event_id']) }}" class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $event['event_name'] }}
                                </a>
                                @else
                                <span class="font-medium text-gray-900 dark:text-white">{{ $event['event_name'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $event['event_date'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">
                                @if($event['venue'])
                                    {{ $event['venue'] }}@if($event['city']), {{ $event['city'] }}@endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($event['order_count']) }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($event['ticket_count']) }}</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ number_format($event['revenue'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right text-amber-600 dark:text-amber-400 whitespace-nowrap">{{ number_format($event['marketplace_commission'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-rose-600 dark:text-rose-400 whitespace-nowrap">{{ number_format($event['tixello_commission'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="3" class="px-3 py-2.5 font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-700 dark:text-gray-300">{{ number_format(collect($d['events'])->sum('order_count')) }}</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-700 dark:text-gray-300">{{ number_format(collect($d['events'])->sum('ticket_count')) }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ number_format($d['revenue_total'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-amber-600 dark:text-amber-400 whitespace-nowrap">{{ number_format($d['marketplace_commission_total'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Nicio comandă în această lună.</p>
            @endif
        </div>

        <!-- Service Orders Breakdown -->
        @foreach($d['services_by_type'] as $type => $service)
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @switch($type)
                        @case('featuring')
                            <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                            @break
                        @case('email')
                            <x-heroicon-o-envelope class="w-5 h-5 text-blue-500" />
                            @break
                        @case('tracking')
                            <x-heroicon-o-chart-bar class="w-5 h-5 text-green-500" />
                            @break
                        @case('campaign')
                            <x-heroicon-o-megaphone class="w-5 h-5 text-purple-500" />
                            @break
                    @endswitch
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">{{ $service['label'] }}</h3>
                </div>
                <span class="text-sm font-bold {{ $service['total'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ number_format($service['total'], 2) }} {{ $d['currency'] }}
                </span>
            </div>

            @if(count($service['orders']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Comandă</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Eveniment</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Organizator</th>
                            @if($type !== 'tracking')
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Pachet</th>
                            @else
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Plan</th>
                            @endif
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Data</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Valoare totală</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-rose-600 dark:text-rose-400">Cotă Tixello (50%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($service['orders'] as $order)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['order_number'] ?? '#' . $order['id'] }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $order['event_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['organizer_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['config_label'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $order['created_at'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 whitespace-nowrap dark:text-gray-300">{{ number_format($order['gross_total'] ?? $order['total'] * 2, 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2 font-medium text-right text-rose-600 whitespace-nowrap dark:text-rose-400">{{ number_format($order['total'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Nicio comandă pentru acest serviciu în luna selectată.</p>
            @endif
        </div>
        @endforeach

        <!-- Grand Total Footer -->
        <div class="p-5 bg-white border-2 shadow-sm dark:bg-gray-800 rounded-xl border-rose-200 dark:border-rose-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Total de plată către Tixello</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $d['month_label'] }}</p>
                </div>
                <p class="text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($d['grand_total'], 2) }} <span class="text-lg">{{ $d['currency'] }}</span></p>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Comision ticketing</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Servicii extra</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['services_total'], 2) }} {{ $d['currency'] }}</p>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
