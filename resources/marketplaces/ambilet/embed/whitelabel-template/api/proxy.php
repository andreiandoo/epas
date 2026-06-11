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
$url = API_BASE_URL . $endpoint;
if (!empty($params)) $url .= '?' . http_build_query($params);

// Make request
$ch = curl_init();
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'X-API-Key: ' . API_KEY,
];

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
