@php
    $periods = $periods ?? [];
    $payoutUrl = $payoutUrl ?? null;
    $eventId = $eventId ?? null;
    $orgId = $orgId ?? null;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $roDate = function ($ymd) {
        try { return \Illuminate\Support\Carbon::parse($ymd)->format('d.m.Y'); }
        catch (\Throwable $e) { return $ymd; }
    };
@endphp

<style>
    details.ep-decont summary::-webkit-details-marker { display: none; }
    details.ep-decont[open] .ep-chev { transform: rotate(90deg); }
</style>

<div class="space-y-3">
    @forelse ($periods as $p)
        @php $cur = $p['currency'] ?? 'RON'; @endphp
        <details class="ep-decont overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800" @if(!empty($p['is_current'])) open @endif>
            <summary class="flex flex-wrap items-center justify-between gap-3 p-4 cursor-pointer select-none">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 transition-transform ep-chev" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $roDate($p['from']) }} – {{ $roDate($p['to']) }}</span>
                    @if (!empty($p['is_current']))
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">în derulare</span>
                    @endif
                    @if ($p['generated_at'])
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">generat</span>
                    @endif
                    @if ($p['settled_at'])
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">lichidat</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center text-xs gap-x-4 gap-y-1">
                    <span class="text-gray-600 dark:text-gray-300">Vânzări: <b class="text-emerald-600 dark:text-emerald-400">{{ $fmt($p['online_revenue']) }}</b> online / <b class="text-sky-600 dark:text-sky-400">{{ $fmt($p['pos_revenue']) }}</b> POS {{ $cur }}</span>
                    <span class="text-gray-600 dark:text-gray-300">Comisioane: <b>{{ $fmt($p['online_commission']) }}</b> online / <b>{{ $fmt($p['pos_commission']) }}</b> POS</span>
                    @if (!empty($p['compensation']) && ($p['compensation']['amount'] ?? 0) > 0.005)
                        @php $c = $p['compensation']; @endphp
                        <span class="text-gray-600 dark:text-gray-300">De achitat prin compensare:
                            @if ($c['direction'] === 'ambilet_to_venue')
                                <b class="text-emerald-700 dark:text-emerald-300">AmBilet → Locație {{ $fmt($c['amount']) }} {{ $cur }}</b>
                            @elseif ($c['direction'] === 'venue_to_ambilet')
                                <b class="text-amber-700 dark:text-amber-300">Locație → AmBilet {{ $fmt($c['amount']) }} {{ $cur }}</b>
                            @else
                                <b class="text-gray-700 dark:text-gray-300">stins prin compensare</b>
                            @endif
                        </span>
                    @endif
                </div>
            </summary>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                {{-- Compensation card — the "De achitat prin compensare" block
                     the operator asked for. Mirrors the standalone
                     ambilet.ro/organizator/deconturi green/amber card. --}}
                @php $c = $p['compensation'] ?? null; @endphp
                @if ($c && ($c['amount'] ?? 0) > 0.005)
                    @php
                        $isAmbiletToVenue = ($c['direction'] ?? '') === 'ambilet_to_venue';
                        $cardBg = $isAmbiletToVenue
                            ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800'
                            : 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800';
                        $accent = $isAmbiletToVenue
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-amber-700 dark:text-amber-300';
                    @endphp
                    <div class="p-4 mb-4 border rounded-lg {{ $cardBg }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-semibold tracking-wide uppercase {{ $accent }}">De achitat, prin compensare</div>
                                <div class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                                    @if ($isAmbiletToVenue)
                                        <span class="font-medium">AmBilet</span>
                                        <svg class="inline w-3.5 h-3.5 mx-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7M5 12h16"/></svg>
                                        <span class="font-medium">Locație</span>
                                    @else
                                        <span class="font-medium">Locație</span>
                                        <svg class="inline w-3.5 h-3.5 mx-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7M5 12h16"/></svg>
                                        <span class="font-medium">AmBilet</span>
                                    @endif
                                </div>
                                <div class="mt-2 space-y-0.5 text-[11px] text-gray-600 dark:text-gray-400">
                                    <div>AmBilet datorează locației (venit online net): <b>{{ $fmt($c['ambilet_owes_venue']) }} {{ $cur }}</b></div>
                                    <div>Locația datorează AmBilet (comision POS): <b>{{ $fmt($c['venue_owes_ambilet']) }} {{ $cur }}</b></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold tabular-nums {{ $accent }}">{{ $fmt($c['amount']) }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cur }}</div>
                            </div>
                        </div>
                    </div>
                @elseif ($c && ($c['amount'] ?? 0) <= 0.005 && (($p['online_revenue'] ?? 0) > 0 || ($p['pos_revenue'] ?? 0) > 0))
                    <div class="p-3 mb-4 text-sm text-center border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700 text-gray-600 dark:text-gray-400">
                        Datoriile se sting prin compensare — nicio sumă nu circulă efectiv.
                    </div>
                @endif

                {{-- Per-company (issuing_company) split. Sf. Ana has 2 SC-uri:
                     SC1 = "acces" tickets (issuing_company='primary')
                     SC2 = restul (issuing_company='secondary') --}}
                @php
                    $byIssuer = $p['by_issuer'] ?? [];
                    $hasSecondary = !empty($p['has_secondary_issuer']);
                    $primary = $byIssuer['primary'] ?? null;
                    $secondary = $hasSecondary ? ($byIssuer['secondary'] ?? null) : null;
                    $primaryHasData = $primary && (($primary['online_revenue'] ?? 0) > 0 || ($primary['pos_revenue'] ?? 0) > 0);
                    $secondaryHasData = $secondary && (($secondary['online_revenue'] ?? 0) > 0 || ($secondary['pos_revenue'] ?? 0) > 0);
                @endphp
                @if ($hasSecondary && ($primaryHasData || $secondaryHasData))
                    <div class="p-4 mb-4 border border-gray-200 rounded-lg bg-gray-50/60 dark:bg-gray-900/30 dark:border-gray-700">
                        <div class="mb-3 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300">
                            Split pe societăți emiterente
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-left text-gray-500 border-b dark:border-gray-700 dark:text-gray-400">
                                        <th class="py-2 pr-3">Societate</th>
                                        <th class="px-3 py-2 text-right">Bilete</th>
                                        <th class="px-3 py-2 text-right">Vânzări online</th>
                                        <th class="px-3 py-2 text-right">Comision online</th>
                                        <th class="px-3 py-2 text-right">Vânzări POS</th>
                                        <th class="px-3 py-2 text-right">Comision POS</th>
                                        <th class="py-2 pl-3 text-right">Compensare (netă)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (['primary' => $primary, 'secondary' => $secondary] as $key => $row)
                                        @continue(!$row || (($row['online_revenue'] ?? 0) == 0 && ($row['pos_revenue'] ?? 0) == 0))
                                        @php
                                            $dir = $row['direction'] ?? 'settled';
                                            $amt = (float) ($row['amount'] ?? 0);
                                            $rowLabel = $dir === 'ambilet_to_venue'
                                                ? '↦ către Locație'
                                                : ($dir === 'venue_to_ambilet' ? '↦ către AmBilet' : 'stins');
                                            $rowColor = $dir === 'ambilet_to_venue'
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : ($dir === 'venue_to_ambilet' ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500');
                                        @endphp
                                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                            <td class="py-2 pr-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $row['name'] ?? ($key === 'primary' ? 'Societatea 1' : 'Societatea 2') }}</div>
                                                <div class="text-[10px] uppercase tracking-wide text-gray-400">{{ $key === 'primary' ? 'acces' : 'celelalte' }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['tickets'] ?? 0 }}</td>
                                            <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $fmt($row['online_revenue'] ?? 0) }}</td>
                                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $fmt($row['online_commission'] ?? 0) }}</td>
                                            <td class="px-3 py-2 text-right text-sky-600 dark:text-sky-400">{{ $fmt($row['pos_revenue'] ?? 0) }}</td>
                                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $fmt($row['pos_commission'] ?? 0) }}</td>
                                            <td class="py-2 pl-3 text-right font-medium tabular-nums {{ $rowColor }}">
                                                {{ $fmt($amt) }}
                                                @if ($amt > 0.005)<span class="ml-1 text-[10px] text-gray-500">{{ $rowLabel }}</span>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-6 mb-4">
                    @if ($payoutUrl && $eventId)
                        <a href="{{ $payoutUrl }}?open_decont=1&org={{ $orgId }}&event={{ $eventId }}&from={{ $p['from'] }}&to={{ $p['to'] }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Generează decont
                        </a>
                    @endif
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" wire:click="toggleDecontFlag('{{ $p['from'] }}','generated')" @checked($p['generated_at']) class="w-4 h-4 text-indigo-600 rounded border-gray-300">
                        <span class="text-gray-800 dark:text-gray-200">S-a generat decont?</span>
                        @if ($p['generated_at'])<span class="text-xs text-gray-400">({{ $p['generated_at'] }})</span>@endif
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" wire:click="toggleDecontFlag('{{ $p['from'] }}','settled')" @checked($p['settled_at']) class="w-4 h-4 text-emerald-600 rounded border-gray-300">
                        <span class="text-gray-800 dark:text-gray-200">S-a lichidat decont?</span>
                        @if ($p['settled_at'])<span class="text-xs text-gray-400">({{ $p['settled_at'] }})</span>@endif
                    </label>
                </div>

                @if (empty($p['days']))
                    <p class="text-sm text-gray-400">Nu există vânzări în această perioadă.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-left text-gray-500 border-b dark:border-gray-700 dark:text-gray-400">
                                    <th class="py-2 pr-3">Zi</th>
                                    <th class="px-3 py-2 text-right">Vânzări online</th>
                                    <th class="px-3 py-2 text-right">Comision online</th>
                                    <th class="px-3 py-2 text-right">Vânzări POS</th>
                                    <th class="px-3 py-2 text-right">Comision POS</th>
                                    <th class="py-2 pl-3 text-right">Bilete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($p['days'] as $day)
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                        <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">{{ $roDate($day['date']) }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $fmt($day['online_revenue']) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $fmt($day['online_commission']) }}</td>
                                        <td class="px-3 py-2 text-right text-sky-600 dark:text-sky-400">{{ $fmt($day['pos_revenue']) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $fmt($day['pos_commission']) }}</td>
                                        <td class="py-2 pl-3 font-medium text-right text-gray-900 dark:text-white">{{ $day['tickets'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold border-t-2 border-gray-200 dark:border-gray-700">
                                    <td class="py-2 pr-3 text-gray-900 dark:text-white">Total perioadă</td>
                                    <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $fmt($p['online_revenue']) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $fmt($p['online_commission']) }}</td>
                                    <td class="px-3 py-2 text-right text-sky-600 dark:text-sky-400">{{ $fmt($p['pos_revenue']) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $fmt($p['pos_commission']) }}</td>
                                    <td class="py-2 pl-3 text-right text-gray-900 dark:text-white">{{ $p['tickets'] }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </details>
    @empty
        <p class="p-4 text-sm text-center text-gray-500">Nu există perioade de decont încă (proiectul începe 15.07.2026).</p>
    @endforelse
</div>
