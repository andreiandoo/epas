<?php
/**
 * Nav helpers — adapt API responses into the shape header.php / category.php /
 * categorii.php expect. Pure transformation; underlying fetch + cache live in
 * nav-cache.php (getEventCategories, getFeaturedCities).
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/nav-cache.php';

/**
 * Map a hex color from the seeded palette back to a Tailwind palette
 * keyword so we can render `bg-vermilion/10 text-vermilion` classes that
 * are safelisted. Unknown hex → vermilion fallback (most common).
 */
function navAccentFromHex(?string $hex): string
{
    if (!$hex) return 'vermilion';
    $hex = strtoupper(trim($hex));
    return match ($hex) {
        '#E84527', '#C5371C' => 'vermilion',
        '#1E4A3D', '#2E6553' => 'forest',
        '#DA9A33' => 'ochre',
        '#2C5F8A' => 'sky',
        default => 'vermilion',
    };
}

/**
 * Normalize a translatable API name field (array OR string) to a flat string.
 */
function navFlatName($value, string $locale = 'ro'): string
{
    if (is_array($value)) {
        return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? (string) (reset($value) ?: '');
    }
    return (string) ($value ?? '');
}

/**
 * Fetch + transform parent event categories for use in header mega menu.
 * Returns an array of items with the shape header.php consumes:
 *   ['label', 'href', 'count', 'accent', 'icon_emoji', 'slug']
 *
 * @param int|null $limit Max items to return (default 6 for mega menu)
 * @return array
 */
function navGetCategories(?int $limit = 6): array
{
    $raw = getEventCategories();
    if (empty($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $cat) {
        // Skip child categories — mega menu shows parents only.
        // The API returns parent + nested children; if it returns a flat
        // list with parent_id we filter on that.
        if (!empty($cat['parent_id'])) {
            continue;
        }

        $name = navFlatName($cat['name'] ?? '');
        $slug = $cat['slug'] ?? '';
        if (!$slug || !$name) continue;

        $count = (int) ($cat['count'] ?? $cat['event_count'] ?? 0);
        $items[] = [
            'label' => $name,
            'href' => '/' . ltrim($slug, '/'),
            'slug' => $slug,
            'count' => $count > 0
                ? ($count . ' ' . ($count === 1 ? 'activitate' : 'activități'))
                : 'în curând',
            'accent' => navAccentFromHex($cat['color'] ?? null),
            'icon_emoji' => $cat['icon_emoji'] ?? '🎫',
        ];

        if ($limit !== null && count($items) >= $limit) break;
    }
    return $items;
}

/**
 * Fetch + transform featured cities for use in header mega menu.
 * Returns [['label','href','slug'], ...]
 *
 * @param int|null $limit Max items
 */
function navGetCities(?int $limit = 8): array
{
    $raw = getFeaturedCities();
    if (empty($raw)) return [];

    $items = [];
    foreach ($raw as $c) {
        $name = navFlatName($c['name'] ?? '');
        $slug = $c['slug'] ?? '';
        if (!$slug || !$name) continue;

        $items[] = [
            'label' => $name,
            'href' => '/' . ltrim($slug, '/'),
            'slug' => $slug,
        ];

        if ($limit !== null && count($items) >= $limit) break;
    }
    return $items;
}

/**
 * Fetch a single category by slug with its meta + children + sample events.
 * Returns the full API payload or null if not found.
 */
function navGetCategoryBySlug(string $slug): ?array
{
    $cacheKey = 'category_full_' . $slug;
    $resp = api_cached($cacheKey, function () use ($slug) {
        return api_get('/event-categories/' . urlencode($slug));
    }, 300);

    if (!is_array($resp) || empty($resp['success']) || empty($resp['data'])) {
        return null;
    }
    return $resp['data'];
}

/**
 * Fetch a single city by slug. Returns the full city payload (with
 * description, cover, lat/lng, region, county, events_count) or null.
 */
function navGetCityBySlug(string $slug): ?array
{
    $cacheKey = 'city_full_' . $slug;
    $resp = api_cached($cacheKey, function () use ($slug) {
        return api_get('/locations/cities/' . urlencode($slug));
    }, 300);

    if (!is_array($resp) || empty($resp['success'])) {
        return null;
    }
    // LocationsController::city() wraps the city in data.city
    return $resp['data']['city'] ?? $resp['data'] ?? null;
}

// ============================================================
// Shared formatting helpers used by category.php / city.php / city-intent.php
// Declared once here so each render template can be included multiple times
// from a dispatcher without "Cannot redeclare function" fatals.
// ============================================================

if (!function_exists('navFormatPriceCents')) {
    /**
     * Format an integer price-in-cents into a human-readable lei string.
     * null → "—" (unknown/uncomputed), 0 → "Gratuit", >0 → "1.230 lei".
     */
    function navFormatPriceCents(?int $cents): string {
        if ($cents === null) return '—';
        if ($cents === 0) return 'Gratuit';
        return number_format($cents / 100, 0, ',', '.') . ' lei';
    }
}

if (!function_exists('navEventTitle')) {
    /**
     * Pull a flat translated title from an event payload that may have
     * `title` as either string or {ro:..., en:...} array.
     */
    function navEventTitle(array $ev, string $locale = 'ro'): string {
        if (is_array($ev['title'] ?? null)) {
            return $ev['title'][$locale] ?? $ev['title']['ro'] ?? $ev['title']['en'] ?? reset($ev['title']);
        }
        return $ev['title'] ?? 'Activitate';
    }
}

if (!function_exists('navEventCity')) {
    /**
     * Pull the city label for an event, preferring marketplace_city.name
     * (translatable) over venue.city (plain string).
     */
    function navEventCity(array $ev, string $locale = 'ro'): string {
        $c = $ev['marketplace_city'] ?? null;
        if ($c && is_array($c['name'] ?? null)) {
            return $c['name'][$locale] ?? $c['name']['ro'] ?? '';
        }
        return $ev['venue']['city'] ?? '';
    }
}

if (!function_exists('navEventCategoryLabel')) {
    function navEventCategoryLabel(array $ev, string $locale = 'ro'): string {
        $c = $ev['marketplace_event_category'] ?? null;
        if ($c && is_array($c['name'] ?? null)) {
            return $c['name'][$locale] ?? $c['name']['ro'] ?? '';
        }
        return '';
    }
}

if (!function_exists('navResetQueryString')) {
    /**
     * Build a query string from $_GET-like array, omitting the given keys
     * (and always omitting `slug` since .htaccess injects it). Returns
     * "?k=v&..." or "" if no params remain.
     */
    function navResetQueryString(array $get, array $omit): string {
        $kept = array_diff_key($get, array_flip($omit));
        unset($kept['slug']);
        return $kept ? '?' . http_build_query($kept) : '';
    }
}

if (!function_exists('navMbUcfirst')) {
    function navMbUcfirst(string $s, string $enc = 'UTF-8'): string {
        if ($s === '') return '';
        return mb_strtoupper(mb_substr($s, 0, 1, $enc), $enc) . mb_substr($s, 1, null, $enc);
    }
}
