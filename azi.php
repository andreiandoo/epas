<?php
/**
 * "Azi" — events happening today, grouped by category.
 *
 * URL: /azi (mapped via .htaccess: RewriteRule ^azi/?$ azi.php)
 *
 * Server-side strategy:
 *   1. Fetch today's events through the marketplace API (the existing
 *      ?date=today filter handles the single_day case; range/multi_day are
 *      cleaned up in PHP below so an event whose window covers today is
 *      surfaced even if event_date itself isn't today).
 *   2. Group by category in PHP. One section per category, ordered by
 *      sort_order; uncategorised events land in a trailing "Altele" bucket.
 *   3. Embed each category's events as a JSON payload; AmbiletEventCard.
 *      renderMany() reads it on DOMContentLoaded and paints the cards
 *      using the same component as every other listing page.
 *   4. Page cache 5 min so the homepage spike (which is the same audience
 *      hitting /azi at the top of the hour) doesn't fan out into N API
 *      calls.
 */
$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';
require_once __DIR__ . '/includes/api.php';

// Load categories for label/icon lookup + ordering. getEventCategories()
// returns name/slug/icon_emoji/sort_order — exactly the per-section
// header data we need below.
$navCategories = getEventCategories();
$categoryBySlug = [];
foreach ($navCategories as $cat) {
    if (!empty($cat['slug'])) {
        $categoryBySlug[$cat['slug']] = $cat;
    }
}

// Fetch today's events. per_page=200 — comfortable ceiling for a single
// day across all categories (Ambilet rarely crosses 60-80 events/day even
// during festival season). The marketplace endpoint already filters by
// the public client + published + non-cancelled, so we get a clean set.
$apiResponse = api_cached('azi_events_today', function () {
    return api_get('/marketplace-events?date=today&per_page=200&sort=date_asc');
}, 300);

$rawEvents = [];
if (is_array($apiResponse)) {
    $rawEvents = $apiResponse['data']['data']
        ?? $apiResponse['data']['events']
        ?? $apiResponse['data']
        ?? [];
    if (!is_array($rawEvents)) {
        $rawEvents = [];
    }
}

// Group events by category slug, keeping per-category sort order from
// the API (date_asc — earliest start time first within a category).
// Events missing a category land under '__other__' which gets the
// "Altele" label at render time.
$eventsByCategory = [];
foreach ($rawEvents as $event) {
    $catSlug = '';
    if (!empty($event['category_slug'])) {
        $catSlug = (string) $event['category_slug'];
    } elseif (!empty($event['category']) && is_array($event['category']) && !empty($event['category']['slug'])) {
        $catSlug = (string) $event['category']['slug'];
    }
    if ($catSlug === '') {
        $catSlug = '__other__';
    }
    $eventsByCategory[$catSlug][] = $event;
}

// Order: nav-cache categories first (in their sort_order), then any
// unknown categories at the end, then "Altele" for uncategorised.
$orderedSlugs = array_keys($categoryBySlug);
foreach (array_keys($eventsByCategory) as $slug) {
    if (!in_array($slug, $orderedSlugs, true) && $slug !== '__other__') {
        $orderedSlugs[] = $slug;
    }
}
if (isset($eventsByCategory['__other__'])) {
    $orderedSlugs[] = '__other__';
}
$orderedSlugs = array_values(array_unique($orderedSlugs));

$totalEvents = count($rawEvents);
$totalCategories = 0;
foreach ($orderedSlugs as $s) {
    if (!empty($eventsByCategory[$s])) {
        $totalCategories++;
    }
}

$todayLabel = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))
    ->format('d M Y');
// Romanian month names — PHP's locale support is unreliable on shared
// hosts so we patch the few short forms we need rather than relying on
// strftime() / IntlDateFormatter.
$todayLabel = strtr($todayLabel, [
    'Jan' => 'Ian', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
    'May' => 'Mai', 'Jun' => 'Iun', 'Jul' => 'Iul', 'Aug' => 'Aug',
    'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Noi', 'Dec' => 'Dec',
]);

$pageTitle = 'Azi pe ' . SITE_NAME . ' — evenimente live astăzi';
$pageDescription = 'Toate evenimentele care se întâmplă astăzi în România, grupate pe categorii.';
$currentPage = 'azi';
$transparentHeader = false;
$cssBundle = 'listing';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="relative pt-40 pb-10 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="absolute -top-32 -right-24 w-[420px] h-[420px] bg-[radial-gradient(circle,rgba(165,28,48,0.30)_0%,transparent_70%)] rounded-full"></div>
    <div class="relative px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center text-center mobile:px-4">
            <span class="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-bold uppercase tracking-widest text-white rounded-full bg-primary/90">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path d="M12 6v6l4 2" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Astăzi · <?= htmlspecialchars($todayLabel) ?>
            </span>
            <h1 class="mb-3 text-4xl font-extrabold text-white md:text-5xl">Ce se întâmplă azi</h1>
            <p class="max-w-2xl text-lg text-gray-300">
                <?php if ($totalEvents > 0): ?>
                    <?= $totalEvents ?> evenimente live azi <?= $totalCategories > 1 ? 'în ' . $totalCategories . ' categorii' : '' ?>. Alege ce te interesează.
                <?php else: ?>
                    Astăzi nu sunt evenimente programate. Aruncă o privire <a href="/evenimente" class="font-semibold text-white underline hover:text-primary-light">la programul săptămânii</a>.
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<main class="px-4 py-10 mx-auto max-w-7xl mobile:px-2">

