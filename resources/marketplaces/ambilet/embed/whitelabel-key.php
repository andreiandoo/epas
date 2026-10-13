<?php
/**
 * Organizer's whitelabel API key — show it, or replace it.
 * Called from the organizer widgets page with the organizer token as Bearer.
 *
 * GET  → current key (issued on first call)
 * POST → new key; the previous one stops working immediately
 */
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (stripos($authHeader, 'Bearer ') !== 0 || strlen($authHeader) < 20) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$regenerate = $_SERVER['REQUEST_METHOD'] === 'POST';

$ch = curl_init(API_BASE_URL . '/organizer/whitelabel-key' . ($regenerate ? '/regenerate' : ''));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Key: ' . API_KEY,
        'Authorization: ' . $authHeader,
    ],
]);
if ($regenerate) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
}
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($status ?: 502);
echo $raw ?: json_encode(['success' => false, 'message' => 'Service unavailable']);
