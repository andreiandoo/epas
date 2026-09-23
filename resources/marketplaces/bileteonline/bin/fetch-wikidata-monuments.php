<?php
/**
 * Build an import CSV for the towns the catalogue is missing, from Wikidata's copy of the
 * Lista Monumentelor Istorice.
 *
 *   php bin/fetch-wikidata-monuments.php [--city=brasov] [--limit=N] [--out=path]
 *
 * Why this exists: `atractii_romania_final_import.csv` covers all 42 counties but has **no row at
 * all** for fifteen county seats — Brașov, Sibiu, Iași, Constanța among them — while their
 * counties hold hundreds of monuments in the surrounding villages. The monuments are on Wikidata
 * with coordinates; what they lack is the administrative link (`P131`) back to the town, which is
 * almost certainly what the original extraction keyed on. Asking by **bounding box** instead finds
 * them: 253 by administrative membership, 680 by geography.
 *
 * Output matches the sixteen columns of the existing import file, plus `imagine_credit`, because
 * Commons photos are CC BY-SA and may only be published next to the photographer's name, the
 * licence and a link back. `import:bilete-attractions` reads that column into
 * `attractions.cover_image_credit`, and the attraction page prints it under the photo.
 *
 * Nothing here invents data: every row carries its LMI code, and a row without a name, without
 * coordinates, or outside the town's box is dropped rather than guessed at.
 */

declare(strict_types=1);

const WDQS     = 'https://query.wikidata.org/sparql';
const COMMONS  = 'https://commons.wikimedia.org/w/api.php';
const UA       = 'bilete.online attractions import (+https://bilete.online; contact nastase.ai@gmail.com)';
const THUMB_PX = 1280;   // one of the widths Wikimedia will render; see the note in the fetch below

/**
 * The fifteen towns, with the box to search and the LMI county prefix to check the code against.
 * Boxes are the built-up area, deliberately tight: a loose box pulls in the next commune's church
 * and files it under the town.
 */
const TOWNS = [
    'brasov'    => ['Brașov',    'Brașov',    'BV', 25.52, 45.60, 25.69, 45.72],
    'sibiu'     => ['Sibiu',     'Sibiu',     'SB', 24.08, 45.74, 24.20, 45.83],
    'iasi'      => ['Iași',      'Iași',      'IS', 27.50, 47.10, 27.68, 47.22],
    'constanta' => ['Constanța', 'Constanța', 'CT', 28.57, 44.14, 28.69, 44.24],
    'arad'      => ['Arad',      'Arad',      'AR', 21.25, 46.13, 21.38, 46.22],
    'suceava'   => ['Suceava',   'Suceava',   'SV', 26.20, 47.61, 26.31, 47.69],
    'galati'    => ['Galați',    'Galați',    'GL', 27.88, 45.39, 28.10, 45.50],
    'braila'    => ['Brăila',    'Brăila',    'BR', 27.90, 45.22, 28.02, 45.32],
    'botosani'  => ['Botoșani',  'Botoșani',  'BT', 26.61, 47.71, 26.72, 47.78],
    'bacau'     => ['Bacău',     'Bacău',     'BC', 26.85, 46.50, 26.97, 46.61],
    'buzau'     => ['Buzău',     'Buzău',     'BZ', 26.76, 45.11, 26.88, 45.20],
    'calarasi'  => ['Călărași',  'Călărași',  'CL', 27.28, 44.16, 27.40, 44.24],
    'tulcea'    => ['Tulcea',    'Tulcea',    'TL', 28.74, 45.14, 28.86, 45.22],
    'giurgiu'   => ['Giurgiu',   'Giurgiu',   'GR', 25.92, 43.86, 26.02, 43.94],
    'vaslui'    => ['Vaslui',    'Vaslui',    'VS', 27.68, 46.60, 27.78, 46.68],
];

/**
 * Wikidata's "instance of" onto the ten types the site uses. Checked in order, first match wins,
 * so the specific entries have to come before the general ones.
 */
