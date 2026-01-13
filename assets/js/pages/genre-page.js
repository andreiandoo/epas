/**
 * Ambilet.ro - Genre Page Controller
 * Handles genre listing page with subgenre filtering, featured artists, and events grouped by month
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard, AmbiletPagination, AmbiletEmptyState, AmbiletDataTransformer
 */

const GenrePage = {
    // Configuration
    genre: '',
    currentPage: 1,
    perPage: 50, // Load more events at once for monthly grouping
    totalEvents: 0,
    hasMore: false,
    filters: {},

    // Month names in Romanian
    monthNames: [
        'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
        'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
    ],

    // DOM element IDs
    elements: {
        grid: 'eventsGrid',
        loadMoreSection: 'loadMoreSection',
        loadMoreBtn: 'loadMoreBtn',
        eventsCount: 'eventsCount',
        artistsCount: 'artistsCount',
        citiesCount: 'citiesCount',
        resultsCount: 'resultsCount',
        pageTitle: 'pageTitle',
        pageDescription: 'pageDescription',
        genreBreadcrumb: 'genreBreadcrumb',
        genreBanner: 'genreBanner',
        genreIcon: 'genreIcon',
        parentCategoryLink: 'parentCategoryLink',
        artistsSection: 'artistsSection',
        artistsScroll: 'artistsScroll',
        subgenresSection: 'subgenresSection',
        subgenresPills: 'subgenresPills',
        filterCity: 'filterCity',
        filterDate: 'filterDate',
        sortEvents: 'sortEvents'
    },

    /**
     * Initialize the page
     * @param {string} genreSlug - Genre slug from URL
     */
    async init(genreSlug) {
        this.genre = genreSlug || '';

        if (!this.genre) {
            window.location.href = '/categorie/concerte';
            return;
        }

        this.filters.genre = this.genre;

        // Load data in parallel
        await Promise.all([
            this.loadGenreInfo(),
            this.loadArtists(),
            this.loadSubgenres(),
            this.loadCities(),
            this.loadEvents()
        ]);

        this.bindEvents();
    },

    /**
     * Load genre info from API
     */
    async loadGenreInfo() {
        try {
            const response = await AmbiletAPI.get('/genres/' + this.genre);
            if (response.data) {
                const genre = response.data;
                const titleEl = document.getElementById(this.elements.pageTitle);
                const breadcrumbEl = document.getElementById(this.elements.genreBreadcrumb);
                const descEl = document.getElementById(this.elements.pageDescription);
                const iconEl = document.getElementById(this.elements.genreIcon);
                const bannerEl = document.getElementById(this.elements.genreBanner);
                const categoryLink = document.getElementById(this.elements.parentCategoryLink);

                if (titleEl) titleEl.textContent = genre.name;
                if (breadcrumbEl) breadcrumbEl.textContent = genre.name;
                if (descEl && genre.description) descEl.textContent = genre.description;
                if (iconEl && genre.icon) iconEl.textContent = genre.icon;
                if (bannerEl && genre.image) bannerEl.src = genre.image;
                if (categoryLink && genre.category) {
                    categoryLink.textContent = genre.category.name;
                    categoryLink.href = '/categorie/' + genre.category.slug;
                }
            }
        } catch (e) {
            console.warn('Failed to load genre info:', e);
        }
    },

    /**
     * Load featured artists for this genre
     */
    async loadArtists() {
        const section = document.getElementById(this.elements.artistsSection);
        if (!section) return;

        try {
            const response = await AmbiletAPI.get('/artists?genre=' + this.genre + '&limit=6');
            if (response.data && response.data.length > 0) {
                const container = document.getElementById(this.elements.artistsScroll);
                if (container) {
                    container.innerHTML = response.data.map(artist => this.renderArtistCard(artist)).join('');
                }

                const countEl = document.getElementById(this.elements.artistsCount);
                if (countEl) countEl.textContent = response.data.length + ' artisti';
            } else {
                section.style.display = 'none';
            }
        } catch (e) {
            section.style.display = 'none';
        }
    },

    /**
     * Render artist card for horizontal scroll
     */
    renderArtistCard(artist) {
        const name = AmbiletEventCard.escapeHtml(artist.name || 'Artist');
        const image = artist.image || artist.portrait || '/assets/images/default-artist.png';
        const eventsCount = artist.events_count || 0;

        return '<a href="/artist/' + (artist.slug || '') + '" class="flex flex-col items-center flex-shrink-0 gap-3 p-4 artist-card bg-surface rounded-2xl hover:bg-primary/5">' +
            '<img src="' + image + '" alt="' + name + '" class="object-cover w-20 h-20 rounded-full ring-4 ring-primary/20" loading="lazy" onerror="this.src=\'/assets/images/default-artist.png\'">' +
            '<span class="text-sm font-semibold text-secondary">' + name + '</span>' +
            '<span class="text-xs text-muted">' + eventsCount + ' evenimente</span>' +
        '</a>';
    },

    /**
     * Load subgenres for filtering
     */
    async loadSubgenres() {
        const section = document.getElementById(this.elements.subgenresSection);
        if (!section) return;

        try {
            const response = await AmbiletAPI.get('/subgenres?genre=' + this.genre);
            if (response.data && response.data.length > 0) {
                const container = document.getElementById(this.elements.subgenresPills);
                if (container) {
                    container.innerHTML = '<button class="px-4 py-2 text-sm font-medium text-white rounded-full bg-primary" data-subgenre="">Toate</button>';

                    response.data.forEach(sub => {
                        container.innerHTML += '<button class="px-4 py-2 text-sm font-medium transition-all border rounded-full bg-surface border-border hover:bg-primary hover:text-white hover:border-primary" data-subgenre="' + sub.slug + '">' + AmbiletEventCard.escapeHtml(sub.name) + '</button>';
                    });
                }
            } else {
                section.style.display = 'none';
            }
        } catch (e) {
            section.style.display = 'none';
        }
    },

    /**
     * Load cities for filter dropdown
     */
    async loadCities() {
        try {
            const response = await AmbiletAPI.get('/cities?genre=' + this.genre);
            if (response.data) {
                const select = document.getElementById(this.elements.filterCity);
                if (select) {
                    select.innerHTML = '<option value="">Toate orasele</option>';
                    response.data.forEach(city => {
                        select.innerHTML += '<option value="' + city.slug + '">' + AmbiletEventCard.escapeHtml(city.name) + '</option>';
                    });
                }

                const countEl = document.getElementById(this.elements.citiesCount);
                if (countEl) countEl.textContent = response.data.length + ' orase';
            }
        } catch (e) {
            console.warn('Failed to load cities:', e);
        }
    },

    /**
     * Load events from API
     * @param {boolean} append - Whether to append to existing grid
     */
    async loadEvents(append = false) {
        const container = document.getElementById(this.elements.grid);
        if (!container) return;

        if (!append) {
            this.currentPage = 1;
            // Show loading skeletons
            container.innerHTML = AmbiletEventCard.renderSkeletons(8);
        }

        try {
            const sortEl = document.getElementById(this.elements.sortEvents);
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                sort: sortEl?.value || 'date_asc'
            });

            // Add filters
            Object.keys(this.filters).forEach(key => {
                if (this.filters[key]) params.append(key, this.filters[key]);
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());
            if (response.data) {
                const events = response.data;
                const meta = response.meta || {};
                this.totalEvents = meta.total || events.length;
                this.hasMore = meta.current_page < meta.last_page;

                // Update counts
                const eventsCountEl = document.getElementById(this.elements.eventsCount);
                const resultsCountEl = document.getElementById(this.elements.resultsCount);
                if (eventsCountEl) eventsCountEl.textContent = this.totalEvents + ' evenimente';
                if (resultsCountEl) resultsCountEl.textContent = this.totalEvents + ' rezultate';

                if (events.length > 0) {
                    // Group events by month
                    const monthGroups = this.groupEventsByMonth(events);

                    // Render grouped events
                    let html = '';
                    monthGroups.forEach(group => {
                        html += this.renderMonthGroup(group);
                    });

                    if (append) {
                        container.innerHTML += html;
                    } else {
                        container.innerHTML = html;
                        // Update container classes for flex layout
                        container.classList.remove('grid', 'sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4', 'gap-5');
                        container.classList.add('flex', 'flex-col', 'gap-10');
                    }
                } else {
                    container.innerHTML = AmbiletEmptyState.noEvents({
                        onButtonClick: () => this.clearFilters()
                    });
                }

                // Show/hide load more button
                const loadMoreSection = document.getElementById(this.elements.loadMoreSection);
                if (loadMoreSection) {
                    loadMoreSection.style.display = this.hasMore ? 'block' : 'none';
                }
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            container.innerHTML = AmbiletEmptyState.error({
                onButtonClick: () => this.loadEvents()
            });
        }
    },

    /**
     * Group events by month
     */
    groupEventsByMonth(events) {
        const groups = {};

        events.forEach(event => {
            const dateStr = event.date || event.starts_at || event.event_date;
            if (!dateStr) return;

            const date = new Date(dateStr);
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
     * Load more events
     */
    loadMore() {
        this.currentPage++;
        AmbiletPagination.setLoadingState(true);
        this.loadEvents(true).finally(() => {
            AmbiletPagination.setLoadingState(false);
        });
    },

    /**
     * Clear all filters
     */
    clearFilters() {
        const cityEl = document.getElementById(this.elements.filterCity);
        const dateEl = document.getElementById(this.elements.filterDate);
        const sortEl = document.getElementById(this.elements.sortEvents);

        if (cityEl) cityEl.value = '';
        if (dateEl) dateEl.value = '';
        if (sortEl) sortEl.value = 'date_asc';

        // Reset subgenre filter
        document.querySelectorAll('#' + this.elements.subgenresPills + ' button').forEach(b => {
            b.classList.remove('bg-primary', 'text-white');
            b.classList.add('bg-surface', 'border', 'border-border');
        });
        const allBtn = document.querySelector('#' + this.elements.subgenresPills + ' button[data-subgenre=""]');
        if (allBtn) {
            allBtn.classList.remove('bg-surface', 'border', 'border-border');
            allBtn.classList.add('bg-primary', 'text-white');
        }

        // Keep genre filter
        this.filters = { genre: this.genre };
        this.loadEvents();
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Subgenre pills
        document.querySelectorAll('#' + this.elements.subgenresPills + ' button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('#' + this.elements.subgenresPills + ' button').forEach(b => {
                    b.classList.remove('bg-primary', 'text-white');
                    b.classList.add('bg-surface', 'border', 'border-border');
                });
                e.target.classList.remove('bg-surface', 'border', 'border-border');
                e.target.classList.add('bg-primary', 'text-white');

                const subgenre = e.target.dataset.subgenre;
                if (subgenre) {
                    this.filters.subgenre = subgenre;
                } else {
                    delete this.filters.subgenre;
                }
                this.loadEvents();
            });
        });

        // Filter changes
        const cityEl = document.getElementById(this.elements.filterCity);
        const dateEl = document.getElementById(this.elements.filterDate);
        const sortEl = document.getElementById(this.elements.sortEvents);

        if (cityEl) {
            cityEl.addEventListener('change', (e) => {
                if (e.target.value) {
                    this.filters.city = e.target.value;
                } else {
                    delete this.filters.city;
                }
                this.loadEvents();
            });
        }

        if (dateEl) {
            dateEl.addEventListener('change', (e) => {
                if (e.target.value) {
                    this.filters.date_filter = e.target.value;
                } else {
                    delete this.filters.date_filter;
                }
                this.loadEvents();
            });
        }

        if (sortEl) {
            sortEl.addEventListener('change', () => this.loadEvents());
        }
    }
};

// Make available globally
window.GenrePage = GenrePage;
