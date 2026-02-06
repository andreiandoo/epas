<?php
/**
 * TICS.ro - Events Listing Page
 *
 * Main events listing with filters, AI recommendations, grid/list views
 * URL: /evenimente or /evenimente/{categorie}
 */

// Initialize
require_once __DIR__ . '/includes/config.php';

// Get URL parameters
$filterCategory = isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie'], ENT_QUOTES, 'UTF-8') : '';
$filterCity = isset($_GET['oras']) ? htmlspecialchars($_GET['oras'], ENT_QUOTES, 'UTF-8') : '';
$filterDate = isset($_GET['data']) ? htmlspecialchars($_GET['data'], ENT_QUOTES, 'UTF-8') : '';
$filterPrice = isset($_GET['pret']) ? htmlspecialchars($_GET['pret'], ENT_QUOTES, 'UTF-8') : '';
$filterSort = isset($_GET['sortare']) ? htmlspecialchars($_GET['sortare'], ENT_QUOTES, 'UTF-8') : 'recommended';
$filterSearch = isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : '';

// Category is passed via query string from .htaccess rewrite
// No PATH_INFO parsing needed

// Page metadata
$categoryData = getCategory($filterCategory);
$currentPage = 'events';

if ($categoryData) {
    $pageTitle = $categoryData['name'] . ' - Evenimente';
    $pageDescription = 'Descoperă cele mai bune ' . strtolower($categoryData['name']) . ' din România. Bilete și evenimente ' . strtolower($categoryData['name']) . ' pe TICS.ro';
    $pageType = 'category';
    $pageData = [
        'name' => $categoryData['name'],
        'events' => [] // Will be populated by JS
    ];
} else {
    $pageTitle = 'Evenimente';
    $pageDescription = 'Descoperă evenimente unice în România. Concerte, festivaluri, stand-up, teatru și multe altele pe TICS.ro';
    $pageType = 'website';
}

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Evenimente', 'url' => '/evenimente']
];
if ($categoryData) {
    $breadcrumbs[] = ['name' => $categoryData['name']];
}

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';

// Include categories bar
require_once __DIR__ . '/includes/categories-bar.php';
?>

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
                        <h1 class="text-xl font-semibold text-gray-900">
                            <span id="resultsCount">0</span> evenimente
                            <?php if ($categoryData): ?>
                                - <?= e($categoryData['icon']) ?> <?= e($categoryData['name']) ?>
                            <?php endif; ?>
                        </h1>
                        <p class="text-sm text-gray-500" id="resultsInfo">
                            <?php if ($filterCity): ?>în <?= e(ucfirst(str_replace('-', ' ', $filterCity))) ?><?php endif; ?>
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

                <!-- AI Banner (shown when logged in and AI enabled) -->
                <div id="aiBanner" class="hidden bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 rounded-2xl p-5 mb-6 border border-indigo-100">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-gray-900">AI caută evenimente perfecte pentru tine</h3>
                                <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">Bazat pe preferințele tale și istoricul de navigare.</p>
                            <div id="aiPreferenceTags" class="flex flex-wrap gap-2">
                                <!-- Will be populated by JS -->
                            </div>
                        </div>
                        <button class="text-sm font-medium text-indigo-600 hover:underline whitespace-nowrap hidden sm:block">Editează preferințe</button>
                    </div>
                </div>

                <!-- CTA Banner (shown when NOT logged in) -->
                <div id="ctaBanner" class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 rounded-2xl p-6 mb-6 text-white">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <div class="w-14 h-14 bg-white/10 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-lg mb-1">Descoperă evenimente perfecte pentru tine!</h3>
                            <p class="text-white/70 text-sm">Creează un cont gratuit și primești recomandări personalizate cu AI, notificări pentru artiștii preferați și acces la oferte exclusive.</p>
                        </div>
                        <div class="flex gap-3 w-full sm:w-auto">
                            <a href="/conectare" class="flex-1 sm:flex-none px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-medium rounded-xl transition-colors text-center">
                                Conectare
                            </a>
                            <a href="/inregistrare" class="flex-1 sm:flex-none px-5 py-2.5 bg-white text-gray-900 font-semibold rounded-xl hover:bg-gray-100 transition-colors text-center">
                                Creează cont gratuit
                            </a>
                        </div>
                    </div>
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
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Nu am găsit evenimente</h3>
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
                    city: <?= json_encode($filterCity) ?>,
                    date: <?= json_encode($filterDate) ?>,
                    price: <?= json_encode($filterPrice) ?>,
                    sort: <?= json_encode($filterSort) ?>,
                    search: <?= json_encode($filterSearch) ?>
                });
            }
        });
    </script>
</body>
</html>
