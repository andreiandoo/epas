<?php
/**
 * Kit client gateway  (served as the site's /api/proxy.php).
 *
 * Single browser→core entry point, unifying the two hand-rolled 180KB proxies
 * from ambilet/teatru into one allow-listed, profile-aware forwarder:
 *   - hides the marketplace API key (never exposed to the browser),
 *   - scopes tenant reads with ?tenant=ID,
 *   - forwards the seating session cookie for /public/* holds,
 *   - short per-action cache for hot read endpoints.
 *
 * Extend $ACTIONS with more cases as you port functionality — the two legacy
 * proxies enumerate the full surface (357 actions on ambilet, ~40 on teatru).
 * This file is intentionally small: it is the pattern, not the whole catalogue.
 *
 * Request:  /api/proxy.php?action=<name>&<params>
 */
declare(strict_types=1);

require_once __DIR__ . '/core/config.php';
// In a deployed site the kit is vendored next to site.config.php. In dev the
// router may pre-boot the kit with the active site config; skip re-booting then.
if (!Kit::booted()) {
    kit_boot(require dirname(__DIR__) . '/site.config.php');
}

header('Content-Type: application/json; charset=utf-8');

$action = (string)($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isTenant = Kit::isProfile('tenant');

/**
 * Allow-list: action => [http method, endpoint template, cache ttl seconds].
 * {id}/{event}/{slug} are filled from query params. Endpoints are relative to
 * the profile's client base, chosen below.
 */
$base = $isTenant ? '/tenant-client' : '/marketplace-client';
$ACTIONS = [
    // public catalogue (cacheable)
    'events'        => ['GET',  "$base/events",              30],
    'event'         => ['GET',  "$base/events/{slug}",       30],
    'categories'    => ['GET',  $isTenant ? "$base/categories" : "$base/events/categories", 3600],
    'artists'       => ['GET',  "$base/artists",             3600],
    'artist'        => ['GET',  "$base/artists/{slug}",      3600],
    'subscriptions' => ['GET',  "$base/subscriptions",       300],
    'payment-methods'=>['GET',  "$base/payment-methods",     300],
    // seating (session-based, never cache)
    'seating'       => ['GET',  '/public/events/{event}/seating', 0],
    'seats'         => ['GET',  '/public/events/{event}/seats',   0],
    'hold'          => ['POST', '/public/seats/hold',             0],
    'release'       => ['DELETE','/public/seats/hold',            0],
    // newsletter (public)
    'newsletter'    => ['POST', "$base/newsletter/subscribe", 0],
    'promo-validate'=> ['POST', "$base/promo-codes/validate", 0],
    // checkout (never cache)
    'checkout'      => ['POST', "$base/demo-checkout",        0],
    'order-summary' => ['GET',  "$base/order-summary",        0],
    // auth + account (require the browser's Bearer token; never cache)
    'login'         => ['POST', "$base/auth/login",           0],
    'register'      => ['POST', "$base/auth/register",        0],
    'me'            => ['GET',  "$base/account/me",           0],
    'logout'        => ['POST', "$base/auth/logout",          0],
    'me-update'     => ['POST', "$base/account/me",           0],
    'acc-stats'     => ['GET',  "$base/account/stats",        0],
    'acc-tickets'   => ['GET',  "$base/account/tickets",      0],
    'acc-orders'    => ['GET',  "$base/account/orders",       0],
    'acc-subscriptions' => ['GET', "$base/account/subscriptions", 0],
    'acc-giftcards'     => ['GET', "$base/account/gift-cards",    0],
    'acc-giftcard-redeem' => ['POST', "$base/account/gift-cards/redeem", 0],
];

// Forward the browser's Authorization header (Bearer token) for account calls.
$fwd = [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($auth) $fwd[] = 'Authorization: ' . $auth;

if (!isset($ACTIONS[$action])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
    exit;
}

[$verb, $endpoint, $ttl] = $ACTIONS[$action];

// Fill {placeholders} from query params, then drop them from the forwarded set.
$params = $_GET;
unset($params['action']);
$endpoint = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$params) {
    $v = $params[$m[1]] ?? '';
    unset($params[$m[1]]);
    return rawurlencode((string)$v);
}, $endpoint);

// Forward. GETs go through kit_api_get when cacheable OR when fixtures are
// configured (so account/order pages preview offline); auth/mutations are a
// direct request that forwards the Bearer token.
$useFixtures = !empty(Kit::get('fixtures'));
if ($verb === 'GET' && ($useFixtures || ($ttl > 0 && !$fwd))) {
    $resp = kit_api_get($endpoint, $params, $ttl);
} else {
    // tenant scoping for direct requests too
    if ($isTenant && strpos($endpoint, '/tenant-client/') !== false && !isset($params['tenant'])) {
        $params['tenant'] = Kit::get('tenant_id');
    }
    $url = rtrim(Kit::get('api_base'), '/') . $endpoint . ($params ? '?' . http_build_query($params) : '');
    $body = $verb === 'GET' ? [] : (json_decode((string)file_get_contents('php://input'), true) ?: []);
    $resp = kit_api_request($verb, $url, is_array($body) ? $body : [], $fwd);
}

http_response_code($resp['status'] ?? ($resp['success'] ? 200 : 502));
echo json_encode($resp['raw'] ?? $resp);
