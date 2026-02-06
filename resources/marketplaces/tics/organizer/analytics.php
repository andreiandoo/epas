<?php
/**
 * TICS Organizer Analytics - Event Performance Dashboard
 * Enhanced analytics with real-time data visualization
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Get event ID from URL
$eventId = isset($_GET['event']) ? htmlspecialchars($_GET['event']) : 'coldplay-2026';

// Demo event data
$event = [
    'id' => 'coldplay-2026',
    'name' => 'Coldplay: Music of the Spheres',
    'date' => '2026-02-14',
    'venue' => 'Arena Nationala',
    'city' => 'Bucuresti',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=600&h=300&fit=crop',
    'status' => 'on_sale',
    'capacity' => 55000,
];

// Demo analytics data
$stats = [
    'revenue' => 2847650,
    'revenue_change' => 23.5,
    'tickets_sold' => 18432,
    'tickets_today' => 127,
    'total_capacity' => 55000,
    'views' => 142850,
    'unique_views' => 89420,
    'conversion_rate' => 4.8,
    'days_until' => 42,
];

$ticketTypes = [
    ['name' => 'General Admission', 'price' => 149, 'sold' => 12500, 'capacity' => 30000, 'revenue' => 1862500, 'trend' => 'up'],
    ['name' => 'VIP Experience', 'price' => 499, 'sold' => 2800, 'capacity' => 5000, 'revenue' => 1397200, 'trend' => 'up'],
    ['name' => 'Golden Circle', 'price' => 349, 'sold' => 3132, 'capacity' => 8000, 'revenue' => 1093068, 'trend' => 'stable'],
];

$trafficSources = [
    ['source' => 'Facebook Ads', 'visits' => 45230, 'conversions' => 2847, 'icon' => 'facebook'],
    ['source' => 'Google Search', 'visits' => 38420, 'conversions' => 1921, 'icon' => 'google'],
    ['source' => 'Direct', 'visits' => 28950, 'conversions' => 1738, 'icon' => 'direct'],
    ['source' => 'Instagram', 'visits' => 18200, 'conversions' => 728, 'icon' => 'instagram'],
];

$topLocations = [
    ['city' => 'Bucuresti', 'country' => 'RO', 'visitors' => 45820, 'percentage' => 32],
    ['city' => 'Cluj-Napoca', 'country' => 'RO', 'visitors' => 18340, 'percentage' => 13],
    ['city' => 'Timisoara', 'country' => 'RO', 'visitors' => 12450, 'percentage' => 9],
    ['city' => 'Iasi', 'country' => 'RO', 'visitors' => 9870, 'percentage' => 7],
    ['city' => 'Constanta', 'country' => 'RO', 'visitors' => 8230, 'percentage' => 6],
];

$recentSales = [
    ['buyer' => 'Maria P.', 'ticket' => 'VIP Experience', 'quantity' => 2, 'total' => 998, 'time' => '2 min'],
    ['buyer' => 'Ion A.', 'ticket' => 'General Admission', 'quantity' => 4, 'total' => 596, 'time' => '5 min'],
    ['buyer' => 'Elena M.', 'ticket' => 'Golden Circle', 'quantity' => 2, 'total' => 698, 'time' => '8 min'],
    ['buyer' => 'Andrei D.', 'ticket' => 'General Admission', 'quantity' => 1, 'total' => 149, 'time' => '12 min'],
];

// Current page for sidebar
$currentPage = 'events';

// Page config for head
$pageTitle = 'Analytics - ' . $event['name'];
$pageDescription = 'Performanta evenimentului in timp real';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Include ApexCharts and Leaflet for maps -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2); opacity: 0; } }
        .live-marker-pulse { animation: marker-pulse 2s ease-in-out infinite; }
        @keyframes marker-pulse { 0%, 100% { opacity: 0.8; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
    </style>

    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 lg:px-6 py-4">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </a>
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars($event['image']) ?>" class="w-12 h-12 rounded-xl object-cover" alt="">
                                <div>
                                    <h1 class="font-bold text-gray-900"><?= htmlspecialchars($event['name']) ?></h1>
                                    <p class="text-sm text-gray-500"><?= date('d M Y', strtotime($event['date'])) ?> - <?= htmlspecialchars($event['venue']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <!-- Period Selector -->
                            <div class="flex items-center p-1 bg-gray-100 rounded-xl">
                                <button class="period-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all text-gray-500" data-period="7d">7D</button>
                                <button class="period-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all bg-white shadow-sm text-gray-900" data-period="30d">30D</button>
                                <button class="period-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all text-gray-500" data-period="90d">90D</button>
                                <button class="period-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all text-gray-500" data-period="all">Tot</button>
                            </div>

                            <!-- Live Indicator -->
                            <div class="flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-xl">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-green-400"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                                </span>
                                <span class="text-sm font-medium text-green-700">247 online</span>
                            </div>

                            <!-- Globe Map Button -->
                            <button onclick="openGlobeModal()" class="flex items-center gap-2 px-3 py-2 text-sm font-medium border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors" title="Vezi trafic live pe harta">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="hidden sm:inline">Harta</span>
                            </button>

                            <!-- Export Button -->
                            <button onclick="exportReport()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Export
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <!-- Revenue -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                +<?= number_format($stats['revenue_change'], 1) ?>%
                            </span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['revenue']) ?> RON</div>
                        <div class="text-xs text-gray-500 mt-1">Venituri totale</div>
                        <div class="flex items-center gap-2 mt-3">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-green-400 to-green-500 rounded-full" style="width: <?= round(($stats['revenue'] / 5000000) * 100) ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tickets Sold -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">+<?= number_format($stats['tickets_today']) ?> azi</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['tickets_sold']) ?></div>
                        <div class="text-xs text-gray-500 mt-1">Bilete vandute</div>
                        <div class="flex items-center gap-2 mt-3">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-400 to-blue-500 rounded-full" style="width: <?= round(($stats['tickets_sold'] / $stats['total_capacity']) * 100) ?>%"></div>
                            </div>
                            <span class="text-[10px] text-gray-400"><?= round(($stats['tickets_sold'] / $stats['total_capacity']) * 100) ?>%</span>
                        </div>
                    </div>

                    <!-- Views -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-cyan-600 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['views']) ?></div>
                        <div class="text-xs text-gray-500 mt-1">Vizualizari totale</div>
                        <div class="text-[11px] text-gray-400 mt-3"><?= number_format($stats['unique_views']) ?> vizitatori unici</div>
                    </div>

                    <!-- Conversion Rate -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['conversion_rate'], 1) ?>%</div>
                        <div class="text-xs text-gray-500 mt-1">Rata conversie</div>
                        <div class="text-[11px] text-gray-400 mt-3">Vizite → Achizitii</div>
                    </div>

                    <!-- Days Until Event -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-rose-400 to-pink-600 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">In vanzare</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?= $stats['days_until'] ?> zile</div>
                        <div class="text-xs text-gray-500 mt-1">Pana la eveniment</div>
                        <div class="text-[11px] text-gray-400 mt-3"><?= date('d M Y', strtotime($event['date'])) ?></div>
                    </div>
                </div>

                <!-- Chart + Forecast -->
                <div class="grid lg:grid-cols-3 gap-6 mb-6">
                    <!-- Main Chart -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Performanta vanzari</h2>
                                <p class="text-xs text-gray-500">Click pentru a comuta metricile</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleChartMetric('revenue')" class="chart-metric-btn active flex items-center gap-2 px-3 py-1.5 rounded-lg border border-green-200 bg-green-50 transition-all" data-metric="revenue">
                                    <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                                    <span class="text-xs font-medium text-green-600">Venituri</span>
                                </button>
                                <button onclick="toggleChartMetric('tickets')" class="chart-metric-btn active flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 transition-all" data-metric="tickets">
                                    <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                                    <span class="text-xs font-medium text-blue-600">Bilete</span>
                                </button>
                                <button onclick="toggleChartMetric('views')" class="chart-metric-btn flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 transition-all" data-metric="views">
                                    <div class="w-2.5 h-2.5 rounded-full bg-cyan-500"></div>
                                    <span class="text-xs font-medium text-gray-500">Vizualizari</span>
                                </button>
                            </div>
                        </div>
                        <div id="mainChart" class="h-[300px]"></div>
                    </div>

                    <!-- Forecast -->
                    <div class="bg-gradient-to-br from-indigo-900 to-purple-900 rounded-2xl p-6 text-white">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold">Estimari AI</h2>
                                <p class="text-xs text-white/60">Predictii bazate pe trend</p>
                            </div>
                        </div>

                        <div class="bg-white/10 rounded-xl p-4 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-white/80">Urmatoarele 7 zile</span>
                                <span class="text-xs px-2 py-0.5 bg-green-500/30 text-green-300 rounded-full">Trend pozitiv</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xl font-bold">+285.400 RON</div>
                                    <div class="text-xs text-white/50">Venituri estimate</div>
                                </div>
                                <div>
                                    <div class="text-xl font-bold">+892</div>
                                    <div class="text-xs text-white/50">Bilete estimate</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white/10 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-white/80">La final</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xl font-bold">4.2M RON</div>
                                    <div class="text-xs text-white/50">Total estimat</div>
                                </div>
                                <div>
                                    <div class="text-xl font-bold">28.500</div>
                                    <div class="text-xs text-white/50">Bilete total</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-white/10">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-xs text-white/50">Bazat pe vanzarile din ultimele 30 zile</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Performance + Campaigns -->
                <div class="grid lg:grid-cols-3 gap-6 mb-6">
                    <!-- Ticket Performance Table -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Performanta tipuri bilete</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="pb-3 text-xs font-medium text-left text-gray-500 uppercase">Tip</th>
                                        <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Pret</th>
                                        <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Vandute</th>
                                        <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Venituri</th>
                                        <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Progres</th>
                                        <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketTypes as $ticket): ?>
                                    <tr class="border-b border-gray-50">
                                        <td class="py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900"><?= htmlspecialchars($ticket['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 text-right text-sm text-gray-600"><?= number_format($ticket['price']) ?> RON</td>
                                        <td class="py-4 text-right text-sm font-medium text-gray-900"><?= number_format($ticket['sold']) ?></td>
                                        <td class="py-4 text-right text-sm font-medium text-gray-900"><?= number_format($ticket['revenue']) ?> RON</td>
                                        <td class="py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                    <div class="h-full bg-indigo-500 rounded-full" style="width: <?= round(($ticket['sold'] / $ticket['capacity']) * 100) ?>%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500"><?= round(($ticket['sold'] / $ticket['capacity']) * 100) ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-4 text-right">
                                            <?php if ($ticket['trend'] === 'up'): ?>
                                            <span class="inline-flex items-center gap-1 text-green-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                            </span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Campaigns ROI -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Campanii ROI</h2>
                            <button onclick="showAddMilestoneModal()" class="text-sm font-medium text-indigo-600 hover:underline">+ Adauga</button>
                        </div>
                        <div class="space-y-3">
                            <div class="p-4 bg-green-50 border border-green-200 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">Facebook Ads</span>
                                    <span class="text-sm font-bold text-green-600">+285% ROI</span>
                                </div>
                                <div class="text-xs text-gray-500">Cost: 2.400 RON → Venit: 8.840 RON</div>
                            </div>
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">Google Ads</span>
                                    <span class="text-sm font-bold text-blue-600">+142% ROI</span>
                                </div>
                                <div class="text-xs text-gray-500">Cost: 1.850 RON → Venit: 4.480 RON</div>
                            </div>
                            <div class="p-4 bg-purple-50 border border-purple-200 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">Instagram Story</span>
                                    <span class="text-sm font-bold text-purple-600">+98% ROI</span>
                                </div>
                                <div class="text-xs text-gray-500">Cost: 800 RON → Venit: 1.584 RON</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Traffic Sources + Top Locations -->
                <div class="grid lg:grid-cols-2 gap-6 mb-6">
                    <!-- Traffic Sources -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Surse de trafic</h2>
                        <div class="space-y-3">
                            <?php foreach ($trafficSources as $source): ?>
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <?php if ($source['icon'] === 'facebook'): ?>
                                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                    <?php elseif ($source['icon'] === 'google'): ?>
                                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                    <?php elseif ($source['icon'] === 'instagram'): ?>
                                    <svg class="w-5 h-5 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    <?php else: ?>
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($source['source']) ?></span>
                                        <span class="text-sm text-gray-500"><?= number_format($source['visits']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?= round(($source['visits'] / $trafficSources[0]['visits']) * 100) ?>%"></div>
                                        </div>
                                        <span class="text-xs text-green-600"><?= number_format($source['conversions']) ?> conv.</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Top Locations -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Locatii top</h2>
                            <button onclick="openGlobeModal()" class="text-sm font-medium text-indigo-600 hover:underline">Vezi harta</button>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($topLocations as $location): ?>
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-lg">
                                    <?= $location['country'] === 'RO' ? '🇷🇴' : '🌍' ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($location['city']) ?></span>
                                        <span class="text-sm text-gray-500"><?= number_format($location['visitors']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?= $location['percentage'] ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-400"><?= $location['percentage'] ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales + Goals -->
                <div class="grid lg:grid-cols-3 gap-6">
                    <!-- Recent Sales -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Vanzari recente</h2>
                            <a href="/organizator/participanti" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toate →</a>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($recentSales as $sale): ?>
                            <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-bold text-indigo-600"><?= strtoupper(substr($sale['buyer'], 0, 1)) ?></span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($sale['buyer']) ?></span>
                                        <span class="text-sm font-bold text-gray-900"><?= number_format($sale['total']) ?> RON</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span><?= $sale['quantity'] ?>x <?= htmlspecialchars($sale['ticket']) ?></span>
                                        <span>acum <?= $sale['time'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Goals -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Obiective</h2>
                            <button onclick="showAddGoalModal()" class="text-sm font-medium text-indigo-600 hover:underline">+ Adauga</button>
                        </div>
                        <div class="space-y-4">
                            <!-- Goal 1 -->
                            <div class="p-4 border border-gray-200 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">Vanzari Luna Ianuarie</span>
                                    <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">In progres</span>
                                </div>
                                <div class="mb-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>2.8M / 4M RON</span>
                                        <span>70%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full" style="width: 70%"></div>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400">Deadline: 31 Ian 2026</div>
                            </div>

                            <!-- Goal 2 -->
                            <div class="p-4 border border-green-200 bg-green-50 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">15.000 bilete</span>
                                    <span class="text-xs px-2 py-0.5 bg-green-200 text-green-700 rounded-full">Atins!</span>
                                </div>
                                <div class="mb-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>18.432 / 15.000</span>
                                        <span>122%</span>
                                    </div>
                                    <div class="h-2 bg-green-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-green-400 to-green-500 rounded-full" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div class="text-xs text-green-600">Obiectiv depasit cu 23%!</div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Goal Modal -->
    <div id="goal-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeGoalModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">Adauga obiectiv</h2>
                        <button onclick="closeGoalModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form id="goal-form" onsubmit="saveGoal(event)" class="p-6 space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Tip obiectiv</label>
                        <select name="type" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required>
                            <option value="revenue">Venituri (RON)</option>
                            <option value="tickets">Bilete vandute</option>
                            <option value="visitors">Vizitatori</option>
                            <option value="conversion_rate">Rata conversie (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Nume obiectiv</label>
                        <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required placeholder="ex: Obiectiv vanzari luna Ianuarie">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Valoare tinta</label>
                        <input type="number" name="target_value" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required min="0" step="any">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Deadline (optional)</label>
                        <input type="date" name="deadline" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeGoalModal()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">Anuleaza</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors">Salveaza</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Milestone/Campaign Modal -->
    <div id="milestone-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeMilestoneModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4 overflow-y-auto">
            <div class="w-full max-w-lg bg-white shadow-2xl rounded-2xl my-8" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">Adauga campanie</h2>
                        <button onclick="closeMilestoneModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form id="milestone-form" onsubmit="saveMilestone(event)" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Nume campanie</label>
                        <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required placeholder="ex: Campanie Facebook Ads">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Tip</label>
                        <select name="type" onchange="updateMilestoneFields(this.value)" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required>
                            <option value="facebook_ads">Facebook Ads</option>
                            <option value="google_ads">Google Ads</option>
                            <option value="instagram_ads">Instagram Ads</option>
                            <option value="tiktok_ads">TikTok Ads</option>
                            <option value="email_campaign">Email Campaign</option>
                            <option value="price_change">Schimbare Pret</option>
                            <option value="announcement">Anunt</option>
                            <option value="influencer">Influencer</option>
                            <option value="other">Altele</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Data start</label>
                            <input type="date" name="start_date" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Data sfarsit</label>
                            <input type="date" name="end_date" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                    </div>

                    <!-- Ad Campaign Fields -->
                    <div id="milestone-ad-fields" class="space-y-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Buget (RON)</label>
                            <input type="number" name="budget" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="p-4 border rounded-xl border-gray-200 bg-gray-50">
                            <h4 class="mb-3 text-sm font-medium text-gray-700">Parametri UTM</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block mb-1 text-xs text-gray-500">UTM Source</label>
                                    <input type="text" name="utm_source" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg" placeholder="facebook">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs text-gray-500">UTM Medium</label>
                                    <input type="text" name="utm_medium" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg" placeholder="cpc">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs text-gray-500">UTM Campaign</label>
                                    <input type="text" name="utm_campaign" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg" placeholder="summer-sale">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs text-gray-500">UTM Content</label>
                                    <input type="text" name="utm_content" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg" placeholder="banner-1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Campaign Fields -->
                    <div id="milestone-other-fields" class="hidden space-y-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Descriere</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2 text-sm border border-gray-200 rounded-xl resize-none" placeholder="Descrieti campania sau actiunea..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeMilestoneModal()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">Anuleaza</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors">Salveaza</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Globe/Map Modal -->
    <div id="globe-modal" class="fixed inset-0 hidden" style="z-index: 99999;">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" onclick="closeGlobeModal()"></div>
        <div class="fixed top-4 left-4 right-4 bottom-4 bg-gray-50 rounded-3xl overflow-hidden shadow-2xl" style="z-index: 100000;">
            <!-- Full-screen Map Container -->
            <div id="globeMapContainer" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; min-height: 400px; background: #f8fafc;"></div>

            <!-- Header Overlay -->
            <div class="absolute top-0 left-0 right-0 p-6 bg-gradient-to-b from-gray-50 via-gray-50/80 to-transparent pointer-events-none" style="z-index: 1000;">
                <div class="flex items-center justify-between pointer-events-auto">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gray-800 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Vizitatori Live</h2>
                            <p class="text-sm text-gray-500">Activitate in timp real</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-3 px-4 py-2 bg-white rounded-xl shadow-sm border border-gray-200">
                            <span class="relative flex h-3 w-3">
                                <span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            <span id="globe-live-count" class="text-lg font-bold text-gray-800">247</span>
                            <span class="text-sm text-gray-500">online acum</span>
                        </div>
                        <button onclick="closeGlobeModal()" class="p-3 bg-white hover:bg-gray-100 rounded-xl shadow-sm border border-gray-200 transition-colors">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Activity Panel (Bottom Left) -->
            <div class="absolute bottom-6 left-6 w-80 bg-white/95 backdrop-blur-xl rounded-2xl shadow-lg border border-gray-200 overflow-hidden" style="z-index: 1000;">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">Activitate Live</span>
                        <span class="text-xs text-gray-400">Ultimele 5 minute</span>
                    </div>
                </div>
                <div id="globe-live-activity" class="p-2 max-h-64 overflow-y-auto">
                    <?php foreach ($topLocations as $loc): ?>
                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-lg"><?= $loc['country'] === 'RO' ? '🇷🇴' : '🌍' ?></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-800 truncate">A vizualizat evenimentul</div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($loc['city']) ?>, <?= $loc['country'] ?></div>
                        </div>
                        <div class="text-xs text-gray-300">acum</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Locations Panel (Bottom Right) -->
            <div class="absolute bottom-6 right-6 w-64 bg-white/95 backdrop-blur-xl rounded-2xl shadow-lg border border-gray-200 p-4" style="z-index: 1000;">
                <div class="text-sm font-semibold text-gray-800 mb-3">Locatii Top</div>
                <div id="globe-top-locations" class="space-y-2">
                    <?php foreach (array_slice($topLocations, 0, 5) as $loc): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span><?= $loc['country'] === 'RO' ? '🇷🇴' : '🌍' ?></span>
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($loc['city']) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-green-500 rounded-full" style="width: <?= $loc['percentage'] ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-400 w-8 text-right"><?= number_format($loc['visitors'] / 1000, 1) ?>K</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart data
        const chartData = {
            dates: ['1 Ian', '5 Ian', '10 Ian', '15 Ian', '20 Ian', '25 Ian', '30 Ian'],
            revenue: [185000, 245000, 312000, 425000, 520000, 680000, 847650],
            tickets: [1250, 1680, 2150, 2890, 3520, 4580, 5782],
            views: [12500, 18200, 24800, 32500, 42000, 52800, 62850]
        };

        let mainChart;
        let chartMetrics = { revenue: true, tickets: true, views: false };

        // Initialize chart
        function initChart() {
            const options = {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: { show: false },
                    animations: { enabled: true, speed: 500 },
                    fontFamily: 'Inter, sans-serif',
                },
                series: getSeries(),
                xaxis: {
                    categories: chartData.dates,
                    labels: { style: { colors: '#9ca3af', fontSize: '11px' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: [
                    {
                        title: { text: 'Venituri (RON)', style: { color: '#10b981', fontSize: '11px' } },
                        labels: {
                            style: { colors: '#9ca3af', fontSize: '11px' },
                            formatter: (val) => (val / 1000).toFixed(0) + 'K'
                        },
                        show: chartMetrics.revenue
                    },
                    {
                        opposite: true,
                        title: { text: 'Bilete', style: { color: '#3b82f6', fontSize: '11px' } },
                        labels: { style: { colors: '#9ca3af', fontSize: '11px' } },
                        show: chartMetrics.tickets
                    }
                ],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.4, opacityTo: 0.05 }
                },
                colors: ['#10b981', '#3b82f6', '#06b6d4'],
                legend: { show: false },
                grid: {
                    borderColor: '#f3f4f6',
                    strokeDashArray: 4,
                },
                tooltip: {
                    shared: true,
                    y: {
                        formatter: function(val, { seriesIndex }) {
                            if (seriesIndex === 0) return val.toLocaleString() + ' RON';
                            return val.toLocaleString();
                        }
                    }
                }
            };

            mainChart = new ApexCharts(document.querySelector('#mainChart'), options);
            mainChart.render();
        }

        function getSeries() {
            const series = [];
            if (chartMetrics.revenue) {
                series.push({ name: 'Venituri', type: 'area', data: chartData.revenue });
            }
            if (chartMetrics.tickets) {
                series.push({ name: 'Bilete', type: 'area', data: chartData.tickets });
            }
            if (chartMetrics.views) {
                series.push({ name: 'Vizualizari', type: 'area', data: chartData.views });
            }
            return series;
        }

        function toggleChartMetric(metric) {
            chartMetrics[metric] = !chartMetrics[metric];

            // Update button UI
            const btn = document.querySelector(`[data-metric="${metric}"]`);
            if (chartMetrics[metric]) {
                btn.classList.add('active');
                const colors = { revenue: 'green', tickets: 'blue', views: 'cyan' };
                btn.classList.add(`border-${colors[metric]}-200`, `bg-${colors[metric]}-50`);
                btn.classList.remove('border-gray-200');
                btn.querySelector('span').classList.remove('text-gray-500');
                btn.querySelector('span').classList.add(`text-${colors[metric]}-600`);
            } else {
                btn.classList.remove('active');
                btn.classList.remove('border-green-200', 'bg-green-50', 'border-blue-200', 'bg-blue-50', 'border-cyan-200', 'bg-cyan-50');
                btn.classList.add('border-gray-200');
                btn.querySelector('span').classList.add('text-gray-500');
                btn.querySelector('span').classList.remove('text-green-600', 'text-blue-600', 'text-cyan-600');
            }

            // Update chart
            mainChart.updateSeries(getSeries());
        }

        // Period selector with working date ranges
        let currentPeriod = '30d';
        const eventPublishDate = new Date('2025-12-01'); // Event publish date

        // Different demo data for different periods
        const periodData = {
            '7d': {
                dates: ['25 Ian', '26 Ian', '27 Ian', '28 Ian', '29 Ian', '30 Ian', '31 Ian'],
                revenue: [520000, 580000, 620000, 680000, 720000, 780000, 847650],
                tickets: [3520, 3920, 4180, 4580, 4850, 5280, 5782],
                views: [42000, 45800, 48200, 52800, 56000, 59400, 62850]
            },
            '30d': {
                dates: ['1 Ian', '5 Ian', '10 Ian', '15 Ian', '20 Ian', '25 Ian', '30 Ian'],
                revenue: [185000, 245000, 312000, 425000, 520000, 680000, 847650],
                tickets: [1250, 1680, 2150, 2890, 3520, 4580, 5782],
                views: [12500, 18200, 24800, 32500, 42000, 52800, 62850]
            },
            '90d': {
                dates: ['1 Nov', '15 Nov', '1 Dec', '15 Dec', '1 Ian', '15 Ian', '30 Ian'],
                revenue: [0, 45000, 125000, 285000, 520000, 680000, 847650],
                tickets: [0, 320, 850, 1890, 3520, 4580, 5782],
                views: [5000, 12200, 24800, 45500, 78000, 102800, 142850]
            },
            'all': {
                dates: ['1 Dec', '15 Dec', '1 Ian', '10 Ian', '20 Ian', '25 Ian', '30 Ian'],
                revenue: [125000, 285000, 420000, 520000, 680000, 780000, 847650],
                tickets: [850, 1890, 2890, 3520, 4580, 5280, 5782],
                views: [24800, 45500, 65000, 78000, 102800, 125000, 142850]
            }
        };

        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.period-btn').forEach(b => {
                    b.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
                this.classList.remove('text-gray-500');

                currentPeriod = this.dataset.period;

                // Update chart data based on period
                const data = periodData[currentPeriod] || periodData['30d'];
                chartData.dates = data.dates;
                chartData.revenue = data.revenue;
                chartData.tickets = data.tickets;
                chartData.views = data.views;

                // Update chart
                if (mainChart) {
                    mainChart.updateOptions({
                        xaxis: { categories: chartData.dates }
                    });
                    mainChart.updateSeries(getSeries());
                }
            });
        });

        function exportReport() {
            alert('Raportul va fi generat si descarcat in format PDF. (Demo)');
        }

        // ============ GOAL MODAL ============
        function showAddGoalModal() {
            document.getElementById('goal-modal').classList.remove('hidden');
        }

        function closeGoalModal() {
            document.getElementById('goal-modal').classList.add('hidden');
            document.getElementById('goal-form').reset();
        }

        function saveGoal(e) {
            e.preventDefault();
            const form = e.target;
            const data = {
                type: form.type.value,
                name: form.name.value,
                target_value: parseFloat(form.target_value.value),
                deadline: form.deadline.value || null
            };
            console.log('Saving goal:', data);

            // Show success notification
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
            notification.textContent = 'Obiectivul a fost adaugat!';
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2000);

            closeGoalModal();
        }

        // ============ MILESTONE/CAMPAIGN MODAL ============
        const AD_CAMPAIGN_TYPES = ['facebook_ads', 'google_ads', 'instagram_ads', 'tiktok_ads', 'influencer'];

        function showAddMilestoneModal() {
            document.getElementById('milestone-modal').classList.remove('hidden');
            updateMilestoneFields('facebook_ads');
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
            } else {
                adFields.classList.add('hidden');
                otherFields.classList.remove('hidden');
            }
        }

        function saveMilestone(e) {
            e.preventDefault();
            const form = e.target;
            console.log('Saving milestone');

            // Show success notification
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
            notification.textContent = 'Campania a fost adaugata!';
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2000);

            closeMilestoneModal();
        }

        // ============ GLOBE/MAP MODAL ============
        let globeMap = null;

        const cityCoordinates = {
            'Bucuresti': { lat: 44.4268, lng: 26.1025 },
            'Cluj-Napoca': { lat: 46.7712, lng: 23.6236 },
            'Timisoara': { lat: 45.7489, lng: 21.2087 },
            'Iasi': { lat: 47.1585, lng: 27.6014 },
            'Constanta': { lat: 44.1598, lng: 28.6348 },
            'Brasov': { lat: 45.6427, lng: 25.5887 },
            'Sibiu': { lat: 45.7983, lng: 24.1256 },
            'Oradea': { lat: 47.0465, lng: 21.9189 },
            'Craiova': { lat: 44.3302, lng: 23.7949 },
        };

        function openGlobeModal() {
            document.getElementById('globe-modal').classList.remove('hidden');
            setTimeout(() => initLeafletMap(), 100);
        }

        function closeGlobeModal() {
            document.getElementById('globe-modal').classList.add('hidden');
            if (globeMap) {
                try { globeMap.remove(); } catch (e) {}
                globeMap = null;
            }
        }

        function initLeafletMap() {
            const container = document.getElementById('globeMapContainer');

            if (typeof L === 'undefined') {
                console.warn('Leaflet not loaded');
                return;
            }

            if (globeMap) {
                try { globeMap.remove(); } catch (e) {}
                globeMap = null;
            }

            try {
                const map = L.map(container, {
                    center: [46, 25],
                    zoom: 6,
                    zoomControl: true,
                    attributionControl: false
                });

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    subdomains: 'abcd',
                    maxZoom: 19
                }).addTo(map);

                globeMap = map;

                // Demo locations from PHP
                const locations = <?= json_encode($topLocations) ?>;

                locations.forEach(loc => {
                    const coords = cityCoordinates[loc.city];
                    if (coords) {
                        const marker = L.circleMarker([coords.lat, coords.lng], {
                            radius: Math.max(8, Math.min(25, loc.visitors / 2000)),
                            fillColor: '#6366f1',
                            color: '#fff',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.6
                        }).addTo(map);

                        marker.bindPopup(`<b>${loc.city}</b><br>${loc.visitors.toLocaleString()} vizitatori`);
                    }
                });

                setTimeout(() => map.invalidateSize(), 200);

            } catch (e) {
                console.error('Error initializing Leaflet map:', e);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', initChart);
    </script>
</body>
</html>
