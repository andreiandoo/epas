@php
    $rfm = $data['rfm'] ?? ['segments' => [], 'total' => 0];
    $geographic = $data['geographic'] ?? [];
    $affinity = $data['affinity'] ?? [];
@endphp

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- RFM Segmentation --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Segmentare RFM</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Recency-Frequency-Monetary: cine sunt clientii tai</p>
            @if($rfm['total'] > 0)
            <div class="h-56" x-data="{
                init() {
                    const seg = {{ Js::from($rfm['segments']) }};
                    const labels = Object.keys(seg);
                    const data = Object.values(seg);
                    const colors = ['#10b981', '#6366f1', '#f59e0b', '#3b82f6', '#ef4444', '#9ca3af'];
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'doughnut',
                        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff' }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-4">
                @php
                    $segColors = ['Champions' => 'emerald', 'Fideli' => 'indigo', 'Potentiali' => 'amber', 'Noi' => 'blue', 'La Risc' => 'red', 'Pierduti' => 'gray'];
                @endphp
                @foreach($rfm['segments'] as $name => $count)
                    @php $color = $segColors[$name] ?? 'gray'; @endphp
                    <div class="p-2 rounded-lg bg-{{ $color }}-50 dark:bg-{{ $color }}-900/20 text-center">
                        <div class="text-lg font-bold text-{{ $color }}-700 dark:text-{{ $color }}-400">{{ $count }}</div>
                        <div class="text-[10px] text-{{ $color }}-600 dark:text-{{ $color }}-500">{{ $name }}</div>
                    </div>
                @endforeach
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt suficiente date.</p>
            @endif
        </div>

        {{-- Geographic Revenue --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Revenue pe orase</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Cele mai profitabile orase</p>
            @if(!empty($geographic))
            <div class="h-72" x-data="{
                init() {
                    const d = {{ Js::from($geographic) }};
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: d.map(r => r.city),
                            datasets: [{ label: 'Revenue', data: d.map(r => r.revenue), backgroundColor: 'rgba(99,102,241,0.6)', borderRadius: 4 }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true, ticks: { callback: v => '{{ $currencySymbol }}' + v.toLocaleString() } } }
                        }
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

    {{-- Cross-Category Affinity --}}
    @if(!empty($affinity))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Afinitate intre categorii</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Clienti care cumpara din categorii multiple - oportunitati de cross-sell</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($affinity as $pair)
                <div class="flex items-center gap-3 p-3 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="px-2 py-0.5 text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 rounded">{{ $pair['category_a'] }}</span>
                            <x-heroicon-o-arrows-right-left class="w-3 h-3 text-gray-400 shrink-0" />
                            <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 rounded">{{ $pair['category_b'] }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $pair['shared_customers'] }}</div>
                        <div class="text-[10px] text-gray-500">clienti comuni</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
