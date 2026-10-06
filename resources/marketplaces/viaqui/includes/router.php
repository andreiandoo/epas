<?php
/**
 * Viaqui — URL router for nginx.
 *
 * viaqui.com runs on nginx (Ploi), which ignores .htaccess. Its default site config sends every URL that is not a file
 * to /index.php, and index.php requires this file first. The router applies the RewriteRule / RewriteCond lines of the
 * root .htaccess (still the single place where routes are declared) and then either redirects or runs the target page.
 *
 * Must be required from the global scope: the target page is required from here, and pages rely on globals set by
 * includes/config.php.
 *
 * Supported: RewriteCond on %{REQUEST_FILENAME} (-f, -d, with a suffix) and %{REQUEST_URI} (regex), `!` negation, [NC];
 * RewriteRule flags L, QSA, R=30x, NE, F, NC and the `-` (no substitution) target. Conditions are ANDed ([OR] is not used).
 */

$__vqPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($__vqPath === '' || $__vqPath === '/' || $__vqPath === '/index.php') {
    return; // the homepage: index.php carries on
}

$__vqRoot = dirname(__DIR__);
$__vqRel = ltrim($__vqPath, '/');
$__vqQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');

/** Parses the rewrite lines of an .htaccess into [[conds, pattern, target, flags], ...]. */
$__vqParse = static function (string $file, string $prefix = ''): array {
    $rules = [];
    $conds = [];
    foreach (@file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        if ($parts[0] === 'RewriteCond' && isset($parts[2])) {
            $conds[] = [$parts[1], $parts[2], strtoupper($parts[3] ?? '')];
        } elseif ($parts[0] === 'RewriteRule' && isset($parts[2])) {
            $flags = isset($parts[3]) ? explode(',', trim($parts[3], '[]')) : [];
            $rules[] = [$conds, $parts[1], $parts[2], $flags, $prefix];
            $conds = [];
        }
    }
    return $rules;
};

$__vqRules = $__vqParse($__vqRoot . '/.htaccess');
if (strncmp($__vqRel, 'api/', 4) === 0) {
    // api/.htaccess declares its own pretty URLs, relative to /api/
    $__vqRules = array_merge($__vqParse($__vqRoot . '/api/.htaccess', 'api/'), $__vqRules);
}

$__vqTarget = null;
foreach ($__vqRules as [$__vqConds, $__vqPattern, $__vqSubst, $__vqFlags, $__vqPrefix]) {
    $__vqSubject = $__vqRel;
    if ($__vqPrefix !== '') {
        if (strncmp($__vqRel, $__vqPrefix, strlen($__vqPrefix)) !== 0) {
            continue;
        }
        $__vqSubject = substr($__vqRel, strlen($__vqPrefix));
    }
    $__vqNot = $__vqPattern[0] === '!';
    $__vqRegex = "\x01" . ($__vqNot ? substr($__vqPattern, 1) : $__vqPattern) . "\x01" . (in_array('NC', $__vqFlags, true) ? 'i' : '');
    $__vqHit = @preg_match($__vqRegex, $__vqSubject, $__vqM) === 1;
    if ($__vqHit === $__vqNot) {
        continue;
    }

    $__vqOk = true;
    foreach ($__vqConds as [$__vqTest, $__vqCond, $__vqCondFlags]) {
        $__vqNeg = $__vqCond[0] === '!';
        $__vqCondBody = $__vqNeg ? substr($__vqCond, 1) : $__vqCond;
        if (strncmp($__vqTest, '%{REQUEST_FILENAME}', 19) === 0) {
            $__vqFile = $__vqRoot . '/' . rtrim($__vqRel, '/') . stripslashes(substr($__vqTest, 19));
            $__vqRes = $__vqCondBody === '-f' ? is_file($__vqFile) : ($__vqCondBody === '-d' ? is_dir($__vqFile) : false);
        } elseif ($__vqTest === '%{REQUEST_URI}') {
            $__vqRes = @preg_match("\x01" . $__vqCondBody . "\x01" . (strpos($__vqCondFlags, 'NC') !== false ? 'i' : ''), $__vqPath) === 1;
        } elseif ($__vqTest === '%{REQUEST_METHOD}') {
            $__vqRes = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === $__vqCondBody;
        } else {
            $__vqRes = false; // a condition this router does not know never matches, so its rule is skipped
        }
        if ($__vqRes === $__vqNeg) {
            $__vqOk = false;
            break;
        }
    }
    if (!$__vqOk) {
        continue;
    }

    if (in_array('F', $__vqFlags, true)) {
        http_response_code(403);
        exit;
    }
    if ($__vqSubst === '-') {
        break; // "leave the URL alone and stop": nothing serves it here
    }

    $__vqSubst = preg_replace_callback('/\$(\d)/', static function ($m) use ($__vqM) {
        return $__vqM[(int) $m[1]] ?? '';
    }, $__vqSubst);
    $__vqNewQuery = '';
    if (($__vqPos = strpos($__vqSubst, '?')) !== false) {
        $__vqNewQuery = substr($__vqSubst, $__vqPos + 1);
        $__vqSubst = substr($__vqSubst, 0, $__vqPos);
    }
    $__vqQsa = in_array('QSA', $__vqFlags, true);
    // mod_rewrite: a target without a query string keeps the original one; with one, the original is kept only on QSA
    $__vqFinalQuery = $__vqNewQuery === '' ? $__vqQuery : ($__vqQsa && $__vqQuery !== '' ? $__vqNewQuery . '&' . $__vqQuery : $__vqNewQuery);

    $__vqStatus = 0;
    foreach ($__vqFlags as $__vqFlag) {
        if (preg_match('/^R(?:=(\d{3}))?$/', $__vqFlag, $__vqRm)) {
            $__vqStatus = (int) ($__vqRm[1] ?? 302);
        }
    }
    if ($__vqStatus >= 300 && $__vqStatus < 400) {
        $__vqLocation = preg_match('#^(https?:)?/#', $__vqSubst) ? $__vqSubst : '/' . $__vqPrefix . $__vqSubst;
        header('Location: ' . $__vqLocation . ($__vqFinalQuery !== '' ? '?' . $__vqFinalQuery : ''), true, $__vqStatus);
        exit;
    }
    if ($__vqStatus === 200) {
        exit; // CORS preflight answer in api/.htaccess
    }

    $__vqCandidate = realpath($__vqRoot . '/' . $__vqPrefix . ltrim($__vqSubst, '/'));
    if ($__vqCandidate !== false && is_file($__vqCandidate) && strncmp($__vqCandidate, realpath($__vqRoot), strlen(realpath($__vqRoot))) === 0
        && strtolower(pathinfo($__vqCandidate, PATHINFO_EXTENSION)) === 'php') {
        $__vqTarget = $__vqCandidate;
        $_SERVER['QUERY_STRING'] = $__vqFinalQuery;
        parse_str($__vqFinalQuery, $_GET);
        $_REQUEST = array_merge($_GET, $_POST);
    }
    break;
}

if ($__vqTarget === null) {
    http_response_code(404);
    $__vqTarget = $__vqRoot . '/404.php';
}

$__vqScript = '/' . str_replace('\\', '/', ltrim(substr($__vqTarget, strlen(realpath($__vqRoot))), '\\/'));
$_SERVER['SCRIPT_FILENAME'] = $__vqTarget;
$_SERVER['SCRIPT_NAME'] = $__vqScript;
$_SERVER['PHP_SELF'] = $__vqScript;
chdir(dirname($__vqTarget));
require $__vqTarget;
exit;
