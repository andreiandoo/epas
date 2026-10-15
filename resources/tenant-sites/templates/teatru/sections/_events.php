<?php
/**
 * Ajutoare comune secțiunilor template-ului teatru (spectacole, butoane).
 * Inclus cu require_once din fiecare secțiune care are nevoie.
 */

const TEATRU_FALLBACK_IMG = 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1600&q=80';

function teatru_ev_img(?array $e, ?string $fallback = TEATRU_FALLBACK_IMG): ?string {
    if (!$e) {
        return $fallback;
    }
    return asset_url($e['hero_image_url'] ?? $e['poster_url'] ?? null, $fallback);
}

function teatru_ev_date(array $e): string {
    if (empty($e['start_date'])) {
        return '';
    }
    $ts = strtotime($e['start_date']);
    if (!$ts) {
        return '';
    }
    $zi = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'][(int) date('w', $ts)];
    return $zi . ', ' . date('d.m', $ts) . (!empty($e['start_time']) ? ' • ' . substr($e['start_time'], 0, 5) : '');
}

/** Spectacolul recomandat: după slug, altfel cel marcat în core, altfel primul din program. */
function teatru_featured(string $slug = ''): ?array {
    static $cache = [];
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }
    $ev = null;
    if ($slug !== '') {
        $r = api_get('/tenant-client/events/' . rawurlencode($slug));
        $ev = ($r['success'] ?? false) && is_array($r['data'] ?? null) ? tc_norm_event($r['data']['event'] ?? $r['data']) : null;
    }
    if (!$ev) {
        $ev = tc_events(api_get('/tenant-client/events/featured', ['limit' => 1]))[0] ?? null;
    }
    if (!$ev) {
        $ev = tc_events(api_get('/tenant-client/events', ['per_page' => 9]))[0] ?? null;
    }
    return $cache[$slug] = $ev;
}

function teatru_button(?string $label, ?string $url, string $class): string {
    if (trim((string) $label) === '' || trim((string) $url) === '') {
        return '';
    }
    return '<a href="' . e(ts_href($url)) . '" class="' . $class . '">' . e($label) . '</a>';
}

function teatru_eyebrow(?string $text, string $extra = 'mb-3'): string {
    return trim((string) $text) === '' ? '' : '<p class="text-primary tracking-[0.2em] text-sm uppercase ' . $extra . '">' . e($text) . '</p>';
}
