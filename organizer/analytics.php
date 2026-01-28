<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Analytics Eveniment';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = '
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
    .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%); backdrop-filter: blur(10px); }
    .forecast-card { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); }
    .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2); opacity: 0; } }
    .milestone-card { transition: all 0.2s ease; }
    .milestone-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
</style>
';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

// Get event ID from URL
$eventId = $_GET['event'] ?? null;
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Top Bar -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 py-3 lg:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <!-- Back Button -->
                    <a href="/organizator/events" class="flex items-center gap-2 text-sm text-muted hover:text-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Înapoi
                    </a>

                    <!-- Event Dropdown -->
                    <div class="relative" id="event-dropdown-container">
                        <button id="event-selector" onclick="toggleEventDropdown()" class="flex items-center gap-3 px-4 py-2.5 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition-all min-w-[280px]">
                            <div id="event-selector-image" class="flex-shrink-0 w-10 h-10 overflow-hidden rounded-lg shadow-sm bg-gray-200"></div>
                            <div class="flex-1 text-left">
                                <div id="event-selector-name" class="text-sm font-semibold text-gray-800">Se încarcă...</div>
                                <div id="event-selector-info" class="text-[11px] text-gray-500"></div>
                            </div>
                            <svg id="event-dropdown-arrow" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <!-- Dropdown Menu -->
                        <div id="event-dropdown-menu" class="absolute left-0 z-50 hidden w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl top-full max-h-80 overflow-y-auto">
                            <div id="event-dropdown-loading" class="py-4 text-sm text-center text-gray-400">
                                <svg class="w-5 h-5 mx-auto mb-2 animate-spin text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Se încarcă...
                            </div>
                            <div id="event-dropdown-list" class="hidden"></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Period Selector -->
                    <div class="flex items-center p-1 bg-gray-100 rounded-xl">
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all period-btn text-gray-500" data-period="7d">7D</button>
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all period-btn bg-white shadow-sm text-gray-900" data-period="30d">30D</button>
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all period-btn text-gray-500" data-period="90d">90D</button>
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all period-btn text-gray-500" data-period="all">Tot</button>
                    </div>

                    <!-- Live Indicator with Globe Button -->
                    <div id="live-indicator" class="items-center hidden gap-2 px-3 py-2 border border-emerald-200 bg-emerald-50 rounded-xl">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex w-full h-full rounded-full opacity-75 pulse-ring bg-emerald-400"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span id="live-count" class="text-sm font-medium text-emerald-700">0 online</span>
                        <button onclick="openGlobeModal()" class="p-1.5 ml-1 transition-colors rounded-lg hover:bg-emerald-100" title="Vezi vizitatori pe hartă">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                    </div>

                    <!-- Export -->
                    <button onclick="exportReport()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white transition-all rounded-xl bg-primary hover:bg-primary-dark">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-6">
        <!-- Stats Cards - 5 columns -->
        <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-5">
            <!-- Revenue -->
            <div class="p-5 stat-card rounded-2xl border border-white/50 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span id="stat-revenue-change" class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <span>+0%</span>
                    </span>
                </div>
                <div id="stat-revenue" class="text-2xl font-bold text-gray-900">0 lei</div>
                <div class="mt-1 text-xs text-gray-500">Venituri totale</div>
                <div class="flex items-center gap-2 mt-3">
                    <div class="flex-1 h-1.5 overflow-hidden bg-gray-100 rounded-full">
                        <div id="stat-revenue-bar" class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500" style="width: 0%"></div>
                    </div>
                    <span id="stat-revenue-percent" class="text-[10px] text-gray-400">0%</span>
                </div>
            </div>

            <!-- Tickets Sold -->
            <div class="p-5 stat-card rounded-2xl border border-white/50 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <span id="stat-tickets-today" class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">+0 azi</span>
                </div>
                <div id="stat-tickets" class="text-2xl font-bold text-gray-900">0</div>
                <div class="mt-1 text-xs text-gray-500">Bilete vândute</div>
                <div class="flex items-center gap-2 mt-3">
                    <div class="flex-1 h-1.5 overflow-hidden bg-gray-100 rounded-full">
                        <div id="stat-tickets-bar" class="h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-500" style="width: 0%"></div>
                    </div>
                    <span id="stat-tickets-percent" class="text-[10px] text-gray-400">0%</span>
                </div>
            </div>

            <!-- Total Visits -->
            <div class="p-5 stat-card rounded-2xl border border-white/50 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                </div>
                <div id="stat-views" class="text-2xl font-bold text-gray-900">0</div>
                <div class="mt-1 text-xs text-gray-500">Vizualizări totale</div>
                <div id="stat-unique" class="mt-3 text-[11px] text-gray-400">0 unice</div>
            </div>

            <!-- Conversion Rate -->
            <div class="p-5 stat-card rounded-2xl border border-white/50 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <div id="stat-conversion" class="text-2xl font-bold text-gray-900">0%</div>
                <div class="mt-1 text-xs text-gray-500">Rata conversie</div>
                <div class="mt-3 text-[11px] text-gray-400">Vizite → Achiziții</div>
            </div>

            <!-- Days Until Event -->
            <div class="p-5 stat-card rounded-2xl border border-white/50 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-pink-600">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <span id="stat-event-status" class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">Activ</span>
                </div>
                <div id="stat-days" class="text-2xl font-bold text-gray-900">-</div>
                <div class="mt-1 text-xs text-gray-500">Zile până la eveniment</div>
                <div id="stat-event-date" class="mt-3 text-[11px] text-gray-400">-</div>
            </div>
        </div>

        <!-- Chart + Forecast -->
        <div class="grid gap-6 mb-6 lg:grid-cols-3">
            <!-- Main Chart -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm lg:col-span-2 rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Performanță vânzări</h2>
                        <p class="text-xs text-gray-500">Click pentru a comuta metricile</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleChartMetric('revenue')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all chart-metric-btn active border-emerald-200 bg-emerald-50" data-metric="revenue">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                            <span class="text-xs font-medium text-emerald-600">Venituri</span>
                        </button>
                        <button onclick="toggleChartMetric('tickets')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all chart-metric-btn active border-blue-200 bg-blue-50" data-metric="tickets">
                            <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                            <span class="text-xs font-medium text-blue-600">Bilete</span>
                        </button>
                        <button onclick="toggleChartMetric('views')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all chart-metric-btn border-gray-200" data-metric="views">
                            <div class="w-2.5 h-2.5 rounded-full bg-cyan-500"></div>
                            <span class="text-xs font-medium text-cyan-600">Vizualizări</span>
                        </button>
                    </div>
                </div>
                <div id="mainChart" class="h-[300px]"></div>
            </div>

            <!-- Forecast / Summary -->
            <div id="forecast-card" class="p-6 text-white forecast-card rounded-2xl">
                <div class="flex items-center gap-3 mb-5">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">Estimări</h2>
                        <p class="text-xs text-white/60">Predicții bazate pe trend</p>
                    </div>
                </div>
                <div class="p-4 mb-4 rounded-xl bg-white/10">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-white/80">Următoarele 7 zile</span>
                        <span class="text-xs px-2 py-0.5 bg-emerald-500/30 text-emerald-300 rounded-full">Trend</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div id="forecast-revenue" class="text-xl font-bold">0 lei</div>
                            <div class="text-xs text-white/50">Venituri estimate</div>
                        </div>
                        <div>
                            <div id="forecast-tickets" class="text-xl font-bold">+0</div>
                            <div class="text-xs text-white/50">Bilete estimate</div>
                        </div>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-white/10">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-white/80">La final</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div id="forecast-total-revenue" class="text-xl font-bold">0 lei</div>
                            <div class="text-xs text-white/50">Total estimat</div>
                        </div>
                        <div>
                            <div id="forecast-total-tickets" class="text-xl font-bold">0</div>
                            <div class="text-xs text-white/50">Bilete total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets & Campaign ROI -->
        <div class="grid gap-6 mb-6 lg:grid-cols-3">
            <!-- Ticket Performance Table -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm lg:col-span-2 rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Performanță tipuri bilete</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-3 text-xs font-medium text-left text-gray-500 uppercase">Tip</th>
                                <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Preț</th>
                                <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Vândute</th>
                                <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Venituri</th>
                                <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Conv.</th>
                                <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Trend</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-table">
                            <tr><td colspan="6" class="py-8 text-sm text-center text-gray-400">Se încarcă...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campaign ROI -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Campanii ROI</h2>
                    <button onclick="showAddMilestoneModal()" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                </div>
                <div id="campaigns-list" class="space-y-3">
                    <div class="py-6 text-sm text-center text-gray-400">Nu ai campanii active</div>
                </div>
            </div>
        </div>

        <!-- Traffic Sources & Locations -->
        <div class="grid gap-6 mb-6 lg:grid-cols-2">
            <!-- Traffic Sources -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Surse de trafic</h2>
                <div id="traffic-sources" class="space-y-3"></div>
            </div>

            <!-- Top Locations -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Locații top</h2>
                <div id="locations-list" class="space-y-3"></div>
            </div>
        </div>

        <!-- Recent Sales & Goals -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent Sales -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm lg:col-span-2 rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Vânzări recente</h2>
                    <a href="/organizator/participanti?event=<?= $eventId ?>" class="text-sm font-medium text-primary">Vezi toate →</a>
                </div>
                <div id="recent-sales" class="space-y-3">
                    <div class="py-6 text-sm text-center text-gray-400">Se încarcă...</div>
                </div>
            </div>

            <!-- Goals -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Obiective</h2>
                    <button onclick="showAddGoalModal()" class="text-sm font-medium text-primary">+ Adaugă</button>
                </div>
                <div id="goals-list" class="space-y-4">
                    <div class="py-6 text-sm text-center text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                        Setează obiective pentru a urmări progresul
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Goal Modal -->
<div id="goal-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeGoalModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-border">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Adaugă obiectiv</h2>
                    <button onclick="closeGoalModal()" class="p-2 transition-colors text-muted hover:text-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="goal-form" onsubmit="saveGoal(event)" class="p-6 space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Tip obiectiv</label>
                    <select name="type" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        <option value="revenue">Venituri (RON)</option>
                        <option value="tickets">Bilete vândute</option>
                        <option value="visitors">Vizitatori</option>
                        <option value="conversion_rate">Rată conversie (%)</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Nume obiectiv</label>
                    <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required placeholder="ex: Obiectiv vânzări luna Ianuarie">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Valoare țintă</label>
                    <input type="number" name="target_value" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required min="0" step="any">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Deadline (opțional)</label>
                    <input type="date" name="deadline" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeGoalModal()" class="px-4 py-2 text-sm font-medium transition-colors text-muted hover:bg-surface rounded-xl">Anulează</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-primary hover:bg-primary-dark rounded-xl">Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Milestone Modal -->
