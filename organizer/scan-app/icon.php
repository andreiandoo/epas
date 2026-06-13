<?php
/**
 * Scan App — dynamic PWA icon generator (PNG output via GD).
 *
 * Renders a square purple icon with a centered camera glyph at any requested
 * size (48..512). Used by the manifest + apple-touch-icon links so we don't
 * have to commit a stack of pre-rendered PNGs to the repo.
 *
 * Routes:
 *   /organizator/scan/icon.php?size=180  → 180×180 PNG (apple-touch-icon)
 *   /organizator/scan/icon.php?size=192  → 192×192 PNG (manifest icon)
 *   /organizator/scan/icon.php?size=512  → 512×512 PNG (manifest icon)
 */

if (!extension_loaded('gd')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'GD extension is not available on this server. Icon cannot be generated.';
    exit;
}

$size = isset($_GET['size']) ? (int) $_GET['size'] : 180;
$size = max(48, min(1024, $size));
$maskable = isset($_GET['maskable']) && $_GET['maskable'] === '1';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000, immutable');

$img = imagecreatetruecolor($size, $size);
imagesavealpha($img, true);

$bg     = imagecolorallocate($img, 139, 92, 246);   // primary purple
$white  = imagecolorallocate($img, 255, 255, 255);
$accent = imagecolorallocate($img, 124, 58, 237);   // primary dark

if ($maskable) {
    // Maskable icons need full-bleed safe area; lay the glyph in the central 80%.
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
} else {
    // Rounded-rect mask for non-maskable use.
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
}

// Camera body
$pad = (int) ($size * 0.20);
$y1  = (int) ($size * 0.34);
$y2  = (int) ($size * 0.78);

imagefilledrectangle($img, $pad, $y1, $size - $pad, $y2, $white);

// Inner viewfinder background (slight inset)
$inset = max(2, (int) ($size * 0.012));
imagefilledrectangle($img, $pad + $inset, $y1 + $inset, $size - $pad - $inset, $y2 - $inset, $accent);

// Lens (white outer, purple inner)
$cx = (int) ($size / 2);
$cy = (int) (($y1 + $y2) / 2);
$rOuter = (int) (($y2 - $y1) * 0.42);
$rInner = (int) ($rOuter * 0.62);

imagefilledellipse($img, $cx, $cy, $rOuter * 2, $rOuter * 2, $white);
imagefilledellipse($img, $cx, $cy, $rInner * 2, $rInner * 2, $bg);

// Tiny shutter highlight
$hr = max(2, (int) ($rInner * 0.30));
imagefilledellipse($img, $cx - $rInner / 2, $cy - $rInner / 2, $hr * 2, $hr * 2, $white);

imagepng($img);
imagedestroy($img);
