<?php
/**
 * Verifică rutele /api/app fără să pornească Laravel.
 *
 * O ruta care trimite catre o clasa sau o metoda inexistenta nu se vede la
 * lint: crapa abia cand cineva cheama endpointul. Fara `composer install` aici
 * nu putem rula `route:list`, deci parcurgem fisierul de rute cu tokenizer-ul
 * si confirmam ca fiecare `[Clasa::class, 'metoda']` exista pe disc.
 *
 * Rulare: php tools/check-app-routes.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$routes = file_get_contents($root.'/routes/api.php');

/* Doar blocul aplicatiei Tixello. */
$start = strpos($routes, "Route::prefix('app')");
if ($start === false) {
    fwrite(STDERR, "Nu am gasit blocul Route::prefix('app')\n");
    exit(2);
}
$block = substr($routes, $start);

preg_match_all(
    '/\[\s*\\\\?([A-Za-z0-9_\\\\]+)::class\s*,\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\]/',
    $block,
    $m,
    PREG_SET_ORDER
);

if (! $m) {
    fwrite(STDERR, "Nicio ruta gasita in bloc\n");
    exit(2);
}

/** Fisierul in care ar trebui sa stea o clasa, dupa PSR-4 (App\ -> app/). */
function fileFor(string $root, string $class): ?string
{
    $class = ltrim($class, '\\');
    if (! str_starts_with($class, 'App\\')) {
        return null;
    }
    return $root.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
}

/** Metodele publice declarate intr-un fisier, prin tokenizer. */
function methodsIn(string $file): array
{
    $tokens = token_get_all(file_get_contents($file));
    $out = [];
    for ($i = 0; $i < count($tokens); $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $out[] = $tokens[$j][1];
                break;
            }
            if (! is_array($tokens[$j])) {
                break; // functie anonima
            }
        }
    }
    return $out;
}

/** Trait-urile folosite de o clasa, ca sa gasim si metodele mostenite. */
function traitsIn(string $file): array
{
    preg_match_all('/^\s*use\s+([A-Za-z0-9_]+);/m', file_get_contents($file), $m);
    return $m[1] ?? [];
}

$errors = [];
$checked = 0;
$cache = [];

foreach ($m as [$full, $class, $method]) {
    $file = fileFor($root, $class);
    if (! $file) {
        continue;
    }
    if (! is_file($file)) {
        $errors[] = "CLASA LIPSA: {$class}  (astept {$file})";
        continue;
    }

    if (! isset($cache[$file])) {
        $methods = methodsIn($file);
        // adaugam metodele din trait-urile folosite, cautate langa clasa
        foreach (traitsIn($file) as $t) {
            foreach (glob($root.'/app/**/**/**/'.$t.'.php') ?: [] as $tf) {
                $methods = array_merge($methods, methodsIn($tf));
            }
        }
        $cache[$file] = $methods;
    }

    $checked++;
    if (! in_array($method, $cache[$file], true)) {
        $errors[] = "METODA LIPSA: {$class}::{$method}()";
    }
}

echo "rute verificate: {$checked}\n";

if ($errors) {
    fwrite(STDERR, "\n".implode("\n", $errors)."\n");
    exit(1);
}

echo "OK — toate rutele /api/app trimit catre clase si metode care exista.\n";
exit(0);
