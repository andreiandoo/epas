<?php
/**
 * Navigation Counts API Endpoint
 *
 * Returns dynamic counts for navigation menu elements.
 * Used by nav-cache.php to update cached counts every 6 hours.
 *
 * GET /api/v1/public/navigation/counts
 *
 * Response format:
 * {
 *   "success": true,
 *   "counts": {
 *     "cities": { "bucuresti": 238, ... },
 *     "categories": { "concerte": 156, ... },
 *     "venues": { "arena-nationala": 12, ... },
 *     "venue_types": { "arene": 24, ... }
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=21600'); // Cache for 6 hours

// In production, these counts should come from database queries
// Example queries:
//   SELECT city_slug, COUNT(*) FROM events WHERE status = 'published' GROUP BY city_slug
//   SELECT category_slug, COUNT(*) FROM events WHERE status = 'published' GROUP BY category_slug

$response = [
    'success' => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'counts' => [
        'cities' => getCityCounts(),
        'categories' => getCategoryCounts(),
        'venues' => getVenueCounts(),
        'venue_types' => getVenueTypeCounts()
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Get event counts by city
 * TODO: Replace with actual database query
 */
function getCityCounts(): array {
    // In production:
    // SELECT city_slug, COUNT(*) as count
    // FROM events
    // WHERE status = 'published' AND end_date >= NOW()
    // GROUP BY city_slug

    return [
        'bucuresti' => 238,
        'cluj' => 94,
        'timisoara' => 67,
        'iasi' => 52,
        'brasov' => 41,
        'constanta' => 38,
        'sibiu' => 29,
        'craiova' => 23,
        'oradea' => 18,
        'galati' => 15
    ];
}

/**
 * Get event counts by category
 * TODO: Replace with actual database query
 */
function getCategoryCounts(): array {
    // In production:
    // SELECT category_slug, COUNT(*) as count
    // FROM events
    // WHERE status = 'published' AND end_date >= NOW()
    // GROUP BY category_slug

    return [
        'concerte' => 156,
        'festivaluri' => 24,
        'teatru' => 89,
        'stand-up' => 67,
        'sport' => 34,
        'cluburi' => 112,
        'opera' => 28,
        'expozitii' => 45,
        'conferinte' => 31,
        'copii' => 56
    ];
}

/**
 * Get event counts by venue
 * TODO: Replace with actual database query
 */
function getVenueCounts(): array {
    // In production:
    // SELECT venue_slug, COUNT(*) as count
    // FROM events
    // WHERE status = 'published' AND end_date >= NOW()
    // GROUP BY venue_slug

    return [
        'arena-nationala' => 12,
        'sala-palatului' => 28,
        'bt-arena' => 18,
        'tnb' => 42,
        'arenele-romane' => 15,
        'romexpo' => 8,
        'cluj-arena' => 14,
        'opera-nationala' => 35,
        'beraria-h' => 22,
        'hard-rock-cafe' => 19
    ];
}

/**
 * Get venue counts by type
 * TODO: Replace with actual database query
 */
function getVenueTypeCounts(): array {
    // In production:
    // SELECT venue_type_slug, COUNT(DISTINCT venue_id) as count
    // FROM venues
    // WHERE is_active = 1
    // GROUP BY venue_type_slug

    return [
        'arene' => 24,
        'teatre' => 86,
        'cluburi' => 142,
        'open-air' => 38,
        'sali-conferinte' => 55,
        'muzee' => 32,
        'parcuri' => 18
    ];
}
