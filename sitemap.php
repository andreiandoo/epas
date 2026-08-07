<?php
/**
 * XML sitemaps for ambilet.ro
 *
 * Routed from .htaccess as:
 *   /sitemap.xml          -> index, lists the child sitemaps
 *   /sitemap-pages.xml    -> static pages + taxonomy landings (categories, genres, venue types)
 *   /sitemap-events.xml   -> /bilete/{slug}
 *   /sitemap-venues.xml   -> /locatie/{slug}
 *   /sitemap-artists.xml  -> /artist/{slug}
 *
 * Split by type because events churn constantly while the static pages barely
 * move. Google can then re-crawl the volatile file without re-reading the
 * stable one, and a failure while building one type can't blank the others.
 *
 * Output is cached on disk because building the events file walks the
 * paginated API — without the cache, every crawler hit would fan out into
 * dozens of upstream calls. Regenerate on demand with ?nocache=1.
 *
 * No <lastmod> on the event/venue/artist entries: the marketplace API does not
 * expose updated_at on those payloads, and a made-up timestamp is worse than
 * no timestamp — Google treats an inaccurate lastmod as a reason to distrust
 * every lastmod on the domain.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}

const SITEMAP_TTL          = 21600; // 6h
const SITEMAP_API_PER_PAGE = 100;   // API caps per_page at 100
const SITEMAP_MAX_PAGES    = 200;   // hard stop so a broken meta can't loop forever
const SITEMAP_MAX_URLS     = 50000; // sitemap protocol limit per file

$type    = strtolower((string) ($_GET['type'] ?? 'index'));
$allowed = ['index', 'pages', 'events', 'venues', 'artists'];

if (!in_array($type, $allowed, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Unknown sitemap type.\n";
    exit;
}

$cacheDir  = __DIR__ . '/cache/sitemap';
$cacheFile = $cacheDir . '/' . $type . '.xml';
$bypass    = !empty($_GET['nocache']);

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

// Serve from cache when fresh.
if (!$bypass && is_file($cacheFile) && (time() - filemtime($cacheFile)) < SITEMAP_TTL) {
    header('X-Sitemap-Cache: hit');
    readfile($cacheFile);
    exit;
}

$xml = build_sitemap($type);

// Never cache an empty body — if the API was down we want the next request to
// retry rather than serve a hollow sitemap for the next six hours. Falling back
// to a stale file beats handing Google an empty <urlset>.
if ($xml === null) {
    if (is_file($cacheFile)) {
        header('X-Sitemap-Cache: stale');
        readfile($cacheFile);
        exit;
    }
    http_response_code(503);
    header('Retry-After: 600');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
    exit;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
// Write via a temp file so a crawler never reads a half-written sitemap.
$tmp = $cacheFile . '.' . getmypid() . '.tmp';
if (@file_put_contents($tmp, $xml) !== false) {
    @rename($tmp, $cacheFile);
} else {
    @unlink($tmp);
}

header('X-Sitemap-Cache: miss');
echo $xml;
exit;


// ---------------------------------------------------------------------------

/**
 * @return string|null XML body, or null when upstream data was unavailable.
 */
function build_sitemap(string $type): ?string
{
    switch ($type) {
        case 'index':   return sitemap_index();
        case 'pages':   return sitemap_pages();
        case 'events':  return sitemap_urlset(sitemap_event_urls());
        case 'venues':  return sitemap_urlset(sitemap_venue_urls());
        case 'artists': return sitemap_urlset(sitemap_artist_urls());
    }

    return null;
}

function sitemap_url(string $path): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function sitemap_index(): string
{
    $children = ['pages', 'events', 'venues', 'artists'];
    $now      = gmdate('Y-m-d\TH:i:s\Z');

    $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($children as $child) {
        $out .= "  <sitemap>\n";
        $out .= '    <loc>' . sitemap_xml_escape(sitemap_url("sitemap-{$child}.xml")) . "</loc>\n";
        $out .= "    <lastmod>{$now}</lastmod>\n";
        $out .= "  </sitemap>\n";
    }

    return $out . '</sitemapindex>' . "\n";
}

/**
 * @param array<int, array{loc: string, lastmod?: string}> $urls
 */
function sitemap_urlset(?array $urls): ?string
{
    if ($urls === null) {
        return null;
    }

    $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach (array_slice($urls, 0, SITEMAP_MAX_URLS) as $url) {
        $out .= "  <url>\n";
        $out .= '    <loc>' . sitemap_xml_escape($url['loc']) . "</loc>\n";
        if (!empty($url['lastmod'])) {
            $out .= '    <lastmod>' . sitemap_xml_escape($url['lastmod']) . "</lastmod>\n";
        }
        $out .= "  </url>\n";
    }

    return $out . '</urlset>' . "\n";
}

/**
 * Static pages plus the taxonomy landings.
 *
 * Deliberately excluded: cart/checkout, auth, /cont/*, /organizator/*,
 * /artist/cont/*, /embed/*, /plata/*, /view/* and /cauta — private,
 * transactional, or thin duplicate-content pages that should never be indexed.
 */
