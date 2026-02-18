<?php
/**
 * TICS.ro - Venue Category Listing
 * Category tabs, city filter, venue cards grid, pagination
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$pageTitle = 'Săli de concerte';
$pageDescription = 'Descoperă cele mai bune săli de concerte din România. De la arene mari la cluburi intime, găsește locația perfectă pentru următorul eveniment.';
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Locații', 'url' => '/locatii'],
    ['name' => 'Săli de concerte', 'url' => null],
];

// Venue category tabs
$venueCategories = [
    ['icon' => '&#x1F3DF;&#xFE0F;', 'name' => 'Săli de concerte', 'count' => 48, 'slug' => 'sali-concerte', 'active' => true],
    ['icon' => '&#x1F3AA;', 'name' => 'Festivaluri & Open Air', 'count' => 23, 'slug' => 'festivaluri-open-air', 'active' => false],
    ['icon' => '&#x1F3AD;', 'name' => 'Teatre & Opere', 'count' => 36, 'slug' => 'teatre-opere', 'active' => false],
    ['icon' => '&#x1F378;', 'name' => 'Cluburi & Baruri', 'count' => 92, 'slug' => 'cluburi-baruri', 'active' => false],
    ['icon' => '&#x1F3E2;', 'name' => 'Centre conferințe', 'count' => 18, 'slug' => 'centre-conferinte', 'active' => false],
    ['icon' => '&#x1F333;', 'name' => 'Parcuri & Grădini', 'count' => 15, 'slug' => 'parcuri-gradini', 'active' => false],
];

// City filter chips
$cityChips = [
    ['name' => 'Toate', 'count' => null, 'active' => true],
    ['name' => 'București', 'count' => 18, 'active' => false],
    ['name' => 'Cluj-Napoca', 'count' => 9, 'active' => false],
    ['name' => 'Timișoara', 'count' => 6, 'active' => false],
    ['name' => 'Iași', 'count' => 5, 'active' => false],
    ['name' => 'Brașov', 'count' => 4, 'active' => false],
    ['name' => 'Sibiu', 'count' => 3, 'active' => false],
    ['name' => 'Constanța', 'count' => 3, 'active' => false],
];

// Sort options
$venueSortOptions = ['Cele mai populare', 'Capacitate ↓', 'Capacitate ↑', 'Cele mai multe ev.', 'A → Z'];

$totalVenues = 48;

// Featured venue
$featuredVenue = [
    'name' => 'Sala Palatului',
    'slug' => 'sala-palatului',
    'city' => 'București',
    'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=600&h=400&fit=crop',
    'description' => 'Cea mai prestigioasă sală de spectacole din București, cu o capacitate de peste 4.000 de locuri și o acustică excepțională.',
    'rating' => '4.8',
    'tags' => ['4.000+ locuri', 'Scaune', 'Parcare', 'Accesibil'],
    'upcomingEvents' => 12,
    'featured' => true,
];

// Regular venues
$venues = [
    [
        'name' => 'Arenele Romane',
        'slug' => 'arenele-romane',
        'city' => 'București',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=300&fit=crop',
        'rating' => '4.6',
        'tags' => ['5.000 locuri', 'Open Air'],
        'upcomingEvents' => 8,
    ],
    [
        'name' => 'Fratelli Studios',
        'slug' => 'fratelli-studios',
        'city' => 'București',
        'image' => 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&h=300&fit=crop',
        'rating' => '4.5',
        'tags' => ['2.500 locuri', 'Indoor'],
        'upcomingEvents' => 6,
    ],
    [
        'name' => 'Form Space',
        'slug' => 'form-space',
        'city' => 'Cluj-Napoca',
        'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=400&h=300&fit=crop',
        'rating' => '4.7',
        'tags' => ['1.200 locuri', 'Indoor'],
        'upcomingEvents' => 9,
    ],
    [
        'name' => 'Filarmonica Brașov',
        'slug' => 'filarmonica-brasov',
        'city' => 'Brașov',
        'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=400&h=300&fit=crop',
        'rating' => '4.9',
        'tags' => ['800 locuri', 'Indoor'],
        'upcomingEvents' => 4,
    ],
];

// Pagination
$currentPage = 1;
$totalPages = 3;

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                <a href="/" class="hover:text-gray-900 transition-colors">Acasă</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/locatii" class="hover:text-gray-900 transition-colors">Locații</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-900 font-medium"><?= e($pageTitle) ?></span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2"><?= e($pageTitle) ?></h1>
            <p class="text-gray-600 max-w-2xl"><?= e($pageDescription) ?></p>
        </div>
    </div>

    <!-- Venue Category Tabs -->
    <div class="bg-white border-b border-gray-200 sticky top-16 z-30">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center gap-3 py-3 overflow-x-auto no-scrollbar">
                <?php foreach ($venueCategories as $cat): ?>
                <a href="/locatii/<?= e($cat['slug']) ?>" class="cat-card<?= $cat['active'] ? ' active' : '' ?> flex items-center gap-2.5 px-4 py-2.5 rounded-xl border border-gray-200 whitespace-nowrap group">
                    <span class="text-lg"><?= $cat['icon'] ?></span>
                    <div>
                        <p class="font-medium text-sm <?= $cat['active'] ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' ?>"><?= e($cat['name']) ?></p>
                        <p class="text-xs text-gray-500"><?= $cat['count'] ?> locații</p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-8">

        <!-- City Filter -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto no-scrollbar pb-1">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider whitespace-nowrap mr-1">Oraș:</span>
            <?php foreach ($cityChips as $chip): ?>
            <button class="city-chip<?= $chip['active'] ? ' active' : '' ?> px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-medium<?= !$chip['active'] ? ' text-gray-600' : '' ?> whitespace-nowrap" onclick="toggleCity(this)">
                <?= e($chip['name']) ?>
                <?php if ($chip['count'] !== null): ?>
                <span class="text-gray-400 ml-0.5"><?= $chip['count'] ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Results count + sort -->
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500"><span class="font-semibold text-gray-900"><?= $totalVenues ?></span> locații găsite</p>
            <select class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 cursor-pointer focus:outline-none">
                <?php foreach ($venueSortOptions as $opt): ?>
                <option><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Venues Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">

            <!-- Featured Venue Card -->
            <a href="<?= venueUrl($featuredVenue['slug']) ?>" class="venue-card sm:col-span-2 lg:col-span-2 bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="flex flex-col sm:flex-row">
                    <div class="relative sm:w-72 aspect-video sm:aspect-auto overflow-hidden flex-shrink-0">
                        <img src="<?= e($featuredVenue['image']) ?>" class="absolute inset-0 w-full h-full object-cover venue-img" alt="<?= e($featuredVenue['name']) ?>">
                        <div class="absolute top-3 left-3"><span class="px-2.5 py-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">&#x2B50; Recomandat</span></div>
                    </div>
                    <div class="flex-1 p-5">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-semibold text-gray-900 text-lg group-hover:text-indigo-600 transition-colors"><?= e($featuredVenue['name']) ?></h3>
                                <p class="text-sm text-gray-500 flex items-center gap-1 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    <?= e($featuredVenue['city']) ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-1 px-2.5 py-1 bg-yellow-50 rounded-lg">
                                <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span class="text-sm font-semibold text-gray-900"><?= e($featuredVenue['rating']) ?></span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?= e($featuredVenue['description']) ?></p>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <?php foreach ($featuredVenue['tags'] as $tag): ?>
                            <span class="px-2.5 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded-full"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <div class="flex items-center gap-1 text-sm"><span class="text-indigo-600 font-semibold"><?= $featuredVenue['upcomingEvents'] ?> ev.</span><span class="text-gray-400">viitoare</span></div>
                            <span class="text-sm font-medium text-indigo-600 group-hover:underline">Vezi detalii &rarr;</span>
                        </div>
                    </div>
                </div>
            </a>

            <!-- Regular Venue Cards -->
            <?php foreach ($venues as $venue): ?>
            <a href="<?= venueUrl($venue['slug']) ?>" class="venue-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img src="<?= e($venue['image']) ?>" class="absolute inset-0 w-full h-full object-cover venue-img" alt="<?= e($venue['name']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between mb-1">
                        <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors"><?= e($venue['name']) ?></h3>
                        <div class="flex items-center gap-0.5">
                            <svg class="w-3.5 h-3.5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span class="text-xs font-semibold text-gray-700"><?= e($venue['rating']) ?></span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 flex items-center gap-1 mb-3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        <?= e($venue['city']) ?>
                    </p>
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        <?php foreach ($venue['tags'] as $tag): ?>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <span class="text-xs text-indigo-600 font-medium"><?= $venue['upcomingEvents'] ?> ev.</span>
                        <span class="text-xs font-medium text-gray-400 group-hover:text-indigo-600 transition-colors">Detalii &rarr;</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-center gap-2 mt-10">
            <button class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <button class="w-10 h-10 rounded-full <?= $p === $currentPage ? 'bg-gray-900 text-white' : 'border border-gray-200 text-gray-600 hover:border-gray-300 transition-colors' ?> text-sm font-medium"><?= $p ?></button>
            <?php endfor; ?>
            <button class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-600 hover:border-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    function toggleCity(btn){document.querySelectorAll('.city-chip').forEach(c=>{c.classList.remove('active');c.style.background='';c.style.color=''});btn.classList.add('active')}
    </script>
</body>
</html>
