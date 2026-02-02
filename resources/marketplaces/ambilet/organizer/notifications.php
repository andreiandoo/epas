<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Notificari';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'notifications';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Notificari</h1>
                    <p class="text-sm text-muted">Urmareste activitatea si evenimentele importante</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="markAllRead()" id="mark-all-read-btn" class="hidden items-center gap-2 px-4 py-2.5 bg-white border border-border text-secondary rounded-xl text-sm font-medium hover:bg-surface transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Marcheaza toate ca citite
                    </button>
                    <button onclick="clearReadNotifications()" id="clear-read-btn" class="hidden items-center gap-2 px-4 py-2.5 bg-white border border-border text-muted rounded-xl text-sm font-medium hover:bg-surface transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Sterge citite
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-secondary" id="stat-total">0</p>
                            <p class="text-xs text-muted">Total notificari</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-warning/10 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-warning" id="stat-unread">0</p>
                            <p class="text-xs text-muted">Necitite</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-success/10 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-success" id="stat-sales">0</p>
                            <p class="text-xs text-muted">Vanzari</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-info/10 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-info" id="stat-documents">0</p>
                            <p class="text-xs text-muted">Documente</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Row -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <select id="type-filter" class="px-4 py-2 bg-white border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate tipurile</option>
                </select>
                <select id="read-filter" class="px-4 py-2 bg-white border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate notificarile</option>
                    <option value="0">Necitite</option>
                    <option value="1">Citite</option>
                </select>
            </div>

            <!-- Notifications List -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div id="notifications-list" class="divide-y divide-border">
                    <!-- Notifications will be rendered here -->
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="hidden p-12 text-center">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-secondary mb-2">Nu ai notificari</h3>
                    <p class="text-muted">Vei primi notificari cand apar vanzari noi, documente sau alte evenimente importante.</p>
                </div>

                <!-- Loading State -->
                <div id="loading-state" class="p-12 text-center">
                    <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-muted">Se incarca notificarile...</p>
                </div>
            </div>

            <!-- Load More Button -->
            <div id="load-more-container" class="hidden mt-4 text-center">
                <button onclick="loadMore()" class="px-6 py-2.5 bg-white border border-border text-secondary rounded-xl text-sm font-medium hover:bg-surface transition-colors">
                    Incarca mai multe
                </button>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

let currentPage = 1;
let totalPages = 1;
let hasMore = false;
let notificationTypes = {};

// Initialize
init();

async function init() {
    await loadNotificationTypes();
    await loadNotifications();
}

async function loadNotificationTypes() {
    try {
        const response = await AmbiletAPI.get('/organizer/notifications/types');
        if (response.success && response.data.types) {
            notificationTypes = response.data.types;
            populateTypeFilter();
        }
    } catch (e) {
        console.error('Failed to load notification types:', e);
    }
}

function populateTypeFilter() {
    const select = document.getElementById('type-filter');
    select.innerHTML = '<option value="">Toate tipurile</option>';

    for (const [type, label] of Object.entries(notificationTypes)) {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = label;
        select.appendChild(option);
    }
}

async function loadNotifications(append = false) {
    if (!append) {
        currentPage = 1;
        document.getElementById('loading-state').classList.remove('hidden');
        document.getElementById('notifications-list').innerHTML = '';
        document.getElementById('empty-state').classList.add('hidden');
    }

    const typeFilter = document.getElementById('type-filter').value;
    const readFilter = document.getElementById('read-filter').value;

    let url = `/organizer/notifications?page=${currentPage}&per_page=20`;
    if (typeFilter) url += `&type=${typeFilter}`;
    if (readFilter !== '') url += `&read=${readFilter}`;

    try {
        const response = await AmbiletAPI.get(url);
        document.getElementById('loading-state').classList.add('hidden');

        if (response.success) {
            const notifications = response.data || [];
            const meta = response.meta || {};

            totalPages = meta.last_page || 1;
            hasMore = currentPage < totalPages;

            if (!append) {
                renderNotifications(notifications);
                updateStats(notifications);
            } else {
                appendNotifications(notifications);
            }

            updateButtons(notifications);
            document.getElementById('load-more-container').classList.toggle('hidden', !hasMore);
        }
    } catch (e) {
        console.error('Failed to load notifications:', e);
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('empty-state').classList.remove('hidden');
    }
}

function renderNotifications(notifications) {
    const container = document.getElementById('notifications-list');

    if (!notifications.length) {
        container.innerHTML = '';
        document.getElementById('empty-state').classList.remove('hidden');
        return;
    }

    document.getElementById('empty-state').classList.add('hidden');
    container.innerHTML = notifications.map(n => createNotificationHTML(n)).join('');
}

function appendNotifications(notifications) {
    const container = document.getElementById('notifications-list');
    notifications.forEach(n => {
        container.insertAdjacentHTML('beforeend', createNotificationHTML(n));
    });
}