<?php if ($totalEvents === 0): ?>
    <div class="p-12 text-center bg-white border rounded-2xl border-border">
        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-primary/10">
            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-secondary">Niciun eveniment azi</h2>
        <p class="mt-2 text-sm text-muted">Verifică <a href="/evenimente" class="font-semibold text-primary hover:underline">toate evenimentele</a> pentru programul complet.</p>
    </div>
<?php else: ?>

    <?php
    // Build a flat catalog table-of-contents so visitors can jump to a
    // category section without scrolling past unrelated ones.
    $tocItems = [];
    foreach ($orderedSlugs as $slug) {
        if (empty($eventsByCategory[$slug])) continue;
        $catData = $slug === '__other__'
            ? ['name' => 'Altele', 'icon_emoji' => '🎟️', 'slug' => '__other__']
            : ($categoryBySlug[$slug] ?? ['name' => ucfirst($slug), 'icon_emoji' => '🎫', 'slug' => $slug]);
        $tocItems[] = [
            'slug' => $slug,
            'name' => $catData['name'] ?? ucfirst($slug),
            'icon' => $catData['icon_emoji'] ?? '🎫',
            'count' => count($eventsByCategory[$slug]),
        ];
    }
    ?>

    <?php if (count($tocItems) > 1): ?>
        <nav class="flex flex-wrap gap-2 mb-8" aria-label="Categorii">
            <?php foreach ($tocItems as $item): ?>
                <a href="#cat-<?= htmlspecialchars($item['slug']) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold transition-all bg-white border rounded-full border-border text-secondary hover:border-primary hover:text-primary">
                    <span aria-hidden="true"><?= htmlspecialchars($item['icon']) ?></span>
                    <?= htmlspecialchars($item['name']) ?>
                    <span class="px-1.5 py-0.5 text-xs font-bold rounded-full bg-primary/10 text-primary"><?= (int) $item['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php foreach ($orderedSlugs as $slug):
        if (empty($eventsByCategory[$slug])) continue;
        $events = $eventsByCategory[$slug];
        $catData = $slug === '__other__'
            ? ['name' => 'Altele', 'icon_emoji' => '🎟️', 'slug' => '__other__']
            : ($categoryBySlug[$slug] ?? ['name' => ucfirst($slug), 'icon_emoji' => '🎫', 'slug' => $slug]);
        $catName = $catData['name'] ?? ucfirst($slug);
        $catIcon = $catData['icon_emoji'] ?? '🎫';
        $catLink = $slug !== '__other__' ? '/' . rawurlencode($slug) : null;
        $payload = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    ?>
        <section id="cat-<?= htmlspecialchars($slug) ?>" class="mb-12 scroll-mt-24">
            <div class="flex items-end justify-between gap-4 mb-5">
                <div class="flex items-center gap-3">
                    <span class="text-3xl" aria-hidden="true"><?= htmlspecialchars($catIcon) ?></span>
                    <div>
                        <h2 class="text-2xl font-bold text-secondary"><?= htmlspecialchars($catName) ?></h2>
                        <p class="text-sm text-muted"><?= count($events) ?> <?= count($events) === 1 ? 'eveniment' : 'evenimente' ?> astăzi</p>
                    </div>
                </div>
                <?php if ($catLink): ?>
                    <a href="<?= htmlspecialchars($catLink) ?>" class="hidden text-sm font-semibold text-primary hover:underline sm:inline-block">
                        Vezi toate →
                    </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 md:gap-5"
                 data-azi-grid="<?= htmlspecialchars($slug) ?>"
                 data-events="<?= htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') ?>">
                <!-- Cards rendered client-side via AmbiletEventCard.renderMany -->
            </div>
        </section>
    <?php endforeach; ?>

<?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
// Render today's events using the shared event-card component. Each
// .data-azi-grid container ships its own JSON payload from the server,
// so this is a pure paint-the-data step — no API calls. data-events
// is JSON-escaped via PHP's JSON_HEX_* + htmlspecialchars, and the
// DOM-attribute decode below restores it exactly.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof AmbiletEventCard === 'undefined' || typeof AmbiletEventCard.renderMany !== 'function') {
        console.warn('[azi] AmbiletEventCard not available, leaving sections empty');
        return;
    }
    document.querySelectorAll('[data-azi-grid]').forEach(function (grid) {
        var raw = grid.getAttribute('data-events') || '[]';
        var events = [];
        try { events = JSON.parse(raw); } catch (e) {
            console.warn('[azi] Failed to parse payload for', grid.dataset.aziGrid, e);
            return;
        }
        if (!Array.isArray(events) || events.length === 0) return;

        grid.innerHTML = AmbiletEventCard.renderMany(events, {
            urlPrefix: '/bilete/',
            // Category badge hidden — the page section header already
            // signals the category, repeating it on every card is noise.
            showCategory: false,
            showPrice: true,
            showVenue: true,
            // Date badge stays visible so visitors can spot start time
            // at a glance (most relevant info for "what's on today").
        });
    });
});
</script>
JS;
require_once __DIR__ . '/includes/scripts.php';
?>
