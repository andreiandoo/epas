@php
    $goldenPrice = $data['goldenPrice'] ?? [];
    $priceVolume = $data['priceVolume'] ?? [];
    $pareto = $data['pareto'] ?? [];
    $repeatCustomer = $data['repeatCustomer'] ?? [];
    $leadTime = $data['leadTime'] ?? [];
    $refundRate = $data['refundRate'] ?? [];
@endphp

<div class="space-y-6">
    {{-- Golden Price Zone --}}
    @if(!empty($goldenPrice))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Golden Price Zone</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Intervalul de pret la care se vinde cel mai bine pe fiecare categorie</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($goldenPrice as $gp)
                <div class="p-4 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $gp['category'] }}</div>
                    <div class="flex items-center gap-2 mt-2">
                        <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                            @php
                                $range = $gp['max_price'] - $gp['min_price'];
                                $leftPct = $range > 0 ? (($gp['golden_min'] - $gp['min_price']) / $range) * 100 : 0;
                                $widthPct = $range > 0 ? (($gp['golden_max'] - $gp['golden_min']) / $range) * 100 : 100;
                            @endphp
                            <div class="h-2 bg-emerald-500 rounded-full" style="margin-left: {{ $leftPct }}%; width: {{ $widthPct }}%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between mt-1 text-[10px] text-gray-400">
                        <span>{{ $currencySymbol }}{{ number_format($gp['min_price'], 0) }}</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $currencySymbol }}{{ number_format($gp['golden_min'], 0) }} - {{ $currencySymbol }}{{ number_format($gp['golden_max'], 0) }}</span>
                        <span>{{ $currencySymbol }}{{ number_format($gp['max_price'], 0) }}</span>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $gp['golden_pct'] }}% din vanzari</span>
                        <span>{{ number_format($gp['total_sold']) }} bilete</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Price vs Volume Scatter --}}
    @if(!empty($priceVolume))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Pret vs Volum vandut</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Fiecare punct = un tip de bilet. Marime = revenue</p>
        <div class="h-80" wire:ignore x-data="{
            init() {
                const raw = {{ Js::from($priceVolume) }};
                const cats = [...new Set(raw.map(r => r.category))];
                const colors = ['rgb(99,102,241)', 'rgb(16,185,129)', 'rgb(249,115,22)', 'rgb(239,68,68)', 'rgb(168,85,247)', 'rgb(59,130,246)'];
                const datasets = cats.map((cat, i) => ({
                    label: cat,
                    data: raw.filter(r => r.category === cat).map(r => ({ x: r.price, y: r.volume, r: Math.min(20, Math.max(4, r.revenue / 500)) })),
                    backgroundColor: colors[i % colors.length] + '80',
                    borderColor: colors[i % colors.length],
                }));
                new Chart(this.$refs.canvas.getContext('2d'), {
                    type: 'bubble',
                    data: { datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            x: { title: { display: true, text: 'Pret ({{ $currencySymbol }})' } },
                            y: { title: { display: true, text: 'Bilete vandute' }, beginAtZero: true }
                        }
                    }
                });
            }
        }">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Pareto / Revenue Concentration --}}
        @if(!empty($pareto))
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Concentrare revenue (Pareto)</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Cum este distribuit revenue-ul intre evenimente</p>
            <div class="h-64" wire:ignore x-data="{
                init() {
                    const d = {{ Js::from(array_slice($pareto, 0, 15)) }};
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: d.map(r => r.name.substring(0, 20)),
                            datasets: [
                                { label: 'Revenue', data: d.map(r => r.revenue), backgroundColor: 'rgba(99,102,241,0.6)', borderRadius: 4, yAxisID: 'y' },
                                { label: 'Cumulativ %', data: d.map(r => r.cumulative_pct), type: 'line', borderColor: 'rgb(239,68,68)', pointRadius: 2, yAxisID: 'y1' }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: {
                                y: { position: 'left', beginAtZero: true, ticks: { callback: v => '{{ $currencySymbol }}' + v.toLocaleString() } },
                                y1: { position: 'right', min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { display: false } },
                                x: { ticks: { maxRotation: 45 } }
                            }
                        }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
        @endif

        {{-- Repeat Customer --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Clienti recurenti</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Rata de repeat si valoare medie</p>
            @if(!empty($repeatCustomer) && $repeatCustomer['total_customers'] > 0)
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                    <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ $repeatCustomer['repeat_rate'] }}%</div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-500">Repeat rate</div>
                </div>
                <div class="p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20">
                    <div class="text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ number_format($repeatCustomer['avg_orders'], 1) }}</div>
                    <div class="text-xs text-indigo-600 dark:text-indigo-500">Comenzi medii/client</div>
                </div>
                <div class="p-3 rounded-lg bg-violet-50 dark:bg-violet-900/20">
                    <div class="text-lg font-bold text-violet-700 dark:text-violet-400">{{ $currencySymbol }}{{ number_format($repeatCustomer['avg_repeat_spent'], 0) }}</div>
                    <div class="text-xs text-violet-600 dark:text-violet-500">Avg cheltuiala repeat</div>
                </div>
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <div class="text-lg font-bold text-gray-700 dark:text-gray-300">{{ $currencySymbol }}{{ number_format($repeatCustomer['avg_single_spent'], 0) }}</div>
                    <div class="text-xs text-gray-500">Avg cheltuiala one-time</div>
                </div>
            </div>
            <div class="h-32" wire:ignore x-data="{
                init() {
                    const d = {{ Js::from($repeatCustomer['distribution'] ?? []) }};
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: d.map(r => r.orders + ' comenzi'),
                            datasets: [{ data: d.map(r => r.customers), backgroundColor: 'rgba(99,102,241,0.6)', borderRadius: 4 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt suficiente date.</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Booking Lead Time --}}
        @if(!empty($leadTime))
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Lead time de cumparare</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Cu cate zile inainte de eveniment se cumpara biletele</p>
            <div class="h-48" wire:ignore x-data="{
                init() {
                    const d = {{ Js::from($leadTime) }};
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: d.map(r => r.category),
                            datasets: [{ label: 'Zile', data: d.map(r => r.avg_lead_days), backgroundColor: 'rgba(99,102,241,0.6)', borderRadius: 4 }]
                        },
                        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, title: { display: true, text: 'Zile inainte' } } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
        @endif

        {{-- Refund Rate by Category --}}
        @if(!empty($refundRate))
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Rata de refund pe categorii</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Categorii cu cele mai multe refund-uri</p>
            <div class="h-48" wire:ignore x-data="{
                init() {
                    const d = {{ Js::from($refundRate) }};
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: d.map(r => r.category),
                            datasets: [{ label: 'Refund %', data: d.map(r => r.refund_rate), backgroundColor: d.map(r => r.refund_rate > 10 ? 'rgba(239,68,68,0.7)' : 'rgba(251,191,36,0.6)'), borderRadius: 4 }]
                        },
                        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => v + '%' } } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
        @endif
    </div>
</div>
