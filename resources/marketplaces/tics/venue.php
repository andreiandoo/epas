<?php
/**
 * TICS.ro - Venue Page
 * Shows events at a specific venue
 * URL: /bilete-{venue-slug}
 */

require_once __DIR__ . '/includes/config.php';

// Get venue slug from URL
$venueSlug = $_GET['venue'] ?? '';

// Venue data (would come from API in production)
$venues = [
    'arena-nationala' => [
        'name' => 'Arena Națională',
        'slug' => 'arena-nationala',
        'city' => 'București',
        'city_slug' => 'bucuresti',
        'address' => 'Bulevardul Basarabia 37-39, Sector 2',
        'description' => 'Cea mai mare arenă multifuncțională din România. Găzduiește cele mai importante concerte internaționale și meciuri de fotbal.',
        'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=1600&h=500&fit=crop',
        'icon' => '🏟️',
        'capacity' => '55.000 locuri',
        'type' => 'Arenă',
        'events_count' => 12,
        'rating' => '4.8',
        'amenities' => ['Parcare', 'Food Court', 'Acces dizabilități', 'WiFi gratuit', 'VIP Lounge'],
        'transport' => [
            'metro' => 'Piața Muncii (M1, M3)',
            'bus' => '311, 330, 335',
            'parking' => '20 RON'
        ],
        'lat' => 44.4378,
        'lng' => 26.1546
    ],
    'sala-palatului' => [
        'name' => 'Sala Palatului',
        'slug' => 'sala-palatului',
        'city' => 'București',
        'city_slug' => 'bucuresti',
        'address' => 'Strada Ion Câmpineanu 28',
        'description' => 'Una dintre cele mai prestigioase săli de spectacole din România, cu o acustică excepțională.',
        'image' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1600&h=500&fit=crop',
        'icon' => '🎭',
        'capacity' => '4.000 locuri',
        'type' => 'Sală de concerte',
        'events_count' => 28,
        'rating' => '4.9',
        'amenities' => ['Garderobă', 'Restaurant', 'Acces dizabilități', 'VIP Lounge'],
        'transport' => [
            'metro' => 'Universitate (M1, M2)',
            'bus' => '336, 368',
            'parking' => '15 RON'
        ],
        'lat' => 44.4352,
        'lng' => 26.0986
    ],
    'romexpo' => [
        'name' => 'Romexpo',
        'slug' => 'romexpo',
        'city' => 'București',
        'city_slug' => 'bucuresti',
        'address' => 'Bd. Mărăști 65-67',
        'description' => 'Cel mai mare centru expozițional din România, folosit pentru evenimente de mari dimensiuni.',
        'image' => 'https://images.unsplash.com/photo-1531058020387-3be344556be6?w=1600&h=500&fit=crop',
        'icon' => '🎪',
        'capacity' => '10.000 locuri',
        'type' => 'Centru expozițional',
        'events_count' => 8,
        'rating' => '4.5',
        'amenities' => ['Parcare', 'Food Court', 'Acces dizabilități'],
        'transport' => [
            'metro' => 'Piața Presei Libere',
            'bus' => '131, 335',
            'parking' => '10 RON'
        ],
        'lat' => 44.4685,
        'lng' => 26.0761
    ],
    'bt-arena' => [
        'name' => 'BT Arena',
        'slug' => 'bt-arena',
        'city' => 'Cluj-Napoca',
        'city_slug' => 'cluj-napoca',
        'address' => 'Aleea Stadionului 2',
        'description' => 'Arena multifuncțională modernă din Cluj-Napoca, gazdă a marilor evenimente sportive și concerte.',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=1600&h=500&fit=crop',
        'icon' => '🏟️',
        'capacity' => '10.000 locuri',
        'type' => 'Arenă',
        'events_count' => 15,
        'rating' => '4.7',
        'amenities' => ['Parcare', 'Food Court', 'Acces dizabilități', 'WiFi gratuit'],
        'transport' => [
            'bus' => '24, 27, 43',
            'parking' => '15 RON'
        ],
        'lat' => 46.7591,
        'lng' => 23.5757
    ],
    'beraria-h' => [
        'name' => 'Berăria H',
        'slug' => 'beraria-h',
        'city' => 'București',
        'city_slug' => 'bucuresti',
        'address' => 'Strada Lânăriei 87',
        'description' => 'Cea mai mare berărie din România, loc perfect pentru concerte și evenimente live.',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1600&h=500&fit=crop',
        'icon' => '🎤',
        'capacity' => '2.500 locuri',
        'type' => 'Club / Berărie',
        'events_count' => 34,
        'rating' => '4.6',
        'amenities' => ['Restaurant', 'Parcare', 'Terasă'],
        'transport' => [
            'bus' => '311',
            'parking' => 'Gratuit'
        ],
        'lat' => 44.4458,
        'lng' => 26.1392
    ]
];

