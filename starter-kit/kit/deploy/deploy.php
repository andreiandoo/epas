<?php
/**
 * deploy.php — pull the built site from GitHub. No git binary, no shell.
 *
 * For hosts where you have FTP/File-Manager but no SSH, and where exec() is
 * disabled (so _webhook-deploy.php, which shells out to git, cannot work).
 * It downloads the deploy branch as an archive over HTTPS and syncs it into
 * this directory.
 *
 * ── Setup (once) ────────────────────────────────────────────────────────────
 * Upload this file and a sibling `.deploy-config.php` to the site root:
 *
 *   <?php return [
 *       'secret' => 'a-long-random-string',   // required
 *       'repo'   => 'andreiandoo/epas',
 *       'branch' => 'parc',
 *       // 'token'    => 'ghp_…',             // only if the repo is private
 *       // 'preserve' => ['uploads'],         // extra paths sync must not touch
 *   ];
 *
 * The leading dot matters: the shipped .htaccess denies dotfiles, so the secret
 * is not readable over the web. NEVER commit that file — this branch is public.
 *
 * ── Use ─────────────────────────────────────────────────────────────────────
 *   https://site/deploy.php?token=SECRET&dry=1    see what would change
 *   https://site/deploy.php?token=SECRET          apply
 *
 * Sync is additive-plus-prune: every file in the archive is written, and files
 * under this root that are no longer in the archive are removed — except the
 * preserve list (this script's own config, .well-known, cgi-bin, logs, php.ini
 * and anything you add). It refuses to run if the archive does not look like a
 * built kit site, so a bad download cannot empty the site.
 */
declare(strict_types=1);

@set_time_limit(300);
@ini_set('memory_limit', '256M');

$ROOT = __DIR__;
$CONFIG_FILE = $ROOT . '/.deploy-config.php';

/** Paths (relative to root) sync must never create, overwrite or delete. */
const ALWAYS_PRESERVE = [
    '.deploy-config.php', '.deploy-token', '.well-known', 'cgi-bin',
    '.htpasswd', 'php.ini', '.user.ini', 'error_log', '.ftpquota', 'tmp',
    // Written locally at the end of every run. It also travels in the archive,
    // so without this a no-op deploy would always report one changed file.
    '.deploy-timestamp',
];

/** Files the archive MUST contain, or we assume the download is broken. */
const SANITY_FILES = ['index.php', 'site.config.php', 'routes.php'];

/**
 * Refuse an archive larger than this. A built kit site is a few hundred KB;
 * pointing `branch` at a source branch by mistake pulls the whole application
 * and exhausts memory during extraction before any sanity check can run.
 */
const MAX_ARCHIVE_BYTES = 50 * 1024 * 1024;

$isCli = PHP_SAPI === 'cli';
$out = ['ok' => false, 'steps' => []];
$step = function (string $msg, array $extra = []) use (&$out) {
    $out['steps'][] = $extra ? [$msg => $extra] : $msg;
};

// ── config ──────────────────────────────────────────────────────────────────
if (!is_file($CONFIG_FILE)) {
    respond(503, [
        'ok' => false,
        'error' => 'Missing .deploy-config.php next to deploy.php.',
        'create' => ".deploy-config.php  →  <?php return ['secret'=>'…','repo'=>'owner/repo','branch'=>'parc'];",
    ], $isCli);
}
$cfg = require $CONFIG_FILE;
if (!is_array($cfg) || empty($cfg['secret']) || empty($cfg['repo']) || empty($cfg['branch'])) {
    respond(503, ['ok' => false, 'error' => '.deploy-config.php must return secret, repo and branch.'], $isCli);
}

// ── auth ────────────────────────────────────────────────────────────────────
$given = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($argv[1] ?? '')));
if (!hash_equals((string) $cfg['secret'], $given)) {
    respond(403, ['ok' => false, 'error' => 'forbidden'], $isCli);
}

