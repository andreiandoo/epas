<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found.</p>
        </div>
    @else
        @php $d = $data; @endphp

        {{-- ================================================================ --}}
        {{-- Month Navigation                                                  --}}
        {{-- ================================================================ --}}
        <div class="flex items-center justify-between p-4 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            {{-- Plain full-page links (NOT wire:navigate): SPA nav re-runs the
                 panel's inline scripts which redeclare `const EP_PANELS` and
                 throw. Same reason as BillingBreakdown. --}}
            <a href="{{ $d['prev_url'] }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                Luna anterioară
            </a>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $d['month_label'] }}</h2>
            @if(!$d['is_current_month'] && $d['next_url'])
                <a href="{{ $d['next_url'] }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Luna următoare
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </a>
            @else
                <div></div>
            @endif
        </div>

        {{-- ================================================================ --}}
        {{-- Summary tiles                                                     --}}
        {{-- ================================================================ --}}
        <div class="grid grid-cols-1 gap-3 mb-5 lg:grid-cols-4">
            <div class="p-4 border shadow-sm border-primary-200 bg-primary-50 dark:bg-primary-900/20 dark:border-primary-800 rounded-xl">
                <p class="text-xs tracking-wide uppercase text-primary-700 dark:text-primary-300">Total vânzări</p>
                <p class="mt-1 text-2xl font-bold text-primary-700 dark:text-primary-300">
                    {{ number_format($d['sales_total'], 2) }} {{ $d['currency'] }}
                </p>
                <p class="mt-0.5 text-xs text-primary-700/70 dark:text-primary-300/70">
                    Online + POS &middot; {{ number_format($d['online_order_count'] + $d['pos_order_count']) }} comenzi &middot; {{ number_format($d['online_ticket_count'] + $d['pos_ticket_count']) }} bilete
                </p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Încasat online</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ number_format($d['online_total'], 2) }} {{ $d['currency'] }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($d['online_order_count']) }} comenzi &middot; {{ number_format($d['online_ticket_count']) }} bilete
                </p>
                @if($d['refunded_orders_count'] > 0)
                    <p class="pt-2 mt-2 text-[11px] leading-tight text-gray-500 border-t border-gray-100 dark:text-gray-400 dark:border-gray-700">
                        <span class="font-semibold">+ {{ number_format($d['refunded_orders_count']) }} comenzi refund-ate complet ({{ number_format($d['refunded_orders_total'], 2) }} {{ $d['currency'] }})</span>
                        au fost inițial captate prin procesator. Le vezi în dashboard-ul Netopia la <em>Tranzacții acceptate</em>, dar nu apar aici pentru că banii au plecat înapoi. Total <em>acceptate</em> comparabil: <strong>{{ number_format($d['processor_accepted_total'], 2) }} {{ $d['currency'] }}</strong>.
                    </p>
                @endif
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Returnat prin procesator</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">
                    {{ number_format($d['refund_total'], 2) }} {{ $d['currency'] }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($d['refund_order_count']) }} comenzi &middot; {{ number_format($d['refund_ticket_count']) }} bilete
                </p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Încasat POS</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ number_format($d['pos_total'], 2) }} {{ $d['currency'] }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($d['pos_order_count']) }} comenzi &middot; {{ number_format($d['pos_ticket_count']) }} bilete
                </p>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- BLOCK A — ONLINE SALES per event (accordion, collapsed default)   --}}
        {{-- ================================================================ --}}
        <div x-data="{ open: false }" class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-left">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-emerald-500" />
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                        Vânzări online (procesator de plăți)
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                        {{ number_format($d['online_total'], 2) }} {{ $d['currency'] }}
                    </span>
                    <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                </div>
            </button>
            <div x-show="open" x-cloak class="mt-4">
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Comenzi cu status <em>paid / confirmed / completed</em>, sursă diferită de POS. Include comenzile cu refund parțial (valoarea refund-ată apare în tabelul de mai jos, nu se scade aici).
            </p>

            @if(empty($d['online_rows']))
                <p class="p-4 text-sm text-center text-gray-500 rounded-lg dark:text-gray-400 bg-gray-50 dark:bg-gray-900/40">
                    Nicio vânzare online în această lună.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b border-gray-200 dark:text-gray-400 dark:border-gray-700">
                                <th class="px-3 py-2">Eveniment</th>
                                <th class="px-3 py-2 text-right">Comenzi</th>
                                <th class="px-3 py-2 text-right">Bilete</th>
                                <th class="px-3 py-2 text-right">Încasat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($d['online_rows'] as $row)
                                <tr class="hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <td class="px-3 py-2">
                                        @if($row['event_id'] > 0)
                                            <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $row['event_id']]) }}"
                                               class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                                {{ $row['event_name'] }}
                                            </a>
                                        @else
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $row['event_name'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($row['event_date']){{ $row['event_date'] }} &middot; @endif
                                            @if($row['venue']){{ $row['venue'] }}@endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['order_count']) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['ticket_count']) }}</td>
                                    <td class="px-3 py-2 font-medium text-right text-emerald-600 dark:text-emerald-400">
                                        {{ number_format($row['revenue'], 2) }} {{ $d['currency'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="text-sm font-semibold text-gray-900 border-t-2 border-gray-300 dark:text-white dark:border-gray-600">
                                <td class="px-3 py-2">Total</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['online_order_count']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['online_ticket_count']) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">
                                    {{ number_format($d['online_total'], 2) }} {{ $d['currency'] }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            </div>{{-- /x-show open A --}}
        </div>{{-- /accordion A --}}

        {{-- ================================================================ --}}
        {{-- BLOCK B — REFUNDS via processor per event (accordion, collapsed)  --}}
        {{-- ================================================================ --}}
        <div x-data="{ open: false }" class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-left">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-uturn-left class="w-5 h-5 text-rose-500" />
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                        Returnat prin procesator
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                        {{ number_format($d['refund_total'], 2) }} {{ $d['currency'] }}
                    </span>
                    <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                </div>
            </button>
            <div x-show="open" x-cloak class="mt-4">
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Sursă: <code class="px-1 rounded bg-gray-100 dark:bg-gray-900">marketplace_refund_items</code>, status <em>refunded</em>, ancorat pe data procesării (când a plecat banul înapoi), nu pe data comenzii. Refund-urile parțiale apar la valoarea reală returnată.
            </p>

            @if(empty($d['refund_rows']))
                <p class="p-4 text-sm text-center text-gray-500 rounded-lg dark:text-gray-400 bg-gray-50 dark:bg-gray-900/40">
                    Niciun refund online în această lună.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b border-gray-200 dark:text-gray-400 dark:border-gray-700">
                                <th class="px-3 py-2">Eveniment</th>
                                <th class="px-3 py-2 text-right">Comenzi</th>
                                <th class="px-3 py-2 text-right">Bilete refund</th>
                                <th class="px-3 py-2 text-right">Sumă returnată</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($d['refund_rows'] as $row)
                                <tr class="hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <td class="px-3 py-2">
                                        @if($row['event_id'] > 0)
                                            <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $row['event_id']]) }}"
                                               class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                                {{ $row['event_name'] }}
                                            </a>
                                        @else
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $row['event_name'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($row['event_date']){{ $row['event_date'] }} &middot; @endif
                                            @if($row['venue']){{ $row['venue'] }}@endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['order_count']) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['ticket_count']) }}</td>
                                    <td class="px-3 py-2 font-medium text-right text-rose-600 dark:text-rose-400">
                                        {{ number_format($row['amount'], 2) }} {{ $d['currency'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="text-sm font-semibold text-gray-900 border-t-2 border-gray-300 dark:text-white dark:border-gray-600">
                                <td class="px-3 py-2">Total</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['refund_order_count']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['refund_ticket_count']) }}</td>
                                <td class="px-3 py-2 text-right text-rose-600 dark:text-rose-400">
                                    {{ number_format($d['refund_total'], 2) }} {{ $d['currency'] }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            </div>{{-- /x-show open B --}}
        </div>{{-- /accordion B --}}

        {{-- ================================================================ --}}
        {{-- BLOCK D — POS SALES per event, split cash/card (accordion)        --}}
        {{-- ================================================================ --}}
        <div x-data="{ open: false }" class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-left">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-building-storefront class="w-5 h-5 text-indigo-500" />
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                        Vânzări prin POS (fără procesator)
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400 tabular-nums">
                        {{ number_format($d['pos_total'], 2) }} {{ $d['currency'] }}
                    </span>
                    <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                </div>
            </button>
            <div x-show="open" x-cloak class="mt-4">
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Comenzi cu <em>source</em> = <code class="px-1 rounded bg-gray-100 dark:bg-gray-900">pos_app</code> (aplicația mobilă Tixello), <code class="px-1 rounded bg-gray-100 dark:bg-gray-900">venue_owner_pos</code> (POS operator locație) sau <code class="px-1 rounded bg-gray-100 dark:bg-gray-900">pos</code> (leisure &mdash; Sf. Ana). Split cash/card citit din <code class="px-1 rounded bg-gray-100 dark:bg-gray-900">meta.payment_method</code>. Comenzile create înainte de patch nu au acest câmp și apar la <em>metodă neînregistrată</em>.
            </p>

            {{-- POS payment-method summary tiles — fixed 3-col layout --}}
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="p-3 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30">
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Cash</p>
                    <p class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($d['pos_cash'], 2) }} {{ $d['currency'] }}
                    </p>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30">
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Card</p>
                    <p class="mt-1 text-lg font-bold text-sky-600 dark:text-sky-400">
                        {{ number_format($d['pos_card'], 2) }} {{ $d['currency'] }}
                    </p>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30">
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Metodă neînregistrată</p>
                    <p class="mt-1 text-lg font-bold text-gray-700 dark:text-gray-300">
                        {{ number_format($d['pos_unknown'], 2) }} {{ $d['currency'] }}
                    </p>
                </div>
            </div>

            @if(empty($d['pos_rows']))
                <p class="p-4 text-sm text-center text-gray-500 rounded-lg dark:text-gray-400 bg-gray-50 dark:bg-gray-900/40">
                    Nicio vânzare POS în această lună.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b border-gray-200 dark:text-gray-400 dark:border-gray-700">
                                <th class="px-3 py-2">Eveniment</th>
                                <th class="px-3 py-2 text-right">Comenzi</th>
                                <th class="px-3 py-2 text-right">Bilete</th>
                                <th class="px-3 py-2 text-right">Cash</th>
                                <th class="px-3 py-2 text-right">Card</th>
                                <th class="px-3 py-2 text-right">Neînreg.</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($d['pos_rows'] as $row)
                                <tr class="hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <td class="px-3 py-2">
                                        @if($row['event_id'] > 0)
                                            <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $row['event_id']]) }}"
                                               class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                                {{ $row['event_name'] }}
                                            </a>
                                        @else
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $row['event_name'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($row['event_date']){{ $row['event_date'] }} &middot; @endif
                                            @if($row['venue']){{ $row['venue'] }}@endif
                                        </div>
                                        @php
                                            $srcLabels = [];
                                            if ($row['by_source']['pos_app'] > 0) $srcLabels[] = 'pos_app: ' . number_format($row['by_source']['pos_app'], 2);
                                            if ($row['by_source']['venue_owner_pos'] > 0) $srcLabels[] = 'venue_owner_pos: ' . number_format($row['by_source']['venue_owner_pos'], 2);
                                            if ($row['by_source']['pos'] > 0) $srcLabels[] = 'pos: ' . number_format($row['by_source']['pos'], 2);
                                        @endphp
                                        @if(count($srcLabels) > 1)
                                            <div class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">{{ implode(' &middot; ', $srcLabels) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['order_count']) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['ticket_count']) }}</td>
                                    <td class="px-3 py-2 text-right {{ $row['cash'] > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400' }}">
                                        {{ number_format($row['cash'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right {{ $row['card'] > 0 ? 'text-sky-600 dark:text-sky-400 font-medium' : 'text-gray-400' }}">
                                        {{ number_format($row['card'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right {{ $row['unknown'] > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">
                                        {{ number_format($row['unknown'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 font-medium text-right text-indigo-600 dark:text-indigo-400">
                                        {{ number_format($row['total'], 2) }} {{ $d['currency'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="text-sm font-semibold text-gray-900 border-t-2 border-gray-300 dark:text-white dark:border-gray-600">
                                <td class="px-3 py-2">Total</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['pos_order_count']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['pos_ticket_count']) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ number_format($d['pos_cash'], 2) }}</td>
                                <td class="px-3 py-2 text-right text-sky-600 dark:text-sky-400">{{ number_format($d['pos_card'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d['pos_unknown'], 2) }}</td>
                                <td class="px-3 py-2 text-right text-indigo-600 dark:text-indigo-400">
                                    {{ number_format($d['pos_total'], 2) }} {{ $d['currency'] }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            </div>{{-- /x-show open D --}}
        </div>{{-- /accordion D --}}

        {{-- Alpine x-cloak hides accordion bodies until Alpine hydrates,
             preventing a brief flash of the expanded state on first paint. --}}
        <style>[x-cloak]{display:none!important;}</style>
    @endif
</x-filament-panels::page>
