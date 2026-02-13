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
$page = max(1, intval($_GET['pagina'] ?? 1));

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

// Demo trending artists
$trendingArtists = [
    [
        'id' => 1,
        'name' => "Carla's Dreams",
        'slug' => 'carlas-dreams',
        'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=500&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '312K',
        'eventsCount' => 5,
        'isVerified' => true,
        'trendRank' => 1,
        'badge' => 'trending',
    ],
    [
        'id' => 2,
        'name' => 'Subcarpați',
        'slug' => 'subcarpati',
        'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=500&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=80&h=80&fit=crop',
        'genres' => ['Hip-Hop', 'Folk'],
        'followers' => '189K',
        'eventsCount' => 3,
        'isVerified' => true,
        'trendRank' => 2,
        'badge' => 'trending',
    ],
    [
        'id' => 3,
        'name' => 'The Motans',
        'slug' => 'the-motans',
        'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=500&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '245K',
        'eventsCount' => 2,
        'isVerified' => false,
        'trendRank' => null,
        'badge' => 'new',
    ],
    [
        'id' => 4,
        'name' => 'Irina Rimes',
        'slug' => 'irina-rimes',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=500&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '278K',
        'eventsCount' => 4,
        'isVerified' => true,
        'trendRank' => 4,
        'badge' => 'trending',
        'isFollowing' => true,
    ],
];

// Demo all artists
$allArtists = [
    [
        'id' => 1,
        'name' => "Carla's Dreams",
        'slug' => 'carlas-dreams',
        'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=80&h=80&fit=crop',
        'genres' => ['Pop', 'Românesc'],
        'followers' => '312K',
        'eventsCount' => 5,
        'isVerified' => true,
    ],
    [
        'id' => 2,
        'name' => 'Subcarpați',
        'slug' => 'subcarpati',
        'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=80&h=80&fit=crop',
        'genres' => ['Hip-Hop', 'Folk'],
        'followers' => '189K',
        'eventsCount' => 3,
        'isVerified' => true,
    ],
    [
        'id' => 3,
        'name' => 'The Motans',
        'slug' => 'the-motans',
        'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '245K',
        'eventsCount' => 2,
        'isVerified' => false,
    ],
    [
        'id' => 4,
        'name' => 'Irina Rimes',
        'slug' => 'irina-rimes',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '278K',
        'eventsCount' => 4,
        'isVerified' => true,
        'isFollowing' => true,
    ],
    [
        'id' => 5,
        'name' => 'INNA',
        'slug' => 'inna',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=80&h=80&fit=crop',
        'genres' => ['Pop', 'Dance'],
        'followers' => '512K',
        'eventsCount' => 3,
        'isVerified' => true,
    ],
    [
        'id' => 6,
        'name' => 'Smiley',
        'slug' => 'smiley',
        'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '198K',
        'eventsCount' => 3,
        'isVerified' => true,
    ],
    [
        'id' => 7,
        'name' => 'Vita de Vie',
        'slug' => 'vita-de-vie',
        'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=80&h=80&fit=crop',
        'genres' => ['Rock', 'Alternativ'],
        'followers' => '98K',
        'eventsCount' => 2,
        'isVerified' => false,
    ],
    [
        'id' => 8,
        'name' => 'Luiza Zan',
        'slug' => 'luiza-zan',
        'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=80&h=80&fit=crop',
        'genres' => ['Jazz'],
        'followers' => '42K',
        'eventsCount' => 3,
        'isVerified' => false,
    ],
    [
        'id' => 9,
        'name' => 'Delia',
        'slug' => 'delia',
        'image' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=80&h=80&fit=crop',
        'genres' => ['Pop'],
        'followers' => '356K',
        'eventsCount' => 6,
        'isVerified' => true,
    ],
    [
        'id' => 10,
        'name' => 'Grasu XXL',
        'slug' => 'grasu-xxl',
        'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=80&h=80&fit=crop',
        'genres' => ['Hip-Hop'],
        'followers' => '134K',
        'eventsCount' => 1,
        'isVerified' => false,
    ],
];

// Pagination
$totalArtists = 280;
$perPage = 10;
$totalPages = ceil($totalArtists / $perPage);

