<?php
/**
 * Instant Search API Endpoint
 *
 * Returns structured search results for events, artists, and locations.
 *
 * GET /api/v1/public/search?q={query}&limit={limit}
 *
 * Response format:
 * {
 *   "success": true,
 *   "query": "search term",
 *   "events": [...],
 *   "artists": [...],
 *   "locations": [...]
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=60'); // Cache for 1 minute

// Get query parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 10) : 5;

// Validate query
if (strlen($query) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Query must be at least 2 characters'
    ]);
    exit;
}

// In production, replace this with actual database queries
// For now, return demo data that matches the query

$response = [
    'success' => true,
    'query' => $query,
    'events' => searchEvents($query, $limit),
    'artists' => searchArtists($query, $limit),
    'locations' => searchLocations($query, $limit)
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Search events
 * TODO: Replace with actual database query
 */
function searchEvents(string $query, int $limit): array {
    // Demo data - replace with actual database query
    $allEvents = [
        [
            'name' => 'Coldplay - Music of the Spheres World Tour',
            'slug' => 'coldplay-music-of-the-spheres',
            'date' => '15 Iun 2025',
            'venue' => 'Arena Națională, București',
            'price' => 299,
            'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200&h=200&fit=crop',
            'keywords' => ['coldplay', 'concert', 'rock', 'bucuresti', 'arena']
        ],
        [
            'name' => 'UNTOLD Festival 2025',
            'slug' => 'untold-festival-2025',
            'date' => '7-10 Aug 2025',
            'venue' => 'Cluj Arena, Cluj-Napoca',
            'price' => 449,
            'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=200&h=200&fit=crop',
            'keywords' => ['untold', 'festival', 'cluj', 'electronic', 'dance']
        ],
        [
            'name' => 'Electric Castle 2025',
            'slug' => 'electric-castle-2025',
            'date' => '16-20 Iul 2025',
            'venue' => 'Bonțida, Cluj',
            'price' => 379,
            'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200&h=200&fit=crop',
            'keywords' => ['electric castle', 'festival', 'cluj', 'rock', 'alternative']
        ],
        [
            'name' => 'Stand-up Comedy cu Micutzu',
            'slug' => 'stand-up-micutzu',
            'date' => '22 Ian 2025',
            'venue' => 'Sala Palatului, București',
            'price' => 89,
            'image' => null,
            'keywords' => ['stand-up', 'comedy', 'micutzu', 'bucuresti']
        ],
        [
            'name' => 'Concert Smiley - 20 de ani',
            'slug' => 'concert-smiley-20-ani',
            'date' => '14 Feb 2025',
            'venue' => 'Romexpo, București',
            'price' => 150,
            'image' => null,
            'keywords' => ['smiley', 'concert', 'pop', 'bucuresti', 'romexpo']
        ],
        [
            'name' => 'Teatru: O noapte furtunoasă',
            'slug' => 'teatru-o-noapte-furtunoasa',
            'date' => '28 Ian 2025',
            'venue' => 'Teatrul Național, București',
            'price' => 60,
            'image' => null,
            'keywords' => ['teatru', 'drama', 'caragiale', 'bucuresti', 'tnb']
        ],
        [
            'name' => 'Neversea Festival 2025',
            'slug' => 'neversea-2025',
            'date' => '3-6 Iul 2025',
            'venue' => 'Plaja Modern, Constanța',
            'price' => 399,
            'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=200&h=200&fit=crop',
            'keywords' => ['neversea', 'festival', 'constanta', 'beach', 'electronic']
        ],
        [
            'name' => 'Cargo - Concert Aniversar',
            'slug' => 'cargo-concert-aniversar',
            'date' => '8 Mar 2025',
            'venue' => 'Arenele Romane, București',
            'price' => 120,
            'image' => null,
            'keywords' => ['cargo', 'rock', 'concert', 'bucuresti', 'arenele romane']
        ]
    ];

    $queryLower = mb_strtolower($query);
    $results = [];

    foreach ($allEvents as $event) {
        $matchScore = 0;

        // Check name match
        if (stripos($event['name'], $query) !== false) {
            $matchScore += 10;
        }

        // Check keywords
        foreach ($event['keywords'] as $keyword) {
            if (stripos($keyword, $queryLower) !== false || stripos($queryLower, $keyword) !== false) {
                $matchScore += 5;
            }
        }

        if ($matchScore > 0) {
            $results[] = [
                'name' => $event['name'],
                'slug' => $event['slug'],
                'date' => $event['date'],
                'venue' => $event['venue'],
                'price' => $event['price'],
                'image' => $event['image'],
                'score' => $matchScore
            ];
        }
    }

    // Sort by score descending
    usort($results, fn($a, $b) => $b['score'] - $a['score']);

    // Remove score from results and limit
    return array_map(function($item) {
        unset($item['score']);
        return $item;
    }, array_slice($results, 0, $limit));
}

/**
 * Search artists
 * TODO: Replace with actual database query
 */
