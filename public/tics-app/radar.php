<?php
/**
 * Proxy pentru feed-ul TICS Radar.
 *
 * DE CE EXISTA
 * API-ul public al app.tics.ro (/api/v1/events) NU intoarce preturi in lista —
 * alea vin doar din detaliu, cate o cerere per eveniment. Aplicatia ajungea sa
 * faca zeci de cereri ca sa umple un ecran si intra in limitarea de rata
 * (~60/min), de unde ecrane pe jumatate goale.
 *
 * Pagina lor /events isi embed-uieste insa TOT listingul in HTML: ~1000 de
 * evenimente pentru urmatoarele 3 saptamani, fiecare cu pretul cel mai mic,
 * platforma, posterul si categoria. O singura cerere in loc de zeci.
 *
 * Pagina aia n-are antete CORS, deci browserul aplicatiei n-o poate citi
 * direct. De asta stam noi la mijloc: citim, subtiem, punem in cache si
 * servim JSON cu CORS.
 *
 * E PHP simplu, in afara Laravel, ca deploy-ul sa ramana un `git pull` fara
 * `route:cache` — aceeasi conventie ca updates.php de langa el.
 */

declare(strict_types=1);

const SOURCE   = 'https://app.tics.ro/events';
const TTL      = 600;   // 10 minute: preturile nu se misca mai repede de atat
const TIMEOUT  = 20;

/**
 * Cache-ul sta in temp, NU langa script: pe core.tixello.com php-fpm ruleaza
 * ca alt utilizator decat cel care face deploy, deci `public/` nu-i e
 * scriibil. In temp are drepturi sigur.
 */
$CACHE = sys_get_temp_dir() . '/tixello-radar-feed.json';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

/** Raspuns + iesire. */
function out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Extrage un array JSON echilibrat care incepe la `"<key>":[`. */
function extractArray(string $html, string $key): ?array {
    $needle = '"' . $key . '":[';
    $at = strpos($html, $needle);
    if ($at === false) {
        return null;
    }

    $start = $at + strlen($needle) - 1;   // pe '['
    $depth = 0;
    $len = strlen($html);
    $inStr = false;
    $esc = false;

    for ($i = $start; $i < $len; $i++) {
        $ch = $html[$i];

        // sarim peste paranteze aflate in interiorul sirurilor
        if ($inStr) {
            if ($esc)            { $esc = false; }
            elseif ($ch === '\\'){ $esc = true; }
            elseif ($ch === '"') { $inStr = false; }
            continue;
        }
        if ($ch === '"') { $inStr = true; continue; }

        if ($ch === '[') { $depth++; }
        elseif ($ch === ']') {
            $depth--;
            if ($depth === 0) {
                $json = substr($html, $start, $i - $start + 1);
                $data = json_decode($json, true);
                return is_array($data) ? $data : null;
            }
        }
    }
    return null;
}

/** Doar campurile de care are nevoie aplicatia — taie payload-ul la ~o treime. */
function slim(array $e): array {
    return [
        'id'    => (int)($e['id'] ?? 0),
        'cat'   => (string)($e['cat'] ?? 'arta'),
        'label' => (string)($e['catLabel'] ?? 'Altele'),
        'color' => (string)($e['color'] ?? '#b91c1c'),
        't'     => (string)($e['title'] ?? ''),
        'city'  => (string)($e['city'] ?? ''),
        'ven'   => (string)($e['venue'] ?? ''),
        'genre' => $e['genre'] ?? null,
        'plat'  => (string)($e['platform'] ?? ''),
        'price' => isset($e['priceNum']) ? (float)$e['priceNum'] : null,
        'save'  => (int)($e['save'] ?? 0),
        'sold'  => (bool)($e['soldout'] ?? false),
        'days'  => (int)($e['days'] ?? 0),
        'date'  => (string)($e['dateLabel'] ?? ''),
        'wknd'  => (bool)($e['weekend'] ?? false),
        'img'   => $e['poster'] ?? null,
    ];
}

/**
 * Aducem pagina cu cURL daca exista si cadem pe file_get_contents altfel:
 * `allow_url_fopen` e des dezactivat, iar fara el fluxul ar muri mut.
 */
function fetchSource(): ?string {
    $ua = 'TixelloApp/1.0 (+https://core.tixello.com/tics-app)';

    if (function_exists('curl_init')) {
        $ch = curl_init(SOURCE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_ENCODING       => '',   // acceptam gzip: ~1.2MB devin ~190KB
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ]);
        $html = curl_exec($ch);
        $ok = $html !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        if ($ok && strlen((string)$html) >= 10000) {
            return (string)$html;
        }
    }

    if (!ini_get('allow_url_fopen')) {
        return null;
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => TIMEOUT, 'header' => "Accept: text/html
User-Agent: {$ua}
"],
    ]);
    $html = @file_get_contents(SOURCE, false, $ctx);
    return ($html === false || strlen($html) < 10000) ? null : $html;
}

/* ---------- cache ---------- */
$fresh = is_readable($CACHE) && (time() - filemtime($CACHE)) < TTL;
if ($fresh && !isset($_GET['refresh'])) {
    $cached = file_get_contents($CACHE);
    if ($cached !== false) {
        header('X-Radar-Cache: hit');
        echo $cached;
        exit;
    }
}

/* ---------- reimprospatare ---------- */
$html = fetchSource();

if ($html === null) {
    // sursa e picata: servim ce aveam, oricat de vechi, in loc de nimic
    if (is_readable($CACHE)) {
        header('X-Radar-Cache: stale');
        echo file_get_contents($CACHE);
        exit;
    }
    out(['error' => 'source_unavailable', 'events' => [], 'cats' => [], 'cities' => []], 502);
}

$events = extractArray($html, 'events');
$cats   = extractArray($html, 'subnav');   // lista COMPLETA de categorii (22), cu etichete oficiale
$cities = extractArray($html, 'cities');

if (!is_array($events) || count($events) === 0) {
    // structura paginii s-a schimbat: mai bine date vechi decat un ecran gol
    if (is_readable($CACHE)) {
        header('X-Radar-Cache: stale-parse');
        echo file_get_contents($CACHE);
        exit;
    }
    out(['error' => 'parse_failed', 'events' => [], 'cats' => [], 'cities' => []], 502);
}

$payload = [
    'fetched' => gmdate('c'),
    'events'  => array_values(array_map('slim', $events)),
    'cats'    => array_values(array_map(static fn($c) => [
        'key'   => (string)($c['k'] ?? $c['key'] ?? ''),
        'label' => (string)($c['l'] ?? $c['label'] ?? ''),
        'color' => (string)($c['c'] ?? $c['color'] ?? '#7c3aed'),
    ], is_array($cats) ? $cats : [])),
    'cities'  => array_values(array_map(static fn($c) => [
        'name' => (string)($c['name'] ?? ''),
        'n'    => (int)($c['n'] ?? 0),
    ], is_array($cities) ? $cities : [])),
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@file_put_contents($CACHE, $json, LOCK_EX);

header('X-Radar-Cache: miss');
echo $json;
