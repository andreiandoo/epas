<?php
/**
 * Organizer Notifications Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];

// Current page for sidebar
$currentPage = 'notifications';

// Page config for head
$pageTitle = 'Notificări';
$pageDescription = 'Notificările tale pe TICS.ro';

// Demo notifications data for organizer
$notifications = [
    [
        'id' => 1,
        'type' => 'new_order',
        'title' => 'Comandă nouă #TCS-2024-5678',
        'message' => 'Coldplay - Music of the Spheres • 3 bilete VIP • 1.350 RON',
        'link' => '/organizator/comenzi',
        'icon' => 'cart',
        'iconBg' => 'from-green-100 to-emerald-100',
        'iconColor' => 'text-green-600',
        'time' => 'Acum 5 minute',
        'read' => false
    ],
    [
        'id' => 2,
        'type' => 'new_order',
        'title' => 'Comandă nouă #TCS-2024-5677',
        'message' => 'Coldplay - Music of the Spheres • 2 bilete Standard • 450 RON',
        'link' => '/organizator/comenzi',
        'icon' => 'cart',
        'iconBg' => 'from-green-100 to-emerald-100',
        'iconColor' => 'text-green-600',
        'time' => 'Acum 23 minute',
        'read' => false
    ],
    [
        'id' => 3,
        'type' => 'payout',
        'title' => 'Plată procesată cu succes',
        'message' => '45.230 RON transferați în contul tău bancar',
        'link' => '/organizator/plati',
        'icon' => 'money',
        'iconBg' => 'from-indigo-100 to-purple-100',
        'iconColor' => 'text-indigo-600',
        'time' => 'Acum 2 ore',
        'read' => false
    ],
    [
        'id' => 4,
        'type' => 'milestone',
        'title' => 'Felicitări! 1.000 bilete vândute',
        'message' => 'Coldplay - Music of the Spheres a atins 1.000 bilete vândute',
        'link' => '/organizator/analytics/coldplay-music-of-the-spheres-bucuresti',
        'icon' => 'trophy',
        'iconBg' => 'from-amber-100 to-yellow-100',
        'iconColor' => 'text-amber-600',
        'time' => 'Ieri, 18:45',
        'read' => true
    ],
    [
        'id' => 5,
        'type' => 'review',
        'title' => 'Recenzie nouă primită',
        'message' => '★★★★★ "Organizare impecabilă!" - Maria P.',
        'link' => '/organizator/recenzii',
        'icon' => 'star',
        'iconBg' => 'from-pink-100 to-rose-100',
        'iconColor' => 'text-pink-600',
        'time' => 'Acum 2 zile',
        'read' => true
    ],
    [
        'id' => 6,
        'type' => 'refund',
        'title' => 'Cerere de rambursare',
        'message' => 'Comanda #TCS-2024-4532 • 2 bilete • 300 RON',
        'link' => '/organizator/rambursari',
        'icon' => 'refund',
        'iconBg' => 'from-red-100 to-orange-100',
        'iconColor' => 'text-red-600',
        'time' => 'Acum 3 zile',
        'read' => true
    ],
    [
        'id' => 7,
        'type' => 'event_approved',
        'title' => 'Eveniment aprobat',
        'message' => 'Dua Lipa - Radical Optimism Tour a fost aprobat și publicat',
        'link' => '/organizator/evenimente',
        'icon' => 'check',
        'iconBg' => 'from-green-100 to-teal-100',
        'iconColor' => 'text-green-600',
        'time' => 'Acum o săptămână',
        'read' => true
    ],
    [
        'id' => 8,
        'type' => 'team',
        'title' => 'Membru nou în echipă',
        'message' => 'Alexandru M. a acceptat invitația de Scanner',
        'link' => '/organizator/echipa',
        'icon' => 'user',
        'iconBg' => 'from-blue-100 to-indigo-100',
        'iconColor' => 'text-blue-600',
        'time' => 'Acum 2 săptămâni',
        'read' => true
    ]
];

$unreadCount = count(array_filter($notifications, fn($n) => !$n['read']));

// Icons map
$icons = [
    'cart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
    'money' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'trophy' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
    'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
    'refund' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/>',
    'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'user' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>'
];

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
                    <h1 class="text-2xl font-bold text-gray-900">Notificări</h1>
                    <p class="text-gray-500"><?= $unreadCount ?> notificări necitite</p>
                </div>
                <button onclick="markAllRead()" class="px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors">
                    Marchează toate citite
                </button>
            </div>

            <!-- Notification Filters -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 animate-fadeInUp" style="animation-delay: 0.1s">
                <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                    <button class="notification-filter active px-4 py-2 text-sm font-medium rounded-xl bg-gray-900 text-white" data-filter="all">
                        Toate
                    </button>
                    <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="unread">
                        Necitite <span class="ml-1 text-indigo-600">(<?= $unreadCount ?>)</span>
                    </button>
                    <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="orders">
                        Comenzi
                    </button>
                    <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="payouts">
                        Plăți
                    </button>
                    <button class="notification-filter px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100" data-filter="events">
                        Evenimente
                    </button>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden animate-fadeInUp" style="animation-delay: 0.15s">
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
                <a href="/organizator/setari#notifications" class="text-sm text-indigo-600 hover:underline">
                    Modifică preferințele de notificări →
                </a>
            </div>
        </div>
    </main>

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
                    else if (filter === 'orders') show = type === 'new_order' || type === 'refund';
                    else if (filter === 'payouts') show = type === 'payout';
                    else if (filter === 'events') show = type === 'event_approved' || type === 'milestone';

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
        }

        function deleteNotification(id) {
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    item.remove();
                    // Check if list is empty
                    if (document.querySelectorAll('.notification-item').length === 0) {
                        document.getElementById('emptyState').classList.remove('hidden');
                    }
                }, 200);
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
