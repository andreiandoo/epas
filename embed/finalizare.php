<?php
/**
 * The checkout inside the booking widget: /embed/finalizare?a=<allow token>&u=<operator page>[&anulat=1]
 * (embed code v2, embed/bo-widget.js on the operator's page).
 *
 * The same checkout as /finalizare (checkout.php + checkout-page.js) in embed mode ($ckEmbed): no site header and
 * footer, no tracking, guest only (a frame on another site has no bilete.online login), and the payment goes through
 * the operator's page: the page sends itself to the processor's card page, which returns to embed/retur and from
 * there to the operator's page. Only the sites in the signed allow token may frame it; a missing or expired token
 * shows a short message instead (the widget is then simply reloaded by the customer).
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/embed-return.php';

$ckAllowRaw = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
$ckAllow = $ckAllowRaw !== '' ? bo_embed_allow_verify($ckAllowRaw) : null;
$ckPage = $ckAllow && isset($_GET['u']) && is_string($_GET['u']) ? bo_embed_return_url($_GET['u'], $ckAllow) : null;

header('Content-Security-Policy: frame-ancestors ' . bo_embed_frame_ancestors($ckAllow));
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

if (!$ckAllow || !$ckPage) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Sesiune expirată</title>'
        . '<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1B1F1D;background:transparent}div{max-width:520px;margin:24px auto;padding:24px;border:1px solid #E4E2DA;border-radius:16px;background:#fff}h1{margin:0 0 6px;font-size:1.125rem}p{margin:0;color:#5F6461}</style></head>'
        . '<body><div><h1>Sesiunea de cumpărare a expirat</h1><p>Reîncarcă pagina și alege din nou biletele.</p></div></body></html>';
    exit;
}

$site = rtrim(SITE_URL, '/');
$ckEmbed = [
    'site' => $site,
    'page' => $ckPage,
    'name' => preg_replace('/^www\./', '', (string) parse_url($ckPage, PHP_URL_HOST)),
    // the processor comes back here, then to the operator's page (embed/retur.php); the order number is added in JS
    'return' => $site . '/embed/retur?' . http_build_query(['a' => $ckAllowRaw, 'u' => $ckPage]) . '&o=',
    'cancel' => $site . '/embed/retur?' . http_build_query(['a' => $ckAllowRaw, 'u' => $ckPage, 'c' => 1]),
    // a free order has no payment: straight to the confirmation, in the frame (the order number is added in JS)
    'confirm' => '/embed/confirmare?' . http_build_query(['a' => $ckAllowRaw]) . '&comanda=',
    'cancelled' => !empty($_GET['anulat']),
];

require dirname(__DIR__) . '/checkout.php';
