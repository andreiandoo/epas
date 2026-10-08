<?php
/**
 * Viaqui v2: languages.
 *
 * Pages are written in English and every text a visitor reads goes through one of the functions below, with the
 * English text itself as the key:
 *
 *   v2_t('Sign in')                                  the text in the visitor's language (not escaped)
 *   v2_te('Sign in')                                 the same, escaped for HTML (what templates print)
 *   v2_t('Hello, {name}', ['name' => $n])            {placeholders} are filled after translation
 *   v2_num(5, 'city', 'cities')                      "5 cities": the count formatted, the noun in the right plural form
 *
 * A language's catalogue is lang/<code>.php, an array with three parts:
 *   'strings' => ['Sign in' => 'Anmelden', …]                    texts of PHP pages
 *   'plurals' => ['city|cities' => ['Stadt', 'Städte'], …]       the forms the language needs, in the order of its rule
 *   'js'      => ['Loading…' => 'Wird geladen…', …]              texts of the scripts (VQ.t in assets/v2/js/i18n.js)
 * A text with no translation is shown in English, so a catalogue can be filled gradually.
 *
 * Which languages exist and which are open is said in includes/locales.php. The language of a request is its first
 * path segment (/de/rome); includes/router.php takes the prefix off and leaves the code in $GLOBALS['V2_LOCALE'].
 * The pages keep printing plain addresses (/rome): for a language other than the default, the whole page is passed
 * through v2_i18n_links() on its way out, which puts the prefix on every internal link.
 */

/** ['default' => 'en', 'enabled' => [...], 'names' => [...]] from includes/locales.php. */
function v2_locales(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = dirname(__DIR__) . '/locales.php';
        $cfg = is_file($file) ? (array) require $file : [];
        $cfg += ['default' => 'en', 'enabled' => ['en'], 'names' => ['en' => 'English']];
    }
    return $cfg;
}

/** The language of this request: an enabled code, the default one when the address carries no prefix. */
function v2_locale(): string
{
    $cfg = v2_locales();
    $code = (string) ($GLOBALS['V2_LOCALE'] ?? '');
    return $code !== '' && in_array($code, $cfg['enabled'], true) ? $code : (string) $cfg['default'];
}

function v2_is_default_locale(): bool
{
    return v2_locale() === v2_locales()['default'];
}

/** The catalogue of the current language; empty for the default language, whose texts are in the pages. */
function v2_catalogue(): array
{
    static $loaded = [];
    $code = v2_locale();
    if (!isset($loaded[$code])) {
        $file = dirname(__DIR__, 2) . '/lang/' . $code . '.php';
        $data = (!v2_is_default_locale() && is_file($file)) ? (array) require $file : [];
        $loaded[$code] = $data + ['strings' => [], 'plurals' => [], 'js' => []];
    }
    return $loaded[$code];
}

/** A text in the visitor's language. $text is the English text as written in the page; {name} parts are filled from $vars. */
function v2_t(string $text, array $vars = []): string
{
    $out = v2_catalogue()['strings'][$text] ?? $text;
    if ($out === '') {
        $out = $text;
    }
    if ($vars) {
        $pairs = [];
        foreach ($vars as $k => $v) {
            $pairs['{' . $k . '}'] = (string) $v;
        }
        $out = strtr($out, $pairs);
    }
    return $out;
}

