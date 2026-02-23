<?php
/**
 * TICS.ro - Event Category Page
 *
 * Category-specific event listing with hero, subcategories, filters
 * URL: /evenimente/{category-slug}
 */

// Initialize
require_once __DIR__ . '/includes/config.php';

// Get category slug from URL
$categorySlug = $_GET['categorie'] ?? '';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo && preg_match('/^\/([a-z0-9-]+)$/', $pathInfo, $matches)) {
    $categorySlug = $matches[1];
}

if (!$categorySlug) {
    header('Location: /evenimente');
    exit;
}

// Fetch category from API (includes children/subcategories)
$_catApiResponse = callApi('event-categories/' . $categorySlug);
$_apiCatData     = (!empty($_catApiResponse['success']) && !empty($_catApiResponse['data']))
                    ? $_catApiResponse['data'] : null;

// Build categoryData from API or fallback to hardcoded config
if ($_apiCatData) {
    $_apiName = is_array($_apiCatData['name'] ?? null)
        ? ($_apiCatData['name']['ro'] ?? reset($_apiCatData['name']))
        : ($_apiCatData['name'] ?? ucfirst($categorySlug));

    $categoryData = [
        'name' => $_apiName,
        'icon' => $_apiCatData['icon_emoji'] ?? '📅',
        'slug' => $_apiCatData['slug'] ?? $categorySlug,
    ];

    // Children → subcategories
    $subcategories = array_map(function($_ch) {
        $_chName = is_array($_ch['name'] ?? null)
            ? ($_ch['name']['ro'] ?? reset($_ch['name']))
            : ($_ch['name'] ?? '');
        return [
            'name' => $_chName,
            'slug' => $_ch['slug'] ?? '',
            'icon' => $_ch['icon_emoji'] ?? '📅',
        ];
    }, $_apiCatData['children'] ?? []);
} else {
    // Fallback to hardcoded config
    $categoryData = getCategory($categorySlug);
    if (!$categoryData) {
        header('Location: /evenimente');
        exit;
    }
    // Hardcoded fallback subcategories
    $_FALLBACK_SUBS = [
        'concerte'   => [['name'=>'Rock','slug'=>'rock','icon'=>'🎸'],['name'=>'Pop','slug'=>'pop','icon'=>'🎤'],['name'=>'Electronic','slug'=>'electronic','icon'=>'🎧'],['name'=>'Jazz & Blues','slug'=>'jazz-blues','icon'=>'🎷'],['name'=>'Clasic','slug'=>'clasic','icon'=>'🎻']],
        'festivaluri'=> [['name'=>'Muzică','slug'=>'muzica','icon'=>'🎵'],['name'=>'Film','slug'=>'film','icon'=>'🎬'],['name'=>'Artă','slug'=>'arta','icon'=>'🎨']],
        'stand-up'   => [['name'=>'Stand-up','slug'=>'standup','icon'=>'🎤'],['name'=>'Improvizație','slug'=>'improv','icon'=>'🎭']],
        'teatru'     => [['name'=>'Dramă','slug'=>'drama','icon'=>'🎭'],['name'=>'Comedie','slug'=>'comedie','icon'=>'😂'],['name'=>'Musical','slug'=>'musical','icon'=>'🎵']],
        'sport'      => [['name'=>'Fotbal','slug'=>'fotbal','icon'=>'⚽'],['name'=>'Baschet','slug'=>'baschet','icon'=>'🏀'],['name'=>'Tenis','slug'=>'tenis','icon'=>'🎾']],
    ];
    $subcategories = $_FALLBACK_SUBS[$categorySlug] ?? [];
}

// Get URL parameters
$filterSubcategory = isset($_GET['subcategorie']) ? htmlspecialchars($_GET['subcategorie'], ENT_QUOTES, 'UTF-8') : '';
$filterCity = isset($_GET['oras']) ? htmlspecialchars($_GET['oras'], ENT_QUOTES, 'UTF-8') : '';
$filterDate = isset($_GET['data']) ? htmlspecialchars($_GET['data'], ENT_QUOTES, 'UTF-8') : '';
$filterPrice = isset($_GET['pret']) ? htmlspecialchars($_GET['pret'], ENT_QUOTES, 'UTF-8') : '';
$filterSort = isset($_GET['sortare']) ? htmlspecialchars($_GET['sortare'], ENT_QUOTES, 'UTF-8') : 'recommended';
$filterCategory = $categorySlug;

