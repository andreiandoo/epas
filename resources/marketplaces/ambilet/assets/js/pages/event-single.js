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
    galleryImages: [],
    isInterested: false,
    shareMenuOpen: false,
    seatingLayout: null,
    selectedSeats: {},  // ticketTypeId -> [seatId, ...]
    seatSelectionModal: null,

    // DOM element IDs
    elements: {
        loadingState: 'loading-state',
        eventContent: 'event-content',
        breadcrumbTitle: 'breadcrumb-title',
        mainImage: 'mainImage',
        eventBadges: 'event-badges',
        galleryThumbs: 'gallery-thumbs',
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
        this.slug = new URLSearchParams(window.location.search).get('slug') ||
                   window.location.pathname.split('/bilete/')[1]?.split('?')[0] || '';

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
                        this.formatCount(response.data.views_count) + ' vizualizari';
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
                    '<button onclick="EventPage.closeLoginPrompt()" class="w-full px-6 py-3 font-medium transition-colors text-muted hover:text-secondary">' +
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
        try {
            const response = await AmbiletAPI.getEvent(this.slug);
            console.log('[EventPage] API Response:', response);
            console.log('[EventPage] === EVENT DATA ===');
            console.log('[EventPage] Event object:', response.data?.event);
            console.log('[EventPage] TARGET_PRICE:', response.data?.event?.target_price);
            console.log('[EventPage] === TAXES ===');
            console.log('[EventPage] Taxes from API:', response.data?.taxes);
            console.log('[EventPage] === TICKET TYPES ===');
            console.log('[EventPage] Ticket types from API:', response.data?.ticket_types);
            if (response.success && response.data) {
                this.event = this.transformApiData(response.data);
                console.log('[EventPage] Transformed event:', this.event);
                console.log('[EventPage] Transformed target_price:', this.event.target_price);
                console.log('[EventPage] Event taxes:', this.event.taxes);
                console.log('[EventPage] Event ticket_types:', this.ticketTypes);
                this.render();
            } else {
                this.showError('Eveniment negasit');
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            if (error.status === 404) {
                this.showError('Eveniment negasit');
            } else {
                this.showError('Eroare la incarcarea evenimentului');
            }
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

        // Parse starts_at to get date and time
        var startsAt = eventData.starts_at ? new Date(eventData.starts_at) : new Date();
        var doorsAt = eventData.doors_open_at ? new Date(eventData.doors_open_at) : null;

        function formatTime(date) {
            if (!date) return null;
            return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }

        var mainImage = eventData.image_url || eventData.cover_image_url || null;
        var coverImage = eventData.cover_image_url || eventData.image_url || null;

        return {
            id: eventData.id,
            title: eventData.name,
            slug: eventData.slug,
            description: eventData.description,
            content: eventData.description,
            short_description: eventData.short_description,
            image: coverImage || mainImage,
            images: [coverImage, mainImage].filter(Boolean).filter(function(v, i, a) { return a.indexOf(v) === i; }),
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
            start_time: formatTime(startsAt),
            doors_time: formatTime(doorsAt),
            is_popular: eventData.is_featured,
            is_featured: eventData.is_featured,
            interested_count: eventData.interested_count || 0,
            views_count: eventData.views_count || 0,
            // Status flags
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
                console.log('[EventPage] Processing ticket type:', tt.name, 'price:', tt.price, 'original_price:', tt.original_price, 'has_seating:', tt.has_seating);
                return {
                    id: tt.id,
                    name: tt.name,
                    description: tt.description,
                    price: tt.price,
                    original_price: tt.original_price || null,
                    discount_percent: tt.discount_percent || null,
                    currency: tt.currency || 'RON',
                    available: available,
                    min_per_order: tt.min_per_order || 1,
                    max_per_order: tt.max_per_order || 10,
                    status: tt.status,
                    is_sold_out: available <= 0,
                    has_seating: tt.has_seating || false,
                    seating_sections: tt.seating_sections || []
                };
            }),
            seating_layout: apiData.seating_layout || null,
            max_tickets_per_order: eventData.max_tickets_per_order || 10,
            target_price: eventData.target_price || null,
            commission_rate: apiData.commission_rate || 5,
            commission_mode: apiData.commission_mode || 'included',
            taxes: apiData.taxes || [],
            // Custom related events
            has_custom_related: eventData.has_custom_related || false,
            custom_related_event_ids: eventData.custom_related_event_ids || [],
            custom_related_events: apiData.custom_related_events || []
        };
    },

    /**
     * Show error state
     */
    showError(message) {
        document.getElementById(this.elements.loadingState).innerHTML =
            '<div class="w-full py-16 text-center">' +
                '<svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<h1 class="mb-4 text-2xl font-bold text-secondary">' + message + '</h1>' +
                '<a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>' +
                    'Inapoi acasa' +
                '</a>' +
            '</div>';
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

        // Main image
        const mainImg = e.image || e.images?.[0] || '/assets/images/default-event.png';
        document.getElementById(this.elements.mainImage).src = mainImg;
        document.getElementById(this.elements.mainImage).alt = e.title;

        // Gallery - include seating map background if available
        this.galleryImages = e.images?.length ? e.images : [mainImg];
        if (e.seating_layout?.background_image) {
            // Add seating map as last gallery image
            this.galleryImages.push(e.seating_layout.background_image);
        }
        this.renderGallery();

        // Badges
        this.renderBadges(e);

        // Status alerts (cancelled, postponed)
        this.renderStatusAlerts(e);

        // Title
        document.getElementById(this.elements.eventTitle).textContent = e.title;

        // Date
        this.renderDate(e);

        // Time
        document.getElementById(this.elements.eventTime).textContent = 'Acces: ' + (e.start_time || '20:00');
        document.getElementById(this.elements.eventDoors).textContent = 'Doors: ' + (e.doors_time || '19:00');

        // Venue
        document.getElementById(this.elements.venueName).textContent = e.venue?.name || e.location || 'Locatie TBA';
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
        document.getElementById(this.elements.eventViews).textContent = this.formatCount(e.views_count || 0) + ' vizualizari';

        // Description
        document.getElementById(this.elements.eventDescription).innerHTML = this.formatDescription(e.description || e.content);

        // Artist section
        if (e.artist || e.artists?.length) {
            this.renderArtist(e.artist || e.artists[0]);
        }

        // Venue section
        this.renderVenue(e.venue || { name: e.location || 'Locatie TBA' });

        // Seating layout
        this.seatingLayout = e.seating_layout || null;
        if (this.seatingLayout) {
            console.log('[EventPage] Event has seating layout:', this.seatingLayout.name);
        }

        // Ticket types
        this.ticketTypes = e.ticket_types || this.getDefaultTicketTypes();
        this.renderTicketTypes();

        // Related events
        this.loadRelatedEvents();
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
        } else if (e.is_sold_out) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-gray-600 text-white text-xs font-bold rounded-lg uppercase">SOLD OUT</span>');
        }

        // Category badge
        if (e.category) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-accent text-white text-xs font-bold rounded-lg uppercase">' + e.category + '</span>');
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
                            ${e.cancel_reason ? '<p class="mt-1 text-sm text-red-700">' + e.cancel_reason + '</p>' : ''}
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
                            ${e.postponed_reason ? '<p class="mt-1 text-sm text-orange-700">' + e.postponed_reason + '</p>' : ''}
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
            const firstSlot = e.multi_slots[0];
            const firstDate = new Date(firstSlot.date);

            if (e.multi_slots.length === 1) {
                // Single slot
                document.getElementById(this.elements.eventDay).textContent = firstDate.getDate();
                document.getElementById(this.elements.eventMonth).textContent = months[firstDate.getMonth()];
                document.getElementById(this.elements.eventWeekday).textContent = weekdays[firstDate.getDay()];
                document.getElementById(this.elements.eventDateFull).textContent =
                    firstDate.getDate() + ' ' + months[firstDate.getMonth()] + ' ' + firstDate.getFullYear();
            } else {
                // Multiple slots
                document.getElementById(this.elements.eventDay).textContent = e.multi_slots.length;
                document.getElementById(this.elements.eventMonth).textContent = 'zile';
                document.getElementById(this.elements.eventWeekday).textContent = 'Mai multe date';

                const dates = e.multi_slots.map(function(slot) {
                    const d = new Date(slot.date);
                    return d.getDate() + ' ' + months[d.getMonth()];
                }).join(', ');
                document.getElementById(this.elements.eventDateFull).textContent = dates;
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
     * Format description (handle HTML vs plain text)
     */
    formatDescription(desc) {
        if (!desc) return '<p class="text-muted">Descriere indisponibila</p>';

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
     * Render gallery thumbnails
     */
    renderGallery() {
        const container = document.getElementById(this.elements.galleryThumbs);
        if (this.galleryImages.length <= 1) {
            container.innerHTML = '';
            return;
        }
        var self = this;
        container.innerHTML = this.galleryImages.slice(0, 4).map(function(img, i) {
            return '<button onclick="EventPage.changeImage(' + i + ')" class="gallery-thumb ' + (i === 0 ? 'active' : '') + ' w-16 h-12 rounded-lg overflow-hidden border-2 border-white/50 opacity-80">' +
                '<img src="' + img + '" class="object-cover w-full h-full">' +
            '</button>';
        }).join('');
    },

    /**
     * Change main gallery image
     */
    changeImage(index) {
        document.getElementById(this.elements.mainImage).src = this.galleryImages[index];
        document.querySelectorAll('.gallery-thumb').forEach(function(thumb, i) {
            thumb.classList.toggle('active', i === index);
        });
    },

    /**
     * Render artist section
     */
    renderArtist(artist) {
        if (!artist) return;
        document.getElementById(this.elements.artistSection).style.display = 'block';

        var artistImage = artist.image_url || artist.image || '/assets/images/default-artist.png';
        var artistLink = artist.slug ? '/artist/' + artist.slug : '#';
        var artistDescription = artist.bio || artist.description || '';

        // Build social links HTML
        var socialLinksHtml = '';
        var socialLinks = artist.social_links || {};
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

        if (Object.keys(socialLinks).length > 0) {
            socialLinksHtml = '<div class="flex items-center gap-3 mb-4">';
            for (var platform in socialLinks) {
                if (socialLinks.hasOwnProperty(platform) && socialIcons[platform]) {
                    socialLinksHtml += '<a href="' + socialLinks[platform] + '" target="_blank" rel="noopener noreferrer" class="p-2 text-muted transition-colors rounded-full bg-surface ' + (socialColors[platform] || 'hover:text-primary') + '" title="' + platform.charAt(0).toUpperCase() + platform.slice(1) + '">' + socialIcons[platform] + '</a>';
                }
            }
            socialLinksHtml += '</div>';
        }

        var html = '<div class="flex flex-col gap-6 md:flex-row">' +
            '<div class="md:w-1/3">' +
                '<a href="' + artistLink + '">' +
                    '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full transition-transform aspect-square rounded-2xl hover:scale-105">' +
                '</a>' +
            '</div>' +
            '<div class="md:w-2/3">' +
                '<div class="flex items-center gap-3 mb-4 mobile:justify-between">' +
                    '<a href="' + artistLink + '" class="text-2xl font-bold text-secondary hover:text-primary">' + artist.name + '</a>' +
                    (artist.verified ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>' : '') +
                '</div>' +
                (artistDescription ? '<p class="mb-4 leading-relaxed text-muted">' + artistDescription + '</p>' : '<p class="mb-4 leading-relaxed text-muted">Detalii despre artist vor fi disponibile in curand.</p>') +
                socialLinksHtml +
                '<a href="' + artistLink + '" class="inline-flex mobile:flex items-center gap-2 font-semibold text-primary border border-primary rounded-md py-2 px-6 mobile:justify-center">' +
                    'Vezi profilul artistului' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>' +
                '</a>' +
            '</div>' +
        '</div>';

        document.getElementById(this.elements.artistContent).innerHTML = html;
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

        var html = '<div class="flex flex-col gap-6 md:flex-row">' +
            '<div class="md:w-1/3">' +
                '<img src="' + (venue.image || '/assets/images/default-venue.png') + '" alt="' + venue.name + '" class="object-cover w-full h-64 mb-4 rounded-2xl">' +
            '</div>' +
            '<div class="md:w-2/3">' +
                '<h3 class="mb-2 text-xl font-bold text-secondary">' + venue.name + '</h3>' +
                '<p class="mb-4 text-muted">' + venueAddress + '</p>' +
                '<p class="mb-4 leading-relaxed text-muted">' + (venue.description || '') + '</p>';

        if (venue.amenities && venue.amenities.length) {
            html += '<div class="mb-6 space-y-3">';
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

        if (googleMapsUrl) {
            html += '<a href="' + googleMapsUrl + '" target="_blank" class="inline-flex items-center gap-2 font-semibold text-primary border border-primary rounded-md py-2 px-6">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                'Deschide in Google Maps' +
            '</a>';
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
    renderTicketTypes() {
        const container = document.getElementById(this.elements.ticketTypes);
        var self = this;
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';
        var targetPrice = this.event.target_price ? parseFloat(this.event.target_price) : null;

        // Check if event is cancelled, postponed, or sold out - disable all ticket purchasing
        var eventIsCancelled = this.event.is_cancelled || false;
        var eventIsPostponed = this.event.is_postponed || false;
        var eventIsSoldOut = this.event.is_sold_out || false;
        var eventDisabled = eventIsCancelled || eventIsPostponed || eventIsSoldOut;

        container.innerHTML = this.ticketTypes.map(function(tt) {
            self.quantities[tt.id] = 0;
            // Force all tickets as unavailable if event is disabled (cancelled/postponed/sold out)
            const isSoldOut = eventDisabled || tt.is_sold_out || tt.available <= 0;

            // Always show base ticket price (without commission on top)
            var displayPrice = tt.price;

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
                availabilityHtml = '<span class="text-xs font-semibold text-accent">🔥 Doar ' + tt.available + ' disponibile</span>';
            } else if (tt.available < 40) {
                availabilityHtml = '<span class="text-xs font-semibold text-success">⚡ ' + tt.available + ' disponibile</span>';
            } else {
                availabilityHtml = '<span class="text-xs font-semibold text-success"></span>';
            }

            // Calculate commission for tooltip (displayPrice already calculated above)
            var basePrice, commissionAmount;
            if (commissionMode === 'included') {
                basePrice = tt.price / (1 + commissionRate / 100);
                commissionAmount = tt.price - basePrice;
            } else {
                basePrice = tt.price;
                commissionAmount = tt.price * (commissionRate / 100);
            }

            // Tooltip HTML - show commission as "Taxe procesare"
            var tooltipHtml = '<p class="mb-2 text-sm font-semibold">Detalii pret bilet:</p><div class="space-y-1 text-xs">';
            if (commissionMode === 'included') {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + basePrice.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Taxe procesare (' + commissionRate + '%):</span><span>' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + tt.price.toFixed(2) + ' lei</span></div>';
            } else {
                // Commission added on top - show base price first, then processing fees
                var totalWithCommission = tt.price + commissionAmount;
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + tt.price.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Taxe procesare (' + commissionRate + '%):</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total la plata:</span><span class="font-semibold">' + totalWithCommission.toFixed(2) + ' lei</span></div>';
            }
            tooltipHtml += '</div>';

            // Card classes
            var cardClasses = isSoldOut
                ? 'relative z-10 p-4 border-2 ticket-card border-gray-200 rounded-2xl bg-gray-100 cursor-default'
                : 'relative z-10 p-4 border-2 cursor-pointer ticket-card border-border rounded-2xl hover:z-20';
            var titleClasses = isSoldOut ? 'text-gray-400' : 'text-secondary';
            var priceClasses = isSoldOut ? 'text-gray-400 line-through' : 'text-primary';
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
                // Quantity controls (always show for available tickets)
                controlsHtml = '<div class="flex items-center gap-2">' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', -1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">-</button>' +
                    '<span id="qty-' + tt.id + '" class="w-8 font-bold text-center">' + currentQty + '</span>' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', 1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">+</button>';

                // Add "Alege locul/locurile" button for seating tickets when quantity > 0
                if (hasSeating && currentQty > 0) {
                    var btnLabel = currentQty === 1 ? 'Alege locul' : 'Alege locurile';
                    if (selectedCount > 0) {
                        btnLabel = selectedCount + ' loc' + (selectedCount > 1 ? 'uri' : '') + ' selectat' + (selectedCount > 1 ? 'e' : '');
                    }
                    controlsHtml += '<button onclick="EventPage.openSeatSelection(\'' + tt.id + '\')" class="flex items-center gap-2 px-3 py-2 ml-2 text-xs font-semibold transition-colors rounded-lg ' +
                        (selectedCount > 0 ? 'bg-green-500 text-white' : 'bg-accent text-white hover:bg-accent/80') + '">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>' +
                        btnLabel +
                    '</button>';
                }

                controlsHtml += '</div>';
            }

            return '<div class="' + cardClasses + '" data-ticket="' + tt.id + '" data-price="' + displayPrice + '">' +
                '<div class="flex items-start justify-between">' +
                    '<div class="relative tooltip-trigger">' +
                        '<h3 class="flex items-center font-bold gap-x-2 ' + titleClasses + ' cursor-help border-muted">' + tt.name +
                            (hasDiscount && !isSoldOut ? '<span class="discount-badge text-white text-[10px] font-bold py-1 px-2 rounded-full">-' + discountPercent + '%</span>' : '') +
                        '</h3>' +
                        '<p class="text-sm ' + descClasses + '">' + (tt.description || '') + '</p>' +
                        (isSoldOut ? '' : '<div class="absolute left-0 z-10 w-64 p-4 mt-2 text-white shadow-xl tooltip top-full bg-secondary rounded-xl">' + tooltipHtml + '</div>') +
                    '</div>' +
                    '<div class="text-right">' +
                        (hasDiscount && !isSoldOut ? '<span class="text-sm line-through text-muted">' + crossedOutPrice.toFixed(0) + ' lei</span>' : '') +
                        '<span class="block text-xl font-bold ' + priceClasses + '">' + displayPrice.toFixed(2) + ' lei</span>' +
                    '</div>' +
                '</div>' +
                '<div class="flex items-center justify-between">' +
                    availabilityHtml +
                    controlsHtml +
                '</div>' +
            '</div>';
        }).join('');
    },

    /**
     * Update ticket quantity
     */
    updateQuantity(ticketId, delta) {
        const tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
        if (!tt) return;

        const newQty = (this.quantities[ticketId] || 0) + delta;
        if (newQty >= 0 && newQty <= tt.available) {
            this.quantities[ticketId] = newQty;
            document.getElementById('qty-' + ticketId).textContent = newQty;

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
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';
        var self = this;
        var ticketBreakdown = [];  // For showing individual ticket lines

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            if (qty <= 0) continue;

            var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
            if (tt) {
                var ticketBasePrice = tt.price;
                var ticketCommission = 0;

                if (commissionMode === 'added_on_top') {
                    ticketCommission = ticketBasePrice * commissionRate / 100;
                }

                baseSubtotal += qty * ticketBasePrice;
                totalCommission += qty * ticketCommission;

                ticketBreakdown.push({
                    name: tt.name,
                    qty: qty,
                    basePrice: ticketBasePrice,
                    lineTotal: qty * ticketBasePrice
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

                // Show commission as "Taxe procesare" only if on top
                if (commissionMode === 'added_on_top' && totalCommission > 0) {
                    breakdownHtml += '<div class="flex justify-between text-sm pt-2 mt-2 border-t border-border">' +
                        '<span class="text-muted">Taxe procesare (' + commissionRate + '%)</span>' +
                        '<span class="font-medium">' + totalCommission.toFixed(2) + ' lei</span>' +
                    '</div>';
                }

                taxesContainer.innerHTML = breakdownHtml;
            }

            document.getElementById(this.elements.totalPrice).textContent = total.toFixed(2) + ' lei';

            const pointsEl = document.getElementById(this.elements.pointsEarned);
            pointsEl.innerHTML = points + ' puncte';
            pointsEl.classList.remove('points-counter');
            void pointsEl.offsetWidth;
            pointsEl.classList.add('points-counter');

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
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';

        console.log('[EventPage] addToCart called');
        console.log('[EventPage] Event taxes to add:', self.event.taxes);

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
                        commission_rate: commissionRate,
                        commission_mode: commissionMode
                    };
                    console.log('[EventPage] eventData for cart:', eventData);

                    // Store BASE price in cart (without commission)
                    // Commission will be calculated at display time in cart/checkout
                    var basePrice = tt.price;
                    var baseOriginalPrice = tt.original_price;

                    // Use target_price as original_price if applicable
                    if (targetPrice && basePrice < targetPrice) {
                        baseOriginalPrice = targetPrice;
                    }

                    var ticketTypeData = {
                        id: tt.id,
                        name: tt.name,
                        price: basePrice,
                        original_price: baseOriginalPrice,
                        description: tt.description
                    };
                    AmbiletCart.addItem(self.event.id, eventData, tt.id, ticketTypeData, qty);
                    addedAny = true;
                }
            }
        }

        if (addedAny) {
            setTimeout(function() {
                if (typeof window.openCartDrawer === 'function') {
                    window.openCartDrawer();
                } else if (typeof AmbiletCart !== 'undefined' && AmbiletCart.openDrawer) {
                    AmbiletCart.openDrawer();
                } else {
                    window.location.href = '/cos';
                }
            }, 300);
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
                '<img src="' + image + '" alt="' + esc(title) + '" class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110" loading="lazy">' +

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
                            '<span class="block text-xs text-muted">de la</span>' +
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
     * Render related events grid using AmbiletEventCard component
     */
    renderRelatedEvents(events) {
        document.getElementById(this.elements.relatedEventsSection).style.display = 'block';

        var category = this.event.category;
        var categorySlug = this.event.category_slug;
        var seeAllLink = document.getElementById(this.elements.seeAllLink);

        if (category && categorySlug) {
            document.getElementById(this.elements.relatedCategoryText).textContent = 'Evenimente similare din categoria ' + category;
            seeAllLink.href = '/evenimente?category=' + encodeURIComponent(categorySlug);
        } else if (category) {
            document.getElementById(this.elements.relatedCategoryText).textContent = 'Evenimente similare din categoria ' + category;
            var slug = category.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-');
            seeAllLink.href = '/evenimente?category=' + encodeURIComponent(slug);
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
     * Open seat selection modal for a ticket type
     */
    openSeatSelection(ticketTypeId) {
        var self = this;
        var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
        if (!tt || !this.seatingLayout) return;

        console.log('[EventPage] Opening seat selection for:', tt.name, 'sections:', tt.seating_sections);
        console.log('[EventPage] Seating layout:', this.seatingLayout);

        // Get allowed section IDs for this ticket type
        var allowedSectionIds = tt.seating_sections.map(function(s) { return s.id; });

        // Create modal if not exists
        if (!this.seatSelectionModal) {
            this.createSeatSelectionModal();
        }

        // Store current ticket type for the modal
        this.currentTicketTypeId = ticketTypeId;

        // Clear previous seat selection for this ticket type to avoid duplicates
        this.selectedSeats[ticketTypeId] = [];

        // Reset zoom
        this.mapZoom = 1;

        // Render ticket types sidebar
        this.renderModalTicketTypes(ticketTypeId);

        // Render the seating map
        this.renderSeatingMap(allowedSectionIds, ticketTypeId);

        // Show modal
        document.getElementById('seat-selection-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
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
            '<div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="EventPage.closeSeatSelection()"></div>' +
            '<div class="absolute inset-2 md:inset-4 lg:inset-8 bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden">' +
                // Header
                '<div class="flex items-center justify-between px-4 md:px-6 py-3 border-b border-border">' +
                    '<div>' +
                        '<h2 class="text-lg md:text-xl font-bold text-secondary">Alege locurile tale la ' + (this.event?.title || 'eveniment') + '</h2>' +
                        '<p class="text-xs md:text-sm text-muted" id="seat-selection-subtitle">Selectează locurile dorite pe hartă</p>' +
                    '</div>' +
                    '<button onclick="EventPage.closeSeatSelection()" class="p-2 transition-colors rounded-lg hover:bg-surface">' +
                        '<svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                    '</button>' +
                '</div>' +
                // Content area with sidebar and map
                '<div class="flex-1 flex overflow-hidden">' +
                    // Sidebar - ticket types
                    '<div class="w-64 lg:w-80 border-r border-border bg-white overflow-y-auto hidden md:block" id="seat-modal-sidebar">' +
                        '<div class="p-4">' +
                            '<h3 class="font-bold text-secondary mb-3">Tipuri de bilete</h3>' +
                            '<div id="seat-modal-ticket-types" class="space-y-2"></div>' +
                        '</div>' +
                    '</div>' +
                    // Map container
                    '<div class="flex-1 flex flex-col bg-surface/50 overflow-hidden">' +
                        // Zoom controls
                        '<div class="flex items-center justify-between px-4 py-2 bg-white border-b border-border">' +
                            '<div class="flex items-center gap-2">' +
                                '<button onclick="EventPage.zoomMap(-0.2)" class="p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors" title="Zoom out">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>' +
                                '</button>' +
                                '<span id="zoom-level" class="text-sm font-medium text-muted w-12 text-center">100%</span>' +
                                '<button onclick="EventPage.zoomMap(0.2)" class="p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors" title="Zoom in">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                                '</button>' +
                                '<button onclick="EventPage.resetMapZoom()" class="ml-2 p-2 rounded-lg bg-surface hover:bg-gray-200 transition-colors text-xs" title="Reset zoom">' +
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
                                '</button>' +
                            '</div>' +
                            '<div class="text-xs text-muted">Scroll pentru zoom, drag pentru panoramare</div>' +
                        '</div>' +
                        // Map SVG container
                        '<div class="flex-1 overflow-hidden p-2" id="seat-map-container">' +
                            '<div id="seat-map-wrapper" class="w-full h-full overflow-auto" style="cursor: grab;">' +
                                '<div id="seat-map-svg" class="inline-block min-w-full min-h-full flex items-center justify-center" style="transform-origin: center center;">' +
                                    '<div class="text-center text-muted">' +
                                        '<svg class="w-12 h-12 mx-auto mb-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>' +
                                        'Se încarcă harta...' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                // Legend
                '<div class="px-4 md:px-6 py-2 border-t border-border bg-white">' +
                    '<div class="flex flex-wrap items-center justify-center gap-3 md:gap-4 text-xs md:text-sm">' +
                        '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded bg-green-500"></span> Disponibil</div>' +
                        '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded bg-primary"></span> Selectat</div>' +
                        '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded bg-gray-300"></span> Ocupat</div>' +
                        '<div class="flex items-center gap-1"><span class="w-3 h-3 md:w-4 md:h-4 rounded bg-gray-100 border border-gray-300"></span> Indisponibil</div>' +
                    '</div>' +
                '</div>' +
                // Footer
                '<div class="flex flex-col md:flex-row items-center justify-between px-4 md:px-6 py-3 border-t border-border bg-white gap-3">' +
                    '<div class="text-center md:text-left">' +
                        '<span class="text-sm text-muted">Locuri selectate: </span>' +
                        '<span id="selected-seats-count" class="font-bold text-secondary">0</span>' +
                        '<span class="mx-2 text-muted">|</span>' +
                        '<span class="text-sm text-muted">Total: </span>' +
                        '<span id="selected-seats-total" class="font-bold text-primary">0 lei</span>' +
                    '</div>' +
                    '<div class="flex gap-2 md:gap-3">' +
                        '<button onclick="EventPage.closeSeatSelection()" class="px-4 md:px-6 py-2 text-sm font-semibold text-muted border border-border rounded-xl hover:bg-surface transition-colors">Anulează</button>' +
                        '<button onclick="EventPage.confirmSeatSelection()" class="px-4 md:px-6 py-2 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary-dark transition-colors">Comandă</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        this.seatSelectionModal = modal;

        // Setup zoom with mouse wheel
        var mapWrapper = document.getElementById('seat-map-wrapper');
        mapWrapper.addEventListener('wheel', function(e) {
            e.preventDefault();
            var delta = e.deltaY > 0 ? -0.1 : 0.1;
            self.zoomMap(delta);
        }, { passive: false });

        // Setup pan with mouse drag
        var isDragging = false;
        var startX, startY, scrollLeft, scrollTop;

        mapWrapper.addEventListener('mousedown', function(e) {
            isDragging = true;
            mapWrapper.style.cursor = 'grabbing';
            startX = e.pageX - mapWrapper.offsetLeft;
            startY = e.pageY - mapWrapper.offsetTop;
            scrollLeft = mapWrapper.scrollLeft;
            scrollTop = mapWrapper.scrollTop;
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
            var x = e.pageX - mapWrapper.offsetLeft;
            var y = e.pageY - mapWrapper.offsetTop;
            var walkX = (x - startX) * 1.5;
            var walkY = (y - startY) * 1.5;
            mapWrapper.scrollLeft = scrollLeft - walkX;
            mapWrapper.scrollTop = scrollTop - walkY;
        });
    },

    /**
     * Zoom the map
     */
    zoomMap(delta) {
        this.mapZoom = Math.max(0.5, Math.min(3, this.mapZoom + delta));
        var svgContainer = document.getElementById('seat-map-svg');
        if (svgContainer) {
            svgContainer.style.transform = 'scale(' + this.mapZoom + ')';
        }
        document.getElementById('zoom-level').textContent = Math.round(this.mapZoom * 100) + '%';
    },

    /**
     * Reset map zoom
     */
    resetMapZoom() {
        this.mapZoom = 1;
        var svgContainer = document.getElementById('seat-map-svg');
        if (svgContainer) {
            svgContainer.style.transform = 'scale(1)';
        }
        document.getElementById('zoom-level').textContent = '100%';
    },

    /**
     * Render ticket types in modal sidebar
     */
    renderModalTicketTypes(currentTicketTypeId) {
        var self = this;
        var container = document.getElementById('seat-modal-ticket-types');
        if (!container) return;

        var html = this.ticketTypes.filter(function(tt) {
            return tt.has_seating && !tt.is_sold_out;
        }).map(function(tt) {
            var isActive = String(tt.id) === String(currentTicketTypeId);
            var selectedCount = self.selectedSeats[tt.id]?.length || 0;
            var requiredCount = self.quantities[tt.id] || 0;

            return '<div class="p-3 rounded-xl border-2 cursor-pointer transition-all ' +
                (isActive ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50') + '" ' +
                'onclick="EventPage.switchTicketType(\'' + tt.id + '\')">' +
                '<div class="flex justify-between items-start mb-1">' +
                    '<span class="font-semibold text-secondary text-sm">' + tt.name + '</span>' +
                    '<span class="font-bold text-primary">' + tt.price.toFixed(0) + ' lei</span>' +
                '</div>' +
                '<div class="text-xs text-muted">' + (tt.description || 'Acces general') + '</div>' +
                (requiredCount > 0 ?
                    '<div class="mt-2 text-xs font-medium ' + (selectedCount >= requiredCount ? 'text-green-600' : 'text-accent') + '">' +
                        selectedCount + '/' + requiredCount + ' locuri selectate' +
                    '</div>' : ''
                ) +
            '</div>';
        }).join('');

        container.innerHTML = html || '<p class="text-sm text-muted">Nu există bilete cu locuri pentru acest eveniment.</p>';
    },

    /**
     * Switch to different ticket type in modal
     */
    switchTicketType(ticketTypeId) {
        var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
        if (!tt) return;

        this.currentTicketTypeId = ticketTypeId;
        var allowedSectionIds = tt.seating_sections.map(function(s) { return s.id; });
        this.renderSeatingMap(allowedSectionIds, ticketTypeId);
        this.renderModalTicketTypes(ticketTypeId);
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

        // Calculate viewBox
        var canvasW = layout.canvas_width || 1920;
        var canvasH = layout.canvas_height || 1080;

        // Build SVG with tooltip styles
        var svg = '<svg viewBox="0 0 ' + canvasW + ' ' + canvasH + '" class="w-full h-full" style="min-width: ' + canvasW + 'px; min-height: ' + canvasH + 'px;" preserveAspectRatio="xMidYMid meet">';
        svg += '<style>.seat-hover:hover rect { stroke-width: 3; filter: brightness(1.1); } .seat-hover { transition: all 0.15s; }</style>';

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

            // Calculate rotation transform - Konva rotates around top-left corner (x, y)
            var rotation = section.rotation || 0;
            var transform = rotation !== 0 ? ' transform="rotate(' + rotation + ' ' + section.x + ' ' + section.y + ')"' : '';

            // Create a group for the section with rotation
            svg += '<g' + transform + '>';

            // Section name (positioned ABOVE the section, outside)
            var textY = section.y - 5;
            var textX = section.x + (section.width / 2);
            var textColor = isAllowed ? '#1F2937' : '#9CA3AF';
            svg += '<text x="' + textX + '" y="' + textY + '" text-anchor="middle" font-size="11" font-weight="600" fill="' + textColor + '" style="text-shadow: 0 0 3px white, 0 0 3px white;">' + section.name + '</text>';

            // Render seats for this section
            if (section.rows) {
                var seatSize = 18;
                var seatGap = 3;
                var rowGap = 4;
                var startY = section.y + 4; // Small padding from section top

                section.rows.forEach(function(row, rowIndex) {
                    if (row.seats) {
                        var startX = section.x + 4; // Small padding from section edge

                        row.seats.forEach(function(seat, seatIndex) {
                            // Calculate position - use stored x/y if available, otherwise calculate from index
                            // Use parseFloat to ensure numeric addition (API may return strings)
                            var seatX = seat.x !== null && seat.x !== undefined ? parseFloat(section.x) + parseFloat(seat.x) : startX + seatIndex * (seatSize + seatGap);
                            var seatY = seat.y !== null && seat.y !== undefined ? parseFloat(section.y) + parseFloat(seat.y) : startY + rowIndex * (seatSize + rowGap);

                            var isSelected = self.isSeatSelected(ticketTypeId, seat.id);
                            var status = seat.status || 'available';

                            // Determine seat color and interactivity
                            var seatColor, cursor, isClickable;

                            if (!isAllowed) {
                                // Section not available for this ticket type
                                seatColor = '#E5E7EB'; // Light gray
                                cursor = 'not-allowed';
                                isClickable = false;
                            } else if (isSelected) {
                                seatColor = '#3B82F6'; // Selected - primary blue
                                cursor = 'pointer';
                                isClickable = true;
                            } else if (status === 'available') {
                                seatColor = '#22C55E'; // Available - green
                                cursor = 'pointer';
                                isClickable = true;
                            } else if (status === 'sold' || status === 'held') {
                                seatColor = '#9CA3AF'; // Occupied - darker gray
                                cursor = 'not-allowed';
                                isClickable = false;
                            } else {
                                seatColor = '#E5E7EB'; // Disabled/blocked - light gray
                                cursor = 'not-allowed';
                                isClickable = false;
                            }

                            var clickHandler = isClickable ?
                                'onclick="EventPage.toggleSeat(\'' + ticketTypeId + '\', ' + seat.id + ', \'' + section.name.replace(/'/g, "\\'") + '\', \'' + row.label + '\', \'' + seat.label + '\')"' : '';

                            var strokeColor = isSelected ? '#1D4ED8' : (isAllowed && status === 'available' ? '#16A34A' : '#D1D5DB');
                            var strokeWidth = isSelected ? '2' : '1';

                            // Tooltip text
                            var tooltipText = section.name + ', Rând ' + row.label + ', Loc ' + seat.label;
                            if (!isAllowed) {
                                tooltipText += ' (indisponibil pentru acest bilet)';
                            } else if (status === 'sold') {
                                tooltipText += ' (vândut)';
                            } else if (status === 'held') {
                                tooltipText += ' (rezervat)';
                            }

                            svg += '<g class="seat-hover" ' + clickHandler + ' style="cursor: ' + cursor + '">' +
                                '<title>' + tooltipText + '</title>' +
                                '<rect x="' + seatX + '" y="' + seatY + '" width="' + seatSize + '" height="' + seatSize + '" rx="3" fill="' + seatColor + '" stroke="' + strokeColor + '" stroke-width="' + strokeWidth + '"/>' +
                            '</g>';
                        });
                    }
                });
            }

            svg += '</g>'; // Close section group
        });

        svg += '</svg>';
        container.innerHTML = svg;

        this.updateSeatSelectionSummary(ticketTypeId);
    },

    /**
     * Check if a seat is selected for a ticket type
     */
    isSeatSelected(ticketTypeId, seatId) {
        var seats = this.selectedSeats[ticketTypeId] || [];
        return seats.some(function(s) { return s.id === seatId; });
    },

    /**
     * Toggle seat selection
     */
    toggleSeat(ticketTypeId, seatId, sectionName, rowLabel, seatLabel) {
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
    updateSeatSelectionSummary(ticketTypeId) {
        var seats = this.selectedSeats[ticketTypeId] || [];
        var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketTypeId); });
        var price = tt ? tt.price : 0;

        document.getElementById('selected-seats-count').textContent = seats.length;
        document.getElementById('selected-seats-total').textContent = (seats.length * price).toFixed(2) + ' lei';

        // Update subtitle with selected seats
        var subtitle = document.getElementById('seat-selection-subtitle');
        if (seats.length > 0) {
            var seatNames = seats.map(function(s) { return s.section + ', rând ' + s.row + ', loc ' + s.seat; }).join('; ');
            subtitle.textContent = 'Selectate: ' + seatNames;
        } else {
            subtitle.textContent = 'Selectează locurile dorite pe hartă';
        }
    },

    /**
     * Close seat selection modal
     */
    closeSeatSelection() {
        document.getElementById('seat-selection-modal').classList.add('hidden');
        document.body.style.overflow = '';
    },

    /**
     * Confirm seat selection, add to cart, and redirect to cart page
     */
    confirmSeatSelection() {
        var ticketTypeId = this.currentTicketTypeId;
        var seats = this.selectedSeats[ticketTypeId] || [];

        if (seats.length === 0) {
            alert('Te rugăm să selectezi cel puțin un loc.');
            return;
        }

        // Update quantities based on selected seats
        this.quantities[ticketTypeId] = seats.length;

        // Close modal
        this.closeSeatSelection();

        // Re-render ticket types to show updated count
        this.renderTicketTypes();

        // Update cart display
        this.updateCart();

        // Add to cart and redirect
        console.log('[EventPage] Seats confirmed:', seats.length, 'for ticket type:', ticketTypeId);

        // Add tickets to cart with seat information
        this.addToCart();

        // Redirect to cart page
        window.location.href = '/cos';
    }
};

// Make available globally
window.EventPage = EventPage;
