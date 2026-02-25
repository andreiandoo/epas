/**
 * Ambilet.ro - Data Transformer
 * Normalizes API responses to consistent data structures
 */

const AmbiletDataTransformer = {
    // Romanian month abbreviations
    MONTHS_SHORT: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    WEEKDAYS: ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'],

    /**
     * Normalize event data from API to consistent format
     * Handles all field name variations from different API endpoints
     */
    normalizeEvent(apiEvent) {
        if (!apiEvent) return null;

        // Extract date - handle multiple field name variations
        const rawDate = apiEvent.starts_at || apiEvent.event_date || apiEvent.start_date || apiEvent.date;
        const eventDate = rawDate ? new Date(rawDate) : null;

        // Extract title - handle variations
        const title = apiEvent.name || apiEvent.title || 'Eveniment';

        // Extract image - prefer poster for card displays
        let image = apiEvent.poster_url || apiEvent.image_url || apiEvent.featured_image || apiEvent.image || apiEvent.cover_image_url || null;
        if (image && !image.startsWith('http') && !image.startsWith('/')) {
            image = '/storage/' + image;
        }

        // Extract separate poster (vertical, for mobile) and hero (horizontal, for desktop)
        let posterImage = apiEvent.poster_url || apiEvent.image_url || apiEvent.image || null;
        if (posterImage && !posterImage.startsWith('http') && !posterImage.startsWith('/')) {
            posterImage = '/storage/' + posterImage;
        }
        let heroImage = apiEvent.hero_image_url || apiEvent.cover_image_url || apiEvent.image_url || apiEvent.image || null;
        if (heroImage && !heroImage.startsWith('http') && !heroImage.startsWith('/')) {
            heroImage = '/storage/' + heroImage;
        }

        // Extract venue - handle both string and object formats
        let venueName = '';
        let venueCity = '';
        let venueSlug = '';
        if (typeof apiEvent.venue === 'string') {
            venueName = apiEvent.venue;
        } else if (apiEvent.venue && typeof apiEvent.venue === 'object') {
            venueName = apiEvent.venue.name || '';
            venueCity = apiEvent.venue.city || '';
            venueSlug = apiEvent.venue.slug || '';
        }
        // Fallback to flat venue_name/venue_city fields (from list API)
        if (!venueName && apiEvent.venue_name) {
            venueName = apiEvent.venue_name;
        }
        if (!venueCity && apiEvent.venue_city) {
            venueCity = apiEvent.venue_city;
        }
        if (!venueCity && apiEvent.city) {
            venueCity = apiEvent.city;
        }

        // Extract price - skip free (price=0) ticket types, show min paid price
        let minPrice = 0;
        let hasMultipleTicketTypes = false;
        if (apiEvent.ticket_types && Array.isArray(apiEvent.ticket_types)) {
            const paidTickets = apiEvent.ticket_types.filter(t => (t.price || 0) > 0);
            minPrice = paidTickets.length > 0 ? Math.min(...paidTickets.map(t => t.price || 0)) : 0;
            hasMultipleTicketTypes = apiEvent.ticket_types.length > 1;
        } else {
            minPrice = apiEvent.price_from || apiEvent.min_price || apiEvent.price || 0;
            const ttCount = apiEvent.ticket_types_count;
            hasMultipleTicketTypes = ttCount != null ? ttCount > 1 : false;
        }

        // Extract date range for multi-day events (festivals)
        const durationMode = apiEvent.duration_mode || 'single_day';
        let rangeStartDate = null;
        let rangeEndDate = null;
        if (durationMode === 'range' || durationMode === 'date_range') {
            rangeStartDate = apiEvent.range_start_date ? new Date(apiEvent.range_start_date) : null;
            rangeEndDate = apiEvent.range_end_date ? new Date(apiEvent.range_end_date) : null;
        }

        // Extract category
        let categoryName = '';
        let categorySlug = '';
        if (typeof apiEvent.category === 'string') {
            categoryName = apiEvent.category;
            categorySlug = this.slugify(apiEvent.category);
        } else if (apiEvent.category && typeof apiEvent.category === 'object') {
            categoryName = apiEvent.category.name || '';
            categorySlug = apiEvent.category.slug || '';
        }
        if (apiEvent.category_slug) {
            categorySlug = apiEvent.category_slug;
        }

        // Status flags
        const isSoldOut = apiEvent.is_sold_out || false;
        const isLowStock = apiEvent.is_low_stock || false;
        const isFeatured = apiEvent.is_featured || apiEvent.is_homepage_featured || apiEvent.is_general_featured || false;

        return {
            id: apiEvent.id,
            slug: apiEvent.slug || '',
            title: title,
            description: apiEvent.description || apiEvent.short_description || '',
            image: image,
            posterImage: posterImage,
            heroImage: heroImage,

            // Date information
            date: eventDate,
            dateFormatted: eventDate ? this.formatDateShort(eventDate) : '',
            day: eventDate ? eventDate.getDate() : '',
            month: eventDate ? this.MONTHS_SHORT[eventDate.getMonth()] : '',
            weekday: eventDate ? this.WEEKDAYS[eventDate.getDay()] : '',
            time: apiEvent.start_time || (eventDate ? this.formatTime(eventDate) : ''),

            // Date range for multi-day events (festivals)
            durationMode: durationMode,
            rangeStartDate: rangeStartDate,
            rangeEndDate: rangeEndDate,
            isDateRange: durationMode === 'range' || durationMode === 'date_range',
            dateRangeFormatted: this.formatDateRange(rangeStartDate, rangeEndDate),

            // Venue information
            venueName: venueName,
            venueCity: venueCity,
            venueSlug: venueSlug,
            venueAddress: apiEvent.venue?.address || '',
            location: venueCity ? (venueName + ', ' + venueCity) : venueName,

            // Price information
            minPrice: minPrice,
            priceFormatted: minPrice > 0 ? (hasMultipleTicketTypes ? 'De la ' : 'Bilete: ') + AmbiletUtils.formatCurrency(minPrice) : 'Gratuit',

            // Category
            categoryName: categoryName,
            categorySlug: categorySlug,

            // Status
            isSoldOut: isSoldOut,
            isLowStock: isLowStock,
            isFeatured: isFeatured,
            hasAvailability: apiEvent.has_availability !== false && !isSoldOut,

            // Original data for anything else needed
            _raw: apiEvent
        };
    },

    /**
     * Normalize multiple events
     */
    normalizeEvents(apiEvents) {
        if (!Array.isArray(apiEvents)) return [];
        return apiEvents.map(e => this.normalizeEvent(e)).filter(e => e !== null);
    },

    /**
     * Normalize artist data
     */
    normalizeArtist(apiArtist) {
        if (!apiArtist) return null;

        let image = apiArtist.image_url || apiArtist.portrait_url || apiArtist.logo_url || apiArtist.image || null;
        if (image && !image.startsWith('http') && !image.startsWith('/')) {
            image = '/storage/' + image;
        }

        return {
            id: apiArtist.id,
            slug: apiArtist.slug || '',
            name: apiArtist.name || '',
            image: image,
            description: apiArtist.description || apiArtist.bio || '',
            genre: apiArtist.genre || apiArtist.genres?.[0]?.name || '',
            eventsCount: apiArtist.events_count || apiArtist.upcoming_events_count || 0,
            followersCount: apiArtist.total_followers || 0,
            _raw: apiArtist
        };
    },

    /**
     * Normalize venue data
     */
    normalizeVenue(apiVenue) {
        if (!apiVenue) return null;

        let image = apiVenue.image_url || apiVenue.image || null;
        if (image && !image.startsWith('http') && !image.startsWith('/')) {
            image = '/storage/' + image;
        }

        return {
            id: apiVenue.id,
            slug: apiVenue.slug || '',
            name: apiVenue.name || '',
            image: image,
            description: apiVenue.description || '',
            address: apiVenue.address || '',
            city: apiVenue.city || '',
            state: apiVenue.state || '',
            country: apiVenue.country || '',
            latitude: apiVenue.latitude || apiVenue.lat || null,
            longitude: apiVenue.longitude || apiVenue.lng || null,
            googleMapsUrl: apiVenue.google_maps_url || null,
            capacity: apiVenue.capacity || null,
            eventsCount: apiVenue.events_count || 0,
            _raw: apiVenue
        };
    },

    /**
     * Normalize pagination metadata
     */
    normalizePagination(apiMeta) {
        if (!apiMeta) {
            return {
                currentPage: 1,
                lastPage: 1,
                perPage: 12,
                total: 0,
                hasMore: false
            };
        }

        const currentPage = apiMeta.current_page || apiMeta.currentPage || 1;
        const lastPage = apiMeta.last_page || apiMeta.lastPage || 1;

        return {
            currentPage: currentPage,
            lastPage: lastPage,
            perPage: apiMeta.per_page || apiMeta.perPage || 12,
            total: apiMeta.total || 0,
            hasMore: currentPage < lastPage
        };
    },

    // ==================== HELPER METHODS ====================

    /**
     * Format date as "15 Ian 2024"
     */
    formatDateShort(date) {
        if (!date) return '';
        const d = new Date(date);
        return d.getDate() + ' ' + this.MONTHS_SHORT[d.getMonth()] + ' ' + d.getFullYear();
    },

    /**
     * Format date range as "15 Ian - 18 Ian 2024" or "15 Ian 2024 - 2 Feb 2025"
     */
    formatDateRange(startDate, endDate) {
        if (!startDate || !endDate) return '';
        const start = new Date(startDate);
        const end = new Date(endDate);

        const startDay = start.getDate();
        const startMonth = this.MONTHS_SHORT[start.getMonth()];
        const startYear = start.getFullYear();

        const endDay = end.getDate();
        const endMonth = this.MONTHS_SHORT[end.getMonth()];
        const endYear = end.getFullYear();

        // Same year
        if (startYear === endYear) {
            // Same month
            if (start.getMonth() === end.getMonth()) {
                return startDay + ' - ' + endDay + ' ' + endMonth + ' ' + endYear;
            }
            // Different month, same year
            return startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ' ' + endYear;
        }

        // Different year
        return startDay + ' ' + startMonth + ' ' + startYear + ' - ' + endDay + ' ' + endMonth + ' ' + endYear;
    },

    /**
     * Format time as "HH:MM"
     */
    formatTime(date) {
        if (!date) return '';
        const d = new Date(date);
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    },

    /**
     * Create URL-friendly slug
     */
    slugify(text) {
        if (!text) return '';
        return text
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .trim();
    },

    /**
     * Format number with K/M suffix
     */
    formatCount(num) {
        if (!num || num === 0) return '0';
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        }
        return String(num);
    }
};

// Make available globally
window.AmbiletDataTransformer = AmbiletDataTransformer;
