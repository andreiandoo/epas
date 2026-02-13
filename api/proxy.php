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
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-ID, X-Auto-Refresh');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config (contains API_KEY and API_BASE_URL)
require_once dirname(__DIR__) . '/includes/config.php';

// ==================== FILE-BASED CACHE SYSTEM ====================
class ApiCache {
    private static $cacheDir;
    private static $enabled = true;

    // Cache TTL in seconds by action pattern
    private static $ttlMap = [
        // Long cache (1 hour) - static/rarely changing content
        'categories' => 3600,
        'event-categories' => 3600,
        'event-genres' => 3600,
        'venue-categories' => 3600,
        'artists.genre-counts' => 3600,
        'artists.alphabet' => 3600,
        'locations.stats' => 3600,
        'locations.regions' => 3600,
        'locations.cities.alphabet' => 3600,

        // Medium cache (10 minutes) - content that changes occasionally
        'events.featured' => 600,
        'events.cities' => 600,
        'venues.featured' => 600,
        'artists.featured' => 600,
        'artists.trending' => 600,
        'locations.cities.featured' => 600,
        'cities' => 600,

        // Short cache (3 minutes) - frequently changing but still cacheable
        'events' => 180,
        'venues' => 180,
        'artists' => 180,
        'organizers' => 180,
        'organizer' => 180,

        // Very short cache (10 seconds) - single item views need quick updates
        'event' => 10,
        'venue' => 10,
        'artist' => 10,

        // Very short cache (1 minute) - search results
        'search' => 60,
    ];

    public static function init() {
        self::$cacheDir = dirname(__DIR__) . '/cache/api';
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        // Disable cache if directory not writable
        if (!is_writable(self::$cacheDir)) {
            self::$enabled = false;
        }
    }

    public static function getCacheKey($action, $params) {
        // Create unique cache key from action and all GET params
        $key = $action . '_' . md5(serialize($params));
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }

    public static function getTtl($action) {
        // Check for exact match first
        if (isset(self::$ttlMap[$action])) {
            return self::$ttlMap[$action];
        }
        // Check for prefix match (e.g., 'events' matches 'events.featured')
        foreach (self::$ttlMap as $pattern => $ttl) {
            if (strpos($action, $pattern) === 0) {
                return $ttl;
            }
        }
        return 0; // No caching for unknown actions
    }

    public static function get($action, $params) {
        if (!self::$enabled) return null;

        $ttl = self::getTtl($action);
        if ($ttl === 0) return null;

        $cacheKey = self::getCacheKey($action, $params);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.json';

        if (!file_exists($cacheFile)) return null;

        $mtime = filemtime($cacheFile);
        if (time() - $mtime > $ttl) {
            @unlink($cacheFile); // Expired
            return null;
        }

        $data = @file_get_contents($cacheFile);
        if ($data === false) return null;

        $cached = json_decode($data, true);
        if (!$cached || !isset($cached['response'])) return null;

        return $cached;
    }

    public static function set($action, $params, $response, $statusCode) {
        if (!self::$enabled) return;

        $ttl = self::getTtl($action);
        if ($ttl === 0) return;

        // Only cache successful responses
        if ($statusCode < 200 || $statusCode >= 300) return;

        $cacheKey = self::getCacheKey($action, $params);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.json';

        $data = json_encode([
            'response' => $response,
            'statusCode' => $statusCode,
            'cached_at' => time()
        ]);

        @file_put_contents($cacheFile, $data, LOCK_EX);
    }

    public static function isCacheable($action, $method, $requiresAuth) {
        // Only cache GET requests
        if ($method !== 'GET') return false;

        // Don't cache authenticated requests
        if ($requiresAuth) return false;

        // Check if we have a TTL for this action
        return self::getTtl($action) > 0;
    }
}

ApiCache::init();

// ==================== SHARE LINK LOCAL STORAGE ====================

function shareLinksFile() {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/share-links.json';
    if (!file_exists($file)) {
        file_put_contents($file, '{}', LOCK_EX);
    }
    return $file;
}

