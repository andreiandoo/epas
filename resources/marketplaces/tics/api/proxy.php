<?php
/**
 * TICS.ro - API Proxy
 *
 * Serves as proxy to Tixello Core API or returns demo data for development
 * Endpoints:
 *   GET /api/proxy.php?endpoint=events - Get events
 *   GET /api/proxy.php?endpoint=search&q=query - Search
 *   GET /api/proxy.php?endpoint=event&slug=slug - Get single event
 */

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include config for API settings
require_once __DIR__ . '/../includes/config.php';

// Get endpoint from query
$endpoint = $_GET['endpoint'] ?? 'events';

// Development mode: use demo data
$useDemoData = true; // Set to false in production

if ($useDemoData) {
    serveDemoData($endpoint);
} else {
    proxyToAPI($endpoint);
}

/**
 * Serve demo data from local JSON files
 */
function serveDemoData($endpoint) {
    switch ($endpoint) {
        case 'events':
            $data = loadDemoEvents();
            $data = filterAndPaginate($data);
            echo json_encode($data);
            break;

        case 'search':
            $query = $_GET['q'] ?? '';
            $data = searchDemoData($query);
            echo json_encode($data);
            break;

        case 'event':
            $slug = $_GET['slug'] ?? '';
            $event = findEventBySlug($slug);
            echo json_encode(['success' => true, 'data' => $event]);
            break;

        case 'categories':
            global $CATEGORIES;
            echo json_encode(['success' => true, 'data' => array_values($CATEGORIES)]);
            break;

        case 'cities':
            global $FEATURED_CITIES;
            echo json_encode(['success' => true, 'data' => $FEATURED_CITIES]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown endpoint']);
    }
}

/**
 * Proxy request to Tixello Core API
 */
function proxyToAPI($endpoint) {
    $apiUrl = API_BASE_URL . '/' . $endpoint;

    // Forward query parameters
    $queryParams = $_GET;
    unset($queryParams['endpoint']);

    if (!empty($queryParams)) {
        $apiUrl .= '?' . http_build_query($queryParams);
    }

    // Initialize cURL
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . API_KEY,
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'API connection failed']);
        return;
    }

    http_response_code($httpCode);
    echo $response;
}

/**
 * Load demo events from JSON file
 */
function loadDemoEvents() {
    $file = __DIR__ . '/../data/demo-events.json';
    if (!file_exists($file)) {
        return ['success' => true, 'data' => [], 'meta' => ['total' => 0]];
    }

    $content = file_get_contents($file);
    return json_decode($content, true);
}

/**
 * Filter and paginate events
 */
