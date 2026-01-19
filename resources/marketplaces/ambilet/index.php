<?php
/**
 * Homepage - Ambilet.ro
 * Based on ticketing-homepage-v3.html template
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Bilete Evenimente Romania';
$pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Platforma de ticketing pentru evenimente din Romania.';
$currentPage = 'home';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Slider Section -->
<section class="relative overflow-hidden bg-secondary mt-28 mobile:mt-18" id="heroSlider">
    <div class="relative h-[480px] md:h-[560px]" id="heroSection">
        <!-- Slides container -->
        <div id="heroSlides" class="relative w-full h-full">
            <!-- Default slide (will be replaced by JS) -->
            <div class="hero-slide active">
                <img src="/assets/images/hero-default.jpg" alt="Festival" class="absolute inset-0 object-cover w-full h-full">
                <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-black/20"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
            </div>
        </div>

        <!-- Content overlay -->
        <div class="absolute inset-0 flex items-center">
            <div class="px-4 mx-auto max-w-7xl w-full">
                <div class="max-w-xl text-white fade-in">
                    <span class="inline-flex items-center gap-2 px-4 py-2 mb-5 text-sm font-bold rounded-full bg-primary">
                        <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                        <span id="heroLabel">Featured</span>
                    </span>
                    <h1 id="heroTitle" class="text-4xl md:text-5xl lg:text-6xl font-extrabold mb-4 leading-[1.1]">
                        Descopera evenimente
                    </h1>
                    <p id="heroMeta" class="flex flex-wrap items-center gap-3 mb-8 text-lg md:text-xl text-white/80">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span id="heroDate">Eveniment nou</span>
                        </span>
                        <span class="w-1.5 h-1.5 bg-white/40 rounded-full"></span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span id="heroLocation">Romania</span>
                        </span>
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="/evenimente" id="heroCta" class="inline-flex items-center gap-2 px-8 py-4 font-bold text-white btn-primary rounded-xl">
                            Cumpara Bilete
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                        <a href="/evenimente" id="heroDetails" class="px-8 py-4 font-bold text-white transition-colors border bg-white/15 backdrop-blur-sm rounded-xl hover:bg-white/25 border-white/20">
                            Vezi Detalii
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slider Navigation Arrows -->
        <button id="heroPrev" class="absolute hidden md:flex left-4 top-1/2 -translate-y-1/2 w-12 h-12 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm hover:bg-white/30 transition-colors text-white z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button id="heroNext" class="absolute hidden md:flex right-4 top-1/2 -translate-y-1/2 w-12 h-12 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm hover:bg-white/30 transition-colors text-white z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>

        <!-- Slider Dots -->
        <div id="heroDots" class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2 z-10"></div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<!-- Latest Events -->
<section class="py-10 md:py-14 bg-surface">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Ultimele evenimente adaugate</h2>
            <a href="/evenimente" class="items-center hidden gap-2 font-semibold md:flex text-primary">
                Vezi toate
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 md:gap-5" id="latestEventsGrid">
            <!-- Latest events will be loaded dynamically -->
            <?php for ($i = 0; $i < 20; $i++): ?>
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

<!-- Categories -->
<section class="py-10 md:py-14 bg-surface">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-8 text-xl font-bold md:text-2xl text-secondary">Exploreaza dupa categorie</h2>

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

<!-- Cities -->
<section class="py-10 bg-white md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Evenimente dupa oras</h2>
            <a href="/orase" class="items-center hidden gap-2 font-semibold md:flex text-primary">
                Toate orasele
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6 md:gap-4" id="citiesGrid">
            <!-- Cities will be loaded dynamically -->
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
            <div class="skeleton rounded-2xl" style="height: 176px;"></div>
        </div>
    </div>
</section>

<!-- Organizers CTA -->
<section class="py-12 bg-white md:py-16">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center gap-8 p-6 bg-surface rounded-3xl md:p-10 md:flex-row">
            <div class="flex-1">
                <span class="inline-block px-3 py-1.5 bg-accent/20 text-accent font-bold text-xs rounded-full mb-4 uppercase tracking-wide">Pentru Organizatori</span>
                <h2 class="mb-3 text-2xl font-bold md:text-3xl text-secondary">Vrei sa-ti vinzi biletele prin <?= SITE_NAME ?>?</h2>
                <p class="mb-6 text-muted">Comisioane transparente, plati rapide si suport dedicat pentru organizatori.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="/organizer/register" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary rounded-xl">
                        Inregistreaza-te gratuit
                    </a>
                    <a href="/organizer/landing" class="px-6 py-3 font-bold transition-colors border-2 border-secondary text-secondary rounded-xl hover:bg-secondary hover:text-white">
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
<style>
/* Hero Slider Styles */
.hero-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.8s ease-in-out;
}
.hero-slide.active {
    opacity: 1;
}
.hero-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
#heroDots button {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.4);
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}
#heroDots button.active {
    background: white;
    width: 24px;
    border-radius: 5px;
}
</style>
<script>
// Hero Slider Module
const HeroSlider = {
    events: [],
    currentIndex: 0,
    autoplayInterval: null,
    autoplayDelay: 5000, // 5 seconds

    async init() {
        try {
            // Load homepage featured events using type=homepage
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=10');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.render();
                this.bindEvents();
                this.startAutoplay();
            }
        } catch (error) {
            console.warn('Failed to load hero slider events:', error);
        }
    },

    render() {
        const slidesContainer = document.getElementById('heroSlides');
        const dotsContainer = document.getElementById('heroDots');

        if (!slidesContainer || this.events.length === 0) return;

        // Create slides
        slidesContainer.innerHTML = this.events.map((event, index) => {
            // Use homepage_featured_image if available, fallback to featured_image or image
            const image = event.homepage_featured_image || event.featured_image || event.image || '/assets/images/hero-default.jpg';
            return `
                <div class="hero-slide ${index === 0 ? 'active' : ''}" data-index="${index}">
                    <img src="${image}" alt="${this.escapeHtml(event.title || 'Event')}" loading="${index === 0 ? 'eager' : 'lazy'}">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-black/20"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                </div>
            `;
        }).join('');

        // Create dots
        dotsContainer.innerHTML = this.events.map((_, index) =>
            `<button data-index="${index}" class="${index === 0 ? 'active' : ''}" aria-label="Go to slide ${index + 1}"></button>`
        ).join('');

        // Update content for first slide
        this.updateContent(0);
    },

    updateContent(index) {
        const event = this.events[index];
        if (!event) return;

        const date = new Date(event.starts_at || event.start_date || event.event_date);
        const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });

        document.getElementById('heroLabel').textContent = 'Featured';
        document.getElementById('heroTitle').textContent = event.title || event.name || 'Eveniment';
        document.getElementById('heroDate').textContent = formattedDate;
        document.getElementById('heroLocation').textContent = event.venue_name || event.venue?.name || event.city?.name || 'Romania';
        document.getElementById('heroCta').href = '/bilete/' + (event.slug || '');
        document.getElementById('heroDetails').href = '/bilete/' + (event.slug || '');
    },

    goTo(index) {
        if (index < 0) index = this.events.length - 1;
        if (index >= this.events.length) index = 0;

        const slides = document.querySelectorAll('.hero-slide');
        const dots = document.querySelectorAll('#heroDots button');

        slides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });

        this.currentIndex = index;
        this.updateContent(index);
    },

    next() {
        this.goTo(this.currentIndex + 1);
    },

    prev() {
        this.goTo(this.currentIndex - 1);
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
        const dotsContainer = document.getElementById('heroDots');
        const heroSection = document.getElementById('heroSlider');

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

        if (dotsContainer) {
            dotsContainer.addEventListener('click', (e) => {
                if (e.target.tagName === 'BUTTON') {
                    const index = parseInt(e.target.dataset.index, 10);
                    this.goTo(index);
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
        if (heroSection) {
            heroSection.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });

            heroSection.addEventListener('touchend', (e) => {
                const touchEndX = e.changedTouches[0].clientX;
                const diff = touchStartX - touchEndX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) this.next();
                    else this.prev();
                    this.startAutoplay();
                }
            }, { passive: true });
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
};

// Homepage initialization
document.addEventListener('DOMContentLoaded', async function() {
    // Load homepage data
    await Promise.all([
        HeroSlider.init(),
        loadCategories(),
        loadCities(),
        loadLatestEvents(),
        FeaturedCarousel.init()
    ]);
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

// Load cities
async function loadCities() {
    try {
        const response = await AmbiletAPI.get('/locations/cities/featured?limit=6');
        if (response.data && response.data.cities) {
            renderCities(response.data.cities);
        }
    } catch (error) {
        console.warn('Failed to load cities:', error);
    }
}

function renderCities(cities) {
    const container = document.getElementById('citiesGrid');

    if (!cities || cities.length === 0) {
        container.innerHTML = '<p class="text-muted col-span-full">Nu sunt orase disponibile.</p>';
        return;
    }

    container.innerHTML = cities.map(city => `
        <a href="/${city.slug}" class="relative overflow-hidden city-card group h-36 md:h-44 rounded-2xl">
            <img src="${city.image || '/assets/images/default-city.png'}" alt="${city.name}" class="absolute inset-0 object-cover w-full h-full city-image" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
            <div class="absolute bottom-3 left-3 right-3">
                <h3 class="text-base font-bold text-white md:text-lg">${city.name}</h3>
                <p class="text-xs text-white/70 md:text-sm">${city.events_count || 0} evenimente</p>
            </div>
        </a>
    `).join('');
}

// Load latest events
async function loadLatestEvents() {
    try {
        const response = await AmbiletAPI.get('/marketplace-events?sort=latest&limit=20');
        if (response.data) {
            renderLatestEvents(response.data);
        }
    } catch (error) {
        console.warn('Failed to load latest events:', error);
    }
}

function renderLatestEvents(events) {
    const container = document.getElementById('latestEventsGrid');

    if (!events || events.length === 0) {
        container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
        return;
    }

    // Use AmbiletEventCard component for rendering
    container.innerHTML = AmbiletEventCard.renderMany(events, {
        urlPrefix: '/bilete/',
        showCategory: true,
        showPrice: true,
        showVenue: true
    });

    // Trigger reveal animation
    setTimeout(() => {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
    }, 100);
}
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
