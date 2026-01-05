<?php
/**
 * Navigation Data Cache Helper
 *
 * Provides cached counts for navigation elements.
 * Cache updates every 6 hours to avoid excessive API calls.
 *
 * Usage:
 *   require_once 'includes/nav-cache.php';
 *   $navCounts = getNavCounts();
 *   // $navCounts['cities']['bucuresti'] => 238
 *   // $navCounts['categories']['concerte'] => 156
 */

// Cache configuration
define('NAV_CACHE_FILE', __DIR__ . '/cache/nav-counts.json');
define('NAV_CACHE_TTL', 6 * 60 * 60); // 6 hours in seconds

// API configuration - update this to your actual API endpoint
define('NAV_API_URL', '/api/v1/public/navigation/counts');

/**
 * Get navigation counts with caching
 *
 * @return array Associative array of counts by type and slug
 */
function getNavCounts(): array {
    // Check if cache exists and is valid
    if (isNavCacheValid()) {
        return loadNavCache();
    }

    // Fetch fresh data from API
    $freshData = fetchNavCountsFromAPI();

    // Save to cache
    saveNavCache($freshData);

    return $freshData;
}

/**
 * Check if cached data is still valid
 */
function isNavCacheValid(): bool {
    if (!file_exists(NAV_CACHE_FILE)) {
        return false;
    }

    $cacheTime = filemtime(NAV_CACHE_FILE);
    return (time() - $cacheTime) < NAV_CACHE_TTL;
}

/**
 * Load data from cache file
 */
function loadNavCache(): array {
    $content = file_get_contents(NAV_CACHE_FILE);
    $data = json_decode($content, true);

    return $data ?: getDefaultCounts();
}

/**
 * Save data to cache file
 */
