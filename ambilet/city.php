<?php
/**
 * City Page - Events filtered by city
 *
 * Dynamically loads city data from API
 */
require_once __DIR__ . '/includes/config.php';

$citySlug = $_GET['slug'] ?? '';

if (!$citySlug) {
    header('Location: /orase');
    exit;
}

// Validate city exists via API
$cityConfig = null;
$cityNotFound = false;
try {
    $apiUrl = API_BASE_URL . '/locations/cities/' . urlencode($citySlug);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
            ]) . "\r\n",
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents($apiUrl, false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] && isset($data['data']['city'])) {
            $city = $data['data']['city'];
            $cityConfig = [
                'name' => $city['name'] ?? ucwords(str_replace('-', ' ', $citySlug)),
                'description' => $city['description'] ?? 'Descoperă cele mai bune evenimente din acest oraș.',
                'hero_image' => $city['cover_image'] ?? $city['image'] ?? 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=1920&q=80',
                'count' => $city['events_count'] ?? 0
            ];
        } elseif (isset($data['success']) && !$data['success']) {
            // API returned explicit 404 - city doesn't exist
            $cityNotFound = true;
        }
    }
} catch (Exception $e) {
    // If API fails, use fallback data (don't show 404)
}

// If city explicitly not found (API returned 404), show 404 page
if ($cityNotFound) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// If API failed but didn't return explicit 404, use fallback data
if (!$cityConfig) {
    $cityConfig = [
        'name' => ucwords(str_replace('-', ' ', $citySlug)),
        'description' => 'Descoperă cele mai bune evenimente din acest oraș.',
        'hero_image' => 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=1920&q=80',
        'count' => 0
    ];
}

$pageTitle = 'Evenimente în ' . $cityConfig['name'];
$pageDescription = $cityConfig['description'];
$currentPage = 'cities';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative h-[420px] md:h-[480px] overflow-hidden">
    <img src="<?= htmlspecialchars($cityConfig['hero_image']) ?>" alt="<?= htmlspecialchars($cityConfig['name']) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-black/30"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-12 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/orase" class="transition-colors hover:text-white">Orașe</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white"><?= htmlspecialchars($cityConfig['name']) ?></span>
        </nav>
        <div class="flex items-center gap-4 mb-4">
            <div class="flex items-center justify-center w-16 h-16 text-3xl shadow-lg bg-primary rounded-2xl">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <span class="text-sm font-medium tracking-wider uppercase text-white/60">Evenimente în</span>
                <h1 class="text-4xl font-extrabold text-white md:text-5xl"><?= htmlspecialchars($cityConfig['name']) ?></h1>
            </div>
        </div>
        <p class="max-w-2xl mb-6 text-lg text-white/80"><?= htmlspecialchars($cityConfig['description']) ?></p>
        <div class="flex flex-wrap items-center gap-3">
            <span class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm" id="eventsCount"><?= $cityConfig['count'] ?> evenimente disponibile</span>
        </div>
    </div>
</section>

<!-- Mobile Filters Button -->
<section class="sticky top-[72px] z-20 py-3 bg-white border-b border-gray-200 shadow-sm lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4">
        <button onclick="openCityFiltersDrawer()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filtre
            <span id="mobileFilterCount" class="hidden px-2 py-0.5 text-xs font-bold text-white rounded-full bg-primary">0</span>
        </button>
        <select id="sortSelectMobile" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="document.getElementById('sortSelect').value = this.value; CityPage.filter();">
            <option value="date">Data</option>
            <option value="popular">Popular</option>
            <option value="price_asc">Preț ↑</option>
            <option value="price_desc">Preț ↓</option>
        </select>
    </div>
</section>

