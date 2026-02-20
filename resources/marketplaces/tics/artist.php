<?php
/**
 * TICS.ro - Artist Single Page
 * Displays artist information, upcoming events, videos, and booking info
 */

require_once __DIR__ . '/includes/config.php';

// Get artist slug from query
$artistSlug = $_GET['slug'] ?? null;

// Fetch from real API
$apiData = $artistSlug ? callApi('artists/' . urlencode($artistSlug)) : null;

// Handle both {data: {...}} and direct object response
$apiArtist = null;
if ($apiData) {
    $apiArtist = $apiData['data'] ?? $apiData;
    // If it has typical artist fields, use it; otherwise null
    if (!isset($apiArtist['name'])) {
        $apiArtist = null;
    }
}

// Fallback: empty artist for graceful 404
if (!$apiArtist) {
    $pageTitle       = 'Artist negăsit';
    $pageDescription = 'Artistul căutat nu a fost găsit pe TICS.ro';
    $bodyClass       = 'bg-white';
    $transparentHeader = false;
    $breadcrumbs = [
        ['name' => 'Acasă', 'url' => '/'],
        ['name' => 'Artiști', 'url' => '/artisti'],
        ['name' => 'Negăsit', 'url' => null],
    ];
    setLoginState($isLoggedIn, $loggedInUser);
    include __DIR__ . '/includes/head.php';
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-16 text-center">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Artistul nu a fost găsit</h1>
        <p class="text-gray-500 mb-8">Artistul pe care îl cauți nu există sau a fost eliminat.</p>
        <a href="/artisti" class="px-6 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">Înapoi la artiști</a>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    ?>
    </body>
    </html>
    <?php
    exit;
}

// Map API fields to template variables
$genreNames   = array_map(fn($g) => $g['name'], $apiArtist['genres'] ?? []);
$stats        = $apiArtist['stats'] ?? [];
$social       = $apiArtist['social'] ?? [];
$externalIds  = $apiArtist['external_ids'] ?? [];

$totalSocial = ($stats['instagram_followers'] ?? 0)
             + ($stats['facebook_followers'] ?? 0)
             + ($stats['tiktok_followers'] ?? 0);

// First upcoming event for sidebar
$firstEvent    = $apiArtist['upcoming_events'][0] ?? null;
$nextEventDate = $firstEvent ? formatDate($firstEvent['starts_at'] ?? '') : '';
$nextEventCity = $firstEvent ? ($firstEvent['venue']['city'] ?? '') : '';
$minPrice      = $firstEvent ? ($firstEvent['price_from'] ?? null) : null;

$artist = [
    'id'             => $apiArtist['id'],
    'name'           => $apiArtist['name'],
    'slug'           => $apiArtist['slug'],
    'image'          => getStorageUrl($apiArtist['logo'] ?? $apiArtist['image'] ?? ''),
    'coverImage'     => getStorageUrl($apiArtist['image'] ?? ''),
    'portraitImage'  => getStorageUrl($apiArtist['portrait'] ?? $apiArtist['image'] ?? ''),
    'genres'         => $genreNames,
    'country'        => $apiArtist['country'] ?? '',
    'countryFlag'    => '',
    'city'           => $apiArtist['city'] ?? '',
    'activeSince'    => null,
    'isVerified'     => $apiArtist['is_verified'] ?? false,
    'bio'            => $apiArtist['biography'] ?? '',
    'stats'          => [
        'spotify_monthly'         => formatFollowers($stats['spotify_listeners'] ?? 0),
        'spotify_monthly_full'    => number_format($stats['spotify_listeners'] ?? 0),
        'spotify_popularity'      => $stats['spotify_popularity'] ?? 0,
        'youtube_subscribers'     => formatFollowers($stats['youtube_subscribers'] ?? 0),
        'youtube_subscribers_full'=> number_format($stats['youtube_subscribers'] ?? 0),
        'youtube_views'           => formatFollowers($stats['youtube_total_views'] ?? 0),
        'total_social'            => formatFollowers($totalSocial),
        'instagram'               => formatFollowers($stats['instagram_followers'] ?? 0),
        'facebook'                => formatFollowers($stats['facebook_followers'] ?? 0),
        'tiktok'                  => formatFollowers($stats['tiktok_followers'] ?? 0),
    ],
    'social'         => [
        'instagram' => $social['instagram'] ?? '',
        'youtube'   => $social['youtube'] ?? '',
        'spotify'   => $social['spotify'] ?? '',
        'facebook'  => $social['facebook'] ?? '',
        'tiktok'    => $social['tiktok'] ?? '',
    ],
    'spotifyId'      => $externalIds['spotify_id'] ?? null,
    'nextEvent'      => ['date' => $nextEventDate, 'city' => $nextEventCity],
    'minPrice'       => $minPrice,
    'discography'    => [],
    'awards'         => [],
];

