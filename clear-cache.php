<?php
/**
 * Nuke-all cache endpoint.
 *
 * Single command that wipes every local cache the marketplace touches.
 * No auth, no options — just open https://bilete.online/clear-cache.php
 * in a browser (or curl it) and everything that can be cached locally
 * gets dropped.
 *
 * What gets cleared:
 *   1. includes/cache/pages/*.html         — full HTML output cache
 *   2. includes/cache/*.json               — nav-counts + featured + categories
 *   3. sys_get_temp_dir()/bileteonline_cache/*.json  — api_cached() entries
 *   4. /tmp/bileteonline_backend_unreachable.flag    — backend reachability flag
 *   5. assets/js/core-bundle.js                      — JS bundle (head.php rebuilds)
 *   6. opcache_reset() when the extension is loaded  — PHP bytecode cache
 *
 * Why no token: the file caches re-fill automatically within ~1 page view,
 * so the worst a hostile script could do is force a brief cache miss on
 * already-cold traffic. Not worth the friction of managing a secret.
 */

require_once __DIR__ . '/includes/config.php';

$root = __DIR__;
$results = [];
$totalRemoved = 0;
$totalBytes   = 0;

$wipeGlob = function (string $pattern, string $label) use (&$results, &$totalRemoved, &$totalBytes) {
    $count = 0;
    $bytes = 0;
    foreach (glob($pattern) ?: [] as $f) {
        if (!is_file($f)) continue;
        $sz = @filesize($f) ?: 0;
        if (@unlink($f)) {
            $count++;
            $bytes += $sz;
        }
    }
    $results[] = ['target' => $label, 'pattern' => $pattern, 'removed' => $count, 'bytes' => $bytes];
    $totalRemoved += $count;
    $totalBytes   += $bytes;
};

$dropFile = function (string $path, string $label) use (&$results, &$totalRemoved, &$totalBytes) {
    if (!file_exists($path)) {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 0, 'bytes' => 0, 'note' => 'not present'];
        return;
    }
    $sz = @filesize($path) ?: 0;
    if (@unlink($path)) {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 1, 'bytes' => $sz];
        $totalRemoved += 1;
        $totalBytes   += $sz;
    } else {
        $results[] = ['target' => $label, 'pattern' => $path, 'removed' => 0, 'bytes' => 0, 'note' => 'unlink failed'];
    }
};

// Page HTML cache
$wipeGlob($root . '/includes/cache/pages/*.html', 'Page cache (HTML)');

// Nav / featured / categories JSON cache
$wipeGlob($root . '/includes/cache/*.json', 'Nav + feature cache (JSON)');

// api_cached() responses live in /tmp/bileteonline_cache/
$wipeGlob(sys_get_temp_dir() . '/bileteonline_cache/*.json', 'API response cache (tmp)');

// Backend-unreachable flag
$dropFile(sys_get_temp_dir() . '/bileteonline_backend_unreachable.flag', 'Backend reachability flag');

// Core JS bundle (head.php concats config+utils+api+auth → core-bundle.js on next request)
$dropFile($root . '/assets/js/core-bundle.js', 'Core JS bundle');

// OPcache (PHP bytecode)
$opcacheStatus = 'extension not loaded';
if (function_exists('opcache_reset')) {
    $opcacheStatus = @opcache_reset() ? 'reset' : 'reset failed';
}
$results[] = ['target' => 'OPcache (PHP bytecode)', 'pattern' => 'opcache_reset()', 'removed' => 0, 'bytes' => 0, 'note' => $opcacheStatus];

// Render
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
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
        .note{font-size:12px;color:#5A4F46;font-style:italic}
    </style>
</head>
<body>
    <h1>Cache cleared — bilete.online</h1>
    <p class="summary">
        Removed <strong><?= number_format($totalRemoved) ?></strong> file(s),
        freed <strong><?= $humanBytes($totalBytes) ?></strong>.
    </p>
    <table>
        <thead>
            <tr>
                <th>Target</th>
                <th>Path / pattern</th>
                <th style="text-align:right">Files</th>
                <th style="text-align:right">Size</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td class="target"><?= htmlspecialchars($r['target']) ?></td>
                    <td class="pattern"><?= htmlspecialchars($r['pattern']) ?></td>
                    <td class="num">
                        <span class="badge <?= $r['removed'] > 0 ? 'badge-pos' : 'badge-zero' ?>"><?= $r['removed'] ?></span>
                    </td>
                    <td class="num"><?= $r['bytes'] ? $humanBytes($r['bytes']) : '—' ?></td>
                    <td><?php if (!empty($r['note'])): ?><span class="note"><?= htmlspecialchars($r['note']) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
