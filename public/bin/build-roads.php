<?php
/**
 * Builds assets/v2/data/drumuri.json: the roads of includes/v2/map-roads.php, as the pages need them.
 *
 *   php bin/build-roads.php [--force] [--only=slug]
 *
 * For every road: the line — routed through its declared points (FOSSGIS OSRM, OpenStreetMap data),
 * or, for an entry with 'osm', taken from OpenStreetMap route relations through Overpass —
 * the elevation along it (Open Topo Data, EU-DEM 25 m), and the catalogue's attractions that stand
 * within reach of it, in the order you meet them. One routing call and one elevation call per road,
 * spaced out; a road whose points did not change since the last build is reused, not asked again.
 *
 * Run it after map-roads.php changes or after a catalogue import (the attractions beside a road
 * come from assets/v2/data/atractii.json), then commit drumuri.json like any other asset.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/v2/map-roads.php';

$flags = array_slice($argv, 1);
$force = in_array('--force', $flags, true);
$only  = '';
foreach ($flags as $fl) {
    if (strpos($fl, '--only=') === 0) {
        $only = substr($fl, 7);
    }
}

$dataDir = BILETEONLINE_ROOT . '/assets/v2/data';
$outFile = $dataDir . '/drumuri.json';
$pinFile = $dataDir . '/atractii.json';

const ROAD_REACH_KM   = 2.5;     // how far from the line an attraction still counts as "on the road"
const ROAD_STOPS_MAX  = 8;
const ROAD_SAMPLES    = 100;     // the elevation service takes a hundred points in one call
const ROAD_SIMPLIFY_M = 22;      // tolerance for the drawn line: hairpins survive, noise does not

function roadGet(string $url, int $timeout = 45): ?array
{
    for ($try = 0; $try < 3; $try++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'viaqui.com road builder (' . SUPPORT_EMAIL . ')',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($code === 200) {
            $d = json_decode((string) $body, true);
            if (is_array($d)) {
                return $d;
            }
        }
        fwrite(STDERR, '    ! HTTP ' . $code . ' ' . $err . "\n");
        sleep(4);
    }

    return null;
}

function roadKm(float $aLat, float $aLng, float $bLat, float $bLng): float
{
    $r = M_PI / 180;
    $x = sin(($bLat - $aLat) * $r / 2) ** 2 + cos($aLat * $r) * cos($bLat * $r) * sin(($bLng - $aLng) * $r / 2) ** 2;

    return 12742 * asin(min(1, sqrt($x)));
}

/** Google's polyline format, precision 5 — what the map and the planner already read. */
function roadDecode(string $str): array
{
    $out = [];
    $i = 0;
    $lat = 0;
    $lng = 0;
    $n = strlen($str);
    while ($i < $n) {
        foreach ([0, 1] as $k) {
            $res = 0;
            $shift = 0;
            do {
                $b = ord($str[$i++]) - 63;
                $res |= ($b & 31) << $shift;
                $shift += 5;
            } while ($b >= 32);
            $d = ($res & 1) ? ~($res >> 1) : ($res >> 1);
            if ($k === 0) {
                $lat += $d;
            } else {
                $lng += $d;
            }
        }
        $out[] = [$lat / 1e5, $lng / 1e5];
    }

    return $out;
}

function roadEncode(array $pts): string
{
    $out = '';
    $pl = 0;
    $pn = 0;
    $enc = function (int $v): string {
        $v = $v < 0 ? ~($v << 1) : ($v << 1);
        $s = '';
        while ($v >= 32) {
            $s .= chr((32 | ($v & 31)) + 63);
            $v >>= 5;
        }

        return $s . chr($v + 63);
    };
    foreach ($pts as [$lat, $lng]) {
        $a = (int) round($lat * 1e5);
        $b = (int) round($lng * 1e5);
        $out .= $enc($a - $pl) . $enc($b - $pn);
        $pl = $a;
        $pn = $b;
    }

    return $out;
}

