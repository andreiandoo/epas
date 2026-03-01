<?php
/**
 * Common head section for all pages with complete SEO
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
if (!isset($pageDescription)) $pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Cel mai mare portal de bilete din Romania.';
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
    'description' => 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele.',
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
        'https://facebook.com/ambilet',
        'https://instagram.com/ambilet',
        'https://twitter.com/ambilet'
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
    // Event schema (for event detail pages)
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
            return [
                '@type' => 'PerformingGroup',
                'name' => $artist['name']
            ];
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
    // Remove null values
    $pageSchema = array_filter($pageSchema, fn($v) => $v !== null);
    if (isset($pageSchema['offers'])) {
        $pageSchema['offers'] = array_filter($pageSchema['offers'], fn($v) => $v !== null);
    }
} elseif ($pageType === 'venue' && isset($pageData)) {
    // Venue schema
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'EventVenue',
        'name' => $pageData['name'] ?? $pageTitle,
        'description' => $pageData['description'] ?? $pageDescription,
        'url' => $canonicalUrl,
        'image' => $pageData['image'] ?? $pageImage,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $pageData['address'] ?? '',
            'addressLocality' => $pageData['city'] ?? '',
            'addressCountry' => 'RO'
        ],
        'maximumAttendeeCapacity' => $pageData['capacity'] ?? null,
        'geo' => isset($pageData['lat']) && isset($pageData['lng']) ? [
            '@type' => 'GeoCoordinates',
            'latitude' => $pageData['lat'],
            'longitude' => $pageData['lng']
        ] : null
    ];
    $pageSchema = array_filter($pageSchema, fn($v) => $v !== null);
} elseif ($pageType === 'artist' && isset($pageData)) {
    // Artist/Performer schema
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'PerformingGroup',
        'name' => $pageData['name'] ?? $pageTitle,
        'description' => $pageData['description'] ?? $pageDescription,
        'url' => $canonicalUrl,
        'image' => $pageData['image'] ?? $pageImage,
        'genre' => $pageData['genre'] ?? null
    ];
    $pageSchema = array_filter($pageSchema, fn($v) => $v !== null);
} elseif ($pageType === 'category' && isset($pageData)) {
    // Category/Collection schema
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
    <title><?= htmlspecialchars($pageTitle) ?> — <?= SITE_NAME ?></title>
    <meta name="title" content="<?= htmlspecialchars($pageTitle) ?> — <?= SITE_NAME ?>">
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="author" content="<?= SITE_NAME ?>">
    <meta name="keywords" content="bilete, evenimente, concerte, festivaluri, teatru, sport, romania, <?= strtolower(htmlspecialchars($pageTitle)) ?>">
    <?php if (!empty($noIndex)): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <!-- Language & Locale -->
    <meta property="og:locale" content="ro_RO">
    <meta name="language" content="Romanian">
    <link rel="alternate" hreflang="ro" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($canonicalUrl) ?>">

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
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <?php if ($pageType === 'event' && isset($pageData['startDate'])): ?>
    <meta property="event:start_time" content="<?= htmlspecialchars($pageData['startDate']) ?>">
    <?php if (isset($pageData['endDate'])): ?>
    <meta property="event:end_time" content="<?= htmlspecialchars($pageData['endDate']) ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:site" content="@ambilet">
    <meta name="twitter:creator" content="@ambilet">

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json"><?= json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
    <script type="application/ld+json"><?= json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
    <?php if ($pageSchema): ?>
    <script type="application/ld+json"><?= json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
    <?php endif; ?>
    <?php if ($breadcrumbSchema): ?>
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
    <?php endif; ?>

    <!-- Critical CSS (inline above-the-fold styles) -->
    <style>
    :root{--color-primary:#A51C30;--color-primary-dark:#8B1728;--color-primary-light:#C41E3A;--color-secondary:#1E293B;--color-accent:#E67E22;--color-surface:#F8FAFC;--color-muted:#64748B;--color-border:#E2E8F0;--color-success:#10B981;--color-warning:#F59E0B;--color-error:#EF4444}*,::after,::before{box-sizing:border-box;border:0 solid #e5e7eb;margin:0;padding:0}html{line-height:1.5;-webkit-text-size-adjust:100%;font-family:'Plus Jakarta Sans',sans-serif;scroll-behavior:smooth}body{margin:0;line-height:inherit;background:#fff;color:#1e293b;-webkit-font-smoothing:antialiased}img,video{max-width:100%;height:auto;display:block}a{color:inherit;text-decoration:inherit}button,[role=button]{cursor:pointer}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}ul,ol,menu{list-style:none;margin:0;padding:0}.hidden{display:none}.flex{display:flex}.block{display:block}.relative{position:relative}.absolute{position:absolute}.fixed{position:fixed}.sticky{position:sticky}.items-center{align-items:center}.justify-between{justify-content:space-between}.justify-center{justify-content:center}.gap-2{gap:.5rem}.gap-4{gap:1rem}.w-full{width:100%}.h-full{height:100%}.overflow-hidden{overflow:hidden}.bg-white{background-color:#fff}.bg-secondary{background-color:#1e293b}.text-white{color:#fff}.text-gray-900{color:#111827}.font-bold{font-weight:700}.font-semibold{font-weight:600}.font-medium{font-weight:500}.text-sm{font-size:.875rem;line-height:1.25rem}.text-xs{font-size:.75rem;line-height:1rem}.text-lg{font-size:1.125rem;line-height:1.75rem}.rounded-xl{border-radius:.75rem}.rounded-lg{border-radius:.5rem}.rounded-full{border-radius:9999px}.p-4{padding:1rem}.px-4{padding-left:1rem;padding-right:1rem}.px-5{padding-left:1.25rem;padding-right:1.25rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.py-2{padding-top:.5rem;padding-bottom:.5rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.py-4{padding-top:1rem;padding-bottom:1rem}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.mb-6{margin-bottom:1.5rem}.mt-18{margin-top:4.5rem}.mx-auto{margin-left:auto;margin-right:auto}.max-w-7xl{max-width:80rem}.border{border-width:1px}.border-b{border-bottom-width:1px}.border-border{border-color:#e2e8f0}.shadow-sm{box-shadow:0 1px 2px 0 rgb(0 0 0/.05)}.transition-all{transition-property:all;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.opacity-0{opacity:0}.z-\[1000\]{z-index:1000}.z-\[2000\]{z-index:2000}.inset-0{inset:0}.top-0{top:0}.left-0{left:0}.right-0{right:0}.object-cover{object-fit:cover}.bg-gradient-to-b{background-image:linear-gradient(to bottom,var(--tw-gradient-stops))}.text-primary{color:#a51c30}.bg-primary{background-color:#a51c30}.container{width:100%;margin-left:auto;margin-right:auto}@media(min-width:782px){.container{max-width:782px}.md\:flex{display:flex}}@media(min-width:1024px){.container{max-width:1024px}.lg\:flex{display:flex}.lg\:hidden{display:none}}@media(min-width:1280px){.container{max-width:1280px}}@media(min-width:1440px){.container{max-width:1440px}}@media(max-width:768px){.mobile\:hidden{display:none}.mobile\:px-4{padding-left:1rem;padding-right:1rem}}
    .site-header{position:fixed;top:0;left:0;right:0;z-index:1000;transition:all .3s ease}.site-header.scrolled{background:rgba(255,255,255,.98);backdrop-filter:blur(12px);box-shadow:0 1px 3px rgba(0,0,0,.08)}.mega-menu{position:absolute;top:100%;left:50%;transform:translateX(-50%);opacity:0;visibility:hidden;pointer-events:none;transition:all .2s ease}.nav-item:hover .mega-menu{opacity:1;visibility:visible;pointer-events:auto}.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;font-weight:600;border-radius:.75rem;transition:all .2s ease;cursor:pointer;border:none;text-decoration:none}.btn-primary{background:linear-gradient(135deg,#a51c30,#c41e3a);color:#fff;padding:.625rem 1.25rem;font-size:.875rem}.btn-primary:hover{background:linear-gradient(135deg,#8b1728,#a51c30);box-shadow:0 4px 12px rgba(165,28,48,.3)}
    </style>
    <!-- Full CSS (async, non-render-blocking) -->
    <link rel="preload" href="<?= asset('assets/css/custom.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="<?= asset('assets/css/tailwind.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= asset('assets/css/custom.css') ?>">
        <link rel="stylesheet" href="<?= asset('assets/css/tailwind.min.css') ?>">
    </noscript>

    <!-- Page-specific preloads (LCP image, etc.) -->
    <?php if (!empty($extraHead)) echo $extraHead . "\n"; ?>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://core.tixello.com">
    <link rel="dns-prefetch" href="https://core.tixello.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//images.unsplash.com">

    <!-- Fonts (non-render-blocking: preload + print swap) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>

    <!-- Google Consent Mode v2 — MUST be before any tracking scripts -->
    <script>
    window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
    (function(){
        var c=null;
        try{c=JSON.parse(localStorage.getItem('ambilet_cookie_consent'))}catch(e){}
        var m=c&&c.marketing,a=c&&c.analytics,f=c&&c.functional;
        gtag('consent','default',{
            'ad_storage':m?'granted':'denied',
            'ad_user_data':m?'granted':'denied',
            'ad_personalization':m?'granted':'denied',
            'analytics_storage':a?'granted':'denied',
            'functionality_storage':f?'granted':'denied',
            'personalization_storage':f?'granted':'denied',
            'security_storage':'granted',
            'wait_for_update':c?0:500
        });
        gtag('set','ads_data_redaction',true);
        gtag('set','url_passthrough',true);
    })();
    </script>

    <!-- Tracking Scripts (head) — deferred until user interaction or 5s (idle-aware) -->
    <?php
    if (!isset($trackingHeadScripts)) {
        require_once __DIR__ . '/tracking.php';
    }
    if (!empty($trackingHeadScripts)): ?>
    <script>
    (function(){
        var h=<?= json_encode($trackingHeadScripts) ?>;
        var done=false;
        function go(){
            if(done)return;done=true;
            var d=document.createElement('div');d.innerHTML=h;
            d.querySelectorAll('script').forEach(function(s){
                var n=document.createElement('script');
                if(s.src){n.src=s.src;n.async=true}else{n.textContent=s.textContent}
                document.head.appendChild(n);
            });
        }
        ['scroll','click','touchstart','mousemove','keydown'].forEach(function(e){
            window.addEventListener(e,go,{once:true,passive:true});
        });
        if('requestIdleCallback' in window){requestIdleCallback(function(){setTimeout(go,2500)});}
        else{setTimeout(go,5000);}
    })();
    </script>
    <?php endif; ?>

    <!-- Page-specific head content -->
    <?php if (isset($headExtra)) echo $headExtra; ?>
</head>
<body class="bg-slate-100 <?= isset($bodyClass) ? htmlspecialchars($bodyClass) : 'bg-white' ?>">