function createNotificationHTML(n) {
    const iconSvg = getIconSvg(n.type, n.color);
    const colorClass = getColorClass(n.color);
    const readClass = n.is_read ? 'opacity-60' : '';
    const unreadDot = !n.is_read ? '<div class="absolute top-4 right-4 w-2.5 h-2.5 bg-primary rounded-full"></div>' : '';

    return `
        <div class="relative p-4 lg:p-5 hover:bg-surface/50 transition-colors ${readClass}" data-notification-id="${n.id}">
            ${unreadDot}
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 ${colorClass} rounded-full flex items-center justify-center flex-shrink-0">
                    ${iconSvg}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-secondary">${escapeHtml(n.title)}</p>
                            ${n.message ? `<p class="text-sm text-muted mt-0.5">${escapeHtml(n.message)}</p>` : ''}
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs text-muted">${n.time_ago}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full ${colorClass}">${escapeHtml(n.type_label)}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            ${n.action_url ? `
                                <a href="${n.action_url}" class="p-2 hover:bg-surface rounded-lg transition-colors" title="Vezi detalii">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            ` : ''}
                            ${!n.is_read ? `
                                <button onclick="markAsRead(${n.id})" class="p-2 hover:bg-surface rounded-lg transition-colors" title="Marcheaza ca citita">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            ` : ''}
                            <button onclick="deleteNotification(${n.id})" class="p-2 hover:bg-surface rounded-lg transition-colors" title="Sterge">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function getIconSvg(type, color) {
    const icons = {
        'ticket_sale': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
        'refund_request': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>',
        'document_generated': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'service_order': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'service_order_completed': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'service_order_invoice': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>',
        'service_order_results': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
        'service_order_started': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'payout_request': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'
    };

    const path = icons[type] || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>';
    const colorClasses = {
        'success': 'text-success',
        'warning': 'text-warning',
        'danger': 'text-danger',
        'info': 'text-info',
        'primary': 'text-primary'
    };

    return `<svg class="w-5 h-5 ${colorClasses[color] || 'text-primary'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">${path}</svg>`;
}

function getColorClass(color) {
    const classes = {
        'success': 'bg-success/10 text-success',
        'warning': 'bg-warning/10 text-warning',
        'danger': 'bg-danger/10 text-danger',
        'info': 'bg-info/10 text-info',
        'primary': 'bg-primary/10 text-primary'
    };
    return classes[color] || 'bg-primary/10 text-primary';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateStats(notifications) {
    // Calculate stats from loaded notifications
    // In production, these should come from a dedicated stats endpoint
    const total = notifications.length;
    const unread = notifications.filter(n => !n.is_read).length;
    const sales = notifications.filter(n => n.type === 'ticket_sale').length;
    const documents = notifications.filter(n => n.type === 'document_generated').length;

    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-unread').textContent = unread;
    document.getElementById('stat-sales').textContent = sales;
    document.getElementById('stat-documents').textContent = documents;
}

function updateButtons(notifications) {
    const hasUnread = notifications.some(n => !n.is_read);
    const hasRead = notifications.some(n => n.is_read);

    const markAllBtn = document.getElementById('mark-all-read-btn');
    const clearReadBtn = document.getElementById('clear-read-btn');

    markAllBtn.classList.toggle('hidden', !hasUnread);
    markAllBtn.classList.toggle('flex', hasUnread);

    clearReadBtn.classList.toggle('hidden', !hasRead);
    clearReadBtn.classList.toggle('flex', hasRead);
}

async function markAsRead(id) {
    try {
        const response = await AmbiletAPI.post(`/organizer/notifications/${id}/read`);
        if (response.success) {
            const el = document.querySelector(`[data-notification-id="${id}"]`);
            if (el) {
                el.classList.add('opacity-60');
                const dot = el.querySelector('.bg-primary.rounded-full.w-2\\.5');
                if (dot) dot.remove();
                const markBtn = el.querySelector('button[onclick^="markAsRead"]');
                if (markBtn) markBtn.remove();
            }
            // Update unread count
            const unreadEl = document.getElementById('stat-unread');
            const current = parseInt(unreadEl.textContent) || 0;
            if (current > 0) unreadEl.textContent = current - 1;
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la marcarea notificarii');
    }
}

async function markAllRead() {
    try {
        const response = await AmbiletAPI.post('/organizer/notifications/read-all');
        if (response.success) {
            AmbiletNotifications.success('Toate notificarile au fost marcate ca citite');
            loadNotifications();
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la marcarea notificarilor');
    }
}

async function deleteNotification(id) {
    if (!confirm('Sigur vrei sa stergi aceasta notificare?')) return;

    try {
        const response = await AmbiletAPI.delete(`/organizer/notifications/${id}`);
        if (response.success) {
            const el = document.querySelector(`[data-notification-id="${id}"]`);
            if (el) {
                el.style.height = el.offsetHeight + 'px';
                el.style.overflow = 'hidden';
                el.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    el.style.height = '0';
                    el.style.padding = '0';
                    el.style.margin = '0';
                    el.style.opacity = '0';
                }, 10);
                setTimeout(() => el.remove(), 300);
            }
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la stergerea notificarii');
    }
}

async function clearReadNotifications() {
    if (!confirm('Sigur vrei sa stergi toate notificarile citite?')) return;

    try {
        const response = await AmbiletAPI.delete('/organizer/notifications/clear-read');
        if (response.success) {
            AmbiletNotifications.success('Notificarile citite au fost sterse');
            loadNotifications();
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la stergerea notificarilor');
    }
}

function loadMore() {
    if (hasMore) {
        currentPage++;
        loadNotifications(true);
    }
}

// Event listeners
document.getElementById('type-filter').addEventListener('change', () => loadNotifications());
document.getElementById('read-filter').addEventListener('change', () => loadNotifications());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
