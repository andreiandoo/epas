<?php
/**
 * Venue Type / Category Page
 *
 * Displays venues filtered by type/category
 * URL: /locatii/{type} where type = arene|teatre|cluburi|open-air|sali-de-concerte|stadiane|centre-culturale|alte-locatii
 *
 * Based on venue-type.html design - converted to Tailwind CSS only
 */

require_once __DIR__ . '/includes/config.php';

// Get venue type from URL
$typeSlug = $_GET['type'] ?? '';

// Venue type configurations
$venueTypes = [
    'arene' => [
        'name' => 'Arene & Stadioane',
        'short_name' => 'Arene',
        'icon' => 'building',
        'description' => 'Descoperă cele mai impresionante arene și stadioane din România. Locații cu capacitate mare pentru concerte legendare, meciuri de top și evenimente spectaculoase care rămân în istorie.',
        'meta_description' => 'Descoperă cele mai mari arene și stadioane din România. Concerte, meciuri și evenimente spectaculoase în locații cu capacitate mare.',
        'gradient' => 'from-slate-800 to-slate-900',
    ],
    'teatre' => [
        'name' => 'Teatre & Săli de Spectacole',
        'short_name' => 'Teatre & Săli',
        'icon' => 'theater',
        'description' => 'Explorează cele mai frumoase teatre și săli de spectacole din România. Locuri cu istorie și acustică perfectă pentru piese de teatru, opere și concerte clasice.',
        'meta_description' => 'Teatre și săli de spectacole din România. Piese de teatru, opere, concerte clasice în locații cu tradiție.',
        'gradient' => 'from-amber-800 to-amber-900',
    ],
    'cluburi' => [
        'name' => 'Cluburi & Pub-uri',
        'short_name' => 'Cluburi',
        'icon' => 'music',
        'description' => 'Descoperă cele mai tari cluburi și pub-uri din România. Locații pentru concerte live, petreceri și evenimente private într-o atmosferă unică.',
        'meta_description' => 'Cluburi și pub-uri din România. Concerte live, petreceri și evenimente într-o atmosferă unică.',
        'gradient' => 'from-purple-800 to-purple-900',
    ],
    'open-air' => [
        'name' => 'Locații Open Air',
        'short_name' => 'Open Air',
        'icon' => 'sun',
        'description' => 'Descoperă locațiile open air perfecte pentru festivaluri, concerte în aer liber și evenimente sub cerul liber. De la parcuri la plaje și de la amfiteatre la spații neconvenționale.',
        'meta_description' => 'Locații open air pentru festivaluri și concerte în aer liber din România.',
        'gradient' => 'from-emerald-700 to-emerald-900',
    ],
    'sali-de-concerte' => [
        'name' => 'Săli de Concerte',
        'short_name' => 'Săli Concerte',
        'icon' => 'music-note',
        'description' => 'Săli de concerte moderne cu acustică excepțională pentru experiențe muzicale de neuitat. De la jazz și clasică la rock și electronic.',
        'meta_description' => 'Săli de concerte cu acustică excepțională din România pentru experiențe muzicale de neuitat.',
        'gradient' => 'from-blue-800 to-blue-900',
    ],
    'stadiane' => [
        'name' => 'Stadioane',
        'short_name' => 'Stadioane',
        'icon' => 'stadium',
        'description' => 'Cele mai mari stadioane din România pentru evenimente de anvergură. Meciuri de fotbal, concerte gigantice și spectacole care adună zeci de mii de spectatori.',
        'meta_description' => 'Stadioane din România pentru meciuri, concerte și evenimente de anvergură.',
        'gradient' => 'from-green-700 to-green-900',
    ],
    'centre-culturale' => [
        'name' => 'Centre Culturale',
        'short_name' => 'Centre Culturale',
        'icon' => 'building-library',
        'description' => 'Centre culturale și spații creative pentru expoziții, conferințe, workshop-uri și evenimente culturale diverse.',
        'meta_description' => 'Centre culturale din România pentru expoziții, conferințe și evenimente culturale.',
        'gradient' => 'from-rose-700 to-rose-900',
    ],
    'alte-locatii' => [
        'name' => 'Alte Locații',
        'short_name' => 'Alte Locații',
        'icon' => 'map-pin',
        'description' => 'Locații unice și neconvenționale pentru evenimente speciale. De la castele și conace la spații industriale reconvertite.',
        'meta_description' => 'Locații unice și neconvenționale pentru evenimente speciale în România.',
        'gradient' => 'from-gray-700 to-gray-900',
    ],
];

