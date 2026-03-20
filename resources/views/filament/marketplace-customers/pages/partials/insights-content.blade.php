
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Eveniment</x-slot>
                    @if(!empty($eventTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventTypes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Genuri Eveniment</x-slot>
                    @if(!empty($eventGenres))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventGenres as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tag-uri Eveniment</x-slot>
                    @if(!empty($eventTags))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventTags as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Locație</x-slot>
                    @if(!empty($venueTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($venueTypes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Genuri Muzicale (Artiști)</x-slot>
                    @if(!empty($artistGenres))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($artistGenres as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Top Artiști</x-slot>
                    @if(!empty($topArtists))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($topArtists as $a)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $a->name }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $a->cnt }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Top 3 Zile Preferate</x-slot>
                    @if(!empty($preferredDays))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredDays as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Orașe Preferate</x-slot>
                    @if(!empty($preferredCities))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredCities as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Ora Start Preferată</x-slot>
                    @if(!empty($preferredStartTimes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredStartTimes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">Luni Preferate ale Anului</x-slot>
                    @if(!empty($preferredMonths))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredMonths as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Perioadă Lunară Preferată</x-slot>
                    @if(!empty($preferredMonthPeriods))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredMonthPeriods as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            @if(!empty($recentEvents))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Evenimente Recente</x-slot>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($recentEvents as $ev)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $ev->title }}</span>
                                    <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $ev->id]) }}" class="text-xs text-primary-600 hover:underline">Deschide</a>
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                </div>
            @endif

            {{-- Weighted Profile (Orders 50% + Favorites 30% + Personal 20%) --}}
            @php
                $hasWeightedData = false;
                foreach ($weightedProfileData as $cat => $items) {
                    if (!empty($items)) { $hasWeightedData = true; break; }
                }
                $hasPersonalData = false;
                foreach ($weightedProfileData as $items) {
                    foreach ($items as $item) {
                        if (($item['personal_pct'] ?? 0) > 0) { $hasPersonalData = true; break 2; }
                    }
                }
            @endphp
            @if($hasWeightedData)
                <div class="mt-6">
                    <div class="p-4 mb-4 rounded-xl bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-950/30 dark:via-orange-950/30 dark:to-yellow-950/30 ring-1 ring-amber-200/60 dark:ring-amber-700/40">
                        <div class="flex items-center gap-2 mb-1">
                            <x-filament::icon icon="heroicon-o-scale" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                Profil Ponderat (Comenzi 50% + Favorite 30% + Selecții Personale 20%)
                            </h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Scorurile combină datele din comenzi, favorite/watchlist și selecțiile personale ale clientului.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        @foreach([
                            'event_types' => 'Tipuri Eveniment',
                            'event_genres' => 'Genuri Eveniment',
                            'artist_genres' => 'Genuri Muzicale',
                            'venue_types' => 'Tipuri Locație',
                            'cities' => 'Orașe',
                        ] as $wpKey => $wpLabel)
                            @if(!empty($weightedProfileData[$wpKey]))
                                <x-filament::section>
                                    <x-slot name="heading">{{ $wpLabel }}</x-slot>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-500 dark:text-gray-400">
                                                    <th class="pb-1 pr-2 font-medium text-left">Categorie</th>
                                                    <th class="pb-1 px-1 font-medium text-center" title="Din comenzi">Cmd%</th>
                                                    <th class="pb-1 px-1 font-medium text-center" title="Din favorite">Fav%</th>
                                                    @if($hasPersonalData)
                                                        <th class="pb-1 px-1 font-medium text-center" title="Selecții personale">Pers%</th>
                                                    @endif
                                                    <th class="pb-1 pl-1 font-medium text-right" title="Scor ponderat">Scor</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach(array_slice($weightedProfileData[$wpKey], 0, 7) as $wpItem)
                                                    <tr>
                                                        <td class="py-1.5 pr-2 text-gray-700 dark:text-gray-300">{{ $wpItem['label'] }}</td>
                                                        <td class="py-1.5 px-1 text-center text-blue-600 dark:text-blue-400">{{ $wpItem['order_pct'] }}%</td>
                                                        <td class="py-1.5 px-1 text-center text-purple-600 dark:text-purple-400">{{ $wpItem['fav_pct'] }}%</td>
                                                        @if($hasPersonalData)
                                                            <td class="py-1.5 px-1 text-center text-emerald-600 dark:text-emerald-400">{{ $wpItem['personal_pct'] ?? 0 }}%</td>
                                                        @endif
                                                        <td class="py-1.5 pl-1 text-right font-semibold text-amber-600 dark:text-amber-400">{{ $wpItem['weighted'] }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </x-filament::section>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

    </div>

    {{-- Chart.js for Monthly Orders --}}
    @if(!empty($monthlyChart['labels']))
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('mpProfileMonthlyChart');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($monthlyChart['labels']),
                        datasets: [
                            {
                                label: 'Comenzi',
                                data: @json($monthlyChart['counts']),
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: 'Venit (RON)',
                                data: @json($monthlyChart['revenues']),
                                type: 'line',
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.3,
                                fill: true,
                                yAxisID: 'y1',
                                order: 1,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Comenzi' } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'RON' } },
                        }
                    }
                });
            });
        </script>
        @endpush
