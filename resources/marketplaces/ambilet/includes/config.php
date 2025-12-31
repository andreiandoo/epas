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
define('API_KEY', 'mpc_YOUR_API_KEY_HERE'); // Replace with actual API key
define('API_SECRET', 'YOUR_API_SECRET_HERE'); // Replace with actual secret

// Site Configuration
define('SITE_NAME', 'Ambilet');
define('SITE_TAGLINE', 'Bilete Evenimente Romania');
define('SITE_URL', 'https://bilete.online'); // Change to ambilet.ro in production
define('SITE_LOCALE', 'ro');

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
