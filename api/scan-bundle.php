<?php
/**
 * Scan App — core JS bundle endpoint.
 *
 * PERF P1/3 — instead of loading 6 separate <script defer> tags (auth, api,
 * app-context, event-context, scanner, app) we concatenate them server-side
 * and emit a single response. Saves 5 HTTP requests per page load, which on
 * HTTP/1.1 saves ~150-400ms and even on HTTP/2 saves the per-script parsing
 * setup overhead.
 *
 * Strategy:
 *  - Browser cache key = max(filemtime) of all sources, passed as ?v= by
 *    _layout_end.php. So when ANY source changes, the URL changes and the
 *    browser refetches.
 *  - ETag + Last-Modified for 304 handling (cheap conditional GETs).
 *  - Server cache: built bundle is written to sys_get_temp_dir() so we don't
 *    re-read 6 files on every request.
 *  - No minification — sources are already small (~56KB total, ~14KB gzip)
 *    and shipping unminified makes debugging easier. The whole win is in
 *    request count, not bytes.
 *  - Defensive: if a source file is missing, emit a JS comment instead of
 *    breaking the bundle. The page can still try to recover.
 *
 * Scope: only used by scan-app pages via _layout_end.php. Main panel and
 * marketplace pages are untouched.
 */

// Sources in load order (matches the previous <script> tags in _layout_end.php).
// Order MATTERS — auth.js defines ScanAuth before api.js / app-context.js use it.
$sources = [
    'auth.js',
    'api.js',
    'app-context.js',
    'event-context.js',
    'scanner.js',
    'app.js',
];

$baseDir = dirname(__DIR__) . '/assets/js/scan-app/';

$paths = [];
$mtimes = [];
foreach ($sources as $s) {
    $full = $baseDir . $s;
    $paths[$s] = $full;
    $mtimes[$s] = is_file($full) ? filemtime($full) : 0;
}

$fingerprint = substr(md5(implode('|', array_map(
    fn($s) => $s . ':' . $mtimes[$s],
    array_keys($mtimes)
))), 0, 12);

$etag = '"scan-bundle-' . $fingerprint . '"';
$lastModified = $mtimes ? max($mtimes) : time();

header('Content-Type: application/javascript; charset=utf-8');
// Browser cache: 1 hour. The ?v= in _layout_end.php invalidates immediately
// on file change, so we don't need a long max-age. 1h covers a full operator
// shift without stale issues.
header('Cache-Control: public, max-age=3600, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('Vary: Accept-Encoding');

// Conditional GET — return 304 with no body when client has fresh copy.
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

// Server-side cache: build once per unique fingerprint, then readfile.
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scan-bundle-' . $fingerprint . '.js';
if (!is_file($cacheFile)) {
    $out = "/*! scan-app bundle " . $fingerprint . " — assembled " . gmdate('c') . " */\n";
    foreach ($sources as $s) {
        $full = $paths[$s];
        $out .= "\n/* ── " . $s . " ────────────────────────────────────────────────────── */\n";
        if (is_file($full)) {
            $contents = @file_get_contents($full);
            if ($contents !== false) {
                $out .= $contents;
                // Ensure each block ends with a newline + semicolon-safe boundary.
                // The source files are all `(function () { ... })();` IIFEs or use
                // explicit `;` at boundaries, but a defensive newline costs nothing.
                if (substr($out, -1) !== "\n") $out .= "\n";
            } else {
                $out .= "/* WARN: could not read " . $s . " */\n";
            }
        } else {
            $out .= "/* WARN: missing source " . $s . " */\n";
        }
    }
    // Write atomically so concurrent readers can't get a half-built file.
    $tmp = $cacheFile . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $out) !== false) {
        @rename($tmp, $cacheFile);
    } else {
        // Fallback: emit directly without caching. The page still works.
        echo $out;
        exit;
    }
}

readfile($cacheFile);
