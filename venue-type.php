<?php
/**
 * Venue Type Page - Lists venues by type (arene, teatre, cluburi, etc.)
 */
require_once __DIR__ . '/includes/config.php';

$typeSlug = $_GET['type'] ?? '';

// Venue types configuration
$venueTypes = [
    'arene' => [
        'name' => 'Arene & Stadioane',
        'title' => 'Arene si Stadioane',
        'description' => 'Locatii mari pentru concerte si evenimente de amploare. Capacitati de la 5.000 la 55.000 de locuri.',
        'hero_image' => 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=1920&q=80',
        'icon' => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/>'
    ],
    'teatre' => [
        'name' => 'Teatre & Sali de Spectacole',
        'title' => 'Teatre si Sali de Spectacole',
        'description' => 'Sali elegante pentru piese de teatru, opere, spectacole de balet si concerte simfonice.',
        'hero_image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1920&q=80',
        'icon' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>'
    ],
    'cluburi' => [
        'name' => 'Cluburi & Baruri',
        'title' => 'Cluburi si Baruri',
        'description' => 'Spatii intime pentru concerte live, DJ sets, petreceri si evenimente culturale.',
        'hero_image' => 'https://images.unsplash.com/photo-1566737236500-c8ac43014a67?w=1920&q=80',
        'icon' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'
    ],
    'open-air' => [
        'name' => 'Open Air & Festivaluri',
        'title' => 'Locatii Open Air',
        'description' => 'Spatii in aer liber perfecte pentru festivaluri, concerte de vara si evenimente outdoor.',
        'hero_image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=1920&q=80',
        'icon' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>'
    ],
    'sali-de-concerte' => [
        'name' => 'Sali de Concerte',
        'title' => 'Sali de Concerte',
        'description' => 'Spatii dedicate muzicii live cu acustica profesionala si facilitati moderne.',
        'hero_image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=1920&q=80',
        'icon' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'
    ],
    'stadiane' => [
        'name' => 'Stadioane',
        'title' => 'Stadioane',
        'description' => 'Cele mai mari arene din Romania pentru evenimente sportive si concerte de amploare.',
        'hero_image' => 'https://images.unsplash.com/photo-1577223625816-7546f13df25d?w=1920&q=80',
        'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>'
    ],
    'centre-culturale' => [
        'name' => 'Centre Culturale',
        'title' => 'Centre Culturale',
        'description' => 'Spatii multifunctionale pentru expozitii, conferinte, spectacole si evenimente culturale.',
        'hero_image' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1920&q=80',
        'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>'
    ],
    'alte-locatii' => [
        'name' => 'Alte Locatii',
        'title' => 'Alte Locatii',
        'description' => 'Spatii unice si neconventionale pentru evenimente speciale.',
        'hero_image' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1920&q=80',
        'icon' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'
    ]
];

// Check if type exists
if (!isset($venueTypes[$typeSlug])) {
    header('Location: /locatii');
    exit;
}

$venueType = $venueTypes[$typeSlug];

