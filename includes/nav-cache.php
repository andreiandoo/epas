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
 * Check if a slug is valid (no corrupted characters)
 * Valid slugs contain only: a-z, 0-9, hyphen
 */
function isValidSlug(?string $slug): bool {
    if (empty($slug)) return false;
    // Check for UTF-8 replacement character (corrupted data)
    if (strpos($slug, "\xEF\xBF\xBD") !== false) return false;
    if (strpos($slug, '�') !== false) return false;
    // Valid slug pattern: lowercase letters, numbers, hyphens only
    return (bool) preg_match('/^[a-z0-9-]+$/', $slug);
}

/**
 * Check if content contains corruption markers
 */
function isContentCorrupted(string $content): bool {
    // Yen symbol indicates Shift-JIS encoding issue
    if (strpos($content, '¥') !== false) return true;
    // UTF-8 replacement character (raw bytes)
    if (strpos($content, "\xEF\xBF\xBD") !== false) return true;
    // UTF-8 replacement character (as displayed)
    if (strpos($content, '�') !== false) return true;
    // Half-width katakana (Shift-JIS corruption)
    if (preg_match('/[\xEF\xBD\x80-\xEF\xBD\xBF\xEF\xBE\x80-\xEF\xBE\x9F]/u', $content)) return true;
    return false;
}

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

    // Detect corrupted files
    if (isContentCorrupted($content)) {
        @unlink(NAV_CACHE_FILE);
        return getDefaultCounts();
    }

    // Strip UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

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

    // Use all JSON flags for proper encoding (avoid backslash issues on some servers)
    file_put_contents(NAV_CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    // Detect corrupted files
    if (isContentCorrupted($content)) {
        @unlink(FEATURED_CITIES_CACHE_FILE);
        return getDefaultFeaturedCities();
    }

    // Strip UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $data = json_decode($content, true);

    if (!$data) {
        return getDefaultFeaturedCities();
    }

    // Filter out items with invalid slugs
    return array_values(array_filter($data, fn($item) => isValidSlug($item['slug'] ?? null)));
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

    // Call API directly (not through proxy) for server-side requests
    $apiUrl = API_BASE_URL . '/locations/cities/featured';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
            ],
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        error_log('[nav-cache] Failed to fetch featured cities from API: ' . $apiUrl);
        return getDefaultFeaturedCities();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['cities'])) {
        error_log('[nav-cache] Invalid API response for featured cities: ' . substr($response, 0, 500));
        return getDefaultFeaturedCities();
    }

    // Transform API response to nav format
    $cities = [];
    foreach ($data['data']['cities'] as $index => $city) {
        $cities[] = [
            'name' => $city['name'],
            'slug' => $city['slug'],
            'image' => $city['image'] ?? '/assets/images/default-city.png',
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

    // Detect corrupted files
    if (isContentCorrupted($content)) {
        @unlink(EVENT_CATEGORIES_CACHE_FILE);
        return getDefaultEventCategories();
    }

    // Strip UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $data = json_decode($content, true);

    if (!$data) {
        return getDefaultEventCategories();
    }

    // Filter out items with invalid slugs
    return array_values(array_filter($data, fn($item) => isValidSlug($item['slug'] ?? null)));
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
 * Fetch event categories from API directly
 */
function fetchEventCategoriesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Call API directly (not through proxy) for server-side requests
    $apiUrl = API_BASE_URL . '/event-categories';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
            ],
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        error_log('[nav-cache] Failed to fetch event categories from API: ' . $apiUrl);
        return getDefaultEventCategories();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['categories'])) {
        error_log('[nav-cache] Invalid API response for event categories: ' . substr($response, 0, 500));
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
            'sort_order' => $category['sort_order'] ?? 0,
        ];
    }

    // Sort by sort_order ascending (in case API didn't sort)
    usort($categories, function($a, $b) {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

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

    // Detect corrupted files
    if (isContentCorrupted($content)) {
        @unlink(FEATURED_VENUES_CACHE_FILE);
        return getDefaultFeaturedVenues();
    }

    // Strip UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $data = json_decode($content, true);

    if (!$data) {
        return getDefaultFeaturedVenues();
    }

    // Filter out items with invalid slugs
    return array_values(array_filter($data, fn($item) => isValidSlug($item['slug'] ?? null)));
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
 * Fetch featured venues from API directly
 */
function fetchFeaturedVenuesFromAPI(): array {
    require_once __DIR__ . '/../includes/config.php';

    // Call API directly (not through proxy) for server-side requests
    $apiUrl = API_BASE_URL . '/venues/featured';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
            ],
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        error_log('[nav-cache] Failed to fetch featured venues from API: ' . $apiUrl);
        return getDefaultFeaturedVenues();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['venues'])) {
        error_log('[nav-cache] Invalid API response for featured venues: ' . substr($response, 0, 500));
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

    // Detect corrupted files
    if (isContentCorrupted($content)) {
        @unlink(VENUE_CATEGORIES_CACHE_FILE);
        return getDefaultVenueCategories();
    }

    // Strip UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $data = json_decode($content, true);

    if (!$data) {
        return getDefaultVenueCategories();
    }

    // Filter out items with invalid slugs
    return array_values(array_filter($data, fn($item) => isValidSlug($item['slug'] ?? null)));
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

    // Call API directly (not through proxy) for server-side requests
    $apiUrl = API_BASE_URL . '/venue-categories';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
            ],
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        error_log('[nav-cache] Failed to fetch venue categories from API: ' . $apiUrl);
        return getDefaultVenueCategories();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success']) || !$data['success'] || !isset($data['data']['categories'])) {
        error_log('[nav-cache] Invalid API response for venue categories: ' . substr($response, 0, 500));
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
 * Convert heroicon name to SVG path elements
 * Supports common outline heroicons used for categories
 */
