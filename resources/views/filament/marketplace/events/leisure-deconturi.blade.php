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
        <details class="ep-decont overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-800">
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
                </div>
            </summary>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
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
                    @php
                        // One direct "Generează decont" button per issuing company
                        // that has sales this period. Single-society events show
                        // only the primary button.
                        $decontButtons = [];
                        if (!empty($primaryHasData)) {
                            $decontButtons['primary'] = $primary;
                        }
                        if (!empty($hasSecondary) && !empty($secondaryHasData)) {
                            $decontButtons['secondary'] = $secondary;
                        }
                    @endphp
                    @forelse ($decontButtons as $skey => $srow)
                        @php
                            $sName = $srow['name'] ?? ($skey === 'primary' ? 'Societatea 1' : 'Societatea 2');
                            $existingDecont = $p['deconturi'][$skey] ?? null;
                        @endphp
                        @if ($existingDecont)
                            {{-- Decont deja generat pe această societate + perioadă →
                                 seria + data/ora, link către decont (tab nou). Reapare
                                 butonul dacă decontul e șters (datele sunt live). --}}
                            <a href="{{ $existingDecont['url'] }}" target="_blank" rel="noopener"
                               title="Deschide decontul {{ $existingDecont['label'] }} (tab nou)"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Decont {{ $existingDecont['label'] }} · {{ $existingDecont['created'] }} · {{ $sName }}</span>
                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        @else
                            @php
                                $sGross = (float) ($srow['online_revenue'] ?? 0);
                                $sComm = (float) ($srow['online_commission'] ?? 0);
                                $sNet = (float) ($srow['ambilet_owes_venue'] ?? 0);
                                $sTickets = (int) ($srow['tickets'] ?? 0);
                                $confirmMsg = 'Generezi decontul pentru ' . $sName . ' (' . $roDate($p['from']) . ' – ' . $roDate($p['to']) . ')?'
                                    . "\n\nVânzări online (brut, = suma decont): " . $fmt($sGross) . ' ' . $cur . '  ·  ' . $sTickets . ' bilete'
                                    . "\nComision inclus (de facturat separat către societate): " . $fmt($sComm) . ' ' . $cur
                                    . "\nNet efectiv (după comision): " . $fmt($sNet) . ' ' . $cur
                                    . "\n\nSe creează decontul + documentul PDF pe această societate.";
                            @endphp
                            <button type="button"
                                wire:click="generateSocietyDecont('{{ $skey }}', '{{ $p['from'] }}', '{{ $p['to'] }}')"
                                wire:confirm="{{ $confirmMsg }}"
                                wire:target="generateSocietyDecont"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg wire:loading.remove wire:target="generateSocietyDecont" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <svg wire:loading wire:target="generateSocietyDecont" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 8h4z"></path></svg>
                                Generează decont · {{ $sName }}
                            </button>
                        @endif
                    @empty
                        <span class="text-xs text-gray-400">Nu există vânzări de decontat în această perioadă.</span>
                    @endforelse
                </div>

                @php
                    // Every society that has sales this period must have a decont
                    // generated → then the per-day breakdown is hidden (period done).
                    $societiesWithData = [];
                    if (!empty($primaryHasData)) { $societiesWithData[] = 'primary'; }
                    if (!empty($hasSecondary) && !empty($secondaryHasData)) { $societiesWithData[] = 'secondary'; }
                    $allSocietiesDecontat = !empty($societiesWithData);
                    foreach ($societiesWithData as $sk) {
                        if (empty($p['deconturi'][$sk] ?? null)) { $allSocietiesDecontat = false; break; }
                    }
                @endphp
                @if ($allSocietiesDecontat)
                    <p class="text-sm text-center text-gray-400">Perioadă decontată integral — deconturile sunt generate pe fiecare societate (vezi linkurile de mai sus).</p>
                @elseif (empty($p['days']))
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
