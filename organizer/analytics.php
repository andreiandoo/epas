<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Analytics Eveniment';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = '
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%); backdrop-filter: blur(10px); }
    .forecast-card { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); }
    .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2); opacity: 0; } }
    .milestone-card { transition: all 0.2s ease; }
    .milestone-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .leaflet-container { background: #1e293b !important; font-size: 14px; }
    .leaflet-pane { z-index: 1 !important; }
    .leaflet-tile-pane { z-index: 1 !important; }
    .leaflet-overlay-pane { z-index: 2 !important; }
    .leaflet-marker-pane { z-index: 3 !important; }
    .leaflet-popup-pane { z-index: 4 !important; }
    .live-marker-pulse { animation: marker-pulse 2s ease-in-out infinite; }
    @keyframes marker-pulse { 0%, 100% { opacity: 0.8; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
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

                    <!-- Live Indicator -->
                    <div id="live-indicator" class="items-center hidden gap-2 px-3 py-2 border border-emerald-200 bg-emerald-50 rounded-xl">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex w-full h-full rounded-full opacity-75 pulse-ring bg-emerald-400"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span id="live-count" class="text-sm font-medium text-emerald-700">0 online</span>
                    </div>

                    <!-- Globe Button (always visible) -->
                    <button onclick="openGlobeModal()" class="flex items-center gap-2 px-3 py-2 text-sm font-medium border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors" title="Vezi trafic live pe hartă">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="hidden sm:inline">Hartă</span>
                    </button>

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
                        <button onclick="toggleChartMetric('views')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all chart-metric-btn active border-cyan-200 bg-cyan-50" data-metric="views">
                            <div class="w-2.5 h-2.5 rounded-full bg-cyan-500"></div>
                            <span class="text-xs font-medium text-cyan-600">Vizualizări</span>
                        </button>
                    </div>
                </div>
                <div id="mainChart" class="h-[300px]"></div>
                <!-- Milestone Tooltip -->
                <div id="milestone-tooltip" class="fixed z-50 hidden max-w-xs p-4 bg-white border border-gray-200 shadow-xl rounded-xl">
                    <div class="flex items-start gap-3">
                        <div id="milestone-tooltip-icon" class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg"></div>
                        <div class="flex-1 min-w-0">
                            <div id="milestone-tooltip-type" class="text-xs font-medium text-gray-500 uppercase tracking-wide"></div>
                            <div id="milestone-tooltip-title" class="text-sm font-semibold text-gray-900 truncate"></div>
                        </div>
                    </div>
                    <div id="milestone-tooltip-details" class="mt-3 space-y-2 text-sm">
                        <!-- Dynamic content -->
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div id="milestone-tooltip-dates" class="text-xs text-gray-500"></div>
                    </div>
                </div>
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
                    <input type="date" name="deadline" id="goal-deadline-input" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    <p id="goal-deadline-hint" class="mt-1 text-xs text-muted"></p>
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
    <div class="fixed inset-0 flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-lg bg-white shadow-2xl rounded-2xl my-8" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-border">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Adaugă campanie</h2>
                    <button onclick="closeMilestoneModal()" class="p-2 transition-colors text-muted hover:text-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="milestone-form" onsubmit="saveMilestone(event)" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Nume campanie</label>
                    <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required placeholder="ex: Campanie Facebook Ads">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Tip</label>
                    <select name="type" onchange="updateMilestoneFields(this.value)" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        <option value="facebook_ads">Facebook Ads</option>
                        <option value="google_ads">Google Ads</option>
                        <option value="instagram_ads">Instagram Ads</option>
                        <option value="tiktok_ads">TikTok Ads</option>
                        <option value="email_campaign">Email Campaign</option>
                        <option value="price_change">Schimbare Preț</option>
                        <option value="announcement">Anunț</option>
                        <option value="press">Comunicat de Presă</option>
                        <option value="lineup">Update Lineup</option>
                        <option value="influencer">Influencer</option>
                        <option value="other">Altele</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Data start</label>
                        <input type="date" name="start_date" id="milestone-start-date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Data sfârșit</label>
                        <input type="date" name="end_date" id="milestone-end-date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>
                <p id="milestone-date-hint" class="text-xs text-muted"></p>

                <!-- Ad Campaign Fields (shown for ad types) -->
                <div id="milestone-ad-fields" class="space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Buget (RON)</label>
                        <input type="number" name="budget" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">ID Campanie platformă</label>
                        <input type="text" name="platform_campaign_id" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="ex: 120215478965421">
                    </div>
                    <div class="p-4 border rounded-xl border-border bg-gray-50">
                        <h4 class="mb-3 text-sm font-medium text-secondary">Parametri UTM</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-xs text-muted">UTM Source</label>
                                <input type="text" name="utm_source" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="facebook">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs text-muted">UTM Medium</label>
                                <input type="text" name="utm_medium" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="cpc">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs text-muted">UTM Campaign</label>
                                <input type="text" name="utm_campaign" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="summer-sale">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs text-muted">UTM Content</label>
                                <input type="text" name="utm_content" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="banner-1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Non-Ad Campaign Fields (shown for email, price, announcement, etc.) -->
                <div id="milestone-other-fields" class="hidden space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Descriere</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Descrieți campania sau acțiunea..."></textarea>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Metrică de impact</label>
                        <select name="impact_metric" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="">Selectează metrica</option>
                            <option value="tickets_sold">Bilete vândute</option>
                            <option value="page_views">Vizualizări pagină</option>
                            <option value="revenue">Venituri</option>
                            <option value="conversion_rate">Rata conversie</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-secondary">Valoare inițială</label>
                            <input type="number" name="baseline_value" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="0.01" placeholder="0">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-secondary">Valoare după</label>
                            <input type="number" name="post_value" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border">
                    <button type="button" onclick="closeMilestoneModal()" class="px-4 py-2 text-sm font-medium transition-colors text-muted hover:bg-surface rounded-xl">Anulează</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-primary hover:bg-primary-dark rounded-xl">Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Globe/Map Modal - Full screen like core.tixello.com -->
<div id="globe-modal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" onclick="closeGlobeModal()"></div>
    <div class="fixed top-4 left-4 right-4 bottom-4 bg-slate-50 rounded-3xl overflow-hidden shadow-2xl" style="z-index: 100000;">
        <!-- Full-screen Map Container -->
        <div id="globeMapContainer" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; min-height: 400px; background: #f8fafc;"></div>

        <!-- Header Overlay -->
        <div class="absolute top-0 left-0 right-0 p-6 bg-gradient-to-b from-slate-50 via-slate-50/80 to-transparent pointer-events-none" style="z-index: 1000;">
            <div class="flex items-center justify-between pointer-events-auto">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"></path>
                        </svg>
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
                        <span id="globe-live-count" class="text-lg font-bold text-slate-800">0</span>
                        <span class="text-sm text-slate-500">online now</span>
                    </div>
                    <button onclick="closeGlobeModal()" class="p-3 bg-white hover:bg-slate-100 rounded-xl shadow-sm border border-slate-200 transition-colors">
                        <svg class="w-5 h-5 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Live Activity Panel (Bottom Left) -->
        <div class="absolute bottom-6 left-6 w-80 bg-white/95 backdrop-blur-xl rounded-2xl shadow-lg border border-slate-200 overflow-hidden" style="z-index: 1000;">
            <div class="p-4 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-800">Live Activity</span>
                    <span class="text-xs text-slate-400">Last 5 minutes</span>
                </div>
            </div>
            <div id="globe-live-activity" class="p-2 max-h-64 overflow-y-auto">
                <div class="flex items-center gap-3 p-2 rounded-lg">
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-lg">🇷🇴</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-slate-800 truncate">No live visitors</div>
                        <div class="text-xs text-slate-400">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Locations Panel (Bottom Right) -->
        <div class="absolute bottom-6 right-6 w-64 bg-white/95 backdrop-blur-xl rounded-2xl shadow-lg border border-slate-200 p-4" style="z-index: 1000;">
            <div class="text-sm font-semibold text-slate-800 mb-3">Top Locations</div>
            <div id="globe-top-locations" class="space-y-2">
                <!-- Will be populated by JS -->
            </div>
        </div>
    </div>
</div>

<script>
const eventId = <?= json_encode($eventId) ?>;
let currentPeriod = '30d';
let mainChart = null;
let chartMetrics = { revenue: true, tickets: true, views: true };
let eventData = null;
let milestonesData = [];
let globeMap = null;

// Helper to fix image URLs - use core.tixello.com URLs directly
function fixImageUrl(url) {
    if (!url) return '';
    // If it's a relative path (like /storage/...), prepend the core domain
    if (url.startsWith('/storage/') || url.startsWith('storage/')) {
        return 'https://core.tixello.com' + (url.startsWith('/') ? '' : '/') + url;
    }
    // If it's events/posters path, it's from core storage
    if (url.includes('events/posters/') && !url.includes('://')) {
        return 'https://core.tixello.com/storage/' + url;
    }
    // Already a full URL, return as-is
    return url;
}

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
                milestonesData = milestonesResponse.data.milestones || milestonesResponse.data || [];
                updateCampaigns(milestonesResponse.data);
                // Re-render chart with milestone annotations
                if (eventData?.chart) {
                    updateMainChart(eventData.chart);
                }
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
        const dateSource = data.event.starts_at || data.event.start_date || data.event.date_start || data.event.date;
        if (dateSource) {
            const d = new Date(dateSource);
            if (!isNaN(d.getTime())) {
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const year = d.getFullYear();
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                dateStr = `${day}.${month}.${year} | ${hours}:${minutes}`;
            } else if (typeof dateSource === 'string') {
                // Try to parse date string like "2025-01-30"
                const match = dateSource.match(/(\d{4})-(\d{2})-(\d{2})/);
                if (match) {
                    dateStr = `${match[3]}.${match[2]}.${match[1]}`;
                } else {
                    dateStr = dateSource;
                }
            }
        }

        // Format venue properly (handle object or string)
        let venueStr = '';
        let cityStr = '';
        if (data.event.venue) {
            if (typeof data.event.venue === 'object') {
                venueStr = data.event.venue.name || data.event.venue.title || '';
                cityStr = data.event.venue.city || '';
            } else {
                venueStr = data.event.venue;
            }
        }
        // Also check for city at event level
        if (!cityStr && data.event.city) {
            cityStr = data.event.city;
        }
        // Build location string
        let locationStr = venueStr;
        if (cityStr && cityStr !== venueStr) {
            locationStr = venueStr ? `${venueStr}, ${cityStr}` : cityStr;
        }

        document.getElementById('event-selector-info').textContent = `${dateStr}${locationStr ? ' • ' + locationStr : ''}`;
        if (data.event.image) {
            document.getElementById('event-selector-image').innerHTML = `<img src="${fixImageUrl(data.event.image)}" class="object-cover w-full h-full">`;
        }

        // Days until event
        let daysUntil = data.event.days_until ?? data.overview?.days_until;
        // Calculate days until if not provided
        if (daysUntil === undefined || daysUntil === null) {
            const eventDateSource = data.event.starts_at || data.event.start_date || data.event.date_start || data.event.date;
            if (eventDateSource) {
                const eventDate = new Date(eventDateSource);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                eventDate.setHours(0, 0, 0, 0);
                daysUntil = Math.ceil((eventDate - today) / (1000 * 60 * 60 * 24));
            }
        }
        document.getElementById('stat-days').textContent = daysUntil !== undefined && daysUntil !== null ? daysUntil : '-';
        document.getElementById('stat-event-date').textContent = dateStr || data.event.date || '';

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

// Get milestone type-specific color
function getMilestoneColor(type) {
    const colors = {
        'campaign_fb': '#1877f2',
        'campaign_google': '#ea4335',
        'campaign_tiktok': '#000000',
        'campaign_instagram': '#e4405f',
        'campaign_other': '#6b7280',
        'email': '#f59e0b',
        'price': '#10b981',
        'announcement': '#8b5cf6',
        'press': '#3b82f6',
        'lineup': '#ec4899',
        'custom': '#6b7280',
    };
    return colors[type] || '#6b7280';
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

    // Build milestone annotations for the chart
    const rawDates = chartData.raw_dates || [];
    const labels = chartData.labels || [];
    const milestoneAnnotations = (milestonesData || [])
        .filter(m => m.start_date)
        .map(m => {
            const milestoneDate = m.start_date.split('T')[0]; // "2026-01-14"
            const dateIndex = rawDates.findIndex(d => d === milestoneDate);
            if (dateIndex === -1) return null;

            const color = getMilestoneColor(m.type);

            return {
                x: labels[dateIndex],
                borderColor: color,
                borderWidth: 2,
                strokeDashArray: 0,
                label: {
                    borderColor: color,
                    borderWidth: 0,
                    borderRadius: 6,
                    style: {
                        color: '#fff',
                        background: color,
                        fontSize: '10px',
                        fontWeight: 600,
                        padding: { left: 8, right: 8, top: 4, bottom: 4 }
                    },
                    text: (m.title?.substring(0, 18) || '') + (m.title?.length > 18 ? '...' : ''),
                    position: 'top',
                    offsetY: -8
                }
            };
        })
        .filter(Boolean);

    // Store milestone data indexed by label text for tooltip lookup
    const milestoneLabelMap = {};
    (milestonesData || []).forEach(m => {
        if (m.start_date) {
            const shortTitle = (m.title?.substring(0, 18) || '') + (m.title?.length > 18 ? '...' : '');
            milestoneLabelMap[shortTitle] = m;
        }
    });
    window.milestoneLabelMap = milestoneLabelMap;

    const options = {
        series: series,
        chart: {
            type: 'area',
            height: 300,
            toolbar: { show: false },
            zoom: { enabled: false },
            events: {
                mounted: function() {
                    // Add click listeners to annotation labels after chart renders
                    setupMilestoneAnnotationListeners();
                },
                updated: function() {
                    setupMilestoneAnnotationListeners();
                }
            }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        annotations: {
            xaxis: milestoneAnnotations
        },
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

// Setup click/hover listeners for milestone annotations in the chart
function setupMilestoneAnnotationListeners() {
    setTimeout(() => {
        const chartEl = document.getElementById('mainChart');
        if (!chartEl) return;

        // Find all annotation label elements (they are in <g> elements with class 'apexcharts-xaxis-annotations')
        const annotationLabels = chartEl.querySelectorAll('.apexcharts-xaxis-annotations text, .apexcharts-xaxis-annotations rect');

        annotationLabels.forEach(el => {
            // Get the label text from nearby text element
            let labelText = '';
            if (el.tagName === 'text') {
                labelText = el.textContent;
            } else if (el.tagName === 'rect') {
                // Find sibling text element
                const parent = el.parentElement;
                const textEl = parent?.querySelector('text');
                labelText = textEl?.textContent || '';
            }

            // Add hover and click events
            el.style.cursor = 'pointer';
            el.addEventListener('mouseenter', (e) => showMilestoneTooltip(e, labelText));
            el.addEventListener('mouseleave', hideMilestoneTooltip);
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                showMilestoneTooltip(e, labelText, true);
            });
        });
    }, 100);
}

// Show milestone tooltip
function showMilestoneTooltip(event, labelText, sticky = false) {
    const milestone = window.milestoneLabelMap?.[labelText];
    if (!milestone) return;

    const tooltip = document.getElementById('milestone-tooltip');
    if (!tooltip) return;

    // Populate tooltip content
    const typeLabels = {
        'campaign_fb': 'Facebook Ads',
        'campaign_google': 'Google Ads',
        'campaign_tiktok': 'TikTok Ads',
        'campaign_instagram': 'Instagram Ads',
        'campaign_other': 'Alte Ads',
        'email': 'Email Marketing',
        'price': 'Schimbare Preț',
        'announcement': 'Anunț',
        'press': 'Comunicat Presă',
        'lineup': 'Anunț Lineup',
        'custom': 'Personalizat'
    };

    const typeIcons = {
        'campaign_fb': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'campaign_google': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
        'email': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        'price': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'announcement': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>'
    };

    const color = getMilestoneColor(milestone.type);

    document.getElementById('milestone-tooltip-type').textContent = typeLabels[milestone.type] || milestone.type;
    document.getElementById('milestone-tooltip-title').textContent = milestone.title || '';
    document.getElementById('milestone-tooltip-icon').innerHTML = typeIcons[milestone.type] || typeIcons['announcement'];
    document.getElementById('milestone-tooltip-icon').style.background = color + '20';
    document.getElementById('milestone-tooltip-icon').style.color = color;

    // Build details
    let detailsHtml = '';
    if (milestone.budget && milestone.budget > 0) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">Buget:</span><span class="font-medium">${formatCurrency(milestone.budget)}</span></div>`;
    }
    if (milestone.impressions) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">Impresii:</span><span class="font-medium">${formatNumber(milestone.impressions)}</span></div>`;
    }
    if (milestone.clicks) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">Click-uri:</span><span class="font-medium">${formatNumber(milestone.clicks)}</span></div>`;
    }
    if (milestone.conversions) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">Conversii:</span><span class="font-medium">${formatNumber(milestone.conversions)}</span></div>`;
    }
    if (milestone.attributed_revenue && milestone.attributed_revenue > 0) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">Venituri atribuite:</span><span class="font-medium text-emerald-600">${formatCurrency(milestone.attributed_revenue)}</span></div>`;
    }
    if (milestone.roas && milestone.roas > 0) {
        detailsHtml += `<div class="flex justify-between"><span class="text-gray-500">ROAS:</span><span class="font-medium">${milestone.roas.toFixed(2)}x</span></div>`;
    }
    if (milestone.description) {
        detailsHtml += `<div class="mt-2 text-xs text-gray-600">${milestone.description}</div>`;
    }
    document.getElementById('milestone-tooltip-details').innerHTML = detailsHtml || '<div class="text-gray-400 text-xs">Fără detalii suplimentare</div>';

    // Dates
    let datesText = '';
    if (milestone.start_date) {
        const startDate = new Date(milestone.start_date).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        datesText = startDate;
        if (milestone.end_date && milestone.end_date !== milestone.start_date) {
            const endDate = new Date(milestone.end_date).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
            datesText += ' - ' + endDate;
        }
    }
    document.getElementById('milestone-tooltip-dates').textContent = datesText;

    // Position tooltip near the mouse
    const rect = event.target.getBoundingClientRect();
    const tooltipEl = tooltip;
    tooltipEl.classList.remove('hidden');

    // Position above the annotation
    let left = rect.left + window.scrollX;
    let top = rect.top + window.scrollY - tooltipEl.offsetHeight - 10;

    // Ensure tooltip stays within viewport
    if (top < 50) {
        top = rect.bottom + window.scrollY + 10;
    }
    if (left + tooltipEl.offsetWidth > window.innerWidth - 20) {
        left = window.innerWidth - tooltipEl.offsetWidth - 20;
    }
    if (left < 10) {
        left = 10;
    }

    tooltipEl.style.left = left + 'px';
    tooltipEl.style.top = top + 'px';

    // If sticky (clicked), add a close-on-click-outside listener
    if (sticky) {
        tooltipEl.dataset.sticky = 'true';
        setTimeout(() => {
            document.addEventListener('click', hideMilestoneTooltipOnOutsideClick);
        }, 10);
    }
}

