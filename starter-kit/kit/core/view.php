<?php
/**
 * Kit view engine.
 *
 * component('event-card', ['event' => $e, 'variant' => 'grid'])
 *   → renders kit/components/event-card.php with $event, $variant in scope.
 *
 * layout('public', ['title'=>..,'nav'=>..], fn() => { ...body... })
 *   → wraps a body closure in kit/layouts/public.php.
 *
 * Components are plain PHP partials. They receive ONLY the keys passed in
 * (plus sensible defaults they declare themselves) and MUST NOT fetch data —
 * data comes from the page via the data layer. Keep components pure: data in,
 * HTML out.
 */

/** Escape for HTML output. */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize "rich text" HTML coming from the API/CMS (event descriptions, blog
 * bodies, pages) before echoing it. Allowlist of tags + attributes; strips
 * script/style/iframe/form, on* handlers, and unsafe URL schemes.
 * A deployment that fully trusts its content can set config trust_api_html=true.
 */
function kit_html(?string $html): string {
    if ($html === null || $html === '') return '';
    if (Kit::get('trust_api_html')) return $html;
    if (!class_exists('DOMDocument')) return e($html); // no ext-dom → escape rather than trust

    static $allowed = [
        'p'=>[], 'br'=>[], 'strong'=>[], 'b'=>[], 'em'=>[], 'i'=>[], 'u'=>[], 's'=>[], 'small'=>[],
        'ul'=>[], 'ol'=>[], 'li'=>[], 'h2'=>[], 'h3'=>[], 'h4'=>[], 'h5'=>[], 'blockquote'=>[], 'hr'=>[],
        'a'=>['href','title','target','rel'], 'img'=>['src','alt','width','height'],
        'span'=>[], 'figure'=>[], 'figcaption'=>[], 'table'=>[], 'thead'=>[], 'tbody'=>[], 'tr'=>[], 'td'=>[], 'th'=>[],
    ];
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"><div id="kit-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    if (!$ok) return '';
    $root = $doc->getElementById('kit-root');
    if (!$root) return '';
    $xp = new DOMXPath($doc);
    // Remove dangerous elements entirely (with their content).
    foreach (iterator_to_array($xp->query('.//script|.//style|.//iframe|.//object|.//embed|.//form|.//link|.//meta|.//base', $root)) as $el) {
        if ($el->parentNode) $el->parentNode->removeChild($el);
    }
    // Walk remaining elements: unwrap unknown tags, filter attributes/URLs.
    foreach (iterator_to_array($xp->query('.//*', $root)) as $el) {
        if (!$el instanceof DOMElement || !$el->parentNode) continue;
        $tag = strtolower($el->tagName);
        if (!isset($allowed[$tag])) {
            while ($el->firstChild) $el->parentNode->insertBefore($el->firstChild, $el);
            $el->parentNode->removeChild($el);
            continue;
        }
        foreach (iterator_to_array($el->attributes) as $attr) {
            $an = strtolower($attr->name);
            if (strpos($an, 'on') === 0 || !in_array($an, $allowed[$tag], true)) { $el->removeAttribute($attr->name); continue; }
            if (($an === 'href' || $an === 'src') && !kit_safe_url(trim($attr->value), $tag)) $el->removeAttribute($attr->name);
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') $el->setAttribute('rel', 'noopener noreferrer');
    }
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

/** Whitelist URL schemes for sanitized HTML (relative/anchor/http(s)/mailto; data:image only on <img>). */
function kit_safe_url(string $v, string $tag): bool {
    if ($v === '') return false;
    if (preg_match('#^(https?:|mailto:|tel:)#i', $v)) return true;
    if ($v[0] === '/' || $v[0] === '#' || $v[0] === '.' || $v[0] === '?') return true;
    if ($tag === 'img' && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);#i', $v)) return true;
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $v)) return false; // any other explicit scheme → reject
    return true; // schemeless relative
}

/**
 * Translate a UI string key for the active locale.
 *   t('cart.title')            → "Coșul meu" (ro) / "My cart" (en)
 *   t('cart.count', ['n'=>3])  → replaces {n}
 * Resolution: site config `lang[locale]` override → kit/lang/<locale>.php →
 * kit/lang/<default>.php → the key itself (so a missing key is visible in dev).
 */
