<?php
/**
 * Scaffold a new site from a starter.
 *
 *   php tools/create-template.php <kind|profile> <slug> "Site Name"
 *
 *   php tools/create-template.php teatru opera-cluj "Opera Națională Cluj"
 *   php tools/create-template.php leisure aqua-park "Aqua Park"
 *   php tools/create-template.php marketplace bilete-x "BileteX"
 *
 * <kind>    = teatru | filarmonica | agentie | leisure | artist | organizator
 *             → profile 'tenant'. Scaffolds the kind's FULL page-set + routes.php
 *               (pages are thin wrappers over kit/pagesets/*, editable).
 * <profile> = tenant | marketplace   (blank starter, no kind)
 *
 * After scaffolding you edit two files — site.config.php (tenant_id/api_key) and
 * theme.css (brand) — then `php tools/build.php <slug>`. Pages already exist.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/kit/core/config.php';

$arg  = $argv[1] ?? '';
$slug = $argv[2] ?? '';
$name = $argv[3] ?? ucfirst($slug);

$kinds = kit_kinds();
if ($slug === '' || $arg === '') {
    fwrite(STDERR, "usage: php tools/create-template.php <kind|profile> <slug> \"Site Name\"\n");
    fwrite(STDERR, "kinds: " . implode(', ', array_keys($kinds)) . "\nprofiles: tenant, marketplace\n");
    exit(1);
}

$kind = null; $profile = null; $manifest = [];
if (isset($kinds[$arg])) {
    $manifest = require $root . "/kit/kinds/$arg.php";
    $kind = $arg; $profile = $manifest['profile'] ?? 'tenant';
} elseif (in_array($arg, ['tenant', 'marketplace'], true)) {
    $profile = $arg;
} else {
    fwrite(STDERR, "unknown kind/profile: $arg\nkinds: " . implode(', ', array_keys($kinds)) . "\n");
    exit(1);
}

$src = "$root/templates/_starter-$profile";
$dst = "$root/templates/$slug";
if (!is_dir($src)) { fwrite(STDERR, "missing starter: $src\n"); exit(1); }
if (is_dir($dst))  { fwrite(STDERR, "already exists: $dst\n"); exit(1); }

rcopy($src, $dst);

// Fill placeholders in site.config.php + theme.css.
foreach (['site.config.php', 'theme.css'] as $f) {
    $p = "$dst/$f";
    if (!is_file($p)) continue;
    $c = str_replace(['__SITE_NAME__', '__SLUG__', '__KIND__'], [addslashes($name), $slug, (string)$kind], file_get_contents($p));
    file_put_contents($p, $c);
}

// For a KIND: generate the full page-set + routes.php (replacing the starter's
// placeholder index/404). Pages are thin wrappers over kit/pagesets/*.
$generated = [];
if ($kind) {
    array_map('unlink', glob("$dst/pages/*.php") ?: []);
    @unlink("$dst/routes.php");

    $pages = $manifest['pages'] ?? [];

    // Standard commerce + account block for tenant kinds (feature-gated).
    // pageName => [pageset, cleanRoute]
    $extraExact = [];
    if ($profile === 'tenant') {
        $feat = $manifest['features'] ?? [];
        $commerce = [
            'login'        => ['login',               '/autentificare'],
            'register'     => ['register',            '/inregistrare'],
            'confirmare'   => ['confirmare',          '/confirmare'],
            'cont-index'   => ['account-dashboard',   '/cont'],
            'cont-bilete'  => ['account-tickets',     '/cont/bilete'],
            'cont-comenzi' => ['account-orders',      '/cont/comenzi'],
            'cont-favorite'=> ['account-favorites',   '/cont/favorite'],
            'cauta'        => ['search',              '/cauta'],
        ];
        if (!empty($feat['subscriptions'])) $commerce['cont-abonamente'] = ['account-subscriptions', '/cont/abonamente'];
        if (!empty($feat['gift_cards']))    $commerce['cont-carduri']    = ['account-giftcards', '/cont/carduri-cadou'];
        $commerce['cont-setari'] = ['account-settings', '/cont/setari'];
        foreach ($commerce as $pname => [$set, $route]) {
            $pages[$pname] = ['set' => $set];
            $extraExact[$route] = $pname;
        }
    }

    // Content pages: legal (all tenant kinds) + blog (kinds with feature 'blog').
    // [pageset, route, vars]
    $extraCapture = [];
    if ($profile === 'tenant') {
        $content = [
            'termeni'           => ['page', '/termeni',            ['slug' => 'terms',   'nav' => 'terms']],
            'confidentialitate' => ['page', '/confidentialitate',  ['slug' => 'privacy', 'nav' => 'privacy']],
        ];
        if (!empty(($manifest['features'] ?? [])['blog'])) {
            $content['blog'] = ['blog', '/blog', ['nav' => 'blog']];
            $content['post'] = ['post', null, []];         // reached via capture, not an exact route
            $extraCapture['blog'] = 'post';
        }
        foreach ($content as $pname => [$set, $route, $vars]) {
            $pages[$pname] = ['set' => $set] + ($vars ? ['vars' => $vars] : []);
            if ($route) $extraExact[$route] = $pname;
        }
    }

    // A kind may declare clean URLs of its own — the operator panel's pages are
    // not reachable from the public menu, so they cannot be derived from it.
    foreach (($manifest['routes_extra'] ?? []) as $route => $pname) {
        $extraExact[$route] = $pname;
    }

    // Error pages (all kinds). 404 comes from the kind's own pageset.
    foreach ([403, 500, 503] as $code) {
        $pages[(string)$code] = ['set' => 'error', 'vars' => ['code' => $code]];
        $extraExact["/$code"] = (string)$code;
    }

    foreach ($pages as $pname => $spec) {
        $set  = $spec['set'] ?? $pname;
        $vars = $spec['vars'] ?? [];
        if (isset($spec['nav'])) $vars['nav'] = $spec['nav'];
        $body = "<?php\nrequire __DIR__ . '/../includes/bootstrap.php';\n";
        if ($vars) $body .= "\$PAGE = " . var_export($vars, true) . ";\n";
        $body .= "require KIT_DIR . '/pagesets/" . $set . ".php';\n";
        file_put_contents("$dst/pages/$pname.php", $body);
        $generated[] = $pname;
    }

    file_put_contents("$dst/routes.php", derive_routes($manifest, $extraExact, $extraCapture));
}

echo "Created templates/$slug" . ($kind ? " (kind: $kind)" : " (profile: $profile)") . "\n";
if ($kind) echo "Pages generated: " . implode(', ', $generated) . "\n";
echo "Next:\n";
echo "  1. edit templates/$slug/site.config.php   (" . ($profile === 'marketplace' ? 'api_key' : 'tenant_id') . ", brand)\n";
echo "  2. edit templates/$slug/theme.css          (tokens)\n";
echo "  3. (optional) customize any pages/*.php — they wrap kit/pagesets/*\n";
echo "  4. php tools/build.php $slug\n";

/* ---- routes derivation ---- */
function derive_routes(array $m, array $extraExact = [], array $extraCapture = []): string {
    $pages = $m['pages'] ?? [];
    $menu  = $m['menu'] ?? [];
    $urlByKey = [];
    foreach ($menu as $it) $urlByKey[$it['key'] ?? ''] = $it['url'] ?? '';

    $exact = ['/' => 'index'];
    $special = ['index' => 1, 'show' => 1, 'cart' => 1, 'checkout' => 1, '404' => 1];
    foreach ($pages as $pname => $spec) {
        if (isset($special[$pname])) continue;
        $nav = $spec['nav'] ?? $pname;
        $url = $urlByKey[$nav] ?? '';
        if ($url && $url[0] === '/') $exact[strtok($url, '?')] = $pname;
    }
    if (isset($pages['cart']))     $exact[$m['cart_url'] ?? '/cos'] = 'cart';
    if (isset($pages['checkout'])) $exact['/finalizare'] = 'checkout';
    foreach ($extraExact as $route => $name) $exact[$route] = $name;

    $capture = [];
    if (isset($pages['show']) && preg_match('#^/([^/{]+)/#', $m['event_url_pattern'] ?? '', $mm)) {
        $capture[$mm[1]] = 'show';
    }
    foreach ($extraCapture as $prefix => $target) $capture[$prefix] = $target;

    $fmt = function (array $a): string {
        $out = [];
        foreach ($a as $k => $v) $out[] = "        " . var_export($k, true) . " => " . var_export($v, true) . ",";
        return implode("\n", $out);
    };
    return "<?php\n/** Auto-generated from the kind manifest. Edit freely. */\nreturn [\n"
        . "    'exact' => [\n" . $fmt($exact) . "\n    ],\n"
        . "    'capture' => [\n" . $fmt($capture) . "\n    ],\n];\n";
}

function rcopy($s, $d) {
    @mkdir($d, 0775, true);
    foreach (scandir($s) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$s/$f") ? rcopy("$s/$f", "$d/$f") : copy("$s/$f", "$d/$f");
    }
}
