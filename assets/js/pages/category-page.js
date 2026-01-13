/**
 * Ambilet.ro - Category Page Controller
 * Handles category/event type listing page with genre filtering
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard, AmbiletPagination, AmbiletEmptyState, AmbiletDataTransformer
 */

const CategoryPage = {
    // Configuration
    category: '',
    currentPage: 1,
    perPage: 24,
    totalEvents: 0,
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
        citiesCount: 'citiesCount',
        pageTitle: 'pageTitle',
        pageDescription: 'pageDescription',
        breadcrumbTitle: 'breadcrumbTitle',
        categoryBanner: 'categoryBanner',
        genresSection: 'genresSection',
        genresPills: 'genresPills',
        filterCity: 'filterCity',
        filterDate: 'filterDate',
        filterPrice: 'filterPrice',
        sortEvents: 'sortEvents',
        featuredSection: 'featuredSection',
        featuredEvents: 'featuredEvents',
        categoryFeaturedSection: 'categoryFeaturedSection',
        categoryFeaturedEvents: 'categoryFeaturedEvents'
    },

    /**
     * Initialize the page
     * @param {string} categorySlug - Category slug from URL
     */
    async init(categorySlug) {
        this.category = categorySlug || '';

        // Set initial category filter
        if (this.category) {
            this.filters.category = this.category;
        }

        // Load data in parallel
        await Promise.all([
            this.loadCategoryInfo(),
            this.loadGenres(),
            this.loadCities(),
            this.loadCategoryFeaturedEvents(),
            this.loadFeaturedEvents(),
            this.loadEvents()
        ]);

        this.bindEvents();
    },

    /**
     * Load category info from API
     */
    async loadCategoryInfo() {
        if (!this.category) return;

        try {
            const response = await AmbiletAPI.get('event-categories');
            if (response.data?.categories) {
                const cat = response.data.categories.find(c => c.slug === this.category);
                if (cat) {
                    const titleEl = document.getElementById(this.elements.pageTitle);
                    const breadcrumbEl = document.getElementById(this.elements.breadcrumbTitle);
                    const descEl = document.getElementById(this.elements.pageDescription);
                    const bannerEl = document.getElementById(this.elements.categoryBanner);

                    if (titleEl) titleEl.innerHTML = (cat.icon_emoji || '🎫') + ' ' + cat.name;
                    if (breadcrumbEl) breadcrumbEl.textContent = cat.name;
                    if (descEl && cat.description) descEl.textContent = cat.description;
                    if (bannerEl && cat.image) bannerEl.src = cat.image;
                }
            }
        } catch (e) {
            console.warn('Failed to load category info:', e);
        }
    },

    /**
     * Load genres for this category
     */
    async loadGenres() {
        const section = document.getElementById(this.elements.genresSection);
        if (!this.category || !section) {
            if (section) section.style.display = 'none';
            return;
        }

        try {
            const response = await AmbiletAPI.get('/genres?category=' + this.category);
            if (response.data && response.data.length > 0) {
                const container = document.getElementById(this.elements.genresPills);
                if (container) {
                    container.innerHTML = '<button class="genre-pill active px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all" data-genre="">Toate</button>';

                    response.data.forEach(genre => {
                        container.innerHTML += '<a href="/gen/' + genre.slug + '" class="genre-pill px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all hover:border-primary">' + genre.name + '</a>';
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
            const params = this.category ? '?category=' + this.category : '';
            const response = await AmbiletAPI.get('/cities' + params);
            if (response.data) {
                const select = document.getElementById(this.elements.filterCity);
                if (select) {
                    select.innerHTML = '<option value="">Toate orasele</option>';
                    response.data.forEach(city => {
                        select.innerHTML += '<option value="' + city.slug + '">' + city.name + ' (' + (city.events_count || 0) + ')</option>';
                    });
                }

                // Update cities count
                const citiesCountEl = document.getElementById(this.elements.citiesCount);
                if (citiesCountEl) {
                    citiesCountEl.textContent = response.data.length + ' orase';
                }
            }
        } catch (e) {
            console.warn('Failed to load cities:', e);
        }
    },

    /**
     * Load category featured events (is_category_featured checkbox)
     */
    async loadCategoryFeaturedEvents() {
        const section = document.getElementById(this.elements.categoryFeaturedSection);
        const container = document.getElementById(this.elements.categoryFeaturedEvents);
        if (!section || !container || !this.category) return;

        try {
            const params = new URLSearchParams({
                type: 'category',
                require_image: 'true',
                category: this.category,
                limit: 6
            });

            const response = await AmbiletAPI.get('/featured?' + params.toString());
            if (response.data?.events && response.data.events.length > 0) {
                section.classList.remove('hidden');
                container.innerHTML = response.data.events.map(event => this.renderCategoryFeaturedCard(event)).join('');
            } else {
                section.classList.add('hidden');
            }
        } catch (e) {
            console.warn('Failed to load category featured events:', e);
            section.classList.add('hidden');
        }
    },

    /**
     * Render premium card for category featured events
     */
    renderCategoryFeaturedCard(event) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const days = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];
        const dateStr = event.starts_at || event.date;
        const date = dateStr ? new Date(dateStr) : new Date();
        const day = date.getDate();
        const month = months[date.getMonth()];
        const weekday = days[date.getDay()];

        // Use featured_image if available, otherwise fall back to regular image
        const image = event.featured_image || event.image || '/assets/images/default-event.png';
        const title = event.name || event.title || 'Eveniment';
        const venue = event.venue_name || (event.venue ? event.venue.name : '');
        const city = event.venue_city || (event.venue ? event.venue.city : '');
        const location = city ? (venue ? venue + ', ' + city : city) : venue;
        const priceFrom = event.price_from ? event.price_from + ' lei' : '';

        return '<a href="/bilete/' + (event.slug || '') + '" class="group relative block overflow-hidden transition-all duration-500 bg-white rounded-2xl hover:shadow-2xl hover:shadow-primary/20 hover:-translate-y-1">' +
            // Image container with premium badge
            '<div class="relative overflow-hidden aspect-[16/10]">' +
                '<img src="' + image + '" alt="' + this.escapeHtml(title) + '" class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110" loading="lazy">' +
                '<div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>' +
                // Premium badge
                '<div class="absolute top-3 left-3">' +
                    '<span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-white uppercase rounded-full bg-gradient-to-r from-primary to-accent shadow-lg">' +
                        '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                        'Premium' +
                    '</span>' +
                '</div>' +
                // Date badge
                '<div class="absolute bottom-3 right-3 bg-white/95 backdrop-blur-sm rounded-xl px-3 py-2 text-center shadow-lg transform transition-transform duration-300 group-hover:scale-105">' +
                    '<div class="text-xs font-medium text-primary uppercase">' + weekday + '</div>' +
                    '<div class="text-xl font-extrabold text-secondary leading-none">' + day + '</div>' +
                    '<div class="text-xs font-semibold text-muted uppercase">' + month + '</div>' +
                '</div>' +
            '</div>' +
            // Content
            '<div class="p-5">' +
                '<h3 class="mb-2 text-lg font-bold text-secondary line-clamp-2 group-hover:text-primary transition-colors">' + this.escapeHtml(title) + '</h3>' +
                (location ? '<p class="flex items-center gap-1.5 mb-3 text-sm text-muted"><svg class="w-4 h-4 text-primary/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(location) + '</p>' : '') +
                '<div class="flex items-center justify-between pt-3 border-t border-border">' +
                    (priceFrom ? '<div class="text-sm text-muted">de la <span class="text-lg font-bold text-primary">' + priceFrom + '</span></div>' : '<div></div>') +
                    '<span class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold text-white transition-all rounded-full bg-secondary group-hover:bg-primary group-hover:shadow-lg">' +
                        'Cumpără' +
                        '<svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>' +
                    '</span>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    /**
     * Load featured events for category (is_general_featured)
     */
    async loadFeaturedEvents() {
        const section = document.getElementById(this.elements.featuredSection);
        const container = document.getElementById(this.elements.featuredEvents);
        if (!section || !container) return;

        try {
            const params = new URLSearchParams({
                type: 'general',
                require_image: 'true',
                limit: 6
            });

            // Add category filter if on category page
            if (this.category) {
                params.append('category', this.category);
            }

            const response = await AmbiletAPI.get('/featured?' + params.toString());
            if (response.data?.events && response.data.events.length > 0) {
                section.classList.remove('hidden');
                container.innerHTML = response.data.events.map(event => this.renderFeaturedCard(event)).join('');
            } else {
                section.classList.add('hidden');
            }
        } catch (e) {
            console.warn('Failed to load featured events:', e);
            section.classList.add('hidden');
        }
    },

    /**
     * Render featured event card with large banner image
     */
    renderFeaturedCard(event) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateStr = event.starts_at || event.date;
        const date = dateStr ? new Date(dateStr) : new Date();
        const day = date.getDate();
        const month = months[date.getMonth()];

        // Use featured_image if available, otherwise fall back to regular image
        const image = event.featured_image || event.image || '/assets/images/default-event.png';
        const title = event.name || event.title || 'Eveniment';
        const venue = event.venue_name || (event.venue ? event.venue.name : '');
        const city = event.venue_city || (event.venue ? event.venue.city : '');
        const location = city ? (venue ? venue + ', ' + city : city) : venue;
        const priceFrom = event.price_from ? 'de la ' + event.price_from + ' lei' : '';
        const category = event.category?.name || event.category || '';

        return '<a href="/bilete/' + (event.slug || '') + '" class="group relative overflow-hidden rounded-2xl bg-secondary aspect-[16/9] md:aspect-[21/9]">' +
            '<img src="' + image + '" alt="' + this.escapeHtml(title) + '" class="absolute inset-0 object-cover w-full h-full transition-transform duration-500 group-hover:scale-105" loading="lazy">' +
            '<div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>' +
            '<div class="absolute bottom-0 left-0 right-0 p-5">' +
                '<div class="flex items-end justify-between">' +
                    '<div class="flex-1">' +
                        (category ? '<span class="inline-block px-3 py-1 mb-2 text-xs font-bold text-white uppercase rounded-full bg-primary">' + this.escapeHtml(category) + '</span>' : '') +
                        '<h3 class="mb-2 text-xl font-bold text-white md:text-2xl line-clamp-2">' + this.escapeHtml(title) + '</h3>' +
                        '<div class="flex flex-wrap items-center gap-3 text-sm text-white/80">' +
                            '<span class="flex items-center gap-1">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
                                day + ' ' + month +
                            '</span>' +
                            (location ? '<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(location) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    (priceFrom ? '<div class="flex-shrink-0 px-4 py-2 text-sm font-bold text-white rounded-xl bg-primary">' + priceFrom + '</div>' : '') +
                '</div>' +
            '</div>' +
            '<div class="absolute top-4 right-4">' +
                '<span class="flex items-center gap-1 px-3 py-1 text-xs font-bold text-white rounded-full bg-accent/90 backdrop-blur-sm">' +
                    '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                    'Recomandat' +
                '</span>' +
            '</div>' +
        '</a>';
    },

    /**
     * Escape HTML characters
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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

                // Update events count
                const eventsCountEl = document.getElementById(this.elements.eventsCount);
                if (eventsCountEl) {
                    eventsCountEl.textContent = this.totalEvents + ' evenimente';
                }

                if (events.length > 0) {
                    // Group events by month
                    const monthGroups = this.groupEventsByMonth(events);

                    // Render grouped events
                    let html = '';
                    monthGroups.forEach(group => {
                        html += this.renderMonthGroup(group);
                    });

                    container.innerHTML = html;
                    container.classList.remove('grid');
                    container.classList.add('flex', 'flex-col', 'gap-8');
                } else {
                    container.innerHTML = AmbiletEmptyState.noEvents({
                        onButtonClick: () => this.clearFilters()
                    });
                }

                // Render pagination
                const pagination = AmbiletPagination.normalize(meta);
                AmbiletPagination.render({
                    containerId: this.elements.pagination,
                    currentPage: pagination.currentPage,
                    totalPages: pagination.totalPages,
                    mode: 'smart',
                    onPageChange: (page) => this.goToPage(page)
                });
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            container.innerHTML = AmbiletEmptyState.error({
                onButtonClick: () => this.loadEvents()
            });
        }
    },

    /**
     * Custom event card rendering with category-specific badges
     * Uses base AmbiletEventCard but adds genre colors and stock status
     */
    renderEventCard(event) {
        // Normalize event data
        const normalized = AmbiletDataTransformer.normalizeEvent(event);
        if (!normalized) return '';

        // Genre color mapping
        const genreColors = {
            'rock': 'bg-accent',
            'pop': 'bg-blue-600',
            'jazz': 'bg-yellow-600',
            'electronic': 'bg-purple-600',
            'folk': 'bg-emerald-600',
            'metal': 'bg-red-800',
            'alternative': 'bg-purple-600'
        };

        // Build custom status badge
        let statusBadge = '';
        if (normalized.isSoldOut) {
            statusBadge = '<span class="bg-secondary text-white text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase animate-pulse">Sold Out</span>';
        } else if (event.genre?.name) {
            const genreBg = genreColors[event.genre?.slug] || 'bg-accent';
            statusBadge = '<span class="' + genreBg + ' text-white text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase">' + AmbiletEventCard.escapeHtml(event.genre.name) + '</span>';
        }

        // Build price display with stock status
        let priceDisplay = '';
        if (normalized.isSoldOut) {
            priceDisplay = '<span class="font-bold line-through text-muted">' + normalized.priceFormatted + '</span><span class="text-xs font-semibold text-primary">Epuizat</span>';
        } else if (event.is_low_stock) {
            priceDisplay = '<span class="font-bold text-primary">' + normalized.priceFormatted + '</span><span class="text-xs font-semibold text-accent">Ultimele locuri</span>';
        } else {
            priceDisplay = '<span class="font-bold text-primary">' + normalized.priceFormatted + '</span><span class="text-xs text-muted">Disponibil</span>';
        }

        // Date badge - show range for festivals, single date otherwise
        let dateBadgeHtml;
        if (normalized.isDateRange && normalized.dateRangeFormatted) {
            dateBadgeHtml = '<div class="px-3 py-2 text-center text-white shadow-lg bg-primary rounded-xl">' +
                '<span class="block text-xs font-semibold leading-tight">' + AmbiletEventCard.escapeHtml(normalized.dateRangeFormatted) + '</span>' +
            '</div>';
        } else {
            dateBadgeHtml = '<div class="px-3 py-2 text-center text-white shadow-lg bg-primary rounded-xl">' +
                '<span class="block text-xl font-bold leading-none">' + normalized.day + '</span>' +
                '<span class="block text-[10px] uppercase tracking-wide mt-0.5">' + normalized.month + '</span>' +
            '</div>';
        }

        return '<a href="/bilete/' + normalized.slug + '" class="overflow-hidden bg-white border event-card rounded-2xl border-border group hover:-translate-y-1 hover:shadow-xl hover:border-primary transition-all">' +
            '<div class="relative h-48 overflow-hidden">' +
                (normalized.isSoldOut ? '<div class="absolute inset-0 z-10 bg-black/30"></div>' : '') +
                '<img src="' + (normalized.image || AmbiletEventCard.PLACEHOLDER) + '" alt="' + AmbiletEventCard.escapeHtml(normalized.title) + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" loading="lazy" onerror="this.src=\'' + AmbiletEventCard.PLACEHOLDER + '\'">' +
                '<div class="absolute top-3 left-3">' + dateBadgeHtml + '</div>' +
                (statusBadge ? '<div class="absolute top-3 right-3 z-20">' + statusBadge + '</div>' : '') +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">' + AmbiletEventCard.escapeHtml(normalized.title) + '</h3>' +
                '<p class="text-sm text-muted mt-2 flex items-center gap-1.5">' +
                    '<svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                    AmbiletEventCard.escapeHtml(normalized.location || 'Romania') +
                '</p>' +
                '<div class="flex items-center justify-between pt-3 mt-3 border-t border-border">' + priceDisplay + '</div>' +
            '</div>' +
        '</a>';
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
        const eventsHtml = group.events.map(e => this.renderEventCard(e)).join('');

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
     * Navigate to specific page
     */
    goToPage(page) {
        this.currentPage = page;
        this.loadEvents();
        AmbiletPagination.scrollTo(300);
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Genre pills
        document.querySelectorAll('.genre-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                if (e.target.tagName === 'A') return; // Let links work normally
                e.preventDefault();
                document.querySelectorAll('.genre-pill').forEach(p => p.classList.remove('active'));
                e.target.classList.add('active');
                this.filters.genre = e.target.dataset.genre || '';
                this.currentPage = 1;
                this.loadEvents();
            });
        });

        // Filter changes
        const cityEl = document.getElementById(this.elements.filterCity);
        const dateEl = document.getElementById(this.elements.filterDate);
        const priceEl = document.getElementById(this.elements.filterPrice);
        const sortEl = document.getElementById(this.elements.sortEvents);

        if (cityEl) cityEl.addEventListener('change', () => this.applyFilters());
        if (dateEl) dateEl.addEventListener('change', () => this.applyFilters());
        if (priceEl) priceEl.addEventListener('change', () => this.applyFilters());
        if (sortEl) sortEl.addEventListener('change', () => this.loadEvents());
    },

    /**
     * Apply filters and reload
     */
    applyFilters() {
        const city = document.getElementById(this.elements.filterCity)?.value;
        const dateFilter = document.getElementById(this.elements.filterDate)?.value;
        const priceRange = document.getElementById(this.elements.filterPrice)?.value;

        if (city) this.filters.city = city;
        else delete this.filters.city;

        if (dateFilter) this.filters.date_filter = dateFilter;
        else delete this.filters.date_filter;

        if (priceRange) {
            const [min, max] = priceRange.split('-');
            if (min) this.filters.min_price = min;
            else delete this.filters.min_price;
            if (max) this.filters.max_price = max;
            else delete this.filters.max_price;
        } else {
            delete this.filters.min_price;
            delete this.filters.max_price;
        }

        this.currentPage = 1;
        this.loadEvents();
    },

    /**
     * Clear all filters
     */
    clearFilters() {
        const cityEl = document.getElementById(this.elements.filterCity);
        const dateEl = document.getElementById(this.elements.filterDate);
        const priceEl = document.getElementById(this.elements.filterPrice);
        const sortEl = document.getElementById(this.elements.sortEvents);

        if (cityEl) cityEl.value = '';
        if (dateEl) dateEl.value = '';
        if (priceEl) priceEl.value = '';
        if (sortEl) sortEl.value = 'date_asc';

        // Reset genre filter
        document.querySelectorAll('.genre-pill').forEach(p => p.classList.remove('active'));
        const allGenrePill = document.querySelector('.genre-pill[data-genre=""]');
        if (allGenrePill) allGenrePill.classList.add('active');

        // Keep category filter
        this.filters = this.category ? { category: this.category } : {};
        this.currentPage = 1;
        this.loadEvents();
    }
};

// Make available globally
window.CategoryPage = CategoryPage;
