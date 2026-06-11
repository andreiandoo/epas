<x-filament-panels::page>
    {{-- Filters --}}
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
        </div>
    </div>

    {{-- Top stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Visitatori unici</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($visitorsTotal) }}</div>
            <div class="text-xs text-gray-500 mt-1">în intervalul selectat</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Cumpărători</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($customersWithPurchase) }}</div>
            <div class="text-xs text-gray-500 mt-1">cu cel puțin 1 comandă confirmată</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Repeat customers</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($repeatCustomers) }}</div>
            <div class="text-xs text-gray-500 mt-1">≥ 2 comenzi în interval</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Cross-organizer loyalists</div>
            <div class="text-2xl font-bold text-pink-600 dark:text-pink-400">{{ number_format($crossOrganizerLoyalists) }}</div>
            <div class="text-xs text-gray-500 mt-1">cumpărat la ≥ 2 organizatori</div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue total</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRevenue, 0) }} RON</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg LTV (lifetime, global)</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($avgLtv, 2) }} RON</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg time-to-purchase</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $avgTimeToPurchaseDays }} zile</div>
            <div class="text-xs text-gray-500 mt-1">de la prima vizită până la prima comandă</div>
        </div>
    </div>

    {{-- Top customers --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top 20 customers după valoare în interval</h3>
            <p class="text-sm text-gray-500">Cei care au cheltuit cel mai mult în perioada selectată. Email-urile mascate pentru privacy.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Nume</th>
                        <th class="px-4 py-3 text-right">Comenzi</th>
                        <th class="px-4 py-3 text-right">Organizatori</th>
                        <th class="px-4 py-3 text-right">Cheltuit</th>
                        <th class="px-4 py-3 text-left">Ultima comandă</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($topCustomersByLtv as $i => $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-mono text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $c['email_masked'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $c['customer_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $c['orders_count'] }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $c['organizers_count'] }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-green-600">{{ number_format($c['total_spent'], 2) }} RON</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($c['last_purchase_at'])->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Nicio comandă în interval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Multi-organizer loyalists --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cross-organizer loyalists</h3>
            <p class="text-sm text-gray-500">Customers care au cumpărat de la cel puțin 2 organizatori diferiți. Asset unic Tixello — concurența nu vede asta.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Nume</th>
                        <th class="px-4 py-3 text-right">Organizatori</th>
                        <th class="px-4 py-3 text-right">Comenzi</th>
                        <th class="px-4 py-3 text-right">Cheltuit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($multiOrganizerLoyalists as $i => $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-mono text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $c['email_masked'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $c['customer_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-pink-600">{{ $c['organizers_count'] }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $c['orders_count'] }}</td>
                            <td class="px-4 py-3 text-right font-mono text-green-600">{{ number_format($c['total_spent'], 2) }} RON</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Niciun loyalist cross-organizer detectat în interval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Channel mix --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Channel mix — sursa primară de achiziție</h3>
            <p class="text-sm text-gray-500">Distribuția first_source pentru visitatori văzuți prima dată în interval. Indicator de unde vin clienții noi.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Sursă</th>
                        <th class="px-4 py-3 text-right">Visitatori</th>
                        <th class="px-4 py-3 text-right">Buyers</th>
                        <th class="px-4 py-3 text-right">Conversion %</th>
                        <th class="px-4 py-3 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($channelMix as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-mono">{{ $row['source'] }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['visitors']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['buyers']) }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $row['conversion_pct'] >= 2 ? 'text-green-600' : ($row['conversion_pct'] >= 1 ? 'text-yellow-600' : 'text-red-600') }}">{{ number_format($row['conversion_pct'], 2) }}%</td>
                            <td class="px-4 py-3 text-right font-mono text-green-600">{{ number_format($row['revenue'], 0) }} RON</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Niciun visitator nou cu first_source în interval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
