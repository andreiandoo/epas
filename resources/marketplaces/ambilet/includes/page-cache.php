<?php
/**
 * Simple full-page HTML output cache.
 *
 * Include at the TOP of a page (before any output) to enable caching.
 * On cache hit: serves cached HTML and exits immediately — zero API calls.
 * On cache miss: starts output buffering, saves HTML to disk when page finishes.
 *
 * Usage:
 *   $pageCacheTTL = 300; // optional, default 5 min
 *   require_once __DIR__ . '/includes/page-cache.php';
 *
 * Cache is stored in includes/cache/pages/ directory.
 * Clear cache: delete files in includes/cache/pages/
 */

// Skip caching for: POST, preview mode, logged-in admin, cache-busting
if (
    $_SERVER['REQUEST_METHOD'] !== 'GET' ||
    !empty($_GET['preview']) ||
    !empty($_GET['nocache']) ||
    isset($_COOKIE['admin_session'])
) {
    return;
}

$pageCacheTTL = $pageCacheTTL ?? 300; // Default 5 minutes
$pageCacheDir = __DIR__ . '/cache/pages';

// Include $_GET params (other than our control flags) in the cache key so
// different query strings (e.g. tour_slug injected by vanity-tour.php) get
// their own cache file and don't collide on stale content.
$cacheParams = $_GET;
unset($cacheParams['preview'], $cacheParams['nocache']);
ksort($cacheParams);
$pageCacheKey = md5(($_SERVER['REQUEST_URI'] ?? '/') . '|' . serialize($cacheParams));
$pageCacheFile = $pageCacheDir . '/' . $pageCacheKey . '.html';

// Check cache
if (file_exists($pageCacheFile) && (time() - filemtime($pageCacheFile)) < $pageCacheTTL) {
    // Cache HIT — serve directly and exit (no PHP processing at all!)
    header('X-Page-Cache: HIT');
    readfile($pageCacheFile);
    exit;
}

// Cache MISS — start output buffering
header('X-Page-Cache: MISS');

if (!is_dir($pageCacheDir)) {
    @mkdir($pageCacheDir, 0755, true);
}

ob_start(function ($html) use ($pageCacheFile) {
    // Callers can opt out by setting $skipPageCache = true at any point
    // before the buffer flushes (e.g. event.php flags the shell when the
    // event isn't published yet, so the blank "not found" HTML doesn't
    // get written to disk and served to the next visitor).
    if (!empty($GLOBALS['skipPageCache'])) {
        return $html;
    }
    // Only cache successful responses with actual content
    if (strlen($html) > 500 && http_response_code() === 200) {
        @file_put_contents($pageCacheFile, $html);
    }
    return $html;
});
