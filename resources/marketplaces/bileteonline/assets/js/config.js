/**
 * bilete.online - Configuration
 * Marketplace client for Tixello
 *
 * SECURITY: API credentials are handled server-side via /api/proxy.php
 * The client never sees the API key - all requests go through the proxy
 */

// Merge PHP-injected config with defaults
const PHP_CONFIG = window.BILETEONLINE || {};

const BILETEONLINE_CONFIG = {
    // API is handled via server-side proxy for security
    // Client only needs to know the proxy URL
    API_PROXY_URL: PHP_CONFIG.apiUrl || '/api/proxy.php',

    // Site Configuration (from PHP if available)
    SITE_NAME: PHP_CONFIG.siteName || 'bilete.online',
    SITE_URL: PHP_CONFIG.siteUrl || 'https://bilete.online',
    STORAGE_URL: PHP_CONFIG.storageUrl || 'https://core.tixello.com/storage',
    SUPPORT_EMAIL: 'contact@bilete.online',

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
    PLACEHOLDER_EVENT: '/assets/images/default-event.png',
    PLACEHOLDER_ARTIST: '/assets/images/default-artist.png',
    PLACEHOLDER_ORGANIZER: '/assets/images/placeholder-organizer.jpg',

    // Social Links
    SOCIAL: {
        FACEBOOK: 'https://facebook.com/',
        INSTAGRAM: 'https://instagram.com/',
        TWITTER: 'https://twitter.com/'
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

/**
 * Get the full URL for a storage image
 * @param {string} path - The image path (can be relative or absolute)
 * @returns {string} The full URL to the image
 */
function getStorageUrl(path) {
    if (!path) return BILETEONLINE_CONFIG.PLACEHOLDER_EVENT;

    // If already a full URL, return as-is
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    // Remove leading slash if present for consistent concatenation
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;

    // If path starts with 'storage/', remove it since STORAGE_URL already includes it
    const finalPath = cleanPath.startsWith('storage/') ? cleanPath.substring(8) : cleanPath;

    return `${BILETEONLINE_CONFIG.STORAGE_URL}/${finalPath}`;
}

// Freeze config to prevent modifications
Object.freeze(BILETEONLINE_CONFIG);
Object.freeze(BILETEONLINE_CONFIG.TAXES);
Object.freeze(BILETEONLINE_CONFIG.SOCIAL);
Object.freeze(BILETEONLINE_CONFIG.THEME);

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BILETEONLINE_CONFIG;
}
