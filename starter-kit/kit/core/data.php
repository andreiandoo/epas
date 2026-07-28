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
 * Translate the kit's canonical list params into the API's dialect.
 * Both clients paginate with per_page + page; only the free-text key differs
 * (callers say `q`, both backends filter on `search`). Verified live against
 * /tenant-client/events and /marketplace-client/events.
 */
function kit_list_params(array $p): array {
    if (isset($p['q'])) { $p['search'] = $p['q']; unset($p['q']); }
    return $p;
}

/**
 * List events → array of canonical events.
 * @param array $params  query params (per_page, page, q, category, city, sort, ...)
 */
function kit_events(array $params = [], ?int $ttl = null): array {
    $resp = kit_api_get(kit_events_endpoint(), kit_list_params($params), $ttl);
    return kit_map_events($resp);
}

/** Featured/promoted events. */
function kit_featured_events(int $limit = 8, ?int $ttl = null): array {
    $resp = kit_api_get(kit_events_endpoint() . '/featured', ['limit' => $limit], $ttl);
    return kit_map_events($resp);
}

/**
 * Single event by slug/id → canonical event or null.
 *
 * marketplace-client returns the event under data.event but hangs `venue`,
 * `ticket_types` and `artists` off data as SIBLINGS; fold them back in so both
 * profiles hand the adapter one complete event.
 */
function kit_event(string $slugOrId, ?int $ttl = null): ?array {
    $resp = kit_api_get(kit_events_endpoint() . '/' . rawurlencode($slugOrId), [], $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return null;
    $d = $resp['data'];
    $raw = $d['event'] ?? $d;
    if (!is_array($raw)) return null;
    if (isset($d['event'])) {
        foreach (['venue', 'ticket_types', 'artists', 'gallery', 'genres', 'performances'] as $k) {
            if (isset($d[$k]) && !isset($raw[$k])) $raw[$k] = $d[$k];
        }
    }
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

/**
 * Venues → canonical venue view-models.
 * Marketplace only: tenant-client ships venue data inside each event and has no
 * /venues endpoint, so a tenant site renders its venue list from its events.
 */
function kit_venues(array $params = [], ?int $ttl = null): array {
    if (!Kit::isProfile('marketplace')) return kit_venues_from_events($ttl);
    $resp = kit_api_get('/marketplace-client/venues', kit_list_params($params), $ttl);
    $list = $resp['data']['venues'] ?? $resp['data'] ?? [];
    if (!is_array($list)) return [];
    $cfg = Kit::config();
    $out = [];
    foreach ($list as $v) {
        if (!is_array($v)) continue;
        $slug = $v['slug'] ?? (string)($v['id'] ?? '');
        $out[] = vm_fill([
            'id' => $v['id'] ?? 0, 'slug' => $slug,
            'name' => $v['name'] ?? '', 'city' => $v['city'] ?? '', 'country' => $v['country'] ?? '',
            'image_url' => kit_asset_url($v['image_url'] ?? $v['poster_url'] ?? null, $cfg),
            'events_count' => $v['events_count'] ?? $v['count'] ?? null,
            'url' => vm_url($cfg['venue_url_pattern'] ?? '/venue/{slug}', ['slug' => $slug, 'id' => $v['id'] ?? 0]),
        ], vm_venue_defaults());
    }
    return $out;
}

/** ASCII-fold Romanian/Hungarian diacritics so a name can become a URL slug. */
function kit_deaccent(string $s): string {
    return strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U']);
}

/** Derive a venue list from the tenant's events (see kit_venues). */
function kit_venues_from_events(?int $ttl = null): array {
    $cfg = Kit::config();
    $out = [];
    foreach (kit_events(['per_page' => 100], $ttl) as $e) {
        $name = $e['venue_name'] ?? '';
        if ($name === '' || isset($out[$name])) {
            if ($name !== '') $out[$name]['events_count']++;
            continue;
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(kit_deaccent($name))), '-');
        $out[$name] = vm_fill([
            'slug' => $slug, 'name' => $name, 'city' => $e['city'] ?? '',
            'events_count' => 1,
            'url' => vm_url($cfg['venue_url_pattern'] ?? '/venue/{slug}', ['slug' => $slug]),
        ], vm_venue_defaults());
    }
    return array_values($out);
}

/** Subscription plans (tenant only). Returns raw plan arrays (shape is tenant-specific). */
function kit_subscriptions(?int $ttl = null): array {
    if (!Kit::isProfile('tenant')) return [];
    $resp = kit_api_get('/tenant-client/subscriptions', [], $ttl);
    return ($resp['success'] ?? false) && is_array($resp['data'] ?? null) ? $resp['data'] : [];
}

/**
 * A CMS page (terms, privacy, custom) by slug → ['title','html','description'] or null.
 * Backend: /tenant-client/pages/{slug}. marketplace-client has no CMS pages
 * endpoint, so a marketplace template supplies legal copy from its own template.
 */
function kit_page(string $slug, ?int $ttl = null): ?array {
    if (Kit::isProfile('marketplace')) return null;
    $resp = kit_api_get('/tenant-client/pages/' . rawurlencode($slug), [], $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return null;
    $d = $resp['data']['page'] ?? $resp['data'];
    if (!is_array($d)) return null;
    return [
        'title'       => $d['title'] ?? '',
        'html'        => $d['content'] ?? $d['body_html'] ?? $d['body'] ?? '',
        'description' => $d['meta_description'] ?? $d['excerpt'] ?? '',
    ];
}

/**
 * Blog posts (list) → [{title,slug,excerpt,image,date,author,url}].
 * The two backends differ in path: marketplace-client/blog-articles vs
 * tenant-client/blog (BlogController, gated on the blog microservice).
 */
function kit_blog_endpoint(): string {
    return Kit::isProfile('marketplace') ? '/marketplace-client/blog-articles' : '/tenant-client/blog';
}

function kit_posts(array $params = [], ?int $ttl = null): array {
    $resp = kit_api_get(kit_blog_endpoint(), $params, $ttl);
    $list = $resp['data']['articles'] ?? $resp['data'] ?? [];
    if (!is_array($list)) return [];
    $cfg = Kit::config();
    return array_values(array_map(fn($p) => is_array($p) ? kit_map_post($p, $cfg) : null, $list));
}

/** A single blog post by slug → normalized post + 'html', or null. */
function kit_post(string $slug, ?int $ttl = null): ?array {
    $resp = kit_api_get(kit_blog_endpoint() . '/' . rawurlencode($slug), [], $ttl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) return null;
    $d = $resp['data']['article'] ?? $resp['data'];
    if (!is_array($d)) return null;
    $post = kit_map_post($d, Kit::config());
    $post['html'] = $d['content'] ?? $d['body_html'] ?? $d['body'] ?? '';
    return $post;
}

function kit_map_post(array $p, array $cfg): array {
    $slug = $p['slug'] ?? (string)($p['id'] ?? '');
    // marketplace-client returns author as {name,avatar}; tenant-client a string.
    $author = $p['author'] ?? '';
    if (is_array($author)) $author = (string)($author['name'] ?? '');
    return [
        'id' => $p['id'] ?? 0, 'slug' => $slug,
        'title' => $p['title'] ?? '', 'excerpt' => $p['excerpt'] ?? $p['summary'] ?? '',
        'image' => kit_asset_url($p['image_url'] ?? $p['cover_url'] ?? $p['featured_image_url'] ?? null, $cfg),
        'date' => vm_date($p['published_at'] ?? $p['created_at'] ?? null),
        'author' => $author,
        'url' => vm_url($cfg['post_url_pattern'] ?? '/blog/{slug}', ['slug' => $slug, 'id' => $p['id'] ?? 0]),
    ];
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
            // tenant-client categories return `image`; marketplace `image_url`.
            'image_url' => kit_asset_url($c['image_url'] ?? $c['image'] ?? null, $cfg),
            'count' => $c['events_count'] ?? $c['count'] ?? null,
            'url'   => vm_url($cfg['category_url_pattern'] ?? '/category/{slug}', ['slug' => $slug]),
        ], vm_taxonomy_defaults());
    }
    return $out;
}

