<?php
/**
 * Template-ul party (petrecere recurentă: ediții tematice în mai multe orașe), din core:
 *   /tenant-client/events          edițiile viitoare (cu tickets_left / tickets_low)
 *   /tenant-client/events/past     edițiile trecute
 *   /tenant-client/events/{slug}   o ediție: valurile de preț (ticket_group „Valuri”), pachetele,
 *                                  mesele pe hartă (has_seating), DJ-ii (roster), galeria foto
 *   /tenant-client/roster          DJ-ii rezidenți (TenantArtist cu rol „dj”)
 * Titlul unei ediții: „Brand: Tema · Oraș”.
 */

const PT_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const PT_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>';

/** Edițiile viitoare, cronologic. */
function pt_upcoming(): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events', ['per_page' => 40, 'limit' => 40]));
        usort($l, fn ($a, $b) => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? '')));
    }
    return $l;
}

/** Edițiile trecute, cele mai recente primele. */
function pt_past(int $n = 24): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events/past', ['per_page' => $n]));
        usort($l, fn ($a, $b) => strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? '')));
    }
    return $l;
}

/** O ediție (detaliu cu bilete). */
function pt_event(string $slug): ?array {
    static $c = [];
    if ($slug === '') {
        return null;
    }
    if (!array_key_exists($slug, $c)) {
        $r = api_get('/tenant-client/events/' . rawurlencode($slug), [], 60);
        $c[$slug] = (is_array($r['data'] ?? null) && !empty($r['data']['id'])) ? tc_norm_event($r['data']) : null;
    }
    return $c[$slug];
}

/** Ediția din afiș: cea aleasă în setări sau următorul cu bilete în vânzare aici (nu la alt organizator). */
function pt_next(): ?array {
    $slug = trim((string) site('settings.featured_slug', ''));
    if ($slug === '') {
        foreach (pt_upcoming() as $ev) {
            if (empty($ev['is_sold_out']) && empty($ev['is_cancelled']) && !pt_external($ev)) {
                $slug = (string) $ev['slug'];
                break;
            }
        }
        $slug = $slug ?: (string) (pt_upcoming()[0]['slug'] ?? '');
    }
    return $slug !== '' ? pt_event($slug) : null;
}

/** Linkul de bilete al unei ediții vândute de alt organizator (ex. un festival), dacă are. */
function pt_external(array $ev): ?string {
    $u = trim((string) ($ev['external_ticket_url'] ?? ''));
    return preg_match('#^https?://#i', $u) ? $u : null;
}

/** Starea biletelor: [cod, etichetă] — on_sale, low, sold_out, external, cancelled, past. */
function pt_status(array $ev): array {
    if (!empty($ev['is_cancelled'])) {
        return ['cancelled', t('Anulat')];
    }
    if (pt_is_past($ev)) {
        return ['past', t('A avut loc')];
    }
    if (pt_external($ev)) {
        return ['external', trim((string) ($ev['external_ticket_label'] ?? '')) ?: t('Bilete la organizator')];
    }
    $left = $ev['tickets_left'] ?? null;
    if (!empty($ev['is_sold_out']) || ($left !== null && (int) $left <= 0)) {
        return ['sold_out', t('Sold out')];
    }
    if (!empty($ev['tickets_low'])) {
        return ['low', t('Ultimele bilete')];
    }
    return ['on_sale', t('Bilete')];
}

function pt_is_past(array $ev): bool {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    return $d !== '' && strtotime($d . ' 23:59') < time();
}

/** Bucățile datei pentru listă: zi, lună scurtă, an, zi a săptămânii. */
function pt_date_parts(?array $ev): array {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    $ts = $d !== '' ? strtotime($d) : false;
    if (!$ts) {
        return ['day' => '', 'month' => '', 'year' => '', 'weekday' => ''];
    }
    $en = ts_locale() === 'en';
    $mon = $en ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        : ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $wd = $en ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] : ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
    return ['day' => date('d', $ts), 'month' => $mon[(int) date('n', $ts) - 1], 'year' => date('Y', $ts), 'weekday' => $wd[(int) date('w', $ts)]];
}

/** Data și ora în limba curentă: „joi, 19 noiembrie 2026 · 20:00”. */
function pt_when(?array $ev, bool $withTime = true): string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return '';
    }
    $t = substr((string) ($ev['start_time'] ?? ''), 0, 5);
    return ts_date($d, true) . ($withTime && $t !== '' ? ' · ' . $t : '');
}

/** Orașul concertului, cu numele lui în limba vizitatorului (lang/en/places.php: Viena → Vienna). */
function pt_city(?array $ev): string {
    $c = trim((string) ($ev['venue']['city'] ?? ''));
    return $c !== '' ? t($c) : '';
}

