<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Full-page HTML cache: serves cached HTML on hit (zero API calls)
$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

$eventSlug = $_GET['slug'] ?? '';
$pageTitle = 'Eveniment';
$pageDescription = 'Detalii eveniment si cumparare bilete';
$bodyClass = 'bg-surface';

// Preview mode: prevent browser from caching the page itself
// AND invalidate all server caches so the next public visit gets fresh data
if (!empty($_GET['preview'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($eventSlug) {
        // 1. Invalidate full-page HTML cache for the public (non-preview) URL
        $publicUri = '/bilete/' . $eventSlug;
        $pageCacheDir = __DIR__ . '/includes/cache/pages';
        $publicCacheFile = $pageCacheDir . '/' . md5($publicUri) . '.html';
        if (file_exists($publicCacheFile)) @unlink($publicCacheFile);

        // 2. Invalidate application-level api_cached() entry
        $appCacheDir = sys_get_temp_dir() . '/ambilet_cache';
        $appCacheFile = $appCacheDir . '/' . md5('event_preload_' . $eventSlug) . '.json';
        if (file_exists($appCacheFile)) @unlink($appCacheFile);

        // 3. Invalidate proxy API cache for single event endpoint
        //    The proxy uses action='event' with empty $params (slug is in the URL, not params)
        //    so cache key = 'event_' + md5(serialize([])) — shared by all events (10s TTL anyway)
        $apiCacheDir = __DIR__ . '/cache/api';
        if (is_dir($apiCacheDir)) {
            $apiCacheKey = 'event_' . md5(serialize([]));
            $apiCacheFile = $apiCacheDir . '/' . $apiCacheKey . '.json';
            if (file_exists($apiCacheFile)) @unlink($apiCacheFile);
        }
    }
}

// Server-side: fetch event data for LCP image preload and SEO meta
$eventPreload = null;
$isPreview = !empty($_GET['preview']);
if ($eventSlug) {
    if ($isPreview) {
        // Preview mode: always fetch fresh data, bypass cache
        $eventPreload = api_get('/events/' . urlencode($eventSlug));
    } else {
        $eventPreload = api_cached('event_preload_' . $eventSlug, function () use ($eventSlug) {
            return api_get('/events/' . urlencode($eventSlug));
        }, 1800); // 30min cache (stale-while-revalidate extends to ~2.5h)
    }
    // API returns: { data: { event: {...}, venue: {...}, ... } }
    $ev = $eventPreload['data']['event'] ?? null;
    if ($ev) {
        $pageTitle = $ev['name'] ?? $ev['title'] ?? $pageTitle;
        $pageDescription = !empty($ev['short_description']) ? mb_substr(strip_tags($ev['short_description']), 0, 160) : $pageDescription;
    }
}

// DEBUG: temporary - remove after testing
if (!empty($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "eventSlug: " . ($eventSlug ?? 'NULL') . "\n";
    echo "isPreview: " . ($isPreview ? 'true' : 'false') . "\n";
    echo "eventPreload success: " . ($eventPreload['success'] ?? 'NULL') . "\n";
    echo "ev is null: " . ($ev === null ? 'YES' : 'NO') . "\n";
    echo "display_template: " . ($ev['display_template'] ?? 'NOT SET') . "\n";
    echo "api_get result keys: " . implode(', ', array_keys($eventPreload ?? [])) . "\n";
    echo "data keys: " . implode(', ', array_keys($eventPreload['data'] ?? [])) . "\n";
    exit;
}

// External redirect: if event has a redirect_url, send 302 and exit
$redirectUrl = $ev['redirect_url'] ?? null;
if ($redirectUrl && !$isPreview) {
    header('Location: ' . $redirectUrl, true, 302);
    exit;
}

// Leisure venue: delegate to custom template
$displayTemplate = $ev['display_template'] ?? 'standard';
if ($displayTemplate === 'leisure_venue') {
    include __DIR__ . '/leisure-venue.php';
    return;
}

// Build LCP image URLs for preload (hero for desktop, poster for mobile)
$lcpHeroUrl = '';
$lcpPosterUrl = '';
if (!empty($ev)) {
    $heroRaw = $ev['hero_image_url'] ?? $ev['cover_image_url'] ?? $ev['image_url'] ?? $ev['image'] ?? '';
    $posterRaw = $ev['poster_url'] ?? $ev['poster_image_url'] ?? '';
    if ($heroRaw) {
        $lcpHeroUrl = (str_starts_with($heroRaw, 'http') ? '' : STORAGE_URL . '/') . $heroRaw;
    }
    if ($posterRaw) {
        $lcpPosterUrl = (str_starts_with($posterRaw, 'http') ? '' : STORAGE_URL . '/') . $posterRaw;
    }
}
// Fallback: if only one exists, use it for both
$lcpImageUrl = $lcpHeroUrl ?: $lcpPosterUrl;
if (!$lcpPosterUrl) $lcpPosterUrl = $lcpHeroUrl;
if (!$lcpHeroUrl) $lcpHeroUrl = $lcpPosterUrl;

// Extra head tags: responsive preload (poster on mobile, hero on desktop)
$extraHead = '';
if ($lcpPosterUrl && $lcpHeroUrl && $lcpPosterUrl !== $lcpHeroUrl) {
    $extraHead = '<link rel="preload" as="image" href="' . htmlspecialchars($lcpPosterUrl) . '" media="(max-width: 767px)" fetchpriority="high">'
        . "\n    " . '<link rel="preload" as="image" href="' . htmlspecialchars($lcpHeroUrl) . '" media="(min-width: 768px)" fetchpriority="high">';
} elseif ($lcpImageUrl) {
    $extraHead = '<link rel="preload" as="image" href="' . htmlspecialchars($lcpImageUrl) . '" fetchpriority="high">';
}

// Inject server-fetched data so JS can render immediately (no API roundtrip)
if (!empty($eventPreload['data'])) {
    $headExtra = '<script>window.__EVENT_PRELOAD__=' . json_encode($eventPreload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
}

// Check if event is password protected
$isPasswordProtected = !empty($ev['is_password_protected']);

// Venue info for redirect modal
$venue = $eventPreload['data']['venue'] ?? null;
$venueName = $venue['name'] ?? '';
if (is_array($venueName)) $venueName = $venueName['ro'] ?? $venueName['en'] ?? reset($venueName) ?: '';
$venueCity = $venue['city'] ?? '';
$eventDate = '';
if (!empty($ev['event_date'])) {
    $eventDate = date('d.m.Y', strtotime($ev['event_date']));
    if (!empty($ev['start_time'])) $eventDate .= ', ora ' . $ev['start_time'];
} elseif (!empty($ev['starts_at'])) {
    $eventDate = date('d.m.Y, H:i', strtotime($ev['starts_at']));
}
$ambiletUrl = 'https://ambilet.ro/bilete/' . urlencode($eventSlug);

$cssBundle = 'event';
require_once __DIR__ . '/includes/head.php';
?>
    <style>
        .date-badge { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); }
        .btn-primary { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(165, 28, 48, 0.3); }

        .ticket-card { transition: all 0.3s ease; }
        .ticket-card:hover { border-color: #A51C30; }
        .cursor-default.ticket-card:hover { border-color: #e5e7eb; }
        .ticket-card.selected { border-color: #A51C30; background-color: rgba(165, 28, 48, 0.05); }

        .tooltip {
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            transform: translateY(5px);
        }
        .tooltip-trigger:hover .tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .event-card { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        .event-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(165, 28, 48, 0.2); }
        .event-card:hover .event-image { transform: scale(1.08); }
        .event-image { transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }

        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* Prevent event description from overflowing on mobile */
        #event-description { overflow-x: hidden; overflow-wrap: break-word; word-break: break-word; }
        #event-description img, #event-description iframe, #event-description video, #event-description table, #event-description pre {
            max-width: 100%; height: auto;
        }
        #event-description table { display: block; overflow-x: auto; }

        /* Collapsible description */
        #event-description.is-collapsed { max-height: 300px; overflow: hidden; position: relative; }
        #event-description.is-collapsed::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0; height: 80px;
            background: linear-gradient(to bottom, rgba(255,255,255,0) 0%, rgba(255,255,255,1) 100%);
            pointer-events: none;
        }
        #event-description.is-expanded { max-height: none; }
        #desc-toggle { transition: opacity 0.3s ease; }
        #desc-toggle svg { transition: transform 0.3s ease; }
        #desc-toggle.is-expanded svg { transform: rotate(180deg); }

        .points-counter { animation: pointsPulse 0.3s ease; }
        @keyframes pointsPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }

        .sticky-cart { position: sticky; top: 88px; }

        /* Mobile ticket drawer */
        @media (max-width: 1023px) {
            .sticky-cart-wrapper { display: none; }
        }
        #ticketDrawerBackdrop {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        #ticketDrawerBackdrop.open {
            opacity: 1;
            visibility: visible;
        }
        #ticketDrawer {
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #ticketDrawer.open {
            transform: translateY(0);
        }

        /* Related events horizontal scroll on mobile */
        @media (max-width: 1023px) {
            .related-events-scroll {
                padding-left: 1rem;
                padding-right: 1rem;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            .related-events-scroll::-webkit-scrollbar {
                display: none;
            }
            .related-events-scroll > * {
                flex: 0 0 70%;
                min-width: 260px;
                max-width: 300px;
                scroll-snap-align: start;
            }
            .related-events-scroll > *:first-child {
                margin-left: 0;
            }
            .related-events-scroll > *:last-child {
                margin-right: 1rem;
            }
        }
    </style>

<?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="bg-white border-b border-border mt-17 mobile:hidden">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <nav class="flex items-center gap-2 text-sm" id="breadcrumb">
                <a href="/" class="transition-colors text-muted hover:text-primary">Acasă</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-medium text-secondary" id="breadcrumb-title"><?= (!empty($pageTitle) && $pageTitle !== 'Eveniment') ? htmlspecialchars($pageTitle) : 'Se încarcă...' ?></span>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl mobile:p-0 mobile:mt-18" id="main-content">
        <!-- Loading State -->
        <div id="loading-state" class="flex flex-col gap-8 lg:flex-row">
            <div class="lg:w-2/3">
                <div class="mb-8 overflow-hidden bg-white border rounded-3xl border-border">
                    <?php if ($lcpPosterUrl && $lcpHeroUrl && $lcpPosterUrl !== $lcpHeroUrl): ?>
                    <div class="relative overflow-hidden rounded-t-3xl" style="aspect-ratio: 1.904/1;">
                        <picture>
                            <source srcset="<?= htmlspecialchars($lcpPosterUrl) ?>" media="(max-width: 767px)">
                            <img src="<?= htmlspecialchars($lcpHeroUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="object-cover w-full h-full" fetchpriority="high" width="800" height="420">
                        </picture>
                    </div>
                    <?php elseif ($lcpImageUrl): ?>
                    <div class="relative overflow-hidden rounded-t-3xl" style="aspect-ratio: 1.904/1;">
                        <img src="<?= htmlspecialchars($lcpImageUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="object-cover w-full h-full" fetchpriority="high" width="800" height="420">
                    </div>
                    <?php else: ?>
                    <div class="bg-gray-200 animate-pulse h-72 md:h-[29rem]"></div>
                    <?php endif; ?>
                    <div class="p-6 md:p-8">
                        <?php if (!empty($pageTitle) && $pageTitle !== 'Eveniment'): ?>
                        <h1 class="mb-4 text-2xl font-bold md:text-3xl text-secondary"><?= htmlspecialchars($pageTitle) ?></h1>
                        <?php else: ?>
                        <div class="w-3/4 h-10 mb-4 bg-gray-200 rounded animate-pulse"></div>
                        <?php endif; ?>
                        <div class="grid gap-4 mb-6 sm:grid-cols-2">
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:w-1/3">
                <div class="bg-gray-100 animate-pulse h-96 rounded-3xl"></div>
            </div>
        </div>

        <!-- Event Content (hidden until loaded) -->
        <div id="event-content" class="flex flex-col hidden gap-8 lg:flex-row">
            <!-- Left Column - Event Details -->
            <div class="lg:w-2/3">
                <!-- Event Header -->
                <div class="mb-8 bg-white border rounded-3xl border-border mobile:border-0 mobile:border-b mobile:rounded-none">
                    <!-- Main Image -->
                    <div class="relative overflow-hidden rounded-t-3xl mobile:rounded-none" style="aspect-ratio: 1.904/1;">
                        <img id="mainImage" src="<?= htmlspecialchars($lcpImageUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="object-cover w-full h-full" fetchpriority="high" width="800" height="420">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="absolute flex gap-2 top-4 left-4" id="event-badges"></div>
                    </div>

                    <!-- Event Info -->
                    <div class="px-0 py-6 mobile:px-0">
                        <h1 id="event-title" class="px-6 mb-4 text-2xl font-extrabold md:text-4xl text-secondary mobile:px-4"></h1>

                        <!-- Key Details -->
                        <div class="grid grid-cols-1 gap-4 px-6 mb-6 mobile:px-4 md:grid-cols-2">
                            <div class="flex items-center gap-3 p-4 bg-surface rounded-xl mobile:rounded-none mobile:p-0 mobile:bg-transparent">
                                <div class="flex-shrink-0 px-3 py-2 text-center text-white date-badge rounded-xl">
                                    <span id="event-day" class="block text-xl font-bold leading-none">--</span>
                                    <span id="event-month" class="block text-[10px] uppercase tracking-wide mt-0.5">---</span>
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <p id="event-weekday" class="font-semibold text-secondary"></p>
                                        <p id="event-date-full" class="font-semibold text-secondary"></p>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm tracking-tight">
                                        <p id="event-time" class="font-semibold text-secondary"></p>
                                        <p id="event-doors" class="font-semibold text-secondary"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Location -->
                            <div id="venue-short-display" class="flex items-center gap-3 p-4 bg-surface rounded-xl mobile:rounded-none mobile:p-0 mobile:bg-transparent">
                                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p id="venue-name" class="font-semibold text-secondary"></p>
                                    <p id="venue-address" class="text-sm text-muted"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Social Stats -->
                        <div id="social-stats" class="flex flex-wrap items-center gap-4 px-6 mb-8 mobile:justify-between mobile:border-t mobile:border-b border-slate-200 mobile:p-4">
                            <!-- Interested Button -->
                            <button id="interest-btn" onclick="EventPage.toggleInterest()" class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition-all rounded-full border border-border hover:border-primary hover:text-primary" aria-label="Arată interes pentru acest eveniment">
                                <svg id="interest-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span id="event-interested">Mă interesează</span>
                            </button>
                            <!-- Views -->
                            <span class="flex items-center gap-2 text-sm text-muted">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span id="event-views">0 vizualizări</span>
                            </span>
                            <!-- Share Dropdown -->
                            <div class="relative" id="share-dropdown">
                                <button onclick="EventPage.toggleShareMenu()" class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition-colors rounded-full border border-border hover:border-primary hover:text-primary" aria-label="Distribuie acest eveniment">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <span class="mobile:hidden">Distribuie</span>
                                </button>
                                <div id="share-menu" class="absolute right-0 z-50 hidden w-48 py-2 mt-2 bg-white border shadow-lg rounded-xl border-border">
                                    <a href="#" onclick="EventPage.shareOn('facebook'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-[#1877F2]" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                                        Facebook
                                    </a>
                                    <a href="#" onclick="EventPage.shareOn('whatsapp'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        WhatsApp
                                    </a>
                                    <a href="#" onclick="EventPage.shareOn('email'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Email
                                    </a>
                                    <a href="#" onclick="EventPage.copyLink(); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        Copiază link
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div id="event-description" class="px-6 prose prose-slate max-w-none mobile:px-4"></div>
                        <button id="desc-toggle" style="display:none" class="flex items-center gap-2 px-6 pb-4 mx-auto text-sm font-medium transition-colors text-primary hover:text-primary/80 mobile:px-4">
                            <span id="desc-toggle-text">Vezi mai mult</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Tour Events Section -->
                <section id="tour-events-section" style="display:none;" class="mb-8 mobile:mb-0 mobile:border-b mobile:border-border">
                    <div class="overflow-hidden bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <span class="flex items-center justify-center w-9 h-9 rounded-xl" style="background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </span>
                            <div>
                                <h2 id="tour-section-title" class="text-lg font-bold text-secondary">Alte date din serie</h2>
                                <p class="text-sm text-muted"><span id="tour-name-display"></span><span id="tour-name-fallback">Evenimentul face parte dintr-o serie. Alege și alte date.</span></p>
                            </div>
                        </div>
                        <div id="tour-events-list" class="px-2 py-2 divide-y divide-gray-50">
                            <!-- Loaded dynamically by JS -->
                        </div>
                    </div>
                </section>

                <!-- Artist Section -->
                <div class="px-8 mb-8 mobile:mb-0 mobile:p-0" id="artist-section" style="display:none;">
                    <div id="artist-content" class="mobile:p-4"></div>
                </div>

                <!-- Venue Section -->
                <div class="mb-8 mobile:mb-0 mobile:border-b mobile:border-border" id="venue">
                    <div id="venue-content" class="mobile:p-4"></div>
                </div>
            </div>

            <!-- Right Column - Ticket Selection (Hidden on mobile, shown in drawer) -->
            <div class="lg:w-1/3 sticky-cart-wrapper">
                <div class="sticky-cart">
                    <div class="rounded-3xl ">
                        <div class="hidden p-6 border-b border-border">
                            <h2 class="mb-2 text-xl font-bold text-secondary">Selectează bilete</h2>
                            <p class="text-sm text-muted">Alege tipul de bilet și cantitatea</p>
                        </div>

                        <!-- Ticket Types -->
                        <div class="space-y-3" id="ticket-types"></div>

                        <!-- Cart Summary -->
                        <div id="cartSummary" class="hidden border-t border-border">
                            <div class="p-4 bg-surface/50">
                                <!-- Points Earned -->
                                <div class="flex items-center justify-between p-3 mb-4 bg-accent/10 rounded-xl">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">🎁</span>
                                        <span class="text-sm font-medium text-secondary">Vei câștiga</span>
                                    </div>
                                    <span id="pointsEarned" class="text-lg font-bold text-accent points-counter">0</span>
                                </div>

                                <!-- Summary -->
                                <div class="mb-4 space-y-2">
                                    <!-- Dynamic taxes container -->
                                    <div id="taxesContainer" class="space-y-1">
                                        <!-- Taxes will be rendered here dynamically -->
                                    </div>
                                    <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                                        <span>Total:</span>
                                        <span id="totalPrice" class="text-primary">0 lei</span>
                                    </div>
                                </div>
                                <!-- Hidden subtotal for JavaScript calculations -->
                                <span id="subtotal" class="hidden">0 lei</span>

                                <button id="checkoutBtn" onclick="EventPage.handleCheckout()" class="flex items-center justify-center w-full gap-2 py-4 text-lg font-bold text-white btn-primary rounded-xl bg-primary" aria-label="Cumpără bilete pentru acest eveniment">
                                    <svg id="checkoutBtnIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    <span id="checkoutBtnText">Cumpără bilete</span>
                                </button>
                            </div>

                            <!-- Trust Badges -->
                            <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                                <div class="flex items-center justify-center gap-6">
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Plată securizată
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Livrare instant
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="emptyCart" class="p-4 text-center border-t border-border">
                            <p class="text-sm text-muted">Selectează cel puțin un bilet pentru a continua</p>
                        </div>

                        <!-- Ticket Terms Section -->
                        <div id="ticket-terms-section" class="mb-4">
                            <div id="ticket-terms-content" class="p-4 text-xs prose text-blue-800 prose-slate max-w-none">
                                <!-- Loaded dynamically by JS -->
                            </div>
                        </div>
                        
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Fixed Bottom Button (shows on mobile only) -->
        <div id="mobileTicketBtn" class="hidden sticky bottom-0 left-0 right-0 z-[105] p-4 bg-primary border-t lg:hidden border-border border-b safe-area-bottom mobile:block">
            <button onclick="openTicketDrawer()" class="flex items-center justify-center w-full gap-3 py-4 text-lg font-bold bg-white text-primary rounded-xl" aria-label="Cumpără bilete pentru acest eveniment">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                <span>Cumpără bilete</span>
                <span id="mobileMinPrice" class="px-2 py-1 text-sm font-semibold rounded-lg bg-white/20">De la -- lei</span>
            </button>
        </div>
    </main>

    <!-- Sentinel for lazy-loading related events (always visible, zero height) -->
    <div id="related-events-sentinel" aria-hidden="true"></div>

    <!-- Custom Recommended Events (Îți recomandăm) - Premium Section -->
    <section class="relative mt-16 -mx-4 overflow-hidden md:-mx-8 lg:-mx-12" id="custom-related-section" style="display:none;">
        <!-- Premium gradient background -->
        <div class="absolute inset-0 bg-gradient-to-b from-primary to-primary/80"></div>

        <div class="relative px-4 py-12 md:px-8 lg:px-12 md:py-16 max-w-[90%] mx-auto">
            <!-- Section header -->
            <div class="flex flex-col items-center mb-10 text-center">
                <h2 class="mb-3 text-3xl font-extrabold text-white md:text-4xl">Îți recomandăm</h2>
                <p class="max-w-md text-white/90">Evenimente selectate special pentru tine, care nu trebuie ratate</p>
            </div>

            <!-- Premium events grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5" id="custom-related-events">
                <!-- Premium event cards loaded dynamically -->
            </div>
        </div>
    </section>

    <div class="border-t bg-slate-200 border-slate-300">
        <div class="px-4 pb-8 mx-auto max-w-7xl mobile:px-0 mobile:py-6">
            <!-- Related Events -->
            <section class="mt-8 mobile:px-4 mobile:mb-8 mobile:mt-18" id="related-events-section" style="display:none;">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-secondary">Alte evenimente recomandate</h2>
                        <p class="mt-1 text-muted" id="related-category-text">Evenimente similare</p>
                    </div>
                    <a href="/evenimente" id="see-all-link" class="items-center hidden gap-2 font-semibold md:flex text-primary">
                        Vezi toate
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
                <!-- Mobile: horizontal scroll snap, Desktop: grid -->
                <div class="flex gap-4 pb-4 -mx-4 overflow-x-auto snap-x snap-mandatory scroll-px-4 lg:mx-0 lg:pb-0 lg:overflow-visible lg:grid lg:grid-cols-4 lg:gap-5 lg:snap-none related-events-scroll" id="related-events"></div>
            </section>
        </div>
    </div>

    <!-- Mobile Ticket Drawer -->
    <div id="ticketDrawerBackdrop" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm lg:hidden" onclick="closeTicketDrawer()" style="visibility:hidden"></div>
    <div id="ticketDrawer" class="fixed bottom-0 left-0 right-0 z-[1000] flex flex-col overflow-hidden bg-white lg:hidden rounded-t-3xl max-h-[85vh]" style="visibility:hidden">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between flex-shrink-0 p-4 bg-white border-b border-border">
            <div>
                <h2 class="text-lg font-bold text-secondary">Selectează bilete</h2>
                <p class="text-sm text-muted">Alege tipul și cantitatea</p>
            </div>
            <button onclick="closeTicketDrawer()" class="flex items-center justify-center w-10 h-10 transition-colors rounded-full bg-surface hover:bg-gray-200" aria-label="Închide selecția de bilete">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <!-- Scrollable area: ticket types + collapsible terms -->
        <div class="flex-1 overflow-y-auto">
            <!-- Drawer Content (will be populated by JS) -->
            <div id="drawerTicketTypes" class="p-4 space-y-2"></div>
            <!-- Drawer Ticket Terms (collapsible) -->
            <div id="drawer-ticket-terms-section" class="px-4 pb-4" style="display: none;">
                <button type="button" onclick="toggleDrawerTerms()" class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium transition-colors rounded-xl text-primary bg-primary/5 hover:bg-primary/10" aria-label="Vezi termenii și condițiile pentru biletele selectate">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Vezi termeni și condiții
                    </span>
                    <svg id="drawer-terms-chevron" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="drawer-ticket-terms-content" class="hidden p-4 mt-2 overflow-y-auto text-xs prose text-blue-800 border prose-slate max-w-none rounded-xl border-primary/10 bg-primary/5 max-h-40">
                    <!-- Loaded dynamically by JS -->
                </div>
            </div>
        </div>
        <!-- Drawer Footer with summary -->
        <div id="drawerCartSummary" class="p-4 border-t border-border bg-surface/50" style="display: none;">
            <!-- Points Earned -->
            <div id="drawerPointsRow" class="flex items-center justify-between p-2 mb-3 rounded-lg bg-accent/10" style="display: none;">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-accent" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <span class="text-sm font-medium text-secondary">Vei câștiga</span>
                </div>
                <span id="drawerPointsEarned" class="font-bold text-accent">0</span>
            </div>
            <!-- Summary Details -->
            <div class="mb-3 space-y-2">
                <div class="flex justify-between hidden text-sm">
                    <span class="text-muted">Subtotal:</span>
                    <span id="drawerSubtotal" class="font-medium">0 lei</span>
                </div>
                <!-- Dynamic taxes container -->
                <div id="drawerTaxesContainer" class="space-y-1 text-sm">
                    <!-- Taxes will be synced here dynamically -->
                </div>
                <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                    <span>Total:</span>
                    <span id="drawerTotalPrice" class="text-primary">0 lei</span>
                </div>
            </div>
            <button onclick="closeTicketDrawer(); EventPage.handleCheckout();" class="flex items-center justify-center w-full gap-2 py-4 text-lg font-bold text-white btn-primary rounded-xl bg-primary" aria-label="Cumpără bilete pentru acest eveniment">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                Cumpără bilete
            </button>
        </div>
        <div id="drawerEmptyCart" class="p-4 text-center border-t border-border">
            <p class="text-sm text-muted">Selectează cel puțin un bilet pentru a continua</p>
        </div>
    </div>

    <!-- Redirect Modal -->
    <div id="redirectModal" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);opacity:0;transition:opacity .3s ease;">
        <!-- Close button - top left of screen -->
        <button onclick="closeRedirectModal()" style="position:fixed;top:16px;left:16px;z-index:10000;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.9);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);transition:transform .2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>

        <div style="background:#fff;border-radius:16px;max-width:520px;width:90%;padding:36px 32px;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.3);transform:translateY(20px);transition:transform .3s ease;">
            <!-- Icon -->
            <div style="width:64px;height:64px;margin:0 auto 20px;border-radius:50%;background:linear-gradient(135deg,#A51C30,#8B1728);display:flex;align-items:center;justify-content:center;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>

            <h2 style="font-size:20px;font-weight:700;color:#1a1a2e;margin:0 0 12px;line-height:1.3;">Platforma în curs de testare</h2>

            <p style="font-size:15px;color:#555;line-height:1.7;margin:0 0 8px;">
                Ne bucurăm că ești interesat de
            </p>
            <p style="font-size:17px;font-weight:700;color:#A51C30;margin:0 0 6px;">
                <?= htmlspecialchars($pageTitle) ?>
            </p>
            <?php if ($eventDate || ($venueName && $venueCity)): ?>
            <p style="font-size:14px;color:#777;margin:0 0 20px;">
                <?php if ($eventDate): ?>
                    <?= htmlspecialchars($eventDate) ?>
                <?php endif; ?>
                <?php if ($venueName && $venueCity): ?>
                    <?= $eventDate ? ' &middot; ' : '' ?><?= htmlspecialchars($venueName) ?>, <?= htmlspecialchars($venueCity) ?>
                <?php endif; ?>
            </p>
            <?php else: ?>
            <div style="margin-bottom:20px;"></div>
            <?php endif; ?>

            <p style="font-size:14px;color:#666;line-height:1.7;margin:0 0 28px;">
                Platforma <strong>bilete.online</strong> este momentan în etapa de testare și nu procesează încă comenzi reale. Pentru a achiziționa bilete, te rugăm să folosești platforma noastră activă.
            </p>

            <a href="<?= htmlspecialchars($ambiletUrl) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:14px 32px;background:linear-gradient(135deg,#A51C30,#8B1728);color:#fff;font-size:16px;font-weight:600;border-radius:10px;text-decoration:none;transition:all .3s;box-shadow:0 4px 15px rgba(165,28,48,0.3);" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(165,28,48,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 15px rgba(165,28,48,0.3)'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                Cumpără bilete pe ambilet.ro
            </a>

            <p style="font-size:12px;color:#aaa;margin:20px 0 0;">
                Vei fi redirecționat către aceeași pagină de eveniment
            </p>
        </div>
    </div>

    <script>
    (function() {
        var modal = document.getElementById('redirectModal');
        if (!modal) return;
        // Animate in
        requestAnimationFrame(function() {
            modal.style.opacity = '1';
            modal.querySelector('div[style*="translateY"]').style.transform = 'translateY(0)';
        });
    })();

    function closeRedirectModal() {
        var modal = document.getElementById('redirectModal');
        if (!modal) return;
        modal.style.opacity = '0';
        setTimeout(function() { modal.style.display = 'none'; }, 300);
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Page controller script
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/event-drawer.js') . '"></script>
<script defer src="' . asset('assets/js/pages/event-single.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => EventPage.init());</script>';

require_once __DIR__ . '/includes/scripts.php';
