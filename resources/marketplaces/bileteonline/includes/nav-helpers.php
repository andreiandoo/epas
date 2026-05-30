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
 * Strip the parent-slug suffix from a subcategory slug so URLs render
 * in their short form. Returns the original slug unchanged for top-level
 * categories (no parent suffix to strip).
 *
 * Examples:
 *   "muzee-de-stiinta-muzee-expozitii" → "muzee-de-stiinta"
 *   "escape-rooms-clasice-escape-rooms" → "escape-rooms-clasice"
 *   "escape-rooms"                      → "escape-rooms"  (top-level, unchanged)
 *
 * The legacy long slug stays the canonical record in the DB; this helper
 * is what we use for OUTBOUND links + the 301 redirect target. Inbound
 * requests for the long form get a 301 to the short form (see slug.php).
 */
function bo_short_category_slug($category): string
{
    if (is_array($category)) {
        $slug = $category['slug'] ?? '';
        $parentSlug = $category['parent_slug']
            ?? ($category['parent']['slug'] ?? null);
    } else {
        $slug = (string) $category;
        $parentSlug = null;
    }

    if (! $slug) return '';

    // Explicit parent — strip if it matches the trailing segment.
    if ($parentSlug && $slug !== $parentSlug) {
        if (str_ends_with($slug, '-' . $parentSlug)) {
            return substr($slug, 0, -1 - strlen($parentSlug));
        }
    }

    // Fallback: scan all known top-level categories. The category list is
    // already memoised by navGetCategories(); we walk the cached parents and
    // strip the longest matching trailing segment.
    static $parentsCache = null;
    if ($parentsCache === null) {
        $parentsCache = [];
        try {
            foreach (navGetCategories(50) as $cat) {
                if (! empty($cat['slug']) && empty($cat['parent_id'])) {
                    $parentsCache[] = $cat['slug'];
                }
            }
            usort($parentsCache, fn ($a, $b) => strlen($b) - strlen($a));
        } catch (\Throwable $e) {
            $parentsCache = [];
        }
    }
    foreach ($parentsCache as $p) {
        if ($slug !== $p && str_ends_with($slug, '-' . $p)) {
            return substr($slug, 0, -1 - strlen($p));
        }
    }
    return $slug;
}

/**
 * Fetch the FULL category list — parents + every subcategory — including
 * the parent_id so we can strip the parent-suffix correctly. Cached
 * 10 min on disk via api_cached() so this is one API roundtrip per
 * marketplace per 10min, regardless of how many requests hit slug.php.
 *
 * navGetCategories() returns ONLY top-level categories (it skips anything
 * with parent_id set), which is what the mega-menu wants. This helper is
 * what we need for short-URL reverse lookup.
 */
function bo_all_categories_flat(): array
{
    $resp = api_cached('all_event_categories_flat', function () {
        return api_get('/events/categories', ['all' => 1]);
    }, 600);
    $rows = $resp['data']['categories'] ?? [];
    return is_array($rows) ? $rows : [];
}

/**
 * Reverse lookup — given a short category slug like "muzee-de-stiinta",
 * find the full category record whose stored slug strips down to it.
 *
 * Used by slug.php to render the short URL without forcing every category
 * to be migrated in the DB.
 */
function bo_find_category_by_short_slug(string $shortSlug): ?array
{
    if ($shortSlug === '') return null;
    // Cache the lookup table for the lifetime of the request (cheap — we
    // call this at most once per page load).
    static $shortToFull = null;
    if ($shortToFull === null) {
        $shortToFull = [];
        try {
            // First pass: index parents by id so we can resolve parent_slug
            // for any child whose API row only has parent_id (no nested
            // parent object).
            $allCategories = bo_all_categories_flat();
            $parentById = [];
            foreach ($allCategories as $cat) {
                if (empty($cat['parent_id']) && ! empty($cat['slug'])) {
                    $parentById[$cat['id']] = $cat;
                }
            }
            // Second pass: build short→full map. For every category we
            // hydrate the `parent_slug` field so bo_short_category_slug()
            // can do its job. Top-level entries strip to themselves.
            foreach ($allCategories as $cat) {
                if (empty($cat['slug'])) continue;
                if (! empty($cat['parent_id']) && isset($parentById[$cat['parent_id']])) {
                    $cat['parent_slug'] = $parentById[$cat['parent_id']]['slug'];
                }
                $candidate = bo_short_category_slug($cat);
                if ($candidate && ! isset($shortToFull[$candidate])) {
                    $shortToFull[$candidate] = $cat;
                }
            }
        } catch (\Throwable $e) {}
    }
    if (! isset($shortToFull[$shortSlug])) return null;

    // Re-resolve via navGetCategoryBySlug so the page gets the full
    // payload (description, hero image, related categories etc.).
    return navGetCategoryBySlug($shortToFull[$shortSlug]['slug'])
        ?: $shortToFull[$shortSlug];
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

if (!function_exists('navBuildUrl')) {
    /**
     * Build a URL preserving current filter state, with selective overrides
     * (and a list of keys to clear). Always strips `slug` (set by .htaccess
     * rewrite) and `page` (reset pagination when filters change).
     *
     * navBuildUrl('escape-rooms', $_GET, ['city' => 'cluj-napoca'])
     *   → "/escape-rooms?city=cluj-napoca&q=...&sort=..."
     */
    function navBuildUrl(string $slug, array $get, array $override = [], array $omit = []): string {
        $params = array_merge($get, $override);
        foreach ($omit as $k) unset($params[$k]);
        unset($params['slug'], $params['page']);
        return '/' . $slug . ($params ? '?' . http_build_query($params) : '');
    }
}
