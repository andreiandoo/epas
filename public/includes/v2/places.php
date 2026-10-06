<?php
/**
 * Viaqui v2: shared pieces of the destination pages (/cities, /{country}).
 *
 * Styles: assets/v2/css/places.css (classes start with "v-").
 */

/** "2.3 million", "367,000", "11,900": a population a reader takes in at a glance. */
function v2_population(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . ' million';
    }
    if ($n >= 100000) {
        return number_format(round($n / 1000) * 1000);
    }
    return $n > 0 ? number_format(round($n / 100) * 100) : '';
}

/** A city as an arch card: photo when there is one, the line illustration otherwise. */
function v2_city_card(array $c, int $i = 0): string
{
    $photo = $c['photo'] ?? null;
    $meta = array_filter([$c['region'] ?? '', !empty($c['population']) ? v2_population((int) $c['population']) . ' inhabitants' : '']);
    return '<a class="v-pcard" href="' . v2_e($c['href']) . '">'
        . '<span class="v-pcard-ph">' . ($photo ? v2_photo([$photo[0], 0, 0, $photo[3] ?? '']) : v2_fallback($c['name'], $i))
        . (!empty($c['capital']) ? '<small>Capital</small>' : '') . '</span>'
        . '<strong>' . v2_e($c['name']) . '</strong>'
        . ($meta ? '<span>' . v2_e(implode(' · ', $meta)) . '</span>' : '')
        . '</a>';
}

/** A city row of the API (/locations/cities) in the shape the cards and lists use. */
function v2_city_from_api(array $c): array
{
    $img = v2_media_url($c['image'] ?? null);
    $local = V2_SEED_CITY_PHOTOS[$c['slug']] ?? null;
    return [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'region' => (string) ($c['region'] ?? ''),
        'population' => (int) ($c['population'] ?? 0),
        'capital' => !empty($c['is_capital']),
        'href' => '/' . $c['slug'],
        'photo' => $img ? [$img, 0, 0, ''] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
    ];
}
