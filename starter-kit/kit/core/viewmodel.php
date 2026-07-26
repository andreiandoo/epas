<?php
/**
 * Canonical view-models.
 *
 * The whole point of the kit: components NEVER see a raw API payload.
 * Both the marketplace-client API (flat) and the tenant-client API (nested)
 * are normalized by an adapter into ONE canonical shape defined here.
 *
 * A component that receives a canonical `event` array can therefore be reused
 * across both profiles without a single conditional. Add a field here once,
 * teach both adapters to fill it, and every component gets it for free.
 */

if (!function_exists('vm_event_defaults')) {

/** Canonical EVENT. Every key is always present so components never isset()-guard. */
function vm_event_defaults(): array {
    return [
        'id'            => 0,
        'slug'          => '',
        'title'         => 'Eveniment',
        'category'      => '',        // human name, e.g. "Teatru"
        'category_slug' => '',
        'venue_name'    => '',
        'city'          => '',
        'country'       => '',
        'starts_at'     => null,      // ISO 8601 or null
        'date'          => '',        // Y-m-d
        'time'          => '',        // H:i
        'end_date'      => '',        // Y-m-d for ranges/tours
        'duration_mode' => 'single',  // single | range | recurring
        'poster_url'    => '',
        'hero_image_url'=> '',
        'price_from'    => null,      // float, MAJOR units (lei, not bani); null = free/unknown
        'currency'      => 'RON',
        'is_sold_out'   => false,
        'is_cancelled'  => false,
        'is_postponed'  => false,
        'is_promoted'   => false,
        'url'           => '#',       // permalink within THIS site (built from config pattern)
        'short_description' => '',
        'description'   => '',
        'ticket_types'  => [],        // [{name, price, sale_price, currency}]
        'artists'       => [],        // [{name, slug, image}]
        'tags'          => [],
    ];
}

/** Canonical VENUE. */
function vm_venue_defaults(): array {
    return [
        'id' => 0, 'slug' => '', 'name' => '', 'city' => '', 'country' => '',
        'image_url' => '', 'events_count' => null, 'url' => '#',
    ];
}

/** Canonical ARTIST. */
function vm_artist_defaults(): array {
    return [
        'id' => 0, 'slug' => '', 'name' => '', 'role' => '', 'type' => '',
        'image' => '', 'bio' => '', 'events_count' => null, 'url' => '#',
    ];
}

/** Canonical CATEGORY / GENRE / CITY chip. */
function vm_taxonomy_defaults(): array {
    return [
        'slug' => '', 'name' => '', 'icon' => '', 'image_url' => '',
        'count' => null, 'url' => '#',
    ];
}

/**
 * Merge a partial canonical array over its defaults. Adapters build the
 * partial; this guarantees the full shape. Unknown extra keys are preserved.
 */
function vm_fill(array $partial, array $defaults): array {
    return array_replace($defaults, array_filter(
        $partial,
        static fn($v) => $v !== null || true // keep everything incl. explicit nulls
    ));
}

/**
 * Extract a calendar date (Y-m-d) from an API date string WITHOUT timezone
 * drift. An ISO value like "2026-09-18T00:00:00+03:00" must render as the 18th,
 * not shift to the 17th in the server's UTC. We take the literal date part when
 * present; otherwise fall back to strtotime.
 */
function vm_date(?string $s): string {
    if (!$s) return '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) return $m[1];
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : '';
}

/**
 * Build an on-site permalink from a config URL pattern like "/spectacol/{slug}".
 * Placeholders: {slug} {id}. Falls back to '#'.
 */
function vm_url(string $pattern, array $vars): string {
    if ($pattern === '') return '#';
    return preg_replace_callback('/\{(\w+)\}/', static function ($m) use ($vars) {
        return rawurlencode((string)($vars[$m[1]] ?? ''));
    }, $pattern);
}

} // functions guard
