<?php
/**
 * TICS.ro - Event Detail Page
 *
 * Single event view with tickets, gallery, info tabs, AI suggestions
 * URL: /bilete/{slug} or /eveniment/{slug}
 */

// Initialize
require_once __DIR__ . '/includes/config.php';

// Get event slug from URL (query string from .htaccess rewrite)
$eventSlug = $_GET['slug'] ?? '';

// For demo, create sample event data (in production, fetch from API)
$event = [
    'id' => 1,
    'slug' => $eventSlug ?: 'coldplay-music-of-the-spheres-bucuresti',
    'name' => 'Coldplay: Music of the Spheres World Tour',
    'short_description' => 'Coldplay revine în România cu turneul mondial "Music of the Spheres"!',
    'description' => '<p>Coldplay revine în România cu turneul mondial "Music of the Spheres"! După succesul răsunător din 2024, formația britanică aduce din nou pe Arena Națională un spectacol de neuitat, plin de efecte vizuale spectaculoase, brățări LED interactive și hituri legendare.</p><p>Turneul "Music of the Spheres" este considerat unul dintre cele mai impresionante spectacole live din lume, combinând muzica extraordinară cu tehnologie de ultimă generație și un angajament ferm pentru sustenabilitate.</p>',
    'image_url' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1600&h=600&fit=crop',
    'gallery' => [
        'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200&h=140&fit=crop',
        'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=200&h=140&fit=crop',
        'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200&h=140&fit=crop',
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200&h=140&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=200&h=140&fit=crop',
    ],
    'starts_at' => '2026-02-14T20:00:00',
    'ends_at' => '2026-02-14T23:00:00',
    'doors_open' => '18:00',
    'duration' => '~3 ore',
    'age_restriction' => '16+',
    'category' => ['name' => 'Concert', 'slug' => 'concerte'],
    'venue' => [
        'name' => 'Arena Națională',
        'address' => 'Bulevardul Basarabia 37-39',
        'city' => 'București',
        'postcode' => '022103',
        'lat' => 44.4378,
        'lng' => 26.1546
    ],
    'organizer' => [
        'name' => 'Live Nation România',
        'logo' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop',
        'events_count' => 124,
        'verified' => true
    ],
    'artists' => [
        ['name' => 'Coldplay', 'type' => 'headliner', 'genre' => 'Rock Alternativ', 'image' => 'https://images.unsplash.com/photo-1499364615650-ec38552f4f34?w=200&h=200&fit=crop', 'listeners' => '102M+'],
        ['name' => 'H.E.R.', 'type' => 'special_guest', 'genre' => 'R&B, Soul', 'image' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?w=150&h=150&fit=crop'],
        ['name' => 'London Grammar', 'type' => 'opening', 'genre' => 'Indie, Electronic', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=150&h=150&fit=crop'],
    ],
    'tickets' => [
        ['id' => 1, 'name' => 'General Admission', 'description' => 'Acces în zona de standing', 'price' => 349, 'available' => true, 'remaining' => 2500],
        ['id' => 2, 'name' => 'Tribuna 1', 'description' => 'Loc numerotat, vedere excelentă', 'price' => 549, 'available' => true, 'remaining' => 50],
        ['id' => 3, 'name' => 'VIP Experience', 'description' => 'Golden Circle + Meet & Greet', 'price' => 1499, 'available' => true, 'remaining' => 20, 'vip' => true, 'perks' => ['Golden Circle (primele rânduri)', 'Meet & Greet cu trupa', 'Merch exclusiv inclus', 'Open bar & catering']],
    ],
    'sold_percentage' => 78,
    'total_capacity' => 20000,
    'views' => 124500,
    'favorites' => 12400,
    'going' => 8200,
    'ai_match' => 98,
    'trending' => true
];

// Calculate remaining tickets
$remainingTickets = $event['total_capacity'] * (100 - $event['sold_percentage']) / 100;

// Romanian months array for formatting
$MONTHS = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Format date
$eventDate = new DateTime($event['starts_at']);
$eventDateFormatted = $eventDate->format('d') . ' ' . $MONTHS[$eventDate->format('n') - 1] . ' ' . $eventDate->format('Y');
$eventTime = $eventDate->format('H:i');
$eventDayName = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'][$eventDate->format('w')];

// Page metadata
$pageTitle = $event['name'];
$pageDescription = $event['short_description'];
$pageImage = $event['image_url'];
$pageType = 'event';
$pageData = [
    'name' => $event['name'],
    'description' => $event['short_description'],
    'image' => $event['image_url'],
    'startDate' => $event['starts_at'],
    'endDate' => $event['ends_at'],
    'venue' => $event['venue'],
    'artists' => $event['artists'],
    'organizer' => $event['organizer']['name'],
    'minPrice' => min(array_column($event['tickets'], 'price')),
    'maxPrice' => max(array_column($event['tickets'], 'price')),
];
$currentPage = 'event';
$hideCategoriesBar = true;

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Evenimente', 'url' => '/evenimente'],
    ['name' => $event['category']['name'], 'url' => '/evenimente/' . $event['category']['slug']],
    ['name' => $event['name']]
];

// Include head
require_once __DIR__ . '/includes/head.php';
?>

    <!-- Toast Notification -->
    <div id="toast" class="toast fixed top-20 right-4 z-[60] bg-gray-900 text-white px-5 py-4 rounded-xl shadow-2xl flex items-center gap-3 max-w-sm transform translate-x-full transition-transform duration-300">
        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold" id="toastTitle">Adăugat în coș!</p>
            <p class="text-sm text-gray-300" id="toastMessage">2x General Admission</p>
        </div>
        <button onclick="TicsEventPage.hideToast()" class="ml-auto p-1 hover:bg-white/10 rounded-full">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="/evenimente" class="p-2 hover:bg-gray-100 rounded-full transition-all hover:scale-110">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <a href="/" class="flex items-center gap-2 group">
                        <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                            <span class="text-white font-bold text-sm">T</span>
                        </div>
                        <span class="font-bold text-lg hidden sm:block">TICS</span>
                    </a>
                </div>

                <div class="flex items-center gap-1">
                    <button class="p-2.5 rounded-full hover:bg-gray-100 transition-all" onclick="TicsEventPage.share()">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                    </button>
                    <button id="favoriteBtn" class="p-2.5 rounded-full hover:bg-gray-100 transition-all" onclick="TicsEventPage.toggleFavorite()">
                        <svg id="favoriteIcon" class="w-5 h-5 text-gray-600 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                    <button onclick="TicsEventPage.openCart()" class="relative flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition-all group">
                        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cartBadge" class="w-5 h-5 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center transition-all">0</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero with Countdown -->
    <section class="relative">
        <div class="aspect-[21/9] sm:aspect-[3/1] lg:aspect-[4/1] relative overflow-hidden">
            <img src="<?= e($event['image_url']) ?>" alt="<?= e($event['name']) ?>" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
        </div>

        <div class="absolute bottom-0 left-0 right-0 p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="px-3 py-1 bg-white/20 backdrop-blur text-white text-xs font-medium rounded-full"><?= e($event['category']['name']) ?></span>
                    <?php if ($event['ai_match']): ?>
                    <span class="px-3 py-1 bg-green-500/90 text-white text-xs font-medium rounded-full flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                        <?= $event['ai_match'] ?>% AI Match
                    </span>
                    <?php endif; ?>
                    <?php if ($event['trending']): ?>
                    <span class="px-3 py-1 bg-gradient-to-r from-red-500 to-orange-500 text-white text-xs font-medium rounded-full">🔥 Trending</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-2"><?= e($event['name']) ?></h1>
                <p class="text-white/80 text-sm sm:text-base mb-4"><?= e($event['venue']['name']) ?>, <?= e($event['venue']['city']) ?></p>

                <!-- Countdown -->
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-white/60 text-sm">Începe în:</span>
                    <div class="flex gap-2" id="countdown">
                        <div class="px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countDays">--</span>
                            <span class="text-white/60 text-xs block">zile</span>
                        </div>
                        <div class="px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countHours">--</span>
                            <span class="text-white/60 text-xs block">ore</span>
                        </div>
                        <div class="px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countMins">--</span>
                            <span class="text-white/60 text-xs block">min</span>
                        </div>
                        <div class="px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countSecs">--</span>
                            <span class="text-white/60 text-xs block">sec</span>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="flex flex-wrap items-center gap-4 text-white/70 text-sm">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?= number_format($event['views'] / 1000, 1) ?>k vizualizări
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <?= number_format($event['favorites'] / 1000, 1) ?>k favorite
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <?= number_format($event['going'] / 1000, 1) ?>k merg
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Strip -->
    <?php if (!empty($event['gallery'])): ?>
    <div class="bg-gray-900 py-4 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex gap-3 overflow-x-auto no-scrollbar">
                <?php foreach ($event['gallery'] as $i => $image): ?>
                <button class="gallery-thumb w-20 h-14 rounded-lg overflow-hidden flex-shrink-0 <?= $i === 0 ? 'ring-2 ring-white ring-offset-2 ring-offset-gray-900' : 'opacity-70 hover:opacity-100' ?>" onclick="TicsEventPage.setGalleryImage(<?= $i ?>)">
                    <img src="<?= e($image) ?>" class="w-full h-full object-cover">
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Column -->
            <div class="flex-1 min-w-0">
                <!-- Quick Info -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-5 mb-8 border border-gray-200">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="text-center sm:text-left">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">📅 Data</p>
                            <p class="font-semibold text-gray-900"><?= e($eventDateFormatted) ?></p>
                            <p class="text-sm text-gray-500"><?= e($eventDayName) ?></p>
                        </div>
                        <div class="text-center sm:text-left">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">🕐 Ora</p>
                            <p class="font-semibold text-gray-900"><?= e($eventTime) ?></p>
                            <p class="text-sm text-gray-500">Porți: <?= e($event['doors_open']) ?></p>
                        </div>
                        <div class="text-center sm:text-left">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">⏱️ Durată</p>
                            <p class="font-semibold text-gray-900"><?= e($event['duration']) ?></p>
                            <p class="text-sm text-gray-500">Cu pauză</p>
                        </div>
                        <div class="text-center sm:text-left">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">👤 Vârstă</p>
                            <p class="font-semibold text-gray-900"><?= e($event['age_restriction']) ?></p>
                            <p class="text-sm text-gray-500">Cu adult</p>
                        </div>
                    </div>
                </div>

                <!-- Sold Progress -->
                <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-xl p-4 mb-8 border border-red-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-900">⚡ Bilete vândute</span>
                        <span class="text-sm font-bold text-red-600"><?= $event['sold_percentage'] ?>%</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-red-500 to-orange-500 rounded-full transition-all duration-1000" style="width: <?= $event['sold_percentage'] ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Rămân doar ~<?= number_format($remainingTickets) ?> bilete din <?= number_format($event['total_capacity']) ?></p>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-8">
                    <nav class="flex gap-6 overflow-x-auto no-scrollbar">
                        <button class="tab-btn active pb-4 text-sm font-medium whitespace-nowrap" data-tab="descriere">Descriere</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="lineup">Line-up</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="locatie">Locație</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="faq">FAQ</button>
                    </nav>
                </div>

                <!-- Tab Contents -->
                <section id="tab-descriere" class="tab-content mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Despre eveniment</h2>
                    <div class="prose prose-gray max-w-none text-gray-600">
                        <?= $event['description'] ?>
                    </div>
                </section>

                <section id="tab-lineup" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Line-up</h2>

                    <?php foreach ($event['artists'] as $artist): ?>
                    <?php if ($artist['type'] === 'headliner'): ?>
                    <!-- Headliner -->
                    <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 rounded-2xl p-6 mb-4 relative overflow-hidden">
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-900 text-xs font-bold rounded-full mb-4">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            HEADLINER
                        </span>
                        <div class="flex items-center gap-5">
                            <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="w-28 h-28 sm:w-36 sm:h-36 rounded-xl object-cover ring-4 ring-white/10">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-1"><?= e($artist['name']) ?></h3>
                                <p class="text-gray-400 text-sm mb-3"><?= e($artist['genre']) ?></p>
                                <?php if (isset($artist['listeners'])): ?>
                                <p class="text-gray-500 text-sm"><?= e($artist['listeners']) ?> ascultători lunari pe Spotify</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Support Act -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
                        <div class="flex items-center gap-4">
                            <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="w-16 h-16 rounded-xl object-cover">
                            <div class="flex-1 min-w-0">
                                <span class="text-xs <?= $artist['type'] === 'special_guest' ? 'text-indigo-600' : 'text-amber-600' ?> font-medium">
                                    <?= $artist['type'] === 'special_guest' ? 'Special Guest' : 'Opening Act' ?>
                                </span>
                                <h4 class="font-semibold text-gray-900"><?= e($artist['name']) ?></h4>
                                <p class="text-sm text-gray-500"><?= e($artist['genre']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <section id="tab-locatie" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Locație</h2>
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="aspect-video bg-gray-100 relative">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2849.3893!2d<?= $event['venue']['lng'] ?>!3d<?= $event['venue']['lat'] ?>!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z<?= urlencode($event['venue']['name']) ?>!5e0!3m2!1sen!2sro!4v<?= time() ?>" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" class="absolute inset-0"></iframe>
                        </div>
                        <div class="p-5">
                            <div class="flex items-start gap-4">
                                <div class="w-14 h-14 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-7 h-7 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900 text-lg mb-1"><?= e($event['venue']['name']) ?></h3>
                                    <p class="text-gray-500 text-sm mb-3"><?= e($event['venue']['address']) ?>, <?= e($event['venue']['city']) ?> <?= e($event['venue']['postcode']) ?></p>
                                </div>
                                <a href="https://maps.google.com?q=<?= urlencode($event['venue']['name'] . ', ' . $event['venue']['city']) ?>" target="_blank" class="px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition-all">
                                    Direcții →
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="tab-faq" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Întrebări frecvente</h2>
                    <div class="space-y-3">
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group" open>
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50">
                                Ce pot aduce la eveniment?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                <p class="mb-2"><strong class="text-green-600">✓ Permis:</strong> telefoane mobile, portofele mici, sticle de apă goale (max 500ml).</p>
                                <p><strong class="text-red-600">✗ Interzis:</strong> camere profesionale, umbrele, sticle de sticlă, alimente, droguri, arme.</p>
                            </div>
                        </details>
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50">
                                Pot returna biletele?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                Biletele pot fi returnate cu până la 14 zile înainte de eveniment. Se reține un comision de 10% din valoare.
                            </div>
                        </details>
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50">
                                Ce fac dacă plouă?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                Evenimentul are loc indiferent de condițiile meteo. Recomandăm să aduceți pelerină de ploaie.
                            </div>
                        </details>
                    </div>
                </section>
            </div>

            <!-- Right Column - Tickets -->
            <div class="lg:w-[420px] flex-shrink-0">
                <div class="sticky top-24">
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xl">
                        <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <div class="flex items-center justify-between mb-2">
                                <h2 class="text-lg font-bold text-gray-900">🎫 Alege bilete</h2>
                                <span class="text-xs text-red-500 font-medium flex items-center gap-1">
                                    <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                    <?= $event['sold_percentage'] ?>% sold
                                </span>
                            </div>
                            <p class="text-sm text-gray-500">Selectează tipul și cantitatea</p>
                        </div>

                        <div class="p-5 space-y-3" id="ticketContainer">
                            <?php foreach ($event['tickets'] as $index => $ticket): ?>
                            <div class="ticket-card <?= $index === 0 ? 'selected' : '' ?> border-2 <?= $index === 0 ? 'border-gray-900' : 'border-gray-200' ?> rounded-xl p-4 cursor-pointer transition-all hover:shadow-lg" data-price="<?= $ticket['price'] ?>" data-name="<?= e($ticket['name']) ?>" data-id="<?= $ticket['id'] ?>">
                                <?php if (isset($ticket['vip']) && $ticket['vip']): ?>
                                <div class="absolute top-0 right-0">
                                    <div class="px-4 py-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs font-bold rounded-bl-lg">⭐ VIP</div>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900"><?= e($ticket['name']) ?></h3>
                                        <p class="text-sm text-gray-500"><?= e($ticket['description']) ?></p>
                                    </div>
                                    <?php if ($ticket['remaining'] <= 50): ?>
                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/></svg>
                                        Ultimele <?= $ticket['remaining'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Disponibil</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($ticket['perks']) && !empty($ticket['perks'])): ?>
                                <ul class="text-xs text-gray-500 mb-4 space-y-1.5">
                                    <?php foreach ($ticket['perks'] as $perk): ?>
                                    <li class="flex items-center gap-2">
                                        <span class="w-4 h-4 bg-green-100 rounded-full flex items-center justify-center"><svg class="w-2.5 h-2.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                                        <?= e($perk) ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xl font-bold text-gray-900"><?= number_format($ticket['price'], 0, ',', '.') ?> RON</span>
                                        <span class="text-xs text-gray-400 ml-1">/ bilet</span>
                                    </div>
                                    <div class="flex items-center gap-2 bg-gray-100 rounded-full p-1">
                                        <button class="qty-btn w-8 h-8 rounded-full border border-gray-300 bg-white flex items-center justify-center font-bold text-gray-600 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-all" onclick="event.stopPropagation(); TicsEventPage.changeQty(<?= $ticket['id'] ?>, -1)">−</button>
                                        <span class="w-8 text-center font-bold qty-display" data-ticket-id="<?= $ticket['id'] ?>">0</span>
                                        <button class="qty-btn w-8 h-8 rounded-full border border-gray-300 bg-white flex items-center justify-center font-bold text-gray-600 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-all" onclick="event.stopPropagation(); TicsEventPage.changeQty(<?= $ticket['id'] ?>, 1)">+</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Total & CTA -->
                        <div class="p-5 bg-gradient-to-br from-gray-50 to-gray-100 border-t border-gray-200">
                            <div class="flex items-center justify-between mb-4 pt-2">
                                <div>
                                    <p class="text-sm text-gray-500">Total (<span id="totalTickets">0</span> bilete)</p>
                                    <p class="text-3xl font-bold text-gray-900" id="totalPrice">0 RON</p>
                                </div>
                            </div>
                            <button onclick="TicsEventPage.addToCart()" id="addToCartBtn" class="w-full py-4 bg-gradient-to-r from-gray-900 to-gray-800 text-white font-bold rounded-xl hover:from-gray-800 hover:to-gray-700 transition-all text-lg shadow-xl shadow-gray-900/20 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                Adaugă în coș
                            </button>
                            <p class="text-xs text-gray-400 text-center mt-3 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Plată securizată • Bilete garantate
                            </p>
                        </div>
                    </div>

                    <!-- Organizer -->
                    <div class="mt-4 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-lg transition-shadow">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Organizator</p>
                        <div class="flex items-center gap-3">
                            <img src="<?= e($event['organizer']['logo']) ?>" alt="<?= e($event['organizer']['name']) ?>" class="w-12 h-12 rounded-xl object-cover">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 flex items-center gap-1">
                                    <?= e($event['organizer']['name']) ?>
                                    <?php if ($event['organizer']['verified']): ?>
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-sm text-gray-500"><?= $event['organizer']['events_count'] ?> evenimente organizate</p>
                            </div>
                            <button class="px-4 py-2 border border-gray-200 text-sm font-medium rounded-full hover:bg-gray-50 transition-all">
                                Contact
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="<?= asset('assets/js/api.js') ?>"></script>
    <script src="<?= asset('assets/js/utils.js') ?>"></script>
    <script src="<?= asset('assets/js/pages/event-page.js') ?>"></script>

    <script>
        // Initialize event page
        document.addEventListener('DOMContentLoaded', function() {
            // Pass event data to JS
            const eventData = <?= json_encode([
                'id' => $event['id'],
                'slug' => $event['slug'],
                'name' => $event['name'],
                'starts_at' => $event['starts_at'],
                'tickets' => $event['tickets']
            ]) ?>;

            if (typeof TicsEventPage !== 'undefined') {
                TicsEventPage.init(eventData);
            }
        });
    </script>
</body>
</html>
