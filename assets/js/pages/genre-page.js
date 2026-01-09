/**
 * Ambilet.ro - Genre Page Controller
 * Handles genre listing page with subgenre filtering, featured artists, and events
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard, AmbiletPagination, AmbiletEmptyState, AmbiletDataTransformer
 */

const GenrePage = {
    // Configuration
    genre: '',
    currentPage: 1,
    perPage: 12,
    totalEvents: 0,
    hasMore: false,
    filters: {},

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
        featuredEventSection: 'featuredEventSection',
        featuredEvent: 'featuredEvent',
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
        const image = artist.image || artist.portrait || '/assets/images/placeholder-artist.jpg';
        const eventsCount = artist.events_count || 0;

        return '<a href="/artist/' + (artist.slug || '') + '" class="flex flex-col items-center flex-shrink-0 gap-3 p-4 artist-card bg-surface rounded-2xl hover:bg-primary/5">' +
            '<img src="' + image + '" alt="' + name + '" class="object-cover w-20 h-20 rounded-full ring-4 ring-primary/20" loading="lazy" onerror="this.src=\'/assets/images/placeholder-artist.jpg\'">' +
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

                // Render featured event (first one) if first page
                if (!append && events.length > 0) {
                    this.renderFeaturedEvent(events[0]);
                    if (events.length > 1) {
                        container.innerHTML = AmbiletEventCard.renderMany(events.slice(1));
                    } else {
                        container.innerHTML = '';
                    }
                } else if (append) {
                    container.innerHTML += AmbiletEventCard.renderMany(events);
                } else if (events.length === 0) {
                    document.getElementById(this.elements.featuredEventSection).style.display = 'none';
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
     * Render featured event (large card)
     */
    renderFeaturedEvent(event) {
        const section = document.getElementById(this.elements.featuredEventSection);
        const container = document.getElementById(this.elements.featuredEvent);
        if (!section || !container) return;

        const normalized = AmbiletDataTransformer.normalizeEvent(event);
        if (!normalized) return;

        const dateObj = new Date(normalized.dateRaw);
        const day = dateObj.getDate();
        const month = dateObj.toLocaleDateString('ro-RO', { month: 'long' });
        const dayName = dateObj.toLocaleDateString('ro-RO', { weekday: 'long' });

        container.href = '/bilete/' + normalized.slug;
        container.innerHTML =
            '<div class="flex flex-col lg:flex-row">' +
                '<div class="relative h-64 overflow-hidden lg:w-2/5 lg:h-auto">' +
                    '<img src="' + (normalized.image || AmbiletEventCard.PLACEHOLDER) + '" alt="' + AmbiletEventCard.escapeHtml(normalized.title) + '" class="object-cover w-full h-full">' +
                    '<div class="absolute top-4 left-4">' +
                        '<span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">Recomandat</span>' +
                    '</div>' +
                '</div>' +
                '<div class="flex flex-col justify-center p-6 lg:w-3/5 lg:p-8">' +
                    '<div class="flex items-center gap-3 mb-4">' +
                        '<div class="px-4 py-3 text-center text-white bg-primary rounded-xl">' +
                            '<span class="block text-2xl font-bold leading-none">' + day + '</span>' +
                            '<span class="block mt-1 text-xs tracking-wide uppercase">' + month + '</span>' +
                        '</div>' +
                        '<div>' +
                            '<span class="text-sm capitalize text-muted">' + dayName + '</span>' +
                            '<p class="text-sm text-muted">' + (normalized.time || '20:00') + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<h2 class="mb-3 text-2xl font-bold transition-colors lg:text-3xl text-secondary hover:text-primary">' + AmbiletEventCard.escapeHtml(normalized.title) + '</h2>' +
                    '<p class="mb-4 text-muted line-clamp-2">' + AmbiletEventCard.escapeHtml(event.description || '') + '</p>' +
                    '<div class="flex items-center gap-4 mb-6">' +
                        '<span class="flex items-center gap-1.5 text-sm text-muted">' +
                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                            AmbiletEventCard.escapeHtml(normalized.location || 'Romania') +
                        '</span>' +
                    '</div>' +
                    '<div class="flex items-center justify-between">' +
                        '<div>' +
                            '<span class="text-sm text-muted">de la</span>' +
                            '<span class="ml-1 text-2xl font-bold text-primary">' + normalized.priceFormatted + '</span>' +
                        '</div>' +
                        '<span class="px-6 py-3 font-bold text-white transition-all bg-primary rounded-xl hover:bg-primary-dark">' +
                            'Cumpara bilete &rarr;' +
                        '</span>' +
                    '</div>' +
                '</div>' +
            '</div>';

        section.style.display = 'block';
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
