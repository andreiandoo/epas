<?php
/**
 * Homepage - Ambilet.ro
 * Based on ticketing-homepage-v3.html template
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Bilete Evenimente Romania';
$pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Platforma de ticketing pentru evenimente din Romania.';
$currentPage = 'home';
$transparentHeader = true;
$headExtra = '<link rel="stylesheet" href="' . asset('assets/css/homepage.css') . '">';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Slider Section - 3D Coverflow Style -->
<section class="relative py-8 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 mt-18 pt-32 mobile:pt-12" id="heroSlider">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Main 3D Carousel -->
        <div class="relative" id="heroSection">
            <!-- Slider Navigation Arrows -->
            <button id="heroPrev" class="absolute z-30 flex items-center justify-center w-10 h-10 text-white transition-all -translate-y-1/2 rounded-full shadow-lg md:w-12 md:h-12 left-2 md:left-4 top-1/2 bg-black/50 hover:bg-primary">
                <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button id="heroNext" class="absolute z-30 flex items-center justify-center w-10 h-10 text-white transition-all -translate-y-1/2 rounded-full shadow-lg md:w-12 md:h-12 right-2 md:right-4 top-1/2 bg-black/50 hover:bg-primary">
                <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- 3D Slides Container -->
            <div class="coverflow-container" id="coverflowContainer">
                <div id="heroSlides" class="coverflow-track">
                    <!-- Loading Skeleton -->
                    <div class="coverflow-slide coverflow-left">
                        <div class="coverflow-slide-inner skeleton"></div>
                    </div>
                    <div class="coverflow-slide coverflow-center">
                        <div class="coverflow-slide-inner skeleton"></div>
                    </div>
                    <div class="coverflow-slide coverflow-right">
                        <div class="coverflow-slide-inner skeleton"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thumbnails Navigation -->
        <div class="mt-6 md:mt-8">
            <div class="flex justify-center gap-2 px-4 pb-2 overflow-x-auto md:gap-3 thumbnails-scroll" id="heroThumbnails">
                <!-- Thumbnails will be loaded dynamically -->
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="flex-shrink-0 w-16 h-16 rounded-lg md:w-20 md:h-20 skeleton"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<?php // require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<!-- Promoted & Recommended Events -->
<section class="py-10 bg-white md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 md:gap-5" id="promotedEventsGrid">
            <!-- Promoted events will be loaded dynamically -->
            <?php for ($i = 0; $i < 12; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-xl border-border">
                <div class="skeleton aspect-[2/3]"></div>
                <div class="h-8 skeleton"></div>
                <div class="p-3">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="py-10 md:py-14 bg-surface lazy-section" id="categoriesSection" data-lazy-load="categories">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-8 text-xl font-bold md:text-2xl text-secondary">Explorează dupa categorie</h2>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 md:gap-4" id="categoriesGrid">
            <!-- Categories will be loaded dynamically -->
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
        </div>
    </div>
</section>

<!-- Events by City (Combined with Latest Events) -->
<section class="py-10 bg-white md:py-14 lazy-section" id="cityEventsSection" data-lazy-load="cityEvents">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- City Filter Buttons -->
        <div class="flex flex-wrap gap-2 mb-8" id="cityFilterButtons">
            <button class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-full city-filter-btn active bg-primary" data-city="">
                Toate
            </button>
            <!-- City buttons will be loaded dynamically -->
        </div>

        <!-- Events Grid (filtered by city) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 md:gap-5" id="cityEventsGrid">
            <!-- Events will be loaded dynamically -->
            <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="skeleton h-44"></div>
                <div class="p-4">
                    <div class="w-1/3 mb-2 skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="mt-10 text-center">
            <a href="/evenimente" class="inline-flex items-center gap-2 px-8 py-4 font-bold transition-all border-2 border-primary text-primary rounded-xl hover:bg-primary hover:text-white">
                Vezi mai multe evenimente
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- Organizers CTA -->
<section class="py-12 bg-white md:py-16 lazy-section" id="organizersCta" data-lazy-load="static">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center gap-8 p-6 bg-surface rounded-3xl md:p-10 md:flex-row">
            <div class="flex-1">
                <span class="inline-block px-3 py-1.5 bg-accent/20 text-accent font-bold text-xs rounded-full mb-4 uppercase tracking-wide">Pentru Organizatori</span>
                <h2 class="mb-3 text-2xl font-bold md:text-3xl text-secondary">Vrei sa-ti vinzi biletele prin <?= SITE_NAME ?>?</h2>
                <p class="mb-6 text-muted">Comisioane transparente, plati rapide si suport dedicat pentru organizatori.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="/organizator/register" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary rounded-xl">
                        Inregistreaza-te gratuit
                    </a>
                    <a href="/organizator/landing" class="px-6 py-3 font-bold transition-colors border-2 border-secondary text-secondary rounded-xl hover:bg-secondary hover:text-white">
                        Afla mai multe
                    </a>
                </div>
            </div>
            <div class="flex-shrink-0 hidden md:block">
                <div class="flex items-center justify-center w-40 h-40 bg-primary/10 rounded-2xl">
                    <svg class="w-20 h-20 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'SCRIPTS'
<script>
// 3D Coverflow Hero Carousel Module
const HeroSlider = {
    events: [],
    currentIndex: 0,
    autoplayInterval: null,
    autoplayDelay: 5000, // 5 seconds

    async init() {
        try {
            // Load homepage featured events
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=12');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.render();
                this.renderThumbnails();
                this.updateSlidePositions();
                this.bindEvents();
                this.startAutoplay();
            }
        } catch (error) {
            console.warn('Failed to load hero slider events:', error);
            // Hide section if no events
            const section = document.getElementById('heroSlider');
            if (section) section.style.display = 'none';
        }
    },

    render() {
        const slidesContainer = document.getElementById('heroSlides');
        if (!slidesContainer || this.events.length === 0) return;

        // Create coverflow slides - dimensions handled by CSS
        slidesContainer.innerHTML = this.events.map((event, index) => {
            const image = getStorageUrl(event.homepage_featured_image || event.featured_image || event.image);
            const date = new Date(event.starts_at || event.start_date || event.event_date);
            const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
            const location = event.venue_name || event.venue?.name || event.city || 'Romania';
            const category = event.category?.name || event.type || 'Eveniment';
            const priceFrom = event.price_from ? `de la ${event.price_from} Lei` : '';

            return `
                <div class="coverflow-slide" data-index="${index}">
                    <a href="/bilete/${event.slug || ''}" class="coverflow-slide-inner">
                        <img src="${image}" alt="${this.escapeHtml(event.title || 'Event')}" loading="${index < 3 ? 'eager' : 'lazy'}">
                        <div class="coverflow-slide-overlay"></div>
                        ${priceFrom ? `<div class="coverflow-slide-price">${priceFrom}</div>` : ''}
                        <div class="coverflow-slide-content">
                            <span class="coverflow-slide-badge">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                ${this.escapeHtml(category)}
                            </span>
                            <h3 class="coverflow-slide-title">${this.escapeHtml(event.title || event.name || 'Eveniment')}</h3>
                            <div class="coverflow-slide-meta">
                                <span class="coverflow-slide-date">
                                    <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${formattedDate}
                                </span>
                                <span class="coverflow-slide-location">
                                    <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    ${this.escapeHtml(location)}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            `;
        }).join('');
    },

    renderThumbnails() {
        const thumbnailsContainer = document.getElementById('heroThumbnails');
        if (!thumbnailsContainer || this.events.length === 0) return;

        thumbnailsContainer.innerHTML = this.events.map((event, index) => {
            const image = getStorageUrl(event.homepage_featured_image || event.featured_image || event.image);
            return `
                <div class="thumbnail-item ${index === 0 ? 'active' : ''}" data-index="${index}">
                    <img src="${image}" alt="${this.escapeHtml(event.title || 'Event')}" class="object-cover w-16 h-16 md:w-20 md:h-20" loading="lazy">
                </div>
            `;
        }).join('');
    },

    updateSlidePositions() {
        const slides = document.querySelectorAll('.coverflow-slide');
        const total = this.events.length;

        slides.forEach((slide, index) => {
            // Remove all position classes
            slide.classList.remove('coverflow-far-left', 'coverflow-left', 'coverflow-center', 'coverflow-right', 'coverflow-far-right', 'coverflow-hidden');

            // Calculate relative position
            let relativePos = index - this.currentIndex;

            // Handle wrapping for circular navigation
            if (relativePos > total / 2) relativePos -= total;
            if (relativePos < -total / 2) relativePos += total;

            // Assign position class based on relative position
            if (relativePos === 0) {
                slide.classList.add('coverflow-center');
            } else if (relativePos === -1) {
                slide.classList.add('coverflow-left');
            } else if (relativePos === 1) {
                slide.classList.add('coverflow-right');
            } else {
                slide.classList.add('coverflow-hidden');
            }
        });

        // Update thumbnails
        const thumbnails = document.querySelectorAll('.thumbnail-item');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('active', index === this.currentIndex);
        });

        // Scroll active thumbnail into view WITHOUT scrolling the page
        const activeThumbnail = thumbnails[this.currentIndex];
        const thumbnailsContainer = document.getElementById('heroThumbnails');
        if (activeThumbnail && thumbnailsContainer) {
            const containerRect = thumbnailsContainer.getBoundingClientRect();
            const thumbRect = activeThumbnail.getBoundingClientRect();

            // Calculate scroll position to center the thumbnail
            const scrollLeft = activeThumbnail.offsetLeft - thumbnailsContainer.offsetWidth / 2 + activeThumbnail.offsetWidth / 2;
            thumbnailsContainer.scrollTo({ left: scrollLeft, behavior: 'smooth' });
        }
    },

    goToSlide(index) {
        if (index < 0) index = this.events.length - 1;
        if (index >= this.events.length) index = 0;
        this.currentIndex = index;
        this.updateSlidePositions();
    },

    next() {
        this.goToSlide(this.currentIndex + 1);
    },

    prev() {
        this.goToSlide(this.currentIndex - 1);
    },

    startAutoplay() {
        this.stopAutoplay();
        if (this.events.length > 1) {
            this.autoplayInterval = setInterval(() => this.next(), this.autoplayDelay);
        }
    },

    stopAutoplay() {
        if (this.autoplayInterval) {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        }
    },

    bindEvents() {
        const prevBtn = document.getElementById('heroPrev');
        const nextBtn = document.getElementById('heroNext');
        const heroSection = document.getElementById('heroSlider');
        const thumbnailsContainer = document.getElementById('heroThumbnails');
        const slidesContainer = document.getElementById('heroSlides');

        // Navigation buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                this.prev();
                this.startAutoplay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                this.next();
                this.startAutoplay();
            });
        }

        // Thumbnail clicks
        if (thumbnailsContainer) {
            thumbnailsContainer.addEventListener('click', (e) => {
                const thumb = e.target.closest('.thumbnail-item');
                if (thumb) {
                    const index = parseInt(thumb.dataset.index, 10);
                    this.goToSlide(index);
                    this.startAutoplay();
                }
            });
        }

        // Side slide clicks
        if (slidesContainer) {
            slidesContainer.addEventListener('click', (e) => {
                const slide = e.target.closest('.coverflow-slide');
                if (slide && !slide.classList.contains('coverflow-center')) {
                    e.preventDefault();
                    const index = parseInt(slide.dataset.index, 10);
                    this.goToSlide(index);
                    this.startAutoplay();
                }
            });
        }

        // Pause on hover
        if (heroSection) {
            heroSection.addEventListener('mouseenter', () => this.stopAutoplay());
            heroSection.addEventListener('mouseleave', () => this.startAutoplay());
        }

        // Touch/swipe support
        let touchStartX = 0;
        if (slidesContainer) {
            slidesContainer.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                this.stopAutoplay();
            }, { passive: true });

            slidesContainer.addEventListener('touchend', (e) => {
                const touchEndX = e.changedTouches[0].clientX;
                const diff = touchStartX - touchEndX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) this.next();
                    else this.prev();
                }
                this.startAutoplay();
            }, { passive: true });
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                this.prev();
                this.startAutoplay();
            } else if (e.key === 'ArrowRight') {
                this.next();
                this.startAutoplay();
            }
        });

        // Recalculate on resize - no need to recalc sizes, CSS handles it
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.updateSlidePositions();
            }, 200);
        });
    },

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
};

// City Events Filter Module
const CityEventsFilter = {
    allEvents: [],
    cities: [],
    selectedCity: '',

    async init() {
        // Load events first, then extract cities from them
        await this.loadEvents();
        this.extractCitiesFromEvents();
        this.renderCityButtons();
        this.renderEvents();
    },

    async loadEvents() {
        try {
            const response = await AmbiletAPI.get('/marketplace-events?sort=latest&limit=30');
            if (response.data) {
                this.allEvents = Array.isArray(response.data) ? response.data : (response.data.events || response.data.data || []);
            }
        } catch (error) {
            console.warn('Failed to load events:', error);
        }
    },

    extractCitiesFromEvents() {
        // Extract unique cities from loaded events
        const cityMap = new Map();

        this.allEvents.forEach(event => {
            // Get city from venue_city (API response) or venue.city
            const cityName = event.venue_city || event.venue?.city || '';
            if (!cityName) return;

            // Normalize city name for deduplication
            const normalizedKey = cityName.toLowerCase().trim();

            if (!cityMap.has(normalizedKey)) {
                cityMap.set(normalizedKey, {
                    name: cityName,
                    slug: normalizedKey.replace(/\s+/g, '-'),
                    count: 1
                });
            } else {
                cityMap.get(normalizedKey).count++;
            }
        });

        // Sort by event count (most events first) and take top 10
        this.cities = Array.from(cityMap.values())
            .sort((a, b) => b.count - a.count)
            .slice(0, 10);
    },

    renderCityButtons() {
        const container = document.getElementById('cityFilterButtons');
        if (!container) return;

        // Keep "Toate" button, add city buttons
        const cityButtons = this.cities.map(city => `
            <button class="px-4 py-2 text-sm font-semibold transition-all rounded-full city-filter-btn" data-city="${city.name}">
                ${city.name}
            </button>
        `).join('');

        container.innerHTML = `
            <button class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-full city-filter-btn active bg-primary" data-city="">
                Toate
            </button>
            ${cityButtons}
        `;

        // Bind click events
        container.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => this.filterByCity(btn.dataset.city));
        });
    },

    filterByCity(cityName) {
        this.selectedCity = cityName;

        // Update active button
        document.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.city === cityName);
        });

        this.renderEvents();
    },

    renderEvents() {
        const container = document.getElementById('cityEventsGrid');
        if (!container) return;

        // Filter events by city if selected
        let events = this.allEvents;
        if (this.selectedCity) {
            const selectedCityLower = this.selectedCity.toLowerCase().trim();
            events = this.allEvents.filter(event => {
                const eventCity = (event.venue_city || event.venue?.city || '').toLowerCase().trim();
                return eventCity === selectedCityLower;
            });
        }

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="py-8 text-center text-muted col-span-full">Nu sunt evenimente disponibile pentru acest oraș.</p>';
            return;
        }

        // Use AmbiletEventCard component for rendering
        if (typeof AmbiletEventCard !== 'undefined') {
            container.innerHTML = AmbiletEventCard.renderMany(events.slice(0, 10), {
                urlPrefix: '/bilete/',
                showCategory: true,
                showPrice: true,
                showVenue: true
            });
        } else {
            // Fallback - shouldn't happen
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
        }

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    }
};

// Promoted Events Module
const PromotedEvents = {
    async init() {
        try {
            // Load promoted (paid) and recommended events
            const [promotedRes, recommendedRes] = await Promise.all([
                AmbiletAPI.get('/marketplace-events?promoted=1&limit=12').catch(() => ({ data: [] })),
                AmbiletAPI.get('/marketplace-events?recommended=1&limit=12').catch(() => ({ data: [] }))
            ]);

            const promoted = this.extractEvents(promotedRes);
            const recommended = this.extractEvents(recommendedRes);

            // Combine: promoted first (priority), then recommended (deduped)
            const combined = [...promoted];
            recommended.forEach(event => {
                if (!combined.find(e => e.id === event.id)) {
                    combined.push(event);
                }
            });

            // Limit to 12 and shuffle randomly
            const limited = combined.slice(0, 12);
            this.shuffle(limited);

            this.render(limited);
        } catch (error) {
            console.warn('Failed to load promoted events:', error);
        }
    },

    extractEvents(response) {
        if (!response || !response.data) return [];
        if (Array.isArray(response.data)) return response.data;
        return response.data.events || response.data.data || [];
    },

    shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    },

    render(events) {
        const container = document.getElementById('promotedEventsGrid');
        if (!container) return;

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
            return;
        }

        // Use poster-style promoted cards
        if (typeof AmbiletEventCard !== 'undefined' && typeof AmbiletEventCard.renderManyPromoted === 'function') {
            container.innerHTML = AmbiletEventCard.renderManyPromoted(events);
        } else {
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
        }

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    }
};

// Lazy Loading Module using Intersection Observer
const LazyLoader = {
    observer: null,
    loadedSections: new Set(),

    init() {
        // Create observer with rootMargin for early loading
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const section = entry.target;
                    const loadType = section.dataset.lazyLoad;

                    if (!this.loadedSections.has(loadType)) {
                        this.loadedSections.add(loadType);
                        this.loadSection(loadType, section);
                    }
                }
            });
        }, {
            rootMargin: '100px 0px', // Start loading 100px before visible
            threshold: 0.01
        });

        // Observe all lazy sections
        document.querySelectorAll('[data-lazy-load]').forEach(section => {
            this.observer.observe(section);
        });
    },

    async loadSection(type, section) {
        try {
            switch (type) {
                case 'categories':
                    await loadCategories();
                    break;
                case 'cityEvents':
                    await CityEventsFilter.init();
                    break;
                case 'static':
                    // Static sections just need the animation
                    break;
            }
        } catch (error) {
            console.warn(`Failed to load section: ${type}`, error);
        }

        // Add loaded class for CSS animation
        section.classList.add('loaded');

        // Stop observing this section
        this.observer.unobserve(section);
    }
};

// Homepage initialization
document.addEventListener('DOMContentLoaded', async function() {
    // Initialize lazy loading first
    LazyLoader.init();

    // Load above-the-fold content immediately (hero + promoted)
    await Promise.all([
        HeroSlider.init(),
        PromotedEvents.init(),
        FeaturedCarousel.init()
    ]);

    // Below-fold content is loaded by LazyLoader when visible
});

// Load categories
async function loadCategories() {
    try {
        const response = await AmbiletAPI.getCategories();
        if (response.data && response.data.categories) {
            renderCategories(response.data.categories);
        }
    } catch (error) {
        console.warn('Failed to load categories:', error);
    }
}

function renderCategories(categories) {
    const container = document.getElementById('categoriesGrid');
    const icons = {
        'concerte': '🎸',
        'festivaluri': '🎪',
        'teatru': '🎭',
        'stand-up': '😂',
        'copii': '👶',
        'sport': '⚽',
        'moto': '🏍️',
        'expozitii': '🖼️',
        'conferinte': '💼'
    };

    if (!categories || categories.length === 0) {
        container.innerHTML = '<p class="text-muted col-span-full">Nu sunt categorii disponibile.</p>';
        return;
    }

    container.innerHTML = categories.slice(0, 6).map(cat => `
        <a href="/${cat.slug}" class="flex flex-col items-center gap-3 p-5 bg-white border category-pill md:p-6 rounded-2xl border-border group">
            <div class="flex items-center justify-center w-12 h-12 transition-colors md:w-14 md:h-14 bg-primary/10 rounded-xl group-hover:bg-white/20">
                <span class="text-2xl md:text-3xl">${icons[cat.slug] || '🎫'}</span>
            </div>
            <span class="text-sm font-semibold transition-colors md:text-base text-secondary group-hover:text-white">${cat.name}</span>
        </a>
    `).join('');
}
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
