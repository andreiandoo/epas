/**
 * Ambilet.ro - Pagination Component
 * Unified pagination for all listing pages
 *
 * Modes:
 * - 'numbered' - Simple page numbers (city, category, genre)
 * - 'smart' - Prev/Next with ellipsis (events, search results)
 * - 'loadmore' - Load more button (infinite scroll style)
 */

const AmbiletPagination = {
    /**
     * Render pagination based on mode
     *
     * @param {Object} options - Pagination options
     * @param {string} options.containerId - Container element ID
     * @param {number} options.currentPage - Current page (1-indexed)
     * @param {number} options.totalPages - Total number of pages
     * @param {string} options.mode - 'numbered' | 'smart' | 'loadmore'
     * @param {Function} options.onPageChange - Callback when page changes: (pageNumber) => void
     * @param {Object} options.labels - Custom labels for buttons
     * @returns {string} HTML string (also renders to container if containerId provided)
     */
    render(options = {}) {
        const {
            containerId = 'pagination',
            currentPage = 1,
            totalPages = 1,
            mode = 'smart',
            onPageChange = null,
            labels = {}
        } = options;

        // Default labels in Romanian
        const labelDefaults = {
            previous: 'Anterior',
            next: 'Următor',
            loadMore: 'Încarcă mai multe',
            loading: 'Se încarcă...',
            page: 'Pagina',
            of: 'din'
        };
        const l = { ...labelDefaults, ...labels };

        // Store callback for button clicks
        if (onPageChange) {
            window._paginationCallback = onPageChange;
        }

        let html = '';

        if (totalPages <= 1) {
            html = '';
        } else if (mode === 'loadmore') {
            html = this.renderLoadMore(currentPage, totalPages, l);
        } else if (mode === 'numbered') {
            html = this.renderNumbered(currentPage, totalPages, l);
        } else {
            html = this.renderSmart(currentPage, totalPages, l);
        }

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
     * Simple numbered pagination (all pages shown)
     * Best for: 5-10 pages max
     */
    renderNumbered(currentPage, totalPages, labels) {
        let html = '';

        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += '<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary" disabled>' + i + '</button>';
            } else {
                html += '<button onclick="window._paginationCallback && window._paginationCallback(' + i + ')" class="w-10 h-10 font-medium transition-colors bg-white border rounded-xl border-border hover:bg-surface">' + i + '</button>';
            }
        }

        return html;
    },

    /**
     * Smart pagination with prev/next and ellipsis
     * Best for: Many pages
     */
    renderSmart(currentPage, totalPages, labels) {
        let html = '';

        // Previous button
        if (currentPage > 1) {
            html += '<button onclick="window._paginationCallback && window._paginationCallback(' + (currentPage - 1) + ')" class="px-4 py-2 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">' +
                '<span class="flex items-center gap-1">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>' +
                    labels.previous +
                '</span>' +
            '</button>';
        }

        // Page numbers with ellipsis
        const range = 2; // Pages to show on each side of current
        let pagesHtml = '';

        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 || // Always show first page
                i === totalPages || // Always show last page
                (i >= currentPage - range && i <= currentPage + range) // Pages around current
            ) {
                if (i === currentPage) {
                    pagesHtml += '<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary" disabled>' + i + '</button>';
                } else {
                    pagesHtml += '<button onclick="window._paginationCallback && window._paginationCallback(' + i + ')" class="w-10 h-10 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">' + i + '</button>';
                }
            } else if (
                (i === currentPage - range - 1 && i > 1) ||
                (i === currentPage + range + 1 && i < totalPages)
            ) {
                pagesHtml += '<span class="px-2 text-gray-400">...</span>';
            }
        }

        html += pagesHtml;

        // Next button
        if (currentPage < totalPages) {
            html += '<button onclick="window._paginationCallback && window._paginationCallback(' + (currentPage + 1) + ')" class="px-4 py-2 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">' +
                '<span class="flex items-center gap-1">' +
                    labels.next +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
                '</span>' +
            '</button>';
        }

        return html;
    },

    /**
     * Load more button (for infinite scroll style)
     * Best for: Mobile-friendly lazy loading
     */
    renderLoadMore(currentPage, totalPages, labels) {
        if (currentPage >= totalPages) {
            return ''; // No more pages to load
        }

        return '<button id="loadMoreBtn" onclick="window._paginationCallback && window._paginationCallback(' + (currentPage + 1) + ')" class="w-full px-6 py-3 font-semibold transition-colors border-2 rounded-xl text-primary border-primary hover:bg-primary hover:text-white">' +
            '<span class="flex items-center justify-center gap-2">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0 0l-4-4m4 4l4-4"/></svg>' +
                labels.loadMore +
            '</span>' +
        '</button>';
    },

    /**
     * Set loading state for load more button
     * @param {boolean} isLoading - Loading state
     * @param {Object} labels - Labels object
     */
    setLoadingState(isLoading, labels = {}) {
        const btn = document.getElementById('loadMoreBtn');
        if (!btn) return;

        const loadingLabel = labels.loading || 'Se încarcă...';
        const loadMoreLabel = labels.loadMore || 'Încarcă mai multe';

        if (isLoading) {
            btn.disabled = true;
            btn.innerHTML = '<span class="flex items-center justify-center gap-2">' +
                '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>' +
                loadingLabel +
            '</span>';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<span class="flex items-center justify-center gap-2">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0 0l-4-4m4 4l4-4"/></svg>' +
                loadMoreLabel +
            '</span>';
        }
    },

    /**
     * Render pagination info text (e.g., "Showing 1-12 of 48")
     *
     * @param {Object} options
     * @param {number} options.currentPage
     * @param {number} options.perPage
     * @param {number} options.total
     * @param {string} options.format - 'short' | 'full'
     * @returns {string} Info text
     */
    renderInfo(options = {}) {
        const {
            currentPage = 1,
            perPage = 12,
            total = 0,
            format = 'short'
        } = options;

        if (total === 0) return '';

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, total);

        if (format === 'short') {
            return start + '-' + end + ' din ' + total;
        }

        return 'Afișare ' + start + '-' + end + ' din ' + total + ' rezultate';
    },

    /**
     * Calculate pagination metadata from API response
     *
     * @param {Object} meta - API meta object (current_page, last_page, per_page, total)
     * @returns {Object} Normalized pagination data
     */
    normalize(meta) {
        if (!meta) {
            return {
                currentPage: 1,
                totalPages: 1,
                perPage: 12,
                total: 0,
                hasMore: false
            };
        }

        const currentPage = meta.current_page || meta.currentPage || 1;
        const totalPages = meta.last_page || meta.lastPage || 1;

        return {
            currentPage: currentPage,
            totalPages: totalPages,
            perPage: meta.per_page || meta.perPage || 12,
            total: meta.total || 0,
            hasMore: currentPage < totalPages
        };
    },

    /**
     * Helper: Scroll to element after page change
     * @param {string|number} target - Element ID, selector, or Y position
     * @param {string} behavior - 'smooth' | 'auto'
     */
    scrollTo(target = 400, behavior = 'smooth') {
        if (typeof target === 'number') {
            window.scrollTo({ top: target, behavior: behavior });
        } else {
            const el = document.querySelector(target) || document.getElementById(target);
            if (el) {
                el.scrollIntoView({ behavior: behavior, block: 'start' });
            }
        }
    }
};

// Make available globally
window.AmbiletPagination = AmbiletPagination;
