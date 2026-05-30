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

// 1. Try category by exact slug first. If the slug is the LEGACY long
//    form ({child}-{parent}), 301-redirect to the new short canonical URL.
//    The short form is what we link from internal nav + what's set as
//    the canonical in <head>, so Google replaces the old URL within a
//    few crawls and the link juice transfers automatically.
$category = navGetCategoryBySlug($slug);
if ($category) {
    $shortSlug = bo_short_category_slug($category);
    if ($shortSlug && $shortSlug !== $slug) {
        header('Location: /' . $shortSlug, true, 301);
        exit;
    }
    require __DIR__ . '/category.php';
    return;
}

// 2. The slug didn't match an exact category record — try as a SHORT
//    form (e.g. /muzee-de-stiinta resolves to the category whose stored
//    slug is muzee-de-stiinta-muzee-expozitii).
$category = bo_find_category_by_short_slug($slug);
if ($category) {
    require __DIR__ . '/category.php';
    return;
}

// 3. Try city.
$cityData = navGetCityBySlug($slug);
if ($cityData) {
    require __DIR__ . '/city.php';
    return;
}

// 4. Neither — 404.
http_response_code(404);
require __DIR__ . '/404.php';
exit;