function loadShareLinks() {
    $file = shareLinksFile();
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveShareLinks($links) {
    $file = shareLinksFile();
    file_put_contents($file, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function generateShareCode($length = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// ==================== RATE LIMITING ====================

function getRateLimitDir() {
    $dir = dirname(__DIR__) . '/data/rate-limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Simple file-based rate limiter.
 * Returns true if the request is allowed, false if rate limit exceeded.
 */
function checkRateLimit($key, $maxRequests = 30, $windowSeconds = 60) {
    $dir = getRateLimitDir();
    $file = $dir . '/' . md5($key) . '.json';

    $now = time();
    $data = [];

    if (file_exists($file)) {
        $data = @json_decode(@file_get_contents($file), true) ?: [];
    }

    // Remove expired entries
    $data = array_filter($data, fn($ts) => $ts > ($now - $windowSeconds));

    if (count($data) >= $maxRequests) {
        return false;
    }

    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

/**
 * Brute-force protection for password attempts.
 * Returns true if attempt is allowed, false if locked out.
 * Locks out for $lockoutSeconds after $maxAttempts failures within $windowSeconds.
 */
function checkBruteForce($code, $maxAttempts = 5, $windowSeconds = 300, $lockoutSeconds = 600) {
    $dir = getRateLimitDir();
    $file = $dir . '/bf_' . md5($code) . '.json';

    $now = time();
    $data = ['attempts' => [], 'locked_until' => 0];

    if (file_exists($file)) {
        $data = @json_decode(@file_get_contents($file), true) ?: $data;
    }

    // Check if currently locked out
    if (($data['locked_until'] ?? 0) > $now) {
        return false;
    }

    // Clean expired attempts
    $data['attempts'] = array_filter($data['attempts'] ?? [], fn($ts) => $ts > ($now - $windowSeconds));

    if (count($data['attempts']) >= $maxAttempts) {
        // Trigger lockout
        $data['locked_until'] = $now + $lockoutSeconds;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    return true;
}

/**
 * Record a failed password attempt.
 */
function recordFailedAttempt($code, $windowSeconds = 300) {
    $dir = getRateLimitDir();
    $file = $dir . '/bf_' . md5($code) . '.json';

    $now = time();
    $data = ['attempts' => [], 'locked_until' => 0];

    if (file_exists($file)) {
        $data = @json_decode(@file_get_contents($file), true) ?: $data;
    }

    $data['attempts'] = array_filter($data['attempts'] ?? [], fn($ts) => $ts > ($now - $windowSeconds));
    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Clear failed attempts after successful auth.
 */
function clearFailedAttempts($code) {
    $dir = getRateLimitDir();
    $file = $dir . '/bf_' . md5($code) . '.json';
    if (file_exists($file)) {
        @unlink($file);
    }
}

function authenticateOrganizer() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    // Validate token against core API
    $url = API_BASE_URL . '/organizer/me';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'X-API-Key: ' . API_KEY,
                'Accept: application/json',
                'Authorization: ' . $authHeader
            ]),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication failed']);
        exit;
    }
    $data = json_decode($response, true);
    $orgId = $data['data']['id'] ?? $data['data']['organizer']['id'] ?? $data['id'] ?? null;
    if (!$orgId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    return (int)$orgId;
}

function fetchEventsForShareLink($eventIds, $storedTicketData = []) {
    $results = [];
    foreach ($eventIds as $eventId) {
        $id = (int)$eventId;
        if ($id <= 0) continue;

        // Fetch from core API using marketplace API key
        $url = API_BASE_URL . '/events/' . $id;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-API-Key: ' . API_KEY,
                    'Accept: application/json'
                ]),
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) continue;

        $responseData = json_decode($response, true);
        if (!$responseData || empty($responseData['data'])) continue;

        $data = $responseData['data'];

        // API returns: { success: true, data: { event: {...}, ticket_types: [...], venue: {...}, ... } }
        $event = $data['event'] ?? $data;
        $venue = $data['venue'] ?? [];
        $ticketTypes = $data['ticket_types'] ?? $event['ticket_types'] ?? [];

        if (!$event || (!isset($event['name']) && !isset($event['title']))) continue;

        // Get stored ticket totals (cached at share link creation time)
        $storedEvent = $storedTicketData[$id] ?? $storedTicketData[(string)$id] ?? [];
        $storedTT = [];
        foreach (($storedEvent['ticket_types'] ?? []) as $stt) {
            $stt_id = $stt['id'] ?? null;
            if ($stt_id) $storedTT[$stt_id] = $stt;
        }

        // Extract only safe public fields
        $results[] = [
            'id' => $event['id'] ?? $id,
            'title' => $event['name'] ?? $event['title'] ?? '',
            'venue_name' => $venue['name'] ?? $event['venue_name'] ?? '',
            'city' => $venue['city'] ?? $event['venue_city'] ?? $event['city'] ?? '',
            'start_date' => $event['starts_at'] ?? $event['start_date'] ?? $event['date'] ?? '',
            'start_time' => $event['doors_open_at'] ?? $event['start_time'] ?? '',
            'end_date' => $event['ends_at'] ?? $event['end_date'] ?? '',
            'end_time' => $event['end_time'] ?? '',
            'status' => $event['status'] ?? '',
            'ticket_types' => array_map(function($tt) use ($storedTT) {
                $ttId = $tt['id'] ?? null;
                $stored = $storedTT[$ttId] ?? [];

                $available = (int)($tt['available'] ?? 0);
                // Use stored totals (from organizer endpoint at creation time)
                $total = (int)($stored['total'] ?? $tt['quantity'] ?? $tt['total'] ?? $tt['quota_total'] ?? 0);
                $sold = 0;

                if ($total > 0) {
                    // Calculate sold from total - available (fresh data)
                    $sold = max(0, $total - $available);
                } else {
                    // Fallback: try direct sold fields
                    $sold = (int)($tt['quantity_sold'] ?? $tt['sold'] ?? $tt['tickets_sold'] ?? 0);
                    if ($sold > 0) {
                        $total = $available + $sold;
                    }
                }

                return [
                    'id' => $ttId,
                    'name' => $tt['name'] ?? $tt['title'] ?? '',
                    'total' => $total,
                    'sold' => $sold,
                    'available' => $available,
                    'price' => (float)($tt['price'] ?? 0),
                ];
            }, $ticketTypes),
            'tickets_total' => 0,
            'tickets_sold' => 0,
        ];

        // Calculate event totals from ticket types
        $lastIdx = count($results) - 1;
        $results[$lastIdx]['tickets_total'] = array_sum(array_column($results[$lastIdx]['ticket_types'], 'total'));
        $results[$lastIdx]['tickets_sold'] = array_sum(array_column($results[$lastIdx]['ticket_types'], 'sold'));
    }
    return $results;
}

function fetchTicketTotalsWithAuth($eventIds, $authHeader) {
    $result = [];
    foreach ($eventIds as $eventId) {
        $id = (int)$eventId;
        if ($id <= 0) continue;

        // Use the organizer endpoint which returns quantity + quantity_sold
        $url = API_BASE_URL . '/organizer/events/' . $id;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-API-Key: ' . API_KEY,
                    'Accept: application/json',
                    'Authorization: ' . $authHeader
                ]),
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) continue;

        $responseData = json_decode($response, true);
        $data = $responseData['data'] ?? $responseData;
        $event = $data['event'] ?? $data;
        $ticketTypes = $data['ticket_types'] ?? $event['ticket_types'] ?? [];

        $result[$id] = [
            'ticket_types' => array_map(function($tt) {
                return [
                    'id' => $tt['id'] ?? null,
                    'name' => $tt['name'] ?? '',
                    'total' => (int)($tt['quantity'] ?? $tt['quota_total'] ?? $tt['total'] ?? 0),
                ];
            }, $ticketTypes),
        ];
    }
    return $result;
}

