<?php
/**
 * Kit client gateway  (served as the site's /api/proxy.php).
 *
 * Single browser→core entry point, unifying the two hand-rolled proxies from
 * ambilet/teatru into one allow-listed, profile-aware forwarder:
 *   - hides the marketplace API key (never exposed to the browser),
 *   - scopes tenant reads with ?tenant=ID and tenant AUTH with ?hostname=,
 *   - keeps a stable seating session (X-Session-Id) per visitor for holds,
 *   - normalizes request bodies and response envelopes so the two profiles
 *     look identical to the client (the browser-side twin of kit/core/adapters),
 *   - short per-action cache for hot read endpoints.
 *
 * The tables below were reconciled action-by-action against routes/api.php and
 * the controllers in app/Http/Controllers/Api/{TenantClient,MarketplaceClient}.
 * They are the CONTRACT: change them, not the pages.
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
 * Allow-list, per profile.
 *
 *   action => [ client method, endpoint template, cache ttl seconds, opts ]
 *
 * {id}/{event}/{slug}/{order} are filled from query params.
 * opts keys:
 *   'upstream' => 'PUT'   upstream verb when it differs from the client's
 *   'scope'    => 'host'  also send ?hostname= (tenant AUTH endpoints need it:
 *                         TenantClient\AuthController resolves ONLY by hostname)
 *   'session'  => true    send X-Session-Id (the /public/* seating session)
 *   'req'      => 'fn'    request-body normalizer
 *   'res'      => 'fn'    response normalizer
 */
