<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Views Chart --}}
        <div class="bg-white rounded-xl shadow-sm border p-6" x-data="viewsChart()" x-init="init()">
            <h3 class="text-lg font-semibold mb-4">Vizualizări (ultimele 30 zile)</h3>
            <div class="h-48 flex items-end gap-1">
                @foreach($chartData as $day)
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-[10px] text-gray-400">{{ $day['views'] > 0 ? $day['views'] : '' }}</span>
                        <div class="w-full bg-primary-500 rounded-t transition-all hover:bg-primary-600"
                             style="height: {{ $day['views'] > 0 ? max(4, ($day['views'] / max(1, collect($chartData)->max('views'))) * 160) : 2 }}px; min-height: 2px;"
                             title="{{ $day['date'] }}: {{ $day['views'] }} vizualizări"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                <span>{{ $chartData[0]['date'] ?? '' }}</span>
                <span>{{ $chartData[count($chartData)-1]['date'] ?? '' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Engagement Funnel --}}
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold mb-4">Engagement Funnel</h3>
                <div class="space-y-3">
                    @foreach($funnel as $step)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">{{ $step['label'] }}</span>
                                <span class="font-semibold">{{ $step['count'] }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3">
                                <div class="{{ $step['color'] }} h-3 rounded-full transition-all"
                                     style="width: {{ $funnel[0]['count'] > 0 ? ($step['count'] / $funnel[0]['count'] * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- UTM Sources --}}
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold mb-4">Surse UTM (Top 10)</h3>
                @if(count($utmSources) > 0)
                    <div class="space-y-2">
                        @foreach($utmSources as $source => $count)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">{{ $source }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-gray-100 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full"
                                             style="width: {{ collect($utmSources)->max() > 0 ? ($count / collect($utmSources)->max() * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium w-8 text-right">{{ $count }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">Nicio intrare UTM înregistrată încă.</p>
                @endif
            </div>
        </div>

        {{-- Top Customizations --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold mb-4">Top Personalizări (după vizualizări)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="pb-3 font-medium">#</th>
                            <th class="pb-3 font-medium">Denumire</th>
                            <th class="pb-3 font-medium">Template</th>
                            <th class="pb-3 font-medium">Token</th>
                            <th class="pb-3 font-medium text-right">Vizualizări</th>
                            <th class="pb-3 font-medium">Ultima vizită</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($topCustomizations as $i => $c)
                            <tr>
                                <td class="py-3 text-gray-400">{{ $i + 1 }}</td>
                                <td class="py-3 font-medium">{{ $c->label ?: '(fără denumire)' }}</td>
                                <td class="py-3">{{ $c->template?->name }}</td>
                                <td class="py-3"><code class="bg-gray-100 px-2 py-0.5 rounded text-xs">{{ $c->unique_token }}</code></td>
                                <td class="py-3 text-right font-bold">{{ number_format($c->viewed_count) }}</td>
                                <td class="py-3 text-gray-500">{{ $c->last_viewed_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Template Performance --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold mb-4">Performanță Template-uri</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="pb-3 font-medium">Template</th>
                            <th class="pb-3 font-medium">Categorie</th>
                            <th class="pb-3 font-medium text-right">Personalizări</th>
                            <th class="pb-3 font-medium text-right">Total Vizualizări</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($templateStats as $stat)
                            <tr>
                                <td class="py-3 font-medium">{{ $stat['name'] }}</td>
                                <td class="py-3">{{ $stat['category'] }}</td>
                                <td class="py-3 text-right">{{ $stat['customizations'] }}</td>
                                <td class="py-3 text-right font-bold">{{ number_format($stat['total_views']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold mb-4">Activitate Recentă</h3>
            <div class="space-y-3">
                @foreach($recentActivity as $activity)
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-2 h-2 rounded-full {{ $activity->last_viewed_at?->isToday() ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                        <span class="font-medium">{{ $activity->label ?: $activity->unique_token }}</span>
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-500">{{ $activity->template?->name }}</span>
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-500">{{ $activity->viewed_count }} vizualizări</span>
                        <span class="ml-auto text-gray-400 text-xs">{{ $activity->last_viewed_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        function viewsChart() {
            return { init() {} };
        }
    </script>
</x-filament-panels::page>
