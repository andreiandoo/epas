<?php
/**
 * Organizer Dashboard Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];
$events = $demoData['events'];
$recentOrders = $demoData['recentOrders'];

// Current page for sidebar
$currentPage = 'dashboard';

// Page config for head
$pageTitle = 'Dashboard';
$pageDescription = 'Portal organizatori TICS';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <div class="p-6 lg:p-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 animate-fadeInUp">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Buna, <?= htmlspecialchars($currentOrganizer['displayName']) ?>!</h1>
                    <p class="text-gray-500">Iata ce se intampla cu evenimentele tale</p>
                </div>
                <a href="/organizator/eveniment-nou" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Eveniment nou
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['totalTicketsSold']) ?></p>
                    <p class="text-sm text-gray-500">Bilete vandute</p>
                </div>

                <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.15s">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-green-600 font-medium bg-green-50 px-2 py-1 rounded-full">+12.5%</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['totalRevenue'], 0, ',', '.') ?> RON</p>
                    <p class="text-sm text-gray-500">Venituri totale</p>
                </div>

                <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <span class="text-xs text-amber-600 font-medium bg-amber-50 px-2 py-1 rounded-full"><?= $stats['newOrders'] ?> noi</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['totalOrders']) ?></p>
                    <p class="text-sm text-gray-500">Comenzi totale</p>
                </div>

                <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.25s">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['totalAttendees']) ?></p>
                    <p class="text-sm text-gray-500">Participanti unici</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Events List -->
                <div class="lg:col-span-2 animate-fadeInUp" style="animation-delay: 0.3s">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-gray-900">Evenimentele tale</h2>
                            <a href="/organizator/evenimente" class="text-sm text-indigo-600 font-medium hover:underline">Vezi toate →</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php foreach (array_slice($events, 0, 4) as $event): ?>
                            <div class="event-row p-4">
                                <div class="flex items-center gap-4">
                                    <img src="<?= htmlspecialchars($event['image']) ?>" class="w-16 h-16 rounded-xl object-cover flex-shrink-0" alt="<?= htmlspecialchars($event['name']) ?>">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($event['name']) ?></h3>
                                            <?php if ($event['status'] === 'active'): ?>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">Activ</span>
                                            <?php else: ?>
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">Draft</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-500"><?= date('j M Y', strtotime($event['date'])) ?> • <?= htmlspecialchars($event['venue']) ?></p>
                                        <div class="flex items-center gap-4 mt-2">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                                    <span><?= number_format($event['ticketsSold']) ?> / <?= number_format($event['ticketsTotal']) ?> bilete</span>
                                                    <span><?= $event['soldPercentage'] ?>%</span>
                                                </div>
                                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                    <div class="h-full bg-indigo-500 rounded-full" style="width: <?= $event['soldPercentage'] ?>%"></div>
                                                </div>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-900"><?= number_format($event['revenue'], 0, ',', '.') ?> RON</span>
                                        </div>
                                    </div>
                                    <a href="/organizator/eveniment/<?= htmlspecialchars($event['id']) ?>" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Pending Payout -->
                <div class="space-y-6 animate-fadeInUp" style="animation-delay: 0.35s">
                    <!-- Pending Payout -->
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl p-5 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">Plata in asteptare</h3>
                            <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-3xl font-bold mb-1"><?= number_format($stats['pendingPayout'], 0, ',', '.') ?> RON</p>
                        <p class="text-white/70 text-sm mb-4">Urmatoarea plata: 15 Feb 2026</p>
                        <a href="/organizator/plati" class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors">
                            Vezi detalii
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>

                    <!-- Recent Orders -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Comenzi recente</h3>
                            <a href="/organizator/comenzi" class="text-sm text-indigo-600 font-medium hover:underline">Vezi toate</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php foreach (array_slice($recentOrders, 0, 4) as $order): ?>
                            <div class="p-3 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($order['customerName']) ?></p>
                                        <p class="text-xs text-gray-500"><?= $order['tickets'] ?> bilete • <?= htmlspecialchars($order['event']) ?></p>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900"><?= number_format($order['total'], 0, ',', '.') ?> RON</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Actiuni rapide</h3>
                        <div class="space-y-2">
                            <a href="/organizator/scanner" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Scanner Check-in</p>
                                    <p class="text-xs text-gray-500">Scaneaza bilete la intrare</p>
                                </div>
                            </a>
                            <a href="/organizator/widget" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Widget integrare</p>
                                    <p class="text-xs text-gray-500">Adauga pe site-ul tau</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
