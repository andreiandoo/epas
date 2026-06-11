/* === config.js === */
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


/* === utils.js === */
/**
 * bilete.online - Utility Functions
 * Common helper functions used across the application
 */

const BileteOnlineUtils = {
    // ==================== NUMBER FORMATTING ====================

    /**
     * Format number with locale separators
     * @param {number} value - Number to format
     */
    formatNumber(value) {
        return new Intl.NumberFormat(BILETEONLINE_CONFIG.CURRENCY_LOCALE || 'ro-RO').format(value);
    },

    // ==================== CURRENCY FORMATTING ====================

    /**
     * Format currency value
     * @param {number} value - Amount to format
     * @param {boolean} showSymbol - Whether to show currency symbol
     */
    formatCurrency(value, showSymbol = true) {
        const formatted = new Intl.NumberFormat(BILETEONLINE_CONFIG.CURRENCY_LOCALE, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);

        return showSymbol ? `${formatted} ${BILETEONLINE_CONFIG.CURRENCY_SYMBOL}` : formatted;
    },

    /**
     * Parse currency string to number
     */
    parseCurrency(value) {
        if (typeof value === 'number') return value;
        return parseFloat(value.replace(/[^\d.-]/g, '')) || 0;
    },

    // ==================== DATE FORMATTING ====================

    /**
     * Format date for display
     * @param {string|Date} date - Date to format
     * @param {string} format - Format type: 'short', 'medium', 'long', 'full'
     */
    formatDate(date, format = 'medium') {
        const d = new Date(date);

        const options = {
            short: { day: 'numeric', month: 'short' },
            medium: { day: 'numeric', month: 'long', year: 'numeric' },
            long: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
            full: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        };

        return d.toLocaleDateString('ro-RO', options[format] || options.medium);
    },

    /**
     * Format time
     */
    formatTime(time) {
        if (!time) return '';

        // Handle HH:MM:SS or HH:MM format
        const parts = time.split(':');
        return `${parts[0]}:${parts[1]}`;
    },

    /**
     * Format date and time together
     */
    formatDateTime(date, time) {
        const formattedDate = this.formatDate(date, 'medium');
        const formattedTime = time ? this.formatTime(time) : '';

        return formattedTime ? `${formattedDate}, ${formattedTime}` : formattedDate;
    },

    /**
     * Get relative time (e.g., "Ã®n 2 zile", "acum 3 ore")
     */
    getRelativeTime(date) {
        const now = new Date();
        const target = new Date(date);
        const diffMs = target - now;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor(diffMs / (1000 * 60));

        if (diffDays > 30) {
            return this.formatDate(date, 'medium');
        } else if (diffDays > 1) {
            return `Ã®n ${diffDays} zile`;
        } else if (diffDays === 1) {
            return 'mÃ¢ine';
        } else if (diffDays === 0 && diffHours > 0) {
            return `Ã®n ${diffHours} ore`;
        } else if (diffMinutes > 0) {
            return `Ã®n ${diffMinutes} minute`;
        } else if (diffMinutes === 0) {
            return 'acum';
        } else {
            return 'trecut';
        }
    },

    /**
     * Check if date is in the past
     */
    isPast(date) {
        return new Date(date) < new Date();
    },

    /**
     * Check if date is today
     */
    isToday(date) {
        const today = new Date();
        const target = new Date(date);
        return today.toDateString() === target.toDateString();
    },

    // ==================== STRING UTILITIES ====================

    /**
     * Create URL-friendly slug
     */
    slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    },

    /**
     * Truncate text with ellipsis
     */
    truncate(text, length = 100, suffix = '...') {
        if (!text || text.length <= length) return text;
        return text.substring(0, length).trim() + suffix;
    },

    /**
     * Capitalize first letter
     */
    capitalize(text) {
        if (!text) return '';
        return text.charAt(0).toUpperCase() + text.slice(1);
    },

    /**
     * Generate random ID
     */
    generateId(prefix = '') {
        return prefix + Math.random().toString(36).substring(2, 11);
    },

    // ==================== URL UTILITIES ====================

    /**
     * Get URL parameter
     */
    getUrlParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    },

    /**
     * Set URL parameter without reload
     */
    setUrlParam(name, value) {
        const url = new URL(window.location);
        if (value) {
            url.searchParams.set(name, value);
        } else {
            url.searchParams.delete(name);
        }
        window.history.replaceState({}, '', url);
    },

    /**
     * Build URL with parameters
     */
    buildUrl(base, params = {}) {
        const url = new URL(base, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, value);
            }
        });
        return url.toString();
    },

    // ==================== VALIDATION ====================

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Validate phone number (Romanian format)
     */
    isValidPhone(phone) {
        const re = /^(\+40|0)[0-9]{9}$/;
        return re.test(phone.replace(/\s/g, ''));
    },

    /**
     * Check password strength
     * Returns: { score: 0-4, feedback: string }
     */
    checkPasswordStrength(password) {
        let score = 0;
        const feedback = [];

        if (password.length >= 8) score++;
        else feedback.push('Minim 8 caractere');

        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        else feedback.push('Litere mari È™i mici');

        if (/[0-9]/.test(password)) score++;
        else feedback.push('Cel puÈ›in o cifrÄƒ');

        if (/[^a-zA-Z0-9]/.test(password)) score++;
        else feedback.push('Un caracter special');

        const labels = ['Foarte slabÄƒ', 'SlabÄƒ', 'Medie', 'BunÄƒ', 'ExcelentÄƒ'];

        return {
            score,
            label: labels[score],
            feedback: feedback.join(', '),
            isStrong: score >= 3
        };
    },

    // ==================== DOM UTILITIES ====================

    /**
     * Debounce function calls
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function calls
     */
    throttle(func, limit = 300) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func(...args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Wait for element to exist in DOM
     */
    waitForElement(selector, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const element = document.querySelector(selector);
            if (element) {
                resolve(element);
                return;
            }

            const observer = new MutationObserver((mutations, obs) => {
                const element = document.querySelector(selector);
                if (element) {
                    obs.disconnect();
                    resolve(element);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            setTimeout(() => {
                observer.disconnect();
                reject(new Error(`Element ${selector} not found within ${timeout}ms`));
            }, timeout);
        });
    },

    /**
     * Scroll to element smoothly
     */
    scrollTo(element, offset = 100) {
        const target = typeof element === 'string' ? document.querySelector(element) : element;
        if (!target) return;

        const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    },

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            const result = document.execCommand('copy');
            document.body.removeChild(textarea);
            return result;
        }
    },

    // ==================== STORAGE UTILITIES ====================

    /**
     * Get item from localStorage with JSON parsing
     */
    getStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch {
            return defaultValue;
        }
    },

    /**
     * Set item in localStorage with JSON stringify
     */
    setStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },

    // ==================== IMAGE UTILITIES ====================

    /**
     * Get image URL or placeholder
     */
    getImageUrl(url, type = 'event') {
        if (url) return url;

        switch (type) {
            case 'artist':
                return BILETEONLINE_CONFIG.PLACEHOLDER_ARTIST;
            case 'organizer':
                return BILETEONLINE_CONFIG.PLACEHOLDER_ORGANIZER;
            default:
                return BILETEONLINE_CONFIG.PLACEHOLDER_EVENT;
        }
    },

    /**
     * Preload image
     */
    preloadImage(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = url;
        });
    },

    // ==================== MISC UTILITIES ====================

    /**
     * Deep clone object
     */
    deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    },

    /**
     * Check if object is empty
     */
    isEmpty(obj) {
        if (!obj) return true;
        if (Array.isArray(obj)) return obj.length === 0;
        if (typeof obj === 'object') return Object.keys(obj).length === 0;
        return false;
    },

    /**
     * Group array by key
     */
    groupBy(array, key) {
        return array.reduce((result, item) => {
            const keyValue = typeof key === 'function' ? key(item) : item[key];
            (result[keyValue] = result[keyValue] || []).push(item);
            return result;
        }, {});
    },

    /**
     * Sort array by key
     */
    sortBy(array, key, order = 'asc') {
        return [...array].sort((a, b) => {
            const valueA = typeof key === 'function' ? key(a) : a[key];
            const valueB = typeof key === 'function' ? key(b) : b[key];

            if (valueA < valueB) return order === 'asc' ? -1 : 1;
            if (valueA > valueB) return order === 'asc' ? 1 : -1;
            return 0;
        });
    },

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Generate QR code URL (using external service)
     */
    getQRCodeUrl(data, size = 200) {
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(data)}`;
    }
};

// Make utilities available globally
window.BileteOnlineUtils = BileteOnlineUtils;

// Shorthand aliases
window.formatNumber = BileteOnlineUtils.formatNumber.bind(BileteOnlineUtils);
window.formatCurrency = BileteOnlineUtils.formatCurrency.bind(BileteOnlineUtils);
window.formatDate = BileteOnlineUtils.formatDate.bind(BileteOnlineUtils);
window.debounce = BileteOnlineUtils.debounce;
window.throttle = BileteOnlineUtils.throttle;


/* === api.js === */
/**
 * bilete.online - API Client
 * Wrapper for fetch with automatic authentication and error handling
 */

const BileteOnlineAPI = {
    // In-memory cache for GET requests (public + authenticated). Keyed by
    // (URL + token-hash) so one user's cache never leaks to another, and so
    // a logout-and-relogin invalidates everything automatically.
    _cache: new Map(),
    // In-flight Promise dedupe: if 5 components on the same page call
    // /customer/me at the same instant, only one fetch goes out and all 5
    // await the same Promise. Cleared on resolve/reject.
    _inflight: new Map(),
    _cacheTTL: {
        // Public endpoints — long TTL
        'config': 300000,
        'events': 30000,
        'events.featured': 60000,
        'events.past': 300000,
        'categories': 300000,
        'event': 30000,
        'cities': 600000,
        // Authenticated /cont/* endpoints — short TTL so they de-dupe the
        // sidebar + page double-fetch but stay fresh after any mutation.
        'customer.me': 60000,
        'customer.profile-data': 60000,
        'customer.smart-suggestions': 60000,
        'customer.stats.dashboard': 30000,
        'customer.dashboard-bundle': 30000,
        'customer.upcoming-events': 30000,
        'customer.rewards': 30000,
        'customer.rewards.config': 600000,  // static, rarely changes
        'customer.rewards.history': 30000,
        'customer.referrals': 60000,
        'customer.recommendations': 60000,
        'customer.reviews.meta': 600000,
        'customer.reviews.to-write': 30000,
        'customer.beneficiaries': 60000,
        'customer.sessions': 30000,
        'customer.2fa.status': 60000,
        'customer.payment-methods': 60000,
        'customer.gdpr.export.status': 30000,
        'customer.gift-cards': 60000,
        'customer.orders': 15000,           // recent orders refresh quickly
        'customer.tickets.all': 15000,
        'customer.support.index': 15000,
        'customer.reviews': 30000,
        'default': 30000,
    },

    _getCacheTTL(action) {
        return this._cacheTTL[action] || this._cacheTTL['default'];
    },

    clearCache(pattern) {
        if (!pattern) {
            this._cache.clear();
            this._inflight.clear();
            return;
        }
        for (const key of this._cache.keys()) {
            if (key.includes(pattern)) this._cache.delete(key);
        }
        for (const key of this._inflight.keys()) {
            if (key.includes(pattern)) this._inflight.delete(key);
        }
    },

    // Tiny non-crypto hash so the auth token is bound to the cache key
    // without storing the raw token anywhere except headers.
    _hashToken(t) {
        if (! t) return 'anon';
        let h = 0;
        for (let i = 0; i < t.length; i++) {
            h = ((h << 5) - h + t.charCodeAt(i)) | 0;
        }
        return 'auth_' + (h >>> 0).toString(36);
    },

    /**
     * Get API base URL (uses proxy for security)
     */
    getApiUrl() {
        return window.BILETEONLINE?.apiUrl || '/api/proxy.php';
    },

    /**
     * Make an API request via proxy
     * @param {string} endpoint - API endpoint (without base URL)
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} - API response data
     */
    async request(endpoint, options = {}) {
        // Build proxy URL - the API key is handled server-side
        const baseUrl = this.getApiUrl();

        // Separate path from query string so routing regexes match correctly
        const [endpointPath, endpointQuery] = endpoint.split('?');
        const requestMethod = (options.method || 'GET').toUpperCase();
        const action = this.getProxyAction(endpointPath, requestMethod);
        const params = this.getProxyParams(endpointPath);

        // Handle unknown endpoints
        if (!action) {
            throw new APIError(`Unknown endpoint: ${endpoint}`, 400);
        }

        let url = `${baseUrl}?action=${action}`;
        if (params) {
            url += '&' + params;
        }
        // Forward any extra query params (e.g. preview=true)
        if (endpointQuery) {
            url += '&' + endpointQuery;
        }
        // Auto-forward preview mode from page URL to bypass all caching
        if (new URLSearchParams(window.location.search).get('preview') === '1') {
            url += '&preview=1';
        }

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        // Add auth token if available
        const authToken = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
        if (authToken) {
            headers['Authorization'] = `Bearer ${authToken}`;
        }

        // Cache GET requests including authenticated ones — the cache key
        // binds the token hash so users never see each other's data, and a
        // logout/relogin invalidates everything automatically. Skip cache on
        // preview mode, non-GET, or explicit { noCache: true }.
        const method = (options.method || 'GET').toUpperCase();
        const isPreview = new URLSearchParams(window.location.search).get('preview') === '1';
        const useCache = method === 'GET' && !isPreview && !options.noCache;
        const cacheKey = this._hashToken(authToken) + ':' + url;

        if (useCache) {
            const cached = this._cache.get(cacheKey);
            if (cached && Date.now() - cached.time < this._getCacheTTL(action)) {
                return cached.data;
            }
            // In-flight dedupe — return the existing Promise so 5 components
            // calling /customer/me at the same instant share one fetch.
            const inflight = this._inflight.get(cacheKey);
            if (inflight) return inflight;
        }

        // Invalidate cached GETs whenever the user MUTATES anything customer-
        // related (PUT /customer/settings, POST /customer/2fa/confirm, …).
        // Coarse on purpose — keeps client/server state in sync without
        // building per-route invalidation maps.
        if (method !== 'GET' && action.startsWith('customer.')) {
            this.clearCache('customer.');
        }

        const fetchPromise = (async () => {
            try {
                const response = await fetch(url, { ...options, headers });
                const data = await response.json();

                if (!response.ok) {
                    var apiErr = new APIError(data.message || data.error || 'An error occurred', response.status, data.errors);
                    apiErr.data = data;
                    throw apiErr;
                }

                if (useCache) {
                    this._cache.set(cacheKey, { data, time: Date.now() });
                    if (this._cache.size > 50) {
                        const firstKey = this._cache.keys().next().value;
                        this._cache.delete(firstKey);
                    }
                }
                return data;
            } catch (error) {
                if (error instanceof APIError) throw error;
                throw new APIError('Network error. Please check your connection.', 0);
            } finally {
                this._inflight.delete(cacheKey);
            }
        })();

        if (useCache) this._inflight.set(cacheKey, fetchPromise);
        return fetchPromise;
    },

    /**
     * Convert endpoint to proxy action
     */
    getProxyAction(endpoint, method) {
        method = (method || 'GET').toUpperCase();
        // Customer auth endpoints
        if (endpoint === '/customer/register') return 'customer.register';
        if (endpoint === '/customer/login') return 'customer.login';
        if (endpoint === '/customer/logout') return 'customer.logout';
        if (endpoint === '/customer/me') return 'customer.me';
        if (endpoint === '/customer/profile') return 'customer.profile';
        if (endpoint === '/customer/password') return 'customer.password';
        if (endpoint === '/customer/account') return 'customer.account';
        if (endpoint === '/customer/settings') return 'customer.settings';
        if (endpoint === '/customer/avatar') return 'customer.avatar';
        if (endpoint === '/customer/profile-data') return 'customer.profile-data';
        if (endpoint === '/customer/smart-suggestions') return 'customer.smart-suggestions';
        if (endpoint === '/customer/forgot-password') return 'customer.forgot-password';
        if (endpoint === '/customer/reset-password') return 'customer.reset-password';
        if (endpoint === '/customer/recover-order/attach') return 'customer.recover-order.attach';
        if (endpoint === '/customer/recover-order') return 'customer.recover-order';
        if (endpoint === '/customer/gift-cards/check-balance') return 'customer.gift-cards.check-balance';
        if (endpoint === '/customer/support-meta') return 'customer.support.meta';
        if (endpoint.match(/^\/customer\/support-tickets\/\d+\/messages$/)) return 'customer.support.reply';
        if (endpoint.match(/^\/customer\/support-tickets\/\d+$/)) return 'customer.support.show';
        if (endpoint === '/customer/support-tickets' || endpoint.startsWith('/customer/support-tickets?')) return 'customer.support.index';
        if (endpoint === '/customer/verify-email') return 'customer.verify-email';
        if (endpoint === '/customer/resend-verification') return 'customer.resend-verification';

        // bilete.online /cont/setari extras (2026-05-30)
        // 2FA
        if (endpoint === '/customer/2fa/status') return 'customer.2fa.status';
        if (endpoint === '/customer/2fa/initiate') return 'customer.2fa.initiate';
        if (endpoint === '/customer/2fa/confirm') return 'customer.2fa.confirm';
        if (endpoint === '/customer/2fa/disable') return 'customer.2fa.disable';
        if (endpoint === '/customer/2fa/recovery-codes/regenerate') return 'customer.2fa.recovery-codes.regenerate';
        if (endpoint === '/customer/2fa/login') return 'customer.2fa.login';
        // Sessions
        if (endpoint === '/customer/sessions/all') return 'customer.sessions.destroy-others';
        if (endpoint === '/customer/sessions') return 'customer.sessions';
        if (endpoint.match(/^\/customer\/sessions\/\d+$/)) return 'customer.sessions.destroy';
        // Beneficiaries
        if (endpoint.match(/^\/customer\/beneficiaries\/\d+$/)) return 'customer.beneficiaries.update';
        if (endpoint === '/customer/beneficiaries') return 'customer.beneficiaries';
        // Payment methods
        if (endpoint === '/customer/payment-methods/setup-intent') return 'customer.payment-methods.setup-intent';
        if (endpoint === '/customer/payment-methods/confirm') return 'customer.payment-methods.confirm';
        if (endpoint.match(/^\/customer\/payment-methods\/\d+\/default$/)) return 'customer.payment-methods.default';
        if (endpoint.match(/^\/customer\/payment-methods\/\d+$/)) return 'customer.payment-methods.destroy';
        if (endpoint === '/customer/payment-methods') return 'customer.payment-methods';
        // GDPR
        if (endpoint === '/customer/gdpr/export') return 'customer.gdpr.export';
        if (endpoint === '/customer/gdpr/export/status') return 'customer.gdpr.export.status';

        // Order confirmation (public, no auth needed â€” for thank-you page)
        if (endpoint.match(/\/order-confirmation\/[\w-]+$/)) return 'order-confirmation';

        // Customer orders (order ID can be numeric or alphanumeric like MKT-W08ABJWH)
        if (endpoint.match(/\/customer\/orders\/[\w-]+$/)) return 'customer.order';
        if (endpoint === '/customer/orders' || endpoint.includes('/customer/orders?')) return 'customer.orders';

        // Customer ticket transfers
        if (endpoint === '/customer/transfers/direct') return 'customer.transfers.direct';

        // Customer refunds
        if (endpoint.includes('/customer/refunds/reasons')) return 'customer.refunds.reasons';
        if (endpoint.includes('/customer/refunds/check-eligibility')) return 'customer.refunds.check-eligibility';
        if (endpoint.match(/\/customer\/refunds\/\d+\/cancel$/)) return 'customer.refund.cancel';
        if (endpoint.match(/\/customer\/refunds\/\d+$/)) return 'customer.refund.show';
        if (endpoint === '/customer/refunds' || endpoint.includes('/customer/refunds?')) return 'customer.refunds';

        // Customer tickets
        if (endpoint.includes('/customer/tickets/all')) return 'customer.tickets.all';
        if (endpoint.match(/\/customer\/tickets\/\d+$/)) return 'customer.ticket';
        if (endpoint.includes('/customer/tickets')) return 'customer.tickets';

        // Customer stats & dashboard
        if (endpoint.includes('/customer/dashboard-bundle')) return 'customer.dashboard-bundle';
        if (endpoint.includes('/customer/recommendations')) return 'customer.recommendations';
        if (endpoint.includes('/customer/stats/upcoming-events')) return 'customer.upcoming-events';
        if (endpoint.includes('/customer/stats')) return 'customer.stats.dashboard';

        // Customer reviews
        if (endpoint.includes('/customer/reviews/meta')) return 'customer.reviews.meta';
        if (endpoint.includes('/customer/reviews/events-to-review')) return 'customer.reviews.to-write';
        if (endpoint.match(/\/customer\/reviews\/\d+$/)) {
            if (method === 'DELETE') return 'customer.review.delete';
            if (method === 'PUT')    return 'customer.review.update';
            return 'customer.review.show';
        }
        if (endpoint === '/customer/reviews') {
            if (method === 'POST') return 'customer.review.store';
            return 'customer.reviews';
        }
        if (endpoint.includes('/customer/reviews?')) return 'customer.reviews';

        // Customer watchlist
        if (endpoint.includes('/customer/watchlist/check')) return 'customer.watchlist.check';
        if (endpoint.match(/\/customer\/watchlist\/\d+$/)) return 'customer.watchlist.remove';
        if (endpoint === '/customer/watchlist' || endpoint.includes('/customer/watchlist?')) return 'customer.watchlist';

        // Customer rewards
        if (endpoint.includes('/customer/rewards/config')) return 'customer.rewards.config';
        if (endpoint.includes('/customer/rewards/history')) return 'customer.rewards.history';
        if (endpoint.includes('/customer/rewards/badges')) return 'customer.badges';
        if (endpoint.includes('/customer/rewards/available')) return 'customer.rewards.available';
        if (endpoint.includes('/customer/rewards/redeem')) return 'customer.rewards.redeem';
        if (endpoint.includes('/customer/rewards/redemptions')) return 'customer.rewards.redemptions';
        if (endpoint === '/customer/rewards' || endpoint.includes('/customer/rewards?')) return 'customer.rewards';

        // Customer notifications
        if (endpoint.includes('/customer/notifications/unread-count')) return 'customer.notifications.unread-count';
        if (endpoint.includes('/customer/notifications/mark-read')) return 'customer.notifications.read';
        if (endpoint.includes('/customer/notifications/settings')) return 'customer.notifications.settings';
        if (endpoint.match(/\/customer\/notifications\/\d+$/)) return 'customer.notification.delete';
        if (endpoint === '/customer/notifications' || endpoint.includes('/customer/notifications?')) return 'customer.notifications';

        // Customer referrals
        if (endpoint.includes('/customer/referrals/regenerate-code')) return 'customer.referrals.regenerate';
        if (endpoint.includes('/customer/referrals/track-click')) return 'customer.referrals.track-click';
        if (endpoint.includes('/customer/referrals/leaderboard')) return 'customer.referrals.leaderboard';
        if (endpoint.includes('/customer/referrals/claim-rewards')) return 'customer.referrals.claim-rewards';
        if (endpoint.includes('/customer/referrals/validate')) return 'customer.referrals.validate';
        if (endpoint === '/customer/referrals' || endpoint.includes('/customer/referrals?')) return 'customer.referrals';

        // Organizer event categories, genres, venues, artists (MUST be before public patterns that use .includes())
        if (endpoint === '/organizer/event-categories') return 'organizer.event-categories';
        if (endpoint === '/organizer/event-genres' || endpoint.includes('/organizer/event-genres?')) return 'organizer.event-genres';
        if (endpoint === '/organizer/venues' || endpoint.includes('/organizer/venues?')) return 'organizer.venues';
        if (endpoint === '/organizer/artists' || endpoint.includes('/organizer/artists?')) return 'organizer.artists';

        // Newsletter
        if (endpoint === '/newsletter/subscribe') return 'newsletter.subscribe';

        // Public organizer profile
        if (endpoint.match(/\/marketplace-events\/organizers\/[a-z0-9-]+\/contact$/i)) return 'organizer.contact';
        if (endpoint.match(/\/marketplace-events\/organizers\/[a-z0-9-]+$/i)) return 'organizer';
        if (endpoint.includes('/marketplace-events/organizers')) return 'organizers';

        // Public endpoints
        if (endpoint === '/contact') return 'public.contact';
        if (endpoint.includes('/search')) return 'search';
        if (endpoint.includes('/marketplace-events/categories')) return 'categories';
        if (endpoint.includes('/marketplace-events/cities')) return 'cities';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+\/date-availability/i)) return 'event.dateAvailability';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+\/slot-availability/i)) return 'event.slotAvailability';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+\/resource-availability/i)) return 'event.resourceAvailability';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+\/verify-password$/i)) return 'event.verify-password';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.includes('/marketplace-events')) return 'events';

        // Customer favorites endpoints (MUST be before venues/artists to avoid false matches)
        if (endpoint === '/customer/favorites/artists') return 'customer.favorites.artists';
        if (endpoint === '/customer/favorites/venues') return 'customer.favorites.venues';
        if (endpoint === '/customer/favorites/summary') return 'customer.favorites.summary';

        // Venue categories
        if (endpoint.match(/\/venue-categories\/[a-z0-9-]+$/i)) return 'venue-category';
        if (endpoint.includes('/venue-categories')) return 'venue-categories';

        // Venues endpoints - specific patterns first, then fallback
        if (endpoint.includes('/venues/featured')) return 'venues.featured';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/toggle-favorite$/i)) return 'venue.toggle-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/check-favorite$/i)) return 'venue.check-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+$/i)) return 'venue';
        if (endpoint.includes('/venues')) return 'venues';

        // Artists endpoints - specific patterns first, then fallback
        if (endpoint.includes('/artists/featured')) return 'artists.featured';
        if (endpoint.includes('/artists/trending')) return 'artists.trending';
        if (endpoint.includes('/artists/genre-counts')) return 'artists.genre-counts';
        if (endpoint.includes('/artists/alphabet')) return 'artists.alphabet';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/toggle-favorite$/i)) return 'artist.toggle-favorite';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/check-favorite$/i)) return 'artist.check-favorite';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/events/i)) return 'artist.events';
        if (endpoint.match(/\/artists\/[a-z0-9-]+$/i)) return 'artist';
        if (endpoint.includes('/artists')) return 'artists';

        // Locations endpoints
        if (endpoint.includes('/locations/stats')) return 'locations.stats';
        if (endpoint.includes('/locations/cities/featured')) return 'locations.cities.featured';
        if (endpoint.includes('/locations/cities/alphabet')) return 'locations.cities.alphabet';
        if (endpoint.match(/\/locations\/cities\/[a-z0-9-]+$/i)) return 'locations.city';
        if (endpoint.includes('/locations/cities')) return 'locations.cities';
        if (endpoint.match(/\/locations\/regions\/[a-z0-9-]+$/i)) return 'locations.region';
        if (endpoint.includes('/locations/regions')) return 'locations.regions';

        // Cart sub-routes (specific patterns first, then catch-all)
        if (endpoint.includes('/cart/items/with-seats')) return 'cart.items.add-with-seats';
        if (endpoint.includes('/cart/seats')) return 'cart.seats.release';
        if (endpoint.match(/\/cart\/items\/[^/]+$/)) return 'cart.items.manage';
        if (endpoint.includes('/cart/items')) return 'cart.items.add';
        if (endpoint.includes('/cart/promo-code')) return 'cart.promo-code';
        if (endpoint.includes('/cart')) return 'cart';
        if (endpoint === '/promo-codes/validate') return 'promo-codes.validate';
        if (endpoint === '/checkout.features' || endpoint === '/checkout/features') return 'checkout.features';
        if (endpoint.includes('/checkout')) return 'checkout';

        // Orders endpoints (payment)
        if (endpoint.match(/\/orders\/\d+\/pay$/)) return 'orders.pay';
        if (endpoint.match(/\/orders\/\d+\/payment-status$/)) return 'orders.status';

        // Event tracking endpoints (must be before general /events routes)
        if (endpoint.match(/\/events\/[a-z0-9-]+\/track-view$/i)) return 'event.track-view';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/toggle-interest$/i)) return 'event.toggle-interest';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/check-interest$/i)) return 'event.check-interest';

        // Events featured endpoint (must be before general /events)
        if (endpoint.includes('/events/featured')) return 'events.featured';
        // Events cities endpoint
        if (endpoint.includes('/events/cities')) return 'events.cities';

        // Events endpoints (match /events but not /marketplace-events which is handled above)
        if (endpoint.match(/^\/events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.startsWith('/events')) return 'events';

        // Tour public landing page (single)
        if (endpoint.match(/^\/tours\/[a-z0-9-]+(?:\?|$)/i)) return 'tour.show';

        // Event types endpoint (global taxonomy)
        if (endpoint === '/event-types') return 'event-types';

        // Event categories endpoint
        if (endpoint.includes('event-categories')) return 'event-categories';

        // Event genres endpoint
        if (endpoint.includes('/event-genres')) return 'event-genres';

        // Subgenres endpoint
        if (endpoint.startsWith('/subgenres')) return 'subgenres';

        // Cities endpoint
        if (endpoint.startsWith('/cities')) return 'cities';

        // Organizer auth endpoints
        if (endpoint === '/organizer/register') return 'organizer.register';
        if (endpoint === '/organizer/login') return 'organizer.login';
        if (endpoint === '/organizer/logout') return 'organizer.logout';
        if (endpoint === '/organizer/me') return 'organizer.me';
        if (endpoint === '/organizer/widget-settings') return 'organizer.settings';
        if (endpoint === '/organizer/widget-image') return 'organizer.widget-image';
        if (endpoint === '/organizer/settings') return 'organizer.me';
        if (endpoint === '/organizer/settings/profile') return 'organizer.profile';
        if (endpoint === '/organizer/settings/company') return 'organizer.profile';
        if (endpoint === '/organizer/settings/password') return 'organizer.password';
        if (endpoint === '/organizer/settings/verify-cui') return 'organizer.verify-cui';
        if (endpoint === '/organizer/settings/notifications') return 'organizer.notifications';
        if (endpoint === '/organizer/contract') return 'organizer.contract';
        if (endpoint === '/organizer/contract/download') return 'organizer.contract.download';
        if (endpoint === '/organizer/documents/upload') return 'organizer.documents.upload';
        if (endpoint === '/organizer/profile') return 'organizer.profile';
        if (endpoint === '/organizer/password') return 'organizer.password';
        if (endpoint === '/organizer/forgot-password') return 'organizer.forgot-password';
        if (endpoint === '/organizer/reset-password') return 'organizer.reset-password';
        if (endpoint === '/organizer/verify-email') return 'organizer.verify-email';
        if (endpoint === '/organizer/resend-verification') return 'organizer.resend-verification';
        if (endpoint === '/organizer/payout-details') return 'organizer.payout-details';

        // Artist account auth endpoints (Etapa 3 - artist self-service)
        if (endpoint === '/artist/register') return 'artist.register';
        if (endpoint === '/artist/login') return 'artist.login';
        if (endpoint === '/artist/logout') return 'artist.logout';
        if (endpoint === '/artist/me') return 'artist.me';
        if (endpoint === '/artist/forgot-password') return 'artist.forgot-password';
        if (endpoint === '/artist/reset-password') return 'artist.reset-password';
        if (endpoint === '/artist/verify-email') return 'artist.verify-email';
        if (endpoint === '/artist/resend-verification') return 'artist.resend-verification';
        if (endpoint.match(/^\/artist\/check-claim\/[a-z0-9-]+$/)) return 'artist.check-claim';
        if (endpoint === '/artist/search' || endpoint.startsWith('/artist/search?')) return 'artist.search';
        // Artist self-service (Etapa 4) â€” proxy.php branches GET/PUT/DELETE
        // on REQUEST_METHOD so a single action string covers all verbs on
        // the same resource.
        if (endpoint === '/artist/dashboard') return 'artist.dashboard';
        // Use a distinct action name to avoid clashing with the pre-existing
        // public `artist.events` case (slug-required) earlier in proxy.php.
        if (endpoint === '/artist/events' || endpoint.startsWith('/artist/events?')) return 'artist.account.events';
        if (endpoint === '/artist/profile') return 'artist.profile';
        if (endpoint === '/artist/profile/image') return 'artist.profile.image';
        if (endpoint === '/artist/profile/taxonomies') return 'artist.profile.taxonomies';
        if (endpoint === '/artist/profile/refresh-social-stats') return 'artist.profile.refresh-social-stats';
        if (endpoint === '/artist/account') return 'artist.account';
        if (endpoint === '/artist/account/password') return 'artist.account.password';

        // Organizer bank accounts
        if (endpoint === '/organizer/bank-accounts') return 'organizer.bank-accounts';
        if (endpoint.match(/\/organizer\/bank-accounts\/\d+$/)) return 'organizer.bank-account.delete';
        if (endpoint.match(/\/organizer\/bank-accounts\/\d+\/primary$/)) return 'organizer.bank-account.primary';

        // Organizer notifications
        if (endpoint.includes('/organizer/notifications/types')) return 'organizer.notifications.types';
        if (endpoint.includes('/organizer/notifications/unread-count')) return 'organizer.notifications.unread-count';
        if (endpoint.includes('/organizer/notifications/mark-read')) return 'organizer.notifications.mark-read';
        if (endpoint.includes('/organizer/notifications/mark-all-read')) return 'organizer.notifications.mark-all-read';
        if (endpoint.match(/\/organizer\/notifications\/\d+\/read$/)) return 'organizer.notifications.read';
        if (endpoint === '/organizer/notifications' || endpoint.includes('/organizer/notifications?')) return 'organizer.notifications';

        // Organizer dashboard
        if (endpoint === '/organizer/dashboard') return 'organizer.dashboard';
        if (endpoint === '/organizer/dashboard/timeline') return 'organizer.dashboard.timeline';
        if (endpoint.includes('/organizer/dashboard/sales-timeline')) return 'organizer.dashboard.sales-timeline';

        // Organizer events
        // Leisure venue endpoints (organizer-side)
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/config$/)) return 'organizer.event.leisure.config';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/reports\/by-issuer/)) return 'organizer.event.leisure.reports.by-issuer';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/venue-config$/)) return 'organizer.event.leisure.venue-config';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/participants/)) return 'organizer.event.leisure.participants';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/sales-timeline/)) return 'organizer.event.leisure.sales-timeline';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/dashboard\/live/)) return 'organizer.event.leisure.dashboard.live';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/raport/)) return 'organizer.event.leisure.raport';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/pos-sale/)) return 'organizer.event.leisure.pos-sale';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/shifts\/\d+/)) return 'organizer.event.leisure.shifts.item';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/shifts/)) return 'organizer.event.leisure.shifts.collection';
        if (endpoint.match(/\/organizer\/venues\/\d+\/gates\/\d+/)) return 'organizer.venue-gates.item';
        if (endpoint.match(/\/organizer\/venues\/\d+\/gates/)) return 'organizer.venue-gates.collection';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/products\/reorder/)) return 'organizer.event.leisure.products.reorder';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/products\/\d+/)) return 'organizer.event.leisure.products.item';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/products/)) return 'organizer.event.leisure.products.collection';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/boats\/sync/)) return 'organizer.event.leisure.boats.sync';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/boats/)) return 'organizer.event.leisure.boats';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/active-rentals/)) return 'organizer.event.leisure.rentals.active';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/boat-rentals\/start/)) return 'organizer.event.leisure.rentals.start';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/boat-rentals\/\d+\/end/)) return 'organizer.event.leisure.rentals.end';
        if (endpoint.match(/\/organizer\/events\/\d+\/leisure\/boat-rentals\/\d+\/finalize/)) return 'organizer.event.leisure.rentals.finalize';
        if (endpoint === '/organizer/me/active-shift') return 'organizer.me.active-shift';

        if (endpoint.match(/\/organizer\/events\/\d+\/analytics/)) return 'organizer.event.analytics';
        if (endpoint.match(/\/organizer\/events\/\d+\/goals\/\d+$/)) return 'organizer.event.goal';
        if (endpoint.match(/\/organizer\/events\/\d+\/goals$/)) return 'organizer.event.goals';
        if (endpoint.match(/\/organizer\/events\/\d+\/milestones\/\d+$/)) return 'organizer.event.milestone';
        if (endpoint.match(/\/organizer\/events\/\d+\/milestones$/)) return 'organizer.event.milestones';
        if (endpoint.match(/\/organizer\/events\/\d+\/images$/)) return 'organizer.event.images';
        if (endpoint.match(/\/organizer\/events\/\d+\/participants\/export$/)) return 'organizer.event.participants.export';
        if (endpoint.match(/\/organizer\/events\/\d+\/participants$/)) return 'organizer.event.participants';
        if (endpoint.match(/\/organizer\/events\/\d+\/check-in\//)) return 'organizer.event.checkin';
        if (endpoint.match(/\/organizer\/events\/\d+\/submit$/)) return 'organizer.event.submit';
        if (endpoint.match(/\/organizer\/events\/\d+\/cancel$/)) return 'organizer.event.cancel';
        if (endpoint.match(/\/organizer\/events\/\d+\/status$/)) return 'organizer.event.status';
        if (endpoint.match(/\/organizer\/events\/\d+\/seating-map$/)) return 'organizer.event.seating-map';
        if (endpoint.match(/\/organizer\/events\/\d+$/)) return 'organizer.event';
        if (endpoint === '/organizer/events' || endpoint.includes('/organizer/events?')) return 'organizer.events';

        // Organizer orders
        if (endpoint === '/organizer/orders/export') return 'organizer.orders.export';
        if (endpoint === '/organizer/orders' || endpoint.includes('/organizer/orders?')) return 'organizer.orders';

        // Organizer finance
        if (endpoint === '/organizer/balance') return 'organizer.balance';
        if (endpoint === '/organizer/finance') return 'organizer.finance';
        if (endpoint === '/organizer/transactions' || endpoint.includes('/organizer/transactions?')) return 'organizer.transactions';
        if (endpoint.match(/\/organizer\/payouts\/\d+$/)) return 'organizer.payout';
        if (endpoint === '/organizer/payouts' || endpoint.includes('/organizer/payouts?')) return 'organizer.payouts';

        // Organizer promo codes
        if (endpoint.match(/\/organizer\/promo-codes\/\d+$/)) return 'organizer.promo-code';
        if (endpoint === '/organizer/promo-codes' || endpoint.includes('/organizer/promo-codes?')) return 'organizer.promo-codes';

        // Organizer team
        if (endpoint === '/organizer/team') return 'organizer.team';
        if (endpoint === '/organizer/team/invite') return 'organizer.team.invite';
        if (endpoint === '/organizer/team/update') return 'organizer.team.update';
        if (endpoint === '/organizer/team/remove') return 'organizer.team.remove';
        if (endpoint === '/organizer/team/resend-invite') return 'organizer.team.resend-invite';
        if (endpoint === '/organizer/team/resend-all-invites') return 'organizer.team.resend-all-invites';

        // Organizer billing/invoices
        if (endpoint.match(/\/organizer\/invoices\/\d+\/pdf$/)) return 'organizer.invoice.pdf';
        if (endpoint.match(/\/organizer\/invoices\/export/)) return 'organizer.invoices.export';
        if (endpoint.match(/\/organizer\/invoices\/\d+$/)) return 'organizer.invoice';
        if (endpoint === '/organizer/invoices' || endpoint.includes('/organizer/invoices?')) return 'organizer.invoices';
        if (endpoint === '/organizer/billing-info') return 'organizer.billing-info';
        if (endpoint === '/organizer/payment-methods') return 'organizer.payment-methods';

        // Organizer participants (all events)
        if (endpoint === '/organizer/participants/export') return 'organizer.participants.export';
        if (endpoint === '/organizer/participants' || endpoint.includes('/organizer/participants?')) return 'organizer.participants';
        if (endpoint === '/organizer/participants/checkin') return 'organizer.participants.checkin';

        // Organizer share links
        if (endpoint.match(/\/organizer\/share-links\/[A-Za-z0-9]+$/)) return 'organizer.share-link';
        if (endpoint === '/organizer/share-links') return 'organizer.share-links';

        // Organizer support tickets
        if (endpoint === '/organizer/support/departments' || endpoint.startsWith('/organizer/support/departments?')) return 'organizer.support.departments';
        if (endpoint.match(/\/organizer\/support\/tickets\/\d+\/messages$/)) return 'organizer.support.tickets.reply';
        if (endpoint.match(/\/organizer\/support\/tickets\/\d+\/close$/)) return 'organizer.support.tickets.close';
        if (endpoint.match(/\/organizer\/support\/tickets\/\d+\/reopen$/)) return 'organizer.support.tickets.reopen';
        if (endpoint.match(/\/organizer\/support\/tickets\/\d+$/)) return 'organizer.support.tickets.show';
        if (endpoint === '/organizer/support/tickets' || endpoint.startsWith('/organizer/support/tickets?')) return 'organizer.support.tickets';

        // Organizer API settings
        if (endpoint === '/organizer/api-key') return 'organizer.api-key';
        if (endpoint === '/organizer/api-key/regenerate') return 'organizer.api-key.regenerate';
        if (endpoint === '/organizer/webhook') return 'organizer.webhook';

        // Organizer documents (Cerere avizare, Declaratie impozite)
        if (endpoint === '/organizer/documents/events') return 'organizer.documents.events';
        if (endpoint === '/organizer/documents/generate') return 'organizer.documents.generate';
        if (endpoint.match(/\/organizer\/documents\/event\/\d+$/)) return 'organizer.documents.for-event';
        if (endpoint.match(/\/organizer\/documents\/\d+\/download$/)) return 'organizer.documents.download';
        if (endpoint.match(/\/organizer\/documents\/\d+\/view$/)) return 'organizer.documents.view';
        if (endpoint === '/organizer/documents' || endpoint.includes('/organizer/documents?')) return 'organizer.documents';

        // Organizer invitations (PDF invite generation)
        if (endpoint === '/organizer/invitations/csv-template') return 'organizer.invitations.csv-template';
        if (endpoint === '/organizer/invitations/hold-seats') return 'organizer.invitations.hold-seats';
        if (endpoint.match(/\/organizer\/invitations\/\d+\/download$/)) return 'organizer.invitations.download';
        if (endpoint.match(/\/organizer\/invitations\/\d+\/generate$/)) return 'organizer.invitations.generate';
        if (endpoint.match(/\/organizer\/invitations\/\d+\/invites$/)) return 'organizer.invitations.delete-invites';
        if (endpoint.match(/\/organizer\/invitations\/\d+$/)) {
            // Distinguish GET (show) from DELETE via action name â€” proxy maps both via batch_id
            return 'organizer.invitations.show';
        }
        if (endpoint === '/organizer/invitations' || endpoint.startsWith('/organizer/invitations?')) return 'organizer.invitations';

        // Organizer services (Extra Services / Promovare)
        if (endpoint === '/organizer/services/pricing') return 'organizer.services.pricing';
        if (endpoint === '/organizer/services/stats') return 'organizer.services.stats';
        if (endpoint === '/organizer/services/types') return 'organizer.services.types';
        if (endpoint === '/organizer/services/email-audiences' || endpoint.includes('/organizer/services/email-audiences?')) return 'organizer.services.email-audiences';
        if (endpoint.match(/\/organizer\/services\/orders\/[^\/\?]+\/pay$/)) return 'organizer.services.orders.pay';
        if (endpoint.match(/\/organizer\/services\/orders\/[^\/\?]+\/cancel$/)) return 'organizer.services.orders.cancel';
        if (endpoint.match(/\/organizer\/services\/orders\/[^\/\?]+$/)) return 'organizer.services.orders.show';
        if (endpoint === '/organizer/services/orders' || endpoint.includes('/organizer/services/orders?')) return 'organizer.services.orders';

        return null; // unknown endpoint - will cause error
    },

    /**
     * Extract params from endpoint for proxy
     */
    getProxyParams(endpoint) {
        // Extract event ID + optional shift ID from leisure shifts CRUD
        const leisureShiftMatch = endpoint.match(/^\/organizer\/events\/(\d+)\/leisure\/shifts\/(\d+)/);
        if (leisureShiftMatch) {
            return `event=${encodeURIComponent(leisureShiftMatch[1])}&shift=${encodeURIComponent(leisureShiftMatch[2])}`;
        }
        // Extract event ID + product ID from leisure products CRUD
        const leisureProductMatch = endpoint.match(/^\/organizer\/events\/(\d+)\/leisure\/products\/(\d+)/);
        if (leisureProductMatch) {
            return `event=${encodeURIComponent(leisureProductMatch[1])}&product=${encodeURIComponent(leisureProductMatch[2])}`;
        }
        // Extract event ID + rental ID from boat-rentals
        const rentalMatch = endpoint.match(/^\/organizer\/events\/(\d+)\/leisure\/boat-rentals\/(\d+)/);
        if (rentalMatch) {
            return `event=${encodeURIComponent(rentalMatch[1])}&rental=${encodeURIComponent(rentalMatch[2])}`;
        }
        // Extract venue+gate IDs
        const venueGateMatch = endpoint.match(/^\/organizer\/venues\/(\d+)\/gates\/(\d+)/);
        if (venueGateMatch) {
            return `venue=${encodeURIComponent(venueGateMatch[1])}&gate=${encodeURIComponent(venueGateMatch[2])}`;
        }
        const venueGatesMatch = endpoint.match(/^\/organizer\/venues\/(\d+)\/gates/);
        if (venueGatesMatch) {
            return `venue=${encodeURIComponent(venueGatesMatch[1])}`;
        }
        // Extract event ID from leisure organizer endpoints
        const leisureMatch = endpoint.match(/^\/organizer\/events\/(\d+)\/leisure\//);
        if (leisureMatch) {
            return `event=${encodeURIComponent(leisureMatch[1])}`;
        }

        // Extract artist slug from /artist/check-claim/{slug}
        const artistClaimMatch = endpoint.match(/^\/artist\/check-claim\/([a-z0-9-]+)$/);
        if (artistClaimMatch) {
            return `slug=${encodeURIComponent(artistClaimMatch[1])}`;
        }

        // Extract organizer slug from /marketplace-events/organizers/{slug}/contact
        const organizerContactMatch = endpoint.match(/\/marketplace-events\/organizers\/([\w-]+)\/contact$/);
        if (organizerContactMatch) {
            return `slug=${encodeURIComponent(organizerContactMatch[1])}`;
        }

        // Extract organizer slug from /marketplace-events/organizers/{slug}
        const organizerMatch = endpoint.match(/\/marketplace-events\/organizers\/([\w-]+)$/);
        if (organizerMatch) {
            return `slug=${encodeURIComponent(organizerMatch[1])}`;
        }

        // Extract order ID from /order-confirmation/{id} (public thank-you page)
        const confirmMatch = endpoint.match(/\/order-confirmation\/([\w-]+)$/);
        if (confirmMatch) {
            return `id=${encodeURIComponent(confirmMatch[1])}`;
        }

        // Extract order ID from /customer/orders/{id} (numeric or alphanumeric like MKT-W08ABJWH)
        const orderMatch = endpoint.match(/\/customer\/orders\/([\w-]+)$/);
        if (orderMatch) {
            return `id=${encodeURIComponent(orderMatch[1])}`;
        }

        // Extract ticket ID from /customer/tickets/{id}
        const ticketMatch = endpoint.match(/\/customer\/tickets\/(\d+)/);
        if (ticketMatch) {
            return `id=${encodeURIComponent(ticketMatch[1])}`;
        }

        // Extract customer support ticket id from /customer/support-tickets/{id}[/messages]
        const customerSupportMatch = endpoint.match(/\/customer\/support-tickets\/(\d+)(?:\/messages)?$/);
        if (customerSupportMatch) {
            return `id=${encodeURIComponent(customerSupportMatch[1])}`;
        }

        // Extract support ticket id from /organizer/support/tickets/{id}[/messages|close|reopen]
        const supportTicketMatch = endpoint.match(/\/organizer\/support\/tickets\/(\d+)(\/messages|\/close|\/reopen)?$/);
        if (supportTicketMatch) {
            return `id=${encodeURIComponent(supportTicketMatch[1])}`;
        }

        // Extract refund ID from /customer/refunds/{id}/cancel or /customer/refunds/{id}
        const refundCancelMatch = endpoint.match(/\/customer\/refunds\/(\d+)\/cancel$/);
        if (refundCancelMatch) {
            return `id=${encodeURIComponent(refundCancelMatch[1])}`;
        }
        const refundMatch = endpoint.match(/\/customer\/refunds\/(\d+)$/);
        if (refundMatch) {
            return `id=${encodeURIComponent(refundMatch[1])}`;
        }

        // Extract review ID from /customer/reviews/{id}
        const reviewMatch = endpoint.match(/\/customer\/reviews\/(\d+)/);
        if (reviewMatch) {
            return `id=${encodeURIComponent(reviewMatch[1])}`;
        }

        // bilete.online /cont/setari extras (2026-05-30)
        // Sessions / Beneficiaries / Payment methods all use /customer/<resource>/{id}
        const accountResourceMatch = endpoint.match(/\/customer\/(?:sessions|beneficiaries|payment-methods)\/(\d+)(?:\/default)?$/);
        if (accountResourceMatch) {
            return `id=${encodeURIComponent(accountResourceMatch[1])}`;
        }

        // Extract watchlist ID from /customer/watchlist/{id}
        const watchlistMatch = endpoint.match(/\/customer\/watchlist\/(\d+)/);
        if (watchlistMatch) {
            return `id=${encodeURIComponent(watchlistMatch[1])}`;
        }

        // Extract notification ID from /customer/notifications/{id}
        const notificationMatch = endpoint.match(/\/customer\/notifications\/(\d+)/);
        if (notificationMatch) {
            return `id=${encodeURIComponent(notificationMatch[1])}`;
        }

        // Extract order ID from /orders/{id}/pay or /orders/{id}/payment-status
        const ordersPayMatch = endpoint.match(/\/orders\/(\d+)\/(pay|payment-status)$/);
        if (ordersPayMatch) {
            return `id=${encodeURIComponent(ordersPayMatch[1])}`;
        }

        // Extract slug from date/slot/resource availability endpoints
        const availSlugMatch = endpoint.match(/\/marketplace-events\/([a-z0-9-]+)\/(date|slot|resource)-availability/i);
        if (availSlugMatch) {
            return `slug=${encodeURIComponent(availSlugMatch[1])}`;
        }

        // Extract slug from endpoints like /marketplace-events/event-slug or /marketplace-events/event-slug/verify-password
        const eventVerifyMatch = endpoint.match(/\/marketplace-events\/([a-z0-9-]+)\/verify-password$/i);
        if (eventVerifyMatch) {
            return `slug=${encodeURIComponent(eventVerifyMatch[1])}`;
        }
        const eventMatch = endpoint.match(/\/marketplace-events\/([a-z0-9-]+)$/i);
        if (eventMatch) {
            return `slug=${encodeURIComponent(eventMatch[1])}`;
        }

        // Extract slug from event tracking endpoints: /events/{slug}/track-view, /events/{slug}/toggle-interest, /events/{slug}/check-interest
        const eventTrackingMatch = endpoint.match(/\/events\/([a-z0-9-]+)\/(track-view|toggle-interest|check-interest)$/i);
        if (eventTrackingMatch) {
            return `slug=${encodeURIComponent(eventTrackingMatch[1])}`;
        }

        // Tour public landing page: /tours/{slug}
        const tourMatch = endpoint.match(/^\/tours\/([a-z0-9-]+)/i);
        if (tourMatch) {
            return `slug=${encodeURIComponent(tourMatch[1])}`;
        }

        // Venue category slug extraction
        const venueCategoryMatch = endpoint.match(/\/venue-categories\/([a-z0-9-]+)$/i);
        if (venueCategoryMatch) {
            return `slug=${encodeURIComponent(venueCategoryMatch[1])}`;
        }

        // Venue favorite endpoints - extract slug before /toggle-favorite or /check-favorite
        const venueFavoriteMatch = endpoint.match(/\/venues\/([a-z0-9-]+)\/(toggle-favorite|check-favorite)/i);
        if (venueFavoriteMatch) {
            return `slug=${encodeURIComponent(venueFavoriteMatch[1])}`;
        }

        const venueMatch = endpoint.match(/\/venues\/([a-z0-9-]+)$/i);
        if (venueMatch) {
            return `slug=${encodeURIComponent(venueMatch[1])}`;
        }

        // Artist favorite endpoints - extract slug before /toggle-favorite or /check-favorite
        const artistFavoriteMatch = endpoint.match(/\/artists\/([a-z0-9-]+)\/(toggle-favorite|check-favorite)/i);
        if (artistFavoriteMatch) {
            return `slug=${encodeURIComponent(artistFavoriteMatch[1])}`;
        }

        // Artist events endpoint - extract slug before /events
        const artistEventsMatch = endpoint.match(/\/artists\/([a-z0-9-]+)\/events/i);
        if (artistEventsMatch) {
            const slug = artistEventsMatch[1];
            const queryStart = endpoint.indexOf('?');
            if (queryStart !== -1) {
                return `slug=${encodeURIComponent(slug)}&${endpoint.substring(queryStart + 1)}`;
            }
            return `slug=${encodeURIComponent(slug)}`;
        }

        const artistMatch = endpoint.match(/\/artists\/([a-z0-9-]+)$/i);
        if (artistMatch) {
            return `slug=${encodeURIComponent(artistMatch[1])}`;
        }

        // Location city endpoint - extract slug
        const cityMatch = endpoint.match(/\/locations\/cities\/([a-z0-9-]+)$/i);
        if (cityMatch) {
            return `slug=${encodeURIComponent(cityMatch[1])}`;
        }

        // Location region endpoint - extract slug
        const regionMatch = endpoint.match(/\/locations\/regions\/([a-z0-9-]+)$/i);
        if (regionMatch) {
            return `slug=${encodeURIComponent(regionMatch[1])}`;
        }

        // Organizer event analytics - extract event ID and query params
        const organizerEventAnalyticsMatch = endpoint.match(/\/organizer\/events\/(\d+)\/analytics/);
        if (organizerEventAnalyticsMatch) {
            const eventId = organizerEventAnalyticsMatch[1];
            const queryStart = endpoint.indexOf('?');
            if (queryStart !== -1) {
                return `event_id=${encodeURIComponent(eventId)}&${endpoint.substring(queryStart + 1)}`;
            }
            return `event_id=${encodeURIComponent(eventId)}`;
        }

        // Organizer event goals - extract event ID and optional goal ID
        const organizerEventGoalMatch = endpoint.match(/\/organizer\/events\/(\d+)\/goals\/(\d+)$/);
        if (organizerEventGoalMatch) {
            return `event_id=${encodeURIComponent(organizerEventGoalMatch[1])}&goal_id=${encodeURIComponent(organizerEventGoalMatch[2])}`;
        }
        const organizerEventGoalsMatch = endpoint.match(/\/organizer\/events\/(\d+)\/goals$/);
        if (organizerEventGoalsMatch) {
            return `event_id=${encodeURIComponent(organizerEventGoalsMatch[1])}`;
        }

        // Organizer event milestones - extract event ID and optional milestone ID
        const organizerEventMilestoneMatch = endpoint.match(/\/organizer\/events\/(\d+)\/milestones\/(\d+)$/);
        if (organizerEventMilestoneMatch) {
            return `event_id=${encodeURIComponent(organizerEventMilestoneMatch[1])}&milestone_id=${encodeURIComponent(organizerEventMilestoneMatch[2])}`;
        }
        const organizerEventMilestonesMatch = endpoint.match(/\/organizer\/events\/(\d+)\/milestones$/);
        if (organizerEventMilestonesMatch) {
            return `event_id=${encodeURIComponent(organizerEventMilestonesMatch[1])}`;
        }

        // Organizer event endpoints - extract event ID
        const organizerEventParticipantsMatch = endpoint.match(/\/organizer\/events\/(\d+)\/participants/);
        if (organizerEventParticipantsMatch) {
            const eventId = organizerEventParticipantsMatch[1];
            const queryStart = endpoint.indexOf('?');
            if (queryStart !== -1) {
                return `event_id=${encodeURIComponent(eventId)}&${endpoint.substring(queryStart + 1)}`;
            }
            return `event_id=${encodeURIComponent(eventId)}`;
        }

        const organizerEventCheckinMatch = endpoint.match(/\/organizer\/events\/(\d+)\/check-in\/(.+)/);
        if (organizerEventCheckinMatch) {
            return `event_id=${encodeURIComponent(organizerEventCheckinMatch[1])}&barcode=${encodeURIComponent(organizerEventCheckinMatch[2])}`;
        }

        const organizerEventImagesMatch = endpoint.match(/\/organizer\/events\/(\d+)\/images$/);
        if (organizerEventImagesMatch) {
            return `event_id=${encodeURIComponent(organizerEventImagesMatch[1])}`;
        }

        const organizerEventActionMatch = endpoint.match(/\/organizer\/events\/(\d+)\/(submit|cancel|status)$/);
        if (organizerEventActionMatch) {
            return `event_id=${encodeURIComponent(organizerEventActionMatch[1])}`;
        }

        const organizerEventSeatingMapMatch = endpoint.match(/\/organizer\/events\/(\d+)\/seating-map$/);
        if (organizerEventSeatingMapMatch) {
            return `event_id=${encodeURIComponent(organizerEventSeatingMapMatch[1])}`;
        }

        const organizerEventMatch = endpoint.match(/\/organizer\/events\/(\d+)$/);
        if (organizerEventMatch) {
            return `event_id=${encodeURIComponent(organizerEventMatch[1])}`;
        }

        // Organizer payout endpoint - extract payout ID
        const organizerPayoutMatch = endpoint.match(/\/organizer\/payouts\/(\d+)$/);
        if (organizerPayoutMatch) {
            return `payout_id=${encodeURIComponent(organizerPayoutMatch[1])}`;
        }

        // Organizer bank account endpoint - extract account ID
        const organizerBankAccountPrimaryMatch = endpoint.match(/\/organizer\/bank-accounts\/(\d+)\/primary$/);
        if (organizerBankAccountPrimaryMatch) {
            return `account_id=${encodeURIComponent(organizerBankAccountPrimaryMatch[1])}`;
        }
        const organizerBankAccountMatch = endpoint.match(/\/organizer\/bank-accounts\/(\d+)$/);
        if (organizerBankAccountMatch) {
            return `account_id=${encodeURIComponent(organizerBankAccountMatch[1])}`;
        }

        // Organizer promo code endpoint - extract code ID
        const organizerPromoCodeMatch = endpoint.match(/\/organizer\/promo-codes\/(\d+)$/);
        if (organizerPromoCodeMatch) {
            return `code_id=${encodeURIComponent(organizerPromoCodeMatch[1])}`;
        }

        // Organizer invoice PDF endpoint - extract invoice ID
        const organizerInvoicePdfMatch = endpoint.match(/\/organizer\/invoices\/(\d+)\/pdf$/);
        if (organizerInvoicePdfMatch) {
            return `invoice_id=${encodeURIComponent(organizerInvoicePdfMatch[1])}`;
        }

        // Organizer invoice endpoint - extract invoice ID
        const organizerInvoiceMatch = endpoint.match(/\/organizer\/invoices\/(\d+)$/);
        if (organizerInvoiceMatch) {
            return `invoice_id=${encodeURIComponent(organizerInvoiceMatch[1])}`;
        }

        // Organizer notification read - extract notification ID
        const organizerNotificationReadMatch = endpoint.match(/\/organizer\/notifications\/(\d+)\/read$/);
        if (organizerNotificationReadMatch) {
            return `id=${encodeURIComponent(organizerNotificationReadMatch[1])}`;
        }

        // Organizer share links - extract code parameter
        const shareLinkMatch = endpoint.match(/\/organizer\/share-links\/([A-Za-z0-9]+)$/);
        if (shareLinkMatch) {
            return `code=${encodeURIComponent(shareLinkMatch[1])}`;
        }

        // Organizer documents endpoint - extract event ID or document ID
        const organizerDocumentForEventMatch = endpoint.match(/\/organizer\/documents\/event\/(\d+)$/);
        if (organizerDocumentForEventMatch) {
            return `event_id=${encodeURIComponent(organizerDocumentForEventMatch[1])}`;
        }
        const organizerDocumentDownloadMatch = endpoint.match(/\/organizer\/documents\/(\d+)\/download$/);
        if (organizerDocumentDownloadMatch) {
            return `document_id=${encodeURIComponent(organizerDocumentDownloadMatch[1])}`;
        }
        const organizerDocumentViewMatch = endpoint.match(/\/organizer\/documents\/(\d+)\/view$/);
        if (organizerDocumentViewMatch) {
            return `document_id=${encodeURIComponent(organizerDocumentViewMatch[1])}`;
        }

        // Organizer invitations - extract batch id (for /{id}, /{id}/generate, /{id}/download, /{id}/invites)
        const invBatchMatch = endpoint.match(/\/organizer\/invitations\/(\d+)(?:\/(?:generate|download|invites))?$/);
        if (invBatchMatch) {
            return `batch_id=${encodeURIComponent(invBatchMatch[1])}`;
        }

        // Organizer services - extract UUID from /organizer/services/orders/{uuid}[/action]
        const serviceOrderUuidMatch = endpoint.match(/\/organizer\/services\/orders\/([^\/\?]+)/);
        if (serviceOrderUuidMatch) {
            return `uuid=${encodeURIComponent(serviceOrderUuidMatch[1])}`;
        }

        // Cart item management - extract item key (but NOT /cart/items/with-seats)
        const cartItemMatch = endpoint.match(/\/cart\/items\/([^/?]+)$/);
        if (cartItemMatch && cartItemMatch[1] !== 'with-seats') {
            return `item_key=${encodeURIComponent(cartItemMatch[1])}`;
        }

        // Pass through query params
        const queryStart = endpoint.indexOf('?');
        if (queryStart !== -1) {
            return endpoint.substring(queryStart + 1);
        }

        return '';
    },

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * PATCH request
     */
    async patch(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    async delete(endpoint, data = null) {
        const options = { method: 'DELETE' };
        if (data) {
            options.body = JSON.stringify(data);
        }
        return this.request(endpoint, options);
    },

    // ==================== PUBLIC ENDPOINTS ====================

    /**
     * Get marketplace configuration
     */
    async getConfig() {
        return this.get('/config');
    },

    /**
     * Get list of events
     */
    async getEvents(params = {}) {
        return this.get('/marketplace-events', params);
    },

    /**
     * Get featured events
     */
    async getFeaturedEvents(limit = 6) {
        return this.get('/marketplace-events/featured', { limit });
    },

    /**
     * Get event categories
     */
    async getCategories() {
        return this.get('/marketplace-events/categories');
    },

    /**
     * Get all event types (global taxonomy, for profiling)
     */
    async getEventTypes() {
        return this.get('/event-types');
    },

    /**
     * Get event genres, optionally filtered by event type IDs
     */
    async getEventGenres(eventTypeIds = []) {
        const params = {};
        if (eventTypeIds.length > 0) {
            params.event_type_ids = eventTypeIds.join(',');
        }
        return this.get('/event-genres', params);
    },

    /**
     * Get event cities
     */
    async getCities() {
        return this.get('/marketplace-events/cities');
    },

    /**
     * Get single event details
     */
    async getEvent(identifier, params = {}) {
        return this.get(`/marketplace-events/${identifier}`, params);
    },

    /**
     * Get event availability
     */
    async getEventAvailability(eventId) {
        return this.get(`/marketplace-events/${eventId}/availability`);
    },

    /**
     * Verify event access password
     */
    async verifyEventPassword(identifier, password) {
        return this.post(`/marketplace-events/${identifier}/verify-password`, { password });
    },

    /**
     * Extract UTM and ad click parameters from current URL
     */
    getTrackingParams() {
        const params = new URLSearchParams(window.location.search);
        const trackingParams = {};

        // UTM parameters
        const utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        utmFields.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Ad click IDs
        const clickIds = ['gclid', 'fbclid', 'ttclid', 'li_fat_id'];
        clickIds.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Facebook browser cookies (if available)
        const fbFields = ['fbc', 'fbp'];
        fbFields.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Also try to get fbc/fbp from cookies (Facebook Pixel convention)
        try {
            const cookies = document.cookie.split(';').reduce((acc, cookie) => {
                const [key, value] = cookie.trim().split('=');
                acc[key] = value;
                return acc;
            }, {});
            if (cookies['_fbc'] && !trackingParams.fbc) trackingParams.fbc = cookies['_fbc'];
            if (cookies['_fbp'] && !trackingParams.fbp) trackingParams.fbp = cookies['_fbp'];
        } catch (e) {
            // Ignore cookie access errors
        }

        // Document referrer (if not same origin)
        try {
            const referrer = document.referrer;
            if (referrer) {
                const referrerUrl = new URL(referrer);
                const currentUrl = new URL(window.location.href);
                // Only include referrer if it's from a different domain
                if (referrerUrl.hostname !== currentUrl.hostname) {
                    trackingParams.referrer = referrer;
                }
            }
        } catch (e) {
            // Ignore URL parsing errors
        }

        return trackingParams;
    },

    /**
     * Track event page view
     */
    async trackEventView(slug) {
        const trackingParams = this.getTrackingParams();
        return this.post(`/events/${slug}/track-view`, trackingParams);
    },

    /**
     * Toggle interest for an event
     */
    async toggleEventInterest(slug) {
        return this.post(`/events/${slug}/toggle-interest`);
    },

    /**
     * Check if user is interested in an event
     */
    async checkEventInterest(slug) {
        return this.get(`/events/${slug}/check-interest`);
    },

    /**
     * Toggle favorite for an artist
     */
    async toggleArtistFavorite(slug) {
        return this.post(`/artists/${slug}/toggle-favorite`);
    },

    /**
     * Check if user has favorited an artist
     */
    async checkArtistFavorite(slug) {
        return this.get(`/artists/${slug}/check-favorite`);
    },

    /**
     * Toggle favorite for a venue
     */
    async toggleVenueFavorite(slug) {
        return this.post(`/venues/${slug}/toggle-favorite`);
    },

    /**
     * Check if user has favorited a venue
     */
    async checkVenueFavorite(slug) {
        return this.get(`/venues/${slug}/check-favorite`);
    },

    /**
     * Get list of all favorite artists
     */
    async getFavoriteArtists() {
        return this.get('/customer/favorites/artists');
    },

    /**
     * Get list of all favorite venues
     */
    async getFavoriteVenues() {
        return this.get('/customer/favorites/venues');
    },

    /**
     * Get favorites summary (counts)
     */
    async getFavoritesSummary() {
        return this.get('/customer/favorites/summary');
    },

    /**
     * Get single venue details
     */
    async getVenue(slug) {
        return this.get(`/venues/${slug}`);
    },

    /**
     * Get list of venues
     */
    async getVenues(params = {}) {
        return this.get('/venues', params);
    },

    /**
     * Get venue categories
     */
    async getVenueCategories() {
        return this.get('/venue-categories');
    },

    /**
     * Get featured venues
     */
    async getFeaturedVenues(limit = 6) {
        return this.get('/venues/featured', { limit });
    },

    /**
     * Validate promo code
     */
    async validatePromoCode(code, eventId, cartTotal, ticketCount, customerEmail = null, items = null) {
        const payload = {
            code,
            event_id: eventId,
            cart_total: cartTotal,
            ticket_count: ticketCount,
            customer_email: customerEmail
        };
        if (items && items.length > 0) {
            payload.items = items;
        }
        return this.post('/promo-codes/validate', payload);
    },

    // ==================== CUSTOMER ENDPOINTS ====================

    customer: {
        /**
         * Register new customer
         */
        async register(data) {
            return BileteOnlineAPI.post('/customer/register', data);
        },

        /**
         * Login customer
         */
        async login(email, password) {
            return BileteOnlineAPI.post('/customer/login', { email, password });
        },

        /**
         * Logout customer
         */
        async logout() {
            return BileteOnlineAPI.post('/customer/logout');
        },

        /**
         * Get current customer profile
         */
        async getProfile() {
            return BileteOnlineAPI.get('/customer/me');
        },

        /**
         * Get rich profile data (taste profile, top artists, cities, etc.)
         */
        async getProfileData() {
            return BileteOnlineAPI.get('/customer/profile-data');
        },

        /**
         * Get smart suggestions for progressive profiling (cities, venues from history)
         */
        async getSmartSuggestions() {
            return BileteOnlineAPI.get('/customer/smart-suggestions');
        },

        /**
         * Upload avatar image
         */
        async uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file);

            const baseUrl = BileteOnlineAPI.getApiUrl();
            const url = `${baseUrl}?action=customer.avatar`;
            const headers = {};
            const token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }

            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData,
            });

            const data = await response.json();
            if (!response.ok) {
                throw new APIError(data.message || 'Upload failed', response.status, data);
            }
            return data;
        },

        /**
         * Update customer profile
         */
        async updateProfile(data) {
            return BileteOnlineAPI.put('/customer/profile', data);
        },

        /**
         * Change password
         */
        async changePassword(currentPassword, newPassword, confirmPassword) {
            return BileteOnlineAPI.put('/customer/password', {
                current_password: currentPassword,
                password: newPassword,
                password_confirmation: confirmPassword
            });
        },

        /**
         * Request password reset
         */
        async forgotPassword(email) {
            return BileteOnlineAPI.post('/customer/forgot-password', { email });
        },

        /**
         * Reset password
         */
        async resetPassword(token, email, password, confirmPassword) {
            return BileteOnlineAPI.post('/customer/reset-password', {
                token,
                email,
                password,
                password_confirmation: confirmPassword
            });
        },

        /**
         * Delete account
         */
        async deleteAccount(password, reason = null) {
            return BileteOnlineAPI.delete('/customer/account', {
                password,
                reason
            });
        },

        /**
         * Verify email
         */
        async verifyEmail(token, email) {
            return BileteOnlineAPI.post('/customer/verify-email', { token, email });
        },

        /**
         * Resend verification email
         */
        async resendVerification(email) {
            return BileteOnlineAPI.post('/customer/resend-verification', { email });
        },

        /**
         * Get customer orders
         */
        async getOrders(params = {}) {
            return BileteOnlineAPI.get('/customer/orders', params);
        },

        /**
         * Get single order
         */
        async getOrder(orderId) {
            return BileteOnlineAPI.get(`/customer/orders/${orderId}`);
        },

        // ==================== REFUND REQUESTS ====================

        /**
         * Get refund reasons
         */
        async getRefundReasons() {
            return BileteOnlineAPI.get('/customer/refunds/reasons');
        },

        /**
         * Check refund eligibility for an order
         */
        async checkRefundEligibility(orderId) {
            return BileteOnlineAPI.post('/customer/refunds/check-eligibility', { order_id: orderId });
        },

        /**
         * Submit refund request
         */
        async submitRefundRequest(data) {
            return BileteOnlineAPI.post('/customer/refunds', data);
        },

        /**
         * Get customer's refund requests
         */
        async getRefundRequests(params = {}) {
            return BileteOnlineAPI.get('/customer/refunds', params);
        },

        /**
         * Cancel a pending refund request
         */
        async cancelRefundRequest(refundId) {
            return BileteOnlineAPI.post(`/customer/refunds/${refundId}/cancel`);
        },

        /**
         * Get customer tickets
         */
        async getTickets(params = {}) {
            return BileteOnlineAPI.get('/customer/tickets', params);
        },

        /**
         * Get all tickets with filter (upcoming, past, all)
         */
        async getAllTickets(filter = 'all', params = {}) {
            return BileteOnlineAPI.get('/customer/tickets/all', { filter, ...params });
        },

        /**
         * Get single ticket with QR data
         */
        async getTicket(ticketId) {
            return BileteOnlineAPI.get(`/customer/tickets/${ticketId}`);
        },

        // ==================== DASHBOARD & STATS ====================

        /**
         * Get dashboard stats (orders, tickets, events, rewards)
         */
        async getDashboardStats() {
            return BileteOnlineAPI.get('/customer/stats');
        },

        /**
         * Get upcoming events for dashboard
         */
        async getUpcomingEvents(limit = 5) {
            return BileteOnlineAPI.get('/customer/stats/upcoming-events', { limit });
        },

        // ==================== REVIEWS ====================

        /**
         * Get customer reviews
         */
        async getReviews(params = {}) {
            return BileteOnlineAPI.get('/customer/reviews', params);
        },

        /**
         * Get events available to review
         */
        async getEventsToReview(params = {}) {
            return BileteOnlineAPI.get('/customer/reviews/events-to-review', params);
        },

        /**
         * Submit a review
         */
        async submitReview(data) {
            return BileteOnlineAPI.post('/customer/reviews', data);
        },

        /**
         * Get single review
         */
        async getReview(reviewId) {
            return BileteOnlineAPI.get(`/customer/reviews/${reviewId}`);
        },

        /**
         * Update a review
         */
        async updateReview(reviewId, data) {
            return BileteOnlineAPI.put(`/customer/reviews/${reviewId}`, data);
        },

        /**
         * Delete a review
         */
        async deleteReview(reviewId) {
            return BileteOnlineAPI.delete(`/customer/reviews/${reviewId}`);
        },

        // ==================== WATCHLIST ====================

        /**
         * Get watchlist
         */
        async getWatchlist(params = {}) {
            return BileteOnlineAPI.get('/customer/watchlist', params);
        },

        /**
         * Add event to watchlist
         */
        async addToWatchlist(eventId, options = {}) {
            return BileteOnlineAPI.post('/customer/watchlist', {
                event_id: eventId,
                notify_on_sale: options.notifyOnSale !== false,
                notify_on_changes: options.notifyOnChanges !== false
            });
        },

        /**
         * Update watchlist item preferences
         */
        async updateWatchlistItem(watchlistId, data) {
            return BileteOnlineAPI.put(`/customer/watchlist/${watchlistId}`, data);
        },

        /**
         * Remove from watchlist/favorites
         * @param {string} type - 'event', 'artist', or 'venue'
         * @param {number} id - The item ID
         */
        async removeFromWatchlist(type, id) {
            if (type === 'event') {
                // Remove event from watchlist
                return BileteOnlineAPI.delete(`/customer/watchlist/${id}`);
            } else if (type === 'artist') {
                // Toggle artist favorite off
                return BileteOnlineAPI.post(`/artists/${id}/toggle-favorite`);
            } else if (type === 'venue') {
                // Toggle venue favorite off
                return BileteOnlineAPI.post(`/venues/${id}/toggle-favorite`);
            }
            return { success: false, message: 'Unknown type' };
        },

        /**
         * Check if event is in watchlist
         */
        async checkWatchlist(eventId) {
            return BileteOnlineAPI.get('/customer/watchlist/check', { event_id: eventId });
        },

        // ==================== REWARDS & GAMIFICATION ====================

        /**
         * Get rewards overview (points, XP, badges count)
         */
        async getRewardsOverview() {
            return BileteOnlineAPI.get('/customer/rewards');
        },

        /**
         * Get points history
         */
        async getPointsHistory(params = {}) {
            return BileteOnlineAPI.get('/customer/rewards/history', params);
        },

        /**
         * Get badges (earned and available)
         */
        async getBadges() {
            return BileteOnlineAPI.get('/customer/rewards/badges');
        },

        /**
         * Get available rewards to redeem
         */
        async getAvailableRewards(params = {}) {
            return BileteOnlineAPI.get('/customer/rewards/available', params);
        },

        /**
         * Redeem a reward
         */
        async redeemReward(rewardId) {
            return BileteOnlineAPI.post('/customer/rewards/redeem', { reward_id: rewardId });
        },

        /**
         * Get redemption history
         */
        async getRedemptions(params = {}) {
            return BileteOnlineAPI.get('/customer/rewards/redemptions', params);
        },

        /**
         * Get points summary (alias for getRewardsOverview)
         */
        async getPoints() {
            return BileteOnlineAPI.get('/customer/rewards');
        },

        /**
         * Get XP/Level summary (alias for getRewardsOverview, XP data included)
         */
        async getXP() {
            return BileteOnlineAPI.get('/customer/rewards');
        },

        /**
         * Get available rewards (alias for getAvailableRewards)
         */
        async getRewards(params = {}) {
            return BileteOnlineAPI.get('/customer/rewards/available', params);
        },

        // ==================== NOTIFICATIONS ====================

        /**
         * Get notifications
         */
        async getNotifications(params = {}) {
            return BileteOnlineAPI.get('/customer/notifications', params);
        },

        /**
         * Get unread notifications count
         */
        async getUnreadNotificationsCount() {
            return BileteOnlineAPI.get('/customer/notifications/unread-count');
        },

        /**
         * Mark notifications as read
         */
        async markNotificationsRead(notificationIds = null) {
            return BileteOnlineAPI.post('/customer/notifications/mark-read', {
                notification_ids: notificationIds // null = mark all
            });
        },

        /**
         * Delete notification
         */
        async deleteNotification(notificationId) {
            return BileteOnlineAPI.delete(`/customer/notifications/${notificationId}`);
        },

        /**
         * Get notification settings
         */
        async getNotificationSettings() {
            return BileteOnlineAPI.get('/customer/notifications/settings');
        },

        /**
         * Update notification settings
         */
        async updateNotificationSettings(settings) {
            return BileteOnlineAPI.put('/customer/notifications/settings', settings);
        },

        // ==================== REFERRALS ====================

        /**
         * Get referral program info and stats
         */
        async getReferrals() {
            return BileteOnlineAPI.get('/customer/referrals');
        },

        /**
         * Regenerate referral code
         */
        async regenerateReferralCode() {
            return BileteOnlineAPI.post('/customer/referrals/regenerate-code');
        },

        /**
         * Track referral link click
         */
        async trackReferralClick(code, source = null) {
            return BileteOnlineAPI.post('/customer/referrals/track-click', { code, source });
        },

        /**
         * Get referral leaderboard
         */
        async getReferralLeaderboard(period = 'month', limit = 10) {
            return BileteOnlineAPI.get('/customer/referrals/leaderboard', { period, limit });
        },

        /**
         * Claim pending referral rewards
         */
        async claimReferralRewards() {
            return BileteOnlineAPI.post('/customer/referrals/claim-rewards');
        },

        /**
         * Validate a referral code (public, no auth required)
         */
        async validateReferralCode(code) {
            return BileteOnlineAPI.get('/customer/referrals/validate', { code });
        },

        /**
         * Get cart
         */
        async getCart() {
            return BileteOnlineAPI.get('/customer/cart');
        },

        /**
         * Add item to cart
         */
        async addToCart(eventId, ticketTypeId, quantity) {
            return BileteOnlineAPI.post('/customer/cart/items', {
                event_id: eventId,
                ticket_type_id: ticketTypeId,
                quantity
            });
        },

        /**
         * Update cart item
         */
        async updateCartItem(itemKey, quantity) {
            return BileteOnlineAPI.put(`/customer/cart/items/${itemKey}`, { quantity });
        },

        /**
         * Remove cart item
         */
        async removeCartItem(itemKey) {
            return BileteOnlineAPI.delete(`/customer/cart/items/${itemKey}`);
        },

        /**
         * Clear cart
         */
        async clearCart() {
            return BileteOnlineAPI.delete('/customer/cart');
        },

        /**
         * Apply promo code to cart
         */
        async applyPromoCode(code) {
            return BileteOnlineAPI.post('/customer/cart/promo-code', { code });
        },

        /**
         * Remove promo code from cart
         */
        async removePromoCode() {
            return BileteOnlineAPI.delete('/customer/cart/promo-code');
        },

        /**
         * Get checkout summary
         */
        async getCheckoutSummary() {
            return BileteOnlineAPI.get('/customer/checkout/summary');
        },

        /**
         * Process checkout
         */
        async checkout(data) {
            return BileteOnlineAPI.post('/customer/checkout', data);
        }
    },

    // ==================== ORGANIZER ENDPOINTS ====================

    organizer: {
        /**
         * Register new organizer
         */
        async register(data) {
            return BileteOnlineAPI.post('/organizer/register', data);
        },

        /**
         * Login organizer
         */
        async login(email, password) {
            return BileteOnlineAPI.post('/organizer/login', { email, password });
        },

        /**
         * Logout organizer
         */
        async logout() {
            return BileteOnlineAPI.post('/organizer/logout');
        },

        /**
         * Get current organizer profile
         */
        async getProfile() {
            return BileteOnlineAPI.get('/organizer/me');
        },

        /**
         * Update organizer profile
         */
        async updateProfile(data) {
            return BileteOnlineAPI.put('/organizer/profile', data);
        },

        /**
         * Get dashboard data
         */
        async getDashboard() {
            return BileteOnlineAPI.get('/organizer/dashboard');
        },

        /**
         * Get dashboard timeline
         */
        async getDashboardTimeline(params = {}) {
            return BileteOnlineAPI.get('/organizer/dashboard/timeline', params);
        },

        /**
         * Get organizer events
         */
        async getEvents(params = {}) {
            return BileteOnlineAPI.get('/organizer/events', params);
        },

        /**
         * Get single event
         */
        async getEvent(eventId) {
            return BileteOnlineAPI.get(`/organizer/events/${eventId}`);
        },

        /**
         * Create event
         */
        async createEvent(data) {
            return BileteOnlineAPI.post('/organizer/events', data);
        },

        /**
         * Update event
         */
        async updateEvent(eventId, data) {
            return BileteOnlineAPI.put(`/organizer/events/${eventId}`, data);
        },

        /**
         * Upload event images (poster and/or cover)
         */
        async uploadEventImages(eventId, posterFile, coverFile) {
            const formData = new FormData();
            if (posterFile) formData.append('poster', posterFile);
            if (coverFile) formData.append('cover_image', coverFile);

            const baseUrl = BileteOnlineAPI.getApiUrl();
            const params = BileteOnlineAPI.getProxyParams(`/organizer/events/${eventId}/images`);
            const url = `${baseUrl}?action=organizer.event.images&${params}`;
            const headers = {};
            const token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }

            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData,
            });

            const data = await response.json();
            if (!response.ok) {
                throw new APIError(data.message || 'Image upload failed', response.status, data);
            }
            return data;
        },

        /**
         * Submit event for review
         */
        async submitEvent(eventId) {
            return BileteOnlineAPI.post(`/organizer/events/${eventId}/submit`);
        },

        /**
         * Cancel event
         */
        async cancelEvent(eventId) {
            return BileteOnlineAPI.post(`/organizer/events/${eventId}/cancel`);
        },

        /**
         * Get event categories for the marketplace
         */
        async getEventCategories() {
            return BileteOnlineAPI.get('/organizer/event-categories');
        },

        /**
         * Get event genres filtered by event type IDs
         */
        async getEventGenres(typeIds = []) {
            return BileteOnlineAPI.get('/organizer/event-genres', { type_ids: typeIds });
        },

        /**
         * Search venues
         */
        async searchVenues(search = '') {
            return BileteOnlineAPI.get('/organizer/venues', { search });
        },

        /**
         * Search artists
         */
        async searchArtists(search = '') {
            return BileteOnlineAPI.get('/organizer/artists', { search });
        },

        /**
         * Create a new artist
         */
        async createArtist(name) {
            return BileteOnlineAPI.post('/organizer/artists', { name });
        },

        /**
         * Get event participants
         */
        async getParticipants(eventId, params = {}) {
            return BileteOnlineAPI.get(`/organizer/events/${eventId}/participants`, params);
        },

        /**
         * Export participants
         */
        async exportParticipants(eventId) {
            return BileteOnlineAPI.get(`/organizer/events/${eventId}/participants/export`);
        },

        /**
         * Check in ticket
         */
        async checkIn(eventId, barcode) {
            return BileteOnlineAPI.post(`/organizer/events/${eventId}/check-in/${barcode}`);
        },

        /**
         * Undo check in
         */
        async undoCheckIn(eventId, barcode) {
            return BileteOnlineAPI.delete(`/organizer/events/${eventId}/check-in/${barcode}`);
        },

        /**
         * Get organizer orders
         */
        async getOrders(params = {}) {
            return BileteOnlineAPI.get('/organizer/orders', params);
        },

        /**
         * Get balance
         */
        async getBalance() {
            return BileteOnlineAPI.get('/organizer/balance');
        },

        /**
         * Get transactions
         */
        async getTransactions(params = {}) {
            return BileteOnlineAPI.get('/organizer/transactions', params);
        },

        /**
         * Get payouts
         */
        async getPayouts(params = {}) {
            return BileteOnlineAPI.get('/organizer/payouts', params);
        },

        /**
         * Request payout
         */
        async requestPayout(data) {
            return BileteOnlineAPI.post('/organizer/payouts', data);
        },

        /**
         * Cancel payout
         */
        async cancelPayout(payoutId) {
            return BileteOnlineAPI.delete(`/organizer/payouts/${payoutId}`);
        },

        /**
         * Get promo codes
         */
        async getPromoCodes(params = {}) {
            return BileteOnlineAPI.get('/organizer/promo-codes', params);
        },

        /**
         * Create promo code
         */
        async createPromoCode(data) {
            return BileteOnlineAPI.post('/organizer/promo-codes', data);
        },

        /**
         * Update promo code
         */
        async updatePromoCode(codeId, data) {
            return BileteOnlineAPI.put(`/organizer/promo-codes/${codeId}`, data);
        },

        /**
         * Delete promo code
         */
        async deletePromoCode(codeId) {
            return BileteOnlineAPI.delete(`/organizer/promo-codes/${codeId}`);
        },

        /**
         * Update payout details
         */
        async updatePayoutDetails(data) {
            return BileteOnlineAPI.put('/organizer/payout-details', data);
        },

        // ==================== SUPPORT TICKETS ====================

        /**
         * Get support taxonomy (departments + problem types + attachment rules).
         */
        async getSupportDepartments(params = {}) {
            return BileteOnlineAPI.get('/organizer/support/departments', params);
        },

        /**
         * List the organizer's own support tickets.
         */
        async getSupportTickets(params = {}) {
            return BileteOnlineAPI.get('/organizer/support/tickets', params);
        },

        /**
         * Get a single support ticket detail + thread.
         */
        async getSupportTicket(id) {
            return BileteOnlineAPI.get(`/organizer/support/tickets/${id}`);
        },

        /**
         * Create a support ticket. Always sent as multipart so attachments
         * can ride along; the proxy handler doesn't care if there are no
         * files in the request.
         *
         * @param {Object} data - { support_problem_type_id, subject, description, meta:{...}, context:{...} }
         * @param {File[]} files - optional list of File objects (jpg/png/pdf, max 3MB each)
         */
        async createSupportTicket(data, files = []) {
            const fd = new FormData();
            fd.append('support_problem_type_id', String(data.support_problem_type_id));
            fd.append('subject', data.subject || '');
            fd.append('description', data.description || '');
            // Flatten meta + context as bracket notation so Laravel can
            // re-parse them with request->input('meta.url').
            for (const [k, v] of Object.entries(data.meta || {})) {
                if (v !== undefined && v !== null && v !== '') fd.append(`meta[${k}]`, String(v));
            }
            for (const [k, v] of Object.entries(data.context || {})) {
                if (v !== undefined && v !== null && v !== '') fd.append(`context[${k}]`, String(v));
            }
            (files || []).forEach((f) => f && fd.append('attachments[]', f));

            return BileteOnlineAPI._postMultipart('/organizer/support/tickets', fd);
        },

        /**
         * Reply on a support ticket (organizer-side).
         */
        async replySupportTicket(id, body, files = []) {
            const fd = new FormData();
            fd.append('body', body || '');
            (files || []).forEach((f) => f && fd.append('attachments[]', f));
            return BileteOnlineAPI._postMultipart(`/organizer/support/tickets/${id}/messages`, fd);
        },

        /**
         * Mark own support ticket resolved.
         */
        async closeSupportTicket(id) {
            return BileteOnlineAPI.post(`/organizer/support/tickets/${id}/close`);
        },

        /**
         * Reopen a previously resolved/closed support ticket.
         */
        async reopenSupportTicket(id) {
            return BileteOnlineAPI.post(`/organizer/support/tickets/${id}/reopen`);
        }
    },

    // ==================== ARTIST ACCOUNT ENDPOINTS ====================

    artist: {
        /**
         * Register a new artist account (optionally claiming an existing
         * /artist/{slug} profile via `artist_slug` in `data`).
         */
        async register(data) {
            return BileteOnlineAPI.post('/artist/register', data);
        },

        /**
         * Login. The Laravel controller returns structured 403 errors
         * with `data.code` of:
         *   email_not_verified | pending_approval | rejected | suspended
         * and `data.reason` for the rejected case. The page-level JS
         * inspects these to redirect appropriately.
         */
        async login(email, password) {
            return BileteOnlineAPI.post('/artist/login', { email, password });
        },

        async logout() {
            return BileteOnlineAPI.post('/artist/logout');
        },

        async getProfile() {
            return BileteOnlineAPI.get('/artist/me');
        },

        async forgotPassword(email) {
            return BileteOnlineAPI.post('/artist/forgot-password', { email });
        },

        async resetPassword(data) {
            return BileteOnlineAPI.post('/artist/reset-password', data);
        },

        async verifyEmail(email, token) {
            return BileteOnlineAPI.post('/artist/verify-email', { email, token });
        },

        async resendVerification(email) {
            return BileteOnlineAPI.post('/artist/resend-verification', { email });
        },

        /**
         * Public â€” used by artist-single.php to render the right CTA on
         * the public profile page (claim vs. verified vs. edit).
         */
        async checkClaim(artistSlug) {
            return BileteOnlineAPI.get(`/artist/check-claim/${artistSlug}`);
        },

        /**
         * Picker search for the register page. Returns up to 20 partner
         * artists with `is_claimed` flagged so claimed ones can be
         * disabled in the dropdown.
         */
        async searchArtists(q = '') {
            return BileteOnlineAPI.get('/artist/search', q ? { q } : {});
        },

        // -------- Self-service (Etapa 4) â€” auth required --------

        async getDashboard() {
            return BileteOnlineAPI.get('/artist/dashboard');
        },

        /**
         * @param {object} params - { filter: 'upcoming'|'past'|'all', per_page, page }
         */
        async getEvents(params = {}) {
            return BileteOnlineAPI.get('/artist/events', params);
        },

        async getProfile() {
            return BileteOnlineAPI.get('/artist/profile');
        },

        async updateProfile(data) {
            return BileteOnlineAPI.put('/artist/profile', data);
        },

        /** Cached server-side; returns { artist_types: [...], artist_genres: [...] } */
        async getTaxonomies() {
            return BileteOnlineAPI.get('/artist/profile/taxonomies');
        },

        /**
         * Trigger a one-shot refresh of the artist's social stats
         * (Spotify followers/popularity, YouTube subs, Facebook/Insta/
         * TikTok followers etc). Server dispatches the FetchArtistSocialStats
         * job; the response returns BEFORE the upstream APIs are hit, so
         * the UI should poll /artist/profile or just tell the user to
         * come back in a few minutes.
         */
        async refreshSocialStats() {
            return BileteOnlineAPI.post('/artist/profile/refresh-social-stats');
        },

        /**
         * Upload an image. `type` is one of: main | logo | portrait | discography.
         * Uses native FormData so the proxy can forward the multipart body
         * upstream without re-encoding.
         */
        async uploadProfileImage(file, type) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('type', type);

            const baseUrl = BileteOnlineAPI.getApiUrl();
            const url = `${baseUrl}?action=artist.profile.image`;
            const headers = {};
            const token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
            if (token) headers['Authorization'] = `Bearer ${token}`;

            const response = await fetch(url, { method: 'POST', headers, body: formData });
            const data = await response.json();
            if (!response.ok) {
                const err = new APIError(data.message || 'Upload failed', response.status, data.errors);
                err.data = data;
                throw err;
            }
            return data;
        },

        async getAccount() {
            return BileteOnlineAPI.get('/artist/account');
        },

        async updateAccount(data) {
            return BileteOnlineAPI.put('/artist/account', data);
        },

        async updatePassword(data) {
            return BileteOnlineAPI.put('/artist/account/password', data);
        },

        /**
         * Self-delete the account. Requires `password` confirmation in body.
         */
        async deleteAccount(password) {
            return BileteOnlineAPI.delete('/artist/account', { password });
        }
    }
};

/**
 * Internal: POST a FormData body through the proxy with auth.
 * Mirrors BileteOnlineAPI.post but doesn't JSON-encode the body so cURL on
 * the proxy side ships a real multipart/form-data request upstream.
 */
BileteOnlineAPI._postMultipart = async function(endpointPath, formData) {
    const action = BileteOnlineAPI.getProxyAction(endpointPath);
    const params = BileteOnlineAPI.getProxyParams(endpointPath);
    const baseUrl = BileteOnlineAPI.getApiUrl();
    if (!action) {
        throw new APIError(`Unknown endpoint: ${endpointPath}`, 400);
    }
    const url = `${baseUrl}?action=${action}${params ? '&' + params : ''}`;

    const headers = {};
    const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
    if (token) headers['Authorization'] = `Bearer ${token}`;
    // Do NOT set Content-Type â€” the browser fills it with the boundary.

    const res = await fetch(url, { method: 'POST', headers, body: formData, credentials: 'same-origin' });
    let data = null;
    try { data = await res.json(); } catch (_) { /* non-JSON */ }
    if (!res.ok) {
        throw new APIError(
            (data && (data.message || data.error)) || `HTTP ${res.status}`,
            res.status,
            data && data.errors ? data.errors : null
        );
    }
    return data;
};

/**
 * Custom API Error class
 */
class APIError extends Error {
    constructor(message, status, errors = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.errors = errors;
    }

    /**
     * Check if error is authentication related
     */
    isAuthError() {
        return this.status === 401 || this.status === 403;
    }

    /**
     * Check if error is validation related
     */
    isValidationError() {
        return this.status === 422;
    }

    /**
     * Get first error message for a field
     */
    getFieldError(field) {
        if (this.errors && this.errors[field]) {
            return Array.isArray(this.errors[field])
                ? this.errors[field][0]
                : this.errors[field];
        }
        return null;
    }

    /**
     * Get all field errors
     */
    getAllFieldErrors() {
        const result = {};
        if (this.errors) {
            for (const [field, messages] of Object.entries(this.errors)) {
                result[field] = Array.isArray(messages) ? messages[0] : messages;
            }
        }
        return result;
    }
}

// Make APIError available globally
window.APIError = APIError;

// Expose the API client on window too. A top-level `const` is a lexical global,
// not a window property, so consumers guarding on `window.BileteOnlineAPI`
// (e.g. the /cont sidebar) would otherwise see `undefined` and never hydrate.
window.BileteOnlineAPI = BileteOnlineAPI;


/* === auth.js === */
/**
 * bilete.online - Authentication Manager
 * Handles customer and organizer authentication state
 */

const BileteOnlineAuth = {
    // Storage keys
    KEYS: {
        CUSTOMER_TOKEN: 'bileteonline_customer_token',
        CUSTOMER_DATA: 'bileteonline_customer_data',
        ORGANIZER_TOKEN: 'bileteonline_organizer_token',
        ORGANIZER_DATA: 'bileteonline_organizer_data',
        ARTIST_TOKEN: 'bileteonline_artist_token',
        ARTIST_DATA: 'bileteonline_artist_data',
        USER_TYPE: 'bileteonline_user_type',
        REDIRECT_AFTER_LOGIN: 'bileteonline_redirect_after_login',
        REFERRAL_CODE: 'bileteonline_referral_code',
        REFERRAL_INFO: 'bileteonline_referral_info'
    },

    /**
     * Get current auth token (customer, organizer, or artist)
     */
    getToken() {
        const userType = this.getUserType();
        if (userType === 'organizer') {
            return localStorage.getItem(this.KEYS.ORGANIZER_TOKEN);
        }
        if (userType === 'artist') {
            return localStorage.getItem(this.KEYS.ARTIST_TOKEN);
        }
        return localStorage.getItem(this.KEYS.CUSTOMER_TOKEN);
    },

    /**
     * Get current user type
     */
    getUserType() {
        return localStorage.getItem(this.KEYS.USER_TYPE) || null;
    },

    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        return !!this.getToken();
    },

    /**
     * Check if user is a customer
     */
    isCustomer() {
        return this.getUserType() === 'customer' && this.isLoggedIn();
    },

    /**
     * Check if user is an organizer
     */
    isOrganizer() {
        return this.getUserType() === 'organizer' && this.isLoggedIn();
    },

    /**
     * Check if user is an artist
     */
    isArtist() {
        return this.getUserType() === 'artist' && this.isLoggedIn();
    },

    // ==================== CUSTOMER AUTH ====================

    /**
     * Login customer
     */
    async loginCustomer(email, password) {
        try {
            const response = await BileteOnlineAPI.customer.login(email, password);

            // 2FA challenge — backend returned a short-lived `challenge` token
            // instead of a real session token. Caller (login page) must show a
            // code input and call `finishCustomer2faLogin(challenge, code)`.
            if (response.success && response.data && response.data.requires_2fa) {
                return {
                    success: true,
                    requires2fa: true,
                    challenge: response.data.challenge,
                };
            }

            if (response.success && response.data.token) {
                this.setCustomerSession(response.data.token, response.data.customer);
                return { success: true, customer: response.data.customer };
            }

            return { success: false, message: response.message || 'Login failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Step 2 of customer login when 2FA is active. Exchanges the challenge +
     * a TOTP/recovery code for the real Sanctum session token.
     */
    async finishCustomer2faLogin(challenge, code) {
        try {
            const response = await BileteOnlineAPI.post('/customer/2fa/login', { challenge, code });
            if (response.success && response.data.token) {
                this.setCustomerSession(response.data.token, response.data.customer);
                return { success: true, customer: response.data.customer };
            }
            return { success: false, message: response.message || 'Codul nu este valid' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Register customer
     */
    async registerCustomer(data) {
        try {
            // Include referral code if present
            const referralCode = this.getReferralCode();
            if (referralCode && !data.referral_code) {
                data.referral_code = referralCode;
            }

            const response = await BileteOnlineAPI.customer.register(data);

            if (response.success) {
                // Auto-login if token is returned
                if (response.data.token) {
                    this.setCustomerSession(response.data.token, response.data.customer);
                }

                // Clear stored referral code after successful registration
                this.clearReferralCode();

                // Show referral message if user was referred
                if (response.data.referral && response.data.referral.message) {
                    setTimeout(() => {
                        if (typeof BileteOnlineNotifications !== 'undefined') {
                            BileteOnlineNotifications.success(response.data.referral.message, 6000);
                        }
                    }, 500);
                }

                return {
                    success: true,
                    customer: response.data.customer,
                    requiresVerification: !response.data.token,
                    referral: response.data.referral
                };
            }

            return { success: false, message: response.message || 'Registration failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Set customer session
     */
    setCustomerSession(token, customerData) {
        localStorage.setItem(this.KEYS.CUSTOMER_TOKEN, token);
        localStorage.setItem(this.KEYS.CUSTOMER_DATA, JSON.stringify(customerData));
        localStorage.setItem(this.KEYS.USER_TYPE, 'customer');

        // Clear organizer session if exists
        localStorage.removeItem(this.KEYS.ORGANIZER_TOKEN);
        localStorage.removeItem(this.KEYS.ORGANIZER_DATA);

        // Dispatch event
        window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
            detail: { type: 'customer', user: customerData }
        }));
    },

    /**
     * Get customer data
     */
    getCustomerData() {
        const data = localStorage.getItem(this.KEYS.CUSTOMER_DATA);
        return data ? JSON.parse(data) : null;
    },

    /**
     * Update customer data
     */
    updateCustomerData(data) {
        const current = this.getCustomerData() || {};
        const updated = { ...current, ...data };
        localStorage.setItem(this.KEYS.CUSTOMER_DATA, JSON.stringify(updated));

        window.dispatchEvent(new CustomEvent('bileteonline:auth:update', {
            detail: { type: 'customer', user: updated }
        }));
    },

    /**
     * Logout customer
     */
    async logoutCustomer() {
        try {
            await BileteOnlineAPI.customer.logout();
        } catch (e) {
            // Ignore logout API errors
        }

        this.clearCustomerSession();
        window.location.href = '/';
    },

    /**
     * Clear customer session
     */
    clearCustomerSession() {
        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);
        if (this.getUserType() === 'customer') {
            localStorage.removeItem(this.KEYS.USER_TYPE);
        }

        window.dispatchEvent(new CustomEvent('bileteonline:auth:logout', {
            detail: { type: 'customer' }
        }));
    },

    // ==================== ORGANIZER AUTH ====================

    /**
     * Login organizer
     */
    async loginOrganizer(email, password) {
        try {
            const response = await BileteOnlineAPI.organizer.login(email, password);

            if (response.success && response.data.token) {
                this.setOrganizerSession(response.data.token, response.data.organizer);
                return { success: true, organizer: response.data.organizer };
            }

            return { success: false, message: response.message || 'Login failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Register organizer
     */
    async registerOrganizer(data) {
        try {
            const response = await BileteOnlineAPI.organizer.register(data);

            if (response.success) {
                // Auto-login if token is returned (depends on verification requirements)
                if (response.data.token) {
                    this.setOrganizerSession(response.data.token, response.data.organizer);
                }
                return { success: true, organizer: response.data.organizer, requiresVerification: !response.data.token };
            }

            return { success: false, message: response.message || 'Registration failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Set organizer session
     */
    setOrganizerSession(token, organizerData) {
        localStorage.setItem(this.KEYS.ORGANIZER_TOKEN, token);
        localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(organizerData));
        localStorage.setItem(this.KEYS.USER_TYPE, 'organizer');

        // Clear customer session if exists
        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);

        // Dispatch event
        window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
            detail: { type: 'organizer', user: organizerData }
        }));
    },

    /**
     * Get organizer data
     */
    getOrganizerData() {
        const data = localStorage.getItem(this.KEYS.ORGANIZER_DATA);
        return data ? JSON.parse(data) : null;
    },

    /**
     * Update organizer data
     */
    updateOrganizerData(data) {
        const current = this.getOrganizerData() || {};
        const updated = { ...current, ...data };
        localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(updated));

        window.dispatchEvent(new CustomEvent('bileteonline:auth:update', {
            detail: { type: 'organizer', user: updated }
        }));
    },

    /**
     * Logout organizer
     */
    async logoutOrganizer() {
        try {
            await BileteOnlineAPI.organizer.logout();
        } catch (e) {
            // Ignore logout API errors
        }

        this.clearOrganizerSession();
        window.location.href = '/';
    },

    /**
     * Clear organizer session
     */
    clearOrganizerSession() {
        localStorage.removeItem(this.KEYS.ORGANIZER_TOKEN);
        localStorage.removeItem(this.KEYS.ORGANIZER_DATA);
        if (this.getUserType() === 'organizer') {
            localStorage.removeItem(this.KEYS.USER_TYPE);
        }

        window.dispatchEvent(new CustomEvent('bileteonline:auth:logout', {
            detail: { type: 'organizer' }
        }));
    },

    // ==================== ARTIST AUTH ====================

    /**
     * Login an artist account. The Laravel controller returns structured 403
     * errors with a `code` field â€” pages call this and handle the codes:
     *   email_not_verified -> redirect to /artist/verifica-email?email=...
     *   pending_approval   -> redirect to /artist/in-asteptare
     *   rejected           -> show inline rejection reason
     *   suspended          -> show inline suspended notice
     */
    async loginArtist(email, password) {
        try {
            const response = await BileteOnlineAPI.artist.login(email, password);

            if (response.success && response.data.token) {
                this.setArtistSession(response.data.token, response.data.account);
                return { success: true, account: response.data.account };
            }

            return { success: false, message: response.message || 'Login failed' };
        } catch (error) {
            // Surface the structured error code so the page can branch.
            const code = error.data?.errors?.code || null;
            const reason = error.data?.errors?.reason || null;
            return {
                success: false,
                message: error.message,
                errors: error.errors,
                code,
                reason
            };
        }
    },

    /**
     * Register a new artist account. The Laravel controller starts the
     * account in `pending` status and emails a verification link, so this
     * does NOT auto-login. Returns `requiresVerification: true` to signal
     * the page should redirect to the pending screen.
     */
    async registerArtist(data) {
        try {
            const response = await BileteOnlineAPI.artist.register(data);

            if (response.success) {
                return {
                    success: true,
                    account: response.data.account,
                    requiresVerification: !!response.data.requires_verification,
                    requiresApproval: !!response.data.requires_approval
                };
            }

            return { success: false, message: response.message || 'Registration failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Persist an artist session. Clears any prior customer/organizer session
     * so the three account types remain mutually exclusive on this device.
     */
    setArtistSession(token, accountData) {
        localStorage.setItem(this.KEYS.ARTIST_TOKEN, token);
        localStorage.setItem(this.KEYS.ARTIST_DATA, JSON.stringify(accountData));
        localStorage.setItem(this.KEYS.USER_TYPE, 'artist');

        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);
        localStorage.removeItem(this.KEYS.ORGANIZER_TOKEN);
        localStorage.removeItem(this.KEYS.ORGANIZER_DATA);

        window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
            detail: { type: 'artist', user: accountData }
        }));
    },

    getArtistData() {
        const data = localStorage.getItem(this.KEYS.ARTIST_DATA);
        return data ? JSON.parse(data) : null;
    },

    updateArtistData(data) {
        const current = this.getArtistData() || {};
        const updated = { ...current, ...data };
        localStorage.setItem(this.KEYS.ARTIST_DATA, JSON.stringify(updated));

        window.dispatchEvent(new CustomEvent('bileteonline:auth:update', {
            detail: { type: 'artist', user: updated }
        }));
    },

    async logoutArtist() {
        try {
            await BileteOnlineAPI.artist.logout();
        } catch (e) {
            // Ignore logout API errors â€” local session is cleared either way.
        }

        this.clearArtistSession();
        window.location.href = '/';
    },

    clearArtistSession() {
        localStorage.removeItem(this.KEYS.ARTIST_TOKEN);
        localStorage.removeItem(this.KEYS.ARTIST_DATA);
        if (this.getUserType() === 'artist') {
            localStorage.removeItem(this.KEYS.USER_TYPE);
        }

        window.dispatchEvent(new CustomEvent('bileteonline:auth:logout', {
            detail: { type: 'artist' }
        }));
    },

    /**
     * Require artist authentication. Redirects to /artist/login if the visitor
     * is not currently logged in as an artist.
     */
    requireArtistAuth(redirectUrl = null) {
        if (!this.isArtist()) {
            const currentUrl = redirectUrl || window.location.href;
            this.setRedirectAfterLogin(currentUrl);
            window.location.href = '/artist/login';
            return false;
        }
        return true;
    },

    // ==================== COMMON ====================

    /**
     * Generic login method (defaults to customer)
     * Alias for loginCustomer for convenience
     */
    async login(email, password) {
        return this.loginCustomer(email, password);
    },

    /**
     * Generic register method (defaults to customer)
     * Alias for registerCustomer for convenience
     */
    async register(data) {
        return this.registerCustomer(data);
    },

    /**
     * Check if authenticated (customer or organizer)
     */
    isAuthenticated() {
        return this.isLoggedIn();
    },

    /**
     * Get user data (alias for getCurrentUser)
     */
    getUser() {
        return this.getCurrentUser();
    },

    /**
     * Get current user data (customer, organizer, or artist)
     */
    getCurrentUser() {
        const userType = this.getUserType();
        if (userType === 'customer') {
            return this.getCustomerData();
        }
        if (userType === 'organizer') {
            return this.getOrganizerData();
        }
        if (userType === 'artist') {
            return this.getArtistData();
        }
        return null;
    },

    /**
     * Full logout (clear all sessions)
     */
    logout() {
        const userType = this.getUserType();
        if (userType === 'customer') {
            return this.logoutCustomer();
        }
        if (userType === 'organizer') {
            return this.logoutOrganizer();
        }
        if (userType === 'artist') {
            return this.logoutArtist();
        }

        // Clear all just in case
        this.clearCustomerSession();
        this.clearOrganizerSession();
        this.clearArtistSession();
    },

    /**
     * Set redirect URL after login
     */
    setRedirectAfterLogin(url) {
        sessionStorage.setItem(this.KEYS.REDIRECT_AFTER_LOGIN, url);
    },

    /**
     * Get and clear redirect URL after login
     */
    getRedirectAfterLogin() {
        const url = sessionStorage.getItem(this.KEYS.REDIRECT_AFTER_LOGIN);
        sessionStorage.removeItem(this.KEYS.REDIRECT_AFTER_LOGIN);
        return url;
    },

    /**
     * Require customer authentication
     * Redirects to login if not authenticated
     */
    requireCustomerAuth(redirectUrl = null) {
        if (!this.isCustomer()) {
            const currentUrl = redirectUrl || window.location.href;
            this.setRedirectAfterLogin(currentUrl);
            window.location.href = '/login';
            return false;
        }
        return true;
    },

    /**
     * Require organizer authentication
     * Redirects to organizer login if not authenticated
     */
    requireOrganizerAuth(redirectUrl = null) {
        // Check for admin impersonation token in URL before auth check
        this._handleAdminToken();

        if (!this.isOrganizer()) {
            const currentUrl = redirectUrl || window.location.href;
            this.setRedirectAfterLogin(currentUrl);
            window.location.href = '/organizator/login';
            return false;
        }
        return true;
    },

    /**
     * Handle admin impersonation token from URL (idempotent)
     */
    _handleAdminToken() {
        const urlParams = new URLSearchParams(window.location.search);
        const adminToken = urlParams.get('_admin_token');
        if (!adminToken) return;

        localStorage.setItem(this.KEYS.ORGANIZER_TOKEN, adminToken);
        localStorage.setItem(this.KEYS.USER_TYPE, 'organizer');
        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);

        // Clean token from URL
        urlParams.delete('_admin_token');
        const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '') + window.location.hash;
        history.replaceState(null, '', cleanUrl);

        // Fetch organizer data in background (once API is ready)
        setTimeout(() => {
            if (typeof BileteOnlineAPI !== 'undefined') {
                BileteOnlineAPI.get('/organizer/me').then(response => {
                    if (response.success && response.data) {
                        const orgData = response.data.organizer || response.data;
                        localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(orgData));
                        window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
                            detail: { type: 'organizer', user: orgData }
                        }));
                    }
                }).catch(() => {});
            }
        }, 100);
    },

    /**
     * Refresh user data from server
     */
    async refreshCurrentUser() {
        try {
            const userType = this.getUserType();
            if (userType === 'customer') {
                const response = await BileteOnlineAPI.customer.getProfile();
                if (response.success) {
                    this.updateCustomerData(response.data);
                    return response.data;
                }
            } else if (userType === 'organizer') {
                const response = await BileteOnlineAPI.organizer.getProfile();
                if (response.success) {
                    this.updateOrganizerData(response.data);
                    return response.data;
                }
            }
        } catch (error) {
            // Token might be expired, clear session
            if (error.isAuthError && error.isAuthError()) {
                this.logout();
            }
            throw error;
        }
        return null;
    },

    // ==================== REFERRAL HANDLING ====================

    /**
     * Get stored referral code
     */
    getReferralCode() {
        return localStorage.getItem(this.KEYS.REFERRAL_CODE);
    },

    /**
     * Store referral code
     */
    setReferralCode(code) {
        localStorage.setItem(this.KEYS.REFERRAL_CODE, code);
    },

    /**
     * Get stored referral info
     */
    getReferralInfo() {
        const info = localStorage.getItem(this.KEYS.REFERRAL_INFO);
        return info ? JSON.parse(info) : null;
    },

    /**
     * Store referral info
     */
    setReferralInfo(info) {
        localStorage.setItem(this.KEYS.REFERRAL_INFO, JSON.stringify(info));
    },

    /**
     * Clear referral data
     */
    clearReferralCode() {
        localStorage.removeItem(this.KEYS.REFERRAL_CODE);
        localStorage.removeItem(this.KEYS.REFERRAL_INFO);
    },

    /**
     * Check for referral code in URL and validate it
     */
    async checkReferralCode() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');

        if (!refCode) return;

        // Don't process if user is already logged in
        if (this.isLoggedIn()) return;

        // Don't process if we already have this code stored
        const storedCode = this.getReferralCode();
        if (storedCode === refCode) {
            // Show stored notification if available
            this.showReferralBanner();
            return;
        }

        try {
            const response = await BileteOnlineAPI.customer.validateReferralCode(refCode);
            if (response.success && response.data.valid) {
                // Store the code and info
                this.setReferralCode(refCode);
                this.setReferralInfo(response.data);

                // Show notification banner
                this.showReferralBanner(response.data);

                // Clean URL without page reload
                const url = new URL(window.location);
                url.searchParams.delete('ref');
                window.history.replaceState({}, '', url);
            }
        } catch (error) {
            console.error('Error validating referral code:', error);
        }
    },

    /**
     * Show referral notification banner
     */
    showReferralBanner(info = null) {
        const referralInfo = info || this.getReferralInfo();
        if (!referralInfo) return;

        // Don't show if already logged in
        if (this.isLoggedIn()) return;

        // Create banner if it doesn't exist
        let banner = document.getElementById('referral-banner');
        if (banner) return; // Already showing

        banner = document.createElement('div');
        banner.id = 'referral-banner';
        banner.className = 'text-sm py-2.5 transition-all duration-200 ease-in-out text-white bg-gradient-to-r from-purple-600 to-pink-600';

        const isRegisterPage = window.location.pathname.includes('inregistrare') || window.location.pathname.includes('register');
        const referrerName = referralInfo.referrer_name || 'un prieten';
        const reward = referralInfo.referred_reward || 50;
        const bannerText = isRegisterPage
            ? `Ai fost invitat de ${referrerName}! FinalizeazÄƒ Ã®nregistrarea È™i primeÈ™ti ${reward} puncte bonus.`
            : (referralInfo.message || `Ai fost invitat de ${referrerName}! PrimeÈ™ti ${reward} puncte bonus la Ã®nregistrare.`);
        const ctaButton = isRegisterPage ? '' : `
                <a href="/inregistrare" class="bg-white text-purple-600 px-4 py-1 rounded-full font-semibold hover:bg-gray-100 transition-colors text-sm">
                    ÃŽnregistreazÄƒ-te acum
                </a>`;

        banner.innerHTML = `
            <div class="flex items-center justify-center gap-3 px-4 mx-auto max-w-7xl flex-wrap">
                <span class="text-lg">ðŸŽ‰</span>
                <span class="font-medium">${bannerText}</span>
                ${ctaButton}
                <button onclick="this.closest('#referral-banner').remove()" aria-label="ÃŽnchide" class="ml-2 text-white/80 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        // Insert inside the header, before the top bar (same position as cart timer)
        const header = document.getElementById('header');
        if (header) {
            header.insertBefore(banner, header.firstChild);
        } else {
            document.body.insertBefore(banner, document.body.firstChild);
        }
    },

    /**
     * Initialize auth state (call on page load)
     */
    init() {
        // Handle admin impersonation token (also handled in requireOrganizerAuth and IIFE)
        this._handleAdminToken();

        // Check if token is still valid on page load
        if (this.isLoggedIn()) {
            // Self-heal: if we have a token but no cached profile (e.g. fresh admin
            // impersonation where head.php already cleaned the URL before this ran),
            // fetch the profile so the page renders with the real user instead of
            // demo / stale data.
            const userType = this.getUserType();
            if (userType === 'customer' && !this.getCustomerData()) {
                if (typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.customer && BileteOnlineAPI.customer.getProfile) {
                    BileteOnlineAPI.customer.getProfile().then(response => {
                        if (response && response.success && response.data) {
                            const customerData = response.data.customer || response.data;
                            this.setCustomerSession(this.getToken(), customerData);
                            window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
                                detail: { type: 'customer', user: customerData }
                            }));
                            window.dispatchEvent(new CustomEvent('bileteonline:customer:loaded', { detail: customerData }));
                        }
                    }).catch(() => {});
                }
            } else if (userType === 'organizer' && !this.getOrganizerData()) {
                if (typeof BileteOnlineAPI !== 'undefined') {
                    BileteOnlineAPI.get('/organizer/me').then(response => {
                        if (response && response.success && response.data) {
                            const orgData = response.data.organizer || response.data;
                            localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(orgData));
                            window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
                                detail: { type: 'organizer', user: orgData }
                            }));
                        }
                    }).catch(() => {});
                }
            }
        } else {
            // Check for referral code in URL
            this.checkReferralCode();

            // Show existing referral banner if code is stored
            if (this.getReferralCode()) {
                this.showReferralBanner();
            }
        }

        // Dispatch initial state
        window.dispatchEvent(new CustomEvent('bileteonline:auth:init', {
            detail: {
                isLoggedIn: this.isLoggedIn(),
                userType: this.getUserType(),
                user: this.getCurrentUser()
            }
        }));
    }
};

