/**
 * Ambilet.ro - City Page Controller
 * Handles city detail page with events listing
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard, AmbiletPagination, AmbiletEmptyState, AmbiletDataTransformer
 */

const CityPage = {
    // Configuration
    city: '',
    cityData: null,
    page: 1,
    perPage: 12,
    totalPages: 1,
    filters: {},

    // DOM element IDs
    elements: {
        grid: 'eventsGrid',
        pagination: 'pagination',
        eventsCount: 'eventsCount',
        categoryFilter: 'categoryFilter',
        dateFilter: 'dateFilter',
        priceFilter: 'priceFilter',
        sortSelect: 'sortSelect'
    },

    /**
     * Initialize the page
     * @param {string} citySlug - City slug from URL
     */
    async init(citySlug) {
        this.city = citySlug;

        if (!this.city) {
            window.location.href = '/orase';
            return;
        }

        // First, load city data to verify it exists
        const cityValid = await this.loadCityData();
        if (!cityValid) {
            window.location.href = '/orase';
            return;
        }

        await this.loadEvents();
    },

    /**
     * Load city data from API
     */
    async loadCityData() {
        try {
            const response = await AmbiletAPI.get('/locations/cities/' + encodeURIComponent(this.city));

            if (response.success && response.data && response.data.city) {
                const city = response.data.city;
                this.cityData = city;
                this.updatePageWithCityData(city);
                return true;
            }

            // City not found - allow page to render with fallback
            console.warn('City not found in API, using fallback');
            return true;

        } catch (e) {
            console.error('Failed to load city data:', e);
            return true; // Still allow page to work
        }
    },

    /**
     * Update page elements with city data
     */
    updatePageWithCityData(city) {
        // Update page title
        document.title = 'Evenimente în ' + city.name + ' - AmBilet.ro';

        // Update hero image
        const heroImage = city.cover_image || city.image;
        if (heroImage) {
            const heroImg = document.querySelector('section.relative img');
            if (heroImg) {
                heroImg.src = heroImage;
                heroImg.alt = city.name;
            }
        }

        // Update hero title
        const heroTitle = document.querySelector('h1');
        if (heroTitle) {
            heroTitle.textContent = city.name;
        }

        // Update description
        const descEl = document.querySelector('section.relative p.text-lg');
        if (descEl) {
            const regionName = city.region ? (typeof city.region === 'object' ? city.region.name : city.region) : null;
            if (regionName) {
                descEl.textContent = 'Descoperă cele mai bune evenimente din ' + city.name + ', ' + regionName + '.';
            } else if (city.description) {
                descEl.textContent = city.description;
            }
        }

        // Update events count
        if (city.events_count !== undefined) {
            const countEl = document.getElementById(this.elements.eventsCount);
            if (countEl) {
                countEl.textContent = city.events_count + ' evenimente disponibile';
            }
        }
    },

    /**
     * Load events from API
     */
    async loadEvents() {
        const container = document.getElementById(this.elements.grid);
        if (!container) return;

        // Show loading skeletons
        container.innerHTML = AmbiletEventCard.renderSkeletons(8);

        try {
            const params = new URLSearchParams({
                city: this.city,
                page: this.page,
                per_page: this.perPage,
                ...this.filters
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());

            if (response.data && response.data.length > 0) {
                // Render events using the component
                container.innerHTML = AmbiletEventCard.renderMany(response.data);

                // Update count and pagination
                if (response.meta) {
                    const countEl = document.getElementById(this.elements.eventsCount);
                    if (countEl) {
                        countEl.textContent = response.meta.total + ' evenimente disponibile';
                    }

                    const pagination = AmbiletPagination.normalize(response.meta);
                    this.totalPages = pagination.totalPages;

                    AmbiletPagination.render({
                        containerId: this.elements.pagination,
                        currentPage: pagination.currentPage,
                        totalPages: pagination.totalPages,
                        mode: 'numbered',
                        onPageChange: (page) => this.goToPage(page)
                    });
                }
            } else {
                this.showEmptyState();
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            // Show demo events in demo mode
            if (window.AMBILET_CONFIG?.DEMO_MODE || window.AMBILET?.demoMode) {
                this.loadDemoEvents();
            } else {
                this.showEmptyState();
            }
        }
    },

    /**
     * Load demo events for development
     */
    loadDemoEvents() {
        const demoEvents = [
            { slug: 'concert-demo-1', title: 'Concert Rock în Centrul Vechi', image_url: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600', event_date: '2025-02-15', venue: { name: 'Club Underground' }, min_price: 60, category: 'Concerte' },
            { slug: 'standup-demo', title: 'Stand-up Comedy Night', image_url: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=600', event_date: '2025-02-18', venue: { name: 'Comedy Club' }, min_price: 45, category: 'Stand-up' },
            { slug: 'teatru-demo', title: 'Hamlet - Premiera', image_url: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=600', event_date: '2025-02-20', venue: { name: 'Teatrul Național' }, min_price: 80, category: 'Teatru' },
            { slug: 'festival-demo', title: 'Electronic Music Festival', image_url: 'https://images.unsplash.com/photo-1571266028243-e4c3a0d64c10?w=600', event_date: '2025-03-01', venue: { name: 'Arena Events' }, min_price: 120, category: 'Festivaluri' },
        ];

        const container = document.getElementById(this.elements.grid);
        if (container) {
            container.innerHTML = AmbiletEventCard.renderMany(demoEvents);
        }
    },

    /**
     * Show empty state when no events found
     */
    showEmptyState() {
        const container = document.getElementById(this.elements.grid);
        if (container) {
            container.innerHTML = AmbiletEmptyState.noEvents({
                onButtonClick: () => this.clearFilters()
            });
        }

        // Clear pagination
        const paginationEl = document.getElementById(this.elements.pagination);
        if (paginationEl) {
            paginationEl.innerHTML = '';
        }
    },

    /**
     * Apply filters and reload events
     */
    filter() {
        const categoryEl = document.getElementById(this.elements.categoryFilter);
        const dateEl = document.getElementById(this.elements.dateFilter);
        const priceEl = document.getElementById(this.elements.priceFilter);
        const sortEl = document.getElementById(this.elements.sortSelect);

        this.filters = {};
        if (categoryEl && categoryEl.value) this.filters.category = categoryEl.value;
        if (dateEl && dateEl.value) this.filters.date = dateEl.value;
        if (priceEl && priceEl.value) this.filters.price = priceEl.value;
        if (sortEl && sortEl.value) this.filters.sort = sortEl.value;

        this.page = 1;
        this.loadEvents();
    },

    /**
     * Clear all filters
     */
    clearFilters() {
        const categoryEl = document.getElementById(this.elements.categoryFilter);
        const dateEl = document.getElementById(this.elements.dateFilter);
        const priceEl = document.getElementById(this.elements.priceFilter);
        const sortEl = document.getElementById(this.elements.sortSelect);

        if (categoryEl) categoryEl.value = '';
        if (dateEl) dateEl.value = '';
        if (priceEl) priceEl.value = '';
        if (sortEl) sortEl.value = 'date';

        this.filters = {};
        this.page = 1;
        this.loadEvents();
    },

    /**
     * Navigate to specific page
     */
    goToPage(page) {
        this.page = page;
        this.loadEvents();
        AmbiletPagination.scrollTo(400);
    }
};

// Make available globally
window.CityPage = CityPage;
