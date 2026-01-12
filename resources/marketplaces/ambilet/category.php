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
$transparentHeader = true;
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
    </div>
</section>

<!-- Filters & Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Genre Pills -->
        <div class="mb-8" id="genresSection">
            <h3 class="mb-4 text-sm font-semibold tracking-wider uppercase text-muted">Filtreaza dupa gen</h3>
            <div class="flex flex-wrap gap-2" id="genresPills">
                <button class="genre-pill active px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all" data-genre="">Toate</button>
                <!-- Genres will be loaded dynamically -->
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="flex flex-col justify-between gap-4 p-4 mb-8 bg-white border md:flex-row md:items-center rounded-2xl border-border">
            <div class="flex flex-wrap items-center gap-3">
                <select id="filterCity" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate orasele</option>
                </select>
                <select id="filterDate" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Oricand</option>
                    <option value="today">Astazi</option>
                    <option value="tomorrow">Maine</option>
                    <option value="this_week">Saptamana aceasta</option>
                    <option value="this_month">Luna aceasta</option>
                </select>
                <select id="filterPrice" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Orice pret</option>
                    <option value="0-50">Sub 50 lei</option>
                    <option value="50-100">50 - 100 lei</option>
                    <option value="100-200">100 - 200 lei</option>
                    <option value="200-">Peste 200 lei</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted">Sorteaza:</span>
                <select id="sortEvents" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Pret (mic)</option>
                    <option value="price_desc">Pret (mare)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>

        <!-- Events Grid -->
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

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-12"></div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$categorySlugJS = json_encode($categorySlug);
$scriptsExtra = '<script src="' . asset('assets/js/pages/category-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => CategoryPage.init(' . $categorySlugJS . '));</script>';

require_once __DIR__ . '/includes/scripts.php';
