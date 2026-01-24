/**
 * Ambilet.ro - Venue Single Page Controller
 * Handles venue detail page with events, gallery, info, and similar venues
 *
 * Dependencies: AmbiletAPI, AmbiletEventCard
 */

const VenuePage = {
    // Configuration
    venueSlug: '',
    venue: null,
    isFollowing: false,

    // Month names for date formatting
    monthNames: ['IAN', 'FEB', 'MAR', 'APR', 'MAI', 'IUN', 'IUL', 'AUG', 'SEP', 'OCT', 'NOI', 'DEC'],

    // DOM element IDs
    elements: {
        hero: 'venueHero',
        heroImage: 'heroImage',
        breadcrumbName: 'breadcrumbName',
        venueType: 'venueType',
        venueName: 'venueName',
        venueLocation: 'venueLocation',
        venueCapacity: 'venueCapacity',
        venueEventsCount: 'venueEventsCount',
        venueRating: 'venueRating',
        venueYear: 'venueYear',
        venueAbout: 'venueAbout',
        quickInfo: 'quickInfo',
        venueAddress: 'venueAddress',
        mapsLink: 'mapsLink',
        venueMap: 'venueMap',
        venueAmenities: 'venueAmenities',
        amenitiesSection: 'amenitiesSection',
        venueGallery: 'venueGallery',
        gallerySection: 'gallerySection',
        eventsList: 'eventsList',
        similarVenues: 'similarVenues',
        followBtn: 'follow-btn',
        followIcon: 'follow-icon',
        followText: 'follow-text'
    },

    /**
     * Initialize the page
     */
    async init() {
        // Get slug from window variable (set by PHP from htaccess rewrite)
        this.venueSlug = window.VENUE_SLUG || '';

        if (!this.venueSlug) {
            this.showError('Locația nu a fost găsită');
            return;
        }

        await this.loadVenueData();
        this.loadFollowStatus();
    },

    /**
     * Load venue data from API
     */
    async loadVenueData() {
        try {
            var response = await AmbiletAPI.getVenue(this.venueSlug);
            if (response.success && response.data) {
                this.venue = this.transformApiData(response.data);
                this.render();
            } else {
                this.showError('Locația nu a fost găsită');
            }
        } catch (error) {
            console.error('Failed to load venue:', error);
            if (error.status === 404) {
                this.showError('Locația nu a fost găsită');
            } else {
                this.showError('Eroare la încărcarea datelor');
            }
        }
    },

    /**
     * Transform API response to expected format
     */
    transformApiData(apiData) {
        if (!apiData) {
            return null;
        }

        var self = this;

        // Transform upcoming_events to expected format
        var events = [];
        if (apiData.upcoming_events && apiData.upcoming_events.length > 0) {
            events = apiData.upcoming_events.map(function(e) {
                var date = new Date(e.event_date || e.starts_at);
                return {
                    slug: e.slug,
                    day: String(date.getDate()).padStart(2, '0'),
                    month: self.monthNames[date.getMonth()],
                    category: e.category || 'Eveniment',
                    title: e.title || e.name,
                    time: e.start_time ? e.start_time.substring(0, 5) : '20:00',
                    price: e.min_price || e.price_from || 0,
                    currency: e.currency || 'RON',
                    is_sold_out: e.is_sold_out || false
                };
            });
        }

        // Get primary category as type
        var venueType = 'Locație';
        if (apiData.categories && apiData.categories.length > 0) {
            venueType = apiData.categories[0].name;
        } else if (apiData.type) {
            venueType = apiData.type;
        }

        // Build full address
        var addressParts = [apiData.address];
        if (apiData.city) addressParts.push(apiData.city);
        if (apiData.postal_code) addressParts.push(apiData.postal_code);
        var fullAddress = addressParts.filter(Boolean).join(', ');

        // Format capacity with thousands separator
        var capacityStr = apiData.capacity ? apiData.capacity.toLocaleString('ro-RO') : '-';

        // Similar venues from API or empty
        var similarVenues = [];
        if (apiData.similar_venues && apiData.similar_venues.length > 0) {
            similarVenues = apiData.similar_venues.map(function(v) {
                return {
                    slug: v.slug,
                    name: v.name,
                    location: v.city + (v.capacity ? ' · ' + v.capacity.toLocaleString('ro-RO') + ' locuri' : ''),
                    events: v.events_count || 0,
                    image: v.image || '/assets/images/default_venue.jpg'
                };
            });
        }

        return {
            name: apiData.name || 'Locație necunoscută',
            slug: apiData.slug,
            type: venueType,
            location: apiData.city || '',
            address: fullAddress,
            latitude: apiData.latitude || apiData.lat,
            longitude: apiData.longitude || apiData.lng,
            capacity: capacityStr,
            rating: apiData.rating || '-',
            reviewsCount: apiData.reviews_count || 0,
            yearBuilt: apiData.year_built || '-',
            eventsCount: apiData.events_count || events.length,
            image: apiData.cover_image || apiData.image || '/assets/images/default_venue.jpg',
            description: apiData.description || '',
            phone: apiData.phone || (apiData.contact ? apiData.contact.phone : null) || '-',
            email: apiData.email || (apiData.contact ? apiData.contact.email : null) || '-',
            website: apiData.website || (apiData.contact ? apiData.contact.website : null) || '',
            schedule: apiData.schedule || 'În funcție de evenimente',
            amenities: apiData.amenities || [],
            gallery: apiData.gallery || apiData.images || [],
            events: events,
            // Store raw events for AmbiletEventCard which handles commission
            rawEvents: apiData.upcoming_events || [],
            similarVenues: similarVenues
        };
    },

    /**
     * Show error message
     */
    showError(message) {
        var skeleton = document.querySelector('.skeleton-hero');
        if (skeleton) skeleton.remove();

        var heroSection = document.getElementById(this.elements.hero);
        if (heroSection) {
            heroSection.innerHTML =
                '<div class="flex items-center justify-center h-full bg-gray-100">' +
                    '<div class="text-center">' +
                        '<svg class="w-16 h-16 mx-auto mb-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                            '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>' +
                            '<circle cx="12" cy="10" r="3"/>' +
                        '</svg>' +
                        '<h2 class="text-xl font-bold text-gray-700">' + this.escapeHtml(message) + '</h2>' +
                        '<a href="/locatii" class="inline-block px-6 py-3 mt-4 text-white rounded-lg bg-primary hover:bg-primary-dark">Vezi toate locațiile</a>' +
                    '</div>' +
                '</div>';
        }
    },

    /**
     * Render all venue content
     */
    render() {
        var venue = this.venue;

        // Hero image
        var heroImg = document.getElementById(this.elements.heroImage);
        if (heroImg) {
            heroImg.src = venue.image;
            heroImg.alt = venue.name;
            heroImg.classList.remove('hidden');
        }

        var skeleton = document.querySelector('.skeleton-hero');
        if (skeleton) skeleton.remove();

        // Basic info
        this.setTextContent(this.elements.breadcrumbName, venue.name);
        this.setTextContent(this.elements.venueType, venue.type);
        this.setTextContent(this.elements.venueName, venue.name);
        this.setTextContent(this.elements.venueLocation, venue.location);

        // Update page title
        document.title = venue.name + ' - ' + (window.AMBILET_CONFIG?.SITE_NAME || 'Bilete.online');

        // Info cards
        this.setTextContent(this.elements.venueCapacity, venue.capacity);
        this.setTextContent(this.elements.venueEventsCount, venue.eventsCount);
        this.setTextContent(this.elements.venueRating, venue.rating);
        this.setTextContent(this.elements.venueYear, venue.yearBuilt);

        // About section
        this.renderAbout(venue.description);

        // Quick Info sidebar
        this.renderQuickInfo(venue);

        // Address & Map
        var addressEl = document.getElementById(this.elements.venueAddress);
        if (addressEl) {
            addressEl.innerHTML = this.escapeHtml(venue.address).replace(/, /g, '<br>');
        }

        // Render interactive Google Map
        this.renderMap(venue);

        // Update Google Maps link
        var mapsLink = document.getElementById(this.elements.mapsLink);
        if (mapsLink) {
            if (venue.latitude && venue.longitude) {
                mapsLink.href = 'https://www.google.com/maps/search/?api=1&query=' + venue.latitude + ',' + venue.longitude;
            } else if (venue.address) {
                mapsLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(venue.address);
            }
        }

        // Amenities
        this.renderAmenities(venue.amenities);

        // Gallery
        this.renderGallery(venue.gallery);

        // Events - use rawEvents for AmbiletEventCard which handles commission
        this.renderEvents(venue.rawEvents && venue.rawEvents.length > 0 ? venue.rawEvents : venue.events);

        // Similar venues
        this.renderSimilarVenues(venue.similarVenues);
    },

    /**
     * Helper to set text content safely
     */
    setTextContent(elementId, text) {
        var el = document.getElementById(elementId);
        if (el) el.textContent = text || '';
    },

    /**
     * Render about section
     */
    renderAbout(description) {
        var container = document.getElementById(this.elements.venueAbout);
        if (!container) return;

        if (description) {
            // Check if description contains HTML tags
            var hasHtml = /<[a-z][\s\S]*>/i.test(description);
            if (hasHtml) {
                // Render HTML content directly (trusted from API)
                container.innerHTML = '<div class="prose prose-sm max-w-none text-gray-600">' + description + '</div>';
            } else {
                // Plain text with whitespace preserved
                container.innerHTML = '<p class="text-base leading-relaxed text-gray-600 whitespace-pre-line">' +
                    this.escapeHtml(description) + '</p>';
            }
        } else {
            container.innerHTML = '<p class="italic text-gray-500">Informații despre această locație vor fi disponibile în curând.</p>';
        }
    },

    /**
     * Render quick info sidebar
     */
    renderQuickInfo(venue) {
        var container = document.getElementById(this.elements.quickInfo);
        if (!container) return;

        var html = '';

        // Schedule
        if (venue.schedule && venue.schedule !== '-') {
            html += this.renderInfoRow(
                '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'Program',
                venue.schedule
            );
        }

        // Phone
        if (venue.phone && venue.phone !== '-') {
            html += this.renderInfoRow(
                '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/></svg>',
                'Telefon',
                '<a href="tel:' + this.escapeHtml(venue.phone) + '" class="text-primary">' + this.escapeHtml(venue.phone) + '</a>',
                true
            );
        }

        // Email
        if (venue.email && venue.email !== '-') {
            html += this.renderInfoRow(
                '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                'Email',
                '<a href="mailto:' + this.escapeHtml(venue.email) + '" class="text-primary">' + this.escapeHtml(venue.email) + '</a>',
                true
            );
        }

        // Website
        if (venue.website && venue.website !== '-' && venue.website !== '') {
            var websiteUrl = venue.website.startsWith('http') ? venue.website : 'https://' + venue.website;
            var websiteDisplay = venue.website.replace(/^https?:\/\//, '');
            html += this.renderInfoRow(
                '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
                'Website',
                '<a href="' + this.escapeHtml(websiteUrl) + '" target="_blank" class="text-primary">' + this.escapeHtml(websiteDisplay) + '</a>',
                true,
                false // no border for last item
            );
        }

        if (!html) {
            html = '<p class="py-3 text-sm italic text-gray-500">Informații de contact indisponibile</p>';
        }

        container.innerHTML = html;
    },

    /**
     * Render info row helper
     */
    renderInfoRow(icon, label, value, isHtml, hasBorder) {
        if (hasBorder === undefined) hasBorder = true;
        var borderClass = hasBorder ? 'border-b border-gray-100' : '';
        var valueHtml = isHtml ? value : this.escapeHtml(value);

        return '<div class="flex items-start gap-3.5 py-3.5 ' + borderClass + '">' +
            '<div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-surface rounded-xl text-muted">' + icon + '</div>' +
            '<div>' +
                '<div class="mb-1 text-xs tracking-wide uppercase text-muted">' + label + '</div>' +
                '<div class="text-sm font-semibold text-secondary">' + valueHtml + '</div>' +
            '</div>' +
        '</div>';
    },

    /**
     * Render interactive Google Map
     */
    renderMap(venue) {
        var mapContainer = document.getElementById(this.elements.venueMap);
        if (!mapContainer) return;

        // Build map query - prefer coordinates, fall back to address
        var mapQuery = '';
        if (venue.latitude && venue.longitude) {
            mapQuery = venue.latitude + ',' + venue.longitude;
        } else if (venue.address) {
            mapQuery = encodeURIComponent(venue.address);
        }

        if (mapQuery) {
            // Render Google Maps embed iframe
            mapContainer.innerHTML = '<iframe ' +
                'src="https://www.google.com/maps?q=' + mapQuery + '&output=embed" ' +
                'width="100%" height="100%" ' +
                'style="border:0; min-height:192px;" ' +
                'allowfullscreen="" ' +
                'loading="lazy" ' +
                'referrerpolicy="no-referrer-when-downgrade">' +
            '</iframe>';
        } else {
            // No location data - show placeholder
            mapContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full gap-3 text-muted">' +
                '<svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                    '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>' +
                    '<circle cx="12" cy="10" r="3"/>' +
                '</svg>' +
                '<p class="text-sm">Locație indisponibilă</p>' +
            '</div>';
        }
    },

    /**
     * Render amenities
     */
    renderAmenities(amenities) {
        var container = document.getElementById(this.elements.venueAmenities);
        var section = document.getElementById(this.elements.amenitiesSection);
        if (!container) return;

        // Hide entire section if no amenities
        if (!amenities || amenities.length === 0) {
            if (section) section.style.display = 'none';
            return;
        }

        // Show section if it was hidden
        if (section) section.style.display = '';

        var self = this;
        var html = amenities.map(function(a) {
            return '<div class="flex items-center gap-2.5 p-3 bg-surface rounded-xl">' +
                '<svg class="w-4 h-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg>' +
                '<span class="text-sm font-medium text-gray-600">' + self.escapeHtml(a) + '</span>' +
            '</div>';
        }).join('');

        container.innerHTML = html;
    },

    /**
     * Render gallery
     */
    renderGallery(gallery) {
        var container = document.getElementById(this.elements.venueGallery);
        var section = document.getElementById(this.elements.gallerySection);
        if (!container) return;

        // Hide entire section if no gallery images
        if (!gallery || gallery.length === 0) {
            if (section) section.style.display = 'none';
            return;
        }

        // Show section if it was hidden
        if (section) section.style.display = '';

        var self = this;
        var html = gallery.slice(0, 5).map(function(img, idx) {
            var extraClass = idx === 0 ? 'col-span-2 row-span-2' : '';
            var aspectClass = idx === 0 ? 'aspect-auto' : 'aspect-[4/3]';
            var moreOverlay = (idx === 4 && gallery.length > 5)
                ? '<div class="absolute inset-0 flex items-center justify-center text-lg font-bold text-white bg-black/60">+' + (gallery.length - 5) + '</div>'
                : '';

            return '<div class="' + extraClass + ' relative rounded-xl overflow-hidden ' + aspectClass + ' cursor-pointer group">' +
                '<img src="' + self.escapeHtml(img) + '" alt="Galerie" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">' +
                '<div class="absolute inset-0 transition-all bg-black/0 group-hover:bg-black/30"></div>' +
                moreOverlay +
            '</div>';
        }).join('');

        container.innerHTML = html;
    },

    /**
     * Render events list using AmbiletEventCard for commission support
     */
    renderEvents(events) {
        var container = document.getElementById(this.elements.eventsList);
        if (!container) return;

        if (!events || events.length === 0) {
            container.innerHTML =
                '<div class="p-8 text-center bg-white border rounded-2xl border-border">' +
                    '<svg class="w-12 h-12 mx-auto mb-3 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                        '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>' +
                        '<line x1="16" y1="2" x2="16" y2="6"/>' +
                        '<line x1="8" y1="2" x2="8" y2="6"/>' +
                        '<line x1="3" y1="10" x2="21" y2="10"/>' +
                    '</svg>' +
                    '<p class="text-gray-500">Nu există evenimente programate la această locație</p>' +
                '</div>';
            return;
        }

        // Use AmbiletEventCard for consistent rendering with commission support
        container.innerHTML = AmbiletEventCard.renderManyHorizontal(events, {
            urlPrefix: '/bilete/',
            showBuyButton: true
        });
    },

    /**
     * Render similar venues
     */
    renderSimilarVenues(venues) {
        var container = document.getElementById(this.elements.similarVenues);
        if (!container) return;

        if (!venues || venues.length === 0) {
            container.innerHTML = '<div class="flex items-center justify-center col-span-4 py-12 text-gray-400"><p>Nu există locații similare de afișat</p></div>';
            return;
        }

        var self = this;
        var html = venues.map(function(v) {
            return '<a href="/locatie/' + self.escapeHtml(v.slug) + '" class="overflow-hidden transition-all bg-white border rounded-2xl border-border hover:-translate-y-1 hover:shadow-lg hover:border-primary">' +
                '<div class="aspect-[16/10] overflow-hidden">' +
                    '<img src="' + self.escapeHtml(v.image) + '" alt="' + self.escapeHtml(v.name) + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105">' +
                '</div>' +
                '<div class="p-4">' +
                    '<h3 class="mb-1 text-base font-bold text-secondary">' + self.escapeHtml(v.name) + '</h3>' +
                    '<p class="mb-2 text-sm text-muted">' + self.escapeHtml(v.location) + '</p>' +
                    '<span class="text-xs font-semibold text-primary">' + v.events + ' evenimente</span>' +
                '</div>' +
            '</a>';
        }).join('');

        container.innerHTML = html;
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Load follow status for venue
     */
    async loadFollowStatus() {
        if (!this.venueSlug) return;

        try {
            var response = await AmbiletAPI.checkVenueFavorite(this.venueSlug);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            // Silently ignore - user not logged in or error
            console.log('[VenuePage] Follow status check skipped');
        }
    },

    /**
     * Toggle follow status for venue
     */
    async toggleFollow() {
        if (!this.venueSlug) return;

        // Check if user is logged in
        if (!AmbiletAuth.isLoggedIn()) {
            window.location.href = '/cont/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        try {
            var response = await AmbiletAPI.toggleVenueFavorite(this.venueSlug);
            console.log('[VenuePage] Toggle follow response:', response);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            console.error('[VenuePage] Toggle follow failed:', e);
            if (e.status === 401) {
                window.location.href = '/cont/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            }
        }
    },

    /**
     * Update follow button visual state
     */
    updateFollowButton() {
        var btn = document.getElementById(this.elements.followBtn);
        var text = document.getElementById(this.elements.followText);
        if (!btn || !text) return;

        if (this.isFollowing) {
            btn.classList.remove('bg-white/20', 'hover:bg-white/30');
            btn.classList.add('bg-primary', 'hover:bg-primary-dark');
            text.textContent = 'Urmărești locația';
        } else {
            btn.classList.remove('bg-primary', 'hover:bg-primary-dark');
            btn.classList.add('bg-white/20', 'hover:bg-white/30');
            text.textContent = 'Urmărește locația';
        }
    }
};

// Make available globally
window.VenuePage = VenuePage;
