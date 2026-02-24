<?php
/**
 * Genre Page - Events filtered by music genre
 * Based on genre.html template
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/category-config.php';

$genreSlug = $_GET['slug'] ?? $_GET['genre'] ?? '';

// Get genre config from centralized file
$genreConfig = getGenre($genreSlug);

if ($genreConfig) {
    $pageTitle = $genreConfig['name'];
    $pageDescription = $genreConfig['description'];
    $genreIcon = $genreConfig['icon'];
    $genreHeroImage = $genreConfig['hero_image'];
    $genreColor = $genreConfig['color'];
    $parentCategory = $genreConfig['category'];
    $parentCategoryConfig = getCategory($parentCategory);
} else {
    $pageTitle = $genreSlug ? ucfirst(str_replace('-', ' ', $genreSlug)) : 'Gen muzical';
    $pageDescription = 'Descopera cele mai tari evenimente din acest gen.';
    $genreIcon = '🎵';
    $genreHeroImage = getHeroImage('rock', 'genre');
    $genreColor = '#A51C30';
    $parentCategory = 'concerte';
    $parentCategoryConfig = getCategory('concerte');
}

$currentPage = 'events';
$transparentHeader = true;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative overflow-hidden  h-[420px] md:h-[480px]">
    <img id="genreBanner" src="<?= htmlspecialchars($genreHeroImage) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-black/30"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-10 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/<?= htmlspecialchars($parentCategory) ?>" id="parentCategoryLink" class="transition-colors hover:text-white"><?= htmlspecialchars($parentCategoryConfig['name'] ?? 'Concerte') ?></a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white" id="genreBreadcrumb"><?= htmlspecialchars($pageTitle) ?></span>
        </nav>
        <div class="flex items-center gap-4 mb-4">
            <span id="genreIcon" class="flex items-center justify-center w-16 h-16 text-3xl shadow-lg rounded-2xl" style="background-color: <?= htmlspecialchars($genreColor) ?>"><?= $genreIcon ?></span>
            <div>
                <span class="text-sm font-medium tracking-wider uppercase text-white/60">Gen muzical</span>
                <h1 id="pageTitle" class="text-4xl font-extrabold text-white md:text-5xl"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </div>
        <p id="pageDescription" class="max-w-2xl mb-6 text-lg text-white/80">Descopera cele mai electrizante evenimente din acest gen.</p>
        <div class="flex flex-wrap items-center gap-3">
            <span id="eventsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- evenimente</span>
            <span id="artistsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- artisti</span>
            <span id="citiesCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- orase</span>
        </div>
    </div>
</section>

<!-- Featured Artists -->
<section class="py-10 bg-white border-b border-border" id="artistsSection">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-6 text-xl font-bold text-secondary">Artisti populari in acest gen</h2>
        <div class="flex gap-4 px-4 pb-4 -mx-4 overflow-x-auto" style="scrollbar-width: none;" id="artistsScroll">
            <!-- Artists will be loaded dynamically -->
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
        </div>
    </div>
</section>

<!-- Subgenres -->
<section class="py-8 bg-white border-b border-border" id="subgenresSection">
    <div class="px-4 mx-auto max-w-7xl">
        <h3 class="mb-4 text-sm font-semibold tracking-wider uppercase text-muted">Subgenuri</h3>
        <div class="flex flex-wrap gap-2" id="subgenresPills">
            <button class="px-4 py-2 text-sm font-medium text-white rounded-full bg-primary" data-subgenre="">Toate</button>
            <!-- Subgenres will be loaded dynamically -->
        </div>
    </div>
</section>

<!-- Mobile Filters Button -->
<section class="sticky top-[72px] z-20 py-3 bg-white border-b border-gray-200 shadow-sm lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4">
        <button onclick="openGenreFiltersDrawer()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filtre
            <span id="mobileFilterCount" class="hidden px-2 py-0.5 text-xs font-bold text-white rounded-full bg-primary">0</span>
        </button>
        <select id="sortEventsMobile" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('sortEvents').value = this.value; GenrePage.loadEvents();">
            <option value="date_asc">Data</option>
            <option value="popularity">Popular</option>
            <option value="price_asc">Preț ↑</option>
            <option value="price_desc">Preț ↓</option>
        </select>
    </div>
</section>

<!-- Desktop Filters Bar -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-gray-200 shadow-sm hidden lg:block">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center gap-3">
            <select id="filterCity" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="GenrePage.loadEvents()">
                <option value="">Toate orașele</option>
            </select>
            <select id="filterDate" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="GenrePage.loadEvents()">
                <option value="">Oricând</option>
                <option value="today">Astăzi</option>
                <option value="tomorrow">Mâine</option>
                <option value="weekend">Weekend</option>
                <option value="this_week">Săptămâna asta</option>
                <option value="this_month">Luna asta</option>
            </select>
            <select id="filterPrice" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="GenrePage.loadEvents()">
                <option value="">Orice preț</option>
                <option value="free">Gratuit</option>
                <option value="0-50">Sub 50 lei</option>
                <option value="50-100">50 - 100 lei</option>
                <option value="100-200">100 - 200 lei</option>
                <option value="200-500">200 - 500 lei</option>
                <option value="500+">Peste 500 lei</option>
            </select>
            <div class="flex items-center gap-2 ml-auto">
                <span class="text-sm text-gray-500">Sortare:</span>
                <select id="sortEvents" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="GenrePage.loadEvents()">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Preț (mic - mare)</option>
                    <option value="price_desc">Preț (mare - mic)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>
    </div>
</section>

<!-- Mobile Filters Drawer -->
<div id="genreFiltersBackdrop" class="fixed inset-0 z-[105] transition-opacity duration-300 bg-black/50 backdrop-blur-sm lg:hidden" style="opacity: 0; visibility: hidden;" onclick="closeGenreFiltersDrawer()"></div>
<div id="genreFiltersDrawer" class="fixed bottom-0 left-0 right-0 z-[110] overflow-hidden transition-transform duration-300 bg-white lg:hidden rounded-t-3xl max-h-[85vh]" style="transform: translateY(100%);">
    <div class="sticky top-0 z-10 flex items-center justify-between p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-bold text-gray-900">Filtre</h2>
        <button onclick="closeGenreFiltersDrawer()" class="flex items-center justify-center w-10 h-10 transition-colors rounded-full bg-gray-100 hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="p-4 space-y-4 overflow-y-auto max-h-[60vh]">
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Oraș</label>
            <select id="filterCityMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterCity').value = this.value;">
                <option value="">Toate orașele</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Dată</label>
            <select id="filterDateMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterDate').value = this.value;">
                <option value="">Oricând</option>
                <option value="today">Astăzi</option>
                <option value="tomorrow">Mâine</option>
                <option value="weekend">Weekend</option>
                <option value="this_week">Săptămâna asta</option>
                <option value="this_month">Luna asta</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Preț</label>
            <select id="filterPriceMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterPrice').value = this.value;">
                <option value="">Orice preț</option>
                <option value="free">Gratuit</option>
                <option value="0-50">Sub 50 lei</option>
                <option value="50-100">50 - 100 lei</option>
                <option value="100-200">100 - 200 lei</option>
                <option value="200-500">200 - 500 lei</option>
                <option value="500+">Peste 500 lei</option>
            </select>
        </div>
    </div>
    <div class="flex gap-3 p-4 border-t border-gray-200 bg-gray-50">
        <button onclick="clearGenreFilters(); closeGenreFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">
            Șterge filtre
        </button>
        <button onclick="GenrePage.loadEvents(); closeGenreFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-bold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
            Aplică filtre
        </button>
    </div>
</div>

<script>
function openGenreFiltersDrawer() {
    document.getElementById('genreFiltersBackdrop').style.opacity = '1';
    document.getElementById('genreFiltersBackdrop').style.visibility = 'visible';
    document.getElementById('genreFiltersDrawer').style.transform = 'translateY(0)';
    document.body.style.overflow = 'hidden';
    var fc = document.getElementById('filterCity'), fcm = document.getElementById('filterCityMobile');
    var fd = document.getElementById('filterDate'), fdm = document.getElementById('filterDateMobile');
    var fp = document.getElementById('filterPrice'), fpm = document.getElementById('filterPriceMobile');
    if (fc && fcm) fcm.value = fc.value;
    if (fd && fdm) fdm.value = fd.value;
    if (fp && fpm) fpm.value = fp.value;
}
function closeGenreFiltersDrawer() {
    document.getElementById('genreFiltersBackdrop').style.opacity = '0';
    document.getElementById('genreFiltersBackdrop').style.visibility = 'hidden';
    document.getElementById('genreFiltersDrawer').style.transform = 'translateY(100%)';
    document.body.style.overflow = '';
}
function clearGenreFilters() {
    ['filterCity', 'filterDate', 'filterPrice', 'filterCityMobile', 'filterDateMobile', 'filterPriceMobile'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('sortEvents').value = 'date_asc';
    var sm = document.getElementById('sortEventsMobile');
    if (sm) sm.value = 'date_asc';
    GenrePage.loadEvents();
}
</script>

<!-- Events Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Events Grid (grouped by month) -->
        <div id="eventsGrid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <!-- Events will be loaded dynamically -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div class="mt-12 text-center" id="loadMoreSection">
            <button id="loadMoreBtn" onclick="GenrePage.loadMore()" class="inline-flex items-center gap-2 px-8 py-4 font-bold transition-all border-2 border-primary text-primary rounded-xl hover:bg-primary hover:text-white">
                Incarca mai multe
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$genreSlugJS = json_encode($genreSlug);
$scriptsExtra = '<script src="' . asset('assets/js/pages/genre-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => { GenrePage.init(' . $genreSlugJS . '); FeaturedCarousel.init({ genre: ' . $genreSlugJS . ' }); });</script>';

require_once __DIR__ . '/includes/scripts.php';
