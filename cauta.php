<?php
/**
 * Search Results Page
 * Displays search results for events, artists, and locations
 */
require_once __DIR__ . '/includes/config.php';

$searchQuery = trim($_GET['q'] ?? '');
$pageTitle = $searchQuery ? 'Rezultate pentru "' . htmlspecialchars($searchQuery) . '"' : 'Căutare';
$pageDescription = 'Caută evenimente, artiști și locații pe ' . SITE_NAME;

$currentPage = 'search';
$transparentHeader = true;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="relative h-[320px] md:h-[380px] overflow-hidden bg-gradient-to-br from-primary via-primary-dark to-secondary pt-32">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0zNiAxOGMzLjMxNCAwIDYgMi42ODYgNiA2cy0yLjY4NiA2LTYgNi02LTIuNjg2LTYtNiAyLjY4Ni02IDYtNiIgc3Ryb2tlPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDUpIiBzdHJva2Utd2lkdGg9IjIiLz48L2c+PC9zdmc+')] opacity-30"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-10 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasă</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white">Căutare</span>
        </nav>
        <h1 class="mb-3 text-3xl font-extrabold text-white md:text-4xl">
            <svg class="inline-block w-8 h-8 mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span id="pageTitle"><?= $searchQuery ? 'Rezultate pentru "' . htmlspecialchars($searchQuery) . '"' : 'Căutare' ?></span>
        </h1>
        <p class="max-w-xl text-lg text-white/80">Găsește evenimente, artiști și locații</p>

        <!-- Search Box -->
        <div class="mt-6 max-w-2xl">
            <form id="searchForm" class="relative">
                <input type="text"
                       id="searchInput"
                       name="q"
                       value="<?= htmlspecialchars($searchQuery) ?>"
                       placeholder="Caută evenimente, artiști, locații..."
                       class="w-full px-5 py-4 pl-12 text-lg bg-white border-0 shadow-xl rounded-2xl focus:outline-none focus:ring-4 focus:ring-white/30"
                       autocomplete="off">
                <svg class="absolute w-6 h-6 text-gray-400 -translate-y-1/2 left-4 top-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <button type="submit" class="absolute px-6 py-2 font-semibold text-white transition-colors -translate-y-1/2 right-2 top-1/2 bg-primary hover:bg-primary-dark rounded-xl">
                    Caută
                </button>
            </form>
        </div>

        <!-- Results Summary -->
        <div id="resultsSummary" class="flex items-center gap-4 mt-4">
            <span id="eventsCount" class="hidden px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- evenimente</span>
            <span id="artistsCount" class="hidden px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- artiști</span>
            <span id="locationsCount" class="hidden px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- locații</span>
        </div>
    </div>
</section>

<!-- Search Results -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">

        <!-- Loading State -->
        <div id="loadingState" class="hidden">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="h-48 skeleton"></div>
                    <div class="p-4">
                        <div class="skeleton skeleton-title"></div>
                        <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                        <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Empty State (no query) -->
        <div id="emptyQueryState" class="<?= $searchQuery ? 'hidden' : '' ?>">
            <div class="py-16 text-center">
                <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-gray-900">Începe să cauți</h2>
                <p class="max-w-md mx-auto text-gray-500">Scrie cel puțin 2 caractere pentru a căuta evenimente, artiști sau locații.</p>
            </div>
        </div>

        <!-- No Results State -->
        <div id="noResultsState" class="hidden">
            <div class="py-16 text-center">
                <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-gray-900">Nu am găsit rezultate</h2>
                <p class="max-w-md mx-auto text-gray-500">Nu am găsit nimic pentru "<span id="noResultsQuery"></span>". Încearcă alți termeni de căutare.</p>
            </div>
        </div>

        <!-- Results Content -->
        <div id="resultsContent" class="hidden space-y-12">

            <!-- Events Section -->
            <div id="eventsSection" class="hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <h2 class="text-2xl font-bold text-secondary">Evenimente</h2>
                        <span id="eventsSectionCount" class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-600"></span>
                    </div>
                </div>
                <div id="eventsGrid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <!-- Events loaded dynamically -->
                </div>
            </div>

            <!-- Artists Section -->
            <div id="artistsSection" class="hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <h2 class="text-2xl font-bold text-secondary">Artiști</h2>
                        <span id="artistsSectionCount" class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-600"></span>
                    </div>
                </div>
                <div id="artistsGrid" class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <!-- Artists loaded dynamically -->
                </div>
            </div>

            <!-- Locations Section -->
            <div id="locationsSection" class="hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-green-100">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <h2 class="text-2xl font-bold text-secondary">Locații</h2>
                        <span id="locationsSectionCount" class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-600"></span>
                    </div>
                </div>
                <div id="locationsGrid" class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <!-- Locations loaded dynamically -->
                </div>
            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$searchQueryJS = json_encode($searchQuery);
$scriptsExtra = '<script src="' . asset('assets/js/pages/search-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => SearchPage.init(' . $searchQueryJS . '));</script>';

require_once __DIR__ . '/includes/scripts.php';
