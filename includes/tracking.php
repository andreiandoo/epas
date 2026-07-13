<?php
/**
 * Tracking Scripts Loader
 *
 * Fetches tracking pixel configurations (GA4, GTM, Meta, TikTok, Google Ads)
 * from the API and prepares script tags for injection into head and body.
 *
 * Sets two global variables:
 * - $trackingHeadScripts: HTML to inject before </head>
 * - $trackingBodyScripts: HTML to inject before </body>
 *
 * Optional: set $organizerTrackingId BEFORE including this file (or rely on the
 * `ambilet_active_organizer` cookie set on event pages) to route through the
 * organizer overlay endpoint. That endpoint returns organizer pixels layered
 * on top of the marketplace fallback per provider — an organizer that only
 * configured GA4 still inherits the marketplace's TikTok/Meta/Google Ads, and
 * conversely marketplace pixels don't double-fire on top of organizer pixels
 * of the same provider.
 *
 * Requires: config.php and api.php to be loaded first.
 */

$trackingHeadScripts = '';
$trackingBodyScripts = '';

// Ensure api.php is loaded (provides api_cached, api_get)
require_once __DIR__ . '/api.php';

$organizerTrackingId = $organizerTrackingId ?? ($_COOKIE['ambilet_active_organizer'] ?? null);
$organizerTrackingId = is_numeric($organizerTrackingId) ? (int) $organizerTrackingId : null;

try {
    if ($organizerTrackingId) {
        // Organizer overlay endpoint returns the fully merged script set
        // (organizer's pixels + marketplace fallback for providers the
        // organizer didn't configure). Do NOT also fetch the marketplace
        // endpoint here — it would re-emit the same fallback pixels a
        // second time and end up with duplicate gtag/fbq/ttq inits.
        $trackingData = api_cached('tracking_scripts_org_' . $organizerTrackingId, function () use ($organizerTrackingId) {
            $response = api_get('/tracking/organizer/' . $organizerTrackingId . '/scripts');
            return $response['success'] ? $response['data'] : [];
        }, 300);
    } else {
        // No organizer context (home, listing, static pages) — pure
        // marketplace pixels.
        $trackingData = api_cached('tracking_scripts', function () {
            $response = api_get('/tracking/scripts');
            return $response['success'] ? $response['data'] : [];
        }, 300);
    }

    if (!empty($trackingData['head_scripts'])) {
        $trackingHeadScripts = $trackingData['head_scripts'];
    }
    if (!empty($trackingData['body_scripts'])) {
        $trackingBodyScripts = $trackingData['body_scripts'];
    }
} catch (Exception $e) {
    // Silently fail - tracking should never break the page
    error_log('Tracking scripts load error: ' . $e->getMessage());
}