/* ==========================================================================
   Leisure (kind `leisure`, tenant profile only).

   A leisure venue does not sell "an event" — it sells a day pass, a rental or
   a guided activity, all of which are ticket TYPES carrying the leisure pricing
   model. The backend resolves price (weekday rules, seasons, duration
   multipliers) server-side, so nothing here recomputes it.

   Backed by /tenant-client/leisure/*, which 404s for non-leisure tenants; every
   function below degrades to an empty result rather than throwing.
   ========================================================================== */

/** True when the active site can talk to the leisure endpoints at all. */
function kit_is_leisure(): bool {
    return Kit::isProfile('tenant') && Kit::feature('booking');
}

/**
 * Bookables → canonical bookable[].
 * @param string|null $category  access | rental | activity (null = all)
 */
function kit_bookables(?string $category = null, ?int $ttl = null): array {
    if (!kit_is_leisure()) return [];
    $resp = kit_api_get('/tenant-client/leisure/bookables', $category ? ['category' => $category] : [], $ttl ?? 60);
    $list = $resp['data']['bookables'] ?? [];
    if (!is_array($list)) return [];
    $cfg = Kit::config();
    return array_values(array_map(fn ($b) => is_array($b) ? kit_map_bookable($b, $cfg) : null, $list));
}

/**
 * Availability for a month → ['YYYY-MM-DD' => [status, remaining, price, slots]].
 * `status` is one of available | limited | sold_out | closed | unavailable.
 */