$ACTIONS = $isTenant ? [
    // ---- public catalogue (cacheable) ----
    'events'         => ['GET',  '/tenant-client/events',            30],
    'event'          => ['GET',  '/tenant-client/events/{slug}',     30],
    'categories'     => ['GET',  '/tenant-client/categories',      3600],
    'artists'        => ['GET',  '/tenant-client/artists',         3600],
    'artist'         => ['GET',  '/tenant-client/artists/{slug}',  3600],
    'subscriptions'  => ['GET',  '/tenant-client/subscriptions',    300],
    'payment-methods'=> ['GET',  '/tenant-client/payment-methods',  300],
    'page'           => ['GET',  '/tenant-client/pages/{slug}',     600],
    // no dedicated tenant search endpoint — the events list takes ?search=
    'search'         => ['GET',  '/tenant-client/events',            60, ['req' => 'kit_req_search']],
    'blog'           => ['GET',  '/tenant-client/blog',             300],
    'post'           => ['GET',  '/tenant-client/blog/{slug}',      300],

    // ---- seating (session-based, never cache) ----
    'seating'        => ['GET',   '/public/events/{event}/seating', 0, ['session' => true]],
    'seats'          => ['GET',   '/public/events/{event}/seats',   0, ['session' => true]],
    'holds'          => ['GET',   '/public/seats/holds',            0, ['session' => true]],
    'hold'           => ['POST',  '/public/seats/hold',             0, ['session' => true, 'req' => 'kit_req_seats']],
    'release'        => ['DELETE','/public/seats/hold',             0, ['session' => true, 'req' => 'kit_req_seats']],
    'seats-confirm'  => ['POST',  '/public/seats/confirm',          0, ['session' => true, 'req' => 'kit_req_seats']],

    // ---- checkout (never cache) ----
    // NOTE: /tenant-client/checkout/submit is still a stub upstream (it creates
    // no Order). demo-checkout is the only tenant endpoint that really writes an
    // order + tickets and returns a payment redirect, and it is what the live
    // teatru skin uses. Point 'checkout' elsewhere once a real one ships.
    'checkout'       => ['POST', '/tenant-client/demo-checkout',    0, ['req' => 'kit_req_checkout_tenant', 'res' => 'kit_res_checkout']],
    'order-summary'  => ['GET',  '/tenant-client/order-summary',    0],

    // ---- auth (hostname-scoped) + account (bearer; never cache) ----
    'login'          => ['POST', '/tenant-client/auth/login',       0, ['scope' => 'host', 'res' => 'kit_res_auth']],
    'register'       => ['POST', '/tenant-client/auth/register',    0, ['scope' => 'host', 'req' => 'kit_req_register', 'res' => 'kit_res_auth']],
    'me'             => ['GET',  '/tenant-client/auth/me',          0, ['scope' => 'host', 'res' => 'kit_res_auth']],
    'logout'         => ['POST', '/tenant-client/auth/logout',      0, ['scope' => 'host']],
    'me-update'      => ['POST', '/tenant-client/account/profile',  0, ['req' => 'kit_req_profile']],
    'acc-stats'      => ['GET',  '/tenant-client/account/stats',    0],
    'acc-tickets'    => ['GET',  '/tenant-client/account/tickets',  0, ['res' => 'kit_res_tickets']],
    'acc-orders'     => ['GET',  '/tenant-client/account/orders',   0],
    'acc-subscriptions'   => ['GET',  '/tenant-client/my-subscriptions', 0],
    'acc-giftcards'       => ['GET',  '/tenant-client/account/gift-cards', 0],
    'acc-giftcard-redeem' => ['POST', '/tenant-client/account/gift-cards/redeem', 0],
    'acc-favorites'  => ['GET',  '/tenant-client/account/favorites', 0, ['res' => 'kit_res_favorites']],
    'fav-toggle'     => ['POST', '/tenant-client/account/favorites/toggle', 0, ['req' => 'kit_req_fav']],
    'review-submit'  => ['POST', '/tenant-client/account/reviews',  0, ['req' => 'kit_req_review']],
] : [
    // ---- public catalogue (cacheable) ----
    'events'         => ['GET',  '/marketplace-client/events',              30],
    'event'          => ['GET',  '/marketplace-client/events/{slug}',       30],
    'categories'     => ['GET',  '/marketplace-client/events/categories', 3600],
    'artists'        => ['GET',  '/marketplace-client/artists',          3600],
    'artist'         => ['GET',  '/marketplace-client/artists/{slug}',   3600],
    'venues'         => ['GET',  '/marketplace-client/venues',           3600],
    'venue'          => ['GET',  '/marketplace-client/venues/{slug}',    3600],
    'payment-methods'=> ['GET',  '/marketplace-client/checkout/features', 300, ['res' => 'kit_res_payment_methods']],
    'search'         => ['GET',  '/marketplace-client/search',             60],
    'blog'           => ['GET',  '/marketplace-client/blog-articles',     300],
    'post'           => ['GET',  '/marketplace-client/blog-articles/{slug}', 300],

    // ---- seating (shared /public/* surface; session-based) ----
    'seating'        => ['GET',   '/public/events/{event}/seating', 0, ['session' => true]],
    'seats'          => ['GET',   '/public/events/{event}/seats',   0, ['session' => true]],
    'holds'          => ['GET',   '/public/seats/holds',            0, ['session' => true]],
    'hold'           => ['POST',  '/public/seats/hold',             0, ['session' => true, 'req' => 'kit_req_seats']],
    'release'        => ['DELETE','/public/seats/hold',             0, ['session' => true, 'req' => 'kit_req_seats']],
    'seats-confirm'  => ['POST',  '/public/seats/confirm',          0, ['session' => true, 'req' => 'kit_req_seats']],

    // ---- commerce ----
    'newsletter'     => ['POST', '/marketplace-client/customer/newsletter/subscribe', 0],
    'promo-validate' => ['POST', '/marketplace-client/promo-codes/validate', 0, ['req' => 'kit_req_promo']],
    'checkout'       => ['POST', '/marketplace-client/customer/checkout', 0, ['req' => 'kit_req_checkout_marketplace', 'res' => 'kit_res_checkout']],
    // marketplace checkout only CREATES the order; payment is a second call
    'checkout-pay'   => ['POST', '/marketplace-client/orders/{order}/pay', 0],
    'order-summary'  => ['GET',  '/marketplace-client/orders/{order}', 0, ['res' => 'kit_res_order']],

    // ---- auth + account (bearer on top of the X-API-Key; never cache) ----
    'login'          => ['POST', '/marketplace-client/customer/login',    0, ['res' => 'kit_res_auth']],
    'register'       => ['POST', '/marketplace-client/customer/register', 0, ['req' => 'kit_req_register', 'res' => 'kit_res_auth']],
    'me'             => ['GET',  '/marketplace-client/customer/me',       0, ['res' => 'kit_res_auth']],
    'logout'         => ['POST', '/marketplace-client/customer/logout',   0],
    'me-update'      => ['POST', '/marketplace-client/customer/profile',  0, ['upstream' => 'PUT', 'req' => 'kit_req_profile']],
    'acc-stats'      => ['GET',  '/marketplace-client/customer/stats',    0],
    'acc-tickets'    => ['GET',  '/marketplace-client/customer/tickets',  0, ['res' => 'kit_res_tickets']],
    'acc-orders'     => ['GET',  '/marketplace-client/customer/orders',   0],
    'acc-giftcards'  => ['GET',  '/marketplace-client/customer/gift-cards', 0],
    'acc-giftcard-redeem' => ['POST', '/marketplace-client/customer/gift-cards/redeem', 0],
    'acc-favorites'  => ['GET',  '/marketplace-client/customer/watchlist', 0],
    'fav-toggle'     => ['POST', '/marketplace-client/customer/watchlist', 0, ['req' => 'kit_req_fav']],
    'review-submit'  => ['POST', '/marketplace-client/customer/reviews',  0, ['req' => 'kit_req_review']],
];

