<?php
/**
 * TICS Organizer Report - Event Performance Report
 * Printable/exportable event summary report
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
    'status' => 'completed',
    'capacity' => 55000,
];

// Demo report data
$reportData = [
    'revenue_gross' => 3245000,
    'revenue_net' => 3082750,
    'tickets_sold' => 21450,
    'commission_rate' => 5,
    'commission_amount' => 162250,
    'refunds_total' => 12500,
    'refunds_count' => 8,
    'views' => 186420,
    'unique_views' => 124280,
    'conversion_rate' => 5.2,
];

$ticketTypes = [
    ['name' => 'General Admission', 'price' => 149, 'sold' => 14200, 'capacity' => 30000, 'revenue' => 2115800, 'percentage' => 65.2],
    ['name' => 'VIP Experience', 'price' => 499, 'sold' => 3450, 'capacity' => 5000, 'revenue' => 1721550, 'percentage' => 16.1],
    ['name' => 'Golden Circle', 'price' => 349, 'sold' => 3800, 'capacity' => 8000, 'revenue' => 1326200, 'percentage' => 17.7],
];

$trafficSources = [
    ['source' => 'Facebook Ads', 'visits' => 58420, 'percentage' => 31],
    ['source' => 'Google Search', 'visits' => 48200, 'percentage' => 26],
    ['source' => 'Direct', 'visits' => 35800, 'percentage' => 19],
    ['source' => 'Instagram', 'visits' => 24500, 'percentage' => 13],
    ['source' => 'Alte surse', 'visits' => 19500, 'percentage' => 11],
];

$topLocations = [
    ['city' => 'Bucuresti', 'buyers' => 8420, 'percentage' => 39],
    ['city' => 'Cluj-Napoca', 'buyers' => 2850, 'percentage' => 13],
    ['city' => 'Timisoara', 'buyers' => 1890, 'percentage' => 9],
    ['city' => 'Iasi', 'buyers' => 1540, 'percentage' => 7],
    ['city' => 'Constanta', 'buyers' => 1280, 'percentage' => 6],
];

$goals = [
    ['name' => 'Vanzari luna Ianuarie', 'type' => 'revenue', 'target' => 3000000, 'achieved' => 3245000, 'status' => 'exceeded'],
    ['name' => '20.000 bilete', 'type' => 'tickets', 'target' => 20000, 'achieved' => 21450, 'status' => 'exceeded'],
    ['name' => 'Sold Out', 'type' => 'tickets', 'target' => 43000, 'achieved' => 21450, 'status' => 'in_progress'],
];

$campaigns = [
    ['name' => 'Facebook Launch', 'budget' => 4500, 'revenue' => 18200, 'roi' => 304],
    ['name' => 'Google Ads', 'budget' => 3200, 'revenue' => 12400, 'roi' => 287],
    ['name' => 'Influencer Promo', 'budget' => 2000, 'revenue' => 5800, 'roi' => 190],
];

// Current page for sidebar
$currentPage = 'events';

// Page config for head
$pageTitle = 'Raport - ' . $event['name'];
$pageDescription = 'Raport detaliat al evenimentului';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-after: always; }
            body { background: white !important; }
            .report-section { page-break-inside: avoid; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>

    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar (hidden on print) -->
        <div class="no-print">
            <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64 print:ml-0">
            <!-- Top Bar (hidden on print) -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40 no-print">
                <div class="px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </a>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div>
                                    <h1 class="font-bold text-gray-900">Raport Eveniment</h1>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($event['name']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Printeaza
                            </button>
                            <button onclick="exportPDF()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Export PDF
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Report Header -->
                <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-6 mb-6 text-white report-section">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <img src="<?= htmlspecialchars($event['image']) ?>" class="w-20 h-20 rounded-xl object-cover" alt="">
                            <div>
                                <p class="text-white/60 text-sm mb-1">Raport Final</p>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($event['name']) ?></h2>
                                <p class="text-white/80 text-sm mt-1"><?= date('d M Y', strtotime($event['date'])) ?> - <?= htmlspecialchars($event['venue']) ?>, <?= htmlspecialchars($event['city']) ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                            <div class="p-4 bg-white/10 rounded-xl text-center">
                                <div class="text-2xl font-bold"><?= number_format($reportData['revenue_gross']) ?></div>
                                <div class="text-xs text-white/70">RON Venituri</div>
                            </div>
                            <div class="p-4 bg-white/10 rounded-xl text-center">
                                <div class="text-2xl font-bold"><?= number_format($reportData['tickets_sold']) ?></div>
                                <div class="text-xs text-white/70">Bilete vandute</div>
                            </div>
                            <div class="p-4 bg-white/10 rounded-xl text-center">
                                <div class="text-2xl font-bold"><?= $reportData['commission_rate'] ?>%</div>
                                <div class="text-xs text-white/70">Comision</div>
                            </div>
                            <div class="p-4 bg-white/10 rounded-xl text-center">
                                <div class="text-2xl font-bold"><?= number_format($reportData['views']) ?></div>
                                <div class="text-xs text-white/70">Vizualizari</div>
                            </div>
                            <div class="p-4 bg-white/10 rounded-xl text-center">
                                <div class="text-2xl font-bold"><?= number_format($reportData['conversion_rate'], 1) ?>%</div>
                                <div class="text-xs text-white/70">Rata conversie</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid lg:grid-cols-3 gap-6 mb-6 report-section">
                    <!-- Sales Performance Chart -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Performanta vanzari in timp</h2>
                        <div id="salesChart" class="h-[280px]"></div>
                    </div>

                    <!-- Ticket Distribution -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Distributie bilete</h2>
                        <div id="ticketChart" class="h-[280px]"></div>
                    </div>
                </div>

                <!-- Ticket Types Performance -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 report-section">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Performanta tipuri bilete</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="pb-3 text-xs font-medium text-left text-gray-500 uppercase">Tip bilet</th>
                                    <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Pret</th>
                                    <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Vandute</th>
                                    <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Capacitate</th>
                                    <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Venituri</th>
                                    <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">% din total</th>
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
                                    <td class="py-4 text-right text-sm text-gray-500"><?= number_format($ticket['capacity']) ?></td>
                                    <td class="py-4 text-right text-sm font-medium text-gray-900"><?= number_format($ticket['revenue']) ?> RON</td>
                                    <td class="py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-indigo-500 rounded-full" style="width: <?= $ticket['percentage'] ?>%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500"><?= number_format($ticket['percentage'], 1) ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td class="py-4 font-bold text-gray-900">Total</td>
                                    <td class="py-4"></td>
                                    <td class="py-4 text-right font-bold text-gray-900"><?= number_format($reportData['tickets_sold']) ?></td>
                                    <td class="py-4 text-right text-gray-500"><?= number_format($event['capacity']) ?></td>
                                    <td class="py-4 text-right font-bold text-gray-900"><?= number_format($reportData['revenue_gross']) ?> RON</td>
                                    <td class="py-4 text-right text-sm text-gray-500">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Goals & Campaigns -->
                <div class="grid lg:grid-cols-2 gap-6 mb-6 report-section">
                    <!-- Goals Achievement -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Obiective atinse</h2>
                        <div class="space-y-4">
                            <?php foreach ($goals as $goal): ?>
                            <div class="p-4 border <?= $goal['status'] === 'exceeded' ? 'border-green-200 bg-green-50' : ($goal['status'] === 'achieved' ? 'border-blue-200 bg-blue-50' : 'border-gray-200') ?> rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($goal['name']) ?></span>
                                    <?php if ($goal['status'] === 'exceeded'): ?>
                                    <span class="text-xs px-2 py-0.5 bg-green-200 text-green-700 rounded-full">Depasit!</span>
                                    <?php elseif ($goal['status'] === 'achieved'): ?>
                                    <span class="text-xs px-2 py-0.5 bg-blue-200 text-blue-700 rounded-full">Atins</span>
                                    <?php else: ?>
                                    <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-700 rounded-full">In progres</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span><?= number_format($goal['achieved']) ?> / <?= number_format($goal['target']) ?> <?= $goal['type'] === 'revenue' ? 'RON' : '' ?></span>
                                        <span><?= min(100, round(($goal['achieved'] / $goal['target']) * 100)) ?>%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full <?= $goal['status'] === 'exceeded' ? 'bg-green-500' : ($goal['status'] === 'achieved' ? 'bg-blue-500' : 'bg-indigo-500') ?> rounded-full" style="width: <?= min(100, round(($goal['achieved'] / $goal['target']) * 100)) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Campaigns ROI -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Campanii marketing</h2>
                        <div class="space-y-3">
                            <?php foreach ($campaigns as $campaign): ?>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($campaign['name']) ?></span>
                                    <span class="text-sm font-bold text-green-600">+<?= $campaign['roi'] ?>% ROI</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>Buget: <?= number_format($campaign['budget']) ?> RON</span>
                                    <span>Venit: <?= number_format($campaign['revenue']) ?> RON</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900">Total investit</span>
                                <span class="text-sm font-bold text-gray-900"><?= number_format(array_sum(array_column($campaigns, 'budget'))) ?> RON</span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-sm font-medium text-gray-900">Total venit</span>
                                <span class="text-sm font-bold text-green-600"><?= number_format(array_sum(array_column($campaigns, 'revenue'))) ?> RON</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Traffic & Locations -->
                <div class="grid lg:grid-cols-2 gap-6 mb-6 report-section">
                    <!-- Traffic Sources -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Surse de trafic</h2>
                        <div class="space-y-3">
                            <?php foreach ($trafficSources as $source): ?>
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($source['source']) ?></span>
                                        <span class="text-sm text-gray-500"><?= number_format($source['visits']) ?> vizite</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?= $source['percentage'] ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-400"><?= $source['percentage'] ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Top Locations -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Locatii top cumparatori</h2>
                        <div class="space-y-3">
                            <?php foreach ($topLocations as $location): ?>
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-lg">🇷🇴</div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($location['city']) ?></span>
                                        <span class="text-sm text-gray-500"><?= number_format($location['buyers']) ?> cumparatori</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
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

                <!-- Refunds Section -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 report-section">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Rambursari</h2>
                        <span class="text-sm font-bold text-red-600">-<?= number_format($reportData['refunds_total']) ?> RON</span>
                    </div>
                    <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?= $reportData['refunds_count'] ?> rambursari procesate</p>
                                <p class="text-xs text-gray-500 mt-1">Total returnat: <?= number_format($reportData['refunds_total']) ?> RON</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Rata rambursare</p>
                                <p class="text-lg font-bold text-red-600"><?= number_format(($reportData['refunds_count'] / $reportData['tickets_sold']) * 100, 2) ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 text-white report-section">
                    <h2 class="text-lg font-bold mb-6">Sumar financiar</h2>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <div class="text-sm text-white/60 mb-1">Venituri brute</div>
                            <div class="text-2xl font-bold"><?= number_format($reportData['revenue_gross']) ?> RON</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/60 mb-1">Rambursari</div>
                            <div class="text-2xl font-bold text-red-400">-<?= number_format($reportData['refunds_total']) ?> RON</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/60 mb-1">Comision platforma (<?= $reportData['commission_rate'] ?>%)</div>
                            <div class="text-2xl font-bold text-amber-400">-<?= number_format($reportData['commission_amount']) ?> RON</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/60 mb-1">Venituri nete</div>
                            <div class="text-2xl font-bold text-green-400"><?= number_format($reportData['revenue_net']) ?> RON</div>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-white/60">Data generarii raport</p>
                                <p class="text-sm font-medium"><?= date('d M Y, H:i') ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-white/60">ID Eveniment</p>
                                <p class="text-sm font-medium"><?= htmlspecialchars($eventId) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Print Footer -->
                <div class="hidden print:block mt-8 pt-4 border-t border-gray-200 text-center text-xs text-gray-400">
                    <p>Raport generat de TICS.ro - <?= date('d M Y, H:i') ?></p>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sales Chart
        function initSalesChart() {
            const options = {
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                },
                series: [
                    {
                        name: 'Venituri',
                        data: [245000, 380000, 520000, 890000, 1250000, 1680000, 2100000, 2650000, 3245000]
                    },
                    {
                        name: 'Bilete',
                        data: [1650, 2540, 3480, 5920, 8350, 11200, 14000, 17680, 21450]
                    }
                ],
                xaxis: {
                    categories: ['Oct', 'Nov', 'Dec', 'Ian', 'Feb (W1)', 'Feb (W2)', 'Feb (W3)', 'Feb (W4)', 'Final'],
                    labels: { style: { colors: '#9ca3af', fontSize: '11px' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: [
                    {
                        title: { text: 'Venituri (RON)', style: { color: '#6366f1', fontSize: '11px' } },
                        labels: {
                            style: { colors: '#9ca3af', fontSize: '11px' },
                            formatter: (val) => (val / 1000000).toFixed(1) + 'M'
                        }
                    },
                    {
                        opposite: true,
                        title: { text: 'Bilete', style: { color: '#10b981', fontSize: '11px' } },
                        labels: {
                            style: { colors: '#9ca3af', fontSize: '11px' },
                            formatter: (val) => (val / 1000).toFixed(0) + 'K'
                        }
                    }
                ],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                colors: ['#6366f1', '#10b981'],
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
            };
            new ApexCharts(document.querySelector('#salesChart'), options).render();
        }

        // Ticket Distribution Chart
        function initTicketChart() {
            const options = {
                chart: {
                    type: 'donut',
                    height: 280,
                    fontFamily: 'Inter, sans-serif',
                },
                series: [14200, 3450, 3800],
                labels: ['General Admission', 'VIP Experience', 'Golden Circle'],
                colors: ['#6366f1', '#8b5cf6', '#a855f7'],
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: () => '21.450'
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
            };
            new ApexCharts(document.querySelector('#ticketChart'), options).render();
        }

        function exportPDF() {
            alert('Raportul va fi generat si descarcat in format PDF. (Demo)');
        }

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initSalesChart();
            initTicketChart();
        });
    </script>
</body>
</html>
