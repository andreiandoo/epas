/**
 * Ambilet.ro - Empty State Component
 * Reusable empty/error state displays for all pages
 *
 * Types:
 * - 'no-results' - No items found (after filtering/search)
 * - 'no-events' - No events specifically
 * - 'not-found' - Item not found (404-style)
 * - 'error' - Error loading data
 * - 'empty' - Empty list (no items exist)
 * - 'coming-soon' - Feature/content coming soon
 */

const AmbiletEmptyState = {
    // Icon library
    ICONS: {
        search: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
        sad: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
        notFound: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        empty: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>',
        location: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>',
        artist: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>',
        clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'
    },

    // Default messages in Romanian
    MESSAGES: {
        'no-results': {
            icon: 'search',
            title: 'Nu am găsit rezultate',
            text: 'Încearcă să modifici filtrele sau să cauți altceva.',
            buttonText: 'Resetează filtrele'
        },
        'no-events': {
            icon: 'sad',
            title: 'Nu am găsit evenimente',
            text: 'Încearcă să modifici filtrele sau verifică mai târziu.',
            buttonText: 'Resetează filtrele'
        },
        'not-found': {
            icon: 'notFound',
            title: 'Pagina nu a fost găsită',
            text: 'Ne pare rău, dar această pagină nu există sau a fost mutată.',
            buttonText: 'Înapoi la pagina principală',
            buttonUrl: '/'
        },
        'error': {
            icon: 'error',
            title: 'A apărut o eroare',
            text: 'Nu am putut încărca datele. Te rugăm să încerci din nou.',
            buttonText: 'Reîncearcă'
        },
        'empty': {
            icon: 'empty',
            title: 'Nu există date',
            text: 'Momentan nu există elemente de afișat.',
            buttonText: null
        },
        'coming-soon': {
            icon: 'clock',
            title: 'În curând',
            text: 'Această secțiune va fi disponibilă în curând.',
            buttonText: null
        },
        'no-venue': {
            icon: 'location',
            title: 'Locația nu a fost găsită',
            text: 'Ne pare rău, dar această locație nu există sau a fost ștearsă.',
            buttonText: 'Vezi toate locațiile',
            buttonUrl: '/locatii'
        },
        'no-artist': {
            icon: 'artist',
            title: 'Artistul nu a fost găsit',
            text: 'Ne pare rău, dar acest artist nu există sau a fost șters.',
            buttonText: 'Vezi toți artiștii',
            buttonUrl: '/artisti'
        }
    },

    /**
     * Render empty state
     *
     * @param {Object} options - Display options
     * @param {string} options.type - Predefined type: 'no-results' | 'no-events' | 'not-found' | 'error' | 'empty' | 'coming-soon'
     * @param {string} options.icon - Custom icon key from ICONS or 'sad'/'search'/etc
     * @param {string} options.title - Custom title
     * @param {string} options.text - Custom description text
     * @param {string} options.buttonText - Button text (null to hide button)
     * @param {string} options.buttonUrl - Button URL (if set, renders <a> instead of <button>)
     * @param {Function} options.onButtonClick - Button click callback (for <button>)
     * @param {string} options.size - 'small' | 'default' | 'large'
     * @param {string} options.containerId - Optional container ID to render into
     * @param {boolean} options.fullWidth - If true, uses col-span-full for grids
     * @returns {string} HTML string
     */
    render(options = {}) {
        const type = options.type || 'empty';
        const defaults = this.MESSAGES[type] || this.MESSAGES['empty'];

        const {
            icon = defaults.icon,
            title = defaults.title,
            text = defaults.text,
            buttonText = defaults.buttonText,
            buttonUrl = defaults.buttonUrl || null,
            onButtonClick = null,
            size = 'default',
            containerId = null,
            fullWidth = true
        } = options;

        // Size classes
        const sizeClasses = {
            small: { padding: 'py-8', icon: 'w-12 h-12', title: 'text-base', text: 'text-sm' },
            default: { padding: 'py-16', icon: 'w-16 h-16', title: 'text-lg', text: 'text-base' },
            large: { padding: 'py-20', icon: 'w-20 h-20', title: 'text-xl', text: 'text-lg' }
        };
        const s = sizeClasses[size] || sizeClasses.default;

        // Icon SVG
        const iconPath = this.ICONS[icon] || this.ICONS['sad'];
        const iconHtml = '<svg class="' + s.icon + ' mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + iconPath + '</svg>';

        // Button HTML
        let buttonHtml = '';
        if (buttonText) {
            if (buttonUrl) {
                buttonHtml = '<a href="' + buttonUrl + '" class="inline-block px-6 py-3 mt-4 font-semibold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">' + this.escapeHtml(buttonText) + '</a>';
            } else {
                // Store callback for button click
                if (onButtonClick) {
                    window._emptyStateCallback = onButtonClick;
                }
                buttonHtml = '<button onclick="window._emptyStateCallback && window._emptyStateCallback()" class="px-6 py-3 mt-4 font-semibold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">' + this.escapeHtml(buttonText) + '</button>';
            }
        }

        // Full width class for grid layouts
        const fullWidthClass = fullWidth ? ' col-span-full' : '';

        const html = '<div class="' + s.padding + ' text-center' + fullWidthClass + '">' +
            iconHtml +
            '<h3 class="mb-2 ' + s.title + ' font-semibold text-secondary">' + this.escapeHtml(title) + '</h3>' +
            '<p class="' + s.text + ' text-muted">' + this.escapeHtml(text) + '</p>' +
            buttonHtml +
        '</div>';

        // Render to container if provided
        if (containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = html;
            }
        }

        return html;
    },

    /**
     * Shorthand methods for common states
     */

    // No events found (after filtering)
    noEvents(options = {}) {
        return this.render({ type: 'no-events', ...options });
    },

    // No search results
    noResults(options = {}) {
        return this.render({ type: 'no-results', ...options });
    },

    // Error loading data
    error(options = {}) {
        return this.render({ type: 'error', ...options });
    },

    // Item not found (404-style)
    notFound(options = {}) {
        return this.render({ type: 'not-found', ...options });
    },

    // Venue not found
    noVenue(options = {}) {
        return this.render({ type: 'no-venue', ...options });
    },

    // Artist not found
    noArtist(options = {}) {
        return this.render({ type: 'no-artist', ...options });
    },

    /**
     * Render inline error message (smaller, for sections)
     *
     * @param {string} message - Error message
     * @param {string} containerId - Container to render into
     * @returns {string} HTML string
     */
    inlineError(message, containerId = null) {
        const html = '<div class="flex items-center justify-center gap-2 p-4 text-sm text-red-600 rounded-xl bg-red-50">' +
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
            '</svg>' +
            '<span>' + this.escapeHtml(message) + '</span>' +
        '</div>';

        if (containerId) {
            const container = document.getElementById(containerId);
            if (container) container.innerHTML = html;
        }

        return html;
    },

    /**
     * Render inline info message
     *
     * @param {string} message - Info message
     * @param {string} containerId - Container to render into
     * @returns {string} HTML string
     */
    inlineInfo(message, containerId = null) {
        const html = '<div class="flex items-center justify-center gap-2 p-4 text-sm text-blue-600 rounded-xl bg-blue-50">' +
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
            '</svg>' +
            '<span>' + this.escapeHtml(message) + '</span>' +
        '</div>';

        if (containerId) {
            const container = document.getElementById(containerId);
            if (container) container.innerHTML = html;
        }

        return html;
    },

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
    }
};

// Make available globally
window.AmbiletEmptyState = AmbiletEmptyState;
