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

<!-- Hero Carousel - 3D Poster Stack -->
<section class="relative py-8 pt-40 overflow-hidden bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 mt-18 mobile:pt-10" id="heroSlider">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="hero-carousel-wrapper">
            <!-- 3D Carousel Container -->
            <div id="heroCarousel" class="hero-carousel">
                <!-- Loading Skeleton -->
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(20deg) translateX(240px); opacity: 0.4;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(10deg) translateX(120px); opacity: 0.6;"></div>
                <div class="hero-skeleton-item skeleton" style="z-index: 3;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(-10deg) translateX(-120px); opacity: 0.6;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(-20deg) translateX(-240px); opacity: 0.4;"></div>
            </div>
            <!-- Dot Indicators -->
            <div id="heroDots" class="hero-dots">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>"></button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<?php // require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<!-- Promoted & Recommended Events -->
<section class="py-10 bg-gray-900 md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-8 md:gap-5" id="promotedEventsGrid">
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
<section class="py-8 bg-primary lazy-section" id="categoriesSection" data-lazy-load="categories">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-4 text-lg font-bold text-center text-white md:text-2xl">Explorează dupa categorie</h2>

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
<section class="lazy-section" id="cityEventsSection" data-lazy-load="cityEvents">
    <div class="pb-2 bg-primary ">
        <div class="px-4 mx-auto max-w-7xl">
            <span class="block w-full mb-4 text-lg font-bold text-center text-white md:text-2xl">sau dupa oraș</span>
            <!-- City Filter Buttons -->
            <div class="flex flex-wrap justify-center gap-2 mb-8" id="cityFilterButtons">
                <button class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-full city-filter-btn active bg-primary" data-city="">
                    Toate
                </button>
                <!-- City buttons will be loaded dynamically -->
            </div>
        </div>
    </div>

    <div class="py-8 bg-white ">
        <div class="px-4 mx-auto max-w-7xl">
            <!-- Events Grid (filtered by city) -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-5 md:gap-5" id="cityEventsGrid">
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
// 3D Poster Stack Carousel Module
const HeroSlider = {
    events: [],
    currentIndex: 0,
    autoplayInterval: null,
    autoplayDelay: 2000, // 2 seconds

    async init() {
        try {
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=12');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.render();
                this.renderDots();
                this.updatePositions();
                this.bindEvents();
                this.startAutoplay();
            } else {
                const section = document.getElementById('heroSlider');
                if (section) section.style.display = 'none';
            }
        } catch (error) {
            console.warn('Failed to load hero events:', error);
            const section = document.getElementById('heroSlider');
            if (section) section.style.display = 'none';
        }
    },

    render() {
        const container = document.getElementById('heroCarousel');
        if (!container || this.events.length === 0) return;

        container.innerHTML = this.events.map((event, index) => {
            const image = getStorageUrl(event.homepage_featured_image || event.featured_image || event.hero_image_url || event.image);
            const title = this.escapeHtml(event.title || event.name || 'Eveniment');
            const city = event.venue_city || event.city || '';
            const venue = event.venue_name || (event.venue ? event.venue.name : '') || '';
            const locationText = city ? (venue ? city + ', ' + venue : city) : venue;
            const priceFrom = event.price_from ? 'De la ' + event.price_from + ' Lei' : '';
            const isPromoted = event.has_paid_promotion === true;

            return '<div class="hero-item" data-index="' + index + '" style="--r: ' + (index - this.currentIndex) + ';">' +
                '<a href="/bilete/' + (event.slug || '') + '" class="hero-item-inner">' +
                    '<img src="' + image + '" alt="' + title + '" loading="' + (index < 5 ? 'eager' : 'lazy') + '">' +
                    '<div class="hero-item-overlay"></div>' +
                    (isPromoted ? '<div class="hero-item-promoted"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>Promovat</div>' : '') +
                    '<div class="hero-item-content">' +
                        '<h3 class="hero-item-title">' + title + '</h3>' +
                        '<div class="hero-item-meta">' +
                            (locationText ? '<span class="hero-item-location"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(locationText) + '</span>' : '') +
                            (priceFrom ? '<span class="hero-item-price">' + priceFrom + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                '</a>' +
            '</div>';
        }).join('');
    },

    renderDots() {
        const dotsContainer = document.getElementById('heroDots');
        if (!dotsContainer) return;

        dotsContainer.innerHTML = this.events.map((_, index) =>
            '<button class="hero-dot' + (index === 0 ? ' active' : '') + '" data-index="' + index + '"></button>'
        ).join('');
    },

    updatePositions() {
        const items = document.querySelectorAll('.hero-item');
        const total = this.events.length;

        items.forEach((item, index) => {
            let r = index - this.currentIndex;
            // Circular wrapping
            if (r > total / 2) r -= total;
            if (r < -total / 2) r += total;

            const abs = Math.abs(r);
            item.style.setProperty('--r', r);
            item.style.setProperty('--abs', abs);
            item.style.zIndex = 10 - abs;
            item.style.opacity = abs > 2 ? 0 : 1;
            item.style.filter = 'brightness(' + (1 - 0.15 * abs) + ')';
            item.style.pointerEvents = abs > 2 ? 'none' : 'auto';
        });

        // Update dots
        const dots = document.querySelectorAll('.hero-dot');
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === this.currentIndex);
        });
    },

    goToSlide(index) {
        const total = this.events.length;
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;
        this.currentIndex = index;
        this.updatePositions();
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
        const heroSection = document.getElementById('heroSlider');
        const carousel = document.getElementById('heroCarousel');
        const dotsContainer = document.getElementById('heroDots');

        // Dot clicks
        if (dotsContainer) {
            dotsContainer.addEventListener('click', (e) => {
                const dot = e.target.closest('.hero-dot');
                if (dot) {
                    this.goToSlide(parseInt(dot.dataset.index, 10));
                    this.startAutoplay();
                }
            });
        }

        // Click side slides to navigate
        if (carousel) {
            carousel.addEventListener('click', (e) => {
                const item = e.target.closest('.hero-item');
                if (!item) return;
                const r = parseFloat(item.style.getPropertyValue('--r'));
                if (r !== 0) {
                    e.preventDefault();
                    this.goToSlide(parseInt(item.dataset.index, 10));
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
        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                this.stopAutoplay();
            }, { passive: true });

            carousel.addEventListener('touchend', (e) => {
                const diff = touchStartX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) this.next();
                    else this.prev();
                }
                this.startAutoplay();
            }, { passive: true });
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { this.prev(); this.startAutoplay(); }
            else if (e.key === 'ArrowRight') { this.next(); this.startAutoplay(); }
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
        <a href="/${cat.slug}" class="flex items-center gap-3 px-3 py-2 bg-white border rounded-lg category-pill border-border group">
            <div class="flex items-center justify-center w-8 h-8 transition-colors rounded-lg bg-primary/10 group-hover:bg-white/20">
                <span class="text-lg">${icons[cat.slug] || '🎫'}</span>
            </div>
            <span class="text-sm font-semibold transition-colors md:text-base text-secondary group-hover:text-white">${cat.name}</span>
        </a>
    `).join('');
}
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
