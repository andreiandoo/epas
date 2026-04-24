/**
 * Ambilet.ro - Event Single Page Controller
 * Handles event detail page with ticket selection, cart, and related events
 *
 * Dependencies: AmbiletAPI, AmbiletCart, AmbiletEventCard, AmbiletDataTransformer
 */

const EventPage = {
    // State
    slug: '',
    event: null,
    quantities: {},
    ticketTypes: [],
    isInterested: false,
    shareMenuOpen: false,
    seatingLayout: null,
    selectedSeats: {},  // ticketTypeId -> [seatId, ...]
    seatSelectionModal: null,
    sectionToTicketTypeMap: {},  // sectionId -> [ticketType, ...]
    rowToTicketTypeMap: {},      // rowId -> [ticketType, ...]

    // Default color palette when ticket type has no color set
    _colorPalette: ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'],

    /**
     * Get display color for a ticket type (with fallback to palette)
     */
    getTicketTypeColor(tt) {
        if (tt.color) return tt.color;
        var idx = this.ticketTypes.indexOf(tt);
        if (idx < 0) {
            for (var i = 0; i < this.ticketTypes.length; i++) {
                if (this.ticketTypes[i].id === tt.id) { idx = i; break; }
            }
        }
        if (idx < 0) idx = 0;
        return this._colorPalette[idx % this._colorPalette.length];
    },

    /**
     * Calculate commission for a ticket type
     * @param {Object} ticketType - The ticket type object
     * @param {number} basePrice - The base price of the ticket
     * @returns {Object} { amount: number, rate: number|null, fixed: number|null, mode: string, type: string }
     */
    calculateTicketCommission(ticketType, basePrice) {
        // Check if ticket has custom commission settings
        if (ticketType.commission && ticketType.commission.type) {
            const comm = ticketType.commission;
            let amount = 0;

            switch (comm.type) {
                case 'percentage':
                    amount = basePrice * (comm.rate / 100);
                    break;
                case 'fixed':
                    amount = comm.fixed || 0;
                    break;
                case 'both':
                    amount = (basePrice * (comm.rate / 100)) + (comm.fixed || 0);
                    break;
            }

            return {
                amount: Math.round(amount * 100) / 100,
                rate: comm.rate || null,
                fixed: comm.fixed || null,
                mode: comm.mode || this.event.commission_mode || 'included',
                type: comm.type
            };
        }

        // Fall back to event-level commission (percentage only)
        const rate = this.event.commission_rate || 5;
        const mode = this.event.commission_mode || 'included';
        const amount = basePrice * (rate / 100);

        return {
            amount: Math.round(amount * 100) / 100,
            rate: rate,
            fixed: null,
            mode: mode,
            type: 'percentage'
        };
    },

    // DOM element IDs
    elements: {
        loadingState: 'loading-state',
        eventContent: 'event-content',
        breadcrumbTitle: 'breadcrumb-title',
        mainImage: 'mainImage',
        eventBadges: 'event-badges',
        eventTitle: 'event-title',
        eventDay: 'event-day',
        eventMonth: 'event-month',
        eventWeekday: 'event-weekday',
        eventDateFull: 'event-date-full',
        eventTime: 'event-time',
        eventDoors: 'event-doors',
        venueName: 'venue-name',
        venueAddress: 'venue-address',
        venueLink: 'venue-link',
        interestBtn: 'interest-btn',
        interestIcon: 'interest-icon',
        eventInterested: 'event-interested',
        eventViews: 'event-views',
        shareDropdown: 'share-dropdown',
        shareMenu: 'share-menu',
        eventDescription: 'event-description',
        artistSection: 'artist-section',
        artistContent: 'artist-content',
        venue: 'venue',
        venueContent: 'venue-content',
        ticketTypes: 'ticket-types',
        cartSummary: 'cartSummary',
        emptyCart: 'emptyCart',
        subtotal: 'subtotal',
        taxesContainer: 'taxesContainer',
        totalPrice: 'totalPrice',
        pointsEarned: 'pointsEarned',
        checkoutBtn: 'checkoutBtn',
        customRelatedSection: 'custom-related-section',
        customRelatedEvents: 'custom-related-events',
        relatedEventsSection: 'related-events-section',
        relatedCategoryText: 'related-category-text',
        seeAllLink: 'see-all-link',
        relatedEvents: 'related-events',
        cartBadge: 'cartBadge',
        cartDrawerCount: 'cartDrawerCount'
    },

    /**
     * Initialize the page
     */
    async init() {
        // Get slug from URL
        const urlParams = new URLSearchParams(window.location.search);
        this.slug = urlParams.get('slug') ||
                   window.location.pathname.split('/bilete/')[1]?.split('?')[0] || '';
        this.isPreview = urlParams.get('preview') === '1';
        this.previewToken = urlParams.get('preview_token') || null;

        if (!this.slug) {
            window.location.href = '/';
            return;
        }

        await this.loadEvent();
        this.updateHeaderCart();
        this.trackView();
        this.loadInterestStatus();
        this.setupClickOutside();
    },

    /**
     * Track page view
     */
    async trackView() {
        try {
            var response = await AmbiletAPI.trackEventView(this.slug);
            console.log('[EventPage] Track view response:', response);
        } catch (e) {
            console.error('[EventPage] View tracking failed:', e);
        }
    },

    /**
     * Load interest status for current user
     */
    async loadInterestStatus() {
        // Skip if user is not logged in - we'll use the counts from the event data
        if (typeof AmbiletAuth !== 'undefined' && !AmbiletAuth.isLoggedIn()) {
            console.log('[EventPage] User not logged in, skipping interest check');
            return;
        }

        try {
            var response = await AmbiletAPI.checkEventInterest(this.slug);
            console.log('[EventPage] Interest check response:', response);
            if (response.success && response.data) {
                this.isInterested = response.data.is_interested;
                this.updateInterestButton();
                // Update views count only (interest button now shows text, not count)
                if (response.data.views_count !== undefined) {
                    document.getElementById(this.elements.eventViews).textContent =
                        this.formatCount(response.data.views_count) + ' vizualizări';
                }
            }
        } catch (e) {
            // Silently ignore auth errors - user just isn't logged in
            if (e.status === 401 || e.status === 500 || (e.message && e.message.includes('Auth'))) {
                console.log('[EventPage] Interest check skipped - user not authenticated');
                return;
            }
            console.error('[EventPage] Interest check failed:', e);
        }
    },

    /**
     * Toggle interest for event
     */
    async toggleInterest() {
        // Check if user is logged in
        if (typeof AmbiletAuth !== 'undefined' && !AmbiletAuth.isLoggedIn()) {
            this.showLoginPrompt();
            return;
        }

        try {
            var response = await AmbiletAPI.toggleEventInterest(this.slug);
            console.log('[EventPage] Toggle interest response:', response);
            if (response.success && response.data) {
                this.isInterested = response.data.is_interested;
                this.updateInterestButton();
                // updateInterestButton now handles the text change
            }
        } catch (e) {
            console.error('[EventPage] Toggle interest failed:', e);
            // Check if it's an auth error
            if (e.status === 401 || e.status === 500 || (e.message && e.message.includes('Auth'))) {
                this.showLoginPrompt();
            }
        }
    },

    /**
     * Show login prompt modal
     */
    showLoginPrompt() {
        // Check if modal already exists
        var existingModal = document.getElementById('login-prompt-modal');
        if (existingModal) {
            existingModal.classList.remove('hidden');
            return;
        }

        // Create modal
        var modal = document.createElement('div');
        modal.id = 'login-prompt-modal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm';
        modal.innerHTML =
            '<div class="w-full max-w-md p-6 bg-white shadow-2xl rounded-2xl">' +
                '<div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-primary/10">' +
                    '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>' +
                    '</svg>' +
                '</div>' +
                '<h3 class="mb-2 text-xl font-bold text-center text-secondary">Conecteaza-te pentru a salva</h3>' +
                '<p class="mb-6 text-center text-muted">Pentru a marca acest eveniment ca fiind de interes, trebuie sa fii conectat in contul tau.</p>' +
                '<div class="flex flex-col gap-3">' +
                    '<a href="/cont/autentificare" class="flex items-center justify-center w-full gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>' +
                        'Conecteaza-te' +
                    '</a>' +
                    '<a href="/cont/inregistrare" class="flex items-center justify-center w-full gap-2 px-6 py-3 font-semibold transition-colors border-2 text-secondary rounded-xl border-border hover:border-primary hover:text-primary">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>' +
                        'Creeaza cont nou' +
                    '</a>' +
                    '<button onclick="EventPage.closeLoginPrompt()" class="w-full px-6 py-3 font-medium transition-colors text-muted hover:text-secondary" aria-label="Close login prompt">' +
                        'Inchide' +
                    '</button>' +
                '</div>' +
            '</div>';

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                EventPage.closeLoginPrompt();
            }
        });

        document.body.appendChild(modal);
    },

    /**
     * Close login prompt modal
     */
    closeLoginPrompt() {
        var modal = document.getElementById('login-prompt-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    },

    /**
     * Update interest button visual state and text
     */
    updateInterestButton() {
        var btn = document.getElementById(this.elements.interestBtn);
        var icon = document.getElementById(this.elements.interestIcon);
        var textSpan = document.getElementById(this.elements.eventInterested);
        if (this.isInterested) {
            btn.classList.add('border-primary', 'text-primary', 'bg-primary/5');
            icon.setAttribute('fill', 'currentColor');
            textSpan.textContent = 'Trecut la favorite';
        } else {
            btn.classList.remove('border-primary', 'text-primary', 'bg-primary/5');
            icon.setAttribute('fill', 'none');
            textSpan.textContent = 'Mă interesează';
        }
    },

    /**
     * Format large numbers (1k, 1M, etc.)
     */
    formatCount(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        }
        return String(num);
    },

    /**
     * Toggle share dropdown menu
     */
    toggleShareMenu() {
        var menu = document.getElementById(this.elements.shareMenu);
        this.shareMenuOpen = !this.shareMenuOpen;
        menu.classList.toggle('hidden', !this.shareMenuOpen);
    },

    /**
     * Setup click outside handler for share menu
     */
    setupClickOutside() {
        var self = this;
        document.addEventListener('click', function(e) {
            var dropdown = document.getElementById(self.elements.shareDropdown);
            if (dropdown && !dropdown.contains(e.target) && self.shareMenuOpen) {
                self.shareMenuOpen = false;
                document.getElementById(self.elements.shareMenu).classList.add('hidden');
            }
        });
    },

    /**
     * Share on social platform
     */
    shareOn(platform) {
        var url = window.location.href;
        var title = this.event ? this.event.title : document.title;
        var shareUrl = '';

        switch (platform) {
            case 'facebook':
                shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                break;
            case 'whatsapp':
                shareUrl = 'https://wa.me/?text=' + encodeURIComponent(title + ' - ' + url);
                break;
            case 'email':
                shareUrl = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent('Uita-te la acest eveniment: ' + url);
                window.location.href = shareUrl;
                this.toggleShareMenu();
                return;
        }

        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
        this.toggleShareMenu();
    },

    /**
     * Copy event link to clipboard
     */
    async copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            // Show brief success message
            var btn = document.querySelector('[onclick*="copyLink"]');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copiat!';
            setTimeout(function() {
                btn.innerHTML = originalText;
            }, 2000);
        } catch (e) {
            console.error('Copy failed:', e);
        }
        this.toggleShareMenu();
    },

    /**
     * Load event data from API
     */
    async loadEvent() {
        // Use server-injected data if available (eliminates browser→API roundtrip)
        if (window.__EVENT_PRELOAD__ && !this.isPreview) {
            try {
                const preloadData = window.__EVENT_PRELOAD__;
                window.__EVENT_PRELOAD__ = null;
                this.event = this.transformApiData(preloadData);

                // Check password protection before rendering
                if (this.event.is_password_protected && !this.isEventUnlocked()) {
                    this.showPasswordGate();
                    return;
                }

                this.render();
                if (this.event.organizer && this.event.organizer.id) {
                    this.loadOrganizerTracking(this.event.organizer.id);
                }
                // If preload data lacks ticket_types, fetch full data in background
                if (!preloadData.ticket_types || !preloadData.ticket_types.length) {
                    this.refreshFromApi();
                }
                return;
            } catch (e) {
                console.warn('[EventPage] Preload data failed, falling back to API:', e);
            }
        }

        try {
            const params = this.isPreview ? { preview: true } : {};
            const response = await AmbiletAPI.getEvent(this.slug, params);
            if (response.success && response.data) {
                this.event = this.transformApiData(response.data);

                // Check password protection before rendering
                if (this.event.is_password_protected && !this.isEventUnlocked()) {
                    this.showPasswordGate();
                    return;
                }

                this.render();
                if (this.event.organizer && this.event.organizer.id) {
                    this.loadOrganizerTracking(this.event.organizer.id);
                }
            } else {
                this.showError('Eveniment negăsit');
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            if (error.status === 410 && error.data && error.data.ended) {
                this.showEndedPerformance(error.data);
            } else if (error.status === 404) {
                this.showError('Eveniment negăsit');
            } else {
                this.showError('Eroare la încărcarea evenimentului');
            }
        }
    },

    /**
     * Check if event is unlocked via sessionStorage
     */
    isEventUnlocked() {
        try {
            return sessionStorage.getItem('event_unlocked_' + this.event.id) === '1';
        } catch (e) {
            return false;
        }
    },

    /**
     * Show password gate instead of event content
     */
    showPasswordGate() {
        const el = document.getElementById(this.elements.loadingState);
        const eventContent = document.getElementById('event-content');
        const mobileBtn = document.getElementById('mobileTicketBtn');

        if (eventContent) eventContent.style.display = 'none';
        if (mobileBtn) mobileBtn.style.display = 'none';

        const title = this.event?.title || 'Eveniment protejat';

        el.className = 'flex flex-col items-center gap-8';
        el.innerHTML =
            '<div class="w-full max-w-md mx-auto py-16 px-4 text-center">' +
                '<div class="mb-8">' +
                    '<div class="w-20 h-20 mx-auto mb-6 flex items-center justify-center rounded-full bg-primary/10">' +
                        '<svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>' +
                    '</div>' +
                    '<h1 class="mb-2 text-2xl font-bold text-secondary">' + title + '</h1>' +
                    '<p class="text-muted">Acest eveniment este protejat cu parolă. Introdu parola pentru a accesa pagina.</p>' +
                '</div>' +
                '<div id="password-gate-form">' +
                    '<div class="flex gap-2">' +
                        '<input type="password" id="event-password-input" placeholder="Introdu parola" ' +
                            'class="flex-1 px-4 py-3 text-base border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" ' +
                            'autocomplete="off">' +
                        '<button onclick="EventPage.submitPassword()" id="password-submit-btn" ' +
                            'class="px-6 py-3 text-base font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition-colors">' +
                            'Accesează' +
                        '</button>' +
                    '</div>' +
                    '<p id="password-error" class="mt-3 text-sm text-red-600 hidden">Parolă incorectă. Încearcă din nou.</p>' +
                '</div>' +
            '</div>';

        // Focus input and handle Enter key
        const input = document.getElementById('event-password-input');
        if (input) {
            input.focus();
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    EventPage.submitPassword();
                }
            });
        }
    },

    /**
     * Submit password for verification
     */
    async submitPassword() {
        const input = document.getElementById('event-password-input');
        const errorEl = document.getElementById('password-error');
        const btn = document.getElementById('password-submit-btn');
        const password = input?.value?.trim();

        if (!password) {
            input?.focus();
            return;
        }

        // Disable button while checking
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Se verifică...';
        }
        if (errorEl) errorEl.classList.add('hidden');

        try {
            const response = await AmbiletAPI.verifyEventPassword(this.slug, password);
            if (response.success) {
                // Store unlock in sessionStorage
                try {
                    sessionStorage.setItem('event_unlocked_' + this.event.id, '1');
                } catch (e) {}

                // Re-render the page normally
                const el = document.getElementById(this.elements.loadingState);
                el.className = 'flex flex-col gap-8 lg:flex-row';
                el.style.display = 'none';

                const eventContent = document.getElementById('event-content');
                if (eventContent) eventContent.style.display = '';

                this.render();
                if (this.event.organizer && this.event.organizer.id) {
                    this.loadOrganizerTracking(this.event.organizer.id);
                }
                if (!this.event.ticketTypes?.length) {
                    this.refreshFromApi();
                }
            }
        } catch (error) {
            if (errorEl) errorEl.classList.remove('hidden');
            if (input) {
                input.value = '';
                input.focus();
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Accesează';
            }
        }
    },

    /**
     * Refresh event data from API in background (for ticket types etc.)
     */
    async refreshFromApi() {
        try {
            const params = this.isPreview ? { preview: true } : {};
            const response = await AmbiletAPI.getEvent(this.slug, params);
            if (response.success && response.data) {
                this.event = this.transformApiData(response.data);
                // Re-render only ticket section with fresh data
                if (this.event.ticketTypes && this.event.ticketTypes.length) {
                    this.renderTicketTypes();
                }
            }
        } catch (e) {
            console.warn('[EventPage] Background API refresh failed:', e);
        }
    },

    /**
     * Transform API response to expected format
     */
    transformApiData(apiData) {
        var eventData = apiData.event || apiData;
        var venueData = apiData.venue || null;
        var artistsData = apiData.artists || [];
        var ticketTypesData = apiData.ticket_types || [];
        // Performances are at top-level in API response, not inside event
        var performancesData = apiData.performances || eventData.performances || [];
        console.log('[EventPage] Performances data:', performancesData, 'apiData keys:', Object.keys(apiData), 'duration_mode:', eventData.duration_mode);

        // Parse starts_at to get date and time
        var startsAt = eventData.starts_at ? new Date(eventData.starts_at) : new Date();
        var doorsAt = eventData.doors_open_at ? new Date(eventData.doors_open_at) : null;

        function formatTime(date) {
            if (!date) return null;
            return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }

        // For range events, use explicit time fields; for single_day, parse from starts_at
        var durationMode = eventData.duration_mode || 'single_day';
        var resolvedStartTime = (durationMode === 'range')
            ? (eventData.range_start_time || eventData.start_time || formatTime(startsAt))
            : (eventData.start_time || formatTime(startsAt));
        var resolvedDoorsTime = eventData.doors_time || eventData.door_time || formatTime(doorsAt);
        // Skip showing 00:00 if it came from a date-only starts_at
        if (resolvedStartTime === '00:00') resolvedStartTime = null;

        // poster_url / image_url = vertical poster (mobile), cover_image_url / hero_image_url = horizontal hero (desktop)
        var posterImage = eventData.poster_url || eventData.image_url || null;
        var heroImage = eventData.hero_image_url || eventData.cover_image_url || eventData.image_url || null;

        return {
            id: eventData.id,
            title: eventData.name,
            slug: eventData.slug,
            description: eventData.description,
            content: eventData.description,
            short_description: eventData.short_description,
            image: heroImage || posterImage,
            posterImage: posterImage,
            heroImage: heroImage,
            images: [heroImage, posterImage].filter(Boolean).filter(function(v, i, a) { return a.indexOf(v) === i; }),
            category: eventData.category,
            category_slug: eventData.category_slug || (eventData.category ? eventData.category.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-') : null),
            tags: eventData.tags,
            // Schedule mode and dates
            duration_mode: eventData.duration_mode || 'single_day',
            start_date: eventData.starts_at,
            date: eventData.starts_at,
            end_date: eventData.ends_at,
            range_start_date: eventData.range_start_date,
            range_end_date: eventData.range_end_date,
            range_start_time: eventData.range_start_time,
            range_end_time: eventData.range_end_time,
            multi_slots: eventData.multi_slots,
            performances: performancesData,
            selectedPerformanceId: eventData.selected_performance_id || null,
            start_time: resolvedStartTime,
            end_time: eventData.end_time || (eventData.ends_at ? eventData.ends_at.substring(11, 16) : null),
            doors_time: resolvedDoorsTime,
            is_popular: eventData.is_featured,
            is_featured: eventData.is_featured,
            interested_count: eventData.interested_count || 0,
            views_count: eventData.views_count || 0,
            // Status flags
            is_password_protected: eventData.is_password_protected || false,
            is_sold_out: eventData.is_sold_out || false,
            is_cancelled: eventData.is_cancelled || false,
            cancel_reason: eventData.cancel_reason || null,
            is_postponed: eventData.is_postponed || false,
            postponed_reason: eventData.postponed_reason || null,
            postponed_date: eventData.postponed_date || null,
            postponed_start_time: eventData.postponed_start_time || null,
            postponed_door_time: eventData.postponed_door_time || null,
            postponed_end_time: eventData.postponed_end_time || null,
            venue: venueData ? {
                name: venueData.name,
                slug: venueData.slug,
                description: venueData.description,
                address: venueData.address,
                city: venueData.city,
                state: venueData.state,
                country: venueData.country,
                latitude: venueData.latitude,
                longitude: venueData.longitude,
                google_maps_url: venueData.google_maps_url,
                image: venueData.image,
                capacity: venueData.capacity
            } : null,
            location: venueData ? (venueData.city ? venueData.name + ', ' + venueData.city : venueData.name) : 'Locatie TBA',
            artist: artistsData.length ? {
                name: artistsData[0].name,
                image: artistsData[0].image_url,
                image_url: artistsData[0].image_url,
                slug: artistsData[0].slug,
                bio: artistsData[0].bio,
                social_links: artistsData[0].social_links,
                verified: artistsData[0].verified
            } : null,
            artists: artistsData,
            ticket_types: ticketTypesData.map(function(tt) {
                var available = tt.available_quantity !== undefined ? tt.available_quantity : (tt.available !== undefined ? tt.available : 999);
                return {
                    id: tt.id,
                    name: tt.name,
                    description: tt.description,
                    price: tt.price,
                    original_price: tt.original_price || null,
                    discount_percent: tt.discount_percent || null,
                    currency: tt.currency || 'RON',
                    color: tt.color || null,
                    available: available,
                    min_per_order: tt.min_per_order || 1,
                    max_per_order: tt.max_per_order || 10,
                    multiplier: tt.multiplier || 1,
                    status: tt.status,
                    is_sold_out: available <= 0,
                    has_seating: tt.has_seating || false,
                    seating_sections: tt.seating_sections || [],
                    seating_rows: tt.seating_rows || [],
                    commission: tt.commission || null,
                    is_refundable: tt.is_refundable || false,
                    ticket_group: tt.ticket_group || null,
                    perks: tt.perks || []
                };
            }),
            seating_layout: apiData.seating_layout || null,
            max_tickets_per_order: eventData.max_tickets_per_order || 10,
            target_price: eventData.target_price || null,
            commission_rate: apiData.commission_rate || 5,
            commission_mode: apiData.commission_mode || 'included',
            taxes: apiData.taxes || [],
            // Organizer
            organizer: apiData.organizer || null,
            // Custom related events
            has_custom_related: eventData.has_custom_related || false,
            custom_related_event_ids: eventData.custom_related_event_ids || [],
            custom_related_events: apiData.custom_related_events || [],
            // Tour/Grouping events
            tour_name: apiData.tour_name || null,
            tour_type: apiData.tour_type || 'serie_evenimente',
            tour_events: apiData.tour_events || [],
            // Ticket terms (HTML from WYSIWYG editor)
            ticket_terms: (apiData.event && apiData.event.ticket_terms) ? apiData.event.ticket_terms : null,
            // Ticket display options
            enable_ticket_groups: apiData.enable_ticket_groups || false,
            enable_ticket_perks: apiData.enable_ticket_perks || false
        };
    },

    /**
     * Show error state
     */
    showError(message) {
        const el = document.getElementById(this.elements.loadingState);
        el.className = 'flex flex-col gap-8';
        el.innerHTML =
            '<div class="w-full py-16 text-center">' +
                '<svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<h1 class="mb-4 text-2xl font-bold text-secondary">' + message + '</h1>' +
                '<a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>' +
                    'Înapoi acasă' +
                '</a>' +
            '</div>' +
            '<div id="error-recommended" class="mt-12 text-left"></div>';

        // Load recommended events
        this.loadErrorRecommendations();
    },

    showEndedPerformance(data) {
        var self = this;
        var months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        var days = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];

        var perfsHtml = '';
        var upcoming = data.upcoming_performances || [];
        if (upcoming.length > 0) {
            perfsHtml = '<div class="mt-6 text-left">' +
                '<h3 class="mb-3 text-lg font-bold text-secondary">Mai poți găsi bilete la:</h3>' +
                '<div class="space-y-2">' +
                upcoming.map(function(p) {
                    var d = new Date(p.date + 'T' + (p.start_time || '00:00'));
                    var dayName = days[d.getDay()];
                    var dayNum = d.getDate();
                    var monthName = months[d.getMonth()];
                    var year = d.getFullYear();
                    var timeRange = p.start_time + (p.end_time ? ' – ' + p.end_time : '');

                    return '<a href="/bilete/' + self.escapeHtml(data.parent_slug) + '" class="flex items-center gap-4 p-4 bg-white border rounded-xl border-border hover:border-primary hover:shadow-md transition-all">' +
                        '<div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl bg-primary/10 shrink-0">' +
                            '<span class="text-lg font-bold leading-none text-primary">' + dayNum + '</span>' +
                            '<span class="text-[10px] font-semibold text-primary/70 uppercase">' + monthName.substring(0, 3) + '</span>' +
                        '</div>' +
                        '<div class="flex-1">' +
                            '<div class="font-semibold text-secondary">' + dayName + ', ' + dayNum + ' ' + monthName + ' ' + year + '</div>' +
                            '<div class="text-sm text-muted">' + timeRange + '</div>' +
                        '</div>' +
                        '<svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
                    '</a>';
                }).join('') +
                '</div>' +
            '</div>';
        }

        var el = document.getElementById(this.elements.loadingState);
        el.className = 'flex flex-col gap-8';
        el.innerHTML =
            '<div class="w-full py-12 text-center">' +
                '<svg class="w-16 h-16 mx-auto mb-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<h1 class="mb-2 text-2xl font-bold text-secondary">Această reprezentație s-a încheiat</h1>' +
                (data.parent_title ? '<p class="text-muted">' + self.escapeHtml(data.parent_title) + '</p>' : '') +
                perfsHtml +
                '<div class="mt-6">' +
                    '<a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>' +
                        'Înapoi acasă' +
                    '</a>' +
                '</div>' +
            '</div>' +
            '<div id="error-recommended" class="mt-8 text-left"></div>';

        this.loadErrorRecommendations();
    },

    async loadErrorRecommendations() {
        try {
            const response = await AmbiletAPI.getFeaturedEvents(8);
            const events = response?.data?.events || response?.data || [];
            if (!Array.isArray(events) || events.length === 0) return;

            const container = document.getElementById('error-recommended');
            if (!container) return;

            container.innerHTML =
                '<h2 class="mb-6 text-xl font-bold text-secondary flex items-center gap-2.5">' +
                    '<svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-width="2"/></svg>' +
                    'Evenimente recomandate' +
                '</h2>' +
                '<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">' +
                    AmbiletEventCard.renderMany(events.slice(0, 8), {
                        urlPrefix: '/bilete/',
                        showPromotedBadge: true
                    }) +
                '</div>';
        } catch (err) {
            console.error('Failed to load recommended events:', err);
        }
    },

    /**
     * Check if event has ended based on its dates
     * - Single day: ended when starts_at has passed
     * - Range: ended when range_end_date + range_end_time has passed
     * - Multi-day: ended when last slot date + end_time has passed
     * - If ends_at is set, use that as the end time
     */
    isEventEnded() {
        const e = this.event;
        if (!e) return false;

        const now = new Date();
        const durationMode = e.duration_mode || 'single_day';

        // If ends_at is explicitly set, use it
        if (e.end_date) {
            const endDate = new Date(e.end_date);
            if (!isNaN(endDate.getTime()) && now > endDate) return true;
        }

        if (durationMode === 'range' && e.range_end_date) {
            let endStr = e.range_end_date;
            if (e.range_end_time) endStr += 'T' + e.range_end_time;
            const endDate = new Date(endStr);
            if (!isNaN(endDate.getTime()) && now > endDate) return true;
            return false;
        }

        if (durationMode === 'multi_day' && e.multi_slots && e.multi_slots.length > 0) {
            const lastSlot = e.multi_slots[e.multi_slots.length - 1];
            let endStr = lastSlot.date;
            if (lastSlot.end_time) endStr += 'T' + lastSlot.end_time;
            else if (lastSlot.start_time) endStr += 'T' + lastSlot.start_time;
            const endDate = new Date(endStr);
            if (!isNaN(endDate.getTime()) && now > endDate) return true;
            return false;
        }

        // Single day: use end_time if available, otherwise start_date
        if (e.end_time && (e.start_date || e.date)) {
            const dateStr = (e.start_date || e.date).substring(0, 10);
            const endDateTime = new Date(dateStr + 'T' + e.end_time);
            if (!isNaN(endDateTime.getTime())) return now > endDateTime;
        }
        const startDate = new Date(e.start_date || e.date);
        if (!isNaN(startDate.getTime()) && now > startDate) return true;

        return false;
    },

    /**
     * Apply ended event layout:
     * - Hide ticket sidebar
     * - Center content column
     * - Show "event ended" banner before #event-content with related events
     * - Hide mobile ticket button
     */
    applyEndedLayout() {
        // Hide ticket sidebar
        const sidebar = document.querySelector('#event-content .sticky-cart-wrapper');
        if (sidebar) sidebar.style.display = 'none';

        // Make content full width and centered
        const contentCol = document.querySelector('#event-content .lg\\:w-2\\/3');
        if (contentCol) {
            contentCol.classList.remove('lg:w-2/3');
            contentCol.classList.add('lg:w-2/3', 'mx-auto');
            contentCol.style.maxWidth = '800px';
        }

        // Remove flex-row layout since sidebar is gone
        const eventContent = document.getElementById('event-content');
        if (eventContent) {
            eventContent.classList.remove('lg:flex-row');
            eventContent.classList.add('flex-col', 'items-center');
        }

        // Hide mobile ticket button
        const mobileBtn = document.getElementById('mobileTicketBtn');
        if (mobileBtn) mobileBtn.style.display = 'none';

        // Hide social stats (interested, views, share)
        const socialStats = document.getElementById('social-stats');
        const venueShortDisplay = document.getElementById('venue-short-display');
        if (socialStats) socialStats.style.display = 'none';
        if (venueShortDisplay) venueShortDisplay.classList.remove('mb-6');

        // Insert ended banner before #event-content
        this.renderEndedBanner();
    },

    /**
     * Render the "event ended" banner with related event recommendations
     */
    renderEndedBanner() {
        const eventContent = document.getElementById('event-content');
        if (!eventContent) return;

        const banner = document.createElement('div');
        banner.id = 'event-ended-banner';
        banner.className = 'w-full mb-8';
        banner.innerHTML =
            '<div class="bg-primary text-white rounded-2xl p-6 mobile:rounded-none">' +
                '<div class="flex items-center justify-center gap-3">' +
                    '<h2 class="text-xl font-bold">Evenimentul s-a încheiat</h2>' +
                '</div>' +
                '<p class="mb-6 text-center">dar încă mai găsești bilete la:</p>' +
                '<div id="ended-related-events" class="grid gap-4 grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>' +
                '<div id="ended-related-loading" class="py-8 text-sm text-center">Se caută sugestii de evenimente...</div>' +
            '</div>';

        eventContent.parentNode.insertBefore(banner, eventContent);

        // Load related events for the banner
        this.loadEndedRelatedEvents();
    },

    /**
     * Load related events to show in the ended banner
     */
    async loadEndedRelatedEvents() {
        try {
            const params = new URLSearchParams({ limit: 8 });
            if (this.event.category_slug) {
                params.append('category', this.event.category_slug);
            }

            const response = await AmbiletAPI.get('/events?' + params.toString());
            const container = document.getElementById('ended-related-events');
            const loading = document.getElementById('ended-related-loading');

            if (loading) loading.style.display = 'none';

            if (response.success && response.data?.length) {
                const currentId = this.event.id;
                const currentSlug = this.event.slug;
                const filtered = response.data.filter(function(e) {
                    return e.id !== currentId && e.slug !== currentSlug;
                }).slice(0, 4);

                if (filtered.length > 0 && container) {
                    container.innerHTML = AmbiletEventCard.renderMany(filtered, {
                        showCategory: true,
                        showPrice: true,
                        showVenue: true,
                        urlPrefix: '/bilete/'
                    });
                } else if (container) {
                    container.innerHTML = '<p class="col-span-full text-white text-sm text-center">Nu sunt alte evenimente disponibile momentan.</p>';
                }
            } else if (container) {
                container.innerHTML = '<p class="col-span-full text-white text-sm text-center">Nu sunt alte evenimente disponibile momentan.</p>';
            }
        } catch (e) {
            console.error('Failed to load ended related events:', e);
            const loading = document.getElementById('ended-related-loading');
            if (loading) loading.textContent = 'Nu sunt alte evenimente disponibile momentan.';
        }
    },

    /**
     * Render event details
     */
    render() {
        const e = this.event;

        // Update page title
        document.title = e.title + ' — ' + (typeof AMBILET_CONFIG !== 'undefined' ? AMBILET_CONFIG.SITE_NAME : 'Ambilet');

        // Update breadcrumb
        document.getElementById(this.elements.breadcrumbTitle).textContent = e.title;

        // Show content, hide loading
        document.getElementById(this.elements.loadingState).classList.add('hidden');
        document.getElementById(this.elements.eventContent).classList.remove('hidden');

        // Check if event has ended
        this.eventEnded = this.isEventEnded();
        if (this.eventEnded) {
            this.applyEndedLayout();
        }

        // Main image - responsive: poster (vertical) on mobile, hero (horizontal) on desktop
        const isMobile = window.innerWidth < 768;
        const usePoster = isMobile && e.posterImage;
        const mainImg = (usePoster ? e.posterImage : (e.heroImage || e.posterImage)) || e.images?.[0] || '/assets/images/default-event.png';
        document.getElementById(this.elements.mainImage).src = mainImg;
        document.getElementById(this.elements.mainImage).alt = e.title;

        // On mobile with poster image: remove fixed aspect-ratio so vertical poster shows naturally
        const mainImageContainer = document.getElementById('mainImageContainer');
        if (mainImageContainer && usePoster) {
            mainImageContainer.style.aspectRatio = '';
        }

        // Badges
        this.renderBadges(e);

        // Status alerts (cancelled, postponed)
        this.renderStatusAlerts(e);

        // Preview banner
        if (this.isPreview) {
            const titleEl = document.getElementById(this.elements.eventTitle);
            if (titleEl) {
                const isTestMode = !!this.previewToken;
                const borderColor = isTestMode ? 'border-amber-500' : 'border-indigo-500';
                const bgColor = isTestMode ? 'bg-amber-50' : 'bg-indigo-50';
                const iconColor = isTestMode ? 'text-amber-600' : 'text-indigo-600';
                const titleColor = isTestMode ? 'text-amber-800' : 'text-indigo-800';
                const textColor = isTestMode ? 'text-amber-600' : 'text-indigo-600';
                const icon = isTestMode
                    ? `<svg class="w-6 h-6 ${iconColor} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                       </svg>`
                    : `<svg class="w-6 h-6 ${iconColor} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                       </svg>`;
                const title = isTestMode ? 'Mod test comandă activ' : 'Previzualizare';
                const description = isTestMode
                    ? 'Comenzile plasate vor fi gratuite și marcate ca test. Nu se procesează plăți.'
                    : 'Acest eveniment nu este încă public. Aceasta este o previzualizare.';

                titleEl.insertAdjacentHTML('beforebegin', `
                    <div id="event-preview-banner" class="p-4 mb-6 border-l-4 ${borderColor} ${bgColor} rounded-r-xl">
                        <div class="flex items-start gap-3">
                            ${icon}
                            <div>
                                <h3 class="font-bold ${titleColor}">${title}</h3>
                                <p class="mt-1 text-sm ${textColor}">${description}</p>
                            </div>
                        </div>
                    </div>
                `);
            }
        }

        // Title
        document.getElementById(this.elements.eventTitle).textContent = e.title;

        // Date
        this.renderDate(e);

        // Time — for multi-day, use selected performance's times
        var displayStartTime = e.start_time;
        var displayDoorsTime = e.doors_time;
        if (e.duration_mode === 'multi_day' && e.performances && e.performances.length > 0) {
            var selPerf = e.performances.find(p => p.id === e.selectedPerformanceId) || e.performances[0];
            displayStartTime = selPerf.start_time || null;
            displayDoorsTime = selPerf.door_time || null;
        }
        document.getElementById(this.elements.eventTime).innerHTML = displayStartTime
            ? '<span class="text-muted">Start:</span> ' + displayStartTime : '';
        document.getElementById(this.elements.eventDoors).innerHTML = displayDoorsTime
            ? '<span class="text-muted">Acces:</span> ' + displayDoorsTime : '';

        // Venue (with city)
        var venueName = e.venue?.name || e.location || 'Locație TBA';
        var venueCity = e.venue?.city || e.city || '';
        document.getElementById(this.elements.venueName).textContent = venueCity ? venueName + ', ' + venueCity : venueName;
        document.getElementById(this.elements.venueAddress).textContent = e.venue?.address || '';

        var venueLink = document.getElementById(this.elements.venueLink);
        if (venueLink) {
            if (e.venue?.slug) {
                venueLink.href = '/locatie/' + e.venue.slug;
                venueLink.style.display = '';
            } else {
                // Hide venue link if venue doesn't exist in marketplace (no slug)
                venueLink.style.display = 'none';
            }
        }

        // Stats (views only - interest button text is handled separately)
        document.getElementById(this.elements.eventViews).textContent = this.formatCount(e.views_count || 0) + ' vizualizări';

        // Performance selector (multi-day events) — insert BEFORE description
        this.renderPerformanceList();

        // Description
        var descEl = document.getElementById(this.elements.eventDescription);
        descEl.innerHTML = this.formatDescription(e.description || e.content);
        this.initDescriptionToggle(descEl);

        // Artist section
        if (e.artist || e.artists?.length) {
            var artists = e.artists && e.artists.length ? e.artists : (e.artist ? [e.artist] : []);
            this.renderArtists(artists);
        }

        // Venue section
        this.renderVenue(e.venue || { name: e.location || 'Locație TBA' });

        // Seating layout
        this.seatingLayout = e.seating_layout || null;
        if (this.seatingLayout) {
            console.log('[EventPage] Event has seating layout:', this.seatingLayout.name);
        }

        // Filter out past performances and auto-select first upcoming
        if (e.performances && e.performances.length > 0) {
            const now = new Date();
            // Keep only future performances (add 2h buffer for ongoing shows)
            this.event.performances = e.performances.filter(function(p) {
                const endTime = p.end_time || p.start_time || '23:59';
                const perfEnd = new Date(p.date + 'T' + endTime);
                return perfEnd >= now;
            });
            // If all past, show the last one as reference
            if (this.event.performances.length === 0) {
                this.event.performances = [e.performances[e.performances.length - 1]];
            }
            // Use API-provided selection (child event) or default to first
            if (this.event.selectedPerformanceId && this.event.performances.find(p => p.id === this.event.selectedPerformanceId)) {
                // Already set from API (child event accessing parent performances)
            } else {
                this.event.selectedPerformanceId = this.event.performances[0].id;
            }
        }

        // Ticket types (skip for ended events)
        if (!this.eventEnded) {
            this.ticketTypes = e.ticket_types || this.getDefaultTicketTypes();
            this.renderTicketTypes();
        }

        // Ticket terms
        if (e.ticket_terms) {
            this.renderTicketTerms(e.ticket_terms);
        }

        // Tour/Grouping events
        if (e.tour_events && e.tour_events.length > 0) {
            this.renderTourEvents(e.tour_events, e.tour_name, e.tour_type);
        }

        // Related events — lazy-load when section scrolls into view
        if (!this.eventEnded) {
            this.setupLazyRelatedEvents();
        }
    },

    /**
     * Render event badges
     */
    renderBadges(e) {
        const badgesHtml = [];

        // Status badges (priority order)
        if (e.is_cancelled) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-red-600 text-white text-xs font-bold rounded-lg uppercase">ANULAT</span>');
        } else if (e.is_postponed) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-lg uppercase">AMÂNAT</span>');
        } else if (this.eventEnded) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-gray-600 text-white text-xs font-bold rounded-lg uppercase">ÎNCHEIAT</span>');
        } else if (e.is_sold_out) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-gray-600 text-white text-xs font-bold rounded-lg uppercase">SOLD OUT</span>');
        }

        // Category badge
        if (e.category) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-accent text-slate-800 text-xs font-bold rounded-lg uppercase">' + e.category + '</span>');
        }

        // Popular badge (only if not cancelled/postponed/sold out)
        if (e.is_popular && !e.is_cancelled && !e.is_postponed && !e.is_sold_out) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">Popular</span>');
        }

        document.getElementById(this.elements.eventBadges).innerHTML = badgesHtml.join('');
    },

    /**
     * Render status alerts (cancelled, postponed, sold out)
     */
    renderStatusAlerts(e) {
        // Remove any existing status alert
        const existingAlert = document.getElementById('event-status-alert');
        if (existingAlert) {
            existingAlert.remove();
        }

        let alertHtml = '';

        if (e.is_cancelled) {
            alertHtml = `
                <div id="event-status-alert" class="p-4 mb-6 border-l-4 border-red-500 bg-red-50 rounded-r-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="font-bold text-red-800">Eveniment anulat</h3>
                            ${e.cancel_reason ? '<div class="mt-1 text-sm text-red-700 prose prose-sm prose-red max-w-none">' + e.cancel_reason + '</div>' : ''}
                            <p class="mt-2 text-sm text-red-600">Biletele nu mai sunt disponibile pentru acest eveniment.</p>
                        </div>
                    </div>
                </div>
            `;
        } else if (e.is_postponed) {
            const months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
            let newDateText = '';
            if (e.postponed_date) {
                const newDate = new Date(e.postponed_date);
                newDateText = newDate.getDate() + ' ' + months[newDate.getMonth()] + ' ' + newDate.getFullYear();
                if (e.postponed_start_time) {
                    newDateText += ' la ora ' + e.postponed_start_time;
                }
            }

            alertHtml = `
                <div id="event-status-alert" class="p-4 mb-6 border-l-4 border-orange-500 bg-orange-50 rounded-r-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="font-bold text-orange-800">Eveniment amânat</h3>
                            ${e.postponed_reason ? '<div class="mt-1 text-sm text-orange-700 prose prose-sm prose-orange max-w-none">' + e.postponed_reason + '</div>' : ''}
                            ${newDateText ? '<p class="mt-2 text-sm font-semibold text-orange-800">Noua dată: ' + newDateText + '</p>' : '<p class="mt-2 text-sm text-orange-600">Noua dată va fi anunțată în curând.</p>'}
                        </div>
                    </div>
                </div>
            `;
        } else if (e.is_sold_out) {
            alertHtml = `
                <div id="event-status-alert" class="p-4 mb-6 border-l-4 border-gray-500 bg-gray-50 rounded-r-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="font-bold text-gray-800">Sold Out</h3>
                            <p class="mt-1 text-sm text-gray-600">Toate biletele pentru acest eveniment au fost vândute.</p>
                        </div>
                    </div>
                </div>
            `;
        }

        if (alertHtml) {
            // Insert alert before the title
            const titleEl = document.getElementById(this.elements.eventTitle);
            if (titleEl) {
                titleEl.insertAdjacentHTML('beforebegin', alertHtml);
            }
        }
    },

    /**
     * Render event date based on duration_mode
     */
    renderDate(e) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const weekdays = ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'];
        const durationMode = e.duration_mode || 'single_day';

        if (durationMode === 'range' && e.range_start_date && e.range_end_date) {
            // Festival/Range mode - show start and end dates
            const startDate = new Date(e.range_start_date);
            const endDate = new Date(e.range_end_date);

            document.getElementById(this.elements.eventDay).textContent = startDate.getDate() + '-' + endDate.getDate();
            document.getElementById(this.elements.eventMonth).textContent = months[startDate.getMonth()];
            document.getElementById(this.elements.eventWeekday).textContent = 'Festival';

            // Full date range text
            let dateFullText = startDate.getDate() + ' ' + months[startDate.getMonth()];
            if (startDate.getMonth() !== endDate.getMonth()) {
                dateFullText += ' - ' + endDate.getDate() + ' ' + months[endDate.getMonth()] + ' ' + endDate.getFullYear();
            } else {
                dateFullText += ' - ' + endDate.getDate() + ' ' + months[endDate.getMonth()] + ' ' + endDate.getFullYear();
            }
            document.getElementById(this.elements.eventDateFull).textContent = dateFullText;

        } else if (durationMode === 'multi_day' && e.multi_slots && e.multi_slots.length > 0) {
            // Multi-day mode - show dates from slots
            // Filter out slots that have no active performance (or are in the past with no performance)
            const now = new Date();
            const activeSlots = e.multi_slots.filter(function(s) {
                // If we have performances, only keep slots that match an active performance
                if (e.performances && e.performances.length > 0) {
                    return e.performances.some(function(p) {
                        return p.date === s.date && p.start_time === s.start_time;
                    });
                }
                // Fallback: keep only slots in the future
                return new Date(s.date + 'T' + (s.start_time || '00:00')) >= now;
            });

            // If all slots are filtered out, fall back to all multi_slots
            const slotsToShow = activeSlots.length > 0 ? activeSlots : e.multi_slots;
            const firstSlot = slotsToShow[0];

            // Show the selected performance's date, or nearest upcoming active slot
            let headerSlot = null;
            if (e.selectedPerformanceId && e.performances) {
                const selPerf = e.performances.find(p => p.id === e.selectedPerformanceId);
                if (selPerf) {
                    headerSlot = slotsToShow.find(s => s.date === selPerf.date && s.start_time === selPerf.start_time);
                }
            }
            if (!headerSlot) {
                headerSlot = slotsToShow.find(function(s) {
                    return new Date(s.date + 'T' + (s.start_time || '00:00')) >= now;
                }) || firstSlot;
            }
            const headerDate = new Date(headerSlot.date);

            document.getElementById(this.elements.eventDay).textContent = headerDate.getDate();
            document.getElementById(this.elements.eventMonth).textContent = months[headerDate.getMonth()];
            document.getElementById(this.elements.eventWeekday).textContent = weekdays[headerDate.getDay()];

            const remainingCount = slotsToShow.length - 1;

            if (remainingCount <= 0) {
                document.getElementById(this.elements.eventDateFull).textContent =
                    headerDate.getDate() + ' ' + months[headerDate.getMonth()] + ' ' + headerDate.getFullYear();
            } else {
                document.getElementById(this.elements.eventDateFull).textContent =
                    headerDate.getDate() + ' ' + months[headerDate.getMonth()] + ' ' + headerDate.getFullYear() +
                    ' (+' + remainingCount + ' ' + (remainingCount === 1 ? 'altă dată' : 'alte date') + ')';
            }

        } else {
            // Single day mode (default)
            const eventDate = new Date(e.start_date || e.date);
            document.getElementById(this.elements.eventDay).textContent = eventDate.getDate();
            document.getElementById(this.elements.eventMonth).textContent = months[eventDate.getMonth()];
            document.getElementById(this.elements.eventWeekday).textContent = weekdays[eventDate.getDay()];
            document.getElementById(this.elements.eventDateFull).textContent =
                eventDate.getDate() + ' ' + months[eventDate.getMonth()] + ' ' + eventDate.getFullYear();
        }
    },

    /**
     * Collapse long descriptions with "Vezi mai mult" toggle
     */
    initDescriptionToggle(descEl) {
        requestAnimationFrame(function() {
            if (descEl.scrollHeight <= 300) return;

            descEl.classList.add('is-collapsed');
            var btn = document.getElementById('desc-toggle');
            var textEl = document.getElementById('desc-toggle-text');
            btn.style.display = 'flex';

            btn.addEventListener('click', function() {
                var collapsed = descEl.classList.contains('is-collapsed');
                if (collapsed) {
                    descEl.style.maxHeight = descEl.scrollHeight + 'px';
                    descEl.classList.remove('is-collapsed');
                    descEl.classList.add('is-expanded');
                    btn.classList.add('is-expanded');
                    textEl.textContent = 'Vezi mai puțin';
                } else {
                    descEl.classList.remove('is-expanded');
                    descEl.classList.add('is-collapsed');
                    descEl.style.maxHeight = '';
                    btn.classList.remove('is-expanded');
                    textEl.textContent = 'Vezi mai mult';
                    descEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
    },

    /**
     * Format description (handle HTML vs plain text)
     */
    formatDescription(desc) {
        if (!desc) return '<p class="text-muted">Descriere indisponibilă</p>';

        var hasHtml = /<[a-z][\s\S]*>/i.test(desc);

        if (hasHtml) {
            return '<div class="space-y-2 prose prose-slate prose-p:text-muted prose-p:leading-relaxed prose-headings:text-secondary prose-strong:text-secondary prose-a:text-primary prose-li:text-muted max-w-none">' + desc + '</div>';
        }

        var paragraphs = desc.split(/\n\n+/).filter(function(p) { return p.trim(); });
        if (paragraphs.length === 1) {
            paragraphs = desc.split(/\n/).filter(function(p) { return p.trim(); });
        }

        return paragraphs.map(function(p) {
            return '<p class="mb-4 leading-relaxed text-muted">' + p.trim() + '</p>';
        }).join('');
    },

    /**
     * Render all artists in the artist section
     */
    renderArtists(artists) {
        if (!artists || !artists.length) return;
        document.getElementById(this.elements.artistSection).style.display = 'block';

        var socialIcons = {
            facebook: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            instagram: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
            tiktok: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
            youtube: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
            spotify: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
            website: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>'
        };
        var socialColors = {
            facebook: 'hover:text-[#1877F2]',
            instagram: 'hover:text-[#E4405F]',
            tiktok: 'hover:text-black',
            youtube: 'hover:text-[#FF0000]',
            spotify: 'hover:text-[#1DB954]',
            website: 'hover:text-primary'
        };

        var allHtml = '';
        for (var i = 0; i < artists.length; i++) {
            var artist = artists[i];
            if (!artist) continue;

            var artistImage = artist.image_url || artist.image || '/assets/images/default-artist.png';
            var artistLink = artist.slug ? '/artist/' + artist.slug : '#';
            var artistDescription = artist.bio || artist.description || '';
            var isHeadliner = artist.is_headliner === true;
            var isCoHeadliner = artist.is_co_headliner === true;

            // Build status badge HTML
            var statusBadgeHtml = '';
            if (isHeadliner) {
                statusBadgeHtml = '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold text-white rounded-full bg-gradient-to-r from-amber-500 to-amber-600 shadow-sm">' +
                    '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' +
                    'HEADLINER</span>';
            } else if (isCoHeadliner) {
                statusBadgeHtml = '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-full bg-secondary/10 text-secondary">' +
                    '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' +
                    'CO-HEADLINER</span>';
            }

            var socialLinksHtml = '';
            var socialLinks = artist.social_links || {};
            if (Object.keys(socialLinks).length > 0) {
                socialLinksHtml = '<div class="flex items-center gap-3">';
                for (var platform in socialLinks) {
                    if (socialLinks.hasOwnProperty(platform) && socialIcons[platform]) {
                        socialLinksHtml += '<a href="' + socialLinks[platform] + '" target="_blank" rel="noopener noreferrer" class="p-2 text-muted transition-colors rounded-full bg-surface ' + (socialColors[platform] || 'hover:text-primary') + '" title="' + platform.charAt(0).toUpperCase() + platform.slice(1) + '">' + socialIcons[platform] + '</a>';
                    }
                }
                socialLinksHtml += '</div>';
            }

            // Headliners get larger display
            var imageColClass = isHeadliner ? 'md:w-2/5' : 'md:w-1/4';
            var contentColClass = isHeadliner ? 'md:w-3/5' : 'md:w-3/4';
            var nameClass = isHeadliner ? 'text-2xl' : 'text-xl';
            var containerClass = isHeadliner ? 'p-4 -m-4 rounded-2xl bg-gradient-to-r from-amber-50 to-transparent border border-amber-100' : '';

            allHtml += '<div class="flex flex-col gap-6 md:flex-row ' + containerClass + (i > 0 ? ' pt-6 mt-6' + (isHeadliner ? '' : ' border-t border-border') : '') + '">' +
                '<div class="' + imageColClass + '">' +
                    '<a href="' + artistLink + '" class="relative block">' +
                        '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full transition-transform aspect-square rounded-2xl hover:scale-105 mobile:h-40" loading="lazy" width="300" height="300">' +
                        (isHeadliner ? '<div class="absolute px-2 py-1 text-xs font-bold text-white rounded-lg shadow-lg top-3 left-3 bg-gradient-to-r from-amber-500 to-amber-600">★ HEADLINER</div>' : '') +
                    '</a>' +
                '</div>' +
                '<div class="' + contentColClass + '">' +
                    '<div class="flex flex-wrap items-center gap-3 mb-2 mobile:justify-between">' +
                        '<a href="' + artistLink + '" class="' + nameClass + ' font-bold text-secondary hover:text-primary">' + artist.name + '</a>' +
                        statusBadgeHtml +
                        (artist.verified ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>' : '') +
                    '</div>' +
                    (artistDescription ? '<p class="mb-2 leading-relaxed text-muted">' + artistDescription + '</p>' : '<p class="mb-4 leading-relaxed text-muted">Detalii despre artist vor fi disponibile în curând.</p>') +
                    '<div class="flex items-center mobile:flex-col gap-4">' +
                        '<a href="' + artistLink + '" class="inline-flex mobile:flex items-center gap-2 font-semibold text-primary border border-primary rounded-md py-2 px-6 mobile:justify-center hover:bg-primary hover:text-white transition-all ease-in-out duration-200">' +
                            'Vezi profilul artistului' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>' +
                        '</a>' +
                        socialLinksHtml +
                    '</div>' +
                '</div>' +
            '</div>';
        }

        document.getElementById(this.elements.artistContent).innerHTML = allHtml;
    },

    /**
     * Render venue section
     */
    renderVenue(venue) {
        var googleMapsUrl = venue.google_maps_url || null;
        if (!googleMapsUrl && venue.latitude && venue.longitude) {
            googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + venue.latitude + ',' + venue.longitude;
        }

        var venueAddress = venue.address || '';
        if (venue.city) {
            venueAddress = venueAddress ? venueAddress + ', ' + venue.city : venue.city;
        }
        if (venue.state && venue.state !== venue.city) {
            venueAddress = venueAddress ? venueAddress + ', ' + venue.state : venue.state;
        }

        var html = '<div class="flex flex-col md:items-center gap-6 md:flex-row px-8 mobile:px-0">' +
            '<div class="md:w-1/4">' +
                '<img src="' + (venue.image || '/assets/images/default-venue.png') + '" alt="' + venue.name + '" class="object-cover w-full h-32 mb-4 rounded-2xl" loading="lazy" width="300" height="128">' +
            '</div>' +
            '<div class="md:w-3/4">' +
                '<div class="mb-2 text-xl font-bold text-secondary">' + venue.name + '</div>' +
                '<div class="flex items-center gap-3 mb-2">' +
                    '<p class="text-muted">' + venueAddress + '</p>' +
                    (googleMapsUrl
                        ? '<a href="' + googleMapsUrl + '" target="_blank" class="inline-flex items-center gap-2 font-semibold text-secondary border border-secondary text-xs rounded-md py-2 px-6 hover:bg-secondary hover:text-white transition-all ease-in-out duration-200">' +
                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                            'Google Maps' +
                          '</a>'
                        : '') +
                '</div>' +
                '<div class="leading-relaxed text-muted text-sm line-clamp-2">' + (venue.description || '') + '</div>';

        if (venue.amenities && venue.amenities.length) {
            html += '<div class="mt-4 mb-6 space-y-3">';
            venue.amenities.forEach(function(a) {
                html += '<div class="flex items-center gap-3">' +
                    '<div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">' +
                        '<svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                    '</div>' +
                    '<span class="text-sm text-secondary">' + a + '</span>' +
                '</div>';
            });
            html += '</div>';
        }

        html += '</div></div>';

        document.getElementById(this.elements.venueContent).innerHTML = html;
    },

    /**
     * Get default ticket types for demo
     */
    getDefaultTicketTypes() {
        return [
            { id: 'early', name: 'Early Bird', price: 65, original_price: 80, available: 23, description: 'Acces general o zi' },
            { id: 'standard', name: 'Standard', price: 80, available: 245, description: 'Acces general o zi' },
            { id: 'vip', name: 'VIP', price: 150, available: 12, description: 'Acces ambele zile + Loc rezervat' },
            { id: 'premium', name: 'Premium', price: 250, available: 5, description: 'Toate beneficiile VIP + Backstage' }
        ];
    },

    /**
     * Render ticket type cards
     */
    /**
     * Get the selected performance object
     */
    getSelectedPerformance() {
        if (!this.event.selectedPerformanceId || !this.event.performances) return null;
        return this.event.performances.find(p => p.id === this.event.selectedPerformanceId) || null;
    },

    /**
     * Get the effective price for a ticket type considering performance overrides
     */
    getEffectivePrice(tt) {
        const perf = this.getSelectedPerformance();
        if (perf && perf.ticket_overrides) {
            const override = perf.ticket_overrides[tt.id] || perf.ticket_overrides[String(tt.id)];
            if (override && override.price !== null && override.price !== undefined) {
                return override.price;
            }
        }
        return tt.price ?? 0;
    },

    /**
     * Render performance list above description for multi-day events
     */
    renderPerformanceList() {
        const performances = this.event.performances || [];
        console.log('[EventPage] renderPerformanceList called, performances count:', performances.length, 'duration_mode:', this.event.duration_mode);
        if (performances.length <= 1) return;

        const descEl = document.getElementById(this.elements.eventDescription);
        if (!descEl) return;

        // Remove previous instance if re-rendering
        const existing = document.getElementById('perf-list-section');
        if (existing) existing.remove();

        const self = this;
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const days = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];

        const section = document.createElement('div');
        section.id = 'perf-list-section';
        section.className = 'p-4 mb-6';
        section.style.cssText = 'background:#1a1f35;';
        section.innerHTML =
            '<h3 style="font-size:15px;font-weight:700;margin-bottom:10px;color:#e2e8f0;">Acest eveniment are mai multe reprezentații. Alege-o pe cea care ți se potrivește cel mai bine.</h3>' +
            '<div style="display:grid;gap:8px;">' +
            performances.map(function(p) {
                const d = new Date(p.date + 'T' + (p.start_time || '00:00'));
                const isActive = p.id === self.event.selectedPerformanceId;
                const dayName = days[d.getDay()];
                const dayNum = d.getDate();
                const monthName = months[d.getMonth()];
                const year = d.getFullYear();
                const time = p.start_time || '';
                const endTime = p.end_time || '';
                const timeRange = time + (endTime ? ' – ' + endTime : '');

                // Calculate min price for this performance
                var minPrice = null;
                var ticketTypes = self.ticketTypes || [];
                ticketTypes.forEach(function(tt) {
                    var effectivePrice = tt.price;
                    if (p.ticket_overrides && p.ticket_overrides[tt.id]) {
                        effectivePrice = p.ticket_overrides[tt.id].price;
                    }
                    if (effectivePrice > 0 && (minPrice === null || effectivePrice < minPrice)) {
                        minPrice = effectivePrice;
                    }
                });
                var priceHtml = minPrice !== null
                    ? '<div style="font-size:12px;font-weight:600;color:#a5b4fc;margin-top:2px;">de la ' + minPrice.toFixed(0) + ' lei</div>'
                    : '';

                return '<button type="button" class="perf-list-btn" data-perf-id="' + p.id + '" ' +
                    'style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;border:1px solid ' +
                    (isActive ? '#6366f1' : 'rgba(255,255,255,0.12)') + ';background:' +
                    (isActive ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.04)') +
                    ';text-align:left;width:100%;cursor:pointer;transition:all .2s;">' +
                    '<div style="flex-shrink:0;width:48px;height:48px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:' +
                    (isActive ? '#6366f1' : 'rgba(255,255,255,0.08)') + ';color:' + (isActive ? '#fff' : 'rgba(255,255,255,0.5)') + ';">' +
                        '<span style="font-size:18px;font-weight:700;line-height:1;">' + dayNum + '</span>' +
                        '<span style="font-size:10px;text-transform:uppercase;line-height:1;margin-top:2px;">' + monthName + '</span>' +
                    '</div>' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div style="font-size:14px;font-weight:600;color:' + (isActive ? '#e2e8f0' : 'rgba(255,255,255,0.7)') + ';">' + dayName + ', ' + dayNum + ' ' + monthName + ' ' + year + '</div>' +
                        '<div style="font-size:12px;color:rgba(255,255,255,0.45);">' + timeRange + '</div>' +
                    '</div>' +
                    (minPrice !== null ? '<div style="text-align:right;flex-shrink:0;"><div style="font-size:14px;font-weight:700;color:#a5b4fc;">de la ' + minPrice.toFixed(0) + ' lei</div></div>' : '') +
                    (isActive ? '<svg width="20" height="20" viewBox="0 0 20 20" fill="#818cf8" style="flex-shrink:0;"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : '') +
                '</button>';
            }).join('') +
            '</div>';

        // Insert BEFORE description
        descEl.parentNode.insertBefore(section, descEl);

        // Bind click handlers
        section.querySelectorAll('.perf-list-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.event.selectedPerformanceId = parseInt(btn.dataset.perfId);

                // Update header date + Start/Acces to show selected performance
                const perf = self.getSelectedPerformance();
                if (perf) {
                    const pd = new Date(perf.date + 'T' + (perf.start_time || '00:00'));
                    const m = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const w = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];
                    document.getElementById(self.elements.eventDay).textContent = pd.getDate();
                    document.getElementById(self.elements.eventMonth).textContent = m[pd.getMonth()];
                    document.getElementById(self.elements.eventWeekday).textContent = w[pd.getDay()];
                    document.getElementById(self.elements.eventDateFull).textContent =
                        pd.getDate() + ' ' + m[pd.getMonth()] + ' ' + pd.getFullYear();

                    // Update Start / Acces
                    document.getElementById(self.elements.eventTime).innerHTML = perf.start_time
                        ? '<span class="text-muted">Start:</span> ' + perf.start_time : '';
                    document.getElementById(self.elements.eventDoors).innerHTML = perf.door_time
                        ? '<span class="text-muted">Acces:</span> ' + perf.door_time : '';
                }

                // Re-render performance list (to update active state)
                self.renderPerformanceList();
                // Re-render ticket types (prices may change)
                self.renderTicketTypes();
            });
        });
    },

    /**
     * Render selected performance info bar in ticket drawer (replaces pills)
     */
    renderPerformanceSelector() {
        const performances = this.event.performances || [];
        if (performances.length <= 1) return '';

        const perf = this.getSelectedPerformance();
        if (!perf) return '';

        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const days = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];
        const d = new Date(perf.date + 'T' + (perf.start_time || '00:00'));
        const dayName = days[d.getDay()];
        const dayNum = d.getDate();
        const monthName = months[d.getMonth()];
        const year = d.getFullYear();
        const time = perf.start_time || '';
        const endTime = perf.end_time || '';
        const timeRange = time + (endTime ? ' – ' + endTime : '');

        return '<div class="flex items-center justify-between gap-2.5 px-3.5 py-2.5 mb-3 rounded-lg bg-primary/10 border border-primary/20">' +
            '<div class="flex items-center gap-2.5 min-w-0">' +
                '<svg class="w-4 h-4 shrink-0 text-slate-800" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>' +
                '<div class="min-w-0">' +
                    '<span class="text-sm font-semibold text-primary">' + dayName + ', ' + dayNum + ' ' + monthName + ' ' + year + '</span>' +
                    '<span class="text-sm font-semibold text-slate-800 ml-2">' + timeRange + '</span>' +
                '</div>' +
            '</div>' +
            '<button type="button" onclick="EventPage.showPerformancePicker()" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white transition-colors rounded-lg shrink-0 bg-secondary hover:bg-secondary/90">' +
                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>' +
                'Schimbă' +
            '</button>' +
        '</div>';
    },

    showPerformancePicker() {
        var isMobile = window.innerWidth < 768;

        if (!isMobile) {
            // Desktop: scroll to perf-list-section
            var section = document.getElementById('perf-list-section');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Brief highlight
                section.style.outline = '2px solid #6366f1';
                section.style.outlineOffset = '4px';
                setTimeout(function() { section.style.outline = ''; section.style.outlineOffset = ''; }, 2000);
            }
            return;
        }

        // Mobile: show modal with performance list
        var self = this;
        var performances = this.event.performances || [];
        var months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var days = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];

        var listHtml = performances.map(function(p) {
            var d = new Date(p.date + 'T' + (p.start_time || '00:00'));
            var isActive = p.id === self.event.selectedPerformanceId;
            var dayName = days[d.getDay()];
            var dayNum = d.getDate();
            var monthName = months[d.getMonth()];
            var time = p.start_time || '';
            var endTime = p.end_time ? ' – ' + p.end_time : '';

            return '<button type="button" data-perf-modal-id="' + p.id + '" ' +
                'class="flex items-center gap-3 w-full px-4 py-3 text-left rounded-xl border transition-colors ' +
                (isActive ? 'border-primary bg-primary/10' : 'border-border bg-white hover:bg-gray-50') + '">' +
                '<div class="flex flex-col items-center justify-center w-12 h-12 rounded-lg shrink-0 ' +
                (isActive ? 'bg-primary text-white' : 'bg-gray-100 text-secondary') + '">' +
                    '<span class="text-lg font-bold leading-none">' + dayNum + '</span>' +
                    '<span class="text-[10px] font-semibold uppercase">' + monthName + '</span>' +
                '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-semibold text-secondary">' + dayName + ', ' + dayNum + ' ' + monthName + '</div>' +
                    '<div class="text-xs text-muted">' + time + endTime + '</div>' +
                '</div>' +
                (isActive ? '<svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : '') +
            '</button>';
        }).join('');

        // Create modal
        var modal = document.createElement('div');
        // Hide ticket drawer while picking performance
        var ticketDrawer = document.getElementById('ticketDrawer');
        var ticketBackdrop = document.getElementById('ticketDrawerBackdrop');
        if (ticketDrawer) ticketDrawer.style.visibility = 'hidden';
        if (ticketBackdrop) ticketBackdrop.style.visibility = 'hidden';

        modal.id = 'perf-picker-modal';
        modal.className = 'fixed inset-0 z-[1100] flex items-end justify-center';

        var closeAndRestore = function() {
            document.getElementById('perf-picker-modal')?.remove();
            // Restore ticket drawer and re-sync its content
            if (ticketDrawer) ticketDrawer.style.visibility = 'visible';
            if (ticketBackdrop) ticketBackdrop.style.visibility = 'visible';
            if (typeof syncDrawerContent === 'function') syncDrawerContent();
        };

        modal.innerHTML =
            '<div class="absolute inset-0 bg-black/50" onclick="document.getElementById(\'perf-picker-modal\')?.remove();var td=document.getElementById(\'ticketDrawer\');var tb=document.getElementById(\'ticketDrawerBackdrop\');if(td)td.style.visibility=\'visible\';if(tb)tb.style.visibility=\'visible\';"></div>' +
            '<div class="relative w-full max-h-[80vh] bg-white rounded-t-2xl shadow-xl overflow-hidden">' +
                '<div class="flex items-center justify-between px-5 py-4 border-b border-border">' +
                    '<h3 class="text-base font-bold text-secondary">Selectează reprezentația</h3>' +
                    '<button type="button" id="perf-picker-close-btn" class="p-1 rounded-lg text-muted hover:bg-gray-100">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                    '</button>' +
                '</div>' +
                '<div class="p-4 space-y-2 overflow-y-auto max-h-[65vh]">' + listHtml + '</div>' +
            '</div>';

        document.body.appendChild(modal);

        // Close button handler
        document.getElementById('perf-picker-close-btn').addEventListener('click', closeAndRestore);

        // Bind click handlers
        modal.querySelectorAll('[data-perf-modal-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.event.selectedPerformanceId = parseInt(btn.dataset.perfModalId);
                self.renderTicketTypes();
                // Also update perf-list-section if exists
                var perfSection = document.getElementById('perf-list-section');
                if (perfSection) {
                    perfSection.querySelectorAll('.perf-list-btn').forEach(function(b) {
                        var bId = parseInt(b.dataset.perfId);
                        var isNowActive = bId === self.event.selectedPerformanceId;
                        b.style.borderColor = isNowActive ? '#6366f1' : 'rgba(255,255,255,0.12)';
                        b.style.background = isNowActive ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.04)';
                    });
                }
                closeAndRestore();
            });
        });
    },

    renderTicketTypes() {
        const container = document.getElementById(this.elements.ticketTypes);
        var self = this;
        var targetPrice = this.event.target_price ? parseFloat(this.event.target_price) : null;

        // Check if event is cancelled, postponed, or sold out - disable all ticket purchasing
        var eventIsCancelled = this.event.is_cancelled || false;
        var eventIsPostponed = this.event.is_postponed || false;
        var eventIsSoldOut = this.event.is_sold_out || false;
        var eventDisabled = eventIsCancelled || eventIsPostponed || eventIsSoldOut;

        // Render performance selector pills for multi-day events
        var perfSelectorHtml = this.renderPerformanceSelector();

        var isGrouped = self.event.enable_ticket_groups;
        var ticketCards = this.ticketTypes.map(function(tt) {
            var ttInGroup = isGrouped && tt.ticket_group;
            if (!(tt.id in self.quantities)) self.quantities[tt.id] = 0;
            // Force all tickets as unavailable if event is disabled (cancelled/postponed/sold out)
            const isSoldOut = eventDisabled || tt.is_sold_out || tt.available <= 0;

            // Get effective price (with performance override if applicable)
            var displayPrice = self.getEffectivePrice(tt);

            // Determine discount: use target_price if available and greater than displayPrice
            // Otherwise fall back to original_price from ticket type
            var hasTargetDiscount = targetPrice && displayPrice < targetPrice;
            var hasTicketDiscount = tt.original_price && tt.original_price > tt.price;

            var hasDiscount = false;
            var discountPercent = 0;
            var crossedOutPrice = null;

            if (hasTargetDiscount) {
                // Use target_price as the reference price
                hasDiscount = true;
                crossedOutPrice = targetPrice;
                discountPercent = Math.round((1 - displayPrice / targetPrice) * 100);
            } else if (hasTicketDiscount) {
                // Fall back to original_price from ticket type (base price)
                hasDiscount = true;
                crossedOutPrice = tt.original_price;
                discountPercent = Math.round((1 - displayPrice / crossedOutPrice) * 100);
            }

            console.log('[EventPage] Rendering ticket:', tt.name,
                'displayPrice:', displayPrice.toFixed(2),
                'targetPrice:', targetPrice,
                'hasTargetDiscount:', hasTargetDiscount,
                'hasTicketDiscount:', hasTicketDiscount,
                'crossedOutPrice:', crossedOutPrice,
                'discountPercent:', discountPercent + '%');

            // Availability display - different messages for event-level vs ticket-level status
            let availabilityHtml = '';
            if (eventIsCancelled) {
                availabilityHtml = '<span class="text-xs font-semibold text-red-500">Eveniment anulat</span>';
            } else if (eventIsPostponed) {
                availabilityHtml = '<span class="text-xs font-semibold text-orange-500">Eveniment amânat</span>';
            } else if (eventIsSoldOut) {
                availabilityHtml = '<span class="text-xs font-semibold text-gray-500">Sold Out</span>';
            } else if (isSoldOut) {
                availabilityHtml = '<span class="text-xs font-semibold text-gray-400">Indisponibil</span>';
            } else if (tt.available <= 1) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">🔥 Ultimul bilet</span>';
            } else if (tt.available <= 5) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">🔥 Ultimele ' + tt.available + ' disponibile</span>';
            } else if (tt.available <= 20) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">🔥 Doar ' + tt.available + ' disponibile</span>';
            } else if (tt.available < 40) {
                availabilityHtml = '<span class="text-xs font-semibold text-success">⚡ ' + tt.available + ' disponibile</span>';
            } else {
                availabilityHtml = '<span class="text-xs font-semibold text-success"></span>';
            }

            // Calculate commission for tooltip using per-ticket commission
            var ticketComm = self.calculateTicketCommission(tt, displayPrice);
            var basePrice, commissionAmount, totalPrice;
            var commissionLabel = '';

            if (ticketComm.type === 'fixed') {
                commissionLabel = (ticketComm.fixed || 0).toFixed(2) + ' lei';
            } else if (ticketComm.type === 'both') {
                commissionLabel = ticketComm.rate + '% + ' + (ticketComm.fixed || 0).toFixed(2) + ' lei';
            } else {
                commissionLabel = ticketComm.rate + '%';
            }

            if (ticketComm.mode === 'included') {
                // Commission is included - calculate base price from effective display price
                if (ticketComm.type === 'fixed') {
                    basePrice = displayPrice - ticketComm.fixed;
                } else if (ticketComm.type === 'both') {
                    basePrice = (displayPrice - ticketComm.fixed) / (1 + ticketComm.rate / 100);
                } else {
                    basePrice = displayPrice / (1 + ticketComm.rate / 100);
                }
                commissionAmount = displayPrice - basePrice;
                totalPrice = displayPrice;
            } else {
                // Commission added on top
                basePrice = displayPrice;
                commissionAmount = ticketComm.amount;
                totalPrice = displayPrice + commissionAmount;
            }

            // Tooltip HTML - show commission as "Taxe procesare"
            var tooltipHtml = '<p class="mb-2 text-sm font-semibold">Detalii pret bilet:</p><div class="space-y-1 text-xs">';
            if (ticketComm.mode === 'included') {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/90">Pret bilet:</span><span>' + basePrice.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/90">Taxe procesare (' + commissionLabel + '):</span><span>' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + totalPrice.toFixed(2) + ' lei</span></div>';
            } else {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/90">Pret bilet:</span><span>' + displayPrice.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/90">Taxe procesare (' + commissionLabel + '):</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total la plata:</span><span class="font-semibold">' + totalPrice.toFixed(2) + ' lei</span></div>';
            }
            tooltipHtml += '</div>';

            // Card classes
            var roundedClass = ttInGroup ? '' : 'rounded-lg';
            var cardClasses = isSoldOut
                ? 'relative z-10 p-2 pl-4 border ticket-card border-gray-200 ' + roundedClass + ' bg-gray-100 cursor-default'
                : 'bg-white relative z-10 p-2 pl-4 border cursor-pointer ticket-card border-border ' + roundedClass + ' hover:z-20 group';
            var titleClasses = isSoldOut ? 'text-gray-400' : 'text-secondary';
            var priceClasses = isSoldOut ? 'text-gray-400 line-through' : 'text-slate-700';
            var descClasses = isSoldOut ? 'text-gray-400' : 'text-muted';

            // Controls HTML - different messages based on event status
            var controlsHtml = '';
            var hasSeating = tt.has_seating && self.seatingLayout;
            var currentQty = self.quantities[tt.id] || 0;
            var selectedCount = self.selectedSeats[tt.id]?.length || 0;

            if (eventIsCancelled) {
                controlsHtml = '<span class="text-sm font-medium text-red-400">Indisponibil</span>';
            } else if (eventIsPostponed) {
                controlsHtml = '<span class="text-sm font-medium text-orange-400">În așteptare</span>';
            } else if (eventIsSoldOut) {
                controlsHtml = '<span class="text-sm font-medium text-gray-500">Sold Out</span>';
            } else if (isSoldOut) {
                controlsHtml = '<span class="text-sm font-medium text-gray-400">Epuizat</span>';
            } else {
                if (currentQty === 0) {
                    controlsHtml = '<div class="flex items-center gap-2">' +
                        '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', 1)" class="flex items-center gap-2 px-3 py-2 text-xs font-semibold transition-colors rounded-lg bg-primary text-white hover:bg-primary/80" aria-label="Add to cart">' +
                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                            'Adaugă în coș' +
                        '</button>';
                } else {
                // Quantity controls (always show for available tickets)
                controlsHtml = '<div class="flex items-center gap-2">' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', -1)" class="flex items-center justify-center w-8 h-8 font-bold transition-all duration-150 ease-in-out rounded-md bg-surface hover:bg-primary border border-slate-200 hover:text-white group-hover:bg-primary/50 group-hover:text-white group-hover:w-6  group-hover:h-6  group-hover:border-none group-hover:rounded"  aria-label="Decrease quantity">' +
                        '<span class="group-hover:opacity-100 group-hover:block hidden opacity-0 transition-all duration-200 ease-in-out">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-6">' +
                                '<path fill-rule="evenodd" d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                        '</span>' +
                        '<span class="group-hover:opacity-0 group-hover:hidden block opacity-100 transition-all duration-100 ease-in-out">-</span>' +
                    '</button>' +
                    '<span id="qty-' + tt.id + '" class="w-8 font-bold text-center text-primary text-xl flex items-center gap-x-1 justify-center">' + currentQty + '<b class="text-sm text-slate-700">x</b></span>' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', 1)" class="btn-qty-add flex items-center justify-center w-8 h-8 font-bold transition-all duration-150 ease-in-out rounded-md bg-surface border border-slate-200 group-hover:text-white group-hover:w-8 group-hover:h-8 group-hover:border-none group-hover:rounded"  aria-label="Increase quantity">' +
                        '<span class="group-hover:opacity-100 group-hover:block hidden opacity-0 transition-all duration-150 ease-in-out">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">' +
                                '<path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"></path>' +
                            '</svg>' + 
                        '</span>' +
                        '<span class="group-hover:opacity-0 group-hover:hidden block opacity-100 transition-all duration-150 ease-in-out">+</span>' +
                    '</button>';
                }

                // Add "Alege locul/locurile" button for seating tickets when quantity > 0
                if (hasSeating && currentQty > 0) {
                    var btnLabel = currentQty === 1 ? 'Alege locul' : 'Alege locurile';
                    if (selectedCount > 0) {
                        btnLabel = selectedCount + ' loc' + (selectedCount > 1 ? 'uri' : '') + ' selectat' + (selectedCount > 1 ? 'e' : '');
                    }
                    controlsHtml += '<button onclick="EventPage.openSeatSelection(\'' + tt.id + '\')" class="flex items-center gap-2 px-3 py-2 ml-2 text-xs font-semibold transition-colors rounded-lg ' +
                        (selectedCount > 0 ? 'bg-green-500 text-white' : 'bg-accent text-white hover:bg-accent/80') + '" aria-label="Select seat(s)">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>' +
                        btnLabel +
                    '</button>';
                }

                controlsHtml += '</div>';
            }

            // Render perks list if available
            var perksHtml = '';
            if (self.event.enable_ticket_perks && tt.perks && tt.perks.length > 0) {
                perksHtml = '<ul class="mt-2 space-y-1">' +
                    tt.perks.map(function(perk) {
                        var perkText = typeof perk === 'string' ? perk : (perk.text || perk);
                        return '<li class="flex items-start gap-1.5 text-xs text-muted">' +
                            '<svg class="w-3.5 h-3.5 mt-0.5 text-green-500 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' +
                            '<span>' + self.escapeHtml(perkText) + '</span>' +
                        '</li>';
                    }).join('') +
                '</ul>';
            }

            return {group: tt.ticket_group || null, html: '<div class="' + cardClasses + '" data-ticket="' + tt.id + '" data-price="' + displayPrice + '">' +
                '<div class="flex items-center justify-between align-stretch">' +
                    '<div class="relative tooltip-trigger">' +
                        '<h3 class="flex items-center font-bold gap-x-2 ' + titleClasses + ' cursor-help border-muted leading-5">' + tt.name +
                            (hasDiscount && !isSoldOut ? '<span class="discount-badge text-white text-[10px] font-bold py-1 px-2 rounded-full">-' + discountPercent + '%</span>' : '') +
                        '</h3>' +
                        '<p class="text-sm ' + descClasses + '">' + (tt.description || '') + '</p>' +
                        perksHtml +
                        availabilityHtml +
                        (isSoldOut ? '' : '<div class="absolute left-0 z-10 w-64 p-4 mt-2 text-white shadow-xl tooltip top-full bg-secondary rounded-xl">' + tooltipHtml + '</div>') +
                    '</div>' +
                    '<div class="text-right relative min-w-[130px] flex flex-col justify-between">' +
                        '<div class="">' +
                            (hasDiscount && !isSoldOut ? '<span class="bg-slate-700 p-1 px-3 rounded-md absolute -right-4 -top-6 line-through font-bold text-xs text-white">' + crossedOutPrice.toFixed(0) + ' lei</span>' : '') +
                            '<span class="block text-xl font-bold ' + priceClasses + '">' + displayPrice.toFixed(2) + ' lei</span>' +
                        '</div>' +
                        controlsHtml +
                    '</div>' +
                '</div>' +
            '</div>'};
        });

        // Group ticket types if enabled
        var ticketCardsHtml;
        if (self.event.enable_ticket_groups) {
            var groupOrder = [];
            var groups = {};
            var ungrouped = [];
            ticketCards.forEach(function(card) {
                if (card.group) {
                    if (!groups[card.group]) {
                        groups[card.group] = [];
                        groupOrder.push(card.group);
                    }
                    groups[card.group].push(card.html);
                } else {
                    ungrouped.push(card.html);
                }
            });

            ticketCardsHtml = '';
            groupOrder.forEach(function(gName, gIdx) {
                var groupCards = groups[gName];
                var groupId = 'ticket-group-' + gIdx;

                ticketCardsHtml += '<div class="mb-4 border rounded-2xl border-border">' +
                    '<button type="button" onclick="document.getElementById(\'' + groupId + '\').classList.toggle(\'hidden\');this.querySelector(\'.chevron-icon\').classList.toggle(\'rotate-180\')" ' +
                        'class="flex items-center justify-between w-full px-5 py-3 text-left transition-colors bg-primary hover:bg-primary/80 rounded-t-2xl">' +
                        '<span class="text-sm font-bold text-white">' + self.escapeHtml(gName) + ' <span class="text-xs font-normal text-white/80">(' + groupCards.length + ')</span></span>' +
                        '<svg class="w-5 h-5 transition-transform chevron-icon text-white/80" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
                    '</button>' +
                    '<div id="' + groupId + '" class="ticket-group-content">' +
                        groupCards.join('') +
                    '</div>' +
                '</div>';
            });

            if (ungrouped.length > 0) {
                ticketCardsHtml += ungrouped.join('');
            }
        } else {
            ticketCardsHtml = ticketCards.map(function(c) { return c.html; }).join('');
        }

        container.innerHTML = perfSelectorHtml + ticketCardsHtml;

        // Bind performance pill click handlers
        container.querySelectorAll('.perf-pill').forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.event.selectedPerformanceId = parseInt(btn.dataset.perfId);
                self.renderTicketTypes(); // Re-render with new prices
            });
        });
    },

    /**
     * Update ticket quantity
     */
    updateQuantity(ticketId, delta) {
        const tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
        if (!tt) return;

        const currentQty = this.quantities[ticketId] || 0;
        const multiplier = tt.multiplier || 1;
        // Apply multiplier: delta is +1 or -1, actual step = multiplier
        const step = delta * multiplier;
        let newQty = currentQty + step;
        const minPerOrder = tt.min_per_order || 1;
        const maxPerOrder = Math.min(tt.max_per_order || 10, tt.available);

        // Handle min/max per order limits
        if (delta > 0) {
            // Increasing - check max limit
            if (newQty > maxPerOrder) {
                if (currentQty < maxPerOrder) {
                    newQty = maxPerOrder; // Clamp to max
                } else {
                    if (typeof AmbiletNotifications !== 'undefined') {
                        AmbiletNotifications.warning('Maxim ' + maxPerOrder + ' bilete de acest tip per comandă');
                    }
                    return;
                }
            }
            // If going from 0, jump to at least min_per_order (but respect multiplier)
            if (currentQty === 0 && newQty < minPerOrder) {
                newQty = Math.max(minPerOrder, multiplier);
            }
        } else if (delta < 0) {
            // Decreasing - if would go below min but not to 0, drop to 0
            if (newQty > 0 && newQty < minPerOrder) {
                newQty = 0; // Allow removing completely
            }
            if (newQty < 0) {
                newQty = 0;
            }
        }

        // Final check for available stock
        if (newQty > tt.available) {
            newQty = tt.available;
        }

        if (newQty !== currentQty) {
            this.quantities[ticketId] = newQty;

            // If transitioning to/from 0, re-render to swap between "Adaugă" button and qty controls
            if (currentQty === 0 || newQty === 0) {
                this.renderTicketTypes();
            } else {
                const qtyEl = document.getElementById('qty-' + ticketId);
                if (qtyEl) qtyEl.innerHTML = newQty + '<b class="text-sm text-slate-700">x</b>';
            }

            const card = document.querySelector('[data-ticket="' + ticketId + '"]');
            if (card) card.classList.toggle('selected', newQty > 0);

            this.updateCart();
        }
    },

    /**
     * Update cart summary
     */
    updateCart() {
        const totalTickets = Object.values(this.quantities).reduce(function(a, b) { return a + b; }, 0);
        let baseSubtotal = 0;  // Subtotal without commission
        let totalCommission = 0;  // Total commission amount
        var self = this;
        var ticketBreakdown = [];  // For showing individual ticket lines

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            if (qty <= 0) continue;

            var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
            if (tt) {
                var ticketBasePrice = self.getEffectivePrice(tt);
                // Calculate per-ticket commission
                var ticketComm = self.calculateTicketCommission(tt, ticketBasePrice);
                var ticketCommission = 0;

                // Only add commission if it's "added_on_top" mode
                if (ticketComm.mode === 'added_on_top') {
                    ticketCommission = ticketComm.amount;
                }

                baseSubtotal += qty * ticketBasePrice;
                totalCommission += qty * ticketCommission;

                ticketBreakdown.push({
                    name: tt.name,
                    qty: qty,
                    basePrice: ticketBasePrice,
                    lineTotal: qty * ticketBasePrice,
                    commission: ticketComm
                });
            }
        }

        // Calculate final total (subtotal = base price + commission if on top)
        const subtotalWithCommission = baseSubtotal + totalCommission;
        const total = subtotalWithCommission;  // No other taxes
        const points = Math.floor(total / 10);

        this.updateHeaderCart();

        const cartSummary = document.getElementById(this.elements.cartSummary);
        const emptyCart = document.getElementById(this.elements.emptyCart);

        if (totalTickets > 0) {
            cartSummary.classList.remove('hidden');
            emptyCart.classList.add('hidden');

            // Show total with commission as Subtotal
            document.getElementById(this.elements.subtotal).textContent = subtotalWithCommission.toFixed(2) + ' lei';

            // Render breakdown in taxes container
            var taxesContainer = document.getElementById(this.elements.taxesContainer);
            if (taxesContainer) {
                var breakdownHtml = '';

                // Show each ticket type with base price
                ticketBreakdown.forEach(function(item) {
                    breakdownHtml += '<div class="flex justify-between text-sm">' +
                        '<span class="text-muted">' + item.qty + 'x ' + item.name + '</span>' +
                        '<span class="font-medium">' + item.lineTotal.toFixed(2) + ' lei</span>' +
                    '</div>';
                });

                // Show commission as "Taxe procesare" only if on top and has value
                if (totalCommission > 0) {
                    breakdownHtml += '<div class="flex justify-between text-sm pt-2 mt-2 border-t border-border">' +
                        '<span class="text-muted">Taxe procesare</span>' +
                        '<span class="font-medium">' + totalCommission.toFixed(2) + ' lei</span>' +
                    '</div>';
                }

                taxesContainer.innerHTML = breakdownHtml;
            }

            document.getElementById(this.elements.totalPrice).textContent = total.toFixed(2) + ' lei';

            const pointsEl = document.getElementById(this.elements.pointsEarned);
            pointsEl.innerHTML = points + ' puncte';
            pointsEl.classList.remove('points-counter');
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    pointsEl.classList.add('points-counter');
                });
            });

            // Update checkout button based on seating status
            this.updateCheckoutButton();
        } else {
            cartSummary.classList.add('hidden');
            emptyCart.classList.remove('hidden');
        }
    },

    /**
     * Check if there are seating tickets that need seat selection
     */
    getSeatingTicketsNeedingSelection() {
        var self = this;
        var needingSelection = [];

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            if (qty <= 0) continue;

            var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
            if (tt && tt.has_seating && this.seatingLayout) {
                var selectedCount = (this.selectedSeats[ticketId] || []).length;
                if (selectedCount < qty) {
                    needingSelection.push({
                        ticketType: tt,
                        required: qty,
                        selected: selectedCount
                    });
                }
            }
        }

        return needingSelection;
    },

    /**
     * Update checkout button text and icon based on state
     */
    updateCheckoutButton() {
        var btnText = document.getElementById('checkoutBtnText');
        var btnIcon = document.getElementById('checkoutBtnIcon');
        if (!btnText) return;

        var needingSelection = this.getSeatingTicketsNeedingSelection();

        if (needingSelection.length > 0) {
            // Need to select seats
            var totalNeeded = needingSelection.reduce(function(sum, item) { return sum + (item.required - item.selected); }, 0);
            btnText.textContent = totalNeeded === 1 ? 'Alege locul' : 'Alege locurile';
            if (btnIcon) {
                btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>';
            }
        } else {
            // Ready to checkout
            btnText.textContent = 'Cumpara bilete';
            if (btnIcon) {
                btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>';
            }
        }
    },

    /**
     * Handle checkout button click
     */
    handleCheckout() {
        var needingSelection = this.getSeatingTicketsNeedingSelection();

        if (needingSelection.length > 0) {
            // Open seat selection for the first ticket type that needs it
            this.openSeatSelection(needingSelection[0].ticketType.id);
        } else {
            // Proceed to cart
            this.addToCart();
        }
    },

    /**
     * Add selected tickets to cart
     */
    addToCart() {
        var addedAny = false;
        var self = this;
        // Keep event-level defaults for fallback
        var defaultCommissionRate = this.event.commission_rate || 5;
        var defaultCommissionMode = this.event.commission_mode || 'included';

        console.log('[EventPage] addToCart called');
        console.log('[EventPage] Event taxes to add:', self.event.taxes);

        // Multi-day events: require performance selection before adding to cart
        if (this.event.duration_mode === 'multi_day' && this.event.performances && this.event.performances.length > 1 && !this.event.selectedPerformanceId) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning('Te rugăm să alegi o reprezentare înainte de a adăuga bilete în coș.');
            } else {
                alert('Te rugăm să alegi o reprezentare înainte de a adăuga bilete în coș.');
            }
            // Scroll to performance list
            var perfSection = document.getElementById('perf-list-section');
            if (perfSection) perfSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            if (qty > 0) {
                var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
                if (tt) {
                    var targetPrice = self.event.target_price ? parseFloat(self.event.target_price) : null;

                    var eventData = {
                        id: self.event.id,
                        title: self.event.title,
                        slug: self.event.slug,
                        start_date: self.event.start_date || self.event.date,
                        start_time: self.event.start_time,
                        image: self.event.image,
                        venue: self.event.venue,
                        taxes: self.event.taxes || [],
                        target_price: targetPrice,
                        commission_rate: defaultCommissionRate,
                        commission_mode: defaultCommissionMode,
                        preview_token: self.previewToken || null
                    };
                    console.log('[EventPage] eventData for cart:', eventData);

                    // Store BASE price in cart (with performance override if applicable)
                    var basePrice = self.getEffectivePrice(tt);
                    var baseOriginalPrice = tt.original_price;

                    // Use target_price as original_price if applicable
                    if (targetPrice && basePrice < targetPrice) {
                        baseOriginalPrice = targetPrice;
                    }

                    // Add performance data to event data
                    var selectedPerf = self.getSelectedPerformance();
                    if (selectedPerf) {
                        eventData.performance_id = selectedPerf.id;
                        eventData.performance_date = selectedPerf.date;
                        eventData.performance_time = selectedPerf.start_time;
                        eventData.performance_label = selectedPerf.label;
                    }

                    // Include per-ticket commission settings if present
                    var ticketTypeData = {
                        id: tt.id,
                        name: tt.name,
                        price: basePrice,
                        original_price: baseOriginalPrice,
                        description: tt.description,
                        min_per_order: tt.min_per_order || 1,
                        max_per_order: tt.max_per_order || 10,
                        // Per-ticket commission (null means use event defaults)
                        commission: tt.commission || null,
                        is_refundable: tt.is_refundable || false
                    };
                    AmbiletCart.addItem(self.event.id, eventData, tt.id, ticketTypeData, qty);
                    addedAny = true;
                }
            }
        }

        if (addedAny) {
            setTimeout(function() {
                window.location.href = '/cos';
            }, 300);
        }
    },

    /**
     * Setup lazy loading for related events using Intersection Observer
     */
    setupLazyRelatedEvents() {
        var self = this;
        // Use the always-visible sentinel div placed before the hidden sections
        var sentinel = document.getElementById('related-events-sentinel');
        if (!sentinel) {
            this.loadRelatedEvents();
            return;
        }
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) {
                    observer.disconnect();
                    self.loadRelatedEvents();
                }
            }, { rootMargin: '400px' }); // Start loading 400px before visible
            observer.observe(sentinel);
        } else {
            this.loadRelatedEvents();
        }
    },

    /**
     * Load related events from the same category
     */
    async loadRelatedEvents() {
        try {
            // First, check for custom related events
            if (this.event.has_custom_related && this.event.custom_related_events?.length) {
                this.renderCustomRelatedEvents(this.event.custom_related_events);
            }

            // Then load regular related events from the same category
            const params = new URLSearchParams({ limit: 8 });
            if (this.event.category_slug) {
                params.append('category', this.event.category_slug);
            }

            const response = await AmbiletAPI.get('/events?' + params.toString());
            if (response.success && response.data?.length) {
                const currentId = this.event.id;
                const currentSlug = this.event.slug;
                // Also exclude custom related events from regular related
                const customIds = (this.event.custom_related_event_ids || []).map(id => parseInt(id));
                const filtered = response.data.filter(function(e) {
                    return e.id !== currentId && e.slug !== currentSlug && !customIds.includes(e.id);
                }).slice(0, 4);

                if (filtered.length > 0) {
                    this.renderRelatedEvents(filtered);
                }
            }
        } catch (e) {
            console.error('Failed to load related events:', e);
        }
    },

    /**
     * Render custom related events (Îți recomandăm section) - Premium style
     */
    renderCustomRelatedEvents(events) {
        if (!events || events.length === 0) return;

        const section = document.getElementById(this.elements.customRelatedSection);
        const container = document.getElementById(this.elements.customRelatedEvents);

        if (!section || !container) return;

        section.style.display = 'block';

        // Render premium cards
        var self = this;
        container.innerHTML = events.map(function(event) {
            return self.renderPremiumEventCard(event);
        }).join('');
    },

    /**
     * Render a premium event card for recommended events
     */
    renderPremiumEventCard(event) {
        var months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var weekdays = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];

        var dateStr = event.starts_at || event.date;
        var date = dateStr ? new Date(dateStr) : new Date();
        var day = date.getDate();
        var month = months[date.getMonth()];
        var weekday = weekdays[date.getDay()];
        var year = date.getFullYear();

        var image = event.image || event.image_url || '/assets/images/default-event.png';
        var title = event.name || event.title || 'Eveniment';
        var slug = event.slug || '';
        var venue = event.venue_name || (event.venue ? event.venue.name : '');
        var city = event.venue_city || (event.venue ? event.venue.city : '');
        var location = city ? (venue ? venue + ', ' + city : city) : venue;
        var category = event.category?.name || event.category || '';
        var priceFrom = event.price_from ? event.price_from : null;

        // Escape HTML helper
        function esc(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        return '<a href="/bilete/' + slug + '" class="group relative block overflow-hidden bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2">' +
            // Glow effect on hover
            '<div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 bg-gradient-to-t from-primary/20 via-transparent to-transparent pointer-events-none"></div>' +

            // Image container with overlay
            '<div class="relative h-52 overflow-hidden">' +
                '<img src="' + image + '" alt="' + esc(title) + '" class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110" loading="lazy" width="400" height="208">' +

                // Gradient overlay
                '<div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>' +

                // Category badge
                (category ? '<div class="absolute top-3 left-3"><span class="px-3 py-1 text-[10px] font-bold text-white uppercase bg-secondary/80 backdrop-blur-sm rounded-full">' + esc(category) + '</span></div>' : '') +

                // Date badge - floating style
                '<div class="absolute bottom-3 right-3">' +
                    '<div class="px-3 py-2 text-center bg-white/95 backdrop-blur-sm rounded-xl shadow-lg">' +
                        '<span class="block text-2xl font-black text-secondary leading-none">' + day + '</span>' +
                        '<span class="block text-[10px] font-bold text-primary uppercase tracking-wide">' + month + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            // Content
            '<div class="relative p-5">' +
                // Title
                '<h3 class="mb-2 text-lg font-bold leading-tight text-secondary line-clamp-2 group-hover:text-primary transition-colors">' + esc(title) + '</h3>' +

                // Location
                (location ? '<p class="flex items-center gap-2 mb-3 text-sm text-muted">' +
                    '<svg class="flex-shrink-0 w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                    '<span class="truncate">' + esc(location) + '</span>' +
                '</p>' : '') +

                // Footer with price and CTA
                '<div class="flex items-center justify-between pt-3 mt-auto border-t border-border/50">' +
                    (priceFrom ?
                        '<div>' +
                            '<span class="block text-xs text-muted">De la</span>' +
                            '<span class="text-xl font-black text-primary">' + priceFrom + ' <span class="text-sm font-semibold">lei</span></span>' +
                        '</div>' :
                        '<span class="text-sm font-medium text-muted">Vezi detalii</span>'
                    ) +
                    '<div class="flex items-center justify-center w-10 h-10 transition-all duration-300 rounded-full bg-primary/10 group-hover:bg-primary group-hover:scale-110">' +
                        '<svg class="w-5 h-5 text-primary group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    /**
     * Render ticket terms section (HTML from WYSIWYG editor)
     */
    renderTicketTerms(termsHtml) {
        var section = document.getElementById('ticket-terms-section');
        var content = document.getElementById('ticket-terms-content');
        if (!section || !content || !termsHtml) return;

        content.innerHTML = termsHtml;
        section.style.display = 'block';

        // Also populate mobile drawer version
        var drawerSection = document.getElementById('drawer-ticket-terms-section');
        var drawerContent = document.getElementById('drawer-ticket-terms-content');
        if (drawerSection && drawerContent) {
            drawerContent.innerHTML = termsHtml;
            drawerSection.style.display = 'block';
        }
    },

    /**
     * Toggle ticket terms accordion open/closed
     */
    toggleTicketTerms() {
        var content = document.getElementById('ticket-terms-content');
        var chevron = document.getElementById('ticket-terms-chevron');
        if (!content) return;

        var isHidden = content.classList.contains('hidden');
        content.classList.toggle('hidden', !isHidden);
        if (chevron) chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
    },

    /**
     * Render tour events section
     */
    renderTourEvents(tourEvents, tourName, tourType) {
        var section = document.getElementById('tour-events-section');
        var container = document.getElementById('tour-events-list');
        if (!section || !container || !tourEvents || tourEvents.length === 0) return;

        // Update section title based on grouping type
        var sectionTitle = document.getElementById('tour-section-title');
        var isTour = tourType === 'turneu';
        if (sectionTitle) {
            sectionTitle.textContent = isTour ? 'Alte date din turneu' : 'Alte date din serie';
        }

        // Update subtitle with tour name if available
        var nameDisplay = document.getElementById('tour-name-display');
        var nameFallback = document.getElementById('tour-name-fallback');
        if (tourName && nameDisplay) {
            nameDisplay.textContent = tourName;
            if (nameFallback) nameFallback.style.display = 'none';
        } else if (nameFallback) {
            nameFallback.textContent = isTour
                ? 'Evenimentul face parte dintr-un turneu. Alege și alte date.'
                : 'Evenimentul face parte dintr-o serie. Alege și alte date.';
        }

        var MONTHS_SHORT = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];
        var now = new Date();

        // Sort: upcoming first (closest date first), then past (most recent first)
        tourEvents.sort(function(a, b) {
            var aDate = a.event_date ? new Date(a.event_date) : new Date(0);
            var bDate = b.event_date ? new Date(b.event_date) : new Date(0);
            var aUpcoming = aDate >= now, bUpcoming = bDate >= now;
            if (aUpcoming && !bUpcoming) return -1;
            if (!aUpcoming && bUpcoming) return 1;
            return aUpcoming ? aDate - bDate : bDate - aDate;
        });

        var html = '';
        for (var i = 0; i < tourEvents.length; i++) {
            var te = tourEvents[i];
            var date = te.event_date ? new Date(te.event_date) : null;
            var isPast = date && date < now;
            var dayNum = date ? date.getDate() : '';
            var monthStr = date ? MONTHS_SHORT[date.getMonth()] : '';
            var yearNum = date ? date.getFullYear() : '';
            var timeStr = te.start_time ? te.start_time.substring(0, 5) : '';
            var city = te.city || '';
            var venueName = te.venue_name || '';
            var location = city ? (venueName ? venueName : city) : venueName;
            var imgSrc = te.image_url || '/assets/images/default-event.png';
            var eventUrl = '/bilete/' + (te.slug || te.id);

            if (isPast) {
                // Past event: muted, no link
                html += '<div class="flex items-center gap-4 p-3 rounded-xl opacity-50">';
            } else {
                html += '<a href="' + eventUrl + '" class="flex items-center gap-4 p-3 transition-colors rounded-xl hover:bg-gray-50 group">';
            }
            // Date badge
            var badgeBg = isPast ? 'background: #94a3b8;' : 'background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%);';
            html += '<div class="flex-shrink-0 flex flex-col items-center justify-center w-14 h-14 rounded-xl text-white text-center" style="' + badgeBg + '">';
            html += '<span class="text-xl font-bold leading-none">' + dayNum + '</span>';
            html += '<span class="text-xs font-semibold uppercase">' + monthStr + '</span>';
            html += '</div>';
            // Image
            html += '<div class="flex-shrink-0 w-16 h-12 rounded-lg overflow-hidden hidden sm:block' + (isPast ? ' grayscale' : '') + '">';
            html += '<img src="' + imgSrc + '" alt="' + te.name + '" class="w-full h-full object-cover" width="64" height="48">';
            html += '</div>';
            // Info
            html += '<div class="flex-1 min-w-0">';
            if (location) {
                html += '<p class="font-semibold truncate ' + (isPast ? 'text-muted' : 'text-secondary group-hover:text-primary transition-colors') + '">';
                html += city ? city : '';
                html += '<svg class="' + (isPast ? 'text-muted' : 'text-primary') + ' inline w-3.5 h-3.5 ml-1 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';
                html += '<span class="' + (isPast ? 'text-muted' : 'text-primary') + '">' + location + '</span></p>';
            }
            html += '<p class="text-sm text-muted">' + (te.name || 'Eveniment') + '</p>';
            html += '</div>';
            if (!isPast) {
                // Arrow only for upcoming
                html += '<svg class="flex-shrink-0 w-5 h-5 text-gray-300 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
            }
            html += isPast ? '</div>' : '</a>';
        }

        container.innerHTML = html;
        section.style.display = 'block';
    },

    /**
     * Render related events grid using AmbiletEventCard component
     */
    renderRelatedEvents(events) {
        document.getElementById(this.elements.relatedEventsSection).style.display = 'block';

        var category = this.event.category;
        var categorySlug = this.event.category_slug;
        var seeAllLink = document.getElementById(this.elements.seeAllLink);

        if (category && categorySlug) {
            document.getElementById(this.elements.relatedCategoryText).textContent = 'Evenimente similare din categoria ' + category;
            seeAllLink.href = '/evenimente?categorie=' + encodeURIComponent(categorySlug);
        } else if (category) {
            document.getElementById(this.elements.relatedCategoryText).textContent = 'Evenimente similare din categoria ' + category;
            var slug = category.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-');
            seeAllLink.href = '/evenimente?categorie=' + encodeURIComponent(slug);
        } else {
            document.getElementById(this.elements.relatedCategoryText).textContent = 'Evenimente similare';
            seeAllLink.href = '/evenimente';
        }

        // Use AmbiletEventCard component for consistent rendering
        document.getElementById(this.elements.relatedEvents).innerHTML = AmbiletEventCard.renderMany(events, {
            showCategory: true,
            showPrice: true,
            showVenue: true,
            urlPrefix: '/bilete/'
        });
    },

    /**
     * Update header cart badge
     */
    updateHeaderCart() {
        const count = typeof AmbiletCart !== 'undefined' ? AmbiletCart.getItemCount() : 0;
        const cartBadge = document.getElementById(this.elements.cartBadge);
        const cartDrawerCount = document.getElementById(this.elements.cartDrawerCount);

        if (cartBadge) {
            if (count > 0) {
                cartBadge.textContent = count > 99 ? '99+' : count;
                cartBadge.classList.remove('hidden');
                cartBadge.classList.add('flex');
            } else {
                cartBadge.classList.add('hidden');
                cartBadge.classList.remove('flex');
            }
        }

        if (cartDrawerCount) {
            if (count > 0) {
                cartDrawerCount.textContent = count;
                cartDrawerCount.classList.remove('hidden');
            } else {
                cartDrawerCount.classList.add('hidden');
            }
        }
    },

    /**
     * Build a mapping of section ID to ticket type(s) that can use it
     */
    buildSectionToTicketTypeMap() {
        var map = {};
        var self = this;
        this.ticketTypes.forEach(function(tt) {
            if (tt.seating_sections && tt.seating_sections.length > 0) {
                tt.seating_sections.forEach(function(section) {
                    if (!map[section.id]) {
                        map[section.id] = [];
                    }
                    map[section.id].push(tt);
                });
            }
        });
        return map;
    },

    /**
     * Build a mapping of row ID to ticket type(s) that can use it.
     * Uses seating_rows (row-level) if available, falls back to seating_sections (section-level).
     */
    buildRowToTicketTypeMap() {
        var map = {};
        var hasRowData = false;

        // Try row-level assignments first (new model)
        this.ticketTypes.forEach(function(tt) {
            if (tt.seating_rows && tt.seating_rows.length > 0) {
                hasRowData = true;
                tt.seating_rows.forEach(function(row) {
                    if (!map[row.id]) {
                        map[row.id] = [];
                    }
                    map[row.id].push(tt);
                });
            }
        });

        // Track which data source was used
        this._rowDataSource = hasRowData ? 'row' : 'section';

        // Fallback: derive row assignments from section-level assignments
        if (!hasRowData && this.seatingLayout && this.seatingLayout.sections) {
            console.warn('[EventPage] No row-level data found, using section-level fallback.');
            var sectionMap = this.buildSectionToTicketTypeMap();
            this.seatingLayout.sections.forEach(function(section) {
                var tts = sectionMap[section.id];
                if (tts && tts.length > 0 && section.rows) {
                    // If section has only 1 ticket type, assign all rows to it
                    // If multiple, distribute rows proportionally among ticket types
                    // but ALL ticket types are still "available" for every row (for clicking)
                    section.rows.forEach(function(row, rowIdx) {
                        if (!map[row.id]) {
                            map[row.id] = [];
                        }
                        // Reorder so the "primary" ticket type for this row comes first
                        // This determines the row's display color
                        var primaryIdx = rowIdx % tts.length;
                        var reordered = [tts[primaryIdx]];
                        tts.forEach(function(tt, i) {
                            if (i !== primaryIdx && !map[row.id].some(function(existing) { return existing.id === tt.id; })) {
                                reordered.push(tt);
                            }
                        });
                        reordered.forEach(function(tt) {
                            if (!map[row.id].some(function(existing) { return existing.id === tt.id; })) {
                                map[row.id].push(tt);
                            }
                        });
                    });
                }
            });
        }

        console.log('[EventPage] Row-to-TicketType map built. Source: ' + this._rowDataSource + ', rows mapped: ' + Object.keys(map).length);
        // Log per-ticket-type row counts
        var self = this;
        this.ticketTypes.forEach(function(tt) {
            var rowCount = 0;
            Object.keys(map).forEach(function(rowId) {
                if (map[rowId].some(function(t) { return t.id === tt.id; })) rowCount++;
            });
            console.log('[EventPage]   TT#' + tt.id + ' "' + tt.name + '" color=' + (tt.color || 'null') + ' rows=' + (tt.seating_rows ? tt.seating_rows.length : 0) + ' mapped_rows=' + rowCount);
        });

        return map;
    },

    /**
     * Re-fetch fresh seat statuses before opening the modal so seats
     * another tab/session held in the meantime show up as unavailable.
     * The page-load snapshot becomes stale very quickly on a contested
     * event; without this, a second browser could pick a seat that's
     * already held and would then fail at the hold API with an opaque
     * "add to cart" error.
     */
    async refreshSeatStatuses() {
        if (!this.seatingLayout || !this.slug) return;
        try {
            const response = await AmbiletAPI.request('/marketplace-events/' + this.slug, { method: 'GET', noCache: true });
            const freshLayout = response && response.data && response.data.seating_layout;
            if (!freshLayout || !Array.isArray(freshLayout.sections)) return;

            // Build a seat_uid → status map from the fresh payload
            const freshStatus = {};
            freshLayout.sections.forEach(function (section) {
                (section.rows || []).forEach(function (row) {
                    (row.seats || []).forEach(function (seat) {
                        if (seat.seat_uid) {
                            freshStatus[seat.seat_uid] = seat.status;
                        }
                    });
                });
            });

            // Patch statuses into the in-memory layout so the renderer sees them.
            (this.seatingLayout.sections || []).forEach(function (section) {
                (section.rows || []).forEach(function (row) {
                    (row.seats || []).forEach(function (seat) {
                        if (seat.seat_uid && freshStatus[seat.seat_uid] !== undefined) {
                            seat.status = freshStatus[seat.seat_uid];
                        }
                    });
                });
            });
        } catch (e) {
            console.warn('[EventPage] Failed to refresh seat statuses:', e);
        }
    },

    /**
     * Open seat selection modal - shows ALL available seats
     */
    async openSeatSelection(ticketTypeId) {
        var self = this;
        if (!this.seatingLayout) return;

        // Pull fresh seat statuses before rendering so holds made by
        // other tabs/sessions after the page loaded are reflected.
        await this.refreshSeatStatuses();

        console.log('[EventPage] Opening seat selection modal');
        console.log('[EventPage] Seating layout sections:', (this.seatingLayout?.sections || []).length);
        console.log('[EventPage] Ticket types from API:');
        this.ticketTypes.forEach(function(tt) {
            console.log('  TT#' + tt.id + ' "' + tt.name + '"' +
                ' | color=' + JSON.stringify(tt.color) +
                ' | seating_rows=' + (tt.seating_rows ? tt.seating_rows.length : 0) +
                ' | seating_sections=' + (tt.seating_sections ? tt.seating_sections.length : 0) +
                ' | resolved_color=' + EventPage.getTicketTypeColor(tt));
        });

        // Build section and row to ticket type mappings
        this.sectionToTicketTypeMap = this.buildSectionToTicketTypeMap();
        this.rowToTicketTypeMap = this.buildRowToTicketTypeMap();

        // Get ALL section IDs that have ticket types assigned
        var allAssignedSectionIds = Object.keys(this.sectionToTicketTypeMap).map(function(id) { return parseInt(id); });

        // Create modal if not exists
        if (!this.seatSelectionModal) {
            this.createSeatSelectionModal();
        }

        // Store the initially selected ticket type (for reference)
        this.currentTicketTypeId = ticketTypeId;

        // Clear ALL previous seat selections
        this.selectedSeats = {};

        // Reset zoom and pan
        this.mapZoom = 1;
        this.mapPan = { x: 0, y: 0 };

        // Render ticket types in sidebar
        this.renderModalTicketTypes(ticketTypeId);

        // Render selected tickets panel
        this.renderSelectedTicketsPanel();

        // Render the seating map with ALL assigned sections
        this.renderSeatingMapAllSections(allAssignedSectionIds);

        // Update summary
        this.updateSeatSelectionSummary();

        // Show modal
        document.getElementById('seat-selection-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Auto-center map after modal is visible (double rAF to ensure layout is settled on mobile)
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                self.centerMapToContent();
            });
        });
    },

    // Zoom level for seating map
    mapZoom: 1,
    mapPan: { x: 0, y: 0 },

    /**
     * Create the seat selection modal
     */
    createSeatSelectionModal() {
        var self = this;
        var modal = document.createElement('div');
        modal.id = 'seat-selection-modal';
        modal.className = 'fixed inset-0 hidden';
        modal.style.zIndex = '9999'; // Above navigation
        modal.innerHTML =
            // Scoped styles: on mobile, the two sidebars overlay the map when toggled.
            // Media query thresholds match the sidebar visibility breakpoints below
            // (#seat-modal-sidebar is md:block, #seat-selected-sidebar is lg:flex).
            '<style>' +
                '@media (max-width: 767px) {' +
                    '#seat-modal-sidebar.ticket-types-mobile-open { position: absolute !important; inset: 0; z-index: 10; display: block !important; }' +
                '}' +
                '@media (max-width: 1023px) {' +
                    '#seat-selected-sidebar.selected-tickets-mobile-open { position: absolute !important; inset: 0; z-index: 10; display: flex !important; flex-direction: column; }' +
                '}' +
            '</style>' +
            '<div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="EventPage.closeSeatSelection()"></div>' +
            // Full-screen on mobile (inset-0), padded on larger screens
            '<div class="absolute inset-0 md:inset-4 lg:inset-8 bg-white md:rounded-2xl shadow-2xl flex flex-col overflow-hidden">' +
                // Header
                '<div class="flex items-center justify-between px-4 md:px-6 py-3 border-b border-border mobile:bg-primary">' +
                    '<div class="flex-1 min-w-0">' +
                        '<h2 id="seat-selection-title" class="text-base md:text-xl font-bold text-secondary truncate mobile:text-white">Alege locurile</h2>' +
                        '<p class="text-xs md:text-sm text-muted mobile:text-white" id="seat-selection-subtitle">Selectează locurile dorite pe hartă</p>' +
                    '</div>' +
                    '<button onclick="EventPage.closeSeatSelection()" aria-label="Închide" class="p-2 transition-colors rounded-lg hover:bg-surface flex-shrink-0" aria-label="Închide">' +
                        '<svg class="w-6 h-6 text-muted mobile:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                    '</button>' +
                '</div>' +
                // Mobile-only toggle that exposes the ticket-types legend (desktop has it in the left sidebar)
                '<button type="button" id="seat-mobile-legend-toggle" aria-expanded="false" aria-controls="seat-modal-sidebar" class="md:hidden w-full flex items-center justify-between px-4 py-2.5 border-b border-border bg-white text-left">' +
                    '<span class="flex items-center gap-2 text-sm font-semibold text-secondary">' +
                        '<svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>' +
                        'Legendă locuri' +
                    '</span>' +
                    '<svg id="seat-mobile-legend-chevron" class="w-4 h-4 text-muted transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
                '</button>' +
                // Content area with sidebar and map (relative so the mobile overlays position correctly)
                '<div class="flex-1 flex overflow-hidden relative">' +
                    // Left sidebar - ticket types (hidden on mobile)
                    '<div class="w-48 lg:w-64 border-r border-border bg-white overflow-y-auto hidden md:block mobile:w-64" id="seat-modal-sidebar">' +
                        '<div class="p-4 border-b border-border mobile:py-0 mobile:px-4 mobile:flex mobile:items-center mobile:h-[49px]">' +
                            '<h3 class="font-bold text-secondary text-sm">Tipuri de bilete</h3>' +
                        '</div>' +
                        '<div id="seat-modal-ticket-types" class="flex-1 overflow-y-auto p-3 space-y-2"></div>' +
                    '</div>' +
                    // Map container
                    '<div class="flex-1 flex flex-col bg-surface/50 overflow-hidden">' +
                        // Zoom controls
                        '<div class="flex items-center justify-between px-3 md:px-4 py-2 bg-white border-b border-border">' +
                            '<div class="flex items-center gap-1 md:gap-2">' +
                                '<button onclick="EventPage.zoomMap(-0.2)" class="p-1.5 md:p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors" title="Zoom out" aria-label="Zoom out" name="zoom-out">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>' +
                                '</button>' +
                                '<span id="zoom-level" class="text-sm font-medium text-muted w-12 text-center">100%</span>' +
                                '<button onclick="EventPage.zoomMap(0.2)" class="p-1.5 md:p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors" title="Zoom in" aria-label="Zoom in" name="zoom-in">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                                '</button>' +
                                '<button onclick="EventPage.resetMapZoom()" class="ml-1 md:ml-2 p-1.5 md:p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors text-xs" title="Reset zoom" aria-label="Reset zoom" name="reset-zoom">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
                                '</button>' +
                            '</div>' +
                            '<div class="text-xs text-muted hidden md:block">Scroll pentru zoom, drag pentru panoramare</div>' +
                            '<div class="text-xs text-muted md:hidden">Ciupește pentru zoom</div>' +
                        '</div>' +
                        // Map SVG container with legend
                        '<div class="flex-1 overflow-hidden p-1 md:p-2 relative" id="seat-map-container">' +
                            '<div id="seat-map-wrapper" class="relative w-full h-full overflow-hidden touch-none" style="cursor: grab; min-width: 0; min-height: 0;">' +
                                '<div id="seat-map-svg" class="absolute top-0 left-0" style="transform-origin: 0 0;">' +
                                    '<div class="text-center text-muted">' +
                                        '<svg class="w-12 h-12 mx-auto mb-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>' +
                                        'Se încarcă harta...' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            // Legend in bottom-left corner of map
                            '<div class="absolute bottom-3 left-3 flex flex-wrap items-center gap-2 md:gap-4 text-[10px] md:text-sm bg-white/90 backdrop-blur-sm rounded-lg px-2 md:px-3 py-1.5 md:py-2 shadow-sm mobile:text-sm mobile:w-full mobile:justify-center">' +
                                '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded" style="background-color: #a51c30;"></span> Selectat</div>' +
                                '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded bg-gray-300"></span> Ocupat</div>' +
                                '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded relative" style="background-color: #D1D5DB;"><span class="absolute inset-0 flex items-center justify-center text-gray-500 font-bold" style="font-size:8px;line-height:1">&times;</span></span> Indisponibil</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    // Right sidebar - selected tickets (hidden on mobile, uses mobile bottom bar)
                    '<div class="w-64 lg:w-80 border-l border-border bg-white overflow-y-auto hidden lg:flex flex-col mobile:border-l-0 mobile:border-r" id="seat-selected-sidebar">' +
                        '<div class="py-4 px-3 border-b border-border flex items-center justify-between mobile:h-[49px] mobile:py-0 mobile:px-4">' +
                            '<h3 class="font-bold text-secondary text-sm" id="selected-tickets-count-header">0 bilete</h3>' +
                            // Close button visible only on mobile/tablet (under lg) when sidebar is shown as overlay
                            '<button type="button" id="seat-selected-close" class="lg:hidden p-1 rounded hover:bg-surface" aria-label="Închide">' +
                                '<svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                            '</button>' +
                        '</div>' +
                        '<div id="seat-selected-tickets" class="flex-1 overflow-y-auto p-3 space-y-2"></div>' +
                        '<div class="p-3 border-t border-border bg-surface/30 mobile:hidden">' +
                            '<div class="mb-3 text-center">' +
                                '<span class="text-sm text-muted">Locuri selectate: </span>' +
                                '<span id="selected-seats-count" class="font-bold text-secondary">0</span>' +
                                '<span class="mx-2 text-muted">|</span>' +
                                '<span class="text-sm text-muted">Total: </span>' +
                                '<span id="selected-seats-total" class="font-bold text-primary">0 lei</span>' +
                            '</div>' +
                            '<div class="flex gap-2">' +
                                '<button onclick="EventPage.confirmSeatSelection()" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary-dark transition-colors" name="buy-tickets">Cumpără bilete</button>' +
                                '<button onclick="EventPage.clearAllSelections()" aria-label="Golește coșul" class="p-2.5 text-secondary border border-secondary rounded-xl opacity-50 hover:bg-red-50 hover:opacity-100 hover:border-red-600 hover:text-red-600 transition-all" title="Golește coșul" aria-label="Golește coșul">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                // Mobile bottom bar - shows selected seats count and buy button (visible on < lg)
                '<div class="lg:hidden border-t border-border bg-white px-4 py-3 flex items-center justify-between gap-3" id="seat-mobile-bottom-bar">' +
                    // Click on the summary opens the selected-tickets overlay.
                    // The chevron + "Vezi bilete" hint appear only after at least
                    // one seat is picked, so the user gets a visual cue that the
                    // summary is tap-able.
                    '<button type="button" id="seat-mobile-selected-toggle" class="flex-1 min-w-0 flex items-center justify-between gap-2 text-left" aria-expanded="false" aria-controls="seat-selected-sidebar">' +
                        '<span class="min-w-0 mobile:flex mobile:flex-col">' +
                            '<span class="text-sm font-bold text-secondary" id="mobile-seats-count">0 locuri</span>' +
                            '<span class="text-sm text-muted ml-2 mobile:ml-0" id="mobile-seats-total">0 lei</span>' +
                        '</span>' +
                        // JS toggles between "hidden" and "inline-flex" on this hint
                        '<span id="seat-mobile-selected-hint" class="hidden items-center gap-1 text-xs font-semibold text-primary uppercase tracking-wide flex-shrink-0">' +
                            'Vezi bilete' +
                            '<svg class="w-4 h-4 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>' +
                        '</span>' +
                    '</button>' +
                    '<div class="flex gap-2 flex-shrink-0">' +
                        '<button onclick="EventPage.clearAllSelections()" aria-label="Golește" class="p-2.5 text-muted border border-gray-200 rounded-xl hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-all" title="Golește" aria-label="Golește">' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                        '</button>' +
                        '<button onclick="EventPage.confirmSeatSelection()" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary-dark transition-colors" id="mobile-seats-buy-btn" aria-label="Cumpără bilete">Cumpără bilete</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        this.seatSelectionModal = modal;

        // Mobile-only toggles: show/hide the two sidebars as overlays over the map.
        // The scoped <style> block above only applies the overlay classes inside the
        // appropriate media queries, so desktop behavior is untouched.
        (function wireMobileSidebarToggles() {
            var legendBtn = document.getElementById('seat-mobile-legend-toggle');
            var legendChev = document.getElementById('seat-mobile-legend-chevron');
            var ticketSidebar = document.getElementById('seat-modal-sidebar');
            if (legendBtn && ticketSidebar) {
                legendBtn.addEventListener('click', function () {
                    var isOpen = ticketSidebar.classList.toggle('ticket-types-mobile-open');
                    legendBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    if (legendChev) legendChev.classList.toggle('rotate-180', isOpen);
                });
            }

            var selectedBtn = document.getElementById('seat-mobile-selected-toggle');
            var selectedSidebar = document.getElementById('seat-selected-sidebar');
            var selectedClose = document.getElementById('seat-selected-close');
            if (selectedBtn && selectedSidebar) {
                selectedBtn.addEventListener('click', function () {
                    var isOpen = selectedSidebar.classList.toggle('selected-tickets-mobile-open');
                    selectedBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            }
            if (selectedClose && selectedSidebar) {
                selectedClose.addEventListener('click', function () {
                    selectedSidebar.classList.remove('selected-tickets-mobile-open');
                    if (selectedBtn) selectedBtn.setAttribute('aria-expanded', 'false');
                });
            }
        })();

        // Setup zoom with mouse wheel — anchored at cursor position
        var mapWrapper = document.getElementById('seat-map-wrapper');
        mapWrapper.addEventListener('wheel', function(e) {
            e.preventDefault();
            var delta = e.deltaY > 0 ? -0.1 : 0.1;
            var rect = mapWrapper.getBoundingClientRect();
            self.zoomMap(delta, e.clientX - rect.left, e.clientY - rect.top);
        }, { passive: false });

        // Setup pan with mouse drag (using transform instead of scroll for better zoom support)
        var isDragging = false;
        var startX, startY, startPanX, startPanY;

        mapWrapper.addEventListener('mousedown', function(e) {
            isDragging = true;
            mapWrapper.style.cursor = 'grabbing';
            startX = e.clientX;
            startY = e.clientY;
            startPanX = self.mapPan.x;
            startPanY = self.mapPan.y;
        });

        mapWrapper.addEventListener('mouseleave', function() {
            isDragging = false;
            mapWrapper.style.cursor = 'grab';
        });

        mapWrapper.addEventListener('mouseup', function() {
            isDragging = false;
            mapWrapper.style.cursor = 'grab';
        });

        mapWrapper.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            self.mapPan.x = startPanX + dx;
            self.mapPan.y = startPanY + dy;
            self.applyMapTransform();
        });

        // Touch events for mobile pan and pinch-zoom
        var touchStartDist = 0;
        var touchStartZoom = 1;
        var isTouchDragging = false;
        var touchStartX, touchStartY, touchStartPanX, touchStartPanY;

        mapWrapper.addEventListener('touchstart', function(e) {
            if (e.touches.length === 2) {
                // Pinch zoom start
                e.preventDefault();
                var dx = e.touches[0].clientX - e.touches[1].clientX;
                var dy = e.touches[0].clientY - e.touches[1].clientY;
                touchStartDist = Math.sqrt(dx * dx + dy * dy);
                touchStartZoom = self.mapZoom;
                isTouchDragging = false;
            } else if (e.touches.length === 1) {
                // Single finger pan
                isTouchDragging = true;
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchStartPanX = self.mapPan.x;
                touchStartPanY = self.mapPan.y;
            }
        }, { passive: false });

        mapWrapper.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2) {
                // Pinch zoom — anchor at midpoint between the two fingers
                e.preventDefault();
                var dx = e.touches[0].clientX - e.touches[1].clientX;
                var dy = e.touches[0].clientY - e.touches[1].clientY;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (touchStartDist > 0) {
                    var scale = dist / touchStartDist;
                    var newZoom = Math.max(0.5, Math.min(3, touchStartZoom * scale));
                    if (newZoom !== self.mapZoom) {
                        var rect = mapWrapper.getBoundingClientRect();
                        var focalX = ((e.touches[0].clientX + e.touches[1].clientX) / 2) - rect.left;
                        var focalY = ((e.touches[0].clientY + e.touches[1].clientY) / 2) - rect.top;
                        var ratio = newZoom / self.mapZoom;
                        self.mapPan.x = focalX - (focalX - self.mapPan.x) * ratio;
                        self.mapPan.y = focalY - (focalY - self.mapPan.y) * ratio;
                        self.mapZoom = newZoom;
                        self.applyMapTransform();
                        var zoomEl = document.getElementById('zoom-level');
                        if (zoomEl) zoomEl.textContent = Math.round(self.mapZoom * 100) + '%';
                    }
                }
            } else if (e.touches.length === 1 && isTouchDragging) {
                // Single finger pan
                e.preventDefault();
                var panDx = e.touches[0].clientX - touchStartX;
                var panDy = e.touches[0].clientY - touchStartY;
                self.mapPan.x = touchStartPanX + panDx;
                self.mapPan.y = touchStartPanY + panDy;
                self.applyMapTransform();
            }
        }, { passive: false });

        mapWrapper.addEventListener('touchend', function(e) {
            if (e.touches.length < 2) {
                touchStartDist = 0;
            }
            if (e.touches.length === 0) {
                isTouchDragging = false;
            }
        });
    },

    /**
     * Apply map transform (zoom + pan)
     */
    applyMapTransform() {
        var svgContainer = document.getElementById('seat-map-svg');
        if (svgContainer) {
            svgContainer.style.transform = 'translate(' + this.mapPan.x + 'px, ' + this.mapPan.y + 'px) scale(' + this.mapZoom + ')';
        }
    },

    /**
     * Zoom the map, keeping a focal point stationary in wrapper coordinates.
     * When focalX/focalY are omitted, zooms around the wrapper center so the
     * +/- buttons feel natural.
     */
    zoomMap(delta, focalX, focalY) {
        var wrapper = document.getElementById('seat-map-wrapper');
        if (!wrapper) return;

        if (typeof focalX !== 'number' || typeof focalY !== 'number') {
            var rect = wrapper.getBoundingClientRect();
            focalX = rect.width / 2;
            focalY = rect.height / 2;
        }

        var oldZoom = this.mapZoom;
        var newZoom = Math.max(0.5, Math.min(3, oldZoom + delta));
        if (newZoom === oldZoom) return;

        // Keep the focal point fixed: newPan = focal - (focal - oldPan) * (newZoom / oldZoom)
        var ratio = newZoom / oldZoom;
        this.mapPan.x = focalX - (focalX - this.mapPan.x) * ratio;
        this.mapPan.y = focalY - (focalY - this.mapPan.y) * ratio;
        this.mapZoom = newZoom;

        this.applyMapTransform();
        document.getElementById('zoom-level').textContent = Math.round(this.mapZoom * 100) + '%';
    },

    /**
     * Reset map zoom
     */
    resetMapZoom() {
        this.mapZoom = 1;
        this.mapPan = { x: 0, y: 0 };
        this.applyMapTransform();
        document.getElementById('zoom-level').textContent = '100%';
    },

    /**
     * Center and fit the seating map within the visible container
     */
    centerMapToContent() {
        var wrapper = document.getElementById('seat-map-wrapper');
        var svgEl = document.querySelector('#seat-map-svg svg');
        if (!wrapper || !svgEl) return;

        var wrapperRect = wrapper.getBoundingClientRect();
        var canvasW = this.seatingLayout?.canvas_width || 1920;
        var canvasH = this.seatingLayout?.canvas_height || 1080;

        // Calculate zoom to fit the canvas in the visible area with some padding
        var isMobile = wrapperRect.width < 768;
        var padX = isMobile ? 10 : 40;
        var padY = isMobile ? 10 : 40;
        var availW = wrapperRect.width - padX;
        var availH = wrapperRect.height - padY;
        var fitZoom = Math.min(availW / canvasW, availH / canvasH);
        fitZoom = Math.max(0.5, Math.min(2, fitZoom));

        // Calculate pan to center
        var scaledW = canvasW * fitZoom;
        var scaledH = canvasH * fitZoom;
        var panX = (wrapperRect.width - scaledW) / 2;
        var panY = (wrapperRect.height - scaledH) / 2;

        this.mapZoom = fitZoom;
        this.mapPan = { x: panX, y: panY };
        this.applyMapTransform();
        document.getElementById('zoom-level').textContent = Math.round(fitZoom * 100) + '%';
    },

    /**
     * Render ticket types in modal sidebar
     */
    renderModalTicketTypes(currentTicketTypeId) {
        var container = document.getElementById('seat-modal-ticket-types');
        if (!container) return;

        var self = this;
        var html = this.ticketTypes.filter(function(tt) {
            return tt.has_seating && !tt.is_sold_out;
        }).map(function(tt) {
            var isActive = String(tt.id) === String(currentTicketTypeId);
            var seatColor = self.getTicketTypeColor(tt);

            return '<div class="p-3 rounded-xl border-2 cursor-pointer transition-all ' +
                (isActive ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50') + '" ' +
                'onclick="EventPage.switchTicketType(\'' + tt.id + '\')">' +
                '<div class="flex justify-between items-start mb-1">' +
                    '<span class="flex items-center gap-1.5 font-semibold text-secondary text-sm"><span class="w-3 h-3 rounded flex-shrink-0" style="background-color: ' + seatColor + '"></span>' + tt.name + '</span>' +
                    '<span class="font-bold text-primary">' + tt.price.toFixed(0) + ' lei</span>' +
                '</div>' +
                '<div class="text-xs text-muted">' + (tt.description || 'Acces general') + '</div>' +
            '</div>';
        }).join('');

        container.innerHTML = html || '<p class="text-sm text-muted">Nu există bilete cu locuri pentru acest eveniment.</p>';
    },

    /**
     * Render selected tickets panel in right sidebar
     */
    renderSelectedTicketsPanel() {
        var self = this;
        var container = document.getElementById('seat-selected-tickets');
        var headerEl = document.getElementById('selected-tickets-count-header');
        if (!container) return;

        var totalCount = 0;
        var html = '';

        // Group by ticket type
        this.ticketTypes.filter(function(tt) {
            return tt.has_seating && !tt.is_sold_out;
        }).forEach(function(tt) {
            var seats = self.selectedSeats[tt.id] || [];
            if (seats.length === 0) return;

            totalCount += seats.length;

            // Category header
            html += '<div class="mb-2">' +
                '<div class="flex items-center justify-between mb-1">' +
                    '<span class="text-xs font-semibold text-secondary">' + tt.name + '</span>' +
                    '<span class="text-xs font-bold text-primary">' + tt.price.toFixed(0) + ' lei</span>' +
                '</div>';

            // Individual seats
            seats.forEach(function(seat, index) {
                html += '<div class="flex items-center justify-between p-2 bg-surface rounded-lg mb-1">' +
                    '<div class="text-xs">' +
                        '<div class="font-medium text-secondary">' + seat.section + '</div>' +
                        '<div class="text-muted">Rând ' + seat.row + ', Loc ' + seat.seat + '</div>' +
                    '</div>' +
                    '<button onclick="EventPage.removeSeat(\'' + tt.id + '\', ' + index + ')" aria-label="Șterge" class="p-1 text-red-500 hover:bg-red-50 rounded transition-colors" title="Șterge" aria-label="Șterge">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                    '</button>' +
                '</div>';
            });

            html += '</div>';
        });

        container.innerHTML = html || '<p class="text-xs text-muted text-center py-4">Selectează locuri pe hartă</p>';

        if (headerEl) {
            headerEl.textContent = totalCount + ' bilet' + (totalCount !== 1 ? 'e' : '');
        }
    },

    /**
     * Remove a seat from selection
     */
    removeSeat(ticketTypeId, index) {
        if (this.selectedSeats[ticketTypeId] && this.selectedSeats[ticketTypeId][index]) {
            this.selectedSeats[ticketTypeId].splice(index, 1);
            this.quantities[ticketTypeId] = this.selectedSeats[ticketTypeId].length;
            this.refreshSeatingMap();
        }
    },

    /**
     * Clear all seat selections
     */
    clearAllSelections() {
        var self = this;

        // Clear all selected seats
        this.ticketTypes.forEach(function(tt) {
            if (tt.has_seating) {
                self.selectedSeats[tt.id] = [];
                self.quantities[tt.id] = 0;
            }
        });

        this.refreshSeatingMap();
    },

    /**
     * Switch to different ticket type in modal
     */
    switchTicketType(ticketTypeId) {
        var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
        if (!tt) return;

        this.currentTicketTypeId = ticketTypeId;
        // Just update the sidebar highlight, keep all sections available
        this.renderModalTicketTypes(ticketTypeId);
    },

    /**
     * Render a decorative section (text, line, polygon) as SVG markup
     * Used by both renderSeatingMap and renderSeatingMapAllSections
     */
    renderDecorativeSectionSvg(section) {
        var metadata = section.metadata || {};
        var shape = metadata.shape || 'polygon';
        var opacity = parseFloat(metadata.opacity) || 0.3;
        var color = section.background_color || section.color_hex || '#10B981';

        var svg = '';

        if (shape === 'polygon' && metadata.points) {
            // Draw filled polygon from stored points
            var points = metadata.points;
            var minX = section.x;
            var minY = section.y;

            // Convert absolute points to relative (offset by section position)
            var svgPoints = '';
            for (var i = 0; i < points.length; i += 2) {
                var px = points[i] - minX;
                var py = points[i + 1] - minY;
                svgPoints += px + ',' + py + ' ';
            }

            // Position group at section x,y so relative points align correctly
            svg += '<g transform="translate(' + section.x + ',' + section.y + ')">';
            svg += '<polygon points="' + svgPoints.trim() + '" fill="' + color + '" opacity="' + opacity + '" stroke="' + color + '" stroke-width="1"/>';

            // Label inside polygon if it has a name
            if (metadata.label || section.name) {
                svg += '<text x="10" y="20" font-size="12" font-family="Arial" fill="#1f2937" opacity="0.8">' + (metadata.label || section.name) + '</text>';
            }
            svg += '</g>';

        } else if (shape === 'text') {
            // Draw text label — centered vertically in section bounds
            var fontSize = parseInt(metadata.fontSize) || 16;
            var fontFamily = metadata.fontFamily || 'Arial';
            var fontWeight = metadata.fontWeight || 'normal';
            var textContent = metadata.text || section.name || 'Text';
            var textY = section.height > 0 ? section.y + section.height / 2 : section.y + fontSize;

            svg += '<text x="' + section.x + '" y="' + textY + '" dominant-baseline="central" font-size="' + fontSize + '" font-family="' + fontFamily + '" font-weight="' + fontWeight + '" fill="' + color + '">' + textContent + '</text>';

        } else if (shape === 'line') {
            // Draw line from stored points
            var linePoints = metadata.points || [0, 0, 100, 0];
            var strokeWidth = parseInt(metadata.strokeWidth) || 2;
            var strokeColor = metadata.strokeColor || color;

            // Points are relative to section position
            var x1 = section.x + (linePoints[0] || 0);
            var y1 = section.y + (linePoints[1] || 0);
            var x2 = section.x + (linePoints[2] || 100);
            var y2 = section.y + (linePoints[3] || 0);

            svg += '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="' + strokeColor + '" stroke-width="' + strokeWidth + '" stroke-linecap="round"/>';
        }

        return svg;
    },

    /**
     * Render the seating map SVG
     */
    renderSeatingMap(allowedSectionIds, ticketTypeId) {
        var self = this;
        var layout = this.seatingLayout;
        var container = document.getElementById('seat-map-svg');

        if (!layout || !layout.sections) {
            container.innerHTML = '<div class="text-center text-muted">Nu există hartă de locuri disponibilă.</div>';
            return;
        }

        // Get ticket type color for available seats
        var currentTT = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
        var ttColor = currentTT ? this.getTicketTypeColor(currentTT) : '#3B82F6';

        // Calculate viewBox
        var canvasW = layout.canvas_width || 1920;
        var canvasH = layout.canvas_height || 1080;

        // Build SVG with tooltip styles and seat shapes
        var svg = '<svg viewBox="0 0 ' + canvasW + ' ' + canvasH + '" class="w-full h-full" style="min-width: ' + canvasW + 'px; min-height: ' + canvasH + 'px;" overflow="visible" preserveAspectRatio="xMidYMid meet">';
        svg += '<style>' +
            '.seat-hover { transition: all 0.2s ease; }' +
            '.seat-hover:hover { transform: scale(1.15); filter: brightness(1.2); }' +
        '</style>';

        // Background image if exists
        if (layout.background_image) {
            var bgScale = layout.background_scale || 1;
            var bgX = layout.background_x || 0;
            var bgY = layout.background_y || 0;
            var bgOpacity = layout.background_opacity || 0.5;
            var bgW = canvasW * bgScale;
            var bgH = canvasH * bgScale;
            svg += '<image href="' + layout.background_image + '" x="' + bgX + '" y="' + bgY + '" width="' + bgW + '" height="' + bgH + '" opacity="' + bgOpacity + '" preserveAspectRatio="xMidYMid meet"/>';
        }

        // Render ALL sections (not just allowed ones)
        layout.sections.forEach(function(section) {
            var isAllowed = allowedSectionIds.includes(section.id);

            // Calculate rotation transform around section center
            var rotation = section.rotation || 0;
            var rcx = section.x + section.width / 2;
            var rcy = section.y + section.height / 2;
            var transform = rotation !== 0 ? ' transform="rotate(' + rotation + ' ' + rcx + ' ' + rcy + ')"' : '';

            svg += '<g' + transform + '>';

            // Handle ICON sections differently
            if (section.section_type === 'icon') {
                var metadata = section.metadata || {};
                var iconSize = metadata.icon_size || 40;
                var bgColor = metadata.background_color || section.color_hex || '#3B82F6';
                var iconColor = metadata.icon_color || '#FFFFFF';
                var iconX = section.x;
                var iconY = section.y;

                // Icon background circle
                var radius = iconSize / 2;
                svg += '<circle cx="' + (iconX + radius) + '" cy="' + (iconY + radius) + '" r="' + radius + '" fill="' + bgColor + '"/>';

                // Render the SVG icon if available
                if (section.icon_svg) {
                    // Calculate scale to fit the icon inside the circle (70% of diameter)
                    var innerSize = iconSize * 0.6;
                    var iconOffset = (iconSize - innerSize) / 2;

                    // Parse and embed the SVG icon
                    var iconSvgContent = section.icon_svg;
                    // Extract path/content from full SVG or use as path data
                    if (iconSvgContent.indexOf('<svg') !== -1) {
                        // Full SVG - extract viewBox and inner content
                        var viewBoxMatch = iconSvgContent.match(/viewBox="([^"]+)"/);
                        var viewBox = viewBoxMatch ? viewBoxMatch[1] : '0 0 512 512';
                        var innerMatch = iconSvgContent.match(/<g[^>]*>([\s\S]*?)<\/g>/);
                        var innerContent = innerMatch ? innerMatch[1] : '';

                        // If no <g> found, try to get content between <svg> and </svg>
                        if (!innerContent) {
                            innerMatch = iconSvgContent.match(/<svg[^>]*>([\s\S]*?)<\/svg>/);
                            innerContent = innerMatch ? innerMatch[1] : '';
                        }

                        svg += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="' + viewBox + '">';
                        // Apply icon color by wrapping in a group with fill
                        svg += '<g fill="' + iconColor + '">' + innerContent.replace(/fill="[^"]*"/g, 'fill="' + iconColor + '"') + '</g>';
                        svg += '</svg>';
                    } else {
                        // Just path data
                        svg += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="0 0 24 24">';
                        svg += '<path d="' + iconSvgContent + '" fill="' + iconColor + '"/>';
                        svg += '</svg>';
                    }
                }

                // Icon label below
                var labelY = iconY + iconSize + 12;
                var labelX = iconX + (iconSize / 2);
                svg += '<text x="' + labelX + '" y="' + labelY + '" text-anchor="middle" font-size="10" font-weight="500" fill="#1F2937" style="text-shadow: 0 0 3px white, 0 0 3px white;">' + (section.icon_label || section.name) + '</text>';

                svg += '</g>'; // Close section group
                return; // Skip rest of section rendering for icons
            }

            // Handle DECORATIVE sections (text, line, polygon)
            if (section.section_type === 'decorative') {
                svg += self.renderDecorativeSectionSvg(section);
                svg += '</g>';
                return;
            }

            // Seat size from section metadata (matching admin designer)
            var sectionMeta2 = section.metadata || {};
            var seatSize2 = parseInt(sectionMeta2.seat_size) || 15;
            var seatRadius2 = seatSize2 / 2;
            var seatFontSize2 = Math.round(seatRadius2 * 0.85 * 10) / 10;
            var xOff2 = Math.round(seatRadius2 * 0.5 * 10) / 10;

            // Section-wide seat X bounds + gap for aligned row labels (same as admin preview)
            var allSeatXs2 = [];
            var seatGap2 = seatRadius2 * 3;
            var gapDetected2 = false;
            if (section.rows) {
                section.rows.forEach(function(_r) {
                    if (!_r.seats) return;
                    _r.seats.forEach(function(_s) { allSeatXs2.push(_s.x || 0); });
                    if (!gapDetected2 && _r.seats.length >= 2) {
                        var sortedXs2 = _r.seats.map(function(s) { return s.x || 0; }).sort(function(a, b) { return a - b; });
                        seatGap2 = Math.abs(sortedXs2[1] - sortedXs2[0]);
                        gapDetected2 = true;
                    }
                });
            }
            var secMinX2 = allSeatXs2.length > 0 ? Math.min.apply(null, allSeatXs2) : 0;
            var secMaxX2 = allSeatXs2.length > 0 ? Math.max.apply(null, allSeatXs2) : 0;
            var leftLabelX2 = section.x + secMinX2 - seatGap2;
            var rightLabelX2 = section.x + secMaxX2 + seatGap2;
            var rowLabelSize2 = Math.max(10, Math.round(seatFontSize2 * 1.1 * 10) / 10);
            var autoShowRowLabels2 = (sectionMeta2.auto_show_row_labels !== false);

            // Render seats using actual x/y coordinates
            if (section.rows) {
                section.rows.forEach(function(row) {
                    if (!row.seats || row.seats.length === 0) return;

                    // Draw table shape if this row is a table
                    if (row.is_table) {
                        var tableColor = section.background_color || '#6B7280';
                        var tcx = section.x + (row.center_x || 0);
                        var tcy = section.y + (row.center_y || 0);

                        if (row.table_type === 'round') {
                            var tr = row.radius || 30;
                            svg += '<circle cx="' + tcx + '" cy="' + tcy + '" r="' + tr + '" fill="' + tableColor + '" fill-opacity="0.25" stroke="' + tableColor + '" stroke-width="1.5" stroke-opacity="0.5"/>';
                        } else {
                            var tw = row.table_width || 80;
                            var th = row.table_height || 30;
                            svg += '<rect x="' + (tcx - tw/2) + '" y="' + (tcy - th/2) + '" width="' + tw + '" height="' + th + '" rx="4" fill="' + tableColor + '" fill-opacity="0.25" stroke="' + tableColor + '" stroke-width="1.5" stroke-opacity="0.5"/>';
                        }
                        // Table label
                        svg += '<text x="' + tcx + '" y="' + (tcy + 4) + '" text-anchor="middle" font-size="10" font-weight="700" fill="rgba(0,0,0,0.4)" class="pointer-events-none select-none">' + row.label + '</text>';
                    }

                    // Row labels aligned on section-wide left/right columns (opt-out via metadata)
                    if (!row.is_table && autoShowRowLabels2) {
                        var firstSeat = row.seats[0];
                        if (firstSeat) {
                            var rlY = section.y + firstSeat.y + seatRadius2 * 0.4;
                            svg += '<text x="' + leftLabelX2 + '" y="' + rlY + '" text-anchor="end" font-size="' + rowLabelSize2 + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + row.label + '</text>';
                            svg += '<text x="' + rightLabelX2 + '" y="' + rlY + '" text-anchor="start" font-size="' + rowLabelSize2 + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + row.label + '</text>';
                        }
                    }

                    row.seats.forEach(function(seat) {
                        var seatCX = section.x + seat.x;
                        var seatCY = section.y + seat.y;

                        var isSelected = self.isSeatSelected(ticketTypeId, seat.id);
                        var status = seat.status || 'available';
                        var isDisabled = (status === 'disabled' || seat.base_status === 'imposibil');

                        var seatColor, strokeColor, cursor, isClickable;

                        if (isDisabled) {
                            seatColor = '#D1D5DB';
                            strokeColor = '#9CA3AF';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (status === 'blocked') {
                            seatColor = '#D1D5DB';
                            strokeColor = '#9CA3AF';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (!isAllowed) {
                            seatColor = '#E5E7EB';
                            strokeColor = '#D1D5DB';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (isSelected) {
                            seatColor = '#a51c30';
                            strokeColor = '#7a141f';
                            cursor = 'pointer';
                            isClickable = true;
                        } else if (status === 'available') {
                            seatColor = ttColor;
                            strokeColor = '#fff';
                            cursor = 'pointer';
                            isClickable = true;
                        } else if (status === 'sold' || status === 'held') {
                            seatColor = '#9CA3AF';
                            strokeColor = '#6B7280';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else {
                            seatColor = '#E5E7EB';
                            strokeColor = '#D1D5DB';
                            cursor = 'not-allowed';
                            isClickable = false;
                        }

                        var clickHandler = isClickable ?
                            'onclick="EventPage.toggleSeat(\'' + ticketTypeId + '\', ' + seat.id + ', \'' + section.name.replace(/'/g, "\\'") + '\', \'' + row.label + '\', \'' + seat.label + '\', \'' + (seat.seat_uid || '') + '\')"' : '';

                        // Tooltip
                        var tooltipText;
                        if (isDisabled || status === 'blocked') {
                            tooltipText = 'Indisponibil';
                        } else {
                            tooltipText = section.name + ', Rând ' + row.label + ', Loc ' + seat.label;
                            if (!isAllowed) tooltipText += ' — indisponibil pentru acest bilet';
                            else if (status === 'sold') tooltipText += ' — vândut';
                            else if (status === 'held') tooltipText += ' — rezervat';
                        }

                        // Selected seats: larger radius and font
                        var drawRadius = isSelected ? seatRadius2 * 1.4 : seatRadius2;
                        var drawFontSize = isSelected ? Math.round(seatRadius2 * 1.2 * 10) / 10 : seatFontSize2;
                        var drawStrokeWidth = isSelected ? '1.5' : '0.5';

                        svg += '<g class="seat-hover" ' + clickHandler + ' style="cursor: ' + cursor + ';">' +
                            '<title>' + tooltipText + '</title>' +
                            '<circle cx="' + seatCX + '" cy="' + seatCY + '" r="' + drawRadius + '" fill="' + seatColor + '" stroke="' + strokeColor + '" stroke-width="' + drawStrokeWidth + '"/>';

                        if (!isDisabled && status !== 'blocked') {
                            svg += '<text x="' + seatCX + '" y="' + (seatCY + drawRadius * 0.35) + '" text-anchor="middle" font-size="' + drawFontSize + '" font-weight="600" fill="white" class="pointer-events-none select-none">' + seat.label + '</text>';
                        }

                        if (isDisabled || status === 'blocked') {
                            svg += '<line x1="' + (seatCX - xOff2) + '" y1="' + (seatCY - xOff2) + '" x2="' + (seatCX + xOff2) + '" y2="' + (seatCY + xOff2) + '" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round"/>' +
                                   '<line x1="' + (seatCX + xOff2) + '" y1="' + (seatCY - xOff2) + '" x2="' + (seatCX - xOff2) + '" y2="' + (seatCY + xOff2) + '" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round"/>';
                        }

                        svg += '</g>';
                    });
                });
            }

            svg += '</g>'; // Close section group
        });

        svg += '</svg>';
        container.innerHTML = svg;

        this.updateSeatSelectionSummary();
    },

    /**
     * Render seating map showing ALL sections using actual seat coordinates
     * Colors seats based on row-level ticket type assignments
     */
    renderSeatingMapAllSections(assignedSectionIds) {
        var self = this;
        var layout = this.seatingLayout;
        var container = document.getElementById('seat-map-svg');

        if (!layout || !layout.sections) {
            container.innerHTML = '<div class="text-center text-muted">Nu există hartă de locuri disponibilă.</div>';
            return;
        }

        var canvasW = layout.canvas_width || 1920;
        var canvasH = layout.canvas_height || 1080;

        var svg = '<svg viewBox="0 0 ' + canvasW + ' ' + canvasH + '" class="w-full h-full" style="min-width: ' + canvasW + 'px; min-height: ' + canvasH + 'px;" overflow="visible" preserveAspectRatio="xMidYMid meet">';
        svg += '<style>' +
            '.seat-hover { transition: transform 0.15s ease, filter 0.15s ease; }' +
            '.seat-hover:hover { filter: brightness(1.2); }' +
        '</style>';

        // Background image if exists
        if (layout.background_image) {
            var bgScale = layout.background_scale || 1;
            var bgX = layout.background_x || 0;
            var bgY = layout.background_y || 0;
            var bgOpacity = layout.background_opacity || 0.5;
            var bgW = canvasW * bgScale;
            var bgH = canvasH * bgScale;
            svg += '<image href="' + layout.background_image + '" x="' + bgX + '" y="' + bgY + '" width="' + bgW + '" height="' + bgH + '" opacity="' + bgOpacity + '" preserveAspectRatio="xMidYMid meet"/>';
        }

        // Render ALL sections
        layout.sections.forEach(function(section) {
            var rotation = section.rotation || 0;
            var cx = section.x + section.width / 2;
            var cy = section.y + section.height / 2;
            var transform = rotation !== 0 ? ' transform="rotate(' + rotation + ' ' + cx + ' ' + cy + ')"' : '';

            svg += '<g' + transform + '>';

            // Handle ICON sections (unchanged)
            if (section.section_type === 'icon') {
                var metadata = section.metadata || {};
                var iconSize = metadata.icon_size || 40;
                var bgColor = metadata.background_color || section.color_hex || '#3B82F6';
                var iconColor = metadata.icon_color || '#FFFFFF';
                var iconX = section.x;
                var iconY = section.y;

                var radius = iconSize / 2;
                svg += '<circle cx="' + (iconX + radius) + '" cy="' + (iconY + radius) + '" r="' + radius + '" fill="' + bgColor + '"/>';

                if (section.icon_svg) {
                    var innerSize = iconSize * 0.6;
                    var iconOffset = (iconSize - innerSize) / 2;
                    var iconSvgContent = section.icon_svg;
                    if (iconSvgContent.indexOf('<svg') !== -1) {
                        var viewBoxMatch = iconSvgContent.match(/viewBox="([^"]+)"/);
                        var viewBox = viewBoxMatch ? viewBoxMatch[1] : '0 0 512 512';
                        var innerMatch = iconSvgContent.match(/<g[^>]*>([\s\S]*?)<\/g>/);
                        var innerContent = innerMatch ? innerMatch[1] : '';
                        if (!innerContent) {
                            innerMatch = iconSvgContent.match(/<svg[^>]*>([\s\S]*?)<\/svg>/);
                            innerContent = innerMatch ? innerMatch[1] : '';
                        }
                        svg += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="' + viewBox + '">';
                        svg += '<g fill="' + iconColor + '">' + innerContent.replace(/fill="[^"]*"/g, 'fill="' + iconColor + '"') + '</g>';
                        svg += '</svg>';
                    } else {
                        svg += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="0 0 24 24">';
                        svg += '<path d="' + iconSvgContent + '" fill="' + iconColor + '"/>';
                        svg += '</svg>';
                    }
                }

                var labelY = iconY + iconSize + 12;
                var labelX = iconX + (iconSize / 2);
                svg += '<text x="' + labelX + '" y="' + labelY + '" text-anchor="middle" font-size="10" font-weight="500" fill="#1F2937" style="text-shadow: 0 0 3px white, 0 0 3px white;">' + (section.icon_label || section.name) + '</text>';

                svg += '</g>';
                return;
            }

            // Handle DECORATIVE sections (text, line, polygon)
            if (section.section_type === 'decorative') {
                svg += self.renderDecorativeSectionSvg(section);
                svg += '</g>';
                return;
            }

            // Seat size from section metadata (matching admin designer)
            var sectionMeta = section.metadata || {};
            var seatSize = parseInt(sectionMeta.seat_size) || 15;
            var seatRadius = seatSize / 2;
            var seatFontSize = Math.round(seatRadius * 0.85 * 10) / 10;
            var xOff = Math.round(seatRadius * 0.5 * 10) / 10;

            // Section-wide seat X bounds and gap — used to align row labels on a
            // single column per section (matches admin seating preview).
            var allSeatXs = [];
            var seatGap = seatRadius * 3;
            var gapDetected = false;
            if (section.rows) {
                section.rows.forEach(function(_r) {
                    if (!_r.seats) return;
                    _r.seats.forEach(function(_s) {
                        allSeatXs.push(_s.x || 0);
                    });
                    if (!gapDetected && _r.seats.length >= 2) {
                        var sortedXs = _r.seats.map(function(s) { return s.x || 0; }).sort(function(a, b) { return a - b; });
                        seatGap = Math.abs(sortedXs[1] - sortedXs[0]);
                        gapDetected = true;
                    }
                });
            }
            var secMinX = allSeatXs.length > 0 ? Math.min.apply(null, allSeatXs) : 0;
            var secMaxX = allSeatXs.length > 0 ? Math.max.apply(null, allSeatXs) : 0;
            var leftLabelX = section.x + secMinX - seatGap;
            var rightLabelX = section.x + secMaxX + seatGap;
            var rowLabelSize = Math.max(10, Math.round(seatFontSize * 1.1 * 10) / 10);
            var autoShowRowLabels = (sectionMeta.auto_show_row_labels !== false);

            // Render seats using actual x/y coordinates from the layout
            if (section.rows) {
                section.rows.forEach(function(row) {
                    if (!row.seats || row.seats.length === 0) return;

                    // Draw table shape if this row is a table
                    if (row.is_table) {
                        var tableColor2 = section.background_color || '#6B7280';
                        var tcx2 = section.x + (row.center_x || 0);
                        var tcy2 = section.y + (row.center_y || 0);

                        if (row.table_type === 'round') {
                            var tr2 = row.radius || 30;
                            svg += '<circle cx="' + tcx2 + '" cy="' + tcy2 + '" r="' + tr2 + '" fill="' + tableColor2 + '" fill-opacity="0.25" stroke="' + tableColor2 + '" stroke-width="1.5" stroke-opacity="0.5"/>';
                        } else {
                            var tw2 = row.table_width || 80;
                            var th2 = row.table_height || 30;
                            svg += '<rect x="' + (tcx2 - tw2/2) + '" y="' + (tcy2 - th2/2) + '" width="' + tw2 + '" height="' + th2 + '" rx="4" fill="' + tableColor2 + '" fill-opacity="0.25" stroke="' + tableColor2 + '" stroke-width="1.5" stroke-opacity="0.5"/>';
                        }
                        svg += '<text x="' + tcx2 + '" y="' + (tcy2 + 4) + '" text-anchor="middle" font-size="10" font-weight="700" fill="rgba(0,0,0,0.4)" class="pointer-events-none select-none">' + row.label + '</text>';
                    }

                    // Row-based ticket type lookup (keep original order — first assigned type determines color)
                    var ticketTypesForRow = self.rowToTicketTypeMap[row.id] || [];
                    var isRowAssigned = ticketTypesForRow.length > 0;

                    // Seat color from the first ticket type assigned to this row
                    var availableSeatColor = isRowAssigned ? self.getTicketTypeColor(ticketTypesForRow[0]) : '#E5E7EB';

                    // Row labels aligned to section-wide leftmost/rightmost seat columns.
                    // Skipped when section opts out via metadata.auto_show_row_labels = false
                    // (organizers can add row names manually as decorative text instead).
                    if (!row.is_table && autoShowRowLabels) {
                        var firstSeat = row.seats[0];
                        if (firstSeat) {
                            var rlY = section.y + firstSeat.y + seatRadius * 0.4;
                            svg += '<text x="' + leftLabelX + '" y="' + rlY + '" text-anchor="end" font-size="' + rowLabelSize + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + row.label + '</text>';
                            svg += '<text x="' + rightLabelX + '" y="' + rlY + '" text-anchor="start" font-size="' + rowLabelSize + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + row.label + '</text>';
                        }
                    }

                    row.seats.forEach(function(seat) {
                        // Use actual x/y positions from layout data
                        var seatCX = section.x + seat.x;
                        var seatCY = section.y + seat.y;

                        var isSelected = self.isSeatSelectedAny(seat.id);
                        var status = seat.status || 'available';
                        var isDisabled = (status === 'disabled' || seat.base_status === 'imposibil');

                        var seatColor, strokeColor, cursor, isClickable;

                        if (isDisabled) {
                            seatColor = '#D1D5DB';
                            strokeColor = '#9CA3AF';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (status === 'blocked') {
                            seatColor = '#D1D5DB';
                            strokeColor = '#9CA3AF';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (!isRowAssigned) {
                            seatColor = '#E5E7EB';
                            strokeColor = '#D1D5DB';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else if (isSelected) {
                            seatColor = '#a51c30';
                            strokeColor = '#7a141f';
                            cursor = 'pointer';
                            isClickable = true;
                        } else if (status === 'available') {
                            seatColor = availableSeatColor;
                            strokeColor = '#fff';
                            cursor = 'pointer';
                            isClickable = true;
                        } else if (status === 'sold' || status === 'held') {
                            seatColor = '#9CA3AF';
                            strokeColor = '#6B7280';
                            cursor = 'not-allowed';
                            isClickable = false;
                        } else {
                            seatColor = '#E5E7EB';
                            strokeColor = '#D1D5DB';
                            cursor = 'not-allowed';
                            isClickable = false;
                        }

                        // Click handler passes row ID for row-based ticket type detection
                        var clickHandler = isClickable ?
                            'onclick="EventPage.toggleSeatAuto(' + row.id + ', ' + seat.id + ', \'' + section.name.replace(/'/g, "\\'") + '\', \'' + row.label + '\', \'' + seat.label + '\', \'' + (seat.seat_uid || '') + '\')"' : '';

                        // Tooltip
                        var tooltipText;
                        if (isDisabled || status === 'blocked') {
                            tooltipText = 'Indisponibil';
                        } else {
                            tooltipText = section.name + ', Rând ' + row.label + ', Loc ' + seat.label;
                            if (ticketTypesForRow.length === 1) {
                                tooltipText += ' (' + ticketTypesForRow[0].name + ')';
                            } else if (ticketTypesForRow.length > 1) {
                                tooltipText += ' (' + ticketTypesForRow.map(function(t) { return t.name; }).join(' / ') + ')';
                            }
                            if (!isRowAssigned) {
                                tooltipText = 'Indisponibil';
                            } else if (status === 'sold') {
                                tooltipText += ' — vândut';
                            } else if (status === 'held') {
                                tooltipText += ' — rezervat';
                            }
                        }

                        // Selected seats: larger radius and font
                        var drawRadius = isSelected ? seatRadius * 1.4 : seatRadius;
                        var drawFontSize = isSelected ? Math.round(seatRadius * 1.2 * 10) / 10 : seatFontSize;
                        var drawStrokeWidth = isSelected ? '1.5' : '0.5';

                        // Render seat as circle at actual position (matching admin layout)
                        svg += '<g class="seat-hover" ' + clickHandler + ' style="cursor: ' + cursor + ';">' +
                            '<title>' + tooltipText + '</title>' +
                            '<circle cx="' + seatCX + '" cy="' + seatCY + '" r="' + drawRadius + '" fill="' + seatColor + '" stroke="' + strokeColor + '" stroke-width="' + drawStrokeWidth + '"/>';

                        // Seat label inside circle
                        if (!isDisabled && status !== 'blocked') {
                            svg += '<text x="' + seatCX + '" y="' + (seatCY + drawRadius * 0.35) + '" text-anchor="middle" font-size="' + drawFontSize + '" font-weight="600" fill="white" class="pointer-events-none select-none">' + seat.label + '</text>';
                        }

                        // X marker for disabled/blocked seats
                        if (isDisabled || status === 'blocked') {
                            svg += '<line x1="' + (seatCX - xOff) + '" y1="' + (seatCY - xOff) + '" x2="' + (seatCX + xOff) + '" y2="' + (seatCY + xOff) + '" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round"/>' +
                                   '<line x1="' + (seatCX + xOff) + '" y1="' + (seatCY - xOff) + '" x2="' + (seatCX - xOff) + '" y2="' + (seatCY + xOff) + '" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round"/>';
                        }

                        svg += '</g>';
                    });
                });
            }

            svg += '</g>';
        });

        svg += '</svg>';
        container.innerHTML = svg;

        this.updateSeatSelectionSummary();
    },

    /**
     * Check if a seat is selected for a ticket type
     */
    isSeatSelected(ticketTypeId, seatId) {
        var seats = this.selectedSeats[ticketTypeId] || [];
        return seats.some(function(s) { return s.id === seatId; });
    },

    /**
     * Check if a seat is selected in ANY ticket type
     */
    isSeatSelectedAny(seatId) {
        var self = this;
        return Object.keys(this.selectedSeats).some(function(ttId) {
            return self.selectedSeats[ttId].some(function(s) { return s.id === seatId; });
        });
    },

    /**
     * Find ticket types for a row (row-based assignment)
     */
    findTicketTypesForRow(rowId) {
        return this.rowToTicketTypeMap[rowId] || [];
    },

    /**
     * Toggle seat selection - auto-detects ticket type from row
     * If row has multiple ticket types, shows a chooser popup
     */
    toggleSeatAuto(rowId, seatId, sectionName, rowLabel, seatLabel, seatUid) {
        var self = this;

        // First check if seat is already selected in any ticket type — if so, deselect
        var existingTtId = null;
        Object.keys(this.selectedSeats).forEach(function(ttId) {
            var seats = self.selectedSeats[ttId] || [];
            if (seats.some(function(s) { return s.id === seatId; })) {
                existingTtId = ttId;
            }
        });

        if (existingTtId) {
            var idx = this.selectedSeats[existingTtId].findIndex(function(s) { return s.id === seatId; });
            if (idx >= 0) {
                this.selectedSeats[existingTtId].splice(idx, 1);
                this.quantities[existingTtId] = this.selectedSeats[existingTtId].length;
            }
            this.refreshSeatingMap();
            return;
        }

        // Find ticket types for this row
        var ticketTypes = this.findTicketTypesForRow(rowId);

        if (!ticketTypes || ticketTypes.length === 0) {
            console.warn('[EventPage] No ticket type found for row:', rowId);
            return;
        }

        if (ticketTypes.length === 1) {
            // Single ticket type — select directly
            this.selectSeatForTicketType(ticketTypes[0], seatId, sectionName, rowLabel, seatLabel, seatUid);
        } else {
            // Multiple ticket types — show chooser
            this.showTicketTypeChooser(ticketTypes, seatId, sectionName, rowLabel, seatLabel, seatUid);
        }
    },

    /**
     * Select a seat for a specific ticket type
     */
    selectSeatForTicketType(tt, seatId, sectionName, rowLabel, seatLabel, seatUid) {
        var ticketTypeId = String(tt.id);

        if (!this.selectedSeats[ticketTypeId]) {
            this.selectedSeats[ticketTypeId] = [];
        }

        this.selectedSeats[ticketTypeId].push({
            id: seatId,
            seat_uid: seatUid,
            section: sectionName,
            row: rowLabel,
            seat: seatLabel
        });

        this.quantities[ticketTypeId] = this.selectedSeats[ticketTypeId].length;
        console.log('[EventPage] Selected seat for', tt.name, ':', this.selectedSeats[ticketTypeId]);
        this.refreshSeatingMap();
    },

    /**
     * Show popup for choosing ticket type when a row has multiple types
     */
    showTicketTypeChooser(ticketTypes, seatId, sectionName, rowLabel, seatLabel, seatUid) {
        var existing = document.getElementById('tt-chooser-popup');
        if (existing) existing.remove();

        var popup = document.createElement('div');
        popup.id = 'tt-chooser-popup';
        popup.className = 'fixed inset-0 z-[9999] flex items-center justify-center';
        popup.style.background = 'rgba(0,0,0,0.4)';

        var html = '<div class="bg-white rounded-xl shadow-2xl p-5 max-w-xs w-full mx-4 mobile:max-w-md">';
        html += '<div class="text-sm font-semibold text-gray-700 mb-1">Alege tipul de bilet</div>';
        html += '<div class="text-xs text-gray-500 mb-2">' + sectionName + ', Rând ' + rowLabel + ', Loc ' + seatLabel + '</div>';
        html += '<div class="text-xs text-blue-600 bg-blue-50 rounded-lg px-3 py-2 mb-3">Acest loc are alocate mai multe tipuri de bilete. Alege-l pe cel care ți se potrivește.</div>';

        var self = this;
        ticketTypes.forEach(function(tt) {
            var color = self.getTicketTypeColor(tt);
            var sn = sectionName.replace(/'/g, "\\'");
            html += '<button onclick="EventPage.chooserSelect(' + tt.id + ', ' + seatId + ', \'' + sn + '\', \'' + rowLabel + '\', \'' + seatLabel + '\', \'' + (seatUid || '') + '\')" ' +
                'class="w-full text-left px-3 py-2.5 mb-1.5 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 flex items-center gap-3 transition-colors" aria-label="Alege tipul de bilet: ' + tt.name + '">' +
                '<span class="w-4 h-4 rounded-full flex-shrink-0" style="background:' + color + '"></span>' +
                '<span class="text-sm font-medium text-gray-800">' + tt.name + '</span>' +
                '<span class="text-xs text-gray-500 ml-auto">' + tt.price.toFixed(2) + ' lei</span>' +
                '</button>';
        });

        html += '<button onclick="document.getElementById(\'tt-chooser-popup\').remove()" class="w-full text-center text-xs text-gray-400 mt-2 py-1 hover:text-gray-600 transition-colors" aria-label="Anulează">Anulează</button>';
        html += '</div>';

        popup.innerHTML = html;
        popup.addEventListener('click', function(e) {
            if (e.target === popup) popup.remove();
        });
        document.body.appendChild(popup);
    },

    /**
     * Handle ticket type selection from chooser popup
     */
    chooserSelect(ticketTypeId, seatId, sectionName, rowLabel, seatLabel, seatUid) {
        var popup = document.getElementById('tt-chooser-popup');
        if (popup) popup.remove();

        var tt = this.ticketTypes.find(function(t) { return t.id === ticketTypeId; });
        if (tt) {
            this.selectSeatForTicketType(tt, seatId, sectionName, rowLabel, seatLabel, seatUid);
        }
    },

    /**
     * Refresh seating map and all related panels
     */
    refreshSeatingMap() {
        var allAssignedSectionIds = Object.keys(this.sectionToTicketTypeMap).map(function(id) { return parseInt(id); });
        this.renderSeatingMapAllSections(allAssignedSectionIds);
        this.renderSelectedTicketsPanel();
        this.renderModalTicketTypes(this.currentTicketTypeId);
        this.updateSeatSelectionSummary();
    },

    /**
     * Toggle seat selection (old method - kept for compatibility)
     */
    toggleSeat(ticketTypeId, seatId, sectionName, rowLabel, seatLabel, seatUid) {
        var self = this;
        if (!this.selectedSeats[ticketTypeId]) {
            this.selectedSeats[ticketTypeId] = [];
        }

        var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });

        var existingIndex = this.selectedSeats[ticketTypeId].findIndex(function(s) { return s.id === seatId; });

        if (existingIndex >= 0) {
            // Deselect
            this.selectedSeats[ticketTypeId].splice(existingIndex, 1);
        } else {
            // Select - no limit, user can select as many as they want
            this.selectedSeats[ticketTypeId].push({
                id: seatId,
                seat_uid: seatUid, // Include seat_uid for API calls
                section: sectionName,
                row: rowLabel,
                seat: seatLabel
            });
        }

        // Update quantities to match selected seats count
        this.quantities[ticketTypeId] = this.selectedSeats[ticketTypeId].length;

        console.log('[EventPage] Selected seats:', this.selectedSeats[ticketTypeId]);

        // Re-render map to update selection visual
        var allowedSectionIds = tt.seating_sections.map(function(s) { return s.id; });
        this.renderSeatingMap(allowedSectionIds, ticketTypeId);

        // Update sidebar to show selection count
        this.renderModalTicketTypes(ticketTypeId);
    },

    /**
     * Update seat selection summary in modal footer
     */
    updateSeatSelectionSummary() {
        var self = this;
        var totalSeats = 0;
        var totalPrice = 0;
        var seatDetails = [];

        // Calculate totals across ALL ticket types
        Object.keys(this.selectedSeats).forEach(function(ttId) {
            var seats = self.selectedSeats[ttId] || [];
            var tt = self.ticketTypes.find(function(t) { return String(t.id) === String(ttId); });
            var price = tt ? tt.price : 0;

            totalSeats += seats.length;
            totalPrice += seats.length * price;

            seats.forEach(function(s) {
                seatDetails.push({
                    ticketType: tt ? tt.name : 'Bilet',
                    section: s.section,
                    row: s.row,
                    seat: s.seat
                });
            });
        });

        // Update desktop sidebar
        var desktopCount = document.getElementById('selected-seats-count');
        var desktopTotal = document.getElementById('selected-seats-total');
        if (desktopCount) desktopCount.textContent = totalSeats;
        if (desktopTotal) desktopTotal.textContent = totalPrice.toFixed(2) + ' lei';

        // Update mobile bottom bar
        var mobileCount = document.getElementById('mobile-seats-count');
        var mobileTotal = document.getElementById('mobile-seats-total');
        var mobileBuyBtn = document.getElementById('mobile-seats-buy-btn');
        if (mobileCount) mobileCount.textContent = totalSeats + (totalSeats === 1 ? ' loc selectat' : ' locuri selectate');
        if (mobileTotal) mobileTotal.textContent = totalPrice.toFixed(2) + ' lei';
        if (mobileBuyBtn) {
            mobileBuyBtn.textContent = totalSeats > 0 ? 'Cumpără bilete' : 'Selectează locuri';
            mobileBuyBtn.disabled = totalSeats === 0;
            mobileBuyBtn.style.opacity = totalSeats > 0 ? '1' : '0.5';
        }
        // Show "Vezi bilete ↑" hint on the bottom-bar summary only after at least
        // one seat is picked, so the user knows that summary is tap-able.
        var mobileHint = document.getElementById('seat-mobile-selected-hint');
        if (mobileHint) {
            mobileHint.classList.toggle('hidden', totalSeats === 0);
            mobileHint.classList.toggle('inline-flex', totalSeats > 0);
        }

        // Update header count for desktop sidebar
        var headerCount = document.getElementById('selected-tickets-count-header');
        if (headerCount) headerCount.textContent = totalSeats + (totalSeats === 1 ? ' bilet' : ' bilete');

        // Update title & subtitle
        var title = document.getElementById('seat-selection-title');
        if (title) {
            title.textContent = totalSeats > 0 ? 'Ai selectat ' + totalSeats + (totalSeats === 1 ? ' loc' : ' locuri') : 'Alege locurile';
        }
        var subtitle = document.getElementById('seat-selection-subtitle');
        if (seatDetails.length > 0) {
            var details = seatDetails.map(function(d) {
                return d.section + ', rând ' + d.row + ', loc ' + d.seat;
            }).slice(0, 3).join('; ');
            if (seatDetails.length > 3) {
                details += ' și încă ' + (seatDetails.length - 3) + ' locuri';
            }
            subtitle.textContent = 'Selectate: ' + details;
        } else {
            subtitle.textContent = 'Selectează locurile dorite pe hartă';
        }
    },

    /** 
     * Close seat selection modal - syncs quantities with selected seats
     */
    closeSeatSelection() {
        var self = this;

        // Sync quantities for all ticket types based on selected seats
        Object.keys(this.selectedSeats).forEach(function(ttId) {
            self.quantities[ttId] = self.selectedSeats[ttId].length;
        });

        // Hide modal
        document.getElementById('seat-selection-modal').classList.add('hidden');
        document.body.style.overflow = '';

        // Re-render ticket types to show updated selections
        this.renderTicketTypes();

        // Update cart summary
        this.updateCart();

        // Update checkout button
        this.updateCheckoutButton();

        console.log('[EventPage] Modal closed, quantities synced:', this.quantities);
    },

    /**
     * Confirm seat selection, add to cart, and redirect to cart page
     */
    async confirmSeatSelection() {
        var self = this;

        // Check if any seats are selected across all ticket types
        var totalSeats = 0;
        Object.keys(this.selectedSeats).forEach(function(ttId) {
            totalSeats += self.selectedSeats[ttId].length;
        });

        if (totalSeats === 0) {
            alert('Te rugăm să selectezi cel puțin un loc.');
            return;
        }

        // Update quantities for all ticket types based on selected seats
        Object.keys(this.selectedSeats).forEach(function(ttId) {
            self.quantities[ttId] = self.selectedSeats[ttId].length;
        });

        // Hide modal (don't call closeSeatSelection to avoid double-syncing)
        document.getElementById('seat-selection-modal').classList.add('hidden');
        document.body.style.overflow = '';

        // Re-render ticket types to show updated count
        this.renderTicketTypes();

        // Update cart display
        this.updateCart();

        // Add to cart and redirect
        console.log('[EventPage] Seats confirmed, total:', totalSeats);
        console.log('[EventPage] Selected seats by ticket type:', this.selectedSeats);
        console.log('[EventPage] Quantities:', this.quantities);

        // IMPORTANT: Clear existing items for this event from cart first
        // This prevents the "adding to existing quantity" problem
        this.clearEventFromCart();

        // Add tickets to cart with seat information (wait for API to complete)
        await this.addToCartWithSeats();

        // Redirect to cart page (only after seats are successfully held)
        window.location.href = '/cos';
    },

    /**
     * Clear all items for the current event from the cart
     */
    clearEventFromCart() {
        if (!this.event || !this.event.id) return;

        var eventId = this.event.id;
        var items = AmbiletCart.getItems();
        var filteredItems = items.filter(function(item) {
            return item.eventId !== eventId;
        });

        // Only save if something was removed
        if (filteredItems.length !== items.length) {
            AmbiletCart.save(filteredItems);
            console.log('[EventPage] Cleared', items.length - filteredItems.length, 'items for event', eventId);
        }
    },

    /**
     * Add to cart with seat information (for seated events)
     * Calls API to hold seats before adding to local cart
     */
    async addToCartWithSeats() {
        var self = this;
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';
        var eventSeatingId = this.seatingLayout?.event_seating_id;

        console.log('[EventPage] addToCartWithSeats called');
        console.log('[EventPage] Selected seats:', this.selectedSeats);
        console.log('[EventPage] Event seating ID:', eventSeatingId);

        // Build event data once
        var targetPrice = self.event.target_price ? parseFloat(self.event.target_price) : null;
        var eventData = {
            id: self.event.id,
            title: self.event.title,
            slug: self.event.slug,
            start_date: self.event.start_date || self.event.date,
            start_time: self.event.start_time,
            image: self.event.image,
            venue: self.event.venue,
            taxes: self.event.taxes || [],
            target_price: targetPrice,
            commission_rate: commissionRate,
            commission_mode: commissionMode,
            preview_token: self.previewToken || null
        };

        // Get cart once before the loop
        var cart = AmbiletCart.getCart();

        // For each ticket type with selected seats, hold via API then add to cart
        var ticketTypeIds = Object.keys(this.selectedSeats);

        for (var i = 0; i < ticketTypeIds.length; i++) {
            var ticketTypeId = ticketTypeIds[i];
            var seats = self.selectedSeats[ticketTypeId];
            if (!seats || seats.length === 0) continue;

            var tt = self.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
            if (!tt) {
                console.warn('[EventPage] Ticket type not found:', ticketTypeId);
                continue;
            }

            var basePrice = tt.price;
            var baseOriginalPrice = tt.original_price;

            if (targetPrice && basePrice < targetPrice) {
                baseOriginalPrice = targetPrice;
            }

            var ticketTypeData = {
                id: tt.id,
                name: tt.name,
                price: basePrice,
                original_price: baseOriginalPrice,
                description: tt.description,
                commission: tt.commission || null,
                is_refundable: tt.is_refundable || false
            };

            // Extract seat_uids for API call
            var seatUids = seats.map(function(s) { return s.seat_uid; }).filter(function(uid) { return uid; });

            // If we have event_seating_id and seat_uids, call API to hold seats
            if (eventSeatingId && seatUids.length > 0) {
                try {
                    console.log('[EventPage] Calling API to hold seats:', seatUids);

                    var apiPayload = {
                        event_id: self.event.id,
                        ticket_type_id: tt.id,
                        event_seating_id: eventSeatingId,
                        seat_uids: seatUids,
                        seats: seats.map(function(s) {
                            return {
                                seat_uid: s.seat_uid,
                                section_name: s.section,
                                row_label: s.row,
                                seat_label: s.seat
                            };
                        })
                    };
                    if (self.previewToken) {
                        apiPayload.preview_token = self.previewToken;
                    }
                    var response = await AmbiletAPI.post('/cart/items/with-seats', apiPayload);

                    if (response.success && response.data) {
                        console.log('[EventPage] Seats held successfully:', response.data);

                        // Get hold expiry from API response
                        var holdExpiresAt = response.data.hold_expires_at || null;

                        // Update cart items from API response (seats are now held server-side)
                        var itemKey = self.event.id + '_' + tt.id;
                        cart.items = cart.items.filter(function(item) { return item.key !== itemKey; });

                        cart.items.push({
                            key: itemKey,
                            eventId: self.event.id,
                            event: {
                                id: eventData.id,
                                title: eventData.title,
                                slug: eventData.slug,
                                date: eventData.start_date,
                                time: eventData.start_time,
                                image: eventData.image,
                                venue: eventData.venue,
                                city: eventData.venue?.city,
                                taxes: eventData.taxes,
                                target_price: eventData.target_price,
                                commission_rate: eventData.commission_rate,
                                commission_mode: eventData.commission_mode,
                                preview_token: eventData.preview_token
                            },
                            ticketTypeId: tt.id,
                            ticketType: {
                                id: ticketTypeData.id,
                                name: ticketTypeData.name,
                                price: ticketTypeData.price,
                                originalPrice: ticketTypeData.original_price,
                                description: ticketTypeData.description,
                                commission: ticketTypeData.commission || null,
                                is_refundable: ticketTypeData.is_refundable || false
                            },
                            quantity: seats.length,
                            seats: seats,
                            seat_uids: seatUids,
                            event_seating_id: eventSeatingId,
                            hold_expires_at: holdExpiresAt,
                            addedAt: new Date().toISOString()
                        });
                    } else {
                        // Handle API error — surface the server's message so
                        // "some seats no longer available" isn't hidden behind
                        // a generic banner.
                        var errorMsg = response.message || 'Locurile selectate nu mai sunt disponibile';
                        console.error('[EventPage] Failed to hold seats:', errorMsg, response.data);
                        alert(errorMsg);
                        // Refresh the map so the offending seats switch to the
                        // correct (held/sold) colour and the user can pick
                        // other ones immediately.
                        self.refreshSeatStatuses().then(function () {
                            if (self.seatSelectionModal) {
                                self.renderSeatingMapAllSections(Object.keys(self.sectionToTicketTypeMap || {}).map(function (id) { return parseInt(id); }));
                            }
                        });
                        return; // Stop processing
                    }
                } catch (error) {
                    // AmbiletAPI throws APIError for non-2xx responses — its
                    // .message comes from the server body, so prefer it over
                    // the opaque generic string.
                    console.error('[EventPage] API error holding seats:', error, error && error.data);
                    var serverMsg = (error && typeof error.message === 'string' && error.message.length > 0 && error.message !== 'An error occurred')
                        ? error.message
                        : 'A apărut o eroare la rezervarea locurilor. Vă rugăm încercați din nou.';
                    // Append the specific seats that couldn't be held when the
                    // server provided them, so the user sees which ones to swap.
                    var unavailable = error && error.data && error.data.unavailable_seats;
                    if (Array.isArray(unavailable) && unavailable.length > 0) {
                        var labels = unavailable.map(function (uid) {
                            var match = seats.find(function (s) { return s.seat_uid === uid; });
                            return match ? (match.section + ' · Rând ' + match.row + ' · Loc ' + match.seat) : uid;
                        });
                        serverMsg += '\n\nLocuri indisponibile: ' + labels.join(', ');
                    }
                    alert(serverMsg);
                    // Refresh map so already-held seats show as unavailable.
                    self.refreshSeatStatuses().then(function () {
                        if (self.seatSelectionModal) {
                            self.renderSeatingMapAllSections(Object.keys(self.sectionToTicketTypeMap || {}).map(function (id) { return parseInt(id); }));
                        }
                    });
                    return; // Stop processing
                }
            } else {
                // Fallback: no event_seating_id (shouldn't happen for seated events)
                console.warn('[EventPage] No event_seating_id, adding to cart without API hold');

                var itemKey = self.event.id + '_' + tt.id;
                cart.items = cart.items.filter(function(item) { return item.key !== itemKey; });

                cart.items.push({
                    key: itemKey,
                    eventId: self.event.id,
                    event: {
                        id: eventData.id,
                        title: eventData.title,
                        slug: eventData.slug,
                        date: eventData.start_date,
                        time: eventData.start_time,
                        image: eventData.image,
                        venue: eventData.venue,
                        city: eventData.venue?.city,
                        taxes: eventData.taxes,
                        target_price: eventData.target_price,
                        commission_rate: eventData.commission_rate,
                        commission_mode: eventData.commission_mode,
                        preview_token: eventData.preview_token
                    },
                    ticketTypeId: tt.id,
                    ticketType: {
                        id: ticketTypeData.id,
                        name: ticketTypeData.name,
                        price: ticketTypeData.price,
                        originalPrice: ticketTypeData.original_price,
                        description: ticketTypeData.description,
                        commission: ticketTypeData.commission || null,
                        is_refundable: ticketTypeData.is_refundable || false
                    },
                    quantity: seats.length,
                    seats: seats,
                    addedAt: new Date().toISOString()
                });
            }
        }

        // Save the cart once after all items are added
        AmbiletCart.save(cart.items);

        // Start reservation timer (15 minutes)
        if (typeof AmbiletCart.startReservationTimer === 'function') {
            AmbiletCart.startReservationTimer();
        }

        console.log('[EventPage] Cart after adding seats:', AmbiletCart.getCart());
    },

    /**
     * Load and inject organizer-specific tracking scripts
     */
    async loadOrganizerTracking(organizerId) {
        try {
            var response = await fetch(
                window.AMBILET.apiUrl + '?action=tracking.organizer-scripts&organizer_id=' + organizerId
            );
            var data = await response.json();
            if (!data.success || !data.data) return;

            // Inject head scripts
            if (data.data.head_scripts) {
                var headDiv = document.createElement('div');
                headDiv.innerHTML = data.data.head_scripts;
                var scripts = headDiv.querySelectorAll('script');
                scripts.forEach(function(origScript) {
                    var newScript = document.createElement('script');
                    if (origScript.src) {
                        newScript.src = origScript.src;
                        newScript.async = true;
                    } else {
                        newScript.textContent = origScript.textContent;
                    }
                    document.head.appendChild(newScript);
                });
                // Append noscript/img elements too
                headDiv.querySelectorAll('noscript').forEach(function(el) {
                    document.head.appendChild(el.cloneNode(true));
                });
            }

            // Inject body scripts
            if (data.data.body_scripts) {
                var bodyDiv = document.createElement('div');
                bodyDiv.innerHTML = data.data.body_scripts;
                var bodyScripts = bodyDiv.querySelectorAll('script');
                bodyScripts.forEach(function(origScript) {
                    var newScript = document.createElement('script');
                    if (origScript.src) {
                        newScript.src = origScript.src;
                        newScript.async = true;
                    } else {
                        newScript.textContent = origScript.textContent;
                    }
                    document.body.appendChild(newScript);
                });
            }

            console.log('[EventPage] Organizer tracking scripts loaded for organizer #' + organizerId);
        } catch (e) {
            // Silently fail - tracking should never break the page
            console.warn('[EventPage] Failed to load organizer tracking:', e.message);
        }
    },

    escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make available globally
window.EventPage = EventPage;
