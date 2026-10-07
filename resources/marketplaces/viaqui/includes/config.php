<?php
/**
 * Viaqui (viaqui.com) Marketplace Configuration — marketplace client id 4 in Tixello core
 *
 * API credentials and site settings
 */

// Prevent direct access
if (!defined('BILETEONLINE_ROOT')) {
    define('BILETEONLINE_ROOT', dirname(__DIR__));
}
// Backwards-compat alias — many shared includes/templates still reference
// AMBILET_ROOT. Keep it pointing to the same dir so we don't have to refactor
// every file at once. New code should use BILETEONLINE_ROOT.
if (!defined('AMBILET_ROOT')) {
    define('AMBILET_ROOT', BILETEONLINE_ROOT);
}

// API Configuration
// Switch between core (production) and stage (testing) by changing USE_STAGE_API
// Set via query param: ?use_stage=1 to enable, ?use_stage=0 to disable
// Or set directly: define('USE_STAGE_API', true);
$useStage = false;
if (isset($_GET['use_stage'])) {
    $useStage = $_GET['use_stage'] === '1';
    setcookie('use_stage_api', $useStage ? '1' : '0', time() + 86400, '/');
} elseif (isset($_COOKIE['use_stage_api'])) {
    $useStage = $_COOKIE['use_stage_api'] === '1';
}
define('USE_STAGE_API', $useStage);

if (USE_STAGE_API) {
    define('CORE_URL', 'https://stage.tixello.com');
    define('API_BASE_URL', 'https://stage.tixello.com/api/marketplace-client');
    define('STORAGE_URL', 'https://stage.tixello.com/storage');
} else {
    define('CORE_URL', 'https://core.tixello.com');
    define('API_BASE_URL', 'https://core.tixello.com/api/marketplace-client');
    define('STORAGE_URL', 'https://core.tixello.com/storage');
}
// Secrets are not kept in git (the repository is public). They are read from a PHP file that exists only on the
// server and returns an array; see includes/secrets.example.php. First match wins:
//   1. secrets.php one level above the web directory (on Ploi: /home/<user>/viaqui.com/secrets.php)
//   2. data/secrets.php inside the site (local development; git-ignored)
$viaquiSecrets = [];
foreach ([dirname(BILETEONLINE_ROOT) . '/secrets.php', BILETEONLINE_ROOT . '/data/secrets.php'] as $viaquiSecretsFile) {
    if (is_file($viaquiSecretsFile)) {
        $viaquiSecrets = (array) require $viaquiSecretsFile;
        break;
    }
}
define('API_KEY', (string) ($viaquiSecrets['api_key'] ?? ''));
define('API_ENV', USE_STAGE_API ? 'stage' : 'production');

// CARTO basemaps key for the maps (Leaflet tiles). A browser key: it travels in every tile URL and CARTO limits it
// to the domains set in the CARTO dashboard; maps fall back to OpenStreetMap tiles when CARTO refuses it.
define('CARTO_API_KEY', 'cb1_4bli_1_60582fe0e737ee021895d6fc');

// Shared secret for /api/cache-bust.php — verifies the POST is coming
// from Tixello admin (which has the matching VIAQUI_CACHE_BUST_TOKEN
// in its env). Rotate by updating both sides simultaneously.
define('CACHE_BUST_TOKEN', (string) ($viaquiSecrets['cache_bust_token'] ?? ''));

// Travelpayouts (affiliate network: WeGoTrip, Tiqets, Aviasales…). The token is secret; the project id ("trs") and
// the account marker are not, they are part of every partner link. Project id 582490 = viaqui.com, the same number
// as in the site script in the <head>. While the marker is 0, partner offers stay off (includes/v2/partners.php).
define('TRAVELPAYOUTS_TOKEN', (string) ($viaquiSecrets['travelpayouts_token'] ?? ''));
define('TRAVELPAYOUTS_TRS', 582490);
define('TRAVELPAYOUTS_MARKER', (int) ($viaquiSecrets['travelpayouts_marker'] ?? 0));

