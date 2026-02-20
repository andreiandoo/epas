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

/**
 * Generate URL for city page
 * @param string $citySlug - City slug (e.g., 'bucuresti', 'cluj-napoca')
 * @return string URL like /evenimente-bucuresti
 */
function cityUrl($citySlug) {
    return '/evenimente-' . $citySlug;
}

/**
 * Generate URL for venue page
 * @param string $venueSlug - Venue slug (e.g., 'arena-nationala')
 * @return string URL like /bilete-arena-nationala
 */
function venueUrl($venueSlug) {
    return '/bilete-' . $venueSlug;
}

/**
 * Generate URL for event page
 * @param string $eventSlug - Event slug
 * @param string $citySlug - City slug
 * @return string URL like /bilete/concert-coldplay-bucuresti
 */
function eventUrl($eventSlug, $citySlug = '') {
    $url = '/bilete/' . $eventSlug;
    if ($citySlug) {
        $url .= '-' . $citySlug;
    }
    return $url;
}

/**
 * Generate slug from text
 * @param string $text - Text to convert to slug
 * @return string URL-friendly slug
 */
function slugify($text) {
    // Replace Romanian diacritics
    $diacritics = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
        'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ț' => 't',
        'ş' => 's', 'ţ' => 't', 'Ş' => 's', 'Ţ' => 't',
    ];
    $text = strtr($text, $diacritics);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

/**
 * Get city slug from city name
 * @param string $cityName - City name with diacritics
 * @return string Slug without diacritics
 */
function getCitySlug($cityName) {
    return slugify($cityName);
}

/**
 * Get city name from slug
 * @param string $slug - City slug
 * @return string|null City name or null if not found
 */
function getCityName($slug) {
    global $FEATURED_CITIES;
    foreach ($FEATURED_CITIES as $city) {
        if ($city['slug'] === $slug) {
            return $city['name'];
        }
    }
    // Fallback: capitalize slug
    return ucfirst(str_replace('-', ' ', $slug));
}

// ============================================================================
// DEMO LOGIN STATE
// ============================================================================

// Demo mode: set to true to simulate logged-in user on all pages
define('DEMO_LOGIN_ENABLED', true);

// Demo user data
$DEMO_USER = [
    'id' => 1,
    'name' => 'Alexandru Marin',
    'firstName' => 'Alexandru',
    'lastName' => 'Marin',
    'email' => 'alexandru.marin@example.com',
    'avatar' => 'https://i.pravatar.cc/40?img=68',
    'points' => 1250,
    'phone' => '+40 721 234 567',
    'city' => 'Bucuresti',
    'birthDate' => '1992-05-15',
];

/**
 * Check if demo user is logged in
 * In production, this would check session/JWT
 */
function isDemoLoggedIn() {
    return defined('DEMO_LOGIN_ENABLED') && DEMO_LOGIN_ENABLED;
}

/**
 * Get demo user data
 */
function getDemoUser() {
    global $DEMO_USER;
    return $DEMO_USER;
}

/**
 * Set login state variables for header
 * Call this before including header.php
 */
function setLoginState(&$isLoggedIn, &$loggedInUser) {
    if (isDemoLoggedIn()) {
        $isLoggedIn = true;
        $loggedInUser = getDemoUser();
    } else {
        $isLoggedIn = false;
        $loggedInUser = null;
    }
}

/**
 * Call Tixello Core API server-side via cURL
 * @param string $endpoint - API endpoint (e.g. 'artists', 'artists/slug')
 * @param array $params - Query parameters
 * @return array|null - Decoded JSON response or null on failure
 */
function callApi($endpoint, $params = []) {
    $url = API_BASE_URL . '/' . ltrim($endpoint, '/');
    // Remove null/empty params
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Format large numbers for display (e.g. 1234567 -> "1.2M", 12345 -> "12.3K")
 */
function formatFollowers($num) {
    if (!is_numeric($num) || $num === 0) return '0';
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}
