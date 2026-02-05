/**
 * TICS.ro - Search Component
 * Handles the header search functionality with AI suggestions
 */

const TicsSearch = {
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
        // Search input
        const input = document.getElementById('searchInput');
        if (input) {
            input.addEventListener('focus', () => this.open());
            input.addEventListener('input', (e) => this.handleInput(e.target.value));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.close();
                if (e.key === 'Enter') this.handleEnter(e.target.value);
            });
        }

        // Mobile search button
        const mobileSearchBtn = document.getElementById('mobileSearchBtn');
        if (mobileSearchBtn) {
            mobileSearchBtn.addEventListener('click', () => this.openMobile());
        }

        // Close on click outside
        document.addEventListener('click', (e) => {
            const searchContainer = document.querySelector('.search-dropdown')?.parentElement;
            if (searchContainer && !searchContainer.contains(e.target)) {
                this.close();
            }
        });

        // Keyboard shortcut (Cmd/Ctrl + K)
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                const input = document.getElementById('searchInput');
                if (input) {
                    input.focus();
                    this.open();
                }
            }
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Popular search suggestions
        document.querySelectorAll('.search-suggestion').forEach(btn => {
            btn.addEventListener('click', () => {
                const query = btn.dataset.query;
                const input = document.getElementById('searchInput');
                if (query && input) {
                    input.value = query;
                    this.handleInput(query);
                }
            });
        });
    },

    /**
     * Open search dropdown
     */
    open() {
        const dropdown = document.getElementById('searchDropdown');
        if (dropdown) {
            dropdown.classList.add('active');
            this.isOpen = true;
        }
    },

    /**
     * Close search dropdown
     */
    close() {
        const dropdown = document.getElementById('searchDropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
            this.isOpen = false;
        }
    },

    /**
     * Open mobile search (overlay)
     */
    openMobile() {
        // TODO: Implement mobile search overlay
        const input = document.getElementById('searchInput');
        if (input) {
            input.focus();
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

        // Get elements
        const minCharsHint = document.getElementById('searchMinChars');
        const quickLinks = document.getElementById('searchQuickLinks');
        const results = document.getElementById('searchResults');
        const loading = document.getElementById('searchLoading');

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
            loading?.classList.add('hidden');
            return;
        }

        // Valid query - debounce and search
        minCharsHint?.classList.add('hidden');
        quickLinks?.classList.add('hidden');
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
        const results = document.getElementById('searchResults');

        results?.classList.remove('hidden');
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
            const response = await TicsAPI.search(query, 5);

            if (response.success && response.data) {
                this.renderResults(query, response.data);
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

        // Render sections
        this.renderEventsSection(events);
        this.renderArtistsSection(artists);
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
        const date = event.starts_at || event.start_date;
        const dateFormatted = date ? TicsUtils.formatDate(date) : '';
        const image = TicsUtils.getStorageUrl(event.image || event.poster_url);
        const price = event.price_from || event.min_price;
        const priceFormatted = price ? `de la ${price} RON` : '';
        const venue = event.venue?.name || event.venue_name || '';
        const match = event.ai_match || Math.floor(Math.random() * 20 + 80); // Demo AI match

        return `
            <a href="/bilete/${TicsUtils.escapeHtml(event.slug)}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-lg bg-gray-100">
                    <img src="${image}" alt="${TicsUtils.escapeHtml(event.title || event.name)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900 truncate">${TicsUtils.escapeHtml(event.title || event.name)}</span>
                        <span class="flex items-center gap-1 px-1.5 py-0.5 bg-green-50 rounded text-xs font-medium text-green-700">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                            ${match}%
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 truncate">${dateFormatted}${venue ? ' • ' + TicsUtils.escapeHtml(venue) : ''}</div>
                    ${priceFormatted ? `<div class="text-xs font-semibold text-indigo-600 mt-1">${priceFormatted}</div>` : ''}
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
        const image = TicsUtils.getStorageUrl(artist.image || artist.photo_url);
        const genre = artist.genre || artist.type || '';

        return `
            <a href="/artist/${TicsUtils.escapeHtml(artist.slug)}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-12 h-12 overflow-hidden rounded-full bg-gray-100">
                    <img src="${image}" alt="${TicsUtils.escapeHtml(artist.name)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="text-sm font-semibold text-gray-900 truncate">${TicsUtils.escapeHtml(artist.name)}</div>
                    ${genre ? `<div class="text-xs text-gray-500 truncate">${TicsUtils.escapeHtml(genre)}</div>` : ''}
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
        const image = TicsUtils.getStorageUrl(location.image);
        const address = location.address || location.city || '';

        return `
            <a href="/locatie/${TicsUtils.escapeHtml(location.slug)}" class="flex gap-3 p-3 transition-all border border-transparent rounded-xl hover:bg-gray-50 hover:border-gray-200">
                <div class="flex-shrink-0 w-12 h-12 overflow-hidden rounded-lg bg-gray-100">
                    <img src="${image}" alt="${TicsUtils.escapeHtml(location.name)}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="text-sm font-semibold text-gray-900 truncate">${TicsUtils.escapeHtml(location.name)}</div>
                    ${address ? `<div class="text-xs text-gray-500 truncate">${TicsUtils.escapeHtml(address)}</div>` : ''}
                </div>
            </a>
        `;
    }
};

// Make available globally
window.TicsSearch = TicsSearch;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    TicsSearch.init();
});