function searchArtists(string $query, int $limit): array {
    $allArtists = [
        [
            'name' => 'Coldplay',
            'slug' => 'coldplay',
            'genre' => 'Rock Alternativ',
            'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200&h=200&fit=crop',
            'keywords' => ['coldplay', 'rock', 'british', 'alternative']
        ],
        [
            'name' => 'Smiley',
            'slug' => 'smiley',
            'genre' => 'Pop',
            'image' => null,
            'keywords' => ['smiley', 'pop', 'romanian', 'haha production']
        ],
        [
            'name' => 'Cargo',
            'slug' => 'cargo',
            'genre' => 'Rock',
            'image' => null,
            'keywords' => ['cargo', 'rock', 'romanian', 'classic rock']
        ],
        [
            'name' => 'Micutzu',
            'slug' => 'micutzu',
            'genre' => 'Stand-up Comedy',
            'image' => null,
            'keywords' => ['micutzu', 'comedy', 'stand-up', 'comedian']
        ],
        [
            'name' => 'Subcarpați',
            'slug' => 'subcarpati',
            'genre' => 'Hip-Hop / Folk',
            'image' => null,
            'keywords' => ['subcarpati', 'hip-hop', 'folk', 'romanian']
        ],
        [
            'name' => 'The Motans',
            'slug' => 'the-motans',
            'genre' => 'Pop / Rock',
            'image' => null,
            'keywords' => ['motans', 'pop', 'rock', 'romanian']
        ],
        [
            'name' => 'Irina Rimes',
            'slug' => 'irina-rimes',
            'genre' => 'Pop',
            'image' => null,
            'keywords' => ['irina', 'rimes', 'pop', 'romanian']
        ],
        [
            'name' => 'Carla\'s Dreams',
            'slug' => 'carlas-dreams',
            'genre' => 'Pop / Electronic',
            'image' => null,
            'keywords' => ['carla', 'dreams', 'pop', 'electronic', 'romanian']
        ]
    ];

    $queryLower = mb_strtolower($query);
    $results = [];

    foreach ($allArtists as $artist) {
        $matchScore = 0;

        if (stripos($artist['name'], $query) !== false) {
            $matchScore += 10;
        }

        foreach ($artist['keywords'] as $keyword) {
            if (stripos($keyword, $queryLower) !== false || stripos($queryLower, $keyword) !== false) {
                $matchScore += 5;
            }
        }

        if ($matchScore > 0) {
            $results[] = [
                'name' => $artist['name'],
                'slug' => $artist['slug'],
                'genre' => $artist['genre'],
                'image' => $artist['image'],
                'score' => $matchScore
            ];
        }
    }

    usort($results, fn($a, $b) => $b['score'] - $a['score']);

    return array_map(function($item) {
        unset($item['score']);
        return $item;
    }, array_slice($results, 0, $limit));
}

/**
 * Search locations/venues
 * TODO: Replace with actual database query
 */
function searchLocations(string $query, int $limit): array {
    $allLocations = [
        [
            'name' => 'Arena Națională',
            'slug' => 'arena-nationala',
            'address' => 'Bd. Basarabia 37-39, București',
            'city' => 'București',
            'image' => 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=200&h=200&fit=crop',
            'keywords' => ['arena', 'nationala', 'bucuresti', 'stadion']
        ],
        [
            'name' => 'Sala Palatului',
            'slug' => 'sala-palatului',
            'address' => 'Str. Ion Câmpineanu 28, București',
            'city' => 'București',
            'image' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=200&h=200&fit=crop',
            'keywords' => ['sala', 'palatului', 'bucuresti', 'concert hall']
        ],
        [
            'name' => 'BT Arena (Sala Polivalentă)',
            'slug' => 'bt-arena',
            'address' => 'Aleea Stadionului 2, Cluj-Napoca',
            'city' => 'Cluj-Napoca',
            'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=200&h=200&fit=crop',
            'keywords' => ['bt', 'arena', 'cluj', 'polivalenta']
        ],
        [
            'name' => 'Teatrul Național București',
            'slug' => 'tnb',
            'address' => 'Bd. Nicolae Bălcescu 2, București',
            'city' => 'București',
            'image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=200&h=200&fit=crop',
            'keywords' => ['teatru', 'national', 'tnb', 'bucuresti']
        ],
        [
            'name' => 'Arenele Romane',
            'slug' => 'arenele-romane',
            'address' => 'Str. Cutitul de Argint 17, București',
            'city' => 'București',
            'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200&h=200&fit=crop',
            'keywords' => ['arenele', 'romane', 'bucuresti', 'open air']
        ],
        [
            'name' => 'Romexpo',
            'slug' => 'romexpo',
            'address' => 'Bd. Mărăști 65-67, București',
            'city' => 'București',
            'image' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=200&h=200&fit=crop',
            'keywords' => ['romexpo', 'bucuresti', 'expo', 'pavilion']
        ],
        [
            'name' => 'Cluj Arena',
            'slug' => 'cluj-arena',
            'address' => 'Aleea Stadionului 2, Cluj-Napoca',
            'city' => 'Cluj-Napoca',
            'image' => null,
            'keywords' => ['cluj', 'arena', 'stadion']
        ],
        [
            'name' => 'Plaja Modern Constanța',
            'slug' => 'plaja-modern-constanta',
            'address' => 'Bulevardul Elisabeta, Constanța',
            'city' => 'Constanța',
            'image' => null,
            'keywords' => ['plaja', 'modern', 'constanta', 'neversea', 'beach']
        ]
    ];

    $queryLower = mb_strtolower($query);
    $results = [];

    foreach ($allLocations as $location) {
        $matchScore = 0;

        if (stripos($location['name'], $query) !== false) {
            $matchScore += 10;
        }

        if (stripos($location['city'], $query) !== false) {
            $matchScore += 5;
        }

        foreach ($location['keywords'] as $keyword) {
            if (stripos($keyword, $queryLower) !== false || stripos($queryLower, $keyword) !== false) {
                $matchScore += 3;
            }
        }

        if ($matchScore > 0) {
            $results[] = [
                'name' => $location['name'],
                'slug' => $location['slug'],
                'address' => $location['address'],
                'city' => $location['city'],
                'image' => $location['image'],
                'score' => $matchScore
            ];
        }
    }

    usort($results, fn($a, $b) => $b['score'] - $a['score']);

    return array_map(function($item) {
        unset($item['score']);
        return $item;
    }, array_slice($results, 0, $limit));
}
