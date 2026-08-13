<?php
/**
 * Server-side deploy webhook (lives at the site root as _webhook-deploy.php).
 *
 * The deploy script force-pushes the built site to this host's branch, then
 * calls this endpoint to pull + swap. Protected by a shared secret:
 *   set KIT_DEPLOY_TOKEN in the host environment (or a .deploy-token file next
 *   to this script), and pass it as ?token=… (or X-Deploy-Token header).
 *
 * Adjust GIT / paths to your host (cPanel git deploy usually auto-pulls, in
 * which case this only needs to bust opcache).
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$expected = getenv('KIT_DEPLOY_TOKEN') ?: (is_file(__DIR__ . '/.deploy-token') ? trim((string)file_get_contents(__DIR__ . '/.deploy-token')) : '');
$given = $_GET['token'] ?? ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');
if ($expected === '' || !hash_equals($expected, (string)$given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$log = [];
$run = function (string $cmd) use (&$log) {
    $out = []; $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    $log[] = ['cmd' => $cmd, 'code' => $code, 'out' => array_slice($out, -20)];
    return $code === 0;
};

// Pull latest (fast-forward / hard reset to the deployed branch).
chdir(__DIR__);
if (is_dir(__DIR__ . '/.git')) {
    $run('git fetch --all --prune');
    $branch = trim((string)shell_exec('git rev-parse --abbrev-ref HEAD')) ?: 'main';
    $run('git reset --hard origin/' . escapeshellarg($branch));
    $run('git clean -fd');
}

// Bust PHP opcache so new code is served immediately.
if (function_exists('opcache_reset')) { @opcache_reset(); $log[] = 'opcache_reset'; }

echo json_encode(['ok' => true, 'time' => gmdate('c'), 'log' => $log], JSON_PRETTY_PRINT);