const TYPE_MAP = [
    'biserica-manastire' => ['biseric', 'mănăstir', 'manastir', 'catedral', 'capel', 'sinagog', 'moschee', 'schit', 'lăcaș', 'templu'],
    'castel-palat'       => ['castel', 'palat', 'cetate', 'fortificaț', 'fortăreaț', 'conac', 'curte domneasc', 'citadel'],
    'muzeu'              => ['muzeu', 'galerie de artă', 'pinacotec', 'colecți'],
    'teatru-opera'       => ['teatru', 'oper', 'filarmonic', 'sală de concert', 'cinematograf', 'ateneu'],
    'parc-gradina'       => ['parc', 'grădin', 'gradin', 'scuar'],
    'lac-natura'         => ['lac', 'râu', 'peșter', 'rezervaț', 'arie protejat', 'cascad', 'chei'],
    'punct-panoramic'    => ['punct panoramic', 'belvedere', 'turn de observ'],
    'piata-centru-vechi' => ['piaț', 'cartier', 'centru istoric', 'ansamblu urban', 'sit urban', 'stradă'],
    // No bare 'monument': Wikidata calls ordinary buildings "clădire monument istoric", and
    // matching that filed every school and library as a monument.
    'monument'           => ['mausoleu', 'statuie', 'bust', 'cruce', 'troiț', 'obelisc', 'memorial', 'cimitir', 'mormânt'],
    'cladire-istorica'   => ['casă', 'casa', 'clădire', 'cladire', 'bancă', 'școal', 'scoal', 'hotel', 'han', 'gar', 'spital', 'vil', 'hal', 'moar', 'fabric', 'primărie', 'tribunal', 'bibliotec', 'universit', 'liceu', 'colegi', 'farmaci', 'depozit', 'turn'],
];

// ------------------------------------------------------------------ arguments
$flags = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $flags[$m[1]] = $m[2] ?? true;
    }
}
$onlyCity = isset($flags['city']) ? (string) $flags['city'] : '';
$limit    = isset($flags['limit']) ? max(1, (int) $flags['limit']) : 0;
$outFile  = isset($flags['out']) ? (string) $flags['out']
    : dirname(__DIR__) . '/csvs/atractii_orase_lipsa.csv';

if ($onlyCity !== '' && !isset(TOWNS[$onlyCity])) {
    fwrite(STDERR, "Unknown city '{$onlyCity}'. Known: " . implode(', ', array_keys(TOWNS)) . "\n");
    exit(1);
}

// ------------------------------------------------------------------ helpers
function http(string $url, array $headers = [], ?string $post = null, int $timeout = 90): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
        // A connection that goes quiet is worse than one that fails: it hangs the whole run.
        CURLOPT_LOW_SPEED_LIMIT => 100,
        CURLOPT_LOW_SPEED_TIME  => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err !== '' || $code !== 200 || !is_string($body)) {
        fwrite(STDERR, '  ! ' . ($err !== '' ? $err : 'HTTP ' . $code) . "\n");

        return null;
    }

    return $body;
}

/** Run one SPARQL query, with a couple of retries: WDQS times out under load often enough. */
function sparql(string $query): ?array
{
    for ($try = 1; $try <= 3; $try++) {
        $body = http(WDQS, ['Accept: application/sparql-results+json'], 'query=' . urlencode($query), 180);
        if ($body !== null) {
            $json = json_decode($body, true);
            if (isset($json['results']['bindings'])) {
                return $json['results']['bindings'];
            }
        }
        if ($try < 3) {
            fwrite(STDERR, "  retrying in {$try}0s\n");
            sleep($try * 10);
        }
    }

    return null;
}

function fold(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');

    return strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
}

