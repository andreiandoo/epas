<?php
/**
 * Main Site Header with Mega Menu Navigation
 *
 * Variables available:
 * - $currentPage: Current page identifier for active state
 * - $transparentHeader: If true, header starts transparent and becomes solid on scroll
 *
 * Dynamic data arrays can be overridden before including this file
 *
 * Features:
 * - Instant search with structured suggestions (min 2 chars)
 * - Cached navigation counts (6h TTL)
 * - Dynamic quick links for filtered events
 * - Transparent mode for hero-integrated pages
 */

$currentPage = $currentPage ?? '';
$transparentHeader = $transparentHeader ?? false;

// Include navigation cache helper
require_once __DIR__ . '/nav-cache.php';

// ==================== NAVIGATION DATA ====================
// These can be overridden before including this file
// Counts are automatically updated from cache

// Cities for mega menu - loaded from API with caching (30 min TTL)
$navCities = $navCities ?? getFeaturedCities();

// Event categories - loaded from API with caching (30 min TTL)
$navCategories = $navCategories ?? getEventCategories();

// Featured/trending events - loaded from API with caching (15 min TTL)
$navFeaturedEvents = $navFeaturedEvents ?? getTrendingEvents();

// Venues/Locations - loaded from API with caching (30 min TTL)
// Shows featured venues (is_featured = true) from admin
$navVenues = $navVenues ?? getFeaturedVenues();

// Venue types/categories - loaded from API with caching (30 min TTL)
// Shows venue categories from marketplace admin
$navVenueTypes = $navVenueTypes ?? getVenueCategories();

