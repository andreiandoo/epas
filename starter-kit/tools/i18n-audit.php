<?php
/**
 * i18n audit: scan pagesets/components/layouts for t('key') calls, compare with
 * the dictionaries, and report:
 *   - keys used in code but MISSING from a locale dictionary
 *   - keys defined but UNUSED (in the base locale)
 * Exit code 1 if any locale is missing a used key (so CI can fail).
 *
 *   php tools/i18n-audit.php
 */
declare(strict_types=1);
$root = dirname(__DIR__);

// 1) collect t('…') / t("…") keys from code
$used = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/kit", FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if ($f->getExtension() !== 'php') continue;
    if (strpos($f->getPathname(), '/lang/') !== false) continue;
    // Strip comments so docblock examples like t('cart.count') aren't counted.
    $src = php_strip_whitespace($f->getPathname());
    if (preg_match_all('/\bt\(\s*([\'"])([a-z0-9_.]+)\1/i', $src, $m)) {
        foreach ($m[2] as $k) $used[$k] = true;
    }
}
ksort($used);

// 2) load dictionaries
$langDir = "$root/kit/lang";
$locales = [];
foreach (glob("$langDir/*.php") as $lf) $locales[basename($lf, '.php')] = require $lf;
if (!$locales) { fwrite(STDERR, "no dictionaries in kit/lang\n"); exit(1); }
$base = array_key_exists('ro', $locales) ? 'ro' : array_key_first($locales);

// 3) report
$fail = 0;
echo "Used t() keys: " . count($used) . " | locales: " . implode(', ', array_keys($locales)) . "\n\n";
foreach ($locales as $loc => $dict) {
    $missing = array_values(array_filter(array_keys($used), fn($k) => !array_key_exists($k, $dict)));
    if ($missing) { $fail = 1; echo "❌ [$loc] missing " . count($missing) . " key(s):\n   " . implode("\n   ", $missing) . "\n\n"; }
    else echo "✅ [$loc] all used keys present\n";
}
// unused (base only) — informational. Ignore keys built dynamically in code
// (e.g. error.php uses t("error.$code.title")), which the literal scan can't see.
$dynamic = '/^error\.\d+\./';
$unused = array_values(array_filter(array_keys($locales[$base]), fn($k) => !isset($used[$k]) && !preg_match($dynamic, $k)));
if ($unused) echo "\nℹ️  [$base] " . count($unused) . " defined-but-unused key(s): " . implode(', ', array_slice($unused, 0, 40)) . (count($unused) > 40 ? ' …' : '') . "\n";

exit($fail);
