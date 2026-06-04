<?php
/**
 * bilete.online — Organizator › Analiză activitate (v3).
 * Route: /organizator/analytics  (and /organizator/analytics/{id})
 *
 * Per-activity analytics dashboard: KPI cards, sales chart (ApexCharts) with
 * campaign milestone annotations, forecast, ticket-type performance, traffic
 * sources, top locations, recent sales, goals, campaign ROI and a live-visitors
 * map (Leaflet). Activity-centric port of the ambilet organizer analytics page,
 * restyled to the bilete.online v3 design and wired to BileteOnlineAPI.organizer
 * (analytics / goals / milestones endpoints).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Analiză activitate';
$currentPage = 'events';
$eventId     = $_GET['event'] ?? null;
// CDN libs + page-specific styles. head.php emits $extraHead; we also set
// $headExtra to match the documented convention for ported pages.
$headExtra = '
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    .forecast-card { background: linear-gradient(135deg, #1B1714 0%, #1E4A3D 100%); }
    .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2); opacity: 0; } }
    .milestone-card { transition: all 0.2s ease; }
    .milestone-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(27,23,20,0.1); }
    .leaflet-container { background: #1B1714 !important; font-size: 14px; }
    .leaflet-pane { z-index: 1 !important; }
    .leaflet-tile-pane { z-index: 1 !important; }
    .leaflet-overlay-pane { z-index: 2 !important; }
    .leaflet-marker-pane { z-index: 3 !important; }
    .leaflet-popup-pane { z-index: 4 !important; }
    .live-marker-pulse { animation: marker-pulse 2s ease-in-out infinite; }
    @keyframes marker-pulse { 0%, 100% { opacity: 0.8; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
</style>
';
$extraHead = $headExtra;
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Sub-header -->
    <div class="sticky top-0 z-30 border-b-2 border-ink/10 bg-paper">
        <div class="px-4 py-3 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <!-- Title + Back -->
                    <a href="/organizator/events" class="flex flex-col items-start gap-1 text-sm text-ink-soft transition hover:text-ink">
                        <span class="font-display text-xl font-bold text-ink">Analiză activitate</span>
                        <span class="flex items-center gap-x-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Înapoi
                        </span>
                    </a>

                    <!-- Activity dropdown -->
                    <div class="relative" id="event-dropdown-container">
                        <button id="event-selector" onclick="toggleEventDropdown()" class="flex min-w-[280px] items-center gap-3 rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 transition hover:border-ink">
                            <div id="event-selector-image" class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-ink/10"></div>
                            <div class="flex-1 text-left">
                                <div id="event-selector-name" class="text-sm font-bold text-ink">Se încarcă...</div>
                                <div id="event-selector-info" class="text-[11px] text-ink-soft"></div>
                            </div>
                            <svg id="event-dropdown-arrow" class="h-4 w-4 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <!-- Dropdown menu -->
                        <div id="event-dropdown-menu" class="absolute left-0 top-full z-50 mt-2 hidden max-h-80 w-full overflow-y-auto overflow-hidden rounded-xl border-2 border-ink/15 bg-paper shadow-deep">
                            <div id="event-dropdown-loading" class="py-4 text-center text-sm text-ink-soft">
                                <svg class="mx-auto mb-2 h-5 w-5 animate-spin text-ink/30" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Se încarcă...
                            </div>
                            <div id="event-dropdown-list" class="hidden"></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Period selector -->
                    <div class="flex items-center rounded-full border-2 border-ink/15 bg-paper-2 p-1">
                        <button class="period-btn rounded-full px-3 py-1.5 text-xs font-bold text-ink-soft transition" data-period="7d">7Z</button>
                        <button class="period-btn rounded-full px-3 py-1.5 text-xs font-bold text-ink-soft transition" data-period="30d">30Z</button>
                        <button class="period-btn rounded-full px-3 py-1.5 text-xs font-bold text-ink-soft transition" data-period="90d">90Z</button>
                        <button class="period-btn rounded-full bg-ink px-3 py-1.5 text-xs font-bold text-paper transition" data-period="all">Tot</button>
                    </div>

                    <!-- Live indicator -->
                    <div id="live-indicator" class="hidden items-center gap-2 rounded-full border-2 border-forest/30 bg-forest/10 px-3 py-2">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-forest opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-forest"></span>
                        </span>
                        <span id="live-count" class="text-sm font-bold text-forest">0 online</span>
                    </div>

                    <!-- Map button -->
                    <button onclick="openGlobeModal()" class="flex items-center gap-2 rounded-full border-2 border-ink px-3 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper" title="Vezi trafic live pe hartă">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="hidden sm:inline">Hartă</span>
                    </button>

                    <!-- Export -->
                    <button onclick="exportReport()" class="flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Stats cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <!-- Revenue -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-forest/10 text-forest">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span id="stat-revenue-change" class="flex items-center gap-1 rounded-full bg-forest/10 px-2 py-1 text-xs font-bold text-forest">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <span>+0%</span>
                    </span>
                </div>
                <div id="stat-revenue" class="font-display text-2xl font-bold text-ink">0 lei</div>
                <div class="mt-1 text-xs text-ink-soft">Venituri totale</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink/10">
                        <div id="stat-revenue-bar" class="h-full rounded-full bg-forest" style="width: 0%"></div>
                    </div>
                    <span id="stat-revenue-percent" class="text-[10px] text-ink-soft">0%</span>
                </div>
            </div>

            <!-- Tickets sold -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-sky/10 text-sky">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <span id="stat-tickets-today" class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">+0 azi</span>
                </div>
                <div id="stat-tickets" class="font-display text-2xl font-bold text-ink">0</div>
                <div class="mt-1 text-xs text-ink-soft">Bilete vândute</div>
                <div class="mt-3 flex items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink/10">
                        <div id="stat-tickets-bar" class="h-full rounded-full bg-sky" style="width: 0%"></div>
                    </div>
                    <span id="stat-tickets-percent" class="text-[10px] text-ink-soft">0%</span>
                </div>
            </div>

            <!-- Total visits -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-sky/10 text-sky">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                </div>
                <div id="stat-views" class="font-display text-2xl font-bold text-ink">0</div>
                <div class="mt-1 text-xs text-ink-soft">Vizualizări totale</div>
                <div id="stat-unique" class="mt-3 text-[11px] text-ink-soft">0 unice</div>
            </div>

            <!-- Conversion rate -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-ochre/10 text-ochre">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <div id="stat-conversion" class="font-display text-2xl font-bold text-ink">0%</div>
                <div class="mt-1 text-xs text-ink-soft">Rata conversie</div>
                <div class="mt-3 text-[11px] text-ink-soft">Vizite → Achiziții</div>
            </div>

            <!-- Days until activity -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-vermilion/10 text-vermilion">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <span id="stat-event-status" class="rounded-full bg-forest/10 px-2 py-1 text-xs font-bold text-forest">Activ</span>
                </div>
                <div id="stat-days" class="font-display text-2xl font-bold text-ink">-</div>
                <div class="mt-1 text-xs text-ink-soft">Zile până la activitate</div>
                <div id="stat-event-date" class="mt-3 text-[11px] text-ink-soft">-</div>
            </div>
        </div>

        <!-- Chart + Forecast -->
        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <!-- Main chart -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-lg font-bold text-ink">Performanță vânzări</h2>
                        <p class="text-xs text-ink-soft">Click pentru a comuta metricile</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleChartMetric('revenue')" class="chart-metric-btn active flex items-center gap-2 rounded-full border-2 border-forest/30 bg-forest/10 px-3 py-1.5 transition" data-metric="revenue">
                            <div class="h-2.5 w-2.5 rounded-full bg-forest"></div>
                            <span class="text-xs font-bold text-forest">Venituri</span>
                        </button>
                        <button onclick="toggleChartMetric('tickets')" class="chart-metric-btn active flex items-center gap-2 rounded-full border-2 border-sky/30 bg-sky/10 px-3 py-1.5 transition" data-metric="tickets">
                            <div class="h-2.5 w-2.5 rounded-full bg-sky"></div>
                            <span class="text-xs font-bold text-sky">Bilete</span>
                        </button>
                        <button onclick="toggleChartMetric('views')" class="chart-metric-btn active flex items-center gap-2 rounded-full border-2 border-ochre/30 bg-ochre/10 px-3 py-1.5 transition" data-metric="views">
                            <div class="h-2.5 w-2.5 rounded-full bg-ochre"></div>
                            <span class="text-xs font-bold text-ochre">Vizualizări</span>
                        </button>
                    </div>
                </div>
                <div id="mainChart" class="h-[300px]"></div>
                <!-- Milestone tooltip -->
                <div id="milestone-tooltip" class="fixed z-50 hidden max-w-xs rounded-xl border-2 border-ink/15 bg-paper p-4 shadow-deep">
                    <div class="flex items-start gap-3">
                        <div id="milestone-tooltip-icon" class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-lg"></div>
                        <div class="min-w-0 flex-1">
                            <div id="milestone-tooltip-type" class="text-xs font-bold uppercase tracking-wide text-ink-soft"></div>
                            <div id="milestone-tooltip-title" class="truncate text-sm font-bold text-ink"></div>
                        </div>
                    </div>
                    <div id="milestone-tooltip-details" class="mt-3 space-y-2 text-sm">
                        <!-- Dynamic content -->
                    </div>
                    <div class="mt-3 border-t-2 border-ink/10 pt-3">
                        <div id="milestone-tooltip-dates" class="text-xs text-ink-soft"></div>
                    </div>
                </div>
            </div>

            <!-- Forecast / summary -->
            <div id="forecast-card" class="forecast-card rounded-2xl p-6 text-paper">
                <div class="mb-5 flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-paper/10">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <div>
                        <h2 class="font-display text-lg font-bold">Estimări</h2>
                        <p class="text-xs text-paper/90">Predicții bazate pe trend</p>
                    </div>
                </div>
                <div class="mb-4 rounded-xl bg-paper/10 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm font-bold text-paper/80">Următoarele 7 zile</span>
                        <span class="rounded-full bg-forest/40 px-2 py-0.5 text-xs text-paper">Trend</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div id="forecast-revenue" class="text-xl font-bold">0 lei</div>
                            <div class="text-xs text-paper/90">Venituri estimate</div>
                        </div>
                        <div>
                            <div id="forecast-tickets" class="text-xl font-bold">+0</div>
                            <div class="text-xs text-paper/90">Bilete estimate</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-paper/10 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm font-bold text-paper/80">La final</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div id="forecast-total-revenue" class="text-xl font-bold">0 lei</div>
                            <div class="text-xs text-paper/90">Total estimat</div>
                        </div>
                        <div>
                            <div id="forecast-total-tickets" class="text-xl font-bold">0</div>
                            <div class="text-xs text-paper/90">Bilete total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets & Campaign ROI -->
        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <!-- Ticket performance table -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6 lg:col-span-2">
                <h2 class="mb-4 font-display text-lg font-bold text-ink">Performanță tipuri bilete</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-ink/10">
                                <th class="pb-3 text-left text-xs font-bold uppercase text-ink-soft">Tip</th>
                                <th class="pb-3 text-right text-xs font-bold uppercase text-ink-soft">Preț</th>
                                <th class="pb-3 text-right text-xs font-bold uppercase text-ink-soft">Vândute</th>
                                <th class="pb-3 text-right text-xs font-bold uppercase text-ink-soft">Venituri</th>
                                <th class="pb-3 text-right text-xs font-bold uppercase text-ink-soft">Conv.</th>
                                <th class="pb-3 text-right text-xs font-bold uppercase text-ink-soft">Trend</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-table">
                            <tr><td colspan="6" class="py-8 text-center text-sm text-ink-soft">Se încarcă...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campaign ROI -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-ink">Campanii ROI</h2>
                    <button onclick="showAddMilestoneModal()" class="text-sm font-bold text-vermilion hover:underline">+ Adaugă</button>
                </div>
                <div id="campaigns-list" class="space-y-3">
                    <div class="py-6 text-center text-sm text-ink-soft">Nu ai campanii active</div>
                </div>
            </div>
        </div>

        <!-- Traffic sources & locations -->
        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <!-- Traffic sources -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold text-ink">Surse de trafic</h2>
                <div id="traffic-sources" class="space-y-3"></div>
            </div>

            <!-- Top locations -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold text-ink">Locații top</h2>
                <div id="locations-list" class="space-y-3"></div>
            </div>
        </div>

        <!-- Recent sales & goals -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent sales -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-ink">Vânzări recente</h2>
                    <a href="/organizator/participanti?event=<?= htmlspecialchars((string) $eventId, ENT_QUOTES) ?>" class="text-sm font-bold text-vermilion hover:underline">Vezi toate →</a>
                </div>
                <div id="recent-sales" class="space-y-3">
                    <div class="py-6 text-center text-sm text-ink-soft">Se încarcă...</div>
                </div>
            </div>

            <!-- Goals -->
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-ink">Obiective</h2>
                    <button onclick="showAddGoalModal()" class="text-sm font-bold text-vermilion hover:underline">+ Adaugă</button>
                </div>
                <div id="goals-list" class="space-y-4">
                    <div class="py-6 text-center text-sm text-ink-soft">
                        <svg class="mx-auto mb-2 h-10 w-10 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                        Setează obiective pentru a urmări progresul
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Add Goal Modal -->
<div id="goal-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-ink/50 backdrop-blur-sm" onclick="closeGoalModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border-2 border-ink bg-paper shadow-deep" onclick="event.stopPropagation()">
            <div class="border-b-2 border-ink/10 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-ink">Adaugă obiectiv</h2>
                    <button onclick="closeGoalModal()" aria-label="Închide" class="p-2 text-ink-soft transition hover:text-ink">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="goal-form" onsubmit="saveGoal(event)" class="space-y-4 p-6">
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Tip obiectiv</label>
                    <select name="type" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                        <option value="revenue">Venituri (RON)</option>
                        <option value="tickets">Bilete vândute</option>
                        <option value="visitors">Vizitatori</option>
                        <option value="conversion_rate">Rată conversie (%)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Nume obiectiv</label>
                    <input type="text" name="name" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required placeholder="ex: Obiectiv vânzări luna Ianuarie">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Valoare țintă</label>
                    <input type="number" name="target_value" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required min="0" step="any">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Deadline (opțional)</label>
                    <input type="date" name="deadline" id="goal-deadline-input" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                    <p id="goal-deadline-hint" class="mt-1 text-xs text-ink-soft"></p>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeGoalModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button>
                    <button type="submit" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Milestone Modal -->
<div id="milestone-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-ink/50 backdrop-blur-sm" onclick="closeMilestoneModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4">
        <div class="my-8 w-full max-w-lg rounded-2xl border-2 border-ink bg-paper shadow-deep" onclick="event.stopPropagation()">
            <div class="border-b-2 border-ink/10 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold text-ink">Adaugă campanie</h2>
                    <button onclick="closeMilestoneModal()" aria-label="Închide" class="p-2 text-ink-soft transition hover:text-ink">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="milestone-form" onsubmit="saveMilestone(event)" class="max-h-[70vh] space-y-4 overflow-y-auto p-6">
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Nume campanie</label>
                    <input type="text" name="name" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required placeholder="ex: Campanie Facebook Ads">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink">Tip</label>
                    <select name="type" onchange="updateMilestoneFields(this.value)" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
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
                        <label class="mb-1 block text-sm font-bold text-ink">Data start</label>
                        <input type="date" name="start_date" id="milestone-start-date" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-bold text-ink">Data sfârșit</label>
                        <input type="date" name="end_date" id="milestone-end-date" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                    </div>
                </div>
                <p id="milestone-date-hint" class="text-xs text-ink-soft"></p>

                <!-- Ad campaign fields -->
                <div id="milestone-ad-fields" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-bold text-ink">Buget (RON)</label>
                        <input type="number" name="budget" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-bold text-ink">ID Campanie platformă</label>
                        <input type="text" name="platform_campaign_id" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: 120215478965421">
                    </div>
                    <div class="rounded-xl border-2 border-ink/15 bg-paper-2 p-4">
                        <h4 class="mb-3 text-sm font-bold text-ink">Parametri UTM</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-ink-soft">UTM Source</label>
                                <input type="text" name="utm_source" class="w-full rounded-lg border-2 border-ink/15 bg-paper px-3 py-1.5 text-sm outline-none transition focus:border-ink" placeholder="facebook">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-ink-soft">UTM Medium</label>
                                <input type="text" name="utm_medium" class="w-full rounded-lg border-2 border-ink/15 bg-paper px-3 py-1.5 text-sm outline-none transition focus:border-ink" placeholder="cpc">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-ink-soft">UTM Campaign</label>
                                <input type="text" name="utm_campaign" class="w-full rounded-lg border-2 border-ink/15 bg-paper px-3 py-1.5 text-sm outline-none transition focus:border-ink" placeholder="summer-sale">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-ink-soft">UTM Content</label>
                                <input type="text" name="utm_content" class="w-full rounded-lg border-2 border-ink/15 bg-paper px-3 py-1.5 text-sm outline-none transition focus:border-ink" placeholder="banner-1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Non-ad campaign fields -->
                <div id="milestone-other-fields" class="hidden space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-bold text-ink">Descriere</label>
                        <textarea name="description" rows="3" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Descrieți campania sau acțiunea..."></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-bold text-ink">Metrică de impact</label>
                        <select name="impact_metric" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                            <option value="">Selectează metrica</option>
                            <option value="tickets_sold">Bilete vândute</option>
                            <option value="page_views">Vizualizări pagină</option>
                            <option value="revenue">Venituri</option>
                            <option value="conversion_rate">Rata conversie</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-ink">Valoare inițială</label>
                            <input type="number" name="baseline_value" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" min="0" step="0.01" placeholder="0">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold text-ink">Valoare după</label>
                            <input type="number" name="post_value" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t-2 border-ink/10 pt-4">
                    <button type="button" onclick="closeMilestoneModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button>
                    <button type="submit" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Globe/Map Modal -->
<div id="globe-modal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div class="fixed inset-0 bg-ink/80 backdrop-blur-sm" onclick="closeGlobeModal()"></div>
    <div class="fixed bottom-4 left-4 right-4 top-4 overflow-hidden rounded-3xl bg-paper-2 shadow-deep" style="z-index: 100000;">
        <!-- Full-screen map container -->
        <div id="globeMapContainer" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; min-height: 400px; background: #1B1714;"></div>

        <!-- Header overlay -->
        <div class="pointer-events-none absolute left-0 right-0 top-0 bg-gradient-to-b from-paper-2 via-paper-2/80 to-transparent p-6" style="z-index: 1000;">
            <div class="pointer-events-auto flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-xl bg-ink">
                        <svg class="h-6 w-6 text-paper" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-display text-xl font-bold text-ink">Vizitatori live</h2>
                        <p class="text-sm text-ink-soft">Activitate globală în timp real</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 shadow-sm">
                        <span class="relative flex h-3 w-3">
                            <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-forest opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-forest"></span>
                        </span>
                        <span id="globe-live-count" class="text-lg font-bold text-ink">0</span>
                        <span class="text-sm text-ink-soft">online acum</span>
                    </div>
                    <button onclick="closeGlobeModal()" aria-label="Închide" class="rounded-xl border-2 border-ink/15 bg-paper p-3 shadow-sm transition hover:bg-paper-2">
                        <svg class="h-5 w-5 text-ink" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Live activity panel (bottom-left) -->
        <div class="absolute bottom-6 left-6 w-80 overflow-hidden rounded-2xl border-2 border-ink/15 bg-paper/95 shadow-deep backdrop-blur-xl" style="z-index: 1000;">
            <div class="border-b-2 border-ink/10 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-ink">Activitate live</span>
                    <span class="text-xs text-ink-soft">Ultimele 5 minute</span>
                </div>
            </div>
            <div id="globe-live-activity" class="max-h-64 overflow-y-auto p-2">
                <div class="flex items-center gap-3 rounded-lg p-2">
                    <div class="grid h-8 w-8 place-items-center rounded-full bg-paper-2 text-lg">🇷🇴</div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm text-ink">Niciun vizitator live</div>
                        <div class="text-xs text-ink-soft">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top locations panel (bottom-right) -->
        <div class="absolute bottom-6 right-6 w-64 rounded-2xl border-2 border-ink/15 bg-paper/95 p-4 shadow-deep backdrop-blur-xl" style="z-index: 1000;">
            <div class="mb-3 text-sm font-bold text-ink">Locații top</div>
            <div id="globe-top-locations" class="space-y-2">
                <!-- Will be populated by JS -->
            </div>
        </div>
    </div>
</div>

<?php
// Inject $eventId into JS outside the static NOWDOC.
$scriptsExtra = "<script>\nconst eventId = " . json_encode($eventId) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
function orgNotify(msg, type) { try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type||'info']) { BileteOnlineNotifications[type||'info'](msg); return; } } catch(e){} if (type==='error'||type==='warning') alert(msg); }

let currentPeriod = 'all';
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
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
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
                b.classList.remove('bg-ink', 'text-paper');
                b.classList.add('text-ink-soft');
            });
            this.classList.remove('text-ink-soft');
            this.classList.add('bg-ink', 'text-paper');
            currentPeriod = this.dataset.period;
            loadAnalytics();
        });
    });
}

async function loadAnalytics() {
    try {
        const response = await BileteOnlineAPI.get(`/organizer/events/${eventId}/analytics?period=${currentPeriod}`);
        if (response.success) {
            eventData = response.data;
            updateDashboard(response.data);
        }

        // Load milestones/campaigns
        try {
            const milestonesResponse = await BileteOnlineAPI.get(`/organizer/events/${eventId}/milestones`);
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
            const goalsResponse = await BileteOnlineAPI.get(`/organizer/events/${eventId}/goals`);
            if (goalsResponse.success) {
                updateGoals(goalsResponse.data);
            }
        } catch (e) { console.log('No goals endpoint'); }
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

function updateDashboard(data) {
    // Activity selector
    if (data.event) {
        document.getElementById('event-selector-name').textContent = data.event.title || 'Activitate';

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

        // Days until activity
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
        let statusClass = 'bg-forest/10 text-forest';
        if (data.event.is_cancelled) {
            statusText = 'Anulat';
            statusClass = 'bg-vermilion/10 text-vermilion';
        } else if (data.event.is_past || daysUntil === 0 || (typeof daysUntil === 'number' && daysUntil < 0)) {
            statusText = 'Încheiat';
            statusClass = 'bg-ink/10 text-ink-soft';
        } else if (data.event.is_sold_out) {
            statusText = 'Sold Out';
            statusClass = 'bg-ochre/10 text-ochre';
        }
        document.getElementById('stat-event-status').textContent = statusText;
        document.getElementById('stat-event-status').className = `rounded-full px-2 py-1 text-xs font-bold ${statusClass}`;
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
        'campaign_tiktok': '#1B1714',
        'campaign_instagram': '#e4405f',
        'campaign_other': '#5A4F46',
        'email': '#DA9A33',
        'price': '#1E4A3D',
        'announcement': '#2C5F8A',
        'press': '#2C5F8A',
        'lineup': '#E84527',
        'custom': '#5A4F46',
    };
    return colors[type] || '#5A4F46';
}

function updateMainChart(chartData) {
    const colors = [];
    const series = [];
    const yaxisConfigs = [];

    if (chartMetrics.revenue) {
        series.push({ name: 'Venituri', data: chartData.revenue || [] });
        colors.push('#1E4A3D');
        yaxisConfigs.push({
            seriesName: 'Venituri',
            min: 0,
            forceNiceScale: true,
            title: { text: 'Venituri (RON)' },
            labels: { formatter: v => formatNumber(v) }
        });
    }
    if (chartMetrics.tickets) {
        series.push({ name: 'Bilete', data: chartData.tickets || [] });
        colors.push('#2C5F8A');
        yaxisConfigs.push({
            seriesName: 'Bilete',
            min: 0,
            forceNiceScale: true,
            opposite: yaxisConfigs.length > 0,
            title: { text: 'Bilete' },
            labels: { formatter: v => formatNumber(v) }
        });
    }
    if (chartMetrics.views) {
        series.push({ name: 'Vizualizări', data: chartData.views || chartData.page_views || [] });
        colors.push('#DA9A33');
        yaxisConfigs.push({
            seriesName: 'Vizualizări',
            min: 0,
            forceNiceScale: true,
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
            type: 'bar',
            height: 300,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
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
        grid: { borderColor: '#E8DFCF' },
        plotOptions: {
            bar: {
                columnWidth: '60%',
                borderRadius: 4
            }
        },
        annotations: {
            xaxis: milestoneAnnotations
        },
        xaxis: {
            categories: chartData.labels || [],
            labels: { style: { colors: '#5A4F46', fontSize: '11px' } }
        },
        yaxis: yaxisConfigs.length > 0 ? yaxisConfigs : [{}],
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (val, { seriesIndex }) => {
                    const name = series[seriesIndex]?.name || '';
                    if (name === 'Venituri') return formatCurrency(val);
                    if (name === 'Bilete') return formatNumber(val) + (val === 1 ? ' bilet' : ' bilete');
                    if (name === 'Vizualizări') return formatNumber(val) + (val === 1 ? ' vizualizare' : ' vizualizări');
                    return formatNumber(val);
                }
            }
        },
        colors: colors,
        legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: '#5A4F46' } }
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
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">Buget:</span><span class="font-bold text-ink">${formatCurrency(milestone.budget)}</span></div>`;
    }
    if (milestone.impressions) {
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">Impresii:</span><span class="font-bold text-ink">${formatNumber(milestone.impressions)}</span></div>`;
    }
    if (milestone.clicks) {
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">Click-uri:</span><span class="font-bold text-ink">${formatNumber(milestone.clicks)}</span></div>`;
    }
    if (milestone.conversions) {
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">Conversii:</span><span class="font-bold text-ink">${formatNumber(milestone.conversions)}</span></div>`;
    }
    if (milestone.attributed_revenue && milestone.attributed_revenue > 0) {
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">Venituri atribuite:</span><span class="font-bold text-forest">${formatCurrency(milestone.attributed_revenue)}</span></div>`;
    }
    if (milestone.roas && milestone.roas > 0) {
        detailsHtml += `<div class="flex justify-between"><span class="text-ink-soft">ROAS:</span><span class="font-bold text-ink">${milestone.roas.toFixed(2)}x</span></div>`;
    }
    if (milestone.description) {
        detailsHtml += `<div class="mt-2 text-xs text-ink-soft">${milestone.description}</div>`;
    }
    document.getElementById('milestone-tooltip-details').innerHTML = detailsHtml || '<div class="text-xs text-ink-soft">Fără detalii suplimentare</div>';

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

    // Get appropriate colors for each metric (full class strings — CSS is prebuilt)
    const activeStyles = {
        revenue: { border: 'border-forest/30', bg: 'bg-forest/10' },
        tickets: { border: 'border-sky/30', bg: 'bg-sky/10' },
        views: { border: 'border-ochre/30', bg: 'bg-ochre/10' }
    };
    const style = activeStyles[metric] || { border: 'border-ink/15', bg: '' };

    if (chartMetrics[metric]) {
        btn.classList.add('active', style.border, style.bg);
        btn.classList.remove('border-ink/15');
        btn.style.opacity = '1';
    } else {
        btn.classList.remove('active', style.border, style.bg);
        btn.classList.add('border-ink/15');
        btn.style.opacity = '0.5';
    }
    if (eventData?.chart) {
        updateMainChart(eventData.chart);
    }
}

function updateForecast(overview) {
    const daysRemaining = overview.days_until || 7;
    const chart = eventData?.chart;

    // Use actual chart data for daily averages instead of dividing by arbitrary number
    let avgDailyRevenue = 0;
    let avgDailyTickets = 0;

    if (chart && chart.revenue && chart.revenue.length > 0) {
        // Only count days that have actually passed (have data)
        const daysWithData = chart.revenue.length;
        const totalChartRevenue = chart.revenue.reduce((sum, v) => sum + (v || 0), 0);
        const totalChartTickets = chart.tickets ? chart.tickets.reduce((sum, v) => sum + (v || 0), 0) : 0;

        if (daysWithData > 0) {
            // Weight recent days more heavily (last 7 days get 2x weight)
            const recentDays = Math.min(7, daysWithData);
            const recentRevenue = chart.revenue.slice(-recentDays).reduce((sum, v) => sum + (v || 0), 0);
            const recentTickets = chart.tickets ? chart.tickets.slice(-recentDays).reduce((sum, v) => sum + (v || 0), 0) : 0;

            if (recentDays < daysWithData && totalChartRevenue > 0) {
                // Blend: 60% recent trend, 40% overall average
                avgDailyRevenue = (recentRevenue / recentDays) * 0.6 + (totalChartRevenue / daysWithData) * 0.4;
                avgDailyTickets = (recentTickets / recentDays) * 0.6 + (totalChartTickets / daysWithData) * 0.4;
            } else {
                avgDailyRevenue = totalChartRevenue / daysWithData;
                avgDailyTickets = totalChartTickets / daysWithData;
            }
        }
    } else if (overview.total_revenue > 0) {
        // Fallback: if no chart data, use activity creation to now
        const createdAt = eventData?.event?.created_at;
        const daysSinceCreation = createdAt
            ? Math.max(1, Math.ceil((Date.now() - new Date(createdAt)) / (1000 * 60 * 60 * 24)))
            : 1;
        avgDailyRevenue = overview.total_revenue / daysSinceCreation;
        avgDailyTickets = overview.tickets_sold / daysSinceCreation;
    }

    document.getElementById('forecast-revenue').textContent = formatCurrency(avgDailyRevenue * 7);
    document.getElementById('forecast-tickets').textContent = '+' + Math.round(avgDailyTickets * 7);
    document.getElementById('forecast-total-revenue').textContent = formatCurrency(overview.total_revenue + avgDailyRevenue * daysRemaining);
    document.getElementById('forecast-total-tickets').textContent = formatNumber(overview.tickets_sold + Math.round(avgDailyTickets * daysRemaining));
}

function updateTicketTypes(tickets) {
    const tbody = document.getElementById('ticket-types-table');
    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-ink-soft">Nu există tipuri de bilete</td></tr>';
        return;
    }

    const colors = ['#1E4A3D', '#2C5F8A', '#DA9A33', '#E84527', '#5A4F46'];
    const html = tickets.map((t, i) => `
        <tr class="border-b border-ink/5">
            <td class="py-3">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-6 rounded-full" style="background: ${colors[i % colors.length]}"></div>
                    <span class="text-sm font-bold text-ink">${t.name}</span>
                </div>
            </td>
            <td class="py-3 text-sm text-right text-ink-soft">${formatCurrency(t.price)}</td>
            <td class="py-3 text-sm font-bold text-right text-ink">${formatNumber(t.sold || 0)}</td>
            <td class="py-3 text-sm font-bold text-right text-ink">${formatCurrency(t.revenue || t.price * (t.sold || 0))}</td>
            <td class="py-3 text-sm font-bold text-right ${(t.conversion_rate || 0) >= 4 ? 'text-forest' : 'text-ink-soft'}">${(t.conversion_rate || 0).toFixed(1)}%</td>
            <td class="py-3 text-right">
                <span class="text-xs font-bold ${(t.trend || 0) >= 0 ? 'text-forest' : 'text-vermilion'}">
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
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu există date despre trafic</div>';
        return;
    }

    const icons = {
        'Facebook': '📘', 'Google': '🔍', 'Instagram': '📷', 'TikTok': '🎵',
        'Direct': '🔗', 'Email': '📧', 'Other': '🌐'
    };
    const colors = {
        'Facebook': '#1877f2', 'Google': '#ea4335', 'Instagram': '#e4405f',
        'TikTok': '#1B1714', 'Direct': '#5A4F46', 'Email': '#DA9A33', 'Other': '#1E4A3D'
    };

    const total = sources.reduce((sum, s) => sum + (s.visitors || 0), 0);

    const html = sources.map(s => {
        const percent = total > 0 ? Math.round((s.visitors / total) * 100) : 0;
        const color = colors[s.source] || colors['Other'];
        const icon = icons[s.source] || icons['Other'];
        return `
        <div class="flex items-center gap-4 p-3 transition-colors rounded-xl hover:bg-paper-2">
            <div class="grid h-10 w-10 place-items-center rounded-xl" style="background: ${color}22">
                <span class="text-lg">${icon}</span>
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-bold text-ink">${s.source || 'Direct'}</span>
                    <span class="text-sm font-bold text-ink">${formatNumber(s.visitors)}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 overflow-hidden bg-ink/10 rounded-full">
                        <div class="h-full rounded-full" style="width: ${percent}%; background: ${color}"></div>
                    </div>
                    <span class="w-10 text-xs text-ink-soft">${percent}%</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold text-ink">${formatCurrency(s.revenue || 0)}</div>
                <div class="text-xs text-ink-soft">${s.conversions || 0} vânzări</div>
            </div>
        </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateLocations(locations) {
    const container = document.getElementById('locations-list');
    if (!locations || locations.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu există date despre locații</div>';
        return;
    }

    const html = locations.slice(0, 6).map((l, i) => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <span class="grid h-6 w-6 place-items-center rounded-lg bg-ink/10 text-xs font-bold text-ink-soft">${i + 1}</span>
                <span class="text-sm font-bold text-ink">${l.city || l.country || 'Necunoscut'}</span>
            </div>
            <div class="text-right">
                <span class="text-sm font-bold text-ink">${formatNumber(l.visitors || l.count || 0)}</span>
                <span class="ml-1 text-xs text-ink-soft">vizite</span>
            </div>
        </div>
    `).join('');
    container.innerHTML = html;
}

function updateCampaigns(data) {
    const container = document.getElementById('campaigns-list');
    const campaigns = data.milestones || data;

    if (!campaigns || campaigns.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu ai campanii active</div>';
        return;
    }

    const html = campaigns.map(c => {
        const roi = c.budget > 0 ? Math.round(((c.attributed_revenue || 0) - c.budget) / c.budget * 100) : 0;
        return `
        <div class="milestone-card p-3 transition-colors rounded-xl border-2 border-ink/10 hover:border-vermilion">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span>${c.type_icon || '📌'}</span>
                    <span class="text-sm font-bold text-ink">${c.title || c.name || 'Campanie'}</span>
                </div>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold ${c.is_active ? 'bg-forest/10 text-forest' : 'bg-ink/10 text-ink-soft'}">
                    ${c.is_active ? 'Activ' : 'Încheiat'}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div><span class="text-ink-soft">Buget:</span> <span class="font-bold text-ink">${formatCurrency(c.budget || 0)}</span></div>
                <div><span class="text-ink-soft">Venituri:</span> <span class="font-bold text-forest">${formatCurrency(c.attributed_revenue || 0)}</span></div>
                <div><span class="text-ink-soft">Conv.:</span> <span class="font-bold text-ink">${c.conversions || 0}</span></div>
                <div><span class="text-ink-soft">ROI:</span> <span class="font-bold ${roi >= 0 ? 'text-forest' : 'text-vermilion'}">${roi >= 0 ? '+' : ''}${roi}%</span></div>
            </div>
        </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateRecentSales(sales) {
    const container = document.getElementById('recent-sales');
    if (!sales || sales.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu există vânzări recente</div>';
        return;
    }

    const html = sales.slice(0, 8).map(s => `
        <div class="flex items-center justify-between py-3 border-b border-ink/10 last:border-b-0">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-full bg-vermilion text-sm font-bold text-paper">
                    ${(s.buyer_name || 'C').charAt(0).toUpperCase()}
                </div>
                <div>
                    <p class="text-sm font-bold text-ink">${s.buyer_name || 'Client'}</p>
                    <p class="text-xs text-ink-soft">${s.time_ago || s.created_at || ''}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-forest">+${formatCurrency(s.amount || 0)}</p>
                <p class="text-xs text-ink-soft">${s.tickets || 1} bilet(e)</p>
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
            <div class="py-6 text-center text-sm text-ink-soft">
                <svg class="mx-auto mb-2 h-10 w-10 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                Setează obiective pentru a urmări progresul
            </div>
        `;
        return;
    }

    const typeLabels = { 'revenue': 'Venituri', 'tickets': 'Bilete', 'visitors': 'Vizitatori', 'conversion_rate': 'Conversie' };
    const html = goals.map(g => {
        const progress = Math.min(g.progress_percent || 0, 100);
        const progressColor = progress >= 100 ? 'bg-forest' : progress >= 75 ? 'bg-sky' : progress >= 50 ? 'bg-ochre' : 'bg-vermilion';
        return `
        <div class="p-4 rounded-xl border-2 border-ink/10">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold text-ink">${g.name || typeLabels[g.type] || g.type}</span>
            </div>
            <div class="flex items-baseline gap-1 mb-2">
                <span class="text-xl font-bold text-ink">${g.formatted_current || 0}</span>
                <span class="text-sm text-ink-soft">/ ${g.formatted_target || 0}</span>
            </div>
            <div class="h-2 overflow-hidden bg-ink/10 rounded-full">
                <div class="h-full transition-all rounded-full ${progressColor}" style="width: ${progress}%"></div>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-xs text-ink-soft">${progress.toFixed(1)}%</span>
                ${g.days_remaining > 0 ? `<span class="text-xs text-ink-soft">${g.days_remaining} zile rămase</span>` : ''}
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
    el.className = `flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold ${isPositive ? 'bg-forest/10 text-forest' : 'bg-vermilion/10 text-vermilion'}`;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value || 0) + ' lei';
}

function formatNumber(value) {
    return new Intl.NumberFormat('ro-RO').format(value || 0);
}

function exportReport() {
    try {
        const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
        const base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
        const p = new URLSearchParams();
        p.set('action', 'organizer.event.analytics.export');
        p.set('event_id', eventId);
        p.set('period', currentPeriod);
        if (token) p.set('token', token);
        window.open(base + '?' + p.toString(), '_blank');
    } catch (e) {
        orgNotify('Eroare la export', 'error');
    }
}

// Activity dropdown functions
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
        const response = await BileteOnlineAPI.get('/organizer/events?per_page=50');
        if (response.success) {
            const events = response.data?.events || response.data || [];
            renderEventsList(events);
            eventsListLoaded = true;
        }
    } catch (error) {
        console.error('Error loading events:', error);
        document.getElementById('event-dropdown-loading').innerHTML = '<div class="py-4 text-center text-sm text-vermilion">Eroare la încărcare</div>';
    }
}

function renderEventsList(events) {
    const loadingEl = document.getElementById('event-dropdown-loading');
    const listEl = document.getElementById('event-dropdown-list');

    loadingEl.classList.add('hidden');
    listEl.classList.remove('hidden');

    if (!events || events.length === 0) {
        listEl.innerHTML = '<div class="py-4 text-center text-sm text-ink-soft">Nu ai activități</div>';
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

        // Get activity name - API returns 'name' not 'title'
        const eventName = e.name || e.title || 'Activitate';

        return `
            <a href="/organizator/analytics/${e.id}" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-paper-2 ${isActive ? 'bg-vermilion/5 border-l-2 border-vermilion' : ''}">
                <div class="flex-shrink-0 w-10 h-10 overflow-hidden bg-ink/10 rounded-lg">
                    ${e.image ? `<img src="${fixImageUrl(e.image)}" class="object-cover w-full h-full">` : '<div class="flex items-center justify-center w-full h-full text-xs text-ink-soft bg-ink/10">📅</div>'}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-ink truncate">${eventName}</div>
                    <div class="text-[11px] text-ink-soft truncate">${dateStr}${locationStr ? ' • ' + locationStr : ''}</div>
                </div>
                ${isActive ? '<svg class="flex-shrink-0 w-4 h-4 text-vermilion" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : ''}
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
            const response = await BileteOnlineAPI.get(`/organizer/events/${eventId}/analytics?period=1d`);
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
                <div class="grid h-8 w-8 place-items-center rounded-full bg-paper-2 text-lg">🌍</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm truncate text-ink">Niciun vizitator live</div>
                    <div class="text-xs text-ink-soft">-</div>
                </div>
            </div>
        `;
        topLocationsEl.innerHTML = '<div class="py-2 text-center text-sm text-ink-soft">Fără date</div>';
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
        const action = `A vizualizat pagina activității`;
        const city = l.city || 'Necunoscut';
        const country = l.country || 'RO';
        return `
            <div class="flex items-center gap-3 p-2 transition-colors rounded-lg hover:bg-paper-2">
                <div class="grid h-8 w-8 place-items-center rounded-full bg-paper-2 text-lg">${flag}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm truncate text-ink">${action}</div>
                    <div class="text-xs text-ink-soft">${city}, ${country}</div>
                </div>
                <div class="text-xs text-ink-soft">acum</div>
            </div>
        `;
    }).join('');
    activityEl.innerHTML = activityHtml || '<div class="py-2 text-center text-sm text-ink-soft">Fără activitate</div>';

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
                    <span class="text-sm text-ink-soft">${l.city || 'Necunoscut'}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-16 h-1.5 bg-ink/10 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-forest" style="width: ${percent}%"></div>
                    </div>
                    <span class="w-8 text-xs text-right text-ink-soft">${count}</span>
                </div>
            </div>
        `;
    }).join('');
    topLocationsEl.innerHTML = topHtml || '<div class="py-2 text-center text-sm text-ink-soft">Fără date</div>';
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

        // Add CartoDB dark matter tile layer
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
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
                fillColor: loc.isLive ? '#1E4A3D' : '#E84527',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: loc.isLive ? 0.8 : 0.6,
                className: loc.isLive ? 'live-marker-pulse' : ''
            }).addTo(map);

            const popupContent = loc.isLive
                ? `<b>${loc.city}</b><br><span style="color:#1E4A3D">Online acum</span>`
                : `<b>${loc.city}</b><br>${loc.visitors} bilete vândute`;
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
    // Set max date for deadline based on activity date
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
        const response = await BileteOnlineAPI.post(`/organizer/events/${eventId}/goals`, data);
        if (response.success) { closeGoalModal(); loadAnalytics(); }
    } catch (error) { console.error('Error saving goal:', error); orgNotify('Eroare la salvarea obiectivului', 'error'); }
}

// Milestone modal functions
const AD_CAMPAIGN_TYPES = ['facebook_ads', 'google_ads', 'instagram_ads', 'tiktok_ads', 'influencer'];

function showAddMilestoneModal() {
    document.getElementById('milestone-modal').classList.remove('hidden');
    updateMilestoneFields('facebook_ads'); // Default to ad fields
    // Set date restrictions based on activity dates
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
    const startDateValue = form.start_date.value;
    const endDateValue = form.end_date.value;

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
        const response = await BileteOnlineAPI.post(`/organizer/events/${eventId}/milestones`, data);
        if (response.success) { closeMilestoneModal(); loadAnalytics(); }
    } catch (error) { console.error('Error saving milestone:', error); orgNotify('Eroare la salvarea campaniei', 'error'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