<!-- Desktop Filters Bar -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-gray-200 shadow-sm hidden lg:block">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center gap-3">
            <select id="categoryFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="CityPage.filter()">
                <option value="">Toate categoriile</option>
                <?php
                $eventCategories = getEventCategories();
                foreach ($eventCategories as $category):
                ?>
                <option value="<?= htmlspecialchars($category['slug']) ?>"><?= htmlspecialchars($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="dateFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="CityPage.filter()">
                <option value="">Oricând</option>
                <option value="today">Astăzi</option>
                <option value="tomorrow">Mâine</option>
                <option value="weekend">Weekend</option>
                <option value="week">Săptămâna asta</option>
                <option value="month">Luna asta</option>
                <option value="next-month">Luna viitoare</option>
            </select>
            <select id="priceFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="CityPage.filter()">
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
                <select id="sortSelect" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="CityPage.filter()">
                    <option value="date">Data (aproape)</option>
                    <option value="popular">Popularitate</option>
                    <option value="price_asc">Preț (mic - mare)</option>
                    <option value="price_desc">Preț (mare - mic)</option>
                </select>
            </div>
        </div>
    </div>
</section>

<!-- Mobile Filters Drawer -->
<div id="cityFiltersBackdrop" class="fixed inset-0 z-[105] transition-opacity duration-300 bg-black/50 backdrop-blur-sm lg:hidden" style="opacity: 0; visibility: hidden;" onclick="closeCityFiltersDrawer()"></div>
<div id="cityFiltersDrawer" class="fixed bottom-0 left-0 right-0 z-[110] overflow-hidden transition-transform duration-300 bg-white lg:hidden rounded-t-3xl max-h-[85vh]" style="transform: translateY(100%);">
    <div class="sticky top-0 z-10 flex items-center justify-between p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-bold text-gray-900">Filtre</h2>
        <button onclick="closeCityFiltersDrawer()" class="flex items-center justify-center w-10 h-10 transition-colors bg-gray-100 rounded-full hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="p-4 space-y-4 overflow-y-auto max-h-[60vh]">
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Categorie</label>
            <select id="categoryFilterMobile" class="w-full px-4 py-3 text-sm font-medium border border-gray-200 bg-gray-50 rounded-xl" onchange="document.getElementById('categoryFilter').value = this.value;">
                <option value="">Toate categoriile</option>
                <?php foreach ($eventCategories as $category): ?>
                <option value="<?= htmlspecialchars($category['slug']) ?>"><?= htmlspecialchars($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Dată</label>
            <select id="dateFilterMobile" class="w-full px-4 py-3 text-sm font-medium border border-gray-200 bg-gray-50 rounded-xl" onchange="document.getElementById('dateFilter').value = this.value;">
                <option value="">Oricând</option>
                <option value="today">Astăzi</option>
                <option value="tomorrow">Mâine</option>
                <option value="weekend">Weekend</option>
                <option value="week">Săptămâna asta</option>
                <option value="month">Luna asta</option>
                <option value="next-month">Luna viitoare</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Preț</label>
            <select id="priceFilterMobile" class="w-full px-4 py-3 text-sm font-medium border border-gray-200 bg-gray-50 rounded-xl" onchange="document.getElementById('priceFilter').value = this.value;">
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
        <button onclick="clearCityFilters(); closeCityFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">
            Șterge filtre
        </button>
        <button onclick="CityPage.filter(); closeCityFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-bold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
            Aplică filtre
        </button>
    </div>
</div>

<script>
function openCityFiltersDrawer() {
    document.getElementById('cityFiltersBackdrop').style.opacity = '1';
    document.getElementById('cityFiltersBackdrop').style.visibility = 'visible';
    document.getElementById('cityFiltersDrawer').style.transform = 'translateY(0)';
    document.body.style.overflow = 'hidden';
    // Sync desktop values to mobile
    document.getElementById('categoryFilterMobile').value = document.getElementById('categoryFilter').value;
    document.getElementById('dateFilterMobile').value = document.getElementById('dateFilter').value;
    document.getElementById('priceFilterMobile').value = document.getElementById('priceFilter').value;
}
function closeCityFiltersDrawer() {
    document.getElementById('cityFiltersBackdrop').style.opacity = '0';
    document.getElementById('cityFiltersBackdrop').style.visibility = 'hidden';
    document.getElementById('cityFiltersDrawer').style.transform = 'translateY(100%)';
    document.body.style.overflow = '';
}
function clearCityFilters() {
    document.getElementById('categoryFilter').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('priceFilter').value = '';
    document.getElementById('categoryFilterMobile').value = '';
    document.getElementById('dateFilterMobile').value = '';
    document.getElementById('priceFilterMobile').value = '';
    CityPage.filter();
}
</script>

<!-- Events Grid -->
<main class="px-4 py-12 mx-auto max-w-7xl">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="eventsGrid">
        <!-- Loading skeletons -->
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="overflow-hidden bg-white border rounded-2xl border-border">
            <div class="h-48 skeleton"></div>
            <div class="p-5">
                <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                <div class="w-1/2 mb-3 skeleton skeleton-text"></div>
                <div class="w-1/3 h-6 skeleton"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-2 mt-12" id="pagination"></div>
</main>

<?php require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$citySlugJS = json_encode($citySlug);
$scriptsExtra = '<script src="' . asset('assets/js/pages/city-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => { CityPage.init(' . $citySlugJS . '); FeaturedCarousel.init({ city: ' . $citySlugJS . ' }); });</script>';

require_once __DIR__ . '/includes/scripts.php';
