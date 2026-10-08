<?php
/**
 * https://viaqui.com/<key>.txt: the IndexNow key file. Answers with the key only when the address names the key kept
 * in the secrets file (indexnow_key); anything else is a 404. See bin/ping-search.php.
 */
require_once __DIR__ . '/includes/config.php';

$asked = is_string($_GET['k'] ?? null) ? $_GET['k'] : '';
$key = (string) (($GLOBALS['viaquiSecrets'] ?? [])['indexnow_key'] ?? '');
if ($key === '' || !preg_match('/^[a-f0-9]{16,64}$/', $key) || !hash_equals($key, $asked)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
echo $key;