function saveNavCache(array $data): void {
    $cacheDir = dirname(NAV_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    file_put_contents(NAV_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Fetch fresh counts from API
 */
function fetchNavCountsFromAPI(): array {
    // Try to fetch from API
    $apiUrl = (defined('SITE_URL') ? SITE_URL : '') . NAV_API_URL;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5, // 5 second timeout
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        // API not available, return defaults
        return getDefaultCounts();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success']) {
        return getDefaultCounts();
    }

    return $data['counts'] ?? getDefaultCounts();
}

/**
 * Get default counts (fallback when API is unavailable)
 */
function getDefaultCounts(): array {
    return [
        'cities' => [
            'bucuresti' => 238,
            'cluj' => 94,
            'timisoara' => 67,
            'iasi' => 52,
            'brasov' => 41,
            'constanta' => 38
        ],
        'categories' => [
            'concerte' => 156,
            'festivaluri' => 24,
            'teatru' => 89,
            'stand-up' => 67,
            'sport' => 34,
            'cluburi' => 112
        ],
        'venues' => [
            'arena-nationala' => 12,
            'sala-palatului' => 28,
            'bt-arena' => 18,
            'tnb' => 42,
            'arenele-romane' => 15,
            'romexpo' => 8
        ],
        'venue_types' => [
            'arene' => 24,
            'teatre' => 86,
            'cluburi' => 142,
            'open-air' => 38
        ]
    ];
}

/**
 * Force cache refresh
 */
function refreshNavCache(): array {
    $freshData = fetchNavCountsFromAPI();
    saveNavCache($freshData);
    return $freshData;
}

/**
 * Get a specific count value
 *
 * @param string $type   Type of count (cities, categories, venues, venue_types)
 * @param string $slug   Slug identifier
 * @param int    $default Default value if not found
 * @return int
 */
function getNavCount(string $type, string $slug, int $default = 0): int {
    static $counts = null;

    if ($counts === null) {
        $counts = getNavCounts();
    }

    return $counts[$type][$slug] ?? $default;
}

/**
 * Apply cached counts to navigation arrays
 *
 * @param array $items Navigation items array
 * @param string $type Type of counts to apply
 * @return array Items with updated counts
 */
function applyNavCounts(array $items, string $type): array {
    $counts = getNavCounts();

    foreach ($items as &$item) {
        $slug = $item['slug'] ?? '';
        if ($slug && isset($counts[$type][$slug])) {
            $item['count'] = $counts[$type][$slug];
        }
    }

    return $items;
}

// ==================== FEATURED CITIES CACHE ====================

define('FEATURED_CITIES_CACHE_FILE', __DIR__ . '/cache/featured-cities.json');
define('FEATURED_CITIES_CACHE_TTL', 30 * 60); // 30 minutes

/**
 * Get featured cities for navigation with caching
 *
 * @return array Featured cities array
 */
function getFeaturedCities(): array {
    // Check if cache exists and is valid
    if (isFeaturedCitiesCacheValid()) {
        return loadFeaturedCitiesCache();
    }

    // Fetch fresh data from API
    $freshData = fetchFeaturedCitiesFromAPI();

    // Save to cache
    saveFeaturedCitiesCache($freshData);

    return $freshData;
}

/**
 * Check if featured cities cache is still valid
 */
function isFeaturedCitiesCacheValid(): bool {
    if (!file_exists(FEATURED_CITIES_CACHE_FILE)) {
        return false;
    }

    $cacheTime = filemtime(FEATURED_CITIES_CACHE_FILE);
    return (time() - $cacheTime) < FEATURED_CITIES_CACHE_TTL;
}

/**
 * Load featured cities from cache file
 */
function loadFeaturedCitiesCache(): array {
    $content = file_get_contents(FEATURED_CITIES_CACHE_FILE);
    $data = json_decode($content, true);

    return $data ?: getDefaultFeaturedCities();
}

/**
 * Save featured cities to cache file
 */
function saveFeaturedCitiesCache(array $data): void {
    $cacheDir = dirname(FEATURED_CITIES_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    file_put_contents(FEATURED_CITIES_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Fetch featured cities from API via proxy
 */
function fetchFeaturedCitiesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Use internal API call via proxy
    $proxyUrl = '/api/proxy.php?action=locations.cities.featured';
    $fullUrl = (defined('SITE_URL') ? SITE_URL : '') . $proxyUrl;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        return getDefaultFeaturedCities();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['cities'])) {
        return getDefaultFeaturedCities();
    }

    // Transform API response to nav format
    $cities = [];
    foreach ($data['data']['cities'] as $index => $city) {
        $cities[] = [
            'name' => $city['name'],
            'slug' => $city['slug'],
            'image' => $city['image'] ?? 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=600&h=600&fit=crop',
            'count' => $city['events_count'] ?? 0,
            'featured' => $index === 0 // First city is featured (larger card)
        ];
    }

    return $cities;
}

/**
 * Get default featured cities (fallback)
 */
function getDefaultFeaturedCities(): array {
    return [
        [
            'name' => 'București',
            'slug' => 'bucuresti',
            'image' => 'https://images.unsplash.com/photo-1584646098378-0874589d76b1?w=600&h=600&fit=crop',
            'count' => 238,
            'featured' => true
        ],
        [
            'name' => 'Cluj-Napoca',
            'slug' => 'cluj-napoca',
            'image' => 'https://images.unsplash.com/photo-1587974928442-77dc3e0dba72?w=400&h=400&fit=crop',
            'count' => 94,
            'featured' => false
        ],
        [
            'name' => 'Timișoara',
            'slug' => 'timisoara',
            'image' => 'https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?w=400&h=400&fit=crop',
            'count' => 67,
            'featured' => false
        ],
        [
            'name' => 'Iași',
            'slug' => 'iasi',
            'image' => 'https://images.unsplash.com/photo-1560969184-10fe8719e047?w=400&h=400&fit=crop',
            'count' => 52,
            'featured' => false
        ],
        [
            'name' => 'Brașov',
            'slug' => 'brasov',
            'image' => 'https://images.unsplash.com/photo-1565264216052-3c9012481015?w=400&h=400&fit=crop',
            'count' => 41,
            'featured' => false
        ],
        [
            'name' => 'Constanța',
            'slug' => 'constanta',
            'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&h=400&fit=crop',
            'count' => 38,
            'featured' => false
        ]
    ];
}

// ==================== EVENT CATEGORIES CACHE ====================

define('EVENT_CATEGORIES_CACHE_FILE', __DIR__ . '/cache/event-categories.json');
define('EVENT_CATEGORIES_CACHE_TTL', 30 * 60); // 30 minutes

/**
 * Get event categories for navigation with caching
 *
 * @return array Event categories array
 */
function getEventCategories(): array {
    // Check if cache exists and is valid
    if (isEventCategoriesCacheValid()) {
        return loadEventCategoriesCache();
    }

    // Fetch fresh data from API
    $freshData = fetchEventCategoriesFromAPI();

    // Save to cache
    saveEventCategoriesCache($freshData);

    return $freshData;
}

/**
 * Check if event categories cache is still valid
 */
function isEventCategoriesCacheValid(): bool {
    if (!file_exists(EVENT_CATEGORIES_CACHE_FILE)) {
        return false;
    }

    $cacheTime = filemtime(EVENT_CATEGORIES_CACHE_FILE);
    return (time() - $cacheTime) < EVENT_CATEGORIES_CACHE_TTL;
}

/**
 * Load event categories from cache file
 */
function loadEventCategoriesCache(): array {
    $content = file_get_contents(EVENT_CATEGORIES_CACHE_FILE);
    $data = json_decode($content, true);

    return $data ?: getDefaultEventCategories();
}

/**
 * Save event categories to cache file
 */
function saveEventCategoriesCache(array $data): void {
    $cacheDir = dirname(EVENT_CATEGORIES_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    file_put_contents(EVENT_CATEGORIES_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Fetch event categories from API via proxy
 */
function fetchEventCategoriesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Use internal API call via proxy
    $proxyUrl = '/api/proxy.php?action=event-categories';
    $fullUrl = (defined('SITE_URL') ? SITE_URL : '') . $proxyUrl;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        return getDefaultEventCategories();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['categories'])) {
        return getDefaultEventCategories();
    }

    // Transform API response to nav format
    $categories = [];
    foreach ($data['data']['categories'] as $category) {
        $categories[] = [
            'name' => $category['name'],
            'slug' => $category['slug'],
            'icon' => $category['icon'] ?? '',
            'icon_emoji' => $category['icon_emoji'] ?? '🎫',
            'description' => $category['description'] ?? '',
            'image' => $category['image'] ?? '',
            'color' => $category['color'] ?? '#A51C30',
            'count' => $category['event_count'] ?? 0,
        ];
    }

    return $categories;
}

// ==================== FEATURED VENUES CACHE ====================

define('FEATURED_VENUES_CACHE_FILE', __DIR__ . '/cache/featured-venues.json');
define('FEATURED_VENUES_CACHE_TTL', 30 * 60); // 30 minutes

/**
 * Get featured venues for navigation with caching
 *
 * @return array Featured venues array
 */
function getFeaturedVenues(): array {
    // Check if cache exists and is valid
    if (isFeaturedVenuesCacheValid()) {
        return loadFeaturedVenuesCache();
    }

    // Fetch fresh data from API
    $freshData = fetchFeaturedVenuesFromAPI();

    // Save to cache
    saveFeaturedVenuesCache($freshData);

    return $freshData;
}

/**
 * Check if featured venues cache is still valid
 */
function isFeaturedVenuesCacheValid(): bool {
    if (!file_exists(FEATURED_VENUES_CACHE_FILE)) {
        return false;
    }

    $cacheTime = filemtime(FEATURED_VENUES_CACHE_FILE);
    return (time() - $cacheTime) < FEATURED_VENUES_CACHE_TTL;
}

/**
 * Load featured venues from cache file
 */
function loadFeaturedVenuesCache(): array {
    $content = file_get_contents(FEATURED_VENUES_CACHE_FILE);
    $data = json_decode($content, true);

    return $data ?: getDefaultFeaturedVenues();
}

/**
 * Save featured venues to cache file
 */
function saveFeaturedVenuesCache(array $data): void {
    $cacheDir = dirname(FEATURED_VENUES_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    file_put_contents(FEATURED_VENUES_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Fetch featured venues from API via proxy
 */
function fetchFeaturedVenuesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Use internal API call via proxy
    $proxyUrl = '/api/proxy.php?action=venues.featured';
    $fullUrl = (defined('SITE_URL') ? SITE_URL : '') . $proxyUrl;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        return getDefaultFeaturedVenues();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['venues'])) {
        return getDefaultFeaturedVenues();
    }

    // Transform API response to nav format
    $venues = [];
    foreach ($data['data']['venues'] as $venue) {
        $venues[] = [
            'name' => $venue['name'],
            'slug' => $venue['slug'],
            'address' => $venue['city'] ?? '',
            'count' => $venue['events_count'] ?? 0,
            'image' => $venue['image'] ?? 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=200&h=200&fit=crop'
        ];
    }

    return $venues;
}

/**
 * Get default featured venues (fallback)
 */
function getDefaultFeaturedVenues(): array {
    return [
        [
            'name' => 'Arena Națională',
            'slug' => 'arena-nationala',
            'address' => 'București',
            'count' => 12,
            'image' => 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=200&h=200&fit=crop'
        ],
        [
            'name' => 'Sala Palatului',
            'slug' => 'sala-palatului',
            'address' => 'București',
            'count' => 28,
            'image' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=200&h=200&fit=crop'
        ],
        [
            'name' => 'BT Arena',
            'slug' => 'bt-arena',
            'address' => 'Cluj-Napoca',
            'count' => 18,
            'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=200&h=200&fit=crop'
        ],
        [
            'name' => 'Teatrul Național',
            'slug' => 'tnb',
            'address' => 'București',
            'count' => 42,
            'image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=200&h=200&fit=crop'
        ],
        [
            'name' => 'Arenele Romane',
            'slug' => 'arenele-romane',
            'address' => 'București',
            'count' => 15,
            'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200&h=200&fit=crop'
        ],
        [
            'name' => 'Romexpo',
            'slug' => 'romexpo',
            'address' => 'București',
            'count' => 8,
            'image' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=200&h=200&fit=crop'
        ]
    ];
}

// ==================== VENUE CATEGORIES CACHE ====================

define('VENUE_CATEGORIES_CACHE_FILE', __DIR__ . '/cache/venue-categories.json');
define('VENUE_CATEGORIES_CACHE_TTL', 30 * 60); // 30 minutes

/**
 * Get venue categories for navigation with caching
 *
 * @return array Venue categories array
 */
function getVenueCategories(): array {
    // Check if cache exists and is valid
    if (isVenueCategoriesCacheValid()) {
        return loadVenueCategoriesCache();
    }

    // Fetch fresh data from API
    $freshData = fetchVenueCategoriesFromAPI();

    // Save to cache
    saveVenueCategoriesCache($freshData);

    return $freshData;
}

/**
 * Check if venue categories cache is still valid
 */
function isVenueCategoriesCacheValid(): bool {
    if (!file_exists(VENUE_CATEGORIES_CACHE_FILE)) {
        return false;
    }

    $cacheTime = filemtime(VENUE_CATEGORIES_CACHE_FILE);
    return (time() - $cacheTime) < VENUE_CATEGORIES_CACHE_TTL;
}

/**
 * Load venue categories from cache file
 */
function loadVenueCategoriesCache(): array {
    $content = file_get_contents(VENUE_CATEGORIES_CACHE_FILE);
    $data = json_decode($content, true);

    return $data ?: getDefaultVenueCategories();
}

/**
 * Save venue categories to cache file
 */
function saveVenueCategoriesCache(array $data): void {
    $cacheDir = dirname(VENUE_CATEGORIES_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    file_put_contents(VENUE_CATEGORIES_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Fetch venue categories from API via proxy
 */
function fetchVenueCategoriesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Use internal API call via proxy
    $proxyUrl = '/api/proxy.php?action=venue-categories';
    $fullUrl = (defined('SITE_URL') ? SITE_URL : '') . $proxyUrl;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        return getDefaultVenueCategories();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['categories'])) {
        return getDefaultVenueCategories();
    }

    // Transform API response to nav format
    $categories = [];
    foreach ($data['data']['categories'] as $category) {
        $categories[] = [
            'name' => $category['name'],
            'slug' => $category['slug'],
            'icon' => $category['icon'] ?? '',
            'count' => $category['venues_count'] ?? 0,
        ];
    }

    return $categories;
}

/**
 * Get default venue categories (fallback)
 */
function getDefaultVenueCategories(): array {
    return [
        [
            'name' => 'Arene & Stadioane',
            'slug' => 'arene',
            'count' => 24,
            'icon' => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>'
        ],
        [
            'name' => 'Teatre & Săli',
            'slug' => 'teatre',
            'count' => 86,
            'icon' => '<path d="M2 16.1A5 5 0 0 1 5.9 20M2 12.05A9 9 0 0 1 9.95 20M2 8V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6"/><line x1="2" y1="20" x2="2" y2="20"/>'
        ],
        [
            'name' => 'Cluburi & Baruri',
            'slug' => 'cluburi',
            'count' => 142,
            'icon' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'
        ],
        [
            'name' => 'Open Air',
            'slug' => 'open-air',
            'count' => 38,
            'icon' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
        ]
    ];
}

/**
 * Get default event categories (fallback)
 */
function getDefaultEventCategories(): array {
    return [
        [
            'name' => 'Concerte',
            'slug' => 'concerte',
            'icon' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
            'icon_emoji' => '🎸',
            'description' => 'Descopera cele mai tari concerte din Romania.',
            'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1920&q=80',
            'color' => '#A51C30',
            'count' => 156
        ],
        [
            'name' => 'Festivaluri',
            'slug' => 'festivaluri',
            'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'icon_emoji' => '🎪',
            'description' => 'Cele mai mari festivaluri de muzica din Romania.',
            'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=1920&q=80',
            'color' => '#E67E22',
            'count' => 24
        ],
        [
            'name' => 'Teatru',
            'slug' => 'teatru',
            'icon' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
            'icon_emoji' => '🎭',
            'description' => 'Spectacole de teatru, opere si balet.',
            'image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1920&q=80',
            'color' => '#8B1728',
            'count' => 89
        ],
        [
            'name' => 'Stand-up',
            'slug' => 'stand-up',
            'icon' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
            'icon_emoji' => '😂',
            'description' => 'Show-uri de stand-up comedy.',
            'image' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=1920&q=80',
            'color' => '#F59E0B',
            'count' => 67
        ],
        [
            'name' => 'Sport',
            'slug' => 'sport',
            'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
            'icon_emoji' => '⚽',
            'description' => 'Evenimente sportive din Romania.',
            'image' => 'https://images.unsplash.com/photo-1461896836934-474a6c1d0f75?w=1920&q=80',
            'color' => '#3B82F6',
            'count' => 34
        ],
        [
            'name' => 'Expozitii',
            'slug' => 'expozitii',
            'icon' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'icon_emoji' => '🖼️',
            'description' => 'Expozitii de arta, fotografie si cultura.',
            'image' => 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5?w=1920&q=80',
            'color' => '#8B5CF6',
            'count' => 45
        ]
    ];
}
