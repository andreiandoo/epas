<?php
/**
 * Common initialization for all embed pages.
 * Sets up organizer data, validates embed_domains, handles CSP.
 *
 * After including this file, these variables are available:
 *   $organizerSlug, $returnUrl, $theme, $accent
 *   $orgData, $embedDomains, $orgName
 */

require_once dirname(dirname(__DIR__)) . '/includes/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/api.php';

$organizerSlug = $_GET['organizer'] ?? '';
$returnUrl = $_GET['return_url'] ?? '';
$theme = $_GET['theme'] ?? 'light';
$accent = $_GET['accent'] ?? '';

if (!$organizerSlug) {
    http_response_code(400);
    echo 'Missing organizer parameter.';
    exit;
}

// Fetch organizer data (cached 5 min)
$orgData = api_cached('embed_org_' . $organizerSlug, function () use ($organizerSlug) {
    return api_get('/marketplace-events/organizers/' . urlencode($organizerSlug));
}, 300);

if (empty($orgData['data'])) {
    http_response_code(404);
    echo 'Organizer not found.';
    exit;
}

$orgName = $orgData['data']['name'] ?? 'Organizator';
$embedDomains = $orgData['data']['embed_domains'] ?? [];
$widgetEnabled = (bool) ($orgData['data']['widget_enabled'] ?? false);

if (!$widgetEnabled) {
    http_response_code(403);
    echo 'Widget embedding is not enabled for this organizer.';
    exit;
}

// Validate return_url against embed_domains whitelist
if ($returnUrl) {
    $returnHost = parse_url($returnUrl, PHP_URL_HOST);
    $allowed = false;
    foreach ($embedDomains as $domain) {
        $domainHost = parse_url($domain, PHP_URL_HOST) ?: $domain;
        if ($returnHost === $domainHost) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        $returnUrl = ''; // Reset — will stay in iframe
    }
}
