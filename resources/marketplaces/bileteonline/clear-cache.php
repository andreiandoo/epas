<?php
/**
 * Nuke-all cache endpoint.
 *
 * Walks every local cache the marketplace touches and drops the files.
 * Designed for occasional manual triggering — NOT a hot path. Idempotent:
 * calling it twice in a row is fine, the second call just reports zero
 * files removed.
 *
 * Usage:
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN          → clear all
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN&what=pages
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN&what=api
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN&what=nav
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN&what=opcache
 *   https://bilete.online/clear-cache.php?token=YOUR_TOKEN&what=bundle
 *
 *   ?format=json    → JSON response instead of HTML page (for CI scripts)
 *
 * Auth: shared CACHE_BUST_TOKEN from includes/config.php. Constant-time
 * compare so the endpoint can't be probed via timing. Without a valid
 * token the response is 401 and nothing is touched.
 *
 * What it clears (when what=all, the default):
 *   1. includes/cache/pages/*.html         — full HTML output cache
 *   2. includes/cache/*.json               — nav-counts + featured + categories
 *   3. sys_get_temp_dir()/bileteonline_cache/*.json  — api_cached() entries
 *   4. /tmp/bileteonline_backend_unreachable.flag    — backend reachability flag
 *   5. assets/js/core-bundle.js                      — JS bundle (head.php rebuilds it)
 *   6. opcache_reset() if the extension is available — PHP bytecode cache
 */

require_once __DIR__ . '/includes/config.php';

$wantsJson = ($_GET['format'] ?? '') === 'json';

// ─── AUTH ───────────────────────────────────────────────────────
$expectedToken = defined('CACHE_BUST_TOKEN') ? CACHE_BUST_TOKEN : '';
$providedToken = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_CACHE_BUST_TOKEN'] ?? '');

if ($expectedToken === '' || str_contains($expectedToken, 'REPLACE_ME')) {
    http_response_code(503);
    header($wantsJson ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/plain; charset=utf-8');
    echo $wantsJson
        ? json_encode(['error' => 'CACHE_BUST_TOKEN not configured on this site'])
        : 'CACHE_BUST_TOKEN is not configured on this site. Set it in includes/config.php (or via env) before using this endpoint.';
    exit;
}

if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    header($wantsJson ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/plain; charset=utf-8');
    echo $wantsJson
        ? json_encode(['error' => 'unauthorized — missing or wrong ?token'])
        : "Unauthorized. Pass ?token=... matching CACHE_BUST_TOKEN.\nAlternatively send the same value as the X-Cache-Bust-Token header.";
    exit;
}

// ─── PLAN ───────────────────────────────────────────────────────
$what = strtolower(trim((string) ($_GET['what'] ?? 'all')));
$allowed = ['all', 'pages', 'nav', 'api', 'opcache', 'bundle', 'flag'];
if (!in_array($what, $allowed, true)) {
    http_response_code(400);
    header($wantsJson ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/plain; charset=utf-8');
    echo $wantsJson
        ? json_encode(['error' => 'unknown what= value', 'allowed' => $allowed])
        : 'Unknown ?what= value. Allowed: ' . implode(', ', $allowed);
    exit;
}

$do = [
    'pages'   => in_array($what, ['all', 'pages'], true),
    'nav'     => in_array($what, ['all', 'nav'], true),
    'api'     => in_array($what, ['all', 'api'], true),
    'flag'    => in_array($what, ['all', 'flag'], true),
    'bundle'  => in_array($what, ['all', 'bundle'], true),
    'opcache' => in_array($what, ['all', 'opcache'], true),
];

// ─── HELPERS ────────────────────────────────────────────────────
$results = [];
$totalRemoved = 0;
$totalBytes   = 0;

$wipeGlob = function (string $pattern, string $label) use (&$results, &$totalRemoved, &$totalBytes) {
    $count = 0;
    $bytes = 0;
    $errors = 0;
    foreach (glob($pattern) ?: [] as $f) {
        if (!is_file($f)) continue;
        $sz = @filesize($f) ?: 0;
        if (@unlink($f)) {
            $count++;
            $bytes += $sz;
        } else {
            $errors++;
        }
    }
    $results[] = [
        'target'  => $label,
        'pattern' => $pattern,
        'removed' => $count,
        'bytes'   => $bytes,
        'errors'  => $errors,
    ];
    $totalRemoved += $count;
    $totalBytes   += $bytes;
};

$dropFile = function (string $path, string $label) use (&$results, &$totalRemoved, &$totalBytes) {
    if (!file_exists($path)) {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 0, 'bytes' => 0, 'errors' => 0, 'note' => 'not present'];
        return;
    }
    $sz = @filesize($path) ?: 0;
    if (@unlink($path)) {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 1, 'bytes' => $sz, 'errors' => 0];
        $totalRemoved += 1;
        $totalBytes   += $sz;
    } else {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 0, 'bytes' => 0, 'errors' => 1];
    }
};

// ─── EXECUTE ────────────────────────────────────────────────────
$root = __DIR__;

if ($do['pages']) {
    $wipeGlob($root . '/includes/cache/pages/*.html', 'Page cache (HTML)');
}

if ($do['nav']) {
    // nav-counts.json + featured-cities.json + event-categories.json +
    // trending-events.json + featured-venues.json + venue-categories.json +
    // event-genres.json — every json directly inside includes/cache/
    $wipeGlob($root . '/includes/cache/*.json', 'Nav/feature cache (JSON)');
}

