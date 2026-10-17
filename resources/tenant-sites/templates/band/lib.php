<?php
/**
 * Template-ul band (trupă): concertele turneului din core
 *   /tenant-client/events          concertele viitoare (cu tickets_left / tickets_low)
 *   /tenant-client/events/past     concertele trecute
 *   /tenant-client/events/{slug}   un concert, cu biletele (has_seating = loc pe hartă)
 * Membrii, discografia și videoclipul vin din secțiunile din panou; venue-urile au coordonate (globul).
 */

const BD_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const BD_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>';

/** Concertele viitoare, cronologic. */
function bd_upcoming(): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events', ['per_page' => 40, 'limit' => 40]));
        usort($l, fn ($a, $b) => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? '')));
    }
    return $l;
}

/** Concertele trecute, cele mai recente primele. */
function bd_past(int $n = 24): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events/past', ['per_page' => $n]));
        usort($l, fn ($a, $b) => strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? '')));
    }
    return $l;
}

/** Un concert (detaliu cu bilete). */
function bd_event(string $slug): ?array {
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

/** Concertul din afiș: cel ales în setări sau următorul cu bilete în vânzare aici (nu la alt organizator). */
function bd_next(): ?array {
    $slug = trim((string) site('settings.featured_slug', ''));
    if ($slug === '') {
        foreach (bd_upcoming() as $ev) {
            if (empty($ev['is_sold_out']) && empty($ev['is_cancelled']) && !bd_external($ev)) {
                $slug = (string) $ev['slug'];
                break;
            }
        }
        $slug = $slug ?: (string) (bd_upcoming()[0]['slug'] ?? '');
    }
    return $slug !== '' ? bd_event($slug) : null;
}

/** Linkul de bilete al unui concert vândut de alt organizator (ex. un festival), dacă are. */
function bd_external(array $ev): ?string {
    $u = trim((string) ($ev['external_ticket_url'] ?? ''));
    return preg_match('#^https?://#i', $u) ? $u : null;
}

/** Starea biletelor: [cod, etichetă] — on_sale, low, sold_out, external, cancelled, past. */
function bd_status(array $ev): array {
    if (!empty($ev['is_cancelled'])) {
        return ['cancelled', t('Anulat')];
    }
    if (bd_is_past($ev)) {
        return ['past', t('A avut loc')];
    }
    if (bd_external($ev)) {
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

function bd_is_past(array $ev): bool {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    return $d !== '' && strtotime($d . ' 23:59') < time();
}

/** Bucățile datei pentru listă: zi, lună scurtă, an, zi a săptămânii. */
function bd_date_parts(?array $ev): array {
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
function bd_when(?array $ev, bool $withTime = true): string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return '';
    }
    $t = substr((string) ($ev['start_time'] ?? ''), 0, 5);
    return ts_date($d, true) . ($withTime && $t !== '' ? ' · ' . $t : '');
}

/** Orașul concertului, cu numele lui în limba vizitatorului (lang/en/places.php: Viena → Vienna). */
function bd_city(?array $ev): string {
    $c = trim((string) ($ev['venue']['city'] ?? ''));
    return $c !== '' ? t($c) : '';
}

function bd_venue(?array $ev): string {
    return trim((string) ($ev['venue']['name'] ?? ''));
}

function bd_place(?array $ev): string {
    return trim(bd_venue($ev) . (bd_city($ev) !== '' ? ', ' . bd_city($ev) : ''), ', ');
}

function bd_start_iso(?array $ev): ?string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return null;
    }
    return $d . 'T' . (substr((string) ($ev['start_time'] ?? ''), 0, 5) ?: '20:00') . ':00';
}

function bd_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Prețul de la care încep biletele unui concert (din listă sau din detaliu). */
function bd_from_price(array $ev): ?float {
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
function bd_countdown(?array $ev, string $class = ''): string {
    $iso = bd_start_iso($ev);
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $out = '<div class="bd-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la concert') . '">';
    foreach (['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')] as $k => $label) {
        $out .= '<div><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Concertul în forma coșului comun. */
function bd_cart_event(array $ev): array {
    return [
        'id' => (int) $ev['id'], 'slug' => (string) $ev['slug'], 'title' => (string) $ev['title'],
        'date' => bd_when($ev), 'place' => bd_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? ''), 'url' => ts_link('event', ['slug' => $ev['slug']]),
    ];
}

/** Rândul unui concert în lista turneului (acasă și /turneu). */
function bd_row(array $ev, bool $past = false): string {
    $dp = bd_date_parts($ev);
    [$code, $label] = bd_status($ev);
    $url = ts_link('event', ['slug' => $ev['slug']]);
    $img = (string) ($ev['poster_url'] ?? $ev['hero_image_url'] ?? '');
    $btn = match ($code) {
        'sold_out' => '<span class="bd-pill bd-pill--out">' . e($label) . '</span>',
        'cancelled' => '<span class="bd-pill bd-pill--out">' . e($label) . '</span>',
        'past' => '<span class="bd-pill">' . te('Detalii') . '</span>',
        'external' => '<span class="bd-pill">' . te('Bilete la organizator') . '</span><span class="bd-btn bd-btn--sm bd-btn--ghost">' . te('Detalii') . ' ' . BD_ARROW . '</span>',
        'low' => '<span class="bd-pill bd-pill--hot">' . e($label) . '</span><span class="bd-btn bd-btn--sm">' . te('Bilete') . ' ' . BD_ARROW . '</span>',
        default => '<span class="bd-btn bd-btn--sm">' . te('Bilete') . ' ' . BD_ARROW . '</span>',
    };
    return '<li><a class="bd-row bd-row--' . $code . '" href="' . e($url) . '" data-img="' . e($img) . '">'
        . '<time class="bd-row__date" datetime="' . e(substr((string) ($ev['start_date'] ?? ''), 0, 10)) . '"><b>' . e($dp['day']) . '</b><span>' . e($dp['month']) . ' ' . e($dp['year']) . '</span><small>' . e($dp['weekday']) . '</small></time>'
        . '<span class="bd-row__city">' . e(bd_city($ev)) . '</span>'
        . '<span class="bd-row__venue">' . e(bd_venue($ev)) . '</span>'
        . '<span class="bd-row__act">' . $btn . '</span>'
        . '</a></li>';
}