// Page settings
$pageTitle = 'Artiști';
$pageDescription = 'Descoperă artiștii preferați și cumpără bilete la concertele lor pe TICS.ro';
$bodyClass = 'bg-gray-50';
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
        <select class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900/10">
            <option value="popularity" <?= $sortBy === 'popularity' ? 'selected' : '' ?>>Popularitate</option>
            <option value="a-z" <?= $sortBy === 'a-z' ? 'selected' : '' ?>>A → Z</option>
            <option value="z-a" <?= $sortBy === 'z-a' ? 'selected' : '' ?>>Z → A</option>
            <option value="events" <?= $sortBy === 'events' ? 'selected' : '' ?>>Cele mai multe ev.</option>
            <option value="new" <?= $sortBy === 'new' ? 'selected' : '' ?>>Adăugat recent</option>
            <option value="trending" <?= $sortBy === 'trending' ? 'selected' : '' ?>>În trend</option>
        </select>
    </div>

    <!-- Featured Artists - Horizontal Scroll -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">🔥 Artiști în trend</h2>
                <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
            </div>
            <a href="/artisti?sort=trending" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toți →</a>
        </div>
        <div class="flex gap-4 pb-2 overflow-x-auto no-scrollbar">
            <?php foreach ($trendingArtists as $artist): ?>
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

    <!-- All Artists Grid -->
    <h2 class="mb-4 text-lg font-semibold text-gray-900">Toți artiștii</h2>
    <div class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" id="artistsGrid">
        <?php foreach ($allArtists as $artist): ?>
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

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-2 mt-10">
        <?php if ($page <= 1): ?>
        <button class="flex items-center justify-center w-10 h-10 text-gray-400 border border-gray-200 rounded-full cursor-not-allowed">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <?php else: ?>
        <a href="?pagina=<?= $page - 1 ?>" class="flex items-center justify-center w-10 h-10 text-gray-600 transition-colors border border-gray-200 rounded-full hover:border-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <?php endif; ?>

        <?php
        $showPages = [];
        if ($totalPages <= 5) {
            $showPages = range(1, $totalPages);
        } else {
            if ($page <= 3) {
                $showPages = [1, 2, 3, '...', $totalPages];
            } elseif ($page >= $totalPages - 2) {
                $showPages = [1, '...', $totalPages - 2, $totalPages - 1, $totalPages];
            } else {
                $showPages = [1, '...', $page - 1, $page, $page + 1, '...', $totalPages];
            }
        }
        foreach ($showPages as $p):
            if ($p === '...'):
        ?>
        <span class="text-gray-400">...</span>
        <?php else: ?>
        <a href="?pagina=<?= $p ?>" class="w-10 h-10 rounded-full <?= $p === $page ? 'bg-gray-900 text-white' : 'border border-gray-200 text-gray-600 hover:border-gray-300' ?> text-sm font-medium flex items-center justify-center transition-colors"><?= $p ?></a>
        <?php endif; endforeach; ?>

        <?php if ($page >= $totalPages): ?>
        <button class="flex items-center justify-center w-10 h-10 text-gray-400 border border-gray-200 rounded-full cursor-not-allowed">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <?php else: ?>
        <a href="?pagina=<?= $page + 1 ?>" class="flex items-center justify-center w-10 h-10 text-gray-600 transition-colors border border-gray-200 rounded-full hover:border-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        <?php endif; ?>
    </div>
</main>

<script>
// Letter filter
function filterByLetter(btn, letter) {
    document.querySelectorAll('.letter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('#artistsGrid > .artist-card');
    let count = 0;

    cards.forEach(card => {
        if (letter === 'all') {
            card.style.display = '';
            count++;
            return;
        }

        const name = card.dataset.name || '';
        const firstChar = name.charAt(0).toUpperCase();

        if (letter === '#') {
            const show = /^[^A-ZĂÂÎȘȚa-zăâîșț]/.test(name);
            card.style.display = show ? '' : 'none';
            if (show) count++;
        } else {
            const match = firstChar === letter;
            card.style.display = match ? '' : 'none';
            if (match) count++;
        }
    });
}

// Genre filter chips
document.querySelectorAll('.genre-chip').forEach(chip => {
    chip.addEventListener('click', function() {
        document.querySelectorAll('.genre-chip').forEach(c => c.classList.remove('chip-active'));
        this.classList.add('chip-active');
        // In production, this would filter or navigate
    });
});

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