function filterAndPaginate($data) {
    $events = $data['data'] ?? [];

    // Apply filters
    $category = $_GET['category'] ?? '';
    $city = $_GET['city'] ?? '';
    $date = $_GET['date'] ?? '';
    $price = $_GET['price'] ?? '';
    $sort = $_GET['sort'] ?? 'recommended';
    $search = $_GET['q'] ?? $_GET['search'] ?? '';

    if ($category) {
        $events = array_filter($events, function($e) use ($category) {
            return ($e['category']['slug'] ?? '') === $category;
        });
    }

    if ($city) {
        $events = array_filter($events, function($e) use ($city) {
            $venueCity = strtolower($e['venue']['city'] ?? '');
            $venueCity = removeDiacritics($venueCity);
            $venueCity = str_replace([' ', '-'], '', $venueCity);
            $searchCity = removeDiacritics(strtolower($city));
            $searchCity = str_replace([' ', '-'], '', $searchCity);
            return strpos($venueCity, $searchCity) !== false;
        });
    }

    if ($search) {
        $search = strtolower($search);
        $events = array_filter($events, function($e) use ($search) {
            $name = strtolower($e['name'] ?? '');
            $venue = strtolower($e['venue']['name'] ?? '');
            $city = strtolower($e['venue']['city'] ?? '');
            return strpos($name, $search) !== false ||
                   strpos($venue, $search) !== false ||
                   strpos($city, $search) !== false;
        });
    }

    if ($date) {
        $now = new DateTime();
        $events = array_filter($events, function($e) use ($date, $now) {
            $eventDate = new DateTime($e['starts_at'] ?? 'now');
            switch ($date) {
                case 'today':
                    return $eventDate->format('Y-m-d') === $now->format('Y-m-d');
                case 'tomorrow':
                    $tomorrow = (clone $now)->modify('+1 day');
                    return $eventDate->format('Y-m-d') === $tomorrow->format('Y-m-d');
                case 'weekend':
                    $friday = (clone $now)->modify('friday this week');
                    $sunday = (clone $now)->modify('sunday this week');
                    return $eventDate >= $friday && $eventDate <= $sunday;
                case 'week':
                    $nextWeek = (clone $now)->modify('+7 days');
                    return $eventDate >= $now && $eventDate <= $nextWeek;
                case 'month':
                    return $eventDate->format('Y-m') === $now->format('Y-m');
                default:
                    return true;
            }
        });
    }

    if ($price) {
        $events = array_filter($events, function($e) use ($price) {
            $eventPrice = $e['price_from'] ?? 0;
            switch ($price) {
                case 'free':
                    return $eventPrice == 0;
                case '0-100':
                    return $eventPrice > 0 && $eventPrice <= 100;
                case '100-300':
                    return $eventPrice > 100 && $eventPrice <= 300;
                case '300-500':
                    return $eventPrice > 300 && $eventPrice <= 500;
                case '500+':
                    return $eventPrice > 500;
                default:
                    return true;
            }
        });
    }

    // Re-index array
    $events = array_values($events);

    // Sort
    switch ($sort) {
        case 'date':
            usort($events, function($a, $b) {
                return strtotime($a['starts_at'] ?? 0) - strtotime($b['starts_at'] ?? 0);
            });
            break;
        case 'price_asc':
            usort($events, function($a, $b) {
                return ($a['price_from'] ?? 0) - ($b['price_from'] ?? 0);
            });
            break;
        case 'price_desc':
            usort($events, function($a, $b) {
                return ($b['price_from'] ?? 0) - ($a['price_from'] ?? 0);
            });
            break;
        case 'popular':
            usort($events, function($a, $b) {
                return ($b['sold_percentage'] ?? 0) - ($a['sold_percentage'] ?? 0);
            });
            break;
        case 'recommended':
        default:
            usort($events, function($a, $b) {
                return ($b['ai_match'] ?? 0) - ($a['ai_match'] ?? 0);
            });
            break;
    }

    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 24)));
    $total = count($events);
    $totalPages = ceil($total / $perPage);

    $offset = ($page - 1) * $perPage;
    $events = array_slice($events, $offset, $perPage);

    return [
        'success' => true,
        'data' => $events,
        'meta' => [
            'current_page' => $page,
            'last_page' => $totalPages,
            'per_page' => $perPage,
            'total' => $total
        ]
    ];
}

/**
 * Search demo data
 */
function searchDemoData($query) {
    if (strlen($query) < 2) {
        return ['success' => true, 'data' => ['events' => [], 'artists' => [], 'locations' => []]];
    }

    $data = loadDemoEvents();
    $events = $data['data'] ?? [];
    $query = strtolower($query);

    // Search events
    $matchedEvents = array_filter($events, function($e) use ($query) {
        $name = strtolower($e['name'] ?? '');
        return strpos($name, $query) !== false;
    });
    $matchedEvents = array_slice(array_values($matchedEvents), 0, 5);

    // Extract unique venues for locations
    $locations = [];
    foreach ($events as $e) {
        $venue = $e['venue'] ?? [];
        $venueName = strtolower($venue['name'] ?? '');
        $city = strtolower($venue['city'] ?? '');
        if (strpos($venueName, $query) !== false || strpos($city, $query) !== false) {
            $key = $venue['name'] . ', ' . $venue['city'];
            if (!isset($locations[$key])) {
                $locations[$key] = [
                    'name' => $venue['name'] ?? '',
                    'city' => $venue['city'] ?? '',
                    'events_count' => 0
                ];
            }
            $locations[$key]['events_count']++;
        }
    }
    $locations = array_slice(array_values($locations), 0, 3);

    // Artists (extracted from event names for demo)
    $artists = [];
    foreach ($matchedEvents as $e) {
        $name = $e['name'];
        if (strpos($name, ' - ') !== false) {
            $artistName = explode(' - ', $name)[0];
            $artists[] = [
                'name' => $artistName,
                'type' => 'artist',
                'events_count' => 1
            ];
        }
    }
    $artists = array_slice($artists, 0, 3);

    return [
        'success' => true,
        'data' => [
            'events' => $matchedEvents,
            'artists' => $artists,
            'locations' => $locations
        ]
    ];
}

/**
 * Remove diacritics from string (Romanian accents, etc.)
 */
function removeDiacritics($str) {
    $diacritics = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
        'ş' => 's', 'ţ' => 't', 'Ş' => 'S', 'Ţ' => 'T', // cedilla variants
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
    ];
    return strtr($str, $diacritics);
}

/**
 * Find event by slug
 */
function findEventBySlug($slug) {
    $data = loadDemoEvents();
    $events = $data['data'] ?? [];

    foreach ($events as $event) {
        if (($event['slug'] ?? '') === $slug) {
            return $event;
        }
    }

    return null;
}