// Map upcoming events
$upcomingEvents = [];
foreach ($apiArtist['upcoming_events'] ?? [] as $ev) {
    $upcomingEvents[] = [
        'id'         => $ev['id'],
        'title'      => $ev['name'] ?? '',
        'slug'       => $ev['slug'] ?? '',
        'image'      => getStorageUrl($ev['cover_image'] ?? $ev['image'] ?? ''),
        'venue'      => $ev['venue']['name'] ?? '',
        'city'       => $ev['venue']['city'] ?? '',
        'date'       => formatDate($ev['starts_at'] ?? ''),
        'time'       => null,
        'category'   => $ev['category']['name'] ?? 'Concert',
        'minPrice'   => $ev['price_from'] ?? 0,
        'soldPercent'=> $ev['sold_percentage'] ?? null,
        'badge'      => ($ev['sold_percentage'] ?? 0) > 60 ? 'trending' : null,
        'isHeadliner'=> false,
    ];
}

// Map videos
$videos = [];
foreach ($apiArtist['youtube_videos'] ?? [] as $i => $vid) {
    $videos[] = [
        'title'     => $vid['title'] ?? '',
        'youtubeId' => $vid['youtube_id'] ?? '',
        'views'     => formatFollowers($vid['views'] ?? 0),
        'isMain'    => ($i === 0),
    ];
}

// Past events — not returned by API directly
$pastEvents = [];

// Similar artists
$similarArtists = [];
foreach ($apiArtist['similar_artists'] ?? [] as $sim) {
    $simGenres = implode(' / ', array_map(fn($g) => $g['name'], $sim['genres'] ?? []));
    $simFollowers = ($sim['stats']['instagram_followers'] ?? 0) + ($sim['stats']['facebook_followers'] ?? 0);
    $similarArtists[] = [
        'name'        => $sim['name'] ?? '',
        'slug'        => $sim['slug'] ?? '',
        'image'       => getStorageUrl($sim['logo'] ?? $sim['image'] ?? ''),
        'genres'      => $simGenres,
        'followers'   => formatFollowers($simFollowers) . ' urmăritori',
        'eventsCount' => $sim['stats']['upcoming_events'] ?? 0,
        'isVerified'  => $sim['is_verified'] ?? false,
    ];
}

