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

// Map a clean path to a page file. Support the teatru rewrites we use in demos.
$aliases = [
    '/' => 'index', '/repertoriu' => 'repertoire', '/program' => 'schedule',
    '/evenimente' => 'events', '/spectacol' => 'show',
];
$name = $aliases[$uri] ?? trim($uri, '/');
if ($name === '' ) $name = 'index';
// /spectacol/hamlet → show.php?slug=hamlet
if (strpos(trim($uri,'/'), 'spectacol/') === 0) { $_GET['slug'] = basename($uri); $name = 'show'; }
if (strpos(trim($uri,'/'), 'bilete/') === 0)    { $_GET['slug'] = basename($uri); $name = 'show'; }

$page = $root . "/templates/$site/pages/$name.php";
if (is_file($page)) { require $page; return true; }
http_response_code(404);
echo "404: $name";
return true;
