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
            linkClass = '',
            showPromotedBadge = false // Show "Promovat" badge for paid promotions
        } = options;

        const eventUrl = urlPrefix + event.slug;

        // Promoted badge (shown on top-left for paid promotions)
        let promotedBadge = '';
        if (showPromotedBadge) {
            promotedBadge = '<span class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 px-2 py-1 text-xs font-bold text-white uppercase rounded-lg" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);">' +
                '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                'Promovat' +
            '</span>';
        }

        // Status badges (cancelled, postponed, sold out take priority)
        let statusBadge = '';
        if (event.isCancelled) {
            statusBadge = '<span class="absolute px-2 py-1 text-sm font-bold text-white uppercase rounded-lg top-3 right-3 bg-red-600">ANULAT</span>';
        } else if (event.isPostponed) {
            statusBadge = '<span class="absolute px-2 py-1 text-sm font-bold text-white uppercase rounded-lg top-3 right-3 bg-orange-500">AMÂNAT</span>';
        } else if (event.isSoldOut) {
            statusBadge = '<span class="absolute px-2 py-1 text-sm font-bold text-white uppercase rounded-lg top-3 right-3 bg-gray-600">SOLD OUT</span>';
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

        // Responsive image: poster (vertical) on mobile, hero (horizontal) on desktop
        const posterSrc = getStorageUrl(event.posterImage || event.image);
        const heroSrc = getStorageUrl(event.heroImage || event.image);

        return '<a href="' + eventUrl + '" class="overflow-hidden transition-all bg-white border group rounded-lg border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary ' + linkClass + '">' +
            '<div class="relative h-40 mobile:h-64 overflow-hidden">' +
                '<picture>' +
                    '<source media="(min-width: 768px)" srcset="' + heroSrc + '">' +
                    '<img src="' + posterSrc + '" alt="' + this.escapeHtml(event.title) + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105 rounded-tl-lg rounded-tr-lg" loading="lazy" width="400" height="256" onerror="this.src=\'' + this.PLACEHOLDER + '\'">' +
                '</picture>' +
                (promotedBadge ? promotedBadge : '<div class="absolute top-3 left-3">' + dateBadgeHtml + '</div>') +
                statusBadge +
            '</div>' +
            '<div class="py-2">' +
                '<h3 class="px-3 font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2 truncate">' + this.escapeHtml(event.title) + '</h3>' +
                (showVenue && event.location ?
                    '<p class="px-3 text-sm text-muted flex items-center gap-1 mb-2">' +
                        '<span class="flex-none font-semibold">' + this.escapeHtml(event.venueCity) + '</span>' +
                        '<svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                        '<span class="truncate text-xs">' + this.escapeHtml(event.venueName) + '</span>' +
                    '</p>' : '') +
                (showPrice ?
                    '<div class="px-3 flex items-center justify-between pt-1 border-t border-border">' +
                        '<span class="font-bold ' + (event.isCancelled || event.isPostponed || event.isSoldOut ? 'text-gray-400 line-through' : 'text-primary') + '">' + event.priceFormatted + '</span>' +
                        '<span class="text-xs ' + (event.isCancelled ? 'text-red-600 font-semibold' : event.isPostponed ? 'text-orange-600 font-semibold' : event.isSoldOut ? 'text-gray-600 font-semibold' : 'text-muted') + '">' +
                            (event.isCancelled ? 'Anulat' : event.isPostponed ? 'Amânat' : event.isSoldOut ? 'Sold Out' : '') +
                        '</span>' +
                        (showCategory && event.categoryName ?
                            '<span class="mobile:hidden cat-pill font-semibold text-white uppercase rounded-lg bg-black/60 backdrop-blur-sm">' + this.escapeHtml(event.categoryName) + '</span>' :
                            '') +
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
     * Render promoted/recommended event card (poster-style)
     * Used on: index.php (Promoted & Recommended section)
     *
     * @param {Object} eventData - Raw API event data or normalized event
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderPromoted(eventData, options = {}) {
        const event = eventData._raw ? eventData : this.normalizeEvent(eventData);
        if (!event) return '';

        const {
            urlPrefix = '/bilete/',
            isPromoted = false
        } = options;

        const eventUrl = urlPrefix + event.slug;

        // Status badges (cancelled, postponed, sold out)
        let statusBadge = '';
        if (event.isCancelled) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 z-10 bg-red-600">ANULAT</span>';
        } else if (event.isPostponed) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 z-10 bg-orange-500">AMÂNAT</span>';
        } else if (event.isSoldOut) {
            statusBadge = '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 right-3 z-10 bg-gray-600">SOLD OUT</span>';
        }

        // Date badge
        let dateBadgeHtml;
        if (event.isDateRange && event.dateRangeFormatted) {
            dateBadgeHtml = '<div class="px-2 py-1 text-center text-white shadow-lg bg-primary rounded-md">' +
                '<span class="block text-xs font-semibold leading-tight mobile:text-sm">' + this.escapeHtml(event.dateRangeFormatted) + '</span>' +
            '</div>';
        } else {
            dateBadgeHtml = '<div class="px-2 py-1 text-center text-white shadow-lg bg-primary rounded-md">' +
                '<span class="block text-lg font-bold leading-none mobile:text-xl">' + event.day + '</span>' +
                '<span class="block text-[10px] uppercase tracking-wide mt-0.5 mobile:text-sm">' + event.month + '</span>' +
            '</div>';
        }

        // Poster image only (vertical)
        const posterSrc = getStorageUrl(event.posterImage || event.image);

        // Normal image only (horizontal fallback)
        const heroSrc = getStorageUrl(event.heroImage || event.image);

        // Type bar: gold for Promovat, red for Recomandat
        let typeBadge;
        if (isPromoted) {
            typeBadge = '<div class="flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white uppercase tracking-wide" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">' +
                '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                'Promovat' +
            '</div>';
        } else {
            typeBadge = '<div class="flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white uppercase tracking-wide bg-red-600">' +
                '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                'Recomandat' +
            '</div>';
        }

        // Location display
        const locationHtml = (event.venueCity || event.venueName) ?
            '<p class="text-xs text-muted flex items-center gap-1 mobile:text-base">' +
                '<svg class="flex-shrink-0 w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                '<span class="">' + (event.venueCity ? (event.venueName ? '<strong>' + this.escapeHtml(event.venueCity) + '</strong>, ' + this.escapeHtml(event.venueName) : this.escapeHtml(event.venueCity)) : this.escapeHtml(event.venueName)) + '</span>' +
            '</p>' : '';

        return '<a href="' + eventUrl + '" class="overflow-hidden relative transition-all bg-primary border group rounded-md border-transparent hover:-translate-y-1 hover:shadow-xl hover:scale-105 duration-300 ease-in-out">' +
            '<div class="relative aspect-[2/3] overflow-hidden mobile:aspect-auto">' +
                '<img src="' + posterSrc + '" alt="' + this.escapeHtml(event.title) + '" class="mobile:hidden object-cover w-full h-full transition-transform duration-300" loading="lazy" width="300" height="450" onerror="this.src=\'' + this.PLACEHOLDER + '\'">' +
                '<img src="' + heroSrc + '" alt="' + this.escapeHtml(event.title) + '" class="hidden mobile:block object-cover w-full h-52 transition-transform duration-300 relative" loading="lazy" width="400" height="208" onerror="this.src=\'' + this.PLACEHOLDER + '\'">' +
                '<div class="opacity-0 z-10 bg-gradient-to-b from-transparent via-gray-900/70 to-gray-900 w-full h-full bottom-0 absolute transition-opacity duration-250 ease-in-out group-hover:opacity-100 "></div>' +
                '<div class="absolute z-20 bottom-0 flex items-end gap-x-2 p-2">' +
                    '<div class="flex z-10">' + dateBadgeHtml + '</div>' +
                    '<div class="opacity-0 flex flex-col transition-opacity duration-250 ease-in-out group-hover:opacity-100">' +
                        '<h3 class="text-sm font-bold leading-snug transition-colors text-white group-hover:text-white line-clamp-2 pb-1 mobile:text-xl">' + this.escapeHtml(event.title) + '</h3>' +
                        locationHtml +
                    '</div>' +
                '</div>' +
                statusBadge +
            '</div>' +
            typeBadge +
        '</a>';
    },

    /**
     * Render multiple promoted cards
     * @param {Array} events - Array of event data
     * @param {Object} options - Options passed to renderPromoted()
     * @returns {string} HTML string
     */
    renderManyPromoted(events, options = {}) {
        if (!Array.isArray(events) || events.length === 0) return '';
        return events.map(e => this.renderPromoted(e, {
            ...options,
            isPromoted: e.has_paid_promotion === true
        })).join('');
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
            showBuyButton = true,
            showArtists = false,
            showPrice = true
        } = options;

        const eventUrl = urlPrefix + event.slug;

        // Price display
        let priceHtml = '', buttonHtml = '';
        if (showPrice) {
            if (event.isSoldOut) {
                priceHtml = '<span class="text-sm font-bold text-red-500">SOLD OUT</span>';
                buttonHtml = showBuyButton ? '<button class="py-2.5 px-5 bg-gray-400 rounded-lg text-white text-sm font-semibold cursor-not-allowed" disabled>Indisponibil</button>' : '';
            } else {
                priceHtml = '<div class="text-sm font-bold text-primary">' + event.priceFormatted + '</div>';
                buttonHtml = showBuyButton ? '<button class="py-2.5 px-5 bg-secondary hover:bg-secondary/90 rounded-lg text-white text-sm font-semibold transition-all">Cumpără bilete</button>' : '';
            }
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

        return '<a href="' + eventUrl + '" class="flex bg-white rounded-2xl overflow-hidden border border-border hover:shadow-lg hover:-translate-y-0.5 hover:border-primary transition-all mobile:flex-col justify-between">' +
            '<div class="flex">' +
            dateHtml +
            '<div class="flex flex-col justify-center flex-1 px-5 py-4 mobile:py-2 mobile:px-4 mobile:border-b mobile:border-border">' +
                (event.categoryName ? '<div class="mb-1 text-xs font-semibold tracking-wide uppercase text-primary">' + this.escapeHtml(event.categoryName) + '</div>' : '') +
                '<h3 class="mb-2 text-base font-bold leading-tight text-secondary mobile:text-lg mobile:leading-tight">' + this.escapeHtml(event.title) + '</h3>' +
                '<div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">' +
                    '<span class="flex items-center gap-1 mobile:hidden">' +
                        '<svg class="w-3.5 h-3.5 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                        event.time +
                    '</span>' +
                    (event.venueName ? '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(event.venueName) + '</span>' : '') +
                    (showArtists && event.artists && event.artists.length > 0 ? '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' + this.escapeHtml(event.artists.join(', ')) + '</span>' : '') +
                '</div>' +
            '</div>' +
            '</div>' +
            (showPrice ?
                '<div class="py-4 px-5 flex flex-col items-end justify-center gap-1.5 mobile:flex-row mobile:items-center mobile:justify-between mobile:py-2 mobile:px-2">' +
                    priceHtml +
                    buttonHtml +
                '</div>' : '') +
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
                    '<img src="' + getStorageUrl(event.image) + '" alt="' + this.escapeHtml(event.title) + '" class="event-image w-full h-full object-cover" loading="lazy" width="800" height="450">' +
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
                            '<span class="text-sm text-muted">De la</span>' +
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
                '<img src="' + getStorageUrl(event.image) + '" alt="' + this.escapeHtml(event.title) + '" class="w-full h-full object-cover" loading="lazy" width="64" height="64">' +
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

        // Extract price - skip free (price=0) tickets when paid tickets also exist
        // Show "Gratuit" only when ALL tickets are free (no paid options)
        let minPrice = 0;
        let hasMultipleTicketTypes = false;
        if (apiEvent.ticket_types && Array.isArray(apiEvent.ticket_types) && apiEvent.ticket_types.length > 0) {
            const paidTickets = apiEvent.ticket_types.filter(t => (t.price || 0) > 0);
            if (paidTickets.length > 0) {
                minPrice = Math.min(...paidTickets.map(t => t.price || 0));
            } else {
                minPrice = 0;
            }
            hasMultipleTicketTypes = apiEvent.ticket_types.length > 1;
        } else {
            const raw = apiEvent.price_from != null ? apiEvent.price_from
                      : apiEvent.min_price != null ? apiEvent.min_price
                      : apiEvent.price != null ? apiEvent.price : 0;
            minPrice = raw;
            const ttCount = apiEvent.ticket_types_count;
            hasMultipleTicketTypes = ttCount != null ? ttCount > 1 : false;
        }

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
            image: apiEvent.poster_url || apiEvent.image_url || apiEvent.featured_image || apiEvent.image || null,
            posterImage: apiEvent.poster_url || apiEvent.image_url || apiEvent.image || null,
            heroImage: apiEvent.hero_image_url || apiEvent.cover_image_url || apiEvent.image_url || apiEvent.image || null,
            date: date,
            day: date ? date.getDate() : '',
            month: date ? this.MONTHS[date.getMonth()] : '',
            time: apiEvent.start_time || (rawDate && typeof rawDate === 'string' && rawDate.includes('T') ? rawDate.split('T')[1].substring(0, 5) : (date ? String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') : '20:00')),
            venueName: venueName,
            venueCity: venueCity,
            location: venueCity ? (venueName) : venueName, // + ', ' + venueCity if both exist
            minPrice: minPrice,
            priceFormatted: minPrice > 0 ? (hasMultipleTicketTypes ? '<span class="font-semibold text-slate-700 text-xs">De la</span> ' : '<span class="font-semibold text-slate-800 text-xs">Bilete: </span>') + minPrice + ' lei' : 'Gratuit',
            categoryName: categoryName,
            isSoldOut: apiEvent.is_sold_out || false,
            isCancelled: apiEvent.is_cancelled || false,
            isPostponed: apiEvent.is_postponed || false,
            postponedDate: apiEvent.postponed_date || null,
            isDateRange: isDateRange,
            dateRangeFormatted: dateRangeFormatted,
            artists: apiEvent.artists || [],
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
