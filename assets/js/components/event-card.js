/**
 * Ambilet.ro - Event Card Component
 * Unified event card rendering for all pages
 *
 * Variants:
 * - render() - Grid card for listings (city, category, genre, events, related)
 * - renderHorizontal() - Horizontal card for venue/artist pages
 * - renderFeatured() - Large hero card for homepage
 * - renderCompact() - Sidebar/small list card
 */

const AmbiletEventCard = {
    // Default placeholder image
    PLACEHOLDER: '/assets/images/default-event.png',

    // Romanian month abbreviations
    MONTHS: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],

    /**
     * Render grid event card (main variant for listings)
     * Used on: city.php, category.php, genre.php, events.php, event.php (related)
     *
     * @param {Object} eventData - Raw API event data or normalized event
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    render(eventData, options = {}) {
        // Normalize if needed (check if already normalized by looking for _raw)
        const event = eventData._raw ? eventData : this.normalizeEvent(eventData);
        if (!event) return '';

        const {
            showCategory = true,
            showPrice = true,
            showVenue = true,
            urlPrefix = '/bilete/',
            linkClass = ''
        } = options;

        const eventUrl = urlPrefix + event.slug;

        // Status badges (cancelled, postponed, sold out take priority)
        let statusBadge = '';
        if (event.isCancelled) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 bg-red-600">ANULAT</span>';
        } else if (event.isPostponed) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 bg-orange-500">AMÂNAT</span>';
        } else if (event.isSoldOut) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 bg-gray-600">SOLD OUT</span>';
        } else if (showCategory && event.categoryName) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-semibold text-white uppercase rounded-lg top-3 right-3 bg-black/60 backdrop-blur-sm">' + this.escapeHtml(event.categoryName) + '</span>';
        }

        // Date badge - show range for festivals, single date otherwise
        let dateBadgeHtml;
        if (event.isDateRange && event.dateRangeFormatted) {
            dateBadgeHtml = '<div class="px-3 py-2 text-center text-white shadow-lg bg-primary rounded-xl">' +
                '<span class="block text-xs font-semibold leading-tight">' + this.escapeHtml(event.dateRangeFormatted) + '</span>' +
            '</div>';
        } else {
            dateBadgeHtml = '<div class="px-3 py-2 text-center text-white shadow-lg bg-primary rounded-xl">' +
                '<span class="block text-lg font-bold leading-none">' + event.day + '</span>' +
                '<span class="block text-[10px] uppercase tracking-wide mt-0.5">' + event.month + '</span>' +
            '</div>';
        }

        return '<a href="' + eventUrl + '" class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary ' + linkClass + '">' +
            '<div class="relative h-48 overflow-hidden">' +
                '<img src="' + getStorageUrl(event.image) + '" alt="' + this.escapeHtml(event.title) + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105 rounded-tl-2xl rounded-tr-2xl" loading="lazy" onerror="this.src=\'' + this.PLACEHOLDER + '\'">' +
                '<div class="absolute top-3 left-3">' + dateBadgeHtml + '</div>' +
                statusBadge +
            '</div>' +
            '<div class="px-3 py-2">' +
                '<h3 class="mb-2 font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2 truncate">' + this.escapeHtml(event.title) + '</h3>' +
                (showVenue && event.location ?
                    '<p class="text-sm text-muted flex items-center gap-1.5 mb-3">' +
                        '<svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                        '<span class="truncate">' + this.escapeHtml(event.location) + '</span>' +
                    '</p>' : '') +
                (showPrice ?
                    '<div class="flex items-center justify-between pt-2 border-t border-border">' +
                        '<span class="font-bold ' + (event.isCancelled || event.isPostponed || event.isSoldOut ? 'text-gray-400 line-through' : 'text-primary') + '">' + event.priceFormatted + '</span>' +
                        '<span class="text-xs ' + (event.isCancelled ? 'text-red-600 font-semibold' : event.isPostponed ? 'text-orange-600 font-semibold' : event.isSoldOut ? 'text-gray-600 font-semibold' : 'text-muted') + '">' +
                            (event.isCancelled ? 'Anulat' : event.isPostponed ? 'Amânat' : event.isSoldOut ? 'Sold Out' : '') +
                        '</span>' +
                    '</div>' : '') +
            '</div>' +
        '</a>';
    },

    /**
     * Render multiple grid cards
     * @param {Array} events - Array of event data
     * @param {Object} options - Options passed to render()
     * @returns {string} HTML string
     */
    renderMany(events, options = {}) {
        if (!Array.isArray(events) || events.length === 0) return '';
        return events.map(e => this.render(e, options)).join('');
    },

    /**
     * Render horizontal event card (for venue/artist detail pages)
     * Used on: venue-single.php, artist-single.php
     *
     * @param {Object} eventData - Raw API event data or normalized event
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderHorizontal(eventData, options = {}) {
        const event = eventData._raw ? eventData : this.normalizeEvent(eventData);
        if (!event) return '';

        const {
            urlPrefix = '/bilete/',
            showBuyButton = true
        } = options;

        const eventUrl = urlPrefix + event.slug;

        // Price display
        let priceHtml, buttonHtml;
        if (event.isSoldOut) {
            priceHtml = '<span class="text-sm font-bold text-red-500">SOLD OUT</span>';
            buttonHtml = showBuyButton ? '<button class="py-2.5 px-5 bg-gray-400 rounded-lg text-white text-sm font-semibold cursor-not-allowed" disabled>Indisponibil</button>' : '';
        } else {
            priceHtml = '<div class="text-xs text-muted">de la <strong class="text-lg font-bold text-success">' + event.priceFormatted + '</strong></div>';
            buttonHtml = showBuyButton ? '<button class="py-2.5 px-5 bg-secondary hover:bg-secondary/90 rounded-lg text-white text-sm font-semibold transition-all">Cumpără bilete</button>' : '';
        }

        // Date section - show range for festivals
        let dateHtml;
        if (event.isDateRange && event.dateRangeFormatted) {
            dateHtml = '<div class="flex flex-col items-center justify-center flex-shrink-0 w-28 py-5 text-center bg-gradient-to-br from-primary to-primary-light mobile:max-w-[96px]">' +
                '<div class="px-2 text-xs font-semibold leading-tight text-white">' + this.escapeHtml(event.dateRangeFormatted) + '</div>' +
            '</div>';
        } else {
            dateHtml = '<div class="flex flex-col items-center justify-center flex-shrink-0 w-24 py-5 text-center bg-gradient-to-br from-primary to-primary-light mobile:max-w-[96px]">' +
                '<div class="text-3xl font-extrabold leading-none text-white">' + event.day + '</div>' +
                '<div class="mt-1 text-sm font-semibold uppercase text-white/90">' + event.month + '</div>' +
            '</div>';
        }

        return '<a href="' + eventUrl + '" class="flex bg-white rounded-2xl overflow-hidden border border-border hover:shadow-lg hover:-translate-y-0.5 hover:border-primary transition-all mobile:flex-col">' +
            '<div class="mobile:flex">' +
            dateHtml +
            '<div class="flex flex-col justify-center flex-1 px-5 py-4 mobile:py-2 mobile:px-4 mobile:border-b mobile:border-border">' +
                (event.categoryName ? '<div class="mb-1 text-xs font-semibold tracking-wide uppercase text-primary">' + this.escapeHtml(event.categoryName) + '</div>' : '') +
                '<h3 class="mb-2 text-base font-bold leading-tight text-secondary mobile:text-lg mobile:leading-tight">' + this.escapeHtml(event.title) + '</h3>' +
                '<div class="flex gap-4 text-sm text-muted">' +
                    '<span class="flex items-center gap-1 mobile:hidden">' +
                        '<svg class="w-3.5 h-3.5 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                        event.time +
                    '</span>' +
                    (event.venueName ? '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(event.venueName) + '</span>' : '') +
                '</div>' +
            '</div>' +
            '</div>' +
            '<div class="py-4 px-5 flex flex-col items-end justify-center gap-1.5 mobile:flex-row mobile:items-center mobile:justify-between mobile:py-2 mobile:px-2">' +
                priceHtml +
                buttonHtml +
            '</div>' +
        '</a>';
    },

    /**
     * Render multiple horizontal cards
     * @param {Array} events - Array of event data
     * @param {Object} options - Options passed to renderHorizontal()
     * @returns {string} HTML string
     */
    renderManyHorizontal(events, options = {}) {
        if (!Array.isArray(events) || events.length === 0) return '';
        return events.map(e => this.renderHorizontal(e, options)).join('');
    },

    /**
     * Render featured/hero event card (for homepage)
     * Large card with horizontal layout
     *
     * @param {Object} eventData - Raw API event data or normalized event
     * @returns {string} HTML string
     */
    renderFeatured(eventData) {
        const event = eventData._raw ? eventData : this.normalizeEvent(eventData);
        if (!event) return '';

        const eventUrl = '/bilete/' + event.slug;

        // Full date format
        const dateFormatted = event.date ? event.date.toLocaleDateString('ro-RO', {
            weekday: 'long',
            day: 'numeric',
            month: 'long'
        }) : '';

        return '<article class="event-card group relative bg-white rounded-3xl overflow-hidden cursor-pointer shadow-lg" onclick="window.location.href=\'' + eventUrl + '\'">' +
            '<div class="flex flex-col lg:flex-row">' +
                '<div class="relative lg:w-2/3 aspect-video lg:aspect-auto overflow-hidden">' +
                    '<img src="' + getStorageUrl(event.image) + '" alt="' + this.escapeHtml(event.title) + '" class="event-image w-full h-full object-cover" loading="lazy">' +
                    '<div class="absolute inset-0 bg-gradient-to-t lg:bg-gradient-to-r from-black/60 to-transparent"></div>' +
                    '<div class="absolute top-4 left-4">' +
                        '<span class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-full">' +
                            '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' +
                            'Recomandat' +
                        '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="lg:w-1/3 p-6 lg:p-8 flex flex-col justify-center">' +
                    (event.categoryName ? '<span class="text-sm font-semibold text-primary mb-2">' + this.escapeHtml(event.categoryName) + '</span>' : '') +
                    '<h3 class="text-2xl lg:text-3xl font-bold text-secondary mb-3 group-hover:text-primary transition-colors">' + this.escapeHtml(event.title) + '</h3>' +
                    '<div class="space-y-2 mb-6">' +
                        '<div class="flex items-center gap-2 text-muted">' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
                            '<span>' + dateFormatted + '</span>' +
                        '</div>' +
                        (event.location ? '<div class="flex items-center gap-2 text-muted"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg><span>' + this.escapeHtml(event.location) + '</span></div>' : '') +
                    '</div>' +
                    '<div class="flex items-center justify-between">' +
                        '<div>' +
                            '<span class="text-sm text-muted">de la</span>' +
                            '<span class="text-2xl font-bold text-primary ml-1">' + event.priceFormatted + '</span>' +
                        '</div>' +
                        '<button class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary-dark text-white font-semibold rounded-xl transition-colors">' +
                            'Cumpără bilete' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</article>';
    },

    /**
     * Render compact event card (for sidebars, small lists)
     *
     * @param {Object} eventData - Raw API event data or normalized event
     * @returns {string} HTML string
     */
    renderCompact(eventData) {
        const event = eventData._raw ? eventData : this.normalizeEvent(eventData);
        if (!event) return '';

        const eventUrl = '/bilete/' + event.slug;

        return '<a href="' + eventUrl + '" class="flex gap-4 p-3 rounded-xl hover:bg-surface transition-colors group">' +
            '<div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0">' +
                '<img src="' + getStorageUrl(event.image) + '" alt="' + this.escapeHtml(event.title) + '" class="w-full h-full object-cover" loading="lazy">' +
            '</div>' +
            '<div class="flex-1 min-w-0">' +
                '<h4 class="font-semibold text-secondary truncate group-hover:text-primary transition-colors">' + this.escapeHtml(event.title) + '</h4>' +
                '<p class="text-sm text-muted">' + event.day + ' ' + event.month + '</p>' +
                (event.venueCity ? '<p class="text-sm text-muted truncate">' + this.escapeHtml(event.venueCity) + '</p>' : '') +
            '</div>' +
        '</a>';
    },

    // ==================== SKELETON LOADERS ====================

    /**
     * Render grid skeleton loading card
     */
    renderSkeleton() {
        return '<div class="overflow-hidden bg-white border rounded-2xl border-border">' +
            '<div class="h-48 skeleton"></div>' +
            '<div class="p-5">' +
                '<div class="w-3/4 mb-2 skeleton skeleton-title"></div>' +
                '<div class="w-1/2 mb-3 skeleton skeleton-text"></div>' +
                '<div class="w-1/3 h-6 skeleton"></div>' +
            '</div>' +
        '</div>';
    },

    /**
     * Render multiple grid skeletons
     * @param {number} count - Number of skeletons
     * @returns {string} HTML string
     */
    renderSkeletons(count = 8) {
        return Array(count).fill(this.renderSkeleton()).join('');
    },

    /**
     * Render horizontal skeleton loading card
     */
    renderSkeletonHorizontal() {
        return '<div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">' +
            '<div class="flex">' +
                '<div class="w-24 h-32 bg-gray-200"></div>' +
                '<div class="flex-1 p-5">' +
                    '<div class="w-16 h-3 mb-2 bg-gray-200 rounded"></div>' +
                    '<div class="w-3/4 h-5 mb-3 bg-gray-200 rounded"></div>' +
                    '<div class="w-24 h-4 bg-gray-200 rounded"></div>' +
                '</div>' +
            '</div>' +
        '</div>';
    },

    /**
     * Render multiple horizontal skeletons
     * @param {number} count - Number of skeletons
     * @returns {string} HTML string
     */
    renderSkeletonsHorizontal(count = 3) {
        return Array(count).fill(this.renderSkeletonHorizontal()).join('');
    },

    // ==================== HELPER METHODS ====================

    /**
     * Normalize event data from various API formats
     * Uses AmbiletDataTransformer if available, otherwise basic normalization
     *
     * @param {Object} apiEvent - Raw event data from API
     * @returns {Object} Normalized event object
     */
    normalizeEvent(apiEvent) {
        if (!apiEvent) return null;

        // Use data transformer if available
        if (typeof AmbiletDataTransformer !== 'undefined') {
            return AmbiletDataTransformer.normalizeEvent(apiEvent);
        }

        // Fallback: basic normalization
        const rawDate = apiEvent.starts_at || apiEvent.event_date || apiEvent.start_date || apiEvent.date;
        const date = rawDate ? new Date(rawDate) : null;

        // Extract venue - handle both object format and flat fields
        let venueName = '', venueCity = '';
        if (typeof apiEvent.venue === 'string') {
            venueName = apiEvent.venue;
        } else if (apiEvent.venue && typeof apiEvent.venue === 'object') {
            venueName = apiEvent.venue.name || '';
            venueCity = apiEvent.venue.city || '';
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

        // Extract price - always show base ticket price (without commission on top)
        let minPrice = apiEvent.price_from || apiEvent.min_price || apiEvent.price || 0;

        // Extract category
        let categoryName = '';
        if (typeof apiEvent.category === 'string') {
            categoryName = apiEvent.category;
        } else if (apiEvent.category && typeof apiEvent.category === 'object') {
            categoryName = apiEvent.category.name || '';
        }

        // Extract date range for festivals
        const durationMode = apiEvent.duration_mode || 'single_day';
        const isDateRange = durationMode === 'range' || durationMode === 'date_range';
        let dateRangeFormatted = '';
        if (isDateRange && apiEvent.range_start_date && apiEvent.range_end_date) {
            const startDate = new Date(apiEvent.range_start_date);
            const endDate = new Date(apiEvent.range_end_date);
            const startDay = startDate.getDate();
            const startMonth = this.MONTHS[startDate.getMonth()];
            const endDay = endDate.getDate();
            const endMonth = this.MONTHS[endDate.getMonth()];
            const startYear = startDate.getFullYear();
            const endYear = endDate.getFullYear();

            if (startYear === endYear) {
                if (startMonth === endMonth) {
                    dateRangeFormatted = startDay + ' - ' + endDay + ' ' + endMonth;
                } else {
                    dateRangeFormatted = startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth;
                }
            } else {
                dateRangeFormatted = startDay + ' ' + startMonth + ' ' + startYear + ' - ' + endDay + ' ' + endMonth + ' ' + endYear;
            }
        }

        return {
            id: apiEvent.id,
            slug: apiEvent.slug || '',
            title: apiEvent.name || apiEvent.title || 'Eveniment',
            image: apiEvent.image_url || apiEvent.featured_image || apiEvent.image || null,
            date: date,
            day: date ? date.getDate() : '',
            month: date ? this.MONTHS[date.getMonth()] : '',
            time: apiEvent.start_time || (date ? String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') : '20:00'),
            venueName: venueName,
            venueCity: venueCity,
            location: venueCity ? (venueName + ', ' + venueCity) : venueName,
            minPrice: minPrice,
            priceFormatted: minPrice > 0 ? 'de la ' + minPrice + ' lei' : 'Gratuit',
            categoryName: categoryName,
            isSoldOut: apiEvent.is_sold_out || false,
            isCancelled: apiEvent.is_cancelled || false,
            isPostponed: apiEvent.is_postponed || false,
            postponedDate: apiEvent.postponed_date || null,
            isDateRange: isDateRange,
            dateRangeFormatted: dateRangeFormatted,
            _raw: apiEvent
        };
    },

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make available globally
window.AmbiletEventCard = AmbiletEventCard;