$dry = isset($_GET['dry']) || in_array('--dry', $argv ?? [], true);
$repo = (string) $cfg['repo'];
$branch = (string) $cfg['branch'];
$token = (string) ($cfg['token'] ?? '');
$preserve = array_merge(ALWAYS_PRESERVE, (array) ($cfg['preserve'] ?? []));

// ── download + extract ──────────────────────────────────────────────────────
$tmp = $ROOT . '/.deploy-tmp';
rrmdir($tmp);
if (!@mkdir($tmp, 0755, true)) {
    respond(500, ['ok' => false, 'error' => "Cannot create $tmp — is the site root writable?"], $isCli);
}

$src = null;
$errors = [];

// Preferred: zip (ZipArchive is present on virtually every cPanel host).
if (class_exists('ZipArchive')) {
    $zip = "$tmp/src.zip";
    if (download("https://codeload.github.com/$repo/zip/refs/heads/$branch", $zip, $token, $errors)) {
        $za = new ZipArchive();
        if ($za->open($zip) === true) {
            $za->extractTo($tmp);
            $za->close();
            $src = firstDir($tmp);
            $step('extracted via ZipArchive', ['bytes' => filesize($zip)]);
        } else {
            $errors[] = 'ZipArchive could not open the download';
        }
    }
}

// Fallback: tar.gz via PharData (phar is enabled far more often than zip is not).
if ($src === null && class_exists('PharData')) {
    $tgz = "$tmp/src.tar.gz";
    if (download("https://codeload.github.com/$repo/tar.gz/refs/heads/$branch", $tgz, $token, $errors)) {
        try {
            $phar = new PharData($tgz);
            $phar->decompress();                       // → src.tar
            (new PharData("$tmp/src.tar"))->extractTo($tmp, null, true);
            $src = firstDir($tmp);
            $step('extracted via PharData', ['bytes' => filesize($tgz)]);
        } catch (Throwable $e) {
            $errors[] = 'PharData: ' . $e->getMessage();
        }
    }
}

if ($src === null) {
    rrmdir($tmp);
    respond(500, ['ok' => false, 'error' => 'Could not download or extract the branch archive.',
                  'detail' => $errors,
                  'hint' => 'Needs ZipArchive or Phar, plus outbound HTTPS to codeload.github.com.'], $isCli);
}

// ── sanity: does this look like a built kit site? ───────────────────────────
foreach (SANITY_FILES as $must) {
    if (!is_file("$src/$must")) {
        rrmdir($tmp);
        respond(500, ['ok' => false, 'error' => "Archive is missing $must — refusing to sync.",
                      'hint' => "Is '$branch' the deploy branch (built site), not a source branch?"], $isCli);
    }
}

// ── plan ────────────────────────────────────────────────────────────────────
$incoming = relFiles($src);                     // rel path => absolute source
$existing = relFiles($ROOT, array_merge($preserve, ['.deploy-tmp', 'deploy.php']));

$toWrite = [];
foreach ($incoming as $rel => $abs) {
    if (isPreserved($rel, $preserve)) continue;
    $dst = "$ROOT/$rel";
    if (!is_file($dst) || filesize($dst) !== filesize($abs) || sha1_file($dst) !== sha1_file($abs)) {
        $toWrite[$rel] = $abs;
    }
}
$toDelete = [];
foreach (array_keys($existing) as $rel) {
    if (!isset($incoming[$rel]) && !isPreserved($rel, $preserve)) {
        $toDelete[] = $rel;
    }
}

$out['branch'] = $branch;
$out['files_in_archive'] = count($incoming);
$out['write'] = array_keys($toWrite);
$out['delete'] = $toDelete;

if ($dry) {
    rrmdir($tmp);
    $out['ok'] = true;
    $out['dry_run'] = true;
    respond(200, $out, $isCli);
}

// ── apply ───────────────────────────────────────────────────────────────────
$written = 0;
foreach ($toWrite as $rel => $abs) {
    $dst = "$ROOT/$rel";
    $dir = dirname($dst);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $out['failed'][] = "mkdir $dir";
        continue;
    }
    if (@copy($abs, $dst)) { $written++; } else { $out['failed'][] = "copy $rel"; }
}
$deleted = 0;
foreach ($toDelete as $rel) {
    if (@unlink("$ROOT/$rel")) { $deleted++; } else { $out['failed'][] = "unlink $rel"; }
}
pruneEmptyDirs($ROOT, $preserve);
rrmdir($tmp);

