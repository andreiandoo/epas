<?php
/**
 * PWA Web App Manifest for Aplicație Scan.
 *
 * Served from /organizator/scan/manifest.webmanifest via .htaccess so the
 * service worker scope and start_url both sit under /organizator/scan/.
 */

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$manifest = [
    'name'             => 'Aplicație Scan — Ambilet',
    'short_name'       => 'Scan',
    'description'      => 'Scanare bilete și vânzare on-site pentru organizatori Ambilet.',
    'start_url'        => '/organizator/scan/panou',
    'scope'            => '/organizator/scan/',
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
        [ 'name' => 'Scanare bilete', 'url' => '/organizator/scan/scanare', 'icons' => [['src' => '/organizator/scan/icon.php?size=96', 'sizes' => '96x96', 'type' => 'image/png']] ],
        [ 'name' => 'Vânzare on-site', 'url' => '/organizator/scan/vanzare', 'icons' => [['src' => '/organizator/scan/icon.php?size=96', 'sizes' => '96x96', 'type' => 'image/png']] ],
    ],
    'prefer_related_applications' => false,
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
