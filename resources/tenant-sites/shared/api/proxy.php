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


header('Content-Type: application/json; charset=utf-8');

// --- Sesiune stabilă pentru hold-uri (X-Session-Id) ---
$cookieName = 'site_seat_sid';
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
        $query['limit'] = 5000; // harta întreagă dintr-o cerere
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

    // Prețul real al coșului (reducere din cod + taxa de procesare), calculat pe server
    case 'quote':
        $method = 'POST';
        $path   = '/tenant-client/demo-checkout/quote';
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    // Agrement: zilele cu locuri dintr-o lună și intervalele unei zile (LeisureController)
    case 'leisure-availability':
        $path  = '/tenant-client/leisure/availability';
        $query = array_filter([
            'month'       => preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : null,
            'ticket_type' => (int) ($_GET['ticket_type'] ?? 0) ?: null,
        ]);
        break;
    case 'leisure-slots':
        $path  = '/tenant-client/leisure/slots';
        $query = array_filter([
            'date'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? $_GET['date'] : null,
            'ticket_type' => (int) ($_GET['ticket_type'] ?? 0) ?: null,
        ]);
        break;

    // Soldul unui card cadou după cod (pagina „Voucher”)
    case 'voucher-verify':
        $path = '/tenant-client/gift-cards/' . rawurlencode(substr((string) ($_GET['code'] ?? ''), 0, 64));
        break;

    // Newsletterul tenantului (NewsletterController): abonare din formular, dezabonare din linkul din email
    case 'newsletter-subscribe':
    case 'newsletter-unsubscribe':
        $method = 'POST';
        $path   = '/tenant-client/newsletter/' . ($action === 'newsletter-subscribe' ? 'subscribe' : 'unsubscribe');
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    // Comenzile și biletele din browserul cumpărătorului, setarea parolei (DemoStorefrontController)
    case 'orders':
        $path = '/tenant-client/storefront/orders';
        break;
    case 'tickets':
        $path = '/tenant-client/storefront/tickets';
        break;
    case 'order':
        $path = '/tenant-client/storefront/orders/' . (int) ($_GET['id'] ?? 0);
        break;
    case 'password-link':
    case 'password-set':
        $method = 'POST';
        $path   = '/tenant-client/storefront/' . $action;
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
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

    case 'gami-history':
        $path   = '/tenant-client/gamification-history';
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

    case 'acc-favorites':
        $path   = '/tenant-client/account/favorites';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-fav-toggle':
        $method = 'POST';
        $path   = '/tenant-client/account/favorites/toggle';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-reviews':
        $path   = '/tenant-client/account/reviews';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-reviews-eligible':
        $path   = '/tenant-client/account/reviews/eligible';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-review-submit':
        $method = 'POST';
        $path   = '/tenant-client/account/reviews';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
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

    case 'acc-notifications':
        $path   = '/tenant-client/account/notifications';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-beneficiaries':
        $path   = '/tenant-client/account/beneficiaries';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-beneficiary-add':
        $method = 'POST';
        $path   = '/tenant-client/account/beneficiaries';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-beneficiary-remove':
        $method = 'DELETE';
        $path   = '/tenant-client/account/beneficiaries/' . (int) ($_GET['id'] ?? 0);
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-payment-methods':
        $path   = '/tenant-client/account/payment-methods';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-payment-add':
        $method = 'POST';
        $path   = '/tenant-client/account/payment-methods';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-payment-default':
        $method = 'POST';
        $path   = '/tenant-client/account/payment-methods/' . (int) ($_GET['id'] ?? 0) . '/default';
        $query  = ['tenant' => TENANT_ID];
        $body   = [];
        break;

    case 'acc-payment-remove':
        $method = 'DELETE';
        $path   = '/tenant-client/account/payment-methods/' . (int) ($_GET['id'] ?? 0);
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-gift-cards':
        $path   = '/tenant-client/account/gift-cards';
        $query  = ['tenant' => TENANT_ID];
        break;

    case 'acc-gift-redeem':
        $method = 'POST';
        $path   = '/tenant-client/account/gift-cards/redeem';
        $query  = ['tenant' => TENANT_ID];
        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        break;

    case 'acc-gift-purchase':
        $method = 'POST';
        $path   = '/tenant-client/account/gift-cards/purchase';
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

// Tenantul se găsește după domeniul pe care a venit vizitatorul (subdomeniu, domeniu propriu sau demo)
if (str_starts_with($path, '/tenant-client/')) {
    $query['hostname'] = ts_host();
    $query['tenant'] = $query['tenant'] ?? TENANT_ID;
}

$url = API_BASE . $path;
if (!empty($query)) { $url .= '?' . http_build_query($query); }

// --- Forward cURL ---
$ch = curl_init($url);
$headers = [
    'Accept: application/json',
    'X-Session-Id: ' . $sid,
];
$headers = array_merge($headers, ts_visitor_headers());
if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
// Forward token-ul de autentificare al clientului (pt. /me, /logout)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authHeader) { $headers[] = 'Authorization: ' . $authHeader; }

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_TIMEOUT        => API_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 8,
    // Forțează IPv4 (vezi includes/api.php) — evită stall-ul de ~5s pe IPv6.
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => 'tixello-sites-proxy/1.0',
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
