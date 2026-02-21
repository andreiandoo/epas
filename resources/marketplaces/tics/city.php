<?php
/**
 * TICS.ro - City Page
 * Shows events in a specific city with full filters.
 * City data is loaded from the API via callApi().
 */

require_once __DIR__ . '/includes/config.php';

// Get city slug from URL (base slug, e.g. 'bucuresti')
$cityBaseSlug = $_GET['city'] ?? 'bucuresti';

// Get filter parameters
$filterCategory = isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie'], ENT_QUOTES, 'UTF-8') : '';
$filterDate     = isset($_GET['data'])      ? htmlspecialchars($_GET['data'],      ENT_QUOTES, 'UTF-8') : '';
$filterPrice    = isset($_GET['pret'])      ? htmlspecialchars($_GET['pret'],      ENT_QUOTES, 'UTF-8') : '';
$filterSort     = isset($_GET['sortare'])   ? htmlspecialchars($_GET['sortare'],   ENT_QUOTES, 'UTF-8') : 'recommended';

// ── Fetch city from API ───────────────────────────────────────────────────────
// LocationsController.city() accepts the base slug (without country prefix)
$cityResponse = callApi('locations/cities/' . $cityBaseSlug);

if (empty($cityResponse['success']) || empty($cityResponse['data']['city'])) {
    // City not found — redirect to cities listing
    http_response_code(302);
    header('Location: /locatii');
    exit;
}

$city     = $cityResponse['data']['city'];
$citySlug = $city['slug'];          // Full slug, e.g. 'ro-bucuresti'
$cityName = $city['name'];          // Display name, e.g. 'București'

// Format population
$populationText = '';
if (!empty($city['population']) && $city['population'] > 0) {
    $pop = (int) $city['population'];
    if ($pop >= 1000000) {
        $populationText = rtrim(rtrim(number_format($pop / 1000000, 1), '0'), '.') . 'M locuitori';
    } elseif ($pop >= 1000) {
        $populationText = number_format($pop / 1000, 0, '.', '') . 'K locuitori';
    } else {
        $populationText = number_format($pop) . ' locuitori';
    }
}

// Region / county label
$locationLabel = '';
if (!empty($city['region']['name']))  $locationLabel  = $city['region']['name'];
if (!empty($city['county']['name']))  $locationLabel .= ($locationLabel ? ', ' : '') . $city['county']['name'];

// Hero image: prefer cover_image, fallback to image
$heroImage = $city['cover_image'] ?? $city['image'] ?? '';

// ── Page configuration ────────────────────────────────────────────────────────
$pageTitle       = 'Evenimente în ' . $cityName;
$pageDescription = !empty($city['description'])
    ? $city['description']
    : 'Descoperă toate evenimentele din ' . $cityName . '. Concerte, festivaluri, teatru și mai mult pe TICS.ro.';
$canonicalUrl = SITE_URL . '/evenimente-' . $cityBaseSlug;

$breadcrumbs = [
    ['name' => 'Acasă',   'url' => '/'],
    ['name' => 'Locații', 'url' => '/locatii'],
    ['name' => $cityName],
];

$headExtra = <<<HTML
<style>
    .hero-gradient { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 50%, #3d7ab5 100%); }
    .stat-card { backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); }
</style>
HTML;

