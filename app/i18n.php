<?php
/**
 * Limbile site-ului (RO, opțional EN).
 *
 * Textele interfeței se scriu în română direct în cod: t('Coșul tău'). Traducerile
 * stau într-un catalog pe limbă (lang/en/*.php, pe zone, plus templates/{t}/lang/en.php
 * pentru textele proprii ale template-ului), cheia fiind textul românesc. Un text
 * fără traducere apare în română, deci nimic nu se strică dacă lipsește o cheie.
 *
 * Textele scrise de tenant în panou rămân cum sunt. Textele implicite ale
 * secțiunilor (cele din template.json) se traduc din câmpul "en" al secțiunii
 * din manifest, doar cât timp tenantul nu le-a schimbat (vezi ts_section_locale).
 *
 * Limba: ?lang=en (ține minte într-un cookie), altfel cookie-ul, altfel prima limbă
 * a site-ului. Limbile permise vin din configurația site-ului (site('languages')).
 */

const TS_LANG_COOKIE = 'site_lang';

/** Limbile site-ului, prima e cea implicită. */
function ts_languages(): array {
    $langs = array_values(array_intersect((array) site('languages', ['ro']), ['ro', 'en']));
    return $langs ?: ['ro'];
}

/** Hotărăște limba cererii; o singură dată, după ce s-a încărcat site-ul. */
function ts_boot_locale(): void {
    $langs = ts_languages();
    $want = strtolower((string) ($_GET['lang'] ?? ''));
    if ($want !== '' && in_array($want, $langs, true)) {
        if (($_COOKIE[TS_LANG_COOKIE] ?? '') !== $want && !headers_sent()) {
            setcookie(TS_LANG_COOKIE, $want, ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
        }
        $locale = $want;
    } else {
        $c = strtolower((string) ($_COOKIE[TS_LANG_COOKIE] ?? ''));
        $locale = in_array($c, $langs, true) ? $c : $langs[0];
    }
    define('LOCALE', $locale);
}

function ts_locale(): string {
    return defined('LOCALE') ? LOCALE : 'ro';
}

/** Catalogul limbii curente: comun + al template-ului (care are prioritate). */
function ts_catalog(): array {
    static $cat = null;
    if ($cat !== null) {
        return $cat;
    }
    $cat = [];
    $l = ts_locale();
    if ($l === 'ro') {
        return $cat;
    }
    // lang/{l}/*.php (comune, pe zone: coș, cont …), apoi templates/{t}/lang/{l}.php
    $files = glob(TS_ROOT . '/lang/' . $l . '/*.php') ?: [];
    if (defined('TEMPLATE_DIR')) {
        $files[] = TEMPLATE_DIR . '/lang/' . $l . '.php';
    }
    foreach ($files as $f) {
        if (is_file($f)) {
            $part = include $f;
            if (is_array($part)) {
                $cat = $part + $cat;
            }
        }
    }
    return $cat;
}

/**
 * Traducerea unui text românesc. Parametrii {nume} se înlocuiesc după traducere:
 * t('Mai sunt {n} locuri', ['n' => 4]).
 */
function t(string $ro, array $params = []): string {
    $s = ts_locale() === 'ro' ? $ro : (ts_catalog()[$ro] ?? $ro);
    if ($params) {
        $rep = [];
        foreach ($params as $k => $v) {
            $rep['{' . $k . '}'] = (string) $v;
        }
        $s = strtr($s, $rep);
    }
    return $s;
}

/** t() escapat pentru HTML. */
function te(string $ro, array $params = []): string {
    return e(t($ro, $params));
}

/** Catalogul pentru JavaScript (TS.t în commerce.js); gol în română. */
function ts_js_catalog(): string {
    $cat = ts_catalog();
    return json_encode((object) $cat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
}

/** Adresa paginii curente în altă limbă. */
function ts_lang_url(string $lang): string {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) parse_url($uri, PHP_URL_PATH);
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    unset($q['lang'], $q['_frame']);
    $q['lang'] = $lang;
    return $path . '?' . http_build_query($q);
}

/**
 * Selectorul de limbă (RO / EN), doar când site-ul are mai multe limbi.
 * $class: clasele containerului; linkul activ primește aria-current.
 */
function ts_lang_switch(string $class = '', string $linkClass = '', string $activeClass = ''): string {
    $langs = ts_languages();
    if (count($langs) < 2) {
        return '';
    }
    $out = '<nav class="' . e($class) . '" aria-label="' . te('Limba') . '">';
    foreach ($langs as $l) {
        $active = $l === ts_locale();
        $out .= '<a href="' . e(ts_lang_url($l)) . '" hreflang="' . $l . '" lang="' . $l . '" class="' . e($linkClass . ($active ? ' ' . $activeClass : '')) . '"'
            . ($active ? ' aria-current="true"' : '') . '>' . strtoupper($l) . '</a>';
    }
    return $out . '</nav>';
}

/**
 * Textele unei secțiuni în limba curentă. Manifestul poate avea, în datele
 * implicite ale secțiunii, o cheie "en" cu aceleași câmpuri traduse. Un câmp se
 * traduce doar dacă tenantul nu l-a modificat (valoarea e încă cea implicită).
 */
function ts_section_locale(array $s, ?array $default): array {
    $l = ts_locale();
    if ($l === 'ro' || !$default || empty($default[$l]) || !is_array($default[$l])) {
        return $s;
    }
    foreach ($default[$l] as $field => $value) {
        $orig = $default[$field] ?? null;
        $cur = $s[$field] ?? null;
        if ($cur === null || $cur === '' || ts_same_value($cur, $orig)) {
            $s[$field] = $value;
        }
    }
    return $s;
}

function ts_same_value($a, $b): bool {
    if (is_array($a) || is_array($b)) {
        return json_encode(ts_strip_ids((array) $a)) === json_encode(ts_strip_ids((array) $b));
    }
    return trim((string) $a) === trim((string) $b);
}

/** Repeaterele din Filament au chei uuid; pentru comparație contează doar valorile. */
function ts_strip_ids(array $rows): array {
    $out = [];
    foreach (array_values($rows) as $r) {
        if (is_array($r)) {
            ksort($r);
            unset($r['enabled']);
            foreach ($r as $k => $v) {
                if (str_ends_with((string) $k, '_url') && ($v === null || $v === '')) {
                    unset($r[$k]);
                }
            }
        }
        $out[] = $r;
    }
    return $out;
}

/** Data în limba curentă: „14 noiembrie 2026” / „14 November 2026”. */
function ts_date(?string $iso, bool $withWeekday = false, bool $withYear = true): string {
    if (!$iso || !($ts = strtotime($iso))) {
        return '';
    }
    $months = ts_locale() === 'en'
        ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
        : ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    $days = ts_locale() === 'en'
        ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
        : ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
    $s = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ($withYear ? ' ' . date('Y', $ts) : '');
    return $withWeekday ? $days[(int) date('w', $ts)] . ', ' . $s : $s;
}
