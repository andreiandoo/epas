<?php
/**
 * Checks a "back to the operator's site" token (includes/embed-return.php) for the thank-you page.
 * GET ?t=<token> → {"ok": true, "url": "https://site.ro/pagina", "name": "site.ro"} or {"ok": false}.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/embed-return.php';

$token = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
$ret = $token !== '' ? bo_return_verify($token) : null;

echo json_encode($ret ? ['ok' => true] + $ret : ['ok' => false], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
