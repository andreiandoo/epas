<?php
/**
 * Tenant-client adapter.
 *
 * Maps the RICH / NESTED event shape returned by /api/tenant-client/* into the
 * canonical view-model (see viewmodel.php). This is the formalized, extended
 * successor of teatru's includes/api.php::tc_norm_event().
 *
 * Contract: adapt_event(array $raw, array $cfg): array  → canonical event
 *   $cfg is the site config (Kit::config()); we read the event_url_pattern
 *   from it so links point at THIS site's routes.
 */

require_once __DIR__ . '/../viewmodel.php';

function tenant_adapt_event(array $e, array $cfg): array {
    // category: back-fill from event_types[0] (tc_norm_event behaviour)
    $category = $e['category']['name'] ?? $e['category'] ?? ($e['event_types'][0]['name'] ?? $e['event_types'][0] ?? '');
    $catSlug  = $e['category']['slug'] ?? '';

    // schedule may be nested under `schedule` or flat
    $sched     = $e['schedule'] ?? $e;
    $startDate = $sched['start_date'] ?? $e['start_date'] ?? null;
    $startTime = $sched['start_time'] ?? $e['start_time'] ?? '';
    $date      = vm_date($startDate);

    // price: explicit price_from, else min of ticket_types
    $tickets = [];
    foreach (($e['ticket_types'] ?? []) as $t) {
        $tickets[] = [
            'name'       => $t['name'] ?? '',
            'price'      => isset($t['price']) ? (float)$t['price'] : null,
            'sale_price' => isset($t['sale_price']) ? (float)$t['sale_price'] : null,
            'currency'   => $t['currency'] ?? ($e['currency'] ?? 'RON'),
        ];
    }
    $priceFrom = $e['price_from'] ?? null;
    if ($priceFrom === null && $tickets) {
        $prices = array_filter(array_column($tickets, 'price'), fn($p) => $p !== null);
        $priceFrom = $prices ? min($prices) : null;
    }

    $artists = [];
    foreach (($e['artists'] ?? []) as $a) {
        $artists[] = [
            'name'  => $a['name'] ?? '',
            'slug'  => $a['slug'] ?? (string)($a['id'] ?? ''),
            'image' => $a['image'] ?? $a['image_url'] ?? '',
        ];
    }

    $slug = $e['slug'] ?? (string)($e['id'] ?? '');

    return vm_fill([
        'id'            => $e['id'] ?? 0,
        'slug'          => $slug,
        'title'         => $e['title'] ?? $e['name'] ?? 'Spectacol',
        'category'      => is_string($category) ? $category : '',
        'category_slug' => $catSlug,
        'venue_name'    => $e['venue']['name'] ?? $e['venue_name'] ?? '',
        'city'          => $e['venue']['city'] ?? $e['city'] ?? '',
        'starts_at'     => $startDate ?: null,
        'date'          => $date,
        'time'          => $startTime ? substr($startTime, 0, 5) : '',
        'end_date'      => vm_date($sched['end_date'] ?? null),
        'duration_mode' => $sched['duration_mode'] ?? 'single',
        'poster_url'    => kit_asset_url($e['poster_url'] ?? null, $cfg),
        'hero_image_url'=> kit_asset_url($e['hero_image_url'] ?? $e['poster_url'] ?? null, $cfg),
        'price_from'    => $priceFrom !== null ? (float)$priceFrom : null,
        'currency'      => $e['currency'] ?? ($tickets[0]['currency'] ?? 'RON'),
        'is_sold_out'   => (bool)($e['is_sold_out'] ?? false),
        'is_cancelled'  => (bool)($e['is_cancelled'] ?? false),
        'is_postponed'  => (bool)($e['is_postponed'] ?? false),
        'is_promoted'   => (bool)($e['is_promoted'] ?? false),
        'url'           => vm_url($cfg['event_url_pattern'] ?? '/spectacol/{slug}', ['slug' => $slug, 'id' => $e['id'] ?? 0]),
        'short_description' => $e['short_description'] ?? '',
        'description'   => $e['description'] ?? '',
        'ticket_types'  => $tickets,
        'artists'       => $artists,
        'tags'          => $e['tags'] ?? [],
    ], vm_event_defaults());
}

function tenant_adapt_artist(array $a, array $cfg): array {
    $slug = $a['slug'] ?? (string)($a['id'] ?? '');
    return vm_fill([
        'id'    => $a['id'] ?? 0,
        'slug'  => $slug,
        'name'  => $a['name'] ?? '',
        'role'  => $a['role'] ?? $a['category'] ?? '',
        'type'  => $a['type'] ?? '',
        'image' => kit_asset_url($a['image'] ?? $a['image_url'] ?? $a['photo_url'] ?? null, $cfg),
        'bio'   => $a['bio'] ?? '',
        'url'   => vm_url($cfg['artist_url_pattern'] ?? '/artist/{slug}', ['slug' => $slug, 'id' => $a['id'] ?? 0]),
    ], vm_artist_defaults());
}
