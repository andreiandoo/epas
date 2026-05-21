<x-filament-panels::page>
    <div class="space-y-4">
        @if (! empty($migrationMissing))
            <div class="p-4 rounded-xl border-2 border-amber-500 bg-amber-50 dark:bg-amber-900/30">
                <div class="font-bold text-amber-700 dark:text-amber-300">Migrare incompletă</div>
                <div class="text-sm text-amber-700 dark:text-amber-200 mt-2">
                    Tabelele <code class="font-mono">tenant_team_members</code> sau
                    <code class="font-mono">tenant_team_member_shifts</code> nu există încă. Rulează:
                </div>
                <pre class="mt-2 px-3 py-2 rounded bg-amber-100 dark:bg-amber-800 text-xs font-mono">cd epas && php artisan migrate</pre>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <button wire:click="previousWeek" class="fi-btn px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">← Săpt. precedentă</button>
            <button wire:click="thisWeek" class="fi-btn px-3 py-2 rounded-lg bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200">Săpt. curentă</button>
            <button wire:click="nextWeek" class="fi-btn px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">Săpt. următoare →</button>
            <div class="ml-auto font-semibold">
                {{ $weekStart->format('d M Y') }} – {{ $weekEnd->format('d M Y') }}
            </div>
        </div>

        @if ($members->isEmpty())
            <div class="text-center py-12 text-gray-500">
                Niciun membru activ în echipă. Adaugă unul la <strong>Echipa</strong>.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full bg-white dark:bg-gray-800 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold sticky left-0 bg-gray-50 dark:bg-gray-900 z-10">Operator</th>
                            @foreach ($days as $d)
                                @php $isToday = $d->isSameDay(now()); @endphp
                                <th class="px-3 py-2 text-center font-semibold min-w-[140px] @if($isToday) bg-emerald-50 dark:bg-emerald-900/30 @endif">
                                    <div class="text-xs text-gray-500">{{ ['Lu','Ma','Mi','Jo','Vi','Sâ','Du'][$d->dayOfWeekIso - 1] }}</div>
                                    <div>{{ $d->format('d.m') }}</div>
                                </th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($members as $m)
                            @php $totalMin = $totalsByMember[$m->id] ?? 0; @endphp
                            <tr>
                                <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-800 z-10">
                                    <div class="font-semibold">{{ $m->user?->name ?? $m->user?->email ?? 'Operator' }}</div>
                                    <div class="text-xs text-gray-500">{{ $leisureRoles[$m->leisure_role] ?? $m->leisure_role }}</div>
                                </td>
                                @foreach ($days as $d)
                                    @php
                                        $key = $m->id . '|' . $d->toDateString();
                                        $cellShifts = $shifts->get($key, collect());
                                        $isToday = $d->isSameDay(now());
                                    @endphp
                                    <td class="px-2 py-2 align-top @if($isToday) bg-emerald-50/40 dark:bg-emerald-900/10 @endif">
                                        @forelse ($cellShifts as $shift)
                                            <div class="mb-1 px-2 py-1 rounded text-xs bg-violet-100 dark:bg-violet-900 text-violet-800 dark:text-violet-200">
                                                <div class="font-semibold">
                                                    @php
                                                        $startStr = $shift->start_time instanceof \DateTimeInterface
                                                            ? $shift->start_time->format('H:i')
                                                            : substr((string) $shift->start_time, 0, 5);
                                                        $endStr = $shift->end_time instanceof \DateTimeInterface
                                                            ? $shift->end_time->format('H:i')
                                                            : substr((string) $shift->end_time, 0, 5);
                                                    @endphp
                                                    {{ $startStr }} – {{ $endStr }}
                                                </div>
                                                @if ($shift->position)
                                                    <div class="text-[10px] opacity-80">{{ $leisureRoles[$shift->position] ?? $shift->position }}</div>
                                                @endif
                                                @if ($shift->location)
                                                    <div class="text-[10px] opacity-80">@ {{ $shift->location }}</div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center text-gray-300 dark:text-gray-600">—</div>
                                        @endforelse
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right align-top">
                                    <div class="font-mono font-semibold">{{ number_format($totalMin / 60, 1) }}h</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-sm text-gray-500">
                Apasă pe un operator în <strong>Echipa</strong> pentru a-i edita schimburile.
            </div>
        @endif
    </div>
</x-filament-panels::page>
