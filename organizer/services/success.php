<?php
/**
 * Service Order Success Handler
 * Redirects to appropriate page based on service type after payment
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$orderId = $_GET['order'] ?? null;
$serviceType = $_GET['type'] ?? null;
$eventId = $_GET['event'] ?? null;

if (!$orderId) {
    header('Location: /organizator/services');
    exit;
}

// Redirect based on service type
switch ($serviceType) {
    case 'email':
        // Redirect to email send confirmation page
        header('Location: /organizator/services/email-send?order=' . urlencode($orderId) . '&event=' . urlencode($eventId));
        break;

    case 'tracking':
        // Redirect to event analytics page
        header('Location: /organizator/analytics?event=' . urlencode($eventId) . '&tracking_activated=1');
        break;

    case 'campaign':
        // Redirect to campaign confirmation page
        header('Location: /organizator/services/campaign-confirmed?order=' . urlencode($orderId) . '&event=' . urlencode($eventId));
        break;

    case 'featuring':
        // Redirect to services page with success message
        header('Location: /organizator/services?featuring_activated=1&event=' . urlencode($eventId));
        break;

    default:
        // Default redirect to services page
        header('Location: /organizator/services?payment_success=1&order=' . urlencode($orderId));
        break;
}
exit;