function sitemap_pages(): string
{
    $paths = [
        '/',
        '/evenimente',
        '/azi',
        '/maine',
        '/weekend',
        '/calendar',
        '/evenimente-trecute',
        '/locatii',
        '/orase',
        '/artisti',
        '/organizatori',
        '/pentru-organizatori',
        '/blog',
        '/noutati',
        '/ajutor',
        '/intrebari',
        '/contact',
        '/despre',
        '/parteneri',
        '/press-kit',
        '/card-cadou',
        '/ghid-organizator',
        '/termeni',
        '/confidentialitate',
        '/cookies',
        '/gdpr',
        '/politica-retur',
        '/accesabilitate',
    ];

    // Event categories — these mirror the pretty-URL whitelist in .htaccess.
    // Hardcoded rather than pulled from the API because the rewrite rule is
    // itself a fixed list: a category the API knows about but .htaccess does
    // not would 404, and a sitemap full of 404s costs crawl budget.
    foreach ([
        'bilete-concerte', 'festivaluri', 'teatru', 'stand-up', 'evenimente-copii',
        'festival-moto', 'sport', 'comedy', 'spectacole', 'alte-evenimente',
        'muzica', 'dans', 'expozitii', 'conferinte', 'workshop',
    ] as $slug) {
        $paths[] = '/' . $slug;
    }

    // Venue types — same reasoning, fixed list in .htaccess.
    foreach (['arene-stadioane', 'teatre-sali', 'cluburi-baruri', 'open-air'] as $slug) {
        $paths[] = '/locatii/' . $slug;
    }

    // Genres are open-ended, so they do come from the API. A failure here
    // degrades to "no genre URLs" rather than killing the whole file.
    foreach (sitemap_genre_slugs() as $slug) {
        $paths[] = '/gen/' . $slug;
    }

    $urls = [];
    foreach (array_unique($paths) as $path) {
        $urls[] = ['loc' => sitemap_url($path)];
    }

    return sitemap_urlset($urls);
}

/**
 * @return array<int, string>
 */
function sitemap_genre_slugs(): array
{
    if (!function_exists('getEventGenres')) {
        require_once __DIR__ . '/includes/nav-cache.php';
    }

    try {
        $genres = getEventGenres();
    } catch (\Throwable $e) {
        error_log('Sitemap: genre lookup failed: ' . $e->getMessage());
        return [];
    }

    $slugs = [];
    foreach ((array) $genres as $genre) {
        $slug = is_array($genre) ? ($genre['slug'] ?? null) : null;
        if (is_string($slug) && preg_match('/^[a-z0-9-]+$/', $slug)) {
            $slugs[] = $slug;
        }
    }

    return array_values(array_unique($slugs));
}

/**
 * @return array<int, array{loc: string}>|null
 */
function sitemap_event_urls(): ?array
{
    // paging=flat opts out of the month-grouped "smart" pagination, which does
    // not honour per_page strictly — we need a predictable page size to walk
    // the whole collection exactly once.
    return sitemap_collect('/events', '/bilete/', [
        'paging' => 'flat',
        'sort'   => 'date_asc',
    ]);
}

/**
 * @return array<int, array{loc: string}>|null
 */
function sitemap_venue_urls(): ?array
{
    return sitemap_collect('/venues', '/locatie/');
}

/**
 * @return array<int, array{loc: string}>|null
 */
function sitemap_artist_urls(): ?array
{
    return sitemap_collect('/artists', '/artist/');
}

/**
 * Walk a paginated marketplace endpoint and turn every row's slug into a URL.
 *
 * Returns null when the very first page fails, so the caller can serve the
 * previous cached file instead of publishing an empty sitemap. A failure on a
 * later page keeps whatever was already collected — a partial sitemap is still
 * useful, an empty one is not.
 *
 * @return array<int, array{loc: string}>|null
 */
function sitemap_collect(string $endpoint, string $pathPrefix, array $extraParams = []): ?array
{
    $urls = [];
    $seen = [];

    for ($page = 1; $page <= SITEMAP_MAX_PAGES; $page++) {
        $response = api_get($endpoint, $extraParams + [
            'per_page' => SITEMAP_API_PER_PAGE,
            'page'     => $page,
        ]);

        if (empty($response['success'])) {
            error_log("Sitemap: {$endpoint} page {$page} failed: " . ($response['error'] ?? 'unknown'));
            return $page === 1 ? null : $urls;
        }

        $rows = $response['data'] ?? [];
        // Some endpoints nest the collection one level deeper.
        if (!array_is_list_compat($rows)) {
            $rows = $rows['data'] ?? $rows['items'] ?? [];
        }
        if (!is_array($rows) || $rows === []) {
            break;
        }

        foreach ($rows as $row) {
            $slug = is_array($row) ? ($row['slug'] ?? null) : null;
            if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/', $slug) || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $urls[] = ['loc' => sitemap_url($pathPrefix . $slug)];
        }

        if (count($urls) >= SITEMAP_MAX_URLS) {
            break;
        }

        // Prefer the paginator's own answer; fall back to a short page meaning
        // "last page" when the endpoint returns no meta at all.
        $lastPage = $response['meta']['last_page'] ?? null;
        if (is_numeric($lastPage)) {
            if ($page >= (int) $lastPage) {
                break;
            }
        } elseif (count($rows) < SITEMAP_API_PER_PAGE) {
            break;
        }
    }

    return $urls;
}

/**
 * array_is_list() shim — the marketplace runs PHP 8.2+ on ROMARG but this file
 * is also deployed to hosts still on 7.x, where the function does not exist.
 */
function array_is_list_compat($value): bool
{
    if (!is_array($value)) {
        return false;
    }
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }

    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}
