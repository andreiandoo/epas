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
