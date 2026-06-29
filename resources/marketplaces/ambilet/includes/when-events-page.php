<?php
/**
 * Shared partial powering the /azi, /maine, /weekend landings.
 *
 * Entry files set $dateMode ('today' | 'tomorrow' | 'weekend') and
 * $pageCacheTTL before requiring page-cache.php; the rest of the
 * page (hero, ToC chips, per-category sections, JS) is here so the
 * three URLs stay in lock-step on layout and behaviour.
 *
 * Inputs (set by the caller):
 *   $dateMode      — required, one of 'today' | 'tomorrow' | 'weekend'
 *   $pageCacheTTL  — already used by page-cache.php before this is loaded
 *
 * Side effects: emits the full page (HTML + script tag at the end).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nav-cache.php';
require_once __DIR__ . '/api.php';

$dateMode = $dateMode ?? 'today';
if (!in_array($dateMode, ['today', 'tomorrow', 'weekend'], true)) {
    $dateMode = 'today';
}

// Per-mode label / hero copy / cache key. Kept here (not in the entry
// files) so adding a new mode means one switch update, not three.
$modeMeta = [
    'today' => [
        'title'      => 'Azi pe ' . SITE_NAME . ' — evenimente live astăzi',
        'desc'       => 'Toate evenimentele care se întâmplă astăzi în România, grupate pe categorii.',
        'heroBadge'  => 'Astăzi',
        'heroTitle'  => 'Ce se întâmplă azi',
        'cacheKey'   => 'when_events_today',
        'apiQuery'   => 'date=today&per_page=200&sort=date_asc',
        'emptyBlurb' => 'Astăzi nu sunt evenimente programate.',
        'nav'        => 'azi',
        'path'       => '/azi',
    ],
    'tomorrow' => [
        'title'      => 'Mâine pe ' . SITE_NAME . ' — evenimente live de mâine',
        'desc'       => 'Evenimentele de mâine în România, grupate pe categorii.',
        'heroBadge'  => 'Mâine',
        'heroTitle'  => 'Ce se întâmplă mâine',
        'cacheKey'   => 'when_events_tomorrow',
        'apiQuery'   => 'date=tomorrow&per_page=200&sort=date_asc',
        'emptyBlurb' => 'Mâine nu sunt evenimente programate momentan.',
        'nav'        => 'maine',
        'path'       => '/maine',
    ],
    'weekend' => [
        'title'      => 'Weekend pe ' . SITE_NAME . ' — evenimente sâmbătă & duminică',
        'desc'       => 'Evenimentele din weekendul care urmează, grupate pe categorii.',
        'heroBadge'  => 'Weekend',
        'heroTitle'  => 'Ce se întâmplă în weekend',
        'cacheKey'   => 'when_events_weekend',
        'apiQuery'   => 'date=weekend&per_page=200&sort=date_asc',
        'emptyBlurb' => 'În weekendul care urmează nu sunt evenimente programate momentan.',
        'nav'        => 'weekend',
        'path'       => '/weekend',
    ],
];
$meta = $modeMeta[$dateMode];

// Categories — name + icon + sort order, used to render section headers
// in a predictable order.
$navCategories = getEventCategories();
$categoryBySlug = [];
foreach ($navCategories as $cat) {
    if (!empty($cat['slug'])) {
        $categoryBySlug[$cat['slug']] = $cat;
    }
}

// Fetch events for the chosen window. The API supports date=today /
// date=tomorrow / date=weekend natively.
$apiResponse = api_cached($meta['cacheKey'], function () use ($meta) {
    return api_get('/marketplace-events?' . $meta['apiQuery']);
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

// Group events by category slug. Uncategorised fall into '__other__'
// which renders last under "Altele".
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

// Section order: configured categories first (their sort_order), then
// any unknown categories, then "Altele".
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
    if (!empty($eventsByCategory[$s])) $totalCategories++;
}

// Date label for the hero pill. For "weekend" we surface Sat-Sun
// dates explicitly so the visitor knows which weekend they're looking
// at (especially during weekdays before the weekend arrives).
$tz = new DateTimeZone('Europe/Bucharest');
$now = new DateTime('now', $tz);
$romMonth = ['Jan'=>'Ian','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','May'=>'Mai','Jun'=>'Iun','Jul'=>'Iul','Aug'=>'Aug','Sep'=>'Sep','Oct'=>'Oct','Nov'=>'Noi','Dec'=>'Dec'];
$fmt = fn (DateTime $d) => strtr($d->format('d M'), $romMonth);

if ($dateMode === 'today') {
    $dateLabel = strtr($now->format('d M Y'), $romMonth);
} elseif ($dateMode === 'tomorrow') {
    $tomorrow = (clone $now)->modify('+1 day');
    $dateLabel = strtr($tomorrow->format('d M Y'), $romMonth);
} else { // weekend
    $sat = clone $now;
    $dow = (int) $sat->format('N'); // 1=Mon..7=Sun
    if ($dow === 6) {
        // Today is Saturday — use today
    } elseif ($dow === 7) {
        // Today is Sunday — back up to Saturday
        $sat->modify('-1 day');
    } else {
        // Weekday — jump to upcoming Saturday
        $sat->modify('next Saturday');
    }
    $sun = (clone $sat)->modify('+1 day');
    $dateLabel = $fmt($sat) . ' – ' . $fmt($sun) . ' ' . $sun->format('Y');
}

$pageTitle = $meta['title'];
$pageDescription = $meta['desc'];
$currentPage = $meta['nav'];
$transparentHeader = false;
$cssBundle = 'listing';

require_once __DIR__ . '/head.php';
require_once __DIR__ . '/header.php';
?>

<!-- Hero (compact) -->
<section class="relative pt-28 pb-6 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="absolute -top-20 -right-20 w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(165,28,48,0.30)_0%,transparent_70%)] rounded-full"></div>
    <div class="relative px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center text-center mobile:px-4">
            <span class="inline-flex items-center gap-2 px-3 py-1 mb-3 text-xs font-bold uppercase tracking-widest text-white rounded-full bg-primary/90">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path d="M12 6v6l4 2" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <?= htmlspecialchars($meta['heroBadge']) ?> · <?= htmlspecialchars($dateLabel) ?>
            </span>
            <h1 class="mb-1 text-3xl font-extrabold text-white md:text-4xl"><?= htmlspecialchars($meta['heroTitle']) ?></h1>
            <p class="max-w-2xl text-base text-gray-300">
                <?php if ($totalEvents > 0): ?>
                    <?= $totalEvents ?> evenimente <?= $totalCategories > 1 ? 'în ' . $totalCategories . ' categorii' : '' ?>. Alege ce te interesează.
                <?php else: ?>
                    <?= htmlspecialchars($meta['emptyBlurb']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<!-- When tabs: azi / mâine / weekend, persistent across the three landings -->
<nav class="border-b border-border bg-white" aria-label="Selector dată">
    <div class="flex items-center justify-center gap-1 px-4 mx-auto max-w-7xl">
        <?php
        $whenTabs = [
            ['nav' => 'azi',     'path' => '/azi',     'label' => 'Azi'],
            ['nav' => 'maine',   'path' => '/maine',   'label' => 'Mâine'],
            ['nav' => 'weekend', 'path' => '/weekend', 'label' => 'Weekend'],
        ];
        foreach ($whenTabs as $tab):
            $isActive = $tab['nav'] === $meta['nav'];
        ?>
            <a href="<?= $tab['path'] ?>"
               class="relative inline-flex items-center px-5 py-3 text-sm font-semibold transition-colors <?= $isActive ? 'text-primary' : 'text-muted hover:text-secondary' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($tab['label']) ?>
                <?php if ($isActive): ?>
                    <span class="absolute inset-x-3 -bottom-px h-0.5 bg-primary rounded-full"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<main class="px-4 py-8 mx-auto max-w-7xl mobile:px-2">

<?php if ($totalEvents === 0): ?>
    <div class="p-10 text-center bg-white border rounded-2xl border-border">
        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-3 rounded-full bg-primary/10">
            <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-secondary">Niciun eveniment</h2>
        <p class="mt-2 text-sm text-muted">
            <?= htmlspecialchars($meta['emptyBlurb']) ?>
            Verifică <a href="/evenimente" class="font-semibold text-primary hover:underline">toate evenimentele</a> sau încearcă altă perioadă din tab-urile de mai sus.
        </p>
    </div>
<?php else: ?>

    <?php
    $tocItems = [];
    foreach ($orderedSlugs as $slug) {
        if (empty($eventsByCategory[$slug])) continue;
        $catData = $slug === '__other__'
            ? ['name' => 'Altele', 'icon_emoji' => '🎟️', 'slug' => '__other__']
            : ($categoryBySlug[$slug] ?? ['name' => ucfirst($slug), 'icon_emoji' => '🎫', 'slug' => $slug]);
        $tocItems[] = [
            'slug'  => $slug,
            'name'  => $catData['name'] ?? ucfirst($slug),
            'icon'  => $catData['icon_emoji'] ?? '🎫',
            'count' => count($eventsByCategory[$slug]),
        ];
    }
    ?>

    <?php if (count($tocItems) > 1): ?>
        <nav class="flex flex-wrap gap-2 mb-6" aria-label="Categorii">
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
        <section id="cat-<?= htmlspecialchars($slug) ?>" class="mb-10 scroll-mt-24">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <span class="text-2xl" aria-hidden="true"><?= htmlspecialchars($catIcon) ?></span>
                    <div>
                        <h2 class="text-xl font-bold text-secondary"><?= htmlspecialchars($catName) ?></h2>
                        <p class="text-xs text-muted"><?= count($events) ?> <?= count($events) === 1 ? 'eveniment' : 'evenimente' ?></p>
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

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
// Render the embedded per-category event payloads using the shared
// event-card component. No API call — server already serialized the
// data into data-events on each grid container.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof AmbiletEventCard === 'undefined' || typeof AmbiletEventCard.renderMany !== 'function') {
        console.warn('[when] AmbiletEventCard not available, leaving sections empty');
        return;
    }
    document.querySelectorAll('[data-azi-grid]').forEach(function (grid) {
        var raw = grid.getAttribute('data-events') || '[]';
        var events = [];
        try { events = JSON.parse(raw); } catch (e) {
            console.warn('[when] Failed to parse payload for', grid.dataset.aziGrid, e);
            return;
        }
        if (!Array.isArray(events) || events.length === 0) return;

        grid.innerHTML = AmbiletEventCard.renderMany(events, {
            urlPrefix: '/bilete/',
            showCategory: false,
            showPrice: true,
            showVenue: true,
        });
    });
});
</script>
JS;
require_once __DIR__ . '/scripts.php';