require_once __DIR__ . '/includes/head.php';
setLoginState($isLoggedIn, $loggedInUser);
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - City -->
<section class="hero-gradient text-white relative overflow-hidden">
    <?php if ($heroImage): ?>
    <div class="absolute inset-0">
        <img src="<?= e($heroImage) ?>" alt="<?= e($cityName) ?>" class="w-full h-full object-cover opacity-30">
    </div>
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900/80 via-gray-900/60 to-transparent"></div>

    <div class="max-w-[1600px] mx-auto px-4 lg:px-8 py-12 lg:py-20 relative">
        <div class="flex flex-col items-start gap-6 max-w-2xl">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-white/60">
                <a href="/" class="hover:text-white transition-colors">Acasă</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/locatii" class="hover:text-white transition-colors">Locații</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white"><?= e($cityName) ?></span>
            </div>

            <div>
                <h1 class="text-4xl lg:text-5xl font-bold"><?= e($cityName) ?></h1>
                <?php if ($locationLabel || $populationText): ?>
                <p class="text-white/80 mt-1">
                    <?php if (!empty($city['is_capital'])): ?><span class="mr-2">🏛️</span><?php endif; ?>
                    <?= e(implode(' • ', array_filter([$locationLabel, $populationText]))) ?>
                </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($city['description'])): ?>
            <p class="text-lg text-white/90"><?= e($city['description']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-3">
                <?php if (!empty($city['events_count'])): ?>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                    <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                    <span class="text-sm font-medium"><?= $city['events_count'] ?> evenimente active</span>
                </div>
                <?php endif; ?>
                <?php if ($locationLabel): ?>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-sm font-medium"><?= e($locationLabel) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Categories Bar - After Hero -->
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar" id="categoriesBar">
            <button class="chip-active px-4 py-2 rounded-full border border-gray-200 text-sm font-medium whitespace-nowrap transition-colors" data-category="">Toate</button>
            <?php foreach ($CATEGORIES as $slug => $cat): ?>
            <button class="px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600 whitespace-nowrap hover:border-gray-300 transition-colors" data-category="<?= e($slug) ?>">
                <?= $cat['icon'] ?> <?= e($cat['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Main Content -->
<main class="max-w-[1600px] mx-auto px-4 lg:px-8 py-6">
    <div class="flex gap-8">
        <!-- Sidebar Filters - Desktop -->
        <aside class="hidden lg:block w-72 flex-shrink-0">
            <div class="sticky top-40 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900">Filtre</h2>
                        <button onclick="TicsCityPage.clearFilters()" class="text-sm text-indigo-600 font-medium hover:underline">Resetează</button>
                    </div>
                </div>

                <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
                    <!-- Date Filter -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Când</h3>
                        <div class="space-y-2.5">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="today">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Astăzi</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="tomorrow">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Mâine</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="weekend">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Weekendul acesta</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="week">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Săptămâna viitoare</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="month">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Luna aceasta</span>
                            </label>
                        </div>
                    </div>

                    <!-- Price Filter -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Preț</h3>
                        <div class="flex gap-2">
                            <button data-price="free" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Gratuit</button>
                            <button data-price="0-100" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;100</button>
                            <button data-price="100-300" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;300</button>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div>
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Sortare</h3>
                        <select id="sortFilterSidebar" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm cursor-pointer focus:outline-none focus:ring-1 focus:ring-gray-900 transition-colors">
                            <option value="recommended">Recomandate</option>
                            <option value="date">Data: cele mai apropiate</option>
                            <option value="price_asc">Preț: mic → mare</option>
                            <option value="price_desc">Preț: mare → mic</option>
                            <option value="popular">Cele mai populare</option>
                        </select>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Events Content -->
        <div class="flex-1 min-w-0">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900"><span id="resultsCount"><?= (int) ($city['events_count'] ?? 0) ?></span> evenimente în <?= e($cityName) ?></h2>
                    <p class="text-sm text-gray-500" id="resultsInfo">Toate evenimentele</p>
                </div>
                <div class="flex items-center gap-3">
                    <button id="filterBtn" class="lg:hidden flex items-center gap-2 px-4 py-2.5 border border-gray-200 rounded-full text-sm font-medium hover:border-gray-300 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filtre
                    </button>
                    <div class="hidden sm:flex items-center border border-gray-200 rounded-full p-1">
                        <button id="viewGrid" class="p-2 rounded-full bg-gray-900 text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </button>
                        <button id="viewList" class="p-2 rounded-full text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            <div id="activeFilters" class="flex items-center gap-2 mb-6 overflow-x-auto no-scrollbar pb-1">
                <span class="text-xs text-gray-500 whitespace-nowrap">Filtre active:</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-full text-xs font-medium whitespace-nowrap">
                    📍 <?= e($cityName) ?>
                </span>
                <div id="dynamicFilters" class="flex items-center gap-2"></div>
                <button onclick="TicsCityPage.clearFilters()" class="text-xs text-indigo-600 font-medium hover:underline whitespace-nowrap" id="clearAllFiltersBtn" style="display: none;">Șterge toate</button>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="hidden">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php for ($i = 0; $i < 8; $i++): ?>
                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 animate-pulse">
                        <div class="aspect-[4/3] bg-gray-200"></div>
                        <div class="p-4 space-y-3">
                            <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
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
                <button onclick="TicsCityPage.clearFilters()" class="px-6 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
                    Resetează filtrele
                </button>
            </div>

            <!-- Load More -->
            <div class="text-center mt-10" id="loadMoreWrap">
                <button id="loadMoreBtn" class="px-8 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors hidden">
                    Încarcă mai multe evenimente
                </button>
                <p class="text-sm text-gray-400 mt-3" id="resultsSummary"></p>
            </div>
        </div>
    </div>
</main>

<!-- Mobile Filter Drawer -->
<div id="filterOverlay" class="overlay fixed inset-0 bg-black/50 z-50 lg:hidden"></div>
<div id="filterDrawer" class="drawer fixed top-0 left-0 bottom-0 w-80 max-w-[85vw] bg-white z-50 overflow-y-auto lg:hidden">
    <div class="sticky top-0 bg-white border-b border-gray-200 px-5 py-4 flex items-center justify-between">
        <h2 class="font-semibold text-lg">Filtre</h2>
        <button onclick="closeFiltersDrawer()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-5">
        <!-- Mobile Date Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Când</h3>
            <div class="space-y-2.5">
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="today"><span class="text-sm text-gray-600">Astăzi</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="tomorrow"><span class="text-sm text-gray-600">Mâine</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="weekend"><span class="text-sm text-gray-600">Weekend</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="month"><span class="text-sm text-gray-600">Luna aceasta</span></label>
            </div>
        </div>

        <!-- Mobile Price Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Preț</h3>
            <div class="flex gap-2">
                <button data-price="free" class="price-quick-btn flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">Gratuit</button>
                <button data-price="0-100" class="price-quick-btn flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">&lt;100</button>
                <button data-price="100-300" class="price-quick-btn flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">&lt;300</button>
            </div>
        </div>
    </div>

    <div class="sticky bottom-0 bg-white border-t border-gray-200 p-5 flex gap-3">
        <button onclick="TicsCityPage.clearFilters(); closeFiltersDrawer();" class="flex-1 py-3 border border-gray-200 rounded-xl font-medium hover:bg-gray-50 transition-colors">Resetează</button>
        <button onclick="TicsCityPage.applyFilters(); closeFiltersDrawer();" class="flex-1 py-3 bg-gray-900 text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Aplică</button>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="<?= asset('assets/js/utils.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-promo-card.js') ?>"></script>
<script>
    // City page configuration — uses full slug so EventsController can find the city
    const citySlug = <?= json_encode($citySlug) ?>;
    const cityName = <?= json_encode($cityName) ?>;
    let currentPage = 1;
    let totalEvents = <?= (int) ($city['events_count'] ?? 0) ?>;
    let isLoading = false;

    // Filter labels mapping
    const filterLabels = {
        date: {
            today: 'Astăzi',
            tomorrow: 'Mâine',
            weekend: 'Weekend',
            week: 'Săptămâna aceasta',
            month: 'Luna aceasta'
        }
    };

    // TicsCityPage object
    const TicsCityPage = {
        filters: {
            city: citySlug,
            category: '',
            date: '',
            price: 1000,
            priceRange: '',
            sort: 'recommended'
        },

        init: function() {
            this.loadEvents(1);
            this.bindEvents();
            this.updateActiveFilters();
        },

        bindEvents: function() {
            // Category chips
            document.querySelectorAll('#categoriesBar button').forEach(chip => {
                chip.addEventListener('click', (e) => {
                    document.querySelectorAll('#categoriesBar button').forEach(c => c.classList.remove('chip-active'));
                    e.target.classList.add('chip-active');
                    this.filters.category = e.target.dataset.category || '';
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // Sort filter (sidebar)
            document.getElementById('sortFilterSidebar')?.addEventListener('change', (e) => {
                this.filters.sort = e.target.value;
                this.loadEvents(1);
            });

            // Load more button
            document.getElementById('loadMoreBtn')?.addEventListener('click', () => {
                currentPage++;
                this.loadEvents(currentPage, true);
            });

            // Mobile filter button
            document.getElementById('filterBtn')?.addEventListener('click', openFiltersDrawer);

            // Date checkboxes
            document.querySelectorAll('[data-date]').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        document.querySelectorAll('[data-date]').forEach(other => {
                            if (other !== e.target) other.checked = false;
                        });
                        this.filters.date = e.target.dataset.date;
                    } else {
                        this.filters.date = '';
                    }
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // Price quick buttons
            document.querySelectorAll('.price-quick-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const priceValue = e.target.dataset.price;
                    if (this.filters.priceRange === priceValue) {
                        this.filters.priceRange = '';
                        e.target.classList.remove('bg-gray-900', 'text-white');
                        e.target.classList.add('bg-gray-100', 'text-gray-600');
                    } else {
                        document.querySelectorAll('.price-quick-btn').forEach(b => {
                            b.classList.remove('bg-gray-900', 'text-white');
                            b.classList.add('bg-gray-100', 'text-gray-600');
                        });
                        e.target.classList.remove('bg-gray-100', 'text-gray-600');
                        e.target.classList.add('bg-gray-900', 'text-white');
                        this.filters.priceRange = priceValue;
                    }
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });
        },

        updateActiveFilters: function() {
            const container = document.getElementById('dynamicFilters');
            const clearBtn  = document.getElementById('clearAllFiltersBtn');
            if (!container) return;

            container.innerHTML = '';
            let hasFilters = false;

            if (this.filters.date) {
                hasFilters = true;
                const label = filterLabels.date[this.filters.date] || this.filters.date;
                container.innerHTML += this.createFilterChip('date', '📅 ' + label);
            }

            if (this.filters.category) {
                hasFilters = true;
                const catBtn = document.querySelector(`#categoriesBar button[data-category="${this.filters.category}"]`);
                const label  = catBtn ? catBtn.textContent.trim() : this.filters.category;
                container.innerHTML += this.createFilterChip('category', label);
            }

            if (this.filters.priceRange) {
                hasFilters = true;
                const priceLabels = { 'free': 'Gratuit', '0-100': 'Sub 100 RON', '100-300': 'Sub 300 RON' };
                container.innerHTML += this.createFilterChip('priceRange', '💰 ' + (priceLabels[this.filters.priceRange] || this.filters.priceRange));
            }

            if (clearBtn) clearBtn.style.display = hasFilters ? 'inline' : 'none';
        },

        createFilterChip: function(type, label) {
            return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white rounded-full text-xs font-medium whitespace-nowrap">
                ${label}
                <button onclick="TicsCityPage.removeFilter('${type}')" class="hover:bg-white/20 rounded-full p-0.5 ml-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>`;
        },

        removeFilter: function(type) {
            switch (type) {
                case 'date':
                    this.filters.date = '';
                    document.querySelectorAll('[data-date]').forEach(cb => cb.checked = false);
                    break;
                case 'category':
                    this.filters.category = '';
                    document.querySelectorAll('#categoriesBar button').forEach((c, i) => {
                        c.classList.toggle('chip-active', i === 0);
                    });
                    break;
                case 'priceRange':
                    this.filters.priceRange = '';
                    document.querySelectorAll('.price-quick-btn').forEach(btn => {
                        btn.classList.remove('bg-gray-900', 'text-white');
                        btn.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    break;
            }
            this.updateActiveFilters();
            this.loadEvents(1);
        },

        loadEvents: async function(page = 1, append = false) {
            if (isLoading) return;
            isLoading = true;

            const grid         = document.getElementById('eventsGrid');
            const loadingState = document.getElementById('loadingState');
            const emptyState   = document.getElementById('emptyState');

            if (!append) {
                grid.innerHTML = '';
                loadingState?.classList.remove('hidden');
            }

            try {
                const params = { city: this.filters.city, page: page, per_page: 12 };
                if (this.filters.category)   params.category = this.filters.category;
                if (this.filters.date)        params.date     = this.filters.date;
                if (this.filters.priceRange)  params.price    = this.filters.priceRange;

                const response = await TicsAPI.getEvents(params);
                loadingState?.classList.add('hidden');

                if (response.success && response.data && response.data.length > 0) {
                    totalEvents = response.meta?.total || response.data.length;

                    response.data.forEach((event, index) => {
                        if (!append && index === 4 && typeof TicsEventPromoCard !== 'undefined') {
                            grid.insertAdjacentHTML('beforeend', TicsEventPromoCard.render());
                        }
                        grid.insertAdjacentHTML('beforeend', TicsEventCard.render(event));
                    });

                    emptyState?.classList.add('hidden');

                    const shown = Math.min(page * 12, totalEvents);
                    document.getElementById('resultsCount').textContent = totalEvents;
                    document.getElementById('resultsSummary').textContent = `Afișezi ${shown} din ${totalEvents} evenimente`;

                    const loadMoreBtn = document.getElementById('loadMoreBtn');
                    if (shown >= totalEvents) {
                        loadMoreBtn.style.display = 'none';
                    } else {
                        loadMoreBtn.style.display = 'inline-block';
                    }
                } else {
                    if (!append) {
                        emptyState?.classList.remove('hidden');
                        document.getElementById('resultsCount').textContent = '0';
                        document.getElementById('loadMoreBtn').style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error loading events:', error);
                loadingState?.classList.add('hidden');
                if (!append) {
                    grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Nu am putut încărca evenimentele. Încearcă din nou.</div>';
                }
            }

            isLoading = false;
        },

        clearFilters: function() {
            this.filters = {
                city: citySlug,
                category: '',
                date: '',
                price: 1000,
                priceRange: '',
                sort: 'recommended'
            };

            document.querySelectorAll('#categoriesBar button').forEach((c, i) => {
                c.classList.toggle('chip-active', i === 0);
            });
            document.querySelectorAll('.cb').forEach(cb => cb.checked = false);
            document.querySelectorAll('.price-quick-btn').forEach(btn => {
                btn.classList.remove('bg-gray-900', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            });

            this.updateActiveFilters();
            this.loadEvents(1);
        },

        applyFilters: function() {
            this.loadEvents(1);
        }
    };

    // Filter drawer
    function openFiltersDrawer() {
        document.getElementById('filterOverlay')?.classList.add('open');
        document.getElementById('filterDrawer')?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFiltersDrawer() {
        document.getElementById('filterOverlay')?.classList.remove('open');
        document.getElementById('filterDrawer')?.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('filterOverlay')?.addEventListener('click', closeFiltersDrawer);

    document.addEventListener('DOMContentLoaded', () => {
        TicsCityPage.init();
    });
</script>

</body>
</html>