function t(string $key, array $vars = []): string {
    $s = kit_lang_dict()[$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return $s;
}

/** Load + memoize the merged dictionary for the active locale. */
function kit_lang_dict(): array {
    static $cache = [];
    $cfg    = Kit::config();
    $active = $cfg['active_locale'] ?? $cfg['locale'];
    if (isset($cache[$active])) return $cache[$active];
    $dir     = __DIR__ . '/../lang';
    $default = $cfg['locale'] ?? 'ro';
    $base = is_file("$dir/$default.php") ? (require "$dir/$default.php") : [];
    $loc  = ($active !== $default && is_file("$dir/$active.php")) ? (require "$dir/$active.php") : [];
    $site = $cfg['lang'][$active] ?? [];
    return $cache[$active] = array_replace($base, $loc, $site);
}

/** Format a price in major units with the site/currency convention. */
function kit_price(?float $amount, string $currency = null): string {
    if ($amount === null) return '';
    $currency = $currency ?: Kit::get('currency', 'RON');
    return number_format($amount, ($amount == (int)$amount ? 0 : 2), ',', '.') . ' ' . $currency;
}

/**
 * Render a component and RETURN its HTML.
 * Data keys are extracted into the partial's scope. A special `slot` key may
 * carry pre-rendered child HTML.
 */
function component_html(string $name, array $data = []): string {
    $file = __DIR__ . '/../components/' . $name . '.php';
    if (!is_file($file)) {
        if (Kit::get('debug')) return "<!-- kit: component '{$name}' not found -->";
        return '';
    }
    extract($data, EXTR_SKIP);
    $__cfg = Kit::config();               // every component may read $__cfg
    ob_start();
    include $file;
    return (string)ob_get_clean();
}

/** Render a component directly to output. */
function component(string $name, array $data = []): void {
    echo component_html($name, $data);
}

/**
 * Wrap a body in a layout.
 * @param string   $name  layout file under kit/layouts/
 * @param array    $vars  layout vars (title, nav, description, extra_styles, ...)
 * @param callable $body  closure that ECHOES the page body
 */
function layout(string $name, array $vars, callable $body): void {
    $file = __DIR__ . '/../layouts/' . $name . '.php';
    ob_start();
    $body();
    $slot = ob_get_clean();               // page body HTML → $slot inside layout
    extract($vars, EXTR_SKIP);
    $__cfg = Kit::config();
    include $file;
}

/**
 * Append a content version to a same-origin asset URL: /kit/kit.js → …?v=1a2b3c.
 *
 * The deploy .htaccess caches css/js for seven days, so without this a new
 * kit.js or theme.css stays invisible for a week — and behind a CDN it is worse:
 * Cloudflare kept serving a stale /kit/kit.js after a deploy, which is exactly
 * how the operator panel shipped with its JS missing. The stamp is the file's
 * mtime, so it changes on every deploy and never on a normal request.
 *
 * External URLs and anything already carrying a query are returned untouched.
 */
function kit_asset_v(?string $href): string
{
    $href = (string) $href;
    if ($href === '' || $href[0] !== '/' || strpos($href, '?') !== false) {
        return $href;
    }
    static $cache = [];
    if (isset($cache[$href])) {
        return $cache[$href];
    }
    // Assets live next to the front controller in a build, and under kit/ in dev.
    $roots = array_filter([
        defined('KIT_SITE_ROOT') ? KIT_SITE_ROOT : null,
        $_SERVER['DOCUMENT_ROOT'] ?? null,
        dirname(__DIR__, 2),
    ]);
    foreach ($roots as $root) {
        $file = rtrim((string) $root, '/') . $href;
        if (is_file($file) && ($m = @filemtime($file))) {
            return $cache[$href] = $href . '?v=' . base_convert((string) $m, 10, 36);
        }
    }
    return $cache[$href] = $href;
}

/**
 * Emit the <head> asset tags for tokens + theme. Called by layouts.
 * tokens.css defines the contract + defaults; theme.css overrides variables.
 */
function kit_theme_links(): string {
    $cfg = Kit::config();
    $tokens = e(kit_asset_v($cfg['tokens_href']));
    $theme  = e(kit_asset_v($cfg['theme_href']));
    return "<link rel=\"stylesheet\" href=\"{$tokens}\">\n<link rel=\"stylesheet\" href=\"{$theme}\">";
}

/**
 * Emit SEO <head> tags: description, canonical, Open Graph, Twitter card, and
 * JSON-LD. $seo keys: title, description, image, canonical, og_type ('website'|
 * 'article'|'event'…), event (canonical event → Event schema).
 */
function kit_seo_tags(array $seo = []): string {
    $cfg   = Kit::config();
    $title = $seo['title'] ?? $cfg['site_name'];
    $desc  = $seo['description'] ?? $cfg['description'] ?? ($cfg['site_name'] . ' — bilete și evenimente');
    $img   = $seo['image'] ?? $cfg['og_image'] ?? '';
    $type  = $seo['og_type'] ?? 'website';
    $base  = rtrim($cfg['site_url'] ?? '', '/');
    $canon = $seo['canonical'] ?? ($base . ($_SERVER['REQUEST_URI'] ?? '/'));
    $canon = strtok($canon, '?');

    $t = [];
    $t[] = '<meta name="description" content="' . e($desc) . '">';
    $t[] = '<link rel="canonical" href="' . e($canon) . '">';
    $t[] = '<meta property="og:site_name" content="' . e($cfg['site_name']) . '">';
    $t[] = '<meta property="og:title" content="' . e($title) . '">';
    $t[] = '<meta property="og:description" content="' . e($desc) . '">';
    $t[] = '<meta property="og:type" content="' . e($type) . '">';
    $t[] = '<meta property="og:url" content="' . e($canon) . '">';
    if ($img) $t[] = '<meta property="og:image" content="' . e($img) . '">';
    $t[] = '<meta name="twitter:card" content="' . ($img ? 'summary_large_image' : 'summary') . '">';
    if (!empty($cfg['twitter'])) $t[] = '<meta name="twitter:site" content="' . e($cfg['twitter']) . '">';

    // hreflang alternates for multi-locale sites (query-param strategy).
    $locales = $cfg['locales'] ?? [$cfg['locale']];
    if (count($locales) > 1) {
        $sep = strpos($canon, '?') === false ? '?' : '&';
        foreach ($locales as $lc) {
            $t[] = '<link rel="alternate" hreflang="' . e($lc) . '" href="' . e($canon . $sep . 'lang=' . $lc) . '">';
        }
        $t[] = '<link rel="alternate" hreflang="x-default" href="' . e($canon) . '">';
    }

    // JSON-LD: Organization (always) + Event (on event pages).
    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => $cfg['organization_type'] ?? 'Organization',
        'name'     => $cfg['site_name'],
        'url'      => $base ?: $canon,
    ];
    if (!empty($cfg['social'])) $ld['sameAs'] = array_values($cfg['social']);
    $graph = [$ld];
    if (!empty($seo['event']) && is_array($seo['event'])) {
        $ev = $seo['event'];
        $event = [
            '@context'   => 'https://schema.org',
            '@type'      => 'Event',
            'name'       => $ev['title'] ?? '',
            'startDate'  => $ev['starts_at'] ?? ($ev['date'] ?? ''),
            'eventStatus'=> $ev['is_cancelled'] ? 'https://schema.org/EventCancelled'
                          : ($ev['is_postponed'] ? 'https://schema.org/EventPostponed' : 'https://schema.org/EventScheduled'),
            'url'        => $canon,
        ];
        if (!empty($ev['venue_name'])) $event['location'] = ['@type' => 'Place', 'name' => $ev['venue_name'], 'address' => $ev['city'] ?? ''];
        if (!empty($ev['poster_url'])) $event['image'] = $ev['poster_url'];
        if ($ev['price_from'] !== null) $event['offers'] = ['@type' => 'Offer', 'price' => $ev['price_from'], 'priceCurrency' => $ev['currency'] ?? 'RON', 'availability' => ($ev['is_sold_out'] ?? false) ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock'];
        $graph[] = $event;
    }
    $t[] = '<script type="application/ld+json">' . json_encode(count($graph) === 1 ? $graph[0] : $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    return implode("\n  ", $t);
}

/** Emit window.KIT_ANALYTICS so kit.js can load trackers AFTER consent. */
function kit_analytics_config(): string {
    $cfg = Kit::config();
    $a = array_filter([
        'ga4'   => $cfg['ga4_id'] ?? null,
        'gtm'   => $cfg['gtm_id'] ?? null,
        'meta'  => $cfg['meta_pixel_id'] ?? null,
    ]);
    if (!$a && !($cfg['cookie_consent'] ?? true)) return '';
    return '<script>window.KIT_ANALYTICS=' . json_encode($a, JSON_UNESCAPED_SLASHES)
         . ';window.KIT_CONSENT_REQUIRED=' . (($cfg['cookie_consent'] ?? true) ? 'true' : 'false') . ';</script>';
}

/**
 * Emit the JS bootstrap in the CORRECT ORDER: window.KIT config, then kit.js
 * (defines KitProxy/KitAuth/kitAccountShell/…), then Alpine. Both scripts are
 * deferred and execute in document order, so kit.js's globals exist before
 * Alpine evaluates x-data. Layouts must call this in <head> (once).
 */
function kit_head_scripts(): string {
    $cfg = Kit::config();
    $kitCfg = json_encode([
        'proxy'    => $cfg['proxy_url'] ?? '/api/proxy.php',
        'cartKey'  => $cfg['cart_key'] ?? 'kit_cart',
        'authKey'  => $cfg['auth_key'] ?? 'kit_auth',
        // Operator identity is deliberately separate from the customer one.
        'operatorKey' => $cfg['operator_key'] ?? 'kit_operator',
        'cartUrl'  => $cfg['cart_url'] ?? '/cos',
        'currency' => $cfg['currency'] ?? 'RON',
        'locale'   => Kit::activeLocale(),
    ], JSON_UNESCAPED_SLASHES);
    $out  = "<script>window.KIT = {$kitCfg};</script>\n";
    $out .= '<script defer src="' . e(kit_asset_v($cfg['kit_js_href'] ?? '/kit/kit.js')) . '"></script>' . "\n";
    if ($cfg['use_alpine'] ?? true) {
        $out .= '<script defer src="' . e(kit_asset_v($cfg['alpine_href'])) . '"></script>';
    }
    return $out;
}

/** Label of the menu item with the given nav key (for page titles/H1s). */
function kit_nav_label(string $navKey, string $fallback = ''): string {
    foreach ((Kit::get('menu') ?? []) as $item) {
        if (($item['key'] ?? '') === $navKey) return $item['label'] ?? $fallback;
    }
    return $fallback;
}

/** URL of the menu item with the given nav key (for pagination/base links). */
function kit_nav_url(string $navKey, string $fallback = '/'): string {
    foreach ((Kit::get('menu') ?? []) as $item) {
        if (($item['key'] ?? '') === $navKey) return $item['url'] ?? $fallback;
    }
    return $fallback;
}

/**
 * Build a `data:` .ics link for a canonical event (add-to-calendar, no backend).
 * All-day if the event has no time.
 */
function kit_ics_link(array $event): string {
    if (empty($event['date'])) return '';
    $esc = fn($s) => preg_replace('/([,;\\\\])/', '\\\\$1', str_replace("\n", '\\n', (string)$s));
    $date = str_replace('-', '', $event['date']);
    if (!empty($event['time'])) {
        $dt = 'DTSTART:' . $date . 'T' . str_replace(':', '', $event['time']) . '00';
    } else {
        $dt = 'DTSTART;VALUE=DATE:' . $date;
    }
    $loc = trim(($event['venue_name'] ?? '') . (!empty($event['city']) ? ', ' . $event['city'] : ''), ', ');
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//kit//event//EN', 'BEGIN:VEVENT',
        'UID:' . ($event['id'] ?? uniqid()) . '@' . preg_replace('#^https?://#', '', (string)Kit::get('site_url', 'site')),
        $dt,
        'SUMMARY:' . $esc($event['title'] ?? ''),
        $loc ? 'LOCATION:' . $esc($loc) : '',
        !empty($event['short_description']) ? 'DESCRIPTION:' . $esc($event['short_description']) : '',
        !empty($event['url']) ? 'URL:' . $esc($event['url']) : '',
        'END:VEVENT', 'END:VCALENDAR',
    ];
    $ics = implode("\r\n", array_filter($lines));
    return 'data:text/calendar;charset=utf-8,' . rawurlencode($ics);
}

/** Convenience: format a date badge (day + short month) from Y-m-d. */
function kit_date_badge(string $ymd, string $locale = 'ro'): array {
    if (!$ymd) return ['day' => '', 'month' => ''];
    $ts = strtotime($ymd);
    $months = ['','Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Noi','Dec'];
    return ['day' => (int)date('j', $ts), 'month' => $months[(int)date('n', $ts)] ?? ''];
}