/** v2_t(), escaped for HTML. Values in $vars are escaped too, so they may come from data. */
function v2_te(string $text, array $vars = []): string
{
    return htmlspecialchars(v2_t($text, $vars), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Which plural form a number takes in a language (0 = the first form of its catalogue entry).
 * English, German, Spanish, Italian, Dutch, Portuguese: one / other. French: 0 and 1 are singular.
 * Romanian: one / few (2-19, and what ends in 01-19) / other ("20 de orașe"). Polish: one / few / many.
 */
function v2_plural_index(int $n, ?string $locale = null): int
{
    $n = abs($n);
    switch ($locale ?? v2_locale()) {
        case 'fr':
            return $n <= 1 ? 0 : 1;
        case 'ro':
            if ($n === 1) {
                return 0;
            }
            return ($n === 0 || ($n % 100 >= 1 && $n % 100 <= 19)) ? 1 : 2;
        case 'pl':
            if ($n === 1) {
                return 0;
            }
            return ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) ? 1 : 2;
        default:
            return $n === 1 ? 0 : 1;
    }
}

/** The noun for a count, in the right form: v2_plural(5, 'city', 'cities') => "cities" (or its translation). */
function v2_plural(int $n, string $one, string $many): string
{
    $forms = v2_catalogue()['plurals'][$one . '|' . $many] ?? null;
    if (is_array($forms) && $forms) {
        $i = v2_plural_index($n);
        return (string) ($forms[$i] ?? end($forms));
    }
    return $n === 1 ? $one : $many;
}

/** An internal address with the language prefix, for the few places that build one outside a page (redirects, emails). */
function v2_url(string $path): string
{
    if (v2_is_default_locale() || $path === '' || $path[0] !== '/' || strncmp($path, '//', 2) === 0) {
        return $path;
    }
    return '/' . v2_locale() . ($path === '/' ? '' : $path);
}

/** Puts the language prefix on every internal link and form of a finished page. Files, the API and other languages are left alone. */
function v2_i18n_links(string $html): string
{
    $prefix = '/' . v2_locale();
    $skip = 'assets/|api/|storage/|sitemap|robots\.txt|favicon|manifest|\.well-known/|' . implode('|', array_map(fn ($c) => preg_quote($c, '#') . '(?:/|["\'?\#])', v2_locales()['enabled']));
    return (string) preg_replace_callback(
        '#\b(href|action)=(["\'])/(?!/)(?!(?:' . $skip . '))([^"\']*)\2#',
        fn ($m) => $m[1] . '=' . $m[2] . $prefix . ($m[3] === '' ? '' : '/' . $m[3]) . $m[2],
        $html
    );
}

/** Called once by head.php: for a language other than the default, the page's links get their prefix on the way out. */
function v2_i18n_boot(): void
{
    static $done = false;
    if ($done || v2_is_default_locale()) {
        return;
    }
    $done = true;
    ob_start('v2_i18n_links');
}

/** <link rel="alternate" hreflang> for every open language, so search engines pair the versions of a page. Nothing with one language. */
function v2_hreflang(string $canonicalUrl): string
{
    $cfg = v2_locales();
    if (count($cfg['enabled']) < 2 || !defined('SITE_URL')) {
        return '';
    }
    $path = (string) substr($canonicalUrl, strlen(SITE_URL));
    $path = (string) preg_replace('#^/(' . implode('|', array_map('preg_quote', $cfg['enabled'])) . ')(?=/|$|\?)#', '', $path);
    $out = '';
    foreach ($cfg['enabled'] as $code) {
        $href = SITE_URL . ($code === $cfg['default'] ? '' : '/' . $code) . ($path === '' ? '/' : $path);
        $out .= '<link rel="alternate" hreflang="' . $code . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    return $out . '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(SITE_URL . ($path === '' ? '/' : $path), ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

/** The languages of the menu: [[code, name, address of this page in that language, current?]]. One entry when only one is open. */
function v2_language_links(): array
{
    $cfg = v2_locales();
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = (string) preg_replace('#^/(' . implode('|', array_map('preg_quote', $cfg['enabled'])) . ')(?=/|$)#', '', $path);
    $rows = [];
    foreach ($cfg['enabled'] as $code) {
        $rows[] = [$code, $cfg['names'][$code] ?? strtoupper($code), ($code === $cfg['default'] ? '' : '/' . $code) . ($path === '' ? '/' : $path), $code === v2_locale()];
    }
    return $rows;
}

/** What the scripts need: the language code, its plural rule and the texts of the 'js' part of the catalogue. '' for the default language. */
function v2_i18n_script(): string
{
    if (v2_is_default_locale()) {
        return '';
    }
    $cat = v2_catalogue();
    $payload = ['locale' => v2_locale(), 'strings' => (object) $cat['js'], 'plurals' => (object) $cat['plurals']];
    return '<script>window.VQ_I18N=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n";
}
