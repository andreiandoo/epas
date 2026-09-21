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

/**
 * An answer meant for one account must never be served to another.
 *
 * The CDN in front of the site caches /api/proxy.php by address alone, so two visitors asking the same question got
 * the same answer — the second one reading the first one's account, and an operator seeing minutes-old data after a
 * change. Every call that carries a token now gets a unique address, which no shared cache can match. The origin also
 * marks these answers private (api/proxy.php); this is the belt to that pair of braces, and it costs one parameter.
 */
(function () {
    if (typeof window === 'undefined' || !window.fetch || window.__boPrivateFetch) return;
    window.__boPrivateFetch = true;
    var nativeFetch = window.fetch.bind(window);

    function carriesToken(input, init) {
        var headers = (init && init.headers) || null;
        if (!headers) return false;
        try {
            if (typeof Headers !== 'undefined' && headers instanceof Headers) return !!headers.get('Authorization');
            if (Array.isArray(headers)) return headers.some(function (pair) { return String(pair[0]).toLowerCase() === 'authorization'; });
            return Object.keys(headers).some(function (k) { return k.toLowerCase() === 'authorization'; });
        } catch (e) {
            return false;
        }
    }

    window.fetch = function (input, init) {
        try {
            if (typeof input === 'string' && input.indexOf('/api/proxy.php') !== -1
                && input.indexOf('_nc=') === -1 && carriesToken(input, init)) {
                input += (input.indexOf('?') === -1 ? '?' : '&') + '_nc='
                    + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
            }
        } catch (e) {}
        return nativeFetch(input, init);
    };
})();
