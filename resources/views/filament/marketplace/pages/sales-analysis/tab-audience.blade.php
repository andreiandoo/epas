@php
    $rfm = $data['rfm'] ?? ['segments' => [], 'total' => 0];
    $geographic = $data['geographic'] ?? [];
    $affinity = $data['affinity'] ?? [];
    $cohort = $data['cohort'] ?? [];

    // Static color map for RFM (avoids JIT issues with dynamic Tailwind classes)
    $rfmStyles = [
        'Champions' => ['bg' => '#ecfdf5', 'bgDark' => 'rgba(16,185,129,0.15)', 'text' => '#047857', 'textDark' => '#34d399'],
        'Fideli' => ['bg' => '#eef2ff', 'bgDark' => 'rgba(99,102,241,0.15)', 'text' => '#4338ca', 'textDark' => '#818cf8'],
        'Potentiali' => ['bg' => '#fffbeb', 'bgDark' => 'rgba(245,158,11,0.15)', 'text' => '#b45309', 'textDark' => '#fbbf24'],
        'Noi' => ['bg' => '#eff6ff', 'bgDark' => 'rgba(59,130,246,0.15)', 'text' => '#1d4ed8', 'textDark' => '#60a5fa'],
        'La Risc' => ['bg' => '#fef2f2', 'bgDark' => 'rgba(239,68,68,0.15)', 'text' => '#b91c1c', 'textDark' => '#f87171'],
        'Pierduti' => ['bg' => '#f9fafb', 'bgDark' => 'rgba(156,163,175,0.15)', 'text' => '#374151', 'textDark' => '#9ca3af'],
    ];
@endphp

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- RFM Segmentation --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Segmentare RFM</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Recency-Frequency-Monetary: cine sunt clientii tai</p>
            @if($rfm['total'] > 0)
            <div class="h-56" wire:ignore x-data="{
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
                @foreach($rfm['segments'] as $name => $count)
                    @php $s = $rfmStyles[$name] ?? $rfmStyles['Pierduti']; @endphp
                    <div class="p-2 text-center rounded-lg" style="background-color: {{ $s['bg'] }}">
                        <div class="text-lg font-bold" style="color: {{ $s['text'] }}">{{ $count }}</div>
                        <div class="text-[10px] font-medium" style="color: {{ $s['text'] }}">{{ $name }}</div>
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
            <div class="h-72" wire:ignore x-data="{
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

    {{-- Cohort Retention Analysis --}}
    @if(!empty($cohort))
    <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
        <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Analiza cohorte - retentie clienti</h3>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">% clienti care revin in lunile urmatoare primei achizitii</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-400">Cohorta</th>
                        <th class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Clienti</th>
                        @for($i = 0; $i <= 6; $i++)
                            <th class="px-2 py-2 text-center text-gray-500 dark:text-gray-400">Luna {{ $i }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($cohort as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ $row['month'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $row['size'] }}</td>
                            @for($i = 0; $i <= 6; $i++)
                                @php
                                    $pct = $row['retention'][$i] ?? null;
                                    if ($pct === null) {
                                        $cellStyle = 'background-color: #f9fafb; color: #d1d5db;';
                                        $cellText = '-';
                                    } elseif ($pct >= 80) {
                                        $cellStyle = 'background-color: #047857; color: white;';
                                        $cellText = $pct . '%';
                                    } elseif ($pct >= 50) {
                                        $cellStyle = 'background-color: #10b981; color: white;';
                                        $cellText = $pct . '%';
                                    } elseif ($pct >= 20) {
                                        $cellStyle = 'background-color: #a7f3d0; color: #065f46;';
                                        $cellText = $pct . '%';
                                    } elseif ($pct > 0) {
                                        $cellStyle = 'background-color: #fef3c7; color: #92400e;';
                                        $cellText = $pct . '%';
                                    } else {
                                        $cellStyle = 'background-color: #fee2e2; color: #991b1b;';
                                        $cellText = '0%';
                                    }
                                @endphp
                                <td class="px-2 py-2 text-center">
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded" style="{{ $cellStyle }}">{{ $cellText }}</span>
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
