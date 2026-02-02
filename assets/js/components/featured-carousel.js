/**
 * Ambilet.ro - Featured Events Carousel Component
 * Displays general featured events in an infinite auto-scrolling carousel
 * Supports drag/swipe interaction
 *
 * Usage: FeaturedCarousel.init({ category: 'concerte' }) // optional category filter
 */

const FeaturedCarousel = {
    elements: {
        section: 'featuredCarouselSection',
        track: 'featuredCarouselTrack'
    },

    // Drag state
    isDragging: false,
    startX: 0,
    scrollLeft: 0,
    track: null,
    container: null,
    animationPaused: false,
    initialized: false,

    /**
     * Initialize the carousel
     * @param {Object} options - Configuration options
     * @param {string} options.category - Optional category slug to filter by
     * @param {string} options.city - Optional city slug to filter by
     * @param {string} options.genre - Optional genre slug to filter by
     */
    async init(options = {}) {
        // Prevent double initialization
        if (this.initialized) return;

        const section = document.getElementById(this.elements.section);
        const track = document.getElementById(this.elements.track);

        if (!section || !track) return;

        this.initialized = true;
        this.track = track;
        this.container = track.parentElement;

        try {
            const params = new URLSearchParams({
                type: 'general',
                limit: 12
            });

            // Add optional filters
            if (options.category) params.append('category', options.category);
            if (options.city) params.append('city', options.city);
            if (options.genre) params.append('genre', options.genre);

            const response = await AmbiletAPI.get('/events/featured?' + params.toString());
            let events = response.data?.events || (Array.isArray(response.data) ? response.data : []);

            // Deduplicate events by id AND slug (to catch any duplicates)
            const seenIds = new Set();
            const seenSlugs = new Set();
            events = events.filter(event => {
                const id = event.id;
                const slug = event.slug || '';
                // Skip if we've already seen this ID or slug
                if (seenIds.has(id) || (slug && seenSlugs.has(slug))) {
                    console.warn('[FeaturedCarousel] Duplicate event filtered:', { id, slug, name: event.name });
                    return false;
                }
                seenIds.add(id);
                if (slug) seenSlugs.add(slug);
                return true;
            });

            console.log('[FeaturedCarousel] Loaded', events.length, 'unique events');

            if (events.length > 0) {
                // Clear track first to ensure no stale content
                track.innerHTML = '';

                // Shuffle events for random order
                const shuffledEvents = this.shuffleArray([...events]);

                // Render cards
                const cardsHtml = shuffledEvents.map(event => this.renderCard(event)).join('');

                // Only duplicate for infinite scroll if we have enough events
                // With few events, duplication is too obvious
                if (shuffledEvents.length >= 4) {
                    // Duplicate the content for seamless infinite scroll
                    track.innerHTML = cardsHtml + cardsHtml;
                } else {
                    // For few events, duplicate more times to fill the screen
                    const duplications = Math.ceil(8 / shuffledEvents.length);
                    track.innerHTML = cardsHtml.repeat(duplications);
                }

                // Show section
                section.classList.remove('hidden');

                // Adjust animation duration based on number of cards
                const cardCount = shuffledEvents.length;
                const duration = Math.max(20, cardCount * 4); // 4 seconds per card, minimum 20s
                track.style.animationDuration = duration + 's';

                // Initialize drag functionality
                this.initDrag();
            }
        } catch (e) {
            console.warn('Failed to load featured carousel:', e);
        }
    },

    /**
     * Initialize drag/swipe functionality
     */
    initDrag() {
        const track = this.track;
        const container = this.container;
        if (!track || !container) return;

        // Mouse events
        container.addEventListener('mousedown', (e) => this.handleDragStart(e));
        container.addEventListener('mousemove', (e) => this.handleDragMove(e));
        container.addEventListener('mouseup', () => this.handleDragEnd());
        container.addEventListener('mouseleave', () => this.handleDragEnd());

        // Touch events
        container.addEventListener('touchstart', (e) => this.handleDragStart(e), { passive: true });
        container.addEventListener('touchmove', (e) => this.handleDragMove(e), { passive: true });
        container.addEventListener('touchend', () => this.handleDragEnd());

        // Prevent default drag behavior on links
        track.querySelectorAll('a').forEach(link => {
            link.addEventListener('dragstart', (e) => e.preventDefault());
        });

        // Add cursor styles
        container.style.cursor = 'grab';
    },

    /**
     * Handle drag start
     */
    handleDragStart(e) {
        this.isDragging = true;
        this.startX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;

        // Get current transform position
        const style = window.getComputedStyle(this.track);
        const matrix = new DOMMatrix(style.transform);
        this.scrollLeft = matrix.m41; // translateX value

        // Pause animation
        this.track.style.animationPlayState = 'paused';
        this.animationPaused = true;

        // Update cursor
        this.container.style.cursor = 'grabbing';

        // Prevent text selection
        document.body.style.userSelect = 'none';
    },

    /**
     * Handle drag move
     */
    handleDragMove(e) {
        if (!this.isDragging) return;

        const x = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
        const walk = (x - this.startX) * 1.5; // Multiplier for drag speed

        // Apply transform
        this.track.style.animation = 'none';
        this.track.style.transform = `translateX(${this.scrollLeft + walk}px)`;
    },

    /**
     * Handle drag end
     */
    handleDragEnd() {
        if (!this.isDragging) return;

        this.isDragging = false;
        this.container.style.cursor = 'grab';
        document.body.style.userSelect = '';

        // Get current position
        const style = window.getComputedStyle(this.track);
        const matrix = new DOMMatrix(style.transform);
        const currentX = matrix.m41;

        // Get track width (half since we duplicated)
        const trackWidth = this.track.scrollWidth / 2;

        // Normalize position to be within bounds
        let normalizedX = currentX % trackWidth;
        if (normalizedX > 0) normalizedX -= trackWidth;

        // Resume animation from current position
        this.track.style.transform = '';
        this.track.style.animation = '';

        // Calculate animation offset percentage
        const offsetPercent = (Math.abs(normalizedX) / trackWidth) * 50;

        // Apply offset to animation
        this.track.style.animationDelay = `-${offsetPercent / 50 * parseFloat(this.track.style.animationDuration || 30)}s`;
        this.track.style.animationPlayState = 'running';
        this.animationPaused = false;
    },

    /**
     * Shuffle array using Fisher-Yates algorithm
     */
    shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    },

    /**
     * Render a carousel card
     */
    renderCard(event) {
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateStr = event.starts_at || event.date;
        const date = dateStr ? new Date(dateStr) : new Date();
        const day = date.getDate();
        const month = months[date.getMonth()];

        const image = event.featured_image || event.image || '/assets/images/default-event.png';
        const title = event.name || event.title || 'Eveniment';
        const venue = event.venue_name || (event.venue ? event.venue.name : '');
        const city = event.venue_city || (event.venue ? event.venue.city : '');
        const location = city ? (venue ? venue + ', ' + city : city) : venue;
        const priceFrom = event.price_from ? 'de la ' + event.price_from + ' lei' : '';
        const category = event.category?.name || event.category || '';

        return '<a href="/bilete/' + (event.slug || '') + '" class="featured-carousel-card group relative overflow-hidden rounded-2xl bg-white shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">' +
            '<div class="relative h-48 overflow-hidden">' +
                '<img src="' + image + '" alt="' + this.escapeHtml(title) + '" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110" loading="lazy">' +
                '<div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>' +
                // Category badge
                (category ? '<div class="absolute top-3 left-3"><span class="px-3 py-1 text-[10px] font-bold text-white uppercase bg-primary/90 backdrop-blur-sm rounded-full">' + this.escapeHtml(category) + '</span></div>' : '') +
                // Date badge
                '<div class="absolute bottom-3 right-3 bg-white/95 backdrop-blur-sm rounded-xl px-3 py-2 text-center shadow-lg">' +
                    '<div class="text-xl font-extrabold text-secondary leading-none">' + day + '</div>' +
                    '<div class="text-xs font-semibold text-primary uppercase">' + month + '</div>' +
                '</div>' +
                // Featured badge
                '<div class="absolute top-3 right-3">' +
                    '<span class="flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-white rounded-full bg-accent/90 backdrop-blur-sm">' +
                        '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' +
                    '</span>' +
                '</div>' +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="mb-2 font-bold leading-tight text-secondary line-clamp-2 group-hover:text-primary transition-colors">' + this.escapeHtml(title) + '</h3>' +
                (location ? '<p class="flex items-center gap-1.5 mb-3 text-sm text-muted"><svg class="w-4 h-4 text-primary/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg><span class="truncate">' + this.escapeHtml(location) + '</span></p>' : '') +
                '<div class="flex items-center justify-between pt-3 border-t border-border">' +
                    (priceFrom ? '<span class="text-sm font-bold text-primary">' + priceFrom + '</span>' : '<span></span>') +
                    '<span class="inline-flex items-center gap-1 text-sm font-semibold text-secondary group-hover:text-primary">' +
                        'Vezi' +
                        '<svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>' +
                    '</span>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    /**
     * Escape HTML characters
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Make available globally
window.FeaturedCarousel = FeaturedCarousel;