if ($do['api']) {
    // api_cached() drops files into sys_get_temp_dir()/bileteonline_cache/
    $tmpCacheDir = sys_get_temp_dir() . '/bileteonline_cache';
    $wipeGlob($tmpCacheDir . '/*.json', 'API response cache (tmp)');
}

if ($do['flag']) {
    $dropFile(sys_get_temp_dir() . '/bileteonline_backend_unreachable.flag', 'Backend reachability flag');
}

if ($do['bundle']) {
    // Removing this is harmless — head.php auto-rebuilds it on next request
    // by concatenating config.js + utils.js + api.js + auth.js. Doing it
    // explicitly is useful when only a source file's mtime changed in a way
    // PHP didn't detect (e.g. rsync preserving mtime from CI).
    $dropFile($root . '/assets/js/core-bundle.js', 'Core JS bundle');
}

$opcacheResult = null;
if ($do['opcache']) {
    if (function_exists('opcache_reset')) {
        $opcacheResult = @opcache_reset() ? 'reset' : 'reset_failed';
    } else {
        $opcacheResult = 'extension_not_loaded';
    }
    $results[] = ['target' => 'OPcache (PHP bytecode)', 'pattern' => 'opcache_reset()', 'removed' => 0, 'bytes' => 0, 'errors' => 0, 'note' => $opcacheResult];
}

// ─── RESPONSE ───────────────────────────────────────────────────
$payload = [
    'success'        => true,
    'what'           => $what,
    'totals'         => [
        'files_removed' => $totalRemoved,
        'bytes_freed'   => $totalBytes,
    ],
    'results'        => $results,
    'opcache'        => $opcacheResult,
    'server_time'    => date('c'),
];

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// HTML response — minimal but readable
header('Content-Type: text/html; charset=utf-8');
$humanBytes = function (int $b): string {
    if ($b < 1024) return $b . ' B';
    if ($b < 1024 * 1024) return number_format($b / 1024, 1) . ' KB';
    return number_format($b / 1024 / 1024, 2) . ' MB';
};
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Cache cleared — bilete.online</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;max-width:880px;margin:40px auto;padding:0 20px;color:#1B1714;background:#F5EFE6}
        h1{margin:0 0 8px;font-size:24px}
        p.summary{font-size:18px;margin:0 0 24px;color:#5A4F46}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        th,td{text-align:left;padding:10px 14px;border-bottom:1px solid #E8DFCF;font-size:14px}
        th{background:#1B1714;color:#F5EFE6;font-weight:600;text-transform:uppercase;letter-spacing:.5px;font-size:12px}
        tr:last-child td{border-bottom:0}
        td.num{text-align:right;font-variant-numeric:tabular-nums}
        td.target{font-weight:600}
        td.pattern{font-family:'SF Mono',Consolas,monospace;font-size:12px;color:#5A4F46;word-break:break-all}
        .badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600}
        .badge-zero{background:#E8DFCF;color:#5A4F46}
        .badge-pos{background:#1B1714;color:#F5EFE6}
        .badge-err{background:#C2410C;color:#fff}
        .note{font-size:12px;color:#5A4F46;font-style:italic}
        .links{margin-top:24px;padding:14px;background:#fff;border-radius:8px;border:1px solid #E8DFCF;font-size:13px}
        .links a{color:#C2410C;text-decoration:none;margin-right:14px}
        .links a:hover{text-decoration:underline}
    </style>
</head>
<body>
    <h1>Cache cleared — bilete.online</h1>
    <p class="summary">
        Removed <strong><?= number_format($totalRemoved) ?></strong> file(s),
        freed <strong><?= $humanBytes($totalBytes) ?></strong>.
        Target: <code><?= htmlspecialchars($what) ?></code>.
    </p>
    <table>
        <thead>
            <tr>
                <th>Target</th>
                <th>Path / pattern</th>
                <th style="text-align:right">Files</th>
                <th style="text-align:right">Size</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td class="target"><?= htmlspecialchars($r['target']) ?></td>
                    <td class="pattern"><?= htmlspecialchars($r['pattern']) ?></td>
                    <td class="num">
                        <?php $rv = $r['removed']; ?>
                        <span class="badge <?= $rv > 0 ? 'badge-pos' : 'badge-zero' ?>"><?= $rv ?></span>
                    </td>
                    <td class="num"><?= $r['bytes'] ? $humanBytes($r['bytes']) : '—' ?></td>
                    <td>
                        <?php if (!empty($r['errors']) && $r['errors'] > 0): ?>
                            <span class="badge badge-err"><?= $r['errors'] ?> error(s)</span>
                        <?php endif; ?>
                        <?php if (!empty($r['note'])): ?>
                            <span class="note"><?= htmlspecialchars($r['note']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="links">
        Granular wipes (token is preserved in current URL):
        <?php
        $base = '/clear-cache.php?token=' . urlencode($providedToken);
        $opts = ['all', 'pages', 'nav', 'api', 'opcache', 'bundle', 'flag'];
        ?>
        <?php foreach ($opts as $opt): ?>
            <a href="<?= $base ?>&what=<?= $opt ?>"><?= $opt ?></a>
        <?php endforeach; ?>
        <br><br>
        JSON: <a href="<?= $base ?>&format=json&what=<?= htmlspecialchars($what) ?>">same response as JSON</a>
    </div>
</body>
</html>