/** Douglas–Peucker, iterative, in a local flat projection; tolerance in metres. */
function roadSimplify(array $pts, float $tolM): array
{
    $n = count($pts);
    if ($n < 3) {
        return $pts;
    }
    $k = cos(deg2rad($pts[0][0]));
    $tol = $tolM / 111320;
    $keep = array_fill(0, $n, false);
    $keep[0] = $keep[$n - 1] = true;
    $stack = [[0, $n - 1]];
    while ($stack) {
        [$a, $b] = array_pop($stack);
        $ax = $pts[$a][1] * $k;
        $ay = $pts[$a][0];
        $dx = $pts[$b][1] * $k - $ax;
        $dy = $pts[$b][0] - $ay;
        $len = $dx * $dx + $dy * $dy;
        $max = 0;
        $at = -1;
        for ($i = $a + 1; $i < $b; $i++) {
            $px = $pts[$i][1] * $k - $ax;
            $py = $pts[$i][0] - $ay;
            $t = $len > 0 ? max(0, min(1, ($px * $dx + $py * $dy) / $len)) : 0;
            $d = hypot($px - $t * $dx, $py - $t * $dy);
            if ($d > $max) {
                $max = $d;
                $at = $i;
            }
        }
        if ($max > $tol && $at > 0) {
            $keep[$at] = true;
            $stack[] = [$a, $at];
            $stack[] = [$at, $b];
        }
    }
    $out = [];
    foreach ($pts as $i => $p) {
        if ($keep[$i]) {
            $out[] = $p;
        }
    }

    return $out;
}

/** n points equally spaced along the line, each with its distance from the start in km. */
function roadSample(array $pts, int $n): array
{
    $cum = [0.0];
    for ($i = 1, $c = count($pts); $i < $c; $i++) {
        $cum[] = $cum[$i - 1] + roadKm($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1]);
    }
    $total = end($cum);
    $out = [];
    $j = 0;
    for ($s = 0; $s < $n; $s++) {
        $d = $total * $s / ($n - 1);
        while ($j < count($pts) - 2 && $cum[$j + 1] < $d) {
            $j++;
        }
        $span = $cum[$j + 1] - $cum[$j];
        $f = $span > 0 ? ($d - $cum[$j]) / $span : 0;
        $out[] = [
            $pts[$j][0] + ($pts[$j + 1][0] - $pts[$j][0]) * $f,
            $pts[$j][1] + ($pts[$j + 1][1] - $pts[$j][1]) * $f,
            $d,
        ];
    }

    return $out;
}

/**
 * A route that already exists as an OpenStreetMap route relation: its member ways, put end to end.
 *
 * A relation is a bag of ways, not a line — unordered, sometimes with a branch or a gap. The ways are
 * chained where they share an end, and the chains are then walked from one extremity of the route to
 * the other, always taking the nearest chain next. What comes out is one line; where two chains do
 * not touch, the line jumps, and the jump is neither counted in the length nor allowed to be long.
 */
