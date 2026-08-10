{{--
    Analytics per short pentru organizator (docs/plans/shorts.md B5).

    Citește rollup-ul zilnic (short_analytics_daily), nu telemetria brută:
    rândurile brute sunt tăiate de retenție, iar o pagină care le-ar scana ar
    deveni mai lentă în fiecare săptămână.
--}}
<x-filament-panels::page>
    @if (! $this->hasData)
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Nu ai încă niciun short. Adaugă unul din secțiunea <strong>Shorts</strong> —
                după prima zi de trafic, aici apar pâlnia și curba de retenție.
            </div>
        </x-filament::section>
    @else
        @php($funnel = $this->funnel)

        <x-filament::section heading="Pâlnie · ultimele {{ $this->days }} zile">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                @foreach ([
                    ['Afișări', number_format($funnel['impressions'])],
                    ['Vizionări', number_format($funnel['views']) . ' · ' . number_format($funnel['view_rate'] * 100, 1) . '%'],
                    ['Click CTA', number_format($funnel['cta_clicks']) . ' · ' . number_format($funnel['ctr'] * 100, 1) . '%'],
                    ['Vânzări', number_format($funnel['conversions']) . ' · ' . number_format($funnel['cvr'] * 100, 1) . '%'],
                    ['Venit', number_format($funnel['revenue_cents'] / 100, 2)],
                ] as [$label, $value])
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold tabular-nums">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section heading="Retenție · unde pleacă spectatorii">
            @php($retention = $this->retention)
            @php($peak = max(1, max($retention)))

            <div class="flex items-end gap-1" style="height: 140px">
                @foreach ($retention as $bucket => $count)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1" style="height: 100%">
                        <div
                            class="w-full rounded-t bg-primary-500/80"
                            style="height: {{ max(2, (int) round($count / $peak * 100)) }}%"
                            title="{{ $count }} spectatori"
                        ></div>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ $bucket * 10 }}%</span>
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Fiecare bară = câți spectatori au ajuns în acea zecime din clip.
                O cădere abruptă la stânga înseamnă că problema e coperta sau primele secunde.
            </p>
        </x-filament::section>

        <x-filament::section heading="Top shorts">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4">Short</th>
                            <th class="py-2 pr-4 text-right">Vizionări</th>
                            <th class="py-2 pr-4 text-right">Watch %</th>
                            <th class="py-2 pr-4 text-right">CTA</th>
                            <th class="py-2 pr-4 text-right">Vânzări</th>
                            <th class="py-2 text-right">Venit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->topShorts as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row->title ?: '#' . $row->id }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($row->views) }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ number_format(((float) $row->avg_watch_ratio) * 100, 1) }}%</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($row->cta_clicks) }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($row->conversions) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($row->revenue_cents / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">
                                    Încă nu există date agregate — rollup-ul rulează zilnic.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
