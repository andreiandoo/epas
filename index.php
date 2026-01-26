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

<!-- Hero Slider Section - 3D Coverflow Style -->
<section class="relative py-8 overflow-hidden bg-secondary mt-28 mobile:mt-18 md:py-12" id="heroSlider">
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

<?php require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<!-- Promoted & Recommended Events -->
<section class="py-10 md:py-14 bg-white">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Evenimente recomandate</h2>
            <a href="/evenimente" class="items-center hidden gap-2 font-semibold md:flex text-primary">
                Vezi toate
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 md:gap-5" id="promotedEventsGrid">
            <!-- Promoted events will be loaded dynamically -->
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
    </div>
</section>

<!-- Categories -->
<section class="py-10 md:py-14 bg-surface">
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
<section class="py-10 bg-white md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Evenimente după oraș</h2>
            <a href="/orase" class="items-center hidden gap-2 font-semibold md:flex text-primary">
                Toate orasele
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- City Filter Buttons -->
        <div class="flex flex-wrap gap-2 mb-8" id="cityFilterButtons">
            <button class="city-filter-btn active px-4 py-2 text-sm font-semibold rounded-full transition-all bg-primary text-white" data-city="">
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
/* 3D Coverflow Carousel Styles - Fixed dimensions */
.coverflow-container {
    perspective: 1200px;
    overflow: hidden;
    padding: 20px 0;
    position: relative;
    width: 100%;
}
.coverflow-track {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    transition: none;
    height: 320px;
}
@media (min-width: 768px) {
    .coverflow-track {
        height: 360px;
    }
}
@media (min-width: 1024px) {
    .coverflow-track {
        height: 400px;
    }
}
.coverflow-slide {
    position: absolute;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    transform-style: preserve-3d;
    backface-visibility: hidden;
}
.coverflow-slide-inner {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.5) 0%, rgba(30, 41, 59, 0.8) 100%);
    /* Fixed dimensions - normalized across all slides */
    width: 280px;
    height: 280px;
}
@media (min-width: 768px) {
    .coverflow-slide-inner {
        width: 400px;
        height: 320px;
    }
}
@media (min-width: 1024px) {
    .coverflow-slide-inner {
        width: 480px;
        height: 360px;
    }
}
.coverflow-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.coverflow-slide-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0) 100%);
}
.coverflow-slide-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
}
.coverflow-slide.coverflow-center .coverflow-slide-content {
    opacity: 1;
    transform: translateY(0);
}
.coverflow-slide-badge {
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
.coverflow-slide-title {
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
@media (min-width: 768px) {
    .coverflow-slide-title {
        font-size: 1.5rem;
    }
}
.coverflow-slide-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.9);
}
.coverflow-slide-date,
.coverflow-slide-location {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.coverflow-slide-price {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: white;
    color: var(--color-primary, #A51C30);
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    opacity: 0;
    transform: scale(0.9);
    transition: all 0.3s ease;
}
.coverflow-slide.coverflow-center .coverflow-slide-price {
    opacity: 1;
    transform: scale(1);
}

/* Slide positions - always show 3: center full, left/right at 50% scale below */
.coverflow-slide.coverflow-far-left,
.coverflow-slide.coverflow-far-right,
.coverflow-slide.coverflow-hidden {
    opacity: 0;
    pointer-events: none;
    z-index: 0;
}
.coverflow-slide.coverflow-left {
    transform: translateX(-70%) translateY(15%) scale(0.65);
    z-index: 2;
    opacity: 0.8;
    filter: brightness(0.75);
}
.coverflow-slide.coverflow-center {
    transform: translateX(0) translateY(0) scale(1);
    z-index: 3;
}
.coverflow-slide.coverflow-right {
    transform: translateX(70%) translateY(15%) scale(0.65);
    z-index: 2;
    opacity: 0.8;
    filter: brightness(0.75);
}

/* Thumbnail Navigation */
.thumbnail-item {
    flex-shrink: 0;
    cursor: pointer;
    border-radius: 0.5rem;
    overflow: hidden;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    opacity: 0.6;
}
.thumbnail-item:hover {
    opacity: 0.9;
    transform: scale(1.05);
}
.thumbnail-item.active {
    border-color: var(--color-primary, #A51C30);
    opacity: 1;
    box-shadow: 0 0 0 2px rgba(165, 28, 48, 0.3);
}
.thumbnail-item img {
    object-fit: cover;
    display: block;
}
.thumbnails-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.3) transparent;
}
.thumbnails-scroll::-webkit-scrollbar {
    height: 4px;
}
.thumbnails-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.thumbnails-scroll::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 2px;
}

