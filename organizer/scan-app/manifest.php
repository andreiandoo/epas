<?php
/**
 * PWA Web App Manifest for Aplicație Scan.
 *
 * Served from /organizator/scan/manifest.webmanifest via .htaccess so the
 * service worker scope and start_url both sit under /organizator/scan/.
 */

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

// /venue/scan/manifest.webmanifest → venue app (own start_url + scope).
$isVenue = ($_GET['venue'] ?? '') === '1';
$base = $isVenue ? '/venue/scan' : '/organizator/scan';

$manifest = [
    'name'             => $isVenue ? 'Aplicație Scan Locație — Ambilet' : 'Aplicație Scan — Ambilet',
    'short_name'       => 'Scan',
    'description'      => $isVenue
        ? 'Scanare bilete și vânzare la intrare pentru locații Ambilet.'
        : 'Scanare bilete și vânzare on-site pentru organizatori Ambilet.',
    'start_url'        => $isVenue ? '/venue/scan/evenimente' : '/organizator/scan/panou',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0A0A0F',
    'theme_color'      => '#0A0A0F',
    'lang'             => 'ro-RO',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity', 'utilities'],
    'icons' => [
        [ 'src' => '/organizator/scan/icon.php?size=192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
        [ 'src' => '/organizator/scan/icon.php?size=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
        [ 'src' => '/organizator/scan/icon.php?size=192&maskable=1', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ],
        [ 'src' => '/organizator/scan/icon.php?size=512&maskable=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
    ],
    'shortcuts' => [
        [ 'name' => 'Scanare bilete', 'url' => $base . '/scanare', 'icons' => [['src' => '/organizator/scan/icon.php?size=96', 'sizes' => '96x96', 'type' => 'image/png']] ],
        [ 'name' => 'Vânzare on-site', 'url' => $base . '/vanzare', 'icons' => [['src' => '/organizator/scan/icon.php?size=96', 'sizes' => '96x96', 'type' => 'image/png']] ],
    ],
    'prefer_related_applications' => false,
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
