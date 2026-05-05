<x-filament-panels::page>
    {{-- Filters --}}
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="date" wire:model.live="endDate"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marketplace</label>
                <select wire:model.live="marketplaceClientId"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                    <option value="">— Toate —</option>
                    @foreach($marketplaces as $mp)
                        <option value="{{ $mp['id'] }}">{{ $mp['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organizator</label>
                <select wire:model.live="marketplaceOrganizerId"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                    <option value="">— Toți —</option>
                    @foreach($organizers as $org)
                        <option value="{{ $org['id'] }}">{{ $org['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Page Views (visitori unici)</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($funnelCounts['PageView'] ?? 0) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Comenzi confirmate</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalOrders) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue Total</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRevenue, 2) }} RON</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Conversion Rate</div>
            <div class="text-2xl font-bold {{ $overallConversionPct >= 2 ? 'text-green-600' : ($overallConversionPct >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ number_format($overallConversionPct, 2) }}%
            </div>
        </div>
    </div>

    {{-- Funnel visualization --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Funnel — visitori unici per pas</h3>
            <p class="text-sm text-gray-500">Numărul de visitori distincti care au atins fiecare pas în intervalul selectat. Drop-off afișat între pași.</p>
        </div>
        <div class="p-6">
            @php
                $maxCount = max(array_values($funnelCounts) ?: [1]);
            @endphp
            @foreach($funnelCounts as $label => $count)
                @php
                    $pct = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                    $colors = [
                        'PageView' => 'bg-blue-500',
                        'ViewContent' => 'bg-indigo-500',
                        'AddToCart' => 'bg-purple-500',
                        'InitiateCheckout' => 'bg-pink-500',
                        'Purchase' => 'bg-green-500',
                    ];
                    $color = $colors[$label] ?? 'bg-gray-500';
                @endphp
                <div class="mb-4 last:mb-0">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-baseline gap-2">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</span>
                            @if(($funnelDropoffPct[$label] ?? 0) > 0 && !$loop->first)
                                <span class="text-xs text-red-600 dark:text-red-400">↓ {{ $funnelDropoffPct[$label] }}% drop-off</span>
                            @endif
                        </div>
                        <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ number_format($count) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="{{ $color }} h-3 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top organizers leaderboard --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top organizatori — Conversion Rate</h3>
            <p class="text-sm text-gray-500">Doar organizatori cu minim 30 visitori unici în interval. Filtrează după marketplace mai sus pentru comparare per-marketplace.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Organizator</th>
                        <th class="px-4 py-3 text-right">Page Views</th>
                        <th class="px-4 py-3 text-right">Purchases</th>
                        <th class="px-4 py-3 text-right">Conversion %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($organizerLeaderboard as $i => $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-mono text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ url('/marketplace/organizers/' . $row['organizer_id'] . '/edit') }}"
                                    class="text-primary-600 hover:underline" target="_blank">
                                    {{ $row['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['page_views']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['purchases']) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ $row['conversion_pct'] >= 2 ? 'text-green-600' : ($row['conversion_pct'] >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($row['conversion_pct'], 2) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                Nicio dată suficientă în interval. Selectează o perioadă mai largă sau verifică dacă tracking-ul e activ.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
