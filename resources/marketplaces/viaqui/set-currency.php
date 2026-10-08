<?php
/**
 * /currency — the visitor chooses the currency prices are shown in.
 *
 *   /currency?c=GBP&back=/rome      every price on the site in pounds
 *   /currency?c=local&back=/rome    back to each place's own currency
 *
 * Sets (or clears) the cookie read by includes/v2/currency.php and returns to the page. `back` must be a path on
 * this site; anything else goes to the homepage.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/v2/currency.php';

$curCode = is_string($_GET['c'] ?? null) ? strtoupper($_GET['c']) : '';
$curBack = is_string($_GET['back'] ?? null) ? $_GET['back'] : '/';
// a local path only: one leading slash, no scheme, no host, no backslashes, no line breaks
if (!preg_match('#^/(?![/\\\\])[^\\\\\r\n]{0,500}$#', $curBack)) {
    $curBack = '/';
}

$curOptions = ['path' => '/', 'secure' => true, 'httponly' => false, 'samesite' => 'Lax'];
if (isset(V2_CURRENCIES[$curCode])) {
    setcookie(V2_CURRENCY_COOKIE, $curCode, ['expires' => time() + 365 * 86400] + $curOptions);
} else {
    setcookie(V2_CURRENCY_COOKIE, '', ['expires' => time() - 3600] + $curOptions);
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Location: ' . $curBack, true, 302);
