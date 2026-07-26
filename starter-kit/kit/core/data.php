<?php
/**
 * Kit data layer — the ONLY thing template pages call to get data.
 *
 * Each function fetches from the correct endpoint for the active profile,
 * runs the profile's adapter, and returns canonical view-models (viewmodel.php).
 * Pages never see a raw payload, never branch on profile, never touch the API
 * shape. That is what removes the per-template wiring cost.
 */

/** Pick the event adapter for the active profile. */
function kit_event_adapter(): callable {
    return Kit::isProfile('marketplace') ? 'marketplace_adapt_event' : 'tenant_adapt_event';
}
function kit_artist_adapter(): callable {
    return Kit::isProfile('marketplace') ? 'marketplace_adapt_artist' : 'tenant_adapt_artist';
}

/** Endpoint base for the active profile. */
function kit_events_endpoint(): string {
    return Kit::isProfile('marketplace') ? '/marketplace-client/events' : '/tenant-client/events';
}

/**
 * List events → array of canonical events.
 * @param array $params  query params (per_page, page, category, city, sort, ...)
 */
function kit_events(array $params = [], ?int $ttl = null): array {
    $resp = kit_api_get(kit_events_endpoint(), $params, $ttl);
    return kit_map_events($resp);
}

/** Featured/promoted events. */
function kit_featured_events(int $limit = 8, ?int $ttl = null): array {
    $resp = kit_api_get(kit_events_endpoint() . '/featured', ['limit' => $limit], $ttl);
    return kit_map_events($resp);
}

/** Single event by slug/id → canonical event or null. */
function kit_event(string $slugOrId, ?int $ttl = null): ?array {
    $resp = kit_api_get(kit_events_endpoint() . '/' . rawurlencode($slugOrId), [], $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return null;
    $raw = $resp['data']['event'] ?? $resp['data'];
    if (!is_array($raw)) return null;
    return (kit_event_adapter())($raw, Kit::config());
}

/** Extract + adapt an event list from a response (handles data.events[] or data[]). */
function kit_map_events(array $resp): array {
    if (!($resp['success'] ?? false)) return [];
    $d = $resp['data'] ?? [];
    if (isset($d['events']) && is_array($d['events'])) $list = $d['events'];
    elseif (is_array($d) && array_keys($d) === range(0, count($d) - 1)) $list = $d;
    else $list = [];
    $adapt = kit_event_adapter();
    $cfg = Kit::config();
    return array_map(static fn($e) => is_array($e) ? $adapt($e, $cfg) : null, $list)
        + []; // keep numeric keys
}

/** Pagination meta from the last list response, if the caller kept it. */
function kit_meta(array $resp): array {
    return $resp['meta'] ?? [];
}

/** Artists / troupe → canonical artists. */
function kit_artists(array $params = [], ?int $ttl = null): array {
    $endpoint = Kit::isProfile('marketplace') ? '/marketplace-client/artists' : '/tenant-client/artists';
    $resp = kit_api_get($endpoint, $params, $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return [];
    $list = $resp['data']['artists'] ?? $resp['data'];
    if (!is_array($list)) return [];
    $adapt = kit_artist_adapter();
    $cfg = Kit::config();
    return array_values(array_map(static fn($a) => is_array($a) ? $adapt($a, $cfg) : null, $list));
}

/** Single artist by slug → canonical artist or null. */
function kit_artist(string $slug, ?int $ttl = null): ?array {
    $endpoint = Kit::isProfile('marketplace') ? '/marketplace-client/artists/' : '/tenant-client/artists/';
    $resp = kit_api_get($endpoint . rawurlencode($slug), [], $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return null;
    return (kit_artist_adapter())($resp['data'], Kit::config());
}

/** Subscription plans (tenant only). Returns raw plan arrays (shape is tenant-specific). */
function kit_subscriptions(?int $ttl = null): array {
    if (!Kit::isProfile('tenant')) return [];
    $resp = kit_api_get('/tenant-client/subscriptions', [], $ttl);
    return ($resp['success'] ?? false) && is_array($resp['data'] ?? null) ? $resp['data'] : [];
}

/** Categories/taxonomy chips → canonical taxonomy view-models. */
function kit_categories(?int $ttl = null): array {
    $endpoint = Kit::isProfile('marketplace') ? '/marketplace-client/events/categories' : '/tenant-client/categories';
    $resp = kit_api_get($endpoint, [], $ttl);
    $list = $resp['data']['categories'] ?? $resp['data'] ?? [];
    if (!is_array($list)) return [];
    $cfg = Kit::config();
    $out = [];
    foreach ($list as $c) {
        if (!is_array($c)) continue;
        $slug = $c['slug'] ?? '';
        $out[] = vm_fill([
            'slug'  => $slug,
            'name'  => $c['name'] ?? '',
            'icon'  => $c['icon'] ?? '',
            'image_url' => kit_asset_url($c['image_url'] ?? null, $cfg),
            'count' => $c['events_count'] ?? $c['count'] ?? null,
            'url'   => vm_url($cfg['category_url_pattern'] ?? '/category/{slug}', ['slug' => $slug]),
        ], vm_taxonomy_defaults());
    }
    return $out;
}
