<?php
/**
 * Artists Listing Page
 * Based on artists-listing.html template
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/category-config.php';

$pageTitle = 'Artisti';
$pageDescription = 'Descopera artistii tai preferati si nu rata urmatoarele lor concerte si evenimente in Romania.';
$currentPage = 'artists';
$transparentHeader = true;

// Get filters from URL
$genreFilter = $_GET['genre'] ?? '';
$letterFilter = $_GET['letter'] ?? '';
$sortBy = $_GET['sort'] ?? 'popular';
$page = max(1, (int)($_GET['page'] ?? 1));

require_once __DIR__ . '/includes/head.php';
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Page Hero -->
<section class="relative pt-40 pb-16 overflow-hidden bg-gradient-to-br from-secondary via-secondary to-secondary/90">
    <div class="absolute rounded-full -top-24 -right-24 w-96 h-96 bg-primary/20 blur-3xl"></div>
    <div class="absolute rounded-full -bottom-32 -left-24 w-80 h-80 bg-primary/15 blur-3xl"></div>
    <div class="relative z-10 px-4 mx-auto text-center max-w-7xl">
        <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">Artisti</h1>
        <p class="max-w-xl mx-auto text-lg text-white/70">Descopera artistii tai preferati si nu rata urmatoarele lor concerte si evenimente in Romania.</p>
    </div>
</section>

<!-- Search Section -->
<section class="px-4 -mt-7">
    <div class="max-w-2xl mx-auto">
        <div class="relative z-10 flex items-center gap-2 p-2 bg-white shadow-xl rounded-2xl">
            <input type="text" id="artistSearch" placeholder="Cauta un artist..." class="flex-1 px-5 py-4 text-base border-0 outline-none text-secondary placeholder-muted">
            <button onclick="ArtistsPage.search()" class="flex items-center gap-2 px-6 py-4 font-semibold text-white transition-all btn-primary rounded-xl hover:shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35"/>
                </svg>
                Cauta
            </button>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="px-4 py-12 mx-auto max-w-7xl">

    <!-- Featured Artists -->
    <section class="mb-16" id="featuredSection">
        <div class="flex items-center justify-between mb-6">
            <h2 class="flex items-center gap-3 text-2xl font-bold text-secondary">
                <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                Artisti populari
            </h2>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4" id="featuredGrid">
            <!-- Loading skeletons -->
            <div class="relative overflow-hidden rounded-2xl aspect-[3/4]">
                <div class="w-full h-full skeleton"></div>
            </div>
            <div class="relative overflow-hidden rounded-2xl aspect-[3/4]">
                <div class="w-full h-full skeleton"></div>
            </div>
            <div class="relative overflow-hidden rounded-2xl aspect-[3/4]">
                <div class="w-full h-full skeleton"></div>
            </div>
            <div class="relative overflow-hidden rounded-2xl aspect-[3/4]">
                <div class="w-full h-full skeleton"></div>
            </div>
        </div>
    </section>

    <!-- Trending Section -->
    <section class="p-8 mb-12 bg-white border mobile:p-4 rounded-3xl border-border" id="trendingSection">
        <div class="flex items-center gap-4 mb-6">
            <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-secondary">In trend saptamana aceasta</h2>
                <p class="text-sm text-muted">Artistii cu cele mai multe vanzari de bilete</p>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2" id="trendingList">
            <!-- Loading skeletons -->
            <div class="flex items-center gap-4 p-4 rounded-2xl bg-surface">
                <div class="w-8 h-8 rounded-lg skeleton"></div>
                <div class="w-14 h-14 rounded-xl skeleton"></div>
                <div class="flex-1">
                    <div class="w-32 mb-2 skeleton skeleton-text"></div>
                    <div class="w-48 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="flex items-center gap-4 p-4 rounded-2xl bg-surface">
                <div class="w-8 h-8 rounded-lg skeleton"></div>
                <div class="w-14 h-14 rounded-xl skeleton"></div>
                <div class="flex-1">
                    <div class="w-32 mb-2 skeleton skeleton-text"></div>
                    <div class="w-48 skeleton skeleton-text"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Genre Tabs -->
    <div class="flex flex-wrap gap-2 mb-8" id="genreTabs">
        <button class="genre-tab active flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= empty($genreFilter) ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Toti
            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= empty($genreFilter) ? 'bg-white/20' : 'bg-black/5' ?>" id="countAll">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'pop' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="pop">
            Pop <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'pop' ? 'bg-white/20' : 'bg-black/5' ?>" id="countPop">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'rock' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="rock">
            Rock <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'rock' ? 'bg-white/20' : 'bg-black/5' ?>" id="countRock">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'hip-hop' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="hip-hop">
            Hip-Hop <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'hip-hop' ? 'bg-white/20' : 'bg-black/5' ?>" id="countHipHop">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'electronic' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="electronic">
            Electronic <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'electronic' ? 'bg-white/20' : 'bg-black/5' ?>" id="countElectronic">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'stand-up' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="stand-up">
            Stand-up <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'stand-up' ? 'bg-white/20' : 'bg-black/5' ?>" id="countStandup">--</span>
        </button>
        <button class="genre-tab flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-full transition-all <?= $genreFilter === 'dj' ? 'bg-primary text-white' : 'bg-white border border-border text-muted hover:text-secondary' ?>" data-genre="dj">
            DJ <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $genreFilter === 'dj' ? 'bg-white/20' : 'bg-black/5' ?>" id="countDJ">--</span>
        </button>
    </div>

    <!-- Alphabet Navigation -->
    <section class="mb-8">
        <div class="flex flex-wrap justify-between gap-1.5 p-4 bg-white border rounded-2xl border-border" id="alphabetNav">
            <?php
            $letters = array_merge(range('A', 'Z'));
            foreach ($letters as $letter):
                $isActive = $letterFilter === $letter;
            ?>
            <a href="?letter=<?= $letter ?><?= $genreFilter ? '&genre=' . $genreFilter : '' ?>"
               class="flex items-center justify-center w-9 h-9 text-sm font-semibold rounded-lg transition-all alphabet-link <?= $isActive ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-border hover:text-secondary' ?>"
               data-letter="<?= $letter ?>">
                <?= $letter ?>
            </a>
            <?php endforeach; ?>
            <a href="?<?= $genreFilter ? 'genre=' . $genreFilter : '' ?>"
               class="flex items-center justify-center px-4 h-9 text-sm font-semibold rounded-lg transition-all <?= empty($letterFilter) ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-border hover:text-secondary' ?>">
                Toate
            </a>
        </div>
    </section>

    <!-- Results Info -->
    <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
        <span class="text-sm text-muted">Se afiseaza <strong class="text-secondary" id="resultsCount">--</strong> artisti</span>
        <select id="sortSelect" class="px-4 py-3 pr-10 text-sm font-medium bg-white border appearance-none cursor-pointer rounded-xl border-border text-secondary focus:outline-none focus:ring-2 focus:ring-primary/20" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2716%27 height=%2716%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%2394A3B8%27 stroke-width=%272%27%3E%3Cpath d=%27M6 9l6 6 6-6%27/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.75rem center;">
            <option value="popular" <?= $sortBy === 'popular' ? 'selected' : '' ?>>Cei mai populari</option>
            <option value="events" <?= $sortBy === 'events' ? 'selected' : '' ?>>Dupa evenimente</option>
            <option value="followers" <?= $sortBy === 'followers' ? 'selected' : '' ?>>Dupa urmaritori</option>
            <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Alfabetic</option>
            <option value="recent" <?= $sortBy === 'recent' ? 'selected' : '' ?>>Adaugati recent</option>
        </select>
    </div>

    <!-- Artists Grid -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="artistsGrid">
        <!-- Loading skeletons -->
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="overflow-hidden bg-white border rounded-2xl border-border">
            <div class="aspect-square skeleton"></div>
            <div class="p-5 text-center">
                <div class="w-32 mx-auto mb-2 skeleton skeleton-title"></div>
                <div class="w-24 mx-auto mb-3 skeleton skeleton-text"></div>
                <div class="h-8 mx-auto rounded-lg w-28 skeleton"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-2 mt-12" id="pagination"></div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Build initial filters for JavaScript
$initialFilters = json_encode([
    'genre' => $genreFilter,
    'letter' => $letterFilter,
    'sort' => $sortBy,
    'page' => $page,
    'search' => ''
]);

// Load external artists page controller
$scriptsExtra = '<script src="' . asset('assets/js/pages/artists-page.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => ArtistsPage.init(' . $initialFilters . '));</script>';

require_once __DIR__ . '/includes/scripts.php';