// Quick links for events section - link to filtered events page
$navQuickLinks = $navQuickLinks ?? [
    ['name' => 'Weekend', 'slug' => 'evenimente?filter=weekend', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ['name' => 'Gratuite', 'slug' => 'evenimente?filter=gratuite', 'icon' => '<path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6"/><path d="M12 3v12"/><path d="M12 3L8 7"/><path d="M12 3l4 4"/><rect x="2" y="7" width="20" height="5" rx="1"/>'],
    ['name' => 'Noi', 'slug' => 'evenimente?filter=noi', 'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>']
];

// ==================== APPLY CACHED COUNTS ====================
// Note: Categories and cities already have counts from their respective API caches
// Only apply nav counts to items that don't already have count from API
// $navCategories - already has 'count' from getEventCategories() API
// $navCities - already has 'count' from getFeaturedCities() API
// $navVenues - already has 'count' (events_count) from getFeaturedVenues() API
$navVenueTypes = applyNavCounts($navVenueTypes, 'venue_types');
?>

<!-- Search Overlay -->
<div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[2000] opacity-0 invisible transition-all duration-300" id="searchOverlay" style="visibility:hidden"></div>

<!-- Search Container -->
<div class="fixed top-0 left-0 right-0 bg-white z-[2001] -translate-y-full transition-transform duration-300" id="searchContainer" style="visibility:hidden">
    <div class="max-w-[800px] mx-auto p-6">
        <div class="flex items-center gap-4 px-5 pr-1 transition-colors border-2 border-gray-200 bg-gray-50 rounded-2xl focus-within:border-primary mobile:px-2">
            <svg class="flex-shrink-0 w-6 h-6 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" class="flex-1 py-4 text-lg text-gray-900 bg-transparent border-none outline-none placeholder:text-gray-400 mobile:placeholder:text-sm" placeholder="Caută evenimente, artiști, locații..." id="searchInput" autocomplete="off">
            <button class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white transition-colors bg-gray-900 rounded-xl hover:bg-gray-800" id="searchCloseBtn" aria-label="Închide căutarea">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="max-w-[800px] mx-auto px-6 pb-6 max-h-[calc(100vh-120px)] overflow-y-auto">
        <!-- Search Results Container -->
        <div id="searchResults" class="hidden">
            <!-- Loading state -->
            <div id="searchLoading" class="hidden py-8 text-center">
                <div class="inline-block w-8 h-8 border-gray-200 rounded-full border-3 border-t-primary animate-spin"></div>
                <p class="mt-3 text-sm text-gray-500">Se caută...</p>
            </div>

            <!-- No results -->
            <div id="searchNoResults" class="hidden py-8 text-center">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                    <path d="M8 8l6 6M14 8l-6 6"/>
                </svg>
                <p class="text-gray-500">Nu am găsit rezultate pentru căutarea ta.</p>
                <p class="mt-1 text-sm text-gray-400">Încearcă cu alți termeni.</p>
            </div>

            <!-- Results by category -->
            <div id="searchResultsContent">
                <!-- Events Section -->
                <div id="searchEventsSection" class="hidden mb-6">
                    <div class="flex items-center gap-2 px-1 mb-3">
                        <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Evenimente</span>
                        <span id="searchEventsCount" class="text-[11px] text-gray-400"></span>
                    </div>
                    <div id="searchEventsList" class="space-y-2"></div>
                </div>

                <!-- Artists Section -->
                <div id="searchArtistsSection" class="hidden mb-6">
                    <div class="flex items-center gap-2 px-1 mb-3">
                        <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18V5l12-2v13"/>
                            <circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                        </svg>
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Artiști</span>
                        <span id="searchArtistsCount" class="text-[11px] text-gray-400"></span>
                    </div>
                    <div id="searchArtistsList" class="space-y-2"></div>
                </div>

                <!-- Locations Section -->
                <div id="searchLocationsSection" class="hidden mb-6">
                    <div class="flex items-center gap-2 px-1 mb-3">
                        <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Locații</span>
                        <span id="searchLocationsCount" class="text-[11px] text-gray-400"></span>
                    </div>
                    <div id="searchLocationsList" class="space-y-2"></div>
                </div>
            </div>

            <!-- View all results link -->
            <div id="searchViewAll" class="hidden pt-4 border-t border-gray-200">
                <a href="#" id="searchViewAllLink" class="flex items-center justify-center gap-2 py-3 text-sm font-semibold transition-all rounded-lg bg-gray-50 text-primary hover:bg-primary hover:text-white">
                    Vezi toate rezultatele
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Min chars hint -->
        <div id="searchMinChars" class="hidden py-6 text-sm text-center text-gray-400">
            Introdu cel puțin 2 caractere pentru a căuta
        </div>
    </div>
</div>

<?php if (defined('USE_STAGE_API') && USE_STAGE_API): ?>
<div id="stage-banner" class="fixed top-0 left-0 right-0 z-[1100] bg-amber-500 text-black text-center text-xs font-bold py-1 tracking-wide">
    STAGE API — Datele sunt de test
    <a href="?use_stage=0" class="ml-3 underline hover:no-underline">Dezactiveaza</a>
</div>
<style>#header { top: 28px !important; } body { padding-top: 28px; }</style>
<?php endif; ?>

<!-- Header -->
<header class="fixed top-0 left-0 right-0 z-[1000] transition-all duration-300 <?= $transparentHeader ? 'header-transparent bg-transparent border-transparent' : 'bg-white border-b border-gray-200' ?>" id="header" data-transparent="<?= $transparentHeader ? 'true' : 'false' ?>">
    <!-- Top Bar (default) -->
    <div class="mobile:hidden bg-secondary text-white text-sm py-2.5 transition-all duration-200 ease-in-out <?= $transparentHeader ? 'hidden' : '' ?>" id="headerTopBar" style="display:none">
        <div class="flex items-center justify-between px-4 mx-auto max-w-7xl">
            <p class="items-center hidden gap-2 sm:flex">
                <svg class="w-4 h-4 text-primary-light" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
                Peste 500+ evenimente disponibile în toată România
            </p>
            <div class="flex items-center gap-6 ml-auto">
                <a href="/ajutor" class="hover:text-primary-light transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Ajutor
                </a>
                <a href="/ghid-organizatori" class="hover:text-primary-light transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Organizatori
                </a>
            </div>
        </div>
    </div>
    <!-- Top Bar (cart timer - replaces default when cart has items) -->
    <div class="mobile:hidden hidden text-sm py-2.5 transition-all duration-200 ease-in-out bg-warning border-b border-warning/20" id="headerTimerBar">
        <div class="flex items-center justify-center gap-3 px-4 mx-auto max-w-7xl">
            <svg class="w-5 h-5 text-warning" id="headerTimerIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-secondary" id="headerTimerText">Rezervare expiră în</span>
            <span class="font-bold text-white tabular-nums" id="headerTimerCountdown">--:--</span>
            <a href="/cos" class="ml-2 font-medium text-primary hover:underline">Vezi coșul →</a>
        </div>
    </div>
    <div class="mx-auto max-w-7xl mobile:px-4">
        <div class="flex items-center justify-between h-[67px]">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-2.5 no-underline flex-shrink-0">
                <img src="/assets/images/ambilet_logo.webp" width="88" height="40" alt="<?= SITE_NAME ?>" class="h-10 w-auto header-logo <?= $transparentHeader ? 'brightness-0 invert' : '' ?>">
                
                <svg class="hidden w-8 h-8" viewBox="0 0 48 48" fill="none">
                    <defs>
                        <linearGradient id="logoGrad" x1="6" y1="10" x2="42" y2="38">
                            <stop stop-color="#A51C30"/>
                            <stop offset="1" stop-color="#C41E3A"/>
                        </linearGradient>
                    </defs>
                    <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#logoGrad)"/>
                    <line x1="17" y1="15" x2="31" y2="15" stroke="white" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="15" y1="19" x2="33" y2="19" stroke="white" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                    <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
                </svg>
                <div class="text-[22px] font-extrabold flex mobile:hidden">
                    <span id="logoTextAm" class="<?= $transparentHeader ? 'text-white/85' : 'text-slate-800' ?>">Am</span>
                    <span id="logoTextBilet" class="<?= $transparentHeader ? 'text-white' : 'text-primary' ?>">Bilet</span>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="items-center hidden gap-1 lg:flex" id="headerNav">
                <!-- Cities Dropdown -->
                <div class="relative group">
                    <button class="nav-btn flex items-center gap-1.5 px-4 py-2.5 text-[15px] font-medium rounded-lg transition-all cursor-pointer border-none bg-transparent <?= $transparentHeader ? 'text-white/90 hover:text-white hover:bg-white/10' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Orașe
                        <svg class="w-4 h-4 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <?php if (!empty($navCities)): ?>
                    <div class="absolute invisible pt-3 transition-all duration-200 -translate-x-1/2 opacity-0 pointer-events-none top-full left-1/2 group-hover:opacity-100 group-hover:visible group-hover:pointer-events-auto">
                        <div class="w-[700px] bg-white border border-gray-200 rounded-2xl shadow-xl py-3 px-2 rounded-tl-none rounded-tr-none pb-2">
                            <div class="flex items-center justify-between px-1 mb-2">
                                <span class="text-[13px] font-semibold text-gray-400 uppercase tracking-wider">Alege orașul</span>
                                <a href="/orase" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                                    Toate orașele
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                            <div class="grid grid-cols-5 gap-3">
                                <?php foreach ($navCities as $city): ?>
                                <a href="/<?= $city['slug'] ?>" class="relative rounded-xl overflow-hidden aspect-square group/card hover:-translate-y-1 hover:shadow-lg transition-all duration-300 <?= $city['featured'] ? 'col-span-2 row-span-2' : '' ?>">
                                    <img src="<?= $city['image'] ?>" alt="<?= htmlspecialchars($city['name']) ?>" class="object-cover w-full h-full transition-transform duration-500 group-hover/card:scale-110">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent"></div>
                                    <div class="absolute bottom-0 left-0 right-0 p-3.5">
                                        <div class="<?= $city['featured'] ? 'text-[22px]' : 'text-[15px]' ?> font-bold text-white mb-0.5"><?= htmlspecialchars($city['name']) ?></div>
                                        <div class="<?= $city['featured'] ? 'text-sm' : 'text-xs' ?> text-white/85 flex items-center gap-1.5 hidden">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                            <?= $city['count'] ?> evenimente
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Events Dropdown -->
                <div class="relative group">
                    <button class="nav-btn flex items-center gap-1.5 px-4 py-2.5 text-[15px] font-medium rounded-lg transition-all cursor-pointer border-none bg-transparent <?= $transparentHeader ? 'text-white/90 hover:text-white hover:bg-white/10' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Evenimente
                        <svg class="w-4 h-4 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="absolute invisible pt-3 transition-all duration-200 -translate-x-1/2 opacity-0 pointer-events-none top-full left-1/2 group-hover:opacity-100 group-hover:visible group-hover:pointer-events-auto">
                        <div class="w-[820px] bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden flex rounded-tl-none rounded-tr-none pb-2">
                            <!-- Categories -->
                            <div class="w-[250px] px-3 py-2 bg-gray-50 border-r border-gray-200">
                                <?php if (!empty($navCategories)): ?>
                                <?php foreach ($navCategories as $index => $cat): ?>
                                <a href="/<?= $cat['slug'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-white hover:text-gray-900 hover:shadow-sm transition-all mb-1 group/cat <?= $index === 0 ? 'bg-white text-gray-900 shadow-sm' : '' ?>" title="<?= htmlspecialchars($cat['name']) ?>">
                                    <div class="w-[38px] h-[38px] bg-white rounded-lg flex items-center justify-center shadow-sm text-gray-500 transition-all group-hover/cat:bg-gradient-to-br group-hover/cat:from-primary group-hover/cat:to-primary-light group-hover/cat:text-white <?= $index === 0 ? '!bg-gradient-to-br !from-primary !to-primary-light !text-white' : '' ?>">
                                        <?php
                                        // Check icon types: SVG path, heroicon name, or emoji
                                        $hasSvgPath = !empty($cat['icon']) && (str_contains($cat['icon'], '<path') || str_contains($cat['icon'], '<circle') || str_contains($cat['icon'], '<rect') || str_contains($cat['icon'], '<line') || str_contains($cat['icon'], '<polygon'));
                                        $heroiconPath = !empty($cat['icon']) && str_starts_with($cat['icon'], 'heroicon-') ? getHeroiconPath($cat['icon']) : null;
                                        $hasEmoji = !empty($cat['icon_emoji']);
                                        ?>
                                        <?php if ($hasSvgPath): ?>
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $cat['icon'] ?></svg>
                                        <?php elseif ($heroiconPath): ?>
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $heroiconPath ?></svg>
                                        <?php elseif ($hasEmoji): ?>
                                        <span class="text-xl"><?= $cat['icon_emoji'] ?></span>
                                        <?php else: ?>
                                        <span class="text-xl">🎫</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-sm font-semibold"><?= htmlspecialchars($cat['name']) ?></div>
                                        <div class="hidden text-xs text-gray-400"><?= $cat['count'] ?? 0 ?> evenimente</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 transition-all group-hover/cat:text-primary group-hover/cat:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 18l6-6-6-6"/>
                                    </svg>
                                </a>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="flex flex-col items-center justify-center py-8 text-sm text-gray-400">
                                    <a href="/evenimente" class="flex items-center gap-2 text-primary hover:underline">
                                        Toate evenimentele
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Featured Events -->
                            <div class="flex flex-col justify-between flex-1 pt-3 pb-2">
                                <div class="flex items-center justify-between px-3 mb-4">
                                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 23c-1.1 0-1.99-.89-1.99-1.99h3.98c0 1.1-.89 1.99-1.99 1.99zm8.29-4.71L19 17V11c0-3.35-2.36-6.15-5.5-6.83V3c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v1.17C7.36 4.85 5 7.65 5 11v6l-1.29 1.29c-.63.63-.19 1.71.7 1.71h15.17c.9 0 1.34-1.08.71-1.71z"/>
                                        </svg>
                                        Trending acum
                                    </span>
                                    <a href="/evenimente" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                                        Toate evenimentele
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>

                                <div class="flex flex-col px-3">
                                <?php if (!empty($navFeaturedEvents)): ?>
                                <?php foreach ($navFeaturedEvents as $event): ?>
                                    <a href="/bilete/<?= $event['slug'] ?>" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all mb-2 justify-between">
                                        <div class="w-[72px] h-[72px] rounded-lg overflow-hidden flex-shrink-0 flex-none bg-gray-100">
                                            <?php if (!empty($event['image'])): ?>
                                            <img src="<?= $event['image'] ?>" alt="<?= htmlspecialchars($event['name']) ?>" class="object-cover w-full h-full">
                                            <?php else: ?>
                                            <div class="flex items-center justify-center w-full h-full text-gray-300">
                                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col justify-center flex-1 ml-0">
                                            <?php if (!empty($event['category'])): ?>
                                            <div class="text-[11px] font-semibold text-primary uppercase tracking-wide mb-1"><?= htmlspecialchars($event['category']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-sm font-bold text-gray-900 mb-1.5 leading-tight"><?= htmlspecialchars($event['name']) ?></div>
                                            <div class="flex gap-3 text-xs text-gray-500">
                                                <?php if (!empty($event['date'])): ?><span><?= htmlspecialchars($event['date']) ?></span><?php endif; ?>
                                                <?php if (!empty($event['venue'])): ?><span><?= htmlspecialchars($event['venue']) ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($event['price']) && $event['price'] > 0): ?>
                                        <div class="mr-0 text-sm font-bold text-emerald-500">de la <?= number_format($event['price'], 0) ?> lei</div>
                                        <?php else: ?>
                                        <div class="mr-0 text-sm font-bold text-emerald-500">Gratuit</div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="flex items-center justify-center py-6 text-sm text-gray-400">
                                        <a href="/evenimente" class="flex items-center gap-2 text-primary hover:underline">
                                            Vezi toate evenimentele
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                </div>

                                <div class="flex gap-2 px-3 pt-2 mt-4 border-t border-gray-200">
                                    <?php foreach ($navQuickLinks as $link): ?>
                                    <a href="/<?= $link['slug'] ?>" class="flex-1 flex items-center justify-center gap-1.5 py-2.5 bg-gray-50 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-primary hover:text-white transition-all">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $link['icon'] ?></svg>
                                        <?= htmlspecialchars($link['name']) ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Artists -->
                <div class="relative">
                    <a href="/artisti" class="nav-btn flex items-center gap-1.5 px-4 py-2.5 text-[15px] font-medium rounded-lg transition-all <?= $transparentHeader ? 'text-white/90 hover:text-white hover:bg-white/10' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' ?> <?= $currentPage === 'artists' ? '!text-primary font-semibold' : '' ?>">Artiști</a>
                </div>

                <!-- Locations Dropdown -->
                <div class="relative group">
                    <button class="nav-btn flex items-center gap-1.5 px-4 py-2.5 text-[15px] font-medium rounded-lg transition-all cursor-pointer border-none bg-transparent <?= $transparentHeader ? 'text-white/90 hover:text-white hover:bg-white/10' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' ?>">
                        Locații
                        <svg class="w-4 h-4 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="absolute invisible pt-3 transition-all duration-200 -translate-x-1/2 opacity-0 pointer-events-none top-full left-1/2 group-hover:opacity-100 group-hover:visible group-hover:pointer-events-auto">
                        <div class="w-[780px] bg-white border border-gray-200 rounded-2xl shadow-xl py-3 px-2 rounded-tl-none rounded-tr-none pb-2">
                            <div class="flex items-center justify-between px-1 mb-2">
                                <span class="text-[13px] font-semibold text-gray-400 uppercase tracking-wider">Locații populare</span>
                                <a href="/locatii" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                                    Toate locațiile
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                            <?php if (!empty($navVenues)): ?>
                            <div class="grid grid-cols-3 gap-4">
                                <?php foreach ($navVenues as $venue): ?>
                                <a href="/locatie/<?= $venue['slug'] ?>" class="flex gap-3.5 p-4 bg-gray-50 rounded-xl border border-transparent hover:bg-white hover:border-gray-200 hover:shadow-md transition-all">
                                    <div class="flex-shrink-0 w-16 h-16 overflow-hidden bg-gray-100 rounded-lg">
                                        <?php if (!empty($venue['image'])): ?>
                                        <img src="<?= $venue['image'] ?>" alt="<?= htmlspecialchars($venue['name']) ?>" class="object-cover w-full h-full">
                                        <?php else: ?>
                                        <div class="flex items-center justify-center w-full h-full text-gray-300">
                                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col justify-center flex-1">
                                        <div class="mb-1 text-sm font-bold text-gray-900"><?= htmlspecialchars($venue['name']) ?></div>
                                        <div class="text-xs text-gray-500 mb-1.5 leading-tight"><?= htmlspecialchars($venue['address']) ?></div>
                                        <div class="text-[11px] font-semibold text-primary hidden"><?= $venue['count'] ?> evenimente</div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="flex items-center justify-center py-8 text-sm text-gray-400">
                                <a href="/locatii" class="flex items-center gap-2 text-primary hover:underline">
                                    Explorează toate locațiile
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($navVenueTypes)): ?>
                            <div class="flex gap-2.5 mt-5 pt-5 border-t border-gray-200">
                                <?php foreach ($navVenueTypes as $type): ?>
                                <a href="/locatii?category=<?= urlencode($type['slug']) ?>" class="flex-1 flex items-center gap-2.5 px-4 py-3 bg-gray-50 rounded-lg text-gray-600 hover:bg-primary hover:text-white transition-all group/type">
                                    <div class="flex items-center justify-center transition-all bg-white rounded-lg w-9 h-9 group-hover/type:bg-white/20 group-hover/type:text-white">
                                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $type['icon'] ?></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-[13px] font-semibold"><?= htmlspecialchars($type['name']) ?></div>
                                        <div class="text-[11px] opacity-70"><?= $type['count'] ?> locații</div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-3" id="headerActions">
                <button class="action-btn flex items-center justify-center w-10 h-10 transition-all rounded-lg <?= $transparentHeader ? 'text-white/90 bg-white/10 hover:bg-white/20 hover:text-white' : 'text-gray-500 bg-gray-100 hover:bg-gray-200 hover:text-gray-900' ?>" id="searchBtn" aria-label="Caută">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                </button>

                <!-- Cart Button -->
                <button type="button" id="cartBtn" class="action-btn p-2.5 rounded-xl transition-colors relative <?= $transparentHeader ? 'hover:bg-white/10' : 'hover:bg-gray-100' ?>" aria-label="Coș de cumpărături">
                    <svg class="w-5 h-5 <?= $transparentHeader ? 'text-white/90' : 'text-gray-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="cartIcon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                    <span class="cart-badge absolute -top-0.5 -right-0.5 w-5 h-5 bg-primary text-white text-[10px] font-bold rounded-full items-center justify-center shadow-sm hidden" id="cartBadge">0</span>
                </button>

                <!-- Login -->
                <a href="/autentificare" class="login-btn hidden sm:flex items-center gap-2 px-4 py-2.5 border-2 rounded-xl font-semibold transition-all <?= $transparentHeader ? 'border-white/30 text-white hover:border-white hover:bg-white/10' : 'border-border hover:border-primary hover:text-primary' ?>" id="loginBtn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="hidden md:inline">Contul meu</span>
                </a>

                <!-- User Menu (shown when logged in) -->
                <div id="headerUserMenu" class="" style="display: none;">
                    <div class="relative dropdown">
                        <button onclick="this.parentElement.classList.toggle('active')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-surface transition-colors" name="userMenuBtn">
                            <div class="flex items-center justify-center rounded-full w-9 h-9 bg-primary/10">
                                <span id="headerUserInitials" class="text-sm font-bold text-primary">--</span>
                            </div>
                            <svg class="hidden w-4 h-4 text-muted sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="absolute right-0 w-56 py-2 mt-2 overflow-hidden bg-white border shadow-lg dropdown-menu top-full rounded-xl border-border">
                            <div class="px-4 py-3 border-b border-border">
                                <p id="headerUserName" class="font-semibold text-secondary">Utilizator</p>
                                <p id="headerUserEmail" class="text-xs text-muted">email@example.com</p>
                            </div>
                            <!-- Customer Links (hidden when organizer) -->
                            <div id="headerCustomerLinks">
                                <a href="/cont/dashboard" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                    Contul meu
                                </a>
                                <a href="/cont/bilete" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                    </svg>
                                    Biletele mele
                                </a>
                                <a href="/cont/comenzi" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Comenzile mele
                                </a>
                                <a href="/cont/setari" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Setări
                                </a>
                            </div>
                            <!-- Organizer Links (hidden by default, shown when organizer) -->
                            <div id="headerOrganizerLinks" style="display: none;">
                                <a href="/organizator/panou" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    Panoul meu
                                </a>
                                <a href="/organizator/events" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Evenimentele mele
                                </a>
                                <a href="/organizator/sold" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Sold & Plăți
                                </a>
                                <a href="/organizator/setari" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Setări
                                </a>
                            </div>
                            <hr class="my-2 border-border">
                            <button onclick="AmbiletAuth.logout();" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-error hover:bg-surface" name="logoutBtn">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Deconectare
                            </button>
                        </div>
                    </div>
                </div>
                
                <button class="action-btn flex items-center justify-center w-10 h-10 rounded-lg lg:hidden <?= $transparentHeader ? 'text-white bg-white/10 hover:bg-white/20' : 'text-primary bg-gray-100' ?>" id="mobileMenuBtn" aria-label="Meniu">
                    <svg viewBox="0 0 24 24" style="enable-background:new 0 0 512 512" class="w-6 h-6"><g><g fill="currentColor"><path d="M11.25 7.75A.75.75 0 0 1 12 7h5.25a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75zM11.25 12a.75.75 0 0 1 .75-.75h5.25a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75zM6.75 15.5a.75.75 0 0 0 0 1.5h10.5a.75.75 0 0 0 0-1.5zM9.5 8a.75.75 0 0 0-1.28-.53l-2 2a.75.75 0 0 0 0 1.06l2 2A.75.75 0 0 0 9.5 12z" fill="currentColor"  class=""></path></g></g></svg>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Menu -->
