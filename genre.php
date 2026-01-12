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

<!-- Events Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
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
            </div>
            <div class="flex items-center gap-3">
                <span id="resultsCount" class="text-sm text-muted">-- rezultate</span>
                <select id="sortEvents" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Pret (mic)</option>
                    <option value="price_desc">Pret (mare)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>

        <!-- Featured Event -->
        <div class="mb-8" id="featuredEventSection" style="display: none;">
            <a href="#" id="featuredEvent" class="block overflow-hidden transition-shadow bg-white border rounded-3xl border-border hover:shadow-xl">
                <!-- Featured event will be loaded dynamically -->
            </a>
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

        <!-- Load More -->
        <div class="mt-12 text-center" id="loadMoreSection">
            <button id="loadMoreBtn" onclick="GenrePage.loadMore()" class="inline-flex items-center gap-2 px-8 py-4 font-bold transition-all border-2 border-primary text-primary rounded-xl hover:bg-primary hover:text-white">
                Incarca mai multe
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$genreSlugJS = json_encode($genreSlug);
$scriptsExtra = '<script src="' . asset('assets/js/pages/genre-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => GenrePage.init(' . $genreSlugJS . '));</script>';

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
