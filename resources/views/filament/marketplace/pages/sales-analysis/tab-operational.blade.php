@php
    $organizers = $data['organizers'] ?? [];
    $capacity = $data['capacity'] ?? [];
    $discount = $data['discount'] ?? ['with_discount' => [], 'without_discount' => []];
    $payments = $data['payments'] ?? [];
    $refundTimeline = $data['refundTimeline'] ?? ['labels' => [], 'data' => []];
@endphp

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Organizer Leaderboard --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Top organizatori</h3>
            @if(!empty($organizers))
            <div class="space-y-2 overflow-y-auto max-h-80">
                @foreach($organizers as $i => $org)
                    <div class="flex items-center gap-3 p-3 rounded-lg {{ $i < 3 ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-gray-900/50' }}">
                        <div class="flex items-center justify-center w-7 h-7 text-xs font-bold rounded-full {{ $i < 3 ? 'bg-indigo-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $org['name'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $org['events'] }} events &middot; {{ number_format($org['tickets']) }} bilete</div>
                        </div>
                        <div class="text-sm font-bold text-right text-gray-900 dark:text-white shrink-0">
                            {{ $currencySymbol }}{{ number_format($org['revenue'], 0) }}
                        </div>
                    </div>
                @endforeach
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt date.</p>
            @endif
        </div>

        {{-- Capacity Utilization --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Utilizare capacitate pe categorii</h3>
            @if(!empty($capacity))
            <div class="space-y-3">
                @foreach($capacity as $cap)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $cap['category'] }}</span>
                            <span class="text-sm font-medium {{ $cap['utilization'] >= 80 ? 'text-green-600' : ($cap['utilization'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $cap['utilization'] }}%
                            </span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                            <div class="h-2 rounded-full transition-all {{ $cap['utilization'] >= 80 ? 'bg-green-500' : ($cap['utilization'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ min(100, $cap['utilization']) }}%"></div>
                        </div>
                        <div class="flex justify-between mt-0.5 text-[10px] text-gray-400">
                            <span>{{ number_format($cap['sold']) }} / {{ number_format($cap['capacity']) }} bilete</span>
                            <span>{{ $cap['events'] }} events</span>
                        </div>
                    </div>
                @endforeach
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt date.</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Discount Impact --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Impact discount/cupoane</h3>
            @php
                $wd = $discount['with_discount'] ?? [];
                $wod = $discount['without_discount'] ?? [];
                $totalOrders = ($wd['orders'] ?? 0) + ($wod['orders'] ?? 0);
                $discountPct = $totalOrders > 0 ? round(($wd['orders'] ?? 0) / $totalOrders * 100, 1) : 0;
            @endphp
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <div class="text-xs text-gray-500">Cu discount</div>
                        <div class="text-lg font-bold text-orange-600">{{ $wd['orders'] ?? 0 }} comenzi</div>
                        <div class="text-xs text-gray-400">AOV: {{ $currencySymbol }}{{ number_format($wd['avg_order'] ?? 0, 0) }}</div>
                    </div>
                    <div class="flex-1">
                        <div class="text-xs text-gray-500">Fara discount</div>
                        <div class="text-lg font-bold text-indigo-600">{{ $wod['orders'] ?? 0 }} comenzi</div>
                        <div class="text-xs text-gray-400">AOV: {{ $currencySymbol }}{{ number_format($wod['avg_order'] ?? 0, 0) }}</div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1 text-xs text-gray-500">
                        <span>Discount</span>
                        <span>{{ $discountPct }}%</span>
                    </div>
                    <div class="w-full h-3 bg-indigo-200 rounded-full dark:bg-indigo-900">
                        <div class="h-3 bg-orange-500 rounded-full" style="width: {{ $discountPct }}%"></div>
                    </div>
                </div>
                @if(($wd['discount_total'] ?? 0) > 0)
                <div class="p-2 text-xs text-center text-orange-700 rounded-lg bg-orange-50 dark:bg-orange-900/20 dark:text-orange-300">
                    Total discounturi acordate: {{ $currencySymbol }}{{ number_format($wd['discount_total'], 0) }}
                </div>
                @endif
            </div>
        </div>

        {{-- Payment Methods --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Metode de plata</h3>
            @if(!empty($payments))
            <div class="h-44" x-data="{
                init() {
                    const d = {{ Js::from($payments) }};
                    const colors = ['rgb(99,102,241)', 'rgb(16,185,129)', 'rgb(249,115,22)', 'rgb(239,68,68)', 'rgb(168,85,247)'];
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: d.map(r => r.method),
                            datasets: [{ data: d.map(r => r.orders), backgroundColor: colors.slice(0, d.length), borderWidth: 2, borderColor: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff' }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt date.</p>
            @endif
        </div>

        {{-- Refund Timeline --}}
        <div class="p-5 bg-white border border-gray-200 dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Cand se cer refund-uri</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Raportat la data evenimentului</p>
            @if(!empty($refundTimeline['data']) && array_sum($refundTimeline['data']) > 0)
            <div class="h-44" x-data="{
                init() {
                    new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($refundTimeline['labels']) }},
                            datasets: [{ data: {{ Js::from($refundTimeline['data']) }}, backgroundColor: 'rgba(239,68,68,0.6)', borderRadius: 4 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                    });
                }
            }">
                <canvas x-ref="canvas"></canvas>
            </div>
            @else
                <p class="text-sm text-gray-500">Nu sunt date de refund.</p>
            @endif
        </div>
    </div>
</div>
