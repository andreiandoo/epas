<?php
/**
 * Smart EPK Public Page — Reverse Proxy
 *
 * Pagina publică EPK e generată de Laravel pe core.tixello.com (Blade view
 * cu OG tags, Schema.org, fonts Tailwind via CDN). Aici facem reverse proxy
 * astfel încât URL-ul rămâne ambilet.ro/epk/{slug} dar conținutul vine din
 * Laravel.
 *
 * URL-uri suportate (mapate prin .htaccess):
 *   /epk/{artist_slug}                  → varianta activă
 *   /epk/{artist_slug}/{variant_slug}   → variantă specifică
 *
 * Returnează:
 *   - 200 cu HTML complet (re-injectat marketplace_name = ambilet)
 *   - 404 dacă upstream zice 404 (artist inexistent sau Extended Artist inactiv)
 *   - 502 dacă upstream e nereachable
 *
 * Pentru rider download (URL semnat) NU folosim acest proxy — link-ul ajunge
 * direct la core.tixello.com (e generat de Laravel cu signature criptografic).
 */

require_once __DIR__ . '/../includes/config.php';

$artistSlug = isset($_GET['artist_slug']) ? trim((string) $_GET['artist_slug']) : '';
$variantSlug = isset($_GET['variant_slug']) ? trim((string) $_GET['variant_slug']) : '';

if (!preg_match('/^[a-z0-9-]+$/i', $artistSlug)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>404 — EPK inexistent</h1>';
    exit;
}

$path = '/epk/' . urlencode($artistSlug);
if ($variantSlug !== '') {
    if (!preg_match('/^[a-z0-9-]+$/i', $variantSlug)) {
        http_response_code(404);
        echo '<h1>404 — variantă inexistentă</h1>';
        exit;
    }
    $path .= '/' . urlencode($variantSlug);
}

$upstreamUrl = CORE_URL . $path;

$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml',
        'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'ambilet.ro'),
        'X-Forwarded-Proto: https',
        'User-Agent: Ambilet EPK Proxy/1.0',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=UTF-8';
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>502 — EPK temporarily unavailable</h1>';
    echo '<p>' . htmlspecialchars($curlError) . '</p>';
    exit;
}

// 404 from upstream → 404 here too (artist nu are EPK / Extended Artist inactiv)
if ($statusCode === 404) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>EPK inexistent</title></head><body style="font-family:sans-serif;text-align:center;padding:60px">';
    echo '<h1>404</h1><p>EPK-ul cerut nu există sau artistul nu are abonament activ.</p>';
    echo '<a href="/">← Înapoi pe ' . htmlspecialchars(SITE_NAME) . '</a></body></html>';
    exit;
}

// Forward status + content type for everything else (200, 5xx)
http_response_code($statusCode);
header('Content-Type: ' . $contentType);

// Allow caching at edge for 5min (matches Laravel cache TTL)
if ($statusCode === 200) {
    header('Cache-Control: public, max-age=300');
}

echo $response;
