<?php
/**
 * Marketplace-client adapter.
 *
 * Maps the FLAT / DENORMALIZED event shape returned by /api/marketplace-client/*
 * (where `venue` and `category` are plain strings, prices already in major units)
 * into the SAME canonical view-model the tenant adapter produces.
 *
 * Because the output is identical in shape to tenant_adapt_event(), every
 * component works unchanged across both profiles.
 */

require_once __DIR__ . '/../viewmodel.php';

function marketplace_adapt_event(array $e, array $cfg): array {
    // marketplace `venue`/`category` are strings; `city` is separate
    $venue    = is_array($e['venue'] ?? null) ? ($e['venue']['name'] ?? '') : ($e['venue'] ?? '');
    $city     = $e['city'] ?? (is_array($e['venue'] ?? null) ? ($e['venue']['city'] ?? '') : '');
    $category = is_array($e['category'] ?? null) ? ($e['category']['name'] ?? '') : ($e['category'] ?? '');

    $date = vm_date($e['event_date'] ?? $e['starts_at'] ?? '');
    $time = $e['start_time'] ?? '';
    if (!$time && !empty($e['starts_at']) && preg_match('/T(\d{2}:\d{2})/', $e['starts_at'], $tm)) $time = $tm[1];

    // marketplace-client already applies the discount: `price` is what the buyer
    // pays and `original_price` is the struck-through one. Canonical is the
    // opposite (price = list, sale_price = discounted), so swap them here.
    $tickets = [];
    foreach (($e['ticket_types'] ?? []) as $t) {
        $paid = isset($t['price']) ? (float)$t['price'] : null;
        $list = isset($t['original_price']) && $t['original_price'] !== null ? (float)$t['original_price'] : null;
        $tickets[] = [
            'id'         => $t['id'] ?? null,
            'name'       => $t['name'] ?? '',
            'price'      => $list ?? $paid,
            'sale_price' => $list !== null ? $paid : (isset($t['sale_price']) ? (float)$t['sale_price'] : null),
            'available'  => $t['available_quantity'] ?? null,
            'currency'   => $t['currency'] ?? ($e['currency'] ?? 'RON'),
        ];
    }
    $priceFrom = $e['price_from'] ?? $e['min_price'] ?? null;
    if ($priceFrom === null && $tickets) {
        $prices = array_filter(array_map(fn ($t) => $t['sale_price'] ?? $t['price'], $tickets), fn($p) => $p !== null);
        $priceFrom = $prices ? min($prices) : null;
    }

    $artists = [];
    foreach (($e['artists'] ?? []) as $a) {
        $artists[] = [
            'name'  => $a['name'] ?? '',
            'slug'  => $a['slug'] ?? (string)($a['id'] ?? ''),
            'image' => $a['image_url'] ?? $a['image'] ?? '',
        ];
    }

    $slug = $e['slug'] ?? (string)($e['id'] ?? '');

    return vm_fill([
        'id'            => $e['id'] ?? 0,
        'slug'          => $slug,
        'title'         => $e['name'] ?? $e['title'] ?? 'Eveniment',
        'category'      => is_string($category) ? $category : '',
        'venue_name'    => $venue,
        'city'          => $city,
        'starts_at'     => $e['starts_at'] ?? ($date ?: null),
        'date'          => $date,
        'time'          => $time ? substr($time, 0, 5) : '',
        'end_date'      => vm_date($e['range_end_date'] ?? null),
        'duration_mode' => $e['duration_mode'] ?? 'single',
        // the detail response only carries image_url / cover_image_url
        'poster_url'    => kit_asset_url($e['poster_url'] ?? $e['image_url'] ?? null, $cfg),
        'hero_image_url'=> kit_asset_url($e['hero_image_url'] ?? $e['cover_image_url'] ?? $e['poster_url'] ?? $e['image_url'] ?? null, $cfg),
        'price_from'    => $priceFrom !== null ? (float)$priceFrom : null,
        'currency'      => $e['currency'] ?? ($tickets[0]['currency'] ?? 'RON'),
        'is_sold_out'   => (bool)($e['is_sold_out'] ?? !($e['has_availability'] ?? true)),
        'is_cancelled'  => (bool)($e['is_cancelled'] ?? false),
        'is_postponed'  => (bool)($e['is_postponed'] ?? false),
        'is_promoted'   => (bool)($e['has_paid_promotion'] ?? $e['is_promoted'] ?? false),
        'url'           => vm_url($cfg['event_url_pattern'] ?? '/bilete/{slug}', ['slug' => $slug, 'id' => $e['id'] ?? 0]),
        'short_description' => $e['short_description'] ?? '',
        'description'   => $e['description'] ?? '',
        'ticket_types'  => $tickets,
        'artists'       => $artists,
    ], vm_event_defaults());
}

function marketplace_adapt_artist(array $a, array $cfg): array {
    $slug = $a['slug'] ?? (string)($a['id'] ?? '');
    return vm_fill([
        'id'    => $a['id'] ?? 0,
        'slug'  => $slug,
        'name'  => $a['name'] ?? '',
        'role'  => $a['role'] ?? '',
        'image' => kit_asset_url($a['image_url'] ?? $a['image'] ?? null, $cfg),
        'url'   => vm_url($cfg['artist_url_pattern'] ?? '/artist/{slug}', ['slug' => $slug, 'id' => $a['id'] ?? 0]),
    ], vm_artist_defaults());
}
