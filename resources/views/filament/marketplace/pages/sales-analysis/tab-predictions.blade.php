@php
    $monthlyForecast = $data['monthlyForecast'] ?? ['labels' => [], 'actuals' => [], 'predicted' => []];
    $yearlyForecast = $data['yearlyForecast'] ?? ['labels' => [], 'this_year' => [], 'last_year' => [], 'predicted' => [], 'current_year' => date('Y')];
    $seasonality = $data['seasonality'] ?? ['labels' => [], 'index' => [], 'baseline' => 100];
    $salesVelocity = $data['salesVelocity'] ?? [];
@endphp

<div class="space-y-6">
    {{-- Monthly Forecast --}}
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Predictie lunara</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Revenue actual vs predictie (Holt-Winters)</p>
        <div class="h-72" x-data="{
            init() {
                const ctx = this.$refs.canvas.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: {{ Js::from($monthlyForecast['labels']) }},
                        datasets: [
                            {
                                label: 'Actual',
                                data: {{ Js::from($monthlyForecast['actuals']) }},
                                borderColor: 'rgb(99, 102, 241)',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                            },
                            {
                                label: 'Predictie',
                                data: {{ Js::from($monthlyForecast['predicted']) }},
                                borderColor: 'rgb(249, 115, 22)',
                                borderDash: [5, 5],
                                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 4,
                                pointStyle: 'triangle',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
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

    {{-- Yearly Forecast --}}
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Predictie anuala {{ $yearlyForecast['current_year'] }}</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Anul curent vs anul trecut + predictie lunile ramase</p>
        <div class="h-72" x-data="{
            init() {
                const ctx = this.$refs.canvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: {{ Js::from($yearlyForecast['labels']) }},
                        datasets: [
                            {
                                label: '{{ $yearlyForecast['current_year'] }}',
                                data: {{ Js::from($yearlyForecast['this_year']) }},
                                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                                borderRadius: 4,
                            },
                            {
                                label: '{{ $yearlyForecast['current_year'] - 1 }}',
                                data: {{ Js::from($yearlyForecast['last_year']) }},
                                backgroundColor: 'rgba(156, 163, 175, 0.4)',
                                borderRadius: 4,
                            },
                            {
                                label: 'Predictie',
                                data: {{ Js::from($yearlyForecast['predicted']) }},
                                backgroundColor: 'rgba(249, 115, 22, 0.6)',
                                borderRadius: 4,
                                borderDash: [3, 3],
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
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

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Seasonality Index --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Index de sezonalitate</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">100 = medie. Peste 100 = luna peste medie</p>
            <div class="h-64" x-data="{
                init() {
                    const idx = {{ Js::from($seasonality['index']) }};
                    const ctx = this.$refs.canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($seasonality['labels']) }},
                            datasets: [{
                                label: 'Index',
                                data: idx,
                                backgroundColor: idx.map(v => v >= 100 ? 'rgba(16, 185, 129, 0.7)' : 'rgba(239, 68, 68, 0.5)'),
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                annotation: {
                                    annotations: {
                                        line1: { type: 'line', yMin: 100, yMax: 100, borderColor: 'rgba(107, 114, 128, 0.5)', borderDash: [3, 3] }
                                    }
                                }
                            },
                            scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } }
                        }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        {{-- Sales Velocity --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Viteza de vanzare</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Bilete/zi - cat de repede se vand evenimentele</p>
            @if(!empty($salesVelocity))
            <div class="overflow-y-auto max-h-64">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-white dark:bg-gray-800">
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-2 py-1">Eveniment</th>
                            <th class="px-2 py-1 text-right">Bilete/zi</th>
                            <th class="px-2 py-1 text-right">Sold %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesVelocity as $ev)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="px-2 py-1.5 text-gray-900 dark:text-white truncate max-w-[200px]" title="{{ $ev['name'] }}">{{ Str::limit($ev['name'], 35) }}</td>
                                <td class="px-2 py-1.5 text-right font-medium text-indigo-600 dark:text-indigo-400">{{ $ev['tickets_per_day'] }}</td>
                                <td class="px-2 py-1.5 text-right">
                                    <span @class([
                                        'text-green-600' => $ev['sell_through'] >= 80,
                                        'text-yellow-600' => $ev['sell_through'] >= 50 && $ev['sell_through'] < 80,
                                        'text-gray-500' => $ev['sell_through'] < 50,
                                    ])>{{ $ev['sell_through'] }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Nu sunt suficiente date.</p>
            @endif
        </div>
    </div>
</div>
