<?php
/**
 * Organizers Page - Lista Organizatorilor
 * Ambilet Marketplace
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Organizatori — ' . SITE_NAME;
$pageDescription = 'Descoperă organizatorii de evenimente din România. De la festivaluri mari la petreceri intime, găsește evenimentele perfecte pentru tine.';


// Stats
$stats = [
    ['value' => '450+', 'label' => 'Organizatori activi'],
    ['value' => '2.8K', 'label' => 'Evenimente în 2024'],
    ['value' => '1.2M', 'label' => 'Bilete vândute'],
    ['value' => '42', 'label' => 'Orașe acoperite']
];

// Featured organizers
$featuredOrganizers = [
    [
        'slug' => 'events-pro',
        'name' => 'Events Pro România',
        'type' => 'Festivaluri & Concerte mari',
        'cover' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&h=200&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1560179707-f14e90ef3623?w=200&h=200&fit=crop',
        'verified' => true,
        'pro' => true,
        'stats' => [
            ['value' => '156', 'label' => 'Evenimente'],
            ['value' => '245K', 'label' => 'Bilete'],
            ['value' => '4.9', 'label' => 'Rating']
        ]
    ],
    [
        'slug' => 'untold',
        'name' => 'UNTOLD Festival',
        'type' => 'Festival internațional',
        'cover' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=600&h=200&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200&h=200&fit=crop',
        'verified' => true,
        'pro' => true,
        'stats' => [
            ['value' => '8', 'label' => 'Ediții'],
            ['value' => '1.5M', 'label' => 'Participanți'],
            ['value' => '5.0', 'label' => 'Rating']
        ]
    ],
    [
        'slug' => 'comedy-club',
        'name' => 'The Comedy Club',
        'type' => 'Stand-up Comedy',
        'cover' => 'https://images.unsplash.com/photo-1527224857830-43a7acc85260?w=600&h=200&fit=crop',
        'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
        'verified' => true,
        'pro' => false,
        'stats' => [
            ['value' => '320', 'label' => 'Show-uri'],
            ['value' => '89K', 'label' => 'Spectatori'],
            ['value' => '4.8', 'label' => 'Rating']
        ]
    ]
];

// Categories
$categories = [
    ['slug' => 'all', 'name' => 'Toți', 'count' => 450, 'icon' => 'grid'],
    ['slug' => 'concerts', 'name' => 'Concerte', 'count' => 124, 'icon' => 'music'],
    ['slug' => 'festivals', 'name' => 'Festivaluri', 'count' => 45, 'icon' => 'star'],
    ['slug' => 'theatre', 'name' => 'Teatru', 'count' => 78, 'icon' => 'book'],
    ['slug' => 'standup', 'name' => 'Stand-up', 'count' => 56, 'icon' => 'smile'],
    ['slug' => 'sport', 'name' => 'Sport', 'count' => 67, 'icon' => 'play'],
    ['slug' => 'clubs', 'name' => 'Cluburi & Party', 'count' => 80, 'icon' => 'moon']
];

// Regular organizers grid
$organizers = [
    [
        'slug' => 'sound-events',
        'name' => 'Sound Events Romania',
        'type' => 'Concerte & Festivaluri',
        'location' => 'București, România',
        'verified' => true,
        'avatar' => 'https://images.unsplash.com/photo-1560179707-f14e90ef3623?w=200&h=200&fit=crop',
        'gradient' => 'blue',
        'stats' => ['events' => 89, 'followers' => '45K', 'rating' => '4.7'],
        'upcoming' => 5
    ],
    [
        'slug' => 'theatre-national',
        'name' => 'Teatrul Național București',
        'type' => 'Teatru & Artele spectacolului',
        'location' => 'București, România',
        'verified' => true,
        'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
        'gradient' => 'purple',
        'stats' => ['events' => 234, 'followers' => '78K', 'rating' => '4.9'],
        'upcoming' => 12
    ],
    [
        'slug' => 'comedy-night',
        'name' => 'Comedy Night Romania',
        'type' => 'Stand-up Comedy',
        'location' => 'Cluj-Napoca, România',
        'verified' => false,
        'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop',
        'gradient' => 'orange',
        'stats' => ['events' => 156, 'followers' => '23K', 'rating' => '4.6'],
        'upcoming' => 8
    ],
    [
        'slug' => 'sport-arena',
        'name' => 'Sport Arena Events',
        'type' => 'Evenimente sportive',
        'location' => 'Timișoara, România',
        'verified' => true,
        'avatar' => 'https://images.unsplash.com/photo-1560179707-f14e90ef3623?w=200&h=200&fit=crop',
        'gradient' => 'green',
        'stats' => ['events' => 67, 'followers' => '34K', 'rating' => '4.5'],
        'upcoming' => 3
    ],
    [
        'slug' => 'electric-castle',
        'name' => 'Electric Castle',
        'type' => 'Festival de muzică',
        'location' => 'Bonțida, Cluj',
        'verified' => true,
        'avatar' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200&h=200&fit=crop',
        'gradient' => 'red',
        'stats' => ['events' => 9, 'followers' => '890K', 'rating' => '4.9'],
        'upcoming' => 1
    ],
    [
        'slug' => 'club-nights',
        'name' => 'Club Nights Bucharest',
        'type' => 'Cluburi & Petreceri',
        'location' => 'București, România',
        'verified' => false,
        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&h=200&fit=crop',
        'gradient' => 'pink',
        'stats' => ['events' => 245, 'followers' => '56K', 'rating' => '4.4'],
        'upcoming' => 15
    ]
];

// Gradient classes
$gradients = [
    'blue' => 'bg-gradient-to-br from-blue-500 to-blue-600',
    'purple' => 'bg-gradient-to-br from-purple-500 to-purple-600',
    'orange' => 'bg-gradient-to-br from-amber-500 to-amber-600',
    'green' => 'bg-gradient-to-br from-emerald-500 to-emerald-600',
    'red' => 'bg-gradient-to-br from-primary to-primary-dark',
    'pink' => 'bg-gradient-to-br from-pink-500 to-pink-600'
];
 
// CTA features
$ctaFeatures = [
    ['icon' => 'card', 'title' => 'Plăți securizate', 'desc' => 'Primești banii rapid în cont'],
    ['icon' => 'chart', 'title' => 'Statistici detaliate', 'desc' => 'Dashboard complet în timp real'],
    ['icon' => 'users', 'title' => 'Suport dedicat', 'desc' => 'Echipă disponibilă 24/7'],
    ['icon' => 'clock', 'title' => 'Setup rapid', 'desc' => 'Primul eveniment în 5 minute']
];

require_once __DIR__ . '/includes/head.php';
$transparentHeader = true;
require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="relative px-6 pt-32 pb-16 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute rounded-full -top-24 -right-24 w-96 h-96 bg-primary/30 blur-3xl"></div>
        <div class="absolute rounded-full -bottom-36 -left-24 w-80 h-80 bg-primary/20 blur-3xl"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">Organizatori</h1>
            <p class="text-lg leading-relaxed text-white/70">Descoperă organizatorii de evenimente din România. De la festivaluri mari la petreceri intime, găsește evenimentele perfecte pentru tine.</p>
        </div>
    </section>

    <!-- Search Section -->
    <section class="relative z-10 max-w-2xl px-6 mx-auto -mt-7">
        <div class="flex items-center p-2 bg-white shadow-xl rounded-2xl">
            <input type="text" class="flex-1 px-5 py-4 text-base placeholder-gray-400 bg-transparent outline-none text-secondary" placeholder="Caută un organizator...">
            <button class="flex items-center gap-2 px-7 py-3.5 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35 transition-all">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                Caută
            </button>
        </div>
    </section>

    <main class="max-w-6xl px-6 py-12 mx-auto">
        <!-- Stats Section -->
        <section class="grid grid-cols-2 gap-5 mb-12 lg:grid-cols-4">
            <?php foreach ($stats as $stat): ?>
            <div class="p-6 text-center bg-white border border-gray-200 rounded-2xl">
                <div class="mb-1 text-3xl font-extrabold lg:text-4xl text-primary"><?= $stat['value'] ?></div>
                <div class="text-sm text-gray-500"><?= $stat['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Featured Organizers -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="flex items-center gap-2 text-2xl font-bold text-secondary">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    Organizatori de top
                </h2>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($featuredOrganizers as $org): ?>
                <a href="/organizer/<?= $org['slug'] ?>" class="overflow-hidden transition-all group bg-gradient-to-br from-slate-800 to-slate-700 rounded-2xl hover:-translate-y-1 hover:shadow-2xl">
                    <div class="relative bg-center bg-cover h-28" style="background-image: url('<?= $org['cover'] ?>')">
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-slate-800/80"></div>
                    </div>
                    <div class="relative p-5 -mt-12">
                        <div class="relative z-10 w-20 h-20 mb-4 overflow-hidden bg-white border-4 rounded-2xl border-slate-800">
                            <img src="<?= $org['avatar'] ?>" alt="<?= $org['name'] ?>" class="object-cover w-full h-full">
                        </div>
                        <div class="absolute top-[-30px] right-5 flex gap-2">
                            <?php if ($org['verified']): ?>
                            <span class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-500 rounded-md text-[11px] font-semibold text-white">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                                Verificat
                            </span>
                            <?php endif; ?>
                            <?php if ($org['pro']): ?>
                            <span class="px-2.5 py-1.5 bg-gradient-to-r from-amber-500 to-amber-600 rounded-md text-[11px] font-bold text-white">PRO</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="mb-1 text-xl font-bold text-white"><?= $org['name'] ?></h3>
                        <p class="mb-4 text-sm text-white/60"><?= $org['type'] ?></p>
                        <div class="flex gap-5">
                            <?php foreach ($org['stats'] as $stat): ?>
                            <div class="text-center">
                                <div class="text-lg font-bold text-white"><?= $stat['value'] ?></div>
                                <div class="text-[11px] text-white/50 uppercase tracking-wider"><?= $stat['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Category Tabs -->
        <div class="flex flex-wrap gap-2 pb-2 mb-8 overflow-x-auto">
            <?php foreach ($categories as $index => $cat): ?>
            <button class="category-tab flex items-center gap-2 px-3 py-3 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-secondary transition-all whitespace-nowrap <?= $index === 0 ? 'active !bg-gradient-to-r !from-primary !to-primary-dark !border-primary !text-white' : '' ?>" data-category="<?= $cat['slug'] ?>">
                <?php if ($cat['icon'] === 'grid'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <?php elseif ($cat['icon'] === 'music'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                </svg>
                <?php elseif ($cat['icon'] === 'star'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <?php elseif ($cat['icon'] === 'book'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <?php elseif ($cat['icon'] === 'smile'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
                </svg>
                <?php elseif ($cat['icon'] === 'play'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
                </svg>
                <?php elseif ($cat['icon'] === 'moon'): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                </svg>
                <?php endif; ?>
                <?= $cat['name'] ?>
                <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs font-semibold"><?= $cat['count'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Filters Bar -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <select class="py-3 px-4 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="">Toate orașele</option>
                    <option value="bucuresti">București</option>
                    <option value="cluj">Cluj-Napoca</option>
                    <option value="timisoara">Timișoara</option>
                    <option value="iasi">Iași</option>
                    <option value="constanta">Constanța</option>
                </select>
                <select class="py-3 px-4 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="popular">Cei mai populari</option>
                    <option value="events">După evenimente</option>
                    <option value="rating">După rating</option>
                    <option value="recent">Adăugați recent</option>
                </select>
            </div>
            <span class="text-sm text-gray-500">Se afișează <strong class="text-secondary">450 organizatori</strong></span>
        </div>

        <!-- Organizers Grid -->
        <div class="grid grid-cols-1 gap-6 mb-12 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($organizers as $org): ?>
            <a href="/organizer/<?= $org['slug'] ?>" class="overflow-hidden transition-all bg-white border border-gray-200 group rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
                <div class="h-24 <?= $gradients[$org['gradient']] ?> relative">
                    <div class="absolute -bottom-9 left-6 w-[72px] h-[72px] rounded-2xl border-4 border-white bg-white overflow-hidden shadow-lg">
                        <img src="<?= $org['avatar'] ?>" alt="<?= $org['name'] ?>" class="object-cover w-full h-full">
                    </div>
                    <?php if ($org['verified']): ?>
                    <div class="absolute -bottom-2 left-[80px] w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border-2 border-white">
                        <svg class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="px-6 pt-12 pb-6">
                    <h3 class="mb-1 text-lg font-bold text-secondary"><?= $org['name'] ?></h3>
                    <p class="mb-4 text-sm text-gray-500"><?= $org['type'] ?></p>
                    <p class="flex items-center gap-1.5 text-sm text-gray-400 mb-4">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <?= $org['location'] ?>
                    </p>
                    <div class="flex gap-4 pt-4 border-t border-gray-100">
                        <div class="flex-1 text-center">
                            <div class="text-base font-bold text-secondary"><?= $org['stats']['events'] ?></div>
                            <div class="text-[11px] text-gray-400 uppercase tracking-wider">Evenimente</div>
                        </div>
                        <div class="flex-1 text-center">
                            <div class="text-base font-bold text-secondary"><?= $org['stats']['followers'] ?></div>
                            <div class="text-[11px] text-gray-400 uppercase tracking-wider">Urmăritori</div>
                        </div>
                        <div class="flex-1 text-center">
                            <div class="text-base font-bold text-secondary"><?= $org['stats']['rating'] ?></div>
                            <div class="text-[11px] text-gray-400 uppercase tracking-wider">Rating</div>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6">
                    <div class="flex items-center gap-2 px-4 py-3 text-sm font-semibold bg-red-50 rounded-xl text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <?= $org['upcoming'] ?> <?= $org['upcoming'] === 1 ? 'eveniment viitor' : 'evenimente viitoare' ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-center gap-2 mb-12">
            <button class="flex items-center justify-center w-10 h-10 text-gray-500 bg-white border border-gray-200 opacity-50 cursor-not-allowed rounded-xl">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
            <a href="#" class="flex items-center justify-center w-10 h-10 font-semibold text-white border bg-gradient-to-r from-primary to-primary-dark border-primary rounded-xl">1</a>
            <a href="#" class="flex items-center justify-center w-10 h-10 font-semibold text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">2</a>
            <a href="#" class="flex items-center justify-center w-10 h-10 font-semibold text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">3</a>
            <a href="#" class="flex items-center justify-center w-10 h-10 font-semibold text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">4</a>
            <span class="flex items-center justify-center w-10 h-10 text-gray-400">...</span>
            <a href="#" class="flex items-center justify-center w-10 h-10 font-semibold text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">15</a>
            <a href="#" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>

        <!-- CTA Section -->
        <section class="relative grid items-center grid-cols-1 gap-12 p-12 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl lg:grid-cols-2">
            <div class="absolute rounded-full -top-24 -right-24 w-72 h-72 bg-primary/30 blur-3xl"></div>
            <div class="relative z-10">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-primary/20 border border-primary/30 rounded-full text-xs font-semibold text-red-300 mb-4">Pentru organizatori</span>
                <h2 class="mb-3 text-3xl font-extrabold text-white">Organizezi evenimente?</h2>
                <p class="mb-6 leading-relaxed text-white/70">Alătură-te comunității de organizatori Ambilet și ajunge la mii de pasionați de evenimente. Vânzări online, statistici în timp real și suport dedicat.</p>
                <a href="/become-organizer" class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/40 transition-all">
                    Începe acum gratuit
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="relative z-10 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php foreach ($ctaFeatures as $feature): ?>
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white bg-white/10 rounded-xl">
                        <?php if ($feature['icon'] === 'card'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'chart'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'users'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'clock'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1 text-base font-bold text-white"><?= $feature['title'] ?></h4>
                        <p class="text-sm text-white/60"><?= $feature['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php require_once __DIR__ . '/includes/scripts.php'; ?>

    <script>
        // Category tabs
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.category-tab').forEach(t => {
                    t.classList.remove('active', '!bg-gradient-to-r', '!from-primary', '!to-primary-dark', '!border-primary', '!text-white');
                });
                tab.classList.add('active', '!bg-gradient-to-r', '!from-primary', '!to-primary-dark', '!border-primary', '!text-white');
            });
        });
    </script>
