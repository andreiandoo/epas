<?php
/**
 * API Proxy for Ambilet Marketplace
 *
 * This proxy handles all API requests to the core Tixello API,
 * keeping the API key hidden from the client.
 *
 * Endpoints:
 * Public:
 * - GET /api/proxy.php?action=search&q=query
 * - GET /api/proxy.php?action=events&category=slug
 * - GET /api/proxy.php?action=event&slug=event-slug
 * - GET /api/proxy.php?action=venues
 * - GET /api/proxy.php?action=artists
 * - POST /api/proxy.php?action=cart (body: cart data)
 *
 * Customer Auth:
 * - POST /api/proxy.php?action=customer.login
 * - POST /api/proxy.php?action=customer.register
 * - POST /api/proxy.php?action=customer.logout
 * - GET /api/proxy.php?action=customer.me
 * - PUT /api/proxy.php?action=customer.profile
 * - PUT /api/proxy.php?action=customer.settings
 * - GET /api/proxy.php?action=customer.orders
 * - GET /api/proxy.php?action=customer.tickets
 * - GET /api/proxy.php?action=customer.stats
 */

// Prevent direct execution without action
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-ID');

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
$requiresAuth = false;

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
        if (isset($_GET['region'])) $params['region'] = $_GET['region'];
        if (isset($_GET['genre'])) $params['genre'] = $_GET['genre'];
        if (isset($_GET['artist'])) $params['artist'] = $_GET['artist'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['date'])) $params['date_filter'] = $_GET['date'];
        if (isset($_GET['date_filter'])) $params['date_filter'] = $_GET['date_filter'];
        if (isset($_GET['min_price'])) $params['min_price'] = (int)$_GET['min_price'];
        if (isset($_GET['max_price'])) $params['max_price'] = (int)$_GET['max_price'];
        if (isset($_GET['featured'])) $params['featured'] = $_GET['featured'];
        if (isset($_GET['featured_only'])) $params['featured_only'] = $_GET['featured_only'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
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

    case 'event.track-view':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event slug']);
            exit;
        }
        $method = 'POST';
        $body = file_get_contents('php://input'); // Forward UTM parameters and tracking data
        $endpoint = '/events/' . urlencode($slug) . '/track-view';
        break;

    case 'event.toggle-interest':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event slug']);
            exit;
        }
        $method = 'POST';
        $endpoint = '/events/' . urlencode($slug) . '/toggle-interest';
        $requiresAuth = true; // Forward auth token if available
        break;

    case 'event.check-interest':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event slug']);
            exit;
        }
        $endpoint = '/events/' . urlencode($slug) . '/check-interest';
        $requiresAuth = true; // Forward auth token if available
        break;

    case 'events.featured':
        $params = [];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['require_image'])) $params['require_image'] = $_GET['require_image'];
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 50);
        $endpoint = '/events/featured?' . http_build_query($params);
        break;

    case 'events.cities':
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['genre'])) $params['genre'] = $_GET['genre'];
        if (isset($_GET['city'])) $params['city'] = $_GET['city'];
        $endpoint = '/events/cities' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'venues':
        $params = [];
        if (isset($_GET['city'])) $params['city'] = $_GET['city'];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['sort'])) $params['sort'] = $_GET['sort'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/venues' . ($params ? '?' . http_build_query($params) : '');
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

    // ==================== ARTISTS ====================

    case 'artists':
        $params = [];
        if (isset($_GET['genre'])) $params['genre'] = $_GET['genre'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['city'])) $params['city'] = $_GET['city'];
        if (isset($_GET['letter'])) $params['letter'] = $_GET['letter'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['featured'])) $params['featured'] = $_GET['featured'];
        if (isset($_GET['with_events'])) $params['with_events'] = $_GET['with_events'];
        if (isset($_GET['sort'])) $params['sort'] = $_GET['sort'];
        if (isset($_GET['order'])) $params['order'] = $_GET['order'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        // Support 'limit' as alias for 'per_page'
        if (isset($_GET['limit']) && !isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['limit'], 50);
        $endpoint = '/artists' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'artists.featured':
        $params = [];
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 20);
        $endpoint = '/artists/featured' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'artists.trending':
        $params = [];
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 20);
        $endpoint = '/artists/trending' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'artists.genre-counts':
        $endpoint = '/artists/genre-counts';
        break;

    case 'artists.alphabet':
        $endpoint = '/artists/alphabet';
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

    case 'artist.events':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing artist slug']);
            exit;
        }
        $params = [];
        if (isset($_GET['filter'])) $params['filter'] = $_GET['filter']; // 'upcoming' or 'past'
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/artists/' . urlencode($slug) . '/events' . ($params ? '?' . http_build_query($params) : '');
        break;
    case "artist.toggle-favorite":
        $slug = $_GET["slug"] ?? "";
        if (!$slug) {
            http_response_code(400);
            echo json_encode(["error" => "Missing artist slug"]);
            exit;
        }
        $method = "POST";
        $endpoint = "/artists/" . urlencode($slug) . "/toggle-favorite";
        $requiresAuth = true;
        break;

    case "artist.check-favorite":
        $slug = $_GET["slug"] ?? "";
        if (!$slug) {
            http_response_code(400);
            echo json_encode(["error" => "Missing artist slug"]);
            exit;
        }
        $endpoint = "/artists/" . urlencode($slug) . "/check-favorite";
        $requiresAuth = true;
        break;

    case "venue.toggle-favorite":
        $slug = $_GET["slug"] ?? "";
        if (!$slug) {
            http_response_code(400);
            echo json_encode(["error" => "Missing venue slug"]);
            exit;
        }
        $method = "POST";
        $endpoint = "/venues/" . urlencode($slug) . "/toggle-favorite";
        $requiresAuth = true;
        break;

    case "venue.check-favorite":
        $slug = $_GET["slug"] ?? "";
        if (!$slug) {
            http_response_code(400);
            echo json_encode(["error" => "Missing venue slug"]);
            exit;
        }
        $endpoint = "/venues/" . urlencode($slug) . "/check-favorite";
        $requiresAuth = true;
        break;

    case "customer.favorites.artists":
        $endpoint = "/customer/favorites/artists";
        $requiresAuth = true;
        break;

    case "customer.favorites.venues":
        $endpoint = "/customer/favorites/venues";
        $requiresAuth = true;
        break;

    case "customer.favorites.summary":
        $endpoint = "/customer/favorites/summary";
        $requiresAuth = true;
        break;

    case 'categories':
        // Use marketplace-events/categories which has the correct implementation
        $endpoint = '/marketplace-events/categories';
        break;

    case 'event-categories':
        $params = [];
        if (isset($_GET['featured'])) $params['featured'] = $_GET['featured'];
        $endpoint = '/event-categories' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'event-genres':
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        $endpoint = '/event-genres' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'subgenres':
        $params = [];
        if (isset($_GET['genre'])) $params['parent'] = $_GET['genre'];
        $endpoint = '/event-genres' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'event-category':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing category slug']);
            exit;
        }
        $endpoint = '/event-categories/' . urlencode($slug);
        break;

    case 'cities':
        $params = [];
        if (isset($_GET['genre'])) $params['genre'] = $_GET['genre'];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        $endpoint = '/marketplace-events/cities' . ($params ? '?' . http_build_query($params) : '');
        break;

    // ==================== LOCATIONS ====================

    case 'locations.stats':
        $endpoint = '/locations/stats';
        break;

    case 'locations.cities.featured':
        $endpoint = '/locations/cities/featured';
        break;

    case 'locations.cities.alphabet':
        $endpoint = '/locations/cities/alphabet';
        break;

    case 'locations.cities':
        $params = [];
        if (isset($_GET['letter'])) $params['letter'] = $_GET['letter'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['sort'])) $params['sort'] = $_GET['sort'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/locations/cities' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'locations.regions':
        $endpoint = '/locations/regions';
        break;

    case 'locations.region':
        $identifier = $_GET['id'] ?? $_GET['slug'] ?? '';
        if (!$identifier) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing region identifier']);
            exit;
        }
        $endpoint = '/locations/regions/' . urlencode($identifier);
        break;

    case 'locations.city':
        $identifier = $_GET['id'] ?? $_GET['slug'] ?? '';
        if (!$identifier) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing city identifier']);
            exit;
        }
        $endpoint = '/locations/cities/' . urlencode($identifier);
        break;

    // ==================== VENUES ====================

    case 'venues.featured':
        $endpoint = '/venues/featured';
        break;

    case 'venue-categories':
        $endpoint = '/venue-categories';
        break;

    case 'venue-category':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing venue category slug']);
            exit;
        }
        $endpoint = '/venue-categories/' . urlencode($slug);
        break;

    case 'cart':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/cart';
        break;

    case 'checkout':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/checkout';
        break;

    case 'orders.pay':
        $orderId = $_GET['id'] ?? '';
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order ID']);
            exit;
        }
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/orders/' . urlencode($orderId) . '/pay';
        break;

    case 'orders.status':
        $orderId = $_GET['id'] ?? '';
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order ID']);
            exit;
        }
        $method = 'GET';
        $endpoint = '/orders/' . urlencode($orderId) . '/payment-status';
        break;

    // ==================== CUSTOMER AUTH ====================

    case 'customer.register':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/register';
        break;

    case 'customer.login':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/login';
        break;

    case 'customer.logout':
        $method = 'POST';
        $endpoint = '/customer/logout';
        $requiresAuth = true;
        break;

    case 'customer.me':
        $method = 'GET';
        $endpoint = '/customer/me';
        $requiresAuth = true;
        break;

    case 'customer.profile':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/profile';
        $requiresAuth = true;
        break;

    case 'customer.password':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/password';
        $requiresAuth = true;
        break;

    case 'customer.account':
        $method = 'DELETE';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/account';
        $requiresAuth = true;
        break;

    case 'customer.settings':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/settings';
        $requiresAuth = true;
        break;

    case 'customer.orders':
        $method = 'GET';
        $params = [];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        if (isset($_GET['upcoming'])) $params['upcoming'] = $_GET['upcoming'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/orders' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.order':
        $method = 'GET';
        $orderId = $_GET['id'] ?? '';
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order ID']);
            exit;
        }
        $endpoint = '/customer/orders/' . urlencode($orderId);
        $requiresAuth = true;
        break;

    case 'customer.tickets':
        $method = 'GET';
        $endpoint = '/customer/tickets';
        $requiresAuth = true;
        break;

    case 'customer.stats':
        $method = 'GET';
        $endpoint = '/customer/stats';
        $requiresAuth = true;
        break;

    case 'customer.forgot-password':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/forgot-password';
        break;

    case 'customer.reset-password':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/reset-password';
        break;

    case 'customer.verify-email':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/verify-email';
        break;

    case 'customer.resend-verification':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/resend-verification';
        break;

    // ==================== CUSTOMER STATS & DASHBOARD ====================

    case 'customer.stats.dashboard':
        $method = 'GET';
        $endpoint = '/customer/stats';
        $requiresAuth = true;
        break;

    case 'customer.upcoming-events':
        $method = 'GET';
        $params = [];
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 20);
        $endpoint = '/customer/stats/upcoming-events' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER TICKETS ====================

    case 'customer.tickets.all':
        $method = 'GET';
        $params = [];
        if (isset($_GET['filter'])) $params['filter'] = $_GET['filter']; // upcoming, past, all
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/tickets/all' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.ticket':
        $ticketId = $_GET['id'] ?? '';
        if (!$ticketId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing ticket ID']);
            exit;
        }
        $method = 'GET';
        $endpoint = '/customer/tickets/' . urlencode($ticketId);
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER REVIEWS ====================

    case 'customer.reviews':
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/reviews' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.reviews.to-write':
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/reviews/events-to-review' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.review.store':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/reviews';
        $requiresAuth = true;
        break;

    case 'customer.review.show':
        $reviewId = $_GET['id'] ?? '';
        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing review ID']);
            exit;
        }
        $method = 'GET';
        $endpoint = '/customer/reviews/' . urlencode($reviewId);
        $requiresAuth = true;
        break;

    case 'customer.review.update':
        $reviewId = $_GET['id'] ?? '';
        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing review ID']);
            exit;
        }
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/reviews/' . urlencode($reviewId);
        $requiresAuth = true;
        break;

    case 'customer.review.delete':
        $reviewId = $_GET['id'] ?? '';
        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing review ID']);
            exit;
        }
        $method = 'DELETE';
        $endpoint = '/customer/reviews/' . urlencode($reviewId);
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER WATCHLIST ====================

    case 'customer.watchlist':
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/watchlist' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.watchlist.add':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/watchlist';
        $requiresAuth = true;
        break;

    case 'customer.watchlist.update':
        $watchlistId = $_GET['id'] ?? '';
        if (!$watchlistId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing watchlist ID']);
            exit;
        }
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/watchlist/' . urlencode($watchlistId);
        $requiresAuth = true;
        break;

    case 'customer.watchlist.remove':
        $watchlistId = $_GET['id'] ?? '';
        if (!$watchlistId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing watchlist ID']);
            exit;
        }
        $method = 'DELETE';
        $endpoint = '/customer/watchlist/' . urlencode($watchlistId);
        $requiresAuth = true;
        break;

    case 'customer.watchlist.check':
        $method = 'GET';
        $params = [];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        $endpoint = '/customer/watchlist/check' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER REWARDS & GAMIFICATION ====================

    case 'customer.rewards':
        $method = 'GET';
        $endpoint = '/customer/rewards';
        $requiresAuth = true;
        break;

    case 'customer.rewards.history':
        $method = 'GET';
        $params = [];
        if (isset($_GET['type'])) $params['type'] = $_GET['type']; // earned, spent, all
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/rewards/history' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.badges':
        $method = 'GET';
        $endpoint = '/customer/rewards/badges';
        $requiresAuth = true;
        break;

    case 'customer.rewards.available':
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/rewards/available' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.rewards.redeem':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/rewards/redeem';
        $requiresAuth = true;
        break;

    case 'customer.rewards.redemptions':
        $method = 'GET';
        $params = [];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/rewards/redemptions' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER NOTIFICATIONS ====================

    case 'customer.notifications':
        $method = 'GET';
        $params = [];
        if (isset($_GET['unread_only'])) $params['unread_only'] = $_GET['unread_only'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/customer/notifications' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'customer.notifications.unread-count':
        $method = 'GET';
        $endpoint = '/customer/notifications/unread-count';
        $requiresAuth = true;
        break;

    case 'customer.notifications.read':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/notifications/mark-read';
        $requiresAuth = true;
        break;

    case 'customer.notification.delete':
        $notificationId = $_GET['id'] ?? '';
        if (!$notificationId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing notification ID']);
            exit;
        }
        $method = 'DELETE';
        $endpoint = '/customer/notifications/' . urlencode($notificationId);
        $requiresAuth = true;
        break;

    case 'customer.notifications.settings':
        $method = 'GET';
        $endpoint = '/customer/notifications/settings';
        $requiresAuth = true;
        break;

    case 'customer.notifications.settings.update':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/notifications/settings';
        $requiresAuth = true;
        break;

    // ==================== CUSTOMER REFERRALS ====================

    case 'customer.referrals':
        $method = 'GET';
        $endpoint = '/customer/referrals';
        $requiresAuth = true;
        break;

    case 'customer.referrals.regenerate':
        $method = 'POST';
        $endpoint = '/customer/referrals/regenerate-code';
        $requiresAuth = true;
        break;

    case 'customer.referrals.track-click':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/referrals/track-click';
        break;

    case 'customer.referrals.leaderboard':
        $method = 'GET';
        $params = [];
        if (isset($_GET['period'])) $params['period'] = $_GET['period']; // week, month, all
        if (isset($_GET['limit'])) $params['limit'] = min((int)$_GET['limit'], 100);
        $endpoint = '/customer/referrals/leaderboard' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'customer.referrals.claim-rewards':
        $method = 'POST';
        $endpoint = '/customer/referrals/claim-rewards';
        $requiresAuth = true;
        break;

    case 'customer.referrals.validate':
        $method = 'GET';
        $params = [];
        if (isset($_GET['code'])) $params['code'] = $_GET['code'];
        $endpoint = '/customer/referrals/validate' . ($params ? '?' . http_build_query($params) : '');
        break;

    // ==================== ORGANIZER AUTH ====================

    case 'organizer.register':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/register';
        break;

    case 'organizer.login':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/login';
        break;

    case 'organizer.logout':
        $method = 'POST';
        $endpoint = '/organizer/logout';
        $requiresAuth = true;
        break;

    case 'organizer.me':
        $method = 'GET';
        $endpoint = '/organizer/me';
        $requiresAuth = true;
        break;

    case 'organizer.profile':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/profile';
        $requiresAuth = true;
        break;

    case 'organizer.password':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/password';
        $requiresAuth = true;
        break;

    case 'organizer.forgot-password':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/forgot-password';
        break;

    case 'organizer.reset-password':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/reset-password';
        break;

    case 'organizer.verify-email':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/verify-email';
        break;

    case 'organizer.resend-verification':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/resend-verification';
        break;

    case 'organizer.payout-details':
        $method = 'PUT';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/payout-details';
        $requiresAuth = true;
        break;

    case 'organizer.verify-cui':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/verify-cui';
        $requiresAuth = true;
        break;

    case 'organizer.contract':
        $method = 'GET';
        $endpoint = '/organizer/contract';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER DASHBOARD ====================

    case 'organizer.dashboard':
        $method = 'GET';
        $endpoint = '/organizer/dashboard';
        $requiresAuth = true;
        break;

    case 'organizer.dashboard.timeline':
        $method = 'GET';
        $params = [];
        if (isset($_GET['period'])) $params['period'] = $_GET['period'];
        if (isset($_GET['start_date'])) $params['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date'])) $params['end_date'] = $_GET['end_date'];
        $endpoint = '/organizer/dashboard/timeline' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER EVENTS ====================

    case 'organizer.events':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        } else {
            $params = [];
            if (isset($_GET['status'])) $params['status'] = $_GET['status'];
            if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
            if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        }
        $endpoint = '/organizer/events' . (!empty($params) ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.event':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        // DELETE method is supported for draft/rejected events
        $endpoint = '/organizer/events/' . urlencode($eventId);
        $requiresAuth = true;
        break;

    case 'organizer.event.submit':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'POST';
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/submit';
        $requiresAuth = true;
        break;

    case 'organizer.event.cancel':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'POST';
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/cancel';
        $requiresAuth = true;
        break;

    case 'organizer.event.status':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'PATCH';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/status';
        $requiresAuth = true;
        break;

    case 'organizer.event-categories':
        $method = 'GET';
        $endpoint = '/organizer/event-categories';
        $requiresAuth = true;
        break;

    case 'organizer.event-genres':
        $method = 'GET';
        $params = [];
        if (isset($_GET['type_ids'])) {
            $params['type_ids'] = is_array($_GET['type_ids']) ? $_GET['type_ids'] : explode(',', $_GET['type_ids']);
        }
        $endpoint = '/organizer/event-genres' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.venues':
        $method = 'GET';
        $params = [];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        $endpoint = '/organizer/venues' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.artists':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        } else {
            $params = [];
            if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        }
        $endpoint = '/organizer/artists' . (!empty($params) ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.event.analytics':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'GET';
        $params = [];
        if (isset($_GET['period'])) $params['period'] = $_GET['period'];
        if (isset($_GET['start_date'])) $params['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date'])) $params['end_date'] = $_GET['end_date'];
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/analytics' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.event.goals':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/goals';
        $requiresAuth = true;
        break;

    case 'organizer.event.goal':
        $eventId = $_GET['event_id'] ?? '';
        $goalId = $_GET['goal_id'] ?? '';
        if (!$eventId || !$goalId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id or goal_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/goals/' . urlencode($goalId);
        $requiresAuth = true;
        break;

    case 'organizer.event.milestones':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/milestones';
        $requiresAuth = true;
        break;

    case 'organizer.event.milestone':
        $eventId = $_GET['event_id'] ?? '';
        $milestoneId = $_GET['milestone_id'] ?? '';
        if (!$eventId || !$milestoneId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id or milestone_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/milestones/' . urlencode($milestoneId);
        $requiresAuth = true;
        break;

    case 'organizer.event.participants':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 100);
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/participants' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.event.participants.export':
        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id parameter']);
            exit;
        }
        $method = 'GET';
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/participants/export';
        $requiresAuth = true;
        break;

    case 'organizer.event.checkin':
        $eventId = $_GET['event_id'] ?? '';
        $barcode = $_GET['barcode'] ?? '';
        if (!$eventId || !$barcode) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id or barcode parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = '/organizer/events/' . urlencode($eventId) . '/check-in/' . urlencode($barcode);
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER ORDERS ====================

    case 'organizer.orders':
        $method = 'GET';
        $params = [];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/organizer/orders' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER FINANCE ====================

    case 'organizer.balance':
        $method = 'GET';
        $endpoint = '/organizer/balance';
        $requiresAuth = true;
        break;

    case 'organizer.finance':
        $method = 'GET';
        $endpoint = '/organizer/finance';
        $requiresAuth = true;
        break;

    case 'organizer.transactions':
        $method = 'GET';
        $params = [];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/organizer/transactions' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.payouts':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        } else {
            $params = [];
            if (isset($_GET['status'])) $params['status'] = $_GET['status'];
            if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
            if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        }
        $endpoint = '/organizer/payouts' . (!empty($params) ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.payout':
        $payoutId = $_GET['payout_id'] ?? '';
        if (!$payoutId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing payout_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD']; // DELETE for canceling
        $endpoint = '/organizer/payouts/' . urlencode($payoutId);
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER PROMO CODES ====================

    case 'organizer.promo-codes':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        } else {
            $params = [];
            if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
            if (isset($_GET['status'])) $params['status'] = $_GET['status'];
            if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
            if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        }
        $endpoint = '/organizer/promo-codes' . (!empty($params) ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.promo-code':
        $codeId = $_GET['code_id'] ?? '';
        if (!$codeId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing code_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD']; // PUT for update, DELETE for delete
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/promo-codes/' . urlencode($codeId);
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER TEAM ====================

    case 'organizer.team':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/team';
        $requiresAuth = true;
        break;

    case 'organizer.team.member':
        $memberId = $_GET['member_id'] ?? '';
        if (!$memberId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing member_id parameter']);
            exit;
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/team/' . urlencode($memberId);
        $requiresAuth = true;
        break;

    case 'organizer.team.invite':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/team/invite';
        $requiresAuth = true;
        break;

    case 'organizer.team.update':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/team/update';
        $requiresAuth = true;
        break;

    case 'organizer.team.remove':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/team/remove';
        $requiresAuth = true;
        break;

    case 'organizer.team.resend-invite':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/team/resend-invite';
        $requiresAuth = true;
        break;

    case 'organizer.team.resend-all-invites':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/team/resend-all-invites';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER API KEY ====================

    case 'organizer.api-key':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/api-key';
        $requiresAuth = true;
        break;

    case 'organizer.api-key.regenerate':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/api-key/regenerate';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER BANK ACCOUNTS ====================

    case 'organizer.bank-accounts':
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/bank-accounts';
        $requiresAuth = true;
        break;

    case 'organizer.bank-account.delete':
        $accountId = $_GET['account_id'] ?? '';
        if (!$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing account_id parameter']);
            exit;
        }
        $method = 'DELETE';
        $endpoint = '/organizer/bank-accounts/' . urlencode($accountId);
        $requiresAuth = true;
        break;

    case 'organizer.bank-account.primary':
        $accountId = $_GET['account_id'] ?? '';
        if (!$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing account_id parameter']);
            exit;
        }
        $method = 'POST';
        $endpoint = '/organizer/bank-accounts/' . urlencode($accountId) . '/primary';
        $requiresAuth = true;
        break;

    case 'organizer.webhook':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/webhook';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER PARTICIPANTS (ALL EVENTS) ====================

    case 'organizer.participants':
        $method = 'GET';
        $params = [];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        if (isset($_GET['checked_in'])) $params['checked_in'] = $_GET['checked_in'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 100);
        $endpoint = '/organizer/participants' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.participants.checkin':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/participants/checkin';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER DOCUMENTS ====================

    case 'organizer.documents':
        $method = 'GET';
        $params = [];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        $endpoint = '/organizer/documents' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.documents.events':
        $method = 'GET';
        $endpoint = '/organizer/documents/events';
        $requiresAuth = true;
        break;

    case 'organizer.documents.for-event':
        $method = 'GET';
        $eventId = $_GET['event_id'] ?? '';
        $endpoint = '/organizer/documents/event/' . $eventId;
        $requiresAuth = true;
        break;

    case 'organizer.documents.generate':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/documents/generate';
        $requiresAuth = true;
        break;

    case 'organizer.documents.download':
        $method = 'GET';
        $documentId = $_GET['document_id'] ?? '';
        $endpoint = '/organizer/documents/' . $documentId . '/download';
        $requiresAuth = true;
        break;

    case 'organizer.documents.view':
        $method = 'GET';
        $documentId = $_GET['document_id'] ?? '';
        $endpoint = '/organizer/documents/' . $documentId . '/view';
        $requiresAuth = true;
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        exit;
}

// Make the actual API request
$url = API_BASE_URL . $endpoint;

// Build headers
$headers = [
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
    'Accept: application/json',
    'User-Agent: Ambilet Marketplace/1.0',
    'X-Session-ID: ' . session_id()  // Pass session ID for cart/checkout functionality
];

// Forward Authorization header for authenticated requests
if ($requiresAuth) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($authHeader) {
        $headers[] = 'Authorization: ' . $authHeader;
    }
}

$context = stream_context_create([
    'http' => [
        'method' => $method,
        'header' => $headers,
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