// Demo venues data
$demoVenues = [
    'arene' => [
        ['name' => 'Arena Nationala', 'slug' => 'arena-nationala', 'city' => 'Bucuresti', 'capacity' => '55.000', 'events' => 12, 'image' => 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=600'],
        ['name' => 'BT Arena', 'slug' => 'bt-arena', 'city' => 'Cluj-Napoca', 'capacity' => '10.000', 'events' => 8, 'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=600'],
        ['name' => 'Sala Polivalenta', 'slug' => 'sala-polivalenta', 'city' => 'Bucuresti', 'capacity' => '5.000', 'events' => 15, 'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=600'],
        ['name' => 'Arenele Romane', 'slug' => 'arenele-romane', 'city' => 'Bucuresti', 'capacity' => '5.500', 'events' => 10, 'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=600'],
    ],
    'teatre' => [
        ['name' => 'Teatrul National', 'slug' => 'tnb', 'city' => 'Bucuresti', 'capacity' => '1.050', 'events' => 42, 'image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=600'],
        ['name' => 'Sala Palatului', 'slug' => 'sala-palatului', 'city' => 'Bucuresti', 'capacity' => '4.000', 'events' => 28, 'image' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=600'],
        ['name' => 'Opera Nationala', 'slug' => 'opera-nationala', 'city' => 'Bucuresti', 'capacity' => '950', 'events' => 35, 'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=600'],
        ['name' => 'Teatrul de Comedie', 'slug' => 'teatrul-comedie', 'city' => 'Bucuresti', 'capacity' => '600', 'events' => 20, 'image' => 'https://images.unsplash.com/photo-1507924538820-ede94a04019d?w=600'],
    ],
    'cluburi' => [
        ['name' => 'Control Club', 'slug' => 'control-club', 'city' => 'Bucuresti', 'capacity' => '500', 'events' => 65, 'image' => 'https://images.unsplash.com/photo-1566737236500-c8ac43014a67?w=600'],
        ['name' => 'Expirat', 'slug' => 'expirat', 'city' => 'Bucuresti', 'capacity' => '800', 'events' => 48, 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600'],
        ['name' => 'Club Midi', 'slug' => 'club-midi', 'city' => 'Cluj-Napoca', 'capacity' => '400', 'events' => 52, 'image' => 'https://images.unsplash.com/photo-1571266028243-e4733b0f0bb0?w=600'],
        ['name' => 'Flying Circus', 'slug' => 'flying-circus', 'city' => 'Cluj-Napoca', 'capacity' => '600', 'events' => 38, 'image' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=600'],
    ],
    'open-air' => [
        ['name' => 'Romexpo', 'slug' => 'romexpo', 'city' => 'Bucuresti', 'capacity' => '30.000', 'events' => 8, 'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600'],
        ['name' => 'Summer Camp Brezoi', 'slug' => 'summer-camp', 'city' => 'Brezoi', 'capacity' => '15.000', 'events' => 4, 'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=600'],
        ['name' => 'Gradina Uranus', 'slug' => 'gradina-uranus', 'city' => 'Bucuresti', 'capacity' => '2.000', 'events' => 25, 'image' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600'],
        ['name' => 'Quantic Garden', 'slug' => 'quantic-garden', 'city' => 'Bucuresti', 'capacity' => '1.500', 'events' => 18, 'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=600'],
    ]
];

// Get venues for this type (or default to arene)
$venues = $demoVenues[$typeSlug] ?? $demoVenues['arene'];

$pageTitle = $venueType['title'] . ' - ' . SITE_NAME;
$pageDescription = $venueType['description'];
$bodyClass = 'bg-surface';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative pt-32 pb-20">
    <div class="absolute inset-0">
        <img src="<?= $venueType['hero_image'] ?>" alt="<?= htmlspecialchars($venueType['name']) ?>" class="object-cover w-full h-full">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/50 to-black/70"></div>
    </div>
    <div class="relative z-10 px-4 mx-auto max-w-7xl">
        <div class="max-w-3xl pt-16">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6 text-sm text-white/70">
                <a href="/" class="transition-colors hover:text-white">Acasa</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/locatii" class="transition-colors hover:text-white">Locatii</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white"><?= htmlspecialchars($venueType['name']) ?></span>
            </nav>

            <div class="flex items-center gap-4 mb-4">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?= $venueType['icon'] ?>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white md:text-5xl"><?= htmlspecialchars($venueType['title']) ?></h1>
            </div>
            <p class="text-lg text-white/80"><?= htmlspecialchars($venueType['description']) ?></p>

            <!-- Stats -->
            <div class="flex flex-wrap gap-6 mt-8">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/10">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-white"><?= count($venues) ?></p>
                        <p class="text-sm text-white/70">Locatii</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/10">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-white"><?= array_sum(array_column($venues, 'events')) ?></p>
                        <p class="text-sm text-white/70">Evenimente active</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Bar -->
<section class="sticky z-20 py-4 bg-white border-b border-gray-200 top-[72px]">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center gap-4">
            <!-- City Filter -->
            <select class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20" id="cityFilter">
                <option value="">Toate orasele</option>
                <option value="bucuresti">Bucuresti</option>
                <option value="cluj">Cluj-Napoca</option>
                <option value="timisoara">Timisoara</option>
                <option value="iasi">Iasi</option>
                <option value="brasov">Brasov</option>
            </select>

            <!-- Capacity Filter -->
            <select class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20" id="capacityFilter">
                <option value="">Orice capacitate</option>
                <option value="small">Sub 500 locuri</option>
                <option value="medium">500 - 2000 locuri</option>
                <option value="large">2000 - 10000 locuri</option>
                <option value="xlarge">Peste 10000 locuri</option>
            </select>

            <!-- Sort -->
            <select class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 ml-auto" id="sortFilter">
                <option value="popular">Cele mai populare</option>
                <option value="events">Dupa nr. evenimente</option>
                <option value="capacity">Dupa capacitate</option>
                <option value="name">Alfabetic</option>
            </select>
        </div>
    </div>
</section>

<!-- Venues Grid -->
<section class="py-12">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="venuesGrid">
            <?php foreach ($venues as $venue): ?>
            <a href="/locatie/<?= $venue['slug'] ?>" class="overflow-hidden transition-all bg-white border border-gray-200 group rounded-2xl hover:shadow-xl hover:-translate-y-1">
                <div class="relative h-48 overflow-hidden">
                    <img src="<?= $venue['image'] ?>" alt="<?= htmlspecialchars($venue['name']) ?>"
                         class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                    <div class="absolute inset-0 transition-opacity bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60 group-hover:opacity-80"></div>
                    <div class="absolute px-3 py-1 text-xs font-semibold text-white rounded-full bottom-4 left-4 bg-white/20 backdrop-blur-sm">
                        <?= $venue['city'] ?>
                    </div>
                </div>
                <div class="p-5">
                    <h3 class="mb-2 text-lg font-bold text-gray-900 transition-colors group-hover:text-primary"><?= htmlspecialchars($venue['name']) ?></h3>
                    <div class="flex items-center gap-4 mb-3 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?= $venue['capacity'] ?> locuri
                        </span>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <span class="text-sm font-semibold text-primary"><?= $venue['events'] ?> evenimente</span>
                        <span class="flex items-center gap-1 text-sm text-gray-400 transition-colors group-hover:text-primary">
                            Vezi detalii
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Load More -->
        <div class="mt-12 text-center">
            <button class="inline-flex items-center gap-2 px-8 py-3 font-semibold text-gray-700 transition-all bg-white border border-gray-200 rounded-xl hover:border-primary hover:text-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Incarca mai multe locatii
            </button>
        </div>
    </div>
</section>

<!-- Other Venue Types -->
<section class="py-12 bg-gray-50">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-8 text-2xl font-bold text-gray-900">Alte tipuri de locatii</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($venueTypes as $slug => $type):
                if ($slug === $typeSlug) continue;
            ?>
            <a href="/locatii/<?= $slug ?>" class="flex items-center gap-4 p-4 transition-all bg-white border border-gray-200 rounded-xl hover:border-primary hover:shadow-lg group">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 transition-colors bg-gray-100 rounded-xl group-hover:bg-primary/10">
                    <svg class="w-6 h-6 text-gray-600 transition-colors group-hover:text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?= $type['icon'] ?>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate group-hover:text-primary"><?= htmlspecialchars($type['name']) ?></h3>
                </div>
                <svg class="flex-shrink-0 w-5 h-5 text-gray-400 transition-transform group-hover:text-primary group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php require_once __DIR__ . '/includes/scripts.php'; ?>
