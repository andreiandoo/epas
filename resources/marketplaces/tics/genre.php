<?php
/**
 * TICS.ro - Genre Page
 * Displays genre information, top artists, upcoming events, related genres, and Spotify playlist
 */

require_once __DIR__ . '/includes/config.php';

// Get genre slug from query
$genreSlug = $_GET['slug'] ?? 'pop';

// ============================================================================
// DEMO DATA
// ============================================================================

// Genre data
$genre = [
    'name' => 'Pop',
    'slug' => 'pop',
    'emoji' => '🎤',
    'subtitle' => 'Muzic&#259; pop rom&#226;neasc&#259; &#537;i interna&#539;ional&#259;',
    'description' => 'Descoperiți cei mai populari artiști pop, de la hiturile rom&#226;nești care domin&#259; topurile p&#226;n&#259; la staruri interna&#539;ionale care concerteaz&#259; &#238;n Rom&#226;nia.',
    'gradient' => 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 40%,#a78bfa 100%)',
    'artistCount' => 86,
    'eventCount' => 142,
    'cityCount' => 23,
];

// Subgenres
$subgenres = [
    ['name' => 'Toate', 'slug' => 'toate', 'active' => true],
    ['name' => 'Pop rom&#226;nesc', 'slug' => 'pop-romanesc', 'active' => false],
    ['name' => 'Electropop', 'slug' => 'electropop', 'active' => false],
    ['name' => 'Indie Pop', 'slug' => 'indie-pop', 'active' => false],
    ['name' => 'Pop-Rock', 'slug' => 'pop-rock', 'active' => false],
    ['name' => 'Dance Pop', 'slug' => 'dance-pop', 'active' => false],
    ['name' => 'R&B / Soul', 'slug' => 'rnb-soul', 'active' => false],
    ['name' => 'Synthpop', 'slug' => 'synthpop', 'active' => false],
];

// Top artist (featured / #1)
$topArtist = [
    'name' => "Carla's Dreams",
    'slug' => 'carlas-dreams',
    'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600&h=800&fit=crop',
    'avatar' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=80&h=80&fit=crop',
    'followers' => '312K',
    'eventsCount' => 5,
    'rank' => 1,
    'isVerified' => true,
];

// Other top artists (#2-#6)
$topArtists = [
    [
        'name' => 'Irina Rimes',
        'slug' => 'irina-rimes',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=60&h=60&fit=crop',
        'followers' => '278K',
        'eventsCount' => 4,
        'rank' => 2,
    ],
    [
        'name' => 'INNA',
        'slug' => 'inna',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=60&h=60&fit=crop',
        'followers' => '512K',
        'eventsCount' => 3,
        'rank' => 3,
    ],
    [
        'name' => 'The Motans',
        'slug' => 'the-motans',
        'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=60&h=60&fit=crop',
        'followers' => '245K',
        'eventsCount' => 2,
        'rank' => 4,
    ],
    [
        'name' => 'Delia',
        'slug' => 'delia',
        'image' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=60&h=60&fit=crop',
        'followers' => '356K',
        'eventsCount' => 6,
        'rank' => 5,
    ],
    [
        'name' => 'Smiley',
        'slug' => 'smiley',
        'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=60&h=60&fit=crop',
        'followers' => '198K',
        'eventsCount' => 3,
        'rank' => 6,
    ],
];

// Upcoming events
$upcomingEvents = [
    [
        'title' => "Carla's Dreams - Turneul Nocturn 2026",
        'slug' => 'carlas-dreams-turneul-nocturn-bucuresti',
        'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=250&fit=crop',
        'venue' => 'Sala Palatului',
        'city' => 'București',
        'date' => '14 Mar 2026',
        'time' => '20:00',
        'category' => 'Concert',
        'categoryColor' => 'bg-indigo-100 text-indigo-700',
        'minPrice' => 149,
        'originalPrice' => null,
        'soldPercent' => 78,
        'badge' => ['label' => '🔥 Popular', 'gradient' => 'linear-gradient(135deg,#ff6b6b,#ee5a24)'],
    ],
    [
        'title' => 'Irina Rimes - Acustic Tour',
        'slug' => 'irina-rimes-acustic-tour-cluj',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=250&fit=crop',
        'venue' => 'Teatrul Național',
        'city' => 'Cluj-Napoca',
        'date' => '28 Mar 2026',
        'time' => null,
        'category' => 'Concert',
        'categoryColor' => 'bg-indigo-100 text-indigo-700',
        'minPrice' => 89,
        'originalPrice' => 119,
        'soldPercent' => null,
        'badge' => ['label' => '🎫 Early Bird', 'gradient' => 'linear-gradient(135deg,#00b894,#00cec9)'],
    ],
    [
        'title' => 'Summer Well 2026',
        'slug' => 'summer-well-2026',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=250&fit=crop',
        'venue' => 'Domeniul Știrbey',
        'city' => 'Buftea',
        'date' => '7-9 Aug 2026',
        'time' => null,
        'category' => 'Festival',
        'categoryColor' => 'bg-purple-100 text-purple-700',
        'minPrice' => 349,
        'originalPrice' => null,
        'soldPercent' => null,
        'badge' => null,
    ],
];

