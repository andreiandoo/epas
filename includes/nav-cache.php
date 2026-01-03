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
