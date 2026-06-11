<?php
/**
 * AmBilet.ro API Proxy
 *
 * This script securely proxies requests to the Core API,
 * keeping the API key hidden on the server side.
 *
 * Usage:
 *   GET  /api/proxy.php?endpoint=events
 *   GET  /api/proxy.php?endpoint=events/123
 *   POST /api/proxy.php?endpoint=orders (with JSON body)
 */

// Load configuration (adjust path based on your setup)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__, 2) . '/config/config.php'; // Outside webroot
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration not found']);
    exit;
}

$config = require $configPath;

// CORS handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['allowed_origins'])) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Simple rate limiting using file-based storage
function checkRateLimit(string $ip, int $limit): bool
{
    $cacheDir = sys_get_temp_dir() . '/ambilet_rate_limit';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $file = $cacheDir . '/' . md5($ip) . '.json';
    $now = time();
    $windowStart = $now - 60;

    $requests = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? [];
        $requests = array_filter($data, fn($t) => $t > $windowStart);
    }

    if (count($requests) >= $limit) {
        return false;
    }

    $requests[] = $now;
    file_put_contents($file, json_encode($requests));
    return true;
}

// Check rate limit
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, $config['rate_limit'])) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Get the endpoint from query string
$endpoint = $_GET['endpoint'] ?? '';
$endpoint = ltrim($endpoint, '/');

// Whitelist allowed endpoints
$allowedEndpoints = [
    'config',
    'tenants',
    'events',
    'orders',
];

// Check if endpoint is allowed
$endpointBase = explode('/', $endpoint)[0];
if (!in_array($endpointBase, $allowedEndpoints)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint']);
    exit;
}

// Build the Core API URL
$apiUrl = rtrim($config['core_api_url'], '/') . '/' . $endpoint;

// Forward query parameters (except 'endpoint')
$queryParams = $_GET;
unset($queryParams['endpoint']);
if (!empty($queryParams)) {
    $apiUrl .= '?' . http_build_query($queryParams);
}

// Prepare cURL request
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $config['api_key'],
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Handle cURL errors
if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to API']);
    exit;
}

// Return the response
http_response_code($httpCode);
echo $response;