// Handle admin impersonation token IMMEDIATELY (before DOMContentLoaded)
// so organizer/customer pages don't redirect to login before the token is stored
(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const adminToken = urlParams.get('_admin_token');
    const adminCustomerToken = urlParams.get('_admin_customer_token');

    if (adminCustomerToken) {
        localStorage.setItem('bileteonline_customer_token', adminCustomerToken);
        localStorage.setItem('bileteonline_user_type', 'customer');
        localStorage.removeItem('bileteonline_organizer_token');
        localStorage.removeItem('bileteonline_organizer_data');
        // Stale customer_data from a previous session would cause the page to render the
        // OLD customer's profile while the impersonated token loads â€” wipe it so /customer/me
        // is the only source of truth for the impersonated session.
        localStorage.removeItem('bileteonline_customer_data');

        urlParams.delete('_admin_customer_token');
        const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '') + window.location.hash;
        history.replaceState(null, '', cleanUrl);

        // Fetch customer profile so the page can render with real data
        // (mirrors the organizer impersonation flow above).
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof BileteOnlineAPI !== 'undefined') {
                BileteOnlineAPI.get('/customer/me').then(response => {
                    if (response && response.success && response.data) {
                        const customerData = response.data.customer || response.data;
                        localStorage.setItem('bileteonline_customer_data', JSON.stringify(customerData));
                        window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
                            detail: { type: 'customer', user: customerData }
                        }));
                        // Re-render any page that listens for customer data â€” most user pages
                        // gate their fetch on bileteonline:auth:login or DOMContentLoaded with token.
                        window.dispatchEvent(new CustomEvent('bileteonline:customer:loaded', { detail: customerData }));
                    }
                }).catch(() => {});
            }
        });
        return;
    }

    if (!adminToken) return;

    localStorage.setItem('bileteonline_organizer_token', adminToken);
    localStorage.setItem('bileteonline_user_type', 'organizer');
    localStorage.removeItem('bileteonline_customer_token');
    localStorage.removeItem('bileteonline_customer_data');

    // Clean token from URL
    urlParams.delete('_admin_token');
    const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '') + window.location.hash;
    history.replaceState(null, '', cleanUrl);

    // Fetch organizer data after API is ready
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof BileteOnlineAPI !== 'undefined') {
            BileteOnlineAPI.get('/organizer/me').then(response => {
                if (response.success && response.data) {
                    const orgData = response.data.organizer || response.data;
                    localStorage.setItem('bileteonline_organizer_data', JSON.stringify(orgData));
                    window.dispatchEvent(new CustomEvent('bileteonline:auth:login', {
                        detail: { type: 'organizer', user: orgData }
                    }));
                }
            }).catch(() => {});
        }
    });
})();

// Expose globally on window. A top-level `const` is a lexical global, NOT a
// property of `window`, so any consumer that guards on `window.BileteOnlineAuth`
// (header, /cont sidebar, login redirect, avatar initials, logout buttons)
// would otherwise see `undefined` and silently treat the user as logged out.
window.BileteOnlineAuth = BileteOnlineAuth;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    BileteOnlineAuth.init();
});