if (function_exists('opcache_reset')) { @opcache_reset(); $step('opcache reset'); }

$out['ok'] = empty($out['failed']);
$out['written'] = $written;
$out['deleted'] = $deleted;
$out['time'] = gmdate('c');
@file_put_contents($ROOT . '/.deploy-timestamp', $out['time'] . " ($branch, $written written, $deleted deleted)\n");
respond($out['ok'] ? 200 : 500, $out, $isCli);

/* ── helpers ─────────────────────────────────────────────────────────────── */

function download(string $url, string $dest, string $token, array &$errors): bool
{
    $headers = ['User-Agent: kit-deploy', 'Accept: application/octet-stream'];
    if ($token !== '') { $headers[] = 'Authorization: token ' . $token; }

    if (function_exists('curl_init')) {
        $fh = @fopen($dest, 'w');
        if (!$fh) { $errors[] = "cannot write $dest"; return false; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true,
            // Abort the moment the body exceeds the cap, instead of spending the
            // full timeout pulling a source branch we would refuse anyway. On a
            // shared host that wait would hit max_execution_time first.
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($ch, $dlTotal, $dlNow) {
                return ($dlTotal > MAX_ARCHIVE_BYTES || $dlNow > MAX_ARCHIVE_BYTES) ? 1 : 0;
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($ok && $code === 200 && filesize($dest) > 0) { return sizeOk($dest, $errors); }
        // libcurl wording varies by version ("Callback aborted",
        // "Operation was aborted by an application callback"), so match loosely.
        $aborted = stripos($err, 'abort') !== false;
        $errors[] = $aborted
            ? sprintf('archive exceeds the %d MB limit — is `branch` really the deploy branch?', MAX_ARCHIVE_BYTES / 1048576)
            : "download $url → HTTP $code" . ($err ? " ($err)" : '');
        @unlink($dest);
        return false;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 180]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) > 0 && @file_put_contents($dest, $data) !== false) {
            unset($data);
            return sizeOk($dest, $errors);
        }
        $errors[] = "file_get_contents failed for $url";
        return false;
    }

    $errors[] = 'neither curl nor allow_url_fopen is available';
    return false;
}

/** Guard against extracting a source branch by mistake (see MAX_ARCHIVE_BYTES). */
function sizeOk(string $file, array &$errors): bool
{
    $size = (int) filesize($file);
    if ($size <= MAX_ARCHIVE_BYTES) return true;
    $errors[] = sprintf(
        'archive is %.1f MB, over the %d MB limit — is `branch` really the deploy branch?',
        $size / 1048576, MAX_ARCHIVE_BYTES / 1048576
    );
    @unlink($file);
    return false;
}

/** GitHub archives wrap everything in one repo-sha directory. */
function firstDir(string $dir): ?string
{
    foreach ((array) @scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_dir("$dir/$f")) return "$dir/$f";
    }
    return null;
}

/** @return array<string,string> relative path => absolute path */
function relFiles(string $base, array $skipTop = []): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        $top = explode('/', $rel)[0];
        if (in_array($top, $skipTop, true)) continue;
        if ($file->isFile()) $out[$rel] = $file->getPathname();
    }
    return $out;
}

function isPreserved(string $rel, array $preserve): bool
{
    foreach ($preserve as $p) {
        if ($rel === $p || strpos($rel, rtrim($p, '/') . '/') === 0) return true;
    }
    return false;
}

function pruneEmptyDirs(string $base, array $preserve): void
{
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if (!$file->isDir()) continue;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        if (isPreserved($rel, $preserve)) continue;
        @rmdir($file->getPathname());   // only succeeds when empty
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach ((array) @scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

function respond(int $code, array $body, bool $isCli): void
{
    if ($isCli) {
        echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit($code === 200 ? 0 : 1);
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
