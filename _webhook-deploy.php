<?php
/**
 * GitHub Webhook Auto-Deploy (FTP-only servers)
 *
 * Descarcă ZIP-ul branch-ului marketplace și extrage fișierele.
 * Nu necesită SSH sau Git pe server.
 *
 * SETUP:
 * 1. Urcă acest fișier pe server: bilete.online/_webhook-deploy.php
 * 2. Schimbă DEPLOY_SECRET mai jos
 * 3. GitHub repo → Settings → Webhooks → Add webhook:
 *    - Payload URL: https://bilete.online/_webhook-deploy.php
 *    - Content type: application/json
 *    - Secret: (același cu DEPLOY_SECRET)
 *    - Events: Just the push event
 */

// ===================== CONFIGURATION =====================

// Secret key - SCHIMBĂ ASTA! Generează cu: https://randomkeygen.com/
define('DEPLOY_SECRET', 'CHANGE_THIS_TO_RANDOM_SECRET');

// GitHub repo details
define('GITHUB_USER', 'andreiandoo');
define('GITHUB_REPO', 'epas');
define('GITHUB_BRANCH', 'marketplace');

// Deploy path (unde să extragă fișierele)
define('DEPLOY_PATH', __DIR__);

// Fișiere/foldere care NU trebuie șterse la deploy (relative la DEPLOY_PATH)
// NOTĂ: .htaccess NU mai e protejat - se actualizează din repo
define('PRESERVE_FILES', [
    '_webhook-deploy.php',
    'deploy.log',
    'wp-config.php',      // dacă ai WordPress
    'configuration.php',  // dacă ai Joomla
    'uploads',            // foldere cu upload-uri
    'media',
]);

// Log file
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
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: PHP Deploy Script\r\n",
            'timeout' => 120
        ]
    ]);

    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        return false;
    }

    return file_put_contents($destination, $content) !== false;
}

function deleteDirectory($dir, $preserve = []) {
    if (!is_dir($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $relativePath = str_replace(DEPLOY_PATH . DIRECTORY_SEPARATOR, '', $path);

        // Skip preserved files
        foreach ($preserve as $preserved) {
            if (strpos($relativePath, $preserved) === 0 || $relativePath === $preserved) {
                continue 2;
            }
        }

        if (is_dir($path)) {
            deleteDirectory($path, $preserve);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function copyDirectory($src, $dst, $preserve = []) {
    if (!is_dir($src)) return;

    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;

        // Skip preserved files in destination
        $relativePath = str_replace(DEPLOY_PATH . DIRECTORY_SEPARATOR, '', $dstPath);
        foreach ($preserve as $preserved) {
            if ($relativePath === $preserved || strpos($relativePath, $preserved . DIRECTORY_SEPARATOR) === 0) {
                continue 2;
            }
        }

        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath, $preserve);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

function cleanupTemp($path) {
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            cleanupTemp($itemPath);
        } else {
            @unlink($itemPath);
        }
    }
    @rmdir($path);
}

// ===================== STATUS PAGE (GET) =====================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Deploy Status - bilete.online</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
            .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; margin-top: 0; }
            .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; }
            .status.active { background: #d4edda; color: #155724; }
            pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; max-height: 400px; overflow-y: auto; }
            .info { color: #666; font-size: 14px; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; border-radius: 6px; text-decoration: none; font-weight: 500; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>🚀 Deploy Webhook <span class="status active">Active</span></h1>
            <p class="info">
                <strong>Branch:</strong> <?= GITHUB_BRANCH ?><br>
                <strong>Repo:</strong> <?= GITHUB_USER ?>/<?= GITHUB_REPO ?><br>
                <strong>Deploy path:</strong> <?= DEPLOY_PATH ?>
            </p>
            <a href="?test=1" class="btn">Test Manual Deploy</a>
        </div>

        <?php if (file_exists(LOG_FILE)): ?>
        <div class="card">
            <h2>📋 Recent Logs</h2>
            <pre><?= htmlspecialchars(substr(file_get_contents(LOG_FILE), -10000)) ?></pre>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>⚙️ Webhook Setup</h2>
            <p class="info">Add this webhook in GitHub repo settings:</p>
            <pre>
Payload URL: <?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>

Content type: application/json
Secret: [your DEPLOY_SECRET value]
Events: Just the push event</pre>
        </div>
    </body>
    </html>
    <?php

    // Manual test deploy
    if (isset($_GET['test']) && $_GET['test'] === '1') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Continue to deploy logic below
    } else {
        exit;
    }
}

