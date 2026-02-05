<?php
/**
 * TICS.ro - City Page
 * Shows events in a specific city
 */

require_once __DIR__ . '/includes/config.php';

// Get city from URL
$citySlug = $_GET['city'] ?? 'bucuresti';

// City data (would come from API in production)
$cities = [
    'bucuresti' => [
        'name' => 'București',
        'population' => '1.8M locuitori',
        'description' => 'Centrul cultural și artistic al României. Descoperă concerte legendare, festivaluri de renume mondial, spectacole de teatru și stand-up comedy în inima orașului.',
        'image' => 'https://images.unsplash.com/photo-1584646098378-0874589d76b1?w=1600&h=500&fit=crop',
        'icon' => '🏛️',
        'events_count' => 324,
        'venues_count' => 87,
        'weekly_events' => 45,
        'tickets_sold' => '124K',
        'rating' => '4.8',
        'rank' => '#1'
    ],
    'cluj-napoca' => [
        'name' => 'Cluj-Napoca',
        'population' => '320K locuitori',
        'description' => 'Capitala Transilvaniei și cel mai vibrant oraș din vestul României. Găzduiește festivaluri internaționale precum UNTOLD și Electric Castle.',
        'image' => 'https://images.unsplash.com/photo-1570168007204-dfb528c6958f?w=1600&h=500&fit=crop',
        'icon' => '🏔️',
        'events_count' => 156,
        'venues_count' => 45,
        'weekly_events' => 28,
        'tickets_sold' => '89K',
        'rating' => '4.9',
        'rank' => '#2'
    ],
    'timisoara' => [
        'name' => 'Timișoara',
        'population' => '320K locuitori',
        'description' => 'Capitala Europeană a Culturii 2023. Un oraș cu o scenă artistică în plină dezvoltare.',
        'image' => 'https://images.unsplash.com/photo-1585409677983-0f6c41ca9c3b?w=1600&h=500&fit=crop',
        'icon' => '🎭',
        'events_count' => 98,
        'venues_count' => 32,
        'weekly_events' => 15,
        'tickets_sold' => '45K',
        'rating' => '4.7',
        'rank' => '#3'
    ]
];

$city = $cities[$citySlug] ?? $cities['bucuresti'];

// Page configuration
$pageTitle = 'Evenimente în ' . $city['name'];
$pageDescription = $city['description'];
$currentPage = 'city';

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Orașe', 'url' => '/orase'],
    ['name' => $city['name']]
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - City -->
<section class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 text-white relative overflow-hidden">
    <div class="absolute inset-0">
        <img src="<?= e($city['image']) ?>" alt="<?= e($city['name']) ?>" class="w-full h-full object-cover opacity-30">
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900/80 via-gray-900/60 to-transparent"></div>

    <div class="max-w-[1600px] mx-auto px-4 lg:px-8 py-12 lg:py-20 relative">
        <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between gap-8">
            <div class="max-w-2xl">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-white/60 mb-4">
                    <a href="/" class="hover:text-white transition-colors">Acasă</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <a href="#" class="hover:text-white transition-colors">Orașe</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-white"><?= e($city['name']) ?></span>
                </div>

                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 bg-white/10 backdrop-blur rounded-2xl flex items-center justify-center border border-white/20">
                        <span class="text-4xl"><?= $city['icon'] ?></span>
                    </div>
                    <div>
                        <h1 class="text-4xl lg:text-5xl font-bold"><?= e($city['name']) ?></h1>
                        <p class="text-white/80"><?= e($city['population']) ?></p>
                    </div>
                </div>

                <p class="text-lg text-white/90 mb-6"><?= e($city['description']) ?></p>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        <span class="text-sm font-medium"><?= $city['events_count'] ?> evenimente active</span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="text-sm font-medium"><?= $city['venues_count'] ?> locații</span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                        <span class="text-sm font-medium">⚡ <?= $city['weekly_events'] ?> evenimente săptămâna asta</span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-3 gap-3 w-full lg:w-auto">
                <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['tickets_sold'] ?></p>
                    <p class="text-xs text-white/70">Bilete vândute<br>luna asta</p>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['rating'] ?>★</p>
                    <p class="text-xs text-white/70">Rating mediu<br>evenimente</p>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['rank'] ?></p>
                    <p class="text-xs text-white/70">Oraș<br>în România</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Popular Venues Section -->
