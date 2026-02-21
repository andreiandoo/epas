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

            <script>
            window.TICS_API_BASE = '<?= defined('API_BASE_URL') ? htmlspecialchars(API_BASE_URL) : 'https://core.tixello.com/api/marketplace-client' ?>';
            window.TICS_API_KEY  = '<?= defined('API_KEY') ? htmlspecialchars(API_KEY) : '' ?>';
            </script>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center h-full">

                <!-- Country Selector -->
                <div class="dropdown h-full flex items-center">
                    <button class="nav-link" type="button">
                        <span id="countryFlag" class="text-xl leading-none">🇷🇴</span>
                        <span id="countryName" class="hidden xl:inline">România</span>
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu py-2" style="min-width:200px">
                        <p class="px-4 pb-1.5 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Selectează țara</p>
                        <button class="dropdown-link country-option flex items-center gap-2.5 active" type="button" data-code="RO" data-flag="🇷🇴" data-name="România" onclick="ticsSelectCountry(this)">
                            <span class="text-lg">🇷🇴</span> România
                        </button>
                        <button class="dropdown-link country-option flex items-center gap-2.5" type="button" data-code="MD" data-flag="🇲🇩" data-name="Moldova" onclick="ticsSelectCountry(this)">
                            <span class="text-lg">🇲🇩</span> Moldova
                        </button>
                        <button class="dropdown-link country-option flex items-center gap-2.5" type="button" data-code="HU" data-flag="🇭🇺" data-name="Ungaria" onclick="ticsSelectCountry(this)">
                            <span class="text-lg">🇭🇺</span> Ungaria
                        </button>
                        <button class="dropdown-link country-option flex items-center gap-2.5" type="button" data-code="BG" data-flag="🇧🇬" data-name="Bulgaria" onclick="ticsSelectCountry(this)">
                            <span class="text-lg">🇧🇬</span> Bulgaria
                        </button>
                    </div>
                </div>

                <!-- Evenimente Dropdown -->
                <div class="dropdown h-full flex items-center">
                    <button class="nav-link">
                        Evenimente
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-menu p-3">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">Categorii</div>
                        <a href="#" class="dropdown-link font-medium">🎵 Concerte</a>
                        <a href="#" class="dropdown-link font-medium">🎪 Festivaluri</a>
                        <a href="#" class="dropdown-link font-medium">😂 Stand-up Comedy</a>
                        <a href="#" class="dropdown-link font-medium">🎭 Teatru</a>
                        <a href="#" class="dropdown-link font-medium">⚽ Sport</a>
                        <a href="#" class="dropdown-link font-medium">🎨 Expo & Muzee</a>
                        <a href="#" class="dropdown-link font-medium">👶 Pentru Copii</a>
                        <a href="#" class="dropdown-link font-medium">📚 Conferințe</a>
                        <div class="border-t border-gray-100 mt-2 pt-2">
                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">Genuri muzicale</div>
                            <a href="#" class="dropdown-link">Rock & Alternative</a>
                            <a href="#" class="dropdown-link">Pop</a>
                            <a href="#" class="dropdown-link">Electronic / DJ</a>
                            <a href="#" class="dropdown-link">Hip-Hop & Rap</a>
                            <a href="#" class="dropdown-link">Jazz & Blues</a>
                            <a href="#" class="dropdown-link">Metal</a>
                            <a href="#" class="dropdown-link">Clasică & Operă</a>
                            <a href="#" class="dropdown-link">Folk & Populară</a>
                        </div>
                        <div class="border-t border-gray-100 mt-2 pt-2">
                            <a href="/evenimente" class="dropdown-link font-semibold text-purple-600">Vezi toate evenimentele →</a>
                        </div>
                    </div>
                </div>

                <!-- Orașe Dropdown – dynamic, populated by JS based on selected country -->
                <div class="dropdown h-full flex items-center">
                    <button class="nav-link" type="button">
                        Orașe
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div class="dropdown-menu wide p-4">
                        <div class="flex items-center justify-between mb-3 px-2">
                            <span id="citiesDropdownTitle" class="text-sm font-semibold text-gray-900">Orașe din România</span>
                            <a href="/orase" class="text-sm font-semibold text-purple-600 hover:text-purple-700">Vezi toate →</a>
                        </div>

                        <div id="citiesDropdownContent" class="city-list grid grid-cols-4 gap-x-4">
                            <div class="col-span-4 flex items-center justify-center py-6">
                                <div class="w-5 h-5 border-2 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Locații Dropdown -->
                <div class="dropdown h-full flex items-center">
                    <button class="nav-link">
                        Locații
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-menu wide venues p-4">
                        <div class="flex items-center justify-between mb-4 px-1">
                            <span class="text-sm font-semibold text-gray-900">Locații populare</span>
                            <a href="/locatii" class="text-sm font-semibold text-purple-600 hover:text-purple-700">Vezi toate locațiile →</a>
                        </div>
                        
                        <div class="grid grid-cols-6 gap-3">
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Arena Națională</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1507901747481-84a4f64fda6d?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Sala Palatului</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Arenele Romane</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">BT Arena</div>
                                    <div class="venue-city">Cluj-Napoca</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Romexpo</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Hard Rock Cafe</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Quantic</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Expirat</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Form Space</div>
                                    <div class="venue-city">Cluj-Napoca</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Filarmonica</div>
                                    <div class="venue-city">Sibiu</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">Club 99</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                            
                            <a href="#" class="venue-card">
                                <img src="https://images.unsplash.com/photo-1504680177321-2e6a879aac86?w=200&h=120&fit=crop" alt="">
                                <div class="venue-info">
                                    <div class="venue-name">TNB</div>
                                    <div class="venue-city">București</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Search -->
            <div class="hidden md:flex flex-1 max-w-xl mx-6 relative">
                <div class="w-full flex items-center bg-gray-100 rounded-full px-4 py-2.5 focus-within:ring-2 focus-within:ring-gray-900 focus-within:bg-white transition-all">
                    <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="searchInput" name="tics_search" placeholder="Caută evenimente, artiști, locații..." class="flex-1 bg-transparent outline-none text-gray-900 placeholder:text-gray-400" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-form-type="other">
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
                <div class="relative" id="headerNotificationsMenu">
                    <button onclick="toggleNotificationsDropdown()" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span id="notificationBadge" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 top-full mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Notificări</h3>
                            <button onclick="markAllNotificationsRead()" class="text-xs text-indigo-600 hover:underline">Marchează toate citite</button>
                        </div>
                        <div id="notificationsList" class="max-h-80 overflow-y-auto">
                            <!-- Notification items will be here -->
                            <a href="/bilete/coldplay-music-of-the-spheres-bucuresti" onclick="markNotificationRead(1)" class="notification-item flex items-start gap-3 p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 unread" data-id="1">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-100 to-emerald-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 font-medium">Biletele tale sunt gata!</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Coldplay - Music of the Spheres • 15 Iunie 2026</p>
                                    <p class="text-xs text-gray-400 mt-1">Acum 2 ore</p>
                                </div>
                                <span class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0 mt-2"></span>
                            </a>
                            <a href="/bilete/dua-lipa-bucuresti" onclick="markNotificationRead(2)" class="notification-item flex items-start gap-3 p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 unread" data-id="2">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-100 to-rose-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 font-medium">Dua Lipa vine în București!</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Pe baza preferințelor tale • Early Bird disponibil</p>
                                    <p class="text-xs text-gray-400 mt-1">Acum 5 ore</p>
                                </div>
                                <span class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0 mt-2"></span>
                            </a>
                            <a href="/cont/comenzi" onclick="markNotificationRead(3)" class="notification-item flex items-start gap-3 p-4 hover:bg-gray-50 transition-colors border-b border-gray-50" data-id="3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-600">Comanda #TCS-2024-1234 confirmată</p>
                                    <p class="text-xs text-gray-500 mt-0.5">2 bilete • 450 RON</p>
                                    <p class="text-xs text-gray-400 mt-1">Ieri, 14:32</p>
                                </div>
                            </a>
                            <a href="/cont/puncte" onclick="markNotificationRead(4)" class="notification-item flex items-start gap-3 p-4 hover:bg-gray-50 transition-colors" data-id="4">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-600">Ai primit 125 puncte bonus!</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Soldut tău: 1.250 puncte</p>
                                    <p class="text-xs text-gray-400 mt-1">Acum 3 zile</p>
                                </div>
                            </a>
                        </div>
                        <a href="/cont/notificari" class="block px-4 py-3 text-center text-sm font-medium text-indigo-600 hover:bg-gray-50 border-t border-gray-100">
                            Vezi toate notificările →
                        </a>
                    </div>
                </div>

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
                    // Close notifications if open
                    document.getElementById('notificationsDropdown')?.classList.add('hidden');
                }

                function toggleNotificationsDropdown() {
                    const dropdown = document.getElementById('notificationsDropdown');
                    dropdown.classList.toggle('hidden');
                    // Close user menu if open
                    document.getElementById('headerUserDropdown')?.classList.add('hidden');
                }

                function markNotificationRead(id) {
                    const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        const dot = item.querySelector('.bg-indigo-500');
                        if (dot) dot.remove();
                    }
                    updateNotificationBadge();
                }

                function markAllNotificationsRead() {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const dot = item.querySelector('.bg-indigo-500');
                        if (dot) dot.remove();
                    });
                    updateNotificationBadge();
                }

                function updateNotificationBadge() {
                    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        badge.style.display = unreadCount > 0 ? 'block' : 'none';
                    }
                }

                document.addEventListener('click', function(e) {
                    // Close user menu
                    const userMenu = document.getElementById('headerUserMenu');
                    const userDropdown = document.getElementById('headerUserDropdown');
                    if (userMenu && userDropdown && !userMenu.contains(e.target)) {
                        userDropdown.classList.add('hidden');
                    }
                    // Close notifications
                    const notifMenu = document.getElementById('headerNotificationsMenu');
                    const notifDropdown = document.getElementById('notificationsDropdown');
                    if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
                        notifDropdown.classList.add('hidden');
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

<script>
(function () {
    'use strict';

    var COUNTRIES = {
        RO: { flag: '🇷🇴', name: 'România'  },
        MD: { flag: '🇲🇩', name: 'Moldova'  },
        HU: { flag: '🇭🇺', name: 'Ungaria'  },
        BG: { flag: '🇧🇬', name: 'Bulgaria' },
    };

    var citiesCache  = {};
    var currentCode  = localStorage.getItem('tics_country') || 'RO';

    /* ------------------------------------------------------------------ */
    /* Public – called from onclick attributes                              */
    /* ------------------------------------------------------------------ */
    window.ticsSelectCountry = function (btn) {
        var code = btn.dataset.code;
        if (!COUNTRIES[code] || code === currentCode) return;
        currentCode = code;
        localStorage.setItem('tics_country', code);
        updateSelectorUI(code);
        loadCities(code);
    };

    /* ------------------------------------------------------------------ */
    /* UI helpers                                                           */
    /* ------------------------------------------------------------------ */
    function updateSelectorUI(code) {
        var c      = COUNTRIES[code] || COUNTRIES.RO;
        var flagEl = document.getElementById('countryFlag');
        var nameEl = document.getElementById('countryName');
        if (flagEl) flagEl.textContent = c.flag;
        if (nameEl) nameEl.textContent = c.name;
        document.querySelectorAll('.country-option').forEach(function (el) {
            el.classList.toggle('active', el.dataset.code === code);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Cities API                                                           */
    /* ------------------------------------------------------------------ */
    function loadCities(code) {
        if (citiesCache[code]) { renderCities(code, citiesCache[code]); return; }

        var container = document.getElementById('citiesDropdownContent');
        if (!container) return;

        container.innerHTML =
            '<div class="col-span-4 flex items-center justify-center py-6">' +
            '<div class="w-5 h-5 border-2 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>' +
            '</div>';

        var base = (window.TICS_API_BASE || 'https://core.tixello.com/api/marketplace-client').replace(/\/$/, '');
        var key  = window.TICS_API_KEY || '';

        fetch(base + '/locations/cities?country=' + encodeURIComponent(code) + '&per_page=50&sort=events', {
            headers: { 'X-Marketplace-Key': key, 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success && Array.isArray(data.data)) {
                citiesCache[code] = data.data;
                renderCities(code, data.data);
            } else {
                showCitiesError();
            }
        })
        .catch(showCitiesError);
    }

    function renderCities(code, cities) {
        var container = document.getElementById('citiesDropdownContent');
        var titleEl   = document.getElementById('citiesDropdownTitle');
        if (!container) return;

        var c = COUNTRIES[code] || COUNTRIES.RO;
        if (titleEl) titleEl.textContent = 'Orașe din ' + c.name;

        if (!cities.length) {
            container.innerHTML =
                '<div class="col-span-4 text-center py-6 text-sm text-gray-400">Nu există orașe disponibile</div>';
            return;
        }

        var featured = cities.filter(function (x) { return x.events_count > 0; }).slice(0, 8);
        var rest     = cities.filter(function (x) { return x.events_count === 0; });

        var html = '';

        featured.forEach(function (city) {
            html += '<a href="/orase/' + city.slug + '" class="dropdown-link font-semibold text-purple-600">'
                  + esc(city.name) + '</a>';
        });

        if (featured.length && rest.length) {
            html += '<div class="col-span-4 border-t border-gray-100 my-2"></div>';
        }

        rest.forEach(function (city) {
            html += '<a href="/orase/' + city.slug + '" class="dropdown-link">' + esc(city.name) + '</a>';
        });

        container.innerHTML = html;
    }

    function showCitiesError() {
        var container = document.getElementById('citiesDropdownContent');
        if (container) {
            container.innerHTML =
                '<div class="col-span-4 text-center py-4 text-sm text-red-400">Nu s-au putut încărca orașele.</div>';
        }
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------------ */
    /* Init                                                                 */
    /* ------------------------------------------------------------------ */
    function init() {
        updateSelectorUI(currentCode);
        loadCities(currentCode);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
</script>
