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

// 1) template files
foreach (['site.config.php', 'theme.css', 'routes.php'] as $f) {
    if (is_file("$src/$f")) copy("$src/$f", "$out/$f");
}
rcopy("$src/includes", "$out/includes");
rcopy("$src/pages",    "$out/pages");

// 2) vendored kit (php + js), minus deploy/ and tokens source dir duplication
rcopy("$root/kit", "$out/kit");
copy("$root/kit/js/kit.js", "$out/kit/kit.js");           // flat path for /kit/kit.js

// 3) theme dir: contract + overrides
mkdir("$out/theme", 0775, true);
copy("$root/kit/tokens/tokens.css", "$out/theme/tokens.css");
copy("$src/theme.css", "$out/theme/theme.css");

// 4) deploy chrome
copy("$root/kit/deploy/index.php", "$out/index.php");
copy("$root/kit/deploy/htaccess",  "$out/.htaccess");

// 5) api proxy shim
mkdir("$out/api", 0775, true);
file_put_contents("$out/api/proxy.php", "<?php require __DIR__ . '/../kit/proxy.php';\n");

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
function rrmdir($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$d/$f") ? rrmdir("$d/$f") : @unlink("$d/$f");
    }
    @rmdir($d);
}
