<?php
/**
 * Category / Events Listing Page
 * Based on category.html template
 *
 * Loads category data from:
 * 1. API cache (nav-cache.php) for dynamic categories from DB
 * 2. Fallback to static category-config.php for defaults
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';
require_once __DIR__ . '/includes/category-config.php';

$categorySlug = $_GET['type'] ?? $_GET['slug'] ?? '';

// First try to get category from API cache (dynamic DB data)
$apiCategories = getEventCategories();
$apiCategory = null;
foreach ($apiCategories as $cat) {
    if ($cat['slug'] === $categorySlug) {
        $apiCategory = $cat;
        break;
    }
}

if ($apiCategory) {
    // Use API data (from DB)
    $pageTitle = $apiCategory['name'];
    $pageDescription = $apiCategory['description'] ?? 'Descopera evenimentele din aceasta categorie.';
    $categoryIcon = $apiCategory['icon_emoji'] ?? '🎫';
    $categoryHeroImage = $apiCategory['image'] ?? getHeroImage($categorySlug, 'category');
    $categoryColor = $apiCategory['color'] ?? '#A51C30';
} else {
    // Fallback to static config file
    $categoryConfig = getCategory($categorySlug);

    if ($categoryConfig) {
        $pageTitle = $categoryConfig['name'];
        $pageDescription = $categoryConfig['description'];
        $categoryIcon = $categoryConfig['icon'];
        $categoryHeroImage = $categoryConfig['hero_image'];
        $categoryColor = $categoryConfig['color'];
    } else {
        $pageTitle = $categorySlug ? ucfirst(str_replace('-', ' ', $categorySlug)) : 'Toate evenimentele';
        $pageDescription = 'Descopera evenimentele din aceasta categorie.';
        $categoryIcon = '🎫';
        $categoryHeroImage = getHeroImage('concerte', 'category');
        $categoryColor = '#A51C30';
    }
}

$currentPage = 'events';
$transparentHeader = false;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative h-[420px] md:h-[480px] overflow-hidden">
    <img id="categoryBanner" src="<?= htmlspecialchars($categoryHeroImage) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-black/40"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-12 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white" id="breadcrumbTitle"><?= htmlspecialchars($pageTitle) ?></span>
        </nav>
        <h1 id="pageTitle" class="mb-3 text-4xl font-extrabold text-white md:text-5xl"><?= $categoryIcon ?> <?= htmlspecialchars($pageTitle) ?></h1>
        <p id="pageDescription" class="max-w-xl text-lg text-white/80"><?= htmlspecialchars($pageDescription) ?></p>
        <div class="flex items-center gap-4 mt-6">
            <span id="eventsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- evenimente</span>
            <span id="citiesCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- orase</span>
        </div>
        <!-- Genre Pills -->
        <div id="genresSection" class="mt-6">
            <div id="genresPills" class="flex items-center gap-3 pb-2 overflow-x-auto scrollbar-hide snap-x snap-mandatory">
                <button class="genre-pill active flex-shrink-0 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer snap-start bg-white text-gray-900" data-genre="">Toate</button>
                <!-- Genres will be loaded dynamically -->
                <div class="flex-shrink-0 w-4 lg:hidden"></div>
            </div>
        </div>
    </div>
</section>

<!-- Category Featured Events Section (is_category_featured) -->
<section id="categoryFeaturedSection" class="relative hidden overflow-hidden">
    <!-- Premium gradient background -->
    <div class="absolute inset-0 bg-gradient-to-b from-primary to-primary/80"></div>
    <!-- Decorative elements -->
    <div class="absolute top-0 left-0 w-64 h-64 rounded-full bg-white/5 -translate-x-1/2 -translate-y-1/2 blur-3xl"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 rounded-full bg-secondary/10 translate-x-1/3 translate-y-1/3 blur-3xl"></div>
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0zNiAxOGMzLjMxNCAwIDYgMi42ODYgNiA2cy0yLjY4NiA2LTYgNi02LTIuNjg2LTYtNiAyLjY4Ni02IDYtNiIgc3Ryb2tlPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDUpIiBzdHJva2Utd2lkdGg9IjIiLz48L2c+PC9zdmc+')] opacity-30"></div>

    <div class="relative px-4 py-10 mx-auto md:py-14 max-w-7xl">
        <!-- Section Header -->
        <div class="mb-8 text-center">
            <h2 class="mb-2 text-3xl font-extrabold text-white md:text-4xl">Nu Rata Aceste Evenimente</h2>
        </div>
        <!-- Events Grid -->
        <div id="categoryFeaturedEvents" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Category featured events loaded dynamically -->
        </div>
    </div>
</section>

<!-- Mobile Filters Button -->
<section class="sticky top-[72px] z-20 py-3 bg-white border-b border-gray-200 shadow-sm lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4">
        <button onclick="openCatFiltersDrawer()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filtre
            <span id="mobileFilterCount" class="hidden px-2 py-0.5 text-xs font-bold text-white rounded-full bg-primary">0</span>
        </button>
        <select id="sortEventsMobile" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('sortEvents').value = this.value; CategoryPage.loadEvents();">
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
            <select id="filterCity" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="">Toate orasele</option>
            </select>
            <select id="filterDate" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="">Oricand</option>
                <option value="today">Astazi</option>
                <option value="tomorrow">Maine</option>
                <option value="weekend">Weekend</option>
                <option value="this_week">Saptamana aceasta</option>
                <option value="this_month">Luna aceasta</option>
                <option value="next_month">Luna viitoare</option>
            </select>
            <select id="filterPrice" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="">Orice pret</option>
                <option value="0-50">Sub 50 lei</option>
                <option value="50-100">50 - 100 lei</option>
                <option value="100-200">100 - 200 lei</option>
                <option value="200-500">200 - 500 lei</option>
                <option value="500-">Peste 500 lei</option>
            </select>
            <div class="flex items-center gap-2 ml-auto">
                <span class="text-sm text-gray-500">Sortare:</span>
                <select id="sortEvents" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Pret (mic)</option>
                    <option value="price_desc">Pret (mare)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>
    </div>
</section>

<!-- Mobile Filters Drawer -->
<div id="catFiltersBackdrop" class="fixed inset-0 z-[105] transition-opacity duration-300 bg-black/50 backdrop-blur-sm lg:hidden" style="opacity: 0; visibility: hidden;" onclick="closeCatFiltersDrawer()"></div>
<div id="catFiltersDrawer" class="fixed bottom-0 left-0 right-0 z-[110] overflow-hidden transition-transform duration-300 bg-white lg:hidden rounded-t-3xl max-h-[85vh]" style="transform: translateY(100%);">
    <div class="sticky top-0 z-10 flex items-center justify-between p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-bold text-gray-900">Filtre</h2>
        <button onclick="closeCatFiltersDrawer()" class="flex items-center justify-center w-10 h-10 transition-colors rounded-full bg-gray-100 hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="p-4 space-y-4 overflow-y-auto max-h-[60vh]">
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Oraș</label>
            <select id="filterCityMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterCity').value = this.value;">
                <option value="">Toate orasele</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Dată</label>
            <select id="filterDateMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterDate').value = this.value;">
                <option value="">Oricand</option>
                <option value="today">Astazi</option>
                <option value="tomorrow">Maine</option>
                <option value="weekend">Weekend</option>
                <option value="this_week">Saptamana aceasta</option>
                <option value="this_month">Luna aceasta</option>
                <option value="next_month">Luna viitoare</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Preț</label>
            <select id="filterPriceMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('filterPrice').value = this.value;">
                <option value="">Orice pret</option>
                <option value="0-50">Sub 50 lei</option>
                <option value="50-100">50 - 100 lei</option>
                <option value="100-200">100 - 200 lei</option>
                <option value="200-500">200 - 500 lei</option>
                <option value="500-">Peste 500 lei</option>
            </select>
        </div>
    </div>
    <div class="flex gap-3 p-4 border-t border-gray-200 bg-gray-50">
        <button onclick="clearCatFilters(); closeCatFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">
            Șterge filtre
        </button>
        <button onclick="CategoryPage.loadEvents(); closeCatFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-bold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
            Aplică filtre
        </button>
    </div>
</div>

<script>
function openCatFiltersDrawer() {
    document.getElementById('catFiltersBackdrop').style.opacity = '1';
    document.getElementById('catFiltersBackdrop').style.visibility = 'visible';
    document.getElementById('catFiltersDrawer').style.transform = 'translateY(0)';
    document.body.style.overflow = 'hidden';
    document.getElementById('filterCityMobile').value = document.getElementById('filterCity').value;
    document.getElementById('filterDateMobile').value = document.getElementById('filterDate').value;
    document.getElementById('filterPriceMobile').value = document.getElementById('filterPrice').value;
}
function closeCatFiltersDrawer() {
    document.getElementById('catFiltersBackdrop').style.opacity = '0';
    document.getElementById('catFiltersBackdrop').style.visibility = 'hidden';
    document.getElementById('catFiltersDrawer').style.transform = 'translateY(100%)';
    document.body.style.overflow = '';
}
function clearCatFilters() {
    document.getElementById('filterCity').value = '';
    document.getElementById('filterDate').value = '';
    document.getElementById('filterPrice').value = '';
    document.getElementById('filterCityMobile').value = '';
    document.getElementById('filterDateMobile').value = '';
    document.getElementById('filterPriceMobile').value = '';
    CategoryPage.loadEvents();
}
</script>

<!-- Events Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Events Grid -->
        <div id="eventsGrid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-12"></div>
    </div>
</section>

<!-- Featured Events Section (is_general_featured) -->
<section id="featuredSection" class="hidden py-8 md:py-12 bg-gradient-to-b from-gray-50 to-white">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10">
                    <svg class="w-5 h-5 text-primary" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </span>
                <h2 class="text-2xl font-bold text-secondary">Evenimente Recomandate</h2>
            </div>
        </div>
        <div id="featuredEvents" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Featured events loaded dynamically -->
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$categorySlugJS = json_encode($categorySlug);
$scriptsExtra = '<script src="' . asset('assets/js/pages/category-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => CategoryPage.init(' . $categorySlugJS . '));</script>';

require_once __DIR__ . '/includes/scripts.php';
