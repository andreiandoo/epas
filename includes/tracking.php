<?php
/**
 * Tracking Scripts Loader
 *
 * Fetches tracking pixel configurations (GA4, GTM, Meta, TikTok) from the API
 * and prepares script tags for injection into head and body.
 *
 * Sets two global variables:
 * - $trackingHeadScripts: HTML to inject before </head>
 * - $trackingBodyScripts: HTML to inject before </body>
 *
 * Optional: set $organizerTrackingId BEFORE including this file (or rely on the
 * `ambilet_active_organizer` cookie set on event pages) to also pull and append
 * the organizer's own tracking pixels — used so an organizer can follow a
 * customer through cart → checkout → thank-you without losing attribution.
 *
 * Requires: config.php and api.php to be loaded first.
 */

$trackingHeadScripts = '';
$trackingBodyScripts = '';

// Ensure api.php is loaded (provides api_cached, api_get)
require_once __DIR__ . '/api.php';

try {
    $trackingData = api_cached('tracking_scripts', function () {
        $response = api_get('/tracking/scripts');
        return $response['success'] ? $response['data'] : [];
    }, 300); // Cache for 5 minutes

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

// Layer organizer-level pixels on top of marketplace pixels when an organizer
// context is set (event page sets it explicitly; cart/checkout/thank-you use
// the cookie persisted from the event page so funnel attribution survives).
$organizerTrackingId = $organizerTrackingId ?? ($_COOKIE['ambilet_active_organizer'] ?? null);
$organizerTrackingId = is_numeric($organizerTrackingId) ? (int) $organizerTrackingId : null;

if ($organizerTrackingId) {
    try {
        $orgData = api_cached('tracking_scripts_org_' . $organizerTrackingId, function () use ($organizerTrackingId) {
            $response = api_get('/tracking/organizer/' . $organizerTrackingId . '/scripts');
            return $response['success'] ? $response['data'] : [];
        }, 300);

        if (!empty($orgData['head_scripts'])) {
            $trackingHeadScripts .= "\n" . $orgData['head_scripts'];
        }
        if (!empty($orgData['body_scripts'])) {
            $trackingBodyScripts .= "\n" . $orgData['body_scripts'];
        }
    } catch (Exception $e) {
        error_log('Organizer tracking scripts load error: ' . $e->getMessage());
    }
}
