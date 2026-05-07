<?php
/**
 * iCal feed thin-proxy.
 *
 * URL: /booking/ical/{token}.ics  (rewrite din .htaccess)
 * Subscribed direct de Google Calendar / Apple Calendar / Outlook — NU trec
 * prin proxy.php (care răspunde JSON). Aici facem un fetch raw la core și
 * streamăm body-ul cu Content-Type text/calendar.
 *
 * Auth la core: X-API-Key adăugat din includes/config.php.
 * Token-ul iCal e secretul în URL — verificat server-side de
 * BookingPublicController::icalFeed.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
if (!$token || strlen($token) < 10) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "NOT FOUND";
    exit;
}

$url = API_BASE_URL . '/public/booking/ical/' . $token;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: text/calendar',
        'X-API-Key: ' . API_KEY,
        'User-Agent: Ambilet Marketplace/1.0 iCal',
    ],
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false || $status >= 500) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Upstream unavailable" . ($err ? ': ' . $err : '');
    exit;
}

if ($status === 404) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "NOT FOUND";
    exit;
}

http_response_code($status ?: 200);
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="booking.ics"');
header('Cache-Control: public, max-age=900');
echo $response;
