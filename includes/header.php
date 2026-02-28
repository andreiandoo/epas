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
<div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[2000] opacity-0 invisible transition-all duration-300" id="searchOverlay"></div>

<!-- Search Container -->
<div class="fixed top-0 left-0 right-0 bg-white z-[2001] -translate-y-full transition-transform duration-300 shadow-xl" id="searchContainer">
    <div class="max-w-[800px] mx-auto p-6">
        <div class="flex items-center gap-4 px-5 pr-1 transition-colors border-2 border-gray-200 bg-gray-50 rounded-2xl focus-within:border-primary mobile:px-2">
            <svg class="flex-shrink-0 w-6 h-6 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" class="flex-1 py-4 text-lg text-gray-900 bg-transparent border-none outline-none placeholder:text-gray-400 mobile:placeholder:text-sm" placeholder="Caută evenimente, artiști, locații..." id="searchInput" autocomplete="off">
            <button class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white transition-colors bg-gray-900 rounded-xl hover:bg-gray-800" id="searchCloseBtn">
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
    <div class="px-6 mx-auto max-w-7xl mobile:px-4">
        <div class="flex items-center justify-between h-[72px]">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-2.5 no-underline flex-shrink-0">
                <img src="/assets/images/ambilet_logo.webp" alt="<?= SITE_NAME ?>" class="h-10 w-auto header-logo mobile:hidden <?= $transparentHeader ? 'brightness-0 invert' : '' ?>">
                
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
                <div class="text-[22px] font-extrabold flex">
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
                    <div class="absolute invisible pt-3 transition-all duration-200 -translate-x-1/2 opacity-0 pointer-events-none top-full left-1/2 group-hover:opacity-100 group-hover:visible group-hover:pointer-events-auto">
                        <div class="w-[700px] bg-white border border-gray-200 rounded-2xl shadow-xl py-3 px-2">
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
                                        <div class="<?= $city['featured'] ? 'text-sm' : 'text-xs' ?> text-white/85 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                            <?= $city['count'] ?> evenimente
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
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
                        <div class="w-[820px] bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden flex">
                            <!-- Categories -->
                            <div class="w-[250px] px-3 py-2 bg-gray-50 border-r border-gray-200">
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
                                        <div class="text-xs text-gray-400"><?= $cat['count'] ?? 0 ?> evenimente</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 transition-all group-hover/cat:text-primary group-hover/cat:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 18l6-6-6-6"/>
                                    </svg>
                                </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- Featured Events -->
                            <div class="flex flex-col justify-between flex-1 p-3">
                                <div class="flex items-center justify-between mb-4">
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

                                <div class="flex flex-col">
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

                                <div class="flex gap-2 pt-4 mt-4 border-t border-gray-200">
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
                        <div class="w-[780px] bg-white border border-gray-200 rounded-2xl shadow-xl py-3 px-2">
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
                                        <div class="text-[11px] font-semibold text-primary"><?= $venue['count'] ?> evenimente</div>
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
                            <div class="flex gap-2.5 mt-5 pt-5 border-t border-gray-200">
                                <?php foreach ($navVenueTypes as $type): ?>
                                <a href="/locatii/<?= $type['slug'] ?>" class="flex-1 flex items-center gap-2.5 px-4 py-3 bg-gray-50 rounded-lg text-gray-600 hover:bg-primary hover:text-white transition-all group/type">
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
                <div id="headerUserMenu" class="mobile:hidden" style="display: none;">
                    <div class="relative dropdown">
                        <button onclick="this.parentElement.classList.toggle('active')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-surface transition-colors">
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
                            <button onclick="AmbiletAuth.logout(); setTimeout(() => window.location.href='/', 100);" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-error hover:bg-surface">
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
                        <div class="text-sm font-bold text-white"><?= htmlspecialchars($city['name']) ?></div>
                        <div class="text-xs text-white/85 flex items-center gap-1.5">
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

    <!-- Events -->
    <div class="px-4 border-b border-gray-200" data-dropdown>
        <div class="flex items-center justify-between py-4 text-base font-semibold text-gray-900 cursor-pointer mobile-nav-link">
            Evenimente
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="hidden pb-4 mobile-dropdown">
            <?php foreach (array_slice($navCategories, 0, 5) as $cat): ?>
            <a href="/<?= $cat['slug'] ?>" class="flex items-center gap-3 p-3 mb-2 text-gray-900 rounded-lg bg-gray-50">
                <div class="flex items-center justify-center text-gray-500 bg-white rounded-lg shadow-sm w-9 h-9">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $cat['icon'] ?></svg>
                </div>
                <span class="text-sm font-semibold"><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <a href="/evenimente" class="block py-3 mt-2 text-sm font-semibold text-center text-white rounded-lg bg-primary">Toate evenimentele →</a>
        </div>
    </div>

    <!-- Artists -->
    <div class="px-4 border-b border-gray-200">
        <a href="/artisti" class="block py-4 text-base font-semibold text-gray-900">Artiști</a>
    </div>

    <!-- Locations -->
    <div class="px-4 border-b border-gray-200" data-dropdown>
        <div class="flex items-center justify-between py-4 text-base font-semibold text-gray-900 cursor-pointer mobile-nav-link">
            Locații
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="hidden pb-4 mobile-dropdown">
            <?php foreach ($navVenueTypes as $type): ?>
            <a href="/locatii/<?= $type['slug'] ?>" class="flex items-center gap-3 p-3 mb-2 text-gray-900 rounded-lg bg-gray-50">
                <div class="flex items-center justify-center text-gray-500 bg-white rounded-lg shadow-sm w-9 h-9">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $type['icon'] ?></svg>
                </div>
                <span class="text-sm font-semibold"><?= htmlspecialchars($type['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <a href="/locatii" class="block py-3 mt-2 text-sm font-semibold text-center text-white rounded-lg bg-primary">Toate locațiile →</a>
        </div>
    </div>

    <!-- Mobile Actions -->
    <div class="flex flex-col gap-3 px-4 pt-6 mt-6">
        <a href="/autentificare" class="w-full py-3.5 text-center border border-gray-200 rounded-lg text-gray-900 text-[15px] font-semibold hover:bg-gray-50 transition-all">Autentificare</a>
        <a href="/inregistrare" class="w-full py-3.5 text-center bg-gradient-to-br from-primary to-primary-light rounded-lg text-white text-[15px] font-semibold">Înregistrare</a>
    </div>
</div>

<!-- Cart Drawer Overlay -->
<div id="cartOverlay" class="fixed inset-0 z-[1001] bg-black/50 opacity-0 invisible transition-all duration-300"></div>

<!-- Cart Drawer -->
<div id="cartDrawer" class="fixed top-0 right-0 bottom-0 w-96 max-w-[90vw] bg-white z-[1002] shadow-2xl translate-x-full transition-transform duration-300 flex flex-col">
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

<script>
(function() {
    // ==================== CONFIGURATION ====================
    const SEARCH_MIN_CHARS = 2;
    const SEARCH_DEBOUNCE_MS = 300;
    const SEARCH_API_URL = '/api/v1/public/search';

    // ==================== DOM ELEMENTS ====================
    const header = document.getElementById('header');
    const searchBtn = document.getElementById('searchBtn');
    const searchOverlay = document.getElementById('searchOverlay');
    const searchContainer = document.getElementById('searchContainer');
    const searchCloseBtn = document.getElementById('searchCloseBtn');
    const searchInput = document.getElementById('searchInput');
    const searchQuickLinks = document.getElementById('searchQuickLinks');
    const searchResults = document.getElementById('searchResults');
    const searchLoading = document.getElementById('searchLoading');
    const searchNoResults = document.getElementById('searchNoResults');
    const searchResultsContent = document.getElementById('searchResultsContent');
    const searchMinChars = document.getElementById('searchMinChars');
    const searchViewAll = document.getElementById('searchViewAll');
    const searchViewAllLink = document.getElementById('searchViewAllLink');

    // Section elements
    const searchEventsSection = document.getElementById('searchEventsSection');
    const searchEventsList = document.getElementById('searchEventsList');
    const searchEventsCount = document.getElementById('searchEventsCount');
    const searchArtistsSection = document.getElementById('searchArtistsSection');
    const searchArtistsList = document.getElementById('searchArtistsList');
    const searchArtistsCount = document.getElementById('searchArtistsCount');
    const searchLocationsSection = document.getElementById('searchLocationsSection');
    const searchLocationsList = document.getElementById('searchLocationsList');
    const searchLocationsCount = document.getElementById('searchLocationsCount');

    // ==================== SCROLL EFFECT ====================
    const isTransparentMode = header.dataset.transparent === 'true';
    const headerTopBar = document.getElementById('headerTopBar');
    const headerTimerBarRef = document.getElementById('headerTimerBar');
    const headerLogo = document.querySelector('.header-logo');
    const logoTextAm = document.getElementById('logoTextAm');
    const logoTextBilet = document.getElementById('logoTextBilet');
    const navBtns = document.querySelectorAll('.nav-btn');
    const actionBtns = document.querySelectorAll('.action-btn');
    const loginBtn = document.getElementById('loginBtn');
    const cartIcon = document.getElementById('cartIcon');

    function updateHeaderState(isScrolled) {
        if (isTransparentMode) {
            if (isScrolled) {
                // Solid mode
                header.classList.remove('bg-transparent', 'border-transparent', 'header-transparent');
                header.classList.add('bg-white', 'border-b', 'border-gray-200', 'shadow-lg');
                headerTopBar?.classList.add('hidden');
                headerLogo?.classList.remove('brightness-0', 'invert');

                // Logo text colors
                if (logoTextAm) {
                    logoTextAm.classList.remove('text-white/85');
                    logoTextAm.classList.add('text-slate-800');
                }
                if (logoTextBilet) {
                    logoTextBilet.classList.remove('text-white');
                    logoTextBilet.classList.add('text-primary');
                }

                navBtns.forEach(btn => {
                    btn.classList.remove('text-white/90', 'hover:text-white', 'hover:bg-white/10');
                    btn.classList.add('text-gray-500', 'hover:text-gray-900', 'hover:bg-gray-100');
                });

                actionBtns.forEach(btn => {
                    btn.classList.remove('text-white/90', 'text-white', 'bg-white/10', 'hover:bg-white/10', 'hover:bg-white/20');
                    btn.classList.add('text-gray-500', 'bg-gray-100', 'hover:bg-gray-200', 'hover:text-gray-900');
                });

                if (loginBtn) {
                    loginBtn.classList.remove('border-white/30', 'text-white', 'hover:border-white', 'hover:bg-white/10');
                    loginBtn.classList.add('border-border', 'hover:border-primary', 'hover:text-primary');
                }

                if (cartIcon) {
                    cartIcon.classList.remove('text-white/90');
                    cartIcon.classList.add('text-gray-500');
                }
            } else {
                // Transparent mode
                header.classList.add('bg-transparent', 'border-transparent', 'header-transparent');
                header.classList.remove('bg-white', 'border-b', 'border-gray-200', 'shadow-lg');
                // Only show headerTopBar if timer bar is not active
                if (!headerTimerBarRef || headerTimerBarRef.classList.contains('hidden')) {
                    headerTopBar?.classList.remove('hidden');
                }
                headerLogo?.classList.add('brightness-0', 'invert');

                // Logo text colors
                if (logoTextAm) {
                    logoTextAm.classList.add('text-white/85');
                    logoTextAm.classList.remove('text-slate-800');
                }
                if (logoTextBilet) {
                    logoTextBilet.classList.add('text-white');
                    logoTextBilet.classList.remove('text-primary');
                }

                navBtns.forEach(btn => {
                    btn.classList.add('text-white/90', 'hover:text-white', 'hover:bg-white/10');
                    btn.classList.remove('text-gray-500', 'hover:text-gray-900', 'hover:bg-gray-100');
                });

                actionBtns.forEach(btn => {
                    btn.classList.add('text-white/90', 'bg-white/10', 'hover:bg-white/20');
                    btn.classList.remove('text-gray-500', 'bg-gray-100', 'hover:bg-gray-200', 'hover:text-gray-900');
                });

                if (loginBtn) {
                    loginBtn.classList.add('border-white/30', 'text-white', 'hover:border-white', 'hover:bg-white/10');
                    loginBtn.classList.remove('border-border', 'hover:border-primary', 'hover:text-primary');
                }

                if (cartIcon) {
                    cartIcon.classList.add('text-white/90');
                    cartIcon.classList.remove('text-gray-500');
                }
            }
        } else {
            // Non-transparent mode - just add shadow on scroll
            header.classList.toggle('shadow-lg', isScrolled);
            // Only toggle headerTopBar if timer bar is not active
            if (!headerTimerBarRef || headerTimerBarRef.classList.contains('hidden')) {
                headerTopBar.classList.toggle('hidden', isScrolled);
            } else {
                // Keep headerTopBar hidden when timer bar is active
                headerTopBar.classList.add('hidden');
            }
        }
    }

    window.addEventListener('scroll', () => {
        updateHeaderState(window.scrollY > 50);
    });

    // Initial state
    updateHeaderState(window.scrollY > 50);

    // ==================== SEARCH PANEL ====================
    function openSearch() {
        searchOverlay.classList.remove('opacity-0', 'invisible');
        searchOverlay.classList.add('opacity-100', 'visible');
        searchContainer.classList.remove('-translate-y-full');
        searchContainer.classList.add('translate-y-0');
        searchInput.focus();
        document.body.style.overflow = 'hidden';
    }

    function closeSearch() {
        searchOverlay.classList.add('opacity-0', 'invisible');
        searchOverlay.classList.remove('opacity-100', 'visible');
        searchContainer.classList.add('-translate-y-full');
        searchContainer.classList.remove('translate-y-0');
        document.body.style.overflow = '';
        // Reset search state
        searchInput.value = '';
        resetSearchUI();
    }

    searchBtn.addEventListener('click', openSearch);
    searchCloseBtn.addEventListener('click', closeSearch);
    searchOverlay.addEventListener('click', closeSearch);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !searchOverlay.classList.contains('invisible')) {
            closeSearch();
        }
        // Ctrl/Cmd + K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
    });

    // ==================== INSTANT SEARCH ====================
    let searchTimeout = null;
    let currentSearchQuery = '';

    function resetSearchUI() {
        searchQuickLinks.classList.remove('hidden');
        searchResults.classList.add('hidden');
        searchMinChars.classList.add('hidden');
        searchLoading.classList.add('hidden');
        searchNoResults.classList.add('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
        searchEventsSection.classList.add('hidden');
        searchArtistsSection.classList.add('hidden');
        searchLocationsSection.classList.add('hidden');
    }

    function showLoading() {
        searchQuickLinks.classList.add('hidden');
        searchMinChars.classList.add('hidden');
        searchResults.classList.remove('hidden');
        searchLoading.classList.remove('hidden');
        searchNoResults.classList.add('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
    }

    function showNoResults() {
        searchLoading.classList.add('hidden');
        searchNoResults.classList.remove('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
    }

    function showMinChars() {
        searchQuickLinks.classList.add('hidden');
        searchResults.classList.add('hidden');
        searchMinChars.classList.remove('hidden');
    }

    function renderResults(data) {
        searchLoading.classList.add('hidden');
        searchNoResults.classList.add('hidden');

        const hasEvents = data.events && data.events.length > 0;
        const hasArtists = data.artists && data.artists.length > 0;
        const hasLocations = data.locations && data.locations.length > 0;
        const hasAnyResults = hasEvents || hasArtists || hasLocations;

        if (!hasAnyResults) {
            showNoResults();
            return;
        }

        searchResultsContent.classList.remove('hidden');

        // Render Events
        if (hasEvents) {
            searchEventsSection.classList.remove('hidden');
            searchEventsCount.textContent = `(${data.events.length})`;
            searchEventsList.innerHTML = data.events.slice(0, 4).map(event => `
                <a href="/bilete/${event.slug}" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">
                    <div class="flex-shrink-0 overflow-hidden bg-gray-100 rounded-lg w-14 h-14">
                        ${event.image ? `<img src="${event.image}" alt="${escapeHtml(event.name)}" class="object-cover w-full h-full">` : `
                        <div class="flex items-center justify-center w-full h-full text-gray-400">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>`}
                    </div>
                    <div class="flex flex-col justify-center flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900 truncate">${escapeHtml(event.name)}</div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                            <span>${escapeHtml(event.date || '')}</span>
                            ${event.venue ? `<span class="truncate">${escapeHtml(event.venue)}</span>` : ''}
                        </div>
                        ${event.price ? `<div class="mt-1 text-sm font-semibold text-emerald-500">de la ${event.price} lei</div>` : ''}
                    </div>
                </a>
            `).join('');
        } else {
            searchEventsSection.classList.add('hidden');
        }

        // Render Artists
        if (hasArtists) {
            searchArtistsSection.classList.remove('hidden');
            searchArtistsCount.textContent = `(${data.artists.length})`;
            searchArtistsList.innerHTML = data.artists.slice(0, 4).map(artist => `
                <a href="/artist/${artist.slug}" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">
                    <div class="flex-shrink-0 w-12 h-12 overflow-hidden bg-gray-100 rounded-full">
                        ${artist.image ? `<img src="${artist.image}" alt="${escapeHtml(artist.name)}" class="object-cover w-full h-full">` : `
                        <div class="flex items-center justify-center w-full h-full text-gray-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                            </svg>
                        </div>`}
                    </div>
                    <div class="flex flex-col justify-center flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900 truncate">${escapeHtml(artist.name)}</div>
                        <div class="text-xs text-gray-500">${escapeHtml(artist.genre || artist.type || '')}</div>
                    </div>
                </a>
            `).join('');
        } else {
            searchArtistsSection.classList.add('hidden');
        }

        // Render Locations
        if (hasLocations) {
            searchLocationsSection.classList.remove('hidden');
            searchLocationsCount.textContent = `(${data.locations.length})`;
            searchLocationsList.innerHTML = data.locations.slice(0, 4).map(location => `
                <a href="/locatie/${location.slug}" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">
                    <div class="flex-shrink-0 w-12 h-12 overflow-hidden bg-gray-100 rounded-lg">
                        ${location.image ? `<img src="${location.image}" alt="${escapeHtml(location.name)}" class="object-cover w-full h-full">` : `
                        <div class="flex items-center justify-center w-full h-full text-gray-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>`}
                    </div>
                    <div class="flex flex-col justify-center flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900 truncate">${escapeHtml(location.name)}</div>
                        <div class="text-xs text-gray-500 truncate">${escapeHtml(location.address || location.city || '')}</div>
                    </div>
                </a>
            `).join('');
        } else {
            searchLocationsSection.classList.add('hidden');
        }

        // Show "View All" link if there are more results
        const totalResults = (data.events?.length || 0) + (data.artists?.length || 0) + (data.locations?.length || 0);
        if (totalResults > 4) {
            searchViewAll.classList.remove('hidden');
            searchViewAllLink.href = `/cauta?q=${encodeURIComponent(currentSearchQuery)}`;
        } else {
            searchViewAll.classList.add('hidden');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function performSearch(query) {
        currentSearchQuery = query;

        if (query.length < SEARCH_MIN_CHARS) {
            if (query.length > 0) {
                showMinChars();
            } else {
                resetSearchUI();
            }
            return;
        }

        showLoading();

        try {
            const response = await fetch(`${SEARCH_API_URL}?q=${encodeURIComponent(query)}&limit=5`);

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const data = await response.json();
            renderResults(data);
        } catch (error) {
            console.error('Search error:', error);
            // Fallback: show mock results for demo purposes
            // Remove this in production and show error message instead
            renderMockResults(query);
        }
    }

    // Mock results for demo (remove in production when API is ready)
    function renderMockResults(query) {
        const mockData = {
            events: [
                { name: `Concert ${query}`, slug: 'concert-' + query.toLowerCase().replace(/\s+/g, '-'), date: '15 Ian 2025', venue: 'Sala Palatului', price: 150, image: null },
                { name: `Festival ${query} 2025`, slug: 'festival-' + query.toLowerCase().replace(/\s+/g, '-') + '-2025', date: '20-22 Feb 2025', venue: 'Cluj-Napoca', price: 299, image: null }
            ],
            artists: [
                { name: query.charAt(0).toUpperCase() + query.slice(1), slug: 'artist-demo', genre: 'Pop/Rock', image: null }
            ],
            locations: [
                { name: `Arena ${query}`, slug: 'arena-demo', city: 'București', image: null }
            ]
        };

        // Filter mock results based on query
        if (query.toLowerCase().includes('concert') || query.toLowerCase().includes('music')) {
            mockData.locations = [];
        }

        renderResults(mockData);
    }

    // Debounced search input handler
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, SEARCH_DEBOUNCE_MS);
    });

    // Handle Enter key for full search
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const query = searchInput.value.trim();
            if (query.length >= SEARCH_MIN_CHARS) {
                window.location.href = `/cauta?q=${encodeURIComponent(query)}`;
            }
        }
    });

    // Popular search suggestions click handler
    document.querySelectorAll('.search-suggestion').forEach(btn => {
        btn.addEventListener('click', () => {
            const query = btn.dataset.query;
            searchInput.value = query;
            performSearch(query);
        });
    });

    // ==================== MOBILE MENU ====================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');

    mobileMenuBtn.addEventListener('click', () => {
        const isOpen = !mobileMenu.classList.contains('hidden');
        mobileMenu.classList.toggle('hidden', isOpen);
        mobileMenu.classList.toggle('block', !isOpen);
        const svg = mobileMenuBtn.querySelector('svg');
        if (!isOpen) {
            svg.innerHTML = '<path d="M18 6L6 18M6 6l12 12"/>';
        } else {
            svg.innerHTML = '<path d="M3 12h18M3 6h18M3 18h18"/>';
        }
    });

    // Mobile dropdown toggles
    document.querySelectorAll('[data-dropdown]').forEach(item => {
        const trigger = item.querySelector('.mobile-nav-link');
        const dropdown = item.querySelector('.mobile-dropdown');
        const icon = trigger.querySelector('svg');

        trigger.addEventListener('click', () => {
            const isOpen = !dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden', isOpen);
            dropdown.classList.toggle('block', !isOpen);
            icon.classList.toggle('rotate-180', !isOpen);
        });
    });

    // ==================== CART DRAWER ====================
    const cartBtn = document.getElementById('cartBtn');
    const cartDrawer = document.getElementById('cartDrawer');
    const cartOverlay = document.getElementById('cartOverlay');
    const cartCloseBtn = document.getElementById('cartCloseBtn');
    const cartBadge = document.getElementById('cartBadge');
    const cartDrawerCount = document.getElementById('cartDrawerCount');
    const cartEmpty = document.getElementById('cartEmpty');
    const cartItems = document.getElementById('cartItems');
    const cartFooter = document.getElementById('cartFooter');
    const cartSubtotal = document.getElementById('cartSubtotal');

    // Cart state
    const CART_STORAGE_KEY = 'ambilet_cart';

    function getCart() {
        try {
            const cart = localStorage.getItem(CART_STORAGE_KEY);
            if (!cart) return { items: [] };
            const parsed = JSON.parse(cart);
            // Handle both old format (plain array) and new format ({ items: [] })
            if (Array.isArray(parsed)) {
                return { items: parsed };
            }
            return parsed && parsed.items ? parsed : { items: [] };
        } catch (e) {
            console.error('Error reading cart:', e);
            return { items: [] };
        }
    }

    function saveCart(cart) {
        try {
            localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
            updateCartUI();
        } catch (e) {
            console.error('Error saving cart:', e);
        }
    }

    function openCartDrawer() {
        updateCartUI();
        cartOverlay.classList.remove('opacity-0', 'invisible');
        cartOverlay.classList.add('opacity-100', 'visible');
        cartDrawer.classList.remove('translate-x-full');
        cartDrawer.classList.add('translate-x-0');
        document.body.style.overflow = 'hidden';
    }

    function closeCartDrawer() {
        cartOverlay.classList.add('opacity-0', 'invisible');
        cartOverlay.classList.remove('opacity-100', 'visible');
        cartDrawer.classList.add('translate-x-full');
        cartDrawer.classList.remove('translate-x-0');
        document.body.style.overflow = '';
    }

    // Helper to calculate per-ticket commission (mirrors AmbiletCart.calculateItemCommission)
    function calculateItemCommission(item) {
        const basePrice = item.ticketType?.price || item.price || 0;
        const commission = item.ticketType?.commission;

        // If ticket has per-ticket commission settings
        if (commission && commission.type) {
            let amount = 0;
            switch (commission.type) {
                case 'percentage':
                    amount = basePrice * ((commission.rate || 0) / 100);
                    break;
                case 'fixed':
                    amount = commission.fixed || 0;
                    break;
                case 'both':
                    amount = (basePrice * ((commission.rate || 0) / 100)) + (commission.fixed || 0);
                    break;
            }
            return {
                amount: amount,
                rate: commission.rate || 0,
                fixed: commission.fixed || 0,
                mode: commission.mode || 'included',
                type: commission.type
            };
        }

        // Fall back to event-level commission
        const eventRate = item.event?.commission_rate || 5;
        const eventMode = item.event?.commission_mode || 'included';
        return {
            amount: basePrice * (eventRate / 100),
            rate: eventRate,
            fixed: 0,
            mode: eventMode,
            type: 'percentage'
        };
    }

    function updateCartUI() {
        const cart = getCart();
        const items = cart.items || [];
        const itemCount = items.reduce((sum, item) => sum + (item.quantity || 1), 0);

        // Calculate base subtotal and commission using per-ticket settings
        let baseSubtotal = 0;
        let totalCommission = 0;

        items.forEach(item => {
            const price = item.ticketType?.price || item.price || 0;
            const qty = item.quantity || 1;
            baseSubtotal += price * qty;

            // Calculate per-ticket commission
            const commission = calculateItemCommission(item);
            if (commission.mode === 'added_on_top') {
                totalCommission += commission.amount * qty;
            }
        });

        const subtotal = baseSubtotal + totalCommission;

        // Update badge
        if (itemCount > 0) {
            cartBadge.textContent = itemCount > 99 ? '99+' : itemCount;
            cartBadge.classList.remove('hidden');
            cartBadge.classList.add('flex');
        } else {
            cartBadge.classList.add('hidden');
            cartBadge.classList.remove('flex');
        }

        // Update drawer count
        if (itemCount > 0) {
            cartDrawerCount.textContent = itemCount;
            cartDrawerCount.classList.remove('hidden');
        } else {
            cartDrawerCount.classList.add('hidden');
        }

        // Update subtotal (shows total including commission if on top)
        cartSubtotal.textContent = subtotal.toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' lei';

        // Show/hide empty state vs items
        if (items.length === 0) {
            cartEmpty.classList.remove('hidden');
            cartItems.classList.add('hidden');
            cartFooter.classList.add('hidden');
        } else {
            cartEmpty.classList.add('hidden');
            cartItems.classList.remove('hidden');
            cartFooter.classList.remove('hidden');
            renderCartItems(items);
        }
    }

    function renderCartItems(items) {
        cartItems.innerHTML = items.map((item, index) => {
            // Handle both AmbiletCart format and legacy format
            const image = item.event?.image || item.image || '';
            const ticketName = item.ticketType?.name || item.name || 'Bilet';
            const eventName = item.event?.title || item.event?.name || item.eventName || '';
            const venueName = item.event?.venue?.name || item.event?.venue || '';
            const cityName = item.event?.city?.name || item.event?.city || '';
            const locationText = [venueName, cityName].filter(Boolean).join(', ');
            const basePrice = item.ticketType?.price || item.price || 0;
            const quantity = item.quantity || 1;
            const itemKey = item.key || index;
            const itemSeats = item.seats || [];
            const hasSeats = itemSeats.length > 0 || (item.seat_uids && item.seat_uids.length > 0);
            const eventSlug = item.event?.slug || '';

            // Calculate per-ticket commission
            const commission = calculateItemCommission(item);
            let displayPrice = basePrice;
            if (commission.mode === 'added_on_top') {
                displayPrice = basePrice + commission.amount;
            }

            // Quantity controls: seating items get read-only + link, others get +/- buttons
            let qtyHtml;
            if (hasSeats) {
                qtyHtml = '<span class="text-sm font-semibold">' + quantity + ' loc' + (quantity > 1 ? 'uri' : '') + '</span>' +
                    (eventSlug ? ' <a href="/bilete/' + escapeHtml(eventSlug) + '" class="text-xs font-semibold underline text-primary">Modifică</a>' : '');
            } else {
                qtyHtml = '<button type="button" class="flex items-center justify-center w-6 h-6 transition-colors bg-gray-100 rounded hover:bg-gray-200 cart-qty-btn" data-action="decrease" data-index="' + index + '">' +
                        '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>' +
                    '</button>' +
                    '<span class="w-6 text-sm font-semibold text-center">' + quantity + '</span>' +
                    '<button type="button" class="flex items-center justify-center w-6 h-6 transition-colors bg-gray-100 rounded hover:bg-gray-200 cart-qty-btn" data-action="increase" data-index="' + index + '">' +
                        '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                    '</button>';
            }

            return '<div class="p-3 bg-white border border-gray-200 rounded-xl" data-cart-item="' + index + '" data-item-key="' + escapeHtml(String(itemKey)) + '">' +
                '<div class="flex gap-3">' +
                    '<div class="flex-shrink-0 w-16 h-16 overflow-hidden bg-gray-100 rounded-lg">' +
                        (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(ticketName) + '" class="object-cover w-full h-full">' :
                        '<div class="flex items-center justify-center w-full h-full text-gray-400">' +
                            '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>' +
                            '</svg>' +
                        '</div>') +
                    '</div>' +
                    '<div class="flex-1 min-w-0">' +
                        '<h4 class="text-sm font-bold text-gray-900 truncate">' + escapeHtml(ticketName) + '</h4>' +
                        '<p class="text-xs text-gray-500 truncate">' + escapeHtml(eventName) + '</p>' +
                        (locationText ? '<p class="text-xs text-gray-400 truncate">' + escapeHtml(locationText) + '</p>' : '') +
                        '<div class="flex items-center justify-between mt-2">' +
                            '<div class="flex items-center gap-2">' +
                                qtyHtml +
                            '</div>' +
                            '<span class="font-bold text-primary">' + (displayPrice * quantity).toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' lei</span>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="self-start p-1 text-gray-400 transition-colors hover:text-red-500 cart-remove-btn" data-index="' + index + '" aria-label="Șterge">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</div>';
        }).join('');

        // Add event listeners for quantity buttons
        cartItems.querySelectorAll('.cart-qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                const action = btn.dataset.action;
                updateItemQuantity(index, action);
            });
        });

        // Add event listeners for remove buttons
        cartItems.querySelectorAll('.cart-remove-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                removeCartItem(index);
            });
        });
    }

    function updateItemQuantity(index, action) {
        const cart = getCart();
        const items = cart.items || [];
        if (index < 0 || index >= items.length) return;

        if (action === 'increase') {
            items[index].quantity = (items[index].quantity || 1) + 1;
        } else if (action === 'decrease') {
            items[index].quantity = (items[index].quantity || 1) - 1;
            if (items[index].quantity <= 0) {
                items.splice(index, 1);
            }
        }

        cart.items = items;
        cart.updatedAt = new Date().toISOString();
        saveCart(cart);
    }

    function removeCartItem(index) {
        const cart = getCart();
        const items = cart.items || [];
        if (index < 0 || index >= items.length) return;
        items.splice(index, 1);
        cart.items = items;
        cart.updatedAt = new Date().toISOString();
        saveCart(cart);
    }

    // Cart drawer event listeners
    cartBtn.addEventListener('click', openCartDrawer);
    cartCloseBtn.addEventListener('click', closeCartDrawer);
    cartOverlay.addEventListener('click', closeCartDrawer);

    // Close cart drawer on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !cartOverlay.classList.contains('invisible')) {
            closeCartDrawer();
        }
    });

    // Initialize cart UI on page load
    updateCartUI();

    // Listen for cart updates from AmbiletCart (cart.js)
    window.addEventListener('ambilet:cart:update', function() {
        updateCartUI();
    });

    // Listen for cart expiration events - close drawer and update UI
    window.addEventListener('ambilet:cart:expired', function(e) {
        console.log('[CartDrawer] Cart expired, updating UI');
        closeCartDrawer();
        updateCartUI();
    });

    // Listen for cart clear events
    window.addEventListener('ambilet:cart:clear', function() {
        updateCartUI();
    });

    // Extend existing AmbiletCart with drawer methods (don't overwrite cart.js)
    if (window.AmbiletCart) {
        // Cart.js already defines AmbiletCart - just add drawer methods
        window.AmbiletCart.openDrawer = openCartDrawer;
        window.AmbiletCart.closeDrawer = closeCartDrawer;
        window.AmbiletCart.updateUI = updateCartUI;
    } else {
        // Fallback if cart.js hasn't loaded yet
        window.AmbiletCart = {
            openDrawer: openCartDrawer,
            closeDrawer: closeCartDrawer,
            updateUI: updateCartUI,
            getCart: getCart
        };
    }

    // Expose openCartDrawer globally for event.php
    window.openCartDrawer = openCartDrawer;
    window.closeCartDrawer = closeCartDrawer;

    // ==================== HEADER CART TIMER BAR ====================
    // Replace headerTopBar with timer bar when cart has items (except on cart/checkout pages)
    (function initHeaderTimerBar() {
        const headerTopBar = document.getElementById('headerTopBar');
        const headerTimerBar = document.getElementById('headerTimerBar');
        const countdownEl = document.getElementById('headerTimerCountdown');

        if (!headerTopBar || !headerTimerBar || !countdownEl) return;

        // Skip cart and checkout pages - they have their own timer
        const currentPath = window.location.pathname;
        if (currentPath === '/cos' || currentPath === '/finalizare') return;

        let timerInterval = null;
        let isRed = false;

        function showTimerBar() {
            headerTopBar.classList.add('hidden');
            headerTimerBar.classList.remove('hidden');
        }

        function showDefaultBar() {
            headerTimerBar.classList.add('hidden');
            // Only show headerTopBar if not scrolled and not transparent mode
            const isScrolled = window.scrollY > 50;
            const isTransparent = document.getElementById('header')?.dataset.transparent === 'true';
            if (!isScrolled && !isTransparent) {
                headerTopBar.classList.remove('hidden');
            }
        }

        function updateHeaderTimer() {
            // Get cart from localStorage
            let cart = [];
            try {
                const stored = localStorage.getItem('ambilet_cart');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    cart = parsed.items || parsed || [];
                }
            } catch (e) {
                cart = [];
            }

            const savedEndTime = localStorage.getItem('cart_end_time');

            // Check if cart has items and timer is active
            if (cart && cart.length > 0 && savedEndTime) {
                const endTime = parseInt(savedEndTime);
                const remaining = Math.max(0, endTime - Date.now());

                if (remaining > 0) {
                    showTimerBar();

                    const minutes = Math.floor(remaining / 60000);
                    const seconds = Math.floor((remaining % 60000) / 1000);
                    countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                    // Under 5 minutes - make bar red
                    if (remaining < 5 * 60 * 1000 && !isRed) {
                        isRed = true;
                        headerTimerBar.classList.remove('bg-warning/10', 'border-warning/20');
                        headerTimerBar.classList.add('bg-primary', 'border-primary');
                        countdownEl.classList.remove('text-warning');
                        countdownEl.classList.add('text-white');
                        const textSpan = document.getElementById('headerTimerText');
                        if (textSpan) {
                            textSpan.classList.remove('text-secondary');
                            textSpan.classList.add('text-white/90');
                        }
                        const icon = document.getElementById('headerTimerIcon');
                        if (icon) {
                            icon.classList.remove('text-warning');
                            icon.classList.add('text-white');
                        }
                        const link = headerTimerBar.querySelector('a.text-primary');
                        if (link) {
                            link.classList.remove('text-primary');
                            link.classList.add('text-white');
                        }
                    }
                } else {
                    // Timer expired
                    showDefaultBar();
                    if (timerInterval) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                    }
                }
            } else {
                // No items or no timer
                showDefaultBar();
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
            }
        }

        // Delayed initial check
        setTimeout(function() {
            updateHeaderTimer();

            const savedEndTime = localStorage.getItem('cart_end_time');
            const storedCart = localStorage.getItem('ambilet_cart');
            if (storedCart && savedEndTime) {
                try {
                    const parsed = JSON.parse(storedCart);
                    const cart = parsed.items || parsed || [];
                    if (cart && cart.length > 0) {
                        timerInterval = setInterval(updateHeaderTimer, 1000);
                    }
                } catch (e) {}
            }
        }, 100);

        // Listen for cart updates
        window.addEventListener('ambilet:cart:update', function() {
            updateHeaderTimer();
            if (!timerInterval) {
                const storedCart = localStorage.getItem('ambilet_cart');
                if (storedCart) {
                    try {
                        const parsed = JSON.parse(storedCart);
                        const cart = parsed.items || parsed || [];
                        if (cart && cart.length > 0) {
                            timerInterval = setInterval(updateHeaderTimer, 1000);
                        }
                    } catch (e) {}
                }
            }
        });

        // Listen for cart clear/expire
        window.addEventListener('ambilet:cart:clear', function() {
            showDefaultBar();
            isRed = false;
            // Reset timer bar styles
            headerTimerBar.classList.remove('bg-primary', 'border-primary');
            headerTimerBar.classList.add('bg-warning/10', 'border-warning/20');
            countdownEl.classList.remove('text-white');
            countdownEl.classList.add('text-warning');
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        });

        window.addEventListener('ambilet:cart:expired', function() {
            showDefaultBar();
            isRed = false;
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        });
    })();
})();
</script>
