/**
 * Ambilet.ro - Search Results Page Controller
 * Handles the full search results page functionality
 */

const SearchPage = {
    currentQuery: '',
    debounceTimeout: null,
    debounceMs: 400,
    minChars: 2,

    /**
     * Initialize search page with optional initial query
     */
    init(initialQuery = '') {
        this.currentQuery = initialQuery;
        this.bindEvents();

        // If we have an initial query, perform search
        if (initialQuery && initialQuery.length >= this.minChars) {
            this.performSearch(initialQuery);
        }
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        const form = document.getElementById('searchForm');
        const input = document.getElementById('searchInput');

        // Form submission
        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            const query = input?.value.trim() || '';
            if (query.length >= this.minChars) {
                this.updateUrl(query);
                this.performSearch(query);
            }
        });

        // Live search on input
        input?.addEventListener('input', (e) => {
            const query = e.target.value.trim();

            if (this.debounceTimeout) {
                clearTimeout(this.debounceTimeout);
            }

            if (query.length < this.minChars) {
                if (query.length === 0) {
                    this.showEmptyQuery();
                }
                return;
            }

            this.debounceTimeout = setTimeout(() => {
                this.updateUrl(query);
                this.performSearch(query);
            }, this.debounceMs);
        });
    },

    /**
     * Update URL without page reload
     */
    updateUrl(query) {
        const url = new URL(window.location);
        url.searchParams.set('q', query);
        window.history.pushState({}, '', url);

        // Update page title
        const pageTitle = document.getElementById('pageTitle');
        if (pageTitle) {
            pageTitle.textContent = `Rezultate pentru "${query}"`;
        }
    },

    /**
     * Show empty query state
     */
    showEmptyQuery() {
        document.getElementById('emptyQueryState')?.classList.remove('hidden');
        document.getElementById('loadingState')?.classList.add('hidden');
        document.getElementById('noResultsState')?.classList.add('hidden');
        document.getElementById('resultsContent')?.classList.add('hidden');

        // Hide summary badges
        document.getElementById('eventsCount')?.classList.add('hidden');
        document.getElementById('artistsCount')?.classList.add('hidden');
        document.getElementById('locationsCount')?.classList.add('hidden');
    },

    /**
     * Show loading state
     */
    showLoading() {
        document.getElementById('emptyQueryState')?.classList.add('hidden');
        document.getElementById('loadingState')?.classList.remove('hidden');
        document.getElementById('noResultsState')?.classList.add('hidden');
        document.getElementById('resultsContent')?.classList.add('hidden');
    },

    /**
     * Show no results state
     */
    showNoResults(query) {
        document.getElementById('emptyQueryState')?.classList.add('hidden');
        document.getElementById('loadingState')?.classList.add('hidden');
        document.getElementById('noResultsState')?.classList.remove('hidden');
        document.getElementById('resultsContent')?.classList.add('hidden');

        const noResultsQuery = document.getElementById('noResultsQuery');
        if (noResultsQuery) {
            noResultsQuery.textContent = query;
        }

        // Hide summary badges
        document.getElementById('eventsCount')?.classList.add('hidden');
        document.getElementById('artistsCount')?.classList.add('hidden');
        document.getElementById('locationsCount')?.classList.add('hidden');
    },

    /**
     * Perform search API call
     */
    async performSearch(query) {
        this.currentQuery = query;
        this.showLoading();

        try {
            const response = await fetch(`/api/proxy.php?action=search&q=${encodeURIComponent(query)}&limit=20`);
            const result = await response.json();

            // API returns { success: true, data: { events, artists, locations } }
            if (result.success && result.data) {
                this.renderResults(result.data);
            } else {
                this.showNoResults(query);
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showNoResults(query);
        }
    },

    /**
     * Render search results
     */
    renderResults(data) {
        const events = data.events || [];
        const artists = data.artists || [];
        const locations = data.locations || [];

        const totalResults = events.length + artists.length + locations.length;

        if (totalResults === 0) {
            this.showNoResults(this.currentQuery);
            return;
        }

        // Hide other states, show results
        document.getElementById('emptyQueryState')?.classList.add('hidden');
        document.getElementById('loadingState')?.classList.add('hidden');
        document.getElementById('noResultsState')?.classList.add('hidden');
        document.getElementById('resultsContent')?.classList.remove('hidden');

        // Update summary badges
        this.updateSummaryBadges(events.length, artists.length, locations.length);

        // Render each section
        this.renderEventsSection(events);
        this.renderArtistsSection(artists);
        this.renderLocationsSection(locations);
    },

    /**
     * Update summary badges in hero
     */
    updateSummaryBadges(eventsCount, artistsCount, locationsCount) {
        const eventsBadge = document.getElementById('eventsCount');
        const artistsBadge = document.getElementById('artistsCount');
        const locationsBadge = document.getElementById('locationsCount');

        if (eventsBadge) {
            if (eventsCount > 0) {
                eventsBadge.textContent = `${eventsCount} ${eventsCount === 1 ? 'eveniment' : 'evenimente'}`;
                eventsBadge.classList.remove('hidden');
            } else {
                eventsBadge.classList.add('hidden');
            }
        }

        if (artistsBadge) {
            if (artistsCount > 0) {
                artistsBadge.textContent = `${artistsCount} ${artistsCount === 1 ? 'artist' : 'artiști'}`;
                artistsBadge.classList.remove('hidden');
            } else {
                artistsBadge.classList.add('hidden');
            }
        }

        if (locationsBadge) {
            if (locationsCount > 0) {
                locationsBadge.textContent = `${locationsCount} ${locationsCount === 1 ? 'locație' : 'locații'}`;
                locationsBadge.classList.remove('hidden');
            } else {
                locationsBadge.classList.add('hidden');
            }
        }
    },

    /**
     * Render events section
     */
    renderEventsSection(events) {
        const section = document.getElementById('eventsSection');
        const grid = document.getElementById('eventsGrid');
        const countBadge = document.getElementById('eventsSectionCount');

        if (!events.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (countBadge) {
            countBadge.textContent = events.length;
        }

        if (grid) {
            // Use AmbiletEventCard for consistent rendering across the site
            if (typeof AmbiletEventCard !== 'undefined') {
                grid.innerHTML = AmbiletEventCard.renderMany(events, {
                    urlPrefix: '/bilete/',
                    showCategory: true,
                    showPrice: true,
                    showVenue: true
                });
            } else {
                grid.innerHTML = events.map(event => this.renderEventCard(event)).join('');
            }
        }
    },

    /**
     * Render single event card (fallback)
     */
    renderEventCard(event) {
        const date = event.start_date ? this.formatDate(event.start_date) : '';
        const image = event.image || event.poster_url || 'https://via.placeholder.com/400x250?text=Event';
        const price = event.min_price ? `de la ${event.min_price} lei` : '';
        const venue = event.venue?.name || event.venue_name || '';

        return `
            <a href="/bilete/${event.slug}" class="group block overflow-hidden bg-white border rounded-2xl border-border hover:shadow-lg transition-all">
                <div class="relative h-48 overflow-hidden bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(event.title)}" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300" loading="lazy">
                    ${price ? `<div class="absolute bottom-3 left-3 px-3 py-1 text-sm font-semibold text-white bg-primary rounded-lg">${price}</div>` : ''}
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 truncate group-hover:text-primary transition-colors">${this.escapeHtml(event.title)}</h3>
                    <div class="mt-2 text-sm text-gray-500">
                        ${date ? `<div class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${date}</div>` : ''}
                        ${venue ? `<div class="flex items-center gap-1 mt-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>${this.escapeHtml(venue)}</div>` : ''}
                    </div>
                </div>
            </a>
        `;
    },

    /**
     * Render artists section
     */
    renderArtistsSection(artists) {
        const section = document.getElementById('artistsSection');
        const grid = document.getElementById('artistsGrid');
        const countBadge = document.getElementById('artistsSectionCount');

        if (!artists.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (countBadge) {
            countBadge.textContent = artists.length;
        }

        if (grid) {
            grid.innerHTML = artists.map(artist => this.renderArtistCard(artist)).join('');
        }
    },

    /**
     * Render single artist card
     */
    renderArtistCard(artist) {
        const image = artist.image || artist.photo_url || 'https://via.placeholder.com/200x200?text=Artist';
        const genre = artist.genre || artist.type || '';

        return `
            <a href="/artist/${artist.slug}" class="group block text-center p-4 bg-white border rounded-2xl border-border hover:shadow-lg transition-all">
                <div class="relative w-24 h-24 mx-auto mb-3 overflow-hidden rounded-full bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(artist.name)}" class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-300" loading="lazy">
                </div>
                <h3 class="font-semibold text-gray-900 truncate group-hover:text-primary transition-colors">${this.escapeHtml(artist.name)}</h3>
                ${genre ? `<div class="mt-1 text-sm text-gray-500 truncate">${this.escapeHtml(genre)}</div>` : ''}
            </a>
        `;
    },

    /**
     * Render locations section
     */
    renderLocationsSection(locations) {
        const section = document.getElementById('locationsSection');
        const grid = document.getElementById('locationsGrid');
        const countBadge = document.getElementById('locationsSectionCount');

        if (!locations.length) {
            section?.classList.add('hidden');
            return;
        }

        section?.classList.remove('hidden');
        if (countBadge) {
            countBadge.textContent = locations.length;
        }

        if (grid) {
            grid.innerHTML = locations.map(location => this.renderLocationCard(location)).join('');
        }
    },

    /**
     * Render single location card
     */
    renderLocationCard(location) {
        const image = location.image || 'https://via.placeholder.com/400x200?text=Location';
        const address = [location.address, location.city].filter(Boolean).join(', ');

        return `
            <a href="/locatie/${location.slug}" class="group block overflow-hidden bg-white border rounded-2xl border-border hover:shadow-lg transition-all">
                <div class="relative h-36 overflow-hidden bg-gray-100">
                    <img src="${image}" alt="${this.escapeHtml(location.name)}" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300" loading="lazy">
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 truncate group-hover:text-primary transition-colors">${this.escapeHtml(location.name)}</h3>
                    ${address ? `<div class="mt-1 text-sm text-gray-500 truncate flex items-center gap-1"><svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>${this.escapeHtml(address)}</div>` : ''}
                </div>
            </a>
        `;
    },

    /**
     * Format date for display
     */
    formatDate(dateStr) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const days = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];
        const date = new Date(dateStr);
        return `${days[date.getDay()]}, ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
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

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SearchPage;
}