// Hide milestone tooltip
function hideMilestoneTooltip() {
    const tooltip = document.getElementById('milestone-tooltip');
    if (tooltip && tooltip.dataset.sticky !== 'true') {
        tooltip.classList.add('hidden');
    }
}

// Hide tooltip on outside click (for sticky mode)
function hideMilestoneTooltipOnOutsideClick(e) {
    const tooltip = document.getElementById('milestone-tooltip');
    if (tooltip && !tooltip.contains(e.target)) {
        tooltip.classList.add('hidden');
        tooltip.dataset.sticky = '';
        document.removeEventListener('click', hideMilestoneTooltipOnOutsideClick);
    }
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

        // Format date - API returns starts_at
        let dateStr = '';
        const dateSource = e.starts_at || e.start_date || e.date;
        if (dateSource) {
            const d = new Date(dateSource);
            if (!isNaN(d.getTime())) {
                const day = String(d.getDate()).padStart(2,'0');
                const month = String(d.getMonth()+1).padStart(2,'0');
                const year = d.getFullYear();
                const hours = String(d.getHours()).padStart(2,'0');
                const minutes = String(d.getMinutes()).padStart(2,'0');
                dateStr = `${day}.${month}.${year} | ${hours}:${minutes}`;
            } else if (typeof dateSource === 'string') {
                const match = dateSource.match(/(\d{4})-(\d{2})-(\d{2})/);
                if (match) {
                    dateStr = `${match[3]}.${match[2]}.${match[1]}`;
                } else {
                    dateStr = dateSource;
                }
            }
        }

        // Get venue name and city - API returns venue_name and venue_city directly
        const venueName = e.venue_name || (typeof e.venue === 'object' ? e.venue?.name : e.venue) || '';
        const cityName = e.venue_city || (typeof e.venue === 'object' ? e.venue?.city : '') || e.city || '';
        let locationStr = venueName;
        if (cityName && cityName !== venueName) {
            locationStr = venueName ? `${venueName}, ${cityName}` : cityName;
        }

        // Get event name - API returns 'name' not 'title'
        const eventName = e.name || e.title || 'Eveniment';

        return `
            <a href="/organizator/analytics/${e.id}" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 ${isActive ? 'bg-primary/5 border-l-2 border-primary' : ''}">
                <div class="flex-shrink-0 w-10 h-10 overflow-hidden bg-gray-200 rounded-lg">
                    ${e.image ? `<img src="${fixImageUrl(e.image)}" class="object-cover w-full h-full">` : '<div class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-500 text-xs">📅</div>'}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-800 truncate">${eventName}</div>
                    <div class="text-[11px] text-gray-500 truncate">${dateStr}${locationStr ? ' • ' + locationStr : ''}</div>
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

// Globe modal functions with Leaflet map
let globeModalOpen = false;
let liveVisitorsData = null;

// Romanian cities with lat/lng coordinates
const cityCoordinates = {
    'București': { lat: 44.4268, lng: 26.1025 },
    'Bucharest': { lat: 44.4268, lng: 26.1025 },
    'Cluj-Napoca': { lat: 46.7712, lng: 23.6236 },
    'Cluj': { lat: 46.7712, lng: 23.6236 },
    'Timișoara': { lat: 45.7489, lng: 21.2087 },
    'Timisoara': { lat: 45.7489, lng: 21.2087 },
    'Iași': { lat: 47.1585, lng: 27.6014 },
    'Iasi': { lat: 47.1585, lng: 27.6014 },
    'Constanța': { lat: 44.1598, lng: 28.6348 },
    'Constanta': { lat: 44.1598, lng: 28.6348 },
    'Brașov': { lat: 45.6427, lng: 25.5887 },
    'Brasov': { lat: 45.6427, lng: 25.5887 },
    'Sibiu': { lat: 45.7983, lng: 24.1256 },
    'Oradea': { lat: 47.0465, lng: 21.9189 },
    'Craiova': { lat: 44.3302, lng: 23.7949 },
    'Galați': { lat: 45.4353, lng: 28.0080 },
    'Galati': { lat: 45.4353, lng: 28.0080 },
    'Ploiești': { lat: 44.9364, lng: 26.0134 },
    'Ploiesti': { lat: 44.9364, lng: 26.0134 },
    'Arad': { lat: 46.1866, lng: 21.3123 },
    'Pitești': { lat: 44.8565, lng: 24.8691 },
    'Pitesti': { lat: 44.8565, lng: 24.8691 },
    'Bacău': { lat: 46.5670, lng: 26.9146 },
    'Bacau': { lat: 46.5670, lng: 26.9146 },
    'Târgu Mureș': { lat: 46.5386, lng: 24.5575 },
    'Targu Mures': { lat: 46.5386, lng: 24.5575 },
    'Baia Mare': { lat: 47.6567, lng: 23.5850 },
    'Suceava': { lat: 47.6517, lng: 26.2556 },
};

function openGlobeModal() {
    document.getElementById('globe-modal').classList.remove('hidden');
    globeModalOpen = true;
    // Small delay to ensure modal is visible before initializing map
    setTimeout(() => loadLiveVisitors(), 100);
}

function closeGlobeModal() {
    document.getElementById('globe-modal').classList.add('hidden');
    globeModalOpen = false;
    // Clean up map when closing
    if (globeMap) {
        try {
            globeMap.remove();
        } catch (e) {}
        globeMap = null;
    }
}

async function loadLiveVisitors() {
    try {
        // Try to get live visitors data from analytics
        if (eventData?.top_locations) {
            renderGlobeData(eventData.top_locations);
        } else {
            // Fallback to showing the analytics data
            const response = await AmbiletAPI.get(`/organizer/events/${eventId}/analytics?period=1d`);
            if (response.success && response.data?.top_locations) {
                renderGlobeData(response.data.top_locations);
            }
        }
    } catch (error) {
        console.error('Error loading live visitors:', error);
    }
}

// Country flags
const countryFlags = {
    'RO': '🇷🇴', 'Romania': '🇷🇴',
    'DE': '🇩🇪', 'Germany': '🇩🇪',
    'GB': '🇬🇧', 'UK': '🇬🇧', 'United Kingdom': '🇬🇧',
    'US': '🇺🇸', 'USA': '🇺🇸', 'United States': '🇺🇸',
    'FR': '🇫🇷', 'France': '🇫🇷',
    'IT': '🇮🇹', 'Italy': '🇮🇹',
    'ES': '🇪🇸', 'Spain': '🇪🇸',
    'MD': '🇲🇩', 'Moldova': '🇲🇩',
    'HU': '🇭🇺', 'Hungary': '🇭🇺',
    'BG': '🇧🇬', 'Bulgaria': '🇧🇬',
};

function getFlag(country) {
    return countryFlags[country] || '🌍';
}

function renderGlobeData(locations) {
    const liveCountEl = document.getElementById('globe-live-count');
    const activityEl = document.getElementById('globe-live-activity');
    const topLocationsEl = document.getElementById('globe-top-locations');

    if (!locations || locations.length === 0) {
        liveCountEl.textContent = '0';
        activityEl.innerHTML = `
            <div class="flex items-center gap-3 p-2 rounded-lg">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-lg">🌍</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-slate-800 truncate">No live visitors</div>
                    <div class="text-xs text-slate-400">-</div>
                </div>
            </div>
        `;
        topLocationsEl.innerHTML = '<div class="text-sm text-slate-400 text-center py-2">No data</div>';
        initLeafletMap([]);
        return;
    }

    // Calculate totals
    const totalVisitors = locations.reduce((sum, l) => sum + (l.visitors || l.count || 0), 0);
    liveCountEl.textContent = formatNumber(totalVisitors);

    // Initialize Leaflet map
    initLeafletMap(locations);

    // Render live activity list
    const activityHtml = locations.slice(0, 8).map(l => {
        const flag = getFlag(l.country || 'RO');
        const action = `Viewed event page`;
        const city = l.city || 'Unknown';
        const country = l.country || 'RO';
        return `
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-lg">${flag}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-slate-800 truncate">${action}</div>
                    <div class="text-xs text-slate-400">${city}, ${country}</div>
                </div>
                <div class="text-xs text-slate-300">now</div>
            </div>
        `;
    }).join('');
    activityEl.innerHTML = activityHtml || '<div class="text-sm text-slate-400 text-center py-2">No activity</div>';

    // Render top locations
    const maxCount = Math.max(...locations.map(l => l.visitors || l.count || 0));
    const topHtml = locations.slice(0, 5).map(l => {
        const count = l.visitors || l.count || 0;
        const percent = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
        const flag = getFlag(l.country || 'RO');
        return `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span>${flag}</span>
                    <span class="text-sm text-slate-600">${l.city || 'Unknown'}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: ${percent}%"></div>
                    </div>
                    <span class="text-xs text-slate-400 w-8 text-right">${count}</span>
                </div>
            </div>
        `;
    }).join('');
    topLocationsEl.innerHTML = topHtml || '<div class="text-sm text-slate-400 text-center py-2">No data</div>';
}

function initLeafletMap(locations) {
    const container = document.getElementById('globeMapContainer');

    // Check if Leaflet is loaded
    if (typeof L === 'undefined') {
        console.warn('Leaflet not loaded');
        return;
    }

    // Remove existing map if any
    if (globeMap) {
        try {
            globeMap.remove();
        } catch (e) {}
        globeMap = null;
    }

    try {
        // Initialize Leaflet map centered on Romania
        const map = L.map(container, {
            center: [46, 25],
            zoom: 6,
            zoomControl: true,
            attributionControl: false
        });

        // Add CartoDB voyager (light) tile layer - matching core.tixello.com
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        globeMap = map;

        // Prepare locations with coordinates
        const mappedLocations = (locations || []).map(l => {
            const city = l.city || '';
            const coords = cityCoordinates[city] || l;
            return {
                lat: coords.lat || null,
                lng: coords.lng || null,
                city: city,
                visitors: l.visitors || l.count || 0,
                isLive: l.isLive || false
            };
        }).filter(l => l.lat && l.lng);

        // Add markers
        mappedLocations.forEach(loc => {
            const marker = L.circleMarker([loc.lat, loc.lng], {
                radius: loc.isLive ? 12 : Math.max(8, Math.min(25, (loc.visitors || 1) / 2)),
                fillColor: loc.isLive ? '#10b981' : '#6366f1',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: loc.isLive ? 0.8 : 0.6,
                className: loc.isLive ? 'live-marker-pulse' : ''
            }).addTo(map);

            const popupContent = loc.isLive
                ? `<b>${loc.city}</b><br><span style="color:#10b981">Online now</span>`
                : `<b>${loc.city}</b><br>${loc.visitors} tickets sold`;
            marker.bindPopup(popupContent);
        });

        // Force map to recalculate size after container is visible
        setTimeout(() => {
            map.invalidateSize();
            // Fit bounds to markers if we have locations
            if (mappedLocations.length > 0) {
                const bounds = L.latLngBounds(mappedLocations.map(l => [l.lat, l.lng]));
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }, 200);

    } catch (e) {
        console.error('Error initializing Leaflet map:', e);
    }
}

// Goal modal functions
function showAddGoalModal() {
    document.getElementById('goal-modal').classList.remove('hidden');
    // Set max date for deadline based on event date
    const deadlineInput = document.getElementById('goal-deadline-input');
    const deadlineHint = document.getElementById('goal-deadline-hint');
    if (deadlineInput && eventData?.event) {
        const eventDate = eventData.event.ends_at || eventData.event.starts_at || eventData.event.date;
        if (eventDate) {
            const maxDate = eventDate.split('T')[0];
            deadlineInput.max = maxDate;
            deadlineHint.textContent = `Max: ${new Date(maxDate).toLocaleDateString('ro-RO')}`;
        }
    }
}
function closeGoalModal() { document.getElementById('goal-modal').classList.add('hidden'); document.getElementById('goal-form').reset(); }

async function saveGoal(e) {
    e.preventDefault();
    const form = e.target;

    // Date validation is handled by HTML5 max attribute on the input
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
const AD_CAMPAIGN_TYPES = ['facebook_ads', 'google_ads', 'instagram_ads', 'tiktok_ads', 'influencer'];

function showAddMilestoneModal() {
    document.getElementById('milestone-modal').classList.remove('hidden');
    updateMilestoneFields('facebook_ads'); // Default to ad fields
    // Set date restrictions based on event dates
    const startInput = document.getElementById('milestone-start-date');
    const endInput = document.getElementById('milestone-end-date');
    const dateHint = document.getElementById('milestone-date-hint');
    if (startInput && endInput && eventData?.event) {
        const createdAt = eventData.event.created_at;
        const eventEndDate = eventData.event.ends_at || eventData.event.starts_at || eventData.event.date;
        if (createdAt) {
            const minDate = createdAt.split('T')[0];
            startInput.min = minDate;
        }
        if (eventEndDate) {
            const maxDate = eventEndDate.split('T')[0];
            endInput.max = maxDate;
            const minStr = createdAt ? new Date(createdAt.split('T')[0]).toLocaleDateString('ro-RO') : '-';
            const maxStr = new Date(maxDate).toLocaleDateString('ro-RO');
            dateHint.textContent = `Interval permis: ${minStr} - ${maxStr}`;
        }
    }
}

function closeMilestoneModal() {
    document.getElementById('milestone-modal').classList.add('hidden');
    document.getElementById('milestone-form').reset();
    updateMilestoneFields('facebook_ads');
}

function updateMilestoneFields(type) {
    const adFields = document.getElementById('milestone-ad-fields');
    const otherFields = document.getElementById('milestone-other-fields');

    if (AD_CAMPAIGN_TYPES.includes(type)) {
        adFields.classList.remove('hidden');
        otherFields.classList.add('hidden');
        // Pre-fill UTM source based on type
        const utmSourceInput = document.querySelector('input[name="utm_source"]');
        const utmMediumInput = document.querySelector('input[name="utm_medium"]');
        if (utmSourceInput) {
            switch(type) {
                case 'facebook_ads':
                case 'instagram_ads':
                    utmSourceInput.placeholder = 'facebook';
                    utmMediumInput.placeholder = 'cpc';
                    break;
                case 'google_ads':
                    utmSourceInput.placeholder = 'google';
                    utmMediumInput.placeholder = 'cpc';
                    break;
                case 'tiktok_ads':
                    utmSourceInput.placeholder = 'tiktok';
                    utmMediumInput.placeholder = 'cpc';
                    break;
                default:
                    utmSourceInput.placeholder = 'organic';
                    utmMediumInput.placeholder = 'referral';
            }
        }
    } else {
        adFields.classList.add('hidden');
        otherFields.classList.remove('hidden');
    }
}

async function saveMilestone(e) {
    e.preventDefault();
    const form = e.target;

    // Date validation is handled by HTML5 min/max attributes on the inputs
    // Map form type values to API type values
    const typeMap = {
        'facebook_ads': 'campaign_fb',
        'google_ads': 'campaign_google',
        'instagram_ads': 'campaign_instagram',
        'tiktok_ads': 'campaign_tiktok',
        'email_campaign': 'email',
        'price_change': 'price',
        'announcement': 'announcement',
        'press': 'press',
        'lineup': 'lineup',
        'influencer': 'campaign_other',
        'other': 'custom'
    };

    const formType = form.type.value;
    const isAdCampaign = AD_CAMPAIGN_TYPES.includes(formType);

    const data = {
        title: form.name.value,
        type: typeMap[formType] || formType,
        start_date: startDateValue,
        end_date: endDateValue || null
    };

    if (isAdCampaign) {
        // Ad campaign specific fields
        data.budget = form.budget.value ? parseFloat(form.budget.value) : null;
        data.platform_campaign_id = form.platform_campaign_id.value || null;
        data.utm_source = form.utm_source.value || null;
        data.utm_medium = form.utm_medium.value || null;
        data.utm_campaign = form.utm_campaign.value || null;
        data.utm_content = form.utm_content.value || null;
    } else {
        // Non-ad campaign fields
        data.description = form.description.value || null;
        data.impact_metric = form.impact_metric.value || null;
        data.baseline_value = form.baseline_value.value ? parseFloat(form.baseline_value.value) : null;
        data.post_value = form.post_value.value ? parseFloat(form.post_value.value) : null;
    }

    try {
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/milestones`, data);
        if (response.success) { closeMilestoneModal(); loadAnalytics(); }
    } catch (error) { console.error('Error saving milestone:', error); alert('Eroare la salvarea campaniei'); }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