/**
 * Actions with no counterpart on this profile. Answered locally with an honest
 * 501 (never a silent success) so a generic pageset degrades instead of
 * pretending the write landed. Keys are action names; values are the reason.
 */
$UNSUPPORTED = $isTenant ? [
    'event-reviews' => 'tenant-client exposes reviews only per account, not per event',
    'newsletter'    => 'tenant-client has no newsletter subscribe endpoint',
    'promo-validate'=> 'tenant promo codes are validated through the cart, not standalone',
    'venues'        => 'tenant-client has no venues endpoint (venue data ships inside the event)',
    'venue'         => 'tenant-client has no venues endpoint',
    'checkout-pay'  => 'the tenant checkout returns its payment URL directly, so there is no second step',
] : [
    'event-reviews'   => 'marketplace-client exposes reviews only per account, not per event',
    'subscriptions'   => 'subscriptions are a tenant-only feature',
    'acc-subscriptions' => 'subscriptions are a tenant-only feature',
    'page'            => 'marketplace-client has no CMS pages endpoint',
];

// Forward the browser's Authorization header (Bearer token) for account calls.
$fwd = [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($auth) $fwd[] = 'Authorization: ' . $auth;

if (isset($UNSUPPORTED[$action])) {
    http_response_code(501);
    echo json_encode(['success' => false, 'data' => [], 'error' => "Not available on this profile: {$UNSUPPORTED[$action]}"]);
    exit;
}

if (!isset($ACTIONS[$action])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
    exit;
}

[$verb, $endpoint, $ttl] = $ACTIONS[$action];
$opts = $ACTIONS[$action][3] ?? [];

// ---- Request-method enforcement -----------------------------------------
// The client must use the action's HTTP method (prevents a GET action being
// triggered by a form POST and vice versa). The UPSTREAM verb may differ.
if ($method !== $verb) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
$upstreamVerb = $opts['upstream'] ?? $verb;

// ---- CSRF / same-origin check for state-changing calls -------------------
// POST/DELETE must originate from this site. A cross-site page cannot forge a
// matching Origin, so this blocks classic CSRF while allowing same-origin JS.
if ($verb !== 'GET') {
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $ohost  = $origin ? parse_url($origin, PHP_URL_HOST) : '';
    if ($host === '' || $ohost === '' || strcasecmp($ohost, parse_url('http://' . $host, PHP_URL_HOST) ?: $host) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cross-origin request blocked']);
        exit;
    }
}

// ---- Rate limiting for sensitive actions ---------------------------------
$SENSITIVE = ['login' => 10, 'register' => 5, 'checkout' => 15, 'review-submit' => 10,
              'newsletter' => 8, 'promo-validate' => 20, 'fav-toggle' => 60, 'me-update' => 20,
              'acc-giftcard-redeem' => 10, 'hold' => 60, 'release' => 60, 'seats-confirm' => 15];
if (isset($SENSITIVE[$action]) && !kit_rate_ok($action, (int)$SENSITIVE[$action])) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please slow down.']);
    exit;
}