// ===================== WEBHOOK HANDLER (POST) =====================

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$isManualTest = isset($_GET['test']);

logMsg("=== Deploy started ===");

// Verify signature (skip for manual test)
if (!$isManualTest && DEPLOY_SECRET !== 'CHANGE_THIS_TO_RANDOM_SECRET') {
    if (!verifySignature($payload, $signature)) {
        logMsg("Invalid signature", 'ERROR');
    }
}

// Parse webhook data
$data = json_decode($payload, true);
$branch = '';

if ($data) {
    $ref = $data['ref'] ?? '';
    $branch = str_replace('refs/heads/', '', $ref);
    $pusher = $data['pusher']['name'] ?? 'manual';
    logMsg("Triggered by: $pusher, Branch: $branch");

    if ($branch !== GITHUB_BRANCH && !$isManualTest) {
        logMsg("Ignoring push to branch: $branch");
        echo json_encode(['success' => true, 'message' => 'Ignored: wrong branch']);
        exit;
    }
}

// Download ZIP from GitHub
$zipUrl = "https://github.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/archive/refs/heads/" . GITHUB_BRANCH . ".zip";
$zipFile = sys_get_temp_dir() . '/deploy_' . time() . '.zip';

logMsg("Downloading: $zipUrl");

if (!downloadFile($zipUrl, $zipFile)) {
    logMsg("Failed to download ZIP from GitHub", 'ERROR');
}

logMsg("Downloaded to: $zipFile (" . round(filesize($zipFile) / 1024) . " KB)");

// Extract ZIP
$zip = new ZipArchive();
$extractPath = sys_get_temp_dir() . '/deploy_extract_' . time();

if ($zip->open($zipFile) !== true) {
    @unlink($zipFile);
    logMsg("Failed to open ZIP file", 'ERROR');
}

$zip->extractTo($extractPath);
$zip->close();
@unlink($zipFile);

logMsg("Extracted to: $extractPath");

// Find the extracted folder (GitHub adds repo-branch prefix)
$extractedFolder = $extractPath . '/' . GITHUB_REPO . '-' . GITHUB_BRANCH;

if (!is_dir($extractedFolder)) {
    // Try to find any folder
    $items = scandir($extractPath);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) {
            $extractedFolder = $extractPath . '/' . $item;
            break;
        }
    }
}

if (!is_dir($extractedFolder)) {
    cleanupTemp($extractPath);
    logMsg("Extracted folder not found", 'ERROR');
}

logMsg("Source folder: $extractedFolder");

// Count files to deploy
$fileCount = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedFolder));
foreach ($iterator as $file) {
    if ($file->isFile()) $fileCount++;
}
logMsg("Files to deploy: $fileCount");

// Clean old files (preserving configured files)
logMsg("Cleaning old files...");
deleteDirectory(DEPLOY_PATH, PRESERVE_FILES);

// Copy new files
logMsg("Copying new files...");
copyDirectory($extractedFolder, DEPLOY_PATH, PRESERVE_FILES);

// Cleanup temp
cleanupTemp($extractPath);

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    logMsg("OPcache cleared");
}

logMsg("=== Deploy completed successfully! ===");

// Response
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Deployed successfully',
    'files' => $fileCount,
    'branch' => GITHUB_BRANCH
]);
