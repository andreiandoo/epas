<?php
/**
 * Client API server-side pentru skin-ul teatru.
 * Apeluri GET către core.tixello.com/api, cu cache pe fișier.
 *
 * Endpoint-urile tenant-client sunt publice și se scopează prin ?tenant=ID —
 * api_get() adaugă automat tenant=TENANT_ID.
 *
 * Returnează întotdeauna un array: ['success'=>bool, 'status'=>int, 'data'=>mixed, 'meta'=>array, 'error'=>?string]
 */

if (!defined('API_BASE')) { require_once __DIR__ . '/config.php'; }

/**
 * GET către API. $path începe cu '/', ex: '/tenant-client/events'.
 */
function api_get(string $path, array $params = [], ?int $cacheTtl = null): array {
    // Scopare pe tenant pentru endpoint-urile tenant-client
    if (strpos($path, '/tenant-client/') !== false && !isset($params['tenant'])) {
        $params['tenant'] = TENANT_ID;
    }

    $url = API_BASE . $path;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Cache pe fișier (doar GET-uri publice)
    $ttl = $cacheTtl ?? API_CACHE_TTL;
    $cacheFile = null;
    if ($ttl > 0) {
        $cacheFile = CACHE_DIR . '/api_' . md5($url) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) { return $cached; }
        }
    }

    $result = api_request('GET', $url);

    if ($result['success'] && $cacheFile) {
        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
    }

    return $result;
}

/**
 * Request cURL generic. Întoarce structura normalizată.
 */
function api_request(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    $defaultHeaders = ['Accept: application/json'];
    if (!empty($body)) { $defaultHeaders[] = 'Content-Type: application/json'; }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
        CURLOPT_USERAGENT      => 'teatru-skin/1.0',
    ]);
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'status' => 0, 'data' => null, 'meta' => [], 'error' => 'cURL: ' . $err];
    }

    $decoded = json_decode($raw, true);
    $success = $status >= 200 && $status < 300;

    return [
        'success' => $success,
        'status'  => $status,
        'data'    => $decoded['data']    ?? ($success ? $decoded : null),
        'meta'    => $decoded['meta']    ?? [],
        'error'   => $success ? null : ($decoded['message'] ?? $decoded['error'] ?? "HTTP $status"),
        'raw'     => $decoded,
    ];
}

/**
 * URL absolut pentru un asset din storage (poster, imagine).
 * Acceptă cale relativă ('events/x.jpg'), URL absolut sau null.
 */
function asset_url(?string $path, ?string $fallback = null): ?string {
    if (empty($path)) { return $fallback; }
    if (preg_match('#^https?://#', $path)) { return $path; }
    return CORE_URL . '/storage/' . ltrim($path, '/');
}

/** Escape rapid pentru output HTML. */
function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Formatare preț simplu. */
function price_fmt($amount, string $currency = 'RON'): string {
    if ($amount === null || $amount === '') { return ''; }
    return number_format((float) $amount, 0, ',', '.') . ' ' . $currency;
}

/**
 * Normalizează un eveniment din API-ul tenant-client la forma folosită în skin:
 * asigură `category` (din event_types[0]) și `currency`.
 */
function tc_norm_event($e): array {
    if (!is_array($e)) { return []; }
    if (empty($e['category']) && !empty($e['event_types'][0])) {
        $e['category'] = $e['event_types'][0];
    }
    if (empty($e['currency'])) {
        $e['currency'] = $e['ticket_types'][0]['currency'] ?? 'RON';
    }
    return $e;
}

/**
 * Extrage lista de evenimente dintr-un răspuns API, tratând ambele forme:
 * data.events[] (paginat) sau data[] (listă directă). Normalizează fiecare eveniment.
 */
function tc_events(array $resp): array {
    if (!($resp['success'] ?? false)) { return []; }
    $d = $resp['data'] ?? [];
    if (isset($d['events']) && is_array($d['events'])) {
        $list = $d['events'];
    } elseif (is_array($d) && (array_keys($d) === range(0, count($d) - 1))) {
        $list = $d; // listă directă
    } else {
        $list = [];
    }
    return array_map('tc_norm_event', $list);
}

/**
 * Lista de artiști/actori ai tenantului (trupa).
 */
function tc_artists(?int $cacheTtl = null): array {
    $resp = api_get('/tenant-client/artists', [], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return []; }
    return $resp['data'];
}

/**
 * Metode de plată disponibile (card + card cultural dacă e activ).
 */
function tc_payment_methods(?int $cacheTtl = 120): array {
    $resp = api_get('/tenant-client/payment-methods', [], $cacheTtl);
    $d = $resp['data'] ?? null;
    if (is_array($d) && !empty($d)) { return $d; }
    return [['id' => 'card', 'name' => 'Card bancar (demo)', 'hint' => 'Visa, Mastercard — gateway simulat', 'surcharge_percent' => 0]];
}

/**
 * Info gamification (public): dacă e activ + procentul de câștig puncte.
 */
function tc_gamification(?int $cacheTtl = 120): array {
    $resp = api_get('/tenant-client/gamification/config', [], $cacheTtl);
    $raw = $resp['raw'] ?? [];
    if (empty($raw['enabled'])) { return ['enabled' => false]; }
    $d = $resp['data'] ?? [];
    return [
        'enabled'         => true,
        'earn_percentage' => (float) ($d['earn_percentage'] ?? 0),
        'min_order'       => 0,
        'points_name'     => $d['points_name'] ?? 'puncte',
    ];
}

/**
 * Planuri de abonament active ale tenantului.
 */
function tc_subscriptions(?int $cacheTtl = null): array {
    $resp = api_get('/tenant-client/subscriptions', [], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return []; }
    return $resp['data'];
}

/**
 * Rezumat comandă pentru pagina de confirmare. Null dacă nu există.
 */
function tc_order_summary(int $orderId): ?array {
    $resp = api_get('/tenant-client/order-summary', ['order' => $orderId], 0);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return null; }
    return $resp['data'];
}

/**
 * Un singur artist după slug sau id (include bio + galerie).
 * Întoarce null dacă nu există.
 */
function tc_artist(string $slug, ?int $cacheTtl = null): ?array {
    $resp = api_get('/tenant-client/artists/' . rawurlencode($slug), [], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return null; }
    return $resp['data'];
}