// Fill {placeholders} from query params, then drop them from the forwarded set.
$params = $_GET;
unset($params['action']);
$endpoint = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$params) {
    $v = $params[$m[1]] ?? '';
    unset($params[$m[1]]);
    return rawurlencode((string)$v);
}, $endpoint);

// Seating endpoints are keyed by the numeric event id, never a slug.
if (!empty($opts['session']) && strpos($endpoint, '/public/events/') === 0 && !preg_match('#/public/events/\d+/#', $endpoint)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Seating requires a numeric event id']);
    exit;
}

// Request body + query normalization (see the kit_req_* helpers below).
$body = $verb === 'GET' ? [] : (json_decode((string)file_get_contents('php://input'), true) ?: []);
if (!is_array($body)) $body = [];
if (!empty($opts['req'])) {
    [$body, $params] = ($opts['req'])($body, $params, $isTenant);
}

// Extra headers: the seating session, and the tenant hostname for auth calls.
if (!empty($opts['session'])) {
    $fwd[] = 'X-Session-Id: ' . kit_seating_session();
}
if (($opts['scope'] ?? '') === 'host') {
    $params['hostname'] = kit_site_host();
    $fwd[] = 'X-Tenant-Domain: ' . $params['hostname'];
}

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
    $resp = kit_api_request($upstreamVerb, $url, $body, $fwd);
}

// A fixtures hit returns the canned body verbatim (no envelope), so treat a
// missing status as a successful local response rather than an upstream error.
$hasEnvelope = array_key_exists('status', $resp);
$ok     = $hasEnvelope ? (bool)($resp['success'] ?? false) : true;
$status = $hasEnvelope ? ((int)$resp['status'] ?: ($ok ? 200 : 502)) : 200;

$out = ($hasEnvelope ? ($resp['raw'] ?? null) : $resp) ?? [];
if (!is_array($out)) $out = ['success' => $ok];
// Every controller family words failures differently (`message` vs `error`);
// the client only ever reads `.error`, so settle it here.
if (!$ok && empty($out['error'])) {
    $out['error'] = $out['message'] ?? ($resp['error'] ?? 'Request failed');
}
if (!empty($opts['res'])) {
    $out = ($opts['res'])($out, $isTenant);
}

http_response_code($status);
echo json_encode($out);

/* ==========================================================================
   Request normalizers.  Each takes ($body, $params, $isTenant) and returns
   [$body, $params]. They exist so a generic pageset can speak ONE vocabulary
   while the two backends keep their own field names.
   ========================================================================== */

/** search: the kit sends ?q=…; tenant events filters on ?search=…. */
function kit_req_search(array $b, array $p, bool $t): array {
    if (isset($p['q']) && !isset($p['search'])) { $p['search'] = $p['q']; unset($p['q']); }
    return [$b, $p];
}

/**
 * Seat holds. SeatingController validates {event_seating_id, seat_uids[]}.
 * Accept the singular `seat_uid` the widget used to send, and drop `event`
 * (it is not part of the hold payload).
 */
function kit_req_seats(array $b, array $p, bool $t): array {
    if (!isset($b['seat_uids']) && isset($b['seat_uid'])) $b['seat_uids'] = [$b['seat_uid']];
    unset($b['seat_uid'], $b['event']);
    if (isset($b['seat_uids']) && !is_array($b['seat_uids'])) $b['seat_uids'] = [$b['seat_uids']];
    return [$b, $p];
}

/** register: both backends want first_name + last_name; the form has one field. */
function kit_req_register(array $b, array $p, bool $t): array {
    [$b['first_name'], $b['last_name']] = kit_split_name($b);
    unset($b['name']);
    // marketplace validates `password` with `confirmed`
    if (!$t && !isset($b['password_confirmation'])) $b['password_confirmation'] = $b['password'] ?? '';
    return [$b, $p];
}

