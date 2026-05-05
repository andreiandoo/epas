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

    @if($hasNoData)
        <div class="p-6 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg text-yellow-800 dark:text-yellow-200 mb-6">
            <strong>Niciun cont Ads sincronizat încă pentru filtrele alese.</strong>
            Asigură-te că organizatorul are CAPI activ + Ad Account ID setat. După prima rulare a job-ului
            <code>ads:sync-meta-insights</code> (zilnic 05:00) sau apel manual:<br>
            <code>php artisan ads:sync-meta-insights --connection=&lt;id&gt;</code>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Spend Meta</div>
            <div class="text-2xl font-bold text-red-600">{{ number_format($totalSpend, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">cheltuit pe Ads în interval</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue (Tixello)</div>
            <div class="text-2xl font-bold text-green-600">{{ number_format($totalRevenue, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">comenzi cu fbclid (last-touch)</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">ROAS</div>
            <div class="text-2xl font-bold {{ $roas >= 3 ? 'text-green-600' : ($roas >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ number_format($roas, 2) }}x
            </div>
            <div class="text-xs text-gray-500 mt-1">revenue / spend</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Impressions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalImpressions) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ number_format($totalClicks) }} click-uri · CTR {{ number_format($avgCtr, 3) }}% · CPC {{ number_format($avgCpc, 4) }}</div>
        </div>
    </div>

    {{-- Per-campaign table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Per-campaign performance</h3>
            <p class="text-sm text-gray-500">
                Revenue per campanie e alocat proporțional cu numărul de click-uri (last-touch fbclid → revenue total split prin click weight).
                Pentru atribuire exactă per campanie, organizatorul trebuie să folosească UTM tags + URL builder Meta.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Campanie</th>
                        <th class="px-4 py-3 text-right">Impressions</th>
                        <th class="px-4 py-3 text-right">Clicks</th>
                        <th class="px-4 py-3 text-right">CTR</th>
                        <th class="px-4 py-3 text-right">CPC</th>
                        <th class="px-4 py-3 text-right">Spend</th>
                        <th class="px-4 py-3 text-right">Meta conv.</th>
                        <th class="px-4 py-3 text-right">Revenue alloc</th>
                        <th class="px-4 py-3 text-right">ROAS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($campaignTable as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $c['name'] }}
                                <div class="text-xs text-gray-500 font-mono">#{{ $c['fb_campaign_id'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($c['impressions']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($c['clicks']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($c['ctr'], 3) }}%</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($c['cpc'], 4) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-600">{{ number_format($c['spend'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($c['meta_conversions']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-green-600">{{ number_format($c['allocated_revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ $c['roas'] >= 3 ? 'text-green-600' : ($c['roas'] >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($c['roas'], 2) }}x
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Nicio campanie sincronizată în interval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
