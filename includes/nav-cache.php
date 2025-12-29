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
