<?php
/**
 * Kit HTTP layer — one client for both profiles.
 *
 * Unifies the two divergent skin clients:
 *   - marketplace (ambilet): sends X-API-Key header
 *   - tenant (teatru):        appends ?tenant=ID to /tenant-client/* paths
 *
 * Caching is stale-while-revalidate on a file cache (the teatru strategy, which
 * is the stronger of the two): a usable copy is served instantly and refreshed
 * in the background after the response is flushed.
 *
 * FIXTURES MODE: if config 'fixtures' points at a dir, GETs are served from
 * <fixtures>/<slugified-path>.json instead of the network. This lets templates
 * be rendered and visually verified with zero backend access.
 */

/** Low-level GET. $path starts with '/', e.g. '/tenant-client/events'. */
function kit_api_get(string $path, array $params = [], ?int $cacheTtl = null): array {
    $cfg = Kit::config();

    // Tenant scoping — mirror teatru's api_get() auto-tenant behaviour.
    if ($cfg['profile'] === 'tenant' && strpos($path, '/tenant-client/') !== false && !isset($params['tenant'])) {
        $params['tenant'] = $cfg['tenant_id'];
    }

    // Multi-locale sites ask the API for content in the active locale (and the
    // locale becomes part of the cache key, so each language caches separately).
    if (count($cfg['locales'] ?? []) > 1 && !isset($params['locale'])) {
        $params['locale'] = $cfg['active_locale'] ?? $cfg['locale'];
    }

    // Fixtures short-circuit (offline dev / CI / this PoC).
    if (!empty($cfg['fixtures'])) {
        $fx = kit_fixture_load($cfg['fixtures'], $path, $params);
        if ($fx !== null) return $fx;
    }

    $url = rtrim($cfg['api_base'], '/') . $path;
    if ($params) $url .= '?' . http_build_query($params);

    $ttl = $cacheTtl ?? (int)$cfg['cache_ttl'];
    $cacheFile = null;
    if ($ttl > 0) {
        $cacheFile = kit_cache_dir() . '/api_' . md5($url) . '.json';
        if (is_file($cacheFile)) {
            $age    = time() - filemtime($cacheFile);
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                if ($age < $ttl) return $cached;                 // fresh
                kit_schedule_refresh($url, $cacheFile);          // stale → serve now, refresh later
                return $cached;
            }
        }
    }

    $result = kit_api_request('GET', $url);
    if (($result['success'] ?? false) && $cacheFile) {
        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
    }
    return $result;
}

/** Low-level POST (uncached). */
function kit_api_post(string $path, array $data = []): array {
    $cfg = Kit::config();
    $url = rtrim($cfg['api_base'], '/') . $path;
    return kit_api_request('POST', $url, $data);
}

/**
 * cURL request, normalized to ['success','status','data','meta','error','raw'].
 * $extraHeaders lets the proxy forward a browser's Authorization: Bearer token
 * for authenticated account calls.
 */
function kit_api_request(string $method, string $url, array $body = [], array $extraHeaders = []): array {
    $cfg = Kit::config();
    $headers = ['Accept: application/json'];
    if ($cfg['profile'] === 'marketplace' && !empty($cfg['api_key'])) {
        $headers[] = 'X-API-Key: ' . $cfg['api_key'];
    }
    if ($body) $headers[] = 'Content-Type: application/json';
    foreach ($extraHeaders as $h) $headers[] = $h;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => (int)$cfg['api_timeout'],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4, // teatru fix: avoid 5s IPv6 stall
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'kit/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'status' => 0, 'data' => null, 'meta' => [], 'error' => 'cURL: ' . $err];
    }
    $decoded = json_decode($raw, true);
    $ok = $status >= 200 && $status < 300;
    return [
        'success' => $ok,
        'status'  => $status,
        'data'    => $decoded['data'] ?? ($ok ? $decoded : null),
        'meta'    => $decoded['meta'] ?? [],
        'error'   => $ok ? null : ($decoded['message'] ?? $decoded['error'] ?? "HTTP $status"),
        'raw'     => $decoded,
    ];
}

/* ---- cache plumbing ---- */

function kit_cache_dir(): string {
    $dir = sys_get_temp_dir() . '/kit_cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

/** Refresh a stale entry AFTER the response is flushed, with a single-flight lock. */
function kit_schedule_refresh(string $url, string $cacheFile): void {
    $lock = $cacheFile . '.lock';
    $fh = @fopen($lock, 'x');
    if ($fh === false) {
        if (is_file($lock) && (time() - filemtime($lock)) > 60) { @unlink($lock); $fh = @fopen($lock, 'x'); }
        if ($fh === false) return;
    }
    fclose($fh);
    $GLOBALS['__kit_refresh'][] = [$url, $cacheFile, $lock];
    static $registered = false;
    if (!$registered) { $registered = true; register_shutdown_function('kit_run_refresh_queue'); }
}

function kit_run_refresh_queue(): void {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    @ignore_user_abort(true);
    foreach (($GLOBALS['__kit_refresh'] ?? []) as [$url, $cacheFile, $lock]) {
        $r = kit_api_request('GET', $url);
        if ($r['success'] ?? false) @file_put_contents($cacheFile, json_encode($r), LOCK_EX);
        @unlink($lock);
    }
    $GLOBALS['__kit_refresh'] = [];
}

/* ---- fixtures ---- */

/**
 * Map an API path to a fixture file. '/tenant-client/events' → 'tenant-client__events.json'.
 * A '{slug}' single-resource lookup falls back to the collection fixture, letting
 * one events.json feed both the listing and detail pages during dev.
 */
function kit_fixture_load(string $dir, string $path, array $params): ?array {
    $base = trim($path, '/');
    $candidates = [
        $base,                                   // tenant-client/events/foo
        preg_replace('#/[^/]+$#', '', $base),    // tenant-client/events
    ];
    foreach ($candidates as $c) {
        $file = rtrim($dir, '/') . '/' . str_replace('/', '__', $c) . '.json';
        if (is_file($file)) {
            $json = json_decode((string)file_get_contents($file), true);
            if (is_array($json)) return $json;
        }
    }
    return null;
}
