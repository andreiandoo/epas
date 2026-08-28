<x-filament-panels::page>
    <style id="ep-stats-style">
        .ep-stats-row:hover { background: rgba(0,0,0,.05); }
        :is(.dark) .ep-stats-row:hover { background: rgba(255,255,255,.06); }
    </style>
    @php
        $statusMeta = [
            'queued'          => ['În așteptare', 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            'active'          => ['Activ', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'offline_message' => ['Offline', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            'resolved'        => ['Rezolvat', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
            'closed'          => ['Închis', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
        ];

        // Format a duration in seconds as m:ss (e.g. 1:23), or "sub 1 min", or "—".
        $fmtDuration = function (?int $seconds): string {
            if ($seconds === null) {
                return '—';
            }
            if ($seconds < 60) {
                return 'sub 1 min';
            }
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;
            return $m . ':' . ($s < 10 ? '0' : '') . $s;
        };

        // Format a rating as "★ 4.7 (12)" or "—".
        $fmtRating = function ($avg, int $count): string {
            if (!$count || !$avg) {
                return '—';
            }
            return '★ ' . number_format((float) $avg, 1) . ' (' . $count . ')';
        };

        $cards = [
            ['label' => 'Total conversații', 'value' => $overall['total'] ?? 0],
            ['label' => 'Active', 'value' => $overall['active'] ?? 0],
            ['label' => 'În așteptare', 'value' => $overall['queued'] ?? 0],
            ['label' => 'Rezolvate', 'value' => $overall['resolved'] ?? 0],
            ['label' => 'Închise (inactivitate)', 'value' => $overall['inactivity'] ?? 0],
            ['label' => 'Rating mediu', 'value' => ($overall['rating_count'] ?? 0) ? number_format((float) $overall['avg_rating'], 1) . ' ★' : '—'],
            ['label' => 'Timp mediu răspuns', 'value' => $fmtDuration($overall['avg_response_seconds'] ?? null)],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Overall stat cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
            @foreach($cards as $card)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $card['label'] }}</div>
                    <div class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Per-operator statistics --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Statistici per operator</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Operator</th>
                            <th class="px-3 py-2 text-right">Preluate</th>
                            <th class="px-3 py-2 text-right">Rezolvate</th>
                            <th class="px-3 py-2 text-right">Închise (inactivitate)</th>
                            <th class="px-3 py-2 text-right">Active</th>
                            <th class="px-3 py-2 text-right">Rată rezolvare</th>
                            <th class="px-3 py-2 text-right">Rating</th>
                            <th class="px-3 py-2 text-right">Timp mediu răspuns</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($operators as $op)
                            <tr class="ep-stats-row">
                                <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-100">{{ $op['name'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $op['claimed'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $op['resolved'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $op['inactivity'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $op['active'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $op['resolution_rate'] === null ? '—' : $op['resolution_rate'] . '%' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if($op['rating_count'] > 0 && $op['avg_rating'])
                                        <span class="text-amber-400">★</span>
                                        <span class="text-gray-700 dark:text-gray-200">{{ number_format((float) $op['avg_rating'], 1) }}</span>
                                        <span class="text-gray-400 text-xs">din {{ $op['rating_count'] }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $fmtDuration($op['avg_response_seconds']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-xs text-gray-400">Niciun operator nu a preluat conversații încă.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- All conversations --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Toate conversațiile</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Operator</th>
                            <th class="px-3 py-2 text-right">Rating</th>
                            <th class="px-3 py-2 text-right">Timp răspuns</th>
                            <th class="px-3 py-2">Începută</th>
                            <th class="px-3 py-2">Încheiată</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($conversations as $c)
                            @php
                                [$slabel, $sclass] = $statusMeta[$c->status] ?? ['—', 'bg-gray-100 text-gray-600'];
                                $responseSeconds = ($c->first_response_at && $c->queued_at)
                                    ? (int) $c->first_response_at->diffInSeconds($c->queued_at, true)
                                    : null;
                                $endedAt = $c->resolved_at ?? $c->closed_at;
                            @endphp
                            <tr class="ep-stats-row">
                                <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $sclass }}">{{ $slabel }}</span>
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $c->assignee?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if($c->rating)
                                        <span class="text-amber-400">★</span>
                                        <span class="text-gray-700 dark:text-gray-200">{{ $c->rating }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ $fmtDuration($responseSeconds) }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300 text-xs">{{ optional($c->created_at)->format('d.m.Y H:i') }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300 text-xs">{{ $endedAt ? $endedAt->format('d.m.Y H:i') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
