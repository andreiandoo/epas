<?php
/**
 * Whitelabel API Proxy — relays requests to Tixello Core API.
 * Keeps the API key server-side.
 */
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? '';
if (!$endpoint) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing endpoint']);
    exit;
}

// Determine method
$method = $_SERVER['REQUEST_METHOD'];
$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $body = file_get_contents('php://input');
}

// Build URL — pass query params (except 'endpoint')
$params = $_GET;
unset($params['endpoint']);

// Marketplace-tracking endpoints live at /api/marketplace-tracking/* (NOT under
// the marketplace-client prefix), so strip the /marketplace-client suffix from
// API_BASE_URL when forwarding to them. Same X-API-Key auth applies to both.
if (preg_match('#^/marketplace-tracking/(track|batch|pixel)$#', $endpoint)) {
    $apiRoot = preg_replace('#/marketplace-client/?$#', '', API_BASE_URL);
    $url = $apiRoot . $endpoint;
} else {
    $url = API_BASE_URL . $endpoint;
}
if (!empty($params)) $url .= '?' . http_build_query($params);

// Make request
$ch = curl_init();
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
];

// Visitor signals for the server-side conversions (Meta CAPI / TikTok). Without them
// core sees this server's IP for every buyer, no browser and no Meta cookies.
$wlPublicIp = function ($ip) {
    $ip = trim((string) $ip);
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : '';
};
$wlVisitorIp = $wlPublicIp($_SERVER['REMOTE_ADDR'] ?? '');
if ($wlVisitorIp === '') {
    // Behind a local reverse proxy / CDN: the first public address it forwarded.
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $wlKey) {
        $wlVisitorIp = $wlPublicIp(explode(',', (string) ($_SERVER[$wlKey] ?? ''))[0]);
        if ($wlVisitorIp !== '') {
            break;
        }
    }
}
if ($wlVisitorIp !== '') {
    $headers[] = 'X-Visitor-IP: ' . $wlVisitorIp;
}
$wlVisitorUa = trim(preg_replace('/[\r\n]+/', ' ', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
if ($wlVisitorUa !== '') {
    $headers[] = 'X-Visitor-UA: ' . substr($wlVisitorUa, 0, 500);
}
// Same visitor id the tracking events carry (sent by assets/js/app.js).
$wlVisitorId = (string) ($_SERVER['HTTP_X_VISITOR_ID'] ?? '');
if (preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $wlVisitorId)) {
    $headers[] = 'X-Visitor-ID: ' . $wlVisitorId;
}
// _fbp / _fbc exactly as in the cookie (no lowercasing, no trimming). When there is
// no _fbc cookie, app.js may send one built from the stored fbclid.
$wlMetaCookie = function ($value) {
    $value = (string) $value;
    return preg_match('/^fb\.\d+\.\d+\.[A-Za-z0-9_.\-]{1,480}$/', $value) ? $value : '';
};
$wlFbp = $wlMetaCookie($_COOKIE['_fbp'] ?? '');
$wlFbc = $wlMetaCookie($_COOKIE['_fbc'] ?? '');
if ($wlFbc === '') {
    $wlFbc = $wlMetaCookie($_SERVER['HTTP_X_VISITOR_FBC'] ?? '');
}
if ($wlFbp !== '') {
    $headers[] = 'X-Visitor-Fbp: ' . $wlFbp;
}
if ($wlFbc !== '') {
    $headers[] = 'X-Visitor-Fbc: ' . $wlFbc;
}

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);

if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
} elseif ($method === 'PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
