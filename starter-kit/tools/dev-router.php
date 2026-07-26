<?php
/**
 * Dev router for `php -S` — serves a template's pages + theme assets locally so
 * the kit can be previewed/screenshotted without a real vhost.
 *
 *   KIT_SITE=teatru KIT_FIXTURES=$PWD/fixtures php -S 127.0.0.1:8899 tools/dev-router.php
 *
 * Routes:
 *   /theme/tokens.css → kit/tokens/tokens.css
 *   /theme/theme.css  → templates/<site>/theme.css
 *   /kit/kit.js       → kit/js/kit.js
 *   /<name>[/...]     → templates/<site>/pages/<name>.php  (default: index)
 */
$root = dirname(__DIR__);
$site = getenv('KIT_SITE') ?: 'teatru';
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$map = [
    '/theme/tokens.css' => [$root . '/kit/tokens/tokens.css', 'text/css'],
    '/theme/theme.css'  => [$root . "/templates/$site/theme.css", 'text/css'],
    '/kit/kit.js'       => [$root . '/kit/js/kit.js', 'application/javascript'],
];
if (isset($map[$uri])) {
    [$file, $mime] = $map[$uri];
    if (is_file($file)) { header("Content-Type: $mime"); readfile($file); return true; }
    http_response_code(404); return true;
}

// Serve any static kit asset (kit.js, vendor/alpine.min.js, ...).
if (strpos($uri, '/kit/') === 0 && preg_match('/\.(js|css|svg|png|woff2?)$/', $uri)) {
    $file = $root . $uri;
    if (is_file($file)) {
        $mimes = ['js' => 'application/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml'];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($file); return true;
    }
    http_response_code(404); return true;
}

// Client gateway: boot the active site, then hand off to the kit proxy.
if ($uri === '/api/proxy.php') {
    require_once $root . '/kit/core/config.php';
    kit_boot(require $root . "/templates/$site/site.config.php");
    require $root . '/kit/proxy.php';
    return true;
}

// Use the template's real routes.php so preview == production routing.
$routes = is_file($root . "/templates/$site/routes.php") ? require $root . "/templates/$site/routes.php" : [];
$path = trim($uri, '/');
$name = null;
if (isset($routes['exact'][$uri]) || isset($routes['exact'][$path === '' ? '/' : $uri])) {
    $name = $routes['exact'][$uri] ?? $routes['exact']['/'];
} else {
    foreach (($routes['capture'] ?? []) as $prefix => $target) {
        if (strpos($path, $prefix . '/') === 0) { $_GET['slug'] = basename($path); $name = $target; break; }
    }
}
if ($name === null) $name = $path === '' ? 'index' : preg_replace('/[^a-z0-9\-_]/i', '', $path);

$page = $root . "/templates/$site/pages/$name.php";
if (is_file($page)) { require $page; return true; }
http_response_code(404);
echo "404: $name";
return true;