// Validate type
if (!isset($venueTypes[$typeSlug])) {
    header('Location: /locatii');
    exit;
}

$currentType = $venueTypes[$typeSlug];
$pageTitle = $currentType['name'] . ' - Locații pentru Evenimente';
$pageDescription = $currentType['meta_description'];
$bodyClass = 'bg-slate-50 min-h-screen';
$transparentHeader = true;

// All venue types for navigation
$allTypes = [
    ['slug' => '', 'name' => 'Toate locațiile', 'icon' => 'grid'],
    ['slug' => 'arene', 'name' => 'Arene & Stadioane', 'icon' => 'building'],
    ['slug' => 'teatre', 'name' => 'Teatre & Săli', 'icon' => 'theater'],
    ['slug' => 'cluburi', 'name' => 'Cluburi & Pub-uri', 'icon' => 'music'],
    ['slug' => 'open-air', 'name' => 'Open Air', 'icon' => 'sun'],
    ['slug' => 'centre-culturale', 'name' => 'Centre Culturale', 'icon' => 'building-library'],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

// Helper function to render SVG icon paths
function getVenueIconPath($icon) {
    $icons = [
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'theater' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>',
        'music' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>',
        'sun' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064"/>',
        'building-library' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>',
        'grid' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
        'map-pin' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'music-note' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/>',
        'stadium' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
        'chevron-right' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>',
        'location' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="10" r="3"/>',
    ];
    return $icons[$icon] ?? $icons['building'];
}

// Get type emoji
$typeEmoji = match($typeSlug) {
    'arene' => '🏟️',
    'teatre' => '🎭',
    'cluburi' => '🎵',
    'open-air' => '☀️',
    'sali-de-concerte' => '🎶',
    'stadiane' => '⚽',
    'centre-culturale' => '🏛️',
    default => '📍'
};
?>

<!-- Hero Section -->
<section class="relative pt-32 pb-16 overflow-hidden bg-gradient-to-br <?= $currentType['gradient'] ?>">
    <!-- Background pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute right-0 w-2/5 h-full bg-gradient-to-l from-white/5 to-transparent"></div>
    </div>

    <div class="relative z-10 max-w-7xl px-6 mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 mb-6 text-sm">
            <a href="/" class="transition-colors text-white/60 hover:text-white">Acasă</a>
            <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath('chevron-right') ?></svg>
            <a href="/locatii" class="transition-colors text-white/60 hover:text-white">Locații</a>
            <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath('chevron-right') ?></svg>
            <span class="text-red-300"><?= htmlspecialchars($currentType['short_name']) ?></span>
        </nav>

        <!-- Icon -->
        <div class="flex items-center justify-center w-[72px] h-[72px] bg-gradient-to-br from-primary to-red-700 rounded-2xl mb-6">
            <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath($currentType['icon']) ?></svg>
        </div>

        <!-- Title -->
        <h1 class="mb-4 text-4xl font-extrabold leading-tight text-white md:text-5xl"><?= htmlspecialchars($currentType['name']) ?></h1>

        <!-- Description -->
        <p class="max-w-2xl mb-8 text-lg leading-relaxed text-slate-300"><?= htmlspecialchars($currentType['description']) ?></p>

        <!-- Stats -->
        <div class="flex flex-wrap gap-8 pt-6 border-t md:gap-12 border-white/10">
            <div class="text-left">
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statVenues">--</div>
                <div class="text-sm text-slate-400"><?= htmlspecialchars($currentType['short_name']) ?></div>
            </div>
            <div class="text-left">
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statEvents">--</div>
                <div class="text-sm text-slate-400">Evenimente active</div>
            </div>
            <div class="text-left">
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statCapacity">--</div>
                <div class="text-sm text-slate-400">Capacitate totală</div>
            </div>
            <div class="text-left">
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statCities">--</div>
                <div class="text-sm text-slate-400">Orașe</div>
            </div>
        </div>
    </div>
</section>

<!-- Venue Types Navigation -->
<nav class="sticky z-40 bg-white border-b top-16 border-slate-200">
    <div class="max-w-6xl px-6 mx-auto">
        <div class="flex gap-1 py-0 overflow-x-auto scrollbar-hide">
            <?php foreach ($allTypes as $type): ?>
                <?php $isActive = $type['slug'] === $typeSlug; ?>
                <a href="/locatii<?= $type['slug'] ? '/' . $type['slug'] : '' ?>"
                   class="flex items-center flex-shrink-0 gap-2 px-5 py-4 text-sm font-semibold transition-all border-b-[3px] <?= $isActive ? 'text-primary border-primary bg-red-50' : 'text-slate-500 border-transparent hover:text-slate-800 hover:bg-slate-50' ?>">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath($type['icon']) ?></svg>
                    <?= htmlspecialchars($type['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="max-w-6xl px-6 py-8 mx-auto">
    <!-- Filters Bar -->
    <div class="flex flex-col gap-4 p-5 mb-8 bg-white border md:flex-row md:items-center rounded-2xl border-slate-200">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-slate-500">Oraș:</span>
                <select id="filterCity" class="py-2 pl-3 pr-8 text-sm font-medium bg-white border rounded-lg appearance-none cursor-pointer border-slate-200 text-slate-800 focus:outline-none focus:border-primary">
                    <option value="">Toate orașele</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-slate-500">Capacitate:</span>
                <select id="filterCapacity" class="py-2 pl-3 pr-8 text-sm font-medium bg-white border rounded-lg appearance-none cursor-pointer border-slate-200 text-slate-800 focus:outline-none focus:border-primary">
                    <option value="">Orice capacitate</option>
                    <option value="0-5000">Sub 5.000</option>
                    <option value="5000-15000">5.000 - 15.000</option>
                    <option value="15000-30000">15.000 - 30.000</option>
                    <option value="30000+">30.000+</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 md:ml-auto">
            <button class="px-4 py-2 text-sm font-medium text-white transition-all rounded-full filter-chip active bg-primary" data-filter="all">Toate</button>
            <button class="px-4 py-2 text-sm font-medium transition-all rounded-full filter-chip bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="has_events">Cu evenimente</button>
            <button class="px-4 py-2 text-sm font-medium transition-all rounded-full filter-chip bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="indoor">Acoperite</button>
            <button class="px-4 py-2 text-sm font-medium transition-all rounded-full filter-chip bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="parking">Cu parcare</button>
        </div>

        <div class="pt-4 mt-4 border-t md:pt-0 md:mt-0 md:border-t-0 md:pl-4 md:border-l border-slate-200">
            <span class="text-sm text-slate-500"><strong class="text-slate-800" id="resultsCount">0</strong> locații găsite</span>
        </div>
    </div>

    <!-- Featured Venue -->
    <section id="featuredVenueSection" class="hidden mb-8">
        <div class="grid overflow-hidden bg-white border md:grid-cols-2 rounded-2xl border-slate-200">
            <div id="featuredVenueImage" class="relative flex items-center justify-center min-h-[320px] bg-gradient-to-br from-indigo-500 to-purple-600">
                <span class="absolute flex items-center gap-2 px-4 py-2 text-xs font-bold text-white uppercase bg-amber-500 top-6 left-6 rounded-lg">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    Locație populară
                </span>
                <svg class="w-20 h-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath($currentType['icon']) ?></svg>
            </div>
            <div class="flex flex-col justify-center p-8">
                <div id="featuredVenueCity" class="mb-2 text-xs font-semibold tracking-wider uppercase text-primary"></div>
                <h2 id="featuredVenueName" class="mb-3 text-2xl font-extrabold text-slate-800"></h2>
                <div id="featuredVenueStats" class="flex flex-wrap gap-6 mb-4"></div>
                <p id="featuredVenueDesc" class="mb-6 leading-relaxed text-slate-500"></p>
                <div class="flex flex-wrap gap-3">
                    <a id="featuredVenueLink" href="#" class="inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-red-700 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">Vezi detalii</a>
                    <a id="featuredVenueEventsLink" href="#" class="inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold transition-all bg-white border rounded-lg text-slate-800 border-slate-200 hover:bg-slate-50">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath('calendar') ?></svg>
                        <span id="featuredVenueEventsCount">0</span> evenimente
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Venues Grid Section -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-slate-800">
                <span class="mr-2"><?= $typeEmoji ?></span>
                Toate <?= strtolower($currentType['name']) ?>
            </h2>
            <div class="flex gap-1 p-1 rounded-lg bg-slate-100">
                <button class="flex items-center justify-center w-9 h-9 transition-all bg-white rounded-md shadow-sm view-btn active text-primary" data-view="grid">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </button>
                <button class="flex items-center justify-center transition-all rounded-md view-btn w-9 h-9 text-slate-400 hover:text-slate-600" data-view="list">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <!-- Venues Grid -->
        <div id="venuesGrid" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- Skeleton loaders -->
            <div class="overflow-hidden bg-white border animate-pulse rounded-2xl border-slate-200">
                <div class="h-[180px] bg-slate-200"></div>
                <div class="p-5">
                    <div class="w-1/3 h-3 mb-2 rounded bg-slate-200"></div>
                    <div class="w-2/3 h-5 mb-4 rounded bg-slate-200"></div>
                    <div class="w-full h-3 rounded bg-slate-200"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border animate-pulse rounded-2xl border-slate-200">
                <div class="h-[180px] bg-slate-200"></div>
                <div class="p-5">
                    <div class="w-1/3 h-3 mb-2 rounded bg-slate-200"></div>
                    <div class="w-2/3 h-5 mb-4 rounded bg-slate-200"></div>
                    <div class="w-full h-3 rounded bg-slate-200"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border animate-pulse rounded-2xl border-slate-200">
                <div class="h-[180px] bg-slate-200"></div>
                <div class="p-5">
                    <div class="w-1/3 h-3 mb-2 rounded bg-slate-200"></div>
                    <div class="w-2/3 h-5 mb-4 rounded bg-slate-200"></div>
                    <div class="w-full h-3 rounded bg-slate-200"></div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-8"></div>
    </section>

    <!-- Events in this venue type -->
    <section id="eventsSection" class="hidden mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-slate-800">
                <span class="mr-2">🎫</span>
                Evenimente în <?= htmlspecialchars($currentType['short_name']) ?>
            </h2>
            <a href="/evenimente?venue_type=<?= urlencode($typeSlug) ?>" class="flex items-center gap-1 text-sm font-semibold transition-colors text-primary">
                Vezi toate (<span id="totalEventsCount">0</span>)
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath('chevron-right') ?></svg>
            </a>
        </div>
        <div id="eventsScroll" class="flex gap-4 pb-2 overflow-x-auto scrollbar-hide"></div>
    </section>

    <!-- Capacity Guide -->
    <section class="p-8 mb-12 bg-white border rounded-2xl border-slate-200">
        <h3 class="flex items-center gap-2 mb-6 text-xl font-bold text-slate-800">
            <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Ghid capacitate
        </h3>
        <div id="capacityTiers" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"></div>
    </section>

    <!-- Other Venue Types -->
    <section class="mb-12">
        <h2 class="mb-6 text-xl font-bold text-slate-800">
            <span class="mr-2">🏛️</span>
            Alte tipuri de locații
        </h2>
        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
            <?php foreach ($venueTypes as $slug => $type): ?>
                <?php if ($slug !== $typeSlug): ?>
                    <a href="/locatii/<?= $slug ?>" class="p-6 text-center transition-all bg-white border rounded-2xl border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg group">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-3 transition-colors rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 group-hover:from-red-100 group-hover:to-red-200">
                            <svg class="w-7 h-7 text-slate-400 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= getVenueIconPath($type['icon']) ?></svg>
                        </div>
                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($type['short_name']) ?></div>
                        <div class="text-xs text-slate-500" data-type-count="<?= $slug ?>">-- locații</div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
const VenueTypePage = {
    type: '<?= $typeSlug ?>',
    venues: [],
    filteredVenues: [],
    currentPage: 1,
    perPage: 9,

    async init() {
        await this.loadData();
        this.bindEvents();
    },

    async loadData() {
        try {
            const response = await fetch(`/api/proxy.php?action=venues&type=${this.type}`);
            const data = await response.json();
            this.venues = data.success && data.data ? (data.data.venues || data.data || []) : this.getMockData();
        } catch (err) {
            this.venues = this.getMockData();
        }

        this.filteredVenues = [...this.venues];
        this.updateStats();
        this.updateCityFilter();
        this.renderFeaturedVenue();
        this.renderVenues();
        this.renderCapacityGuide();
        this.loadEvents();
    },

    getMockData() {
        const gradients = ['from-emerald-500 to-teal-600', 'from-rose-500 to-pink-600', 'from-amber-400 to-yellow-500', 'from-blue-500 to-cyan-600', 'from-purple-500 to-violet-600', 'from-orange-500 to-red-600'];
        return [
            { id: 1, name: 'Arena Națională', slug: 'arena-nationala', city: 'București', capacity: 55600, events_count: 18, rating: 4.8, image: null, gradient: gradients[0], is_featured: true, address: 'Bd. Basarabia 37-39', facilities: ['parking', 'indoor'], description: 'Cea mai mare arenă multifuncțională din România.' },
            { id: 2, name: 'Cluj Arena', slug: 'cluj-arena', city: 'Cluj-Napoca', capacity: 30000, events_count: 12, rating: 4.7, image: null, gradient: gradients[1], is_featured: false, address: 'Calea Someșeni', facilities: ['parking'] },
            { id: 3, name: 'BT Arena (Romexpo)', slug: 'bt-arena', city: 'București', capacity: 16000, events_count: 8, rating: 4.5, image: null, gradient: gradients[2], is_featured: false, address: 'Bd. Expozitiei', facilities: ['parking', 'indoor', 'metro'] },
            { id: 4, name: 'Stadionul Ion Oblemenco', slug: 'stadion-craiova', city: 'Craiova', capacity: 32000, events_count: 5, rating: 4.6, image: null, gradient: gradients[3], is_featured: false, address: 'Calea Severinului', facilities: ['parking'] },
            { id: 5, name: 'Arenele Romane', slug: 'arenele-romane', city: 'București', capacity: 10000, events_count: 6, rating: 4.8, image: null, gradient: gradients[4], is_featured: false, address: 'Parcul Carol I', facilities: [] },
            { id: 6, name: 'Sala Polivalentă', slug: 'sala-polivalenta', city: 'București', capacity: 5000, events_count: 3, rating: 4.3, image: null, gradient: gradients[5], is_featured: false, address: 'Bd. Tineretului', facilities: ['indoor', 'metro'] },
        ];
    },

    updateStats() {
        const totalCapacity = this.venues.reduce((sum, v) => sum + (v.capacity || 0), 0);
        const totalEvents = this.venues.reduce((sum, v) => sum + (v.events_count || 0), 0);
        const uniqueCities = [...new Set(this.venues.map(v => v.city))];

        document.getElementById('statVenues').textContent = this.venues.length;
        document.getElementById('statEvents').textContent = totalEvents;
        document.getElementById('statCapacity').textContent = totalCapacity > 1000 ? Math.round(totalCapacity/1000) + 'K+' : totalCapacity;
        document.getElementById('statCities').textContent = uniqueCities.length;
        document.getElementById('resultsCount').textContent = this.filteredVenues.length;
    },

    updateCityFilter() {
        const cities = [...new Set(this.venues.map(v => v.city).filter(Boolean))].sort();
        const select = document.getElementById('filterCity');
        cities.forEach(city => {
            const opt = document.createElement('option');
            opt.value = city.toLowerCase();
            opt.textContent = city;
            select.appendChild(opt);
        });
    },

    renderFeaturedVenue() {
        const featured = this.venues.find(v => v.is_featured) || this.venues[0];
        if (!featured) return;

        document.getElementById('featuredVenueSection').classList.remove('hidden');
        document.getElementById('featuredVenueCity').textContent = featured.city || '';
        document.getElementById('featuredVenueName').textContent = featured.name || '';
        document.getElementById('featuredVenueDesc').textContent = featured.description || `Locație de top pentru evenimente în ${featured.city}.`;
        document.getElementById('featuredVenueLink').href = `/locatie/${featured.slug}`;
        document.getElementById('featuredVenueEventsLink').href = `/locatie/${featured.slug}/evenimente`;
        document.getElementById('featuredVenueEventsCount').textContent = featured.events_count || 0;

        if (featured.image) {
            const img = document.getElementById('featuredVenueImage');
            img.style.backgroundImage = `url(${featured.image})`;
            img.style.backgroundSize = 'cover';
            img.style.backgroundPosition = 'center';
        }

        document.getElementById('featuredVenueStats').innerHTML = `
            <div class="flex items-center gap-1.5 text-sm text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Capacitate: <strong class="text-slate-800">${(featured.capacity || 0).toLocaleString('ro-RO')}</strong>
            </div>
            <div class="flex items-center gap-1.5 text-sm text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <strong class="text-slate-800">${featured.events_count || 0}</strong> evenimente
            </div>`;
    },

    filterVenues() {
        const city = document.getElementById('filterCity').value;
        const capacity = document.getElementById('filterCapacity').value;
        const activeChip = document.querySelector('.filter-chip.active')?.dataset.filter || 'all';

        this.filteredVenues = this.venues.filter(v => {
            if (city && v.city?.toLowerCase() !== city) return false;
            if (capacity) {
                const cap = v.capacity || 0;
                if (capacity === '0-5000' && cap >= 5000) return false;
                if (capacity === '5000-15000' && (cap < 5000 || cap >= 15000)) return false;
                if (capacity === '15000-30000' && (cap < 15000 || cap >= 30000)) return false;
                if (capacity === '30000+' && cap < 30000) return false;
            }
            if (activeChip === 'has_events' && (!v.events_count || v.events_count === 0)) return false;
            if (activeChip === 'indoor' && !v.facilities?.includes('indoor')) return false;
            if (activeChip === 'parking' && !v.facilities?.includes('parking')) return false;
            return true;
        });

        this.currentPage = 1;
        document.getElementById('resultsCount').textContent = this.filteredVenues.length;
        this.renderVenues();
    },

    renderVenues() {
        const grid = document.getElementById('venuesGrid');
        const start = (this.currentPage - 1) * this.perPage;
        const paginated = this.filteredVenues.slice(start, start + this.perPage);

        if (paginated.length === 0) {
            grid.innerHTML = `<div class="py-16 text-center col-span-full"><svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg><p class="text-lg font-medium text-slate-500">Nu am găsit locații</p><p class="text-sm text-slate-400">Încearcă să ajustezi filtrele</p></div>`;
            this.renderPagination(0);
            return;
        }

        const gradients = ['from-emerald-500 to-teal-600', 'from-rose-500 to-pink-600', 'from-amber-400 to-yellow-500', 'from-blue-500 to-cyan-600', 'from-purple-500 to-violet-600', 'from-orange-500 to-red-600'];

        grid.innerHTML = paginated.map((v, i) => {
            const gradient = v.gradient || gradients[i % gradients.length];
            return `<article class="overflow-hidden transition-all bg-white border rounded-2xl border-slate-200 hover:-translate-y-1 hover:shadow-lg hover:border-primary group">
                <a href="/locatie/${v.slug}" class="block">
                    <div class="relative h-[180px] ${!v.image ? `bg-gradient-to-br ${gradient}` : ''} flex items-center justify-center">
                        ${v.image ? `<img src="${v.image}" alt="${v.name}" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">` : `<svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>`}
                        <span class="absolute flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-white bg-black/60 backdrop-blur-sm top-3 left-3 rounded-md">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/></svg>
                            ${(v.capacity || 0).toLocaleString('ro-RO')} locuri
                        </span>
                        ${v.events_count > 0 ? `<span class="absolute px-2.5 py-1 text-[11px] font-bold text-white bg-primary top-3 right-3 rounded-md">${v.events_count} evenimente</span>` : ''}
                    </div>
                    <div class="p-5">
                        <div class="mb-1 text-[11px] font-semibold tracking-wider uppercase text-primary">${v.city || ''}</div>
                        <h3 class="mb-2 text-lg font-bold leading-tight text-slate-800 group-hover:text-primary">${v.name || ''}</h3>
                        <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-slate-500">
                            ${v.address ? `<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>${v.address}</span>` : ''}
                            ${v.rating ? `<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>${v.rating}</span>` : ''}
                        </div>
                        ${v.facilities?.length > 0 ? `<div class="flex flex-wrap gap-1.5 mb-4">${(v.facilities || []).slice(0, 3).map(f => `<span class="px-2 py-0.5 text-[11px] font-medium bg-slate-100 text-slate-600 rounded">${this.getFacilityLabel(f)}</span>`).join('')}</div>` : ''}
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <span></span>
                            <span class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-red-700 hover:shadow-md">Vezi</span>
                        </div>
                    </div>
                </a>
            </article>`;
        }).join('');

        this.renderPagination(this.filteredVenues.length);
    },

    getFacilityLabel(f) {
        return { 'parking': 'Parcare', 'indoor': 'Acoperit', 'metro': 'Metro', 'vip': 'VIP Lounge', 'food': 'Food Court', 'accessible': 'Acces PRM' }[f] || f;
    },

    renderPagination(total) {
        const pages = Math.ceil(total / this.perPage);
        const container = document.getElementById('pagination');
        if (pages <= 1) { container.innerHTML = ''; return; }

        let html = `<button class="flex items-center justify-center w-10 h-10 transition-all bg-white border rounded-xl border-slate-200 text-slate-500 hover:border-primary hover:text-primary disabled:opacity-50 disabled:cursor-not-allowed" ${this.currentPage === 1 ? 'disabled' : ''} onclick="VenueTypePage.goToPage(${this.currentPage - 1})"><svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>`;
        for (let i = 1; i <= Math.min(pages, 5); i++) {
            html += `<button class="flex items-center justify-center w-10 h-10 text-sm font-semibold transition-all border rounded-xl ${this.currentPage === i ? 'bg-primary border-primary text-white' : 'bg-white border-slate-200 text-slate-500 hover:border-primary hover:text-primary'}" onclick="VenueTypePage.goToPage(${i})">${i}</button>`;
        }
        if (pages > 5) html += `<span class="flex items-center justify-center w-10 h-10 text-slate-400">...</span><button class="flex items-center justify-center w-10 h-10 text-sm font-semibold transition-all bg-white border rounded-xl border-slate-200 text-slate-500 hover:border-primary hover:text-primary" onclick="VenueTypePage.goToPage(${pages})">${pages}</button>`;
        html += `<button class="flex items-center justify-center w-10 h-10 transition-all bg-white border rounded-xl border-slate-200 text-slate-500 hover:border-primary hover:text-primary disabled:opacity-50 disabled:cursor-not-allowed" ${this.currentPage === pages ? 'disabled' : ''} onclick="VenueTypePage.goToPage(${this.currentPage + 1})"><svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>`;
        container.innerHTML = html;
    },

    goToPage(page) {
        this.currentPage = page;
        this.renderVenues();
        window.scrollTo({ top: 600, behavior: 'smooth' });
    },

    renderCapacityGuide() {
        const tiers = [
            { name: 'Mică', range: 'Sub 5.000 locuri', filter: '0-5000', count: this.venues.filter(v => (v.capacity || 0) < 5000).length },
            { name: 'Medie', range: '5.000 - 15.000 locuri', filter: '5000-15000', count: this.venues.filter(v => (v.capacity || 0) >= 5000 && (v.capacity || 0) < 15000).length },
            { name: 'Mare', range: '15.000 - 35.000 locuri', filter: '15000-30000', count: this.venues.filter(v => (v.capacity || 0) >= 15000 && (v.capacity || 0) < 35000).length },
            { name: 'Foarte mare', range: 'Peste 35.000 locuri', filter: '30000+', count: this.venues.filter(v => (v.capacity || 0) >= 35000).length },
        ];

        document.getElementById('capacityTiers').innerHTML = tiers.map(tier => `
            <div class="p-5 text-center transition-all rounded-xl bg-slate-50 hover:bg-red-50 hover:-translate-y-0.5 cursor-pointer" onclick="document.getElementById('filterCapacity').value='${tier.filter}'; VenueTypePage.filterVenues();">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-white rounded-full shadow-sm">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="font-bold text-slate-800">${tier.name}</div>
                <div class="mb-2 text-sm text-slate-500">${tier.range}</div>
                <div class="text-sm font-semibold text-primary">${tier.count} locații</div>
            </div>
        `).join('');
    },

    loadEvents() {
        const mockEvents = [
            { name: 'Coldplay - Music of the Spheres Tour', slug: 'coldplay-tour', date: '15 Iunie 2025', time: '20:00', venue: 'Arena Națională', price: 350, gradient: 'from-indigo-500 to-purple-600' },
            { name: 'UNTOLD Festival 2025', slug: 'untold-2025', date: '7-10 August 2025', venue: 'Cluj Arena', price: 499, gradient: 'from-emerald-500 to-teal-600' },
            { name: 'FCSB vs CFR Cluj', slug: 'fcsb-cfr', date: '12 Aprilie 2025', time: '18:00', venue: 'Arena Națională', price: 45, gradient: 'from-rose-500 to-pink-600' },
            { name: 'Subcarpați - Concert Aniversar', slug: 'subcarpati-15', date: '20 Aprilie 2025', time: '21:00', venue: 'Arenele Romane', price: 120, gradient: 'from-amber-400 to-yellow-500' },
        ];

        if (mockEvents.length > 0) {
            document.getElementById('eventsSection').classList.remove('hidden');
            document.getElementById('totalEventsCount').textContent = mockEvents.length;
            document.getElementById('eventsScroll').innerHTML = mockEvents.map(e => `
                <article class="flex-shrink-0 w-[300px] overflow-hidden bg-white border rounded-2xl border-slate-200 hover:-translate-y-0.5 hover:shadow-lg transition-all group">
                    <a href="/bilete/${e.slug}">
                        <div class="relative h-[140px] bg-gradient-to-br ${e.gradient} flex items-center justify-center">
                            <svg class="w-9 h-9 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                            <span class="absolute px-2 py-1 text-[10px] font-semibold text-white bg-black/70 backdrop-blur-sm bottom-2 left-2 rounded">${e.venue}</span>
                        </div>
                        <div class="p-4">
                            <div class="mb-1 text-[11px] font-semibold tracking-wider uppercase text-primary">${e.date}${e.time ? ' • ' + e.time : ''}</div>
                            <h3 class="mb-3 text-[15px] font-bold leading-snug text-slate-800 line-clamp-2 group-hover:text-primary">${e.name}</h3>
                            <div class="flex items-center justify-between">
                                <span class="text-[15px] font-bold text-slate-800">${e.price} RON <span class="text-xs font-normal text-slate-400">de la</span></span>
                                <button class="px-3 py-1.5 text-xs font-semibold text-white rounded-md bg-gradient-to-r from-primary to-red-700">Bilete</button>
                            </div>
                        </div>
                    </a>
                </article>
            `).join('');
        }
    },

    bindEvents() {
        document.getElementById('filterCity')?.addEventListener('change', () => this.filterVenues());
        document.getElementById('filterCapacity')?.addEventListener('change', () => this.filterVenues());

        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.filter-chip').forEach(c => {
                    c.classList.remove('active', 'bg-primary', 'text-white');
                    c.classList.add('bg-slate-100', 'text-slate-600');
                });
                chip.classList.add('active', 'bg-primary', 'text-white');
                chip.classList.remove('bg-slate-100', 'text-slate-600');
                this.filterVenues();
            });
        });

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.view-btn').forEach(b => {
                    b.classList.remove('active', 'bg-white', 'shadow-sm', 'text-primary');
                    b.classList.add('text-slate-400');
                });
                btn.classList.add('active', 'bg-white', 'shadow-sm', 'text-primary');
                btn.classList.remove('text-slate-400');
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => VenueTypePage.init());
</script>

<?php include __DIR__ . '/includes/scripts.php'; ?>
