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
 * Emit the <head> asset tags for tokens + theme. Called by layouts.
 * tokens.css defines the contract + defaults; theme.css overrides variables.
 */
function kit_theme_links(): string {
    $cfg = Kit::config();
    $tokens = e($cfg['tokens_href']);
    $theme  = e($cfg['theme_href']);
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
        'cartUrl'  => $cfg['cart_url'] ?? '/cos',
        'currency' => $cfg['currency'] ?? 'RON',
    ], JSON_UNESCAPED_SLASHES);
    $out  = "<script>window.KIT = {$kitCfg};</script>\n";
    $out .= '<script defer src="' . e($cfg['kit_js_href'] ?? '/kit/kit.js') . '"></script>' . "\n";
    if ($cfg['use_alpine'] ?? true) {
        $out .= '<script defer src="' . e($cfg['alpine_href']) . '"></script>';
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

/** Convenience: format a date badge (day + short month) from Y-m-d. */
function kit_date_badge(string $ymd, string $locale = 'ro'): array {
    if (!$ymd) return ['day' => '', 'month' => ''];
    $ts = strtotime($ymd);
    $months = ['','Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Noi','Dec'];
    return ['day' => (int)date('j', $ts), 'month' => $months[(int)date('n', $ts)] ?? ''];
}