// City filter options
$cityFilterOptions = ['Toate orașele', 'București', 'Cluj-Napoca', 'Timișoara', 'Iași'];

// Date filter options
$dateFilterOptions = ['Data', 'Luna asta', 'Luna viitoare', 'Următoarele 3 luni'];

// Related genres
$relatedGenres = [
    ['name' => 'Rock', 'slug' => 'rock', 'emoji' => '🎸', 'artistCount' => 45, 'eventCount' => 67],
    ['name' => 'Electronică', 'slug' => 'electronica', 'emoji' => '🎧', 'artistCount' => 38, 'eventCount' => 89],
    ['name' => 'R&B / Soul', 'slug' => 'rnb-soul', 'emoji' => '🎵', 'artistCount' => 22, 'eventCount' => 31],
    ['name' => 'Indie', 'slug' => 'indie', 'emoji' => '🎹', 'artistCount' => 34, 'eventCount' => 42],
    ['name' => 'Dance', 'slug' => 'dance', 'emoji' => '💃', 'artistCount' => 19, 'eventCount' => 56],
    ['name' => 'Clasică', 'slug' => 'clasica', 'emoji' => '🎻', 'artistCount' => 28, 'eventCount' => 35],
];

// Spotify playlist
$spotifyPlaylist = [
    'name' => 'TICS Pop Hits 2026',
    'description' => 'Playlist-ul curat de artiștii Pop disponibili pe TICS. Actualizat s&#259;pt&#259;m&#226;nal.',
    'url' => '#',
];

// ============================================================================
// PAGE SETTINGS
// ============================================================================

$pageTitle = e($genre['name']) . ' | Evenimente și artiști ' . e($genre['name']);
$pageDescription = 'Descoperă artiști și evenimente din genul ' . e($genre['name']) . '. Cumpără bilete online pe TICS.ro';
$bodyClass = 'bg-gray-50';
$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Genuri', 'url' => '/artisti'],
    ['name' => $genre['name']],
];

// Set login state
setLoginState($isLoggedIn, $loggedInUser);