/**
 * Fetch participants for events using organizer auth.
 * Returns array keyed by event ID with participant lists.
 */
function fetchParticipantsWithAuth($eventIds, $authHeader) {
    $result = [];
    foreach ($eventIds as $eventId) {
        $id = (int)$eventId;
        if ($id <= 0) continue;

        $url = API_BASE_URL . '/organizer/events/' . $id . '/participants?per_page=100';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-API-Key: ' . API_KEY,
                    'Accept: application/json',
                    'Authorization: ' . $authHeader
                ]),
                'timeout' => 15,
                'ignore_errors' => true
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) continue;

        $responseData = json_decode($response, true);
        $participants = $responseData['data']['participants'] ?? $responseData['data'] ?? [];

        if (!is_array($participants)) continue;

        $result[$id] = array_map(function($p) {
            // API returns customer data nested under 'customer' key
            $customer = $p['customer'] ?? [];
            return [
                'name' => $customer['name'] ?? $p['name'] ?? '',
                'phone' => $customer['phone'] ?? $p['phone'] ?? '',
                'ticket_type' => $p['ticket_type'] ?? '',
                'seat_label' => $p['seat_label'] ?? null,
                'checked_in' => !empty($p['checked_in_at']),
                'order_date' => $p['purchased_at'] ?? $p['order_date'] ?? '',
            ];
        }, $participants);
    }
    return $result;
}

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

    // ==================== ORGANIZERS (PUBLIC) ====================

    case 'organizers':
        $params = [];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['verified'])) $params['verified'] = $_GET['verified'];
        if (isset($_GET['with_events'])) $params['with_events'] = $_GET['with_events'];
        if (isset($_GET['sort'])) $params['sort'] = $_GET['sort'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/marketplace-events/organizers' . ($params ? '?' . http_build_query($params) : '');
        break;

    case 'organizer':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing organizer slug']);
            exit;
        }
        $endpoint = '/marketplace-events/organizers/' . urlencode($slug);
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
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'DELETE') {
            $endpoint = '/customer/cart';
        } else {
            $endpoint = '/customer/cart';
        }
        break;

    case 'cart.items.add':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/cart/items';
        break;

    case 'cart.items.add-with-seats':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/cart/items/with-seats';
        break;

    case 'cart.items.manage':
        $method = $_SERVER['REQUEST_METHOD'];
        $body = file_get_contents('php://input');
        $itemKey = $_GET['item_key'] ?? '';
        $endpoint = '/customer/cart/items/' . urlencode($itemKey);
        break;

    case 'cart.seats.release':
        $method = 'DELETE';
        $body = file_get_contents('php://input');
        $endpoint = '/customer/cart/seats';
        break;

    case 'cart.promo-code':
        $method = $_SERVER['REQUEST_METHOD'];
        $body = file_get_contents('php://input');
        $endpoint = '/customer/cart/promo-code';
        break;

    case 'checkout.features':
        $endpoint = '/checkout/features';
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
        // Call ANAF public API directly (no auth required)
        $inputBody = file_get_contents('php://input');
        $inputData = json_decode($inputBody, true);

        $cui = $inputData['cui'] ?? '';
        if (!$cui) {
            http_response_code(400);
            echo json_encode(['error' => 'CUI is required']);
            exit;
        }

        // Clean CUI - remove 'RO' prefix if present and any non-numeric characters
        $cui = preg_replace('/[^0-9]/', '', preg_replace('/^RO/i', '', $cui));

        if (empty($cui)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid CUI format']);
            exit;
        }

        // Prepare ANAF API request using cURL for better reliability
        // ANAF API endpoint v9
        $anafUrl = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';
        $anafBody = json_encode([
            ['cui' => (int)$cui, 'data' => date('Y-m-d')]
        ]);

        $ch = curl_init($anafUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $anafBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $anafResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($anafResponse === false || !empty($curlError)) {
            http_response_code(503);
            echo json_encode(['error' => 'ANAF service unavailable: ' . $curlError]);
            exit;
        }

        $anafData = json_decode($anafResponse, true);

        // Check for ANAF API errors
        if (!$anafData) {
            http_response_code(502);
            echo json_encode(['error' => 'Invalid response from ANAF API', 'debug' => substr($anafResponse, 0, 500)]);
            exit;
        }

        // ANAF returns 'found' array for found companies and 'notFound' for not found
        if (!isset($anafData['found']) || empty($anafData['found'])) {
            // Check if it's in notFound
            if (isset($anafData['notFound']) && !empty($anafData['notFound'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Compania nu a fost gasita in baza de date ANAF. Verifica CUI-ul.']);
                exit;
            }
            // Return more info about what went wrong
            http_response_code(404);
            echo json_encode([
                'error' => 'Compania nu a fost gasita sau CUI invalid',
                'anaf_response' => $anafData
            ]);
            exit;
        }

        // Extract company data from ANAF response
        $company = $anafData['found'][0];
        $dateGen = $company['date_generale'] ?? [];
        $adresa = $company['adresa_sediu_social'] ?? [];
        $tva = $company['inregistrare_scop_Tva'] ?? [];

        // Build normalized response (matching frontend expected format)
        $result = [
            'success' => true,
            'data' => [
                'cui' => $dateGen['cui'] ?? $cui,
                'company_name' => $dateGen['denumire'] ?? '',
                'address' => trim(sprintf(
                    '%s %s %s',
                    $adresa['sdenumire_Strada'] ?? '',
                    $adresa['snumar_Strada'] ?? '',
                    $adresa['sdetalii_Adresa'] ?? ''
                )),
                'city' => $adresa['sdenumire_Localitate'] ?? '',
                'county' => $adresa['sdenumire_Judet'] ?? '',
                'zip' => $adresa['scod_Postal'] ?? '',
                'reg_com' => $dateGen['nrRegCom'] ?? '',
                'vat_payer' => !empty($tva['scpTVA']),
                'status' => $dateGen['stare_inregistrare'] ?? '',
                'phone' => $dateGen['telefon'] ?? '',
                'fax' => $dateGen['fax'] ?? ''
            ]
        ];

        http_response_code(200);
        echo json_encode($result);
        exit;

    case 'organizer.contract':
        $method = 'GET';
        $endpoint = '/organizer/contract';
        $requiresAuth = true;
        break;

    case 'organizer.contract.download':
        $method = 'GET';
        $endpoint = '/organizer/contract/download';
        $requiresAuth = true;
        break;

    case 'organizer.documents.upload':
        $method = 'POST';
        $endpoint = '/organizer/documents/upload';
        $requiresAuth = true;
        $isMultipart = true;
        break;

    // ==================== ORGANIZER NOTIFICATIONS ====================

    case 'organizer.notifications':
        $method = 'GET';
        $params = [];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        if (isset($_GET['read'])) $params['read'] = $_GET['read'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        $endpoint = '/organizer/notifications' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.notifications.unread-count':
        $method = 'GET';
        $endpoint = '/organizer/notifications/unread-count';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.mark-read':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/notifications/mark-read';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.mark-all-read':
        $method = 'POST';
        $endpoint = '/organizer/notifications/mark-all-read';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.types':
        $method = 'GET';
        $endpoint = '/organizer/notifications/types';
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

    case 'organizer.dashboard.sales-timeline':
        $method = 'GET';
        $params = [];
        if (isset($_GET['from_date'])) $params['from_date'] = $_GET['from_date'];
        if (isset($_GET['to_date'])) $params['to_date'] = $_GET['to_date'];
        if (isset($_GET['group_by'])) $params['group_by'] = $_GET['group_by'];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        $endpoint = '/organizer/dashboard/sales-timeline' . ($params ? '?' . http_build_query($params) : '');
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
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        if (isset($_GET['from_date'])) $params['from_date'] = $_GET['from_date'];
        if (isset($_GET['to_date'])) $params['to_date'] = $_GET['to_date'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 100);
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

    // ==================== ORGANIZER NOTIFICATIONS ====================

    case 'organizer.notifications':
        $method = 'GET';
        $params = [];
        if (isset($_GET['unread_only'])) $params['unread_only'] = $_GET['unread_only'];
        if (isset($_GET['read'])) $params['read'] = $_GET['read'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 50);
        $endpoint = '/organizer/notifications' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.notifications.unread-count':
        $method = 'GET';
        $endpoint = '/organizer/notifications/unread-count';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.recent':
        $method = 'GET';
        $endpoint = '/organizer/notifications/recent';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.read':
        $notificationId = $_GET['id'] ?? '';
        if (!$notificationId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing notification id']);
            exit;
        }
        $method = 'POST';
        $endpoint = '/organizer/notifications/' . urlencode($notificationId) . '/read';
        $requiresAuth = true;
        break;

    case 'organizer.notifications.read-all':
        $method = 'POST';
        $endpoint = '/organizer/notifications/read-all';
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

    // ==================== ORGANIZER BILLING / INVOICES ====================

    case 'organizer.invoices':
        $method = 'GET';
        $params = [];
        if (isset($_GET['page'])) $params['page'] = (int)$_GET['page'];
        if (isset($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 100);
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        $endpoint = '/organizer/invoices' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.invoice':
        $method = 'GET';
        $invoiceId = $_GET['invoice_id'] ?? '';
        if (!$invoiceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing invoice_id parameter']);
            exit;
        }
        $endpoint = '/organizer/invoices/' . urlencode($invoiceId);
        $requiresAuth = true;
        break;

    case 'organizer.invoice.pdf':
        $method = 'GET';
        $invoiceId = $_GET['invoice_id'] ?? '';
        if (!$invoiceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing invoice_id parameter']);
            exit;
        }
        $endpoint = '/organizer/invoices/' . urlencode($invoiceId) . '/pdf';
        $requiresAuth = true;
        $rawResponse = true;
        break;

    case 'organizer.invoices.export':
        $method = 'GET';
        $params = [];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        $endpoint = '/organizer/invoices/export' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        $rawResponse = true;
        break;

    case 'organizer.billing-info':
        $method = $_SERVER['REQUEST_METHOD'] === 'PUT' ? 'PUT' : 'GET';
        if ($method === 'PUT') {
            $body = file_get_contents('php://input');
        }
        $endpoint = '/organizer/billing-info';
        $requiresAuth = true;
        break;

    case 'organizer.payment-methods':
        $method = 'GET';
        $endpoint = '/organizer/payment-methods';
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

    case 'organizer.documents.regenerate':
        $method = 'POST';
        $documentId = $_GET['document_id'] ?? '';
        if (!$documentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing document_id parameter']);
            exit;
        }
        $endpoint = '/organizer/documents/' . $documentId . '/regenerate';
        $requiresAuth = true;
        break;

    // =============================================
    // Organizer Services (Extra Services)
    // =============================================

    case 'organizer.services.pricing':
        $endpoint = '/organizer/services/pricing';
        $requiresAuth = true;
        break;

    case 'organizer.services.types':
        $endpoint = '/organizer/services/types';
        $requiresAuth = true;
        break;

    case 'organizer.services.stats':
        $endpoint = '/organizer/services/stats';
        $requiresAuth = true;
        break;

    case 'organizer.services.email-audiences':
        $params = [];
        if (isset($_GET['audience_type'])) $params['audience_type'] = $_GET['audience_type'];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        if (isset($_GET['age_min'])) $params['age_min'] = $_GET['age_min'];
        if (isset($_GET['age_max'])) $params['age_max'] = $_GET['age_max'];
        if (isset($_GET['cities'])) $params['cities'] = $_GET['cities'];
        if (isset($_GET['categories'])) $params['categories'] = $_GET['categories'];
        if (isset($_GET['genres'])) $params['genres'] = $_GET['genres'];
        $endpoint = '/organizer/services/email-audiences' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.services':
        $params = [];
        if (isset($_GET['status'])) $params['status'] = $_GET['status'];
        if (isset($_GET['type'])) $params['type'] = $_GET['type'];
        if (isset($_GET['event_id'])) $params['event_id'] = $_GET['event_id'];
        if (isset($_GET['per_page'])) $params['per_page'] = $_GET['per_page'];
        if (isset($_GET['page'])) $params['page'] = $_GET['page'];
        $endpoint = '/organizer/services/orders' . ($params ? '?' . http_build_query($params) : '');
        $requiresAuth = true;
        break;

    case 'organizer.services.order':
        $uuid = $_GET['uuid'] ?? '';
        if (!$uuid) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing uuid parameter']);
            exit;
        }
        $endpoint = '/organizer/services/orders/' . $uuid;
        $requiresAuth = true;
        break;

    case 'organizer.services.create':
        $method = 'POST';
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/services/orders';
        $requiresAuth = true;
        break;

    case 'organizer.services.cancel':
        $method = 'POST';
        $uuid = $_GET['uuid'] ?? '';
        if (!$uuid) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing uuid parameter']);
            exit;
        }
        $endpoint = '/organizer/services/orders/' . $uuid . '/cancel';
        $requiresAuth = true;
        break;

    case 'organizer.services.pay':
        $method = 'POST';
        $uuid = $_GET['uuid'] ?? '';
        if (!$uuid) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing uuid parameter']);
            exit;
        }
        $body = file_get_contents('php://input');
        $endpoint = '/organizer/services/orders/' . $uuid . '/pay';
        $requiresAuth = true;
        break;

    // ==================== ORGANIZER SHARE LINKS (LOCAL) ====================

    case 'organizer.share-links':
        $orgId = authenticateOrganizer();
        $allLinks = loadShareLinks();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Create new share link
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['event_ids'])) {
                http_response_code(400);
                echo json_encode(['error' => 'event_ids is required']);
                exit;
            }

            // Validate event_ids - must be array of positive integers
            $eventIds = [];
            foreach ((array)$input['event_ids'] as $eid) {
                $parsed = (int)$eid;
                if ($parsed > 0) $eventIds[] = $parsed;
            }
            if (empty($eventIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid event_ids']);
                exit;
            }
            if (count($eventIds) > 20) {
                http_response_code(400);
                echo json_encode(['error' => 'Maximum 20 events per share link']);
                exit;
            }

            // Limit share links per organizer
            $orgLinks = array_filter($allLinks, fn($l) => ($l['organizer_id'] ?? 0) === $orgId);
            if (count($orgLinks) >= 50) {
                http_response_code(400);
                echo json_encode(['error' => 'Maximum 50 share links per organizer']);
                exit;
            }

            // Generate unique code
            $code = generateShareCode();
            $attempts = 0;
            while (isset($allLinks[$code]) && $attempts < 10) {
                $code = generateShareCode();
                $attempts++;
            }

            $name = trim(strip_tags($input['name'] ?? ''));
            if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

            // Optional password protection
            $password = trim($input['password'] ?? '');
            $passwordHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;

            // Cache ticket totals using organizer's auth (for sold/total display)
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            $ticketData = $authHeader ? fetchTicketTotalsWithAuth($eventIds, $authHeader) : [];

            // Show participants flag
            $showParticipants = !empty($input['show_participants']);
            $participantsData = [];
            if ($showParticipants && $authHeader) {
                $participantsData = fetchParticipantsWithAuth($eventIds, $authHeader);
            }

            $link = [
                'code' => $code,
                'organizer_id' => $orgId,
                'name' => $name ?: 'Link #' . (count($orgLinks) + 1),
                'event_ids' => array_values(array_unique($eventIds)),
                'is_active' => true,
                'created_at' => date('c'),
                'access_count' => 0,
                'last_accessed_at' => null,
                'password_hash' => $passwordHash,
                'has_password' => !empty($passwordHash),
                'show_participants' => $showParticipants,
                'ticket_data' => $ticketData,
                'participants_data' => $participantsData,
                'ticket_data_updated_at' => date('c'),
            ];

            $allLinks[$code] = $link;
            saveShareLinks($allLinks);

            // Strip sensitive data before returning
            $safeLink = $link;
            unset($safeLink['password_hash'], $safeLink['ticket_data']);
            echo json_encode([
                'success' => true,
                'data' => $safeLink,
                'url' => SITE_URL . '/view/' . $code
            ]);
        } else {
            // List organizer's share links
            $orgLinks = array_values(array_filter($allLinks, fn($l) => ($l['organizer_id'] ?? 0) === $orgId));
            // Sort by created_at descending
            usort($orgLinks, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            // Strip sensitive data before sending to frontend
            $orgLinks = array_map(function($l) {
                unset($l['password_hash'], $l['ticket_data']);
                return $l;
            }, $orgLinks);
            echo json_encode(['success' => true, 'data' => ['links' => $orgLinks]]);
        }
        exit;

    case 'organizer.share-link':
        $orgId = authenticateOrganizer();
        $code = $_GET['code'] ?? '';

        if (!$code || !preg_match('/^[A-Za-z0-9]{6,20}$/', $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid share link code']);
            exit;
        }

        $allLinks = loadShareLinks();

        if (!isset($allLinks[$code]) || ($allLinks[$code]['organizer_id'] ?? 0) !== $orgId) {
            http_response_code(404);
            echo json_encode(['error' => 'Share link not found']);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'DELETE') {
            unset($allLinks[$code]);
            saveShareLinks($allLinks);
            echo json_encode(['success' => true, 'message' => 'Share link deleted']);
        } elseif ($method === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['name'])) {
                $name = trim(strip_tags($input['name']));
                if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);
                $allLinks[$code]['name'] = $name;
            }
            if (isset($input['event_ids'])) {
                $eventIds = [];
                foreach ((array)$input['event_ids'] as $eid) {
                    $parsed = (int)$eid;
                    if ($parsed > 0) $eventIds[] = $parsed;
                }
                if (!empty($eventIds) && count($eventIds) <= 20) {
                    $allLinks[$code]['event_ids'] = array_values(array_unique($eventIds));
                    // Refresh cached ticket totals with new event list
                    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
                    if ($authHeader) {
                        $allLinks[$code]['ticket_data'] = fetchTicketTotalsWithAuth($eventIds, $authHeader);
                        $allLinks[$code]['ticket_data_updated_at'] = date('c');
                    }
                }
            }
            if (isset($input['is_active'])) {
                $allLinks[$code]['is_active'] = (bool)$input['is_active'];
            }
            if (array_key_exists('password', $input)) {
                $pw = trim($input['password'] ?? '');
                if ($pw === '') {
                    // Remove password
                    $allLinks[$code]['password_hash'] = null;
                    $allLinks[$code]['has_password'] = false;
                } else {
                    $allLinks[$code]['password_hash'] = password_hash($pw, PASSWORD_BCRYPT);
                    $allLinks[$code]['has_password'] = true;
                }
            }
            if (isset($input['show_participants'])) {
                $allLinks[$code]['show_participants'] = (bool)$input['show_participants'];
            }
            // Refresh participant + ticket data on demand
            if (!empty($input['refresh_data'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
                $evIds = $allLinks[$code]['event_ids'] ?? [];
                if ($authHeader && !empty($evIds)) {
                    $allLinks[$code]['ticket_data'] = fetchTicketTotalsWithAuth($evIds, $authHeader);
                    if (!empty($allLinks[$code]['show_participants'])) {
                        $allLinks[$code]['participants_data'] = fetchParticipantsWithAuth($evIds, $authHeader);
                    }
                    $allLinks[$code]['ticket_data_updated_at'] = date('c');
                }
            }
            saveShareLinks($allLinks);
            $safeLink = $allLinks[$code];
            unset($safeLink['password_hash'], $safeLink['ticket_data'], $safeLink['participants_data']);
            echo json_encode(['success' => true, 'data' => $safeLink]);
        } else {
            $safeLink = $allLinks[$code];
            unset($safeLink['password_hash'], $safeLink['ticket_data'], $safeLink['participants_data']);
            echo json_encode(['success' => true, 'data' => $safeLink]);
        }
        exit;

    case 'share-link.data':
        // Public endpoint - no auth required
        $code = $_GET['code'] ?? '';

        if (!$code || !preg_match('/^[A-Za-z0-9]{6,20}$/', $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid code']);
            exit;
        }

        // Rate limit: 30 requests per minute per IP
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $clientIp = explode(',', $clientIp)[0]; // Take first IP if multiple
        if (!checkRateLimit('share_' . $clientIp, 30, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests. Please try again later.']);
            exit;
        }

        $allLinks = loadShareLinks();

        if (!isset($allLinks[$code])) {
            http_response_code(404);
            echo json_encode(['error' => 'Link not found']);
            exit;
        }

        $link = $allLinks[$code];

        if (!($link['is_active'] ?? true)) {
            http_response_code(410);
            echo json_encode(['error' => 'This link is no longer active']);
            exit;
        }

        // Password protection check
        if (!empty($link['password_hash'])) {
            // Brute-force protection: max 5 failed attempts per 5 minutes, lockout for 10 minutes
            if (!checkBruteForce($code)) {
                http_response_code(429);
                echo json_encode(['error' => 'too_many_attempts', 'message' => 'Prea multe incercari. Incearca din nou mai tarziu.']);
                exit;
            }

            // Accept password via POST body only (not GET to avoid logging passwords in URLs)
            $inputData = json_decode(file_get_contents('php://input'), true);
            $providedPassword = $inputData['password'] ?? '';

            if (!$providedPassword) {
                http_response_code(401);
                echo json_encode(['error' => 'password_required', 'message' => 'Acest link necesita o parola']);
                exit;
            }

            if (!password_verify($providedPassword, $link['password_hash'])) {
                recordFailedAttempt($code);
                http_response_code(403);
                echo json_encode(['error' => 'invalid_password', 'message' => 'Parola introdusa este incorecta']);
                exit;
            }

            // Successful auth — clear failed attempts
            clearFailedAttempts($code);
        }

        // Update access stats (only on first load, not auto-refresh — check header)
        $isAutoRefresh = ($_SERVER['HTTP_X_AUTO_REFRESH'] ?? '') === '1';
        if (!$isAutoRefresh) {
            $allLinks[$code]['access_count'] = ($allLinks[$code]['access_count'] ?? 0) + 1;
            $allLinks[$code]['last_accessed_at'] = date('c');
            saveShareLinks($allLinks);
        }

        // Fetch event data from core API, merging stored ticket totals
        $storedTicketData = $link['ticket_data'] ?? [];
        $events = fetchEventsForShareLink($link['event_ids'] ?? [], $storedTicketData);

        $responseData = [
            'events' => $events,
            'show_participants' => !empty($link['show_participants']),
            'updated_at' => date('c'),
        ];

        // Include cached participant data if enabled
        if (!empty($link['show_participants']) && !empty($link['participants_data'])) {
            $responseData['participants'] = $link['participants_data'];
        }

        echo json_encode([
            'success' => true,
            'data' => $responseData,
        ]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        exit;
}

// Check cache for GET requests (before making API call)
$useCache = ApiCache::isCacheable($action, $method, $requiresAuth);
if ($useCache) {
    $cached = ApiCache::get($action, $_GET);
    if ($cached) {
        // Return cached response
        http_response_code($cached['statusCode']);
        header('X-Cache: HIT');
        echo $cached['response'];
        exit;
    }
}

// Make the actual API request
$url = API_BASE_URL . $endpoint;

// Build headers
$acceptHeader = (!empty($rawResponse)) ? '*/*' : 'application/json';
$headers = [
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
    'Accept: ' . $acceptHeader,
    'User-Agent: Ambilet Marketplace/1.0',
    'X-Session-ID: ' . session_id()  // Pass session ID for cart/checkout functionality
];

// Forward Authorization header for authenticated requests
if ($requiresAuth) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // For raw responses (PDF/export opened via window.open), accept token as query param
    if (!$authHeader && !empty($rawResponse) && isset($_GET['token'])) {
        $authHeader = 'Bearer ' . $_GET['token'];
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

// Store in cache if cacheable
if ($useCache && $response !== false) {
    ApiCache::set($action, $_GET, $response, $statusCode);
    header('X-Cache: MISS');
}

http_response_code($statusCode);

// For raw responses (PDF, exports), forward Content-Type and Content-Disposition from upstream
if (!empty($rawResponse) && isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^(Content-Type|Content-Disposition):/i', $header)) {
            header($header);
        }
    }
}

if ($response === false) {
    echo json_encode(['error' => 'API request failed']);
} else {
    echo $response;
}
