<?php
/**
 * Back from the payment processor to the operator's site: /embed/retur (checkout inside the widget, embed code v2).
 *
 * The embedded checkout gives the processor this address as return / cancel URL; it only redirects:
 *   ?a=<allow token>&u=<operator page>&o=<order number>  paid (or pending)  →  <page>#bo-back=/embed/confirmare?…
 *   ?a=…&u=…&c=1                                          cancelled          →  <page>#bo-back=/embed/finalizare?…
 * embed/bo-widget.js on the operator's page reads #bo-back and loads that address in the widget. The operator page
 * must be on one of the sites in the signed allow token; anything else lands on bilete.online's own pages, so this
 * never redirects to an address of someone else's choosing. Processors may append their own parameters: only the
 * leading order number is kept.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/embed-return.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');

$a = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
$u = isset($_GET['u']) && is_string($_GET['u']) ? $_GET['u'] : '';
$o = isset($_GET['o']) && is_string($_GET['o']) && preg_match('/^[A-Za-z0-9-]{1,64}/', $_GET['o'], $om) ? $om[0] : '';
$cancel = !empty($_GET['c']);

$allow = $a !== '' ? bo_embed_allow_verify($a) : null;
$page = $allow && $u !== '' ? bo_embed_return_url($u, $allow) : null;
$site = rtrim(SITE_URL, '/');

if (!$page) {
    // Not an allowed operator page: finish on bilete.online.
    header('Location: ' . ($cancel || $o === '' ? $site . '/finalizare' : $site . '/multumim?order=' . rawurlencode($o)), true, 302);
    exit;
}

$back = $cancel || $o === ''
    ? '/embed/finalizare?' . http_build_query(['a' => $a, 'u' => $page, 'anulat' => 1])
    : '/embed/confirmare?' . http_build_query(['comanda' => $o, 'a' => $a]);

header('Location: ' . $page . '#bo-back=' . rawurlencode($back), true, 302);