/** profile update: same name split; strip read-only fields the form echoes back. */
function kit_req_profile(array $b, array $p, bool $t): array {
    if (isset($b['name'])) { [$b['first_name'], $b['last_name']] = kit_split_name($b); }
    unset($b['name'], $b['id'], $b['role'], $b['email']);
    return [$b, $p];
}

/**
 * Favorites. Tenant: {item_type,item_id}. Marketplace: the watchlist is
 * event-only and keyed by {event_id}. The widget sends {type,id,active}.
 */
function kit_req_fav(array $b, array $p, bool $t): array {
    $type = $b['item_type'] ?? $b['type'] ?? 'event';
    $id   = (int)($b['item_id'] ?? $b['id'] ?? 0);
    if ($t) return [['item_type' => $type === 'artist' ? 'artist' : 'event', 'item_id' => $id], $p];
    return [['event_id' => $id], $p];
}

/** review: the page passes ?event=ID; both backends take it in the body. */
function kit_req_review(array $b, array $p, bool $t): array {
    if (!isset($b['event_id']) && isset($p['event'])) $b['event_id'] = (int)$p['event'];
    unset($p['event']);
    return [$b, $p];
}

/** promo: MarketplacePromoCodeController wants {code,event_id,cart_total}. */
function kit_req_promo(array $b, array $p, bool $t): array {
    if (!isset($b['cart_total'])) $b['cart_total'] = (float)($b['subtotal'] ?? 0);
    if (!isset($b['event_id']) && isset($p['event'])) $b['event_id'] = (int)$p['event'];
    unset($b['subtotal']);
    return [$b, $p];
}

/**
 * Tenant checkout. The cart holds kit cart lines; demo-checkout wants a single
 * event plus either `seats` (seat-map flow) or `items` (ticket-type flow).
 */
