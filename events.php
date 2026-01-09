<?php
/**
 * Events Listing Page - Main events browse and search page
 *
 * Features:
 * - Category filter (from API)
 * - City/Region filter
 * - Genre filter
 * - Artist filter
 * - Price filter
 * - Date filter
 * - Popularity sorting
 * - Search
 * - Pagination
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';

// Get filter params from URL
$filterCategory = $_GET['categorie'] ?? $_GET['category'] ?? '';
$filterCity = $_GET['oras'] ?? $_GET['city'] ?? '';
$filterRegion = $_GET['regiune'] ?? $_GET['region'] ?? '';
$filterGenre = $_GET['gen'] ?? $_GET['genre'] ?? '';
$filterArtist = $_GET['artist'] ?? '';
$filterPrice = $_GET['pret'] ?? $_GET['price'] ?? '';
$filterDate = $_GET['data'] ?? $_GET['date'] ?? '';
$filterSort = $_GET['sortare'] ?? $_GET['sort'] ?? 'date';
$searchQuery = $_GET['q'] ?? $_GET['search'] ?? '';

// Load categories for filter
$eventCategories = getEventCategories();
$featuredCities = getFeaturedCities();

$pageTitle = 'Evenimente';
if ($searchQuery) {
    $pageTitle = 'Căutare: ' . htmlspecialchars($searchQuery);
} elseif ($filterCategory) {
    $categoryName = array_filter($eventCategories, fn($c) => $c['slug'] === $filterCategory);
    $categoryName = reset($categoryName);
    $pageTitle = 'Evenimente - ' . ($categoryName['name'] ?? ucfirst($filterCategory));
}

$pageDescription = 'Descoperă cele mai bune evenimente din România. Concerte, festivaluri, teatru, stand-up și multe altele.';
$currentPage = 'events';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="relative pt-40 pb-8 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"1\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
    </div>
    <div class="relative px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center text-center">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">
                <?php if ($searchQuery): ?>
                    Rezultate pentru "<?= htmlspecialchars($searchQuery) ?>"
                <?php elseif ($filterCategory): ?>
                    <?= htmlspecialchars($categoryName['name'] ?? ucfirst($filterCategory)) ?>
                <?php else: ?>
                    Descoperă Evenimente
                <?php endif; ?>
            </h1>
            <p class="max-w-2xl mb-8 text-lg text-gray-300">
                Găsește și cumpără bilete pentru cele mai tari concerte, festivaluri, spectacole de teatru și multe altele.
            </p>
        </div>
    </div>
</section>

<!-- Category Quick Filters -->
<section class="py-6 bg-white border-b border-gray-200">
    <div class="px-4 mx-auto max-w-7xl">
        <div id="categoryFilters" class="flex items-center gap-3 overflow-x-auto scrollbar-hide">
            <button onclick="EventsPage.setCategory('')" data-category="" class="category-btn flex-shrink-0 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer <?= !$filterCategory ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Toate
            </button>
            <?php foreach ($eventCategories as $category): ?>
            <button onclick="EventsPage.setCategory('<?= addslashes($category['slug']) ?>')" data-category="<?= htmlspecialchars($category['slug']) ?>" class="category-btn flex-shrink-0 flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer <?= $filterCategory === $category['slug'] ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <span class="text-base"><?= $category['icon_emoji'] ?? '🎫' ?></span>
                <?= htmlspecialchars($category['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Filters Bar -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-gray-200 shadow-sm">
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
                <option value="pop" <?= $filterGenre === 'pop' ? 'selected' : '' ?>>Pop</option>
                <option value="rock" <?= $filterGenre === 'rock' ? 'selected' : '' ?>>Rock</option>
                <option value="hip-hop" <?= $filterGenre === 'hip-hop' ? 'selected' : '' ?>>Hip-Hop</option>
                <option value="electronic" <?= $filterGenre === 'electronic' ? 'selected' : '' ?>>Electronic</option>
                <option value="jazz" <?= $filterGenre === 'jazz' ? 'selected' : '' ?>>Jazz</option>
                <option value="clasic" <?= $filterGenre === 'clasic' ? 'selected' : '' ?>>Clasic</option>
                <option value="folk" <?= $filterGenre === 'folk' ? 'selected' : '' ?>>Folk</option>
            </select>

            <!-- Date Filter -->
            <select id="dateFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="" <?= !$filterDate ? 'selected' : '' ?>>Oricând</option>
                <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Astăzi</option>
                <option value="tomorrow" <?= $filterDate === 'tomorrow' ? 'selected' : '' ?>>Mâine</option>
                <option value="weekend" <?= $filterDate === 'weekend' ? 'selected' : '' ?>>Weekend</option>
                <option value="week" <?= $filterDate === 'week' ? 'selected' : '' ?>>Săptămâna asta</option>
                <option value="month" <?= $filterDate === 'month' ? 'selected' : '' ?>>Luna asta</option>
                <option value="next-month" <?= $filterDate === 'next-month' ? 'selected' : '' ?>>Luna viitoare</option>
            </select>

            <!-- Price Filter -->
            <select id="priceFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="" <?= !$filterPrice ? 'selected' : '' ?>>Orice preț</option>
                <option value="free" <?= $filterPrice === 'free' ? 'selected' : '' ?>>Gratuit</option>
                <option value="0-50" <?= $filterPrice === '0-50' ? 'selected' : '' ?>>Sub 50 lei</option>
                <option value="50-100" <?= $filterPrice === '50-100' ? 'selected' : '' ?>>50 - 100 lei</option>
                <option value="100-200" <?= $filterPrice === '100-200' ? 'selected' : '' ?>>100 - 200 lei</option>
                <option value="200-500" <?= $filterPrice === '200-500' ? 'selected' : '' ?>>200 - 500 lei</option>
                <option value="500+" <?= $filterPrice === '500+' ? 'selected' : '' ?>>Peste 500 lei</option>
            </select>

            <!-- Sort -->
            <div class="flex items-center gap-2 ml-auto">
                <span class="text-sm text-gray-500">Sortare:</span>
                <select id="sortFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                    <option value="date" <?= $filterSort === 'date' ? 'selected' : '' ?>>Data (aproape)</option>
                    <option value="popular" <?= $filterSort === 'popular' ? 'selected' : '' ?>>Popularitate</option>
                    <option value="price_asc" <?= $filterSort === 'price_asc' ? 'selected' : '' ?>>Preț (mic - mare)</option>
                    <option value="price_desc" <?= $filterSort === 'price_desc' ? 'selected' : '' ?>>Preț (mare - mic)</option>
                    <option value="newest" <?= $filterSort === 'newest' ? 'selected' : '' ?>>Cele mai noi</option>
                </select>
            </div>
        </div>

        <!-- Active Filters -->
        <div id="activeFilters" class="flex flex-wrap items-center gap-2 mt-3" style="display: none;">
            <span class="text-sm text-gray-500">Filtre active:</span>
            <div id="activeFilterTags" class="flex flex-wrap gap-2"></div>
            <button onclick="EventsPage.clearFilters()" class="ml-2 text-sm font-medium text-primary hover:underline">Șterge toate</button>
        </div>
    </div>
</section>

<!-- Results Info -->
<section class="py-4 bg-gray-50">
    <div class="flex items-center justify-between px-4 mx-auto max-w-7xl">
        <p class="text-sm text-gray-600">
            <span id="resultsCount" class="font-semibold text-gray-900">0</span> evenimente găsite
        </p>
        <div class="flex items-center gap-2">
            <button onclick="EventsPage.setView('grid')" id="viewGrid" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            </button>
            <button onclick="EventsPage.setView('list')" id="viewList" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
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
            <h3 class="mb-2 text-xl font-bold text-gray-900">Nu am găsit evenimente</h3>
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
// Build initial filters object for JavaScript
$initialFilters = json_encode([
    'category' => $filterCategory,
    'city' => $filterCity,
    'region' => $filterRegion,
    'genre' => $filterGenre,
    'artist' => $filterArtist,
    'price' => $filterPrice,
    'date' => $filterDate,
    'sort' => $filterSort,
    'search' => $searchQuery
]);

$scriptsExtra = <<<SCRIPTS
<script src="/assets/js/pages/events-page.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => EventsPage.init({$initialFilters}));</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
