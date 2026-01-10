<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Notificari';
$currentPage = 'notifications';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Notificari</h1>
                <p class="mt-1 text-sm text-muted" id="unread-count">Se incarca...</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="markAllRead()" class="px-4 py-2 text-sm font-medium transition-colors text-primary hover:bg-primary/10 rounded-xl">
                    Marcheaza toate ca citite
                </button>
                <a href="/cont/setari" class="p-2 transition-colors text-muted hover:text-primary hover:bg-primary/10 rounded-xl" title="Setari notificari">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 p-1 mb-6 overflow-x-auto bg-surface rounded-xl w-fit">
            <button onclick="showTab('all')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn active whitespace-nowrap" data-tab="all">
                Toate (<span id="count-all">0</span>)
            </button>
            <button onclick="showTab('unread')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" data-tab="unread">
                Necitite (<span id="count-unread">0</span>)
            </button>
            <button onclick="showTab('events')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" data-tab="events">
                Evenimente
            </button>
            <button onclick="showTab('rewards')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" data-tab="rewards">
                Recompense
            </button>
        </div>

        <!-- Notifications List -->
        <div class="overflow-hidden bg-white border rounded-xl lg:rounded-2xl border-border" id="notifications-container">
            <!-- Loading -->
            <div class="p-4 animate-pulse" id="loading-state">
                <div class="flex gap-4">
                    <div class="w-12 h-12 bg-muted/20 rounded-xl"></div>
                    <div class="flex-1">
                        <div class="w-2/3 h-4 mb-2 rounded bg-muted/20"></div>
                        <div class="w-1/2 h-3 rounded bg-muted/20"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div class="mt-6 text-center" id="load-more-container" style="display: none;">
            <button onclick="loadMore()" class="px-6 py-2.5 bg-surface text-secondary font-medium rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                Incarca mai multe
            </button>
        </div>

        <!-- Empty State -->
        <div class="hidden py-12 text-center" id="empty-state">
            <svg class="w-16 h-16 mx-auto mb-4 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="mb-2 text-muted">Nu ai notificari</p>
            <a href="/" class="text-sm font-medium text-primary">Descopera evenimente</a>
        </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const NotificationsPage = {
    notifications: [],
    currentTab: 'all',
    page: 1,

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/notificari';
            return;
        }

        this.loadUserInfo();
        await this.loadNotifications();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = user.points || '0';
            }
        }
    },

    async loadNotifications() {
        try {
            const response = await AmbiletAPI.customer.getNotifications();
            if (response.success && response.data) {
                const notifications = response.data.notifications || response.data || [];
                if (notifications.length > 0) {
                    this.notifications = notifications;
                } else {
                    this.loadDemoNotifications();
                }
            } else {
                this.loadDemoNotifications();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.loadDemoNotifications();
        }

        this.render();
    },

    loadDemoNotifications() {
        const now = new Date();
        this.notifications = [
            {
                id: 1,
                type: 'reminder',
                title: 'Reminder: Concert Rock',
                message: 'Evenimentul tau este in 3 zile! Nu uita sa iti descarci biletele.',
                action_url: '/cont/bilete',
                action_text: 'Vezi biletele',
                is_read: false,
                created_at: new Date(now - 2 * 60 * 60 * 1000).toISOString(),
                category: 'events'
            },
            {
                id: 2,
                type: 'achievement',
                title: 'Ai obtinut badge-ul "Rock Veteran"!',
                message: 'Felicitari! Ai participat la 10+ concerte rock. Ai primit +200 XP.',
                action_url: '/cont/puncte',
                action_text: 'Vezi badge-urile',
                is_read: false,
                created_at: new Date(now - 5 * 60 * 60 * 1000).toISOString(),
                category: 'rewards'
            },
            {
                id: 3,
                type: 'alert',
                title: 'Biletele se vand rapid!',
                message: 'Evenimentul din lista ta de favorite a ajuns la 85% din capacitate.',
                action_url: '/event/rock-concert',
                action_text: 'Cumpara acum',
                is_read: false,
                created_at: new Date(now - 24 * 60 * 60 * 1000).toISOString(),
                category: 'events'
            },
            {
                id: 4,
                type: 'order',
                title: 'Comanda confirmata #TIX-78453',
                message: 'Biletele au fost emise cu succes.',
                action_url: '/cont/comenzi',
                action_text: 'Vezi comanda',
                is_read: true,
                created_at: new Date(now - 24 * 60 * 60 * 1000 - 4 * 60 * 60 * 1000).toISOString(),
                category: 'orders'
            },
            {
                id: 5,
                type: 'points',
                title: 'Ai primit 120 puncte!',
                message: 'Pentru achizitia biletului. Total puncte: 2,450.',
                action_url: '/cont/puncte',
                action_text: 'Vezi recompense',
                is_read: false,
                created_at: new Date(now - 2 * 24 * 60 * 60 * 1000).toISOString(),
                category: 'rewards'
            },
            {
                id: 6,
                type: 'announcement',
                title: 'Artistul tau favorit anunta turneu!',
                message: 'Noi concerte anuntate. Biletele disponibile in curand.',
                action_url: '/cont/favorite',
                action_text: 'Seteaza alerta',
                is_read: true,
                created_at: new Date(now - 3 * 24 * 60 * 60 * 1000).toISOString(),
                category: 'events'
            },
            {
                id: 7,
                type: 'order',
                title: 'Comanda confirmata #TIX-78501',
                message: 'Biletele au fost emise cu succes.',
                action_url: '/cont/comenzi',
                action_text: 'Vezi comanda',
                is_read: true,
                created_at: new Date(now - 4 * 24 * 60 * 60 * 1000).toISOString(),
                category: 'orders'
            },
            {
                id: 8,
                type: 'welcome',
                title: 'Bun venit pe Ambilet!',
                message: 'Contul tau a fost creat cu succes. Descopera evenimente si incepe sa acumulezi puncte.',
                action_url: '/',
                action_text: 'Descopera',
                is_read: true,
                created_at: new Date(now - 12 * 24 * 60 * 60 * 1000).toISOString(),
                category: 'system'
            }
        ];
    },

    render() {
        const container = document.getElementById('notifications-container');
        const emptyState = document.getElementById('empty-state');
        const loadingState = document.getElementById('loading-state');
        const loadMoreContainer = document.getElementById('load-more-container');

        if (loadingState) loadingState.remove();

        let filtered = this.notifications;

        // Apply tab filter
        if (this.currentTab === 'unread') {
            filtered = filtered.filter(n => !n.is_read);
        } else if (this.currentTab === 'events') {
            filtered = filtered.filter(n => n.category === 'events');
        } else if (this.currentTab === 'rewards') {
            filtered = filtered.filter(n => n.category === 'rewards');
        }

        // Update counts
        const unreadCount = this.notifications.filter(n => !n.is_read).length;
        document.getElementById('unread-count').textContent = `Ai ${unreadCount} notificari necitite`;
        document.getElementById('count-all').textContent = this.notifications.length;
        document.getElementById('count-unread').textContent = unreadCount;

        if (filtered.length === 0) {
            container.classList.add('hidden');
            emptyState.classList.remove('hidden');
            loadMoreContainer.style.display = 'none';
            return;
        }

        container.classList.remove('hidden');
        emptyState.classList.add('hidden');

        // Group by date
        const groups = this.groupByDate(filtered);

        let html = '';
        for (const [label, items] of Object.entries(groups)) {
            html += `
                <div class="px-4 py-3 border-b bg-surface border-border">
                    <p class="text-xs font-semibold uppercase text-muted">${label}</p>
                </div>
            `;

            items.forEach(notification => {
                html += this.renderNotification(notification);
            });
        }

        container.innerHTML = html;

        // Show load more if needed
        loadMoreContainer.style.display = filtered.length > 10 ? 'block' : 'none';
    },

    groupByDate(notifications) {
        const groups = {};
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today - 24 * 60 * 60 * 1000);
        const weekAgo = new Date(today - 7 * 24 * 60 * 60 * 1000);

        notifications.forEach(n => {
            const date = new Date(n.created_at);
            let label;

            if (date >= today) {
                label = 'Astazi';
            } else if (date >= yesterday) {
                label = 'Ieri';
            } else if (date >= weekAgo) {
                label = 'Saptamana aceasta';
            } else {
                label = 'Mai devreme';
            }

            if (!groups[label]) groups[label] = [];
            groups[label].push(n);
        });

        return groups;
    },

    renderNotification(notification) {
        const iconConfig = {
            reminder: { bg: 'bg-warning/10', color: 'text-warning', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
            achievement: { bg: 'bg-success/10', color: 'text-success', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
            alert: { bg: 'bg-primary/10', color: 'text-primary', icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z' },
            order: { bg: 'bg-success/10', color: 'text-success', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
            points: { bg: 'bg-accent/10', color: 'text-accent', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            announcement: { bg: 'bg-blue-500/10', color: 'text-blue-500', icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
            welcome: { bg: 'bg-muted/10', color: 'text-muted', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' }
        };

        const config = iconConfig[notification.type] || iconConfig.welcome;
        const timeAgo = this.formatTimeAgo(notification.created_at);

        return `
            <div class="notification-item ${notification.is_read ? '' : 'unread'} p-4 border-b border-border cursor-pointer" onclick="NotificationsPage.markRead(${notification.id})">
                <div class="flex gap-4">
                    <div class="w-12 h-12 ${config.bg} rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 ${config.color}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${config.icon}"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-${notification.is_read ? 'medium' : 'semibold'} text-secondary">${notification.title}</p>
                                <p class="mt-1 text-sm text-muted">${notification.message}</p>
                            </div>
                            ${!notification.is_read ? '<span class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-primary"></span>' : ''}
                        </div>
                        <div class="flex items-center gap-3 mt-3">
                            ${notification.action_url ? `<a href="${notification.action_url}" class="text-sm font-medium text-primary" onclick="event.stopPropagation()">${notification.action_text}</a>` : ''}
                            <span class="text-xs text-muted">${timeAgo}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    formatTimeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now - date;

        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 60) return `Acum ${minutes} minute`;
        if (hours < 24) return `Acum ${hours} ore`;
        if (days === 1) return 'Ieri';
        if (days < 7) return `Acum ${days} zile`;
        return date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    markRead(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification && !notification.is_read) {
            notification.is_read = true;
            this.render();

            // API call
            AmbiletAPI.customer.markNotificationsRead([id]).catch(console.error);
        }
    },

    markAllRead() {
        this.notifications.forEach(n => n.is_read = true);
        this.render();
        AmbiletNotifications.success('Toate notificarile au fost marcate ca citite');

        // API call - null marks all as read
        AmbiletAPI.customer.markNotificationsRead(null).catch(console.error);
    }
};

function showTab(tabName) {
    NotificationsPage.currentTab = tabName;

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('text-muted');
    });

    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.remove('text-muted');

    NotificationsPage.render();
}

function markAllRead() {
    NotificationsPage.markAllRead();
}

function loadMore() {
    AmbiletNotifications.info('Toate notificarile au fost incarcate');
}

// Initialize page
document.addEventListener('DOMContentLoaded', () => NotificationsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
