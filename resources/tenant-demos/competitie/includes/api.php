<?php
/**
 * Client API server-side pentru skin-ul competitie.
 * Apeluri GET către core.tixello.com/api, cu cache pe fișier.
 *
 * Endpoint-urile tenant-client sunt publice și se scopează prin ?hostname=DOMENIU —
 * api_get() adaugă automat hostname=TENANT_HOST.
 *
 * Returnează întotdeauna un array: ['success'=>bool, 'status'=>int, 'data'=>mixed, 'meta'=>array, 'error'=>?string]
 */

if (!defined('API_BASE')) { require_once __DIR__ . '/config.php'; }

/**
 * GET către API. $path începe cu '/', ex: '/tenant-client/events'.
 */
function api_get(string $path, array $params = [], ?int $cacheTtl = null): array {
    // Scopare pe tenant: după domeniul înregistrat în core (fără ID hardcodat)
    if (strpos($path, '/tenant-client/') !== false && !isset($params['tenant']) && !isset($params['hostname'])) {
        $params['hostname'] = TENANT_HOST;
    }

    $url = API_BASE . $path;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Cache pe fișier cu stale-while-revalidate: dacă avem o copie (chiar expirată)
    // o servim INSTANT și reîmprospătăm în fundal — userul nu așteaptă niciodată
    // apelul lent către core (a fost măsurat ~5s pe cache rece).
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
    // Lock atomic: create-exclusive. Dacă există deja, altcineva reîmprospătează.
    $fh = @fopen($lock, 'x');
    if ($fh === false) {
        // Recuperare lock rămas blocat (proces mort) mai vechi de 60s.
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
    // Închide conexiunea cu clientul; restul rulează detașat, în fundal.
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
        CURLOPT_USERAGENT      => 'competitie-skin/1.0',
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
 * Rezumat comandă pentru pagina de confirmare. Null dacă nu există.
 */
function tc_order_summary(int $orderId): ?array {
    $resp = api_get('/tenant-client/order-summary', ['order' => $orderId], 0);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return null; }
    return $resp['data'];
}

/** Un eveniment după slug (detaliu complet, cu tipuri de bilete). Null dacă nu există. */
function tc_event(string $slug, ?int $cacheTtl = null): ?array {
    $resp = api_get('/tenant-client/events/' . rawurlencode($slug), ['locale' => 'ro'], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null) || empty($resp['data']['id'])) { return null; }
    return tc_norm_event($resp['data']);
}

/** Toate competițiile viitoare, în ordine cronologică. */
function tc_upcoming(int $limit = 50): array {
    $events = tc_events(api_get('/tenant-client/events', ['limit' => $limit, 'per_page' => $limit, 'locale' => 'ro']));
    // API-ul ordonează după event_date, care e gol la competițiile pe mai multe zile
    usort($events, fn ($a, $b) => strcmp((string) ev_day($a['start_date'] ?? null), (string) ev_day($b['start_date'] ?? null)));
    return $events;
}

const RO_MONTHS = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
const RO_MONTHS_SHORT = [1 => 'ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];

/** Data calendaristică (Y-m-d) dintr-un ISO 8601, fără conversii de fus orar. */
function ev_day(?string $iso): ?string {
    return ($iso && preg_match('/^\d{4}-\d{2}-\d{2}/', $iso, $m)) ? $m[0] : null;
}

/** [zi start, zi sfârșit] pentru un eveniment; sfârșitul e null la competițiile de o zi. */
function ev_span(array $e): array {
    $s = ev_day($e['start_date'] ?? ($e['date'] ?? null));
    $f = ev_day($e['end_date'] ?? null);
    if ($f === $s) { $f = null; }
    return [$s, $f];
}

/** „14–15 noiembrie 2026” / „3 aprilie 2027” / „30 ianuarie – 1 februarie 2027”. */
function ev_date_label(array $e): string {
    [$s, $f] = ev_span($e);
    if (!$s) { return 'Dată în curs de anunțare'; }
    [$y1, $m1, $d1] = array_map('intval', explode('-', $s));
    if (!$f) { return $d1 . ' ' . RO_MONTHS[$m1] . ' ' . $y1; }
    [$y2, $m2, $d2] = array_map('intval', explode('-', $f));
    if ($y1 === $y2 && $m1 === $m2) { return $d1 . '–' . $d2 . ' ' . RO_MONTHS[$m1] . ' ' . $y1; }
    return $d1 . ' ' . RO_MONTHS[$m1] . ' – ' . $d2 . ' ' . RO_MONTHS[$m2] . ' ' . $y2;
}

/** Bucăți pentru blocul de dată: ['days' => '14–15', 'month' => 'noi', 'year' => '2026']. */
function ev_date_parts(array $e): array {
    [$s, $f] = ev_span($e);
    if (!$s) { return ['days' => '—', 'month' => '', 'year' => '']; }
    [$y1, $m1, $d1] = array_map('intval', explode('-', $s));
    $days = (string) $d1;
    if ($f) {
        [, $m2, $d2] = array_map('intval', explode('-', $f));
        $days = $m1 === $m2 ? $d1 . '–' . $d2 : $d1 . '+';
    }
    return ['days' => $days, 'month' => RO_MONTHS_SHORT[$m1], 'year' => (string) $y1];
}

/** Zile rămase până la start (0 = azi, negativ = trecut, null = fără dată). */
function ev_days_left(array $e): ?int {
    [$s] = ev_span($e);
    if (!$s) { return null; }
    return (int) round((strtotime($s . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
}

/** Momentul de start ca dată locală ISO („2026-11-14T09:00:00”), pentru numărătoarea inversă. */
function ev_start_iso(array $e): ?string {
    [$s] = ev_span($e);
    if (!$s) { return null; }
    $time = !empty($e['start_time']) ? substr((string) $e['start_time'], 0, 5) : '09:00';
    return $s . 'T' . $time . ':00';
}

/** Felul competiției, dedus din titlu (API-ul public nu expune categoriile tenantului). */
function ev_kind(array $e): string {
    $t = mb_strtolower((string) ($e['title'] ?? ''));
    if (str_contains($t, 'campionat')) { return 'Campionat național'; }
    if (str_contains($t, 'cupa româniei') || str_contains($t, 'cupa romaniei')) { return 'Cupa României'; }
    return 'Cupă';
}

/** Cheie de filtrare pentru felul competiției. */
function ev_kind_key(array $e): string {
    return ['Campionat național' => 'campionat', 'Cupa României' => 'cupa-romaniei', 'Cupă' => 'cupa'][ev_kind($e)];
}

/** Numărul de zile de concurs. */
function ev_days_count(array $e): int {
    [$s, $f] = ev_span($e);
    if (!$s || !$f) { return 1; }
    return max(1, (int) round((strtotime($f) - strtotime($s)) / 86400) + 1);
}

/** „Sala X, Oraș” din venue. */
function ev_place(array $e): string {
    $v = $e['venue'] ?? null;
    if (!is_array($v)) { return ''; }
    return implode(', ', array_filter([$v['name'] ?? '', $v['city'] ?? '']));
}

/** Ora de start „HH:MM” sau ''. */
function ev_time(array $e): string {
    return !empty($e['start_time']) ? substr((string) $e['start_time'], 0, 5) : '';
}

/** Preț afișat fără zecimale inutile: „25 lei”, „12,50 lei”. */
function lei($amount): string {
    $n = (float) $amount;
    return (floor($n) == $n ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.')) . ' lei';
}
