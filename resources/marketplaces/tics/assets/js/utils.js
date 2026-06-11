/**
 * TICS.ro - Utility Functions
 */

const TicsUtils = {
    // Romanian month abbreviations
    MONTHS: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],

    // Full month names
    MONTHS_FULL: [
        'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
        'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
    ],

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Format price for display
     * @param {number} price - Price in RON
     * @returns {string} Formatted price
     */
    formatPrice(price) {
        if (price === 0 || price === null || price === undefined) return 'Gratuit';
        return `${price.toLocaleString('ro-RO')} RON`;
    },

    /**
     * Format date for display
     * @param {string|Date} date - Date to format
     * @param {string} format - Format type: 'short', 'long', 'full'
     * @returns {string} Formatted date
     */
    formatDate(date, format = 'short') {
        if (!date) return '';
        const d = new Date(date);
        const day = d.getDate();
        const month = this.MONTHS[d.getMonth()];
        const monthFull = this.MONTHS_FULL[d.getMonth()];
        const year = d.getFullYear();

        switch (format) {
            case 'short':
                return `${day} ${month}`;
            case 'long':
                return `${day} ${monthFull} ${year}`;
            case 'full':
                const weekday = d.toLocaleDateString('ro-RO', { weekday: 'long' });
                return `${weekday}, ${day} ${monthFull} ${year}`;
            default:
                return `${day} ${month} ${year}`;
        }
    },

    /**
     * Format time for display
     * @param {string|Date} date - Date/time to format
     * @returns {string} Formatted time (HH:MM)
     */
    formatTime(date) {
        if (!date) return '';
        const d = new Date(date);
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    },

    /**
     * Format date range for festivals
     * @param {string} startDate - Start date
     * @param {string} endDate - End date
     * @returns {string} Formatted range
     */
    formatDateRange(startDate, endDate) {
        if (!startDate || !endDate) return '';
        const start = new Date(startDate);
        const end = new Date(endDate);
        const startDay = start.getDate();
        const startMonth = this.MONTHS[start.getMonth()];
        const endDay = end.getDate();
        const endMonth = this.MONTHS[end.getMonth()];
        const startYear = start.getFullYear();
        const endYear = end.getFullYear();

        if (startYear === endYear) {
            if (startMonth === endMonth) {
                return `${startDay} - ${endDay} ${endMonth}`;
            } else {
                return `${startDay} ${startMonth} - ${endDay} ${endMonth}`;
            }
        } else {
            return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
        }
    },

    /**
     * Get AI match color based on percentage
     * @param {number} match - Match percentage (0-100)
     * @returns {string} Tailwind color class
     */
    getMatchColor(match) {
        if (match >= 90) return 'bg-green-500';
        if (match >= 80) return 'bg-green-500';
        if (match >= 70) return 'bg-indigo-500';
        return 'bg-amber-500';
    },

    /**
     * Get storage URL for images
     * @param {string} path - Image path
     * @returns {string} Full URL
     */
    getStorageUrl(path) {
        if (!path) return '/assets/images/default-event.jpg';
        if (path.startsWith('http')) return path;
        return `https://core.tixello.com/storage/${path}`;
    },

    /**
     * Debounce function
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
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
     * Throttle function
     * @param {Function} func - Function to throttle
     * @param {number} limit - Limit in ms
     * @returns {Function} Throttled function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Parse URL query parameters
     * @returns {Object} Query parameters
     */
    getQueryParams() {
        const params = {};
        const searchParams = new URLSearchParams(window.location.search);
        for (const [key, value] of searchParams) {
            params[key] = value;
        }
        return params;
    },

    /**
     * Update URL query parameters without reload
     * @param {Object} params - Parameters to update
     */
    updateQueryParams(params) {
        const url = new URL(window.location);
        Object.entries(params).forEach(([key, value]) => {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        });
        history.pushState({}, '', url);
    },

    /**
     * Scroll to element smoothly
     * @param {string|Element} target - Element or selector
     * @param {number} offset - Offset from top
     */
    scrollTo(target, offset = 0) {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        if (element) {
            const top = element.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    },

    /**
     * Check if element is in viewport
     * @param {Element} element - Element to check
     * @returns {boolean}
     */
    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    },

    /**
     * Convert text to URL-friendly slug
     * @param {string} text - Text to convert
     * @returns {string} URL-friendly slug
     */
    slugify(text) {
        if (!text) return '';
        // Romanian diacritics mapping
        const diacritics = {
            'ă': 'a', 'â': 'a', 'î': 'i', 'ș': 's', 'ț': 't',
            'Ă': 'a', 'Â': 'a', 'Î': 'i', 'Ș': 's', 'Ț': 't',
            'ş': 's', 'ţ': 't', 'Ş': 's', 'Ţ': 't'
        };
        return text
            .split('')
            .map(char => diacritics[char] || char)
            .join('')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    },

    /**
     * Generate skeleton HTML for loading states
     * @param {number} count - Number of skeletons
     * @returns {string} HTML string
     */
    generateSkeletons(count = 8) {
        return Array(count).fill(`
            <div class="overflow-hidden bg-white border rounded-2xl border-gray-200">
                <div class="h-48 skeleton"></div>
                <div class="p-5">
                    <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                    <div class="w-1/2 mb-3 skeleton skeleton-text"></div>
                    <div class="w-1/3 h-6 skeleton"></div>
                </div>
            </div>
        `).join('');
    }
};

// Make available globally
window.TicsUtils = TicsUtils;

// Also expose individual functions for convenience
window.escapeHtml = TicsUtils.escapeHtml.bind(TicsUtils);
window.formatPrice = TicsUtils.formatPrice.bind(TicsUtils);
window.formatDate = TicsUtils.formatDate.bind(TicsUtils);
window.getStorageUrl = TicsUtils.getStorageUrl.bind(TicsUtils);
