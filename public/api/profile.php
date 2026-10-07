<?php
/**
 * Elevation along a day of the trip planner, for the profile shown to riders.
 *
 *   GET /api/profile.php?c=45.6044,24.6168;45.5990,24.6171;…        (2 to 80 points along the road)
 *   -> { ok: true, z: [metres, …], cached: bool }
 *
 * Same contract as /api/route.php, for the same reason: the elevation service is a free public one
 * (Open Topo Data, EU-DEM 25 m) with a daily allowance, so the browser never calls it. The planner
 * samples the routed line, asks here once per distinct day, and the answer is cached on disk.
 *
 * On any failure this answers ok:false and the planner simply shows no profile.
 */

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const PROFILE_BOX = [27.0, -32.0, 72.0, 45.0];
const PROFILE_MAX = 80;
const PROFILE_CACHE_DAYS = 180;      // mountains do not move

function profileOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ------------------------------------------------------------------ input
$parts = array_values(array_filter(explode(';', (string) ($_GET['c'] ?? '')), fn ($p) => $p !== ''));
if (count($parts) < 2 || count($parts) > PROFILE_MAX) {
    profileOut(['ok' => false, 'error' => 'between 2 and ' . PROFILE_MAX . ' points'], 400);
}
$points = [];
foreach ($parts as $p) {
    $xy = explode(',', $p);
    if (count($xy) !== 2 || !is_numeric($xy[0]) || !is_numeric($xy[1])) {
        profileOut(['ok' => false, 'error' => 'bad coordinate'], 400);
    }
    // four decimals is eleven metres: finer than the elevation grid, and it makes the cache hit more often
    $lat = round((float) $xy[0], 4);
    $lng = round((float) $xy[1], 4);
    if ($lat < PROFILE_BOX[0] || $lat > PROFILE_BOX[2] || $lng < PROFILE_BOX[1] || $lng > PROFILE_BOX[3]) {
        profileOut(['ok' => false, 'error' => 'outside the area'], 400);
    }
    $points[] = [$lat, $lng];
}

// ------------------------------------------------------------------ cache
$cacheDir = sys_get_temp_dir() . '/bileteonline_profiles';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . sha1(json_encode($points)) . '.json';
if (is_file($cacheFile) && filemtime($cacheFile) > time() - PROFILE_CACHE_DAYS * 86400) {
    $hit = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($hit)) {
        header('Cache-Control: public, max-age=604800');
        profileOut($hit + ['cached' => true]);
    }
}

// ------------------------------------------------------------------ rate limit (only on a miss)
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', (string) $ip)[0]);
$limitDir = dirname(__DIR__) . '/data/rate-limits';
if (!is_dir($limitDir)) {
    @mkdir($limitDir, 0755, true);
}
$limitFile = $limitDir . '/profile_' . md5($ip) . '.json';
$state = ['n' => 0, 'start' => time()];
if (is_file($limitFile)) {
    $decoded = json_decode((string) @file_get_contents($limitFile), true);
    if (is_array($decoded) && ($decoded['start'] ?? 0) > time() - 60) {
        $state = $decoded;
    }
}
$state['n']++;
@file_put_contents($limitFile, json_encode($state), LOCK_EX);
if ($state['n'] > 12) {
    profileOut(['ok' => false, 'error' => 'too many requests'], 429);
}

// ------------------------------------------------------------------ upstream
$url = 'https://api.opentopodata.org/v1/eudem25m?locations='
    . implode('|', array_map(fn ($p) => $p[0] . ',' . $p[1], $points));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'viaqui.com trip planner (' . SUPPORT_EMAIL . ')',
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$d = $code === 200 ? json_decode((string) $body, true) : null;
if (!is_array($d) || ($d['status'] ?? '') !== 'OK' || count($d['results'] ?? []) !== count($points)) {
    profileOut(['ok' => false, 'error' => 'elevation unavailable'], 200);
}

$z = [];
$last = 0;
foreach ($d['results'] as $r) {
    // a hole in the grid (water, a tunnel mouth) repeats the previous height rather than dropping to zero
    $last = is_numeric($r['elevation'] ?? null) ? (int) round((float) $r['elevation']) : $last;
    $z[] = $last;
}

$answer = ['ok' => true, 'z' => $z];
@file_put_contents($cacheFile, json_encode($answer), LOCK_EX);

header('Cache-Control: public, max-age=604800');
profileOut($answer + ['cached' => false]);
