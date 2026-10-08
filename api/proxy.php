<?php
/**
 * Proxy browser → core.tixello.com pentru acțiunile care nu pot fi făcute
 * server-side la randare: checkout, autentificare client, contul clientului.
 *
 * Evită CORS (apel server-side) și scopează fiecare cerere pe tenant prin
 * ?hostname=TENANT_HOST (domeniul înregistrat în core).
 *
 * Acțiuni:
 *   POST ?action=checkout   body: {event_id, customer:{…}, items:[{ticket_type_id, quantity}], success_url, cancel_url}
 *   POST ?action=login      body: {email, password}
 *   POST ?action=register   body: {first_name, last_name, email, password, …}
 *   GET  ?action=me         (Authorization: Bearer)
 *   POST ?action=logout     (Authorization: Bearer)
 *   GET  ?action=acc-orders (Authorization: Bearer)
 *   GET  ?action=acc-tickets(Authorization: Bearer)
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? '';

// Rutare acțiune → (method, path upstream)
$routes = [
    'checkout'    => ['POST', '/tenant-client/demo-checkout'],
    'login'       => ['POST', '/tenant-client/auth/login'],
    'register'    => ['POST', '/tenant-client/auth/register'],
    'me'          => ['GET',  '/tenant-client/auth/me'],
    'logout'      => ['POST', '/tenant-client/auth/logout'],
    'acc-orders'  => ['GET',  '/tenant-client/account/orders'],
    'acc-tickets' => ['GET',  '/tenant-client/account/tickets'],
];

if (!isset($routes[$action])) {
    http_response_code(400);
    echo json_encode(['error' => 'acțiune necunoscută']);
    exit;
}

[$method, $path] = $routes[$action];
$body = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : null;

$url = API_BASE . $path . '?' . http_build_query(['hostname' => TENANT_HOST]);

// --- Forward cURL ---
$headers = ['Accept: application/json'];
if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
// Forward token-ul de autentificare al clientului
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authHeader) { $headers[] = 'Authorization: ' . $authHeader; }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_TIMEOUT        => API_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 8,
    // Forțează IPv4 (vezi includes/api.php) — evită stall-ul de ~5s pe IPv6.
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => 'competitie-skin-proxy/1.0',
]);
if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?: new stdClass()));
}

$raw    = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'upstream', 'detail' => $err]);
    exit;
}

http_response_code($status ?: 502);
echo $raw;
