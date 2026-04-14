<?php
/**
 * EPASTracking Proxy
 *
 * Proxies browser-side tracking calls to core API, keeping the API key server-side.
 * Routes: POST /api/tracking.php/track, POST /api/tracking.php/batch
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';

// Extract path info from REQUEST_URI, falling back to PATH_INFO
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (empty($pathInfo)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Remove query string
    $requestPath = strtok($requestUri, '?');
    // Extract path after the script name
    if ($scriptName && str_starts_with($requestPath, $scriptName)) {
        $pathInfo = substr($requestPath, strlen($scriptName));
    }
}

$coreEndpoint = match ($pathInfo) {
    '/track' => '/marketplace-tracking/track',
    '/batch' => '/marketplace-tracking/batch',
    default => null,
};

if (!$coreEndpoint) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'debug_path' => $pathInfo]);
    exit;
}

$body = file_get_contents('php://input');
$apiUrl = rtrim(API_BASE_URL, '/');
// marketplace-tracking routes are at the same level as marketplace-client
// API_BASE_URL = https://core.tixello.com/api/marketplace-client
// We need: https://core.tixello.com/api/marketplace-tracking/...
$baseUrl = preg_replace('#/marketplace-client$#', '', $apiUrl);
$url = $baseUrl . $coreEndpoint;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . API_KEY,
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'Tracking service unavailable']);
    exit;
}

http_response_code($httpCode);
echo $response;