// Page settings
$pageTitle       = $artist['name'];
$pageDescription = "Descoperă evenimentele și concertele artistului {$artist['name']}. Cumpără bilete online pe TICS.ro";
$pageImage       = $artist['coverImage'];
$pageType        = 'artist';
$bodyClass       = 'bg-white';
$transparentHeader = false;

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Artiști', 'url' => '/artisti'],
    ['name' => $artist['name'], 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="relative overflow-hidden text-white artist-hero">
    <div class="absolute w-40 h-40 rounded-full top-10 right-20 bg-indigo-500/10 blur-3xl animate-float"></div>
    <div class="absolute rounded-full bottom-10 left-10 w-60 h-60 bg-purple-500/10 blur-3xl animate-float" style="animation-delay:1.5s"></div>
    <div class="px-4 py-10 mx-auto max-w-7xl lg:px-8 lg:py-16">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-8 text-sm text-white/60 animate-fadeInUp">
            <a href="/" class="transition-colors hover:text-white">Acasă</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/artisti" class="transition-colors hover:text-white">Artiști</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white"><?= e($artist['name']) ?></span>
        </div>

        <div class="flex flex-col items-start gap-8 lg:flex-row lg:items-end">
            <!-- Artist Info -->
            <div class="flex items-start flex-1 gap-6 animate-fadeInUp" style="animation-delay:.1s">
                <div class="relative flex-shrink-0">
                    <img src="<?= e($artist['image']) ?>" class="object-cover border-4 shadow-2xl w-28 h-28 lg:w-36 lg:h-36 rounded-2xl border-white/20" alt="<?= e($artist['name']) ?>">
                    <?php if ($artist['isVerified']): ?>
                    <div class="absolute flex items-center justify-center w-8 h-8 bg-blue-500 border-2 rounded-full -bottom-2 -right-2 border-white/30">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <!-- Genres -->
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <?php foreach ($artist['genres'] as $genre): ?>
                        <span class="px-2.5 py-1 bg-white/15 backdrop-blur text-white text-xs font-medium rounded-full"><?= e($genre) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <h1 class="mb-1 text-3xl font-bold lg:text-4xl"><?= e($artist['name']) ?></h1>
                    <p class="flex items-center gap-2 mb-4 text-white/70">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?= e($artist['city']) ?>, <?= e($artist['country']) ?> &bull; Activ din <?= e($artist['activeSince']) ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button class="flex items-center gap-2 px-6 py-2.5 bg-white text-gray-900 font-semibold rounded-full hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            Urmărește
                        </button>
                        <div class="flex items-center gap-2">
                            <?php if ($artist['social']['instagram']): ?>
                            <a href="<?= e($artist['social']['instagram']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center w-10 h-10 border rounded-full social-btn bg-white/15 backdrop-blur hover:bg-white/25 border-white/20" title="Instagram">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($artist['social']['youtube']): ?>
                            <a href="<?= e($artist['social']['youtube']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center w-10 h-10 border rounded-full social-btn bg-white/15 backdrop-blur hover:bg-white/25 border-white/20" title="YouTube">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($artist['social']['spotify']): ?>
                            <a href="<?= e($artist['social']['spotify']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center w-10 h-10 border rounded-full social-btn bg-white/15 backdrop-blur hover:bg-white/25 border-white/20" title="Spotify">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($artist['social']['facebook']): ?>
                            <a href="<?= e($artist['social']['facebook']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center w-10 h-10 border rounded-full social-btn bg-white/15 backdrop-blur hover:bg-white/25 border-white/20" title="Facebook">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($artist['social']['tiktok']): ?>
                            <a href="<?= e($artist['social']['tiktok']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center w-10 h-10 border rounded-full social-btn bg-white/15 backdrop-blur hover:bg-white/25 border-white/20" title="TikTok">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.46V13.2a8.28 8.28 0 005.58 2.17v-3.45a4.85 4.85 0 01-4.83-1.56V6.69h4.83z"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid w-full grid-cols-3 gap-3 lg:w-auto animate-fadeInUp" style="animation-delay:.3s">
                <div class="p-4 text-center border stat-card rounded-2xl border-white/20">
                    <div class="flex items-center justify-center mb-1">
                        <svg class="w-4 h-4 text-[#1DB954]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                    </div>
                    <p class="text-2xl font-bold lg:text-3xl"><?= e($artist['stats']['spotify_monthly']) ?></p>
                    <p class="text-xs text-white/70">Ascultători<br>lunari Spotify</p>
                </div>
                <div class="p-4 text-center border stat-card rounded-2xl border-white/20">
                    <div class="flex items-center justify-center mb-1">
                        <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </div>
                    <p class="text-2xl font-bold lg:text-3xl"><?= e($artist['stats']['youtube_subscribers']) ?></p>
                    <p class="text-xs text-white/70">Abonați<br>YouTube</p>
                </div>
                <div class="p-4 text-center border stat-card rounded-2xl border-white/20">
                    <div class="flex items-center justify-center mb-1">
                        <svg class="w-4 h-4 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </div>
                    <p class="text-2xl font-bold lg:text-3xl"><?= e($artist['stats']['total_social']) ?></p>
                    <p class="text-xs text-white/70">Urmăritori<br>social media</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tabs Navigation -->
<div class="sticky z-30 bg-white border-b border-gray-200 top-16">
    <div class="px-4 mx-auto max-w-7xl lg:px-8">
        <div class="flex items-center gap-8 overflow-x-auto no-scrollbar">
            <button class="py-4 text-sm font-medium text-gray-500 tab-btn active whitespace-nowrap" onclick="switchTab('upcoming')">
                Evenimente viitoare <span class="ml-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full"><?= count($upcomingEvents) ?></span>
            </button>
            <button class="py-4 text-sm font-medium text-gray-500 tab-btn whitespace-nowrap" onclick="switchTab('bio')">Despre</button>
            <button class="py-4 text-sm font-medium text-gray-500 tab-btn whitespace-nowrap" onclick="switchTab('videos')">
                Videoclipuri <span class="ml-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full"><?= count($videos) ?></span>
            </button>
            <button class="py-4 text-sm font-medium text-gray-500 tab-btn whitespace-nowrap" onclick="switchTab('past')">Evenimente trecute</button>
        </div>
    </div>
</div>

<main class="px-4 py-8 mx-auto max-w-7xl lg:px-8">
    <div class="flex flex-col gap-8 lg:flex-row">
        <!-- Main Content -->
        <div class="flex-1 min-w-0">

            <!-- TAB: Upcoming Events -->
            <section id="tab-upcoming" class="tab-content">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">Evenimente viitoare</h2>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <select class="font-medium bg-transparent cursor-pointer">
                            <option>Toate orașele</option>
                            <option>București</option>
                            <option>Cluj-Napoca</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php foreach ($upcomingEvents as $event): ?>
                    <a href="/bilete/<?= e($event['slug']) ?>" class="block overflow-hidden bg-white border border-gray-200 event-card rounded-2xl group">
                        <div class="flex flex-col sm:flex-row">
                            <div class="relative flex-shrink-0 overflow-hidden sm:w-56 aspect-video sm:aspect-auto">
                                <img src="<?= e($event['image']) ?>" class="absolute inset-0 object-cover w-full h-full event-img" alt="<?= e($event['title']) ?>">
                                <?php if ($event['badge'] === 'trending'): ?>
                                <div class="absolute top-3 left-3"><span class="trending-badge px-2.5 py-1 rounded-full text-xs font-semibold text-white">🔥 Popular</span></div>
                                <?php elseif ($event['badge'] === 'early'): ?>
                                <div class="absolute top-3 left-3"><span class="early-badge px-2.5 py-1 rounded-full text-xs font-semibold text-white">🎫 Early Bird</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="px-2 py-0.5 bg-<?= $event['category'] === 'Festival' ? 'purple' : 'indigo' ?>-100 text-<?= $event['category'] === 'Festival' ? 'purple' : 'indigo' ?>-700 text-xs font-medium rounded-full"><?= e($event['category']) ?></span>
                                            <?php if ($event['soldPercent']): ?>
                                            <span class="text-xs font-medium text-red-500">⚡ <?= e($event['soldPercent']) ?>% sold</span>
                                            <?php elseif (!empty($event['isHeadliner'])): ?>
                                            <span class="text-xs font-medium text-gray-400">Headliner</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="mb-1 text-lg font-semibold text-gray-900 transition-colors group-hover:text-indigo-600"><?= e($event['title']) ?></h3>
                                        <p class="mb-2 text-sm text-gray-500"><?= e($event['venue']) ?>, <?= e($event['city']) ?></p>
                                        <div class="flex items-center gap-4 text-sm text-gray-500">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <?= e($event['date']) ?>
                                            </span>
                                            <?php if ($event['time']): ?>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <?= e($event['time']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="text-lg font-bold text-gray-900">de la <?= e($event['minPrice']) ?> RON</p>
                                        <?php if (!empty($event['originalPrice'])): ?>
                                        <p class="text-xs text-gray-400 line-through"><?= e($event['originalPrice']) ?> RON</p>
                                        <?php elseif ($event['category'] === 'Festival'): ?>
                                        <p class="text-xs text-gray-400">Abonament</p>
                                        <?php else: ?>
                                        <p class="text-xs text-gray-400">General Admission</p>
                                        <?php endif; ?>
                                        <button class="px-4 py-2 mt-2 text-sm font-medium text-white transition-colors bg-gray-900 rounded-full hover:bg-gray-800">Cumpără bilete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Notification CTA -->
                <div class="p-5 mt-6 border border-indigo-100 bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 rounded-2xl">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 font-semibold text-gray-900">Nu rata niciun eveniment!</h3>
                            <p class="text-sm text-gray-600">Urmărește-l pe <?= e($artist['name']) ?> și vei fi notificat când apar evenimente noi.</p>
                        </div>
                        <button class="px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 flex-shrink-0 hidden sm:block">Activează notificări</button>
                    </div>
                </div>
            </section>

            <!-- TAB: About -->
            <section id="tab-bio" class="hidden tab-content">
                <h2 class="mb-6 text-xl font-semibold text-gray-900">Despre <?= e($artist['name']) ?></h2>

                <!-- Image Gallery -->
                <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                    <div class="overflow-hidden sm:col-span-2 rounded-2xl">
                        <img src="<?= e($artist['coverImage']) ?>" alt="<?= e($artist['name']) ?> live" class="object-cover w-full h-64 sm:h-72">
                    </div>
                    <div class="overflow-hidden rounded-2xl">
                        <img src="<?= e($artist['portraitImage']) ?>" alt="<?= e($artist['name']) ?> portrait" class="object-cover w-full h-64 sm:h-72">
                    </div>
                </div>

                <!-- Bio -->
                <div class="p-6 mb-6 bg-white border border-gray-200 rounded-2xl">
                    <?php foreach (explode("\n\n", $artist['bio']) as $paragraph): ?>
                    <p class="mb-4 leading-relaxed text-gray-700"><?= nl2br(e($paragraph)) ?></p>
                    <?php endforeach; ?>
                </div>

                <!-- Digital Presence Stats -->
                <div class="p-6 mb-6 bg-white border border-gray-200 rounded-2xl">
                    <h3 class="mb-5 font-semibold text-gray-900">Prezență digitală</h3>

                    <!-- Spotify -->
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-[#1DB954]/10 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#1DB954]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                            </div>
                            <span class="font-semibold text-gray-900">Spotify</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="mb-1 text-xs text-gray-500">Ascultători lunari</p>
                                <p class="text-xl font-bold text-gray-900"><?= e($artist['stats']['spotify_monthly_full']) ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="mb-1 text-xs text-gray-500">Popularitate Spotify</p>
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2.5 spotify-bar-bg rounded-full overflow-hidden">
                                        <div class="h-full rounded-full spotify-bar-fill" style="width:<?= e($artist['stats']['spotify_popularity']) ?>%"></div>
                                    </div>
                                    <span class="text-xl font-bold text-gray-900"><?= e($artist['stats']['spotify_popularity']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- YouTube -->
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-red-50 rounded-xl">
                                <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </div>
                            <span class="font-semibold text-gray-900">YouTube</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="mb-1 text-xs text-gray-500">Abonați</p>
                                <p class="text-xl font-bold text-gray-900"><?= e($artist['stats']['youtube_subscribers_full']) ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="mb-1 text-xs text-gray-500">Vizualizări totale</p>
                                <p class="text-xl font-bold text-gray-900"><?= e($artist['stats']['youtube_views']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Social followers grid -->
                    <p class="mb-3 text-sm font-medium text-gray-500">Social Media</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-4 text-center bg-gray-50 rounded-xl">
                            <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 rounded-lg bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            </div>
                            <p class="font-bold text-gray-900"><?= e($artist['stats']['instagram']) ?></p>
                            <p class="text-xs text-gray-500">Instagram</p>
                        </div>
                        <div class="p-4 text-center bg-gray-50 rounded-xl">
                            <div class="w-8 h-8 mx-auto mb-2 bg-[#1877F2] rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </div>
                            <p class="font-bold text-gray-900"><?= e($artist['stats']['facebook']) ?></p>
                            <p class="text-xs text-gray-500">Facebook</p>
                        </div>
                        <div class="p-4 text-center bg-gray-50 rounded-xl">
                            <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-black rounded-lg">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.46V13.2a8.28 8.28 0 005.58 2.17v-3.45a4.85 4.85 0 01-4.83-1.56V6.69h4.83z"/></svg>
                            </div>
                            <p class="font-bold text-gray-900"><?= e($artist['stats']['tiktok']) ?></p>
                            <p class="text-xs text-gray-500">TikTok</p>
                        </div>
                    </div>
                </div>

                <!-- Discography -->
                <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                    <h3 class="mb-3 font-semibold text-gray-900">Discografie selectivă</h3>
                    <div class="grid gap-3 mb-6 sm:grid-cols-2">
                        <?php foreach ($artist['discography'] as $album): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                            <div class="flex items-center justify-center w-12 h-12 text-lg bg-gray-200 rounded-lg">🎵</div>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?= e($album['title']) ?></p>
                                <p class="text-xs text-gray-500"><?= e($album['year']) ?> &bull; <?= e($album['type']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <h3 class="mb-3 font-semibold text-gray-900">Premii și realizări</h3>
                    <div class="space-y-2">
                        <?php
                        $awardIcons = ['🏆', '🎤', '📀'];
                        foreach ($artist['awards'] as $i => $award):
                        ?>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <span class="text-lg"><?= $awardIcons[$i % count($awardIcons)] ?></span>
                            <span><?= e($award) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- TAB: Videos -->
            <section id="tab-videos" class="hidden tab-content">
                <h2 class="mb-6 text-xl font-semibold text-gray-900">Videoclipuri</h2>
                <?php
                $mainVideo = array_filter($videos, fn($v) => $v['isMain']);
                $mainVideo = reset($mainVideo);
                $otherVideos = array_filter($videos, fn($v) => !$v['isMain']);
                ?>
                <?php if ($mainVideo): ?>
                <div class="mb-4 overflow-hidden border border-gray-200 video-card rounded-2xl">
                    <div class="relative bg-gray-900 aspect-video">
                        <iframe class="absolute inset-0 w-full h-full" src="https://www.youtube.com/embed/<?= e($mainVideo['youtubeId']) ?>" title="<?= e($mainVideo['title']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="p-4">
                        <h3 class="mb-1 font-semibold text-gray-900"><?= e($mainVideo['title']) ?></h3>
                        <p class="text-sm text-gray-500"><?= e($mainVideo['views']) ?> vizualizări &bull; Official Music Video</p>
                    </div>
                </div>
                <?php endif; ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ($otherVideos as $video): ?>
                    <div class="overflow-hidden border border-gray-200 video-card rounded-2xl">
                        <div class="relative bg-gray-900 aspect-video">
                            <iframe class="absolute inset-0 w-full h-full" src="https://www.youtube.com/embed/<?= e($video['youtubeId']) ?>" title="<?= e($video['title']) ?>" frameborder="0" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <div class="p-4">
                            <h3 class="mb-1 text-sm font-medium text-gray-900"><?= e($video['title']) ?></h3>
                            <p class="text-xs text-gray-500"><?= e($video['views']) ?> vizualizări</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- TAB: Past Events -->
            <section id="tab-past" class="hidden tab-content">
                <h2 class="mb-6 text-xl font-semibold text-gray-900">Evenimente trecute</h2>
                <div class="space-y-3">
                    <?php foreach ($pastEvents as $event): ?>
                    <div class="flex items-center gap-4 p-4 bg-white border border-gray-200 event-row rounded-xl">
                        <div class="flex-shrink-0 text-center w-14">
                            <p class="text-xs text-gray-400 uppercase"><?= e($event['date']['month']) ?></p>
                            <p class="text-xl font-bold text-gray-900"><?= e($event['date']['day']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($event['date']['year']) ?></p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-gray-900"><?= e($event['title']) ?></h3>
                            <p class="text-sm text-gray-500"><?= $event['venue'] ? e($event['venue']) . ', ' : '' ?><?= e($event['city']) ?></p>
                        </div>
                        <span class="flex-shrink-0 px-3 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded-full"><?= e($event['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="mt-4 text-sm font-medium text-indigo-600 hover:underline">Arată toate (42 evenimente) &rarr;</button>
            </section>
        </div>

        <!-- SIDEBAR -->
        <aside class="flex-shrink-0 w-full space-y-6 lg:w-80">
            <!-- Quick Info -->
            <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                <div class="p-5 border-b border-gray-100"><h3 class="font-semibold text-gray-900">Informații rapide</h3></div>
                <div class="p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Gen muzical</p>
                            <p class="text-sm font-medium text-gray-900"><?= e(implode(', ', $artist['genres'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm0 0h9"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Țară</p>
                            <p class="text-sm font-medium text-gray-900"><?= e($artist['country']) ?> <?= e($artist['countryFlag']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Oraș de reședință</p>
                            <p class="text-sm font-medium text-gray-900"><?= e($artist['city']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Activ din</p>
                            <p class="text-sm font-medium text-gray-900"><?= e($artist['activeSince']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Următorul eveniment</p>
                            <p class="text-sm font-medium text-gray-900"><?= e($artist['nextEvent']['date']) ?> &bull; <?= e($artist['nextEvent']['city']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-100 rounded-xl">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Preț bilete de la</p>
                            <p class="text-sm font-medium text-gray-900"><?= e($artist['minPrice']) ?> RON</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spotify Embed -->
            <?php if ($artist['spotifyId']): ?>
            <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                <div class="p-5 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Ascultă pe Spotify</h3>
                        <svg class="w-5 h-5 text-[#1DB954]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                    </div>
                </div>
                <div class="p-3">
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/artist/<?= e($artist['spotifyId']) ?>?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>
                <div class="px-5 pb-4">
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <span class="text-gray-500">Popularitate:</span>
                            <span class="ml-1 font-semibold text-gray-900"><?= e($artist['stats']['spotify_popularity']) ?>/100</span>
                        </div>
                        <a href="<?= e($artist['social']['spotify']) ?>" target="_blank" rel="noopener" class="text-[#1DB954] font-medium hover:underline">Deschide &rarr;</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Booking & Management -->
            <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Booking & Management</h3>
                    <p class="mt-1 text-xs text-gray-500">Contactează echipa artistului</p>
                </div>
                <div class="p-5 space-y-3">
                    <button onclick="openContactModal('manager')" class="flex items-center w-full gap-3 px-4 py-3 text-white transition-colors bg-gray-900 rounded-xl hover:bg-gray-800 group">
                        <div class="flex items-center justify-center flex-shrink-0 rounded-lg w-9 h-9 bg-white/15">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="text-sm font-medium">Contactează managerul</p>
                            <p class="text-xs text-white/60">Management & PR</p>
                        </div>
                        <svg class="w-4 h-4 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <button onclick="openContactModal('booking')" class="flex items-center w-full gap-3 px-4 py-3 text-gray-900 transition-all bg-white border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 group">
                        <div class="flex items-center justify-center flex-shrink-0 bg-gray-100 rounded-lg w-9 h-9">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="text-sm font-medium">Contactează agenția de booking</p>
                            <p class="text-xs text-gray-500">Pentru rezervări și evenimente</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- Similar Artists -->
            <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                <div class="p-5 border-b border-gray-100"><h3 class="font-semibold text-gray-900">Artiști similari</h3></div>
                <div class="p-3">
                    <?php foreach ($similarArtists as $similar): ?>
                    <a href="/artist/<?= e($similar['slug']) ?>" class="flex items-center gap-3 p-3 transition-colors hover:bg-gray-50 rounded-xl group">
                        <img src="<?= e($similar['image']) ?>" class="object-cover w-12 h-12 rounded-full" alt="<?= e($similar['name']) ?>">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1">
                                <p class="text-sm font-medium text-gray-900 transition-colors group-hover:text-indigo-600"><?= e($similar['name']) ?></p>
                                <?php if ($similar['isVerified']): ?>
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-500"><?= e($similar['genres']) ?> &bull; <?= e($similar['followers']) ?></p>
                        </div>
                        <span class="text-xs font-medium text-indigo-600"><?= e($similar['eventsCount']) ?> ev.</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="px-5 pb-4"><a href="/artisti" class="block text-sm font-medium text-center text-indigo-600 hover:underline">Vezi mai mulți artiști &rarr;</a></div>
            </div>

            <!-- Share -->
            <div class="p-5 bg-white border border-gray-200 rounded-2xl">
                <h3 class="mb-3 font-semibold text-gray-900">Distribuie pagina artistului</h3>
                <div class="flex items-center gap-2">
                    <button onclick="shareOnFacebook()" class="flex-1 flex items-center justify-center gap-2 py-2.5 bg-[#1877F2] text-white text-sm font-medium rounded-xl hover:opacity-90 transition-opacity">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook
                    </button>
                    <button onclick="shareOnWhatsApp()" class="flex-1 flex items-center justify-center gap-2 py-2.5 bg-[#25D366] text-white text-sm font-medium rounded-xl hover:opacity-90 transition-opacity">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp
                    </button>
                    <button onclick="copyLink()" class="flex items-center justify-center flex-shrink-0 transition-colors bg-gray-100 w-11 h-11 rounded-xl hover:bg-gray-200">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- Contact Modal -->
<div id="contactModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 modal-backdrop" onclick="closeContactModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative w-full max-w-lg overflow-hidden bg-white shadow-2xl modal-content-enter rounded-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <div>
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Contactează managerul</h3>
                    <p id="modalSubtitle" class="text-sm text-gray-500 mt-0.5">Trimite un mesaj către echipa de management</p>
                </div>
                <button onclick="closeContactModal()" class="flex items-center justify-center w-10 h-10 transition-colors rounded-full hover:bg-gray-100">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 p-4 mb-6 bg-gray-50 rounded-xl">
                    <img src="<?= e($artist['image']) ?>" class="object-cover w-12 h-12 rounded-xl" alt="<?= e($artist['name']) ?>">
                    <div>
                        <p class="font-semibold text-gray-900"><?= e($artist['name']) ?></p>
                        <p id="modalContactType" class="text-sm text-gray-500">Management & PR</p>
                    </div>
                </div>
                <form id="contactForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Numele tău *</label>
                        <input type="text" name="name" placeholder="ex. Ion Popescu" required class="w-full px-4 py-3 text-sm transition-all border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                        <input type="email" name="email" placeholder="ex. ion@company.ro" required class="w-full px-4 py-3 text-sm transition-all border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telefon</label>
                        <input type="tel" name="phone" placeholder="ex. +40 7XX XXX XXX" class="w-full px-4 py-3 text-sm transition-all border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Companie / Organizator</label>
                        <input type="text" name="company" placeholder="ex. Live Events SRL" class="w-full px-4 py-3 text-sm transition-all border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Subiect *</label>
                        <select name="subject" required class="w-full px-4 py-3 text-sm text-gray-600 transition-all border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400">
                            <option value="">Selectează subiectul</option>
                            <option value="booking">Booking eveniment</option>
                            <option value="corporate">Eveniment corporate</option>
                            <option value="festival">Participare festival</option>
                            <option value="collab">Colaborare muzicală</option>
                            <option value="press">Presă & Interviuri</option>
                            <option value="other">Altele</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Mesaj *</label>
                        <textarea name="message" rows="4" placeholder="Descrie detaliile cererii tale: dată dorită, locație, tip eveniment, buget estimat..." required class="w-full px-4 py-3 text-sm transition-all border border-gray-200 resize-none rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400"></textarea>
                    </div>
                </form>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50/50">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">* câmpuri obligatorii</p>
                    <div class="flex items-center gap-3">
                        <button onclick="closeContactModal()" class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">Anulează</button>
                        <button onclick="submitContactForm()" class="px-6 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Trimite mesajul
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div id="successToast" class="fixed z-50 hidden bottom-6 right-6">
    <div class="flex items-center gap-3 px-5 py-3 text-white bg-gray-900 shadow-2xl rounded-xl animate-fadeInUp">
        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-green-500 rounded-full">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
            <p class="text-sm font-medium">Mesaj trimis cu succes!</p>
            <p class="text-xs text-white/60">Vei primi un răspuns în 24-48h.</p>
        </div>
    </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabName)?.classList.remove('hidden');
    event.target.closest('.tab-btn')?.classList.add('active') || (event.target.classList.add('active'));
}

// Contact Modal
function openContactModal(type) {
    const m = document.getElementById('contactModal');
    const t = document.getElementById('modalTitle');
    const s = document.getElementById('modalSubtitle');
    const c = document.getElementById('modalContactType');

    if (type === 'manager') {
        t.textContent = 'Contactează managerul';
        s.textContent = 'Trimite un mesaj către echipa de management';
        c.textContent = 'Management & PR';
    } else {
        t.textContent = 'Contactează agenția de booking';
        s.textContent = 'Trimite o cerere de booking pentru evenimente';
        c.textContent = 'Booking & Evenimente';
    }
    m.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeContactModal() {
    document.getElementById('contactModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function submitContactForm() {
    const form = document.getElementById('contactForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    closeContactModal();
    const t = document.getElementById('successToast');
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 4000);
}

// Share functions
function shareOnFacebook() {
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href), '_blank', 'width=600,height=400');
}

function shareOnWhatsApp() {
    window.open('https://wa.me/?text=' + encodeURIComponent(document.title + ' ' + window.location.href), '_blank');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const t = document.getElementById('successToast');
        t.querySelector('.font-medium').textContent = 'Link copiat!';
        t.querySelector('.text-xs').textContent = 'Poți partaja linkul cu prietenii.';
        t.classList.remove('hidden');
        setTimeout(() => {
            t.classList.add('hidden');
            t.querySelector('.font-medium').textContent = 'Mesaj trimis cu succes!';
            t.querySelector('.text-xs').textContent = 'Vei primi un răspuns în 24-48h.';
        }, 2000);
    });
}

// Close modal on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeContactModal();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
