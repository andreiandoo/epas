<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Rezumat sold: total vânzări și din ce se compune (plătit + POS + în procesare + sold) --}}
        @php
            $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
            $total = (float) $summary['total_sales'];
            $negative = $summary['available'] < -0.004;
            $parts = [
                ['key' => 'paid', 'label' => 'Plătit de AmBilet', 'value' => $summary['paid'], 'color' => '#3b82f6', 'text' => 'text-blue-600 dark:text-blue-400',
                 'hint' => 'Deconturi marcate finalizate + avansuri.'],
                ['key' => 'pos', 'label' => 'Încasat direct (POS)', 'value' => $summary['pos'], 'color' => '#8b5cf6', 'text' => 'text-gray-900 dark:text-white',
                 'hint' => 'Vândut la ușă: banii sunt deja la organizator. Comisionul POS se facturează separat.'],
                ['key' => 'pending', 'label' => 'În procesare', 'value' => $summary['pending'], 'color' => '#f59e0b', 'text' => 'text-yellow-600 dark:text-yellow-400',
                 'hint' => 'Deconturi aprobate, încă nemarcate finalizate. Marchează-le din Lista deconturi după plată.'],
                ['key' => 'available', 'label' => 'Sold disponibil', 'value' => $summary['available'], 'color' => $negative ? '#ef4444' : '#10b981',
                 'text' => $negative ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400',
                 'hint' => $negative
                    ? 'Negativ: s-a plătit mai mult decât vânzările — diferența se recuperează din vânzările următoare.'
                    : 'Vânzări pentru care nu s-a emis încă decont.'],
            ];
            $barBase = collect($parts)->sum(fn ($p) => max(0, (float) $p['value']));
        @endphp
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5" aria-label="Rezumat sold">
            <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total vânzări (net)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $fmt($total) }} RON</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Calculat la {{ $summary['computed_at'] }} · se actualizează la 5 minute sau din „Reîmprospătează".</p>
            </div>

            @if($barBase > 0.004)
                <div class="mt-4 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img"
                     aria-label="{{ collect($parts)->map(fn ($p) => $p['label'] . ' ' . $fmt($p['value']) . ' RON')->implode(', ') }}">
                    @foreach($parts as $p)
                        @if((float) $p['value'] > 0.004)
                            <div style="width: {{ round(max(0, (float) $p['value']) / $barBase * 100, 3) }}%; background: {{ $p['color'] }};" title="{{ $p['label'] }}: {{ $fmt($p['value']) }} RON"></div>
                        @endif
                    @endforeach
                </div>
            @endif

            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                @foreach($parts as $p)
                    <div class="min-w-0 border-t border-gray-200 dark:border-gray-700 pt-3">
                        <dt class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $p['color'] }};" aria-hidden="true"></span>
                            {{ $p['label'] }}
                        </dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums {{ $p['text'] }}">{{ $fmt($p['value']) }} RON</dd>
                        <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $p['hint'] }}</dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Plătit {{ $fmt($summary['paid']) }} + POS {{ $fmt($summary['pos']) }} + în procesare {{ $fmt($summary['pending']) }} + sold {{ $fmt($summary['available']) }} = <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $fmt($summary['paid'] + $summary['pos'] + $summary['pending'] + $summary['available']) }} RON</span> (total vânzări)
            </p>
        </section>

        {{-- Avansuri --}}
        @if($advances->isNotEmpty())
            <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" class="w-full px-6 py-4 flex flex-wrap items-center justify-between gap-2 text-left" x-bind:class="open ? 'border-b border-gray-200 dark:border-gray-700' : ''">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-gray-400" />
                        Avansuri
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $advances->count() }})</span>
                    </h3>
                    <span class="flex items-center gap-3">
                        <span class="text-sm {{ $advanceOpen > 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                            Avans nedecontat: {{ number_format($advanceOpen, 2, ',', '.') }} RON
                        </span>
                        <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                    </span>
                </button>
                <div x-show="open" x-cloak>
                <p class="px-6 pt-3 text-xs text-gray-500 dark:text-gray-400">
                    Avansul se scade din sold la înregistrare. Deconturile pe eveniment aprobate după aceea se compensează automat din el, cel mai vechi avans întâi; la plată se transferă doar restul.
                </p>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avans</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plătit la</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sumă</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compensat</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rămas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compensat în deconturile</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($advances as $advance)
                            <tr>
                                <td class="px-6 py-3 text-sm font-mono">
                                    <a href="{{ url('/marketplace/payouts/' . $advance->id) }}" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 hover:underline">{{ $advance->reference }}</a>
                                    @if($advance->payment_reference)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-sans">Ref: {{ $advance->payment_reference }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ ($advance->completed_at ?? $advance->created_at)?->format('d.m.Y') }}</td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format((float) $advance->amount, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600 dark:text-gray-300">{{ number_format((float) $advance->advance_used, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-sm text-right font-semibold {{ $advance->advance_remaining > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">{{ number_format((float) $advance->advance_remaining, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @forelse($advance->allocationsFromAdvance as $alloc)
                                        @php
                                            $allocEvent = $alloc->payout?->event;
                                            $allocTitle = $allocEvent ? (is_array($allocEvent->title) ? ($allocEvent->title['ro'] ?? $allocEvent->title['en'] ?? (reset($allocEvent->title) ?: null)) : $allocEvent->title) : null;
                                        @endphp
                                        <div>
                                            <a href="{{ url('/marketplace/payouts/' . $alloc->payout_id) }}" class="font-mono text-primary-600 dark:text-primary-400 hover:underline">{{ $alloc->payout?->decont_series ?? $alloc->payout?->reference ?? ('#' . $alloc->payout_id) }}</a>
                                            @if($allocTitle) · {{ $allocTitle }} @endif
                                            · {{ number_format((float) $alloc->amount, 2, ',', '.') }} RON
                                        </div>
                                    @empty
                                        <span class="text-gray-400">Încă necompensat</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif

        {{-- Bank Info --}}
        @if($organizer->bank_name || $organizer->iban)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-building-library class="w-5 h-5 text-gray-400" />
                    <div class="flex gap-6 text-sm">
                        @if($organizer->bank_name)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Bancă:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $organizer->bank_name }}</span>
                            </div>
                        @endif
                        @if($organizer->iban)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">IBAN:</span>
                                <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $organizer->iban }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Sold pe eveniment --}}
        @php
            $th = 'px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider';
            $td = 'px-4 py-3 text-sm text-right whitespace-nowrap';
            $hasPos = $eventRows->contains(fn ($r) => abs($r['pos_net'] ?? 0) > 0.004);
        @endphp
        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" class="w-full px-6 py-4 flex items-center justify-between gap-4 text-left" x-bind:class="open ? 'border-b border-gray-200 dark:border-gray-700' : ''">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-calendar class="w-5 h-5 text-gray-400" />
                        Sold pe eveniment
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $eventRows->count() }})</span>
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Brut − comision − reduceri − taxe peste preț (asigurare, card cultural) = net. Net − plătit − în procesare = sold.@if($hasPos) Vânzările POS le-a încasat organizatorul la ușă: nu intră în net și nici în sold.@endif</p>
                </div>
                <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-cloak>
            @if($eventRows->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-chart-bar class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nu există încă vânzări.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="{{ $th }} text-left">Eveniment</th>
                            <th class="{{ $th }} text-right">Brut</th>
                            <th class="{{ $th }} text-right">Comision</th>
                            <th class="{{ $th }} text-right">Reduceri</th>
                            <th class="{{ $th }} text-right">Taxe peste preț</th>
                            <th class="{{ $th }} text-right">Net</th>
                            <th class="{{ $th }} text-right">Plătit</th>
                            <th class="{{ $th }} text-right">În procesare</th>
                            <th class="{{ $th }} text-right">Sold</th>
                            @if($hasPos)
                                <th class="{{ $th }} text-right" title="Net din vânzări POS, încasat direct de organizator. Informativ, nu intră în sold.">POS (la organizator)</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($eventRows as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ url('/marketplace/events/' . $row['id'] . '/edit') }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 hover:underline">{{ $row['title'] }}</a>
                                    @if($row['is_past'])
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Încheiat</span>
                                    @endif
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ implode(' - ', array_filter([$row['date'], $row['venue'], $row['city']])) }}</div>
                                </td>
                                <td class="{{ $td }} text-gray-900 dark:text-white">{{ number_format((float) $row['revenue'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-red-600 dark:text-red-400">{{ $row['commission'] > 0 ? '−' : '' }}{{ number_format((float) $row['commission'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-red-600 dark:text-red-400">{{ $row['discount'] > 0 ? '−' : '' }}{{ number_format((float) $row['discount'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-red-600 dark:text-red-400">{{ $row['extras'] > 0 ? '−' : '' }}{{ number_format((float) $row['extras'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} font-medium text-gray-900 dark:text-white">{{ number_format((float) $row['net'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-blue-600 dark:text-blue-400">{{ number_format((float) $row['paid'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-yellow-600 dark:text-yellow-400">{{ number_format((float) $row['pending'], 2, ',', '.') }}</td>
                                <td class="{{ $td }} font-semibold {{ $row['balance'] < -0.004 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ number_format((float) $row['balance'], 2, ',', '.') }}
                                    @if($row['balance'] < -0.004)
                                        <div class="text-[11px] font-normal">de regularizat</div>
                                    @endif
                                </td>
                                @if($hasPos)
                                    <td class="{{ $td }} text-gray-500 dark:text-gray-400">{{ abs($row['pos_net'] ?? 0) > 0.004 ? number_format((float) $row['pos_net'], 2, ',', '.') : '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700/50 text-sm">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">Total evenimente</td>
                            <td class="{{ $td }} font-semibold text-gray-900 dark:text-white">{{ number_format((float) $eventRows->sum('revenue'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-red-600 dark:text-red-400">−{{ number_format((float) $eventRows->sum('commission'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-red-600 dark:text-red-400">−{{ number_format((float) $eventRows->sum('discount'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-red-600 dark:text-red-400">−{{ number_format((float) $eventRows->sum('extras'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-gray-900 dark:text-white">{{ number_format((float) $eventRows->sum('net'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-blue-600 dark:text-blue-400">{{ number_format((float) $eventRows->sum('paid'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format((float) $eventRows->sum('pending'), 2, ',', '.') }}</td>
                            <td class="{{ $td }} font-semibold text-gray-900 dark:text-white">{{ number_format((float) $eventRows->sum('balance'), 2, ',', '.') }}</td>
                            @if($hasPos)
                                <td class="{{ $td }} font-semibold text-gray-500 dark:text-gray-400">{{ number_format((float) $eventRows->sum('pos_net'), 2, ',', '.') }}</td>
                            @endif
                        </tr>
                        @if($orgWidePaid > 0 || $orgWidePending > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300" colspan="6">Plăți fără eveniment (avansuri, deconturi multi-eveniment)</td>
                                <td class="{{ $td }} text-blue-600 dark:text-blue-400">{{ number_format((float) $orgWidePaid, 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-yellow-600 dark:text-yellow-400">{{ number_format((float) $orgWidePending, 2, ',', '.') }}</td>
                                <td class="{{ $td }} text-gray-600 dark:text-gray-300">−{{ number_format((float) $orgWidePaid + $orgWidePending, 2, ',', '.') }}</td>
                                @if($hasPos)<td></td>@endif
                            </tr>
                        @endif
                        @if($advanceOffset > 0.004)
                            <tr>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300" colspan="8">Deconturi acoperite din avans (avansul e numărat o singură dată)</td>
                                <td class="{{ $td }} text-gray-600 dark:text-gray-300">+{{ number_format((float) $advanceOffset, 2, ',', '.') }}</td>
                                @if($hasPos)<td></td>@endif
                            </tr>
                        @endif
                        @if($orgWidePaid > 0 || $orgWidePending > 0 || $advanceOffset > 0.004)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white" colspan="8">Sold disponibil</td>
                                <td class="{{ $td }} font-semibold text-gray-900 dark:text-white">{{ number_format((float) $summary['available'], 2, ',', '.') }}</td>
                                @if($hasPos)<td></td>@endif
                            </tr>
                        @endif
                    </tfoot>
                </table>
                </div>
            @endif
            </div>
        </div>

        {{-- Payout History --}}
        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" class="w-full px-6 py-4 flex items-center justify-between gap-4 text-left" x-bind:class="open ? 'border-b border-gray-200 dark:border-gray-700' : ''">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-gray-400" />
                    Lista deconturi
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $payouts->count() }})</span>
                </h3>
                <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-cloak>
            @if($payouts->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-banknotes class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nu există încă deconturi.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Eveniment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Referință</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sumă</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Referință plată</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Creat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Finalizat</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($payouts as $payout)
                            @php
                                $ev = $payout->event;
                                $evTitle = null;
                                $evDate = null;
                                $evVenue = null;
                                $evCity = null;
                                if ($ev) {
                                    $t = $ev->title;
                                    $evTitle = is_array($t) ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: null)) : $t;
                                    $evDate = $ev->start_date?->format('d.m.Y');
                                    if ($ev->venue) {
                                        $vn = $ev->venue->name;
                                        $evVenue = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? (reset($vn) ?: null)) : $vn;
                                        $evCity = $ev->venue->city ?: null;
                                    }
                                    // Event date - venue - city, only the parts we have
                                    $metaParts = array_filter([$evDate, $evVenue, $evCity]);
                                    $evMeta = implode(' - ', $metaParts);
                                }
                            @endphp
                            <tr wire:key="payout-row-{{ $payout->id }}">
                                <td class="px-6 py-3 text-sm">
                                    @if($ev)
                                        <a href="{{ url('/marketplace/events/' . $ev->id . '/edit') }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
                                            {{ $evTitle ?? ('Eveniment #' . $ev->id) }}
                                        </a>
                                        @if(!empty($evMeta))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $evMeta }}</div>
                                        @endif
                                    @elseif($payout->isAdvance())
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">Avans</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm font-mono whitespace-nowrap">
                                    {{-- Seria AmBilet întâi, cu link; referința internă dedesubt. --}}
                                    @if($payout->decont_series)
                                        <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-base font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">{{ $payout->decont_series }}</a>
                                        @if($payout->reference)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $payout->reference }}</div>
                                        @endif
                                    @else
                                        <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">{{ $payout->reference ?: ('#' . $payout->id) }}</a>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format((float) $payout->amount, 2, ',', '.') }} {{ $payout->currency ?? 'RON' }}
                                    @if((float) ($payout->advance_covered ?? 0) > 0)
                                        <div class="text-xs font-normal text-amber-600 dark:text-amber-400 mt-0.5">din avans: −{{ number_format((float) $payout->advance_covered, 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500 dark:text-gray-400">de plată: {{ number_format(max(0, (float) $payout->amount - (float) $payout->advance_covered), 2, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                                            'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                            'processing' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        ];
                                        $color = $statusColors[$payout->status] ?? $statusColors['cancelled'];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $color }}">
                                        {{ ['pending' => 'În așteptare', 'approved' => 'Aprobat', 'processing' => 'În procesare', 'completed' => 'Finalizat', 'rejected' => 'Respins', 'cancelled' => 'Anulat'][$payout->status] ?? ucfirst($payout->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->payment_reference ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->created_at?->format('d.m.Y') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->completed_at?->format('d.m.Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                                    @if($payout->canBeCompleted())
                                        {{-- Finalizare rapidă: referința e opțională, Enter sau butonul
                                             finalizează, rândul se actualizează pe loc. --}}
                                        <div style="display:inline-flex;align-items:center;gap:.5rem" data-quick-row="{{ $payout->id }}">
                                            <input type="text"
                                                   wire:model="payRefs.{{ $payout->id }}"
                                                   wire:keydown.enter.prevent="quickComplete({{ $payout->id }})"
                                                   data-quick-ref="{{ $payout->id }}"
                                                   placeholder="Referință plată (opțional)"
                                                   maxlength="255"
                                                   class="ep-quick-ref">
                                            <button type="button"
                                                    wire:click="quickComplete({{ $payout->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="quickComplete({{ $payout->id }})"
                                                    title="Marchează plătit"
                                                    class="ep-quick-btn">
                                                <span wire:loading.remove wire:target="quickComplete({{ $payout->id }})">Finalizează</span>
                                                <span wire:loading wire:target="quickComplete({{ $payout->id }})">…</span>
                                            </button>
                                        </div>
                                    @elseif($payout->isCompleted())
                                        {{ ($this->editPaymentReferenceAction)(['payout' => $payout->id]) }}
                                    @elseif($payout->isPending())
                                        <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-xs text-gray-500 hover:underline dark:text-gray-400" title="Decontul trebuie aprobat înainte de a fi finalizat">Aprobă întâi</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
            </div>
        </div>

    </div>

    {{-- După finalizarea rapidă, focusul trece pe referința următorului decont
         de finalizat, fără să se miște pagina. --}}
    <style>
        .ep-quick-ref { width: 12rem; padding: .25rem .5rem; font-size: .75rem; border-radius: .5rem; border: 1px solid rgba(107,114,128,.4); background: #fff; color: #111827; }
        .ep-quick-ref:focus { outline: 2px solid rgba(37,99,235,.5); outline-offset: 0; border-color: rgb(37,99,235); }
        .dark .ep-quick-ref { background: rgba(255,255,255,.05); color: #f9fafb; border-color: rgba(255,255,255,.2); }
        .ep-quick-btn { display: inline-flex; align-items: center; padding: .25rem .65rem; font-size: .75rem; font-weight: 600; border-radius: .5rem; background: #16a34a; color: #fff; }
        .ep-quick-btn:hover { background: #15803d; }
        .ep-quick-btn:disabled { opacity: .5; }
    </style>
    <script>
        (() => {
            if (window.__epQuickPayoutFocus) return;
            window.__epQuickPayoutFocus = true;
            let order = [];
            document.addEventListener('focusin', (e) => {
                if (e.target?.matches?.('[data-quick-ref]')) {
                    order = [...document.querySelectorAll('[data-quick-ref]')].map((el) => el.dataset.quickRef);
                }
            });
            window.addEventListener('ep-payout-completed', (e) => {
                const id = String(e.detail?.id ?? '');
                const list = order.length ? order : [...document.querySelectorAll('[data-quick-ref]')].map((el) => el.dataset.quickRef);
                const next = list.slice(list.indexOf(id) + 1);
                requestAnimationFrame(() => {
                    for (const nid of next) {
                        const el = document.querySelector('[data-quick-ref="' + nid + '"]');
                        if (el) { el.focus({ preventScroll: true }); break; }
                    }
                });
            });
        })();
    </script>
</x-filament-panels::page>
