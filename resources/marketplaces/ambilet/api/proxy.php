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
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// Build headers
$headers = [
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
    'Accept: application/json',
    'User-Agent: Ambilet Marketplace/1.0'
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

/**
 * Generate mock data for demo mode
 */
function getMockData($action, $params) {
    // Demo customer data (stored in session for persistence)
    $demoCustomer = [
        'id' => 1,
        'email' => 'demo@ambilet.ro',
        'first_name' => 'Demo',
        'last_name' => 'User',
        'full_name' => 'Demo User',
        'phone' => '+40722123456',
        'city' => 'București',
        'country' => 'RO',
        'locale' => 'ro',
        'accepts_marketing' => true,
        'email_verified' => true,
        'is_guest' => false,
        'stats' => [
            'total_orders' => 5,
            'total_spent' => 750.00,
        ],
        'created_at' => '2024-01-15T10:30:00+00:00',
    ];

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

        // ==================== CUSTOMER AUTH (Demo Mode) ====================

        case 'customer.register':
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $_SESSION['demo_customer'] = array_merge($demoCustomer, [
                'email' => $body['email'] ?? $demoCustomer['email'],
                'first_name' => $body['first_name'] ?? $demoCustomer['first_name'],
                'last_name' => $body['last_name'] ?? $demoCustomer['last_name'],
                'full_name' => trim(($body['first_name'] ?? '') . ' ' . ($body['last_name'] ?? '')),
                'phone' => $body['phone'] ?? null,
            ]);
            $_SESSION['demo_customer_token'] = 'demo_token_' . time();
            return [
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data' => [
                    'customer' => $_SESSION['demo_customer'],
                    'token' => $_SESSION['demo_customer_token'],
                    'requires_verification' => true,
                ]
            ];

        case 'customer.login':
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            // Accept any email/password combination in demo mode
            $_SESSION['demo_customer'] = $demoCustomer;
            if (!empty($body['email'])) {
                $_SESSION['demo_customer']['email'] = $body['email'];
            }
            $_SESSION['demo_customer_token'] = 'demo_token_' . time();
            return [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'customer' => $_SESSION['demo_customer'],
                    'token' => $_SESSION['demo_customer_token'],
                ]
            ];

        case 'customer.logout':
            unset($_SESSION['demo_customer'], $_SESSION['demo_customer_token']);
            return [
                'success' => true,
                'message' => 'Logged out successfully',
                'data' => null
            ];

        case 'customer.me':
            if (empty($_SESSION['demo_customer_token'])) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Unauthorized'];
            }
            return [
                'success' => true,
                'data' => ['customer' => $_SESSION['demo_customer'] ?? $demoCustomer]
            ];

        case 'customer.profile':
            if (empty($_SESSION['demo_customer_token'])) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Unauthorized'];
            }
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $_SESSION['demo_customer'] = array_merge($_SESSION['demo_customer'] ?? $demoCustomer, $body);
            return [
                'success' => true,
                'message' => 'Profile updated',
                'data' => ['customer' => $_SESSION['demo_customer']]
            ];

        case 'customer.settings':
            if (empty($_SESSION['demo_customer_token'])) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Unauthorized'];
            }
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $_SESSION['demo_customer_settings'] = $body;
            return [
                'success' => true,
                'message' => 'Settings updated',
                'data' => $body
            ];

        case 'customer.orders':
            if (empty($_SESSION['demo_customer_token'])) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Unauthorized'];
            }
            return [
                'success' => true,
                'data' => [
                    [
                        'id' => 1,
                        'order_number' => 'AMB-2024-0001',
                        'status' => 'completed',
                        'total' => 150.00,
                        'currency' => 'RON',
                        'tickets_count' => 2,
                        'event' => [
                            'id' => 1,
                            'name' => 'Concert Demo',
                            'slug' => 'concert-demo',
                            'date' => '2025-02-15T19:00:00+00:00',
                            'venue' => 'Sala Palatului',
                            'city' => 'București',
                            'image' => '/assets/images/demo-event.jpg',
                            'is_upcoming' => true,
                        ],
                        'created_at' => '2024-12-20T10:30:00+00:00',
                    ]
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1]
            ];

        case 'customer.tickets':
            if (empty($_SESSION['demo_customer_token'])) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Unauthorized'];
            }
            return [
                'success' => true,
                'data' => [
                    'tickets' => [
                        [
                            'id' => 1,
                            'barcode' => 'AMB-TKT-001',
                            'type' => 'VIP',
                            'status' => 'valid',
                            'order_number' => 'AMB-2024-0001',
                            'event' => [
                                'id' => 1,
                                'name' => 'Concert Demo',
                                'slug' => 'concert-demo',
                                'date' => '2025-02-15T19:00:00+00:00',
                                'venue' => 'Sala Palatului',
                                'city' => 'București',
                                'image' => '/assets/images/demo-event.jpg',
                            ]
                        ]
                    ]
                ]
            ];

        case 'customer.stats':
            return [
                'success' => true,
                'data' => [
                    'active_tickets' => 2,
                    'attended_events' => 5,
                    'points' => 150,
                    'favorites' => 3
                ]
            ];

        case 'customer.forgot-password':
        case 'customer.reset-password':
        case 'customer.verify-email':
        case 'customer.resend-verification':
            return [
                'success' => true,
                'message' => 'Demo mode - action simulated',
                'data' => null
            ];

        default:
            return ['success' => false, 'message' => 'Demo mode - no data for this action'];
    }
}
