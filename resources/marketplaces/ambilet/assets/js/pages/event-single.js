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
                slug: artistsData[0].slug
            } : null,
            artists: artistsData,
            ticket_types: ticketTypesData.map(function(tt) {
                var available = tt.available_quantity !== undefined ? tt.available_quantity : (tt.available !== undefined ? tt.available : 999);
                console.log('[EventPage] Processing ticket type:', tt.name, 'price:', tt.price, 'original_price:', tt.original_price);
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
                    is_sold_out: available <= 0
                };
            }),
            max_tickets_per_order: eventData.max_tickets_per_order || 10,
            target_price: eventData.target_price || null,
            commission_rate: apiData.commission_rate || 5,
            commission_mode: apiData.commission_mode || 'included',
            taxes: apiData.taxes || []
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
        const mainImg = e.image || e.images?.[0] || '/assets/images/placeholder-event.jpg';
        document.getElementById(this.elements.mainImage).src = mainImg;
        document.getElementById(this.elements.mainImage).alt = e.title;

        // Gallery
        this.galleryImages = e.images?.length ? e.images : [mainImg];
        this.renderGallery();

        // Badges
        this.renderBadges(e);

        // Title
        document.getElementById(this.elements.eventTitle).textContent = e.title;

        // Date
        this.renderDate(e);

        // Time
        document.getElementById(this.elements.eventTime).textContent = 'Acces: ' + (e.start_time || '20:00');
        document.getElementById(this.elements.eventDoors).textContent = 'Deschidere usi: ' + (e.doors_time || '19:00');

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
        if (e.category) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-accent text-white text-xs font-bold rounded-lg uppercase">' + e.category + '</span>');
        }
        if (e.is_popular) {
            badgesHtml.push('<span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">Popular</span>');
        }
        document.getElementById(this.elements.eventBadges).innerHTML = badgesHtml.join('');
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

        var artistImage = artist.image_url || artist.image || '/assets/images/placeholder-artist.jpg';
        var artistLink = artist.slug ? '/artist/' + artist.slug : '#';
        var artistDescription = artist.description || artist.bio || '';

        var html = '<div class="flex flex-col gap-6 md:flex-row">' +
            '<div class="md:w-1/3">' +
                '<a href="' + artistLink + '">' +
                    '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full transition-transform aspect-square rounded-2xl hover:scale-105">' +
                '</a>' +
            '</div>' +
            '<div class="md:w-2/3">' +
                '<div class="flex items-center gap-3 mb-4">' +
                    '<a href="' + artistLink + '" class="text-2xl font-bold text-secondary hover:text-primary">' + artist.name + '</a>' +
                    (artist.verified ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>' : '') +
                '</div>' +
                (artistDescription ? '<p class="mb-4 leading-relaxed text-muted">' + artistDescription + '</p>' : '<p class="mb-4 leading-relaxed text-muted">Detalii despre artist vor fi disponibile in curand.</p>') +
                '<a href="' + artistLink + '" class="inline-flex items-center gap-2 font-semibold text-primary">' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>' +
                    'Vezi profilul artistului' +
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
                '<img src="' + (venue.image || '/assets/images/placeholder-venue.jpg') + '" alt="' + venue.name + '" class="object-cover w-full h-64 mb-4 rounded-2xl">' +
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
            html += '<a href="' + googleMapsUrl + '" target="_blank" class="inline-flex items-center gap-2 font-semibold text-primary">' +
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

        container.innerHTML = this.ticketTypes.map(function(tt) {
            self.quantities[tt.id] = 0;
            const isSoldOut = tt.is_sold_out || tt.available <= 0;

            // Calculate display price based on commission mode
            var displayPrice = tt.price;
            if (commissionMode === 'added_on_top') {
                displayPrice = tt.price + (tt.price * commissionRate / 100);
            }

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
                // Fall back to original_price from ticket type
                hasDiscount = true;
                crossedOutPrice = commissionMode === 'added_on_top'
                    ? tt.original_price + (tt.original_price * commissionRate / 100)
                    : tt.original_price;
                discountPercent = Math.round((1 - displayPrice / crossedOutPrice) * 100);
            }

            console.log('[EventPage] Rendering ticket:', tt.name,
                'displayPrice:', displayPrice.toFixed(2),
                'targetPrice:', targetPrice,
                'hasTargetDiscount:', hasTargetDiscount,
                'hasTicketDiscount:', hasTicketDiscount,
                'crossedOutPrice:', crossedOutPrice,
                'discountPercent:', discountPercent + '%');

            // Availability display
            let availabilityHtml = '';
            if (isSoldOut) {
                availabilityHtml = '<span class="text-xs font-semibold text-gray-400">Indisponibil</span>';
            } else if (tt.available <= 5) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">🔥 Ultimele ' + tt.available + ' disponibile</span>';
            } else if (tt.available <= 20) {
                availabilityHtml = '<span class="text-xs font-semibold text-accent">🔥 Doar ' + tt.available + ' disponibile</span>';
            } else if (tt.available < 40) {
                availabilityHtml = '<span class="text-xs font-semibold text-success">⚡ ' + tt.available + ' disponibile</span>';
            } else {
                availabilityHtml = '<span class="text-xs font-semibold text-success">✓ Disponibil</span>';
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

            // Tooltip HTML
            var tooltipHtml = '<p class="mb-2 text-sm font-semibold">Detalii pret bilet:</p><div class="space-y-1 text-xs">';
            if (commissionMode === 'included') {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + basePrice.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Comision (' + commissionRate + '%):</span><span>' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + tt.price.toFixed(2) + ' lei</span></div>';
            } else {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + tt.price.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Comision (' + commissionRate + '%):</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + displayPrice.toFixed(2) + ' lei</span></div>';
            }
            tooltipHtml += '</div>';

            // Card classes
            var cardClasses = isSoldOut
                ? 'relative z-10 p-4 border-2 ticket-card border-gray-200 rounded-2xl bg-gray-100 cursor-default'
                : 'relative z-10 p-4 border-2 cursor-pointer ticket-card border-border rounded-2xl hover:z-20';
            var titleClasses = isSoldOut ? 'text-gray-400' : 'text-secondary';
            var priceClasses = isSoldOut ? 'text-gray-400 line-through' : 'text-primary';
            var descClasses = isSoldOut ? 'text-gray-400' : 'text-muted';

            // Controls HTML
            var controlsHtml = '';
            if (isSoldOut) {
                controlsHtml = '<span class="text-sm font-medium text-gray-400">Epuizat</span>';
            } else {
                controlsHtml = '<div class="flex items-center gap-2">' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', -1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">-</button>' +
                    '<span id="qty-' + tt.id + '" class="w-8 font-bold text-center">0</span>' +
                    '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', 1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">+</button>' +
                '</div>';
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
        let subtotal = 0;
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';
        var self = this;

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            var tt = this.ticketTypes.find(function(t) { return String(t.id) === String(ticketId); });
            if (tt) {
                var ticketPrice = tt.price;
                if (commissionMode === 'added_on_top') {
                    ticketPrice = tt.price + (tt.price * commissionRate / 100);
                }
                subtotal += qty * ticketPrice;
            }
        }

        // Calculate taxes
        var totalTaxes = 0;
        var taxBreakdown = [];
        var taxes = this.event.taxes || [];

        taxes.forEach(function(tax) {
            var taxAmount = 0;
            if (tax.value_type === 'percent') {
                taxAmount = subtotal * (tax.value / 100);
            } else if (tax.value_type === 'fixed') {
                taxAmount = tax.value;
            }
            totalTaxes += taxAmount;
            taxBreakdown.push({ name: tax.name, amount: taxAmount, value: tax.value, value_type: tax.value_type });
        });

        const total = subtotal + totalTaxes;
        const points = Math.floor(subtotal / 10);

        this.updateHeaderCart();

        const cartSummary = document.getElementById(this.elements.cartSummary);
        const emptyCart = document.getElementById(this.elements.emptyCart);

        if (totalTickets > 0) {
            cartSummary.classList.remove('hidden');
            emptyCart.classList.add('hidden');

            document.getElementById(this.elements.subtotal).textContent = subtotal.toFixed(2) + ' lei';

            // Render taxes
            var taxesContainer = document.getElementById(this.elements.taxesContainer);
            if (taxesContainer) {
                var taxesHtml = '';
                taxBreakdown.forEach(function(tax) {
                    var rateLabel = tax.value_type === 'percent' ? '(' + tax.value + '%)' : '';
                    taxesHtml += '<div class="flex justify-between text-sm"><span class="text-muted">' + tax.name + ' ' + rateLabel + ':</span><span class="font-medium">' + tax.amount.toFixed(2) + ' lei</span></div>';
                });
                taxesContainer.innerHTML = taxesHtml;
            }

            document.getElementById(this.elements.totalPrice).textContent = total.toFixed(2) + ' lei';

            const pointsEl = document.getElementById(this.elements.pointsEarned);
            pointsEl.innerHTML = points + ' puncte';
            //pointsEl.textContent = points;
            pointsEl.classList.remove('points-counter');
            void pointsEl.offsetWidth;
            pointsEl.classList.add('points-counter');
        } else {
            cartSummary.classList.add('hidden');
            emptyCart.classList.remove('hidden');
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

                    // Calculate final price including commission when added_on_top
                    var finalPrice = tt.price;
                    var finalOriginalPrice = tt.original_price;
                    if (commissionMode === 'added_on_top') {
                        finalPrice = tt.price + (tt.price * commissionRate / 100);
                        if (tt.original_price) {
                            finalOriginalPrice = tt.original_price + (tt.original_price * commissionRate / 100);
                        }
                    }

                    // Use target_price as original_price if applicable
                    // (when target_price exists and is greater than the display price)
                    if (targetPrice && finalPrice < targetPrice) {
                        finalOriginalPrice = targetPrice;
                    }

                    var ticketTypeData = {
                        id: tt.id,
                        name: tt.name,
                        price: finalPrice,
                        original_price: finalOriginalPrice,
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
     * Load related events
     */
    async loadRelatedEvents() {
        try {
            const response = await AmbiletAPI.get('/events', { limit: 8 });
            if (response.success && response.data?.length) {
                const currentId = this.event.id;
                const currentSlug = this.event.slug;
                const filtered = response.data.filter(function(e) {
                    return e.id !== currentId && e.slug !== currentSlug;
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
    }
};

// Make available globally
window.EventPage = EventPage;