// Category gradient colors
$CATEGORY_GRADIENTS = [
    'concerte' => 'from-purple-600 via-indigo-600 to-blue-500',
    'festivaluri' => 'from-orange-500 via-red-500 to-pink-500',
    'stand-up' => 'from-yellow-500 via-amber-500 to-orange-500',
    'teatru' => 'from-rose-500 via-pink-500 to-purple-500',
    'sport' => 'from-green-500 via-emerald-500 to-teal-500',
    'arta-muzee' => 'from-cyan-500 via-blue-500 to-indigo-500',
    'familie' => 'from-pink-400 via-purple-400 to-indigo-400',
    'business' => 'from-gray-600 via-slate-600 to-gray-700',
    'educatie' => 'from-blue-500 via-indigo-500 to-purple-500',
];
$categoryGradient = $CATEGORY_GRADIENTS[$categorySlug] ?? 'from-indigo-500 via-purple-500 to-pink-500';

// Category stats (demo data)
$categoryStats = [
    'events_count' => 245,
    'cities_count' => 32,
    'this_week' => 18,
    'tickets_sold' => '45K+',
    'rating' => '4.9',
];

// Page metadata
$currentPage = 'events';
$pageTitle = $categoryData['name'] . ' - Evenimente';
$pageDescription = 'Descoperă cele mai bune ' . strtolower($categoryData['name']) . ' din România. Bilete și evenimente ' . strtolower($categoryData['name']) . ' pe TICS.ro';
$pageType = 'category';
$pageData = [
    'name' => $categoryData['name'],
    'events' => []
];

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Evenimente', 'url' => '/evenimente'],
    ['name' => $categoryData['name']]
];

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header (without categories bar - we have our own)
$hideCategoriesBar = true;
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Breadcrumb + Subcategories Bar -->
    <div class="sticky top-16 z-30 bg-white border-b border-gray-200">
        <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 py-3 text-sm">
                <a href="/" class="text-gray-500 hover:text-gray-900">Acasă</a>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/evenimente" class="text-gray-500 hover:text-gray-900">Evenimente</a>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/bilete-la-<?= e($categorySlug) ?>" class="text-gray-900 font-medium hover:text-purple-700"><?= e($categoryData['name']) ?></a>
            </div>
            <!-- Subcategories -->
            <?php if (!empty($subcategories)): ?>
            <div class="flex items-center gap-2 pb-3 overflow-x-auto no-scrollbar">
                <a href="/bilete-la-<?= e($categorySlug) ?>" class="category-chip <?= empty($filterSubcategory) ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium whitespace-nowrap transition-colors" data-subcategory="">
                    <?= e($categoryData['icon'] ?? '📅') ?> Toate
                </a>
                <?php foreach ($subcategories as $sub): ?>
                <a href="/bilete-la-<?= e($categorySlug) ?>?subcategorie=<?= e($sub['slug']) ?>" class="category-chip <?= $filterSubcategory === $sub['slug'] ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600 whitespace-nowrap hover:border-gray-300 transition-colors" data-subcategory="<?= e($sub['slug']) ?>">
                    <?= $sub['icon'] ?> <?= e($sub['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r <?= $categoryGradient ?> text-white py-12 lg:py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/></pattern></defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>
        <div class="max-w-[1600px] mx-auto px-4 lg:px-8 relative">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center">
                            <span class="text-3xl"><?= $categoryData['icon'] ?></span>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold"><?= e($categoryData['name']) ?></h1>
                            <p class="text-white/80">Experiențe de neuitat</p>
                        </div>
                    </div>
                    <p class="text-lg text-white/90 max-w-xl mb-6">
                        Descoperă cele mai așteptate <?= strtolower(e($categoryData['name'])) ?> din România. De la artiști internaționali la evenimentele locale preferate.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur rounded-full">
                            <span class="w-2 h-2 bg-green-400 rounded-full pulse"></span>
                            <span class="text-sm font-medium"><?= $categoryStats['events_count'] ?> evenimente active</span>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur rounded-full">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            <span class="text-sm font-medium"><?= $categoryStats['cities_count'] ?> orașe</span>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur rounded-full">
                            <span class="text-sm font-medium">⚡ <?= $categoryStats['this_week'] ?> evenimente săptămâna asta</span>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center">
                        <p class="text-3xl font-bold"><?= $categoryStats['tickets_sold'] ?></p>
                        <p class="text-sm text-white/80">Bilete vândute luna asta</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center">
                        <p class="text-3xl font-bold"><?= $categoryStats['rating'] ?>★</p>
                        <p class="text-sm text-white/80">Rating mediu</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <main class="max-w-[1600px] mx-auto px-4 lg:px-8 py-6">
        <div class="flex gap-8">
            <!-- Sidebar Filters -->
            <?php require_once __DIR__ . '/parts/side-filter.php'; ?>

            <!-- Events Content -->
            <div class="flex-1 min-w-0">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">
                            <span id="resultsCount">0</span> evenimente
                        </h2>
                        <p class="text-sm text-gray-500" id="resultsInfo">
                            <?= e($categoryData['icon']) ?> <?= e($categoryData['name']) ?>
                            <?php if ($filterCity): ?> • în <?= e(ucfirst(str_replace('-', ' ', $filterCity))) ?><?php endif; ?>
                            <?php if ($filterDate): ?> • <?= e($DATE_FILTERS[$filterDate] ?? '') ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="filterBtn" class="lg:hidden flex items-center gap-2 px-4 py-2.5 border border-gray-200 rounded-full text-sm font-medium hover:border-gray-300 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            Filtre
                            <span id="mobileFilterCount" class="hidden w-5 h-5 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center">0</span>
                        </button>
                        <select id="sortFilter" class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm font-medium cursor-pointer hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition-colors">
                            <?php foreach ($SORT_OPTIONS as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filterSort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hidden sm:flex items-center border border-gray-200 rounded-full p-1">
                            <button id="viewGrid" class="p-2 rounded-full bg-gray-900 text-white" title="Vizualizare grilă">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                            </button>
                            <button id="viewList" class="p-2 rounded-full text-gray-400 hover:text-gray-600 transition-colors" title="Vizualizare listă">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Filters -->
                <div id="activeFilters" class="hidden flex items-center gap-2 mb-6 overflow-x-auto no-scrollbar pb-1">
                    <span class="text-xs text-gray-500 whitespace-nowrap">Filtre active:</span>
                    <div id="activeFilterTags" class="flex items-center gap-2"></div>
                    <button onclick="TicsEventsPage.clearFilters()" class="text-xs text-indigo-600 font-medium hover:underline whitespace-nowrap">Șterge toate</button>
                </div>

                <!-- Loading State -->
                <div id="loadingState" class="hidden">
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                        <!-- Skeletons will be inserted by JS -->
                    </div>
                </div>

                <!-- Events Grid -->
                <div id="eventsGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <!-- Events will be rendered by JS -->
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden py-16 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-4xl"><?= $categoryData['icon'] ?></span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Nu am găsit <?= strtolower(e($categoryData['name'])) ?></h3>
                    <p class="text-gray-500 mb-6">Încearcă să modifici filtrele sau să cauți altceva.</p>
                    <button onclick="TicsEventsPage.clearFilters()" class="px-6 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
                        Resetează filtrele
                    </button>
                </div>

                <!-- Pagination -->
                <div id="pagination" class="flex items-center justify-center gap-2 mt-10">
                    <!-- Pagination will be rendered by JS -->
                </div>

                <!-- Results summary -->
                <p id="resultsSummary" class="text-sm text-center text-gray-400 mt-4"></p>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="<?= asset('assets/js/api.js') ?>"></script>
    <script src="<?= asset('assets/js/utils.js') ?>"></script>
    <script src="<?= asset('assets/js/components/search.js') ?>"></script>
    <script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
    <script src="<?= asset('assets/js/components/event-promo-card.js') ?>"></script>
    <script src="<?= asset('assets/js/components/side-filter.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/events-page.js') ?>"></script>

    <script>
        // Initialize page with server-side filters
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search component
            if (typeof TicsSearch !== 'undefined') {
                TicsSearch.init();
            }

            // Initialize events page with filters from PHP
            if (typeof TicsEventsPage !== 'undefined') {
                TicsEventsPage.init({
                    category: <?= json_encode($filterCategory) ?>,
                    subcategory: <?= json_encode($filterSubcategory) ?>,
                    city: <?= json_encode($filterCity) ?>,
                    date: <?= json_encode($filterDate) ?>,
                    price: <?= json_encode($filterPrice) ?>,
                    sort: <?= json_encode($filterSort) ?>
                });
            }
        });
    </script>
</body>
</html>
