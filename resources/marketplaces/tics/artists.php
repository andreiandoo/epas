<?php
/**
 * TICS.ro - Artists Listing Page
 * Displays all artists with filtering by genre, letter, and search
 */

require_once __DIR__ . '/includes/config.php';

// Get filter parameters
$selectedGenre = $_GET['gen'] ?? 'all';
$selectedLetter = $_GET['litera'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'popularity';
$page = 1; // Always start at page 1, load-more handles subsequent pages

// Genre categories
$genres = [
    ['id' => 'all', 'name' => 'Toți', 'icon' => ''],
    ['id' => 'pop', 'name' => 'Pop', 'icon' => '🎤'],
    ['id' => 'rock', 'name' => 'Rock', 'icon' => '🎸'],
    ['id' => 'electronica', 'name' => 'Electronică', 'icon' => '🎧'],
    ['id' => 'hip-hop', 'name' => 'Hip-Hop', 'icon' => '🎵'],
    ['id' => 'stand-up', 'name' => 'Stand-up', 'icon' => '😂'],
    ['id' => 'teatru', 'name' => 'Teatru', 'icon' => '🎭'],
    ['id' => 'jazz', 'name' => 'Jazz & Blues', 'icon' => '🎷'],
    ['id' => 'clasica', 'name' => 'Clasică', 'icon' => '🎻'],
    ['id' => 'folk', 'name' => 'Folk / Etno', 'icon' => '🪗'],
];

// Alphabet for filtering
$alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'Ș', 'T', 'Ț', 'U', 'V', 'W', 'X', 'Y', 'Z'];

// Fetch artists from real API
$apiParams = [
    'page'     => 1,
    'per_page' => 24,
    'sort'     => $sortBy,
    'genre'    => $selectedGenre !== 'all' ? $selectedGenre : null,
    'letter'   => $selectedLetter !== 'all' ? $selectedLetter : null,
];
$apiResponse  = callApi('artists', $apiParams);
$allArtists   = $apiResponse['data'] ?? [];
$totalArtists = $apiResponse['meta']['total'] ?? count($allArtists);
$lastPage     = $apiResponse['meta']['last_page'] ?? 1;
$hasMore      = $lastPage > 1;

// Trending artists: first 4 from the API result
$trendingArtists = array_slice($allArtists, 0, 4);

// Helper: map API artist to template-compatible array
function mapArtistForDisplay($a) {
    $genreNames = array_map(fn($g) => $g['name'], $a['genres'] ?? []);
    $totalFollowers = ($a['stats']['instagram_followers'] ?? 0)
                    + ($a['stats']['facebook_followers'] ?? 0);
    return [
        'id'          => $a['id'],
        'name'        => $a['name'],
        'slug'        => $a['slug'],
        'image'       => getStorageUrl($a['image'] ?? ''),
        'avatar'      => getStorageUrl($a['logo'] ?? $a['image'] ?? ''),
        'genres'      => $genreNames,
        'followers'   => formatFollowers($totalFollowers),
        'eventsCount' => $a['stats']['upcoming_events'] ?? 0,
        'isVerified'  => $a['is_verified'] ?? false,
        'trendRank'   => null,
        'badge'       => null,
        'isFollowing' => false,
    ];
}

$trendingArtistsMapped = array_map('mapArtistForDisplay', $trendingArtists);
$allArtistsMapped      = array_map('mapArtistForDisplay', $allArtists);

// Page settings
$pageTitle       = 'Artiști';
$pageDescription = 'Descoperă artiștii preferați și cumpără bilete la concertele lor pe TICS.ro';
$bodyClass       = 'bg-gray-50';
$transparentHeader = false;

// Set login state
setLoginState($isLoggedIn, $loggedInUser);

