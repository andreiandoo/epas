<?php
/**
 * Single-segment URL dispatcher.
 *
 * .htaccess routes `/{slug}` (any single-segment slug not claimed by an
 * earlier explicit rule) to this file. Tries the marketplace API to decide
 * whether the slug is a category, a city, or unknown, and includes the
 * appropriate render template.
 *
 * Direct access to /category.php?slug=X or /city.php?slug=X still works
 * because each render template falls back to fetching its own data when
 * called standalone.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';

if (!preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// 1. Try category first (categories tend to be more numerous in seed data
// and are the primary SEO landing targets).
$category = navGetCategoryBySlug($slug);
if ($category) {
    require __DIR__ . '/category.php';
    return;
}

// 2. Try city.
$cityData = navGetCityBySlug($slug);
if ($cityData) {
    require __DIR__ . '/city.php';
    return;
}

// 3. Neither — 404.
http_response_code(404);
require __DIR__ . '/404.php';
exit;
