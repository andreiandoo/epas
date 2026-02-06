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
    ['source' => 'Facebook Ads', 'visits' => 45230, 'conversions' => 2847, 'revenue' => 845000, 'icon' => 'facebook'],
    ['source' => 'Google Search', 'visits' => 38420, 'conversions' => 1921, 'revenue' => 523000, 'icon' => 'google'],
    ['source' => 'Direct', 'visits' => 28950, 'conversions' => 1738, 'revenue' => 567000, 'icon' => 'direct'],
    ['source' => 'Instagram', 'visits' => 18200, 'conversions' => 728, 'revenue' => 312000, 'icon' => 'instagram'],
];

$topLocations = [
    ['city' => 'Bucuresti', 'country' => 'RO', 'visitors' => 45820, 'percentage' => 32, 'tickets' => 12450, 'revenue' => 745000],
    ['city' => 'Cluj-Napoca', 'country' => 'RO', 'visitors' => 18340, 'percentage' => 13, 'tickets' => 8920, 'revenue' => 534000],
    ['city' => 'Timisoara', 'country' => 'RO', 'visitors' => 12450, 'percentage' => 9, 'tickets' => 4560, 'revenue' => 273000],
    ['city' => 'Iasi', 'country' => 'RO', 'visitors' => 9870, 'percentage' => 7, 'tickets' => 3200, 'revenue' => 192000],
    ['city' => 'Constanta', 'country' => 'RO', 'visitors' => 8230, 'percentage' => 6, 'tickets' => 2100, 'revenue' => 126000],
];

$campaignsROI = [
    ['id' => 1, 'name' => 'Facebook Ads', 'type' => 'facebook', 'spend' => 2400, 'revenue' => 8840, 'cac' => 28, 'roi' => 285, 'status' => 'active', 'startDate' => '2026-01-05', 'endDate' => '2026-01-20', 'targeting' => '18-35, Music Lovers, Romania'],
    ['id' => 2, 'name' => 'Google Ads', 'type' => 'google', 'spend' => 1850, 'revenue' => 4480, 'cac' => 35, 'roi' => 142, 'status' => 'active', 'startDate' => '2026-01-03', 'endDate' => '2026-01-25', 'targeting' => 'Concert Romania, Festival Bucuresti'],
    ['id' => 3, 'name' => 'Instagram Story', 'type' => 'instagram', 'spend' => 800, 'revenue' => 1584, 'cac' => 45, 'roi' => 98, 'status' => 'ended', 'startDate' => '2025-12-28', 'endDate' => '2026-01-05', 'targeting' => '18-25, Music, Events'],
];

$goals = [
    ['id' => 1, 'name' => 'Vanzari Luna Ianuarie', 'type' => 'revenue', 'current' => 2800000, 'target' => 4000000, 'deadline' => '2026-01-31', 'status' => 'in_progress'],
    ['id' => 2, 'name' => '15.000 bilete', 'type' => 'tickets', 'current' => 18432, 'target' => 15000, 'deadline' => '2026-01-15', 'status' => 'completed'],
];