<div class="hidden fixed top-[72px] left-0 right-0 bottom-0 bg-white z-[999] overflow-y-auto lg:hidden" id="mobileMenu">
    <!-- Cities -->
    <?php if (!empty($navCities)): ?>
    <div class="px-4 border-b border-gray-200" data-dropdown>
        <div class="flex items-center justify-between py-4 text-base font-semibold text-gray-900 cursor-pointer mobile-nav-link">
            Orașe
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="hidden pb-4 mobile-dropdown">
            <div class="grid grid-cols-2 gap-2.5 mb-3">
                <?php foreach (array_slice($navCities, 0, 4) as $city): ?>
                <a href="/<?= $city['slug'] ?>" class="relative overflow-hidden rounded-lg aspect-video">
                    <img src="<?= $city['image'] ?>" alt="<?= htmlspecialchars($city['name']) ?>" class="object-cover w-full h-full">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-2.5">
                        <div class="text-base font-bold text-white"><?= htmlspecialchars($city['name']) ?></div>
                        <div class="text-sm text-white/85 flex items-center gap-1.5 hidden">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                            <?= $city['count'] ?> evenimente
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <a href="/orase" class="block py-3 text-sm font-semibold text-center text-white rounded-lg bg-primary">Vezi toate orașele →</a>
        </div>
    </div>
    <?php else: ?>
    <div class="px-4 border-b border-gray-200">
        <a href="/orase" class="block py-4 text-base font-semibold text-gray-900">Orașe</a>
    </div>
    <?php endif; ?>

    <!-- Events -->
    <div class="px-4 border-b border-gray-200" <?php if (!empty($navCategories)): ?>data-dropdown<?php endif; ?>>
        <?php if (!empty($navCategories)): ?>
        <div class="flex items-center justify-between py-4 text-base font-semibold text-gray-900 cursor-pointer mobile-nav-link">
            Evenimente
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="hidden pb-4 mobile-dropdown">
            <?php foreach ($navCategories as $cat):
                $hasSvgPath = !empty($cat['icon']) && (str_contains($cat['icon'], '<path') || str_contains($cat['icon'], '<circle') || str_contains($cat['icon'], '<rect') || str_contains($cat['icon'], '<line') || str_contains($cat['icon'], '<polygon'));
                $heroiconPath = !empty($cat['icon']) && str_starts_with($cat['icon'], 'heroicon-') ? getHeroiconPath($cat['icon']) : null;
                $hasEmoji = !empty($cat['icon_emoji']);
            ?>
            <a href="/<?= $cat['slug'] ?>" class="flex items-center gap-3 p-3 mb-2 text-gray-900 rounded-lg bg-gray-50">
                <div class="flex items-center justify-center text-gray-500 bg-white rounded-lg shadow-sm w-9 h-9">
                    <?php if ($hasSvgPath): ?>
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $cat['icon'] ?></svg>
                    <?php elseif ($heroiconPath): ?>
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $heroiconPath ?></svg>
                    <?php elseif ($hasEmoji): ?>
                    <span class="text-lg"><?= $cat['icon_emoji'] ?></span>
                    <?php else: ?>
                    <span class="text-lg">🎫</span>
                    <?php endif; ?>
                </div>
                <span class="text-base font-semibold"><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <a href="/evenimente" class="block py-3 mt-2 text-sm font-semibold text-center text-white rounded-lg bg-primary">Toate evenimentele →</a>
        </div>
        <?php else: ?>
        <a href="/evenimente" class="block py-4 text-base font-semibold text-gray-900">Evenimente</a>
        <?php endif; ?>
    </div>

    <!-- Artists -->
    <div class="px-4 border-b border-gray-200">
        <a href="/artisti" class="block py-4 text-base font-semibold text-gray-900">Artiști</a>
    </div>

    <!-- Locations -->
    <div class="px-4 border-b border-gray-200" <?php if (!empty($navVenueTypes)): ?>data-dropdown<?php endif; ?>>
        <?php if (!empty($navVenueTypes)): ?>
        <div class="flex items-center justify-between py-4 text-base font-semibold text-gray-900 cursor-pointer mobile-nav-link">
            Locații
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="hidden pb-4 mobile-dropdown">
            <?php foreach ($navVenueTypes as $type): ?>
            <a href="/locatii?category=<?= urlencode($type['slug']) ?>" class="flex items-center gap-3 p-3 mb-2 text-gray-900 rounded-lg bg-gray-50">
                <div class="flex items-center justify-center text-gray-500 bg-white rounded-lg shadow-sm w-9 h-9">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $type['icon'] ?></svg>
                </div>
                <span class="text-base font-semibold"><?= htmlspecialchars($type['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <a href="/locatii" class="block py-3 mt-2 text-sm font-semibold text-center text-white rounded-lg bg-primary">Toate locațiile →</a>
        </div>
        <?php else: ?>
        <a href="/locatii" class="block py-4 text-base font-semibold text-gray-900">Locații</a>
        <?php endif; ?>
    </div>

    <!-- Mobile Actions -->
    <div class="flex flex-col gap-3 px-4 pt-6 mt-6">
        <a href="/autentificare" class="w-full py-3.5 text-center border border-gray-200 rounded-lg text-gray-900 text-[15px] font-semibold hover:bg-gray-50 transition-all">Autentificare</a>
        <a href="/inregistrare" class="w-full py-3.5 text-center bg-gradient-to-br from-primary to-primary-light rounded-lg text-white text-[15px] font-semibold">Înregistrare</a>
    </div>
</div>

<!-- Cart Drawer Overlay -->
<div id="cartOverlay" class="fixed inset-0 z-[1001] bg-black/50 opacity-0 invisible transition-all duration-300" style="visibility:hidden"></div>

<!-- Cart Drawer -->
<div id="cartDrawer" class="fixed top-0 right-0 bottom-0 w-96 max-w-[90vw] bg-white z-[1002] translate-x-full transition-transform duration-300 flex flex-col" style="visibility:hidden">
    <!-- Header -->
    <div class="flex items-center justify-between flex-shrink-0 p-4 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
            <span class="text-lg font-bold text-gray-900">Biletele mele</span>
            <span id="cartDrawerCount" class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-bold rounded-full hidden">0</span>
        </div>
        <button id="cartCloseBtn" class="p-2 transition-colors rounded-lg hover:bg-gray-100" aria-label="Închide coșul">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Cart Content - Scrollable -->
    <div class="flex-1 overflow-y-auto">
        <!-- Empty State -->
        <div id="cartEmpty" class="flex flex-col items-center justify-center h-full p-6 text-center">
            <div class="flex items-center justify-center w-20 h-20 mb-4 bg-gray-100 rounded-full">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-gray-900">Coșul tău este gol</h3>
            <p class="mb-6 text-gray-500">Adaugă bilete pentru a continua</p>
            <a href="/evenimente" class="px-6 py-3 font-semibold text-white transition-all bg-gradient-to-br from-primary to-primary-light rounded-xl hover:shadow-lg">
                Explorează evenimente
            </a>
        </div>

        <!-- Cart Items -->
        <div id="cartItems" class="hidden p-4 space-y-3">
            <!-- Items will be populated by JS -->
        </div>
    </div>

    <!-- Footer with Total and Buttons -->
    <div id="cartFooter" class="flex-shrink-0 hidden p-4 border-t border-gray-200 bg-gray-50">
        <!-- Subtotal -->
        <div class="flex items-center justify-between mb-4">
            <span class="text-gray-600">Subtotal</span>
            <span id="cartSubtotal" class="text-lg font-bold text-gray-900">0 lei</span>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2">
            <a href="/finalizare" class="flex items-center justify-center w-full gap-2 px-4 py-3 font-semibold text-white transition-all bg-gradient-to-br from-primary to-primary-light rounded-xl hover:shadow-lg">
                Finalizează comanda
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="/cos" class="flex items-center justify-center w-full gap-2 px-4 py-3 font-semibold text-gray-700 transition-all bg-white border border-gray-200 rounded-xl hover:bg-gray-50">
                Vezi coșul complet
            </a>
        </div>
    </div>
</div>


<script defer src="<?= asset('assets/js/header.js') ?>"></script>
