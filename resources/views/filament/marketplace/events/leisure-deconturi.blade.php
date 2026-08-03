@php
    $periods = $periods ?? [];
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
            <summary class="flex flex-wrap items-center justify-between gap-3 p-4 cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-gray-700/40">
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
                <div class="flex flex-wrap gap-6 mb-4">
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
