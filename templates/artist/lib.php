<?php
/**
 * Template-ul artist (artist solo): concertele turneului din core
 *   /tenant-client/events          concertele viitoare (cu tickets_left / tickets_low)
 *   /tenant-client/events/past     concertele trecute
 *   /tenant-client/events/{slug}   un concert, cu biletele (has_seating = loc pe hartă)
 * Textele albumului, ale videoclipului și „Despre” vin din secțiunile din panou.
 */

const AR_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const AR_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>';

/** Concertele viitoare, cronologic. */
function ar_upcoming(): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events', ['per_page' => 40, 'limit' => 40]));
        usort($l, fn ($a, $b) => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? '')));
    }
    return $l;
}

/** Concertele trecute, cele mai recente primele. */
function ar_past(int $n = 24): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events/past', ['per_page' => $n]));
        usort($l, fn ($a, $b) => strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? '')));
    }
    return $l;
}

/** Un concert (detaliu cu bilete). */
function ar_event(string $slug): ?array {
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

/** Concertul din afiș: cel ales în setări sau următorul cu bilete în vânzare. */
function ar_next(): ?array {
    $slug = trim((string) site('settings.featured_slug', ''));
    if ($slug === '') {
        foreach (ar_upcoming() as $ev) {
            if (empty($ev['is_sold_out']) && empty($ev['is_cancelled'])) {
                $slug = (string) $ev['slug'];
                break;
            }
        }
        $slug = $slug ?: (string) (ar_upcoming()[0]['slug'] ?? '');
    }
    return $slug !== '' ? ar_event($slug) : null;
}

/** Starea biletelor: [cod, etichetă] — on_sale, low, sold_out, cancelled, past. */
function ar_status(array $ev): array {
    if (!empty($ev['is_cancelled'])) {
        return ['cancelled', t('Anulat')];
    }
    if (ar_is_past($ev)) {
        return ['past', t('A avut loc')];
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

function ar_is_past(array $ev): bool {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    return $d !== '' && strtotime($d . ' 23:59') < time();
}

/** Bucățile datei pentru listă: zi, lună scurtă, an, zi a săptămânii. */
function ar_date_parts(?array $ev): array {
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
function ar_when(?array $ev, bool $withTime = true): string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return '';
    }
    $t = substr((string) ($ev['start_time'] ?? ''), 0, 5);
    return ts_date($d, true) . ($withTime && $t !== '' ? ' · ' . $t : '');
}

/** Orașul concertului, cu numele lui în limba vizitatorului (lang/en/places.php: Viena → Vienna). */
function ar_city(?array $ev): string {
    $c = trim((string) ($ev['venue']['city'] ?? ''));
    return $c !== '' ? t($c) : '';
}

function ar_venue(?array $ev): string {
    return trim((string) ($ev['venue']['name'] ?? ''));
}

function ar_place(?array $ev): string {
    return trim(ar_venue($ev) . (ar_city($ev) !== '' ? ', ' . ar_city($ev) : ''), ', ');
}

function ar_start_iso(?array $ev): ?string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return null;
    }
    return $d . 'T' . (substr((string) ($ev['start_time'] ?? ''), 0, 5) ?: '20:00') . ':00';
}

function ar_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Prețul de la care încep biletele unui concert (din listă sau din detaliu). */
function ar_from_price(array $ev): ?float {
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

/** Numărătoarea până la concert (o actualizează fx.js). */
function ar_countdown(?array $ev, string $class = ''): string {
    $iso = ar_start_iso($ev);
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $out = '<div class="ar-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la concert') . '">';
    foreach (['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')] as $k => $label) {
        $out .= '<div><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Concertul în forma coșului comun. */
function ar_cart_event(array $ev): array {
    return [
        'id' => (int) $ev['id'], 'slug' => (string) $ev['slug'], 'title' => (string) $ev['title'],
        'date' => ar_when($ev), 'place' => ar_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? ''), 'url' => ts_link('event', ['slug' => $ev['slug']]),
    ];
}

/** Rândul unui concert în lista turneului (acasă și /turneu). */
function ar_row(array $ev, bool $past = false): string {
    $dp = ar_date_parts($ev);
    [$code, $label] = ar_status($ev);
    $url = ts_link('event', ['slug' => $ev['slug']]);
    $img = (string) ($ev['poster_url'] ?? $ev['hero_image_url'] ?? '');
    $btn = match ($code) {
        'sold_out' => '<span class="ar-pill ar-pill--out">' . e($label) . '</span>',
        'cancelled' => '<span class="ar-pill ar-pill--out">' . e($label) . '</span>',
        'past' => '<span class="ar-pill">' . te('Detalii') . '</span>',
        'low' => '<span class="ar-pill ar-pill--hot">' . e($label) . '</span><span class="ar-btn ar-btn--sm">' . te('Bilete') . ' ' . AR_ARROW . '</span>',
        default => '<span class="ar-btn ar-btn--sm">' . te('Bilete') . ' ' . AR_ARROW . '</span>',
    };
    return '<li><a class="ar-row ar-row--' . $code . '" href="' . e($url) . '" data-img="' . e($img) . '">'
        . '<time class="ar-row__date" datetime="' . e(substr((string) ($ev['start_date'] ?? ''), 0, 10)) . '"><b>' . e($dp['day']) . '</b><span>' . e($dp['month']) . ' ' . e($dp['year']) . '</span><small>' . e($dp['weekday']) . '</small></time>'
        . '<span class="ar-row__city">' . e(ar_city($ev)) . '</span>'
        . '<span class="ar-row__venue">' . e(ar_venue($ev)) . '</span>'
        . '<span class="ar-row__act">' . $btn . '</span>'
        . '</a></li>';
}
