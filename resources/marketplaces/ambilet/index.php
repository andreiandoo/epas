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
                        <div class="coverflow-slide-inner skeleton" style="width: 450px; height: 360px;"></div>
                    </div>
                    <div class="coverflow-slide coverflow-center">
                        <div class="coverflow-slide-inner skeleton" style="width: 450px; height: 360px;"></div>
                    </div>
                    <div class="coverflow-slide coverflow-right">
                        <div class="coverflow-slide-inner skeleton" style="width: 450px; height: 360px;"></div>
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

<!-- Latest Events -->
<section class="py-10 md:py-14 bg-surface">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Ultimele evenimente adăugate</h2>
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

<!-- Cities -->
<section class="py-10 bg-white md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-bold md:text-2xl text-secondary">Evenimente dupa oraș</h2>
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
/* 3D Coverflow Carousel Styles */
.coverflow-container {
    perspective: 1200px;
    overflow: visible;
    padding: 20px 0;
    position: relative;
}
.coverflow-track {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    transition: none;
    min-height: 380px;
}
@media (max-width: 768px) {
    .coverflow-track {
        min-height: 320px;
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
    font-size: 1.25rem;
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

/* Slide positions - center is largest, sides are smaller */
.coverflow-slide.coverflow-far-left {
    transform: translateX(-200%) scale(0.5) rotateY(45deg);
    z-index: 1;
    opacity: 0.3;
    filter: brightness(0.5);
}
.coverflow-slide.coverflow-left {
    transform: translateX(-85%) scale(0.75) rotateY(25deg);
    z-index: 2;
    opacity: 0.7;
    filter: brightness(0.7);
}
.coverflow-slide.coverflow-center {
    transform: translateX(0) scale(1) rotateY(0deg);
    z-index: 3;
}
.coverflow-slide.coverflow-right {
    transform: translateX(85%) scale(0.75) rotateY(-25deg);
    z-index: 2;
    opacity: 0.7;
    filter: brightness(0.7);
}
.coverflow-slide.coverflow-far-right {
    transform: translateX(200%) scale(0.5) rotateY(-45deg);
    z-index: 1;
    opacity: 0.3;
    filter: brightness(0.5);
}
.coverflow-slide.coverflow-hidden {
    opacity: 0;
    pointer-events: none;
    z-index: 0;
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
    width: 100%;
    height: 100%;
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
</style>
<script>
// 3D Coverflow Hero Carousel Module
const HeroSlider = {
    events: [],
    currentIndex: 0,
    autoplayInterval: null,
    autoplayDelay: 5000, // 5 seconds
    slideWidth: 450, // Center slide width (desktop)
    slideHeight: 360, // Slide height (desktop)

    async init() {
        try {
            // Load homepage featured events
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=12');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.calculateSizes();
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

    calculateSizes() {
        if (window.innerWidth < 640) {
            this.slideWidth = 280;
            this.slideHeight = 280;
        } else if (window.innerWidth < 1024) {
            this.slideWidth = 360;
            this.slideHeight = 320;
        } else {
            this.slideWidth = 450;
            this.slideHeight = 360;
        }
    },

    render() {
        const slidesContainer = document.getElementById('heroSlides');
        if (!slidesContainer || this.events.length === 0) return;

        // Create coverflow slides
        slidesContainer.innerHTML = this.events.map((event, index) => {
            const image = event.homepage_featured_image || event.featured_image || event.image || '/assets/images/hero-default.jpg';
            const date = new Date(event.starts_at || event.start_date || event.event_date);
            const formattedDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
            const location = event.venue_name || event.venue?.name || event.city || 'Romania';
            const category = event.category?.name || event.type || 'Eveniment';
            const priceFrom = event.price_from ? `de la ${event.price_from} Lei` : '';

            return `
                <div class="coverflow-slide" data-index="${index}">
                    <a href="/bilete/${event.slug || ''}" class="coverflow-slide-inner" style="width: ${this.slideWidth}px; height: ${this.slideHeight}px;">
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
            } else if (relativePos === -2) {
                slide.classList.add('coverflow-far-left');
            } else if (relativePos === 2) {
                slide.classList.add('coverflow-far-right');
            } else {
                slide.classList.add('coverflow-hidden');
            }
        });

        // Update thumbnails
        const thumbnails = document.querySelectorAll('.thumbnail-item');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('active', index === this.currentIndex);
        });

        // Scroll active thumbnail into view
        const activeThumbnail = thumbnails[this.currentIndex];
        if (activeThumbnail) {
            activeThumbnail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
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

        // Recalculate on resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.calculateSizes();
                this.render();
                this.renderThumbnails();
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
