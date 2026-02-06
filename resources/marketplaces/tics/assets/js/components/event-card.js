/**
 * TICS.ro - Event Card Component
 * Unified event card rendering for all pages
 *
 * Variants:
 * - render() - Standard grid card for listings
 * - renderFeatured() - Large featured card (2x2 grid)
 * - renderHorizontal() - Horizontal card for list view
 * - renderCompact() - Compact card for sidebars
 */

const TicsEventCard = {
    // Default placeholder image
    PLACEHOLDER: '/assets/images/default-event.jpg',

    /**
     * Render standard grid event card
     * @param {Object} event - Event data from API
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    render(event, options = {}) {
        const {
            showCategory = true,
            showPrice = true,
            showVenue = true,
            showMatch = true,
            showBadges = true,
            urlPrefix = '/bilete/',
        } = options;

        const data = this.normalizeEvent(event);
        if (!data) return '';

        // Build event URL - format: /bilete/[event-slug]-[city-slug]
        let eventUrl = `/event/${data.id}`;
        if (data.slug) {
            eventUrl = urlPrefix + data.slug;
            if (data.citySlug) {
                eventUrl += '-' + data.citySlug;
            }
        }

        // AI Match badge
        const matchBadge = showMatch && data.aiMatch ? `
            <div class="absolute top-3 left-3 flex items-center gap-1.5 bg-white/95 px-2.5 py-1 rounded-full">
                <span class="w-2 h-2 ${TicsUtils.getMatchColor(data.aiMatch)} rounded-full"></span>
                <span class="text-xs font-semibold">${data.aiMatch}%</span>
            </div>
        ` : '';

        // Status/Feature badges
        let statusBadge = '';
        if (showBadges) {
            if (data.isTrending) {
                statusBadge = '<span class="absolute top-3 right-12 trending-badge px-2 py-1 rounded-full text-xs font-semibold text-white">🔥 Trending</span>';
            } else if (data.isEarlyBird) {
                statusBadge = '<span class="absolute top-3 right-12 early-badge px-2 py-1 rounded-full text-xs font-semibold text-white">Early Bird</span>';
            } else if (data.hasVip) {
                statusBadge = '<span class="absolute top-3 right-12 vip-badge px-2 py-1 rounded-full text-xs font-semibold text-white">VIP</span>';
            }
        }

        // Category badge on image
        const categoryBadge = showCategory && data.categoryName ? `
            <span class="absolute bottom-3 left-3 px-2.5 py-1 bg-gray-900/80 backdrop-blur text-white text-xs font-medium rounded-full">${TicsUtils.escapeHtml(data.categoryName)}</span>
        ` : '';

        // Sold percentage
        const soldInfo = data.soldPercent ? `
            <span class="text-xs ${data.soldPercent >= 70 ? 'text-red-500' : 'text-amber-600'} font-medium">⚡ ${data.soldPercent}% sold</span>
        ` : '';

        // Price display
        const priceDisplay = showPrice ? `
            <div class="flex items-center justify-between">
                <span class="font-semibold">${data.priceFormatted}</span>
                ${soldInfo}
            </div>
        ` : '';

        return `
            <a href="${eventUrl}" class="event-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="absolute inset-0 w-full h-full object-cover event-img" loading="lazy" onerror="this.src='${this.PLACEHOLDER}'">
                    ${matchBadge}
                    ${statusBadge}
                    <button class="absolute top-3 right-3 w-8 h-8 bg-white/90 rounded-full flex items-center justify-center hover:bg-white transition-colors" onclick="event.preventDefault(); TicsEventCard.toggleFavorite('${data.id}', this)">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                    ${categoryBadge}
                </div>
                <div class="p-4">
                    <p class="text-xs text-gray-500 mb-1">${data.dateFormatted}${data.time ? ' • ' + data.time : ''}</p>
                    <h3 class="font-semibold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors line-clamp-2">${TicsUtils.escapeHtml(data.title)}</h3>
                    ${showVenue && data.location ? `<p class="text-sm text-gray-500 mb-3 truncate">${TicsUtils.escapeHtml(data.location)}</p>` : '<div class="mb-3"></div>'}
                    ${priceDisplay}
                </div>
            </a>
        `;
    },

    /**
     * Render featured/large event card (spans 2 columns and 2 rows)
     * @param {Object} event - Event data from API
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderFeatured(event, options = {}) {
        const { showMatch = true } = options;
        const data = this.normalizeEvent(event);
        if (!data) return '';

        // Build event URL - format: /bilete/[event-slug]-[city-slug]
        let eventUrl = `/event/${data.id}`;
        if (data.slug) {
            eventUrl = '/bilete/' + data.slug;
            if (data.citySlug) {
                eventUrl += '-' + data.citySlug;
            }
        }

        return `
            <a href="${eventUrl}" class="event-card bg-white rounded-2xl overflow-hidden border border-gray-200 sm:col-span-2 sm:row-span-2 group">
                <div class="relative aspect-[16/10] sm:aspect-[16/12] overflow-hidden">
                    <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="absolute inset-0 w-full h-full object-cover event-img" loading="lazy" onerror="this.src='${this.PLACEHOLDER}'">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                    <div class="absolute top-4 left-4 flex items-center gap-2">
                        ${showMatch && data.aiMatch ? `
                        <div class="flex items-center gap-1.5 bg-white/95 px-2.5 py-1 rounded-full">
                            <span class="w-2 h-2 ${TicsUtils.getMatchColor(data.aiMatch)} rounded-full"></span>
                            <span class="text-xs font-semibold">${data.aiMatch}% match</span>
                        </div>
                        ` : ''}
                        ${data.isTrending ? '<span class="trending-badge px-2.5 py-1 rounded-full text-xs font-semibold text-white">🔥 Trending</span>' : ''}
                    </div>
                    <button class="absolute top-4 right-4 w-10 h-10 bg-white/90 rounded-full flex items-center justify-center hover:bg-white transition-colors" onclick="event.preventDefault(); TicsEventCard.toggleFavorite('${data.id}', this)">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                    <div class="absolute bottom-4 left-4 right-4">
                        ${data.categoryName ? `<span class="inline-block px-3 py-1 bg-white/20 backdrop-blur text-white text-xs font-medium rounded-full mb-2">${TicsUtils.escapeHtml(data.categoryName)}</span>` : ''}
                        <h3 class="text-xl sm:text-2xl font-bold text-white mb-1 group-hover:underline">${TicsUtils.escapeHtml(data.title)}</h3>
                        <p class="text-white/80 text-sm">${TicsUtils.escapeHtml(data.location)}</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">${data.dateFormatted}${data.time ? ' • ' + data.time : ''}</p>
                            <p class="text-lg font-bold text-gray-900">${data.priceFormatted}</p>
                        </div>
                        <div class="text-right">
                            ${data.soldPercent ? `<p class="text-xs text-red-500 font-medium mb-1">⚡ ${data.soldPercent}% sold</p>` : ''}
                            ${data.interestedCount ? `<p class="text-xs text-gray-400">${data.interestedCount} interesați</p>` : ''}
                        </div>
                    </div>
                </div>
            </a>
        `;
    },

    /**
     * Render horizontal event card (for list view)
     * @param {Object} event - Event data from API
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderHorizontal(event, options = {}) {
        const { showMatch = true } = options;
        const data = this.normalizeEvent(event);
        if (!data) return '';

        // Build event URL - format: /bilete/[event-slug]-[city-slug]
        let eventUrl = `/event/${data.id}`;
        if (data.slug) {
            eventUrl = '/bilete/' + data.slug;
            if (data.citySlug) {
                eventUrl += '-' + data.citySlug;
            }
        }

        return `
            <a href="${eventUrl}" class="event-card flex bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative w-32 sm:w-40 flex-shrink-0 overflow-hidden">
                    <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="absolute inset-0 w-full h-full object-cover event-img" loading="lazy" onerror="this.src='${this.PLACEHOLDER}'">
                    ${showMatch && data.aiMatch ? `
                    <div class="absolute top-2 left-2 flex items-center gap-1 bg-white/95 px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 ${TicsUtils.getMatchColor(data.aiMatch)} rounded-full"></span>
                        <span class="text-xs font-semibold">${data.aiMatch}%</span>
                    </div>
                    ` : ''}
                </div>
                <div class="flex-1 p-4 flex flex-col justify-center min-w-0">
                    <p class="text-xs text-gray-500 mb-1">${data.dateFormatted}${data.time ? ' • ' + data.time : ''}</p>
                    <h3 class="font-semibold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors truncate">${TicsUtils.escapeHtml(data.title)}</h3>
                    <p class="text-sm text-gray-500 truncate">${TicsUtils.escapeHtml(data.location)}</p>
                </div>
                <div class="p-4 flex flex-col items-end justify-center">
                    <span class="font-semibold text-gray-900">${data.priceFormatted}</span>
                    ${data.soldPercent && data.soldPercent >= 60 ? `<span class="text-xs text-amber-600 font-medium">⚡ Ultimele locuri</span>` : ''}
                </div>
            </a>
        `;
    },

    /**
     * Render compact event card (for sidebars)
     * @param {Object} event - Event data from API
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderCompact(event, options = {}) {
        const data = this.normalizeEvent(event);
        if (!data) return '';

        // Build event URL - format: /bilete/[event-slug]-[city-slug]
        let eventUrl = `/event/${data.id}`;
        if (data.slug) {
            eventUrl = '/bilete/' + data.slug;
            if (data.citySlug) {
                eventUrl += '-' + data.citySlug;
            }
        }

        return `
            <a href="${eventUrl}" class="flex gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0">
                    <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="w-full h-full object-cover" loading="lazy" onerror="this.src='${this.PLACEHOLDER}'">
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-gray-900 text-sm truncate group-hover:text-indigo-600 transition-colors">${TicsUtils.escapeHtml(data.title)}</h4>
                    <p class="text-xs text-gray-500">${data.dateFormatted}</p>
                    <p class="text-xs font-medium text-indigo-600">${data.priceFormatted}</p>
                </div>
            </a>
        `;
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
     * Render skeleton loading card
     * @param {string} type - 'grid', 'featured', 'horizontal'
     * @returns {string} HTML string
     */
    renderSkeleton(type = 'grid') {
        if (type === 'featured') {
            return `
                <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 sm:col-span-2 sm:row-span-2">
                    <div class="aspect-[16/10] sm:aspect-[16/12] skeleton"></div>
                    <div class="p-5">
                        <div class="skeleton skeleton-text w-1/3 mb-2"></div>
                        <div class="skeleton skeleton-title w-2/3"></div>
                    </div>
                </div>
            `;
        }
        if (type === 'horizontal') {
            return `
                <div class="flex bg-white rounded-2xl overflow-hidden border border-gray-200">
                    <div class="w-32 sm:w-40 h-28 skeleton"></div>
                    <div class="flex-1 p-4">
                        <div class="skeleton skeleton-text w-1/4 mb-2"></div>
                        <div class="skeleton skeleton-title w-3/4 mb-2"></div>
                        <div class="skeleton skeleton-text w-1/2"></div>
                    </div>
                </div>
            `;
        }
        return `
            <div class="bg-white rounded-2xl overflow-hidden border border-gray-200">
                <div class="aspect-[4/3] skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-text w-1/3 mb-2"></div>
                    <div class="skeleton skeleton-title w-3/4 mb-2"></div>
                    <div class="skeleton skeleton-text w-1/2 mb-3"></div>
                    <div class="skeleton w-1/3 h-5"></div>
                </div>
            </div>
        `;
    },

    /**
     * Render multiple skeleton cards
     * @param {number} count - Number of skeletons
     * @param {string} type - Skeleton type
     * @returns {string} HTML string
     */
    renderSkeletons(count = 8, type = 'grid') {
        return Array(count).fill(this.renderSkeleton(type)).join('');
    },

    /**
     * Normalize event data from various API formats
     * @param {Object} apiEvent - Raw event data from API
     * @returns {Object|null} Normalized event object
     */
    normalizeEvent(apiEvent) {
        if (!apiEvent) return null;

        // Get date
        const rawDate = apiEvent.starts_at || apiEvent.event_date || apiEvent.start_date || apiEvent.date;
        const date = rawDate ? new Date(rawDate) : null;

        // Get venue info
        let venueName = '', venueCity = '', citySlug = '';
        if (typeof apiEvent.venue === 'string') {
            venueName = apiEvent.venue;
        } else if (apiEvent.venue && typeof apiEvent.venue === 'object') {
            venueName = apiEvent.venue.name || '';
            venueCity = apiEvent.venue.city || '';
        }
        if (!venueName && apiEvent.venue_name) venueName = apiEvent.venue_name;
        if (!venueCity && apiEvent.venue_city) venueCity = apiEvent.venue_city;
        if (!venueCity && apiEvent.city) venueCity = apiEvent.city;

        // Generate city slug for URL
        if (venueCity) {
            citySlug = TicsUtils.slugify(venueCity);
        }

        // Get category
        let categoryName = '';
        if (typeof apiEvent.category === 'string') {
            categoryName = apiEvent.category;
        } else if (apiEvent.category && typeof apiEvent.category === 'object') {
            categoryName = apiEvent.category.name || '';
        }

        // Get price
        const minPrice = apiEvent.price_from || apiEvent.min_price || apiEvent.price || 0;

        // Get image
        const image = TicsUtils.getStorageUrl(apiEvent.image_url || apiEvent.featured_image || apiEvent.image || apiEvent.poster_url);

        // AI Match (demo or from API)
        const aiMatch = apiEvent.ai_match || Math.floor(Math.random() * 25 + 75);

        return {
            id: apiEvent.id,
            slug: apiEvent.slug || '',
            citySlug: citySlug,
            title: apiEvent.name || apiEvent.title || 'Eveniment',
            image: image,
            date: date,
            dateFormatted: date ? TicsUtils.formatDate(date) : '',
            time: apiEvent.start_time || (date ? TicsUtils.formatTime(date) : ''),
            venueName: venueName,
            venueCity: venueCity,
            location: venueCity ? `${venueName}, ${venueCity}` : venueName,
            minPrice: minPrice,
            priceFormatted: minPrice > 0 ? `de la ${minPrice} RON` : 'Gratuit',
            categoryName: categoryName,
            aiMatch: aiMatch,
            isTrending: apiEvent.is_trending || apiEvent.trending || false,
            isEarlyBird: apiEvent.is_early_bird || apiEvent.early_bird || false,
            hasVip: apiEvent.has_vip || apiEvent.vip_available || false,
            isSoldOut: apiEvent.is_sold_out || false,
            soldPercent: apiEvent.sold_percent || apiEvent.capacity_sold || null,
            interestedCount: apiEvent.interested_count || null,
            _raw: apiEvent
        };
    },

    /**
     * Toggle favorite status
     * @param {string} eventId - Event ID
     * @param {Element} button - Button element
     */
    toggleFavorite(eventId, button) {
        const svg = button.querySelector('svg');
        const isFavorited = svg.getAttribute('fill') === 'currentColor';

        if (isFavorited) {
            svg.setAttribute('fill', 'none');
            svg.classList.remove('text-red-500');
            svg.classList.add('text-gray-600');
        } else {
            svg.setAttribute('fill', 'currentColor');
            svg.classList.remove('text-gray-600');
            svg.classList.add('text-red-500');
            button.classList.add('animate-cartBounce');
            setTimeout(() => button.classList.remove('animate-cartBounce'), 400);
        }

        // TODO: Save to API/localStorage
        console.log(`Toggle favorite for event ${eventId}: ${!isFavorited}`);
    }
};

// Make available globally
window.TicsEventCard = TicsEventCard;
