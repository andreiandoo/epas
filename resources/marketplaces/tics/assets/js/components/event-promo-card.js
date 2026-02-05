/**
 * TICS.ro - Event Promo Card Component
 * Promotional/Ad cards for events listings
 *
 * Variants:
 * - renderPromo() - Gradient background promo card
 * - renderAIRecommend() - AI recommendation style card
 */

const TicsEventPromoCard = {
    /**
     * Render General Featured card (big 2x2 promo with image)
     * This is the main highlighted promo card, spans 2 columns and 2 rows
     * @param {Object} event - Event data
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderGeneralFeatured(event, options = {}) {
        const { showMatch = true } = options;
        const data = this.normalizeEvent(event);
        if (!data) return '';

        const eventUrl = data.slug ? ('/bilete/' + data.slug) : `/event/${data.id}`;

        return `
            <a href="${eventUrl}" class="event-card bg-white rounded-2xl overflow-hidden border-2 border-indigo-200 sm:col-span-2 sm:row-span-2 group relative">
                <div class="absolute top-4 left-4 z-10">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-xs font-semibold rounded-full shadow-lg">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Recomandat
                    </span>
                </div>
                <div class="relative aspect-[16/10] sm:aspect-[16/12] overflow-hidden">
                    <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="absolute inset-0 w-full h-full object-cover event-img" loading="lazy" onerror="this.src='/assets/images/default-event.jpg'">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        ${showMatch && data.aiMatch ? `
                        <div class="flex items-center gap-1.5 bg-white/95 px-2.5 py-1 rounded-full">
                            <span class="w-2 h-2 ${TicsUtils.getMatchColor(data.aiMatch)} rounded-full"></span>
                            <span class="text-xs font-semibold">${data.aiMatch}% match</span>
                        </div>
                        ` : ''}
                    </div>
                    <button class="absolute top-4 right-${showMatch && data.aiMatch ? '24' : '4'} w-10 h-10 bg-white/90 rounded-full flex items-center justify-center hover:bg-white transition-colors" onclick="event.preventDefault(); TicsEventCard.toggleFavorite('${data.id}', this)">
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
                <div class="p-5 bg-gradient-to-r from-indigo-50 to-purple-50">
                    ${data._raw?.promo_message ? `<p class="text-sm text-indigo-700 font-medium mb-3">${TicsUtils.escapeHtml(data._raw.promo_message)}</p>` : ''}
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">${data.dateFormatted}${data.time ? ' • ' + data.time : ''}</p>
                            <p class="text-lg font-bold text-gray-900">${data.priceFormatted}</p>
                        </div>
                        <div class="text-right">
                            ${data.soldPercent ? `<p class="text-xs text-red-500 font-medium mb-1">⚡ ${data.soldPercent}% sold</p>` : ''}
                            <span class="inline-flex items-center gap-1 text-xs text-indigo-600 font-medium">
                                Vezi detalii
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        `;
    },

    /**
     * Render promotional event card (gradient background) - Category Featured
     * This is the smaller promo card for category-specific featured events
     * @param {Object} event - Event data
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderPromo(event, options = {}) {
        const {
            showBadge = true,
        } = options;

        const data = this.normalizeEvent(event);
        if (!data) return '';

        // Use promo_background from event or default gradient
        const gradient = data._raw?.promo_background || 'from-indigo-600 via-purple-600 to-pink-500';
        const promoMessage = data._raw?.promo_message || '';

        return `
            <div class="rounded-2xl overflow-hidden relative bg-gradient-to-br ${gradient} text-white p-5 flex flex-col justify-between min-h-[320px]">
                <div>
                    ${showBadge ? `
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-white/20 text-xs font-medium rounded-full mb-4">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Promovat
                    </span>
                    ` : ''}
                    <h3 class="text-xl font-bold mb-2">${TicsUtils.escapeHtml(data.title)}</h3>
                    ${data.description ? `<p class="text-sm text-white/80 mb-2">${TicsUtils.escapeHtml(data.description)}</p>` : ''}
                    ${promoMessage ? `<p class="text-sm text-white/90 font-medium mb-2">${TicsUtils.escapeHtml(promoMessage)}</p>` : ''}
                    <p class="text-xs text-white/60">${data.dateFormatted}${data.location ? ' • ' + TicsUtils.escapeHtml(data.location) : ''}</p>
                </div>
                <a href="${data.slug ? '/bilete/' + data.slug : '/event/' + data.id}" class="w-full py-3 bg-white text-indigo-600 font-semibold rounded-xl mt-4 hover:bg-white/90 transition-colors text-center block">
                    ${data.priceFormatted} →
                </a>
            </div>
        `;
    },

    /**
     * Render AI recommendation style card
     * @param {Object} event - Event data
     * @param {Object} options - Display options
     * @returns {string} HTML string
     */
    renderAIRecommend(event, options = {}) {
        const data = this.normalizeEvent(event);
        if (!data) return '';

        const reasonText = options.reason || 'Pe baza preferințelor tale';

        return `
            <div class="rounded-2xl bg-gradient-to-br from-amber-50 via-orange-50 to-rose-50 border-2 border-dashed border-amber-200 p-5 flex flex-col justify-between min-h-[320px]">
                <div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full mb-4">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        AI Recomandă
                    </span>
                    <h3 class="font-bold text-gray-900 mb-2">${TicsUtils.escapeHtml(data.title)}</h3>
                    <p class="text-sm text-gray-600 mb-3">${TicsUtils.escapeHtml(reasonText)}</p>
                </div>
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <img src="${data.image}" alt="${TicsUtils.escapeHtml(data.title)}" class="w-14 h-14 rounded-xl object-cover" onerror="this.src='/assets/images/default-event.jpg'">
                        <div>
                            <p class="font-semibold text-gray-900">${TicsUtils.escapeHtml(data.title)}</p>
                            <p class="text-xs text-gray-500">${data.location ? TicsUtils.escapeHtml(data.location) + ' • ' : ''}${data.dateFormatted}</p>
                            ${data.aiMatch ? `<p class="text-xs text-green-600 font-medium">${data.aiMatch}% match</p>` : ''}
                        </div>
                    </div>
                    <a href="${data.slug ? '/bilete/' + data.slug : '/event/' + data.id}" class="w-full py-2.5 bg-amber-500 text-white font-semibold rounded-xl hover:bg-amber-600 transition-colors text-sm text-center block">
                        Află mai multe →
                    </a>
                </div>
            </div>
        `;
    },

    /**
     * Render AI banner (for top of listings)
     * @param {number} count - Number of AI-matched events
     * @param {Array} preferences - User preferences tags
     * @returns {string} HTML string
     */
    renderAIBanner(count, preferences = []) {
        const prefTags = preferences.length > 0
            ? preferences.map(p => `<span class="px-2.5 py-1 bg-white/80 rounded-full text-xs font-medium text-gray-700">${TicsUtils.escapeHtml(p)}</span>`).join('')
            : '';

        return `
            <div class="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 rounded-2xl p-5 mb-6 border border-indigo-100">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-semibold text-gray-900">AI a găsit ${count} evenimente perfecte pentru tine</h3>
                            <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">Bazat pe preferințele tale și istoricul de navigare.</p>
                        ${prefTags ? `<div class="flex flex-wrap gap-2">${prefTags}</div>` : ''}
                    </div>
                    <button class="text-sm font-medium text-indigo-600 hover:underline whitespace-nowrap hidden sm:block">Editează preferințe</button>
                </div>
            </div>
        `;
    },

    /**
     * Normalize event data (reuses TicsEventCard logic)
     * @param {Object} apiEvent - Raw event data
     * @returns {Object|null} Normalized data
     */
    normalizeEvent(apiEvent) {
        if (!apiEvent) return null;

        // Use TicsEventCard normalizer if available
        if (typeof TicsEventCard !== 'undefined' && TicsEventCard.normalizeEvent) {
            return TicsEventCard.normalizeEvent(apiEvent);
        }

        // Fallback normalization
        const rawDate = apiEvent.starts_at || apiEvent.event_date || apiEvent.start_date || apiEvent.date;
        const date = rawDate ? new Date(rawDate) : null;

        let venueName = '', venueCity = '';
        if (typeof apiEvent.venue === 'string') {
            venueName = apiEvent.venue;
        } else if (apiEvent.venue && typeof apiEvent.venue === 'object') {
            venueName = apiEvent.venue.name || '';
            venueCity = apiEvent.venue.city || '';
        }

        const minPrice = apiEvent.price_from || apiEvent.min_price || apiEvent.price || 0;

        return {
            id: apiEvent.id,
            slug: apiEvent.slug || '',
            title: apiEvent.name || apiEvent.title || 'Eveniment',
            description: apiEvent.short_description || apiEvent.description || '',
            image: TicsUtils.getStorageUrl(apiEvent.image_url || apiEvent.featured_image || apiEvent.image),
            date: date,
            dateFormatted: date ? TicsUtils.formatDate(date) : '',
            time: date ? date.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }) : '',
            location: venueCity ? `${venueName}, ${venueCity}` : venueName,
            minPrice: minPrice,
            priceFormatted: minPrice > 0 ? `Bilete de la ${minPrice} RON` : 'Gratuit',
            aiMatch: apiEvent.ai_match || Math.floor(Math.random() * 15 + 85),
            soldPercent: apiEvent.sold_percentage || 0,
            _raw: apiEvent
        };
    }
};

// Make available globally
window.TicsEventPromoCard = TicsEventPromoCard;
