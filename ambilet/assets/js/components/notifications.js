/**
 * Ambilet.ro - Toast Notifications Component
 * Displays toast notifications for user feedback
 */

const AmbiletNotifications = {
    container: null,
    queue: [],
    isProcessing: false,

    /**
     * Initialize notifications system
     */
    init() {
        this.createContainer();
        this.bindEvents();
    },

    /**
     * Create toast container
     */
    createContainer() {
        if (document.getElementById('toast-container')) return;

        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        window.addEventListener('ambilet:notification', (e) => {
            this.show(e.detail.message, e.detail.type);
        });
    },

    /**
     * Show a toast notification
     * @param {string} message - Message to display
     * @param {string} type - Type: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in ms (default: 4000)
     */
    show(message, type = 'info', duration = 4000) {
        const toast = this.createToast(message, type);
        this.container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Auto-remove
        setTimeout(() => {
            this.hide(toast);
        }, duration);
    },

    /**
     * Create toast element
     */
    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icon = this.getIcon(type);

        toast.innerHTML = `
            <div class="flex-shrink-0">${icon}</div>
            <div class="flex-1 text-sm font-medium text-secondary">${message}</div>
            <button class="flex-shrink-0 p-1 hover:bg-gray-100 rounded-lg transition-colors" onclick="AmbiletNotifications.hide(this.closest('.toast'))">
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        `;

        return toast;
    },

    /**
     * Get icon for toast type
     */
    getIcon(type) {
        const icons = {
            success: `<svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`,
            error: `<svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`,
            warning: `<svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>`,
            info: `<svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`
        };

        return icons[type] || icons.info;
    },

    /**
     * Hide a toast
     */
    hide(toast) {
        if (!toast) return;

        toast.classList.remove('show');

        setTimeout(() => {
            toast.remove();
        }, 300);
    },

    /**
     * Clear all toasts
     */
    clearAll() {
        const toasts = this.container.querySelectorAll('.toast');
        toasts.forEach(toast => this.hide(toast));
    },

    // ==================== SHORTHAND METHODS ====================

    /**
     * Show success toast
     */
    success(message, duration) {
        this.show(message, 'success', duration);
    },

    /**
     * Show error toast
     */
    error(message, duration) {
        this.show(message, 'error', duration);
    },

    /**
     * Show warning toast
     */
    warning(message, duration) {
        this.show(message, 'warning', duration);
    },

    /**
     * Show info toast
     */
    info(message, duration) {
        this.show(message, 'info', duration);
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletNotifications.init();
});

// Global shorthand
window.toast = {
    success: (msg, dur) => AmbiletNotifications.success(msg, dur),
    error: (msg, dur) => AmbiletNotifications.error(msg, dur),
    warning: (msg, dur) => AmbiletNotifications.warning(msg, dur),
    info: (msg, dur) => AmbiletNotifications.info(msg, dur)
};
