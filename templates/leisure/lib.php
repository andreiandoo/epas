<?php
/**
 * Ajutoarele template-ului leisure (Nordvale): produsele rezervabile și
 * disponibilitatea reală din core (API tenant-client /leisure/*), plus paleta.
 *
 * Modelul din core: o „experiență” e un TicketType (service_category access |
 * rental | activity) al evenimentului-umbrelă al parcului; locurile sunt pe zi
 * sau pe interval în ticket_type_capacities. Vezi plans/mds/PLAN_TENANT_SITE_TEMPLATES.md.
 */

/** Un eveniment după slug (detaliu complet). */
function tc_event(string $slug, ?int $cacheTtl = null): ?array {
    $resp = api_get('/tenant-client/events/' . rawurlencode($slug), [], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) { return null; }
    return tc_norm_event($resp['data']['event'] ?? $resp['data']);
}

/**
 * Produsele rezervabile ale parcului: [{ticket_type_id, name, description, service_category,
 * price_cents, currency, available, duration_variants[], event:{id,slug,title,venue,city}}].
 */
function lv_bookables(?string $category = null): array {
    static $cache = [];
    $key = $category ?? '*';
    if (!isset($cache[$key])) {
        $r = api_get('/tenant-client/leisure/bookables', array_filter(['category' => $category]), 120);
        $cache[$key] = ($r['success'] ?? false) ? (array) ($r['data']['bookables'] ?? []) : [];
    }
    return $cache[$key];
}

/**
 * Ce se vinde pe site: access + activity + rental cu preț și cu locuri programate
 * (`bookable_online`; articolele de bar vândute doar la POS nu au). Fără câmp
 * (core mai vechi), le lăsăm pe toate.
 */
function lv_tickets(?string $category = null): array {
    return array_values(array_filter(lv_bookables(), fn ($b) => in_array($b['service_category'] ?? '', ['access', 'activity', 'rental'], true)
        && ($b['price_cents'] ?? 0) > 0
        && ($b['bookable_online'] ?? true)
        && ($category === null || ($b['service_category'] ?? '') === $category)));
}

function lv_price(int $cents): string {
    $n = $cents / 100;
    return (floor($n) == $n ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.')) . ' lei';
}

function lv_category_label(?string $c): string {
    return ['access' => 'Acces', 'activity' => 'Activitate', 'rental' => 'Închiriere'][$c ?? ''] ?? 'Experiență';
}

/** Slug pentru /experienta/{slug}: id + nume (stabil și lizibil). */
function lv_slug(array $b): string {
    $name = strtolower((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) ($b['name'] ?? '')) ?: ''));
    return (int) ($b['ticket_type_id'] ?? 0) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-');
}

function lv_find(string $slug): ?array {
    $id = (int) strtok($slug, '-');
    foreach (lv_tickets() as $b) {
        if ((int) ($b['ticket_type_id'] ?? 0) === $id) { return $b; }
    }
    return null;
}

/**
 * Fotografiile unui produs. Tipurile de bilet nu au imagine în core, așa că
 * template-ul folosește un set pe categorie (Unsplash, verificate).
 */
const LV_PHOTOS = [
    'access' => [
        'photo-1441974231531-c6227db76b6e',
        'photo-1500530855697-b586d89ba3ee',
        'photo-1469474968028-56623f02e42e',
        'photo-1470770841072-f978cf4d019e',
    ],
    'activity' => [
        'photo-1516939884455-1445c8652f83',
        'photo-1521336575822-6da63fb45455',
        'photo-1486911278844-a81c5267e227',
        'photo-1464278533981-50106e6176b1',
    ],
    'rental' => [
        'photo-1551632811-561732d1e306',
        'photo-1558618666-fcd25c85cd64',
        'photo-1500530855697-b586d89ba3ee',
        'photo-1469474968028-56623f02e42e',
    ],
];

function lv_photo(string $id, int $w = 1600): string {
    return 'https://images.unsplash.com/' . $id . '?auto=format&fit=crop&w=' . $w . '&q=86';
}

/** Imaginea principală a unui produs (variază după id, ca produsele din aceeași categorie să difere). */
function lv_image(array $b, int $w = 1600): string {
    $set = LV_PHOTOS[$b['service_category'] ?? 'access'] ?? LV_PHOTOS['access'];
    return lv_photo($set[((int) ($b['ticket_type_id'] ?? 0)) % count($set)], $w);
}

/** Galeria unui produs: imaginea principală întâi, apoi restul setului categoriei. */
function lv_gallery(array $b, int $w = 1600): array {
    $set = LV_PHOTOS[$b['service_category'] ?? 'access'] ?? LV_PHOTOS['access'];
    $first = ((int) ($b['ticket_type_id'] ?? 0)) % count($set);
    $out = [];
    for ($i = 0; $i < count($set); $i++) {
        $out[] = lv_photo($set[($first + $i) % count($set)], $w);
    }
    return $out;
}

/** Durata afișată: variantele de durată, altfel „toată ziua” / „pe interval”. */
function lv_duration(array $b): string {
    $labels = [];
    foreach ((array) ($b['duration_variants'] ?? []) as $v) {
        if (!empty($v['duration_minutes'])) {
            $labels[] = $v['label'] ?: ((int) $v['duration_minutes'] . ' min');
        }
    }
    if ($labels) {
        return count($labels) > 2 ? $labels[0] . ' – ' . end($labels) : implode(' / ', $labels);
    }
    return ($b['service_category'] ?? '') === 'activity' ? 'Pe interval orar' : 'Toată ziua';
}

/**
 * Nuanțele „pine” ale template-ului, calculate din culoarea secundară a temei,
 * ca un tenant care își schimbă culoarea să primească toată gama.
 */
function lv_palette_css(): string {
    $c = ts_colors();
    $accent = $c['accent'];
    $black = [0, 0, 0];
    $white = [255, 255, 255];
    $shades = [
        'pine-950' => ts_mix($accent, $black, 0.32),
        'pine-900' => $accent,
        'pine-850' => ts_mix($accent, $white, 0.04),
        'pine-800' => ts_mix($accent, $white, 0.08),
        'pine-700' => ts_mix($accent, $white, 0.17),
        'pine-600' => ts_mix($accent, $white, 0.3),
    ];
    $css = ':root{';
    foreach ($shades as $k => $rgb) {
        $css .= '--c-' . $k . ':' . implode(' ', $rgb) . ';';
    }
    return $css . '}';
}
