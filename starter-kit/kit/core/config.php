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
            'kind'               => null,          // tenant sub-type (see kit/kinds/)
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
            'alpine_href'        => '/kit/js/vendor/alpine.min.js', // vendored (no CDN dependency)
            'use_tailwind'       => false, // kit is self-contained via kit-* classes; opt in only if a template uses Tailwind utilities
            'use_alpine'         => true,
            // SEO / social
            'description'        => null,   // default meta description
            'og_image'           => null,   // absolute URL for OG/Twitter image
            'twitter'            => null,   // @handle
            'social'             => [],     // ['facebook'=>url,'instagram'=>url,...] for footer + JSON-LD sameAs
            'organization_type'  => 'Organization', // schema.org type (e.g. PerformingGroup, MusicGroup)
            // Analytics (gated behind cookie consent). Any may be null.
            'ga4_id'             => null,   // G-XXXXXXXXXX
            'gtm_id'             => null,   // GTM-XXXXXXX
            'meta_pixel_id'      => null,
            'cookie_consent'     => true,   // show the GDPR banner
            'newsletter'         => true,   // show the footer newsletter form
            'favicon'            => '/favicon.svg',
            // Nouns the UI uses; a kind overrides these so generic pages read
            // "Spectacole" for a theatre, "Activități" for a leisure venue, etc.
            'terms' => [
                'event'      => 'eveniment',  'events'     => 'evenimente',
                'events_cap' => 'Evenimente', 'event_cap'  => 'Eveniment',
                'venue'      => 'locație',     'artist'     => 'artist',
                'artists'    => 'artiști',     'artists_cap'=> 'Artiști',
                'buy'        => 'Cumpără bilete', 'buy_short' => 'Bilete',
            ],
            // Capability switches; a kind turns on what it supports.
            'features' => [
                'seating' => false, 'subscriptions' => false, 'gamification' => false,
                'reviews' => false, 'gift_cards' => false, 'tours' => false,
                'multi_venue' => false, 'epk' => false, 'booking' => false,
                'rentals' => false, 'merch' => false, 'fan_crm' => false,
            ],
        ];

        // Load the kind manifest (if any) and layer it BETWEEN defaults and the
        // site config: defaults ← kind ← site. `terms` and `features` deep-merge
        // so a site can override a single noun/flag without redeclaring the map.
        $kind = [];
        $kindName = $cfg['kind'] ?? null;
        if ($kindName) {
            $file = __DIR__ . '/../kinds/' . preg_replace('/[^a-z0-9_-]/', '', (string)$kindName) . '.php';
            if (is_file($file)) { $kind = require $file; }
            elseif (!empty($cfg['debug'])) { error_log("Kit: unknown kind '{$kindName}'"); }
        }

        $merged = array_replace($defaults, $kind, $cfg);
        $merged['terms']    = array_replace($defaults['terms'],    $kind['terms']    ?? [], $cfg['terms']    ?? []);
        $merged['features'] = array_replace($defaults['features'], $kind['features'] ?? [], $cfg['features'] ?? []);

        self::$cfg = $merged;
        self::$booted = true;
    }

    public static function booted(): bool {
        return self::$booted;
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

    public static function kind(): ?string {
        return self::$cfg['kind'] ?? null;
    }

    /** Is a capability enabled for this site's kind? */
    public static function feature(string $name): bool {
        return !empty(self::$cfg['features'][$name]);
    }

    /** A UI noun for this kind, e.g. term('events_cap') → 'Spectacole'. */
    public static function term(string $key, string $fallback = ''): string {
        return self::$cfg['terms'][$key] ?? $fallback;
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

/** Active tenant kind (teatru, filarmonica, …) or null. */
function kit_kind(): ?string { return Kit::kind(); }

/** True if a capability is enabled for this site's kind. */
function kit_feature(string $name): bool { return Kit::feature($name); }

/** A kind-specific UI noun. e.g. kit_term('events_cap', 'Evenimente'). */
function kit_term(string $key, string $fallback = ''): string { return Kit::term($key, $fallback); }

/** List available kinds (from kit/kinds/*.php) with their labels. */
function kit_kinds(): array {
    $out = [];
    foreach (glob(__DIR__ . '/../kinds/*.php') as $file) {
        $name = basename($file, '.php');
        $m = require $file;
        $out[$name] = $m['label'] ?? ucfirst($name);
    }
    return $out;
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
