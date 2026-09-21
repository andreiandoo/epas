<?php
/**
 * bilete.online v2: small helpers shared by the v2 partials and pages.
 * Prefixed v2_ so nothing collides with the rest of the site.
 */

function v2_e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v2_ic(string $name, string $cls = 'ic'): string
{
    return '<svg class="' . $cls . '" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
}

/** Romanian counting: 1 experiență, 5 experiențe, 20 de experiențe, 101 experiențe. */
function v2_num(int $n, string $one, string $many): string
{
    if ($n === 1) {
        return '1 ' . $one;
    }
    $rem = $n % 100;
    $de = $n >= 20 && !($rem >= 1 && $rem <= 19);
    return $n . ' ' . ($de ? 'de ' : '') . $many;
}

function v2_exp(int $n): string
{
    return v2_num($n, 'experiență', 'experiențe');
}

function v2_thousands(int $n): string
{
    return number_format($n, 0, ',', '.');
}

function v2_asset(string $path): string
{
    return asset('assets/v2/' . ltrim($path, '/'));
}

/**
 * The pin dataset behind the interactive map, as written by
 * bin/build-map-data.php: ['url', 'v', 'total', 'types', 'cities', 'generated_at'].
 *
 * Returns null when the dataset has not been built yet, which is the signal
 * for callers to hide the map entry points instead of shipping a button that
 * opens an empty map.
 */
function v2_map_data(): ?array
{
    static $cached = false;
    static $value = null;

    if ($cached) {
        return $value;
    }
    $cached = true;

    $metaFile = BILETEONLINE_ROOT . '/data/map/atractii.meta.json';
    $dataFile = BILETEONLINE_ROOT . '/data/map/atractii.json';
    if (!is_file($metaFile) || !is_file($dataFile)) {
        return $value;
    }

    $meta = json_decode((string) file_get_contents($metaFile), true);
    if (!is_array($meta) || (int) ($meta['total'] ?? 0) < 1) {
        return $value;
    }

    $version = (string) ($meta['v'] ?? filemtime($dataFile));
    $value = [
        // ?v=<content hash>: the file name stays stable, the URL changes
        // whenever the data does, and data/map/.htaccess can mark it immutable.
        'url'          => '/data/map/atractii.json?v=' . rawurlencode($version),
        'v'            => $version,
        'total'        => (int) $meta['total'],
        'types'        => (int) ($meta['types'] ?? 0),
        'cities'       => (int) ($meta['cities'] ?? 0),
        'bytes'        => (int) ($meta['bytes'] ?? 0),
        'generated_at' => (string) ($meta['generated_at'] ?? ''),
    ];

    return $value;
}

/** API media paths come either absolute or relative to the core storage. */
function v2_media_url($path): ?string
{
    if (!is_string($path) || $path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim(STORAGE_URL, '/') . '/' . ltrim($path, '/');
}

function v2_cauta(string $term): string
{
    return '/cauta?q=' . rawurlencode($term);
}

function v2_duration(int $minutes): string
{
    if ($minutes <= 0) {
        return '';
    }
    if ($minutes >= 120 && $minutes % 60 === 0) {
        return ($minutes / 60) . ' ore';
    }
    return $minutes . ' min';
}

/** <img> from a [src, width, height, alt] tuple; dimensions only when known. */
function v2_photo(?array $photo, string $extra = ''): string
{
    if (!$photo || empty($photo[0])) {
        return '';
    }
    $dims = !empty($photo[1]) ? ' width="' . (int) $photo[1] . '" height="' . (int) $photo[2] . '"' : '';
    return '<img src="' . v2_e($photo[0]) . '"' . $dims . ' alt="' . v2_e($photo[3] ?? '') . '" loading="lazy" decoding="async"' . $extra . '>';
}

/** Image-less state: a segment of the brand line on deep green, picked from the name. */
function v2_fallback(string $seed, ?int $position = null): string
{
    static $segs = [
        ['1060 585 220 310', '220 / 310'], ['1455 585 290 310', '290 / 310'],
        ['2170 625 340 270', '340 / 270'], ['2665 625 250 270', '250 / 270'],
    ];
    // In a row of cards the position keeps neighbours different; alone, the name decides.
    $sum = $position ?? 0;
    if ($position === null) {
        foreach (preg_split('//u', $seed, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $sum += mb_ord($ch);
        }
    }
    [$vb, $ar] = $segs[$sum % count($segs)];
    return '<span class="fb" aria-hidden="true"><svg viewBox="' . $vb . '" style="aspect-ratio:' . $ar . '"><use href="#drum-g"/></svg></span>';
}

function v2_brand(string $cls = 'brand'): string
{
    return '<span class="' . $cls . '" role="img" aria-label="bilete.online">'
        . '<svg class="s" viewBox="24 33 148 205" aria-hidden="true"><use href="#sym-g"/></svg>'
        . '<svg class="w" viewBox="52 68 514 74" aria-hidden="true"><use href="#logo-g"/></svg></span>';
}

/** Shape of an activity from /activities, as the cards need it. */
function v2_activity(array $a): ?array
{
    $title = navFlatName($a['title'] ?? '');
    $slug = (string) ($a['slug'] ?? '');
    if ($title === '' || $slug === '') {
        return null;
    }
    $city = is_array($a['city'] ?? null) ? $a['city'] : [];
    $cat = is_array($a['category'] ?? null) ? $a['category'] : [];
    $reviews = is_array($a['reviews'] ?? null) ? $a['reviews'] : [];
    $citySlug = (string) ($city['slug'] ?? '');
    return [
        'slug' => $slug,
        'title' => $title,
        'city' => navFlatName($city['name'] ?? ''),
        'cat' => (string) ($cat['slug'] ?? ''),
        'catName' => navFlatName($cat['name'] ?? ''),
        'price' => (int) round(((int) ($a['cheapest_price_cents'] ?? 0)) / 100),
        'dur' => v2_duration((int) ($a['duration_minutes'] ?? 0)),
        'rating' => round((float) ($reviews['average'] ?? 0), 1),
        'reviews' => (int) ($reviews['count'] ?? 0),
        'image' => v2_media_url($a['cover_image_url'] ?? null),
        'href' => '/experienta/' . $slug,
        'dates' => [],
    ];
}

/** Shape of an attraction from /attractions or /attractions/{slug}. */
function v2_attraction(array $a): ?array
{
    $slug = (string) ($a['slug'] ?? '');
    $name = navFlatName($a['name'] ?? '');
    if ($slug === '' || $name === '') {
        return null;
    }
    return [
        'slug' => $slug,
        'name' => $name,
        'city' => is_array($a['city'] ?? null) ? navFlatName($a['city']['name'] ?? '') : '',
        'type' => is_array($a['type'] ?? null) ? (string) ($a['type']['name'] ?? '') : '',
        'image' => v2_media_url($a['cover_image_url'] ?? null),
        'href' => '/atractie/' . $slug,
    ];
}
