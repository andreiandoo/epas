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
    perPage: 24,
    totalPages: 1,
    filters: {},

    // Month names in Romanian
    monthNames: [
        'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
        'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
    ],

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
                // Group events by month
                const monthGroups = this.groupEventsByMonth(response.data);

                // Render grouped events
                let html = '';
                monthGroups.forEach(group => {
                    html += this.renderMonthGroup(group);
                });

                container.innerHTML = html;
                container.classList.remove('grid');
                container.classList.add('flex', 'flex-col', 'gap-8');

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
            this.showEmptyState();
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
    },

    /**
     * Group events by month
     */
    groupEventsByMonth(events) {
        const groups = {};

        events.forEach(event => {
            const dateStr = event.date || event.starts_at || event.range_start_date || event.event_date;
            if (!dateStr) return;

            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return;
            const monthKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            const monthLabel = this.monthNames[date.getMonth()] + ' ' + date.getFullYear();

            if (!groups[monthKey]) {
                groups[monthKey] = {
                    key: monthKey,
                    label: monthLabel,
                    events: []
                };
            }

            groups[monthKey].events.push(event);
        });

        // Sort by month key and return as array
        return Object.values(groups).sort((a, b) => a.key.localeCompare(b.key));
    },

    /**
     * Render a month group with header and events grid
     */
    renderMonthGroup(group) {
        const eventsHtml = AmbiletEventCard.renderMany(group.events);

        return '<div class="month-group">' +
            '<div class="flex items-center gap-4 mb-6">' +
                '<h2 class="text-2xl font-bold text-gray-900">' + group.label + '</h2>' +
                '<span class="px-3 py-1 text-sm font-medium text-gray-600 bg-gray-100 rounded-full">' +
                    group.events.length + ' ' + (group.events.length === 1 ? 'eveniment' : 'evenimente') +
                '</span>' +
                '<div class="flex-1 h-px bg-gray-200"></div>' +
            '</div>' +
            '<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">' + eventsHtml + '</div>' +
        '</div>';
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
