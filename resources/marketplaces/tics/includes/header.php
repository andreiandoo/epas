<?php
/**
 * TICS.ro - Header Component
 *
 * Variables:
 * - $transparentHeader (optional): Set to true for transparent header on hero pages
 * - $currentPage (optional): Current page identifier for nav highlighting
 * - $isLoggedIn (optional): Set to true if user is logged in
 * - $isOrganizerLoggedIn (optional): Set to true if organizer is logged in
 * - $loggedInUser (optional): Array with logged in user data
 */

$headerClass = isset($transparentHeader) && $transparentHeader
    ? 'sticky top-0 z-40 bg-white/95 backdrop-blur-lg border-b border-gray-200'
    : 'sticky top-0 z-40 bg-white border-b border-gray-200';

// Check login status (in production this would come from session)
$isLoggedIn = $isLoggedIn ?? false;
$isOrganizerLoggedIn = $isOrganizerLoggedIn ?? false;

// Demo user data for logged in state
if ($isLoggedIn && !isset($loggedInUser)) {
    $loggedInUser = [
        'id' => 1,
        'name' => 'Alexandru Marin',
        'firstName' => 'Alexandru',
        'email' => 'alexandru.marin@example.com',
        'avatar' => 'https://i.pravatar.cc/40?img=68',
        'points' => 1250
    ];
}
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
                    <span id="cartBadge" class="w-5 h-5 bg-indigo-600 text-white text-xs font-bold rounded-full items-center justify-center" style="display: none;">0</span>
                </a>
                <div class="w-px h-6 bg-gray-200 mx-2 hidden sm:block"></div>

                <?php if ($isLoggedIn): ?>
                <!-- Logged in user: Notifications + User Menu -->
                <button class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>

                <!-- User Menu Dropdown -->
                <div class="relative" id="headerUserMenu">
                    <button onclick="toggleHeaderUserMenu()" class="flex items-center gap-3 p-1.5 hover:bg-gray-100 rounded-xl transition-colors">
                        <img src="<?= htmlspecialchars($loggedInUser['avatar']) ?>" class="w-8 h-8 rounded-lg object-cover" alt="<?= htmlspecialchars($loggedInUser['firstName']) ?>">
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($loggedInUser['firstName']) ?></p>
                            <p class="text-xs text-gray-500"><?= number_format($loggedInUser['points']) ?> puncte</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <!-- Dropdown -->
                    <div id="headerUserDropdown" class="hidden absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-50">
                        <a href="/cont" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            Dashboard
                        </a>
                        <a href="/cont/bilete" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            Biletele mele
                        </a>
                        <a href="/cont/comenzi" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Comenzile mele
                        </a>
                        <div class="border-t border-gray-100 my-2"></div>
                        <a href="/cont/setari" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Setari
                        </a>
                        <div class="border-t border-gray-100 my-2"></div>
                        <a href="/deconectare" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Deconectare
                        </a>
                    </div>
                </div>

                <script>
                function toggleHeaderUserMenu() {
                    const dropdown = document.getElementById('headerUserDropdown');
                    dropdown.classList.toggle('hidden');
                }
                document.addEventListener('click', function(e) {
                    const menu = document.getElementById('headerUserMenu');
                    const dropdown = document.getElementById('headerUserDropdown');
                    if (menu && dropdown && !menu.contains(e.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
                </script>

                <?php elseif ($isOrganizerLoggedIn): ?>
                <!-- Organizer logged in: Show link to organizer portal -->
                <a href="/organizator" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Portal Organizator
                </a>

                <?php else: ?>
                <!-- Not logged in: Show login/register buttons -->
                <a href="/conectare" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Conectare</a>
                <a href="/inregistrare" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">Înregistrare</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
