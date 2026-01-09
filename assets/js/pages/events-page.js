/**
 * Ambilet.ro - Events Page Controller
 * Handles main events listing page with filtering, sorting, search
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard, AmbiletPagination, AmbiletEmptyState, AmbiletDataTransformer
 */

const EventsPage = {
    // State
    events: [],
    page: 1,
    perPage: 12,
    totalPages: 1,
    view: 'grid',
    filters: {
        category: '',
        city: '',
        region: '',
        genre: '',
        artist: '',
        price: '',
        date: '',
        sort: 'date',
        search: ''
    },

    // DOM element IDs
    elements: {
        loadingState: 'loadingState',
        eventsGrid: 'eventsGrid',
        emptyState: 'emptyState',
        pagination: 'pagination',
        resultsCount: 'resultsCount',
        activeFilters: 'activeFilters',
        activeFilterTags: 'activeFilterTags',
        categoryFilters: 'categoryFilters',
        cityFilter: 'cityFilter',
        genreFilter: 'genreFilter',
        dateFilter: 'dateFilter',
        priceFilter: 'priceFilter',
        sortFilter: 'sortFilter',
        searchInput: 'searchInput',
        viewGrid: 'viewGrid',
        viewList: 'viewList'
    },

    /**
     * Initialize the page
     * @param {Object} initialFilters - Initial filter values from PHP
     */
    init(initialFilters = {}) {
        // Apply initial filters
        Object.assign(this.filters, initialFilters);

        this.loadEvents();
        this.updateActiveFilters();
        this.setView(this.view);
    },

    /**
     * Load events from API
     */
    async loadEvents() {
        const loadingEl = document.getElementById(this.elements.loadingState);
        const gridEl = document.getElementById(this.elements.eventsGrid);
        const emptyEl = document.getElementById(this.elements.emptyState);

        if (loadingEl) loadingEl.classList.remove('hidden');
        if (gridEl) gridEl.classList.add('hidden');
        if (emptyEl) emptyEl.classList.add('hidden');

        try {
            const params = new URLSearchParams({
                page: this.page,
                per_page: this.perPage
            });

            // Add active filters
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.append(key, value);
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());

            // Handle API response
            let eventsData = null;
            let meta = null;

            if (response.success !== false) {
                if (Array.isArray(response.data)) {
                    eventsData = response.data;
                    meta = response.meta;
                } else if (response.data && response.data.events) {
                    eventsData = response.data.events;
                    meta = response.data.meta;
                } else if (response.data) {
                    eventsData = [response.data];
                }
            }

            if (eventsData && eventsData.length > 0) {
                this.events = eventsData;
                this.totalPages = meta?.last_page || 1;

                const resultsEl = document.getElementById(this.elements.resultsCount);
                if (resultsEl) resultsEl.textContent = meta?.total || this.events.length;

                this.renderEvents();
                this.renderPagination();
            } else {
                this.showEmpty();
            }
        } catch (error) {
            console.error('Error loading events:', error);
            // Try demo data fallback
            if (window.AMBILET?.demoMode) {
                this.loadDemoData();
            } else {
                this.showEmpty();
            }
        }
    },

    /**
     * Load demo data as fallback
     */
    loadDemoData() {
        this.events = [
            {
                id: 1,
                title: 'Coldplay - Music of the Spheres Tour',
                slug: 'coldplay-music-of-the-spheres',
                date: '2025-06-15',
                time: '20:00',
                venue: 'Arena Nationala',
                city: 'Bucuresti',
                category: 'Concerte',
                price_from: 299,
                image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=500&fit=crop'
            },
            {
                id: 2,
                title: 'UNTOLD Festival 2025',
                slug: 'untold-2025',
                date: '2025-08-07',
                time: '16:00',
                venue: 'Cluj Arena',
                city: 'Cluj-Napoca',
                category: 'Festivaluri',
                price_from: 449,
                image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&h=500&fit=crop'
            },
            {
                id: 3,
                title: 'Electric Castle 2025',
                slug: 'electric-castle-2025',
                date: '2025-07-16',
                time: '14:00',
                venue: 'Castelul Banffy',
                city: 'Bontida',
                category: 'Festivaluri',
                price_from: 359,
                image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop'
            },
            {
                id: 4,
                title: 'Micutzu - Stand-up Comedy',
                slug: 'micutzu-stand-up',
                date: '2025-02-20',
                time: '19:00',
                venue: 'Sala Palatului',
                city: 'Bucuresti',
                category: 'Stand-up',
                price_from: 89,
                image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=800&h=500&fit=crop'
            }
        ];

        const resultsEl = document.getElementById(this.elements.resultsCount);
        if (resultsEl) resultsEl.textContent = this.events.length;

        this.renderEvents();
    },

    /**
     * Render events to grid
     */
    renderEvents() {
        const grid = document.getElementById(this.elements.eventsGrid);
        const loadingEl = document.getElementById(this.elements.loadingState);

        if (loadingEl) loadingEl.classList.add('hidden');

        if (!grid || this.events.length === 0) {
            this.showEmpty();
            return;
        }

        // Normalize and render events
        const html = this.events.map(event => this.renderEventCard(event)).join('');
        grid.innerHTML = html;
        grid.classList.remove('hidden');
        grid.classList.add('grid');
    },

    /**
     * Custom event card for events page
     */
    renderEventCard(event) {
        // Normalize event data
        const normalized = AmbiletDataTransformer.normalizeEvent(event);
        if (!normalized) return '';

        const venueCity = normalized.city ? normalized.venue + ', ' + normalized.city : normalized.venue;

        return '<a href="/bilete/' + normalized.slug + '" class="overflow-hidden transition-all bg-white border border-gray-200 group rounded-2xl hover:shadow-xl hover:-translate-y-1">' +
            '<div class="relative aspect-[16/10] overflow-hidden">' +
                '<img src="' + (normalized.image || AmbiletEventCard.PLACEHOLDER) + '" alt="' + AmbiletEventCard.escapeHtml(normalized.title) + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" loading="lazy" onerror="this.src=\'' + AmbiletEventCard.PLACEHOLDER + '\'">' +
                '<div class="absolute top-3 left-3">' +
                    '<span class="px-3 py-1 text-xs font-bold text-white rounded-full bg-primary">' + AmbiletEventCard.escapeHtml(normalized.category || 'Evenimente') + '</span>' +
                '</div>' +
                '<div class="absolute flex flex-col items-center justify-center text-center bg-white shadow-lg w-14 h-14 rounded-xl top-3 right-3">' +
                    '<span class="text-xs font-medium text-gray-500 uppercase">' + normalized.weekday + '</span>' +
                    '<span class="text-lg font-bold text-gray-900">' + normalized.day + '</span>' +
                    '<span class="text-[10px] font-medium text-gray-500 uppercase">' + normalized.month + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="mb-2 text-lg font-bold text-gray-900 transition-colors line-clamp-2 group-hover:text-primary">' + AmbiletEventCard.escapeHtml(normalized.title) + '</h3>' +
                '<div class="flex items-center gap-2 mb-3 text-sm text-gray-500">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>' +
                    '</svg>' +
                    AmbiletEventCard.escapeHtml(venueCity) +
                '</div>' +
                '<div class="flex items-center justify-between">' +
                    '<div class="flex items-center gap-1 text-sm text-gray-500">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
                        '</svg>' +
                        (normalized.time || '20:00') +
                    '</div>' +
                    '<div class="text-right">' +
                        '<span class="text-xs text-gray-500">de la</span>' +
                        '<span class="ml-1 text-lg font-bold text-primary">' + normalized.priceFormatted + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    /**
     * Show empty state
     */
    showEmpty() {
        const loadingEl = document.getElementById(this.elements.loadingState);
        const gridEl = document.getElementById(this.elements.eventsGrid);
        const emptyEl = document.getElementById(this.elements.emptyState);

        if (loadingEl) loadingEl.classList.add('hidden');
        if (gridEl) gridEl.classList.add('hidden');
        if (emptyEl) emptyEl.classList.remove('hidden');
    },

    /**
     * Render pagination
     */
    renderPagination() {
        AmbiletPagination.render({
            containerId: this.elements.pagination,
            currentPage: this.page,
            totalPages: this.totalPages,
            mode: 'smart',
            onPageChange: (page) => this.goToPage(page)
        });
    },

    /**
     * Navigate to page
     */
    goToPage(page) {
        this.page = page;
        this.loadEvents();
        AmbiletPagination.scrollTo(400);
    },

    /**
     * Apply filters and reload
     */
    applyFilters() {
        const cityEl = document.getElementById(this.elements.cityFilter);
        const genreEl = document.getElementById(this.elements.genreFilter);
        const dateEl = document.getElementById(this.elements.dateFilter);
        const priceEl = document.getElementById(this.elements.priceFilter);
        const sortEl = document.getElementById(this.elements.sortFilter);

        if (cityEl) this.filters.city = cityEl.value;
        if (genreEl) this.filters.genre = genreEl.value;
        if (dateEl) this.filters.date = dateEl.value;
        if (priceEl) this.filters.price = priceEl.value;
        if (sortEl) this.filters.sort = sortEl.value;

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    /**
     * Set category filter
     */
    setCategory(categorySlug) {
        this.filters.category = categorySlug;

        // Update visual state of category buttons
        const buttons = document.querySelectorAll('#' + this.elements.categoryFilters + ' .category-btn');
        buttons.forEach(btn => {
            const btnCategory = btn.getAttribute('data-category') || '';
            if (btnCategory === categorySlug) {
                btn.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                btn.classList.add('bg-primary', 'text-white');
            } else {
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
            }
        });

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    /**
     * Search events
     */
    search() {
        const searchEl = document.getElementById(this.elements.searchInput);
        if (searchEl) this.filters.search = searchEl.value;
        this.page = 1;
        this.updateURL();
        this.loadEvents();
    },

    /**
     * Clear all filters
     */
    clearFilters() {
        this.filters = {
            category: '',
            city: '',
            region: '',
            genre: '',
            artist: '',
            price: '',
            date: '',
            sort: 'date',
            search: ''
        };

        // Reset category buttons visual state
        const buttons = document.querySelectorAll('#' + this.elements.categoryFilters + ' .category-btn');
        buttons.forEach(btn => {
            const btnCategory = btn.getAttribute('data-category') || '';
            if (btnCategory === '') {
                btn.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                btn.classList.add('bg-primary', 'text-white');
            } else {
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
            }
        });

        // Reset dropdown filters
        const cityEl = document.getElementById(this.elements.cityFilter);
        const genreEl = document.getElementById(this.elements.genreFilter);
        const dateEl = document.getElementById(this.elements.dateFilter);
        const priceEl = document.getElementById(this.elements.priceFilter);
        const sortEl = document.getElementById(this.elements.sortFilter);
        const searchEl = document.getElementById(this.elements.searchInput);

        if (cityEl) cityEl.value = '';
        if (genreEl) genreEl.value = '';
        if (dateEl) dateEl.value = '';
        if (priceEl) priceEl.value = '';
        if (sortEl) sortEl.value = 'date';
        if (searchEl) searchEl.value = '';

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    /**
     * Update browser URL with filters
     */
    updateURL() {
        const params = new URLSearchParams();
        if (this.filters.category) params.set('categorie', this.filters.category);
        if (this.filters.city) params.set('oras', this.filters.city);
        if (this.filters.genre) params.set('gen', this.filters.genre);
        if (this.filters.date) params.set('data', this.filters.date);
        if (this.filters.price) params.set('pret', this.filters.price);
        if (this.filters.sort && this.filters.sort !== 'date') params.set('sortare', this.filters.sort);
        if (this.filters.search) params.set('q', this.filters.search);

        const newURL = params.toString() ? '/evenimente?' + params.toString() : '/evenimente';
        history.pushState({}, '', newURL);
    },

    /**
     * Update active filters display
     */
    updateActiveFilters() {
        const container = document.getElementById(this.elements.activeFilters);
        const tagsContainer = document.getElementById(this.elements.activeFilterTags);
        if (!container || !tagsContainer) return;

        const activeFilters = [];

        if (this.filters.category) {
            const categoryBtn = document.querySelector('#' + this.elements.categoryFilters + ' .category-btn[data-category="' + this.filters.category + '"]');
            const categoryName = categoryBtn ? categoryBtn.textContent.trim() : this.filters.category;
            activeFilters.push({ key: 'category', label: 'Categorie: ' + categoryName });
        }
        if (this.filters.city) activeFilters.push({ key: 'city', label: 'Oras: ' + this.filters.city });
        if (this.filters.genre) activeFilters.push({ key: 'genre', label: 'Gen: ' + this.filters.genre });
        if (this.filters.date) activeFilters.push({ key: 'date', label: 'Data: ' + this.filters.date });
        if (this.filters.price) activeFilters.push({ key: 'price', label: 'Pret: ' + this.filters.price });
        if (this.filters.search) activeFilters.push({ key: 'search', label: 'Cautare: ' + this.filters.search });

        if (activeFilters.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';
        tagsContainer.innerHTML = activeFilters.map(f =>
            '<span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-full">' +
                AmbiletEventCard.escapeHtml(f.label) +
                '<button onclick="EventsPage.removeFilter(\'' + f.key + '\')" class="ml-1 text-gray-400 hover:text-gray-600">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                    '</svg>' +
                '</button>' +
            '</span>'
        ).join('');
    },

    /**
     * Remove a specific filter
     */
    removeFilter(key) {
        this.filters[key] = '';

        if (key === 'category') {
            this.setCategory('');
            return;
        }

        const elementMap = {
            city: this.elements.cityFilter,
            genre: this.elements.genreFilter,
            date: this.elements.dateFilter,
            price: this.elements.priceFilter,
            search: this.elements.searchInput
        };

        const elementId = elementMap[key];
        if (elementId) {
            const el = document.getElementById(elementId);
            if (el) el.value = '';
        }

        this.applyFilters();
    },

    /**
     * Set grid/list view
     */
    setView(view) {
        this.view = view;
        const grid = document.getElementById(this.elements.eventsGrid);
        const gridBtn = document.getElementById(this.elements.viewGrid);
        const listBtn = document.getElementById(this.elements.viewList);

        if (!grid) return;

        if (view === 'grid') {
            grid.classList.remove('grid-cols-1');
            grid.classList.add('sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4');
            if (gridBtn) {
                gridBtn.classList.add('bg-primary', 'text-white');
                gridBtn.classList.remove('bg-white');
            }
            if (listBtn) {
                listBtn.classList.remove('bg-primary', 'text-white');
                listBtn.classList.add('bg-white');
            }
        } else {
            grid.classList.add('grid-cols-1');
            grid.classList.remove('sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4');
            if (listBtn) {
                listBtn.classList.add('bg-primary', 'text-white');
                listBtn.classList.remove('bg-white');
            }
            if (gridBtn) {
                gridBtn.classList.remove('bg-primary', 'text-white');
                gridBtn.classList.add('bg-white');
            }
        }
    }
};

// Make available globally
window.EventsPage = EventsPage;