/* City Filter Buttons */
.city-filter-btn {
    border: 2px solid transparent;
    background: #f1f5f9;
    color: #64748b;
}
.city-filter-btn:hover {
    background: #e2e8f0;
    color: #334155;
}
.city-filter-btn.active {
    background: var(--color-primary, #A51C30);
    color: white;
    border-color: var(--color-primary, #A51C30);
}

/* Promoted Badge */
.promoted-badge {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    z-index: 10;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    border-radius: 0.375rem;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}
</style>
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
            const image = event.homepage_featured_image || event.featured_image || event.image || '/assets/images/hero-default.jpg';
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
            const image = event.homepage_featured_image || event.featured_image || event.image || '/assets/images/hero-default.jpg';
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
        await Promise.all([
            this.loadCities(),
            this.loadEvents()
        ]);
    },

    async loadCities() {
        try {
            const response = await AmbiletAPI.get('/locations/cities/featured?limit=10');
            if (response.data && response.data.cities) {
                this.cities = response.data.cities;
                this.renderCityButtons();
            }
        } catch (error) {
            console.warn('Failed to load cities:', error);
        }
    },

    async loadEvents() {
        try {
            const response = await AmbiletAPI.get('/marketplace-events?sort=latest&limit=20');
            if (response.data) {
                this.allEvents = Array.isArray(response.data) ? response.data : (response.data.events || response.data.data || []);
                this.renderEvents();
            }
        } catch (error) {
            console.warn('Failed to load events:', error);
        }
    },

    renderCityButtons() {
        const container = document.getElementById('cityFilterButtons');
        if (!container || this.cities.length === 0) return;

        // Keep "Toate" button, add city buttons
        const cityButtons = this.cities.map(city => `
            <button class="city-filter-btn px-4 py-2 text-sm font-semibold rounded-full transition-all" data-city="${city.slug || city.name}">
                ${city.name}
            </button>
        `).join('');

        container.innerHTML = `
            <button class="city-filter-btn active px-4 py-2 text-sm font-semibold rounded-full transition-all bg-primary text-white" data-city="">
                Toate
            </button>
            ${cityButtons}
        `;

        // Bind click events
        container.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => this.filterByCity(btn.dataset.city));
        });
    },

    filterByCity(citySlug) {
        this.selectedCity = citySlug;

        // Update active button
        document.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.city === citySlug);
        });

        this.renderEvents();
    },

    renderEvents() {
        const container = document.getElementById('cityEventsGrid');
        if (!container) return;

        // Filter events by city if selected
        let events = this.allEvents;
        if (this.selectedCity) {
            events = this.allEvents.filter(event => {
                const eventCity = (event.city || event.venue?.city || '').toLowerCase();
                const eventCitySlug = (event.city_slug || '').toLowerCase();
                return eventCity.includes(this.selectedCity.toLowerCase()) ||
                       eventCitySlug === this.selectedCity.toLowerCase();
            });
        }

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="text-muted col-span-full py-8 text-center">Nu sunt evenimente disponibile pentru acest oraș.</p>';
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
            // Fallback rendering
            container.innerHTML = events.slice(0, 10).map(event => this.renderEventCard(event)).join('');
        }

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    },

    renderEventCard(event) {
        const image = event.featured_image || event.image || '/assets/images/event-default.jpg';
        const date = new Date(event.starts_at || event.start_date || event.event_date);
        const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short' });
        const location = event.venue_name || event.venue?.name || event.city || '';
        const priceFrom = event.price_from ? `de la ${event.price_from} Lei` : '';

        return `
            <a href="/bilete/${event.slug || ''}" class="overflow-hidden bg-white border rounded-2xl border-border hover:shadow-lg transition-shadow">
                <div class="relative h-44">
                    <img src="${image}" alt="${event.title || 'Event'}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold text-primary mb-1">${formattedDate}</p>
                    <h3 class="font-bold text-secondary line-clamp-2">${event.title || event.name || 'Eveniment'}</h3>
                    ${location ? `<p class="text-sm text-muted mt-1">${location}</p>` : ''}
                    ${priceFrom ? `<p class="text-sm font-semibold text-primary mt-2">${priceFrom}</p>` : ''}
                </div>
            </a>
        `;
    }
};

// Promoted Events Module
const PromotedEvents = {
    async init() {
        try {
            // Load promoted and recommended events
            const [promotedRes, recommendedRes] = await Promise.all([
                AmbiletAPI.get('/marketplace-events?promoted=1&limit=5').catch(() => ({ data: [] })),
                AmbiletAPI.get('/marketplace-events?recommended=1&limit=10').catch(() => ({ data: [] }))
            ]);

            const promoted = this.extractEvents(promotedRes);
            const recommended = this.extractEvents(recommendedRes);

            // Mark promoted events
            promoted.forEach(e => e._isPromoted = true);

            // Combine and dedupe
            const combined = [...promoted];
            recommended.forEach(event => {
                if (!combined.find(e => e.id === event.id)) {
                    combined.push(event);
                }
            });

            this.render(combined.slice(0, 10));
        } catch (error) {
            console.warn('Failed to load promoted events:', error);
        }
    },

    extractEvents(response) {
        if (!response || !response.data) return [];
        if (Array.isArray(response.data)) return response.data;
        return response.data.events || response.data.data || [];
    },

    render(events) {
        const container = document.getElementById('promotedEventsGrid');
        if (!container) return;

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
            return;
        }

        container.innerHTML = events.map(event => this.renderCard(event)).join('');

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    },

    renderCard(event) {
        const image = event.featured_image || event.image || '/assets/images/event-default.jpg';
        const date = new Date(event.starts_at || event.start_date || event.event_date);
        const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short' });
        const location = event.venue_name || event.venue?.name || event.city || '';
        const priceFrom = event.price_from ? `de la ${event.price_from} Lei` : '';
        const promotedBadge = event._isPromoted ? `
            <span class="promoted-badge">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Promovat
            </span>
        ` : '';

        return `
            <a href="/bilete/${event.slug || ''}" class="relative overflow-hidden bg-white border rounded-2xl border-border hover:shadow-lg transition-shadow group">
                <div class="relative h-44">
                    ${promotedBadge}
                    <img src="${image}" alt="${this.escapeHtml(event.title || 'Event')}" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300" loading="lazy">
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold text-primary mb-1">${formattedDate}</p>
                    <h3 class="font-bold text-secondary line-clamp-2">${this.escapeHtml(event.title || event.name || 'Eveniment')}</h3>
                    ${location ? `<p class="text-sm text-muted mt-1">${this.escapeHtml(location)}</p>` : ''}
                    ${priceFrom ? `<p class="text-sm font-semibold text-primary mt-2">${priceFrom}</p>` : ''}
                </div>
            </a>
        `;
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
        CityEventsFilter.init(),
        PromotedEvents.init(),
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
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
