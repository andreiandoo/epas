<?php
/**
 * Template-ul festival: evenimentul festivalului, lineup-ul (zile, scene, apariții)
 * și biletele grupate, din core:
 *   /tenant-client/events/{slug}         detaliu + ticket_types (group, perks, valid_date)
 *   /tenant-client/events/{slug}/lineup  zile, scene, sloturi (modulul festival)
 * Festivalul afișat: setarea „event_slug” din panou, altfel primul eveniment viitor.
 */

/** Evenimentul festivalului (detaliu complet). */
function fest_event(): ?array {
    static $ev = false;
    if ($ev !== false) {
        return $ev;
    }
    $slug = trim((string) site('settings.event_slug', ''));
    if ($slug === '') {
        $list = tc_events(api_get('/tenant-client/events', ['per_page' => 5, 'limit' => 5]));
        $slug = (string) ($list[0]['slug'] ?? '');
    }
    $ev = null;
    if ($slug !== '') {
        $r = api_get('/tenant-client/events/' . rawurlencode($slug), [], 120);
        if (is_array($r['data'] ?? null) && !empty($r['data']['id'])) {
            $ev = tc_norm_event($r['data']);
        }
    }
    return $ev;
}

/** Lineup-ul: ['days' => [], 'stages' => [], 'slots' => []] (gol dacă festivalul nu are program). */
function fest_lineup(): array {
    static $l = null;
    if ($l !== null) {
        return $l;
    }
    $l = ['days' => [], 'stages' => [], 'slots' => []];
    $ev = fest_event();
    if ($ev) {
        $r = api_get('/tenant-client/events/' . rawurlencode($ev['slug']) . '/lineup', [], 120);
        if (($r['success'] ?? false) && is_array($r['data'] ?? null)) {
            $l = array_merge($l, $r['data']);
        }
    }
    return $l;
}

/** Biletele grupate după `group`, în ordinea din core: [['name' => …, 'items' => [...]], …]. */
function fest_ticket_groups(): array {
    $ev = fest_event();
    $groups = [];
    foreach ((array) ($ev['ticket_types'] ?? []) as $t) {
        $g = trim((string) ($t['group'] ?? '')) ?: t('Bilete');
        $groups[$g][] = $t;
    }
    $out = [];
    foreach ($groups as $name => $items) {
        $out[] = ['name' => $name, 'items' => $items];
    }
    return $out;
}

/** Biletele în forma folosită de componenta de cumpărare (preț efectiv, epuizat, faze). */
function fest_ticket_js(array $t): array {
    $price = (float) ($t['sale_price'] ?? 0) ?: (float) ($t['price'] ?? 0);
    $avail = $t['available'] ?? null;
    return [
        'id' => (int) $t['id'],
        'name' => (string) $t['name'],
        'description' => (string) ($t['description'] ?? ''),
        'price' => $price,
        'was' => !empty($t['sale_price']) && (float) $t['sale_price'] < (float) $t['price'] ? (float) $t['price'] : null,
        'perks' => array_values((array) ($t['perks'] ?? [])),
        'soldOut' => $avail !== null && (int) $avail <= 0,
        'left' => $avail !== null && (int) $avail > 0 && (int) $avail < 50 ? (int) $avail : null,
        'date' => $t['valid_date'] ?? null,
        'dateLabel' => !empty($t['valid_date']) ? ts_date($t['valid_date'], true, false) : null,
        'parking' => !empty($t['is_parking']),
        // Bilet cu loc numerotat: se alege locul pe harta sălii (seatmap.js), nu cantitatea
        'seated' => !empty($t['has_seating']),
    ];
}

/** Datele festivalului: [start, end] ca ISO (Y-m-d). */
function fest_dates(?array $ev): array {
    if (!$ev) {
        return [null, null];
    }
    $start = $ev['start_date'] ?? $ev['range_start_date'] ?? $ev['event_date'] ?? null;
    $end = $ev['end_date'] ?? $ev['range_end_date'] ?? $start;
    return [$start ? substr((string) $start, 0, 10) : null, $end ? substr((string) $end, 0, 10) : null];
}

/** „14–16 august 2027” / „14 Aug – 2 Sep 2027” în limba curentă. */
function fest_date_range(?array $ev): string {
    [$a, $b] = fest_dates($ev);
    if (!$a) {
        return '';
    }
    $ta = strtotime($a);
    $tb = strtotime($b ?: $a);
    if (date('Y-m', $ta) === date('Y-m', $tb)) {
        $full = ts_date($b ?: $a);
        return (date('j', $ta) !== date('j', $tb) ? date('j', $ta) . '–' : '') . $full;
    }
    return ts_date($a, false, false) . ' – ' . ts_date($b);
}

/** Ziua (ISO) la care începe festivalul, cu ora porților din prima zi a lineup-ului. */
function fest_start_iso(): ?string {
    [$a] = fest_dates(fest_event());
    if (!$a) {
        return null;
    }
    $gates = fest_lineup()['days'][0]['gates_open'] ?? '16:00';
    return $a . 'T' . $gates . ':00';
}

function fest_place(?array $ev): string {
    $v = $ev['venue'] ?? null;
    if (!is_array($v)) {
        return '';
    }
    return trim(($v['name'] ?? '') . (!empty($v['city']) ? ', ' . $v['city'] : ''));
}

/** Prețul în formatul limbii curente (aceeași regulă ca TS.lei). */
function fest_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Evenimentul în forma coșului comun (TS.Cart). */
function fest_cart_event(?array $ev): array {
    return [
        'id' => (int) ($ev['id'] ?? 0),
        'slug' => (string) ($ev['slug'] ?? ''),
        'title' => (string) ($ev['title'] ?? SITE_NAME),
        'date' => fest_date_range($ev),
        'place' => fest_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? $ev['image'] ?? ''),
        'url' => '/bilete',
    ];
}

/** Numărătoarea până la deschiderea porților (o actualizează fx.js). */
function fest_countdown(string $class = ''): string {
    $iso = fest_start_iso();
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $cells = ['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')];
    $out = '<div class="fx-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la deschiderea porților') . '">';
    foreach ($cells as $k => $label) {
        $out .= '<div class="fx-cd__cell"><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

const FEST_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