// Site Configuration
define('SITE_NAME', 'Viaqui');
define('SITE_TAGLINE', 'Your way in.');
define('SITE_URL', 'https://viaqui.com');
define('SITE_LOCALE', 'en');
// Prices are shown in euro everywhere on Viaqui (owner's decision, 2026-10-06). Amounts are not converted:
// venues price their tickets in euro.
define('SITE_CURRENCY', 'EUR');
define('SITE_CURRENCY_SYMBOL', '€');

// Pre-launch: viaqui.com still carries content copied from another marketplace, so search engines are kept out.
// Set to false at launch (and replace robots.txt).
define('SITE_PRELAUNCH', true);
if (SITE_PRELAUNCH && PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
}

$siteName = SITE_NAME;

// Support Contact
define('SUPPORT_EMAIL', 'contact@viaqui.com');
define('SUPPORT_PHONE', ''); // TODO: set actual phone for viaqui.com

// ===========================================
// BREVO (Sendinblue) Email Configuration
// ===========================================
// Get your API key from: https://app.brevo.com/settings/keys/api
define('BREVO_API_KEY', (string) ($viaquiSecrets['brevo_api_key'] ?? ''));
define('BREVO_SENDER_NAME', SITE_NAME);
define('BREVO_SENDER_EMAIL', 'noreply@viaqui.com'); // Must be verified in Brevo

// Email templates directory
define('EMAIL_TEMPLATES_DIR', BILETEONLINE_ROOT . '/emails');

// Email template IDs (for Brevo template-based sending, optional)
$EMAIL_TEMPLATES = [
    'client_welcome' => 1,
    'client_email_confirmation' => 2,
    'client_order_confirmation' => 3,
    'client_referral_invitation' => 4,
    'organizer_welcome' => 5,
    'organizer_email_confirmation' => 6,
    'organizer_payment_confirmation' => 7,
    'organizer_weekly_report' => 8,
    'organizer_monthly_report' => 9,
    'organizer_event_finished_report' => 10,
    'ticket_beneficiary' => 11,
];

// Theme Colors (for PHP-generated content)
// TODO: adjust to viaqui.com brand palette
$THEME = [
    'primary' => '#A51C30',
    'primary_dark' => '#8B1728',
    'primary_light' => '#C41E3A',
    'secondary' => '#1E293B',
    'accent' => '#E67E22',
    'surface' => '#F8FAFC',
    'muted' => '#64748B',
    'border' => '#E2E8F0',
    'success' => '#10B981',
    'warning' => '#F59E0B',
    'error' => '#EF4444',
];

// Categories with icons
// NOTE: viaqui.com does NOT use Artists. Concert/festival categories still
// list events directly; we just don't expose artist profiles or artist routes.
$CATEGORY_ICONS = [
    'concert' => '🎵',
    'festival' => '🎪',
    'theater' => '🎭',
    'sport' => '⚽',
    'comedy' => '😂',
    'conference' => '🎤',
    'exhibition' => '🖼️',
    'workshop' => '🛠️',
    'default' => '📅'
];

/**
 * Get category icon by slug
 */
function getCategoryIcon($slug) {
    global $CATEGORY_ICONS;
    return $CATEGORY_ICONS[$slug] ?? $CATEGORY_ICONS['default'];
}

/**
 * Get asset URL with cache busting
 */
function asset($path) {
    $file = BILETEONLINE_ROOT . '/' . ltrim($path, '/');
    $version = file_exists($file) ? filemtime($file) : time();
    return '/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Get the current page name for navigation highlighting
 */
function getCurrentPage() {
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    $path = strtok($path, '?'); // Remove query string
    return basename($path, '.php');
}

/**
 * Check if current page matches
 */
function isCurrentPage($page) {
    return getCurrentPage() === basename($page, '.php');
}
