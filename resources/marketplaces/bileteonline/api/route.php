<?php
/**
 * Road routing for the trip planner: real driving distance and time between a day's stops.
 *
 *   GET /api/route.php?c=45.3553,25.5496;45.3597,25.5428;45.3613,25.5362
 *   -> { ok: true, km, min, legs: [[km, min], …], geometry: "<encoded polyline>", cached: bool }
 *
 * The planner cannot call the routing service itself: it is a community instance, and a page that
 * hits it from every visitor's browser is exactly the abuse it asks you not to commit. So the call
 * happens here, once per distinct day, and the answer is cached on disk — the same day asked for
 * again, by anyone, costs nothing upstream.
 *
 * On any failure this answers ok:false and the planner falls back to its straight-line estimate,
 * which it labels as an estimate. Nothing on the page depends on this succeeding.
 */

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Romania plus a margin: a coordinate outside it is not a trip we plan, it is someone poking.
const RO_BOX = [43.0, 20.0, 49.2, 30.5];
const MAX_POINTS = 25;
const CACHE_DAYS = 30;

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ------------------------------------------------------------------ input
$raw = (string) ($_GET['c'] ?? '');
$parts = array_values(array_filter(explode(';', $raw), fn ($p) => $p !== ''));
if (count($parts) < 2 || count($parts) > MAX_POINTS) {
    out(['ok' => false, 'error' => 'between 2 and ' . MAX_POINTS . ' points'], 400);
}

$points = [];
foreach ($parts as $p) {
    $xy = explode(',', $p);
    if (count($xy) !== 2 || !is_numeric($xy[0]) || !is_numeric($xy[1])) {
        out(['ok' => false, 'error' => 'bad coordinate'], 400);
    }
    $lat = round((float) $xy[0], 5);
    $lng = round((float) $xy[1], 5);
    if ($lat < RO_BOX[0] || $lat > RO_BOX[2] || $lng < RO_BOX[1] || $lng > RO_BOX[3]) {
        out(['ok' => false, 'error' => 'outside the area'], 400);
    }
    $points[] = [$lat, $lng];
}

// ------------------------------------------------------------------ cache
$key = sha1(json_encode($points));
$cacheDir = sys_get_temp_dir() . '/bileteonline_routes';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . $key . '.json';

if (is_file($cacheFile) && filemtime($cacheFile) > time() - CACHE_DAYS * 86400) {
    $hit = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($hit)) {
        header('Cache-Control: public, max-age=86400');
        out($hit + ['cached' => true]);
    }
}

// ------------------------------------------------------------------ rate limit (only on a miss)
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', (string) $ip)[0]);
$limitDir = dirname(__DIR__) . '/data/rate-limits';
if (!is_dir($limitDir)) {
    @mkdir($limitDir, 0755, true);
}
$limitFile = $limitDir . '/route_' . md5($ip) . '.json';
$window = 60;
$max = 20;                     // a plan is a handful of days; twenty a minute is generous
$state = ['n' => 0, 'start' => time()];
if (is_file($limitFile)) {
    $decoded = json_decode((string) @file_get_contents($limitFile), true);
    if (is_array($decoded) && ($decoded['start'] ?? 0) > time() - $window) {
        $state = $decoded;
    }
}
$state['n']++;
@file_put_contents($limitFile, json_encode($state), LOCK_EX);
if ($state['n'] > $max) {
    out(['ok' => false, 'error' => 'too many requests'], 429);
}

// ------------------------------------------------------------------ upstream
$pairs = array_map(fn ($p) => $p[1] . ',' . $p[0], $points);   // OSRM wants lng,lat
$url = 'https://routing.openstreetmap.de/routed-car/route/v1/driving/' . implode(';', $pairs)
    . '?overview=simplified&geometries=polyline&annotations=false&steps=false';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'bilete.online trip planner (' . SUPPORT_EMAIL . ')',
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    out(['ok' => false, 'error' => 'routing unavailable'], 200);
}
$d = json_decode((string) $body, true);
$r = $d['routes'][0] ?? null;
if (($d['code'] ?? '') !== 'Ok' || !is_array($r) || count($r['legs'] ?? []) !== count($points) - 1) {
    out(['ok' => false, 'error' => 'routing unusable'], 200);
}

$legs = [[0.0, 0]];
foreach ($r['legs'] as $l) {
    $legs[] = [round($l['distance'] / 1000, 1), (int) round($l['duration'] / 60)];
}

$answer = [
    'ok'       => true,
    'km'       => round($r['distance'] / 1000, 1),
    'min'      => (int) round($r['duration'] / 60),
    'legs'     => $legs,
    'geometry' => (string) $r['geometry'],
];
@file_put_contents($cacheFile, json_encode($answer), LOCK_EX);

header('Cache-Control: public, max-age=86400');
out($answer + ['cached' => false]);
