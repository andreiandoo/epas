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
