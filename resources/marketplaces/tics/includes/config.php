<?php
/**
 * TICS.ro Marketplace Configuration
 *
 * API credentials and site settings
 */

// Prevent direct access
if (!defined('TICS_ROOT')) {
    define('TICS_ROOT', dirname(__DIR__));
}

// API Configuration - Tixello Core API
define('API_BASE_URL', 'https://core.tixello.com/api/marketplace-client');
define('API_KEY', 'mpc_TICS_API_KEY_HERE'); // Replace with actual API key
define('STORAGE_URL', 'https://core.tixello.com/storage'); // Core storage URL for images

// Site Configuration
define('SITE_NAME', 'TICS');
define('SITE_TAGLINE', 'Descoperă experiențe unice');
define('SITE_URL', 'https://tics.ro');
define('SITE_LOCALE', 'ro');

$siteName = SITE_NAME;

// Support Contact
define('SUPPORT_EMAIL', 'suport@tics.ro');
define('SUPPORT_PHONE', '+40 21 XXX XXXX');

// Theme Colors (for PHP-generated content)
$THEME = [
    'primary' => '#111827',       // Gray-900
    'primary_dark' => '#030712',  // Gray-950
    'primary_light' => '#1f2937', // Gray-800
    'secondary' => '#6366f1',     // Indigo-500
    'accent' => '#8b5cf6',        // Violet-500
    'surface' => '#f9fafb',       // Gray-50
    'muted' => '#6b7280',         // Gray-500
    'border' => '#e5e7eb',        // Gray-200
    'success' => '#10b981',       // Emerald-500
    'warning' => '#f59e0b',       // Amber-500
    'error' => '#ef4444',         // Red-500
];

// Categories with icons and emojis
$CATEGORIES = [
    'concerte' => ['name' => 'Concerte', 'icon' => '🎸', 'slug' => 'concerte'],
    'festivaluri' => ['name' => 'Festivaluri', 'icon' => '🎪', 'slug' => 'festivaluri'],
    'stand-up' => ['name' => 'Stand-up', 'icon' => '😂', 'slug' => 'stand-up'],
    'teatru' => ['name' => 'Teatru', 'icon' => '🎭', 'slug' => 'teatru'],
    'sport' => ['name' => 'Sport', 'icon' => '⚽', 'slug' => 'sport'],
    'arta-muzee' => ['name' => 'Artă & Muzee', 'icon' => '🎨', 'slug' => 'arta-muzee'],
    'familie' => ['name' => 'Familie', 'icon' => '👨‍👩‍👧', 'slug' => 'familie'],
    'business' => ['name' => 'Business', 'icon' => '💼', 'slug' => 'business'],
    'educatie' => ['name' => 'Educație', 'icon' => '🎓', 'slug' => 'educatie'],
];

// Featured cities
$FEATURED_CITIES = [
    ['name' => 'București', 'slug' => 'bucuresti', 'count' => 124],
    ['name' => 'Cluj-Napoca', 'slug' => 'cluj-napoca', 'count' => 89],
    ['name' => 'Timișoara', 'slug' => 'timisoara', 'count' => 45],
    ['name' => 'Iași', 'slug' => 'iasi', 'count' => 32],
    ['name' => 'Constanța', 'slug' => 'constanta', 'count' => 28],
    ['name' => 'Brașov', 'slug' => 'brasov', 'count' => 24],
    ['name' => 'Sibiu', 'slug' => 'sibiu', 'count' => 18],
    ['name' => 'Craiova', 'slug' => 'craiova', 'count' => 15],
];

// Date filter options
$DATE_FILTERS = [
    '' => 'Oricând',
    'today' => 'Astăzi',
    'tomorrow' => 'Mâine',
    'weekend' => 'Weekendul acesta',
    'week' => 'Săptămâna aceasta',
    'month' => 'Luna aceasta',
    'next-month' => 'Luna viitoare',
];

// Price filter options
$PRICE_FILTERS = [
    '' => 'Orice preț',
    'free' => 'Gratuit',
    '0-100' => 'Sub 100 RON',
    '100-300' => '100 - 300 RON',
    '300-500' => '300 - 500 RON',
    '500+' => 'Peste 500 RON',
];

// Sort options
$SORT_OPTIONS = [
    'recommended' => 'Recomandate pentru tine',
    'date' => 'Data: cele mai apropiate',
    'price_asc' => 'Preț: mic - mare',
    'price_desc' => 'Preț: mare - mic',
    'popular' => 'Cele mai populare',
    'newest' => 'Recent adăugate',
];

/**
 * Get category by slug
 */
function getCategory($slug) {
    global $CATEGORIES;
    return $CATEGORIES[$slug] ?? null;
}

/**
 * Get category icon by slug
 */
function getCategoryIcon($slug) {
    global $CATEGORIES;
    return $CATEGORIES[$slug]['icon'] ?? '📅';
}

/**
 * Get asset URL with cache busting
 */
function asset($path) {
    $file = TICS_ROOT . '/' . ltrim($path, '/');
    $version = file_exists($file) ? filemtime($file) : time();
    return '/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Get storage URL for images
 */
function getStorageUrl($path) {
    if (!$path) return '/assets/images/default-event.jpg';
    if (strpos($path, 'http') === 0) return $path;
    return STORAGE_URL . '/' . ltrim($path, '/');
}

/**
 * Get the current page name for navigation highlighting
 */
function getCurrentPage() {
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    $path = strtok($path, '?');
    return basename($path, '.php');
}

/**
 * Check if current page matches
 */
function isCurrentPage($page) {
    return getCurrentPage() === basename($page, '.php');
}

/**
 * Format price for display
 */
function formatPrice($price, $currency = 'RON') {
    if ($price == 0) return 'Gratuit';
    return number_format($price, 0, ',', '.') . ' ' . $currency;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd M Y') {
    if (!$date) return '';
    $months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $d = new DateTime($date);
    $result = str_replace(
        ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        $months,
        $d->format($format)
    );
    return $result;
}

/**
 * Escape HTML
 */
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
