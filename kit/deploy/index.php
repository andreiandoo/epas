<?php
/**
 * Front controller (deployed site root).
 *
 * Maps a clean URL to templates' pages/<name>.php. Route aliases (RO slugs +
 * {slug} captures) live in routes.php next to this file so a template can add
 * its own without editing the controller. Static assets (/theme, /kit) are
 * served by .htaccess before this runs.
 */
$root   = __DIR__;
$routes = is_file($root . '/routes.php') ? require $root . '/routes.php' : [];

// Where /theme and /kit actually live, so kit_asset_v() can stamp asset URLs
// with their mtime and defeat the seven-day cache the .htaccess sets.
define('KIT_SITE_ROOT', $root);

// Apache's ErrorDocument does an INTERNAL redirect: REQUEST_URI still holds the
// URL that failed, and the status lands in REDIRECT_STATUS. Without this the
// `ErrorDocument 403 /403` lines in .htaccess were dead — a denied path fell
// through to the name-based fallback below and rendered the 404 page with a 404
// status, so a blocked resource looked merely missing.
$redirectStatus = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);
if (in_array($redirectStatus, [403, 500, 503], true)) {
    $errPage = $root . '/pages/' . $redirectStatus . '.php';
    if (is_file($errPage)) {
        http_response_code($redirectStatus);
        require $errPage;
        exit;
    }
}

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = trim($uri, '/');

// 1) exact alias  ('' => 'index', 'repertoriu' => 'repertoire')
$name = null;
if (isset($routes['exact'][$path === '' ? '/' : $uri])) {
    $name = $routes['exact'][$path === '' ? '/' : $uri];
} else {
    // 2) prefix capture  ('spectacol/{slug}' => 'show')
    foreach (($routes['capture'] ?? []) as $prefix => $target) {
        if (strpos($path, $prefix . '/') === 0) {
            $_GET['slug'] = basename($path);
            $name = $target;
            break;
        }
    }
}
// 3) direct page name fallback
if ($name === null) $name = $path === '' ? 'index' : preg_replace('/[^a-z0-9\-_]/i', '', $path);

$page = $root . '/pages/' . $name . '.php';
if (is_file($page)) { require $page; exit; }

http_response_code(404);
$e404 = $root . '/pages/404.php';
if (is_file($e404)) require $e404; else echo '404';
