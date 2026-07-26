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
 *             → profile 'tenant', seeds menu/terms/features/URLs from the kind.
 * <profile> = tenant | marketplace   (no kind; blank preset)
 *
 * After scaffolding you edit two files — site.config.php (tenant_id/api_key) and
 * theme.css (brand) — then `php tools/build.php <slug>`.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/kit/core/config.php';

$arg  = $argv[1] ?? '';
$slug = $argv[2] ?? '';
$name = $argv[3] ?? ucfirst($slug);

$kinds = kit_kinds();  // [name => label]
if ($slug === '' || ($arg === '')) {
    fwrite(STDERR, "usage: php tools/create-template.php <kind|profile> <slug> \"Site Name\"\n");
    fwrite(STDERR, "kinds: " . implode(', ', array_keys($kinds)) . "\n");
    fwrite(STDERR, "profiles: tenant, marketplace\n");
    exit(1);
}

// Resolve kind vs profile.
$kind = null; $profile = null;
if (isset($kinds[$arg])) {
    $manifest = require $root . "/kit/kinds/$arg.php";
    $kind = $arg;
    $profile = $manifest['profile'] ?? 'tenant';
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
    $c = file_get_contents($p);
    $c = str_replace(['__SITE_NAME__', '__SLUG__', '__KIND__'], [addslashes($name), $slug, (string)$kind], $c);
    file_put_contents($p, $c);
}

echo "Created templates/$slug" . ($kind ? " (kind: $kind)" : " (profile: $profile)") . "\n";
if ($kind) {
    $pages = $manifest['pages'] ?? [];
    echo "Recommended pages for '$kind': " . implode(', ', $pages) . "\n";
    echo "Starter ships: index, 404. Add the rest under templates/$slug/pages/ (see docs/TEMPLATE-AUTHORING.md §4).\n";
}
echo "Next:\n";
echo "  1. edit templates/$slug/site.config.php   (" . ($profile === 'marketplace' ? 'api_key' : 'tenant_id') . ", brand)\n";
echo "  2. edit templates/$slug/theme.css          (tokens)\n";
echo "  3. php tools/build.php $slug\n";

function rcopy($s, $d) {
    @mkdir($d, 0775, true);
    foreach (scandir($s) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$s/$f") ? rcopy("$s/$f", "$d/$f") : copy("$s/$f", "$d/$f");
    }
}
