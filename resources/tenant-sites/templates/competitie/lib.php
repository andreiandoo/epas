<?php
/**
 * Ajutoarele template-ului competitie: date, prețuri, fragmente de markup.
 * Portate din resources/tenant-demos/competitie (includes/api.php + parts.php).
 */

const ICON_ARROW = '<svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const ICON_PIN   = '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.2 7-11.5A7 7 0 005 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
const ICON_CAL   = '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>';
const ICON_CHECK = '<svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';

/** Buton-link cu săgeată. */
function part_btn(string $label, string $href, string $cls = ''): string {
    return '<a class="btn ' . $cls . '" href="' . e($href) . '">' . e($label) . '<span class="btn__arrow">' . ICON_ARROW . '</span></a>';
}

/** Afișul competiției sau placeholder tipografic când lipsește. */
function part_poster(array $ev, string $loading = 'lazy'): string {
    $src = $ev['poster_url'] ?? ($ev['hero_image_url'] ?? null);
    if ($src) {
        return '<img src="' . e($src) . '" alt="Afiș ' . e($ev['title'] ?? '') . '" loading="' . $loading . '" decoding="async">';
    }
    return '<div class="poster-ph"><span>' . e($ev['title'] ?? '') . '</span></div>';
}

/** Card de competiție (afiș + detalii), cu înclinare 3D la cursor. */
function part_card(array $ev): string {
    $d    = ev_date_parts($ev);
    $from = $ev['price_from'] ?? null;
    ob_start(); ?>
    <a class="pcard" href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>" data-tilt="6">
        <div class="pcard__media">
            <?= part_poster($ev) ?>
            <span class="pcard__date"><b><?= e($d['days']) ?></b><span><?= e($d['month'] . ' ' . $d['year']) ?></span></span>
            <span class="pcard__kind"><?= e(ev_kind($ev)) ?></span>
        </div>
        <div class="pcard__body">
            <span class="pcard__title"><?= e($ev['title']) ?></span>
            <span class="pcard__place"><?= ICON_PIN ?><?= e(ev_place($ev)) ?></span>
            <span class="pcard__foot">
                <?php if (!empty($ev['is_sold_out'])): ?>
                    <span>Bilete epuizate</span>
                <?php elseif ($from !== null): ?>
                    <span>de la <b><?= e(lei($from)) ?></b></span>
                <?php else: ?>
                    <span>Bilete în curând</span>
                <?php endif; ?>
                <span class="pcard__go"><?= ICON_ARROW ?></span>
            </span>
        </div>
        <span class="pcard__glare" aria-hidden="true"></span>
    </a>
    <?php return ob_get_clean();
}

/** Numărătoare inversă până la startul competiției (o actualizează fx.js). */
function part_countdown(array $ev): string {
    $iso = ev_start_iso($ev);
    if (!$iso || (ev_days_left($ev) ?? -1) < 0) { return ''; }
    $cells = ['d' => 'zile', 'h' => 'ore', 'm' => 'min', 's' => 'sec'];
    $out = '<div class="cd" data-countdown="' . e($iso) . '" role="timer" aria-label="Timp rămas până la start">';
    foreach ($cells as $k => $label) {
        $out .= '<div class="cd__cell"><b data-cd="' . $k . '">–</b><span>' . $label . '</span></div>';
    }
    return $out . '</div>';
}

/** Un eveniment după slug (detaliu complet, cu tipuri de bilete). Null dacă nu există. */
function tc_event(string $slug, ?int $cacheTtl = null): ?array {
    $resp = api_get('/tenant-client/events/' . rawurlencode($slug), ['locale' => 'ro'], $cacheTtl);
    if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null) || empty($resp['data']['id'])) { return null; }
    return tc_norm_event($resp['data']);
}

/** Toate competițiile viitoare, în ordine cronologică. */
function tc_upcoming(int $limit = 50): array {
    $events = tc_events(api_get('/tenant-client/events', ['limit' => $limit, 'per_page' => $limit, 'locale' => 'ro']));
    // API-ul ordonează după event_date, care e gol la competițiile pe mai multe zile
    usort($events, fn ($a, $b) => strcmp((string) ev_day($a['start_date'] ?? null), (string) ev_day($b['start_date'] ?? null)));
    return $events;
}

