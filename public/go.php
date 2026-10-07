<?php
/**
 * /go — the way out to a partner's site.
 *
 *   /go?p=<programme>&u=<address on the partner's site>&s=<sub id>
 *
 * Turns the address into a Travelpayouts partner link (includes/v2/partners.php) and redirects to it. Only
 * addresses on the hosts of a known programme are accepted, so this cannot be used to redirect anywhere else.
 * When the links API does not answer, the visitor still reaches the partner's page, without our tracking.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/v2/partners.php';

$goProgram = is_string($_GET['p'] ?? null) ? $_GET['p'] : '';
$goUrl = is_string($_GET['u'] ?? null) ? $_GET['u'] : '';
$goSub = v2_partner_sub(is_string($_GET['s'] ?? null) ? $_GET['s'] : '');

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (strlen($goUrl) > 600 || !v2_partner_url_ok($goProgram, $goUrl)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

header('Location: ' . (v2_partner_link($goUrl, $goSub) ?? $goUrl), true, 302);
