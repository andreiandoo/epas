<?php
/**
 * Past Events Listing Page - Browse ended events
 *
 * Features:
 * - Category filter (from API)
 * - City filter
 * - Genre filter
 * - Search
 * - Pagination
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';

// Get filter params from URL
$filterCategory = $_GET['categorie'] ?? $_GET['category'] ?? '';
$filterCity = $_GET['oras'] ?? $_GET['city'] ?? '';
$filterGenre = $_GET['gen'] ?? $_GET['genre'] ?? '';
$filterSort = $_GET['sortare'] ?? $_GET['sort'] ?? 'date';
$searchQuery = $_GET['q'] ?? $_GET['search'] ?? '';

// Load filter options from API with caching
$eventCategories = getEventCategories();
$featuredCities = getFeaturedCities();
$eventGenres = getEventGenres();

$pageTitle = 'Evenimente Trecute';
if ($searchQuery) {
    $pageTitle = 'Evenimente Trecute - Căutare: ' . htmlspecialchars($searchQuery);
} elseif ($filterCategory) {
    $categoryName = array_filter($eventCategories, fn($c) => $c['slug'] === $filterCategory);
    $categoryName = reset($categoryName);
    $pageTitle = 'Evenimente Trecute - ' . ($categoryName['name'] ?? ucfirst($filterCategory));
}

$pageDescription = 'Explorează evenimentele trecute din România. Concerte, festivaluri, teatru, stand-up și multe altele care au avut loc.';
$currentPage = 'past-events';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="relative pt-40 pb-8 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0"></div>
    </div>
    <div class="relative px-4 mx-auto max-w-7xl mobile:px-0">
        <div class="flex flex-col items-center text-center mobile:px-4">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">
                <?php if ($searchQuery): ?>
                    Rezultate pentru "<?= htmlspecialchars($searchQuery) ?>"
                <?php elseif ($filterCategory): ?>
                    <?= htmlspecialchars($categoryName['name'] ?? ucfirst($filterCategory)) ?> - Trecute
                <?php else: ?>
                    Evenimente Trecute
                <?php endif; ?>
            </h1>
            <p class="max-w-2xl mb-8 text-lg text-gray-300">
                Răsfoiește evenimentele care au avut loc. Revizuiește concertele, festivalurile și spectacolele din trecut.
            </p>
        </div>
        <div id="categoryFilters" class="flex items-center gap-3 pb-2 overflow-x-auto lg:justify-center scrollbar-hide snap-x snap-mandatory mobile:pl-4 mobile:-mr-4">
            <button onclick="EventsPage.setCategory('')" data-category="" class="category-btn flex-shrink-0 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer snap-start <?= !$filterCategory ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Toate
            </button>
            <?php foreach ($eventCategories as $category): ?>
            <button onclick="EventsPage.setCategory('<?= addslashes($category['slug']) ?>')" data-category="<?= htmlspecialchars($category['slug']) ?>" class="category-btn flex-shrink-0 flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer snap-start <?= $filterCategory === $category['slug'] ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <span class="text-base"><?= $category['icon_emoji'] ?? '🎫' ?></span>
                <?= htmlspecialchars($category['name']) ?>
            </button>
            <?php endforeach; ?>
            <div class="flex-shrink-0 w-4 lg:hidden"></div>
        </div>
    </div>
</section>

<!-- Mobile Filters Button -->
<section class="sticky top-[72px] z-20 py-3 bg-white border-b border-gray-200 shadow-sm lg:hidden">
    <div class="flex items-center justify-between gap-3 px-4">
        <button onclick="openFiltersDrawer()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filtre
            <span id="mobileFilterCount" class="hidden px-2 py-0.5 text-xs font-bold text-white rounded-full bg-primary">0</span>
        </button>
        <select id="sortFilterMobile" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="syncFilters('sort'); EventsPage.applyFilters()">
            <option value="date" <?= $filterSort === 'date' ? 'selected' : '' ?>>Cele mai recente</option>
            <option value="popular" <?= $filterSort === 'popular' ? 'selected' : '' ?>>Popular</option>
        </select>
    </div>
</section>

<!-- Desktop Filters Bar -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-gray-200 shadow-sm hidden lg:block">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center gap-3">
            <!-- City Filter -->
            <select id="cityFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="">Toate orașele</option>
                <?php foreach ($featuredCities as $city): ?>
                <option value="<?= htmlspecialchars($city['slug']) ?>" <?= $filterCity === $city['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($city['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Genre Filter -->
            <select id="genreFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="">Toate genurile</option>
                <?php foreach ($eventGenres as $genre): ?>
                <option value="<?= htmlspecialchars($genre['slug']) ?>" <?= $filterGenre === $genre['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($genre['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Sort -->
            <div class="flex items-center gap-2 ml-auto">
                <span class="text-sm text-gray-500">Sortare:</span>
                <select id="sortFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                    <option value="date" <?= $filterSort === 'date' ? 'selected' : '' ?>>Cele mai recente</option>
                    <option value="popular" <?= $filterSort === 'popular' ? 'selected' : '' ?>>Popularitate</option>
                </select>
            </div>
        </div>

        <!-- Active Filters -->
        <div id="activeFilters" class="flex flex-wrap items-center gap-2 mt-3" style="display: none;">
            <span class="text-sm text-gray-500">Filtre active:</span>
            <div id="activeFilterTags" class="flex flex-wrap gap-2"></div>
            <button onclick="EventsPage.clearFilters()" class="ml-2 text-sm font-medium text-primary">Șterge toate</button>
        </div>
    </div>
</section>

<!-- Mobile Filters Drawer -->
<div id="filtersDrawerBackdrop" class="fixed inset-0 z-[105] transition-opacity duration-300 bg-black/50 backdrop-blur-sm lg:hidden" style="opacity: 0; visibility: hidden;" onclick="closeFiltersDrawer()"></div>
<div id="filtersDrawer" class="fixed bottom-0 left-0 right-0 z-[110] overflow-hidden transition-transform duration-300 bg-white lg:hidden rounded-t-3xl max-h-[85vh]" style="transform: translateY(100%);">
    <!-- Drawer Header -->
    <div class="sticky top-0 z-10 flex items-center justify-between p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-bold text-gray-900">Filtre</h2>
        <button onclick="closeFiltersDrawer()" class="flex items-center justify-center w-10 h-10 transition-colors rounded-full bg-gray-100 hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <!-- Drawer Content -->
    <div class="p-4 space-y-4 overflow-y-auto max-h-[60vh]">
        <!-- City Filter -->
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Oraș</label>
            <select id="cityFilterMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="syncFilters('city')">
                <option value="">Toate orașele</option>
                <?php foreach ($featuredCities as $city): ?>
                <option value="<?= htmlspecialchars($city['slug']) ?>" <?= $filterCity === $city['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($city['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Genre Filter -->
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Gen muzical</label>
            <select id="genreFilterMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl" onchange="syncFilters('genre')">
                <option value="">Toate genurile</option>
                <?php foreach ($eventGenres as $genre): ?>
                <option value="<?= htmlspecialchars($genre['slug']) ?>" <?= $filterGenre === $genre['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($genre['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <!-- Drawer Footer -->
    <div class="flex gap-3 p-4 border-t border-gray-200 bg-gray-50">
        <button onclick="EventsPage.clearFilters(); closeFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">
            Șterge filtre
        </button>
        <button onclick="EventsPage.applyFilters(); closeFiltersDrawer();" class="flex-1 px-4 py-3 text-sm font-bold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
            Aplică filtre
        </button>
    </div>
</div>

<script>
// Mobile filters drawer functions
function openFiltersDrawer() {
    const backdrop = document.getElementById('filtersDrawerBackdrop');
    const drawer = document.getElementById('filtersDrawer');
    backdrop.style.opacity = '1';
    backdrop.style.visibility = 'visible';
    drawer.style.transform = 'translateY(0)';
    document.body.style.overflow = 'hidden';
}

function closeFiltersDrawer() {
    const backdrop = document.getElementById('filtersDrawerBackdrop');
    const drawer = document.getElementById('filtersDrawer');
    backdrop.style.opacity = '0';
    backdrop.style.visibility = 'hidden';
    drawer.style.transform = 'translateY(100%)';
    document.body.style.overflow = '';
}

// Sync filters between mobile and desktop
function syncFilters(type) {
    const mappings = {
        city: ['cityFilter', 'cityFilterMobile'],
        genre: ['genreFilter', 'genreFilterMobile'],
        sort: ['sortFilter', 'sortFilterMobile']
    };

    if (mappings[type]) {
        const [desktop, mobile] = mappings[type];
        const desktopEl = document.getElementById(desktop);
        const mobileEl = document.getElementById(mobile);
        if (desktopEl && mobileEl) {
            desktopEl.value = mobileEl.value;
        }
    }
    updateMobileFilterCount();
}

// Update mobile filter count badge
function updateMobileFilterCount() {
    let count = 0;
    const filters = ['cityFilterMobile', 'genreFilterMobile'];
    filters.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) count++;
    });

    const badge = document.getElementById('mobileFilterCount');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sortMobile = document.getElementById('sortFilterMobile');
    const sortDesktop = document.getElementById('sortFilter');
    if (sortMobile && sortDesktop) {
        sortMobile.addEventListener('change', () => {
            sortDesktop.value = sortMobile.value;
        });
    }
    updateMobileFilterCount();
});
</script>

<!-- Results Info -->
<section class="py-4 bg-gray-50">
    <div class="flex items-center justify-between px-4 mx-auto max-w-7xl">
        <p class="text-sm text-gray-600">
            <span id="resultsCount" class="font-semibold text-gray-900">0</span> evenimente trecute
        </p>
        <div class="flex items-center gap-2">
            <button onclick="EventsPage.setView('grid')" id="viewGrid" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            </button>
            <button onclick="EventsPage.setView('list')" id="viewList" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
</section>

<!-- Events Grid -->
<section class="py-8 bg-gray-50">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Loading State -->
        <div id="loadingState" class="py-20 text-center">
            <div class="inline-block w-12 h-12 border-4 rounded-full border-primary border-t-transparent animate-spin"></div>
            <p class="mt-4 text-gray-500">Se încarcă evenimentele...</p>
        </div>

        <!-- Events Container -->
        <div id="eventsGrid" class="hidden grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>

        <!-- Empty State -->
        <div id="emptyState" class="hidden py-20 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="mb-2 text-xl font-bold text-gray-900">Nu am găsit evenimente trecute</h3>
            <p class="mb-6 text-gray-500">Încearcă să modifici filtrele sau să cauți altceva.</p>
            <button onclick="EventsPage.clearFilters()" class="px-6 py-3 font-semibold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
                Resetează filtrele
            </button>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-10"></div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Build initial filters object for JavaScript - includes time_scope=past
$initialFilters = json_encode([
    'category' => $filterCategory,
    'city' => $filterCity,
    'genre' => $filterGenre,
    'sort' => $filterSort,
    'search' => $searchQuery,
    'time_scope' => 'past'
]);

$scriptsExtra = '<script src="' . asset('assets/js/pages/events-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => { EventsPage.init(' . $initialFilters . '); });</script>';

require_once __DIR__ . '/includes/scripts.php';