<div id="milestone-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeMilestoneModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-border">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Adaugă campanie</h2>
                    <button onclick="closeMilestoneModal()" class="p-2 transition-colors text-muted hover:text-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="milestone-form" onsubmit="saveMilestone(event)" class="p-6 space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Nume campanie</label>
                    <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required placeholder="ex: Campanie Facebook Ads">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Tip</label>
                    <select name="type" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        <option value="facebook_ads">Facebook Ads</option>
                        <option value="google_ads">Google Ads</option>
                        <option value="instagram_ads">Instagram Ads</option>
                        <option value="tiktok_ads">TikTok Ads</option>
                        <option value="email_campaign">Email Campaign</option>
                        <option value="influencer">Influencer</option>
                        <option value="other">Altele</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Data start</label>
                        <input type="date" name="start_date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Data sfârșit</label>
                        <input type="date" name="end_date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Buget (RON)</label>
                    <input type="number" name="budget" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="0.01">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeMilestoneModal()" class="px-4 py-2 text-sm font-medium transition-colors text-muted hover:bg-surface rounded-xl">Anulează</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-primary hover:bg-primary-dark rounded-xl">Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Globe/Map Modal -->
<div id="globe-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" onclick="closeGlobeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-2xl overflow-hidden" onclick="event.stopPropagation()">
            <div class="p-5 border-b border-white/10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/20">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Vizitatori Live</h2>
                            <p class="text-xs text-white/60">Persoane care vizualizează evenimentul acum</p>
                        </div>
                    </div>
                    <button onclick="closeGlobeModal()" class="p-2 transition-colors text-white/60 hover:text-white rounded-lg hover:bg-white/10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-white/5 rounded-xl">
                        <div id="globe-total-visitors" class="text-2xl font-bold text-white">0</div>
                        <div class="text-xs text-white/60">Total online</div>
                    </div>
                    <div class="p-4 bg-white/5 rounded-xl">
                        <div id="globe-countries" class="text-2xl font-bold text-white">0</div>
                        <div class="text-xs text-white/60">Țări</div>
                    </div>
                    <div class="p-4 bg-white/5 rounded-xl">
                        <div id="globe-cities" class="text-2xl font-bold text-white">0</div>
                        <div class="text-xs text-white/60">Orașe</div>
                    </div>
                </div>

                <!-- Map Container -->
                <div class="relative h-80 bg-slate-800 rounded-xl overflow-hidden mb-6">
                    <div id="globe-map" class="w-full h-full"></div>
                    <div id="globe-map-overlay" class="absolute inset-0 flex items-center justify-center bg-slate-800/80">
                        <div class="text-center">
                            <svg class="w-12 h-12 mx-auto mb-3 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="text-white/40 text-sm">Se încarcă vizitatorii...</div>
                        </div>
                    </div>
                </div>

                <!-- Visitors List -->
                <div>
                    <h3 class="text-sm font-semibold text-white mb-3">Locații active</h3>
                    <div id="globe-visitors-list" class="space-y-2 max-h-40 overflow-y-auto">
                        <div class="text-sm text-white/40 text-center py-4">Niciun vizitator activ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const eventId = <?= json_encode($eventId) ?>;
