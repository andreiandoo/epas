<?php
/**
 * Test pentru logica de extragere din radar.php, fără rețea.
 *
 * De ce exista: radar.php ruleaza in productie si depinde de forma paginii
 * /events de pe app.tics.ro. Daca ei schimba structura, extragerea se rupe si
 * aplicatia ramane fara date. Testul asta ruleaza parsarea peste un fisier HTML
 * salvat, ca sa se poata verifica fara sa depinda de retea sau de certificate.
 *
 * Rulare:
 *   php radar-parse-test.php <cale-catre-events.html>
 *
 * Iese cu 0 daca extragerea produce ce trebuie, cu 1 altfel.
 */

declare(strict_types=1);

$htmlPath = $argv[1] ?? null;
if (! $htmlPath || ! is_readable($htmlPath)) {
    fwrite(STDERR, "Foloseste: php radar-parse-test.php <events.html>\n");
    exit(2);
}

/* Incarcam DOAR declaratiile din radar.php (pana la sectiunea care executa),
   ca sa testam exact functiile din productie, nu o copie a lor. */
$src = file_get_contents(__DIR__.'/radar.php');
$cut = strpos($src, '/* ---------- cache ---------- */');
if ($cut === false) {
    fwrite(STDERR, "Nu am gasit marcajul de sectiune in radar.php\n");
    exit(2);
}
$decls = substr($src, 0, $cut);
$decls = preg_replace('/^<\?php/', '', $decls, 1);
$decls = str_replace('declare(strict_types=1);', '', $decls);
eval($decls);

$html = file_get_contents($htmlPath);

$events = extractArray($html, 'events');
$cats = extractArray($html, 'subnav');
$cities = extractArray($html, 'cities');

$fails = [];

if (! is_array($events) || count($events) < 100) {
    $fails[] = 'events: '.(is_array($events) ? count($events).' (prea putine)' : 'lipsa');
}
if (! is_array($cats) || count($cats) < 10) {
    $fails[] = 'subnav (categorii): '.(is_array($cats) ? count($cats) : 'lipsa');
}
if (! is_array($cities) || count($cities) < 10) {
    $fails[] = 'cities: '.(is_array($cities) ? count($cities) : 'lipsa');
}

if ($fails) {
    fwrite(STDERR, "ESEC:\n  ".implode("\n  ", $fails)."\n");
    exit(1);
}

$slim = array_map('slim', $events);

$withPrice = count(array_filter($slim, fn ($e) => $e['price'] !== null));
$withImg = count(array_filter($slim, fn ($e) => $e['img'] !== null));
$days = array_column($slim, 'days');

$byCat = [];
foreach ($slim as $e) {
    $byCat[$e['cat']] = ($byCat[$e['cat']] ?? 0) + 1;
}
arsort($byCat);

echo 'evenimente : '.count($slim)."\n";
echo 'categorii  : '.count($cats)."\n";
echo 'orase      : '.count($cities)."\n";
echo 'cu pret    : '.$withPrice."\n";
echo 'cu poster  : '.$withImg."\n";
echo 'zile       : '.min($days).'..'.max($days)."\n";
echo 'top categ. : ';
$top = array_slice($byCat, 0, 5, true);
foreach ($top as $k => $n) {
    echo "$k=$n ";
}
echo "\n";
echo 'exemplu    : '.json_encode($slim[2], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

/* Verificari de forma: campurile pe care se bazeaza aplicatia. */
$required = ['id', 'cat', 'label', 'color', 't', 'city', 'ven', 'plat', 'price', 'save', 'sold', 'days', 'date', 'wknd', 'img'];
$missing = array_diff($required, array_keys($slim[0]));
if ($missing) {
    fwrite(STDERR, 'ESEC: campuri lipsa in payload: '.implode(', ', $missing)."\n");
    exit(1);
}

if ($withPrice < count($slim) * 0.5) {
    fwrite(STDERR, "ESEC: sub jumatate din evenimente au pret — probabil s-a schimbat structura\n");
    exit(1);
}

echo "\nOK — extragerea produce ce asteapta aplicatia.\n";
exit(0);
