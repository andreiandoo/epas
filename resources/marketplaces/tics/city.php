<?php
/**
 * TICS.ro - City Page
 * Shows events in a specific city with full filters
 */

require_once __DIR__ . '/includes/config.php';

// Get city from URL
$citySlug = $_GET['city'] ?? 'bucuresti';

// Get filter parameters
$filterCategory = isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie'], ENT_QUOTES, 'UTF-8') : '';
$filterDate = isset($_GET['data']) ? htmlspecialchars($_GET['data'], ENT_QUOTES, 'UTF-8') : '';
$filterPrice = isset($_GET['pret']) ? htmlspecialchars($_GET['pret'], ENT_QUOTES, 'UTF-8') : '';
$filterSort = isset($_GET['sortare']) ? htmlspecialchars($_GET['sortare'], ENT_QUOTES, 'UTF-8') : 'recommended';
$filterSearch = isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : '';

// City is the main filter
$filterCity = $citySlug;

// City data (would come from API in production)
$cities = [
    'bucuresti' => [
        'name' => 'București',
        'population' => 'Capitala României • 1.8M locuitori',
        'description' => 'Centrul cultural și artistic al României. Descoperă concerte legendare, festivaluri de renume mondial, spectacole de teatru și stand-up comedy în inima orașului.',
        'image' => 'https://images.unsplash.com/photo-1584646098378-0874589d76b1?w=1600&h=500&fit=crop',
        'icon' => '🏛️',
        'events_count' => 324,
        'venues_count' => 87,
        'weekly_events' => 45,
        'tickets_sold' => '124K',
        'rating' => '4.8',
        'rank' => '#1',
        'neighborhoods' => [
            ['slug' => 'centru', 'name' => 'Centru', 'count' => 89],
            ['slug' => 'pipera', 'name' => 'Pipera / Aviatorilor', 'count' => 34],
            ['slug' => 'herastrau', 'name' => 'Herăstrău', 'count' => 28],
            ['slug' => 'baneasa', 'name' => 'Băneasa', 'count' => 15],
        ],
        'venues' => [
            ['name' => 'Arena Națională', 'icon' => '🏟️', 'capacity' => '55.000 locuri', 'events' => 12, 'gradient' => 'from-indigo-100 to-purple-100'],
            ['name' => 'Sala Palatului', 'icon' => '🎭', 'capacity' => '4.000 locuri', 'events' => 28, 'gradient' => 'from-pink-100 to-rose-100'],
            ['name' => 'Romexpo', 'icon' => '🎪', 'capacity' => '10.000 locuri', 'events' => 8, 'gradient' => 'from-amber-100 to-orange-100'],
            ['name' => 'TNB', 'icon' => '🎭', 'capacity' => '1.200 locuri', 'events' => 45, 'gradient' => 'from-green-100 to-emerald-100'],
            ['name' => 'Arenele Romane', 'icon' => '🎸', 'capacity' => '5.000 locuri', 'events' => 6, 'gradient' => 'from-blue-100 to-cyan-100'],
            ['name' => 'Berăria H', 'icon' => '🎤', 'capacity' => '2.500 locuri', 'events' => 34, 'gradient' => 'from-purple-100 to-violet-100'],
        ]
    ],
    'cluj-napoca' => [
        'name' => 'Cluj-Napoca',
        'population' => 'Capitala Transilvaniei • 320K locuitori',
        'description' => 'Capitala Transilvaniei și cel mai vibrant oraș din vestul României. Găzduiește festivaluri internaționale precum UNTOLD și Electric Castle.',
        'image' => 'https://images.unsplash.com/photo-1570168007204-dfb528c6958f?w=1600&h=500&fit=crop',
        'icon' => '🏔️',
        'events_count' => 156,
        'venues_count' => 45,
        'weekly_events' => 28,
        'tickets_sold' => '89K',
        'rating' => '4.9',
        'rank' => '#2',
        'neighborhoods' => [
            ['slug' => 'centru', 'name' => 'Centru', 'count' => 45],
            ['slug' => 'marasti', 'name' => 'Mărăști', 'count' => 22],
            ['slug' => 'gheorgheni', 'name' => 'Gheorgheni', 'count' => 18],
        ],
        'venues' => [
            ['name' => 'BT Arena', 'icon' => '🏟️', 'capacity' => '10.000 locuri', 'events' => 15, 'gradient' => 'from-indigo-100 to-purple-100'],
            ['name' => 'Casa de Cultură', 'icon' => '🎭', 'capacity' => '1.500 locuri', 'events' => 22, 'gradient' => 'from-pink-100 to-rose-100'],
            ['name' => 'Form Space', 'icon' => '🎸', 'capacity' => '800 locuri', 'events' => 34, 'gradient' => 'from-amber-100 to-orange-100'],
            ['name' => 'Flying Circus', 'icon' => '🎤', 'capacity' => '600 locuri', 'events' => 28, 'gradient' => 'from-green-100 to-emerald-100'],
        ]
    ],
    'timisoara' => [
        'name' => 'Timișoara',
        'population' => 'Capitala Europeană a Culturii • 320K locuitori',
        'description' => 'Capitala Europeană a Culturii 2023. Un oraș cu o scenă artistică în plină dezvoltare.',
        'image' => 'https://images.unsplash.com/photo-1585409677983-0f6c41ca9c3b?w=1600&h=500&fit=crop',
        'icon' => '🎭',
        'events_count' => 98,
        'venues_count' => 32,
        'weekly_events' => 15,
        'tickets_sold' => '45K',
        'rating' => '4.7',
        'rank' => '#3',
        'neighborhoods' => [
            ['slug' => 'centru', 'name' => 'Centru', 'count' => 38],
            ['slug' => 'fabric', 'name' => 'Fabric', 'count' => 15],
        ],
        'venues' => [
            ['name' => 'Sala Capitol', 'icon' => '🎭', 'capacity' => '1.200 locuri', 'events' => 18, 'gradient' => 'from-indigo-100 to-purple-100'],
            ['name' => 'Filarmonica', 'icon' => '🎻', 'capacity' => '800 locuri', 'events' => 24, 'gradient' => 'from-pink-100 to-rose-100'],
        ]
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

// Extra head styles for hero gradient
$headExtra = <<<HTML
<style>
    .hero-gradient { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 50%, #3d7ab5 100%); }
    .stat-card { backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); }
    .venue-card { transition: all 0.2s ease; }
    .venue-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); }
