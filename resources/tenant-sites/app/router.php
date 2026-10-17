<?php
/**
 * Router: paginile comune (coș, checkout, cont client ...), paginile fixe ale
 * template-ului (template.json → routes), paginile din secțiuni (pages) și
 * adresele standard, redirecționate spre ale template-ului când diferă.
 */

/** Adresele standard folosite de paginile comune (shared/). */
const TS_STANDARD_LINKS = [
    'events' => '/program',
    'catalog' => '/repertoriu',
    'event' => '/spectacol/{slug}',
    'subscriptions' => '/abonamente',
    'artists' => '/trupa',
    'artist' => '/artist/{id}',
    'contact' => '/contact',
];

/** Paginile comune tuturor template-urilor. */
const TS_SHARED_ROUTES = [
    '/cos' => 'pages/cart.php',
    '/finalizare' => 'pages/checkout.php',
    '/confirmare' => 'pages/confirmare.php',
    '/autentificare' => 'pages/login.php',
    '/inregistrare' => 'pages/register.php',
    '/recuperare-parola' => 'pages/forgot-password.php',
    '/confidentialitate' => 'pages/privacy.php',
    '/termeni' => 'pages/terms.php',
    '/cookies' => 'pages/cookies.php',
    '/cont' => 'cont/index.php',
    '/api/proxy.php' => 'api/proxy.php',
];

/**
 * Fișiere statice, înainte de a căuta site-ul după host. Pe server nginx le
 * servește direct din public/ (workflow-ul copiază templates/X/assets în
 * public/tpl/X); aici e drumul pentru dezvoltare locală și pentru un fișier
 * de template încă necopiat.
 */
function ts_serve_static(): void {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (str_contains($path, '..')) {
        return;
    }
    $file = null;
    if (preg_match('#^/tpl/([a-z0-9-]+)/(.+)$#', $path, $m)) {
        $file = TS_TEMPLATES . '/' . $m[1] . '/assets/' . $m[2];
    } elseif (str_starts_with($path, '/assets/')) {
        $file = TS_ROOT . '/public' . $path;
    }
    if (!$file || !is_file($file)) {
        return;
    }
    $types = [
        'js' => 'text/javascript', 'mjs' => 'text/javascript', 'css' => 'text/css', 'json' => 'application/json',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'woff2' => 'font/woff2',
        'glb' => 'model/gltf-binary', 'gltf' => 'model/gltf+json', 'ktx2' => 'image/ktx2', 'mp4' => 'video/mp4',
        'webm' => 'video/webm', 'hdr' => 'application/octet-stream', 'wasm' => 'application/wasm',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) {
        return;
    }
    header('Content-Type: ' . $types[$ext]);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Access-Control-Allow-Origin: *');
    readfile($file);
    exit;
}

function ts_dispatch(): void {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $path = '/' . trim($path, '/');

    // Fișiere generate
    if ($path === '/robots.txt') {
        ts_robots();
    }
    if ($path === '/favicon.svg' || $path === '/favicon.ico') {
        ts_favicon();
    }

    // Pagini comune
    if (isset(TS_SHARED_ROUTES[$path])) {
        ts_run(TS_SHARED . '/' . TS_SHARED_ROUTES[$path]);
    }
    if (preg_match('#^/cont/([a-z0-9-]+)$#', $path, $m) && is_file(TS_SHARED . '/cont/' . $m[1] . '.php')) {
        ts_run(TS_SHARED . '/cont/' . $m[1] . '.php');
    }

    // Pagini din secțiuni (Acasă, Despre, Contact ...)
    foreach ((array) site('pages', []) as $key => $page) {
        if (($page['path'] ?? null) === $path) {
            ts_render_sections_page((string) $key);
            exit;
        }
    }

    // Pagini fixe ale template-ului, cu parametri {nume} în $_GET
    foreach ((array) manifest('routes', []) as $pattern => $file) {
        $params = ts_match($pattern, $path);
        if ($params !== null) {
            foreach ($params as $k => $v) {
                $_GET[$k] = $v;
            }
            ts_run(TEMPLATE_DIR . '/pages/' . basename($file));
        }
    }

    // Adrese standard pe care template-ul le are sub alt nume
    foreach (TS_STANDARD_LINKS as $key => $pattern) {
        $params = ts_match($pattern, $path);
        if ($params !== null) {
            $target = manifest('links.' . $key);
            if ($target && $target !== $pattern) {
                $qs = $_SERVER['QUERY_STRING'] ?? '';
                header('Location: ' . ts_link($key, $params) . ($qs !== '' ? '?' . $qs : ''), true, 301);
                exit;
            }
        }
    }

    ts_not_found();
}

/** Potrivește „/spectacol/{slug}” cu o cale; întoarce parametrii sau null. */
function ts_match(string $pattern, string $path): ?array {
    $regex = '#^' . preg_replace('#\\\\\{([a-z_]+)\\\\\}#', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#';
    if (!preg_match($regex, $path, $m)) {
        return null;
    }
    return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
}

/** Rulează o pagină PHP în scop global (paginile portate folosesc variabile globale). */
function ts_run(string $file): void {
    if (!is_file($file)) {
        ts_not_found();
    }
    if (str_starts_with($file, TS_SHARED . '/')) {
        define('TS_SHARED_PAGE', true);
    }
    // Pagina și layout-ul inclus de ea împart scopul acestei funcții
    // ($pageTitle, $activeNav ... setate înainte de layout).
    require $file;
    exit;
}

function ts_robots(): void {
    header('Content-Type: text/plain; charset=utf-8');
    if (SITE_PREVIEW) {
        echo "User-agent: *\nDisallow: /\n";
    } else {
        echo "User-agent: *\nDisallow: /cont\nDisallow: /cos\nDisallow: /finalizare\nDisallow: /confirmare\nDisallow: /api/\n";
    }
    exit;
}

/** Favicon-ul urcat în core sau, în lipsă, inițialele pe culoarea principală. */
function ts_favicon(): void {
    $url = site('branding.favicon');
    if ($url) {
        header('Location: ' . $url, true, 302);
        exit;
    }
    $c = ts_colors();
    $bg = vsprintf('#%02x%02x%02x', $c['canvas']);
    $fg = vsprintf('#%02x%02x%02x', $c['primary']);
    $text = mb_substr(SITE_LOGO_TEXT ?: SITE_NAME, 0, 2);
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=3600');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="32" fill="' . $bg . '"/>'
        . '<circle cx="32" cy="32" r="29" fill="none" stroke="' . $fg . '" stroke-width="3"/>'
        . '<text x="32" y="41" font-family="Georgia,serif" font-size="24" text-anchor="middle" fill="' . $fg . '">' . e($text) . '</text></svg>';
    exit;
}
