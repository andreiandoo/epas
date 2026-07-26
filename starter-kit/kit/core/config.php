<?php
/**
 * Kit bootstrap + configuration.
 *
 * A template calls kit_boot($siteConfig) ONCE (from its includes/bootstrap.php),
 * passing its site.config.php array. Everything else in the kit reads config
 * through Kit::config() / kit_cfg().
 *
 * $siteConfig keys (see the _starter-* templates' site.config.php for the full list):
 *   profile            'marketplace' | 'tenant'      (selects the adapter + proxy allow-list)
 *   api_base           e.g. https://core.tixello.com/api
 *   core_url           e.g. https://core.tixello.com          (asset origin)
 *   api_key            marketplace only  (X-API-Key)
 *   tenant_id          tenant only       (?tenant=ID)
 *   site_name, site_city, logo_text, site_url, locale, currency
 *   event_url_pattern  e.g. /spectacol/{slug}   (canonical link builder)
 *   artist_url_pattern, venue_url_pattern, category_url_pattern
 *   cache_ttl, api_timeout, debug
 *   fixtures           optional dir with JSON fixtures for offline rendering
 */

final class Kit {
    private static array $cfg = [];
    private static bool $booted = false;

    public static function boot(array $cfg): void {
        $defaults = [
            'profile'            => 'tenant',
            'api_base'           => 'https://core.tixello.com/api',
            'core_url'           => 'https://core.tixello.com',
            'api_key'            => '',
            'tenant_id'          => null,
            'site_name'          => 'Site',
            'site_city'          => '',
            'logo_text'          => '',
            'site_url'           => '',
            'locale'             => 'ro',
            'currency'           => 'RON',
            'event_url_pattern'  => '/spectacol/{slug}',
            'artist_url_pattern' => '/artist/{slug}',
            'venue_url_pattern'  => '/venue/{slug}',
            'category_url_pattern'=> '/category/{slug}',
            'cache_ttl'          => 120,
            'api_timeout'        => 15,
            'debug'              => false,
            'cart_key'           => 'kit_cart',   // localStorage key for the cart
            'auth_key'           => 'kit_auth',   // localStorage key for {token,user}
            'proxy_url'          => '/api/proxy.php',
            'fixtures'           => null,
            'tokens_href'        => '/theme/tokens.css',
            'theme_href'         => '/theme/theme.css',
        ];
        self::$cfg = array_replace($defaults, $cfg);
        self::$booted = true;
    }

    public static function config(): array {
        if (!self::$booted) {
            throw new RuntimeException('Kit not booted. Call kit_boot($siteConfig) first.');
        }
        return self::$cfg;
    }

    public static function get(string $key, $default = null) {
        return self::$cfg[$key] ?? $default;
    }

    public static function isProfile(string $p): bool {
        return (self::$cfg['profile'] ?? '') === $p;
    }
}

/** Boot the kit from a template's site config, then wire the core modules. */
function kit_boot(array $siteConfig): void {
    Kit::boot($siteConfig);
    require_once __DIR__ . '/viewmodel.php';
    require_once __DIR__ . '/http.php';
    require_once __DIR__ . '/data.php';
    require_once __DIR__ . '/view.php';
    // Load only the adapter for the active profile.
    if (Kit::isProfile('marketplace')) {
        require_once __DIR__ . '/adapters/marketplace.php';
    } else {
        require_once __DIR__ . '/adapters/tenant.php';
    }
}

/** Shorthand config accessor for templates/components. */
function kit_cfg(string $key = null, $default = null) {
    return $key === null ? Kit::config() : Kit::get($key, $default);
}

/**
 * Resolve a storage asset path to an absolute URL.
 * Accepts a relative path ('events/x.jpg'), an absolute URL, or null.
 * (Ported/generalized from teatru asset_url().)  Also usable by adapters
 * before view.php is loaded, so it lives here.
 */
function kit_asset_url(?string $path, ?array $cfg = null, ?string $fallback = ''): string {
    if (empty($path)) return (string)$fallback;
    if (preg_match('#^https?://#', $path)) return $path;
    $core = $cfg['core_url'] ?? Kit::get('core_url', '');
    return rtrim($core, '/') . '/storage/' . ltrim($path, '/');
}
