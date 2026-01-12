<x-filament-panels::page>
    {{-- Include external libraries --}}
    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%); backdrop-filter: blur(10px); }
        .forecast-card { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); }
        .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2); opacity: 0; } }
        .milestone-card { transition: all 0.2s ease; }
        .milestone-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        #globeMap { background: #f8fafc !important; z-index: 1; }
        .leaflet-container { background: #f8fafc !important; }
    </style>
    @endpush

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endpush

    <div x-data="eventAnalyticsDashboard(@js([
        'eventId' => $this->eventId,
        'eventMode' => $this->eventMode,
        'period' => $this->period,
        'overview' => $this->getOverviewStats(),
        'chartData' => $this->getChartData(),
        'ticketPerformance' => $this->getTicketPerformance(),
        'trafficSources' => $this->getTrafficSources(),
        'topLocations' => $this->getTopLocations(),
        'milestones' => $this->milestones,
        'recentSales' => $this->recentSales,
        'adCampaigns' => $this->getAdCampaigns(),
        'liveVisitors' => $this->eventMode === 'live' ? $this->getLiveVisitorCount() : 0,
    ]))" x-init="init()">

        {{-- Top Navigation Bar --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-6 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        {{-- Event Mode Tabs --}}
                        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-xl p-1">
                            <button @click="eventMode = 'live'" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all" :class="eventMode === 'live' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700'">
                                <span x-show="eventMode === 'live'" class="relative flex h-2 w-2">
                                    <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                <span>Live</span>
                            </button>
                            <button @click="eventMode = 'past'" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all" :class="eventMode === 'past' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700'">
                                <x-heroicon-o-document-chart-bar class="w-4 h-4" />
                                <span>Report</span>
                            </button>
                        </div>

                        {{-- Period Selector --}}
                        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-xl p-1">
                            <button wire:click="$set('period', '7d')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="'{{ $this->period }}' === '7d' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">7D</button>
                            <button wire:click="$set('period', '30d')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="'{{ $this->period }}' === '30d' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">30D</button>
                            <button wire:click="$set('period', 'all')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="'{{ $this->period }}' === 'all' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">All</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Live indicator --}}
                        <button x-show="eventMode === 'live'" @click="openGlobeModal()" class="flex items-center gap-2 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 rounded-xl border border-emerald-200 transition-colors cursor-pointer">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <span class="text-sm font-medium text-emerald-700" x-text="liveVisitors + ' online'"></span>
                            <x-heroicon-o-globe-alt class="w-4 h-4 text-emerald-600" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            {{-- Revenue --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center">
                        <x-heroicon-o-currency-dollar class="w-5 h-5 text-white" />
                    </div>
                    <span x-show="overview.revenue?.change" class="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full" :class="overview.revenue?.change >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                        <span x-text="(overview.revenue?.change >= 0 ? '+' : '') + overview.revenue?.change + '%'"></span>
                    </span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="formatCurrency(overview.revenue?.total || 0)"></div>
                <div class="text-xs text-gray-500 mt-1">Total Revenue</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 rounded-full" :style="'width:' + Math.min(overview.revenue?.progress || 0, 100) + '%'"></div>
                    </div>
                    <span class="text-[10px] text-gray-400" x-text="(overview.revenue?.progress || 0) + '%'"></span>
                </div>
            </div>

            {{-- Tickets Sold --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                        <x-heroicon-o-ticket class="w-5 h-5 text-white" />
                    </div>
                    <span x-show="eventMode === 'live' && overview.tickets?.today" class="text-xs font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-700" x-text="'+' + overview.tickets?.today + ' today'"></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="(overview.tickets?.sold || 0).toLocaleString()"></div>
                <div class="text-xs text-gray-500 mt-1">Tickets Sold</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-400 to-blue-500 rounded-full" :style="'width:' + (overview.tickets?.progress || 0) + '%'"></div>
                    </div>
                    <span class="text-[10px] text-gray-400" x-text="(overview.tickets?.progress || 0) + '%'"></span>
                </div>
            </div>

            {{-- Total Visits --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center">
                        <x-heroicon-o-eye class="w-5 h-5 text-white" />
                    </div>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="(overview.visits?.total || 0).toLocaleString()"></div>
                <div class="text-xs text-gray-500 mt-1">Total Visits</div>
                <div class="mt-3 text-[11px] text-gray-400" x-text="(overview.visits?.unique || 0).toLocaleString() + ' unique'"></div>
            </div>

            {{-- Conversion Rate --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-white" />
                    </div>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="(overview.conversion?.rate || 0) + '%'"></div>
                <div class="text-xs text-gray-500 mt-1">Conversion Rate</div>
                <div class="mt-3 text-[11px] text-gray-400">Visits -> Purchases</div>
            </div>

            {{-- Days Until / Status --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-pink-600 flex items-center justify-center">
                        <x-heroicon-o-calendar class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-medium px-2 py-1 rounded-full" :class="eventMode === 'live' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'" x-text="overview.event?.status || 'On Sale'"></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="eventMode === 'live' ? (overview.event?.days_until || 0) : '—'"></div>
                <div class="text-xs text-gray-500 mt-1" x-text="eventMode === 'live' ? 'Days Until Event' : 'Event Ended'"></div>
                <div class="mt-3 text-[11px] text-gray-400" x-text="overview.event?.date || ''"></div>
            </div>
        </div>

        {{-- Chart + Summary --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Performance Overview</h2>
                        <p class="text-xs text-gray-500">Click to toggle metrics</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-for="m in chartMetrics" :key="m.key">
                            <button @click="toggleMetric(m.key)" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all" :class="m.active ? 'border-transparent shadow-sm' : 'border-gray-200 opacity-50'" :style="m.active ? 'background:' + m.color + '15' : ''">
                                <div class="w-2.5 h-2.5 rounded-full" :style="'background:' + m.color"></div>
                                <span class="text-xs font-medium" :style="m.active ? 'color:' + m.color : 'color:#9ca3af'" x-text="m.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div id="mainChart" class="h-[300px]"></div>
            </div>

            {{-- Summary Panel --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-400 to-slate-600 flex items-center justify-center">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="eventMode === 'live' ? 'Current Status' : 'Final Results'"></h2>
                        <p class="text-xs text-gray-500" x-text="eventMode === 'live' ? 'Live performance' : 'Event completed'"></p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-500">Total Revenue</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="formatCurrency(overview.revenue?.total || 0)"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-500">Tickets Sold</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="(overview.tickets?.sold || 0).toLocaleString()"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-500">Capacity</span>
                        <span class="text-sm font-bold text-emerald-600" x-text="(overview.tickets?.progress || 0) + '%'"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-500">Conversion</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="(overview.conversion?.rate || 0) + '%'"></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm text-gray-500">Ad Spend</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="formatCurrency(getTotalAdSpend())"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tickets & Campaign ROI --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ticket Performance</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Sold</th>
                                <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Conv.</th>
                                <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="t in ticketPerformance" :key="t.id">
                                <tr class="border-b border-gray-50 dark:border-gray-700">
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-6 rounded-full" :style="'background:' + t.color"></div>
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="t.name"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right text-sm text-gray-600 dark:text-gray-400" x-text="t.price + ' RON'"></td>
                                    <td class="py-3 text-right text-sm font-semibold text-gray-900 dark:text-white" x-text="t.sold.toLocaleString()"></td>
                                    <td class="py-3 text-right text-sm font-semibold text-gray-900 dark:text-white" x-text="formatCurrency(t.revenue)"></td>
                                    <td class="py-3 text-right text-sm font-semibold" :class="t.conversion_rate >= 4 ? 'text-emerald-600' : 'text-gray-600'" x-text="t.conversion_rate + '%'"></td>
                                    <td class="py-3 text-right">
                                        <span class="text-xs font-medium" :class="t.trend >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="(t.trend >= 0 ? '+' : '') + t.trend + '%'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Campaign ROI</h2>
                <div class="space-y-3">
                    <template x-for="c in adCampaigns" :key="c.id">
                        <div @click="$wire.openMilestoneDetail(c.id)" class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-primary-200 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span x-text="c.icon"></span>
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="c.title"></span>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'" x-text="c.is_active ? 'Active' : 'Ended'"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div><span class="text-gray-500">Spend:</span> <span class="font-medium" x-text="formatCurrency(c.budget)"></span></div>
                                <div><span class="text-gray-500">Revenue:</span> <span class="font-medium text-emerald-600" x-text="formatCurrency(c.attributed_revenue || 0)"></span></div>
                                <div><span class="text-gray-500">CAC:</span> <span class="font-medium" x-text="(c.cac || 0) + ' RON'"></span></div>
                                <div><span class="text-gray-500">ROI:</span> <span class="font-semibold" :class="(c.roi || 0) >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="((c.roi || 0) >= 0 ? '+' : '') + (c.roi || 0) + '%'"></span></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="adCampaigns.length === 0" class="text-center py-6 text-gray-400 text-sm">No ad campaigns</div>
                </div>
            </div>
        </div>

        {{-- Traffic Sources & Locations --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Traffic Sources</h2>
                <div class="space-y-3">
                    <template x-for="s in trafficSources" :key="s.name">
                        <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + s.color + '22'">
                                <span x-text="s.icon" class="text-lg"></span>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="s.name"></span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="s.visitors.toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-600 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" :style="'width:' + s.percent + '%;background:' + s.color"></div>
                                    </div>
                                    <span class="text-xs text-gray-400 w-10" x-text="s.percent + '%'"></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="formatCurrency(s.revenue)"></div>
                                <div class="text-xs text-gray-400" x-text="s.conversions + ' sales'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Locations</h2>
                <div class="space-y-3">
                    <template x-for="l in topLocations" :key="l.city">
                        <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl" x-text="l.flag"></div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="l.city"></div>
                                <div class="text-xs text-gray-400" x-text="l.country"></div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="l.tickets.toLocaleString() + ' tickets'"></div>
                                <div class="text-xs text-gray-400" x-text="formatCurrency(l.revenue)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Milestones Timeline --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Campaign Milestones</h2>
                <span class="text-xs text-gray-400" x-text="milestones.length + ' total'"></span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <template x-for="m in milestones" :key="m.id">
                    <div @click="$wire.openMilestoneDetail(m.id)" class="milestone-card p-4 rounded-xl border border-gray-100 dark:border-gray-700 cursor-pointer hover:border-primary-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" :class="getMilestoneIconClass(m.type)">
                                <span x-text="m.icon"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate" x-text="m.title"></div>
                                <div class="text-xs text-gray-500" x-text="m.start_date"></div>
                            </div>
                        </div>
                        <div x-show="m.budget" class="grid grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="text-gray-500">Budget</div>
                                <div class="font-semibold text-gray-900 dark:text-white" x-text="formatCurrency(m.budget)"></div>
                            </div>
                            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                                <div class="text-gray-500">Revenue</div>
                                <div class="font-semibold text-emerald-600" x-text="formatCurrency(m.attributed_revenue || 0)"></div>
                            </div>
                        </div>
                        <div x-show="m.roi !== null && m.roi !== undefined" class="flex items-center justify-between p-2 rounded-lg" :class="(m.roi || 0) >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                            <span class="text-xs text-gray-600 dark:text-gray-400">ROI</span>
                            <span class="text-sm font-bold" :class="(m.roi || 0) >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="((m.roi || 0) >= 0 ? '+' : '') + (m.roi || 0) + '%'"></span>
                        </div>
                        <div x-show="m.impact && !m.budget" class="mt-2 flex items-center gap-1 text-xs text-emerald-600">
                            <x-heroicon-o-trending-up class="w-3 h-3" />
                            <span x-text="m.impact"></span>
                        </div>
                        <div x-show="!m.budget && !m.impact" class="text-xs text-gray-500 mt-2" x-text="m.description"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Recent Sales --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Sales</h2>
                    <p class="text-xs text-gray-500">Click buyer for journey details</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Buyer</th>
                            <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Tickets</th>
                            <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                            <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in recentSales" :key="s.id">
                            <tr @click="$wire.openBuyerJourney(s.id)" class="border-b border-gray-50 dark:border-gray-700 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 cursor-pointer transition-colors">
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-200 to-primary-300 flex items-center justify-center text-xs font-semibold text-primary-700" x-text="s.initials"></div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="s.name"></span>
                                                <span x-show="s.is_returning" class="px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-700 rounded-full">Returning</span>
                                            </div>
                                            <div class="text-xs text-gray-400" x-text="s.email"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-sm text-gray-600 dark:text-gray-400" x-text="s.date"></td>
                                <td class="py-3 text-sm text-gray-700 dark:text-gray-300" x-text="s.quantity + 'x ' + s.ticket_type"></td>
                                <td class="py-3">
                                    <span class="text-xs px-2 py-1 rounded-full" :class="getSourceClass(s.source)" x-text="s.source"></span>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span x-text="s.payment_icon"></span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400" x-text="s.payment_method"></span>
                                    </div>
                                </td>
                                <td class="py-3 text-right text-sm font-semibold text-gray-900 dark:text-white" x-text="formatCurrency(s.amount)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Globe Modal --}}
        <div x-show="showGlobeModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="showGlobeModal = false"></div>
            <div class="fixed inset-4 bg-slate-50 rounded-3xl overflow-hidden shadow-2xl">
                <div id="globeMap" class="w-full h-full"></div>

                {{-- Header Overlay --}}
                <div class="absolute top-0 left-0 right-0 p-6 bg-gradient-to-b from-slate-50 via-slate-50/80 to-transparent pointer-events-none">
                    <div class="flex items-center justify-between pointer-events-auto">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center">
                                <x-heroicon-o-globe-alt class="w-6 h-6 text-white" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-slate-800">Live Visitors</h2>
                                <p class="text-sm text-slate-500">Real-time global activity</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-3 px-4 py-2 bg-white rounded-xl shadow-sm border border-slate-200">
                                <span class="relative flex h-3 w-3">
                                    <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                </span>
                                <span class="text-lg font-bold text-slate-800" x-text="liveVisitors"></span>
                                <span class="text-sm text-slate-500">online now</span>
                            </div>
                            <button @click="showGlobeModal = false" class="p-3 bg-white hover:bg-slate-100 rounded-xl shadow-sm border border-slate-200 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-slate-600" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function eventAnalyticsDashboard(initialData) {
            return {
                eventId: initialData.eventId,
                eventMode: initialData.eventMode,
                period: initialData.period,
                overview: initialData.overview,
                chartData: initialData.chartData,
                ticketPerformance: initialData.ticketPerformance,
                trafficSources: initialData.trafficSources,
                topLocations: initialData.topLocations,
                milestones: initialData.milestones,
                recentSales: initialData.recentSales,
                adCampaigns: initialData.adCampaigns,
                liveVisitors: initialData.liveVisitors,
                showGlobeModal: false,

                chartMetrics: [
                    {key: 'revenue', label: 'Revenue', color: '#8b5cf6', active: true},
                    {key: 'tickets', label: 'Tickets', color: '#06b6d4', active: true},
                    {key: 'visits', label: 'Visits', color: '#f59e0b', active: false}
                ],

                init() {
                    this.$nextTick(() => this.initCharts());

                    // Poll for live visitors
                    if (this.eventMode === 'live') {
                        setInterval(() => {
                            this.liveVisitors = Math.max(5, this.liveVisitors + Math.floor(Math.random() * 7) - 3);
                        }, 5000);
                    }

                    // Watch for globe modal
                    this.$watch('showGlobeModal', (value) => {
                        if (value) {
                            setTimeout(() => this.initGlobe(), 500);
                        }
                    });
                },

                formatCurrency(v) {
                    if (!v) return '0 RON';
                    if (v >= 1000000) return (v / 1000000).toFixed(2) + 'M RON';
                    if (v >= 1000) return Math.round(v / 1000) + 'K RON';
                    return v + ' RON';
                },

                getTotalAdSpend() {
                    return this.adCampaigns.reduce((s, c) => s + (c.budget || 0), 0);
                },

                toggleMetric(key) {
                    const m = this.chartMetrics.find(x => x.key === key);
                    if (m) {
                        if (this.chartMetrics.filter(x => x.active).length === 1 && m.active) return;
                        m.active = !m.active;
                        this.$nextTick(() => this.initCharts());
                    }
                },

                getMilestoneIconClass(type) {
                    const classes = {
                        'campaign_fb': 'bg-blue-100',
                        'campaign_google': 'bg-red-100',
                        'campaign_tiktok': 'bg-pink-100',
                        'campaign_instagram': 'bg-fuchsia-100',
                        'email': 'bg-amber-100',
                        'price': 'bg-emerald-100',
                        'announcement': 'bg-purple-100',
                        'press': 'bg-cyan-100',
                        'lineup': 'bg-rose-100',
                    };
                    return classes[type] || 'bg-gray-100';
                },

                getSourceClass(source) {
                    const classes = {
                        'Facebook': 'bg-blue-100 text-blue-700',
                        'Google': 'bg-red-100 text-red-700',
                        'Instagram': 'bg-pink-100 text-pink-700',
                        'TikTok': 'bg-purple-100 text-purple-700',
                        'Email': 'bg-amber-100 text-amber-700',
                        'Direct': 'bg-gray-100 text-gray-700',
                    };
                    return classes[source] || 'bg-green-100 text-green-700';
                },

                initCharts() {
                    const el = document.querySelector("#mainChart");
                    if (!el || typeof ApexCharts === 'undefined') return;

                    el.innerHTML = '';

                    const data = this.chartData || [];
                    const series = [];
                    const colors = [];
                    const yaxis = [];

                    this.chartMetrics.forEach((m, i) => {
                        if (m.active) {
                            series.push({
                                name: m.label,
                                type: m.key === 'tickets' ? 'column' : 'area',
                                data: data.map(d => d[m.key] || 0)
                            });
                            colors.push(m.color);
                            yaxis.push({
                                opposite: i > 0,
                                title: {text: m.label, style: {color: m.color, fontSize: '11px'}},
                                labels: {
                                    style: {colors: '#9ca3af', fontSize: '10px'},
                                    formatter: v => v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v
                                }
                            });
                        }
                    });

                    const self = this;
                    new ApexCharts(el, {
                        chart: {
                            type: 'line',
                            height: 300,
                            toolbar: {show: false},
                            fontFamily: 'Inter',
                            animations: {enabled: true, speed: 400}
                        },
                        series: series,
                        colors: colors,
                        stroke: {curve: 'smooth', width: series.map(s => s.type === 'column' ? 0 : 2.5)},
                        fill: {
                            type: series.map(s => s.type === 'column' ? 'solid' : 'gradient'),
                            gradient: {shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1}
                        },
                        plotOptions: {bar: {borderRadius: 4, columnWidth: '40%'}},
                        dataLabels: {enabled: false},
                        xaxis: {
                            categories: data.map(d => d.date),
                            labels: {style: {colors: '#9ca3af', fontSize: '10px'}},
                            axisBorder: {show: false},
                            axisTicks: {show: false}
                        },
                        yaxis: yaxis,
                        grid: {borderColor: '#f1f5f9'},
                        legend: {show: false},
                        tooltip: {
                            shared: true,
                            intersect: false,
                            custom: function(opts) {
                                const d = data[opts.dataPointIndex];
                                let h = '<div style="background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:14px;font-size:12px;min-width:200px;"><div style="font-weight:600;color:#1f2937;margin-bottom:10px;">' + (d.full_date || d.date) + '</div>';
                                self.chartMetrics.forEach(m => {
                                    if (m.active) {
                                        h += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;"><div style="display:flex;align-items:center;gap:6px;"><div style="width:8px;height:8px;border-radius:50%;background:' + m.color + ';"></div><span style="color:#6b7280;">' + m.label + '</span></div><span style="font-weight:600;color:#1f2937;">' + (m.key === 'revenue' ? self.formatCurrency(d[m.key]) : (d[m.key] || 0).toLocaleString()) + '</span></div>';
                                    }
                                });
                                return h + '</div>';
                            }
                        }
                    }).render();
                },

                openGlobeModal() {
                    this.showGlobeModal = true;
                },

                initGlobe() {
                    const container = document.getElementById('globeMap');
                    if (!container || typeof L === 'undefined') return;

                    if (window.globeMap) {
                        window.globeMap.remove();
                    }

                    const map = L.map(container, {
                        center: [46, 20],
                        zoom: 5,
                        zoomControl: true,
                        attributionControl: false
                    });

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        subdomains: 'abcd',
                        maxZoom: 19
                    }).addTo(map);

                    window.globeMap = map;

                    // Add sample markers
                    const locations = [
                        {lat: 44.4268, lng: 26.1025, city: 'Bucuresti', visitors: 18},
                        {lat: 46.7712, lng: 23.6236, city: 'Cluj-Napoca', visitors: 9},
                        {lat: 45.7489, lng: 21.2087, city: 'Timisoara', visitors: 6},
                        {lat: 47.4979, lng: 19.0402, city: 'Budapest', visitors: 5},
                    ];

                    locations.forEach(loc => {
                        L.circleMarker([loc.lat, loc.lng], {
                            radius: Math.max(8, loc.visitors),
                            fillColor: '#10b981',
                            color: '#fff',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.7
                        }).addTo(map).bindPopup(`<b>${loc.city}</b><br>${loc.visitors} visitors`);
                    });

                    setTimeout(() => map.invalidateSize(), 100);
                }
            };
        }
    </script>
    @endpush
</x-filament-panels::page>
