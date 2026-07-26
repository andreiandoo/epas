<?php
/**
 * Scaffold a new site from a starter.  (Phase 4 — the "minutes, not days" step.)
 *
 *   php tools/create-template.php <profile> <slug> "Site Name"
 *   php tools/create-template.php tenant opera-cluj "Opera Națională Cluj"
 *   php tools/create-template.php marketplace bilete-x "BileteX"
 *
 * Copies templates/_starter-<profile> → templates/<slug> and fills the obvious
 * placeholders in site.config.php. After that you edit exactly two files:
 *   templates/<slug>/site.config.php   (tenant_id / api_key, menu, urls)
 *   templates/<slug>/theme.css         (the look)
 * ...then `php tools/build.php <slug>` and deploy.
 */
declare(strict_types=1);

$root    = dirname(__DIR__);
$profile = $argv[1] ?? '';
$slug    = $argv[2] ?? '';
$name    = $argv[3] ?? ucfirst($slug);

if (!in_array($profile, ['tenant', 'marketplace'], true) || $slug === '') {
    fwrite(STDERR, "usage: php tools/create-template.php <tenant|marketplace> <slug> \"Site Name\"\n");
    exit(1);
}
$src = "$root/templates/_starter-$profile";
$dst = "$root/templates/$slug";
if (!is_dir($src)) { fwrite(STDERR, "missing starter: $src\n"); exit(1); }
if (is_dir($dst))  { fwrite(STDERR, "already exists: $dst\n"); exit(1); }

rcopy($src, $dst);

// Fill simple placeholders in site.config.php
$cfgFile = "$dst/site.config.php";
$cfg = file_get_contents($cfgFile);
$cfg = str_replace(['__SITE_NAME__', '__SLUG__'], [addslashes($name), $slug], $cfg);
file_put_contents($cfgFile, $cfg);

echo "Created templates/$slug ($profile)\n";
echo "Next:\n";
echo "  1. edit templates/$slug/site.config.php   (" . ($profile === 'tenant' ? 'tenant_id' : 'api_key') . ", menu, url patterns)\n";
echo "  2. edit templates/$slug/theme.css          (brand tokens)\n";
echo "  3. php tools/build.php $slug                (assemble deployable)\n";

function rcopy($s, $d) {
    @mkdir($d, 0775, true);
    foreach (scandir($s) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$s/$f") ? rcopy("$s/$f", "$d/$f") : copy("$s/$f", "$d/$f");
    }
}