let currentPeriod = '30d';
let mainChart = null;
let chartMetrics = { revenue: true, tickets: true, views: false };
let eventData = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) {
        window.location.href = '/organizator/events';
        return;
    }
    loadAnalytics();
    setupPeriodButtons();
});

function setupPeriodButtons() {
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.period-btn').forEach(b => {
                b.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
                b.classList.add('text-gray-500');
            });
            this.classList.remove('text-gray-500');
            this.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
            currentPeriod = this.dataset.period;
            loadAnalytics();
        });
    });
}

async function loadAnalytics() {
    try {
        const response = await AmbiletAPI.get(`/organizer/events/${eventId}/analytics?period=${currentPeriod}`);
        if (response.success) {
            eventData = response.data;
            updateDashboard(response.data);
        }

        // Load milestones/campaigns
        try {
            const milestonesResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/milestones`);
            if (milestonesResponse.success) {
                updateCampaigns(milestonesResponse.data);
            }
        } catch (e) { console.log('No milestones endpoint'); }

        // Load goals
        try {
            const goalsResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/goals`);
            if (goalsResponse.success) {
                updateGoals(goalsResponse.data);
            }
        } catch (e) { console.log('No goals endpoint'); }
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

function updateDashboard(data) {
    // Event selector
    if (data.event) {
        document.getElementById('event-selector-name').textContent = data.event.title || 'Eveniment';

        // Format date properly (from ISO to DD.MM.YYYY | HH:MM)
        let dateStr = '';
        if (data.event.starts_at) {
            const d = new Date(data.event.starts_at);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            dateStr = `${day}.${month}.${year} | ${hours}:${minutes}`;
        } else if (data.event.date) {
            dateStr = data.event.date;
        }

        // Format venue properly (handle object or string)
        let venueStr = '';
        if (data.event.venue) {
            if (typeof data.event.venue === 'object') {
                venueStr = data.event.venue.name || data.event.venue.title || '';
            } else {
                venueStr = data.event.venue;
            }
        }

        document.getElementById('event-selector-info').textContent = `${dateStr}${venueStr ? ' • ' + venueStr : ''}`;
        if (data.event.image) {
            document.getElementById('event-selector-image').innerHTML = `<img src="${data.event.image}" class="object-cover w-full h-full">`;
        }

        // Days until event
        const daysUntil = data.event.days_until ?? data.overview?.days_until ?? '-';
        document.getElementById('stat-days').textContent = daysUntil;
        document.getElementById('stat-event-date').textContent = data.event.date || '';

        // Status
        let statusText = 'Activ';
        let statusClass = 'bg-emerald-100 text-emerald-700';
        if (data.event.is_cancelled) {
            statusText = 'Anulat';
            statusClass = 'bg-red-100 text-red-700';
        } else if (data.event.is_past || daysUntil === 0 || (typeof daysUntil === 'number' && daysUntil < 0)) {
            statusText = 'Încheiat';
            statusClass = 'bg-gray-100 text-gray-700';
        } else if (data.event.is_sold_out) {
            statusText = 'Sold Out';
            statusClass = 'bg-amber-100 text-amber-700';
        }
        document.getElementById('stat-event-status').textContent = statusText;
        document.getElementById('stat-event-status').className = `px-2 py-1 text-xs font-medium rounded-full ${statusClass}`;
    }

    // Stats
    if (data.overview) {
        const o = data.overview;
        document.getElementById('stat-revenue').textContent = formatCurrency(o.total_revenue || 0);
        document.getElementById('stat-tickets').textContent = formatNumber(o.tickets_sold || 0);
        document.getElementById('stat-views').textContent = formatNumber(o.page_views || 0);
        document.getElementById('stat-conversion').textContent = (o.conversion_rate || 0).toFixed(1) + '%';
        document.getElementById('stat-unique').textContent = formatNumber(o.unique_visitors || 0) + ' unice';
        document.getElementById('stat-tickets-today').textContent = '+' + (o.tickets_today || 0) + ' azi';

        // Progress bars
        const revenueTarget = o.revenue_target || o.total_revenue * 1.5 || 100000;
        const revenuePercent = Math.min((o.total_revenue / revenueTarget) * 100, 100);
        document.getElementById('stat-revenue-bar').style.width = revenuePercent + '%';
        document.getElementById('stat-revenue-percent').textContent = Math.round(revenuePercent) + '%';

        const ticketCapacity = o.capacity || o.tickets_sold * 1.5 || 1000;
        const ticketsPercent = Math.min((o.tickets_sold / ticketCapacity) * 100, 100);
        document.getElementById('stat-tickets-bar').style.width = ticketsPercent + '%';
        document.getElementById('stat-tickets-percent').textContent = Math.round(ticketsPercent) + '%';

        // Revenue change
        updateChangeIndicator('stat-revenue-change', o.revenue_change);

        // Live indicator
        if (o.live_visitors > 0) {
            document.getElementById('live-indicator').classList.remove('hidden');
            document.getElementById('live-indicator').classList.add('flex');
            document.getElementById('live-count').textContent = o.live_visitors + ' online';
        }

        // Forecast
        updateForecast(o);
    }

    // Chart
    if (data.chart) {
        updateMainChart(data.chart);
    }

    // Ticket types
    if (data.ticket_performance) {
        updateTicketTypes(data.ticket_performance);
    }

    // Traffic sources
    if (data.traffic_sources) {
        updateTrafficSources(data.traffic_sources);
    }

    // Locations
    if (data.top_locations) {
        updateLocations(data.top_locations);
    }

    // Recent sales
    if (data.recent_sales) {
        updateRecentSales(data.recent_sales);
    }
}

function updateMainChart(chartData) {
    const colors = [];
    const series = [];
    const yaxisConfigs = [];

    if (chartMetrics.revenue) {
        series.push({ name: 'Venituri', data: chartData.revenue || [] });
        colors.push('#10b981');
        yaxisConfigs.push({
            seriesName: 'Venituri',
            title: { text: 'Venituri (RON)' },
            labels: { formatter: v => formatNumber(v) }
        });
    }
    if (chartMetrics.tickets) {
        series.push({ name: 'Bilete', data: chartData.tickets || [] });
        colors.push('#3b82f6');
        yaxisConfigs.push({
            seriesName: 'Bilete',
            opposite: yaxisConfigs.length > 0,
            title: { text: 'Bilete' },
            labels: { formatter: v => formatNumber(v) }
        });
    }
    if (chartMetrics.views) {
        series.push({ name: 'Vizualizări', data: chartData.views || chartData.page_views || [] });
        colors.push('#06b6d4');
        yaxisConfigs.push({
            seriesName: 'Vizualizări',
            opposite: yaxisConfigs.length > 0,
            title: { text: 'Vizualizări' },
            labels: { formatter: v => formatNumber(v) }
        });
    }

    const options = {
        series: series,
        chart: {
            type: 'area',
            height: 300,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        xaxis: {
            categories: chartData.labels || [],
            labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
        },
        yaxis: yaxisConfigs.length > 0 ? yaxisConfigs : [{}],
        tooltip: {
            shared: true,
            y: {
                formatter: (val, { seriesIndex }) => {
                    const name = series[seriesIndex]?.name || '';
                    if (name === 'Venituri') return formatCurrency(val);
                    if (name === 'Bilete') return formatNumber(val) + ' bilete';
                    if (name === 'Vizualizări') return formatNumber(val) + ' vizualizări';
                    return formatNumber(val);
                }
            }
        },
        colors: colors,
        legend: { position: 'bottom', horizontalAlign: 'center' }
    };

    if (mainChart) {
        mainChart.destroy();
    }
    mainChart = new ApexCharts(document.getElementById('mainChart'), options);
    mainChart.render();
}

function toggleChartMetric(metric) {
    chartMetrics[metric] = !chartMetrics[metric];
    const btn = document.querySelector(`.chart-metric-btn[data-metric="${metric}"]`);

    // Get appropriate colors for each metric
    const activeStyles = {
        revenue: { border: 'border-emerald-200', bg: 'bg-emerald-50' },
        tickets: { border: 'border-blue-200', bg: 'bg-blue-50' },
        views: { border: 'border-cyan-200', bg: 'bg-cyan-50' }
    };
    const style = activeStyles[metric] || { border: 'border-gray-200', bg: '' };

    if (chartMetrics[metric]) {
        btn.classList.add('active', style.border, style.bg);
        btn.classList.remove('border-gray-200');
        btn.style.opacity = '1';
    } else {
        btn.classList.remove('active', style.border, style.bg);
        btn.classList.add('border-gray-200');
        btn.style.opacity = '0.5';
    }
    if (eventData?.chart) {
        updateMainChart(eventData.chart);
    }
}

function updateForecast(overview) {
    // Simple forecast based on daily average
    const avgDailyRevenue = overview.total_revenue / 30 || 0;
    const avgDailyTickets = overview.tickets_sold / 30 || 0;
    const daysRemaining = overview.days_until || 7;

    document.getElementById('forecast-revenue').textContent = formatCurrency(avgDailyRevenue * 7);
    document.getElementById('forecast-tickets').textContent = '+' + Math.round(avgDailyTickets * 7);
    document.getElementById('forecast-total-revenue').textContent = formatCurrency(overview.total_revenue + avgDailyRevenue * daysRemaining);
    document.getElementById('forecast-total-tickets').textContent = formatNumber(overview.tickets_sold + Math.round(avgDailyTickets * daysRemaining));
}

function updateTicketTypes(tickets) {
    const tbody = document.getElementById('ticket-types-table');
    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-sm text-center text-gray-400">Nu există tipuri de bilete</td></tr>';
        return;
    }

    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'];
    const html = tickets.map((t, i) => `
        <tr class="border-b border-gray-50">
            <td class="py-3">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-6 rounded-full" style="background: ${colors[i % colors.length]}"></div>
                    <span class="text-sm font-medium text-gray-800">${t.name}</span>
                </div>
            </td>
            <td class="py-3 text-sm text-right text-gray-600">${formatCurrency(t.price)}</td>
            <td class="py-3 text-sm font-semibold text-right text-gray-900">${formatNumber(t.sold || 0)}</td>
            <td class="py-3 text-sm font-semibold text-right text-gray-900">${formatCurrency(t.revenue || t.price * (t.sold || 0))}</td>
            <td class="py-3 text-sm font-semibold text-right ${(t.conversion_rate || 0) >= 4 ? 'text-emerald-600' : 'text-gray-600'}">${(t.conversion_rate || 0).toFixed(1)}%</td>
            <td class="py-3 text-right">
                <span class="text-xs font-medium ${(t.trend || 0) >= 0 ? 'text-emerald-600' : 'text-red-600'}">
                    ${(t.trend || 0) >= 0 ? '↑' : '↓'}${Math.abs(t.trend || 0)}%
                </span>
            </td>
        </tr>
    `).join('');
    tbody.innerHTML = html;
}

function updateTrafficSources(sources) {
    const container = document.getElementById('traffic-sources');
    if (!sources || sources.length === 0) {
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu există date despre trafic</div>';
        return;
    }

    const icons = {
        'Facebook': '📘', 'Google': '🔍', 'Instagram': '📷', 'TikTok': '🎵',
        'Direct': '🔗', 'Email': '📧', 'Other': '🌐'
    };
    const colors = {
        'Facebook': '#1877f2', 'Google': '#ea4335', 'Instagram': '#e4405f',
        'TikTok': '#000000', 'Direct': '#6b7280', 'Email': '#f59e0b', 'Other': '#10b981'
    };

    const total = sources.reduce((sum, s) => sum + (s.visitors || 0), 0);

    const html = sources.map(s => {
        const percent = total > 0 ? Math.round((s.visitors / total) * 100) : 0;
        const color = colors[s.source] || colors['Other'];
        const icon = icons[s.source] || icons['Other'];
        return `
        <div class="flex items-center gap-4 p-3 transition-colors rounded-xl hover:bg-gray-50">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl" style="background: ${color}22">
                <span class="text-lg">${icon}</span>
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-800">${s.source || 'Direct'}</span>
                    <span class="text-sm font-semibold text-gray-900">${formatNumber(s.visitors)}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 overflow-hidden bg-gray-100 rounded-full">
                        <div class="h-full rounded-full" style="width: ${percent}%; background: ${color}"></div>
                    </div>
                    <span class="text-xs text-gray-400 w-10">${percent}%</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-medium text-gray-700">${formatCurrency(s.revenue || 0)}</div>
                <div class="text-xs text-gray-400">${s.conversions || 0} vânzări</div>
            </div>
        </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateLocations(locations) {
    const container = document.getElementById('locations-list');
    if (!locations || locations.length === 0) {
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu există date despre locații</div>';
        return;
    }

    const html = locations.slice(0, 6).map((l, i) => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-6 h-6 text-xs font-semibold text-gray-600 rounded-lg bg-gray-100">${i + 1}</span>
                <span class="text-sm font-medium text-gray-800">${l.city || l.country || 'Necunoscut'}</span>
            </div>
            <div class="text-right">
                <span class="text-sm font-semibold text-gray-900">${formatNumber(l.visitors || l.count || 0)}</span>
                <span class="text-xs text-gray-400 ml-1">vizite</span>
            </div>
        </div>
    `).join('');
    container.innerHTML = html;
}

function updateCampaigns(data) {
    const container = document.getElementById('campaigns-list');
    const campaigns = data.milestones || data;

    if (!campaigns || campaigns.length === 0) {
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu ai campanii active</div>';
        return;
    }

    const html = campaigns.map(c => {
        const roi = c.budget > 0 ? Math.round(((c.attributed_revenue || 0) - c.budget) / c.budget * 100) : 0;
        return `
        <div class="p-3 transition-colors border border-gray-100 rounded-xl hover:border-violet-200 milestone-card">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span>${c.type_icon || '📌'}</span>
                    <span class="text-sm font-medium text-gray-800">${c.title || c.name || 'Campanie'}</span>
                </div>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full ${c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}">
                    ${c.is_active ? 'Activ' : 'Încheiat'}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div><span class="text-gray-500">Buget:</span> <span class="font-medium">${formatCurrency(c.budget || 0)}</span></div>
                <div><span class="text-gray-500">Venituri:</span> <span class="font-medium text-emerald-600">${formatCurrency(c.attributed_revenue || 0)}</span></div>
                <div><span class="text-gray-500">Conv.:</span> <span class="font-medium">${c.conversions || 0}</span></div>
                <div><span class="text-gray-500">ROI:</span> <span class="font-semibold ${roi >= 0 ? 'text-emerald-600' : 'text-red-600'}">${roi >= 0 ? '+' : ''}${roi}%</span></div>
            </div>
        </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateRecentSales(sales) {
    const container = document.getElementById('recent-sales');
    if (!sales || sales.length === 0) {
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu există vânzări recente</div>';
        return;
    }

    const html = sales.slice(0, 8).map(s => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 font-semibold text-white rounded-full bg-gradient-to-br from-violet-400 to-purple-600 text-sm">
                    ${(s.buyer_name || 'C').charAt(0).toUpperCase()}
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-800">${s.buyer_name || 'Client'}</p>
                    <p class="text-xs text-gray-500">${s.time_ago || s.created_at || ''}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-emerald-600">+${formatCurrency(s.amount || 0)}</p>
                <p class="text-xs text-gray-500">${s.tickets || 1} bilet(e)</p>
            </div>
        </div>
    `).join('');
    container.innerHTML = html;
}

function updateGoals(data) {
    const container = document.getElementById('goals-list');
    const goals = data.goals || data;

    if (!goals || goals.length === 0) {
        container.innerHTML = `
            <div class="py-6 text-sm text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                Setează obiective pentru a urmări progresul
            </div>
        `;
        return;
    }

    const typeLabels = { 'revenue': 'Venituri', 'tickets': 'Bilete', 'visitors': 'Vizitatori', 'conversion_rate': 'Conversie' };
    const html = goals.map(g => {
        const progress = Math.min(g.progress_percent || 0, 100);
        const progressColor = progress >= 100 ? 'bg-emerald-500' : progress >= 75 ? 'bg-blue-500' : progress >= 50 ? 'bg-amber-500' : 'bg-red-400';
        return `
        <div class="p-4 border rounded-xl border-gray-100">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-800">${g.name || typeLabels[g.type] || g.type}</span>
            </div>
            <div class="flex items-baseline gap-1 mb-2">
                <span class="text-xl font-bold text-gray-900">${g.formatted_current || 0}</span>
                <span class="text-sm text-gray-500">/ ${g.formatted_target || 0}</span>
            </div>
            <div class="h-2 overflow-hidden bg-gray-100 rounded-full">
                <div class="h-full transition-all rounded-full ${progressColor}" style="width: ${progress}%"></div>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-xs text-gray-500">${progress.toFixed(1)}%</span>
                ${g.days_remaining > 0 ? `<span class="text-xs text-gray-500">${g.days_remaining} zile rămase</span>` : ''}
            </div>
        </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateChangeIndicator(elementId, change) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const value = parseFloat(change) || 0;
    const isPositive = value >= 0;
    el.innerHTML = `
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isPositive ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3'}"/>
        </svg>
        <span>${isPositive ? '+' : ''}${value.toFixed(1)}%</span>
    `;
    el.className = `flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full ${isPositive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value || 0) + ' lei';
}

function formatNumber(value) {
    return new Intl.NumberFormat('ro-RO').format(value || 0);
}

function exportReport() {
    window.open(`/api/marketplace-client/organizer/events/${eventId}/analytics/export?period=${currentPeriod}`, '_blank');
}

// Event dropdown functions
let eventDropdownOpen = false;
let eventsListLoaded = false;

function toggleEventDropdown() {
    const menu = document.getElementById('event-dropdown-menu');
    const arrow = document.getElementById('event-dropdown-arrow');
    eventDropdownOpen = !eventDropdownOpen;

    if (eventDropdownOpen) {
        menu.classList.remove('hidden');
        arrow.classList.add('rotate-180');
        if (!eventsListLoaded) {
            loadEventsList();
        }
    } else {
        menu.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
}

async function loadEventsList() {
    try {
        const response = await AmbiletAPI.get('/organizer/events?per_page=50');
        if (response.success) {
            const events = response.data?.events || response.data || [];
            renderEventsList(events);
            eventsListLoaded = true;
        }
    } catch (error) {
        console.error('Error loading events:', error);
        document.getElementById('event-dropdown-loading').innerHTML = '<div class="py-4 text-sm text-center text-red-500">Eroare la încărcare</div>';
    }
}

function renderEventsList(events) {
    const loadingEl = document.getElementById('event-dropdown-loading');
    const listEl = document.getElementById('event-dropdown-list');

    loadingEl.classList.add('hidden');
    listEl.classList.remove('hidden');

    if (!events || events.length === 0) {
        listEl.innerHTML = '<div class="py-4 text-sm text-center text-gray-400">Nu ai evenimente</div>';
        return;
    }

    const html = events.map(e => {
        const isActive = String(e.id) === String(eventId);

        // Format date
        let dateStr = '';
        if (e.starts_at) {
            const d = new Date(e.starts_at);
            dateStr = `${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}.${d.getFullYear()}`;
        } else if (e.date) {
            dateStr = e.date;
        }

        // Get venue name
        let venueName = '';
        if (e.venue) {
            venueName = typeof e.venue === 'object' ? (e.venue.name || '') : e.venue;
        }

        return `
            <a href="/organizator/analytics/${e.id}" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 ${isActive ? 'bg-primary/5 border-l-2 border-primary' : ''}">
                <div class="flex-shrink-0 w-10 h-10 overflow-hidden bg-gray-200 rounded-lg">
                    ${e.image ? `<img src="${e.image}" class="object-cover w-full h-full">` : ''}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-800 truncate">${e.title || 'Eveniment'}</div>
                    <div class="text-[11px] text-gray-500 truncate">${dateStr}${venueName ? ' • ' + venueName : ''}</div>
                </div>
                ${isActive ? '<svg class="flex-shrink-0 w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : ''}
            </a>
        `;
    }).join('');

    listEl.innerHTML = html;
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('event-dropdown-container');
    if (container && !container.contains(e.target) && eventDropdownOpen) {
        toggleEventDropdown();
    }
});

// Globe modal functions
let globeModalOpen = false;
let liveVisitorsData = null;

function openGlobeModal() {
    document.getElementById('globe-modal').classList.remove('hidden');
    globeModalOpen = true;
    loadLiveVisitors();
}

function closeGlobeModal() {
    document.getElementById('globe-modal').classList.add('hidden');
    globeModalOpen = false;
}

async function loadLiveVisitors() {
    const overlay = document.getElementById('globe-map-overlay');

    try {
        // Try to get live visitors data from analytics
        // For now we'll simulate with top locations data
        if (eventData?.top_locations) {
            const locations = eventData.top_locations;
            renderGlobeData(locations);
        } else {
            // Fallback to showing the analytics data
            const response = await AmbiletAPI.get(`/organizer/events/${eventId}/analytics?period=1d`);
            if (response.success && response.data?.top_locations) {
                renderGlobeData(response.data.top_locations);
            }
        }
    } catch (error) {
        console.error('Error loading live visitors:', error);
        overlay.innerHTML = '<div class="text-red-400 text-sm">Eroare la încărcare</div>';
    }
}

function renderGlobeData(locations) {
    const overlay = document.getElementById('globe-map-overlay');
    const mapContainer = document.getElementById('globe-map');
    const visitorsList = document.getElementById('globe-visitors-list');

    if (!locations || locations.length === 0) {
        overlay.innerHTML = `
            <div class="text-center">
                <svg class="w-12 h-12 mx-auto mb-3 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div class="text-white/40 text-sm">Niciun vizitator activ în acest moment</div>
            </div>
        `;
        return;
    }

    // Calculate totals
    const totalVisitors = locations.reduce((sum, l) => sum + (l.visitors || l.count || 0), 0);
    const uniqueCountries = [...new Set(locations.map(l => l.country || 'RO'))].length;
    const uniqueCities = locations.length;

    document.getElementById('globe-total-visitors').textContent = formatNumber(totalVisitors);
    document.getElementById('globe-countries').textContent = uniqueCountries;
    document.getElementById('globe-cities').textContent = uniqueCities;

    // Render simple map visualization
    overlay.classList.add('hidden');
    mapContainer.innerHTML = renderSimpleMap(locations);

    // Render visitors list
    const html = locations.slice(0, 10).map(l => {
        const count = l.visitors || l.count || 0;
        const percent = totalVisitors > 0 ? Math.round((count / totalVisitors) * 100) : 0;
        return `
            <div class="flex items-center justify-between p-2 bg-white/5 rounded-lg">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                    <span class="text-sm text-white">${l.city || l.country || 'Necunoscut'}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-16 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: ${percent}%"></div>
                    </div>
                    <span class="text-xs text-white/60 w-8 text-right">${count}</span>
                </div>
            </div>
        `;
    }).join('');
    visitorsList.innerHTML = html || '<div class="text-sm text-white/40 text-center py-4">Niciun vizitator activ</div>';
}

function renderSimpleMap(locations) {
    // Simple visual representation with dots on a stylized map background
    const maxVisitors = Math.max(...locations.map(l => l.visitors || l.count || 0));

    // Romanian cities approximate positions (normalized 0-100)
    const cityPositions = {
        'București': { x: 65, y: 55 },
        'Bucharest': { x: 65, y: 55 },
        'Cluj-Napoca': { x: 45, y: 30 },
        'Cluj': { x: 45, y: 30 },
        'Timișoara': { x: 25, y: 45 },
        'Timisoara': { x: 25, y: 45 },
        'Iași': { x: 80, y: 25 },
        'Iasi': { x: 80, y: 25 },
        'Constanța': { x: 85, y: 55 },
        'Constanta': { x: 85, y: 55 },
        'Brașov': { x: 55, y: 45 },
        'Brasov': { x: 55, y: 45 },
        'Sibiu': { x: 45, y: 45 },
        'Oradea': { x: 25, y: 25 },
        'Craiova': { x: 40, y: 60 },
        'Galați': { x: 80, y: 45 },
        'Galati': { x: 80, y: 45 },
        'Ploiești': { x: 60, y: 50 },
        'Ploiesti': { x: 60, y: 50 },
        'Arad': { x: 22, y: 35 },
        'Pitești': { x: 50, y: 55 },
        'Pitesti': { x: 50, y: 55 },
    };

    const dots = locations.map((l, i) => {
        const city = l.city || '';
        const count = l.visitors || l.count || 0;
        const size = Math.max(8, Math.min(24, (count / maxVisitors) * 24));

        // Get position or generate random position
        let pos = cityPositions[city];
        if (!pos) {
            // Random position for unknown cities
            pos = { x: 20 + Math.random() * 60, y: 20 + Math.random() * 60 };
        }

        return `
            <div class="absolute animate-pulse" style="left: ${pos.x}%; top: ${pos.y}%; transform: translate(-50%, -50%);">
                <div class="relative">
                    <div class="absolute inset-0 bg-emerald-400 rounded-full opacity-30 animate-ping" style="width: ${size * 2}px; height: ${size * 2}px; margin: -${size/2}px;"></div>
                    <div class="bg-emerald-500 rounded-full shadow-lg shadow-emerald-500/50" style="width: ${size}px; height: ${size}px;"></div>
                </div>
                <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 whitespace-nowrap text-[10px] text-white/80 font-medium">${city || 'Loc. ' + (i+1)}</div>
            </div>
        `;
    }).join('');

    return `
        <div class="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900 opacity-50"></div>
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><path fill=\"none\" stroke=\"%23ffffff10\" stroke-width=\"0.5\" d=\"M10,20 Q30,10 50,20 T90,25 M5,50 Q25,40 50,50 T95,55 M10,80 Q30,70 50,80 T90,75\"/></svg>'); background-size: cover;"></div>
        <div class="absolute inset-4">
            ${dots}
        </div>
    `;
}

// Goal modal functions
function showAddGoalModal() { document.getElementById('goal-modal').classList.remove('hidden'); }
function closeGoalModal() { document.getElementById('goal-modal').classList.add('hidden'); document.getElementById('goal-form').reset(); }

async function saveGoal(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        type: form.type.value,
        name: form.name.value,
        target_value: parseFloat(form.target_value.value),
        deadline: form.deadline.value || null
    };
    try {
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/goals`, data);
        if (response.success) { closeGoalModal(); loadAnalytics(); }
    } catch (error) { console.error('Error saving goal:', error); alert('Eroare la salvarea obiectivului'); }
}

// Milestone modal functions
function showAddMilestoneModal() { document.getElementById('milestone-modal').classList.remove('hidden'); }
function closeMilestoneModal() { document.getElementById('milestone-modal').classList.add('hidden'); document.getElementById('milestone-form').reset(); }

async function saveMilestone(e) {
    e.preventDefault();
    const form = e.target;

    // Map form type values to API type values
    const typeMap = {
        'facebook_ads': 'campaign_fb',
        'google_ads': 'campaign_google',
        'instagram_ads': 'campaign_instagram',
        'tiktok_ads': 'campaign_tiktok',
        'email_campaign': 'email',
        'influencer': 'campaign_other',
        'other': 'custom'
    };

    const data = {
        title: form.name.value,
        type: typeMap[form.type.value] || form.type.value,
        start_date: form.start_date.value,
        end_date: form.end_date.value || null,
        budget: form.budget.value ? parseFloat(form.budget.value) : null
    };
    try {
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/milestones`, data);
        if (response.success) { closeMilestoneModal(); loadAnalytics(); }
    } catch (error) { console.error('Error saving milestone:', error); alert('Eroare la salvarea campaniei'); }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