function roadFromOsm(array $ids, ?array $start = null, float $tolM = 25): ?array
{
    $q = '[out:json][timeout:180];relation(id:' . implode(',', array_map('intval', $ids)) . ');out geom;';
    $d = roadGet('http://overpass-api.de/api/interpreter?data=' . rawurlencode($q), 200);
    if (!is_array($d['elements'] ?? null)) {
        return null;
    }
    $ways = [];
    $tags = [];
    foreach ($d['elements'] as $rel) {
        $tags[] = $rel['tags'] ?? [];
        foreach ($rel['members'] ?? [] as $m) {
            if (($m['type'] ?? '') !== 'way' || empty($m['geometry']) || count($m['geometry']) < 2) {
                continue;
            }
            $ways[$m['ref']] = array_map(fn ($g) => [(float) $g['lat'], (float) $g['lon']], $m['geometry']);
        }
    }
    if (!$ways) {
        return null;
    }
    $ways = array_values($ways);

    // 1. chain the ways that share an end point
    $k = fn ($p) => sprintf('%.6f,%.6f', $p[0], $p[1]);
    $ends = [];
    foreach ($ways as $i => $w) {
        $ends[$k($w[0])][] = $i;
        $ends[$k(end($w))][] = $i;
    }
    $used = [];
    $chains = [];
    foreach ($ways as $i => $w) {
        if (isset($used[$i])) {
            continue;
        }
        $used[$i] = true;
        $cur = $w;
        for ($side = 0; $side < 2; $side++) {
            while (true) {
                $tip = end($cur);
                $next = null;
                foreach ($ends[$k($tip)] ?? [] as $j) {
                    if (!isset($used[$j])) {
                        $next = $j;
                        break;
                    }
                }
                if ($next === null) {
                    break;
                }
                $used[$next] = true;
                $add = $ways[$next];
                if ($k($add[0]) !== $k($tip)) {
                    $add = array_reverse($add);
                }
                $cur = array_merge($cur, array_slice($add, 1));
            }
            $cur = array_reverse($cur);
        }
        $chains[] = $cur;
    }

    // 2. start at one extremity: the chain end farthest from the end that is farthest from anywhere
    $tips = [];
    foreach ($chains as $c) {
        $tips[] = $c[0];
        $tips[] = end($c);
    }
    $far = function (array $from) use ($tips) {
        $best = $from;
        $bd = -1;
        foreach ($tips as $t) {
            $dd = roadKm($from[0], $from[1], $t[0], $t[1]);
            if ($dd > $bd) {
                $bd = $dd;
                $best = $t;
            }
        }

        return $best;
    };
    $at = $far($far($tips[0]));
    if ($start) {
        // the route's declared first end: begin at the chain tip nearest to it
        $bd = INF;
        foreach ($tips as $t) {
            $dd = roadKm($start[0], $start[1], $t[0], $t[1]);
            if ($dd < $bd) {
                $bd = $dd;
                $at = $t;
            }
        }
    }

    // 3. walk the chains, nearest next. Each chain is simplified on its own, so the places where the
    //    line jumps from one to the next stay known: `breaks` holds the index each new piece starts at.
    $line = [];
    $breaks = [];
    $jumps = 0;
    $skipped = 0.0;
    $left = $chains;
    while ($left) {
        $pick = -1;
        $rev = false;
        $bd = INF;
        foreach ($left as $i => $c) {
            $d0 = roadKm($at[0], $at[1], $c[0][0], $c[0][1]);
            $e = end($c);
            $d1 = roadKm($at[0], $at[1], $e[0], $e[1]);
            if ($d0 < $bd) {
                $bd = $d0;
                $pick = $i;
                $rev = false;
            }
            if ($d1 < $bd) {
                $bd = $d1;
                $pick = $i;
                $rev = true;
            }
        }
        $c = $left[$pick];
        unset($left[$pick]);
        if ($rev) {
            $c = array_reverse($c);
        }
        $len = 0.0;
        for ($i = 1, $n = count($c); $i < $n; $i++) {
            $len += roadKm($c[$i - 1][0], $c[$i - 1][1], $c[$i][0], $c[$i][1]);
        }
        // a scrap far from where the line has got to is left out rather than jumped to
        if ($line && $bd > 3.0 && $len < 1.5) {
            $skipped += $len;
            continue;
        }
        $c = roadSimplify($c, $tolM);
        if ($line && $bd > 0.05) {
            $jumps++;
            $breaks[] = count($line);
        } elseif ($line) {
            array_shift($c);       // the shared point
        }
        $line = array_merge($line, $c);
        $at = end($line);
    }

    $km = 0.0;
    $isBreak = array_flip($breaks);
    for ($i = 1, $n = count($line); $i < $n; $i++) {
        if (isset($isBreak[$i])) {
            continue;          // a jump between two pieces is not road
        }
        $km += roadKm($line[$i - 1][0], $line[$i - 1][1], $line[$i][0], $line[$i][1]);
    }
    $tagKm = 0.0;
    foreach ($tags as $t) {
        if (preg_match('/^\s*([0-9]+(?:[.,][0-9]+)?)/', (string) ($t['distance'] ?? ''), $mm)) {
            $tagKm += (float) str_replace(',', '.', $mm[1]);
        }
    }

    return ['line' => $line, 'breaks' => $breaks, 'km' => $km, 'tag_km' => $tagKm, 'jumps' => $jumps, 'skipped' => $skipped, 'ways' => count($ways), 'chains' => count($chains)];
}

