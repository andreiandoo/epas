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
//
// IMPORTANT: strip GA4 linker (_gl, _ga, _up, _gac) and UTM params before
// hashing. Otherwise every visitor arriving from another linker domain gets
// a unique cache key → always cache MISS + thousands of dead files on disk.
$cacheParams = $_GET;
unset($cacheParams['preview'], $cacheParams['nocache']);
foreach (array_keys($cacheParams) as $_k) {
    if (preg_match('/^(_gl|_ga|_up|_gac|utm_|fbclid|gclid|msclkid|yclid)/i', $_k)) {
        unset($cacheParams[$_k]);
    }
}
ksort($cacheParams);
// Use just the path (strtok strips ?query) so tracking-laden URLs collapse
// onto the canonical cache entry of the same path.
$_cachePath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$pageCacheKey = md5($_cachePath . '|' . serialize($cacheParams));
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

// Expired files were never removed (TTL is only checked on read), so every
// distinct URL left a file behind until the disk filled up. On ~1% of misses,
// drop files older than an hour (longest TTL in use is 30 min), within a
// short time budget so a large backlog is cleared over several requests.
if (mt_rand(1, 100) === 1 && ($dh = @opendir($pageCacheDir))) {
    $gcStart = microtime(true);
    $gcCutoff = time() - 3600;
    while (($gcName = readdir($dh)) !== false) {
        if (substr($gcName, -5) !== '.html') {
            continue;
        }
        $gcPath = $pageCacheDir . '/' . $gcName;
        if (@filemtime($gcPath) < $gcCutoff) {
            @unlink($gcPath);
        }
        if (microtime(true) - $gcStart > 0.5) {
            break;
        }
    }
    closedir($dh);
}

ob_start(function ($html) use ($pageCacheFile, $pageCacheDir) {
    // Callers can opt out by setting $skipPageCache = true at any point
    // before the buffer flushes (e.g. event.php flags the shell when the
    // event isn't published yet, so the blank "not found" HTML doesn't
    // get written to disk and served to the next visitor).
    if (!empty($GLOBALS['skipPageCache'])) {
        return $html;
    }
    // Only cache successful responses with actual content, and never when the
    // disk is nearly full (the cache must not take the site down with it).
    if (strlen($html) > 500 && http_response_code() === 200) {
        $free = @disk_free_space($pageCacheDir);
        if ($free === false || $free > 500 * 1024 * 1024) {
            // Write to a temp file and rename, so a write cut short by a full
            // disk never leaves a truncated page that would be served as a hit.
            $tmp = $pageCacheFile . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $html) === strlen($html)) {
                @rename($tmp, $pageCacheFile);
            } else {
                @unlink($tmp);
            }
        }
    }
    return $html;
});
