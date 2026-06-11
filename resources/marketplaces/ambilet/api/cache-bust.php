<?php
/**
 * Cross-host cache invalidation endpoint.
 *
 * Tixello admin POSTs here whenever a seating layout or section is
 * saved, with the slugs of every event that uses the changed layout.
 * For each slug we drop:
 *   - all page-cache HTML files whose body references the slug
 *     (one slug can produce several files when query strings differ)
 *   - the api_cached entries for `event_preload_<slug>` and
 *     `event_redirect_<slug>` in /tmp/ambilet_cache
 *
 * Auth: a shared token in CACHE_BUST_TOKEN (defined in includes/config.php
 * via env). Constant-time compare to avoid timing leaks.
 */

require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

$expectedToken = defined('CACHE_BUST_TOKEN') ? CACHE_BUST_TOKEN : '';
if ($expectedToken === '' || !hash_equals($expectedToken, (string) ($body['token'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$slugs = is_array($body['slugs'] ?? null) ? $body['slugs'] : [];
$pageCacheDir = dirname(__DIR__) . '/includes/cache/pages';
$apiCachedDir = sys_get_temp_dir() . '/ambilet_cache';

$result = [];
$totalPage = 0;
$totalApi = 0;

foreach ($slugs as $slug) {
    // Same character set the slugger uses; reject anything else to keep
    // glob/scan safe.
    if (!is_string($slug)) continue;
    $slug = trim($slug);
    if (!preg_match('/^[a-z0-9-]+$/i', $slug)) continue;

    $busted = ['slug' => $slug, 'page_cache' => 0, 'api_cached' => 0];

    // 1) api_cached preload + redirect for this slug
    foreach (['event_preload_', 'event_redirect_'] as $prefix) {
        $f = $apiCachedDir . '/' . md5($prefix . $slug) . '.json';
        if (file_exists($f) && @unlink($f)) {
            $busted['api_cached']++;
            $totalApi++;
        }
    }

    // 2) page-cache: scan every cached HTML file and drop those that
    //    reference the slug. Cheap-ish — only runs on cache bust, and
    //    each event page is ~50KB so even hundreds of files complete
    //    in well under a second.
    if (is_dir($pageCacheDir)) {
        foreach (glob($pageCacheDir . '/*.html') ?: [] as $f) {
            // Read just the head so we don't pull massive HTML into memory
            // when only the URL slug needs to match.
            $head = @file_get_contents($f, false, null, 0, 16384);
            if ($head !== false && stripos($head, $slug) !== false) {
                if (@unlink($f)) {
                    $busted['page_cache']++;
                    $totalPage++;
                }
            }
        }
    }

    $result[] = $busted;
}

echo json_encode([
    'success' => true,
    'busted' => $result,
    'totals' => [
        'page_cache' => $totalPage,
        'api_cached' => $totalApi,
        'slugs' => count($result),
    ],
]);