function kit_req_checkout_tenant(array $b, array $p, bool $t): array {
    $items = $b['items'] ?? [];
    $c     = $b['contact'] ?? $b['customer'] ?? [];
    [$first, $last] = kit_split_name($c);

    $seats = [];
    $lines = [];
    $eventId = $b['event_id'] ?? null;
    $seatingId = $b['event_seating_id'] ?? null;
    foreach ($items as $l) {
        $eventId   = $eventId   ?: ($l['event_id'] ?? null);
        $seatingId = $seatingId ?: ($l['event_seating_id'] ?? null);
        foreach (($l['seats'] ?? []) as $s) {
            $seats[] = ['seat_uid' => $s['seat_uid'] ?? '', 'price' => (float)($s['price'] ?? 0), 'label' => $s['label'] ?? ''];
        }
        if (empty($l['seats']) && !empty($l['ticket_type_id'])) {
            $lines[] = ['ticket_type_id' => (int)$l['ticket_type_id'], 'quantity' => max(1, (int)($l['qty'] ?? 1))];
        }
    }

    return [array_filter([
        'event_id'         => (int)$eventId,
        'event_seating_id' => $seatingId !== null ? (int)$seatingId : null,
        'customer' => array_filter([
            'first_name' => $first, 'last_name' => $last,
            'email' => $c['email'] ?? '', 'phone' => $c['phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''),
        'seats'          => $seats ?: null,
        'items'          => $seats ? null : ($lines ?: null),
        'payment_method' => $b['payment_method'] ?? 'card',
        'newsletter'     => (bool)($b['newsletter'] ?? false),
        'success_url'    => $b['success_url'] ?? null,
        'cancel_url'     => $b['cancel_url'] ?? null,
    ], fn ($v) => $v !== null), $p];
}

/**
 * Marketplace checkout. Customer\CheckoutController takes the localStorage cart
 * verbatim as `items` and REQUIRES customer.first_name/last_name + accept_terms.
 */
function kit_req_checkout_marketplace(array $b, array $p, bool $t): array {
    $c = $b['contact'] ?? $b['customer'] ?? [];
    [$first, $last] = kit_split_name($c);
    $b['customer'] = array_filter([
        'first_name' => $first, 'last_name' => $last,
        'email' => $c['email'] ?? '', 'phone' => $c['phone'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
    unset($b['contact']);
    $b['accept_terms'] = true;   // the checkout pageset gates its submit on this
    $b['items'] = array_map(function ($l) {
        $l['quantity'] = max(1, (int)($l['qty'] ?? $l['quantity'] ?? 1));
        return $l;
    }, $b['items'] ?? []);
    return [$b, $p];
}

/* ==========================================================================
   Response normalizers.  Each takes ($decoded, $isTenant) and returns the body
   the client sees. Same job as kit/core/adapters, on the browser side.
   ========================================================================== */

/**
 * login / register / me. Three different envelopes upstream:
 *   tenant login    → data:{token, user:{…}}
 *   tenant me       → data:{id,name,email,…}            (the profile itself)
 *   marketplace all → data:{customer:{…}, token?}
 * Settle on data:{token?, user:{…}} PLUS the profile fields flattened onto data,
 * so both the login page (data.user) and the settings page (data.email) work.
 */
function kit_res_auth(array $r, bool $t): array {
    $d = $r['data'] ?? null;
    if (!is_array($d)) return $r;

    $u = $d['user'] ?? $d['customer'] ?? null;
    if (!is_array($u)) {
        // a bare profile response: everything except the token IS the user
        $u = $d; unset($u['token']);
        if (!isset($u['id']) && !isset($u['email'])) return $r;
    }
    if (empty($u['name'])) {
        $u['name'] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    }
    unset($d['customer']);
    $r['data'] = array_merge($u, $d, ['user' => $u]);
    return $r;
}

/**
 * checkout: settle two very different success payloads on {order_id, payment_url,
 * payment_required}.
 *   tenant      → flat {success, order_id, total, redirect_url}
 *   marketplace → {data:{orders:[{id,…}], payment_required}} and NO payment url
 *                 (the browser then calls the checkout-pay action)
 */
function kit_res_checkout(array $r, bool $t): array {
    $d = is_array($r['data'] ?? null) ? $r['data'] : [];
    $order = $d['orders'][0] ?? null;
    $r['data'] = [
        'order_id'         => $r['order_id'] ?? $d['order_id'] ?? ($order['id'] ?? null),
        'order_number'     => $order['order_number'] ?? ($r['order_number'] ?? null),
        'total'            => $r['total'] ?? ($order['total'] ?? null),
        'payment_url'      => $r['redirect_url'] ?? $r['payment_url'] ?? $d['payment_url'] ?? null,
        'payment_required' => $d['payment_required'] ?? null,
    ];
    return $r;
}

/** tickets: tenant splits {upcoming,past}; the pages render one list. */
function kit_res_tickets(array $r, bool $t): array {
    $d = $r['data'] ?? null;
    if (is_array($d) && (isset($d['upcoming']) || isset($d['past']))) {
        $r['data'] = array_merge(array_values($d['upcoming'] ?? []), array_values($d['past'] ?? []));
    }
    return $r;
}

/** favorites: tenant splits {events,artists}; the page renders one list. */
function kit_res_favorites(array $r, bool $t): array {
    $d = $r['data'] ?? null;
    if (is_array($d) && (isset($d['events']) || isset($d['artists']))) {
        $r['data'] = array_merge(array_values($d['events'] ?? []), array_values($d['artists'] ?? []));
    }
    return $r;
}

/**
 * payment-methods: the tenant endpoint returns the canonical list already.
 * The marketplace only exposes /checkout/features, so build the same list here.
 */
function kit_res_payment_methods(array $r, bool $t): array {
    $f = $r['data'] ?? [];
    $methods = [['id' => 'card', 'name' => 'Card bancar', 'hint' => 'Visa, Mastercard', 'surcharge_percent' => 0]];
    if (!empty($f['cultural_card']['enabled'])) {
        $methods[] = ['id' => 'card_cultural', 'name' => 'Card cultural', 'hint' => 'Tichet Cultural, Edenred, Up Cultural',
                      'surcharge_percent' => (float)($f['cultural_card']['surcharge_percent'] ?? 4)];
    }
    return ['success' => true, 'data' => $methods];
}

/**
 * order-summary: the marketplace returns a full Order resource; reshape it to
 * the small confirmation view-model the tenant endpoint already returns.
 */
function kit_res_order(array $r, bool $t): array {
    $o = $r['data']['order'] ?? $r['data'] ?? null;
    if (!is_array($o)) return $r;
    $ev = $o['event'] ?? ($o['items'][0]['event'] ?? null);
    $r['data'] = [
        'order_id'       => $o['id'] ?? ($o['order_number'] ?? null),
        'status'         => $o['status'] ?? null,
        'is_paid'        => in_array($o['status'] ?? '', ['paid', 'confirmed', 'completed'], true),
        'created_at'     => $o['created_at'] ?? null,
        'customer_name'  => $o['customer_name'] ?? trim((string)($o['customer']['name'] ?? '')),
        'customer_email' => $o['customer_email'] ?? ($o['customer']['email'] ?? null),
        'total'          => isset($o['total']) ? (float)$o['total'] : null,
        'currency'       => $o['currency'] ?? 'RON',
        'payment_method' => $o['payment_method'] ?? null,
        'event'          => is_array($ev) ? [
            'title' => $ev['name'] ?? $ev['title'] ?? '',
            'slug'  => $ev['slug'] ?? '',
            'date'  => $ev['starts_at'] ?? $ev['event_date'] ?? null,
            'venue' => is_array($ev['venue'] ?? null) ? ($ev['venue']['name'] ?? '') : ($ev['venue'] ?? ''),
            'city'  => $ev['city'] ?? '',
        ] : null,
        'tickets'        => $o['tickets'] ?? [],
    ];
    return $r;
}

/* ---- small helpers ------------------------------------------------------ */

/** Split a single "name" field into [first, last]; pass through if already split. */
function kit_split_name(array $src): array {
    $first = $src['first_name'] ?? $src['firstName'] ?? '';
    $last  = $src['last_name']  ?? $src['lastName']  ?? '';
    if ($first === '' && $last === '') {
        $parts = preg_split('/\s+/', trim((string)($src['name'] ?? '')), 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? $first;   // both backends require a last name
    }
    return [$first, $last ?: $first];
}

/**
 * The hostname tenant AUTH is resolved by. Prefer the configured site_url so a
 * local dev-router run still resolves the right tenant Domain upstream; fall
 * back to the request host in production.
 */
function kit_site_host(): string {
    $u = (string)Kit::get('site_url');
    $h = $u ? (parse_url($u, PHP_URL_HOST) ?: '') : '';
    return $h ?: (string)($_SERVER['HTTP_HOST'] ?? '');
}

/**
 * Stable seating session id, in a first-party cookie. SeatingSessionMiddleware
 * prefers X-Session-Id over its own cookie, and its cookie can never reach the
 * browser through a server-side proxy — so this cookie IS the hold session.
 * Without it every hold/release call would land in a different session and no
 * seat could ever be released or confirmed.
 */
function kit_seating_session(): string {
    $name = 'kit_seat_sid';
    $cur  = isset($_COOKIE[$name]) ? preg_replace('/[^a-f0-9]/', '', (string)$_COOKIE[$name]) : '';
    if (strlen($cur) === 32) return $cur;
    $sid = bin2hex(random_bytes(16));
    if (!headers_sent()) {
        setcookie($name, $sid, ['expires' => time() + 3600, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
    }
    $_COOKIE[$name] = $sid;
    return $sid;
}

/**
 * Basic per-IP+action rate limit, fixed 1-minute window, file-backed.
 * Fail-open (returns true) if the filesystem is unavailable, so a broken cache
 * dir never takes the site down. For heavy scale, back this with Redis/the API.
 */
function kit_rate_ok(string $action, int $max): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $window = (int)floor(time() / 60);
    $file = sys_get_temp_dir() . '/kit_rl_' . md5($ip . '|' . $action) . '.json';
    $fh = @fopen($file, 'c+');
    if (!$fh) return true;
    @flock($fh, LOCK_EX);
    $cur = json_decode((string)stream_get_contents($fh), true);
    $n = (is_array($cur) && ($cur['w'] ?? -1) === $window) ? (int)($cur['n'] ?? 0) + 1 : 1;
    @ftruncate($fh, 0); @rewind($fh); @fwrite($fh, json_encode(['w' => $window, 'n' => $n]));
    @flock($fh, LOCK_UN); @fclose($fh);
    return $n <= $max;
}
