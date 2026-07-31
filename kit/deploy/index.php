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
