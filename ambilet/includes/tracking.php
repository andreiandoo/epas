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
