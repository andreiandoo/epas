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

            <style>
                .gradient-primary { background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%); }
                .gradient-text { 
                    background: linear-gradient(135deg, #7c3aed, #ec4899); 
                    -webkit-background-clip: text; 
                    -webkit-text-fill-color: transparent; 
                }
                
                /* Dropdown positioning */
                .dropdown {
                    position: relative;
                }
                
                .dropdown-menu {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    min-width: 280px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
                    opacity: 0;
                    visibility: hidden;
                    transform: translateY(10px);
                    transition: all 0.2s ease;
                    z-index: 1000;
                }
                
                /* Wide dropdown for cities and venues */
                .dropdown-menu.wide {
                    min-width: 700px;
                    left: 50%;
                    transform: translateX(-50%) translateY(10px);
                }
                
                .dropdown-menu.venues {
                    min-width: 900px;
                }
                
                .dropdown:hover .dropdown-menu {
                    opacity: 1;
                    visibility: visible;
                    transform: translateY(0);
                }
                
                .dropdown:hover .dropdown-menu.wide {
                    transform: translateX(-50%) translateY(0);
                }
                
                /* Nav link style */
                .nav-link {
                    position: relative;
                    padding: 0 16px;
                    height: 64px;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-weight: 500;
                    color: #374151;
                    transition: color 0.2s;
                }
                
                .nav-link:hover {
                    color: #7c3aed;
                }
                
                .nav-link::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 16px;
                    right: 16px;
                    height: 3px;
                    background: linear-gradient(135deg, #7c3aed, #ec4899);
                    border-radius: 3px 3px 0 0;
                    transform: scaleX(0);
                    transition: transform 0.2s;
                }
                
                .dropdown:hover .nav-link::after {
                    transform: scaleX(1);
                }
                
                /* Dropdown link hover */
                .dropdown-link {
                    display: block;
                    padding: 8px 16px;
                    color: #4b5563;
                    font-size: 14px;
                    border-radius: 6px;
                    transition: all 0.15s;
                }
                
                .dropdown-link:hover {
                    background: #f3e8ff;
                    color: #7c3aed;
                }
                
                /* City list scrollable */
                .city-list {
                    max-height: 400px;
                    overflow-y: auto;
                }
                
                .city-list::-webkit-scrollbar {
                    width: 6px;
                }
                
                .city-list::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 3px;
                }
                
                .city-list::-webkit-scrollbar-thumb {
                    background: #c4b5fd;
                    border-radius: 3px;
                }
                
                /* Venue card compact */
                .venue-card {
                    display: flex;
                    flex-direction: column;
                    border-radius: 10px;
                    overflow: hidden;
                    background: #f9fafb;
                    transition: all 0.2s;
                }
                
                .venue-card:hover {
                    background: #f3e8ff;
                    transform: translateY(-2px);
                }
                
                .venue-card img {
                    width: 100%;
                    height: 90px;
                    object-fit: cover;
                }
                
                .venue-card .venue-info {
                    padding: 10px;
                }
                
                .venue-card .venue-name {
                    font-weight: 600;
                    font-size: 13px;
                    color: #111827;
                    line-height: 1.3;
                }
                
                .venue-card .venue-city {
                    font-size: 12px;
                    color: #6b7280;
                    margin-top: 2px;
                }
            </style>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center h-full">
                
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

                <!-- Orașe Dropdown -->
                <div class="dropdown h-full flex items-center">
                    <button class="nav-link">
                        Orașe
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-menu wide p-4">
                        <div class="flex items-center justify-between mb-3 px-2">
                            <span class="text-sm font-semibold text-gray-900">1.245 evenimente din 147 orașe</span>
                            <a href="/orase" class="text-sm font-semibold text-purple-600 hover:text-purple-700">Vezi toate →</a>
                        </div>
                        
                        <div class="city-list grid grid-cols-4 gap-x-4">
                            <!-- Popular cities first -->
                            <a href="/orase/bucuresti" class="dropdown-link font-semibold text-purple-600">București</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Cluj-Napoca</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Timișoara</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Iași</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Brașov</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Constanța</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Sibiu</a>
                            <a href="#" class="dropdown-link font-semibold text-purple-600">Craiova</a>
                            
                            <!-- Divider -->
                            <div class="col-span-4 border-t border-gray-100 my-2"></div>
                            
                            <!-- All cities A-Z -->
                            <a href="#" class="dropdown-link">Alba Iulia</a>
                            <a href="#" class="dropdown-link">Arad</a>
                            <a href="#" class="dropdown-link">Bacău</a>
                            <a href="#" class="dropdown-link">Baia Mare</a>
                            <a href="#" class="dropdown-link">Bistrița</a>
                            <a href="#" class="dropdown-link">Botoșani</a>
                            <a href="#" class="dropdown-link">Brăila</a>
                            <a href="#" class="dropdown-link">Buzău</a>
                            <a href="#" class="dropdown-link">Călărași</a>
                            <a href="#" class="dropdown-link">Deva</a>
                            <a href="#" class="dropdown-link">Focșani</a>
                            <a href="#" class="dropdown-link">Galați</a>
                            <a href="#" class="dropdown-link">Giurgiu</a>
                            <a href="#" class="dropdown-link">Hunedoara</a>
                            <a href="#" class="dropdown-link">Mediaș</a>
                            <a href="#" class="dropdown-link">Miercurea Ciuc</a>
                            <a href="#" class="dropdown-link">Oradea</a>
                            <a href="#" class="dropdown-link">Petroșani</a>
                            <a href="#" class="dropdown-link">Piatra Neamț</a>
                            <a href="#" class="dropdown-link">Pitești</a>
                            <a href="#" class="dropdown-link">Ploiești</a>
                            <a href="#" class="dropdown-link">Râmnicu Vâlcea</a>
                            <a href="#" class="dropdown-link">Reșița</a>
                            <a href="#" class="dropdown-link">Roman</a>
                            <a href="#" class="dropdown-link">Satu Mare</a>
                            <a href="#" class="dropdown-link">Sighișoara</a>
                            <a href="#" class="dropdown-link">Slatina</a>
                            <a href="#" class="dropdown-link">Slobozia</a>
                            <a href="#" class="dropdown-link">Suceava</a>
                            <a href="#" class="dropdown-link">Târgoviște</a>
                            <a href="#" class="dropdown-link">Târgu Jiu</a>
                            <a href="#" class="dropdown-link">Târgu Mureș</a>
                            <a href="#" class="dropdown-link">Tulcea</a>
                            <a href="#" class="dropdown-link">Vaslui</a>
                            <a href="#" class="dropdown-link">Zalău</a>
                            <a href="#" class="dropdown-link">Bârlad</a>
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
