<?php
/**
 * TICS.ro - Header Component
 *
 * Variables:
 * - $transparentHeader (optional): Set to true for transparent header on hero pages
 * - $currentPage (optional): Current page identifier for nav highlighting
 */

$headerClass = isset($transparentHeader) && $transparentHeader
    ? 'sticky top-0 z-40 bg-white/95 backdrop-blur-lg border-b border-gray-200'
    : 'sticky top-0 z-40 bg-white border-b border-gray-200';
?>

<!-- Header -->
<header class="<?= $headerClass ?>">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="text-white font-bold text-sm">T</span>
                </div>
                <span class="font-bold text-lg hidden sm:block">TICS</span>
            </a>

            <!-- Search -->
            <div class="hidden md:flex flex-1 max-w-xl mx-6 relative">
                <div class="w-full flex items-center bg-gray-100 rounded-full px-4 py-2.5 focus-within:ring-2 focus-within:ring-gray-900 focus-within:bg-white transition-all">
                    <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Caută evenimente, artiști, locații..." class="flex-1 bg-transparent outline-none text-gray-900 placeholder:text-gray-400">
                    <kbd class="hidden lg:inline-flex px-2 py-1 bg-gray-200 rounded text-xs text-gray-500 ml-2">⌘K</kbd>
                </div>

                <!-- Search Dropdown -->
                <div id="searchDropdown" class="search-dropdown absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden z-50">
                    <div id="searchLoading" class="hidden p-8 text-center">
                        <div class="loading-spinner mx-auto mb-3"></div>
                        <p class="text-sm text-gray-500">Se caută...</p>
                    </div>
                    <div id="searchQuickLinks" class="p-4">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Căutări populare</p>
                        <div class="flex flex-wrap gap-2">
                            <button class="search-suggestion px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-600 transition-colors" data-query="UNTOLD">UNTOLD</button>
                            <button class="search-suggestion px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-600 transition-colors" data-query="Electric Castle">Electric Castle</button>
                            <button class="search-suggestion px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-600 transition-colors" data-query="Coldplay">Coldplay</button>
                            <button class="search-suggestion px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-600 transition-colors" data-query="Micutzu">Micutzu</button>
                        </div>
                    </div>
                    <div id="searchMinChars" class="hidden p-4 text-center text-sm text-gray-500">
                        Introdu minim 2 caractere pentru a căuta
                    </div>
                    <div id="searchResults" class="hidden">
                        <div id="searchResultsContent">
                            <!-- Events Section -->
                            <div id="searchEventsSection" class="hidden p-4 border-b border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Evenimente <span id="searchEventsCount"></span></p>
                                <div id="searchEventsList" class="space-y-1"></div>
                            </div>
                            <!-- Artists Section -->
                            <div id="searchArtistsSection" class="hidden p-4 border-b border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Artiști <span id="searchArtistsCount"></span></p>
                                <div id="searchArtistsList" class="space-y-1"></div>
                            </div>
                            <!-- Locations Section -->
                            <div id="searchLocationsSection" class="hidden p-4">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Locații <span id="searchLocationsCount"></span></p>
                                <div id="searchLocationsList" class="space-y-1"></div>
                            </div>
                        </div>
                        <div id="searchViewAll" class="hidden p-4 bg-gray-50 border-t border-gray-100">
                            <a id="searchViewAllLink" href="/cauta" class="flex items-center justify-center gap-2 w-full py-2.5 bg-gray-900 text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                                Vezi toate rezultatele
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>
                    </div>
                    <div id="searchNoResults" class="hidden p-8 text-center">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-500">Nu am găsit rezultate</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1">
                <button id="mobileSearchBtn" class="md:hidden p-2.5 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
                <a href="/favorite" class="hidden sm:flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <span class="hidden lg:inline">Favorite</span>
                </a>
                <a href="/cos" class="hidden sm:flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span class="hidden lg:inline">Coș</span>
                    <span id="cartBadge" class="w-5 h-5 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
                </a>
                <div class="w-px h-6 bg-gray-200 mx-2 hidden sm:block"></div>
                <a href="/conectare" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Conectare</a>
                <a href="/inregistrare" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">Înregistrare</a>
            </div>
        </div>
    </div>
</header>

<!-- Categories Bar -->
<?php if (!isset($hideCategoriesBar) || !$hideCategoriesBar): ?>
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
            <a href="/evenimente" class="category-chip <?= ($currentPage ?? '') === 'events' && empty($filterCategory) ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium whitespace-nowrap transition-colors hover:border-gray-300" data-category="">
                Toate
            </a>
            <?php foreach ($CATEGORIES as $slug => $cat): ?>
            <a href="/evenimente/<?= e($slug) ?>" class="category-chip <?= ($filterCategory ?? '') === $slug ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600 whitespace-nowrap hover:border-gray-300 transition-colors" data-category="<?= e($slug) ?>">
                <?= $cat['icon'] ?> <?= e($cat['name']) ?>
            </a>
            <?php endforeach; ?>
            <div class="flex-shrink-0 w-4 lg:hidden"></div><!-- Spacer for last item on mobile -->
        </div>
    </div>
</div>
<?php endif; ?>
