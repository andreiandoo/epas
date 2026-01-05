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
    <section class="p-8 mb-12 bg-white border rounded-3xl border-border" id="trendingSection">
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
        <div class="flex flex-wrap gap-1.5 p-4 bg-white border rounded-2xl border-border" id="alphabetNav">
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
        <select id="sortSelect" class="px-4 py-3 pr-10 text-sm font-medium bg-white border rounded-xl border-border text-secondary focus:outline-none focus:ring-2 focus:ring-primary/20" style="background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\"); background-repeat: no-repeat; background-position: right 12px center;">
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
$scriptsExtra = <<<SCRIPTS
<script>
const ArtistsPage = {
    filters: {
        genre: '$genreFilter',
        letter: '$letterFilter',
        sort: '$sortBy',
        page: $page,
        search: ''
    },
    totalArtists: 0,

    async init() {
        await Promise.all([
            this.loadFeaturedArtists(),
            this.loadTrendingArtists(),
            this.loadArtists(),
            this.loadGenreCounts()
        ]);

        this.bindEvents();
    },

    async loadFeaturedArtists() {
        const container = document.getElementById('featuredGrid');
        try {
            const response = await AmbiletAPI.get('/artists/featured?limit=4');
            const artists = response.data?.artists || response.data || [];
            if (artists.length > 0) {
                container.innerHTML = artists.map(artist => this.renderFeaturedCard(artist)).join('');
            }
        } catch (e) {
            console.warn('Failed to load featured artists:', e);
            container.innerHTML = '<p class="col-span-4 py-8 text-center text-muted">Nu am putut incarca artistii populari</p>';
        }
    },

    async loadTrendingArtists() {
        const container = document.getElementById('trendingList');
        try {
            const response = await AmbiletAPI.get('/artists/trending?limit=4');
            const artists = response.data?.artists || response.data || [];
            if (artists.length > 0) {
                container.innerHTML = artists.map((artist, index) => this.renderTrendingItem(artist, index + 1)).join('');
            }
        } catch (e) {
            console.warn('Failed to load trending artists:', e);
        }
    },

    async loadArtists() {
        const container = document.getElementById('artistsGrid');

        try {
            const params = new URLSearchParams({
                page: this.filters.page,
                per_page: 12,
                sort: this.filters.sort
            });

            if (this.filters.genre) params.append('genre', this.filters.genre);
            if (this.filters.letter) params.append('letter', this.filters.letter);
            if (this.filters.search) params.append('search', this.filters.search);

            const response = await AmbiletAPI.get('/artists?' + params.toString());
            if (response.data) {
                const artists = response.data;
                const meta = response.meta || {};
                this.totalArtists = meta.total || artists.length;

                document.getElementById('resultsCount').textContent = this.totalArtists;

                if (artists.length > 0) {
                    container.innerHTML = artists.map(artist => this.renderArtistCard(artist)).join('');
                } else {
                    container.innerHTML = '<div class="py-16 text-center col-span-full"><svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg><h3 class="mb-2 text-lg font-semibold text-secondary">Nu am gasit artisti</h3><p class="text-muted">Incearca sa modifici filtrele sau sa cauti altceva</p></div>';
                }

                this.renderPagination(meta);
            }
        } catch (e) {
            console.error('Failed to load artists:', e);
            container.innerHTML = '<p class="py-8 text-center col-span-full text-error">Eroare la incarcarea artistilor</p>';
        }
    },

    async loadGenreCounts() {
        try {
            const response = await AmbiletAPI.get('/artists/genre-counts');
            const genres = response.data?.genres || [];

            // Calculate total
            let total = 0;
            const genreMap = {};
            genres.forEach(g => {
                genreMap[g.slug] = g.count;
                total += g.count;
            });

            document.getElementById('countAll').textContent = total || '--';
            document.getElementById('countPop').textContent = genreMap['pop'] || '--';
            document.getElementById('countRock').textContent = genreMap['rock'] || '--';
            document.getElementById('countHipHop').textContent = genreMap['hip-hop'] || '--';
            document.getElementById('countElectronic').textContent = genreMap['electronic'] || '--';
            document.getElementById('countStandup').textContent = genreMap['stand-up'] || '--';
            document.getElementById('countDJ').textContent = genreMap['dj'] || '--';
        } catch (e) {
            console.warn('Failed to load genre counts:', e);
        }
    },

    renderFeaturedCard(artist) {
        const verifiedBadge = artist.is_verified ? '<span class="inline-flex items-center justify-center w-5 h-5 ml-1 bg-blue-500 rounded-full"><svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span>' : '';
        const genre = artist.genres?.[0]?.name || 'Artist';
        const totalFollowers = (api.stats?.spotify_listeners || 0) +
                                (api.stats?.instagram_followers || 0) +
                              (api.stats?.facebook_followers || 0) +
                              (api.stats?.youtube_subscribers || 0) +
                              (api.stats?.tiktok_followers || 0);
        const followers = this.formatFollowers(totalFollowers);
        const eventsCount = artist.upcoming_events_count || 0;
        // Use portrait > logo > main image fallback
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/placeholder-artist.jpg';

        return '<a href="/artist/' + artist.slug + '" class="relative overflow-hidden group rounded-2xl aspect-[3/4]">' +
            '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110" loading="lazy">' +
            '<div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>' +
            '<div class="absolute bottom-0 left-0 right-0 p-6">' +
                '<span class="inline-block px-3 py-1 mb-3 text-xs font-semibold text-white uppercase rounded-full bg-white/15 backdrop-blur-sm">' + genre + '</span>' +
                '<h3 class="mb-2 text-xl font-extrabold leading-tight text-white">' + artist.name + verifiedBadge + '</h3>' +
                '<div class="flex items-center gap-4 text-sm text-white/80">' +
                    '<span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' + eventsCount + ' evenimente</span>' +
                    '<span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' + followers + '</span>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    renderTrendingItem(artist, rank) {
        const rankClass = rank === 1 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' :
                         rank === 2 ? 'bg-gradient-to-br from-gray-400 to-gray-500' :
                         rank === 3 ? 'bg-gradient-to-br from-amber-600 to-amber-700' : 'bg-secondary';

        const verifiedBadge = artist.is_verified ? '<span class="inline-flex items-center justify-center w-4 h-4 ml-1 bg-blue-500 rounded-full"><svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span>' : '';

        const changeClass = artist.change >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600';
        const changeIcon = artist.change >= 0 ?
            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>' :
            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';

        const changeText = (artist.change >= 0 ? '+' : '') + (artist.change || 0) + '%';
        // Use portrait > logo > main image fallback
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/placeholder-artist.jpg';

        return '<a href="/artist/' + artist.slug + '" class="flex items-center gap-4 p-4 transition-colors rounded-2xl bg-surface hover:bg-border/50">' +
            '<div class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold text-white rounded-lg ' + rankClass + '">' + rank + '</div>' +
            '<div class="flex-shrink-0 overflow-hidden w-14 h-14 rounded-xl">' +
                '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full h-full">' +
            '</div>' +
            '<div class="flex-1 min-w-0">' +
                '<div class="flex items-center text-sm font-bold text-secondary">' + artist.name + verifiedBadge + '</div>' +
                '<div class="text-xs text-muted">' + (artist.tickets_sold || 0) + ' bilete vandute saptamana aceasta</div>' +
            '</div>' +
            '<div class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md ' + changeClass + '">' +
                changeIcon + changeText +
            '</div>' +
        '</a>';
    },

    renderArtistCard(artist) {
        const verifiedBadge = artist.is_verified ? '<span class="inline-flex items-center justify-center w-4 h-4 ml-1 bg-blue-500 rounded-full"><svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span>' : '';
        const genre = artist.genres?.[0]?.name || 'Artist';
        const totalFollowers = (api.stats?.spotify_listeners || 0) +
                                (api.stats?.instagram_followers || 0) +
                              (api.stats?.facebook_followers || 0) +
                              (api.stats?.youtube_subscribers || 0) +
                              (api.stats?.tiktok_followers || 0);
        const followers = this.formatFollowers(totalFollowers);
        const eventsCount = artist.upcoming_events_count || 0;
        // Use portrait > logo > main image fallback
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/placeholder-artist.jpg';

        const eventsInfo = eventsCount > 0 ?
            '<span class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-primary/10 text-primary">' +
                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                eventsCount + ' evenimente viitoare' +
            '</span>' :
            '<span class="text-xs text-muted">Fara evenimente programate</span>';

        const eventsBadge = eventsCount > 0 ?
            '<span class="absolute px-3 py-1.5 text-xs font-semibold text-white rounded-lg bottom-3 right-3 bg-primary">' + eventsCount + ' evenimente</span>' : '';

        return '<a href="/artist/' + artist.slug + '" class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary">' +
            '<div class="relative overflow-hidden aspect-square">' +
                '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" loading="lazy">' +
                '<span class="absolute px-3 py-1.5 text-xs font-semibold text-white uppercase rounded-full top-3 left-3 bg-black/60 backdrop-blur-sm">' + genre + '</span>' +
                eventsBadge +
            '</div>' +
            '<div class="p-5 text-center">' +
                '<h3 class="flex items-center justify-center mb-1 text-base font-bold text-secondary">' + artist.name + verifiedBadge + '</h3>' +
                '<p class="mb-3 text-sm text-muted">' + followers + ' urmaritori</p>' +
                eventsInfo +
            '</div>' +
        '</a>';
    },

    renderPagination(meta) {
        const container = document.getElementById('pagination');
        if (!meta || meta.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        if (meta.current_page > 1) {
            html += '<button onclick="ArtistsPage.goToPage(' + (meta.current_page - 1) + ')" class="flex items-center justify-center w-10 h-10 transition-colors bg-white border rounded-xl border-border hover:bg-surface"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>';
        }

        // Page numbers
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += '<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary">' + i + '</button>';
            } else if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 2) {
                html += '<button onclick="ArtistsPage.goToPage(' + i + ')" class="w-10 h-10 font-medium transition-colors bg-white border rounded-xl border-border hover:bg-surface">' + i + '</button>';
            } else if (Math.abs(i - meta.current_page) === 3) {
                html += '<span class="px-2 text-muted">...</span>';
            }
        }

        // Next button
        if (meta.current_page < meta.last_page) {
            html += '<button onclick="ArtistsPage.goToPage(' + (meta.current_page + 1) + ')" class="flex items-center justify-center w-10 h-10 transition-colors bg-white border rounded-xl border-border hover:bg-surface"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>';
        }

        container.innerHTML = html;
    },

    goToPage(page) {
        this.filters.page = page;
        this.loadArtists();
        window.scrollTo({ top: 400, behavior: 'smooth' });
    },

    search() {
        const query = document.getElementById('artistSearch').value.trim();
        if (query.length >= 2) {
            this.filters.search = query;
            this.filters.page = 1;
            this.loadArtists();
        }
    },

    subscribeNewsletter(event) {
        event.preventDefault();
        const email = document.getElementById('newsletterEmail').value;
        if (email) {
            AmbiletNotifications.success('Te-ai abonat cu succes!');
            document.getElementById('newsletterEmail').value = '';
        }
        return false;
    },

    bindEvents() {
        // Genre tabs
        document.querySelectorAll('.genre-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const genre = e.currentTarget.dataset.genre;
                this.filters.genre = genre;
                this.filters.page = 1;

                // Update active state
                document.querySelectorAll('.genre-tab').forEach(t => {
                    t.classList.remove('bg-primary', 'text-white');
                    t.classList.add('bg-white', 'border', 'border-border', 'text-muted');
                });
                e.currentTarget.classList.remove('bg-white', 'border', 'border-border', 'text-muted');
                e.currentTarget.classList.add('bg-primary', 'text-white');

                this.loadArtists();
            });
        });

        // Sort select
        document.getElementById('sortSelect')?.addEventListener('change', (e) => {
            this.filters.sort = e.target.value;
            this.filters.page = 1;
            this.loadArtists();
        });

        // Search on enter
        document.getElementById('artistSearch')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.search();
            }
        });

        // Alphabet links - prevent default and use JS
        document.querySelectorAll('.alphabet-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const letter = e.currentTarget.dataset.letter;
                this.filters.letter = letter || '';
                this.filters.page = 1;

                // Update active state
                document.querySelectorAll('.alphabet-link').forEach(l => {
                    l.classList.remove('bg-primary', 'text-white');
                    l.classList.add('bg-surface', 'text-muted');
                });
                e.currentTarget.classList.remove('bg-surface', 'text-muted');
                e.currentTarget.classList.add('bg-primary', 'text-white');

                this.loadArtists();
            });
        });
    },

    formatFollowers(count) {
        if (!count || count === 0) return '0';
        if (count >= 1000000) {
            return (count / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (count >= 1000) {
            return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return count.toString();
    }
};

document.addEventListener('DOMContentLoaded', () => ArtistsPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
