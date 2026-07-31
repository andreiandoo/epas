<?php
/**
 * Build a deployable site folder from a template + the shared kit.
 *
 *   php tools/build.php <site> [outDir]
 *   php tools/build.php teatru build/teatru
 *
 * Output layout (this is what gets pushed to the site's host by the existing
 * git-branch deploy scripts):
 *   <out>/
 *     index.php          front controller   (from kit/deploy/index.php)
 *     .htaccess          clean-URL rewrites (from kit/deploy/htaccess)
 *     site.config.php    site identity + API creds
 *     routes.php         clean-URL map
 *     includes/          bootstrap
 *     pages/             the site's pages
 *     kit/               vendored copy of the shared kit (php + kit.js)
 *     theme/tokens.css   token contract  (copied from kit/tokens)
 *     theme/theme.css    this site's overrides
 *     api/proxy.php      thin include of kit/proxy.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $argv[1] ?? null;
if (!$site) { fwrite(STDERR, "usage: php tools/build.php <site> [outDir]\n"); exit(1); }
$src = "$root/templates/$site";
if (!is_dir($src)) { fwrite(STDERR, "no template: $src\n"); exit(1); }
$out = $argv[2] ?? "$root/build/$site";

rrmdir($out);
mkdir($out, 0775, true);

// 0) PRODUCTION-SAFETY GUARD — never ship a dev config.
//    Set KIT_ALLOW_UNSAFE=1 only for a deliberate non-production build.
$siteCfg = @include "$src/site.config.php";
if (is_array($siteCfg) && !getenv('KIT_ALLOW_UNSAFE')) {
    $bad = [];
    if (!empty($siteCfg['fixtures']))       $bad[] = "fixtures is set (would serve local JSON instead of the live API)";
    if (!empty($siteCfg['debug']))          $bad[] = "debug is on";
    if (!empty($siteCfg['trust_api_html'])) $bad[] = "trust_api_html is on (skips XSS sanitizer)";
    if (strpos((string)($siteCfg['api_key'] ?? ''), 'mpc_TODO') !== false) $bad[] = "api_key is the placeholder";
    // Credentials the site cannot fetch a single row without.
    $profile = $siteCfg['profile'] ?? (isset($siteCfg['kind']) ? 'tenant' : null);
    if ($profile !== 'marketplace' && empty($siteCfg['tenant_id'])) {
        $bad[] = "tenant_id is 0/empty (every API call would resolve no tenant)";
    }
    if ($profile === 'marketplace' && empty($siteCfg['api_key'])) {
        $bad[] = "api_key is empty (every API call would 401)";
    }
    if ($bad) {
        fwrite(STDERR, "REFUSING to build '$site' for production:\n  - " . implode("\n  - ", $bad)
            . "\nFix site.config.php, or set KIT_ALLOW_UNSAFE=1 for a deliberate dev build.\n");
        exit(2);
    }
}

// 1) template files
foreach (['site.config.php', 'theme.css', 'routes.php'] as $f) {
    if (is_file("$src/$f")) copy("$src/$f", "$out/$f");
}
rcopy("$src/includes", "$out/includes");
rcopy("$src/pages",    "$out/pages");

// 2) vendored kit (php + js), minus deploy/ and tokens source dir duplication
rcopy("$root/kit", "$out/kit");
copy("$root/kit/js/kit.js", "$out/kit/kit.js");           // flat path for /kit/kit.js

// 3) theme dir: contract + overrides (minified)
mkdir("$out/theme", 0775, true);
file_put_contents("$out/theme/tokens.css", css_min((string)file_get_contents("$root/kit/tokens/tokens.css")));
file_put_contents("$out/theme/theme.css",  css_min((string)file_get_contents("$src/theme.css")));

// 4) deploy chrome
copy("$root/kit/deploy/index.php", "$out/index.php");
copy("$root/kit/deploy/htaccess",  "$out/.htaccess");
copy("$root/kit/deploy/_webhook-deploy.php", "$out/_webhook-deploy.php");

// 5) api proxy shim
mkdir("$out/api", 0775, true);
file_put_contents("$out/api/proxy.php", "<?php require __DIR__ . '/../kit/proxy.php';\n");

// 6) robots.txt + sitemap.xml (static routes; event URLs are generated at
//    runtime — extend the sitemap from the API when you need full coverage)
$cfg  = @include "$src/site.config.php";
$base = rtrim($cfg['site_url'] ?? '', '/');
$routes = is_file("$src/routes.php") ? (include "$src/routes.php") : ['exact' => []];
$urls = [];
foreach (($routes['exact'] ?? []) as $url => $name) {
    if (in_array($name, ['login','register','confirmare','403','500','503','404','cart','checkout'], true)) continue;
    if (strpos($url, '/cont') === 0) continue;
    $urls[] = $base . $url;
}
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach (array_unique($urls) as $u) $xml .= "  <url><loc>" . htmlspecialchars($u) . "</loc></url>\n";
$xml .= "</urlset>\n";
file_put_contents("$out/sitemap.xml", $xml);
file_put_contents("$out/robots.txt", "User-agent: *\nAllow: /\n" . ($base ? "Sitemap: $base/sitemap.xml\n" : ""));

// 7) PWA (opt-in): manifest + service worker
if (!empty($cfg['pwa'])) {
    $icons = $cfg['pwa_icons'] ?: [['src' => $cfg['favicon'] ?? '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml']];
    $manifest = [
        'name'             => $cfg['site_name'] ?? 'Site',
        'short_name'       => $cfg['logo_text'] ?: ($cfg['site_name'] ?? 'Site'),
        'start_url'        => '/',
        'display'          => 'standalone',
        'background_color' => $cfg['pwa_background'] ?? '#ffffff',
        'theme_color'      => $cfg['pwa_theme_color'] ?? '#000000',
        'icons'            => $icons,
    ];
    file_put_contents("$out/manifest.webmanifest", json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $sw = <<<'JS'
/* kit service worker — cache-first for static, network-first for pages. */
var CACHE = 'kit-v1';
var SHELL = ['/', '/theme/tokens.css', '/theme/theme.css', '/kit/kit.js', '/manifest.webmanifest'];
self.addEventListener('install', function (e) { e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(SHELL).catch(function(){}); }).then(function(){ return self.skipWaiting(); })); });
self.addEventListener('activate', function (e) { e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); })); }).then(function(){ return self.clients.claim(); })); });
self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(function () { return caches.match('/'); }));
  } else if (/\.(css|js|svg|png|webp|woff2?)$/.test(url.pathname)) {
    e.respondWith(caches.match(req).then(function (r) { return r || fetch(req).then(function (resp) { var cp = resp.clone(); caches.open(CACHE).then(function (c) { c.put(req, cp); }); return resp; }); }));
  }
});
JS;
    file_put_contents("$out/service-worker.js", $sw);
}

echo "Built $site → $out\n";
echo "Serve locally:  php -S 127.0.0.1:8080 -t " . escapeshellarg($out) . " " . escapeshellarg($out . '/index.php') . "\n";

/* ---- helpers ---- */
function rcopy($s, $d) {
    if (!is_dir($s)) return;
    @mkdir($d, 0775, true);
    foreach (scandir($s) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$s/$f") ? rcopy("$s/$f", "$d/$f") : copy("$s/$f", "$d/$f");
    }
}
/** Minimal, safe CSS minifier: strip comments + collapse whitespace. */
function css_min(string $css): string {
    $css = preg_replace('#/\*.*?\*/#s', '', $css);          // comments
    $css = preg_replace('/\s+/', ' ', $css);                // collapse whitespace
    $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);  // around separators
    $css = str_replace(';}', '}', $css);                    // trailing semicolons
    return trim($css);
}

function rrmdir($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$d/$f") ? rrmdir("$d/$f") : @unlink("$d/$f");
    }
    @rmdir($d);
}