function pt_venue(?array $ev): string {
    return trim((string) ($ev['venue']['name'] ?? ''));
}

function pt_place(?array $ev): string {
    return trim(pt_venue($ev) . (pt_city($ev) !== '' ? ', ' . pt_city($ev) : ''), ', ');
}

function pt_start_iso(?array $ev): ?string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return null;
    }
    return $d . 'T' . (substr((string) ($ev['start_time'] ?? ''), 0, 5) ?: '20:00') . ':00';
}

function pt_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Prețul de la care încep biletele unei ediții (din listă sau din detaliu). */
function pt_from_price(array $ev): ?float {
    if (isset($ev['price_from']) && $ev['price_from'] !== null) {
        return (float) $ev['price_from'];
    }
    $p = [];
    foreach ((array) ($ev['ticket_types'] ?? []) as $t) {
        $p[] = (float) ($t['sale_price'] ?? 0) ?: (float) ($t['price'] ?? 0);
    }
    $p = array_filter($p);
    return $p ? min($p) : null;
}

/** Numărătoarea până la petrecere (o actualizează fx.js). */
function pt_countdown(?array $ev, string $class = ''): string {
    $iso = pt_start_iso($ev);
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $out = '<div class="pt-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la petrecere') . '">';
    foreach (['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')] as $k => $label) {
        $out .= '<div><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Numele ediției fără oraș: „Casetofonul: Eurodance · București” → „Casetofonul: Eurodance”. */
function pt_show_name(array $ev): string {
    $t = (string) ($ev['title'] ?? '');
    $p = mb_strrpos($t, ' · ');
    return $p !== false ? mb_substr($t, 0, $p) : $t;
}

/** Ediția în forma coșului comun. */
function pt_cart_event(array $ev): array {
    return [
        'id' => (int) $ev['id'], 'slug' => (string) $ev['slug'], 'title' => (string) $ev['title'],
        'date' => pt_when($ev), 'place' => pt_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? ''), 'url' => ts_link('event', ['slug' => $ev['slug']]),
    ];
}

/** Rândul unei ediții în arhivă (/petreceri). */
function pt_row(array $ev, bool $past = false): string {
    $dp = pt_date_parts($ev);
    [$code, $label] = pt_status($ev);
    $url = ts_link('event', ['slug' => $ev['slug']]);
    $img = (string) ($ev['poster_url'] ?? $ev['hero_image_url'] ?? '');
    $btn = match ($code) {
        'sold_out' => '<span class="pt-pill pt-pill--out">' . e($label) . '</span>',
        'cancelled' => '<span class="pt-pill pt-pill--out">' . e($label) . '</span>',
        'past' => '<span class="pt-pill">' . te('Detalii') . '</span>',
        'external' => '<span class="pt-pill">' . te('Bilete la organizator') . '</span><span class="pt-btn pt-btn--sm pt-btn--ghost">' . te('Detalii') . ' ' . PT_ARROW . '</span>',
        'low' => '<span class="pt-pill pt-pill--hot">' . e($label) . '</span><span class="pt-btn pt-btn--sm">' . te('Bilete') . ' ' . PT_ARROW . '</span>',
        default => '<span class="pt-btn pt-btn--sm">' . te('Bilete') . ' ' . PT_ARROW . '</span>',
    };
    return '<li><a class="pt-row pt-row--' . $code . '" href="' . e($url) . '" data-img="' . e($img) . '">'
        . '<time class="pt-row__date" datetime="' . e(substr((string) ($ev['start_date'] ?? ''), 0, 10)) . '"><b>' . e($dp['day']) . '</b><span>' . e($dp['month']) . ' ' . e($dp['year']) . '</span><small>' . e($dp['weekday']) . '</small></time>'
        . '<span class="pt-row__city">' . e(pt_show_name($ev)) . '</span>'
        . '<span class="pt-row__venue">' . e(trim(pt_city($ev) . ' · ' . pt_venue($ev), ' ·')) . '</span>'
        . '<span class="pt-row__act">' . $btn . '</span>'
        . '</a></li>';
}

/* ---------------------------------------------------------------- petreceri */

/** Tema ediției: „Casetofonul: Eurodance · București” → „Eurodance”. */
function pt_theme(array $ev): string {
    $t = pt_show_name($ev);
    $p = mb_strpos($t, ': ');
    return $p !== false ? mb_substr($t, $p + 2) : $t;
}

