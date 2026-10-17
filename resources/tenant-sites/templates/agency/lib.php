<?php
/**
 * Template-ul agency (agenție de artiști), din core:
 *   /tenant-client/roster          artiștii agenției (profil scurt + următorul concert)
 *   /tenant-client/roster/{slug}   un artist: bio, linkuri, video, concerte
 *   /tenant-client/events          concertele produse de agenție (cu tickets_left / tickets_low)
 *   /tenant-client/events/past     concertele trecute
 *   /tenant-client/events/{slug}   un concert, cu biletele (has_seating = loc pe hartă) și roster
 *   POST /tenant-client/booking-requests   cererile de booking (prin proxy: booking-request)
 */

const AG_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const AG_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>';

/** Concertele viitoare, cronologic. */
function ag_upcoming(): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events', ['per_page' => 40, 'limit' => 40]));
        usort($l, fn ($a, $b) => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? '')));
    }
    return $l;
}

/** Concertele trecute, cele mai recente primele. */
function ag_past(int $n = 24): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events/past', ['per_page' => $n]));
        usort($l, fn ($a, $b) => strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? '')));
    }
    return $l;
}

/** Un concert (detaliu cu bilete). */
function ag_event(string $slug): ?array {
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
function ag_next(): ?array {
    $slug = trim((string) site('settings.featured_slug', ''));
    if ($slug === '') {
        foreach (ag_upcoming() as $ev) {
            if (empty($ev['is_sold_out']) && empty($ev['is_cancelled']) && !ag_external($ev)) {
                $slug = (string) $ev['slug'];
                break;
            }
        }
        $slug = $slug ?: (string) (ag_upcoming()[0]['slug'] ?? '');
    }
    return $slug !== '' ? ag_event($slug) : null;
}

/** Linkul de bilete al unui concert vândut de alt organizator (ex. un festival), dacă are. */
function ag_external(array $ev): ?string {
    $u = trim((string) ($ev['external_ticket_url'] ?? ''));
    return preg_match('#^https?://#i', $u) ? $u : null;
}

/** Starea biletelor: [cod, etichetă] — on_sale, low, sold_out, external, cancelled, past. */
function ag_status(array $ev): array {
    if (!empty($ev['is_cancelled'])) {
        return ['cancelled', t('Anulat')];
    }
    if (ag_is_past($ev)) {
        return ['past', t('A avut loc')];
    }
    if (ag_external($ev)) {
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

function ag_is_past(array $ev): bool {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    return $d !== '' && strtotime($d . ' 23:59') < time();
}

/** Bucățile datei pentru listă: zi, lună scurtă, an, zi a săptămânii. */
function ag_date_parts(?array $ev): array {
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
function ag_when(?array $ev, bool $withTime = true): string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return '';
    }
    $t = substr((string) ($ev['start_time'] ?? ''), 0, 5);
    return ts_date($d, true) . ($withTime && $t !== '' ? ' · ' . $t : '');
}

/** Orașul concertului, cu numele lui în limba vizitatorului (lang/en/places.php: Viena → Vienna). */
function ag_city(?array $ev): string {
    $c = trim((string) ($ev['venue']['city'] ?? ''));
    return $c !== '' ? t($c) : '';
}

function ag_venue(?array $ev): string {
    return trim((string) ($ev['venue']['name'] ?? ''));
}

function ag_place(?array $ev): string {
    return trim(ag_venue($ev) . (ag_city($ev) !== '' ? ', ' . ag_city($ev) : ''), ', ');
}

function ag_start_iso(?array $ev): ?string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return null;
    }
    return $d . 'T' . (substr((string) ($ev['start_time'] ?? ''), 0, 5) ?: '20:00') . ':00';
}

function ag_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Prețul de la care încep biletele unui concert (din listă sau din detaliu). */
function ag_from_price(array $ev): ?float {
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
function ag_countdown(?array $ev, string $class = ''): string {
    $iso = ag_start_iso($ev);
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $out = '<div class="ag-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la concert') . '">';
    foreach (['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')] as $k => $label) {
        $out .= '<div><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Numele concertului fără oraș: „Mira Vale · Cluj-Napoca” → „Mira Vale”. */
function ag_show_name(array $ev): string {
    $t = (string) ($ev['title'] ?? '');
    $p = mb_strrpos($t, ' · ');
    return $p !== false ? mb_substr($t, 0, $p) : $t;
}

/** Concertul în forma coșului comun. */
function ag_cart_event(array $ev): array {
    return [
        'id' => (int) $ev['id'], 'slug' => (string) $ev['slug'], 'title' => (string) $ev['title'],
        'date' => ag_when($ev), 'place' => ag_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? ''), 'url' => ts_link('event', ['slug' => $ev['slug']]),
    ];
}