const RO_MONTHS = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
const RO_MONTHS_SHORT = [1 => 'ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];

/** Data calendaristică (Y-m-d) dintr-un ISO 8601, fără conversii de fus orar. */
function ev_day(?string $iso): ?string {
    return ($iso && preg_match('/^\d{4}-\d{2}-\d{2}/', $iso, $m)) ? $m[0] : null;
}

/** [zi start, zi sfârșit] pentru un eveniment; sfârșitul e null la competițiile de o zi. */
function ev_span(array $e): array {
    $s = ev_day($e['start_date'] ?? ($e['date'] ?? null));
    $f = ev_day($e['end_date'] ?? null);
    if ($f === $s) { $f = null; }
    return [$s, $f];
}

/** „14–15 noiembrie 2026” / „3 aprilie 2027” / „30 ianuarie – 1 februarie 2027”. */
function ev_date_label(array $e): string {
    [$s, $f] = ev_span($e);
    if (!$s) { return 'Dată în curs de anunțare'; }
    [$y1, $m1, $d1] = array_map('intval', explode('-', $s));
    if (!$f) { return $d1 . ' ' . RO_MONTHS[$m1] . ' ' . $y1; }
    [$y2, $m2, $d2] = array_map('intval', explode('-', $f));
    if ($y1 === $y2 && $m1 === $m2) { return $d1 . '–' . $d2 . ' ' . RO_MONTHS[$m1] . ' ' . $y1; }
    return $d1 . ' ' . RO_MONTHS[$m1] . ' – ' . $d2 . ' ' . RO_MONTHS[$m2] . ' ' . $y2;
}

/** Bucăți pentru blocul de dată: ['days' => '14–15', 'month' => 'noi', 'year' => '2026']. */
function ev_date_parts(array $e): array {
    [$s, $f] = ev_span($e);
    if (!$s) { return ['days' => '—', 'month' => '', 'year' => '']; }
    [$y1, $m1, $d1] = array_map('intval', explode('-', $s));
    $days = (string) $d1;
    if ($f) {
        [, $m2, $d2] = array_map('intval', explode('-', $f));
        $days = $m1 === $m2 ? $d1 . '–' . $d2 : $d1 . '+';
    }
    return ['days' => $days, 'month' => RO_MONTHS_SHORT[$m1], 'year' => (string) $y1];
}

/** Zile rămase până la start (0 = azi, negativ = trecut, null = fără dată). */
function ev_days_left(array $e): ?int {
    [$s] = ev_span($e);
    if (!$s) { return null; }
    return (int) round((strtotime($s . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
}

/** Momentul de start ca dată locală ISO („2026-11-14T09:00:00”), pentru numărătoarea inversă. */
function ev_start_iso(array $e): ?string {
    [$s] = ev_span($e);
    if (!$s) { return null; }
    $time = !empty($e['start_time']) ? substr((string) $e['start_time'], 0, 5) : '09:00';
    return $s . 'T' . $time . ':00';
}

/** Felul competiției: categoria evenimentului din core, altfel dedus din titlu. */
function ev_kind(array $e): string {
    if (!empty($e['category']['name'])) { return (string) $e['category']['name']; }
    $t = mb_strtolower((string) ($e['title'] ?? ''));
    if (str_contains($t, 'campionat')) { return 'Campionat'; }
    if (str_contains($t, 'cupa') || str_contains($t, 'cupă')) { return 'Cupă'; }
    if (str_contains($t, 'gal')) { return 'Gală'; }
    return 'Competiție';
}

/** Cheie de filtrare pentru felul competiției. */
function ev_kind_key(array $e): string {
    $k = strtolower((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ev_kind($e)) ?: 'x'));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $k), '-') ?: 'x';
}

/** Competiție cu locuri numerotate (bilete pe loc), după semnalele din API-ul public. */
function ev_is_seated(array $e): bool {
    // Dacă API-ul de eveniment spune explicit, îl credem
    if (array_key_exists('has_seating', $e)) { return !empty($e['has_seating']); }
    if (!empty($e['seating_layout_id'])) { return true; }

    // Altfel întrebăm API-ul public de seating, cu răspunsul (și cel negativ) ținut 10 minute
    $id = (int) ($e['id'] ?? 0);
    if (!$id) { return false; }
    $file = CACHE_DIR . '/seated_' . $id . '.txt';
    if (is_file($file) && (time() - filemtime($file)) < 600) {
        return trim((string) @file_get_contents($file)) === '1';
    }
    $resp = api_request('GET', API_BASE . '/public/events/' . $id . '/seating');
    $seated = ($resp['success'] ?? false) && !empty($resp['raw']['event_seating_id']);
    @file_put_contents($file, $seated ? '1' : '0', LOCK_EX);
    return $seated;
}

/** Numărul de zile de concurs. */
function ev_days_count(array $e): int {
    [$s, $f] = ev_span($e);
    if (!$s || !$f) { return 1; }
    return max(1, (int) round((strtotime($f) - strtotime($s)) / 86400) + 1);
}

/** „Sala X, Oraș” din venue. */
function ev_place(array $e): string {
    $v = $e['venue'] ?? null;
    if (!is_array($v)) { return ''; }
    return implode(', ', array_filter([$v['name'] ?? '', $v['city'] ?? '']));
}

/** Ora de start „HH:MM” sau ''. */
function ev_time(array $e): string {
    return !empty($e['start_time']) ? substr((string) $e['start_time'], 0, 5) : '';
}

/** Preț afișat fără zecimale inutile: „25 lei”, „12,50 lei”. */
function lei($amount): string {
    $n = (float) $amount;
    return (floor($n) == $n ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.')) . ' lei';
}

/** Text scurt din panou: *cuvânt* → <em>, **cuvânt** → evidențiat (.hl), rând nou → <br>. */
function comp_md(?string $text): string {
    $h = e((string) $text);
    $h = preg_replace('/\*\*(.+?)\*\*/u', '<span class="hl">$1</span>', $h);
    $h = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $h);
    return nl2br($h, false);
}

/** Competițiile viitoare, o singură dată pe cerere (le folosesc mai multe secțiuni). */
function comp_events(): array {
    static $events = null;
    return $events ??= tc_upcoming();
}

/** Prima competiție (sau cea aleasă după slug), cu detaliile complete. */
function comp_spot(string $slug = ''): ?array {
    static $cache = [];
    if (array_key_exists($slug, $cache)) { return $cache[$slug]; }
    $ev = $slug !== '' ? tc_event($slug) : null;
    if (!$ev && ($next = comp_events()[0] ?? null)) { $ev = tc_event($next['slug']) ?: $next; }
    return $cache[$slug] = $ev;
}
