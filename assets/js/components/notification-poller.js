/**
 * Notification Poller Component
 * Polls for new organizer notifications and plays sound when new ones arrive
 */
const AmbiletNotificationPoller = (function() {
    let pollingInterval = null;
    let lastNotificationCount = 0;
    let lastNotificationId = null;
    let isInitialized = false;
    const POLL_INTERVAL = 30000; // 30 seconds
    const STORAGE_KEY_LAST_ID = 'ambilet_last_notification_id';

    // Get the last known notification ID
    function getLastKnownId() {
        return localStorage.getItem(STORAGE_KEY_LAST_ID);
    }

    // Save the last notification ID
    function saveLastKnownId(id) {
        if (id) {
            localStorage.setItem(STORAGE_KEY_LAST_ID, String(id));
            lastNotificationId = id;
        }
    }

    // Fetch unread notification count
    async function fetchNotifications() {
        try {
            if (typeof AmbiletAPI === 'undefined') return null;

            const response = await AmbiletAPI.get('/organizer/notifications?per_page=5&read=0');

            if (response.success) {
                return {
                    notifications: response.data || [],
                    total: response.meta?.total || 0
                };
            }
        } catch (e) {
            console.warn('Failed to fetch notifications:', e);
        }
        return null;
    }

    // Update the UI with new notification count
    function updateUI(data) {
        const badge = document.getElementById('notification-badge');
        const countEl = document.getElementById('notification-count');
        const listEl = document.getElementById('notifications-list');

        if (!data) return;

        const unreadCount = data.total;
        const notifications = data.notifications;

        // Update badge visibility
        if (badge) {
            badge.classList.toggle('hidden', unreadCount === 0);
        }

        // Update count text
        if (countEl) {
            countEl.textContent = `${unreadCount} noi`;
        }

        // Update dropdown list
        if (listEl && notifications.length > 0) {
            listEl.innerHTML = notifications.slice(0, 5).map(n => {
                const colorClasses = {
                    'success': 'bg-success/10 text-success',
                    'warning': 'bg-warning/10 text-warning',
                    'danger': 'bg-danger/10 text-danger',
                    'info': 'bg-info/10 text-info',
                    'primary': 'bg-primary/10 text-primary'
                };
                const colorClass = colorClasses[n.color] || colorClasses.primary;

                return `
                    <a href="${n.action_url || '/organizator/notifications'}" onclick="AmbiletNotificationPoller.markAsRead(${n.id})" class="flex items-start gap-3 p-4 hover:bg-surface border-b border-border last:border-0 transition-colors ${n.is_read ? 'opacity-60' : ''}">
                        <div class="w-8 h-8 ${colorClass} rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${getNotificationIcon(n.type)}"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-secondary truncate">${escapeHtml(n.title)}</p>
                            <p class="text-xs text-muted mt-0.5">${n.time_ago}</p>
                        </div>
                        ${!n.is_read ? '<div class="w-2 h-2 bg-primary rounded-full flex-shrink-0 mt-2"></div>' : ''}
                    </a>
                `;
            }).join('');
        } else if (listEl && notifications.length === 0) {
            listEl.innerHTML = '<div class="p-6 text-center text-muted text-sm">Nu ai notificari noi</div>';
        }
    }

    // Get icon path for notification type
    function getNotificationIcon(type) {
        const icons = {
            'ticket_sale': 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
            'refund_request': 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
            'document_generated': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'service_order': 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'service_order_completed': 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'payout_request': 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'
        };
        return icons[type] || 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9';
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Check for new notifications and play sound if needed
    async function checkNotifications() {
        const data = await fetchNotifications();
        if (!data) return;

        const notifications = data.notifications;
        const currentCount = data.total;

        // Check if there are new notifications
        if (notifications.length > 0) {
            const newestId = notifications[0].id;
            const storedLastId = getLastKnownId();

            // Play sound if there's a new notification we haven't seen
            if (storedLastId && newestId > parseInt(storedLastId)) {
                // Determine sound type based on notification
                const newestNotification = notifications[0];
                let soundType = 'default';

                if (newestNotification.type === 'ticket_sale') {
                    soundType = 'sale';
                } else if (newestNotification.type === 'refund_request') {
                    soundType = 'warning';
                } else if (newestNotification.type === 'document_generated' || newestNotification.type === 'service_order_completed') {
                    soundType = 'success';
                }

                // Play notification sound
                if (typeof AmbiletNotificationSound !== 'undefined') {
                    AmbiletNotificationSound.play(soundType);
                }

                // Show toast notification for the newest one
                if (typeof AmbiletNotifications !== 'undefined') {
                    AmbiletNotifications.show(newestNotification.title, newestNotification.color || 'info');
                }
            }

            // Save the newest ID
            saveLastKnownId(newestId);
        }

        // Update UI
        updateUI(data);
        lastNotificationCount = currentCount;
    }

    // Start polling for notifications
    function start() {
        if (pollingInterval) return; // Already running

        // Initialize last known ID from storage
        lastNotificationId = getLastKnownId();

        // Initial check
        checkNotifications();

        // Set up polling interval
        pollingInterval = setInterval(checkNotifications, POLL_INTERVAL);
        isInitialized = true;
    }

    // Stop polling
    function stop() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    // Manual refresh
    function refresh() {
        checkNotifications();
    }

    // Check if polling is active
    function isActive() {
        return pollingInterval !== null;
    }

    // Mark a notification as read (fire-and-forget)
    function markAsRead(notificationId) {
        if (!notificationId || typeof AmbiletAPI === 'undefined') return;
        try {
            AmbiletAPI.post('/organizer/notifications/' + notificationId + '/read', {});
        } catch (e) {
            // Silently fail - non-critical
        }
    }

    // Public API
    return {
        start: start,
        stop: stop,
        refresh: refresh,
        isActive: isActive,
        markAsRead: markAsRead
    };
})();

// Auto-start if organizer is logged in
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in organizer context
    const orgData = localStorage.getItem('ambilet_organizer_data');
    if (orgData) {
        // Start polling after a short delay to let other components initialize
        setTimeout(function() {
            AmbiletNotificationPoller.start();
        }, 1000);
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AmbiletNotificationPoller;
}
