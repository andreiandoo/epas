<?php
/**
 * Proxy browser → core.tixello.com pentru API-ul public de SEATING.
 *
 * Evită CORS (apel server-side) și menține o sesiune stabilă de hold-uri
 * (X-Session-Id) per vizitator, printr-un cookie propriu.
 *
 * Acțiuni:
 *   GET  ?action=seating&event=ID              → geometrie + price tiers + event_seating_id
 *   GET  ?action=seats&event=ID[&section=&status=]  → statusuri locuri
 *   POST ?action=hold      body: {event_seating_id, seat_uids:[]}   → blochează 10 min
 *   POST ?action=release   body: {event_seating_id, seat_uids:[]}   → eliberează
 *   GET  ?action=holds&event_seating_id=ID     → hold-urile sesiunii
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Sesiune stabilă pentru hold-uri (X-Session-Id) ---
$cookieName = 'teatru_seat_sid';
if (empty($_COOKIE[$cookieName])) {
    $sid = bin2hex(random_bytes(16));
    setcookie($cookieName, $sid, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    $sid = preg_replace('/[^a-f0-9]/', '', $_COOKIE[$cookieName]);
}

$action = $_GET['action'] ?? '';

// Rutare acțiune → (method, path upstream)
$method = 'GET';
$path   = null;
$query  = [];
$body   = null;

switch ($action) {
    case 'seating':
        $eventId = (int) ($_GET['event'] ?? 0);
        if (!$eventId) { http_response_code(400); echo json_encode(['error' => 'event lipsă']); exit; }
        $path = "/public/events/{$eventId}/seating";
        break;

    case 'seats':
        $eventId = (int) ($_GET['event'] ?? 0);
        if (!$eventId) { http_response_code(400); echo json_encode(['error' => 'event lipsă']); exit; }
        $path = "/public/events/{$eventId}/seats";
        foreach (['section', 'status', 'limit'] as $p) {
            if (isset($_GET[$p])) { $query[$p] = $_GET[$p]; }
        }
        break;

    case 'hold':
        $method = 'POST';
        $path   = '/public/seats/hold';
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'release':
        $method = 'DELETE';
        $path   = '/public/seats/hold';
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'holds':
        $path  = '/public/seats/holds';
        $query = ['event_seating_id' => $_GET['event_seating_id'] ?? ''];
        break;

    case 'checkout':
        $method = 'POST';
        $path   = '/tenant-client/demo-checkout';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'login':
        $method = 'POST';
        $path   = '/tenant-client/auth/login';
        $query  = ['tenant' => TENANT_ID, 'hostname' => ($_SERVER['HTTP_HOST'] ?? '')];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'register':
        $method = 'POST';
        $path   = '/tenant-client/auth/register';
        $query  = ['tenant' => TENANT_ID, 'hostname' => ($_SERVER['HTTP_HOST'] ?? '')];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'me':
        $path   = '/tenant-client/auth/me';
        $query  = ['tenant' => TENANT_ID, 'hostname' => ($_SERVER['HTTP_HOST'] ?? '')];
        break;

    case 'logout':
        $method = 'POST';
        $path   = '/tenant-client/auth/logout';
        $query  = ['tenant' => TENANT_ID, 'hostname' => ($_SERVER['HTTP_HOST'] ?? '')];
        $body   = [];
        break;

    case 'subscribe':
        $method = 'POST';
        $path   = '/tenant-client/subscribe';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'my-subscriptions':
        $path   = '/tenant-client/my-subscriptions';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'gami-balance':
        $path   = '/tenant-client/gamification-balance';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-stats':
        $path   = '/tenant-client/account/stats';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-orders':
        $path   = '/tenant-client/account/orders';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-order':
        $path   = '/tenant-client/account/orders/' . (int) ($_GET['id'] ?? 0);
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-tickets':
        $path   = '/tenant-client/account/tickets';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-profile':
        $method = 'POST';
        $path   = '/tenant-client/account/profile';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-password':
        $method = 'POST';
        $path   = '/tenant-client/account/password';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-delete':
        $method = 'POST';
        $path   = '/tenant-client/account/delete';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'redeem':
        $method = 'POST';
        $subId  = (int) ($_GET['sub'] ?? 0);
        $path   = "/tenant-client/subscriptions/{$subId}/redeem";
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'acțiune necunoscută']);
        exit;
}

$url = API_BASE . $path;
if (!empty($query)) { $url .= '?' . http_build_query($query); }

// --- Forward cURL ---
$ch = curl_init($url);
$headers = [
    'Accept: application/json',
    'X-Session-Id: ' . $sid,
];
if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
// Forward token-ul de autentificare al clientului (pt. /me, /logout)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authHeader) { $headers[] = 'Authorization: ' . $authHeader; }

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_TIMEOUT        => API_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => 'teatru-skin-proxy/1.0',
]);
if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
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
