<?php
/**
 * Shared bits for /blog and /blog/{slug}. Articles come from the admin
 * (Marketplace > Blog) through /blog-articles.
 */

const BLOG_AUTHOR = 'Redacția AmBilet';

/** Cover path from the admin (public disk) or a full URL => absolute URL, null when there is none. */
function blog_image_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return STORAGE_URL . '/' . ltrim(preg_replace('#^/?storage/#', '', $path), '/');
}

/** "2026-10-22T09:00:00Z" => "22 octombrie 2026" */
function blog_date(?string $iso): string
{
    if (!$iso || !($ts = strtotime($iso))) {
        return '';
    }
    $months = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

    return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function blog_url(string $slug): string
{
    return '/blog/' . rawurlencode($slug);
}
