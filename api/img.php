<?php
/**
 * Thumbnails for catalogue images.
 *
 *   /api/img.php?u=<absolute core storage URL>&w=480&h=360
 *
 * The attraction covers come off the core storage at whatever size they were uploaded — several
 * are over a megabyte, one is 2.6 MB — and the cards that show them are 230 to 480 pixels wide.
 * A grid of thirty-six of those is thirty megabytes of JPEG and a browser that stutters on every
 * scroll while it decodes them. This resizes once, keeps the result on disk, and serves it with a
 * year-long cache; the encoded file is typically 3% of the original.
 *
 * It only ever touches the core's own storage URLs (no open proxy), and if anything at all goes
 * wrong — no GD, a download that fails, an unreadable image — it redirects to the original rather
 * than showing a hole.
 */

require_once dirname(__DIR__) . '/includes/config.php';

const IMG_SIZES = [160, 240, 320, 480, 640, 960];   // a fixed ladder, so the cache cannot be flooded
// Bytes are a poor guard — what costs memory is pixels, and GD needs about 4 bytes each. The
// catalogue has an 18 MB JPEG in it, which is worth resizing precisely because it is 18 MB.
const IMG_MAX_BYTES = 40 * 1024 * 1024;
const IMG_MAX_PIXELS = 60000000;
const IMG_CACHE_DAYS = 120;

$src = (string) ($_GET['u'] ?? '');
$w = (int) ($_GET['w'] ?? 480);
$h = (int) ($_GET['h'] ?? 0);

/** Hand the browser the original and stop. Any failure path ends here. */
function img_passthrough(string $url): void
{
    if ($url === '') {
        http_response_code(404);
        exit;
    }
    header('Location: ' . $url, true, 302);
    header('Cache-Control: public, max-age=3600');
    exit;
}

// ------------------------------------------------------------------ input
if (!preg_match('#^https?://#i', $src)) {
    img_passthrough('');
}
$allowed = rtrim(STORAGE_URL, '/') . '/';
if (strncmp($src, $allowed, strlen($allowed)) !== 0) {
    // Not ours to resize; sending it back untouched is safer than fetching arbitrary URLs.
    img_passthrough($src);
}
if (!in_array($w, IMG_SIZES, true)) {
    $w = 480;
}
$h = ($h > 0 && $h <= 1200) ? $h : 0;

if (!function_exists('imagecreatetruecolor')) {
    img_passthrough($src);
}

// ------------------------------------------------------------------ cache
$webp = function_exists('imagewebp') && str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'image/webp');
$ext = $webp ? 'webp' : 'jpg';
$key = sha1($src . '|' . $w . '|' . $h) . '.' . $ext;
$dir = dirname(__DIR__) . '/cache/img';
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    img_passthrough($src);
}
$file = $dir . '/' . $key;

function img_send(string $file, string $ext): void
{
    header('Content-Type: ' . ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

if (is_file($file) && filesize($file) > 0 && filemtime($file) > time() - IMG_CACHE_DAYS * 86400) {
    img_send($file, $ext);
}

// ------------------------------------------------------------------ fetch
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $src,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 2,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || !is_string($body) || $body === '' || strlen($body) > IMG_MAX_BYTES) {
    img_passthrough($src);
}

$size = @getimagesizefromstring($body);
if (!$size || ($size[0] * $size[1]) > IMG_MAX_PIXELS) {
    img_passthrough($src);
}
// Decoding needs roughly four bytes a pixel, plus the copy we resize into.
@ini_set('memory_limit', max(256, (int) ceil($size[0] * $size[1] * 9 / 1048576)) . 'M');

$img = @imagecreatefromstring($body);
if (!$img) {
    img_passthrough($src);
}
unset($body);

// ------------------------------------------------------------------ resize
$sw = imagesx($img);
$sh = imagesy($img);
if ($sw < 1 || $sh < 1) {
    imagedestroy($img);
    img_passthrough($src);
}

if ($h > 0) {
    // Cover: fill the box and crop the overflow, so a grid of cards stays a grid.
    $scale = max($w / $sw, $h / $sh);
    $tw = $w;
    $th = $h;
    $cw = (int) round($w / $scale);
    $chh = (int) round($h / $scale);
    $cx = (int) round(($sw - $cw) / 2);
    $cy = (int) round(($sh - $chh) / 2);
} else {
    // Just narrow it; never enlarge something that is already smaller.
    $tw = min($w, $sw);
    $th = (int) round($sh * ($tw / $sw));
    $cw = $sw;
    $chh = $sh;
    $cx = 0;
    $cy = 0;
}

$out = imagecreatetruecolor($tw, max(1, $th));
imagealphablending($out, false);
imagesavealpha($out, true);
imagecopyresampled($out, $img, 0, 0, $cx, $cy, $tw, max(1, $th), $cw, $chh);
imagedestroy($img);

$ok = $webp ? @imagewebp($out, $file, 82) : @imagejpeg($out, $file, 82);
imagedestroy($out);

if (!$ok || !is_file($file)) {
    img_passthrough($src);
}
img_send($file, $ext);