<section class="max-w-[1600px] mx-auto px-4 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Locații populare în <?= e($city['name']) ?></h2>
            <p class="text-sm text-gray-500">Cele mai căutate săli și arene</p>
        </div>
        <a href="#" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toate (<?= $city['venues_count'] ?>) →</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🏟️</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Arena Națională</h3>
            <p class="text-xs text-gray-500">55.000 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">12 evenimente</p>
        </a>
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-pink-100 to-rose-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🎭</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Sala Palatului</h3>
            <p class="text-xs text-gray-500">4.000 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">28 evenimente</p>
        </a>
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-amber-100 to-orange-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🎪</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Romexpo</h3>
            <p class="text-xs text-gray-500">10.000 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">8 evenimente</p>
        </a>
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🎭</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">TNB</h3>
            <p class="text-xs text-gray-500">1.200 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">45 evenimente</p>
        </a>
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-cyan-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🎸</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Arenele Romane</h3>
            <p class="text-xs text-gray-500">5.000 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">6 evenimente</p>
        </a>
        <a href="#" class="bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-violet-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl">🎤</span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Berăria H</h3>
            <p class="text-xs text-gray-500">2.500 locuri</p>
            <p class="text-xs text-indigo-600 font-medium mt-1">34 evenimente</p>
        </a>
    </div>
</section>

<!-- Events Section -->
<section class="max-w-[1600px] mx-auto px-4 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900"><?= $city['events_count'] ?> evenimente în <?= e($city['name']) ?></h2>
            <p class="text-sm text-gray-500">Descoperă ce se întâmplă în oraș</p>
        </div>
        <div class="flex items-center gap-3">
            <select class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm font-medium cursor-pointer hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition-colors">
                <option value="recommended">Recomandate pentru tine</option>
                <option value="date">Data: cele mai apropiate</option>
                <option value="price_asc">Preț: mic - mare</option>
                <option value="price_desc">Preț: mare - mic</option>
                <option value="popular">Cele mai populare</option>
            </select>
        </div>
    </div>

    <!-- Events Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" id="eventsGrid">
        <!-- Events loaded via JavaScript -->
    </div>

    <!-- Load More -->
    <div class="text-center mt-10">
        <button id="loadMoreBtn" class="px-8 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
            Încarcă mai multe evenimente
        </button>
        <p class="text-sm text-gray-400 mt-3" id="eventsCount">Se încarcă...</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="<?= asset('assets/js/utils.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
<script>
    const citySlug = '<?= e($citySlug) ?>';
    let currentPage = 1;
    let totalEvents = 0;

    async function loadEvents(page = 1) {
        const grid = document.getElementById('eventsGrid');
        if (page === 1) {
            grid.innerHTML = '<div class="col-span-full text-center py-8"><div class="loading-spinner mx-auto"></div></div>';
        }

        try {
            const response = await TicsAPI.getEvents({ city: citySlug, page: page, per_page: 12 });
            if (response.success && response.data) {
                totalEvents = response.meta?.total || response.data.length;

                if (page === 1) {
                    grid.innerHTML = '';
                }

                response.data.forEach(event => {
                    const card = TicsEventCard.render(event);
                    grid.insertAdjacentHTML('beforeend', card);
                });

                const shown = Math.min(page * 12, totalEvents);
                document.getElementById('eventsCount').textContent = `Afișezi ${shown} din ${totalEvents} evenimente`;

                if (shown >= totalEvents) {
                    document.getElementById('loadMoreBtn').style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error loading events:', error);
            grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Nu am putut încărca evenimentele.</div>';
        }
    }

    document.getElementById('loadMoreBtn').addEventListener('click', () => {
        currentPage++;
        loadEvents(currentPage);
    });

    // Load on page ready
    document.addEventListener('DOMContentLoaded', () => loadEvents(1));
</script>

</body>
</html>
