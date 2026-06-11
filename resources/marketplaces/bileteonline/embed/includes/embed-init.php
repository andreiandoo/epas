<?php
/**
 * Common initialization for all embed pages.
 * Persists embed config (theme, accent, logo, bg_image) in cookie so
 * internal navigation within the iframe doesn't lose the settings.
 */

require_once dirname(dirname(__DIR__)) . '/includes/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/api.php';

$organizerSlug = $_GET['organizer'] ?? '';

if (!$organizerSlug) {
    http_response_code(400);
    echo 'Missing organizer parameter.';
    exit;
}

// Cookie name for embed config persistence (per organizer)
$configCookieName = 'emb_cfg_' . preg_replace('/[^a-z0-9]/', '', $organizerSlug);

// Read from GET params first, then fallback to stored cookie
$storedConfig = [];
if (!empty($_COOKIE[$configCookieName])) {
    $storedConfig = json_decode($_COOKIE[$configCookieName], true) ?: [];
}

$returnUrl = $_GET['return_url'] ?? $storedConfig['return_url'] ?? '';
$theme     = $_GET['theme']      ?? $storedConfig['theme']      ?? 'light';
$accent    = $_GET['accent']     ?? $storedConfig['accent']     ?? '';
$embedLogo = $_GET['logo']       ?? $storedConfig['logo']       ?? '';
$embedBgImage = $_GET['bg_image'] ?? $storedConfig['bg_image'] ?? '';

// Persist config in cookie (so internal navigation keeps settings)
$configToStore = json_encode([
    'return_url' => $returnUrl,
    'theme'      => $theme,
    'accent'     => $accent,
    'logo'       => $embedLogo,
    'bg_image'   => $embedBgImage,
]);
// SameSite=None required for cross-origin iframe cookies
setcookie($configCookieName, $configToStore, [
    'expires' => time() + 3600,
    'path' => '/embed/' . $organizerSlug . '/',
    'secure' => true,
    'httponly' => false,
    'samesite' => 'None',
]);

// Fetch organizer data (cached 5 min)
$orgData = api_cached('embed_org_' . $organizerSlug, function () use ($organizerSlug) {
    return api_get('/marketplace-events/organizers/' . urlencode($organizerSlug));
}, 300);

if (empty($orgData['data'])) {
    http_response_code(404);
    echo 'Organizer not found.';
    exit;
}

$orgName = $orgData['data']['name'] ?? 'Organizator';
$embedDomains = $orgData['data']['embed_domains'] ?? [];
$widgetEnabled = (bool) ($orgData['data']['widget_enabled'] ?? false);

// Fallback: use organizer avatar if no logo provided
if (!$embedLogo) {
    $embedLogo = $orgData['data']['avatar'] ?? '';
}

if (!$widgetEnabled) {
    http_response_code(403);
    echo 'Widget embedding is not enabled for this organizer.';
    exit;
}

/**
 * Check if a hostname matches a domain pattern (supports wildcard subdomains).
 */
function matchEmbedDomain(string $host, string $pattern): bool
{
    $patternHost = parse_url($pattern, PHP_URL_HOST) ?: $pattern;
    if ($host === $patternHost) return true;
    if (str_starts_with($patternHost, '*.')) {
        $baseDomain = substr($patternHost, 2);
        return str_ends_with($host, '.' . $baseDomain);
    }
    return false;
}

// Validate return_url against embed_domains whitelist
if ($returnUrl) {
    $returnHost = parse_url($returnUrl, PHP_URL_HOST);
    $allowed = false;
    foreach ($embedDomains as $domain) {
        if (matchEmbedDomain($returnHost, $domain)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        $returnUrl = '';
    }
}