function getHeroiconPath(string $heroiconName): ?string {
    // Remove 'heroicon-o-' or 'heroicon-s-' prefix
    $iconName = preg_replace('/^heroicon-(o|s)-/', '', $heroiconName);

    // Common heroicon outline paths
    $heroicons = [
        // Music & Entertainment
        'musical-note' => '<path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>',
        'microphone' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'music' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',

        // Events & Calendar
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'calendar-days' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>',
        'ticket' => '<path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',

        // Places & Venues
        'building-office' => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>',
        'building-library' => '<path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>',
        'home' => '<path d="M3 12l9-9 9 9"/><path d="M9 21V12h6v9"/>',

        // Entertainment Types
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'sparkles' => '<path d="M5 3v4M3 5h4M6 17v4M4 19h4M13 3l1.5 4.5L19 9l-4.5 1.5L13 15l-1.5-4.5L7 9l4.5-1.5L13 3z"/>',
        'fire' => '<path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/>',
        'face-smile' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',

        // Sports
        'trophy' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'globe-alt' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',

        // Art & Culture
        'photo' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'film' => '<rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/>',
        'paint-brush' => '<path d="M18.37 2.63 14 7l-1.59-1.59a2 2 0 0 0-2.82 0L8 7l9 9 1.59-1.59a2 2 0 0 0 0-2.82L17 10l4.37-4.37a2.12 2.12 0 1 0-3-3Z"/><path d="M9 8c-2 3-4 3.5-7 4l8 10c2-1 6-5 6-7"/><path d="M14.5 17.5 4.5 15"/>',

        // Sun & Nature
        'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',

        // Users & Social
        'user-group' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',

        // Generic
        'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'squares-2x2' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'cube' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'bolt' => '<path d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>',
        'cake' => '<path d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0L3 16.5m15-3.379a48.474 48.474 0 0 0-6-.371c-2.032 0-4.034.126-6 .371m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.094 1.837 2.175v5.169c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 0 1 3 20.625v-5.17c0-1.08.768-2.014 1.837-2.174A47.78 47.78 0 0 1 6 13.12M12.265 3.11a.375.375 0 1 1-.53 0L12 2.845l.265.265Zm-3 0a.375.375 0 1 1-.53 0L9 2.845l.265.265Zm6 0a.375.375 0 1 1-.53 0L15 2.845l.265.265Z"/>',
    ];

    return $heroicons[$iconName] ?? null;
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