function kit_availability(?int $ticketTypeId = null, ?string $month = null, ?int $ttl = null): array {
    if (!kit_is_leisure()) return [];
    $params = ['month' => $month ?: date('Y-m')];
    if ($ticketTypeId) $params['ticket_type'] = $ticketTypeId;
    $resp = kit_api_get('/tenant-client/leisure/availability', $params, $ttl ?? 60);
    $dates = $resp['data']['dates'] ?? [];
    if (!is_array($dates)) return [];
    $out = [];
    foreach ($dates as $date => $d) {
        $out[$date] = [
            'status'    => $d['status'] ?? 'unavailable',
            'remaining' => $d['remaining'] ?? null,
            'price'     => isset($d['min_price_cents']) ? $d['min_price_cents'] / 100 : null,
            'slots'     => (int) ($d['slot_count'] ?? 0),
        ];
    }
    return $out;
}

/** Slots inside one day → [{capacity_id, start, end, status, remaining, price}]. */
function kit_slots(int $ticketTypeId, string $date, ?int $ttl = null): array {
    if (!kit_is_leisure()) return [];
    $resp = kit_api_get('/tenant-client/leisure/slots', ['ticket_type' => $ticketTypeId, 'date' => $date], $ttl ?? 30);
    $list = $resp['data']['slots'] ?? [];
    if (!is_array($list)) return [];
    return array_values(array_map(fn ($s) => [
        'capacity_id' => $s['id'] ?? 0,
        'start'       => $s['time_slot_start'] ?? '',
        'end'         => $s['time_slot_end'] ?? '',
        'status'      => $s['status'] ?? 'available',
        'remaining'   => $s['remaining'] ?? null,
        'price'       => isset($s['price_cents']) ? $s['price_cents'] / 100 : null,
    ], $list));
}

/** Rental catalogue → canonical rental[]. */
function kit_rentals(?int $ttl = null): array {
    if (!kit_is_leisure()) return [];
    $resp = kit_api_get('/tenant-client/leisure/rentals', [], $ttl ?? 120);
    $list = $resp['data']['rentals'] ?? [];
    if (!is_array($list)) return [];
    $cfg = Kit::config();
    $out = [];
    foreach ($list as $r) {
        if (!is_array($r)) continue;
        $options = array_values(array_map(fn ($o) => kit_map_bookable($o, $cfg), $r['options'] ?? []));
        $prices = array_filter(array_column($options, 'price'), fn ($p) => $p !== null);
        $out[] = vm_fill([
            'id'          => $r['id'] ?? 0,
            'slug'        => $r['slug'] ?? '',
            'name'        => $r['name'] ?? '',
            'description' => $r['description'] ?? '',
            'icon'        => $r['icon'] ?? '',
            'image_url'   => kit_asset_url($r['image_url'] ?? null, $cfg),
            'available'   => $r['available'] ?? null,
            'total'       => $r['total'] ?? null,
            'options'     => $options,
            'price_from'  => $prices ? min($prices) : null,
            'currency'    => $options[0]['currency'] ?? ($cfg['currency'] ?? 'RON'),
        ], vm_rental_defaults());
    }
    return $out;
}

/** Raw leisure bookable → canonical. Cents → major units happens HERE, once. */
function kit_map_bookable(array $b, array $cfg): array {
    $ev = $b['event'] ?? [];
    $slug = $ev['slug'] ?? '';
    return vm_fill([
        'ticket_type_id' => $b['ticket_type_id'] ?? 0,
        'name'           => $b['name'] ?? '',
        'description'    => $b['description'] ?? '',
        'category'       => $b['service_category'] ?? 'access',
        'price'          => isset($b['price_cents']) ? $b['price_cents'] / 100 : null,
        'currency'       => $b['currency'] ?? ($cfg['currency'] ?? 'RON'),
        'available'      => $b['available'] ?? null,
        'variants'       => array_values(array_map(fn ($v) => [
            'minutes' => $v['duration_minutes'] ?? null,
            'label'   => $v['label'] ?? '',
            'price'   => isset($v['price_cents']) ? $v['price_cents'] / 100 : null,
        ], $b['duration_variants'] ?? [])),
        'overtime'    => isset($b['overtime']) && is_array($b['overtime']) ? [
            'surcharge'        => ($b['overtime']['surcharge_cents'] ?? 0) / 100,
            'interval_minutes' => $b['overtime']['interval_minutes'] ?? 0,
        ] : null,
        'event_id'    => $ev['id'] ?? 0,
        'event_slug'  => $slug,
        'event_title' => $ev['title'] ?? '',
        'venue_name'  => $ev['venue'] ?? '',
        'city'        => $ev['city'] ?? '',
        'url'         => $slug
            ? vm_url($cfg['event_url_pattern'] ?? '/activitate/{slug}', ['slug' => $slug, 'id' => $ev['id'] ?? 0])
            : '#',
    ], vm_bookable_defaults());
}