// Include head
include __DIR__ . '/includes/head.php';

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Genre Categories Bar -->
<div class="sticky z-30 bg-white border-b border-gray-200 top-16">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
            <?php foreach ($genres as $genre): ?>
            <button class="genre-chip <?= $selectedGenre === $genre['id'] ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium <?= $selectedGenre !== $genre['id'] ? 'text-gray-600' : '' ?> whitespace-nowrap hover:border-gray-300 transition-colors" data-genre="<?= e($genre['id']) ?>">
                <?= $genre['icon'] ? $genre['icon'] . ' ' : '' ?><?= e($genre['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<main class="max-w-[1600px] mx-auto px-4 lg:px-8 py-6">

    <!-- Alphabet Letter Filter -->
    <div class="p-3 mb-6 bg-white border border-gray-200 rounded-2xl">
        <div class="flex flex-wrap items-center justify-center gap-1">
            <button class="letter-btn <?= $selectedLetter === 'all' ? 'active' : '' ?> w-10 h-9 rounded-lg text-xs font-semibold flex items-center justify-center" onclick="filterByLetter(this,'all')">Toți</button>
            <div class="w-px h-6 bg-gray-200 mx-0.5"></div>
            <?php foreach ($alphabet as $letter): ?>
            <button class="letter-btn <?= $selectedLetter === $letter ? 'active' : '' ?> w-8 h-9 rounded-lg text-sm font-medium text-gray-600 flex items-center justify-center" onclick="filterByLetter(this,'<?= e($letter) ?>')"><?= e($letter) ?></button>
            <?php endforeach; ?>
            <div class="w-px h-6 bg-gray-200 mx-0.5"></div>
            <button class="letter-btn <?= $selectedLetter === '#' ? 'active' : '' ?> w-8 h-9 rounded-lg text-sm font-medium text-gray-600 flex items-center justify-center" onclick="filterByLetter(this,'#')">#</button>
        </div>
    </div>

    <!-- Results header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">
                <?php if ($selectedGenre !== 'all'): ?>
                    <?php
                    $genreName = '';
                    foreach ($genres as $g) {
                        if ($g['id'] === $selectedGenre) {
                            $genreName = $g['name'];
                            break;
                        }
                    }
                    ?>
                    Artiști <?= e($genreName) ?>
                <?php elseif ($selectedLetter !== 'all'): ?>
                    Artiști cu litera <?= e($selectedLetter) ?>
                <?php else: ?>
                    Toți artiștii
                <?php endif; ?>
            </h1>
            <p class="text-sm text-gray-500 mt-0.5"><?= number_format($totalArtists) ?> artiști găsiți</p>
        </div>
        <select id="sortSelect" class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900/10" onchange="changeSortAndReload(this.value)">
            <option value="popularity" <?= $sortBy === 'popularity' ? 'selected' : '' ?>>Popularitate</option>
            <option value="a-z" <?= $sortBy === 'a-z' ? 'selected' : '' ?>>A → Z</option>
            <option value="z-a" <?= $sortBy === 'z-a' ? 'selected' : '' ?>>Z → A</option>
            <option value="events" <?= $sortBy === 'events' ? 'selected' : '' ?>>Cele mai multe ev.</option>
            <option value="new" <?= $sortBy === 'new' ? 'selected' : '' ?>>Adăugat recent</option>
            <option value="trending" <?= $sortBy === 'trending' ? 'selected' : '' ?>>În trend</option>
        </select>
    </div>

    <!-- Featured Artists - Horizontal Scroll -->
    <?php if (!empty($trendingArtistsMapped)): ?>
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">🔥 Artiști în trend</h2>
                <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
            </div>
            <a href="/artisti?sort=trending" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toți →</a>
        </div>
        <div class="flex gap-4 pb-2 overflow-x-auto no-scrollbar">
            <?php foreach ($trendingArtistsMapped as $artist): ?>
            <a href="/artist/<?= e($artist['slug']) ?>" class="flex-shrink-0 w-64 overflow-hidden bg-white border border-gray-200 artist-card rounded-2xl group">
                <div class="relative h-40 overflow-hidden">
                    <img src="<?= e($artist['image']) ?>" class="absolute inset-0 object-cover w-full h-full artist-img" alt="<?= e($artist['name']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                    <?php if ($artist['badge'] === 'trending' && $artist['trendRank']): ?>
                    <div class="absolute top-3 left-3"><span class="hot-badge px-2.5 py-1 rounded-full text-xs font-semibold text-white">🔥 #<?= e($artist['trendRank']) ?> Trend</span></div>
                    <?php elseif ($artist['badge'] === 'new'): ?>
                    <div class="absolute top-3 left-3"><span class="new-badge px-2.5 py-1 rounded-full text-xs font-semibold text-white">✨ Nou pe TICS</span></div>
                    <?php endif; ?>
                    <div class="absolute left-3 right-3 bottom-3">
                        <div class="flex items-center gap-2">
                            <img src="<?= e($artist['avatar']) ?>" class="object-cover w-10 h-10 border-2 border-white rounded-full" alt="">
                            <div>
                                <div class="flex items-center gap-1">
                                    <h3 class="text-sm font-semibold text-white"><?= e($artist['name']) ?></h3>
                                    <?php if ($artist['isVerified']): ?>
                                    <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-white/80"><?= e(implode(' / ', $artist['genres'])) ?> • <?= e($artist['followers']) ?> urmăritori</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3">
                    <span class="text-xs font-medium text-indigo-600"><?= e($artist['eventsCount']) ?> ev.</span>
                    <?php if (!empty($artist['isFollowing'])): ?>
                    <button class="follow-btn px-3 py-1.5 bg-gray-100 text-gray-900 text-xs font-medium rounded-full hover:bg-gray-200 border border-gray-200">Urmărești ✓</button>
                    <?php else: ?>
                    <button class="follow-btn px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-full hover:bg-gray-800">Urmărește</button>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Artists Grid -->
    <h2 class="mb-4 text-lg font-semibold text-gray-900">Toți artiștii</h2>
    <div class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" id="artistsGrid">
        <?php foreach ($allArtistsMapped as $artist): ?>
        <a href="/artist/<?= e($artist['slug']) ?>" class="overflow-hidden bg-white border border-gray-200 artist-card rounded-2xl group" data-name="<?= e($artist['name']) ?>">
            <div class="relative aspect-[4/3] overflow-hidden">
                <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="absolute inset-0 object-cover w-full h-full artist-img">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                <button class="absolute flex items-center justify-center w-8 h-8 transition-colors rounded-full top-3 right-3 bg-white/90 hover:bg-white" onclick="event.preventDefault(); toggleFavorite(this);">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
                <div class="absolute flex items-center gap-2 bottom-3 left-3">
                    <?php foreach ($artist['genres'] as $genre): ?>
                    <span class="px-2.5 py-1 bg-gray-900/80 backdrop-blur text-white text-xs font-medium rounded-full"><?= e($genre) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <img src="<?= e($artist['avatar']) ?>" class="object-cover w-10 h-10 border-2 border-gray-100 rounded-full" alt="">
                        <div>
                            <div class="flex items-center gap-1">
                                <h3 class="font-semibold text-gray-900 transition-colors group-hover:text-indigo-600"><?= e($artist['name']) ?></h3>
                                <?php if ($artist['isVerified']): ?>
                                <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-500"><?= e($artist['followers']) ?> urmăritori</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3 mt-3 border-t border-gray-100">
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <?= e($artist['eventsCount']) ?> ev.
                        </span>
                    </div>
                    <?php if (!empty($artist['isFollowing'])): ?>
                    <button class="follow-btn px-3 py-1.5 bg-gray-100 text-gray-900 hover:bg-gray-200 border border-gray-200 text-xs font-medium rounded-full">Urmărești ✓</button>
                    <?php else: ?>
                    <button class="follow-btn px-3 py-1.5 bg-gray-900 text-white hover:bg-gray-800 text-xs font-medium rounded-full">Urmărește</button>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Load More Button -->
    <?php if ($hasMore): ?>
    <div class="flex items-center justify-center mt-10" id="loadMoreContainer">
        <button id="loadMoreBtn" onclick="loadMoreArtists()" class="inline-flex items-center gap-2 px-8 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            Mai mulți artiști
        </button>
    </div>
    <?php endif; ?>
</main>

<script>
// Letter filter — navigate via URL
function filterByLetter(btn, letter) {
    document.querySelectorAll('.letter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const url = new URL(window.location.href);
    if (letter === 'all') {
        url.searchParams.delete('litera');
    } else {
        url.searchParams.set('litera', letter);
    }
    window.location.href = url.toString();
}

// Sort select — navigate via URL
function changeSortAndReload(sort) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    window.location.href = url.toString();
}

// Genre filter chips — navigate via URL
document.querySelectorAll('.genre-chip').forEach(chip => {
    chip.addEventListener('click', function() {
        const genre = this.dataset.genre;
        const url = new URL(window.location.href);
        if (genre === 'all') {
            url.searchParams.delete('gen');
        } else {
            url.searchParams.set('gen', genre);
        }
        window.location.href = url.toString();
    });
});

// Load-more state
let _currentPage = 1;
let _isLoading = false;
let _hasMore = <?= json_encode($hasMore) ?>;
const _sortBy = <?= json_encode($sortBy) ?>;
const _genre = <?= json_encode($selectedGenre !== 'all' ? $selectedGenre : '') ?>;
const _letter = <?= json_encode($selectedLetter !== 'all' ? $selectedLetter : '') ?>;
const _storageUrl = <?= json_encode(STORAGE_URL) ?>;

function getStorageUrlJs(path) {
    if (!path) return '/assets/images/placeholder.jpg';
    if (path.startsWith('http')) return path;
    return _storageUrl + '/' + path.replace(/^\//, '');
}

function formatFollowersJs(num) {
    if (!num || num === 0) return '0';
    if (num >= 1000000) return (Math.round(num / 100000) / 10) + 'M';
    if (num >= 1000) return (Math.round(num / 100) / 10) + 'K';
    return num.toString();
}

function createArtistCard(a) {
    const genreNames = (a.genres || []).map(g => g.name);
    const totalFollowers = (a.stats?.instagram_followers || 0) + (a.stats?.facebook_followers || 0);
    const followers = formatFollowersJs(totalFollowers);
    const image = getStorageUrlJs(a.image || '');
    const avatar = getStorageUrlJs(a.logo || a.image || '');
    const eventsCount = a.stats?.upcoming_events || 0;
    const isVerified = a.is_verified || false;

    const genrePills = genreNames.slice(0, 2).map(g =>
        `<span class="px-2.5 py-1 bg-gray-900/80 backdrop-blur text-white text-xs font-medium rounded-full">${g}</span>`
    ).join('');

    const verifiedBadge = isVerified
        ? `<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`
        : '';

    const div = document.createElement('div');
    div.innerHTML = `
        <a href="/artist/${a.slug}" class="overflow-hidden bg-white border border-gray-200 artist-card rounded-2xl group" data-name="${(a.name || '').replace(/"/g, '&quot;')}">
            <div class="relative aspect-[4/3] overflow-hidden">
                <img src="${image}" alt="${(a.name || '').replace(/"/g, '&quot;')}" class="absolute inset-0 object-cover w-full h-full artist-img">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                <button class="absolute flex items-center justify-center w-8 h-8 transition-colors rounded-full top-3 right-3 bg-white/90 hover:bg-white" onclick="event.preventDefault(); toggleFavorite(this);">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
                <div class="absolute flex items-center gap-2 bottom-3 left-3">${genrePills}</div>
            </div>
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <img src="${avatar}" class="object-cover w-10 h-10 border-2 border-gray-100 rounded-full" alt="">
                        <div>
                            <div class="flex items-center gap-1">
                                <h3 class="font-semibold text-gray-900 transition-colors group-hover:text-indigo-600">${a.name || ''}</h3>
                                ${verifiedBadge}
                            </div>
                            <p class="text-xs text-gray-500">${followers} urmăritori</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3 mt-3 border-t border-gray-100">
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            ${eventsCount} ev.
                        </span>
                    </div>
                    <button class="follow-btn px-3 py-1.5 bg-gray-900 text-white hover:bg-gray-800 text-xs font-medium rounded-full" onclick="event.preventDefault(); event.stopPropagation(); this.textContent = this.textContent.includes('Urmărești') ? 'Urmărește' : 'Urmărești ✓';">Urmărește</button>
                </div>
            </div>
        </a>`.trim();
    return div.firstChild;
}

async function loadMoreArtists() {
    if (_isLoading || !_hasMore) return;
    _isLoading = true;

    const btn = document.getElementById('loadMoreBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Se încarcă...';
    }

    _currentPage++;
    const params = new URLSearchParams({ endpoint: 'artists', page: _currentPage, per_page: 24, sort: _sortBy });
    if (_genre) params.set('genre', _genre);
    if (_letter) params.set('letter', _letter);

    try {
        const res = await fetch('/api/proxy.php?' + params.toString());
        const data = await res.json();
        const artists = data.data || [];
        const lastPage = data.meta?.last_page || 1;
        _hasMore = _currentPage < lastPage;

        const grid = document.getElementById('artistsGrid');
        artists.forEach(a => grid.appendChild(createArtistCard(a)));

        if (!_hasMore && btn) {
            document.getElementById('loadMoreContainer')?.remove();
        } else if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg> Mai mulți artiști';
        }
    } catch (e) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg> Mai mulți artiști';
        }
    }

    _isLoading = false;
}

