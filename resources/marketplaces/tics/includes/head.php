<?php
/**
 * TICS.ro - Common head section for all pages with complete SEO
 *
 * Variables to set before including:
 * - $pageTitle (required): Page title
 * - $pageDescription (optional): Meta description
 * - $bodyClass (optional): Additional body classes
 * - $canonicalUrl (optional): Canonical URL for the page
 * - $pageImage (optional): Open Graph/Twitter image URL
 * - $pageType (optional): Page type for Schema.org (website, event, venue, artist, category)
 * - $pageData (optional): Array of structured data for the specific page type
 * - $noIndex (optional): Set to true to prevent indexing
 * - $headExtra (optional): Additional head content
 */

// Defaults
if (!isset($pageTitle)) $pageTitle = SITE_NAME;
if (!isset($pageDescription)) $pageDescription = 'Descoperă evenimente unice în România. Concerte, festivaluri, teatru, stand-up și multe altele pe TICS.ro';
if (!isset($canonicalUrl)) $canonicalUrl = SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
if (!isset($pageImage)) $pageImage = SITE_URL . '/assets/images/og-default.jpg';
if (!isset($pageType)) $pageType = 'website';

// Clean canonical URL (remove query params for non-search pages)
$canonicalUrl = strtok($canonicalUrl, '?');

// Site schema (always present)
$siteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => SITE_NAME,
    'url' => SITE_URL,
    'description' => 'Descoperă evenimente unice în România. Powered by Tixello.',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => SITE_URL . '/cauta?q={search_term_string}'
        ],
        'query-input' => 'required name=search_term_string'
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'url' => SITE_URL,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SITE_URL . '/assets/images/logo.svg'
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'email' => SUPPORT_EMAIL,
            'telephone' => SUPPORT_PHONE,
            'contactType' => 'customer service',
            'availableLanguage' => ['Romanian', 'English']
        ]
    ]
];

// Organization schema
$orgSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => SITE_NAME,
    'url' => SITE_URL,
    'logo' => SITE_URL . '/assets/images/logo.svg',
    'sameAs' => [
        'https://facebook.com/tics.ro',
        'https://instagram.com/tics.ro',
        'https://twitter.com/ticsro'
    ],
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'email' => SUPPORT_EMAIL,
        'telephone' => SUPPORT_PHONE,
        'contactType' => 'customer service'
    ]
];

// Build page-specific schema
$pageSchema = null;

if ($pageType === 'event' && isset($pageData)) {
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $pageData['name'] ?? $pageTitle,
        'description' => $pageData['description'] ?? $pageDescription,
        'url' => $canonicalUrl,
        'image' => $pageData['image'] ?? $pageImage,
        'startDate' => $pageData['startDate'] ?? null,
        'endDate' => $pageData['endDate'] ?? null,
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => [
            '@type' => 'Place',
            'name' => $pageData['venue']['name'] ?? '',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $pageData['venue']['address'] ?? '',
                'addressLocality' => $pageData['venue']['city'] ?? '',
                'addressCountry' => 'RO'
            ]
        ],
        'performer' => isset($pageData['artists']) ? array_map(function($artist) {
            return ['@type' => 'PerformingGroup', 'name' => $artist['name']];
        }, $pageData['artists']) : null,
        'organizer' => [
            '@type' => 'Organization',
            'name' => $pageData['organizer'] ?? SITE_NAME,
            'url' => SITE_URL
        ],
        'offers' => [
            '@type' => 'AggregateOffer',
            'url' => $canonicalUrl,
            'priceCurrency' => 'RON',
            'lowPrice' => $pageData['minPrice'] ?? null,
            'highPrice' => $pageData['maxPrice'] ?? null,
            'availability' => 'https://schema.org/InStock',
            'validFrom' => $pageData['saleStart'] ?? null
        ]
    ];
    $pageSchema = array_filter($pageSchema, fn($v) => $v !== null);
    if (isset($pageSchema['offers'])) {
        $pageSchema['offers'] = array_filter($pageSchema['offers'], fn($v) => $v !== null);
    }
} elseif ($pageType === 'category' && isset($pageData)) {
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageData['name'] ?? $pageTitle,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => isset($pageData['events']) ? array_map(function($event, $index) {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => SITE_URL . '/bilete/' . $event['slug']
                ];
            }, $pageData['events'], array_keys($pageData['events'])) : []
        ]
    ];
}

// Breadcrumb schema (if breadcrumbs provided)
$breadcrumbSchema = null;
if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0) {
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(function($crumb, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => isset($crumb['url']) ? SITE_URL . $crumb['url'] : null
            ];
        }, $breadcrumbs, array_keys($breadcrumbs))
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= SITE_LOCALE ?>" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Primary Meta Tags -->
    <title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
    <meta name="title" content="<?= e($pageTitle) ?> — <?= SITE_NAME ?>">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="author" content="<?= SITE_NAME ?>">
    <meta name="keywords" content="bilete, evenimente, concerte, festivaluri, teatru, stand-up, sport, romania, <?= strtolower(e($pageTitle)) ?>">
    <?php if (!empty($noIndex)): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- Language & Locale -->
    <meta property="og:locale" content="ro_RO">
    <meta name="language" content="Romanian">
    <link rel="alternate" hreflang="ro" href="<?= e($canonicalUrl) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= e($canonicalUrl) ?>">

    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="<?= $THEME['primary'] ?>">
    <meta name="msapplication-TileColor" content="<?= $THEME['primary'] ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?= $pageType === 'event' ? 'event' : 'website' ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($pageTitle) ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <?php if ($pageType === 'event' && isset($pageData['startDate'])): ?>
    <meta property="event:start_time" content="<?= e($pageData['startDate']) ?>">
    <?php if (isset($pageData['endDate'])): ?>
    <meta property="event:end_time" content="<?= e($pageData['endDate']) ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($canonicalUrl) ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($pageImage) ?>">
    <meta name="twitter:image:alt" content="<?= e($pageTitle) ?>">
    <meta name="twitter:site" content="@ticsro">
    <meta name="twitter:creator" content="@ticsro">

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json"><?= json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/ld+json"><?= json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php if ($pageSchema): ?>
    <script type="application/ld+json"><?= json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <?php if ($breadcrumbSchema): ?>
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://core.tixello.com">
    <link rel="dns-prefetch" href="https://core.tixello.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        'primary': '<?= $THEME['primary'] ?>',
                        'primary-dark': '<?= $THEME['primary_dark'] ?>',
                        'primary-light': '<?= $THEME['primary_light'] ?>',
                        'secondary': '<?= $THEME['secondary'] ?>',
                        'accent': '<?= $THEME['accent'] ?>',
                        'surface': '<?= $THEME['surface'] ?>',
                        'muted': '<?= $THEME['muted'] ?>',
                        'border': '<?= $THEME['border'] ?>',
                    }
                }
            }
        }
    </script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/custom.css') ?>">

    <!-- Accessibility CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/accessibility.css') ?>">

    <!-- Page-specific head content -->
    <?php if (isset($headExtra)) echo $headExtra; ?>

    <meta name="impact-site-verification" value="f53ff7db-e71c-4f20-9281-ead22db2992a" />

    <script data-noptimize="1" data-cfasync="false" data-wpfc-render="false">
        (function () {
            var script = document.createElement("script");
            script.async = 1;
            script.src = 'https://tpembars.com/NDk5OTAz.js?t=499903';
            document.head.appendChild(script);
        })();
    </script>
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : 'bg-gray-50' ?>">