<?php
/**
 * Homepage - Ambilet.ro
 * Based on ticketing-homepage-v3.html template
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Bilete Evenimente Romania';
$pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Platforma de ticketing pentru evenimente din Romania.';
$currentPage = 'home';
$transparentHeader = false;
$headExtra = '<link rel="stylesheet" href="' . asset('assets/css/homepage.css') . '">';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Carousel - 3D Poster Stack -->
<section class="relative pt-0 pb-4 overflow-hidden bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 mt-18 mobile:pt-10" id="heroSlider">
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
<section class="py-10 bg-primary md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-4 text-lg font-bold text-center text-white md:text-2xl">Nu rata aceste evenimente</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-6 md:gap-5" id="promotedEventsGrid">
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
        <h2 class="mb-4 text-lg font-bold text-center text-white md:text-2xl">Explorează după categorie</h2>

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
            <span class="block w-full mb-4 text-lg font-bold text-center text-second-text md:text-2xl">ultimele evenimente adăugate</span>
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
                <p class="text-muted">Comisioane transparente, plati rapide si suport dedicat pentru organizatori.</p>
            </div>
            <div class="flex-shrink-0 hidden md:block">
                <div class="flex flex-wrap gap-3">
                    <a href="/organizator/register" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary rounded-xl">
                        Inregistreaza-te gratuit
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/homepage.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
