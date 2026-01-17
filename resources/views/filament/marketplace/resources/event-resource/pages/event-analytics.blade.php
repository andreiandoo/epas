<x-filament-panels::page>
    {{-- Include external libraries --}}
    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .stat-card { background: rgb(19 17 28); backdrop-filter: blur(10px); }
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
    ]))" x-init="init()" @milestones-updated.window="milestones = $event.detail.milestones; $nextTick(() => initCharts())">

        {{-- Top Navigation Bar --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-2 py-2">
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
                            <button @click="changePeriod('7d')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="period === '7d' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">7D</button>
                            <button @click="changePeriod('30d')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="period === '30d' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">30D</button>
                            <button @click="changePeriod('all')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all" :class="period === 'all' ? 'bg-white dark:bg-gray-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'">All</button>
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
            <div class="stat-card rounded-2xl p-5 border border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center">
                        <x-heroicon-o-currency-dollar class="w-5 h-5 text-white" />
                    </div>
                    <span x-show="overview.revenue?.change" class="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full" :class="overview.revenue?.change >= 0 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                        <span x-text="(overview.revenue?.change >= 0 ? '+' : '') + overview.revenue?.change + '%'"></span>
                    </span>
                </div>
                <div class="text-2xl font-bold text-white" x-text="formatCurrency(overview.revenue?.total || 0)"></div>
                <div class="text-xs text-gray-400 mt-1">Total Revenue</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 rounded-full" :style="'width:' + Math.min(overview.revenue?.progress || 0, 100) + '%'"></div>
                    </div>
                    <span class="text-[10px] text-gray-400" x-text="(overview.revenue?.progress || 0) + '%'"></span>
                </div>
            </div>

            {{-- Tickets Sold --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                        <x-heroicon-o-ticket class="w-5 h-5 text-white" />
                    </div>
                    <span x-show="eventMode === 'live' && overview.tickets?.today" class="text-xs font-medium px-2 py-1 rounded-full bg-blue-500/20 text-blue-400" x-text="'+' + overview.tickets?.today + ' today'"></span>
                </div>
                <div class="text-2xl font-bold text-white" x-text="(overview.tickets?.sold || 0).toLocaleString()"></div>
                <div class="text-xs text-gray-400 mt-1">Tickets Sold</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-400 to-blue-500 rounded-full" :style="'width:' + (overview.tickets?.progress || 0) + '%'"></div>
                    </div>
                    <span class="text-[10px] text-gray-400" x-text="(overview.tickets?.progress || 0) + '%'"></span>
                </div>
            </div>

            {{-- Total Visits --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center">
                        <x-heroicon-o-eye class="w-5 h-5 text-white" />
                    </div>
                </div>
                <div class="text-2xl font-bold text-white" x-text="(overview.visits?.total || 0).toLocaleString()"></div>
                <div class="text-xs text-gray-400 mt-1">Total Visits</div>
                <div class="mt-3 text-[11px] text-gray-500" x-text="(overview.visits?.unique || 0).toLocaleString() + ' unique'"></div>
            </div>

            {{-- Conversion Rate --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-white" />
                    </div>
                </div>
                <div class="text-2xl font-bold text-white" x-text="(overview.conversion?.rate || 0) + '%'"></div>
                <div class="text-xs text-gray-400 mt-1">Conversion Rate</div>
                <div class="mt-3 text-[11px] text-gray-500">Visits -> Purchases</div>
            </div>

            {{-- Days Until / Status --}}
            <div class="stat-card rounded-2xl p-5 border border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-pink-600 flex items-center justify-center">
                        <x-heroicon-o-calendar class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-medium px-2 py-1 rounded-full" :class="eventMode === 'live' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-400'" x-text="overview.event?.status || 'On Sale'"></span>
                </div>
                <div class="text-2xl font-bold text-white" x-text="eventMode === 'live' ? (overview.event?.days_until || 0) : '—'"></div>
                <div class="text-xs text-gray-400 mt-1" x-text="eventMode === 'live' ? 'Days Until Event' : 'Event Ended'"></div>
                <div class="mt-3 text-[11px] text-gray-500" x-text="overview.event?.date || ''"></div>
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
                            <x-heroicon-o-arrow-trending-up class="w-3 h-3" />
                            <span x-text="m.impact"></span>
                        </div>
                        <div x-show="!m.budget && !m.impact" class="text-xs text-gray-500 mt-2" x-text="m.description"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Recent Sales --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
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
                            <tr @click="$dispatch('open-buyer-journey', { orderId: s.id })" class="border-b border-gray-50 dark:border-gray-700 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 cursor-pointer transition-colors">
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

        {{-- Goals Section --}}
        @if(count($this->goals) > 0 || true)
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Goals Progress</h2>
                    <p class="text-xs text-gray-500">Track your event targets</p>
                </div>
                <button wire:click="openGoalModal" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-colors">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Add Goal
                </button>
            </div>
            @if(count($this->goals) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($this->goals as $goal)
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-primary-200 transition-colors">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center
                                @if($goal['type'] === 'revenue') bg-emerald-100 text-emerald-600
                                @elseif($goal['type'] === 'tickets') bg-blue-100 text-blue-600
                                @elseif($goal['type'] === 'visitors') bg-cyan-100 text-cyan-600
                                @else bg-amber-100 text-amber-600 @endif">
                                @if($goal['type'] === 'revenue')
                                    <x-heroicon-o-currency-dollar class="w-4 h-4" />
                                @elseif($goal['type'] === 'tickets')
                                    <x-heroicon-o-ticket class="w-4 h-4" />
                                @elseif($goal['type'] === 'visitors')
                                    <x-heroicon-o-users class="w-4 h-4" />
                                @else
                                    <x-heroicon-o-chart-bar class="w-4 h-4" />
                                @endif
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $goal['type_label'] }}</div>
                                @if($goal['name'])
                                <div class="text-xs text-gray-500">{{ $goal['name'] }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button wire:click="openGoalModal({{ $goal['id'] }})" class="p-1 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                            </button>
                            <button wire:click="deleteGoal({{ $goal['id'] }})" wire:confirm="Are you sure you want to delete this goal?" class="p-1 text-gray-400 hover:text-red-500 transition-colors">
                                <x-heroicon-o-trash class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $goal['formatted_current'] }}</span>
                            <span class="text-xs text-gray-400">/ {{ $goal['formatted_target'] }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500
                                @if($goal['progress_percent'] >= 100) bg-emerald-500
                                @elseif($goal['progress_percent'] >= 75) bg-blue-500
                                @elseif($goal['progress_percent'] >= 50) bg-amber-500
                                @else bg-red-400 @endif"
                                style="width: {{ min($goal['progress_percent'], 100) }}%"></div>
                        </div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ number_format($goal['progress_percent'], 1) }}%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium
                            @if($goal['status'] === 'achieved') bg-emerald-100 text-emerald-700
                            @elseif($goal['status'] === 'on_track') bg-blue-100 text-blue-700
                            @elseif($goal['status'] === 'at_risk') bg-amber-100 text-amber-700
                            @elseif($goal['status'] === 'missed') bg-red-100 text-red-700
                            @else bg-gray-100 text-gray-600 @endif">
                            {{ ucfirst(str_replace('_', ' ', $goal['status'])) }}
                        </span>
                        @if($goal['days_remaining'] !== null && $goal['days_remaining'] > 0)
                        <span class="text-[10px] text-gray-400">{{ $goal['days_remaining'] }} days left</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8">
                <x-heroicon-o-flag class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p class="text-gray-500 text-sm">No goals set yet</p>
                <p class="text-gray-400 text-xs">Set targets to track your event's success</p>
            </div>
            @endif
        </div>
        @endif

        {{-- Report Schedules Section --}}
        @if(count($this->reportSchedules) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Scheduled Reports</h2>
                    <p class="text-xs text-gray-500">Automated analytics reports</p>
                </div>
                <button wire:click="openScheduleModal" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-colors">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Add Schedule
                </button>
            </div>
            <div class="space-y-3">
                @foreach($this->reportSchedules as $schedule)
                <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                            <x-heroicon-o-clock class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $schedule['frequency_label'] }} Report</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full {{ $schedule['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $schedule['is_active'] ? 'Active' : 'Paused' }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ implode(', ', $schedule['recipients']) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Next: {{ $schedule['next_send_at'] ?? 'Not scheduled' }}</span>
                        <button wire:click="sendTestReport({{ $schedule['id'] }})" class="p-1.5 text-gray-400 hover:text-primary-600 transition-colors" title="Send Test">
                            <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        </button>
                        <button wire:click="toggleScheduleActive({{ $schedule['id'] }})" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors" title="Toggle Active">
                            @if($schedule['is_active'])
                            <x-heroicon-o-pause class="w-4 h-4" />
                            @else
                            <x-heroicon-o-play class="w-4 h-4" />
                            @endif
                        </button>
                        <button wire:click="openScheduleModal({{ $schedule['id'] }})" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors" title="Edit">
                            <x-heroicon-o-pencil class="w-4 h-4" />
                        </button>
                        <button wire:click="deleteSchedule({{ $schedule['id'] }})" wire:confirm="Delete this report schedule?" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors" title="Delete">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Goal Modal --}}
        <div x-show="$wire.showGoalModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showGoalModal', false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $this->editingGoalId ? 'Edit Goal' : 'Create Goal' }}
                            </h2>
                            <button wire:click="$set('showGoalModal', false)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <form wire:submit="saveGoal" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Goal Type</label>
                                <select wire:model.live="goalData.type" class="p-2 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <option value="revenue">Revenue Target</option>
                                    <option value="tickets">Tickets Target</option>
                                    <option value="visitors">Visitors Target</option>
                                    <option value="conversion_rate">Conversion Rate Target</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name (Optional)</label>
                                <input type="text" wire:model="goalData.name" class="p-2 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" placeholder="e.g., Q1 Revenue Goal">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Target Value
                                    @if($this->goalData['type'] === 'revenue')
                                    (RON)
                                    @elseif($this->goalData['type'] === 'conversion_rate')
                                    (%)
                                    @endif
                                </label>
                                <input type="number" wire:model="goalData.target_value" class="p-2 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" required min="0" step="any">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deadline (Optional)</label>
                                <input type="date" wire:model="goalData.deadline" class="p-2 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Alert at milestones</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach([25, 50, 75, 90, 100] as $threshold)
                                    <label class="inline-flex items-center gap-1.5">
                                        <input type="checkbox" wire:model="goalData.alert_thresholds" value="{{ $threshold }}" class="rounded border-gray-300 text-primary-600">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $threshold }}%</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="goalData.email_alerts" class="rounded border-gray-300 text-primary-600">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Email Alerts</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="goalData.in_app_alerts" class="rounded border-gray-300 text-primary-600">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">In-App Alerts</span>
                                </label>
                            </div>
                            <div class="pt-4 flex justify-end gap-3">
                                <button type="button" wire:click="$set('showGoalModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                                    {{ $this->editingGoalId ? 'Update Goal' : 'Create Goal' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Export Modal --}}
        <div x-show="$wire.showExportModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showExportModal', false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Export Analytics</h2>
                            <button wire:click="$set('showExportModal', false)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-sm text-gray-500">Export your event analytics for the selected period ({{ $this->period }}).</p>

                        <div class="grid grid-cols-3 gap-3">
                            <button wire:click="exportToCsv" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-300 hover:bg-primary-50/50 transition-colors">
                                <x-heroicon-o-table-cells class="w-8 h-8 text-emerald-600" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CSV</span>
                            </button>
                            <button wire:click="exportToPdf" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-300 hover:bg-primary-50/50 transition-colors">
                                <x-heroicon-o-document class="w-8 h-8 text-red-600" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">PDF</span>
                            </button>
                            <button wire:click="exportSales" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-300 hover:bg-primary-50/50 transition-colors">
                                <x-heroicon-o-banknotes class="w-8 h-8 text-blue-600" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Sales</span>
                            </button>
                        </div>

                        <div class="pt-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Include sections:</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach(['overview' => 'Overview', 'traffic' => 'Traffic', 'milestones' => 'Milestones', 'goals' => 'Goals', 'funnel' => 'Funnel'] as $key => $label)
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                                    <input type="checkbox" wire:model="exportSections" value="{{ $key }}" class="rounded border-gray-300 text-primary-600">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schedule Modal --}}
        <div x-show="$wire.showScheduleModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showScheduleModal', false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $this->editingScheduleId ? 'Edit Report Schedule' : 'Create Report Schedule' }}
                            </h2>
                            <button wire:click="$set('showScheduleModal', false)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <form wire:submit="saveSchedule" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Frequency</label>
                                <select wire:model.live="scheduleData.frequency" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            @if($this->scheduleData['frequency'] === 'weekly')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Day of Week</label>
                                <select wire:model="scheduleData.day_of_week" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <option value="0">Sunday</option>
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                </select>
                            </div>
                            @endif
                            @if($this->scheduleData['frequency'] === 'monthly')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Day of Month</label>
                                <select wire:model="scheduleData.day_of_month" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    @for($i = 1; $i <= 28; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            @endif
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Send At</label>
                                <input type="time" wire:model="scheduleData.send_at" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recipients (one per line)</label>
                                <textarea wire:model="scheduleData.recipients" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" rows="2" placeholder="email@example.com"></textarea>
                                <p class="text-xs text-gray-400 mt-1">Separate multiple emails with commas or new lines</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Report Sections</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(['overview' => 'Overview', 'chart' => 'Chart', 'traffic' => 'Traffic', 'milestones' => 'Milestones', 'goals' => 'Goals', 'top_locations' => 'Locations', 'funnel' => 'Funnel'] as $key => $label)
                                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                                        <input type="checkbox" wire:model="scheduleData.sections" value="{{ $key }}" class="rounded border-gray-300 text-primary-600">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Attachment Format</label>
                                <select wire:model="scheduleData.format" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <option value="email">Email Only</option>
                                    <option value="pdf">Include PDF</option>
                                    <option value="csv">Include CSV</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="scheduleData.include_comparison" class="rounded border-gray-300 text-primary-600">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Include Period Comparison</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="scheduleData.is_active" class="rounded border-gray-300 text-primary-600">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Active</span>
                                </label>
                            </div>
                            <div class="pt-4 flex justify-end gap-3">
                                <button type="button" wire:click="$set('showScheduleModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                                    {{ $this->editingScheduleId ? 'Update Schedule' : 'Create Schedule' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Milestone Detail Modal --}}
        <div x-show="$wire.showMilestoneDetailModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showMilestoneDetailModal', false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
                    @if($this->selectedMilestone)
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background-color: {{ $this->selectedMilestone['color'] }}20">
                                    <x-heroicon-o-megaphone class="w-5 h-5" style="color: {{ $this->selectedMilestone['color'] }}" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $this->selectedMilestone['title'] }}</h2>
                                    <p class="text-sm text-gray-500">{{ $this->selectedMilestone['label'] }}</p>
                                </div>
                            </div>
                            <button wire:click="$set('showMilestoneDetailModal', false)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        @if($this->selectedMilestone['description'])
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $this->selectedMilestone['description'] }}</p>
                        </div>
                        @endif

                        <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                            <div class="flex-1">
                                <div class="text-xs text-gray-500 mb-1">Start Date</div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $this->selectedMilestone['start_date_formatted'] ?? 'N/A' }}</div>
                            </div>
                            <x-heroicon-o-arrow-right class="w-5 h-5 text-gray-400" />
                            <div class="flex-1">
                                <div class="text-xs text-gray-500 mb-1">End Date</div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $this->selectedMilestone['end_date_formatted'] ?? 'Ongoing' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                <div class="text-xs text-blue-600 dark:text-blue-400 mb-1">Budget</div>
                                <div class="text-xl font-bold text-blue-700 dark:text-blue-300">
                                    {{ number_format($this->selectedMilestone['budget'] ?? 0, 2) }} {{ $this->selectedMilestone['currency'] ?? 'RON' }}
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                                <div class="text-xs text-emerald-600 dark:text-emerald-400 mb-1">Revenue</div>
                                <div class="text-xl font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ number_format($this->selectedMilestone['attributed_revenue'] ?? 0, 2) }} RON
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20">
                                <div class="text-xs text-purple-600 dark:text-purple-400 mb-1">Conversions</div>
                                <div class="text-xl font-bold text-purple-700 dark:text-purple-300">
                                    {{ number_format($this->selectedMilestone['conversions'] ?? 0) }}
                                </div>
                            </div>
                            <div class="p-4 rounded-xl {{ ($this->selectedMilestone['roi'] ?? 0) >= 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                                <div class="text-xs {{ ($this->selectedMilestone['roi'] ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mb-1">ROI</div>
                                <div class="text-xl font-bold {{ ($this->selectedMilestone['roi'] ?? 0) >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                    {{ number_format($this->selectedMilestone['roi'] ?? 0, 1) }}%
                                </div>
                            </div>
                        </div>

                        @if(($this->selectedMilestone['cac'] ?? null) || ($this->selectedMilestone['targeting'] ?? null))
                        <div class="space-y-3">
                            @if($this->selectedMilestone['cac'] ?? null)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Customer Acquisition Cost</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ number_format($this->selectedMilestone['cac'], 2) }} RON</span>
                            </div>
                            @endif
                            @if($this->selectedMilestone['targeting'] ?? null)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-1">Targeting</div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedMilestone['targeting'] }}</p>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Buyer Journey Modal --}}
        <div x-show="$wire.showBuyerJourneyModal" x-transition class="fixed inset-0 z-50" x-cloak>
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showBuyerJourneyModal', false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto" @click.stop>
                    @if($this->selectedBuyer)
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-lg">
                                    {{ $this->selectedBuyer['customer']['initials'] ?? 'U' }}
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $this->selectedBuyer['customer']['name'] ?? 'Customer' }}</h2>
                                    <p class="text-sm text-gray-500">{{ $this->selectedBuyer['customer']['email'] ?? '' }}</p>
                                </div>
                            </div>
                            <button wire:click="$set('showBuyerJourneyModal', false)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-center">
                                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($this->selectedBuyer['purchase']['amount'] ?? 0, 2) }}</div>
                                <div class="text-xs text-emerald-600">Amount (RON)</div>
                            </div>
                            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-center">
                                <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $this->selectedBuyer['purchase']['quantity'] ?? 0 }}</div>
                                <div class="text-xs text-blue-600">Tickets</div>
                            </div>
                            <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 text-center">
                                <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ $this->selectedBuyer['timing']['sessions_count'] ?? 1 }}</div>
                                <div class="text-xs text-purple-600">Sessions</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-2">Source</div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 text-xs font-medium rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                        {{ $this->selectedBuyer['source']['channel'] ?? 'Direct' }}
                                    </span>
                                    @if($this->selectedBuyer['source']['campaign'] ?? null)
                                    <span class="text-xs text-gray-500">{{ $this->selectedBuyer['source']['campaign'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-2">Device</div>
                                <div class="flex items-center gap-2">
                                    @if(($this->selectedBuyer['device']['type'] ?? 'Desktop') === 'Mobile')
                                    <x-heroicon-o-device-phone-mobile class="w-4 h-4 text-gray-500" />
                                    @else
                                    <x-heroicon-o-computer-desktop class="w-4 h-4 text-gray-500" />
                                    @endif
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedBuyer['device']['type'] ?? 'Desktop' }}</span>
                                    <span class="text-xs text-gray-400">{{ $this->selectedBuyer['device']['browser'] ?? '' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-2">Location</div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ $this->selectedBuyer['location']['flag'] ?? '🌍' }}</span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->selectedBuyer['location']['city'] ?? 'Unknown' }}</span>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-2">Time to Purchase</div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $this->selectedBuyer['timing']['time_to_purchase'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                        @if(!empty($this->selectedBuyer['journey']))
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Customer Journey</h3>
                            <div class="relative pl-6 space-y-4">
                                <div class="absolute left-2 top-2 bottom-2 w-0.5 bg-gray-200 dark:bg-gray-600"></div>
                                @foreach($this->selectedBuyer['journey'] as $step)
                                <div class="relative flex items-start gap-3">
                                    <div class="absolute -left-4 w-4 h-4 rounded-full {{ ($step['type'] ?? '') === 'purchase' ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-500' }} ring-4 ring-white dark:ring-gray-800"></div>
                                    <div class="flex-1 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $step['title'] ?? $step['type'] ?? 'Event' }}</span>
                                            <span class="text-xs text-gray-400">{{ $step['time'] ?? '' }}</span>
                                        </div>
                                        @if(!empty($step['description']))
                                        <p class="text-xs text-gray-500 mt-1">{{ $step['description'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if($this->selectedBuyer['customer']['is_returning'] ?? false)
                        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                            <div class="flex items-center gap-2 mb-2">
                                <x-heroicon-s-star class="w-5 h-5 text-amber-500" />
                                <span class="font-medium text-amber-700 dark:text-amber-300">Returning Customer</span>
                            </div>
                            <div class="text-sm text-amber-600 dark:text-amber-400">
                                {{ $this->selectedBuyer['customer']['previous_purchases'] ?? 0 }} previous purchases •
                                {{ number_format($this->selectedBuyer['customer']['lifetime_value'] ?? 0, 2) }} RON lifetime value
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
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

                    // Watch for globe modal
                    this.$watch('showGlobeModal', (value) => {
                        if (value) {
                            setTimeout(() => this.initGlobe(), 500);
                        }
                    });
                },

                async changePeriod(newPeriod) {
                    if (this.period === newPeriod) return;
                    this.period = newPeriod;

                    try {
                        const data = await this.$wire.fetchDashboardData(newPeriod);
                        if (data) {
                            this.overview = data.overview || this.overview;
                            this.chartData = data.chartData || this.chartData;
                            this.ticketPerformance = data.ticketPerformance || this.ticketPerformance;
                            this.trafficSources = data.trafficSources || this.trafficSources;
                            this.topLocations = data.topLocations || this.topLocations;
                            this.milestones = data.milestones || this.milestones;
                            this.recentSales = data.recentSales || this.recentSales;
                            this.$nextTick(() => this.initCharts());
                        }
                    } catch (e) {
                        console.error('Failed to fetch dashboard data:', e);
                    }
                },

                async openBuyerJourney(orderId) {
                    this.$dispatch('open-buyer-journey', { orderId: orderId });
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

                    // Build milestone annotations for the chart
                    const milestoneAnnotations = (this.milestones || [])
                        .filter(m => m.start_date)
                        .map(m => {
                            const date = m.start_date.split('T')[0]; // Get just the date part
                            // Find matching category index
                            const dateIndex = data.findIndex(d => d.date === date || d.full_date?.includes(date));
                            if (dateIndex === -1) return null;

                            return {
                                x: data[dateIndex]?.date,
                                borderColor: m.type?.includes('campaign') ? '#3b82f6' : '#8b5cf6',
                                strokeDashArray: 4,
                                label: {
                                    borderColor: m.type?.includes('campaign') ? '#3b82f6' : '#8b5cf6',
                                    style: {
                                        color: '#fff',
                                        background: m.type?.includes('campaign') ? '#3b82f6' : '#8b5cf6',
                                        fontSize: '10px',
                                        fontWeight: 500,
                                        padding: { left: 8, right: 8, top: 4, bottom: 4 }
                                    },
                                    text: m.title?.substring(0, 20) + (m.title?.length > 20 ? '...' : ''),
                                    position: 'top',
                                    offsetY: -8
                                }
                            };
                        })
                        .filter(Boolean);

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
                        annotations: {
                            xaxis: milestoneAnnotations
                        },
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
