<?php
/**
 * User Notifications Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-user.php';
$currentUser = $demoData['user'];

// Current page for sidebar
$currentPage = 'notifications';

// Page config for head
$pageTitle = 'Notificări';
$pageDescription = 'Notificările tale pe TICS.ro';

// Demo notifications data
$notifications = [
    [
        'id' => 1,
        'type' => 'ticket_ready',
        'title' => 'Biletele tale sunt gata!',
        'message' => 'Coldplay - Music of the Spheres • 15 Iunie 2026',
        'link' => '/bilete/coldplay-music-of-the-spheres-bucuresti',
        'icon' => 'ticket',
        'iconBg' => 'from-green-100 to-emerald-100',
        'iconColor' => 'text-green-600',
        'time' => 'Acum 2 ore',
        'read' => false
    ],
    [
        'id' => 2,
        'type' => 'new_event',
        'title' => 'Dua Lipa vine în București!',
        'message' => 'Pe baza preferințelor tale • Early Bird disponibil',
        'link' => '/bilete/dua-lipa-bucuresti',
        'icon' => 'star',
        'iconBg' => 'from-pink-100 to-rose-100',
        'iconColor' => 'text-pink-600',
        'time' => 'Acum 5 ore',
        'read' => false
    ],
    [
        'id' => 3,
        'type' => 'order_confirmed',
        'title' => 'Comanda #TCS-2024-1234 confirmată',
        'message' => '2 bilete • 450 RON',
        'link' => '/cont/comenzi',
        'icon' => 'check',
        'iconBg' => 'from-blue-100 to-indigo-100',
        'iconColor' => 'text-blue-600',
        'time' => 'Ieri, 14:32',
        'read' => true
    ],
    [
        'id' => 4,
        'type' => 'points_earned',
        'title' => 'Ai primit 125 puncte bonus!',
        'message' => 'Soldul tău: 1.250 puncte',
        'link' => '/cont/puncte',
        'icon' => 'coin',
        'iconBg' => 'from-amber-100 to-orange-100',
        'iconColor' => 'text-amber-600',
        'time' => 'Acum 3 zile',
        'read' => true
    ],
    [
        'id' => 5,
        'type' => 'event_reminder',
        'title' => 'Nu uita! Concertul Irina Rimes este mâine',
        'message' => 'Sala Palatului • 20:00',
        'link' => '/bilete/irina-rimes-bucuresti',
        'icon' => 'calendar',
        'iconBg' => 'from-purple-100 to-violet-100',
        'iconColor' => 'text-purple-600',
        'time' => 'Acum 4 zile',
        'read' => true
    ],
    [
        'id' => 6,
        'type' => 'price_drop',
        'title' => 'Reducere la un eveniment favorit!',
        'message' => 'UNTOLD Festival - 20% reducere Early Bird',
        'link' => '/bilete/untold-festival-2026',
        'icon' => 'tag',
        'iconBg' => 'from-red-100 to-orange-100',
        'iconColor' => 'text-red-600',
        'time' => 'Acum o săptămână',
        'read' => true
    ],
    [
        'id' => 7,
        'type' => 'welcome',
        'title' => 'Bine ai venit pe TICS.ro!',
        'message' => 'Descoperă evenimente și experiențe unice',
        'link' => '/evenimente',
        'icon' => 'heart',
        'iconBg' => 'from-indigo-100 to-purple-100',
        'iconColor' => 'text-indigo-600',
        'time' => '15 Ianuarie 2024',
        'read' => true
    ]
];

$unreadCount = count(array_filter($notifications, fn($n) => !$n['read']));

// Icons map
$icons = [
    'ticket' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
    'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
    'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'coin' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    'tag' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
    'heart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>'
];

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
                <!-- Page Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Notificări</h1>
                        <p class="text-gray-500 mt-1"><?= $unreadCount ?> notificări necitite</p>
                    </div>
                    <button onclick="markAllRead()" class="px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors">
                        Marchează toate citite
                    </button>
                </div>

                <!-- Notification Filters -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <button class="notification-filter active px-4 py-2 text-sm font-medium rounded-xl bg-gray-900 text-white" data-filter="all">
                            Toate
                        </button>
                        <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="unread">
                            Necitite <span class="ml-1 text-indigo-600">(<?= $unreadCount ?>)</span>
                        </button>
                        <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="tickets">
                            Bilete
                        </button>
                        <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="events">
                            Evenimente
                        </button>
                        <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="points">
                            Puncte
                        </button>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div id="notificationsList" class="divide-y divide-gray-100">
                        <?php foreach ($notifications as $notif): ?>
                        <a href="<?= htmlspecialchars($notif['link']) ?>"
                           class="notification-item flex items-start gap-4 p-5 hover:bg-gray-50 transition-colors <?= !$notif['read'] ? 'bg-indigo-50/50' : '' ?>"
                           data-id="<?= $notif['id'] ?>"
                           data-read="<?= $notif['read'] ? 'true' : 'false' ?>"
                           data-type="<?= $notif['type'] ?>"
                           onclick="markAsRead(<?= $notif['id'] ?>)">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $notif['iconBg'] ?> flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 <?= $notif['iconColor'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <?= $icons[$notif['icon']] ?>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm <?= !$notif['read'] ? 'font-semibold text-gray-900' : 'text-gray-700' ?>">
                                        <?= htmlspecialchars($notif['title']) ?>
                                    </p>
                                    <?php if (!$notif['read']): ?>
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0 mt-1.5"></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($notif['message']) ?></p>
                                <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($notif['time']) ?></p>
                            </div>
                            <button onclick="event.preventDefault(); event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty State (hidden by default) -->
                    <div id="emptyState" class="hidden p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Nu ai notificări</h3>
                        <p class="text-gray-500">Notificările vor apărea aici când vei primi noutăți.</p>
                    </div>
                </div>

                <!-- Notification Settings Link -->
                <div class="mt-6 text-center">
                    <a href="/cont/setari#notifications" class="text-sm text-indigo-600 hover:underline">
                        Modifică preferințele de notificări →
                    </a>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Filter notifications
        document.querySelectorAll('.notification-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                document.querySelectorAll('.notification-filter').forEach(b => {
                    b.classList.remove('active', 'bg-gray-900', 'text-white');
                    b.classList.add('text-gray-600', 'hover:bg-gray-100');
                });
                btn.classList.add('active', 'bg-gray-900', 'text-white');
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');

                // Filter notifications
                const filter = btn.dataset.filter;
                document.querySelectorAll('.notification-item').forEach(item => {
                    const type = item.dataset.type;
                    const isRead = item.dataset.read === 'true';

                    let show = true;
                    if (filter === 'unread') show = !isRead;
                    else if (filter === 'tickets') show = type === 'ticket_ready' || type === 'order_confirmed';
                    else if (filter === 'events') show = type === 'new_event' || type === 'event_reminder' || type === 'price_drop';
                    else if (filter === 'points') show = type === 'points_earned';

                    item.style.display = show ? 'flex' : 'none';
                });

                // Show empty state if no visible items
                const visibleItems = document.querySelectorAll('.notification-item[style="display: flex"], .notification-item:not([style*="display"])');
                const hasVisible = Array.from(visibleItems).some(item => item.style.display !== 'none');
                document.getElementById('emptyState').classList.toggle('hidden', hasVisible);
            });
        });

        function markAsRead(id) {
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) {
                item.dataset.read = 'true';
                item.classList.remove('bg-indigo-50/50');
                const dot = item.querySelector('.bg-indigo-500');
                if (dot) dot.remove();
                const title = item.querySelector('p');
                if (title) {
                    title.classList.remove('font-semibold', 'text-gray-900');
                    title.classList.add('text-gray-700');
                }
                updateUnreadCount();
            }
        }

        function markAllRead() {
            document.querySelectorAll('.notification-item').forEach(item => {
                item.dataset.read = 'true';
                item.classList.remove('bg-indigo-50/50');
                const dot = item.querySelector('.bg-indigo-500');
                if (dot) dot.remove();
                const title = item.querySelector('p');
                if (title) {
                    title.classList.remove('font-semibold', 'text-gray-900');
                    title.classList.add('text-gray-700');
                }
            });
            updateUnreadCount();
        }

        function deleteNotification(id) {
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    item.remove();
                    updateUnreadCount();
                    // Check if list is empty
                    if (document.querySelectorAll('.notification-item').length === 0) {
                        document.getElementById('emptyState').classList.remove('hidden');
                    }
                }, 200);
            }
        }

        function updateUnreadCount() {
            const unread = document.querySelectorAll('.notification-item[data-read="false"]').length;
            // Update header badge if exists
            const headerBadge = document.getElementById('notificationBadge');
            if (headerBadge) {
                headerBadge.style.display = unread > 0 ? 'block' : 'none';
            }
        }
    </script>

    <style>
        .notification-item {
            transition: all 0.2s ease;
        }
    </style>
</body>
</html>
