<?php
/**
 * Ambilet Marketplace Configuration
 *
 * API credentials and site settings
 */

// Prevent direct access
if (!defined('AMBILET_ROOT')) {
    define('AMBILET_ROOT', dirname(__DIR__));
}

// API Configuration
define('API_BASE_URL', 'https://core.tixello.com/api/marketplace-client');
define('API_KEY', 'mpc_4qkv4pcuogusFM9234dwihfTrrkBNT2PzpHflnLLmKfSXgkef9BvefCISPFB'); // Replace with actual API key
define('STORAGE_URL', 'https://core.tixello.com/storage'); // Core storage URL for images

// Site Configuration
define('SITE_NAME', 'AmBilet');
define('SITE_TAGLINE', 'Bilete Evenimente Romania');
define('SITE_URL', 'https://bilete.online'); // Change to ambilet.ro in production
define('SITE_LOCALE', 'ro');

$siteName = SITE_NAME;

// Support Contact
define('SUPPORT_EMAIL', 'suport@bilete.online'); // Change to suport@ambilet.ro in production
define('SUPPORT_PHONE', '+40 21 XXX XXXX'); // Replace with actual phone number

// ===========================================
// BREVO (Sendinblue) Email Configuration
// ===========================================
// Get your API key from: https://app.brevo.com/settings/keys/api
define('BREVO_API_KEY', 'xkeysib-YOUR-API-KEY-HERE'); // Replace with actual Brevo API key
define('BREVO_SENDER_NAME', SITE_NAME);
define('BREVO_SENDER_EMAIL', 'noreply@bilete.online'); // Must be verified in Brevo

// Email templates directory
define('EMAIL_TEMPLATES_DIR', AMBILET_ROOT . '/emails');

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
    $file = AMBILET_ROOT . '/' . ltrim($path, '/');
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