// Include head & header
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Genre Hero -->
    <section class="genre-hero text-white" style="background:<?= $genre['gradient'] ?>">
        <div class="absolute top-10 right-20 w-40 h-40 bg-white/5 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-10 left-10 w-60 h-60 bg-white/5 rounded-full blur-3xl animate-float" style="animation-delay:1.5s"></div>
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-10 lg:py-16 relative">
            <div class="flex items-center gap-2 text-sm text-white/60 mb-6 animate-fadeInUp">
                <a href="/" class="hover:text-white transition-colors">Acasă</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/artisti" class="hover:text-white transition-colors">Genuri</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white"><?= e($genre['name']) ?></span>
            </div>
            <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between gap-6 animate-fadeInUp" style="animation-delay:.1s">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center text-3xl"><?= $genre['emoji'] ?></div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold"><?= e($genre['name']) ?></h1>
                            <p class="text-white/70 text-sm"><?= $genre['subtitle'] ?></p>
                        </div>
                    </div>
                    <p class="text-white/80 max-w-xl mt-3 leading-relaxed"><?= $genre['description'] ?></p>
                </div>
                <div class="flex items-center gap-3 animate-fadeInUp" style="animation-delay:.3s">
                    <div class="stat-pill rounded-xl px-5 py-3 text-center border border-white/20">
                        <p class="text-2xl font-bold"><?= e($genre['artistCount']) ?></p>
                        <p class="text-xs text-white/70">Artiști</p>
                    </div>
                    <div class="stat-pill rounded-xl px-5 py-3 text-center border border-white/20">
                        <p class="text-2xl font-bold"><?= e($genre['eventCount']) ?></p>
                        <p class="text-xs text-white/70">Ev. viitoare</p>
                    </div>
                    <div class="stat-pill rounded-xl px-5 py-3 text-center border border-white/20">
                        <p class="text-2xl font-bold"><?= e($genre['cityCount']) ?></p>
                        <p class="text-xs text-white/70">Orașe</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Subgenre chips -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wider whitespace-nowrap mr-1">Subgenuri:</span>
                <?php foreach ($subgenres as $subgenre): ?>
                    <?php if ($subgenre['active']): ?>
                        <button class="chip-active px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-medium whitespace-nowrap transition-colors"><?= $subgenre['name'] ?></button>
                    <?php else: ?>
                        <button class="subgenre-chip px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-medium text-gray-600 whitespace-nowrap"><?= $subgenre['name'] ?></button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-8">

        <!-- Top Artists in Genre -->
        <section class="mb-10">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">&#11088; Top artiști <?= e($genre['name']) ?></h2>
                    <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                </div>
                <a href="/artisti" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toți &#8594;</a>
            </div>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <!-- Top artist #1 - large card -->
                <a href="/artist/<?= e($topArtist['slug']) ?>" class="sm:col-span-2 md:col-span-1 lg:col-span-2 lg:row-span-2 artist-card bg-white rounded-2xl overflow-hidden border border-gray-200 group relative">
                    <div class="relative aspect-[3/4] lg:h-full overflow-hidden">
                        <img src="<?= e($topArtist['image']) ?>" alt="<?= e($topArtist['name']) ?>" class="absolute inset-0 w-full h-full object-cover artist-img">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                        <div class="absolute top-4 left-4"><span class="px-3 py-1.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">&#128081; #<?= e($topArtist['rank']) ?> <?= e($genre['name']) ?></span></div>
                        <div class="absolute bottom-0 left-0 right-0 p-5">
                            <div class="flex items-center gap-3 mb-2">
                                <img src="<?= e($topArtist['avatar']) ?>" alt="<?= e($topArtist['name']) ?>" class="w-12 h-12 rounded-full border-2 border-white object-cover">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h3 class="font-bold text-white text-lg"><?= e($topArtist['name']) ?></h3>
                                        <?php if ($topArtist['isVerified']): ?>
                                        <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-white/70 text-sm"><?= e($topArtist['followers']) ?> urm&#259;ritori &bull; <?= e($topArtist['eventsCount']) ?> ev.</p>
                                </div>
                            </div>
                            <button class="follow-btn w-full mt-2 px-4 py-2.5 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-gray-100">Urm&#259;rește</button>
                        </div>
                    </div>
                </a>
                <!-- Top 2-6 smaller -->
                <?php foreach ($topArtists as $artist): ?>
                <a href="/artist/<?= e($artist['slug']) ?>" class="artist-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="absolute inset-0 w-full h-full object-cover artist-img">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                        <div class="absolute top-3 left-3"><span class="px-2 py-1 bg-white/90 text-gray-900 text-xs font-bold rounded-full">#<?= e($artist['rank']) ?></span></div>
                    </div>
                    <div class="p-3">
                        <div class="flex items-center gap-2">
                            <img src="<?= e($artist['avatar']) ?>" alt="<?= e($artist['name']) ?>" class="w-8 h-8 rounded-full object-cover">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900 text-sm truncate group-hover:text-indigo-600 transition-colors"><?= e($artist['name']) ?></h3>
                                <p class="text-xs text-gray-500"><?= e($artist['followers']) ?> &bull; <?= e($artist['eventsCount']) ?> ev.</p>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Upcoming Events in Genre -->
        <section class="mb-10">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-semibold text-gray-900">&#127915; Evenimente <?= e($genre['name']) ?> viitoare</h2>
                <div class="flex items-center gap-2">
                    <select class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 cursor-pointer focus:outline-none">
                        <?php foreach ($cityFilterOptions as $option): ?>
                        <option><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 cursor-pointer focus:outline-none">
                        <?php foreach ($dateFilterOptions as $option): ?>
                        <option><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="space-y-4">
                <?php foreach ($upcomingEvents as $event): ?>
                <a href="<?= eventUrl($event['slug']) ?>" class="event-card block bg-white rounded-2xl border border-gray-200 overflow-hidden group">
                    <div class="flex flex-col sm:flex-row">
                        <div class="relative sm:w-52 aspect-video sm:aspect-auto overflow-hidden flex-shrink-0">
                            <img src="<?= e($event['image']) ?>" alt="<?= e($event['title']) ?>" class="absolute inset-0 w-full h-full object-cover event-img">
                            <?php if ($event['badge']): ?>
                            <div class="absolute top-3 left-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold text-white" style="background:<?= $event['badge']['gradient'] ?>"><?= $event['badge']['label'] ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 <?= e($event['categoryColor']) ?> text-xs font-medium rounded-full"><?= e($event['category']) ?></span>
                                        <?php if ($event['soldPercent']): ?>
                                        <span class="text-xs text-red-500 font-medium">&#9889; <?= e($event['soldPercent']) ?>% sold</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="font-semibold text-gray-900 text-lg mb-1 group-hover:text-indigo-600 transition-colors"><?= e($event['title']) ?></h3>
                                    <p class="text-sm text-gray-500 mb-2"><?= e($event['venue']) ?>, <?= e($event['city']) ?></p>
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
                                <div class="text-right flex-shrink-0">
                                    <p class="text-lg font-bold text-gray-900">de la <?= e(formatPrice($event['minPrice'])) ?></p>
                                    <?php if ($event['originalPrice']): ?>
                                    <p class="text-xs text-gray-400 line-through"><?= e(formatPrice($event['originalPrice'])) ?></p>
                                    <?php endif; ?>
                                    <button class="mt-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">Cump&#259;r&#259; bilete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-6">
                <a href="#" class="inline-flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all">
                    Arat&#259; toate cele <?= e($genre['eventCount']) ?> de evenimente
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </section>

        <!-- Related Genres -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold text-gray-900 mb-5">Genuri similare</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php foreach ($relatedGenres as $related): ?>
                <a href="/gen/<?= e($related['slug']) ?>" class="group bg-white rounded-2xl border border-gray-200 p-5 text-center hover:border-indigo-200 hover:shadow-md transition-all">
                    <div class="text-3xl mb-2"><?= $related['emoji'] ?></div>
                    <h3 class="font-semibold text-gray-900 text-sm group-hover:text-indigo-600 transition-colors"><?= e($related['name']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1"><?= e($related['artistCount']) ?> artiști &bull; <?= e($related['eventCount']) ?> ev.</p>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Spotify Playlist -->
        <section class="bg-gradient-to-r from-[#1DB954]/10 to-[#1DB954]/5 rounded-2xl border border-[#1DB954]/20 p-6 mb-10">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="w-14 h-14 bg-[#1DB954] rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1"><?= e($spotifyPlaylist['name']) ?></h3>
                    <p class="text-sm text-gray-600"><?= $spotifyPlaylist['description'] ?></p>
                </div>
                <a href="<?= e($spotifyPlaylist['url']) ?>" class="px-5 py-2.5 bg-[#1DB954] text-white text-sm font-medium rounded-full hover:bg-[#1aa34a] transition-colors flex-shrink-0">Ascult&#259; pe Spotify</a>
            </div>
        </section>

    </main>

    <script>
    document.querySelectorAll('.subgenre-chip, [class*="chip-active"]').forEach(chip=>{
        chip.addEventListener('click',function(){
            this.parentElement.querySelectorAll('button').forEach(c=>{c.classList.remove('chip-active');c.classList.add('subgenre-chip')});
            this.classList.add('chip-active');this.classList.remove('subgenre-chip');
        });
    });
    document.querySelectorAll('.follow-btn').forEach(btn=>{
        btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();
            if(this.textContent.includes('Urmărești')){this.textContent='Urmărește';this.classList.remove('bg-gray-100','text-gray-900','border','border-gray-200');this.classList.add('bg-gray-900','text-white')}
            else{this.textContent='Urmărești \u2713';this.classList.add('bg-gray-100','text-gray-900','border','border-gray-200');this.classList.remove('bg-gray-900','text-white')}
        });
    });
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
