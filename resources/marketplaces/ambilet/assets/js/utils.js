/**
 * Ambilet.ro - Utility Functions
 * Common helper functions used across the application
 */

const AmbiletUtils = {
    // ==================== NUMBER FORMATTING ====================

    /**
     * Format number with locale separators
     * @param {number} value - Number to format
     */
    formatNumber(value) {
        return new Intl.NumberFormat(AMBILET_CONFIG.CURRENCY_LOCALE || 'ro-RO').format(value);
    },

    // ==================== CURRENCY FORMATTING ====================

    /**
     * Format currency value
     * @param {number} value - Amount to format
     * @param {boolean} showSymbol - Whether to show currency symbol
     */
    formatCurrency(value, showSymbol = true) {
        const formatted = new Intl.NumberFormat(AMBILET_CONFIG.CURRENCY_LOCALE, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);

        return showSymbol ? `${formatted} ${AMBILET_CONFIG.CURRENCY_SYMBOL}` : formatted;
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
     * Get relative time (e.g., "în 2 zile", "acum 3 ore")
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
            return `în ${diffDays} zile`;
        } else if (diffDays === 1) {
            return 'mâine';
        } else if (diffDays === 0 && diffHours > 0) {
            return `în ${diffHours} ore`;
        } else if (diffMinutes > 0) {
            return `în ${diffMinutes} minute`;
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
        else feedback.push('Litere mari și mici');

        if (/[0-9]/.test(password)) score++;
        else feedback.push('Cel puțin o cifră');

        if (/[^a-zA-Z0-9]/.test(password)) score++;
        else feedback.push('Un caracter special');

        const labels = ['Foarte slabă', 'Slabă', 'Medie', 'Bună', 'Excelentă'];

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
                return AMBILET_CONFIG.PLACEHOLDER_ARTIST;
            case 'organizer':
                return AMBILET_CONFIG.PLACEHOLDER_ORGANIZER;
            default:
                return AMBILET_CONFIG.PLACEHOLDER_EVENT;
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
window.AmbiletUtils = AmbiletUtils;

// Shorthand aliases
window.formatNumber = AmbiletUtils.formatNumber.bind(AmbiletUtils);
window.formatCurrency = AmbiletUtils.formatCurrency.bind(AmbiletUtils);
window.formatDate = AmbiletUtils.formatDate.bind(AmbiletUtils);
window.debounce = AmbiletUtils.debounce;
window.throttle = AmbiletUtils.throttle;
