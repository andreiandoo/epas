<?php
/**
 * TICS.ro - Event Detail Page
 *
 * Single event view with tickets, gallery, info tabs, AI suggestions
 * URL: /bilete/{event-slug}-{city-slug}
 */

// Initialize
require_once __DIR__ . '/includes/config.php';

// Get event slug from URL - new format includes city at the end
// URL: /bilete/concert-coldplay-bucuresti -> fullslug=concert-coldplay-bucuresti
$fullSlug = $_GET['fullslug'] ?? $_GET['slug'] ?? '';

// Parse the full slug to extract event slug and city slug
// Known city slugs from config
$knownCitySlugs = array_column($FEATURED_CITIES, 'slug');

$eventSlug = $fullSlug;
$citySlug = '';

// Try to find city slug at the end
foreach ($knownCitySlugs as $city) {
    if (preg_match('/-' . preg_quote($city, '/') . '$/', $fullSlug)) {
        $eventSlug = preg_replace('/-' . preg_quote($city, '/') . '$/', '', $fullSlug);
        $citySlug = $city;
        break;
    }
}

// For demo, create sample event data (in production, fetch from API)
$event = [
    'id' => 1,
    'slug' => $eventSlug ?: 'coldplay-music-of-the-spheres-bucuresti',
    'name' => 'Coldplay: Music of the Spheres World Tour',
    'short_description' => 'Coldplay revine în România cu turneul mondial "Music of the Spheres"!',
    'description' => '<p>Coldplay revine în România cu turneul mondial "Music of the Spheres"! După succesul răsunător din 2024, formația britanică aduce din nou pe Arena Națională un spectacol de neuitat, plin de efecte vizuale spectaculoase, brățări LED interactive și hituri legendare.</p><p>Turneul "Music of the Spheres" este considerat unul dintre cele mai impresionante spectacole live din lume, combinând muzica extraordinară cu tehnologie de ultimă generație și un angajament ferm pentru sustenabilitate.</p>',
    'setlist' => ['Yellow', 'The Scientist', 'Fix You', 'Viva la Vida', 'Paradise', 'A Sky Full of Stars'],
    'rules' => [
        'allowed' => ['Telefoane mobile', 'Portofele mici', 'Sticle de apă goale (max 500ml)', 'Aparate foto compacte', 'Baterii externe mici'],
        'forbidden' => ['Camere profesionale', 'Umbrele', 'Sticle de sticlă', 'Alimente și băuturi', 'Droguri', 'Arme', 'Artificii', 'Obiecte ascuțite', 'Bastoane selfie']
    ],
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
        'lng' => 26.1546,
        'transport' => [
            'metro' => 'Piața Muncii (M1, M3)',
            'bus' => '311, 330, 335',
            'parking' => '20 RON'
        ],
        'facilities' => [
            ['icon' => 'wifi', 'name' => 'WiFi Gratuit', 'description' => 'Internet wireless în toate zonele'],
            ['icon' => 'parking', 'name' => 'Parcare', 'description' => '5000+ locuri de parcare'],
            ['icon' => 'accessibility', 'name' => 'Accesibilitate', 'description' => 'Rampe și locuri dedicate'],
            ['icon' => 'food', 'name' => 'Food & Drinks', 'description' => 'Zone de alimentație'],
            ['icon' => 'toilet', 'name' => 'Toalete', 'description' => 'Toalete moderne pe tot stadionul'],
            ['icon' => 'atm', 'name' => 'ATM', 'description' => 'Bancomate disponibile'],
            ['icon' => 'medical', 'name' => 'Punct Medical', 'description' => 'Asistență medicală de urgență'],
            ['icon' => 'locker', 'name' => 'Vestiare', 'description' => 'Garderobă disponibilă'],
            ['icon' => 'smoking', 'name' => 'Zonă Fumători', 'description' => 'Zone desemnate pentru fumători'],
            ['icon' => 'info', 'name' => 'Punct Info', 'description' => 'Informații și asistență']
        ]
    ],
    'organizer' => [
        'name' => 'Live Nation România',
        'logo' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop',
        'events_count' => 124,
        'verified' => true
    ],
    'artists' => [
        [
            'name' => 'Coldplay',
            'slug' => 'coldplay',
            'type' => 'headliner',
            'genre' => 'Rock Alternativ',
            'country' => 'United Kingdom',
            'image' => 'https://images.unsplash.com/photo-1499364615650-ec38552f4f34?w=200&h=200&fit=crop',
            'listeners' => '102M+',
            'verified' => true,
            'social' => [
                'facebook' => 'https://facebook.com/coldplay',
                'instagram' => 'https://instagram.com/coldplay',
                'youtube' => 'https://youtube.com/coldplay',
                'spotify' => 'https://open.spotify.com/artist/coldplay'
            ]
        ],
        [
            'name' => 'H.E.R.',
            'slug' => 'her',
            'type' => 'special_guest',
            'genre' => 'R&B, Soul',
            'country' => 'United States',
            'image' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?w=150&h=150&fit=crop',
            'listeners' => '25M+',
            'social' => [
                'instagram' => 'https://instagram.com/hermusicofficial',
                'spotify' => 'https://open.spotify.com/artist/her'
            ]
        ],
        [
            'name' => 'London Grammar',
            'slug' => 'london-grammar',
            'type' => 'opening',
            'genre' => 'Indie, Electronic',
            'country' => 'United Kingdom',
            'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=150&h=150&fit=crop',
            'listeners' => '8M+',
            'social' => [
                'instagram' => 'https://instagram.com/londongrammar',
                'spotify' => 'https://open.spotify.com/artist/londongrammar'
            ]
        ],
    ],
    'tickets' => [
        [
            'id' => 1,
            'name' => 'General Admission',
            'description' => 'Acces în zona de standing',
            'price' => 349,
            'base_price' => 299,
            'service_fee' => 35,
            'platform_fee' => 15,
            'fees_included' => false,
            'available' => true,
            'remaining' => 2500
        ],
        [
            'id' => 2,
            'name' => 'Tribuna 1',
            'description' => 'Loc numerotat, vedere excelentă',
            'price' => 549,
            'base_price' => 479,
            'service_fee' => 50,
            'platform_fee' => 20,
            'fees_included' => false,
            'available' => true,
            'remaining' => 50
        ],
        [
            'id' => 3,
            'name' => 'VIP Experience',
            'description' => 'Golden Circle + Meet & Greet',
            'price' => 1499,
            'base_price' => 1499,
            'service_fee' => 0,
            'platform_fee' => 0,
            'fees_included' => true,
            'available' => true,
            'remaining' => 20,
            'vip' => true,
            'perks' => ['Golden Circle (primele rânduri)', 'Meet & Greet cu trupa', 'Merch exclusiv inclus', 'Open bar & catering']
        ],
    ],
    'hotels' => [
        [
            'name' => 'Radisson Blu Hotel',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300&h=200&fit=crop',
            'distance' => '500m de Arena',
            'rating' => '4.5',
            'reviews' => '2.3k',
            'price' => 450,
            'old_price' => 530,
            'discount' => '-15%',
            'badge' => null
        ],
        [
            'name' => 'JW Marriott',
            'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=300&h=200&fit=crop',
            'distance' => '2km de Arena',
            'rating' => '4.8',
            'reviews' => '4.1k',
            'price' => 680,
            'old_price' => null,
            'discount' => null,
            'badge' => 'Popular'
        ],
        [
            'name' => 'Arena Apartments',
            'image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=300&h=200&fit=crop',
            'distance' => '300m de Arena',
            'rating' => '4.6',
            'reviews' => '890',
            'price' => 280,
            'old_price' => null,
            'discount' => null,
            'badge' => 'Best Value'
        ]
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

    <!-- Directions Modal -->
    <div id="directionsModal" class="fixed inset-0 z-[70] hidden">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="TicsEventPage.closeDirectionsModal()"></div>

        <!-- Modal Content -->
        <div class="absolute inset-4 sm:inset-auto sm:left-1/2 sm:top-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">🗺️ Planifică traseul</h3>
                    <p class="text-sm text-gray-500">Găsește cel mai bun traseu către <?= e($event['venue']['name']) ?></p>
                </div>
                <button onclick="TicsEventPage.closeDirectionsModal()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 overflow-y-auto flex-1">
                <!-- Starting Point Input -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Punct de plecare</label>
                    <div class="relative">
                        <input type="text" id="directionsOrigin" placeholder="Introdu adresa ta..." class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <button onclick="TicsEventPage.useCurrentLocation()" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-indigo-600 transition-colors" title="Folosește locația curentă">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>
                    </div>
                    <p id="locationStatus" class="text-xs text-gray-400 mt-1 hidden"></p>
                </div>

                <!-- Destination (Fixed) -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Destinație</label>
                    <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900"><?= e($event['venue']['name']) ?></p>
                            <p class="text-sm text-gray-500"><?= e($event['venue']['address']) ?>, <?= e($event['venue']['city']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Transport Mode Selection -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mijloc de transport</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button onclick="TicsEventPage.setTransportMode('transit')" class="transport-mode-btn active p-3 bg-indigo-50 border-2 border-indigo-500 rounded-xl flex flex-col items-center gap-1 transition-all" data-mode="transit">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span class="text-xs font-medium text-indigo-700">Transport</span>
                        </button>
                        <button onclick="TicsEventPage.setTransportMode('driving')" class="transport-mode-btn p-3 bg-gray-50 border-2 border-gray-200 rounded-xl flex flex-col items-center gap-1 transition-all hover:border-gray-300" data-mode="driving">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8M8 17a2 2 0 11-4 0m4 0a2 2 0 104 0m4 0a2 2 0 11-4 0m4 0a2 2 0 104 0M3 11l2-6h14l2 6M5 11v6h14v-6"/></svg>
                            <span class="text-xs font-medium text-gray-600">Mașină</span>
                        </button>
                        <button onclick="TicsEventPage.setTransportMode('walking')" class="transport-mode-btn p-3 bg-gray-50 border-2 border-gray-200 rounded-xl flex flex-col items-center gap-1 transition-all hover:border-gray-300" data-mode="walking">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="text-xs font-medium text-gray-600">Pe jos</span>
                        </button>
                        <button onclick="TicsEventPage.setTransportMode('bicycling')" class="transport-mode-btn p-3 bg-gray-50 border-2 border-gray-200 rounded-xl flex flex-col items-center gap-1 transition-all hover:border-gray-300" data-mode="bicycling">
                            <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM5 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5zm5.8-10l2.4-2.4.8.8c1.3 1.3 3 2.1 5.1 2.1V9c-1.5 0-2.7-.6-3.6-1.5l-1.9-1.9c-.5-.4-1-.6-1.6-.6s-1.1.2-1.4.6L7.8 8.4c-.4.4-.6.9-.6 1.4 0 .6.2 1.1.6 1.4L11 14v5h2v-6.2l-2.2-2.3zM19 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                            <span class="text-xs font-medium text-gray-600">Bicicletă</span>
                        </button>
                    </div>
                </div>

                <!-- Search Button -->
                <button onclick="TicsEventPage.searchDirections()" id="searchDirectionsBtn" class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Caută traseu
                </button>

                <!-- Results Section -->
                <div id="directionsResults" class="mt-5 hidden">
                    <div class="border-t border-gray-100 pt-5">
                        <h4 class="font-semibold text-gray-900 mb-3">Opțiuni de traseu</h4>
                        <div id="directionsRoutesList" class="space-y-3">
                            <!-- Routes will be inserted here -->
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="directionsLoading" class="mt-5 hidden">
                    <div class="flex items-center justify-center gap-3 py-8">
                        <div class="loading-spinner w-6 h-6 border-2 border-gray-300 border-t-indigo-600 rounded-full animate-spin"></div>
                        <span class="text-sm text-gray-500">Se caută traseul...</span>
                    </div>
                </div>

                <!-- Error State -->
                <div id="directionsError" class="mt-5 hidden">
                    <div class="p-4 bg-red-50 rounded-xl border border-red-100">
                        <p class="text-sm text-red-700" id="directionsErrorMessage">A apărut o eroare. Încearcă din nou.</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">Powered by Google Maps</p>
                    <a href="https://maps.google.com?daddr=<?= urlencode($event['venue']['name'] . ', ' . $event['venue']['city']) ?>" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                        Deschide în Google Maps →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Hero with Countdown -->
    <section class="relative">
        <div class="aspect-[21/9] sm:aspect-[3/1] lg:aspect-[4/1] relative overflow-hidden">
            <!-- Image Carousel -->
            <?php foreach ($event['gallery'] as $i => $image): ?>
            <img src="<?= e(str_replace(['w=200&h=140', 'w=200', 'h=140'], ['w=1600&h=600', 'w=1600', 'h=600'], $image)) ?>"
                 alt="<?= e($event['name']) ?>"
                 class="hero-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 <?= $i === 0 ? 'opacity-100' : 'opacity-0' ?>"
                 data-slide="<?= $i ?>">
            <?php endforeach; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>

            <!-- Carousel Indicators -->
            <div class="absolute bottom-32 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                <?php foreach ($event['gallery'] as $i => $image): ?>
                <button onclick="TicsEventPage.goToSlide(<?= $i ?>)" class="hero-indicator w-2 h-2 rounded-full transition-all <?= $i === 0 ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70' ?>" data-indicator="<?= $i ?>"></button>
                <?php endforeach; ?>
            </div>

            <!-- Floating elements -->
            <div class="absolute top-10 right-10 w-20 h-20 bg-indigo-500/20 rounded-full blur-xl animate-float hidden lg:block"></div>
            <div class="absolute bottom-20 left-10 w-32 h-32 bg-purple-500/20 rounded-full blur-xl animate-float hidden lg:block" style="animation-delay: 1s"></div>
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
                        <div class="countdown-item px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countDays">--</span>
                            <span class="text-white/60 text-xs block">zile</span>
                        </div>
                        <div class="countdown-item px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countHours">--</span>
                            <span class="text-white/60 text-xs block">ore</span>
                        </div>
                        <div class="countdown-item px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countMins">--</span>
                            <span class="text-white/60 text-xs block">min</span>
                        </div>
                        <div class="countdown-item px-3 py-2 bg-gray-900/80 backdrop-blur rounded-lg text-center">
                            <span class="text-white font-bold text-lg" id="countSecs">--</span>
                            <span class="text-white/60 text-xs block">sec</span>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="flex flex-wrap items-center gap-4 text-white/70 text-sm">
                    <span class="flex items-center gap-1.5 hover:text-white transition-colors cursor-default">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?= number_format($event['views'] / 1000, 1) ?>k vizualizări
                    </span>
                    <span class="flex items-center gap-1.5 hover:text-white transition-colors cursor-default">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <?= number_format($event['favorites'] / 1000, 1) ?>k favorite
                    </span>
                    <span class="flex items-center gap-1.5 hover:text-white transition-colors cursor-default">
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
                <?php if (count($event['gallery']) > 5): ?>
                <button class="gallery-thumb w-20 h-14 rounded-lg overflow-hidden flex-shrink-0 opacity-70 hover:opacity-100 relative">
                    <img src="<?= e($event['gallery'][4]) ?>" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                        <span class="text-white text-sm font-medium">+<?= count($event['gallery']) - 5 ?></span>
                    </div>
                </button>
                <?php endif; ?>
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
                        <div class="progress-bar h-full bg-gradient-to-r from-red-500 to-orange-500 rounded-full transition-all duration-1000" style="width: <?= $event['sold_percentage'] ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Rămân doar ~<?= number_format($remainingTickets) ?> bilete din <?= number_format($event['total_capacity']) ?></p>
                </div>

                <!-- Follow Event -->
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100 mb-8">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center animate-float">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Urmărește evenimentul</p>
                            <p class="text-sm text-gray-500">Primești notificări despre noutăți și reduceri</p>
                        </div>
                    </div>
                    <button id="followBtn" onclick="TicsEventPage.toggleFollow()" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-full hover:bg-indigo-700 transition-all hover:scale-105 text-sm shadow-lg shadow-indigo-500/30">
                        Urmărește
                    </button>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-8">
                    <nav class="flex gap-6 overflow-x-auto no-scrollbar">
                        <button class="tab-btn active pb-4 text-sm font-medium whitespace-nowrap" data-tab="descriere">Descriere</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="lineup">Line-up</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="locatie">Locație</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="cazare">Cazare</button>
                        <button class="tab-btn pb-4 text-sm font-medium text-gray-500 whitespace-nowrap" data-tab="faq">FAQ</button>
                    </nav>
                </div>

                <!-- Tab Contents -->
                <section id="tab-descriere" class="tab-content mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Despre eveniment</h2>
                    <div class="prose prose-gray max-w-none text-gray-600">
                        <?= $event['description'] ?>
                    </div>

                    <?php if (!empty($event['setlist'])): ?>
                    <div class="bg-gray-50 rounded-xl p-4 mt-6">
                        <h4 class="font-semibold text-gray-900 mb-2">🎵 Setlist-ul include:</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (array_slice($event['setlist'], 0, 6) as $song): ?>
                            <span class="px-3 py-1 bg-white rounded-full text-sm text-gray-600 border"><?= e($song) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($event['setlist']) > 6): ?>
                            <span class="px-3 py-1 bg-gray-200 rounded-full text-sm text-gray-500">+<?= count($event['setlist']) - 6 ?> piese</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Event Rules Section -->
                    <?php if (!empty($event['rules'])): ?>
                    <div class="mt-8 border-t border-gray-200 pt-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">📋 Regulile evenimentului</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                                <h4 class="font-semibold text-green-800 mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Permis
                                </h4>
                                <ul class="space-y-2">
                                    <?php foreach ($event['rules']['allowed'] as $item): ?>
                                    <li class="text-sm text-green-700 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        <?= e($item) ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                                <h4 class="font-semibold text-red-800 mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Interzis
                                </h4>
                                <ul class="space-y-2">
                                    <?php foreach ($event['rules']['forbidden'] as $item): ?>
                                    <li class="text-sm text-red-700 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                                        <?= e($item) ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <section id="tab-lineup" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Line-up</h2>

                    <?php
                    $headliners = array_filter($event['artists'], fn($a) => $a['type'] === 'headliner');
                    $supportActs = array_filter($event['artists'], fn($a) => $a['type'] !== 'headliner');
                    ?>

                    <?php foreach ($headliners as $artist): ?>
                    <!-- Headliner -->
                    <a href="/artist/<?= e($artist['slug']) ?>" class="block bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 rounded-2xl p-6 mb-4 relative overflow-hidden hover:shadow-2xl transition-shadow">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-900 text-xs font-bold rounded-full mb-4">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            HEADLINER
                        </span>
                        <div class="flex items-center gap-5 relative z-10">
                            <div class="relative">
                                <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="w-28 h-28 sm:w-36 sm:h-36 rounded-xl object-cover ring-4 ring-white/10">
                                <?php if (isset($artist['verified']) && $artist['verified']): ?>
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center border-4 border-gray-900">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-1"><?= e($artist['name']) ?></h3>
                                <p class="text-gray-400 text-sm mb-3"><?= e($artist['genre']) ?> • <?= e($artist['country'] ?? '') ?></p>
                                <?php if (isset($artist['listeners'])): ?>
                                <p class="text-gray-500 text-sm mb-4"><?= e($artist['listeners']) ?> ascultători lunari pe Spotify</p>
                                <?php endif; ?>

                                <!-- Social Links -->
                                <?php if (!empty($artist['social'])): ?>
                                <div class="flex items-center gap-3" onclick="event.preventDefault(); event.stopPropagation();">
                                    <?php if (isset($artist['social']['facebook'])): ?>
                                    <a href="<?= e($artist['social']['facebook']) ?>" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/20 transition-all">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.99 22 12c0-5.523-4.477-10-10-10z"/></svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (isset($artist['social']['instagram'])): ?>
                                    <a href="<?= e($artist['social']['instagram']) ?>" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/20 transition-all">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0z"/></svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (isset($artist['social']['youtube'])): ?>
                                    <a href="<?= e($artist['social']['youtube']) ?>" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/20 transition-all">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (isset($artist['social']['spotify'])): ?>
                                    <a href="<?= e($artist['social']['spotify']) ?>" target="_blank" class="w-9 h-9 bg-[#1DB954] rounded-full flex items-center justify-center text-white hover:scale-110 transition-all">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02z"/></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>

                    <!-- Support Acts Grid -->
                    <?php if (!empty($supportActs)): ?>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <?php foreach ($supportActs as $artist): ?>
                        <a href="/artist/<?= e($artist['slug']) ?>" class="artist-card bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg transition-all">
                            <div class="flex items-center gap-4">
                                <div class="relative w-16 h-16 rounded-xl overflow-hidden flex-shrink-0">
                                    <img src="<?= e($artist['image']) ?>" alt="<?= e($artist['name']) ?>" class="w-full h-full object-cover artist-img">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs <?= $artist['type'] === 'special_guest' ? 'text-indigo-600' : 'text-amber-600' ?> font-medium">
                                        <?= $artist['type'] === 'special_guest' ? 'Special Guest' : 'Opening Act' ?>
                                    </span>
                                    <h4 class="font-semibold text-gray-900"><?= e($artist['name']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= e($artist['genre']) ?> • <?= e($artist['country'] ?? '') ?></p>
                                    <?php if (isset($artist['listeners'])): ?>
                                    <p class="text-xs text-gray-400 mt-1"><?= e($artist['listeners']) ?> ascultători lunari</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <section id="tab-locatie" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Locație</h2>
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="aspect-video bg-gray-100 relative">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2849.3893!2d<?= $event['venue']['lng'] ?>!3d<?= $event['venue']['lat'] ?>!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40b1ff427ed4a2b3%3A0x2b0c0e97e3f41e0!2sArena%20Na%C8%9Bional%C4%83!5e0!3m2!1sen!2sro!4v<?= time() ?>" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" class="absolute inset-0"></iframe>
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

                                    <!-- Transport Info -->
                                    <?php if (!empty($event['venue']['transport'])): ?>
                                    <div class="flex flex-wrap gap-2">
                                        <?php if (isset($event['venue']['transport']['metro'])): ?>
                                        <span class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium">🚇 Metrou: <?= e($event['venue']['transport']['metro']) ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($event['venue']['transport']['bus'])): ?>
                                        <span class="px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium">🚌 Bus: <?= e($event['venue']['transport']['bus']) ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($event['venue']['transport']['parking'])): ?>
                                        <span class="px-3 py-1.5 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium">🅿️ Parcare: <?= e($event['venue']['transport']['parking']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <button onclick="TicsEventPage.openDirectionsModal()" class="px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition-all hover:scale-105">
                                    Direcții →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Venue Facilities -->
                    <?php if (!empty($event['venue']['facilities'])): ?>
                    <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-semibold text-gray-900 text-lg mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Facilități
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <?php
                            // Facility icons mapping
                            $facilityIcons = [
                                'wifi' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>',
                                'parking' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v-4m0 0V6a2 2 0 012-2h2a2 2 0 012 2v4m-4 0h4m-4 4v4m0-4h4m-6-8v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2h3a2 2 0 012 2z"/></svg>',
                                'accessibility' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                                'food' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                                'toilet' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
                                'atm' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
                                'medical' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
                                'locker' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
                                'smoking' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2 16h15v3H2v-3zm18.5 0H22v3h-1.5v-3zM18 16h1.5v3H18v-3zm.85-8.27c.62-.61 1-1.45 1-2.38C19.85 3.5 18.35 2 16.5 2v1.5c1.02 0 1.85.83 1.85 1.85S17.52 7.2 16.5 7.2v1.5c2.24 0 4 1.83 4 4.07V15H22v-2.24c0-2.22-1.28-4.14-3.15-5.03zm-2.82 2.47H14.5c-.85 0-1.5.67-1.5 1.5V15h1.5v-2.8h1.53c2.46 0 4.32-1.86 4.32-4.4V6h-1.5v1.8c0 1.62-1.27 2.9-2.82 2.9z"/></svg>',
                                'info' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                            ];
                            ?>
                            <?php foreach ($event['venue']['facilities'] as $facility): ?>
                            <div class="facility-item group relative bg-gray-50 rounded-xl p-3 text-center hover:bg-gray-100 transition-colors cursor-help">
                                <div class="w-10 h-10 mx-auto bg-white rounded-xl flex items-center justify-center mb-2 text-gray-600 group-hover:text-indigo-600 transition-colors shadow-sm">
                                    <?= $facilityIcons[$facility['icon']] ?? $facilityIcons['info'] ?>
                                </div>
                                <p class="text-xs font-medium text-gray-700"><?= e($facility['name']) ?></p>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-10">
                                    <?= e($facility['description']) ?>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Cazare Tab -->
                <?php
                // Stay22 configuration
                $stay22AffiliateId = '68f75671f26bfb6f2a73d0b9';
                $stay22Lat = $event['venue']['lat'];
                $stay22Lng = $event['venue']['lng'];
                $stay22Address = urlencode($event['venue']['name'] . ', ' . $event['venue']['address'] . ', ' . $event['venue']['city']);
                // Set check-in to event date and checkout to next day
                $eventDateObj = new DateTime($event['starts_at']);
                $stay22Checkin = $eventDateObj->format('Y-m-d');
                $eventDateObj->modify('+1 day');
                $stay22Checkout = $eventDateObj->format('Y-m-d');
                ?>
                <section id="tab-cazare" class="tab-content hidden mb-10">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-900">Cazare în apropiere</h2>
                        <span class="text-xs text-gray-400 flex items-center gap-1">
                            Powered by <span class="font-semibold text-blue-600">Stay22</span>
                        </span>
                    </div>

                    <!-- Stay22 Widget -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <!-- Header Info -->
                        <div class="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center animate-float flex-shrink-0">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-lg">Rezervă cazare lângă <?= e($event['venue']['name']) ?></p>
                                    <p class="text-sm text-gray-500">Hoteluri, apartamente și pensiuni în apropiere de locație</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-3 mt-4">
                                <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-blue-200">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">Check-in: <strong><?= $stay22Checkin ?></strong></span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-blue-200">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">Check-out: <strong><?= $stay22Checkout ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Stay22 Map Widget -->
                        <div class="relative" style="height: 500px;">
                            <iframe
                                id="stay22-widget"
                                width="100%"
                                height="100%"
                                src="https://www.stay22.com/embed/gm?aid=<?= $stay22AffiliateId ?>&lat=<?= $stay22Lat ?>&lng=<?= $stay22Lng ?>&address=<?= $stay22Address ?>&checkin=<?= $stay22Checkin ?>&checkout=<?= $stay22Checkout ?>&maincolor=4f46e5&markertype=circle&zoom=14&currency=RON"
                                frameborder="0"
                                allowtransparency="true"
                                class="w-full h-full"
                                loading="lazy"
                            ></iframe>
                            <!-- Loading overlay -->
                            <div id="stay22-loading" class="absolute inset-0 bg-white flex items-center justify-center">
                                <div class="text-center">
                                    <div class="loading-spinner w-10 h-10 border-4 border-gray-200 border-t-indigo-600 rounded-full animate-spin mx-auto mb-3"></div>
                                    <p class="text-sm text-gray-500">Se încarcă opțiunile de cazare...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer with additional info -->
                        <div class="p-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <p class="text-xs text-gray-500">
                                    💡 Rezervă din timp pentru cele mai bune prețuri și disponibilitate
                                </p>
                                <a href="https://www.stay22.com/allez/booking?aid=<?= $stay22AffiliateId ?>&address=<?= $stay22Address ?>&checkin=<?= $stay22Checkin ?>&checkout=<?= $stay22Checkout ?>"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                    Vezi toate opțiunile
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <script>
                    // Hide loading overlay when Stay22 widget loads
                    document.getElementById('stay22-widget').addEventListener('load', function() {
                        const loadingEl = document.getElementById('stay22-loading');
                        if (loadingEl) {
                            loadingEl.style.display = 'none';
                        }
                    });
                </script>

                <section id="tab-faq" class="tab-content hidden mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Întrebări frecvente</h2>
                    <div class="space-y-3">
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group" open>
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                Ce pot aduce la eveniment?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                <p class="mb-2"><strong class="text-green-600">✓ Permis:</strong> telefoane mobile, portofele mici, sticle de apă goale (max 500ml), aparate foto compacte.</p>
                                <p><strong class="text-red-600">✗ Interzis:</strong> camere profesionale, umbrele, sticle de sticlă, alimente, droguri, arme.</p>
                            </div>
                        </details>
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                Pot returna biletele?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                Biletele pot fi returnate cu până la 14 zile înainte de eveniment. Se reține un comision de 10% din valoare.
                            </div>
                        </details>
                        <details class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                            <summary class="p-4 font-medium text-gray-900 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                Ce fac dacă plouă?
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 text-gray-600 text-sm">
                                Evenimentul are loc indiferent de condițiile meteo. Recomandăm să aduceți pelerină de ploaie (umbrelele nu sunt permise).
                            </div>
                        </details>
                    </div>
                </section>

                <!-- Social Share Section -->
                <section class="mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Distribuie</h2>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="TicsEventPage.shareToFacebook()" class="share-btn flex items-center gap-2 px-5 py-3 bg-[#1877F2] text-white rounded-xl hover:opacity-90 transition-all hover:scale-105 shadow-lg shadow-blue-500/25">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                            Facebook
                        </button>
                        <button onclick="TicsEventPage.shareToX()" class="share-btn flex items-center gap-2 px-5 py-3 bg-black text-white rounded-xl hover:opacity-90 transition-all hover:scale-105 shadow-lg shadow-gray-500/25">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            X
                        </button>
                        <button onclick="TicsEventPage.shareToWhatsApp()" class="share-btn flex items-center gap-2 px-5 py-3 bg-[#25D366] text-white rounded-xl hover:opacity-90 transition-all hover:scale-105 shadow-lg shadow-green-500/25">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            WhatsApp
                        </button>
                        <button onclick="TicsEventPage.copyLink()" class="share-btn flex items-center gap-2 px-5 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all hover:scale-105">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Copiază link
                        </button>
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
                            <div class="ticket-card <?= $index === 0 ? 'selected' : '' ?> border-2 <?= $index === 0 ? 'border-gray-900' : 'border-gray-200' ?> rounded-xl p-4 cursor-pointer transition-all hover:shadow-lg relative"
                                 data-price="<?= $ticket['price'] ?>"
                                 data-base-price="<?= $ticket['base_price'] ?>"
                                 data-service-fee="<?= $ticket['service_fee'] ?>"
                                 data-platform-fee="<?= $ticket['platform_fee'] ?>"
                                 data-fees-included="<?= $ticket['fees_included'] ? 'true' : 'false' ?>"
                                 data-name="<?= e($ticket['name']) ?>"
                                 data-id="<?= $ticket['id'] ?>">
                                <?php if (isset($ticket['vip']) && $ticket['vip']): ?>
                                <div class="absolute top-0 right-0">
                                    <div class="px-4 py-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs font-bold rounded-bl-lg">⭐ VIP</div>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold text-gray-900 ticket-name"><?= e($ticket['name']) ?></h3>
                                            <!-- Info Tooltip -->
                                            <div class="relative ticket-tooltip-container">
                                                <button class="w-5 h-5 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors flex-shrink-0 ticket-info-btn" onclick="event.stopPropagation();">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </button>
                                                <div class="ticket-tooltip absolute top-full mt-2 w-64 p-4 bg-gray-900 text-white text-xs rounded-xl shadow-2xl opacity-0 invisible transition-all duration-200 pointer-events-none" style="z-index: 9999;">
                                                    <p class="font-semibold mb-2">Detalii preț:</p>
                                                    <div class="space-y-1">
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-300">Preț bilet:</span>
                                                            <span><?= number_format($ticket['base_price'], 0, ',', '.') ?> RON</span>
                                                        </div>
                                                        <?php if ($ticket['service_fee'] > 0): ?>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-300">Taxă serviciu:</span>
                                                            <span><?= number_format($ticket['service_fee'], 0, ',', '.') ?> RON</span>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if ($ticket['platform_fee'] > 0): ?>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-300">Comision platformă:</span>
                                                            <span><?= number_format($ticket['platform_fee'], 0, ',', '.') ?> RON</span>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="border-t border-gray-700 pt-1 mt-1 flex justify-between font-semibold">
                                                            <span>Total:</span>
                                                            <span><?= number_format($ticket['price'], 0, ',', '.') ?> RON</span>
                                                        </div>
                                                    </div>
                                                    <?php if ($ticket['fees_included']): ?>
                                                    <p class="mt-2 text-green-400 text-xs">✓ Taxele sunt incluse în preț</p>
                                                    <?php else: ?>
                                                    <p class="mt-2 text-amber-400 text-xs">⚠ Taxele sunt adăugate la preț</p>
                                                    <?php endif; ?>
                                                    <div class="ticket-tooltip-arrow absolute -top-2 w-0 h-0 border-l-8 border-r-8 border-b-8 border-transparent border-b-gray-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1"><?= e($ticket['description']) ?></p>
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

                        <!-- Gift Points -->
                        <div class="mx-5 mb-5 p-4 bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 rounded-xl border border-amber-200 relative overflow-hidden" id="pointsSection" style="display: none;">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-amber-300/20 rounded-full blur-xl"></div>
                            <div class="flex items-center gap-3 relative z-10">
                                <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/30 animate-bounce" style="animation-duration: 2s">
                                    <span class="text-xl">🎁</span>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Câștigi <span class="text-amber-600" id="earnPoints">0</span> puncte!</p>
                                    <p class="text-xs text-gray-500">Ai <strong>1.250</strong> puncte • 500 pt = 50 RON</p>
                                </div>
                            </div>
                            <div class="mt-4 relative z-10">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 relative">
                                        <input type="text" id="promoCode" placeholder="Cod promoțional sau puncte" class="w-full px-4 py-2.5 text-sm bg-white border border-amber-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    </div>
                                    <button onclick="TicsEventPage.applyPromo()" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-sm font-semibold rounded-xl hover:from-amber-600 hover:to-orange-600 transition-all hover:scale-105 shadow-lg shadow-amber-500/25">
                                        Aplică
                                    </button>
                                </div>
                                <button onclick="TicsEventPage.usePoints()" class="w-full mt-2 py-2 text-sm text-amber-700 font-medium hover:bg-amber-100 rounded-lg transition-colors">
                                    Folosește 500 puncte (-50 RON) →
                                </button>
                            </div>
                        </div>

                        <!-- Total & CTA -->
                        <div class="p-5 bg-gradient-to-br from-gray-50 to-gray-100 border-t border-gray-200">
                            <!-- Tax Breakdown (shown when fees not included) -->
                            <div id="taxBreakdown" class="hidden mb-3 space-y-1 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Subtotal bilete</span>
                                    <span class="text-gray-700" id="baseSubtotal">0 RON</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Taxe serviciu</span>
                                    <span class="text-gray-700" id="serviceFees">0 RON</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Comision platformă</span>
                                    <span class="text-gray-700" id="platformFees">0 RON</span>
                                </div>
                                <div class="border-t border-gray-200 my-2"></div>
                            </div>

                            <div class="flex items-center justify-between mb-1" id="discountRow" style="display: none;">
                                <span class="text-sm text-green-600">Reducere puncte</span>
                                <span class="text-sm text-green-600" id="discountAmount">-50 RON</span>
                            </div>

                            <div class="flex items-center justify-between mb-4 pt-2">
                                <div>
                                    <p class="text-sm text-gray-500">Total (<span id="totalTickets">0</span> bilete)</p>
                                    <p class="text-3xl font-bold text-gray-900" id="totalPrice">0 RON</p>
                                </div>
                                <div class="text-right" id="installmentSection" style="display: none;">
                                    <p class="text-xs text-gray-400">sau de la</p>
                                    <p class="text-lg font-semibold text-gray-900" id="monthlyPrice">0 RON</p>
                                    <p class="text-xs text-gray-400">× 6 rate</p>
                                </div>
                            </div>
                            <button onclick="TicsEventPage.addToCart()" id="addToCartBtn" class="add-to-cart-btn w-full py-4 bg-gradient-to-r from-gray-900 to-gray-800 text-white font-bold rounded-xl hover:from-gray-800 hover:to-gray-700 transition-all text-lg shadow-xl shadow-gray-900/20 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
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
                            <button class="px-4 py-2 border border-gray-200 text-sm font-medium rounded-full hover:bg-gray-50 transition-all hover:scale-105">
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
                'image' => $event['image_url'],
                'venue' => [
                    'name' => $event['venue']['name'],
                    'address' => $event['venue']['address'],
                    'city' => $event['venue']['city'],
                    'lat' => $event['venue']['lat'],
                    'lng' => $event['venue']['lng']
                ],
                'starts_at' => $event['starts_at'],
                'tickets' => array_map(function($t) {
                    return [
                        'id' => $t['id'],
                        'name' => $t['name'],
                        'price' => $t['price'],
                        'base_price' => $t['base_price'],
                        'service_fee' => $t['service_fee'],
                        'platform_fee' => $t['platform_fee'],
                        'fees_included' => $t['fees_included']
                    ];
                }, $event['tickets']),
                'redirectToCart' => true
            ]) ?>;

            if (typeof TicsEventPage !== 'undefined') {
                TicsEventPage.init(eventData);
            }
        });
    </script>
</body>
</html>