function slugify(string $s): string
{
    $s = strtr(mb_strtolower(trim($s), 'UTF-8'), [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ö' => 'o', 'ű' => 'u', 'ő' => 'o',
        '„' => '', '”' => '', '"' => '', "'" => '', '’' => '',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);

    return trim((string) $s, '-');
}

/** Wikidata's class names, plus the LMI code's own category, onto one of the site's ten types. */
function mapType(array $classes, string $lmi): string
{
    $hay = fold(implode(' | ', $classes));
    foreach (TYPE_MAP as $slug => $needles) {
        foreach ($needles as $needle) {
            if ($hay !== '' && str_contains($hay, fold($needle))) {
                return $slug;
            }
        }
    }
    // Nothing matched: fall back on what the LMI code says the entry is.
    $cat = strtoupper((string) (explode('-', $lmi)[1] ?? ''));
    $kind = strtolower((string) (explode('-', $lmi)[2] ?? ''));
    if ($cat === 'IV') {
        return 'monument';
    }
    if ($cat === 'III') {
        return 'monument';
    }
    if ($cat === 'II' && $kind === 'a') {
        return 'piata-centru-vechi';   // "ansamblu": a street or a square, not one building
    }

    return 'cladire-istorica';
}

// ------------------------------------------------------------------ 1. the monuments
$towns = $onlyCity !== '' ? [$onlyCity => TOWNS[$onlyCity]] : TOWNS;
$rows  = [];
$seen  = [];

foreach ($towns as $key => [$town, $county, $prefix, $w, $s0, $e, $n0]) {
    fwrite(STDOUT, sprintf('%-12s ', $town));
    $query = <<<SPARQL
SELECT ?m ?nume ?lmi ?lat ?lng ?img ?descriere (GROUP_CONCAT(DISTINCT ?tipLabel; separator=" | ") AS ?tipuri) WHERE {
  SERVICE wikibase:box {
    ?m wdt:P625 ?coord .
    bd:serviceParam wikibase:cornerSouthWest "Point({$w} {$s0})"^^geo:wktLiteral ;
                    wikibase:cornerNorthEast "Point({$e} {$n0})"^^geo:wktLiteral .
  }
  ?m wdt:P1770 ?lmi .
  FILTER(STRSTARTS(?lmi, "{$prefix}-"))
  FILTER(!STRSTARTS(STRAFTER(?lmi, "-"), "I-"))
  ?m rdfs:label ?nume . FILTER(lang(?nume) = "ro")
  ?m p:P625/psv:P625 [ wikibase:geoLatitude ?lat ; wikibase:geoLongitude ?lng ] .
  OPTIONAL { ?m wdt:P18 ?img }
  OPTIONAL { ?m schema:description ?descriere . FILTER(lang(?descriere) = "ro") }
  OPTIONAL { ?m wdt:P31 ?tip . ?tip rdfs:label ?tipLabel . FILTER(lang(?tipLabel) = "ro") }
}
GROUP BY ?m ?nume ?lmi ?lat ?lng ?img ?descriere
SPARQL;

    $found = sparql($query);
    if ($found === null) {
        fwrite(STDOUT, "skipped\n");
        continue;
    }

    $kept = 0;
    foreach ($found as $b) {
        $get  = fn (string $k) => (string) ($b[$k]['value'] ?? '');
        $name = trim($get('nume'));
        $lat  = $get('lat');
        $lng  = $get('lng');
        $lmi  = $get('lmi');
        if ($name === '' || !is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }
        // One LMI code can carry several sub-entries at the same point; keep the first.
        $dedupe = fold($name) . '|' . round((float) $lat, 5) . '|' . round((float) $lng, 5);
        if (isset($seen[$dedupe])) {
            continue;
        }
        $seen[$dedupe] = true;

        $rows[] = [
            'nume'      => $name,
            'slug'      => slugify($name) . '-' . $key,
            'oras'      => $town,
            'judet'     => $county,
            'tip'       => mapType(array_filter(explode(' | ', $get('tipuri'))), $lmi),
            'lat'       => $lat,
            'lng'       => $lng,
            'lmi'       => $lmi,
            'descriere' => trim($get('descriere')),
            'img'       => $get('img'),
            'qid'       => basename($get('m')),
        ];
        $kept++;
        if ($limit > 0 && count($rows) >= $limit) {
            break;
        }
    }
    fwrite(STDOUT, sprintf("%4d monuments\n", $kept));
    if ($limit > 0 && count($rows) >= $limit) {
        break;
    }
    usleep(500000);   // WDQS asks for restraint, and this is a one-off
}

if (!$rows) {
    fwrite(STDERR, "Nothing found, writing nothing\n");
    exit(1);
}

// ------------------------------------------------------------------ 2. the photo licences
$withImg = array_values(array_filter($rows, fn ($r) => $r['img'] !== ''));
fwrite(STDOUT, "\nLicences for " . count($withImg) . " photos ...\n");

$credits = [];
foreach (array_chunk($withImg, 40) as $i => $chunk) {
    $titles = implode('|', array_map(
        fn ($r) => 'File:' . rawurldecode(basename($r['img'])),
        $chunk
    ));
    /* `iiurlwidth` makes the API hand back a thumbnail URL it will actually serve. Building that
       path by hand does not work any more: Wikimedia only renders a fixed set of widths now (an
       arbitrary one answers 400, "Use thumbnail sizes listed on w.wiki/GHai") and thumbnails come
       off thumb.wikimedia.org rather than upload.wikimedia.org. */
    $url = COMMONS . '?' . http_build_query([
        'action' => 'query', 'prop' => 'imageinfo', 'iiprop' => 'extmetadata|url',
        'iiurlwidth' => THUMB_PX, 'format' => 'json', 'titles' => $titles,
    ]);
    $body = http($url, [], null, 60);
    if ($body === null) {
        fwrite(STDOUT, "  batch skipped
");
        continue;
    }
    foreach ((json_decode($body, true)['query']['pages'] ?? []) as $page) {
        $info = $page['imageinfo'][0] ?? null;
        if (!$info) {
            continue;
        }
        $meta = $info['extmetadata'] ?? [];
        $text = fn (string $k) => trim(strip_tags((string) ($meta[$k]['value'] ?? '')));
        $credits[$page['title']] = [
            'author'      => $text('Artist') ?: 'necunoscut',
            'license'     => $text('LicenseShortName') ?: '',
            'license_url' => (string) ($meta['LicenseUrl']['value'] ?? ''),
            'source'      => 'https://commons.wikimedia.org/wiki/' . str_replace(' ', '_', (string) $page['title']),
            'thumb'       => '',
        ];
        /* The scaled copy the API rendered for us; the originals run to tens of megabytes.
           Commons hangs tracking parameters off these fields, so strip the query string. */
        $thumb = strtok((string) ($info['thumburl'] ?? ''), '?');
        $orig  = strtok((string) ($info['url'] ?? ''), '?');
        $credits[$page['title']]['thumb'] = $thumb !== '' ? $thumb : $orig;
    }
    fwrite(STDOUT, '  ' . (($i + 1) * 40 > count($withImg) ? count($withImg) : ($i + 1) * 40) . '/' . count($withImg) . "\n");
    usleep(300000);
}

// ------------------------------------------------------------------ 3. the CSV
$out = fopen($outFile, 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['nume', 'nume_en', 'slug', 'subtitlu', 'descriere', 'oras', 'judet', 'tip', 'adresa',
    'latitudine', 'longitudine', 'meta_title', 'meta_description', 'cuvinte_cheie',
    'imagine_principala', 'galerie_foto', 'imagine_credit', 'cod_lmi'], ',', '"', '');

$types = [];
$photos = 0;
foreach ($rows as $r) {
    $title = $r['img'] !== '' ? 'File:' . str_replace('_', ' ', rawurldecode(basename($r['img']))) : '';
    $credit = $credits[$title] ?? null;
    $image  = $credit && $credit['thumb'] !== '' ? $credit['thumb'] : '';
    if ($image !== '') {
        $photos++;
    }
    $types[$r['tip']] = ($types[$r['tip']] ?? 0) + 1;

    fputcsv($out, [
        $r['nume'],
        '',
        $r['slug'],
        // The subtitle the existing rows carry is "{Tip} în {Oraș}, {Județ}", written by the
        // importer, not by a person. Leave it empty rather than add more of it.
        '',
        $r['descriere'],
        $r['oras'],
        $r['judet'],
        $r['tip'],
        '',
        $r['lat'],
        $r['lng'],
        '',
        '',
        '',
        $image,
        '',
        $credit ? json_encode([
            'author'      => $credit['author'],
            'license'     => $credit['license'],
            'license_url' => $credit['license_url'],
            'source'      => $credit['source'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        $r['lmi'],
    ], ',', '"', '');
}
fclose($out);

arsort($types);
fwrite(STDOUT, sprintf(
    "\nWrote %s\n  %d monuments, %d with a photo (%d%%)\n  types: %s\n",
    $outFile,
    count($rows),
    $photos,
    count($rows) ? round(100 * $photos / count($rows)) : 0,
    implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($types), $types))
));
