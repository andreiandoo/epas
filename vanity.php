<?php
/**
 * Vanity URL Resolver - Ambilet Marketplace
 *
 * Catches single-segment paths (e.g. /qfeel) and resolves them via the
 * marketplace_vanity_urls table. If a vanity slug exists, the corresponding
 * entity page (artist/event/venue/organizer) is rendered DIRECTLY in place
 * (the URL in the address bar stays short).
 *
 * If no vanity match is found, falls back to city.php to preserve the
 * original catch-all behaviour.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$vanitySlug = $_GET['slug'] ?? '';

// Defensive: invalid slug → fallback to city
if (empty($vanitySlug) || !preg_match('/^[a-z][a-z0-9-]{0,99}$/', $vanitySlug)) {
    include __DIR__ . '/city.php';
    return;
}

// Resolve via API
$response = api_get('/vanity/' . urlencode($vanitySlug));

// api_get() wraps the API payload as ['success' => bool, 'data' => <payload>]
// so the actual {found, type, ...} fields live under $response['data'].
$payload = $response['data'] ?? [];

// API returns {found: false} OR HTTP 404 → fall through to city
if (empty($response['success']) || empty($payload['found']) || $payload['found'] !== true) {
    include __DIR__ . '/city.php';
    return;
}

$type = $payload['type'] ?? null;

// External URL → 302 redirect
if ($type === 'external_url') {
    $targetUrl = $payload['target_url'] ?? null;
    if ($targetUrl) {
        header('Location: ' . $targetUrl, true, 302);
        exit;
    }
    include __DIR__ . '/city.php';
    return;
}

// For internal targets, inject the real entity slug into $_GET so the
// included entry file picks it up via its existing $_GET['slug'] reading.
$targetSlug = $payload['target_slug'] ?? null;
if (!$targetSlug) {
    include __DIR__ . '/city.php';
    return;
}

$_GET['slug'] = $targetSlug;
$_GET['vanity_slug'] = $vanitySlug; // Available to the included file if it wants to render canonical

// Optional: tell the included page that it's being rendered via a vanity URL
// so it can adjust canonical link / og:url to the short version
$GLOBALS['VANITY_RENDER'] = [
    'vanity_slug' => $vanitySlug,
    'short_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bilete.online') . '/' . $vanitySlug,
];

switch ($type) {
    case 'artist':
        include __DIR__ . '/artist-single.php';
        return;
    case 'event':
        include __DIR__ . '/event.php';
        return;
    case 'venue':
        include __DIR__ . '/venue-single.php';
        return;
    case 'organizer':
        include __DIR__ . '/organizer-public.php';
        return;
    default:
        include __DIR__ . '/city.php';
        return;
}
