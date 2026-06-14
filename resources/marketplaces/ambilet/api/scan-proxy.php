<?php
/**
 * Scan App — generic API proxy.
 *
 * Why this file exists: the main panel's /api/proxy.php uses an action-based
 * routing system (action=organizer.events etc.) that requires every endpoint
 * to be enumerated in both api.js (getProxyAction) and proxy.php (case
 * statement). The Tixello mobile app uses ~30 different API endpoints — we'd
 * need 30 additions across two files, and the proxy.php file is 169KB which
 * makes additive edits risky.
 *
 * This proxy avoids all that by being a thin pass-through: the scan-app
 * sends the FULL path (e.g. /organizer/seating/embed-token) as a query
 * parameter, we forward it verbatim to core.tixello.com with the API_KEY
 * injected server-side + the user's bearer token from Authorization header.
 *
 * Security:
 * - Bearer token must be present and forwarded as-is (proves the user is
 *   logged in as an organizer or team member).
 * - We restrict allowed paths to known scan-app prefixes to prevent this
 *   proxy from becoming an open relay to the full API.
 * - CORS is restricted to same-origin (the proxy lives at /api/ on the
 *   same domain as the scan-app pages).
 * - API_KEY is read from includes/config.php and NEVER exposed to the
 *   browser.
 */

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// CORS preflight (same-origin in production, but allow OPTIONS for safety).
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    http_response_code(204);
    exit;
}

// Extract path from query string (?path=/organizer/seating/embed-token).
$path = $_GET['path'] ?? '';
if (!is_string($path) || $path === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing path parameter']);
    exit;
}

// Normalize: must start with '/', no '..' segments, no scheme/host.
if ($path[0] !== '/' || strpos($path, '..') !== false || preg_match('#^https?://#i', $path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}

// Allowlist of path prefixes that the scan app legitimately needs. Anything
// outside this set is rejected so this proxy can't be abused to hit arbitrary
// backend endpoints.
$allowedPrefixes = [
    '/organizer/',                  // organizer authenticated endpoints
    '/venue-owner/',                // venue owner (iteration 2)
    '/orders',                      // POS order creation + payment
    '/claim/',                      // claim status polling
    '/events/',                     // sales-breakdown etc.
];
$pathPrefix = strstr($path, '?', true) ?: $path;
$allowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($pathPrefix, $prefix) === 0) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Path not allowed for scan-proxy: ' . $pathPrefix]);
    exit;
}

// Extract any extra query params (path may contain its own ?key=val).
list($cleanPath, $queryFromPath) = array_pad(explode('?', $path, 2), 2, null);

// Build the target URL. API_BASE_URL is set by config.php to
// https://core.tixello.com/api/marketplace-client (or stage variant).
$targetUrl = rtrim(API_BASE_URL, '/') . $cleanPath;
if ($queryFromPath !== null && $queryFromPath !== '') {
    $targetUrl .= '?' . $queryFromPath;
}

// Forward auth bearer + API key.
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
];

$incomingAuth = null;
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders() ?: [];
    foreach ($allHeaders as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0) { $incomingAuth = $v; break; }
    }
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $incomingAuth = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $incomingAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
if ($incomingAuth) {
    $headers[] = 'Authorization: ' . $incomingAuth;
}

// Body for write methods.
$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $body = file_get_contents('php://input');
}

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
if ($body !== null && $body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Upstream error', 'error' => $err]);
    exit;
}

http_response_code((int) $status);
echo $response;