/** Rândul unui concert în listele de evenimente (acasă, /evenimente, pagina artistului). */
function ag_row(array $ev, bool $past = false): string {
    $dp = ag_date_parts($ev);
    [$code, $label] = ag_status($ev);
    $url = ts_link('event', ['slug' => $ev['slug']]);
    $img = (string) ($ev['poster_url'] ?? $ev['hero_image_url'] ?? '');
    $btn = match ($code) {
        'sold_out' => '<span class="ag-pill ag-pill--out">' . e($label) . '</span>',
        'cancelled' => '<span class="ag-pill ag-pill--out">' . e($label) . '</span>',
        'past' => '<span class="ag-pill">' . te('Detalii') . '</span>',
        'external' => '<span class="ag-pill">' . te('Bilete la organizator') . '</span><span class="ag-btn ag-btn--sm ag-btn--ghost">' . te('Detalii') . ' ' . AG_ARROW . '</span>',
        'low' => '<span class="ag-pill ag-pill--hot">' . e($label) . '</span><span class="ag-btn ag-btn--sm">' . te('Bilete') . ' ' . AG_ARROW . '</span>',
        default => '<span class="ag-btn ag-btn--sm">' . te('Bilete') . ' ' . AG_ARROW . '</span>',
    };
    return '<li><a class="ag-row ag-row--' . $code . '" href="' . e($url) . '" data-img="' . e($img) . '">'
        . '<time class="ag-row__date" datetime="' . e(substr((string) ($ev['start_date'] ?? ''), 0, 10)) . '"><b>' . e($dp['day']) . '</b><span>' . e($dp['month']) . ' ' . e($dp['year']) . '</span><small>' . e($dp['weekday']) . '</small></time>'
        . '<span class="ag-row__city">' . e(ag_show_name($ev)) . '</span>'
        . '<span class="ag-row__venue">' . e(trim(ag_city($ev) . ' · ' . ag_venue($ev), ' ·')) . '</span>'
        . '<span class="ag-row__act">' . $btn . '</span>'
        . '</a></li>';
}

/* ---------------------------------------------------------------- rosterul agenției */

/** Artiștii din roster (profil scurt + următorul concert), din /tenant-client/roster. */
function ag_roster(): array {
    static $l = null;
    if ($l === null) {
        $r = api_get('/tenant-client/roster', [], 120);
        $l = (array) ($r['data']['artists'] ?? []);
    }
    return $l;
}

/** Un artist din roster: bio, galerie, linkuri, video, concertele viitoare și trecute. */
function ag_artist(string $slug): ?array {
    static $c = [];
    if ($slug === '') {
        return null;
    }
    if (!array_key_exists($slug, $c)) {
        $r = api_get('/tenant-client/roster/' . rawurlencode($slug), [], 120);
        $c[$slug] = (is_array($r['data'] ?? null) && !empty($r['data']['slug'])) ? $r['data'] : null;
    }
    return $c[$slug];
}

/** Tipul artistului, pe înțelesul vizitatorului. */
function ag_kind(?string $k): string {
    return [
        'solo' => t('Artist solo'), 'band' => t('Trupă'), 'dj' => t('DJ / producător'),
        'duo' => t('Duo'), 'ensemble' => t('Ansamblu'),
    ][$k ?? ''] ?? t('Artist');
}

function ag_artist_url(array $a): string {
    return ts_link('artist', ['id' => $a['slug'] ?? '']);
}

/** 412000 → „412K”, 1250000 → „1,3M” (ascultători lunari). */
function ag_short_number(?int $n): string {
    if (!$n) {
        return '';
    }
    $en = ts_locale() === 'en';
    if ($n >= 1000000) {
        return str_replace('.', $en ? '.' : ',', (string) round($n / 1000000, 1)) . 'M';
    }
    return $n >= 1000 ? round($n / 1000) . 'K' : (string) $n;
}

/** Steagul țării dintr-un cod ISO (RO → 🇷🇴). */
function ag_flag(?string $iso): string {
    $iso = strtoupper(trim((string) $iso));
    if (!preg_match('/^[A-Z]{2}$/', $iso)) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($iso[0]) - 65) . mb_chr(0x1F1E6 + ord($iso[1]) - 65);
}

/** Cardul unui artist din roster: portret, tip, nume, genuri, orașul și următorul concert. */
function ag_card(array $a): string {
    $next = $a['next_show'] ?? null;
    $nextTxt = '';
    if ($next && !empty($next['date'])) {
        $dp = ag_date_parts(['start_date' => $next['date']]);
        $nextTxt = '<em>' . te('Live') . ' · ' . e($dp['day'] . ' ' . $dp['month']) . (!empty($next['city']) ? ' · ' . e(t((string) $next['city'])) : '') . '</em>';
    }
    return '<a class="ag-card" href="' . e(ag_artist_url($a)) . '" data-ag-tilt>'
        . '<span class="ag-card__pic">' . (!empty($a['photo']) ? '<img src="' . e($a['photo']) . '" alt="" loading="lazy">' : '') . '<small>' . e(ag_kind($a['kind'] ?? null)) . '</small></span>'
        . '<span class="ag-card__txt"><b>' . e($a['name']) . '</b>'
        . '<span>' . e(implode(' · ', array_slice((array) ($a['genres'] ?? []), 0, 3))) . '</span>'
        . '<span class="ag-card__city">' . e(trim(ag_flag($a['country'] ?? null) . ' ' . t((string) ($a['city'] ?? '')))) . '</span>'
        . $nextTxt . '</span></a>';
}