/** DJ-ii rezidenți (din roster). */
function pt_djs(): array {
    static $l = null;
    if ($l === null) {
        $r = api_get('/tenant-client/roster', [], 300);
        $l = array_values(array_filter((array) ($r['data']['artists'] ?? []), fn ($a) => ($a['kind'] ?? '') === 'dj' || !empty($a['featured'])));
    }
    return $l;
}

/**
 * Edițiile trecute cu galerie (detaliul fiecăreia, cu fotografiile), cele mai noi primele.
 * Detaliile vin din cache-ul API-ului, deci nu costă la fiecare afișare.
 */
function pt_albums(int $n = 6): array {
    static $c = [];
    if (!isset($c[$n])) {
        $out = [];
        foreach (array_slice(pt_past(), 0, $n + 4) as $ev) {
            $d = pt_event((string) $ev['slug']);
            if ($d && !empty($d['gallery'])) {
                $out[] = $d;
            }
            if (count($out) >= $n) {
                break;
            }
        }
        $c[$n] = $out;
    }
    return $c[$n];
}

/**
 * Valurile de preț ale unei ediții, în ordine: [nume, preț, stare, data de deschidere, id].
 * stare: out (epuizat), on (în vânzare acum), next (se deschide după cel curent), soon (are o dată).
 */
function pt_waves(array $ev): array {
    $waves = [];
    $current = false;
    foreach ((array) ($ev['ticket_types'] ?? []) as $t) {
        if (($t['group'] ?? '') === '' || !empty($t['has_seating'])) {
            continue;
        }
        if (!in_array(mb_strtolower((string) $t['group']), ['valuri', 'waves'], true)) {
            continue;
        }
        $price = (float) ($t['sale_price'] ?? 0) ?: (float) ($t['price'] ?? 0);
        $opens = !empty($t['sales_start_at']) && strtotime((string) $t['sales_start_at']) > time() ? (string) $t['sales_start_at'] : null;
        $out = isset($t['available']) && (int) $t['available'] <= 0;
        $state = $out ? 'out' : ($opens ? 'soon' : ($current ? 'next' : 'on'));
        if ($state === 'on') { $current = true; }
        $waves[] = ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'price' => $price, 'state' => $state, 'opens' => $opens, 'left' => $t['available'] ?? null];
    }
    return $waves;
}

/** Ziua și ora în care se deschide un val: „vin., 30 oct., 12:00”. */
function pt_opens(?string $iso): string {
    $ts = $iso ? strtotime($iso) : false;
    if (!$ts) {
        return '';
    }
    return ts_date(date('Y-m-d', $ts), true, false) . ', ' . date('H:i', $ts);
}


/** Afișul (flyer) unei ediții: tema mare peste fotografie, data, orașul, valul în curs. */
function pt_flyer(array $ev, int $i = 0): string {
    $dp = pt_date_parts($ev);
    [$code, $label] = pt_status($ev);
    $img = (string) ($ev['poster_url'] ?? $ev['hero_image_url'] ?? '');
    $from = pt_from_price($ev);
    // Valul în vânzare (lista din core are și tipurile de bilet): „Valul 2 · 79 lei”, nu prețul early bird-ului epuizat
    $wave = null;
    foreach (pt_waves($ev) as $w) { if ($w['state'] === 'on') { $wave = $w; break; } }
    $price = $wave ? e($wave['name']) . ' · ' . e(pt_money($wave['price'])) : ($from ? te('de la') . ' ' . e(pt_money($from)) : '');
    $badge = match ($code) {
        'sold_out' => '<span class="pt-pill pt-pill--out">' . e($label) . '</span>',
        'low' => ($price ? '<span class="pt-pill">' . $price . '</span>' : '') . '<span class="pt-pill pt-pill--hot">' . e($label) . '</span>',
        'external' => '<span class="pt-pill">' . te('Bilete la organizator') . '</span>',
        default => $price ? '<span class="pt-pill">' . $price . '</span>' : '',
    };
    return '<a class="pt-flyer pt-flyer--' . ($i % 4) . ($code === 'sold_out' ? ' is-out' : '') . '" href="' . e(ts_link('event', ['slug' => $ev['slug']])) . '" data-pt-tilt>'
        . '<span class="pt-flyer__img">' . ($img ? '<img src="' . e($img) . '" alt="" loading="lazy">' : '') . '</span>'
        . '<span class="pt-flyer__date"><b>' . e($dp['day']) . '</b>' . e($dp['month']) . '</span>'
        . '<span class="pt-flyer__body"><small>' . e(pt_city($ev)) . ' · ' . e(pt_venue($ev)) . '</small>'
        . '<b class="pt-flyer__theme">' . e(pt_theme($ev)) . '</b><span class="pt-flyer__tags">' . $badge . '</span></span></a>';
}
