<?php
/**
 * Vanity Tour URL Resolver - Ambilet Marketplace
 *
 * Resolves 2-segment URLs like /qfeel/bucuresti where:
 *   - First segment is an artist vanity slug
 *   - Second segment is a tour/grouping slug (only serie_evenimente type)
 *
 * Renders the standard artist-single page but signals the JS layer to
 * filter the events list to that specific grouping. If the artist or
 * grouping cannot be resolved, falls back to a 404.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$artistSlug = $_GET['slug'] ?? '';
$tourSlug = $_GET['tour'] ?? '';

if (empty($artistSlug) || empty($tourSlug)
    || !preg_match('/^[a-z][a-z0-9-]{0,99}$/', $artistSlug)
    || !preg_match('/^[a-z0-9-]{1,100}$/', $tourSlug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

// Resolve the first segment as an artist via the vanity API
$response = api_get('/vanity/' . urlencode($artistSlug));
$payload = $response['data'] ?? [];

$isArtist = !empty($response['success'])
    && !empty($payload['found'])
    && ($payload['type'] ?? '') === 'artist';

if (!$isArtist) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

// Inject the resolved artist slug + tour filter for artist-single.php / JS
$_GET['slug'] = $payload['target_slug'] ?? $artistSlug;
$_GET['tour_slug'] = $tourSlug;
$_GET['vanity_slug'] = $artistSlug;

$GLOBALS['VANITY_RENDER'] = [
    'vanity_slug' => $artistSlug,
    'short_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'ambilet.ro') . '/' . $artistSlug . '/' . $tourSlug,
];

include __DIR__ . '/artist-single.php';
