/**
 * TICS.ro - Events Page Controller
 * Handles main events listing page with filtering, sorting, AI suggestions
 * Events are grouped by month for better organization
 */

const TicsEventsPage = {
    // State
    events: [],
    page: 1,
    perPage: 24,
    totalPages: 1,
    totalCount: 0,
    view: 'grid',
    isLoggedIn: false, // User login state
    aiEnabled: true,   // AI suggestions toggle state
    filters: {
        category: '',
        city: '',
        genre: '',
        price: '',
        date: '',
        sort: 'recommended',
        search: '',
        aiMatch: 0,
        features: []
    },

    // Month names in Romanian
    monthNames: [
        'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
        'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
    ],

    // DOM element IDs
    elements: {
        loadingState: 'loadingState',
        eventsGrid: 'eventsGrid',
        emptyState: 'emptyState',
        pagination: 'pagination',
        resultsCount: 'resultsCount',
        resultsInfo: 'resultsInfo',
        activeFilters: 'activeFilters',
        activeFilterTags: 'activeFilterTags',
        categoryChips: '.category-chip',
        cityFilter: 'cityFilter',
        dateFilter: 'dateFilter',
        priceFilter: 'priceFilter',
        sortFilter: 'sortFilter',
        viewGrid: 'viewGrid',
        viewList: 'viewList',
        aiBanner: 'aiBanner',
        filterBtn: 'filterBtn',
        mobileFilterCount: 'mobileFilterCount'
    },

    /**
     * Initialize the page
     * @param {Object} initialFilters - Initial filter values from PHP
     */
    init(initialFilters = {}) {
        // Apply initial filters
        Object.assign(this.filters, initialFilters);

        // Check login state (from localStorage or cookie)
        this.isLoggedIn = this.checkLoginState();

        // Get AI toggle state
        const aiToggle = document.getElementById('aiToggle');
        this.aiEnabled = aiToggle ? aiToggle.checked : true;

        // Update UI based on login state
        this.updateUIForLoginState();

        // Load events
        this.loadEvents();

        // Update UI
        this.updateActiveFilters();
        this.setView(this.view);
        this.bindEvents();
    },

    /**
     * Check if user is logged in
     * @returns {boolean}
     */
    checkLoginState() {
        // Check for auth token in localStorage or cookie
        const token = localStorage.getItem('tics_auth_token') ||
                      document.cookie.split('; ').find(row => row.startsWith('tics_auth='));
        return !!token;
    },

    /**
     * Update UI elements based on login state
     */
    updateUIForLoginState() {
        const aiBanner = document.getElementById('aiBanner');
        const ctaBanner = document.getElementById('ctaBanner');

        if (this.isLoggedIn) {
            // Show AI banner when logged in and AI enabled
            if (aiBanner) aiBanner.classList.toggle('hidden', !this.aiEnabled);
            if (ctaBanner) ctaBanner.classList.add('hidden');
        } else {
            // Show CTA banner when not logged in, hide AI banner
            if (aiBanner) aiBanner.classList.add('hidden');
            if (ctaBanner) ctaBanner.classList.remove('hidden');
        }
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Category chips
        document.querySelectorAll(this.elements.categoryChips).forEach(chip => {
            chip.addEventListener('click', (e) => {
                e.preventDefault();
                const category = chip.dataset.category || '';
                this.setCategory(category);
            });
        });

        // Filter dropdowns
        ['cityFilter', 'dateFilter', 'priceFilter', 'sortFilter'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => this.applyFilters());
            }
        });

        // Mobile filters sync
        ['cityFilterMobile', 'dateFilterMobile', 'priceFilterMobile', 'sortFilterMobile'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => this.syncFilters(id));
            }
        });

        // View toggle buttons
        document.getElementById(this.elements.viewGrid)?.addEventListener('click', () => this.setView('grid'));
        document.getElementById(this.elements.viewList)?.addEventListener('click', () => this.setView('list'));

        // AI Toggle (both desktop and mobile)
        const aiToggle = document.getElementById('aiToggle');
        const aiToggleMobile = document.getElementById('aiToggleMobile');

        if (aiToggle) {
            aiToggle.addEventListener('change', () => this.toggleAI(aiToggle.checked));
        }
        if (aiToggleMobile) {
            aiToggleMobile.addEventListener('change', () => {
                this.toggleAI(aiToggleMobile.checked);
                // Sync with desktop
                if (aiToggle) aiToggle.checked = aiToggleMobile.checked;
            });
        }

        // AI Match buttons
        document.querySelectorAll('[data-ai-match]').forEach(btn => {
            btn.addEventListener('click', () => {
                const value = parseInt(btn.dataset.aiMatch) || 0;
                this.setAIMatch(value);
            });
        });

        // Feature checkboxes
        document.querySelectorAll('[data-feature]').forEach(cb => {
            cb.addEventListener('change', () => this.applyFilters());
        });
    },

    /**
     * Toggle AI suggestions on/off
     * @param {boolean} enabled
     */
    toggleAI(enabled) {
        this.aiEnabled = enabled;

        // Update UI
        const aiBanner = document.getElementById('aiBanner');
        if (aiBanner) {
            aiBanner.classList.toggle('hidden', !enabled || !this.isLoggedIn);
        }

        // Re-render events with new settings
        if (this.events.length > 0) {
            this.renderEvents();
        }
    },

    /**
     * Load events from API
     */
    async loadEvents() {
        const loadingEl = document.getElementById(this.elements.loadingState);
        const gridEl = document.getElementById(this.elements.eventsGrid);
        const emptyEl = document.getElementById(this.elements.emptyState);

        if (loadingEl) loadingEl.classList.remove('hidden');
        if (gridEl) {
            gridEl.classList.add('hidden');
            gridEl.innerHTML = TicsEventCard.renderSkeletons(8);
        }
        if (emptyEl) emptyEl.classList.add('hidden');

        try {
            const params = {
                page: this.page,
                per_page: this.perPage
            };

            // Add active filters
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value && value !== '' && (Array.isArray(value) ? value.length > 0 : true)) {
                    params[key] = Array.isArray(value) ? value.join(',') : value;
                }
            });

            const response = await TicsAPI.getEvents(params);

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
                this.totalCount = meta?.total || this.events.length;

                this.updateResultsCount();
                this.renderEvents();
                this.renderPagination();
            } else {
                this.totalCount = 0;
                this.updateResultsCount();
                this.showEmpty();
            }
        } catch (error) {
            console.error('Error loading events:', error);
            this.showEmpty();
        }
    },

    /**
     * Update results count display
     */
    updateResultsCount() {
        const countEl = document.getElementById(this.elements.resultsCount);
        const infoEl = document.getElementById(this.elements.resultsInfo);

        if (countEl) countEl.textContent = this.totalCount;

        if (infoEl) {
            const parts = [];
            if (this.filters.city) parts.push(`în ${this.filters.city}`);
            if (this.filters.date) parts.push(this.getDateLabel(this.filters.date));
            infoEl.textContent = parts.length > 0 ? parts.join(' • ') : '';
        }
    },

    /**
     * Get human-readable date filter label
     */
    getDateLabel(dateValue) {
        const labels = {
            'today': 'Astăzi',
            'tomorrow': 'Mâine',
            'weekend': 'Weekend',
            'week': 'Săptămâna aceasta',
            'month': 'Luna aceasta',
            'next-month': 'Luna viitoare'
        };
        return labels[dateValue] || dateValue;
    },

    /**
     * Group events by month
     */
    groupEventsByMonth(events) {
        const groups = {};

        events.forEach(event => {
            const dateStr = event.starts_at || event.event_date || event.start_date || event.date;
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

        // Sort by month key (ascending for upcoming events)
        return Object.values(groups).sort((a, b) => a.key.localeCompare(b.key));
    },

    /**
     * Render events to grid, grouped by month
     */
    renderEvents() {
        const grid = document.getElementById(this.elements.eventsGrid);
        const loadingEl = document.getElementById(this.elements.loadingState);

        if (loadingEl) loadingEl.classList.add('hidden');

        if (!grid || this.events.length === 0) {
            this.showEmpty();
            return;
        }

        // Determine if AI features should be shown
        const showAI = this.isLoggedIn && this.aiEnabled;

        // Separate featured events
        const generalFeatured = this.events.filter(e => e.is_featured || e.general_featured);
        const categoryFeatured = this.events.filter(e => e.category_featured && !e.is_featured && !e.general_featured);
        const regularEvents = this.events.filter(e => !e.is_featured && !e.general_featured && !e.category_featured);

        // Group regular events by month
        const monthGroups = this.groupEventsByMonth(regularEvents.length > 0 ? regularEvents : this.events);

        if (monthGroups.length === 0) {
            this.showEmpty();
            return;
        }

        // Render grouped events
        let html = '';
        let isFirst = true;

        monthGroups.forEach((group, groupIndex) => {
            html += this.renderMonthGroup(group, isFirst, showAI, generalFeatured, categoryFeatured, groupIndex);
            isFirst = false;
        });

        grid.innerHTML = html;
        grid.classList.remove('hidden', 'grid');
        grid.classList.add('flex', 'flex-col', 'gap-8');

        // Initialize reveal animations
        this.initRevealAnimations();
    },

    /**
     * Render a month group with header and events grid/list
     * @param {Object} group - Month group with events
     * @param {boolean} isFirst - Is this the first group
     * @param {boolean} showAI - Should AI features be shown
     * @param {Array} generalFeatured - General featured events
     * @param {Array} categoryFeatured - Category featured events
     * @param {number} groupIndex - Index of this group
     */
    renderMonthGroup(group, isFirst = false, showAI = true, generalFeatured = [], categoryFeatured = [], groupIndex = 0) {
        let eventsHtml;

        // Card options based on AI state
        const cardOptions = { showMatch: showAI };

        if (this.view === 'list') {
            eventsHtml = group.events.map(event => TicsEventCard.renderHorizontal(event, cardOptions)).join('');
        } else {
            // For first group, include featured cards
            if (isFirst && group.events.length >= 3) {
                let cards = [];

                // Add General Featured (big 2x2 card) if available
                if (generalFeatured.length > 0) {
                    cards.push(TicsEventPromoCard.renderGeneralFeatured(generalFeatured[0], { showMatch: showAI }));
                }

                // Add regular events
                const eventsToShow = generalFeatured.length > 0 ? group.events : group.events.slice(0, group.events.length);
                const insertPromoAt = Math.min(4, eventsToShow.length);

                eventsToShow.forEach((event, idx) => {
                    // Insert Category Featured (small promo) at position 4-5
                    if (idx === insertPromoAt && categoryFeatured.length > 0) {
                        cards.push(TicsEventPromoCard.renderPromo(categoryFeatured[0]));
                    }

                    // Insert AI Recommend card at position 7-8 if AI is enabled
                    if (idx === 7 && showAI && eventsToShow.length > 8) {
                        cards.push(TicsEventPromoCard.renderAIRecommend(eventsToShow[idx], { reason: 'Bazat pe preferințele tale' }));
                    } else {
                        cards.push(TicsEventCard.render(event, cardOptions));
                    }
                });

                eventsHtml = cards.join('');
            } else {
                // For subsequent groups, optionally add category featured
                let cards = [];

                // Add category promo at position 3-4 for second group
                if (groupIndex === 1 && categoryFeatured.length > 1) {
                    const insertAt = Math.min(3, group.events.length);
                    group.events.forEach((event, idx) => {
                        if (idx === insertAt) {
                            cards.push(TicsEventPromoCard.renderPromo(categoryFeatured[1]));
                        }
                        cards.push(TicsEventCard.render(event, cardOptions));
                    });
                    eventsHtml = cards.join('');
                } else {
                    eventsHtml = group.events.map(event => TicsEventCard.render(event, cardOptions)).join('');
                }
            }
        }

        const gridClass = this.view === 'grid'
            ? 'grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
            : 'flex flex-col gap-4';

        return `
            <div class="month-group reveal">
                <div class="flex items-center gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">${group.label}</h2>
                    <span class="px-3 py-1 text-sm font-medium text-gray-600 bg-gray-100 rounded-full">
                        ${group.events.length} ${group.events.length === 1 ? 'eveniment' : 'evenimente'}
                    </span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>
                <div class="${gridClass}">${eventsHtml}</div>
            </div>
        `;
    },

    /**
     * Initialize scroll reveal animations
     */
    initRevealAnimations() {
        const reveals = document.querySelectorAll('.reveal');
        reveals.forEach((el, index) => {
            setTimeout(() => {
                el.classList.add('visible');
            }, index * 100);
        });
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
        const container = document.getElementById(this.elements.pagination);
        if (!container || this.totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        let html = '';
        const maxVisible = 5;
        const start = Math.max(1, this.page - Math.floor(maxVisible / 2));
        const end = Math.min(this.totalPages, start + maxVisible - 1);

        // Previous button
        if (this.page > 1) {
            html += `<button onclick="TicsEventsPage.goToPage(${this.page - 1})" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>`;
        }

        // Page numbers
        for (let i = start; i <= end; i++) {
            if (i === this.page) {
                html += `<button class="w-10 h-10 rounded-lg bg-gray-900 text-white font-medium">${i}</button>`;
            } else {
                html += `<button onclick="TicsEventsPage.goToPage(${i})" class="w-10 h-10 rounded-lg border border-gray-200 hover:bg-gray-50 font-medium">${i}</button>`;
            }
        }

        // Next button
        if (this.page < this.totalPages) {
            html += `<button onclick="TicsEventsPage.goToPage(${this.page + 1})" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>`;
        }

        container.innerHTML = html;
    },

    /**
     * Navigate to page
     */
    goToPage(page) {
        this.page = page;
        this.loadEvents();
        TicsUtils.scrollTo('#eventsGrid', 120);
    },

    /**
     * Apply filters and reload
     */
    applyFilters() {
        const cityEl = document.getElementById(this.elements.cityFilter);
        const dateEl = document.getElementById(this.elements.dateFilter);
        const priceEl = document.getElementById(this.elements.priceFilter);
        const sortEl = document.getElementById(this.elements.sortFilter);

        if (cityEl) this.filters.city = cityEl.value;
        if (dateEl) this.filters.date = dateEl.value;
        if (priceEl) this.filters.price = priceEl.value;
        if (sortEl) this.filters.sort = sortEl.value;

        // Get feature checkboxes
        const features = [];
        document.querySelectorAll('[data-feature]:checked').forEach(cb => {
            features.push(cb.dataset.feature);
        });
        this.filters.features = features;

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

        // Update visual state of category chips
        document.querySelectorAll(this.elements.categoryChips).forEach(chip => {
            const chipCategory = chip.dataset.category || '';
            if (chipCategory === categorySlug) {
                chip.classList.add('chip-active');
            } else {
                chip.classList.remove('chip-active');
            }
        });

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    /**
     * Set AI match minimum filter
     */
    setAIMatch(value) {
        this.filters.aiMatch = value;

        // Update button states
        document.querySelectorAll('[data-ai-match]').forEach(btn => {
            const btnValue = parseInt(btn.dataset.aiMatch) || 0;
            if (btnValue === value) {
                btn.classList.remove('bg-gray-100', 'text-gray-600');
                btn.classList.add('bg-gray-900', 'text-white');
            } else {
                btn.classList.remove('bg-gray-900', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            }
        });

        this.page = 1;
        this.loadEvents();
    },

    /**
     * Sync filters between mobile and desktop
     */
    syncFilters(mobileId) {
        const mappings = {
            cityFilterMobile: 'cityFilter',
            dateFilterMobile: 'dateFilter',
            priceFilterMobile: 'priceFilter',
            sortFilterMobile: 'sortFilter'
        };

        const desktopId = mappings[mobileId];
        if (desktopId) {
            const mobileEl = document.getElementById(mobileId);
            const desktopEl = document.getElementById(desktopId);
            if (mobileEl && desktopEl) {
                desktopEl.value = mobileEl.value;
            }
        }

        this.updateMobileFilterCount();
    },

    /**
     * Update mobile filter count badge
     */
    updateMobileFilterCount() {
        let count = 0;
        ['cityFilterMobile', 'dateFilterMobile', 'priceFilterMobile'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.value) count++;
        });

        const badge = document.getElementById(this.elements.mobileFilterCount);
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    },

    /**
     * Clear all filters
     */
    clearFilters() {
        this.filters = {
            category: '',
            city: '',
            genre: '',
            price: '',
            date: '',
            sort: 'recommended',
            search: '',
            aiMatch: 0,
            features: []
        };

        // Reset category chips
        document.querySelectorAll(this.elements.categoryChips).forEach(chip => {
            const chipCategory = chip.dataset.category || '';
            if (chipCategory === '') {
                chip.classList.add('chip-active');
            } else {
                chip.classList.remove('chip-active');
            }
        });

        // Reset dropdowns
        ['cityFilter', 'dateFilter', 'priceFilter', 'sortFilter',
         'cityFilterMobile', 'dateFilterMobile', 'priceFilterMobile', 'sortFilterMobile'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = id.includes('sort') ? 'recommended' : '';
        });

        // Reset checkboxes
        document.querySelectorAll('[data-feature]').forEach(cb => cb.checked = false);

        this.updateMobileFilterCount();
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
        if (this.filters.date) params.set('data', this.filters.date);
        if (this.filters.price) params.set('pret', this.filters.price);
        if (this.filters.sort && this.filters.sort !== 'recommended') params.set('sortare', this.filters.sort);
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
            const chip = document.querySelector(`${this.elements.categoryChips}[data-category="${this.filters.category}"]`);
            const categoryName = chip ? chip.textContent.trim() : this.filters.category;
            activeFilters.push({ key: 'category', label: categoryName });
        }
        if (this.filters.city) activeFilters.push({ key: 'city', label: this.filters.city });
        if (this.filters.date) activeFilters.push({ key: 'date', label: this.getDateLabel(this.filters.date) });
        if (this.filters.price) activeFilters.push({ key: 'price', label: this.filters.price });

        if (activeFilters.length === 0) {
            container.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        tagsContainer.innerHTML = activeFilters.map(f => `
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white rounded-full text-xs font-medium whitespace-nowrap">
                ${TicsUtils.escapeHtml(f.label)}
                <button onclick="TicsEventsPage.removeFilter('${f.key}')" class="hover:bg-white/20 rounded-full">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>
        `).join('');
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
            date: this.elements.dateFilter,
            price: this.elements.priceFilter
        };

        const elementId = elementMap[key];
        if (elementId) {
            const el = document.getElementById(elementId);
            if (el) el.value = '';
        }

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    /**
     * Set grid/list view
     */
    setView(view) {
        this.view = view;
        const gridBtn = document.getElementById(this.elements.viewGrid);
        const listBtn = document.getElementById(this.elements.viewList);

        if (view === 'grid') {
            gridBtn?.classList.add('bg-gray-900', 'text-white');
            gridBtn?.classList.remove('text-gray-400');
            listBtn?.classList.remove('bg-gray-900', 'text-white');
            listBtn?.classList.add('text-gray-400');
        } else {
            listBtn?.classList.add('bg-gray-900', 'text-white');
            listBtn?.classList.remove('text-gray-400');
            gridBtn?.classList.remove('bg-gray-900', 'text-white');
            gridBtn?.classList.add('text-gray-400');
        }

        // Re-render events with new view
        if (this.events.length > 0) {
            this.renderEvents();
        }
    }
};

// Make available globally
window.TicsEventsPage = TicsEventsPage;
