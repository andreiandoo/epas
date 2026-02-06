<?php
/**
 * User Dashboard Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-user.php';
$currentUser = $demoData['user'];
$tickets = $demoData['tickets'];
$stats = $demoData['stats'];
$recommendations = $demoData['recommendations'];

// Current page for sidebar
$currentPage = 'dashboard';

// Page config for head
$pageTitle = 'Dashboard';
$pageDescription = 'Gestioneaza biletele si contul tau TICS.ro';

// Calculate next event countdown
$nextEventDays = min(array_column($tickets, 'daysUntil'));

// Include user head
include __DIR__ . '/../includes/user-head.php';
?>
<body class="bg-gray-50 min-h-screen">
    <?php
    // Set logged in state for header
    $isLoggedIn = true;
    $loggedInUser = $currentUser;
    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 min-w-0">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 rounded-2xl p-6 mb-8 text-white relative overflow-hidden animate-fadeInUp">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
                    <div class="relative z-10">
                        <h1 class="text-2xl font-bold mb-2">Buna, <?= htmlspecialchars($currentUser['firstName']) ?>! 👋</h1>
                        <p class="text-white/80 mb-4">Ai <?= count($tickets) ?> evenimente viitoare. Urmatorul este in <?= $nextEventDays ?> zile.</p>
                        <a href="/evenimente" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-indigo-600 font-semibold rounded-xl hover:bg-gray-100 transition-colors">
                            Descopera evenimente
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <?php if ($stats['newTickets'] > 0): ?>
                            <span class="text-xs text-green-600 font-medium bg-green-50 px-2 py-1 rounded-full">+<?= $stats['newTickets'] ?> nou</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['activeTickets'] ?></p>
                        <p class="text-sm text-gray-500">Bilete active</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.15s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="text-xs text-amber-600 font-medium">🎁</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['points']) ?></p>
                        <p class="text-sm text-gray-500">Puncte TICS</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['eventsAttended'] ?></p>
                        <p class="text-sm text-gray-500">Evenimente participat</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 animate-fadeInUp" style="animation-delay: 0.25s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-pink-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['favorites'] ?></p>
                        <p class="text-sm text-gray-500">Favorite</p>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="mb-8 animate-fadeInUp" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Evenimente viitoare</h2>
                        <a href="/cont/bilete" class="text-sm text-indigo-600 font-medium hover:underline">Vezi toate →</a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach (array_slice($tickets, 0, 2) as $ticket): ?>
                        <div class="event-card bg-white rounded-xl border border-gray-200 p-4">
                            <div class="flex gap-4">
                                <div class="relative flex-shrink-0">
                                    <img src="<?= htmlspecialchars($ticket['eventImage']) ?>" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl object-cover" alt="<?= htmlspecialchars($ticket['eventName']) ?>">
                                    <div class="absolute -top-2 -right-2 countdown-badge px-2 py-1 text-white text-xs font-bold rounded-full">
                                        <?= $ticket['daysUntil'] ?> zile
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($ticket['eventName']) ?></h3>
                                            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($ticket['venue']) ?>, <?= htmlspecialchars($ticket['city']) ?></p>
                                            <div class="flex items-center gap-4 mt-2">
                                                <span class="flex items-center gap-1 text-sm text-gray-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    <?= date('j M Y', strtotime($ticket['date'])) ?>
                                                </span>
                                                <span class="flex items-center gap-1 text-sm text-gray-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <?= $ticket['time'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full"><?= $ticket['quantity'] ?> bilete</span>
                                            <a href="/cont/bilete" class="text-sm text-indigo-600 font-medium hover:underline">Vezi biletele →</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- AI Recommendations -->
                <div class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 rounded-2xl p-6 border border-indigo-100 mb-8 animate-fadeInUp" style="animation-delay: 0.35s">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center animate-float">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900">Recomandate pentru tine</h3>
                            <p class="text-sm text-gray-500">Bazat pe preferintele tale</p>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <?php foreach ($recommendations as $rec): ?>
                        <a href="/bilete/<?= htmlspecialchars($rec['id']) ?>" class="bg-white rounded-xl p-4 border border-gray-100 hover:shadow-lg transition-all hover:-translate-y-1">
                            <div class="flex items-center gap-1 mb-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                <span class="text-xs font-medium text-green-700"><?= $rec['matchScore'] ?>% match</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($rec['name']) ?></h4>
                            <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($rec['date']) ?> • <?= htmlspecialchars($rec['city']) ?></p>
                            <p class="text-sm font-bold text-gray-900">de la <?= number_format($rec['price']) ?> RON</p>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="animate-fadeInUp" style="animation-delay: 0.4s">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Actiuni rapide</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <a href="/evenimente" class="quick-action bg-white rounded-xl border border-gray-200 p-4 text-center hover:border-indigo-300 hover:shadow-lg">
                            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Cauta evenimente</p>
                        </a>
                        <a href="/cont/bilete" class="quick-action bg-white rounded-xl border border-gray-200 p-4 text-center hover:border-indigo-300 hover:shadow-lg">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Biletele mele</p>
                        </a>
                        <a href="#" class="quick-action bg-white rounded-xl border border-gray-200 p-4 text-center hover:border-indigo-300 hover:shadow-lg">
                            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Cumpara gift card</p>
                        </a>
                        <a href="/cont/setari" class="quick-action bg-white rounded-xl border border-gray-200 p-4 text-center hover:border-indigo-300 hover:shadow-lg">
                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Setari cont</p>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
