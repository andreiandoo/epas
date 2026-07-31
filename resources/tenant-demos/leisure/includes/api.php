<?php
/**
 * Client API server-side pentru skin-ul nordvale (tenant leisure).
 * Apeluri GET către core.tixello.com/api, cu cache pe fișier (stale-while-revalidate).
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

    // Cache pe fișier cu stale-while-revalidate: dacă avem o copie (chiar expirată)
    // o servim INSTANT și reîmprospătăm în fundal — userul nu așteaptă niciodată
    // apelul lent către core.
    $ttl = $cacheTtl ?? API_CACHE_TTL;
    $cacheFile = null;
    if ($ttl > 0) {
        $cacheFile = CACHE_DIR . '/api_' . md5($url) . '.json';
        if (is_file($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                if ($age < $ttl) {
                    return $cached; // proaspăt
                }
                // expirat: servim copia veche acum, reîmprospătăm după flush
                api_schedule_refresh($url, $cacheFile);
                return $cached;
            }
        }
    }

    // Cold miss (nicio copie utilizabilă) — trebuie să așteptăm sincron o dată.
    $result = api_request('GET', $url);

    if ($result['success'] && $cacheFile) {
        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
    }

    return $result;
}

/**
 * Programează reîmprospătarea unei intrări de cache expirate, DUPĂ ce răspunsul
 * a fost trimis clientului (fastcgi/litespeed_finish_request), cu single-flight
 * (un lock atomic) ca să nu pornim mai multe refresh-uri pentru același URL.
 */
function api_schedule_refresh(string $url, string $cacheFile): void {
    $lock = $cacheFile . '.lock';
    $fh = @fopen($lock, 'x');
    if ($fh === false) {
        if (is_file($lock) && (time() - filemtime($lock)) > 60) {
            @unlink($lock);
            $fh = @fopen($lock, 'x');
        }
        if ($fh === false) { return; }
    }
    fclose($fh);

    $GLOBALS['__api_refresh_queue'][] = [$url, $cacheFile, $lock];

    static $registered = false;
    if (!$registered) {
        $registered = true;
        register_shutdown_function('api_run_refresh_queue');
    }
}

/** Rulează coada de reîmprospătări după ce răspunsul a fost flush-uit la client. */
function api_run_refresh_queue(): void {
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }
    @ignore_user_abort(true);

    foreach (($GLOBALS['__api_refresh_queue'] ?? []) as [$url, $cacheFile, $lock]) {
        $result = api_request('GET', $url);
        if ($result['success'] ?? false) {
            @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
        }
        @unlink($lock);
    }
    $GLOBALS['__api_refresh_queue'] = [];
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
        // Forțează IPv4: pe unele host-uri connect-ul IPv6 către core stă blocat
        // ~5s (happy-eyeballs) înainte să cadă pe IPv4 — cauza TTFB-ului de 5s.
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
        CURLOPT_USERAGENT      => 'nordvale-skin/1.0',
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
function price_fmt($amount, string $currency = 'lei'): string {
    if ($amount === null || $amount === '') { return ''; }
    return number_format((float) $amount, 0, ',', '.') . ' ' . $currency;
}

/**
 * Normalizează un eveniment/experiență din API-ul tenant-client la forma folosită în skin:
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
 * Extrage lista de evenimente/experiențe dintr-un răspuns API, tratând ambele forme:
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
 * O singură experiență/eveniment după slug sau id (include descriere + ticket types).
 * Întoarce null dacă nu există.
 */
function tc_event(string $slug, ?int $cacheTtl = null): ?array {
    $resp = api_get('/tenant-client/events/' . rawurlencode($slug), [], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return null; }
    return tc_norm_event($resp['data']);
}

/**
 * Metode de plată disponibile (card, etc.).
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
 * Planuri de abonament (membership) active ale tenantului.
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
