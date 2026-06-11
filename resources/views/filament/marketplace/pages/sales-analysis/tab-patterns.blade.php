@php
    $dowRevenue = $data['dowRevenue'] ?? ['labels' => [], 'revenue' => [], 'orders' => []];
    $dowTickets = $data['dowTickets'] ?? ['labels' => [], 'tickets' => []];
    $categoryDay = $data['categoryDay'] ?? ['days' => [], 'categories' => [], 'matrix' => []];
    $hourly = $data['hourly'] ?? ['days' => [], 'hours' => [], 'matrix' => []];
    $peakWindows = $data['peakWindows'] ?? [];
@endphp

<div class="space-y-6">
    {{-- Day of Week: Revenue + Tickets side by side --}}
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Revenue by Day --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Revenue pe zi a saptamanii</h3>
            <div class="h-64" wire:ignore x-data="{
                init() {
                    const ctx = this.$refs.canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($dowRevenue['labels']) }},
                            datasets: [{
                                label: 'Revenue',
                                data: {{ Js::from($dowRevenue['revenue']) }},
                                backgroundColor: {{ Js::from($dowRevenue['revenue']) }}.map((v, i, arr) => {
                                    const max = Math.max(...arr);
                                    return v === max ? 'rgba(99, 102, 241, 0.9)' : 'rgba(99, 102, 241, 0.4)';
                                }),
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { callback: v => '{{ $currencySymbol }}' + v.toLocaleString() } }
                            }
                        }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        {{-- Tickets by Day --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Bilete vandute pe zi a saptamanii</h3>
            <div class="h-64" wire:ignore x-data="{
                init() {
                    const ctx = this.$refs.canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($dowTickets['labels']) }},
                            datasets: [{
                                label: 'Bilete',
                                data: {{ Js::from($dowTickets['tickets']) }},
                                backgroundColor: {{ Js::from($dowTickets['tickets']) }}.map((v, i, arr) => {
                                    const max = Math.max(...arr);
                                    return v === max ? 'rgba(16, 185, 129, 0.9)' : 'rgba(16, 185, 129, 0.4)';
                                }),
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- Category x Day of Week Heatmap --}}
    @if(!empty($categoryDay['categories']))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Performance categorii pe zile</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-400">Categorie</th>
                        @foreach($categoryDay['days'] as $day)
                            <th class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $allValues = [];
                        foreach ($categoryDay['matrix'] as $catData) {
                            foreach ($catData as $val) {
                                if ($val > 0) $allValues[] = $val;
                            }
                        }
                        $maxVal = !empty($allValues) ? max($allValues) : 1;
                    @endphp
                    @foreach($categoryDay['matrix'] as $catId => $dayData)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ $categoryDay['categories'][$catId] ?? 'N/A' }}</td>
                            @foreach($categoryDay['dayKeys'] ?? array_keys($dayData) as $dow)
                                @php
                                    $val = $dayData[$dow] ?? 0;
                                    $intensity = $maxVal > 0 ? $val / $maxVal : 0;
                                    $bg = $intensity > 0.7 ? 'bg-indigo-600 text-white' : ($intensity > 0.4 ? 'bg-indigo-300 dark:bg-indigo-700 text-indigo-900 dark:text-white' : ($intensity > 0.1 ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' : 'bg-gray-50 dark:bg-gray-900 text-gray-400'));
                                @endphp
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block px-2 py-1 text-xs font-medium rounded {{ $bg }}">
                                        {{ $val > 0 ? $currencySymbol . number_format($val, 0) : '-' }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Hourly Heatmap --}}
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Heatmap: Ore x Zile (Revenue)</h3>
        <div class="overflow-x-auto">
            @php
                $allHourly = [];
                foreach ($hourly['matrix'] as $dayData) {
                    foreach ($dayData as $h) {
                        if (($h['revenue'] ?? 0) > 0) $allHourly[] = $h['revenue'];
                    }
                }
                $maxHourly = !empty($allHourly) ? max($allHourly) : 1;
            @endphp
            <table class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-2 py-1 text-left text-gray-500 dark:text-gray-400">Zi / Ora</th>
                        @foreach(range(6, 23) as $h)
                            <th class="px-1 py-1 text-center text-gray-500 dark:text-gray-400">{{ sprintf('%02d', $h) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($hourly['matrix'] as $day => $hours)
                        <tr>
                            <td class="px-2 py-1 font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ $day }}</td>
                            @foreach(range(6, 23) as $h)
                                @php
                                    $val = $hours[$h]['revenue'] ?? 0;
                                    $intensity = $maxHourly > 0 ? $val / $maxHourly : 0;
                                @endphp
                                <td class="px-1 py-1 text-center" title="{{ $day }} {{ sprintf('%02d:00', $h) }}: {{ $currencySymbol }}{{ number_format($val, 0) }} ({{ $hours[$h]['orders'] ?? 0 }} comenzi)">
                                    <div @class([
                                        'w-7 h-7 rounded flex items-center justify-center text-[10px] font-medium mx-auto',
                                        'bg-red-600 text-white' => $intensity > 0.8,
                                        'bg-orange-500 text-white' => $intensity > 0.6 && $intensity <= 0.8,
                                        'bg-amber-400 text-gray-900' => $intensity > 0.4 && $intensity <= 0.6,
                                        'bg-yellow-200 text-gray-800' => $intensity > 0.2 && $intensity <= 0.4,
                                        'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' => $intensity > 0 && $intensity <= 0.2,
                                        'bg-gray-50 dark:bg-gray-900 text-gray-300 dark:text-gray-600' => $intensity == 0,
                                    ])>
                                        {{ $hours[$h]['orders'] ?? 0 }}
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Peak Sales Windows --}}
    @if(!empty($peakWindows))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Top 10 ferestre de vanzari</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Zi</th>
                        <th class="px-3 py-2">Interval</th>
                        <th class="px-3 py-2 text-right">Revenue</th>
                        <th class="px-3 py-2 text-right">Comenzi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($peakWindows as $i => $window)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2">
                                @if($i < 3)
                                    <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-indigo-500 rounded-full">{{ $i + 1 }}</span>
                                @else
                                    <span class="text-gray-400">{{ $i + 1 }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $window['day'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $window['hour'] }}</td>
                            <td class="px-3 py-2 font-medium text-right text-gray-900 dark:text-white">{{ $currencySymbol }}{{ number_format($window['revenue'], 0) }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $window['orders'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