// Get venue data
$venue = $venues[$venueSlug] ?? null;

// 404 if venue not found
if (!$venue) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Page configuration
$pageTitle = 'Evenimente la ' . $venue['name'] . ' | TICS.ro';
$pageDescription = $venue['description'];
$currentPage = 'venue';

// Extra head styles
$headExtra = <<<HTML
<style>
    .hero-gradient { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 50%, #3d7ab5 100%); }
    .stat-card { backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); }
    .amenity-card { transition: all 0.2s ease; }
    .amenity-card:hover { transform: translateY(-2px); }
</style>
HTML;

include __DIR__ . '/includes/head.php';

// Set login state for header
setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - Venue -->
<section class="hero-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0">
        <img src="<?= e($venue['image']) ?>" alt="<?= e($venue['name']) ?>" class="w-full h-full object-cover opacity-30">
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900/80 via-gray-900/60 to-transparent"></div>

    <div class="max-w-[1600px] mx-auto px-4 lg:px-8 py-12 lg:py-20 relative">
        <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between gap-8">
            <div class="max-w-2xl">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-white/60 mb-4">
                    <a href="/" class="hover:text-white transition-colors">Acasă</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <a href="<?= cityUrl($venue['city_slug']) ?>" class="hover:text-white transition-colors"><?= e($venue['city']) ?></a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-white"><?= e($venue['name']) ?></span>
                </div>

                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 bg-white/10 backdrop-blur rounded-2xl flex items-center justify-center border border-white/20">
                        <span class="text-4xl"><?= $venue['icon'] ?></span>
                    </div>
                    <div>
                        <h1 class="text-4xl lg:text-5xl font-bold"><?= e($venue['name']) ?></h1>
                        <p class="text-white/80"><?= e($venue['city']) ?> • <?= e($venue['type']) ?></p>
                    </div>
                </div>

                <p class="text-lg text-white/90 mb-6"><?= e($venue['description']) ?></p>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                        <span class="w-2 h-2 bg-green-400 rounded-full pulse"></span>
                        <span class="text-sm font-medium"><?= $venue['events_count'] ?> evenimente active</span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-sm font-medium"><?= e($venue['capacity']) ?></span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-amber-500/20 backdrop-blur rounded-full border border-amber-400/30">
                        <span class="text-sm font-medium"><?= $venue['rating'] ?> ★</span>
                    </div>
                </div>
            </div>

            <!-- Location Card -->
            <div class="bg-white/10 backdrop-blur rounded-2xl p-5 border border-white/20 w-full lg:w-auto lg:min-w-[320px]">
                <h3 class="font-semibold mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Cum ajungi
                </h3>
                <p class="text-sm text-white/80 mb-4"><?= e($venue['address']) ?></p>
                <div class="space-y-2 text-sm">
                    <?php if (!empty($venue['transport']['metro'])): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 bg-yellow-500 rounded flex items-center justify-center text-xs font-bold text-black">M</span>
                        <span><?= e($venue['transport']['metro']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($venue['transport']['bus'])): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center text-xs font-bold text-white">B</span>
                        <span>Autobuz: <?= e($venue['transport']['bus']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($venue['transport']['parking'])): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 bg-gray-500 rounded flex items-center justify-center text-xs font-bold text-white">P</span>
                        <span>Parcare: <?= e($venue['transport']['parking']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $venue['lat'] ?>,<?= $venue['lng'] ?>" target="_blank" class="mt-4 block w-full py-2.5 bg-white text-gray-900 font-medium rounded-xl text-center text-sm hover:bg-gray-100 transition-colors">
                    Deschide în Google Maps
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Amenities -->
<section class="max-w-[1600px] mx-auto px-4 lg:px-8 py-8 border-b border-gray-200">
    <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-2">
        <span class="text-sm text-gray-500 whitespace-nowrap">Facilități:</span>
        <?php foreach ($venue['amenities'] as $amenity): ?>
        <span class="amenity-card px-4 py-2 bg-gray-100 rounded-full text-sm font-medium text-gray-700 whitespace-nowrap">
            <?= e($amenity) ?>
        </span>
        <?php endforeach; ?>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-[1600px] mx-auto px-4 lg:px-8 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Events List -->
        <div class="flex-1">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Evenimente la <?= e($venue['name']) ?></h2>
                    <p class="text-sm text-gray-500" id="resultsInfo"><?= $venue['events_count'] ?> evenimente disponibile</p>
                </div>
                <select id="sortFilter" class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm font-medium cursor-pointer hover:border-gray-300 focus:outline-none">
                    <option value="date">Data: cele mai apropiate</option>
                    <option value="price_asc">Preț: mic - mare</option>
                    <option value="price_desc">Preț: mare - mic</option>
                    <option value="popular">Cele mai populare</option>
                </select>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="hidden">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 animate-pulse">
                        <div class="aspect-[4/3] bg-gray-200"></div>
                        <div class="p-4 space-y-3">
                            <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Events Grid -->
            <div id="eventsGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Events will be rendered by JS -->
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="hidden py-16 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="text-4xl"><?= $venue['icon'] ?></span>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Niciun eveniment programat</h3>
                <p class="text-gray-500 mb-6">Momentan nu sunt evenimente programate la această locație.</p>
                <a href="/evenimente" class="px-6 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
                    Explorează alte evenimente
                </a>
            </div>
        </div>

        <!-- Sidebar - Map -->
        <aside class="lg:w-80 flex-shrink-0">
            <div class="sticky top-24 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="aspect-square bg-gray-100">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2849.3893!2d<?= $venue['lng'] ?>!3d<?= $venue['lat'] ?>!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z<?= urlencode($venue['name']) ?>!5e0!3m2!1sro!2sro"
                        width="100%"
                        height="100%"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        class="w-full h-full"
                    ></iframe>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-2"><?= e($venue['name']) ?></h3>
                    <p class="text-sm text-gray-500"><?= e($venue['address']) ?>, <?= e($venue['city']) ?></p>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="<?= asset('assets/js/utils.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
<script>
    const venueSlug = <?= json_encode($venueSlug) ?>;
    const venueName = <?= json_encode($venue['name']) ?>;
    const citySlug = <?= json_encode($venue['city_slug']) ?>;

    const TicsVenuePage = {
        init: function() {
            this.loadEvents();
            this.bindEvents();
        },

        bindEvents: function() {
            document.getElementById('sortFilter')?.addEventListener('change', () => this.loadEvents());
        },

        loadEvents: async function() {
            const grid = document.getElementById('eventsGrid');
            const loadingState = document.getElementById('loadingState');
            const emptyState = document.getElementById('emptyState');

            grid.innerHTML = '';
            loadingState?.classList.remove('hidden');

            try {
                const sort = document.getElementById('sortFilter')?.value || 'date';
                const response = await TicsAPI.getEvents({
                    city: citySlug,
                    sort: sort,
                    per_page: 12
                });

                loadingState?.classList.add('hidden');

                if (response.success && response.data && response.data.length > 0) {
                    response.data.forEach(event => {
                        const card = TicsEventCard.render(event);
                        grid.insertAdjacentHTML('beforeend', card);
                    });
                    emptyState?.classList.add('hidden');
                    document.getElementById('resultsInfo').textContent = response.data.length + ' evenimente găsite';
                } else {
                    emptyState?.classList.remove('hidden');
                    document.getElementById('resultsInfo').textContent = '0 evenimente';
                }
            } catch (error) {
                console.error('Error loading events:', error);
                loadingState?.classList.add('hidden');
                grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Nu am putut încărca evenimentele.</div>';
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        TicsVenuePage.init();
    });
</script>

</body>
</html>
