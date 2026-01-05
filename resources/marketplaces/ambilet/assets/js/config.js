/**
 * Ambilet.ro - Configuration
 * Marketplace client for Tixello
 *
 * SECURITY: API credentials are handled server-side via /api/proxy.php
 * The client never sees the API key - all requests go through the proxy
 */

// Merge PHP-injected config with defaults
const PHP_CONFIG = window.AMBILET || {};

const AMBILET_CONFIG = {
    // API is handled via server-side proxy for security
    // Client only needs to know the proxy URL
    API_PROXY_URL: PHP_CONFIG.apiUrl || '/api/proxy.php',

    // Site Configuration (from PHP if available)
    SITE_NAME: PHP_CONFIG.siteName || 'Ambilet',
    SITE_URL: PHP_CONFIG.siteUrl || 'https://ambilet.ro',
    SUPPORT_EMAIL: 'support@ambilet.ro',

    // Currency
    CURRENCY: 'RON',
    CURRENCY_SYMBOL: 'lei',
    CURRENCY_LOCALE: 'ro-RO',

    // Tax Configuration (Romanian specific)
    TAXES: {
        RED_CROSS: 0.01,  // 1% Red Cross tax
        MUSICAL_STAMP: 0.05  // 5% Musical stamp (included in price)
    },

    // Points/Rewards
    POINTS_PER_CURRENCY: 0.1,  // 1 point per 10 lei

    // Cart Configuration
    CART_RESERVATION_MINUTES: 15,

    // Pagination
    DEFAULT_PAGE_SIZE: 12,

    // Image Placeholders
    PLACEHOLDER_EVENT: '/assets/images/placeholder-event.jpg',
    PLACEHOLDER_ARTIST: '/assets/images/placeholder-artist.jpg',
    PLACEHOLDER_ORGANIZER: '/assets/images/placeholder-organizer.jpg',

    // Social Links
    SOCIAL: {
        FACEBOOK: 'https://facebook.com/ambilet',
        INSTAGRAM: 'https://instagram.com/ambilet',
        TWITTER: 'https://twitter.com/ambilet'
    },

    // Theme Colors
    THEME: {
        PRIMARY: '#A51C30',
        PRIMARY_DARK: '#8B1728',
        SECONDARY: '#1E293B',
        ACCENT: '#E67E22',
        SUCCESS: '#10B981',
        WARNING: '#F59E0B',
        ERROR: '#EF4444'
    }
};

// Freeze config to prevent modifications
Object.freeze(AMBILET_CONFIG);
Object.freeze(AMBILET_CONFIG.TAXES);
Object.freeze(AMBILET_CONFIG.SOCIAL);
Object.freeze(AMBILET_CONFIG.THEME);

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AMBILET_CONFIG;
}