// ---------------------------------------------------------------- the catalogue, for what stands beside a road
if (!is_file($pinFile)) {
    fwrite(STDERR, "Missing {$pinFile}: run bin/build-map-data.php first.\n");
    exit(1);
}
$pins = json_decode((string) file_get_contents($pinFile), true);
$f = array_flip($pins['fields']);
$imgFlag = (int) ($pins['flags']['image'] ?? 1);
// What a rider stops for: a view, water, a castle. A parish church every two kilometres is not it.
$typeWeight = [
    'punct-panoramic' => 4, 'lac-natura' => 4, 'castel-palat' => 3.5, 'muzeu' => 2, 'parc-gradina' => 1.5,
    'piata-centru-vechi' => 1.5, 'cladire-istorica' => 0.5, 'biserica-manastire' => 0.5, 'monument' => 0, 'teatru-opera' => 0,
];

$prev = [];
if (is_file($outFile)) {
    $old = json_decode((string) file_get_contents($outFile), true);
    $prev = is_array($old['roads'] ?? null) ? $old['roads'] : [];
}

$out = [];
foreach (MAP_ROADS as $slug => $road) {
    $key = substr(sha1(json_encode([$road['points'] ?? $road['osm'], $road['modes'][0], $road['cap'] ?? 0])), 0, 12);
    $have = $prev[$slug] ?? null;
    $fresh = ($only !== '' && $only !== $slug) || (!$force && $have && ($have['key'] ?? '') === $key);

    if ($fresh && $have) {
        $line = roadDecode($have['geometry']);
        $base = $have;
    } elseif (!empty($road['osm'])) {
        // ---- taken from OpenStreetMap route relations rather than routed
        fwrite(STDOUT, "  {$slug}: OSM relation " . implode(',', $road['osm']) . ' ... ');
        $osm = roadFromOsm($road['osm'], $road['start'] ?? null, !empty($road['long']) ? 70 : 25);
        sleep(8);      // Overpass is a shared service
        if (!$osm || count($osm['line']) < 2) {
            fwrite(STDERR, "no geometry; the route is left out.\n");
            if ($have) {
                $out[$slug] = $have;
            }
            continue;
        }
        $kmOsm = $osm['tag_km'] > 0 ? $osm['tag_km'] : $osm['km'];
        $line = $osm['line'];
        $breaks = $osm['breaks'];
        fwrite(STDOUT, round($osm['km']) . ' km measured' . ($osm['tag_km'] > 0 ? ', ' . round($osm['tag_km']) . ' km on the relation' : '')
            . ", {$osm['ways']} ways in {$osm['chains']} chains, {$osm['jumps']} jumps, " . round($osm['skipped']) . ' km left out, ' . count($line) . ' points; '
            . sprintf('ends %.4f,%.4f -> %.4f,%.4f', $line[0][0], $line[0][1], end($line)[0], end($line)[1]) . '; elevation ... ');

        $samples = roadSample($line, ROAD_SAMPLES);
        $e = roadGet('https://api.opentopodata.org/v1/eudem25m?locations='
            . implode('|', array_map(fn ($sm) => round($sm[0], 5) . ',' . round($sm[1], 5), $samples)));
        $z = [];
        if (($e['status'] ?? '') === 'OK' && count($e['results'] ?? []) === count($samples)) {
            $last = 0;
            foreach ($e['results'] as $res) {
                $last = is_numeric($res['elevation'] ?? null) ? (int) round((float) $res['elevation']) : $last;
                $z[] = $last;
            }
        }
        fwrite(STDOUT, ($z ? 'max ' . max($z) . ' m' : 'unavailable') . "\n");
        sleep(2);
        $top = $z ? max($z) : 0;
        $low = $z ? min($z) : 0;
        // Mapped in many separate pieces, the line is not one ride from end to end: a profile along it
        // would be a drawing of the gaps. The highest point is still true; the climb is not claimed.
        if (count($breaks) > 6) {
            $z = [];
        }
        $up = 0;
        for ($i = 1, $c = count($z); $i < $c; $i++) {
            $up += max(0, $z[$i] - $z[$i - 1]);
        }
        $lats = array_column($line, 0);
        $lngs = array_column($line, 1);
        $base = [
            'key'      => $key,
            'km'       => (int) round($kmOsm),
            'min'      => 0,
            'profile'  => 'osm',
            'geometry' => roadEncode($line),
            'breaks'   => $breaks,
            'measured' => (int) round($osm['km']),
            'z'        => $z,
            'max'      => $top,
            'min_alt'  => $low,
            'up'       => $up,
            'bounds'   => [round(min($lats), 5), round(min($lngs), 5), round(max($lats), 5), round(max($lngs), 5)],
            'a'        => [round($line[0][0], 5), round($line[0][1], 5)],
            'b'        => [round(end($line)[0], 5), round(end($line)[1], 5)],
        ];
    } else {
        // A road that is only for bicycles is routed as one; anything a motorcycle takes is routed as a car.
        $profile = $road['modes'] === ['bike'] ? 'bike' : 'car';
        fwrite(STDOUT, "  {$slug}: routing ({$profile}) ... ");
        $pairs = implode(';', array_map(fn ($p) => $p[1] . ',' . $p[0], $road['points']));
        $d = roadGet('https://routing.openstreetmap.de/routed-' . $profile . '/route/v1/driving/' . $pairs
            . '?overview=full&geometries=polyline&annotations=false&steps=false');
        $r = $d['routes'][0] ?? null;
        if (($d['code'] ?? '') !== 'Ok' || !is_array($r)) {
            fwrite(STDERR, "no route; the road is left out.\n");
            if ($have) {
                $out[$slug] = $have;
            }
            continue;
        }
        $line = roadSimplify(roadDecode((string) $r['geometry']), ROAD_SIMPLIFY_M);
        fwrite(STDOUT, round($r['distance'] / 1000) . ' km, ' . count($line) . " points; elevation ... ");
        sleep(1);

        $samples = roadSample($line, ROAD_SAMPLES);
        $e = roadGet('https://api.opentopodata.org/v1/eudem25m?locations='
            . implode('|', array_map(fn ($s) => round($s[0], 5) . ',' . round($s[1], 5), $samples)));
        $z = [];
        if (($e['status'] ?? '') === 'OK' && count($e['results'] ?? []) === count($samples)) {
            $last = 0;
            foreach ($e['results'] as $res) {
                $last = is_numeric($res['elevation'] ?? null) ? (int) round((float) $res['elevation']) : $last;
                $z[] = isset($road['cap']) ? min($last, (int) $road['cap']) : $last;
            }
        }
        fwrite(STDOUT, ($z ? 'max ' . max($z) . ' m' : 'unavailable') . "\n");
        sleep(2);

        $up = 0;
        for ($i = 1, $c = count($z); $i < $c; $i++) {
            $up += max(0, $z[$i] - $z[$i - 1]);
        }
        $lats = array_column($line, 0);
        $lngs = array_column($line, 1);
        $base = [
            'key'      => $key,
            'km'       => (int) round($r['distance'] / 1000),
            'min'      => (int) round($r['duration'] / 60),
            'profile'  => $profile,
            'geometry' => roadEncode($line),
            'z'        => $z,
            'max'      => $z ? max($z) : 0,
            'min_alt'  => $z ? min($z) : 0,
            'up'       => $up,
            'bounds'   => [round(min($lats), 5), round(min($lngs), 5), round(max($lats), 5), round(max($lngs), 5)],
            'a'        => [round($line[0][0], 5), round($line[0][1], 5)],
            'b'        => [round(end($line)[0], 5), round(end($line)[1], 5)],
        ];
    }

    // where the road's number sits on the overview map: a third of the way in, clear of the ends two roads may share
    $third = roadSample($line, 4)[1];
    $base['mid'] = [round($third[0], 5), round($third[1], 5)];

    // ---- the catalogue's attractions beside the line, in the order you meet them
    $along = roadSample($line, 240);
    $box = [min(array_column($line, 0)) - 0.04, min(array_column($line, 1)) - 0.06, max(array_column($line, 0)) + 0.04, max(array_column($line, 1)) + 0.06];
    $near = [];
    foreach ($pins['points'] as $p) {
        $lat = $p[$f['lat_e5']] / 1e5;
        $lng = $p[$f['lng_e5']] / 1e5;
        if ($lat < $box[0] || $lat > $box[2] || $lng < $box[1] || $lng > $box[3]) {
            continue;
        }
        $best = INF;
        $pos = 0.0;
        foreach ($along as $s) {
            $dist = roadKm($lat, $lng, $s[0], $s[1]);
            if ($dist < $best) {
                $best = $dist;
                $pos = $s[2];
            }
        }
        if ($best > ROAD_REACH_KM) {
            continue;
        }
        $t = $p[$f['type']] >= 0 ? $pins['types'][$p[$f['type']]] : null;
        $score = ($typeWeight[$t[0] ?? ''] ?? 0) + (($p[$f['flags']] & $imgFlag) ? 2.5 : 0) - $best;
        $near[] = ['p' => $p, 't' => $t, 'lat' => $lat, 'lng' => $lng, 'pos' => $pos, 'score' => $score];
    }
    usort($near, fn ($a, $b) => $b['score'] <=> $a['score']);
    // The best ones, but never two on top of each other: a town centre would otherwise take every slot.
    $picked = [];
    $gap = max(1.5, $base['km'] / 25);
    foreach ($near as $c) {
        if (count($picked) >= ROAD_STOPS_MAX) {
            break;
        }
        if ($c['score'] < 1.5) {
            continue;
        }
        $clash = false;
        foreach ($picked as $q) {
            if (abs($q['pos'] - $c['pos']) < $gap) {
                $clash = true;
                break;
            }
        }
        if (!$clash) {
            $picked[] = $c;
        }
    }
    usort($picked, fn ($a, $b) => $a['pos'] <=> $b['pos']);

    $stops = [];
    $before = 0.0;
    foreach ($picked as $c) {
        $p = $c['p'];
        $city = $p[$f['city']] >= 0 ? $pins['cities'][$p[$f['city']]] : null;
        $zone = $p[$f['zone']] >= 0 ? $pins['zones'][$p[$f['zone']]] : null;
        $stops[] = [
            $p[$f['slug']], $p[$f['name']], $city ? $city[1] : '', $city ? $city[0] : '', $zone ? $zone[0] : '',
            $c['t'] ? $c['t'][1] : '', $c['t'] ? $c['t'][0] : '', round($c['lat'], 5), round($c['lng'], 5), $p[$f['img']],
            round($c['pos'] - $before, 1), 0, round($c['pos'], 1),
        ];
        $before = $c['pos'];
    }

    $base['stops'] = $stops;
    $out[$slug] = $base;
    fwrite(STDOUT, "  {$slug}: {$base['km']} km, " . count($stops) . " attractions beside it\n");
}

if (count($out) < (int) floor(count(MAP_ROADS) * 0.8) && !$force) {
    fwrite(STDERR, 'Only ' . count($out) . ' of ' . count(MAP_ROADS) . " roads resolved; nothing written (use --force to write anyway).\n");
    exit(1);
}

$payload = ['v' => 1, 'generated_at' => gmdate('c'), 'roads' => $out];
file_put_contents($outFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
fwrite(STDOUT, 'Wrote ' . count($out) . ' roads, ' . round(filesize($outFile) / 1024) . " KB -> {$outFile}\n");