$recentSales = [
    ['id' => 1, 'buyer' => 'Maria P.', 'email' => 'm***@gmail.com', 'initials' => 'MP', 'ticket' => 'VIP Experience', 'quantity' => 2, 'total' => 998, 'time' => '2 min', 'date' => '31 Ian 2026, 14:36', 'source' => 'Facebook', 'payment' => 'Visa •••• 4242', 'paymentIcon' => '💳', 'isReturning' => true, 'location' => 'Bucuresti', 'device' => 'Desktop', 'browser' => 'Chrome'],
    ['id' => 2, 'buyer' => 'Ion A.', 'email' => 'i***@yahoo.com', 'initials' => 'IA', 'ticket' => 'General Admission', 'quantity' => 4, 'total' => 596, 'time' => '5 min', 'date' => '31 Ian 2026, 14:33', 'source' => 'Google', 'payment' => 'Mastercard •••• 8888', 'paymentIcon' => '💳', 'isReturning' => false, 'location' => 'Cluj-Napoca', 'device' => 'iPhone 14', 'browser' => 'Safari'],
    ['id' => 3, 'buyer' => 'Elena M.', 'email' => 'e***@gmail.com', 'initials' => 'EM', 'ticket' => 'Golden Circle', 'quantity' => 2, 'total' => 698, 'time' => '8 min', 'date' => '31 Ian 2026, 14:21', 'source' => 'Direct', 'payment' => 'Apple Pay', 'paymentIcon' => '🍎', 'isReturning' => true, 'location' => 'Timisoara', 'device' => 'MacBook', 'browser' => 'Safari'],
    ['id' => 4, 'buyer' => 'Andrei D.', 'email' => 'a***@outlook.com', 'initials' => 'AD', 'ticket' => 'General Admission', 'quantity' => 1, 'total' => 149, 'time' => '12 min', 'date' => '31 Ian 2026, 14:11', 'source' => 'Instagram', 'payment' => 'Google Pay', 'paymentIcon' => '🔵', 'isReturning' => false, 'location' => 'Iasi', 'device' => 'Samsung S23', 'browser' => 'Chrome'],
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
                            <?php foreach ($campaignsROI as $campaign): ?>
                            <div onclick="showCampaignDetail(<?= $campaign['id'] ?>)" class="p-4 rounded-xl border cursor-pointer transition-all hover:shadow-md <?= $campaign['status'] === 'active' ? 'border-green-200 bg-green-50 hover:border-green-300' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php if ($campaign['type'] === 'facebook'): ?>
                                        <span class="text-lg">📘</span>
                                        <?php elseif ($campaign['type'] === 'google'): ?>
                                        <span class="text-lg">🔍</span>
                                        <?php else: ?>
                                        <span class="text-lg">📸</span>
                                        <?php endif; ?>
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($campaign['name']) ?></span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full <?= $campaign['status'] === 'active' ? 'bg-green-200 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                        <?= $campaign['status'] === 'active' ? 'Activ' : 'Finalizat' ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div><span class="text-gray-500">Spend:</span> <span class="font-medium"><?= number_format($campaign['spend']) ?> RON</span></div>
                                    <div><span class="text-gray-500">Revenue:</span> <span class="font-medium text-green-600"><?= number_format($campaign['revenue']) ?> RON</span></div>
                                    <div><span class="text-gray-500">CAC:</span> <span class="font-medium"><?= $campaign['cac'] ?> RON</span></div>
                                    <div><span class="text-gray-500">ROI:</span> <span class="font-bold <?= $campaign['roi'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">+<?= $campaign['roi'] ?>%</span></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                            <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center <?php
                                    if ($source['icon'] === 'facebook') echo 'bg-blue-100';
                                    elseif ($source['icon'] === 'google') echo 'bg-red-50';
                                    elseif ($source['icon'] === 'instagram') echo 'bg-pink-100';
                                    else echo 'bg-gray-100';
                                ?>">
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
                                        <span class="text-sm font-semibold text-gray-900"><?= number_format($source['visits']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?= round(($source['visits'] / $trafficSources[0]['visits']) * 100) ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-400 w-10"><?= round(($source['visits'] / array_sum(array_column($trafficSources, 'visits'))) * 100) ?>%</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-700"><?= number_format($source['revenue'] / 1000) ?>K RON</div>
                                    <div class="text-xs text-gray-400"><?= number_format($source['conversions']) ?> vanzari</div>
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
                            <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-lg">
                                    <?= $location['country'] === 'RO' ? '🇷🇴' : '🌍' ?>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($location['city']) ?></div>
                                    <div class="text-xs text-gray-400"><?= $location['country'] ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900"><?= number_format($location['tickets']) ?> bilete</div>
                                    <div class="text-xs text-gray-400"><?= number_format($location['revenue'] / 1000) ?>K RON</div>
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
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Vanzari recente</h2>
                                <p class="text-xs text-gray-500">Click pe cumparator pentru detalii</p>
                            </div>
                            <a href="/organizator/participanti" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toate →</a>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Cumparator</th>
                                    <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                    <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Bilete</th>
                                    <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Sursa</th>
                                    <th class="pb-3 text-left text-xs font-medium text-gray-500 uppercase">Plata</th>
                                    <th class="pb-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): ?>
                                <tr onclick="showBuyerDetail(<?= $sale['id'] ?>)" class="border-b border-gray-50 hover:bg-indigo-50/50 cursor-pointer transition-colors">
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-200 to-purple-300 flex items-center justify-center text-xs font-semibold text-indigo-700">
                                                <?= $sale['initials'] ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($sale['buyer']) ?></span>
                                                    <?php if ($sale['isReturning']): ?>
                                                    <span class="px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-700 rounded-full">Returning</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-400"><?= $sale['email'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm text-gray-600"><?= $sale['date'] ?></td>
                                    <td class="py-3 text-sm text-gray-700"><?= $sale['quantity'] ?>x <?= htmlspecialchars($sale['ticket']) ?></td>
                                    <td class="py-3">
                                        <span class="text-xs px-2 py-1 rounded-full <?php
                                            if ($sale['source'] === 'Facebook') echo 'bg-blue-100 text-blue-700';
                                            elseif ($sale['source'] === 'Google') echo 'bg-red-100 text-red-700';
                                            elseif ($sale['source'] === 'Instagram') echo 'bg-pink-100 text-pink-700';
                                            else echo 'bg-gray-100 text-gray-700';
                                        ?>"><?= $sale['source'] ?></span>
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span><?= $sale['paymentIcon'] ?></span>
                                            <span class="text-xs text-gray-600"><?= $sale['payment'] ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right text-sm font-semibold text-gray-900"><?= number_format($sale['total']) ?> RON</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Goals -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Obiective</h2>
                            <button onclick="showAddGoalModal()" class="text-sm font-medium text-indigo-600 hover:underline">+ Adauga</button>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($goals as $goal):
                                $progress = min(round(($goal['current'] / $goal['target']) * 100), 100);
                                $isCompleted = $goal['status'] === 'completed';
                            ?>
                            <div onclick="showGoalDetail(<?= $goal['id'] ?>)" class="p-4 border rounded-xl cursor-pointer transition-all hover:shadow-md <?= $isCompleted ? 'border-green-200 bg-green-50 hover:border-green-300' : 'border-gray-200 hover:border-gray-300' ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($goal['name']) ?></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full <?= $isCompleted ? 'bg-green-200 text-green-700' : 'bg-amber-100 text-amber-700' ?>">
                                        <?= $isCompleted ? 'Atins!' : 'In progres' ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span><?php
                                            if ($goal['type'] === 'revenue') {
                                                echo number_format($goal['current'] / 1000000, 1) . 'M / ' . number_format($goal['target'] / 1000000, 0) . 'M RON';
                                            } else {
                                                echo number_format($goal['current']) . ' / ' . number_format($goal['target']);
                                            }
                                        ?></span>
                                        <span><?= round(($goal['current'] / $goal['target']) * 100) ?>%</span>
                                    </div>
                                    <div class="h-2 rounded-full overflow-hidden <?= $isCompleted ? 'bg-green-100' : 'bg-gray-100' ?>">
                                        <div class="h-full rounded-full <?= $isCompleted ? 'bg-gradient-to-r from-green-400 to-green-500' : 'bg-gradient-to-r from-amber-400 to-amber-500' ?>" style="width: <?= $progress ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-xs <?= $isCompleted ? 'text-green-600' : 'text-gray-400' ?>">
                                    <?php if ($isCompleted): ?>
                                        Obiectiv depasit cu <?= round((($goal['current'] / $goal['target']) - 1) * 100) ?>%!
                                    <?php else: ?>
                                        Deadline: <?= date('d M Y', strtotime($goal['deadline'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
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

    <!-- Campaign Detail Modal -->
    <div id="campaign-detail-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCampaignDetailModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div id="campaign-detail-icon" class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-2xl">📘</div>
                        <div>
                            <div id="campaign-detail-name" class="text-lg font-semibold text-gray-900">Campanie</div>
                            <div id="campaign-detail-dates" class="text-sm text-gray-500">Date campanie</div>
                        </div>
                    </div>
                    <div id="campaign-detail-targeting" class="mb-4 p-3 bg-gray-50 rounded-xl text-sm text-gray-600"></div>
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-500">Buget</span>
                            <span id="campaign-detail-spend" class="text-sm font-semibold">0 RON</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-500">Venituri generate</span>
                            <span id="campaign-detail-revenue" class="text-sm font-semibold text-green-600">0 RON</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-500">CAC (Cost per achizitie)</span>
                            <span id="campaign-detail-cac" class="text-sm font-semibold">0 RON</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-sm text-gray-500">ROI</span>
                            <span id="campaign-detail-roi" class="text-sm font-bold text-green-600">+0%</span>
                        </div>
                    </div>
                    <div id="campaign-detail-status" class="p-3 bg-green-50 rounded-xl flex items-center gap-2 text-green-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <span class="text-sm font-medium">Campanie activa</span>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end">
                    <button onclick="closeCampaignDetailModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl">Inchide</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Goal Detail Modal -->
    <div id="goal-detail-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeGoalDetailModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div id="goal-detail-icon" class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <div id="goal-detail-name" class="text-lg font-semibold text-gray-900">Obiectiv</div>
                            <div id="goal-detail-type" class="text-sm text-gray-500">Tip obiectiv</div>
                        </div>
                    </div>
                    <div class="space-y-4 mb-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-gray-500">Progres</span>
                                <span id="goal-detail-progress" class="font-semibold">0%</span>
                            </div>
                            <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div id="goal-detail-progress-bar" class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <div class="text-xs text-gray-500">Actual</div>
                                <div id="goal-detail-current" class="text-lg font-bold text-gray-900">0</div>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <div class="text-xs text-gray-500">Tinta</div>
                                <div id="goal-detail-target" class="text-lg font-bold text-gray-900">0</div>
                            </div>
                        </div>
                        <div class="flex justify-between py-2 border-t border-gray-100">
                            <span class="text-sm text-gray-500">Deadline</span>
                            <span id="goal-detail-deadline" class="text-sm font-semibold">-</span>
                        </div>
                    </div>
                    <div id="goal-detail-status" class="p-3 bg-amber-50 rounded-xl text-amber-700 text-sm font-medium text-center">In progres</div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end">
                    <button onclick="closeGoalDetailModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl">Inchide</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Buyer Detail Modal -->
    <div id="buyer-detail-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeBuyerDetailModal()"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[85vh] overflow-hidden" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div id="buyer-avatar" class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-200 to-purple-300 flex items-center justify-center text-lg font-semibold text-indigo-700">MP</div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span id="buyer-name" class="text-lg font-semibold text-gray-900">Nume</span>
                                <span id="buyer-returning-badge" class="px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded-full hidden">🔄 Returning</span>
                            </div>
                            <div id="buyer-email" class="text-sm text-gray-500">email@example.com</div>
                        </div>
                    </div>
                    <button onclick="closeBuyerDetailModal()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex max-h-[60vh]">
                    <div class="w-72 border-r border-gray-100 p-6 bg-gray-50 overflow-y-auto">
                        <div class="space-y-4">
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Data achizitie</div>
                                <div id="buyer-date" class="text-sm font-medium text-gray-800">-</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Locatie</div>
                                <div id="buyer-location" class="text-sm text-gray-800">-</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Dispozitiv</div>
                                <div id="buyer-device" class="text-sm text-gray-800">-</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Browser</div>
                                <div id="buyer-browser" class="text-sm text-gray-800">-</div>
                            </div>
                            <div class="pt-4 border-t border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total comanda</div>
                                <div id="buyer-total" class="text-xl font-bold text-gray-900">0 RON</div>
                                <div id="buyer-tickets" class="text-sm text-gray-500">-</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Metoda plata</div>
                                <div id="buyer-payment" class="text-sm text-gray-800">-</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Sursa</div>
                                <div id="buyer-source" class="text-sm text-gray-800">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 p-6 overflow-y-auto">
                        <div class="text-sm font-medium text-gray-700 mb-4">Activitate cumparator</div>
                        <div class="relative">
                            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                            <div id="buyer-journey" class="space-y-4">
                                <!-- Journey events will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Demo data for modals
        const campaignsData = <?= json_encode($campaignsROI) ?>;
        const goalsData = <?= json_encode($goals) ?>;
        const salesData = <?= json_encode($recentSales) ?>;

        // Helper to generate daily dates
        function generateDailyDates(startDate, days) {
            const dates = [];
            const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const start = new Date(startDate);
            for (let i = 0; i < days; i++) {
                const d = new Date(start);
                d.setDate(start.getDate() + i);
                dates.push(d.getDate() + ' ' + months[d.getMonth()]);
            }
            return dates;
        }

        // Helper to generate progressive daily data
        function generateDailyData(startVal, endVal, days, variance = 0.15) {
            const data = [];
            const dailyGrowth = (endVal - startVal) / days;
            for (let i = 0; i < days; i++) {
                const baseVal = startVal + (dailyGrowth * i);
                const randomFactor = 1 + (Math.random() - 0.5) * variance;
                data.push(Math.round(baseVal * randomFactor));
            }
            // Ensure last value matches target
            data[data.length - 1] = endVal;
            return data;
        }

        // Chart data with daily points
        const chartData = {
            dates: generateDailyDates('2026-01-01', 31),
            revenue: generateDailyData(85000, 847650, 31, 0.12),
            tickets: generateDailyData(450, 5782, 31, 0.15),
            views: generateDailyData(8500, 62850, 31, 0.18)
        };

        let mainChart;
        let chartMetrics = { revenue: true, tickets: true, views: false };

        // Generate campaign annotations for chart
        function getCampaignAnnotations() {
            const annotations = [];
            const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            campaignsData.forEach((campaign, idx) => {
                const startDate = new Date(campaign.startDate);
                const dateLabel = startDate.getDate() + ' ' + months[startDate.getMonth()];

                // Only show if within current chart dates
                if (chartData.dates.includes(dateLabel)) {
                    const colors = {
                        facebook: '#1877f2',
                        google: '#ea4335',
                        instagram: '#e4405f'
                    };
                    annotations.push({
                        x: dateLabel,
                        strokeDashArray: 4,
                        borderColor: colors[campaign.type] || '#8b5cf6',
                        label: {
                            borderColor: colors[campaign.type] || '#8b5cf6',
                            style: {
                                color: '#fff',
                                background: colors[campaign.type] || '#8b5cf6',
                                fontSize: '10px',
                                padding: { left: 6, right: 6, top: 3, bottom: 3 }
                            },
                            text: campaign.name,
                            position: 'top',
                            offsetY: -10 - (idx * 18) // Stagger labels vertically
                        }
                    });
                }
            });
            return annotations;
        }

        // Initialize chart
        function initChart() {
            const options = {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: {
                        show: true,
                        tools: {
                            download: false,
                            selection: false,
                            zoom: false,
                            zoomin: false,
                            zoomout: false,
                            pan: false,
                            reset: true
                        }
                    },
                    zoom: {
                        enabled: true,
                        type: 'x',
                        autoScaleYaxis: true
                    },
                    events: {
                        // Enable mouse wheel zoom
                        mounted: function(chartContext) {
                            const chartEl = document.querySelector('#mainChart');
                            chartEl.addEventListener('wheel', function(e) {
                                e.preventDefault();
                                const chart = chartContext;
                                if (e.deltaY < 0) {
                                    // Zoom in
                                    chart.zoomX(
                                        chart.w.globals.minX + (chart.w.globals.maxX - chart.w.globals.minX) * 0.1,
                                        chart.w.globals.maxX - (chart.w.globals.maxX - chart.w.globals.minX) * 0.1
                                    );
                                } else {
                                    // Zoom out - reset to full range
                                    chart.resetSeries();
                                }
                            }, { passive: false });
                        }
                    },
                    animations: { enabled: true, speed: 500 },
                    fontFamily: 'Inter, sans-serif',
                },
                series: getSeries(),
                annotations: {
                    xaxis: getCampaignAnnotations()
                },
                xaxis: {
                    categories: chartData.dates,
                    labels: {
                        style: { colors: '#9ca3af', fontSize: '10px' },
                        rotate: -45,
                        rotateAlways: false,
                        hideOverlappingLabels: true,
                        showDuplicates: false,
                        trim: true,
                        maxHeight: 50
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    tickAmount: 10
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

        // Generate daily data for different periods
        const periodData = {
            '7d': {
                dates: generateDailyDates('2026-01-25', 7),
                revenue: generateDailyData(520000, 847650, 7, 0.08),
                tickets: generateDailyData(3520, 5782, 7, 0.10),
                views: generateDailyData(42000, 62850, 7, 0.12)
            },
            '30d': {
                dates: generateDailyDates('2026-01-01', 31),
                revenue: generateDailyData(85000, 847650, 31, 0.12),
                tickets: generateDailyData(450, 5782, 31, 0.15),
                views: generateDailyData(8500, 62850, 31, 0.18)
            },
            '90d': {
                dates: generateDailyDates('2025-11-03', 90),
                revenue: generateDailyData(0, 847650, 90, 0.15),
                tickets: generateDailyData(0, 5782, 90, 0.18),
                views: generateDailyData(2000, 142850, 90, 0.20)
            },
            'all': {
                dates: generateDailyDates('2025-12-01', 62),
                revenue: generateDailyData(25000, 847650, 62, 0.12),
                tickets: generateDailyData(120, 5782, 62, 0.15),
                views: generateDailyData(5000, 142850, 62, 0.18)
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

                // Update chart with new data and annotations
                if (mainChart) {
                    mainChart.updateOptions({
                        xaxis: { categories: chartData.dates },
                        annotations: { xaxis: getCampaignAnnotations() }
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

        // ============ CAMPAIGN DETAIL MODAL ============
        function showCampaignDetail(id) {
            const campaign = campaignsData.find(c => c.id === id);
            if (!campaign) return;

            const icons = { facebook: '📘', google: '🔍', instagram: '📸' };
            document.getElementById('campaign-detail-icon').textContent = icons[campaign.type] || '📣';
            document.getElementById('campaign-detail-name').textContent = campaign.name;
            document.getElementById('campaign-detail-dates').textContent = campaign.startDate + ' → ' + (campaign.endDate || 'In curs');
            document.getElementById('campaign-detail-targeting').textContent = 'Targeting: ' + campaign.targeting;
            document.getElementById('campaign-detail-spend').textContent = campaign.spend.toLocaleString() + ' RON';
            document.getElementById('campaign-detail-revenue').textContent = campaign.revenue.toLocaleString() + ' RON';
            document.getElementById('campaign-detail-cac').textContent = campaign.cac + ' RON';
            document.getElementById('campaign-detail-roi').textContent = '+' + campaign.roi + '%';
            document.getElementById('campaign-detail-roi').className = 'text-sm font-bold ' + (campaign.roi >= 0 ? 'text-green-600' : 'text-red-600');

            const statusEl = document.getElementById('campaign-detail-status');
            if (campaign.status === 'active') {
                statusEl.className = 'p-3 bg-green-50 rounded-xl flex items-center gap-2 text-green-700';
                statusEl.querySelector('span').textContent = 'Campanie activa';
            } else {
                statusEl.className = 'p-3 bg-gray-50 rounded-xl flex items-center gap-2 text-gray-600';
                statusEl.querySelector('span').textContent = 'Campanie finalizata';
            }

            document.getElementById('campaign-detail-modal').classList.remove('hidden');
        }

        function closeCampaignDetailModal() {
            document.getElementById('campaign-detail-modal').classList.add('hidden');
        }

        // ============ GOAL DETAIL MODAL ============
        function showGoalDetail(id) {
            const goal = goalsData.find(g => g.id === id);
            if (!goal) return;

            const progress = Math.round((goal.current / goal.target) * 100);
            const isCompleted = goal.status === 'completed';

            document.getElementById('goal-detail-name').textContent = goal.name;
            document.getElementById('goal-detail-type').textContent = goal.type === 'revenue' ? 'Venituri' : goal.type === 'tickets' ? 'Bilete' : goal.type;
            document.getElementById('goal-detail-progress').textContent = progress + '%';
            document.getElementById('goal-detail-progress-bar').style.width = Math.min(progress, 100) + '%';

            const iconEl = document.getElementById('goal-detail-icon');
            const progressBar = document.getElementById('goal-detail-progress-bar');
            const statusEl = document.getElementById('goal-detail-status');

            if (isCompleted) {
                iconEl.className = 'w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center';
                progressBar.className = 'h-full bg-gradient-to-r from-green-400 to-green-500 rounded-full transition-all';
                statusEl.className = 'p-3 bg-green-50 rounded-xl text-green-700 text-sm font-medium text-center';
                statusEl.textContent = 'Obiectiv atins! Depasit cu ' + (progress - 100) + '%';
            } else {
                iconEl.className = 'w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center';
                progressBar.className = 'h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all';
                statusEl.className = 'p-3 bg-amber-50 rounded-xl text-amber-700 text-sm font-medium text-center';
                statusEl.textContent = 'In progres - mai sunt ' + (100 - progress) + '% pana la obiectiv';
            }

            if (goal.type === 'revenue') {
                document.getElementById('goal-detail-current').textContent = (goal.current / 1000000).toFixed(1) + 'M RON';
                document.getElementById('goal-detail-target').textContent = (goal.target / 1000000).toFixed(0) + 'M RON';
            } else {
                document.getElementById('goal-detail-current').textContent = goal.current.toLocaleString();
                document.getElementById('goal-detail-target').textContent = goal.target.toLocaleString();
            }

            document.getElementById('goal-detail-deadline').textContent = goal.deadline ? new Date(goal.deadline).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

            document.getElementById('goal-detail-modal').classList.remove('hidden');
        }

        function closeGoalDetailModal() {
            document.getElementById('goal-detail-modal').classList.add('hidden');
        }

        // ============ BUYER DETAIL MODAL ============
        function showBuyerDetail(id) {
            const sale = salesData.find(s => s.id === id);
            if (!sale) return;

            document.getElementById('buyer-avatar').textContent = sale.initials;
            document.getElementById('buyer-name').textContent = sale.buyer;
            document.getElementById('buyer-email').textContent = sale.email;

            const returningBadge = document.getElementById('buyer-returning-badge');
            if (sale.isReturning) {
                returningBadge.classList.remove('hidden');
            } else {
                returningBadge.classList.add('hidden');
            }

            document.getElementById('buyer-date').textContent = sale.date;
            document.getElementById('buyer-location').textContent = '🇷🇴 ' + sale.location;
            document.getElementById('buyer-device').textContent = sale.device;
            document.getElementById('buyer-browser').textContent = sale.browser;
            document.getElementById('buyer-total').textContent = sale.total.toLocaleString() + ' RON';
            document.getElementById('buyer-tickets').textContent = sale.quantity + 'x ' + sale.ticket;
            document.getElementById('buyer-payment').textContent = sale.paymentIcon + ' ' + sale.payment;
            document.getElementById('buyer-source').textContent = sale.source;

            // Generate journey
            const journeyEl = document.getElementById('buyer-journey');
            const journeyEvents = [
                { type: 'found', icon: '🔍', text: 'A descoperit evenimentul via <span class="font-medium text-indigo-600">' + sale.source + '</span>', time: 'acum ' + (parseInt(sale.time) + 5) + ' min' },
                { type: 'pageview', icon: '👁', text: 'A vizualizat pagina evenimentului', time: 'acum ' + (parseInt(sale.time) + 4) + ' min' },
                { type: 'event', icon: '⚡', text: 'A selectat bilete: <span class="text-gray-500">' + sale.quantity + 'x ' + sale.ticket + '</span>', time: 'acum ' + (parseInt(sale.time) + 2) + ' min' },
                { type: 'pageview', icon: '👁', text: 'A accesat <span class="font-mono text-blue-600">/checkout</span>', time: 'acum ' + (parseInt(sale.time) + 1) + ' min' },
                { type: 'purchase', icon: '✓', text: '<span class="font-semibold text-green-600">Achizitie finalizata</span>', time: 'acum ' + sale.time }
            ];

            journeyEl.innerHTML = journeyEvents.map(e => `
                <div class="relative flex gap-4 pl-2">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs z-10 flex-shrink-0 ${
                        e.type === 'found' ? 'bg-purple-100' :
                        e.type === 'pageview' ? 'bg-blue-100' :
                        e.type === 'event' ? 'bg-amber-100' :
                        'bg-green-100'
                    }">${e.icon}</div>
                    <div class="flex-1 pb-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-800">${e.text}</div>
                            <span class="text-xs text-gray-400">${e.time}</span>
                        </div>
                    </div>
                </div>
            `).join('');

            document.getElementById('buyer-detail-modal').classList.remove('hidden');
        }

        function closeBuyerDetailModal() {
            document.getElementById('buyer-detail-modal').classList.add('hidden');
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