</style>
HTML;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - City -->
<section class="hero-gradient text-white relative overflow-hidden">
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
                        <span class="w-2 h-2 bg-green-400 rounded-full pulse"></span>
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
                <div class="stat-card rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['tickets_sold'] ?></p>
                    <p class="text-xs text-white/70">Bilete vândute<br>luna asta</p>
                </div>
                <div class="stat-card rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['rating'] ?>★</p>
                    <p class="text-xs text-white/70">Rating mediu<br>evenimente</p>
                </div>
                <div class="stat-card rounded-2xl p-4 text-center border border-white/20">
                    <p class="text-2xl lg:text-3xl font-bold"><?= $city['rank'] ?></p>
                    <p class="text-xs text-white/70">Oraș<br>în România</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Bar - After Hero -->
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar" id="categoriesBar">
            <button class="chip-active px-4 py-2 rounded-full border border-gray-200 text-sm font-medium whitespace-nowrap transition-colors" data-category="">Toate</button>
            <?php foreach ($CATEGORIES as $slug => $cat): ?>
            <button class="px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600 whitespace-nowrap hover:border-gray-300 transition-colors" data-category="<?= e($slug) ?>">
                <?= $cat['icon'] ?> <?= e($cat['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

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
        <?php foreach ($city['venues'] as $venue): ?>
        <a href="#" class="venue-card bg-white rounded-2xl p-4 border border-gray-200 text-center group hover:shadow-lg transition-all">
            <div class="w-16 h-16 bg-gradient-to-br <?= $venue['gradient'] ?> rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <span class="text-2xl"><?= $venue['icon'] ?></span>
            </div>
            <h3 class="font-semibold text-gray-900 text-sm mb-1"><?= e($venue['name']) ?></h3>
            <p class="text-xs text-gray-500"><?= $venue['capacity'] ?></p>
            <p class="text-xs text-indigo-600 font-medium mt-1"><?= $venue['events'] ?> evenimente</p>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-[1600px] mx-auto px-4 lg:px-8 py-6">
    <div class="flex gap-8">
        <!-- Sidebar Filters - Desktop -->
        <aside class="hidden lg:block w-72 flex-shrink-0">
            <div class="sticky top-40 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900">Filtre</h2>
                        <button onclick="TicsCityPage.clearFilters()" class="text-sm text-indigo-600 font-medium hover:underline">Resetează</button>
                    </div>
                </div>

                <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
                    <!-- AI Toggle -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 text-sm">AI Suggestions</p>
                                    <p class="text-xs text-gray-500">Recomandări personalizate</p>
                                </div>
                            </div>
                            <label class="relative inline-flex cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked id="aiToggle">
                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Date Filter -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Când</h3>
                        <div class="space-y-2.5">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="today">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Astăzi</span>
                                <span class="ml-auto text-xs text-gray-400">24</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="tomorrow">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Mâine</span>
                                <span class="ml-auto text-xs text-gray-400">18</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="weekend">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Weekendul acesta</span>
                                <span class="ml-auto text-xs text-gray-400">67</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="week">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Săptămâna viitoare</span>
                                <span class="ml-auto text-xs text-gray-400">89</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-date="month">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Luna aceasta</span>
                                <span class="ml-auto text-xs text-gray-400">156</span>
                            </label>
                        </div>
                        <button class="mt-3 text-sm text-indigo-600 font-medium hover:underline flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Alege interval
                        </button>
                    </div>

                    <!-- Price Filter -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Preț</h3>
                        <input type="range" id="priceRange" min="0" max="1000" value="500" class="w-full mb-2">
                        <div class="flex justify-between text-xs text-gray-500 mb-3">
                            <span>0 RON</span>
                            <span class="font-medium text-gray-900" id="priceLabel">până la 500 RON</span>
                            <span>1000+</span>
                        </div>
                        <div class="flex gap-2">
                            <button data-price="free" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Gratuit</button>
                            <button data-price="0-100" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;100</button>
                            <button data-price="100-300" class="price-quick-btn flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;300</button>
                        </div>
                    </div>

                    <!-- Neighborhoods Filter -->
                    <?php if (!empty($city['neighborhoods'])): ?>
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Zonă</h3>
                        <div class="space-y-2.5">
                            <?php foreach ($city['neighborhoods'] as $neighborhood): ?>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-zone="<?= e($neighborhood['slug']) ?>">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900"><?= e($neighborhood['name']) ?></span>
                                <span class="ml-auto text-xs text-gray-400"><?= $neighborhood['count'] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="mt-3 text-sm text-indigo-600 font-medium hover:underline">Vezi toate zonele</button>
                    </div>
                    <?php endif; ?>

                    <!-- Features Filter -->
                    <div class="pb-5 mb-5 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900 mb-3 text-sm">Caracteristici</h3>
                        <div class="space-y-2.5">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-feature="sold_out_soon">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Sold out aproape</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-feature="early_bird">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Early Bird activ</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-feature="vip">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">VIP disponibil</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" class="cb" data-feature="parking">
                                <span class="text-sm text-gray-600 group-hover:text-gray-900">Parcare gratuită</span>
                            </label>
                        </div>
                    </div>

                    <!-- AI Match Filter -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <h3 class="font-medium text-gray-900 text-sm">AI Match minim</h3>
                            <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                        </div>
                        <div class="flex gap-2">
                            <button data-ai-match="0" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Orice</button>
                            <button data-ai-match="80" class="flex-1 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg">80%+</button>
                            <button data-ai-match="90" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">90%+</button>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Events Content -->
        <div class="flex-1 min-w-0">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900"><span id="resultsCount"><?= $city['events_count'] ?></span> evenimente în <?= e($city['name']) ?></h2>
                    <p class="text-sm text-gray-500" id="resultsInfo">Toate evenimentele</p>
                </div>
                <div class="flex items-center gap-3">
                    <button id="filterBtn" class="lg:hidden flex items-center gap-2 px-4 py-2.5 border border-gray-200 rounded-full text-sm font-medium hover:border-gray-300 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filtre
                        <span class="w-5 h-5 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
                    </button>
                    <select id="sortFilter" class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm font-medium cursor-pointer hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition-colors">
                        <option value="recommended">Recomandate pentru tine</option>
                        <option value="date">Data: cele mai apropiate</option>
                        <option value="price_asc">Preț: mic - mare</option>
                        <option value="price_desc">Preț: mare - mic</option>
                        <option value="popular">Cele mai populare</option>
                        <option value="recent">Recent adăugate</option>
                    </select>
                    <div class="hidden sm:flex items-center border border-gray-200 rounded-full p-1">
                        <button id="viewGrid" class="p-2 rounded-full bg-gray-900 text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </button>
                        <button id="viewList" class="p-2 rounded-full text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            <div id="activeFilters" class="flex items-center gap-2 mb-6 overflow-x-auto no-scrollbar pb-1">
                <span class="text-xs text-gray-500 whitespace-nowrap">Filtre active:</span>
                <!-- City filter - always shown, not removable -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-full text-xs font-medium whitespace-nowrap">
                    📍 <?= e($city['name']) ?>
                </span>
                <!-- Dynamic filters will be added here by JS -->
                <div id="dynamicFilters" class="flex items-center gap-2"></div>
                <button onclick="TicsCityPage.clearFilters()" class="text-xs text-indigo-600 font-medium hover:underline whitespace-nowrap" id="clearAllFiltersBtn" style="display: none;">Șterge toate</button>
            </div>

            <!-- AI Banner -->
            <div id="aiBanner" class="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 rounded-2xl p-5 mb-6 border border-indigo-100">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-semibold text-gray-900">AI a găsit 18 evenimente perfecte pentru tine în <?= e($city['name']) ?></h3>
                            <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">Bazat pe preferințele tale pentru stand-up comedy, concerte rock și locații în centru.</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-2.5 py-1 bg-white/80 rounded-full text-xs font-medium text-gray-700">😂 Stand-up</span>
                            <span class="px-2.5 py-1 bg-white/80 rounded-full text-xs font-medium text-gray-700">🎸 Rock</span>
                            <span class="px-2.5 py-1 bg-white/80 rounded-full text-xs font-medium text-gray-700">📍 Centru</span>
                        </div>
                    </div>
                    <button class="text-sm font-medium text-indigo-600 hover:underline whitespace-nowrap hidden sm:block">Editează preferințe</button>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="hidden">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php for ($i = 0; $i < 8; $i++): ?>
                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 animate-pulse">
                        <div class="aspect-[4/3] bg-gray-200"></div>
                        <div class="p-4 space-y-3">
                            <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Events Grid -->
            <div id="eventsGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <!-- Events will be rendered by JS -->
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="hidden py-16 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Nu am găsit evenimente</h3>
                <p class="text-gray-500 mb-6">Încearcă să modifici filtrele sau să cauți altceva.</p>
                <button onclick="TicsCityPage.clearFilters()" class="px-6 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
                    Resetează filtrele
                </button>
            </div>

            <!-- Load More -->
            <div class="text-center mt-10">
                <button id="loadMoreBtn" class="px-8 py-3 bg-gray-900 text-white font-medium rounded-full hover:bg-gray-800 transition-colors">
                    Încarcă mai multe evenimente
                </button>
                <p class="text-sm text-gray-400 mt-3" id="resultsSummary">Afișezi 12 din <?= $city['events_count'] ?> evenimente</p>
            </div>
        </div>
    </div>
</main>

<!-- Mobile Filter Drawer -->
<div id="filterOverlay" class="overlay fixed inset-0 bg-black/50 z-50 lg:hidden"></div>
<div id="filterDrawer" class="drawer fixed top-0 left-0 bottom-0 w-80 max-w-[85vw] bg-white z-50 overflow-y-auto lg:hidden">
    <div class="sticky top-0 bg-white border-b border-gray-200 px-5 py-4 flex items-center justify-between">
        <h2 class="font-semibold text-lg">Filtre</h2>
        <button onclick="closeFiltersDrawer()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-5">
        <!-- Mobile AI Toggle -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-medium text-sm">AI Suggestions</span>
                </div>
                <label class="relative inline-flex cursor-pointer">
                    <input type="checkbox" class="sr-only peer" checked id="aiToggleMobile">
                    <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>
        </div>

        <!-- Mobile Date Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Când</h3>
            <div class="space-y-2.5">
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="today"><span class="text-sm text-gray-600">Astăzi</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="tomorrow"><span class="text-sm text-gray-600">Mâine</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="weekend"><span class="text-sm text-gray-600">Weekend</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="month"><span class="text-sm text-gray-600">Luna aceasta</span></label>
            </div>
        </div>

        <!-- Mobile Price Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Preț maxim</h3>
            <input type="range" id="priceRangeMobile" min="0" max="1000" value="500" class="w-full mb-2">
            <div class="flex justify-between text-xs text-gray-500">
                <span>0</span><span class="font-medium text-gray-900" id="priceLabelMobile">500 RON</span><span>1000+</span>
            </div>
        </div>

        <!-- Mobile Zone Filter -->
        <?php if (!empty($city['neighborhoods'])): ?>
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Zonă</h3>
            <div class="space-y-2.5">
                <?php foreach ($city['neighborhoods'] as $neighborhood): ?>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-zone="<?= e($neighborhood['slug']) ?>"><span class="text-sm text-gray-600"><?= e($neighborhood['name']) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mobile AI Match -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <h3 class="font-medium text-sm">AI Match minim</h3>
                <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
            </div>
            <div class="flex gap-2">
                <button data-ai-match="0" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">Orice</button>
                <button data-ai-match="80" class="flex-1 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg">80%+</button>
                <button data-ai-match="90" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">90%+</button>
            </div>
        </div>
    </div>

    <div class="sticky bottom-0 bg-white border-t border-gray-200 p-5 flex gap-3">
        <button onclick="TicsCityPage.clearFilters(); closeFiltersDrawer();" class="flex-1 py-3 border border-gray-200 rounded-xl font-medium hover:bg-gray-50 transition-colors">Resetează</button>
        <button onclick="TicsCityPage.applyFilters(); closeFiltersDrawer();" class="flex-1 py-3 bg-gray-900 text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Aplică (<span id="filterResultCount"><?= $city['events_count'] ?></span>)</button>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="<?= asset('assets/js/utils.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-promo-card.js') ?>"></script>
<script>
    // City page configuration
    const citySlug = <?= json_encode($citySlug) ?>;
    const cityName = <?= json_encode($city['name']) ?>;
    let currentPage = 1;
    let totalEvents = <?= $city['events_count'] ?>;
    let isLoading = false;

    // Filter labels mapping
    const filterLabels = {
        date: {
            today: 'Astăzi',
            tomorrow: 'Mâine',
            weekend: 'Weekend',
            week: 'Săptămâna aceasta',
            month: 'Luna aceasta'
        },
        zone: {
            centru: 'Centru',
            pipera: 'Pipera',
            herastrau: 'Herăstrău',
            baneasa: 'Băneasa',
            marasti: 'Mărăști',
            gheorgheni: 'Gheorgheni',
            fabric: 'Fabric'
        }
    };

    // TicsCityPage object
    const TicsCityPage = {
        filters: {
            city: citySlug,
            category: '',
            date: '',
            zone: '',
            price: 1000,
            priceRange: '', // 'free', '0-100', '100-300'
            aiMatch: 0
        },

        init: function() {
            this.loadEvents(1);
            this.bindEvents();
            this.updateActiveFilters();
        },

        bindEvents: function() {
            // Category chips
            document.querySelectorAll('#categoriesBar button').forEach(chip => {
                chip.addEventListener('click', (e) => {
                    document.querySelectorAll('#categoriesBar button').forEach(c => c.classList.remove('chip-active'));
                    e.target.classList.add('chip-active');
                    this.filters.category = e.target.dataset.category || '';
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // Sort filter
            document.getElementById('sortFilter')?.addEventListener('change', (e) => {
                this.filters.sort = e.target.value;
                this.loadEvents(1);
            });

            // Load more button
            document.getElementById('loadMoreBtn')?.addEventListener('click', () => {
                currentPage++;
                this.loadEvents(currentPage, true);
            });

            // Mobile filter button
            document.getElementById('filterBtn')?.addEventListener('click', openFiltersDrawer);

            // Price range
            document.getElementById('priceRange')?.addEventListener('input', (e) => {
                const val = e.target.value;
                document.getElementById('priceLabel').textContent = val >= 1000 ? '1000+ RON' : `până la ${val} RON`;
                this.filters.price = parseInt(val);
            });

            // Mobile price range
            document.getElementById('priceRangeMobile')?.addEventListener('input', (e) => {
                const val = e.target.value;
                document.getElementById('priceLabelMobile').textContent = val >= 1000 ? '1000+ RON' : `${val} RON`;
            });

            // Date checkboxes
            document.querySelectorAll('[data-date]').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    // Only one date can be selected
                    if (e.target.checked) {
                        document.querySelectorAll('[data-date]').forEach(other => {
                            if (other !== e.target) other.checked = false;
                        });
                        this.filters.date = e.target.dataset.date;
                    } else {
                        this.filters.date = '';
                    }
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // Zone checkboxes
            document.querySelectorAll('[data-zone]').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    // Only one zone can be selected
                    if (e.target.checked) {
                        document.querySelectorAll('[data-zone]').forEach(other => {
                            if (other !== e.target) other.checked = false;
                        });
                        this.filters.zone = e.target.dataset.zone;
                    } else {
                        this.filters.zone = '';
                    }
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // AI Match buttons
            document.querySelectorAll('[data-ai-match]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const parent = e.target.closest('.flex');
                    parent.querySelectorAll('button').forEach(b => {
                        b.classList.remove('bg-gray-900', 'text-white');
                        b.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    e.target.classList.remove('bg-gray-100', 'text-gray-600');
                    e.target.classList.add('bg-gray-900', 'text-white');
                    this.filters.aiMatch = parseInt(e.target.dataset.aiMatch);
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });

            // Price quick buttons (Gratuit, <100, <300)
            document.querySelectorAll('.price-quick-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const priceValue = e.target.dataset.price;
                    // Toggle: if already selected, deselect
                    if (this.filters.priceRange === priceValue) {
                        this.filters.priceRange = '';
                        e.target.classList.remove('bg-gray-900', 'text-white');
                        e.target.classList.add('bg-gray-100', 'text-gray-600');
                    } else {
                        // Remove active state from all price buttons
                        document.querySelectorAll('.price-quick-btn').forEach(b => {
                            b.classList.remove('bg-gray-900', 'text-white');
                            b.classList.add('bg-gray-100', 'text-gray-600');
                        });
                        // Activate this button
                        e.target.classList.remove('bg-gray-100', 'text-gray-600');
                        e.target.classList.add('bg-gray-900', 'text-white');
                        this.filters.priceRange = priceValue;
                    }
                    this.updateActiveFilters();
                    this.loadEvents(1);
                });
            });
        },

        updateActiveFilters: function() {
            const container = document.getElementById('dynamicFilters');
            const clearBtn = document.getElementById('clearAllFiltersBtn');
            if (!container) return;

            container.innerHTML = '';
            let hasFilters = false;

            // Add date filter chip
            if (this.filters.date) {
                hasFilters = true;
                const label = filterLabels.date[this.filters.date] || this.filters.date;
                container.innerHTML += this.createFilterChip('date', '📅 ' + label);
            }

            // Add zone filter chip
            if (this.filters.zone) {
                hasFilters = true;
                const label = filterLabels.zone[this.filters.zone] || this.filters.zone;
                container.innerHTML += this.createFilterChip('zone', '📍 ' + label);
            }

            // Add category filter chip
            if (this.filters.category) {
                hasFilters = true;
                const catBtn = document.querySelector(`#categoriesBar button[data-category="${this.filters.category}"]`);
                const label = catBtn ? catBtn.textContent.trim() : this.filters.category;
                container.innerHTML += this.createFilterChip('category', label);
            }

            // Add price filter chip
            if (this.filters.priceRange) {
                hasFilters = true;
                const priceLabels = {
                    'free': 'Gratuit',
                    '0-100': 'Sub 100 RON',
                    '100-300': 'Sub 300 RON'
                };
                const label = priceLabels[this.filters.priceRange] || this.filters.priceRange;
                container.innerHTML += this.createFilterChip('priceRange', '💰 ' + label);
            }

            // Show/hide clear all button
            if (clearBtn) {
                clearBtn.style.display = hasFilters ? 'inline' : 'none';
            }

            // Update results info
            this.updateResultsInfo();
        },

        createFilterChip: function(type, label) {
            return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white rounded-full text-xs font-medium whitespace-nowrap">
                ${label}
                <button onclick="TicsCityPage.removeFilter('${type}')" class="hover:bg-white/20 rounded-full p-0.5 ml-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>`;
        },

        removeFilter: function(type) {
            switch (type) {
                case 'date':
                    this.filters.date = '';
                    document.querySelectorAll('[data-date]').forEach(cb => cb.checked = false);
                    break;
                case 'zone':
                    this.filters.zone = '';
                    document.querySelectorAll('[data-zone]').forEach(cb => cb.checked = false);
                    break;
                case 'category':
                    this.filters.category = '';
                    document.querySelectorAll('#categoriesBar button').forEach((c, i) => {
                        c.classList.toggle('chip-active', i === 0);
                    });
                    break;
                case 'priceRange':
                    this.filters.priceRange = '';
                    document.querySelectorAll('.price-quick-btn').forEach(btn => {
                        btn.classList.remove('bg-gray-900', 'text-white');
                        btn.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    break;
            }
            this.updateActiveFilters();
            this.loadEvents(1);
        },

        updateResultsInfo: function() {
            const parts = [];
            if (this.filters.date) {
                parts.push(filterLabels.date[this.filters.date] || this.filters.date);
            }
            if (this.filters.zone) {
                parts.push(filterLabels.zone[this.filters.zone] || this.filters.zone);
            }
            const info = document.getElementById('resultsInfo');
            if (info) {
                info.textContent = parts.length > 0 ? parts.join(' • ') : 'Toate evenimentele';
            }
        },

        loadEvents: async function(page = 1, append = false) {
            if (isLoading) return;
            isLoading = true;

            const grid = document.getElementById('eventsGrid');
            const loadingState = document.getElementById('loadingState');
            const emptyState = document.getElementById('emptyState');

            if (!append) {
                grid.innerHTML = '';
                loadingState?.classList.remove('hidden');
            }

            try {
                const params = {
                    city: this.filters.city,
                    page: page,
                    per_page: 12
                };

                // Only add non-empty filters
                if (this.filters.category) params.category = this.filters.category;
                if (this.filters.date) params.date = this.filters.date;
                if (this.filters.priceRange) params.price = this.filters.priceRange;

                const response = await TicsAPI.getEvents(params);

                loadingState?.classList.add('hidden');

                if (response.success && response.data && response.data.length > 0) {
                    totalEvents = response.meta?.total || response.data.length;

                    response.data.forEach((event, index) => {
                        // Insert promo card after first 4 events
                        if (!append && index === 4 && typeof TicsEventPromoCard !== 'undefined') {
                            grid.insertAdjacentHTML('beforeend', TicsEventPromoCard.render());
                        }
                        const card = TicsEventCard.render(event);
                        grid.insertAdjacentHTML('beforeend', card);
                    });

                    emptyState?.classList.add('hidden');

                    const shown = Math.min(page * 12, totalEvents);
                    document.getElementById('resultsCount').textContent = totalEvents;
                    document.getElementById('resultsSummary').textContent = `Afișezi ${shown} din ${totalEvents} evenimente`;

                    if (shown >= totalEvents) {
                        document.getElementById('loadMoreBtn').style.display = 'none';
                    } else {
                        document.getElementById('loadMoreBtn').style.display = 'inline-block';
                    }
                } else {
                    if (!append) {
                        emptyState?.classList.remove('hidden');
                        document.getElementById('resultsCount').textContent = '0';
                        document.getElementById('loadMoreBtn').style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error loading events:', error);
                loadingState?.classList.add('hidden');
                if (!append) {
                    grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">Nu am putut încărca evenimentele. Încearcă din nou.</div>';
                }
            }

            isLoading = false;
        },

        clearFilters: function() {
            this.filters = {
                city: citySlug,
                category: '',
                date: '',
                zone: '',
                price: 1000,
                priceRange: '',
                aiMatch: 0
            };

            // Reset UI
            document.querySelectorAll('#categoriesBar button').forEach((c, i) => {
                c.classList.toggle('chip-active', i === 0);
            });
            document.querySelectorAll('.cb').forEach(cb => cb.checked = false);
            const priceRange = document.getElementById('priceRange');
            if (priceRange) {
                priceRange.value = 500;
                document.getElementById('priceLabel').textContent = 'până la 500 RON';
            }

            // Reset price quick buttons
            document.querySelectorAll('.price-quick-btn').forEach(btn => {
                btn.classList.remove('bg-gray-900', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            });

            // Reset AI match buttons
            document.querySelectorAll('[data-ai-match]').forEach((btn, i) => {
                btn.classList.remove('bg-gray-900', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-600');
                if (i === 0) {
                    btn.classList.remove('bg-gray-100', 'text-gray-600');
                    btn.classList.add('bg-gray-900', 'text-white');
                }
            });

            this.updateActiveFilters();
            this.loadEvents(1);
        },

        applyFilters: function() {
            this.loadEvents(1);
        }
    };

    // Filter drawer functions
    function openFiltersDrawer() {
        document.getElementById('filterOverlay')?.classList.add('open');
        document.getElementById('filterDrawer')?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFiltersDrawer() {
        document.getElementById('filterOverlay')?.classList.remove('open');
        document.getElementById('filterDrawer')?.classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close drawer on overlay click
    document.getElementById('filterOverlay')?.addEventListener('click', closeFiltersDrawer);

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        TicsCityPage.init();
    });
</script>

</body>
</html>
