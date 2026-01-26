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

<!-- Hero Slider Section - Card Carousel Style -->
<section class="relative bg-secondary mt-28 mobile:mt-18 py-8 md:py-12" id="heroSlider">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Section Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-white md:text-2xl">Evenimente Recomandate</h2>
                <p class="text-sm text-white/60 mt-1">Descopera cele mai populare evenimente</p>
            </div>
            <a href="/evenimente" class="hidden md:flex items-center gap-2 text-white/80 hover:text-white transition-colors font-medium">
                Vezi toate
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- Carousel Container -->
        <div class="relative" id="heroSection">
            <!-- Slider Navigation Arrows -->
            <button id="heroPrev" class="absolute z-20 items-center justify-center hidden md:flex w-12 h-12 text-white transition-all -translate-y-1/2 rounded-full left-0 -ml-4 top-1/2 bg-secondary/90 hover:bg-primary shadow-lg disabled:opacity-30 disabled:cursor-not-allowed">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button id="heroNext" class="absolute z-20 items-center justify-center hidden md:flex w-12 h-12 text-white transition-all -translate-y-1/2 rounded-full right-0 -mr-4 top-1/2 bg-secondary/90 hover:bg-primary shadow-lg disabled:opacity-30 disabled:cursor-not-allowed">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Cards Track -->
            <div class="overflow-hidden mx-2 md:mx-6">
                <div id="heroSlides" class="flex transition-transform duration-500 ease-out gap-4 md:gap-6">
                    <!-- Loading Skeleton Cards -->
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="hero-card flex-shrink-0 w-[280px] md:w-[320px] lg:w-[340px]">
                        <div class="relative overflow-hidden bg-white/10 rounded-2xl aspect-[3/4]">
                            <div class="skeleton absolute inset-0"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Slider Dots -->
            <div id="heroDots" class="flex justify-center gap-2 mt-6"></div>
        </div>
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
<style>
/* Hero Card Carousel Styles */
.hero-card {
    flex-shrink: 0;
    cursor: pointer;
    transition: transform 0.3s ease;
}
.hero-card:hover {
    transform: translateY(-4px);
}
.hero-card-inner {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    aspect-ratio: 3/4;
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.5) 0%, rgba(30, 41, 59, 0.8) 100%);
}
.hero-card-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.hero-card:hover .hero-card-image {
    transform: scale(1.05);
}
.hero-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.3) 50%, rgba(0,0,0,0.1) 100%);
}
.hero-card-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.25rem;
}
.hero-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
    background: var(--color-primary, #A51C30);
    color: white;
    margin-bottom: 0.75rem;
}
.hero-card-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: white;
    line-height: 1.3;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.hero-card-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.8);
}
.hero-card-date,
.hero-card-location {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.hero-card-price {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: white;
    color: var(--color-primary, #A51C30);
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 700;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
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
// Hero Card Carousel Module
const HeroSlider = {
    events: [],
    currentPage: 0,
    cardsPerPage: 4,
    totalPages: 0,
    autoplayInterval: null,
    autoplayDelay: 6000, // 6 seconds
    track: null,
    cardWidth: 340, // Default card width
    gap: 24, // Gap between cards

    async init() {
        try {
            // Load homepage featured events
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=12');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.calculateLayout();
                this.render();
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

    calculateLayout() {
        const container = document.querySelector('#heroSlides')?.parentElement;
        if (!container) return;

        const containerWidth = container.offsetWidth;

        // Responsive card widths
        if (window.innerWidth < 640) {
            this.cardWidth = 260;
            this.gap = 16;
        } else if (window.innerWidth < 1024) {
            this.cardWidth = 300;
            this.gap = 20;
        } else {
            this.cardWidth = 340;
            this.gap = 24;
        }

        // Calculate how many cards fit per view
        this.cardsPerPage = Math.max(1, Math.floor((containerWidth + this.gap) / (this.cardWidth + this.gap)));
        this.totalPages = Math.ceil(this.events.length / this.cardsPerPage);
    },

    render() {
        const slidesContainer = document.getElementById('heroSlides');
        const dotsContainer = document.getElementById('heroDots');

        if (!slidesContainer || this.events.length === 0) return;

        this.track = slidesContainer;

        // Create event cards
        slidesContainer.innerHTML = this.events.map((event, index) => {
            const image = event.homepage_featured_image || event.featured_image || event.image || '/assets/images/hero-default.jpg';
            const date = new Date(event.starts_at || event.start_date || event.event_date);
            const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short' });
            const location = event.venue_name || event.venue?.name || event.city || 'Romania';
            const category = event.category?.name || event.type || 'Eveniment';
            const priceFrom = event.price_from ? `de la ${event.price_from} Lei` : '';

            return `
                <a href="/bilete/${event.slug || ''}" class="hero-card" style="width: ${this.cardWidth}px;" data-index="${index}">
                    <div class="hero-card-inner">
                        <img src="${image}" alt="${this.escapeHtml(event.title || 'Event')}" class="hero-card-image" loading="${index < 4 ? 'eager' : 'lazy'}">
                        <div class="hero-card-overlay"></div>
                        ${priceFrom ? `<div class="hero-card-price">${priceFrom}</div>` : ''}
                        <div class="hero-card-content">
                            <span class="hero-card-badge">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                ${this.escapeHtml(category)}
                            </span>
                            <h3 class="hero-card-title">${this.escapeHtml(event.title || event.name || 'Eveniment')}</h3>
                            <div class="hero-card-meta">
                                <span class="hero-card-date">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${formattedDate}
                                </span>
                                <span class="hero-card-location">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    ${this.escapeHtml(location)}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            `;
        }).join('');

        // Create pagination dots (one per page, not per card)
        if (this.totalPages > 1) {
            dotsContainer.innerHTML = Array.from({ length: this.totalPages }, (_, i) =>
                `<button data-page="${i}" class="${i === 0 ? 'active' : ''}" aria-label="Go to page ${i + 1}"></button>`
            ).join('');
        } else {
            dotsContainer.innerHTML = '';
        }

        this.updateNavButtons();
    },

    goToPage(page) {
        if (page < 0) page = this.totalPages - 1;
        if (page >= this.totalPages) page = 0;

        this.currentPage = page;

        // Calculate the translation
        const offset = page * this.cardsPerPage * (this.cardWidth + this.gap);
        if (this.track) {
            this.track.style.transform = `translateX(-${offset}px)`;
        }

        // Update dots
        const dots = document.querySelectorAll('#heroDots button');
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === page);
        });

        this.updateNavButtons();
    },

    updateNavButtons() {
        const prevBtn = document.getElementById('heroPrev');
        const nextBtn = document.getElementById('heroNext');

        if (prevBtn) {
            prevBtn.disabled = this.currentPage === 0;
        }
        if (nextBtn) {
            nextBtn.disabled = this.currentPage >= this.totalPages - 1;
        }
    },

    next() {
        if (this.currentPage < this.totalPages - 1) {
            this.goToPage(this.currentPage + 1);
        } else {
            this.goToPage(0); // Loop back to start
        }
    },

    prev() {
        if (this.currentPage > 0) {
            this.goToPage(this.currentPage - 1);
        }
    },

    startAutoplay() {
        this.stopAutoplay();
        if (this.totalPages > 1) {
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
                    const page = parseInt(e.target.dataset.page, 10);
                    this.goToPage(page);
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
        const track = document.getElementById('heroSlides');
        if (track) {
            track.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                this.stopAutoplay();
            }, { passive: true });

            track.addEventListener('touchend', (e) => {
                const touchEndX = e.changedTouches[0].clientX;
                const diff = touchStartX - touchEndX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) this.next();
                    else this.prev();
                }
                this.startAutoplay();
            }, { passive: true });
        }

        // Recalculate on resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.calculateLayout();
                this.render();
                this.goToPage(0);
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
        container.innerHTML = '<p class="text-muted col-span-full">Nu sunt orașe disponibile.</p>';
        return;
    }

    container.innerHTML = cities.map(city => `
        <a href="/${city.slug}" class="relative overflow-hidden city-card group h-36 md:h-44 mobile:w-auto mobile:h-auto rounded-2xl">
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
