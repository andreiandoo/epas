<?php
/**
 * Template-ul gala (MMA, box, kickbox): galele, fight card-ul și luptătorii din core:
 *   /tenant-client/events (viitoare), /tenant-client/events/past (arhiva)
 *   /tenant-client/events/{slug}              detaliu + bilete
 *   /tenant-client/events/{slug}/fight-card   meciurile, pe secțiuni
 *   /tenant-client/fighters                   luptătorii cu record și istoric
 */

const GX_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const GX_BELT = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 9h4l2-3h8l2 3h4v6h-4l-2 3H8l-2-3H2z" opacity=".35"/><circle cx="12" cy="12" r="4"/></svg>';

/** Galele viitoare, cronologic. */
function gx_upcoming(): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events', ['per_page' => 20, 'limit' => 20]));
        usort($l, fn ($a, $b) => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? '')));
    }
    return $l;
}

/** Galele trecute, cele mai recente primele. */
function gx_past(int $n = 12): array {
    static $l = null;
    if ($l === null) {
        $l = tc_events(api_get('/tenant-client/events/past', ['per_page' => $n]));
        usort($l, fn ($a, $b) => strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? '')));
    }
    return $l;
}

/** O gală (detaliu cu bilete). */
function gx_event(string $slug): ?array {
    static $c = [];
    if (!array_key_exists($slug, $c)) {
        $r = api_get('/tenant-client/events/' . rawurlencode($slug), [], 120);
        $c[$slug] = (is_array($r['data'] ?? null) && !empty($r['data']['id'])) ? tc_norm_event($r['data']) : null;
    }
    return $c[$slug];
}

/** Meciurile galei, în ordinea de pe afiș. */
function gx_card(string $slug): array {
    static $c = [];
    if (!array_key_exists($slug, $c)) {
        $r = api_get('/tenant-client/events/' . rawurlencode($slug) . '/fight-card', [], 120);
        $c[$slug] = ($r['success'] ?? false) ? (array) ($r['data']['bouts'] ?? []) : [];
    }
    return $c[$slug];
}

/** Luptătorii tenantului. */
function gx_fighters(): array {
    static $l = null;
    if ($l === null) {
        $r = api_get('/tenant-client/fighters', [], 120);
        $l = ($r['success'] ?? false) ? (array) ($r['data']['fighters'] ?? []) : [];
    }
    return $l;
}

/** Gala următoare (sau cea aleasă în panou). */
function gx_next(): ?array {
    $slug = trim((string) site('settings.featured_slug', ''));
    if ($slug === '') {
        $slug = (string) (gx_upcoming()[0]['slug'] ?? '');
    }
    return $slug !== '' ? gx_event($slug) : null;
}

function gx_section_label(string $s): string {
    return [
        'main_event' => t('Main event'), 'co_main' => t('Co-main event'), 'main_card' => t('Main card'),
        'prelims' => t('Preliminarii'), 'early_prelims' => t('Preliminarii timpurii'),
    ][$s] ?? $s;
}

function gx_discipline(?string $d): string {
    return ['mma' => 'MMA', 'kickboxing' => t('Kickbox'), 'boxing' => t('Box'), 'muay_thai' => 'Muay Thai', 'grappling' => 'Grappling'][$d ?? ''] ?? '';
}

/** Steagul unei țări din codul ISO (emoji). */
function gx_flag(?string $iso): string {
    $iso = strtoupper(trim((string) $iso));
    if (!preg_match('/^[A-Z]{2}$/', $iso)) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($iso[0]) - 65) . mb_chr(0x1F1E6 + ord($iso[1]) - 65);
}

function gx_record(?array $f): string {
    $r = $f['record'] ?? null;
    return $r ? $r['w'] . '-' . $r['l'] . '-' . $r['d'] : '';
}

/** Porecla între ghilimelele limbii curente. */
function gx_nick(?string $nick): string {
    if (!$nick) { return ''; }
    return ts_locale() === 'ro' ? '„' . $nick . '”' : '“' . $nick . '”';
}

function gx_initials(string $name): string {
    $p = preg_split('/\s+/u', trim($name)) ?: [];
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr(end($p) ?: '', 0, 1));
}

/** Numele pe două rânduri: prenumele mic, numele de familie mare. */
function gx_name_parts(string $name): array {
    $p = preg_split('/\s+/u', trim($name)) ?: [$name];
    $last = array_pop($p);
    return [implode(' ', $p), (string) $last];
}

/** Data și ora galei, în limba curentă: „Sâmbătă, 21 noiembrie 2026 · 19:00”. */
function gx_when(?array $ev, bool $withTime = true): string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return '';
    }
    $t = substr((string) ($ev['start_time'] ?? ''), 0, 5);
    return ts_date($d, true) . ($withTime && $t !== '' ? ' · ' . $t : '');
}

function gx_place(?array $ev): string {
    $v = $ev['venue'] ?? null;
    return is_array($v) ? trim(($v['name'] ?? '') . (!empty($v['city']) ? ', ' . $v['city'] : '')) : '';
}

function gx_start_iso(?array $ev): ?string {
    $d = substr((string) ($ev['start_date'] ?? ''), 0, 10);
    if ($d === '') {
        return null;
    }
    return $d . 'T' . (substr((string) ($ev['start_time'] ?? ''), 0, 5) ?: '19:00') . ':00';
}

function gx_money(float $n): string {
    $en = ts_locale() === 'en';
    $s = floor($n) == $n ? number_format($n, 0, '', $en ? ',' : '.') : number_format($n, 2, $en ? '.' : ',', $en ? ',' : '.');
    return $s . ($en ? ' RON' : ' lei');
}

/** Numărătoarea până la gală (o actualizează fx.js). */
function gx_countdown(?array $ev, string $class = ''): string {
    $iso = gx_start_iso($ev);
    if (!$iso || strtotime($iso) < time()) {
        return '';
    }
    $out = '<div class="gx-cd ' . e($class) . '" data-countdown="' . e($iso) . '" role="timer" aria-label="' . te('Timp rămas până la gală') . '">';
    foreach (['d' => t('zile'), 'h' => t('ore'), 'm' => t('min'), 's' => t('sec')] as $k => $label) {
        $out .= '<div><b data-cd="' . $k . '">–</b><span>' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Fotografia luptătorului sau monograma (inițiale + steag), pe colțul lui. */
function gx_portrait(array $f, string $corner, string $class = ''): string {
    $photo = $f['cutout'] ?? $f['photo'] ?? null;
    if ($photo) {
        return '<span class="gx-pic gx-pic--' . $corner . ' ' . e($class) . '"><img src="' . e($photo) . '" alt="' . e($f['name'] ?? '') . '" loading="lazy" decoding="async"></span>';
    }
    return '<span class="gx-pic gx-pic--' . $corner . ' gx-pic--mono ' . e($class) . '" aria-hidden="true"><b>' . e(empty($f['id']) ? '?' : gx_initials((string) ($f['name'] ?? '?'))) . '</b><i>' . gx_flag($f['country'] ?? null) . '</i></span>';
}

/** Evenimentul în forma coșului comun. */
function gx_cart_event(array $ev): array {
    return [
        'id' => (int) $ev['id'], 'slug' => (string) $ev['slug'], 'title' => (string) $ev['title'],
        'date' => gx_when($ev), 'place' => gx_place($ev),
        'poster' => (string) ($ev['poster_url'] ?? ''), 'url' => ts_link('event', ['slug' => $ev['slug']]),
    ];
}
