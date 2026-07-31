<?php
/**
 * GitHub Webhook Auto-Deploy (FTP-only servers) — nordvale.tixello.ro
 *
 * Descarcă ZIP-ul branch-ului `nordvale` și extrage fișierele în web root.
 * Nu necesită SSH sau Git pe server (funcționează pe hosting FTP simplu).
 *
 * SETUP:
 * 1. Fișierul e deja în branch → ajunge pe server la /_webhook-deploy.php
 * 2. Schimbă DEPLOY_SECRET mai jos (randomkeygen.com)
 * 3. GitHub repo → Settings → Webhooks → Add webhook:
 *    - Payload URL: https://nordvale.tixello.ro/_webhook-deploy.php
 *    - Content type: application/json
 *    - Secret: (același cu DEPLOY_SECRET)
 *    - Events: Just the push event
 * 4. Prima dată poți declanșa manual: https://nordvale.tixello.ro/_webhook-deploy.php?test=1
 */

// ===================== CONFIGURATION =====================

// Secret key - SCHIMBĂ ASTA!
define('DEPLOY_SECRET', 'CHANGE_THIS_TO_RANDOM_SECRET');

define('GITHUB_USER', 'andreiandoo');
define('GITHUB_REPO', 'epas');
define('GITHUB_BRANCH', 'nordvale');

define('DEPLOY_PATH', __DIR__);

// Fișiere/foldere care NU trebuie șterse la deploy (relative la DEPLOY_PATH)
define('PRESERVE_FILES', [
    '_webhook-deploy.php',
    'deploy.log',
    'includes/config.local.php',  // secrete API locale (dacă există), nu din repo
    'data',
    'cache',
]);

define('LOG_FILE', __DIR__ . '/deploy.log');

// ===================== FUNCTIONS =====================

function logMsg($msg, $type = 'INFO') {
    $line = "[" . date('Y-m-d H:i:s') . "] [$type] $msg\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    if ($type === 'ERROR') {
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => $msg]));
    }
}

function verifySignature($payload, $signature) {
    if (empty($signature)) return false;
    $expected = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_SECRET);
    return hash_equals($expected, $signature);
}

function downloadFile($url, $destination) {
    $ch = curl_init($url);
    $fp = fopen($destination, 'w');
    if (!$fp) return false;
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'PHP Deploy Script',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$success || $httpCode >= 400) {
        @unlink($destination);
        return false;
    }
    return true;
}

function deleteDirectory($dir, $preserve = []) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $relativePath = str_replace(DEPLOY_PATH . DIRECTORY_SEPARATOR, '', $path);
        foreach ($preserve as $preserved) {
            if (strpos($relativePath, $preserved) === 0 || $relativePath === $preserved) {
                continue 2;
            }
        }
        if (is_dir($path)) { deleteDirectory($path, $preserve); @rmdir($path); }
        else { @unlink($path); }
    }
}

function copyDirectory($src, $dst, $preserve = []) {
    if (!is_dir($src)) return;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
        $relativePath = str_replace(DEPLOY_PATH . DIRECTORY_SEPARATOR, '', $dstPath);
        foreach ($preserve as $preserved) {
            if ($relativePath === $preserved || strpos($relativePath, $preserved . DIRECTORY_SEPARATOR) === 0) {
                continue 2;
            }
        }
        if (is_dir($srcPath)) { copyDirectory($srcPath, $dstPath, $preserve); }
        else { copy($srcPath, $dstPath); }
    }
}

function cleanupTemp($path) {
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (scandir($path) as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) cleanupTemp($itemPath); else @unlink($itemPath);
    }
    @rmdir($path);
}

// ===================== STATUS PAGE (GET) =====================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['test'])) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html><html><head><title>Deploy — nordvale.tixello.ro</title>
    <style>body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:20px;background:#f0ecdf}.card{background:#fffdf6;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 2px 4px rgba(0,0,0,.1)}pre{background:#09251d;color:#dffc62;padding:15px;border-radius:6px;overflow:auto;font-size:13px;max-height:400px}.btn{display:inline-block;padding:10px 20px;background:#09251d;color:#dffc62;border-radius:6px;text-decoration:none;font-weight:600}</style>
    </head><body>
    <div class="card"><h1>🌲 Deploy Webhook — Active</h1>
    <p><strong>Branch:</strong> <?= GITHUB_BRANCH ?><br><strong>Repo:</strong> <?= GITHUB_USER ?>/<?= GITHUB_REPO ?><br><strong>Path:</strong> <?= DEPLOY_PATH ?></p>
    <a href="?test=1" class="btn">Test Manual Deploy</a></div>
    <?php if (file_exists(LOG_FILE)): ?>
    <div class="card"><h2>📋 Log</h2><pre><?= htmlspecialchars(substr(file_get_contents(LOG_FILE), -10000)) ?></pre></div>
    <?php endif; ?>
    </body></html>
    <?php
    exit;
}

// ===================== WEBHOOK HANDLER (POST or ?test=1) =====================

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$isManualTest = isset($_GET['test']);

logMsg("=== Deploy started ===");

if (!$isManualTest && DEPLOY_SECRET !== 'CHANGE_THIS_TO_RANDOM_SECRET') {
    if (!verifySignature($payload, $signature)) {
        logMsg("Invalid signature", 'ERROR');
    }
}

$data = json_decode($payload, true);
if ($data) {
    $branch = str_replace('refs/heads/', '', $data['ref'] ?? '');
    logMsg("Triggered by: " . ($data['pusher']['name'] ?? 'manual') . ", Branch: $branch");
    if ($branch !== GITHUB_BRANCH && !$isManualTest) {
        echo json_encode(['success' => true, 'message' => 'Ignored: wrong branch']);
        exit;
    }
}

$zipUrl = "https://github.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/archive/refs/heads/" . GITHUB_BRANCH . ".zip";
$zipFile = sys_get_temp_dir() . '/nordvale_deploy_' . time() . '.zip';
logMsg("Downloading: $zipUrl");
if (!downloadFile($zipUrl, $zipFile)) logMsg("Failed to download ZIP from GitHub", 'ERROR');
logMsg("Downloaded (" . round(filesize($zipFile) / 1024) . " KB)");

$zip = new ZipArchive();
$extractPath = sys_get_temp_dir() . '/nordvale_extract_' . time();
if ($zip->open($zipFile) !== true) { @unlink($zipFile); logMsg("Failed to open ZIP", 'ERROR'); }
$zip->extractTo($extractPath);
$zip->close();
@unlink($zipFile);

$extractedFolder = $extractPath . '/' . GITHUB_REPO . '-' . GITHUB_BRANCH;
if (!is_dir($extractedFolder)) {
    foreach (scandir($extractPath) as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) { $extractedFolder = $extractPath . '/' . $item; break; }
    }
}
if (!is_dir($extractedFolder)) { cleanupTemp($extractPath); logMsg("Extracted folder not found", 'ERROR'); }

logMsg("Cleaning old files...");
deleteDirectory(DEPLOY_PATH, PRESERVE_FILES);
logMsg("Copying new files...");
copyDirectory($extractedFolder, DEPLOY_PATH, PRESERVE_FILES);
cleanupTemp($extractPath);

if (function_exists('opcache_reset')) { opcache_reset(); logMsg("OPcache cleared"); }
logMsg("=== Deploy completed successfully! ===");

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Deployed', 'branch' => GITHUB_BRANCH]);
