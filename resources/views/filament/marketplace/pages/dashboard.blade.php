<x-filament-panels::page>
    @if(!$marketplace)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found. Please contact support.</p>
        </div>
    @else
        <!-- Welcome -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $marketplace->name }}</h2>
                    @if($marketplace->domain)
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $marketplace->domain }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Stats Cards - 4 per row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            {{-- 1. Evenimente --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_events']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Evenimente</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">{{ $stats['active_events'] }} active</p>
                    </div>
                </div>
            </div>

            {{-- 2. Clienți --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-users class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_customers']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Clienți</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ number_format($stats['registered_customers']) }} registered · {{ number_format($stats['guest_customers']) }} guest
                        </p>
                    </div>
                </div>
            </div>

            {{-- 3. Comenzi --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-shopping-cart class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_orders']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Comenzi</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <span class="text-green-600 dark:text-green-400">{{ number_format($stats['paid_orders']) }} valide</span>
                            @if($stats['other_orders'] > 0)
                                · <span class="text-gray-400">{{ number_format($stats['other_orders']) }} alte</span>
                            @endif
                            @if($stats['today_orders'] > 0)
                                · <span class="text-blue-600 dark:text-blue-400">+{{ $stats['today_orders'] }} azi</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 4. Încasări --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_incasari'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Încasări</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Comenzi: {{ number_format($stats['order_revenue'], 2) }}
                            @if($stats['service_revenue'] > 0)
                                · Servicii: {{ number_format($stats['service_revenue'], 2) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 5. Venituri (comisioane + servicii) --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-currency-euro class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['commissions'] + $stats['service_orders_total'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Venituri</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Comisioane: {{ number_format($stats['commissions'], 2) }}
                            @if($stats['service_orders_total'] > 0)
                                · Servicii: {{ number_format($stats['service_orders_total'], 2) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- 6. Bilete vândute --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_tickets']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Bilete Vândute</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            @if($stats['today_tickets'] > 0)
                                <span class="text-blue-600 dark:text-blue-400">+{{ $stats['today_tickets'] }} azi</span> ·
                            @endif
                            {{ number_format($stats['total_tickets_db']) }} total în DB
                        </p>
                    </div>
                </div>
            </div>

            {{-- 7. Organizatori --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-user-group class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_organizers']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Organizatori</p>
                        <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">{{ $stats['active_organizers'] }} activi</p>
                    </div>
                </div>
            </div>

            {{-- 8. Payouts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg shrink-0">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['pending_payouts_value'], 2) }} <span class="text-sm font-medium text-gray-400">RON</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Payouts Pending</p>
                        <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">{{ number_format($stats['completed_payouts_value'], 2) }} plătite</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables: Top Organizers + Top Live Events side by side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            {{-- Top Organizers --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 uppercase tracking-wide">Top Organizatori</h3>
                @if($topOrganizers && $topOrganizers->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Organizator</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Încasări</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Bilete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topOrganizers as $organizer)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[180px]">{{ $organizer->company_name ?? $organizer->name }}</p>
                                    </td>
                                    <td class="py-2 px-3 text-right font-medium text-gray-900 dark:text-white text-sm whitespace-nowrap">
                                        {{ number_format($organizer->total_revenue ?? 0, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 text-sm">
                                        {{ number_format($organizer->total_tickets_sold ?? 0) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Niciun organizator activ.</p>
                @endif
            </div>

            {{-- Top Live Events --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 uppercase tracking-wide">Top Evenimente Live</h3>
                @if($topLiveEvents && $topLiveEvents->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Eveniment</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Încasări</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400 text-xs">Bilete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topLiveEvents as $event)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[180px]">{{ $event->name }}</p>
                                        <p class="text-xs text-gray-400">
                                            @if($event->duration_mode === 'single_day' && $event->event_date)
                                                {{ \Carbon\Carbon::parse($event->event_date)->format('d M Y') }}
                                            @elseif($event->range_start_date)
                                                {{ \Carbon\Carbon::parse($event->range_start_date)->format('d M') }} - {{ \Carbon\Carbon::parse($event->range_end_date)->format('d M Y') }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="py-2 px-3 text-right font-medium text-gray-900 dark:text-white text-sm whitespace-nowrap">
                                        {{ number_format($event->event_revenue ?? 0, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 text-sm">
                                        {{ number_format($event->sold_tickets_count ?? 0) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Niciun eveniment live.</p>
                @endif
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5" wire:key="charts-{{ $chartPeriod }}">
            <!-- Sales Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Vânzări</h3>
                    <select
                        wire:model.live="chartPeriod"
                        class="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500 py-1"
                    >
                        <option value="7">7 zile</option>
                        <option value="15">15 zile</option>
                        <option value="30">30 zile</option>
                        <option value="60">60 zile</option>
                        <option value="90">90 zile</option>
                    </select>
                </div>
                <div class="h-52">
                    <canvas id="salesChart" data-chart='@json($chartData)' data-currency="RON"></canvas>
                </div>
            </div>

            <!-- Tickets Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Bilete Vândute</h3>
                </div>
                <div class="h-52">
                    <canvas id="ticketsChart" data-chart='@json($ticketChartData)'></canvas>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() { initCharts(); });
        document.addEventListener('livewire:navigated', function() { initCharts(); });
        document.addEventListener('charts-updated', function() { setTimeout(() => initCharts(), 100); });
        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector && (el.querySelector('#salesChart') || el.querySelector('#ticketsChart'))) {
                setTimeout(() => initCharts(), 100);
            }
        });

        function initCharts() { initSalesChart(); initTicketsChart(); }

        function initSalesChart() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            const isDark = document.documentElement.classList.contains('dark');
            const chartDataStr = ctx.getAttribute('data-chart');
            if (!chartDataStr) return;
            const chartData = JSON.parse(chartDataStr);
            const currency = ctx.getAttribute('data-currency') || 'RON';

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Vânzări',
                        data: chartData.data,
                        borderColor: isDark ? '#818cf8' : '#6366f1',
                        backgroundColor: isDark ? 'rgba(129, 140, 248, 0.1)' : 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2, pointHoverRadius: 4,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1, padding: 10, displayColors: false,
                            callbacks: {
                                label: (ctx) => new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(ctx.parsed.y) + ' ' + currency
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: isDark ? '#9ca3af' : '#6b7280', maxRotation: 45, font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: isDark ? '#374151' : '#f3f4f6' }, ticks: { color: isDark ? '#9ca3af' : '#6b7280', font: { size: 10 }, callback: (v) => new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(v) } }
                    }
                }
            });
        }

        function initTicketsChart() {
            const ctx = document.getElementById('ticketsChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            const isDark = document.documentElement.classList.contains('dark');
            const dataStr = ctx.getAttribute('data-chart');
            if (!dataStr) return;
            const ticketData = JSON.parse(dataStr);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ticketData.labels,
                    datasets: [{
                        label: 'Bilete',
                        data: ticketData.data,
                        backgroundColor: isDark ? 'rgba(168, 85, 247, 0.7)' : 'rgba(147, 51, 234, 0.7)',
                        borderColor: isDark ? '#a855f7' : '#9333ea',
                        borderWidth: 1, borderRadius: 3,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1, padding: 10, displayColors: false,
                            callbacks: { label: (ctx) => ctx.parsed.y + ' bilet' + (ctx.parsed.y !== 1 ? 'e' : '') }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: isDark ? '#9ca3af' : '#6b7280', maxRotation: 45, font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: isDark ? '#374151' : '#f3f4f6' }, ticks: { color: isDark ? '#9ca3af' : '#6b7280', font: { size: 10 }, stepSize: 1, callback: (v) => Number.isInteger(v) ? v : '' } }
                    }
                }
            });
        }
    </script>
    @endpush
</x-filament-panels::page>
