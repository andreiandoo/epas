/**
 * Ambilet.ro - Event Card Component
 * Reusable event card for listings
 */

const AmbiletEventCard = {
    /**
     * Render event card HTML
     * @param {Object} event - Event data
     * @param {Object} options - Display options
     */
    render(event, options = {}) {
        const {
            showPrice = true,
            showDate = true,
            showLocation = true,
            size = 'default' // 'default', 'small', 'large'
        } = options;

        const imageUrl = event.featured_image || event.image || AMBILET_CONFIG.PLACEHOLDER_EVENT;
        const eventUrl = `/event/${event.slug}`;

        // Format date
        const startDate = new Date(event.start_date);
        const day = startDate.getDate();
        const month = startDate.toLocaleDateString('ro-RO', { month: 'short' }).toUpperCase();

        // Price range
        const minPrice = event.min_price || event.ticket_types?.[0]?.price || 0;
        const priceDisplay = minPrice > 0 ? `de la ${AmbiletUtils.formatCurrency(minPrice)}` : 'Gratuit';

        // Location
        const location = event.venue?.city || event.city || '';

        return `
        <article class="event-card group bg-white rounded-2xl border border-border overflow-hidden cursor-pointer" onclick="window.location.href='${eventUrl}'">
            <!-- Image -->
            <div class="relative aspect-[16/10] overflow-hidden">
                <img
                    src="${imageUrl}"
                    alt="${event.title}"
                    class="event-image w-full h-full object-cover"
                    loading="lazy"
                    onerror="this.src='${AMBILET_CONFIG.PLACEHOLDER_EVENT}'"
                >

                <!-- Date Badge -->
                ${showDate ? `
                <div class="absolute top-3 left-3 date-badge">
                    <div class="date-badge-day">${day}</div>
                    <div class="date-badge-month">${month}</div>
                </div>
                ` : ''}

                <!-- Category Badge -->
                ${event.category ? `
                <div class="absolute top-3 right-3 px-3 py-1 bg-white/90 backdrop-blur rounded-full text-xs font-semibold text-secondary">
                    ${event.category.name || event.category}
                </div>
                ` : ''}

                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end">
                    <div class="p-4 w-full">
                        <span class="inline-flex items-center gap-1 text-white text-sm font-medium">
                            <span>Vezi detalii</span>
                            <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4">
                <h3 class="text-lg font-bold text-secondary mb-2 line-clamp-2 group-hover:text-primary transition-colors">
                    ${event.title}
                </h3>

                ${showLocation && location ? `
                <div class="flex items-center gap-1.5 text-sm text-muted mb-3">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">${location}</span>
                </div>
                ` : ''}

                ${showPrice ? `
                <div class="flex items-center justify-between">
                    <span class="text-primary font-bold">${priceDisplay}</span>
                    <span class="text-xs text-muted">
                        ${event.tickets_sold || 0} bilete vândute
                    </span>
                </div>
                ` : ''}
            </div>
        </article>
        `;
    },

    /**
     * Render skeleton loading card
     */
    renderSkeleton() {
        return `
        <div class="bg-white rounded-2xl border border-border overflow-hidden">
            <div class="skeleton skeleton-image aspect-[16/10]"></div>
            <div class="p-4">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-text w-3/4"></div>
                <div class="skeleton skeleton-text w-1/2 mt-4"></div>
            </div>
        </div>
        `;
    },

    /**
     * Render multiple skeleton cards
     */
    renderSkeletons(count = 6) {
        return Array(count).fill(this.renderSkeleton()).join('');
    },

    /**
     * Render featured event card (larger, horizontal)
     */
    renderFeatured(event) {
        const imageUrl = event.featured_image || event.image || AMBILET_CONFIG.PLACEHOLDER_EVENT;
        const eventUrl = `/event/${event.slug}`;

        const startDate = new Date(event.start_date);
        const dateFormatted = startDate.toLocaleDateString('ro-RO', {
            weekday: 'long',
            day: 'numeric',
            month: 'long'
        });

        const minPrice = event.min_price || event.ticket_types?.[0]?.price || 0;

        return `
        <article class="event-card group relative bg-white rounded-3xl overflow-hidden cursor-pointer shadow-lg" onclick="window.location.href='${eventUrl}'">
            <div class="flex flex-col lg:flex-row">
                <!-- Image -->
                <div class="relative lg:w-2/3 aspect-video lg:aspect-auto overflow-hidden">
                    <img
                        src="${imageUrl}"
                        alt="${event.title}"
                        class="event-image w-full h-full object-cover"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t lg:bg-gradient-to-r from-black/60 to-transparent"></div>

                    <!-- Featured badge -->
                    <div class="absolute top-4 left-4">
                        <span class="badge badge-exclusive">
                            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Recomandat
                        </span>
                    </div>
                </div>

                <!-- Content -->
                <div class="lg:w-1/3 p-6 lg:p-8 flex flex-col justify-center">
                    ${event.category ? `
                    <span class="text-sm font-semibold text-primary mb-2">
                        ${event.category.name || event.category}
                    </span>
                    ` : ''}

                    <h3 class="text-2xl lg:text-3xl font-bold text-secondary mb-3 group-hover:text-primary transition-colors">
                        ${event.title}
                    </h3>

                    <div class="space-y-2 mb-6">
                        <div class="flex items-center gap-2 text-muted">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>${dateFormatted}</span>
                        </div>
                        ${event.venue ? `
                        <div class="flex items-center gap-2 text-muted">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <span>${event.venue.name}, ${event.venue.city}</span>
                        </div>
                        ` : ''}
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-sm text-muted">de la</span>
                            <span class="text-2xl font-bold text-primary ml-1">${AmbiletUtils.formatCurrency(minPrice)}</span>
                        </div>
                        <button class="btn btn-primary">
                            Cumpără bilete
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </article>
        `;
    },

    /**
     * Render compact event card (for sidebars, lists)
     */
    renderCompact(event) {
        const imageUrl = event.featured_image || event.image || AMBILET_CONFIG.PLACEHOLDER_EVENT;
        const eventUrl = `/event/${event.slug}`;

        const startDate = new Date(event.start_date);
        const day = startDate.getDate();
        const month = startDate.toLocaleDateString('ro-RO', { month: 'short' });

        return `
        <a href="${eventUrl}" class="flex gap-4 p-3 rounded-xl hover:bg-surface transition-colors group">
            <div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0">
                <img src="${imageUrl}" alt="${event.title}" class="w-full h-full object-cover" loading="lazy">
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-secondary truncate group-hover:text-primary transition-colors">${event.title}</h4>
                <p class="text-sm text-muted">${day} ${month}</p>
                ${event.venue ? `<p class="text-sm text-muted truncate">${event.venue.city}</p>` : ''}
            </div>
        </a>
        `;
    }
};

// Make available globally
window.AmbiletEventCard = AmbiletEventCard;