// Letter filter — local filtering (works on already loaded cards)
function filterByLetter(btn, letter) {
    document.querySelectorAll('.letter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const url = new URL(window.location.href);
    if (letter === 'all') {
        url.searchParams.delete('litera');
    } else {
        url.searchParams.set('litera', letter);
    }
    window.location.href = url.toString();
}

// Follow button toggle
document.querySelectorAll('.follow-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (this.textContent.includes('Urmărești')) {
            this.textContent = 'Urmărește';
            this.classList.remove('bg-gray-100', 'text-gray-900', 'border', 'border-gray-200');
            this.classList.add('bg-gray-900', 'text-white');
        } else {
            this.textContent = 'Urmărești ✓';
            this.classList.add('bg-gray-100', 'text-gray-900', 'border', 'border-gray-200');
            this.classList.remove('bg-gray-900', 'text-white');
        }
    });
});

// Favorite toggle
function toggleFavorite(btn) {
    const svg = btn.querySelector('svg');
    const isFilled = svg.getAttribute('fill') === 'currentColor';

    if (isFilled) {
        svg.setAttribute('fill', 'none');
        svg.classList.remove('text-red-500');
        svg.classList.add('text-gray-600');
    } else {
        svg.setAttribute('fill', 'currentColor');
        svg.classList.add('text-red-500');
        svg.classList.remove('text-gray-600');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
