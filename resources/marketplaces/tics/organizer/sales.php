<?php
/**
 * TICS Organizer Sales - Event Sales Details
 * View detailed sales information for a specific event
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Get event ID from URL
$eventId = isset($_GET['id']) ? htmlspecialchars($_GET['id']) : null;

// Demo event data
$event = [
    'id' => $eventId ?: 'coldplay-2026',
    'name' => 'Coldplay: Music of the Spheres',
    'date' => '2026-02-14',
    'venue' => 'Arena Nationala, Bucuresti',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop',
    'status' => 'active',
];

// Demo ticket types with sales
$ticketTypes = [
    ['id' => 1, 'name' => 'General Access', 'price' => 350, 'sold' => 1847, 'total' => 2500, 'revenue' => 646450],
    ['id' => 2, 'name' => 'Golden Circle', 'price' => 650, 'sold' => 623, 'total' => 800, 'revenue' => 404950],
    ['id' => 3, 'name' => 'VIP Experience', 'price' => 1200, 'sold' => 98, 'total' => 150, 'revenue' => 117600],
];

// Demo recent sales
$recentSales = [
    ['id' => 'ORD-4521', 'customer' => 'Maria Popescu', 'email' => 'maria@email.ro', 'tickets' => 2, 'type' => 'Golden Circle', 'total' => 1300, 'date' => '2025-02-06 14:32', 'status' => 'confirmed'],
    ['id' => 'ORD-4520', 'customer' => 'Ion Ionescu', 'email' => 'ion@email.ro', 'tickets' => 4, 'type' => 'General Access', 'total' => 1400, 'date' => '2025-02-06 14:15', 'status' => 'confirmed'],
    ['id' => 'ORD-4519', 'customer' => 'Ana Dumitrescu', 'email' => 'ana@email.ro', 'tickets' => 1, 'type' => 'VIP Experience', 'total' => 1200, 'date' => '2025-02-06 13:58', 'status' => 'confirmed'],
    ['id' => 'ORD-4518', 'customer' => 'Andrei Vasile', 'email' => 'andrei@email.ro', 'tickets' => 3, 'type' => 'General Access', 'total' => 1050, 'date' => '2025-02-06 13:42', 'status' => 'confirmed'],
    ['id' => 'ORD-4517', 'customer' => 'Elena Popa', 'email' => 'elena@email.ro', 'tickets' => 2, 'type' => 'Golden Circle', 'total' => 1300, 'date' => '2025-02-06 13:28', 'status' => 'refunded'],
    ['id' => 'ORD-4516', 'customer' => 'Mihai Stanescu', 'email' => 'mihai@email.ro', 'tickets' => 5, 'type' => 'General Access', 'total' => 1750, 'date' => '2025-02-06 12:55', 'status' => 'confirmed'],
    ['id' => 'ORD-4515', 'customer' => 'Cristina Marinescu', 'email' => 'cristina@email.ro', 'tickets' => 2, 'type' => 'VIP Experience', 'total' => 2400, 'date' => '2025-02-06 12:31', 'status' => 'confirmed'],
    ['id' => 'ORD-4514', 'customer' => 'Dan Gheorghe', 'email' => 'dan@email.ro', 'tickets' => 1, 'type' => 'Golden Circle', 'total' => 650, 'date' => '2025-02-06 11:47', 'status' => 'confirmed'],
];

// Calculate totals
$totalSold = array_sum(array_column($ticketTypes, 'sold'));
$totalTickets = array_sum(array_column($ticketTypes, 'total'));
$totalRevenue = array_sum(array_column($ticketTypes, 'revenue'));
$soldPercentage = round(($totalSold / $totalTickets) * 100);

// Current page for sidebar
$currentPage = 'events';

// Page config for head
$pageTitle = 'Vanzari - ' . $event['name'];
$pageDescription = 'Detalii vanzari pentru eveniment';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </a>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Vanzari</h1>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($event['name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="exportSales()" class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Export
                            </button>
                            <a href="/organizator/analytics/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Event Info + Progress -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                        <div class="flex items-center gap-4 flex-1">
                            <img src="<?= htmlspecialchars($event['image']) ?>" alt="" class="w-20 h-20 rounded-xl object-cover">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($event['name']) ?></h2>
                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mt-1">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <?= date('d M Y', strtotime($event['date'])) ?>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <?= htmlspecialchars($event['venue']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:w-96">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700"><?= number_format($totalSold, 0, ',', '.') ?> / <?= number_format($totalTickets, 0, ',', '.') ?> bilete vandute</span>
                                <span class="text-sm font-bold text-indigo-600"><?= $soldPercentage ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-indigo-600 h-3 rounded-full transition-all" style="width: <?= $soldPercentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-5 text-white">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-green-100 text-sm">Venituri totale</span>
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <p class="text-3xl font-bold"><?= number_format($totalRevenue, 0, ',', '.') ?></p>
                        <p class="text-green-100 text-sm">RON</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-gray-500 text-sm">Bilete vandute</span>
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($totalSold, 0, ',', '.') ?></p>
                        <p class="text-gray-500 text-sm">din <?= number_format($totalTickets, 0, ',', '.') ?> disponibile</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-gray-500 text-sm">Pret mediu bilet</span>
                            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format(round($totalRevenue / $totalSold), 0, ',', '.') ?></p>
                        <p class="text-gray-500 text-sm">RON</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-gray-500 text-sm">Comenzi totale</span>
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900">847</p>
                        <p class="text-gray-500 text-sm">comenzi</p>
                    </div>
                </div>

                <!-- Ticket Types Breakdown -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Vanzari pe categorii de bilete</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Categorie</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Pret</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Vandute</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Progres</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Venituri</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($ticketTypes as $ticket):
                                    $ticketPercent = round(($ticket['sold'] / $ticket['total']) * 100);
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($ticket['name']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-600"><?= number_format($ticket['price'], 0, ',', '.') ?> RON</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900"><?= number_format($ticket['sold'], 0, ',', '.') ?> / <?= number_format($ticket['total'], 0, ',', '.') ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-1 bg-gray-200 rounded-full h-2 w-32">
                                                <div class="<?= $ticketPercent >= 80 ? 'bg-amber-500' : 'bg-indigo-600' ?> h-2 rounded-full" style="width: <?= $ticketPercent ?>%"></div>
                                            </div>
                                            <span class="text-sm font-medium <?= $ticketPercent >= 80 ? 'text-amber-600' : 'text-gray-600' ?>"><?= $ticketPercent ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-semibold text-gray-900"><?= number_format($ticket['revenue'], 0, ',', '.') ?> RON</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td class="px-6 py-4 font-bold text-gray-900">Total</td>
                                    <td class="px-6 py-4"></td>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?= number_format($totalSold, 0, ',', '.') ?> / <?= number_format($totalTickets, 0, ',', '.') ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-1 bg-gray-200 rounded-full h-2 w-32">
                                                <div class="bg-indigo-600 h-2 rounded-full" style="width: <?= $soldPercentage ?>%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-indigo-600"><?= $soldPercentage ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-gray-900"><?= number_format($totalRevenue, 0, ',', '.') ?> RON</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900">Comenzi recente</h3>
                            <div class="flex items-center gap-2">
                                <input type="text" placeholder="Cauta dupa nume sau ID..." class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 w-64">
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">ID Comanda</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Bilete</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Data</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($recentSales as $sale): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-medium text-indigo-600"><?= htmlspecialchars($sale['id']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($sale['customer']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($sale['email']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= $sale['tickets'] ?>x <?= htmlspecialchars($sale['type']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-900"><?= number_format($sale['total'], 0, ',', '.') ?> RON</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($sale['date'])) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($sale['status'] === 'confirmed'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Confirmat
                                        </span>
                                        <?php elseif ($sale['status'] === 'refunded'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Returnat
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            In asteptare
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="viewOrder('<?= $sale['id'] ?>')" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Vezi detalii">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <button onclick="resendTickets('<?= $sale['id'] ?>')" class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Retrimite bilete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="p-4 border-t border-gray-200 flex items-center justify-between">
                        <p class="text-sm text-gray-500">Afisez 1-8 din 847 comenzi</p>
                        <div class="flex items-center gap-1">
                            <button class="px-3 py-1 text-gray-400 text-sm" disabled>&laquo; Anterior</button>
                            <button class="px-3 py-1 bg-indigo-600 text-white text-sm rounded-lg">1</button>
                            <button class="px-3 py-1 text-gray-600 text-sm hover:bg-gray-100 rounded-lg">2</button>
                            <button class="px-3 py-1 text-gray-600 text-sm hover:bg-gray-100 rounded-lg">3</button>
                            <span class="px-2 text-gray-400">...</span>
                            <button class="px-3 py-1 text-gray-600 text-sm hover:bg-gray-100 rounded-lg">106</button>
                            <button class="px-3 py-1 text-gray-600 text-sm hover:bg-gray-100 rounded-lg">Urmator &raquo;</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function exportSales() {
            alert('Se exporta lista de vanzari in format CSV. (Demo)');
        }

        function viewOrder(orderId) {
            alert(`Se deschide comanda ${orderId}. (Demo)`);
        }

        function resendTickets(orderId) {
            if (confirm('Retrimiti biletele pe email pentru aceasta comanda?')) {
                alert(`Bilete retrimise pentru comanda ${orderId}. (Demo)`);
            }
        }
    </script>
</body>
</html>
