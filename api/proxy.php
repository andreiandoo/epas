<?php
/**
 * API Proxy for Ambilet Marketplace
 *
 * This proxy handles all API requests to the core Tixello API,
 * keeping the API key hidden from the client.
 *
 * Endpoints:
 * - GET /api/proxy.php?action=search&q=query
 * - GET /api/proxy.php?action=events&category=slug
 * - GET /api/proxy.php?action=event&slug=event-slug
 * - GET /api/proxy.php?action=venues
 * - GET /api/proxy.php?action=artists
 * - POST /api/proxy.php?action=cart (body: cart data)
 */

// Prevent direct execution without action
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config (contains API_KEY and API_BASE_URL)
require_once dirname(__DIR__) . '/includes/config.php';

// Rate limiting (simple IP-based)
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$rateKey = 'rate_' . md5($ip);
$rateLimit = 60; // requests per minute
$rateWindow = 60; // seconds

if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'start' => time()];
}

if (time() - $_SESSION[$rateKey]['start'] > $rateWindow) {
    $_SESSION[$rateKey] = ['count' => 0, 'start' => time()];
}

$_SESSION[$rateKey]['count']++;

if ($_SESSION[$rateKey]['count'] > $rateLimit) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action parameter']);
    exit;
}

// Build API endpoint based on action
$endpoint = '';
$method = 'GET';
$body = null;

switch ($action) {
    case 'search':
        $query = $_GET['q'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 10), 50);
        if (strlen($query) < 2) {
            echo json_encode(['events' => [], 'artists' => [], 'locations' => []]);
            exit;
        }
        $endpoint = '/search?' . http_build_query(['q' => $query, 'limit' => $limit]);
        break;

    case 'events':
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['city'])) $params['city'] = $_GET['city'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 50);
        if (isset($_GET['sort'])) $params['sort'] = $_GET['sort'];
        $endpoint = '/events?' . http_build_query($params);
        break;

    case 'event':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event slug']);
            exit;
        }
        $endpoint = '/events/' . urlencode($slug);
        break;

    case 'venues':
        $params = [];
        if (isset($_GET['city'])) $params['city'] = $_GET['city'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        $endpoint = '/venues?' . http_build_query($params);
        break;

    case 'venue':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing venue slug']);
            exit;
        }
        $endpoint = '/venues/' . urlencode($slug);
        break;

    case 'artists':
        $params = [];
        if (isset($_GET['genre'])) $params['genre'] = $_GET['genre'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        $endpoint = '/artists?' . http_build_query($params);
        break;

    case 'artist':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing artist slug']);
            exit;
        }
        $endpoint = '/artists/' . urlencode($slug);
        break;

    case 'categories':
        $endpoint = '/categories';
        break;

    case 'cities':
        $endpoint = '/cities';
        break;

    case 'cart':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/cart';
        break;

    case 'checkout':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/checkout';
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        exit;
}

// Demo mode - return mock data
if (DEMO_MODE) {
    echo json_encode(getMockData($action, $_GET));
    exit;
}

// Make the actual API request
$url = API_BASE_URL . $endpoint;

$context = stream_context_create([
    'http' => [
        'method' => $method,
        'header' => [
            'Content-Type: application/json',
            'X-API-Key: ' . API_KEY,
            'Accept: application/json',
            'User-Agent: Ambilet Marketplace/1.0'
        ],
        'content' => $body,
        'timeout' => 30,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($url, false, $context);

// Get HTTP status from response headers
$statusCode = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
            $statusCode = (int)$matches[1];
        }
    }
}

http_response_code($statusCode);

if ($response === false) {
    echo json_encode(['error' => 'API request failed']);
} else {
    echo $response;
}

/**
 * Generate mock data for demo mode
 */
function getMockData($action, $params) {
    switch ($action) {
        case 'search':
            $query = $params['q'] ?? '';
            return [
                'events' => [
                    ['name' => 'Concert ' . ucfirst($query), 'slug' => 'concert-' . strtolower($query), 'date' => '15 Ian 2025', 'venue' => 'Sala Palatului', 'price' => 150],
                    ['name' => 'Festival ' . ucfirst($query), 'slug' => 'festival-' . strtolower($query), 'date' => '20 Feb 2025', 'venue' => 'Cluj-Napoca', 'price' => 299]
                ],
                'artists' => [
                    ['name' => ucfirst($query), 'slug' => strtolower($query), 'genre' => 'Pop/Rock']
                ],
                'locations' => [
                    ['name' => 'Arena ' . ucfirst($query), 'slug' => 'arena-' . strtolower($query), 'city' => 'București']
                ]
            ];

        case 'events':
            return [
                'data' => [
                    ['id' => 1, 'name' => 'Concert Demo', 'slug' => 'concert-demo', 'date' => '2025-01-15', 'venue' => 'Sala Palatului', 'price' => 150],
                    ['id' => 2, 'name' => 'Festival Demo', 'slug' => 'festival-demo', 'date' => '2025-02-20', 'venue' => 'Cluj-Napoca', 'price' => 299]
                ],
                'meta' => ['total' => 2, 'page' => 1, 'per_page' => 12]
            ];

        case 'categories':
            return [
                ['slug' => 'concerte', 'name' => 'Concerte', 'count' => 156],
                ['slug' => 'festivaluri', 'name' => 'Festivaluri', 'count' => 24],
                ['slug' => 'teatru', 'name' => 'Teatru', 'count' => 89],
                ['slug' => 'stand-up', 'name' => 'Stand-up', 'count' => 67],
                ['slug' => 'sport', 'name' => 'Sport', 'count' => 34]
            ];

        case 'cities':
            return [
                ['slug' => 'bucuresti', 'name' => 'București', 'count' => 238],
                ['slug' => 'cluj', 'name' => 'Cluj-Napoca', 'count' => 94],
                ['slug' => 'timisoara', 'name' => 'Timișoara', 'count' => 67],
                ['slug' => 'iasi', 'name' => 'Iași', 'count' => 52]
            ];

        default:
            return ['message' => 'Demo mode - no data for this action'];
    }
}
