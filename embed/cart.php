<?php
/**
 * Embed: Cart page — redirects to unified checkout.
 */
$organizerSlug = $_GET['organizer'] ?? '';
header('Location: /embed/' . urlencode($organizerSlug) . '/checkout');
exit;
