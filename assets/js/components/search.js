/**
 * Ambilet.ro - Search Component
 * Handles the header search overlay functionality
 */

const AmbiletSearch = {
    isOpen: false,
    searchTimeout: null,
    minChars: 2,
    debounceMs: 300,

    /**
     * Initialize search component
     */
    init() {
        this.bindEvents();
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Open search
        const searchBtn = document.getElementById('searchBtn');
        searchBtn?.addEventListener('click', () => this.open());

        // Close search
        const closeBtn = document.getElementById('searchCloseBtn');
        closeBtn?.addEventListener('click', () => this.close());

        const overlay = document.getElementById('searchOverlay');
        overlay?.addEventListener('click', () => this.close());

        // Handle input
        const input = document.getElementById('searchInput');
        input?.addEventListener('input', (e) => this.handleInput(e.target.value));
        input?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
            if (e.key === 'Enter') this.handleEnter(e.target.value);
        });

        // Popular search suggestions
        document.querySelectorAll('.search-suggestion').forEach(btn => {
            btn.addEventListener('click', () => {
                const query = btn.dataset.query;
                if (query && input) {
                    input.value = query;
                    this.handleInput(query);
                }
            });
        });

        // Close on escape key globally
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    },

    /**
     * Open search overlay
     */
    open() {
        const container = document.getElementById('searchContainer');
        const overlay = document.getElementById('searchOverlay');
        const input = document.getElementById('searchInput');

        if (container && overlay) {
            container.classList.remove('-translate-y-full');
            overlay.classList.remove('opacity-0', 'invisible');
            overlay.classList.add('opacity-100', 'visible');
            this.isOpen = true;

            // Focus input
            setTimeout(() => input?.focus(), 100);
        }
    },

    /**
     * Close search overlay
     */
    close() {
        const container = document.getElementById('searchContainer');
        const overlay = document.getElementById('searchOverlay');
        const input = document.getElementById('searchInput');

        if (container && overlay) {
            container.classList.add('-translate-y-full');
            overlay.classList.add('opacity-0', 'invisible');
            overlay.classList.remove('opacity-100', 'visible');
            this.isOpen = false;

            // Clear input and reset view
            if (input) input.value = '';
            this.resetView();
        }
    },

    /**
     * Handle search input
     */
    handleInput(value) {
        const query = value.trim();

        // Clear any pending search
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Show/hide min chars hint
        const minCharsHint = document.getElementById('searchMinChars');
        const quickLinks = document.getElementById('searchQuickLinks');
        const results = document.getElementById('searchResults');

        if (query.length === 0) {
            // Empty - show quick links
            this.resetView();
            return;
        }

        if (query.length < this.minChars) {
            // Too short - show min chars hint
            minCharsHint?.classList.remove('hidden');
            quickLinks?.classList.add('hidden');
            results?.classList.add('hidden');
            return;
        }

        // Valid query - debounce and search
        minCharsHint?.classList.add('hidden');
        quickLinks?.classList.add('hidden');
        results?.classList.remove('hidden');
        this.showLoading();

        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceMs);
    },

    /**
     * Handle enter key - go to search results page
     */
    handleEnter(value) {
        const query = value.trim();
        if (query.length >= this.minChars) {
            window.location.href = `/cauta?q=${encodeURIComponent(query)}`;
        }
    },

    /**
     * Reset view to initial state
     */
    resetView() {
        const minCharsHint = document.getElementById('searchMinChars');
        const quickLinks = document.getElementById('searchQuickLinks');
        const results = document.getElementById('searchResults');
        const loading = document.getElementById('searchLoading');
        const noResults = document.getElementById('searchNoResults');

        minCharsHint?.classList.add('hidden');
        quickLinks?.classList.remove('hidden');
        results?.classList.add('hidden');
        loading?.classList.add('hidden');
        noResults?.classList.add('hidden');
    },

    /**
     * Show loading state
     */
    showLoading() {
        const loading = document.getElementById('searchLoading');
        const noResults = document.getElementById('searchNoResults');
        const content = document.getElementById('searchResultsContent');
        const viewAll = document.getElementById('searchViewAll');

        loading?.classList.remove('hidden');
        noResults?.classList.add('hidden');
        content?.classList.add('hidden');
        viewAll?.classList.add('hidden');
    },

    /**
     * Perform search API call
     */
    async performSearch(query) {
        try {
            const response = await fetch(`/api/proxy.php?action=search&q=${encodeURIComponent(query)}&limit=5`);
            const result = await response.json();

            // API returns { success: true, data: { events, artists, locations } }
            if (result.success && result.data) {
                this.renderResults(query, result.data);
            } else {
                this.showNoResults();
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showNoResults();
        }
    },

    /**
     * Render search results
     */
    renderResults(query, data) {
        const loading = document.getElementById('searchLoading');
        const noResults = document.getElementById('searchNoResults');
        const content = document.getElementById('searchResultsContent');
        const viewAll = document.getElementById('searchViewAll');
        const viewAllLink = document.getElementById('searchViewAllLink');

        loading?.classList.add('hidden');

        const events = data.events || [];
        const artists = data.artists || [];
        const locations = data.locations || [];

        const totalResults = events.length + artists.length + locations.length;

        if (totalResults === 0) {
            this.showNoResults();
            return;
        }

        content?.classList.remove('hidden');
        noResults?.classList.add('hidden');

        // Render events
        this.renderEventsSection(events);

        // Render artists
        this.renderArtistsSection(artists);

        // Render locations
        this.renderLocationsSection(locations);

        // Show view all link
        if (viewAll && viewAllLink) {
            viewAll.classList.remove('hidden');
            viewAllLink.href = `/cauta?q=${encodeURIComponent(query)}`;
        }
    },

    /**
     * Show no results state
     */
    showNoResults() {
        const loading = document.getElementById('searchLoading');
        const noResults = document.getElementById('searchNoResults');
        const content = document.getElementById('searchResultsContent');
        const viewAll = document.getElementById('searchViewAll');

        loading?.classList.add('hidden');
        noResults?.classList.remove('hidden');
        content?.classList.add('hidden');
        viewAll?.classList.add('hidden');
    },

    /**
     * Render events section
     */
    renderEventsSection(events) {
        const section = document.getElementById('searchEventsSection');
        const list = document.getElementById('searchEventsList');
        const count = document.getElementById('searchEventsCount');

        if (!events.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (count) count.textContent = `(${events.length})`;

        if (list) {
            list.innerHTML = events.map(event => this.renderEventItem(event)).join('');
        }
    },

    /**
     * Render single event item
     */
    renderEventItem(event) {
        const date = event.start_date ? this.formatDate(event.start_date) : '';
        const image = event.image || event.poster_url || 'https://via.placeholder.com/80x80?text=Event';
        const price = event.min_price ? `de la ${event.min_price} lei` : '';
        const venue = event.venue?.name || event.venue_name || '';

        return `
            <a href="/bilete/${event.slug}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-lg bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(event.title)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-gray-900 truncate">${this.escapeHtml(event.title)}</div>
                    <div class="text-xs text-gray-500 truncate">${date}${venue ? ' • ' + this.escapeHtml(venue) : ''}</div>
                    ${price ? `<div class="text-xs font-semibold text-primary mt-1">${price}</div>` : ''}
                </div>
            </a>
        `;
    },

    /**
     * Render artists section
     */
    renderArtistsSection(artists) {
        const section = document.getElementById('searchArtistsSection');
        const list = document.getElementById('searchArtistsList');
        const count = document.getElementById('searchArtistsCount');

        if (!artists.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (count) count.textContent = `(${artists.length})`;

        if (list) {
            list.innerHTML = artists.map(artist => this.renderArtistItem(artist)).join('');
        }
    },

    /**
     * Render single artist item
     */
    renderArtistItem(artist) {
        const image = artist.image || artist.photo_url || 'https://via.placeholder.com/80x80?text=Artist';
        const genre = artist.genre || artist.type || '';

        return `
            <a href="/artist/${artist.slug}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-12 h-12 overflow-hidden rounded-full bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(artist.name)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="text-sm font-semibold text-gray-900 truncate">${this.escapeHtml(artist.name)}</div>
                    ${genre ? `<div class="text-xs text-gray-500 truncate">${this.escapeHtml(genre)}</div>` : ''}
                </div>
            </a>
        `;
    },

    /**
     * Render locations section
     */
    renderLocationsSection(locations) {
        const section = document.getElementById('searchLocationsSection');
        const list = document.getElementById('searchLocationsList');
        const count = document.getElementById('searchLocationsCount');

        if (!locations.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (count) count.textContent = `(${locations.length})`;

        if (list) {
            list.innerHTML = locations.map(location => this.renderLocationItem(location)).join('');
        }
    },

    /**
     * Render single location item
     */
    renderLocationItem(location) {
        const image = location.image || 'https://via.placeholder.com/80x80?text=Location';
        const address = location.address || location.city || '';

        return `
            <a href="/locatie/${location.slug}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-12 h-12 overflow-hidden rounded-lg bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(location.name)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="text-sm font-semibold text-gray-900 truncate">${this.escapeHtml(location.name)}</div>
                    ${address ? `<div class="text-xs text-gray-500 truncate">${this.escapeHtml(address)}</div>` : ''}
                </div>
            </a>
        `;
    },

    /**
     * Format date for display
     */
    formatDate(dateStr) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const date = new Date(dateStr);
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletSearch.init();
});
