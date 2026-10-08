<?php
/**
 * Single activity detail + booking sidebar: the first design (Alpine + the legacy head).
 *
 * Not routed any more: /activity/{slug} and /{city}/{slug} are served by single-activitate.php. Kept in step with
 * the language layer in case it is linked again.
 * Server-rendered shell built from the v2 single-activity.html design.
 * Alpine.js handles the sidebar date+slot+variants picker and the gallery
 * modal. Booking submission is wired up to a placeholder until A5 lands
 * the cart pipeline.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/v2/helpers.php';   // v2_t(), v2_te(), v2_money_in()

// 10-minute page cache — activity content rarely changes; staff push a
// change via /admin/activities/{id}/edit which already busts the API cache
// + we serve stale for up to 40min. ?nocache=1 forces a refresh. Skips
// caching for POSTs + logged-in admins (see page-cache.php).
$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';

// ============================================================
// SLUG RESOLUTION
// ============================================================
$slug = $_GET['slug'] ?? null;
if (! $slug || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

// ============================================================
// FETCH DETAIL
// ============================================================
$activityResp = api_cached("activity_detail_{$slug}", fn () => api_get('/activities/' . $slug), 60);
if (! ($activityResp['success'] ?? false) || empty($activityResp['data']['activity'])) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

$activity = $activityResp['data']['activity'];

// ============================================================
// PAGE METADATA
// ============================================================
$pageTitleRaw = ($activity['seo']['title'] ?? null)
    ?: ($activity['title'] . ' | ' . SITE_NAME);
$pageDescription = $activity['seo']['description']
    ?? $activity['short_description']
    ?? v2_t('{title} on {site}', ['title' => $activity['title'], 'site' => SITE_NAME]);

$canonicalUrl = SITE_URL . '/activity/' . $activity['slug'];
$currentPage = 'activitate';
$cssBundle = 'single';

$breadcrumbs = [
    ['name' => v2_t('Home'), 'url' => SITE_URL . '/'],
];
if (! empty($activity['category'])) {
    $breadcrumbs[] = [
        'name' => $activity['category']['name'],
        'url'  => SITE_URL . '/' . $activity['category']['slug'],
    ];
}
$breadcrumbs[] = ['name' => $activity['title'], 'url' => $canonicalUrl];

// JSON-LD: Product + AggregateOffer + BreadcrumbList + FAQPage (when populated).
$variantPrices = array_filter(array_column($activity['variants'] ?? [], 'price_cents'));
$lowPriceCents = $variantPrices ? min($variantPrices) : null;
$highPriceCents = $variantPrices ? max($variantPrices) : null;
$heroImage = $activity['hero_image_url'] ?? $activity['cover_image_url'] ?? null;
// The currency the activity is sold (and charged) in: its own when the API sends it, else its variants', else the site's.
$actCurrency = strtoupper((string) ($activity['currency'] ?? (array_values(array_filter(array_column((array) ($activity['variants'] ?? []), 'currency')))[0] ?? ''))) ?: SITE_CURRENCY;

$structuredData = [
    [
        '@context'    => 'https://schema.org',
        '@type'       => ['Product', 'TouristAttraction'],
        'name'        => $activity['title'],
        'description' => $activity['short_description'] ?? '',
        'image'       => $heroImage,
        'category'    => $activity['category']['name'] ?? null,
        'url'         => $canonicalUrl,
        'offers'      => $lowPriceCents ? [
            '@type'         => 'AggregateOffer',
            'priceCurrency' => $actCurrency,
            'lowPrice'      => number_format($lowPriceCents / 100, 2, '.', ''),
            'highPrice'     => number_format($highPriceCents / 100, 2, '.', ''),
            'offerCount'    => count($activity['variants'] ?? []),
            'availability'  => 'https://schema.org/InStock',
        ] : null,
    ],
];
if (! empty($activity['faqs']) && is_array($activity['faqs'])) {
    $structuredData[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'] ?? '',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a'] ?? ''],
        ], $activity['faqs']),
    ];
}

// ============================================================
// HELPERS (page-local)
// ============================================================
$pricedFromCents = function (?int $cents, $currency = null) use ($actCurrency): string {
    if (! $cents || $cents <= 0) return '—';
    return v2_money_in($cents / 100, is_string($currency) && $currency !== '' ? strtoupper($currency) : $actCurrency);
};

$dayLabels = [
    1 => v2_t('Monday'), 2 => v2_t('Tuesday'), 3 => v2_t('Wednesday'), 4 => v2_t('Thursday'),
    5 => v2_t('Friday'), 6 => v2_t('Saturday'), 7 => v2_t('Sunday'),
];

$difficultyLabels = [
    'easy' => v2_t('Easy'), 'medium' => v2_t('Medium'), 'hard' => v2_t('Hard'), 'expert' => v2_t('Expert'),
];

// Bootstrap Alpine state.
$bookingBootstrap = [
    'activity_id'       => (int) ($activity['id'] ?? 0),
    'slug'              => $activity['slug'],
    'title'             => $activity['title'] ?? '',
    'cover_image'       => $activity['cover_image_url'] ?? ($activity['hero_image_url'] ?? null),
    'venue_name'        => $activity['venue']['name']    ?? null,
    'venue_city'        => $activity['city']['name']     ?? ($activity['venue']['city'] ?? null),
    'organizer_id'      => (int) ($activity['organizer']['id'] ?? 0) ?: null,
    'duration_minutes'  => (int) ($activity['duration_minutes'] ?? 0) ?: null,
    'commission_rate'   => (float) ($activity['organizer']['commission_rate'] ?? 0),
    'commission_mode'   => (string) ($activity['organizer']['commission_mode'] ?? 'included'),
    'variants'          => array_map(fn ($v) => [
        'id'             => $v['id'],
        'name'           => $v['name'],
        'description'    => $v['description'] ?? null,
        'price_cents'    => (int) $v['price_cents'],
        'currency'       => $v['currency'] ?? null,
        'capacity_share' => (int) $v['capacity_share'],
        'min_per_order'  => (int) $v['min_per_order'],
        'max_per_order'  => (int) $v['max_per_order'],
        'min_age'        => $v['min_age'],
        'max_age'        => $v['max_age'],
    ], $activity['variants'] ?? []),
    'window' => $activity['booking_window'] ?? [
        'lead_time_hours'  => 2,
        'max_advance_days' => 60,
        'min_participants' => 1,
        'max_participants' => 10,
    ],
    'gallery' => array_map(fn ($url) => ['src' => $url, 'alt' => $activity['title']], $activity['gallery'] ?? []),
    'currency' => $actCurrency,
    'locale' => v2_locale() === 'en' ? 'en-GB' : v2_locale(),
    'cartUrl' => v2_url('/cart'),
    // texts the script at the end of the page prints
    'labels' => [
        'choose' => v2_t('Choose your tickets'),
        'min' => v2_t('Minimum {n} participants'),
        'max' => v2_t('Maximum {n} participants'),
        'one' => v2_t('1 participant'),
        'many' => v2_t('{n} participants'),
        'noCart' => v2_t('The basket did not load. Reload the page and try again.'),
        'notAdded' => v2_t('We could not add this to your basket. Check the date and time you chose, then try again.'),
    ],
];

// Three independent recommendation rails — only rendered when ≥1 card each.
// Admin-managed connections (`related`) is shown first as its own section if set.
$rails = [];
if (! empty($activity['related'])) {
    $rails[] = [
        'kicker' => v2_t('Hand-picked'),
        'title'  => v2_t('You might also like'),
        'subtitle' => null,
        'cards'  => $activity['related'],
    ];
}
$recs = $activity['recommendations'] ?? [];
$organizerName = $activity['organizer']['name'] ?? null;
$cityName = $activity['city']['name'] ?? null;
$categoryName = $activity['category']['name'] ?? null;

if (! empty($recs['same_organizer'])) {
    $rails[] = [
        'kicker' => v2_t('From the same venue'),
        'title'  => $organizerName ? v2_t('More experiences from {operator}', ['operator' => $organizerName]) : v2_t('More experiences from this operator'),
        'subtitle' => null,
        'cards'  => $recs['same_organizer'],
    ];
}
if (! empty($recs['same_city_same_cat'])) {
    $rails[] = [
        'kicker' => v2_t('Similar activities'),
        'title'  => $cityName && $categoryName
            ? v2_t('{category} in {city}', ['category' => $categoryName, 'city' => $cityName])
            : v2_t('Similar activities in this city'),
        'subtitle' => null,
        'cards'  => $recs['same_city_same_cat'],
    ];
}
if (! empty($recs['same_city'])) {
    $rails[] = [
        'kicker' => v2_t('In the same city'),
        'title'  => $cityName ? v2_t('More experiences in {city}', ['city' => $cityName]) : v2_t('More experiences in this city'),
        'subtitle' => null,
        'cards'  => $recs['same_city'],
    ];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main id="top" x-data="activityPage(<?= htmlspecialchars(json_encode($bookingBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>)">

<!-- ============================================================ -->
<!-- HERO                                                            -->
<!-- ============================================================ -->
<section class="relative overflow-hidden">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-paper via-paper to-paper-2" aria-hidden="true"></div>
    <div class="absolute -z-10 -top-32 -right-40 w-[640px] h-[640px] rounded-full bg-vermilion/10 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -z-10 top-40 -left-40 w-[520px] h-[520px] rounded-full bg-forest/10 blur-3xl" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 lg:pt-14 pb-8">

        <!-- Breadcrumbs -->
        <nav aria-label="<?= v2_te('Breadcrumb') ?>" class="mb-7 font-mono text-[11px] tracking-wide text-ink-soft">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="/" class="hover:text-vermilion"><?= v2_te('Home') ?></a></li>
                <?php if (! empty($activity['category'])): ?>
                    <li class="text-ink/30">/</li>
                    <li><a href="/<?= htmlspecialchars($activity['category']['slug']) ?>" class="hover:text-vermilion"><?= htmlspecialchars($activity['category']['name']) ?></a></li>
                <?php endif; ?>
                <li class="text-ink/30">/</li>
                <li aria-current="page" class="text-ink"><?= htmlspecialchars($activity['title']) ?></li>
            </ol>
        </nav>

        <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start">
            <!-- left hero copy -->
            <div class="lg:col-span-7">
                <div class="flex flex-wrap items-center gap-2 mb-5">
                    <?php if (! empty($activity['category'])): ?>
                        <span class="stamp px-3 py-1 text-[10px] font-mono tracking-[.2em] text-vermilion rotate-[-1.5deg]"><?= htmlspecialchars(strtoupper($activity['category']['name'])) ?></span>
                    <?php endif; ?>
                    <?php if (! empty($activity['city'])): ?>
                        <span class="px-3 py-1 rounded-full bg-forest/10 text-forest text-xs font-700"><?= htmlspecialchars($activity['city']['name']) ?></span>
                    <?php endif; ?>
                    <span class="px-3 py-1 rounded-full bg-ink/5 text-ink-soft text-xs font-700"><?= v2_te('Instant confirmation') ?></span>
                </div>

                <h1 class="font-display text-[clamp(2.6rem,7vw,6.4rem)] leading-[.88] font-700 tracking-tight max-w-4xl">
                    <?= htmlspecialchars($activity['title']) ?>
                    <?php if (! empty($activity['subtitle'])): ?>
                        <span class="block text-vermilion ital font-500 text-[clamp(1.5rem,3.5vw,3rem)] mt-2"><?= htmlspecialchars($activity['subtitle']) ?></span>
                    <?php endif; ?>
                </h1>

                <?php if (! empty($activity['short_description'])): ?>
                    <p class="mt-6 max-w-2xl text-xl sm:text-2xl leading-snug text-ink-soft">
                        <?= htmlspecialchars($activity['short_description']) ?>
                    </p>
                <?php endif; ?>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#rezerva" class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-vermilion text-paper font-700 hover:bg-vermilion-d transition-colors">
                        <?= v2_te('Book tickets') ?>
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </a>
                    <a href="#despre" class="inline-flex items-center justify-center px-6 py-3.5 rounded-full border-2 border-ink font-700 hover:bg-ink hover:text-paper transition-colors"><?= v2_te('See details') ?></a>
                </div>

                <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl">
                    <?php if ($activity['duration_minutes']): ?>
                        <div class="bg-paper/75 border border-ink/10 rounded-2xl p-4">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft"><?= v2_te('Duration') ?></dt>
                            <dd class="mt-1 font-display text-2xl font-700"><?= (int) $activity['duration_minutes'] ?> min</dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($activity['capacity_per_slot']): ?>
                        <div class="bg-paper/75 border border-ink/10 rounded-2xl p-4">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft"><?= v2_te('Group') ?></dt>
                            <dd class="mt-1 font-display text-2xl font-700"><?= v2_te('up to {n}', ['n' => (int) $activity['capacity_per_slot']]) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($activity['age_min'] !== null): ?>
                        <div class="bg-paper/75 border border-ink/10 rounded-2xl p-4">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft"><?= v2_te('Age') ?></dt>
                            <dd class="mt-1 font-display text-2xl font-700"><?= (int) $activity['age_min'] ?>+</dd>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($activity['difficulty_level'])): ?>
                        <div class="bg-paper/75 border border-ink/10 rounded-2xl p-4">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft"><?= v2_te('Difficulty') ?></dt>
                            <dd class="mt-1 font-display text-2xl font-700"><?= htmlspecialchars($difficultyLabels[$activity['difficulty_level']] ?? '—') ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- right hero card -->
            <div class="lg:col-span-5">
                <div class="relative">
                    <div class="ticket bg-ink text-paper rounded-[2rem] border-2 border-ink overflow-hidden shadow-2xl rotate-[1deg]" style="--perf:72%; --punch:#1B1714">
                        <div class="relative h-[420px] duotone bg-gradient-to-br from-vermilion via-ochre to-forest text-paper">
                            <div class="grid-tex"></div>
                            <?php if ($heroImage): ?>
                                <img src="<?= htmlspecialchars($heroImage) ?>"
                                     alt="<?= htmlspecialchars($activity['title']) ?>"
                                     class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-75"
                                     loading="eager">
                            <?php endif; ?>
                            <?php if (! empty($bookingBootstrap['gallery'])): ?>
                                <button @click="openGallery(0)" class="absolute inset-0 group" aria-label="<?= v2_te('Open the photo gallery') ?>">
                                    <span class="absolute bottom-5 left-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-paper text-ink text-sm font-700 group-hover:bg-vermilion group-hover:text-paper transition-colors">
                                        <?= v2_te('See the gallery') ?>
                                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                    </span>
                                </button>
                            <?php endif; ?>
                            <span class="stamp absolute top-5 left-5 text-paper/85 px-3 py-1 text-[10px] font-mono -rotate-6"><?= v2_te('Admit one') ?></span>
                        </div>
                        <div class="p-5 sm:p-6">
                            <p class="font-mono text-[10px] text-paper/50 tracking-[.18em]"><?= v2_te('Quick booking') ?></p>
                            <div class="mt-3 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-paper/60 text-sm"><?= v2_te('from') ?></p>
                                    <p class="font-display text-4xl font-700"><?= v2_e($pricedFromCents($activity['cheapest_price_cents'] ?? null)) ?></p>
                                </div>
                                <a href="#rezerva" class="px-5 py-3 rounded-full bg-vermilion text-paper font-700 hover:bg-vermilion-d transition-colors"><?= v2_te('Choose a time') ?></a>
                            </div>
                        </div>
                        <div class="notch top"></div><div class="notch bot"></div><div class="perf hidden sm:block"></div>
                    </div>

                    <?php if (! empty($activity['organizer'])): ?>
                        <div class="absolute -bottom-5 -left-3 sm:-left-8 bg-paper border-2 border-ink rounded-2xl p-4 shadow-ticket rotate-[-3deg] max-w-[220px]">
                            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft"><?= v2_te('Operator') ?></p>
                            <p class="mt-1 font-display text-xl font-700 leading-tight"><?= htmlspecialchars($activity['organizer']['name']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- STICKY ANCHOR NAV                                               -->
<!-- ============================================================ -->
<section class="sticky top-24 z-40 bg-paper/90 backdrop-blur-md border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="no-bar flex items-center gap-2 overflow-x-auto py-3 text-sm font-700">
            <a href="#despre" class="shrink-0 px-4 py-2 rounded-full hover:bg-ink hover:text-paper transition-colors"><?= v2_te('About') ?></a>
            <a href="#rezerva" class="shrink-0 px-4 py-2 rounded-full bg-vermilion text-paper hover:bg-vermilion-d transition-colors"><?= v2_te('Tickets') ?></a>
            <?php if (! empty($activity['schedule'])): ?>
                <a href="#program" class="shrink-0 px-4 py-2 rounded-full hover:bg-ink hover:text-paper transition-colors"><?= v2_te('Opening hours') ?></a>
            <?php endif; ?>
            <?php if (! empty($activity['venue'])): ?>
                <a href="#locatie" class="shrink-0 px-4 py-2 rounded-full hover:bg-ink hover:text-paper transition-colors"><?= v2_te('Venue') ?></a>
            <?php endif; ?>
            <?php if (! empty($activity['faqs'])): ?>
                <a href="#faq" class="shrink-0 px-4 py-2 rounded-full hover:bg-ink hover:text-paper transition-colors"><?= v2_te('FAQ') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- MAIN CONTENT + BOOKING                                          -->
<!-- ============================================================ -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12 lg:py-16">
    <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start">

        <!-- LEFT CONTENT -->
        <div class="lg:col-span-8 space-y-12">

            <!-- trust strip -->
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="ticket bg-paper border-2 border-ink rounded-2xl p-5">
                    <p class="font-display text-2xl font-700"><?= v2_te('Instant QR') ?></p>
                    <p class="mt-1 text-ink-soft"><?= v2_te('You get your ticket by email straight away.') ?></p>
                </div>
                <div class="ticket bg-paper border-2 border-ink rounded-2xl p-5">
                    <p class="font-display text-2xl font-700"><?= v2_te('Secure payment') ?></p>
                    <p class="mt-1 text-ink-soft"><?= v2_te('Quick confirmation and a clear booking.') ?></p>
                </div>
                <div class="ticket bg-paper border-2 border-ink rounded-2xl p-5">
                    <p class="font-display text-2xl font-700"><?= v2_te('Support') ?></p>
                    <p class="mt-1 text-ink-soft"><?= v2_te('You have all the details before you arrive.') ?></p>
                </div>
            </div>

            <!-- about + facts panel -->
            <section id="despre" class="scroll-mt-40">
                <div class="flex items-end justify-between gap-4 mb-6">
                    <div>
                        <h2 class="font-display text-4xl sm:text-5xl font-700 leading-none mt-2"><?= v2_te('What to expect') ?></h2>
                    </div>
                </div>

                <div class="grid md:grid-cols-5 gap-6">
                    <div class="md:col-span-3 text-lg leading-8 text-ink-soft">
                        <?php if (! empty($activity['description'])): ?>
                            <article class="prose-custom max-w-none">
                                <?= $activity['description'] ?>
                            </article>
                        <?php elseif (! empty($activity['short_description'])): ?>
                            <p><?= htmlspecialchars($activity['short_description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="md:col-span-2">
                        <div class="bg-paper-2 rounded-3xl border border-ink/10 p-5">
                            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft"><?= v2_te('At a glance') ?></p>
                            <dl class="mt-4 space-y-3 text-sm">
                                <?php if (! empty($activity['category'])): ?>
                                    <div class="flex justify-between gap-4 border-b border-ink/10 pb-3"><dt class="text-ink-soft"><?= v2_te('Category') ?></dt><dd class="font-700"><?= htmlspecialchars($activity['category']['name']) ?></dd></div>
                                <?php endif; ?>
                                <?php if (! empty($activity['difficulty_level'])): ?>
                                    <div class="flex justify-between gap-4 border-b border-ink/10 pb-3"><dt class="text-ink-soft"><?= v2_te('Difficulty') ?></dt><dd class="font-700"><?= htmlspecialchars($difficultyLabels[$activity['difficulty_level']] ?? $activity['difficulty_level']) ?></dd></div>
                                <?php endif; ?>
                                <?php
                                $envFlags = [];
                                if (! empty($activity['flags']['is_indoor'])) $envFlags[] = v2_t('Indoor');
                                if (! empty($activity['flags']['is_outdoor'])) $envFlags[] = v2_t('Outdoor');
                                if (! empty($envFlags)):
                                    ?>
                                    <div class="flex justify-between gap-4 border-b border-ink/10 pb-3"><dt class="text-ink-soft"><?= v2_te('Setting') ?></dt><dd class="font-700"><?= htmlspecialchars(implode(' / ', $envFlags)) ?></dd></div>
                                <?php endif; ?>
                                <?php if ($activity['age_min'] !== null || $activity['age_max'] !== null): ?>
                                    <div class="flex justify-between gap-4 border-b border-ink/10 pb-3">
                                        <dt class="text-ink-soft"><?= v2_te('Age') ?></dt>
                                        <dd class="font-700">
                                            <?php
                                            if ($activity['age_min'] !== null && $activity['age_max'] !== null) {
                                                echo v2_te('{min}–{max} years', ['min' => (int) $activity['age_min'], 'max' => (int) $activity['age_max']]);
                                            } elseif ($activity['age_min'] !== null) {
                                                echo v2_te('from {age} years', ['age' => (int) $activity['age_min']]);
                                            } else {
                                                echo v2_te('up to {age} years', ['age' => (int) $activity['age_max']]);
                                            }
                                            ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (! empty($activity['languages_offered'])): ?>
                                    <div class="flex justify-between gap-4 border-b border-ink/10 pb-3"><dt class="text-ink-soft"><?= v2_te('Languages') ?></dt><dd class="font-700 uppercase"><?= htmlspecialchars(implode(' · ', (array) $activity['languages_offered'])) ?></dd></div>
                                <?php endif; ?>
                                <div class="flex justify-between gap-4"><dt class="text-ink-soft"><?= v2_te('Access') ?></dt><dd class="font-700"><?= v2_te('By booking') ?></dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>

            <!-- gallery -->
            <?php if (! empty($bookingBootstrap['gallery'])): ?>
                <section id="galerie" class="scroll-mt-40">
                    <div class="flex items-end justify-between gap-4 mb-6">
                        <div>
                            <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= v2_te('Gallery') ?></p>
                            <h2 class="font-display text-4xl sm:text-5xl font-700 leading-none mt-2"><?= v2_te('Get a feel for it before you book') ?></h2>
                        </div>
                        <button @click="openGallery(0)" class="hidden sm:inline-flex px-4 py-2 rounded-full border-2 border-ink text-sm font-700 hover:bg-ink hover:text-paper transition-colors"><?= v2_te('Open the gallery') ?></button>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php foreach (array_slice($bookingBootstrap['gallery'], 0, 5) as $idx => $img): ?>
                            <button @click="openGallery(<?= $idx ?>)"
                                    class="<?= $idx === 0 ? 'col-span-2 row-span-2 aspect-[4/3]' : 'aspect-square' ?> relative rounded-2xl overflow-hidden border-2 border-ink group">
                                <img src="<?= htmlspecialchars($img['src']) ?>" alt="<?= htmlspecialchars($img['alt']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- included / not-included / requirements -->
            <?php if (! empty($activity['included_items']) || ! empty($activity['not_included']) || ! empty($activity['requirements'])): ?>
                <section class="scroll-mt-40">
                    <div class="mb-6">
                        <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= v2_te('What is included') ?></p>
                        <h2 class="font-display text-4xl sm:text-5xl font-700 leading-none mt-2"><?= v2_te('Practical details') ?></h2>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <?php if (! empty($activity['included_items'])): ?>
                            <div class="soft-card p-5">
                                <h3 class="font-display text-xl font-700"><?= v2_te('Included') ?></h3>
                                <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                    <?php foreach ($activity['included_items'] as $item): ?>
                                        <li>✓ <?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($activity['not_included'])): ?>
                            <div class="soft-card p-5">
                                <h3 class="font-display text-xl font-700"><?= v2_te('Not included') ?></h3>
                                <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                    <?php foreach ($activity['not_included'] as $item): ?>
                                        <li>× <?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($activity['requirements'])): ?>
                            <div class="soft-card p-5">
                                <h3 class="font-display text-xl font-700"><?= v2_te('Requirements') ?></h3>
                                <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                    <?php foreach ($activity['requirements'] as $item): ?>
                                        <li>• <?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- program -->
            <?php if (! empty($activity['schedule'])): ?>
                <section id="program" class="scroll-mt-40">
                    <div class="grid lg:grid-cols-2 gap-5">
                        <div class="bg-forest text-paper rounded-[2rem] p-6 sm:p-8 overflow-hidden relative">
                            <div class="absolute inset-0 opacity-10 bg-dotgrid-light"></div>
                            <div class="relative">
                                <p class="font-mono text-[11px] tracking-[.22em] text-paper/60"><?= v2_te('Opening hours') ?></p>
                                <h2 class="font-display text-4xl font-700 leading-none mt-2"><?= v2_te('Available times') ?></h2>
                                <dl class="mt-6 space-y-3">
                                    <?php
                                    $byDay = [];
                                    foreach ($activity['schedule'] as $row) {
                                        $byDay[$row['day_of_week']][] = $row['open_time'] . '–' . $row['close_time'];
                                    }
                                    for ($d = 1; $d <= 7; $d++):
                                        $intervals = $byDay[$d] ?? null;
                                        ?>
                                        <div class="flex items-center justify-between gap-4 border-b border-paper/15 pb-3 <?= $intervals ? '' : 'opacity-50' ?>">
                                            <dt><?= v2_e($dayLabels[$d]) ?></dt>
                                            <dd class="font-mono font-700"><?= $intervals ? implode(' · ', $intervals) : v2_te('Closed') ?></dd>
                                        </div>
                                    <?php endfor; ?>
                                </dl>
                                <p class="mt-5 text-paper/70 text-sm leading-6"><?= v2_te('Check the exact times available in the booking panel.') ?></p>
                            </div>
                        </div>

                        <div class="bg-paper-2 rounded-[2rem] p-6 sm:p-8 border border-ink/10">
                            <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= v2_te('Good to know') ?></p>
                            <h2 class="font-display text-4xl font-700 leading-none mt-2"><?= v2_te('Rules & tips') ?></h2>
                            <ul class="mt-6 space-y-3">
                                <?php if ($activity['booking_window']['lead_time_hours'] ?? null): ?>
                                    <li class="flex gap-3"><span class="mt-1 w-5 h-5 rounded-full bg-vermilion text-paper grid place-items-center text-[11px] font-700">✓</span><span><?= v2_te('Book at least {n} hours before the time slot.', ['n' => (int) $activity['booking_window']['lead_time_hours']]) ?></span></li>
                                <?php endif; ?>
                                <li class="flex gap-3"><span class="mt-1 w-5 h-5 rounded-full bg-vermilion text-paper grid place-items-center text-[11px] font-700">✓</span><span><?= v2_te('There is no need to print the ticket: the QR code on your phone is enough.') ?></span></li>
                                <?php if (! empty($activity['cancellation_policy'])): ?>
                                    <li class="flex gap-3"><span class="mt-1 w-5 h-5 rounded-full bg-vermilion text-paper grid place-items-center text-[11px] font-700">✓</span><span><?= htmlspecialchars($activity['cancellation_policy']) ?></span></li>
                                <?php endif; ?>
                                <?php if (! empty($activity['meeting_point'])): ?>
                                    <li class="flex gap-3"><span class="mt-1 w-5 h-5 rounded-full bg-vermilion text-paper grid place-items-center text-[11px] font-700">✓</span><span><?= htmlspecialchars($activity['meeting_point']) ?></span></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- SEO body -->
            <?php if (! empty($activity['seo']['body'])): ?>
                <section class="proseish max-w-none scroll-mt-40" id="ghid">
                    <p class="font-mono text-[11px] tracking-[.22em] text-vermilion uppercase mb-3"><?= v2_te('Guide') ?></p>
                    <?php if (! empty($activity['seo']['body_title'])): ?>
                        <h2><?= htmlspecialchars($activity['seo']['body_title']) ?></h2>
                    <?php endif; ?>
                    <?= strip_tags($activity['seo']['body'], '<p><h2><h3><ul><ol><li><a><strong><em><blockquote><br>') ?>
                </section>
            <?php endif; ?>

            <!-- location -->
            <?php if (! empty($activity['venue'])): ?>
                <section id="locatie" class="scroll-mt-40">
                    <div class="mb-6">
                        <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= v2_te('Venue') ?></p>
                        <h2 class="font-display text-4xl sm:text-5xl font-700 leading-none mt-2"><?= v2_te('Where the experience takes place') ?></h2>
                    </div>

                    <div class="grid lg:grid-cols-5 gap-5">
                        <div class="lg:col-span-3 rounded-[2rem] overflow-hidden border-2 border-ink min-h-[360px] bg-paper-2 relative">
                            <?php if (! empty($activity['venue']['lat']) && ! empty($activity['venue']['lng'])): ?>
                                <iframe
                                    src="https://www.google.com/maps?q=<?= urlencode($activity['venue']['lat'] . ',' . $activity['venue']['lng']) ?>&output=embed"
                                    class="w-full h-full min-h-[360px] border-0"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    title="<?= v2_te('Map of {name}', ['name' => $activity['venue']['name']]) ?>"></iframe>
                            <?php else: ?>
                                <div class="absolute inset-0 grid place-items-center text-center p-8">
                                    <div>
                                        <div class="mx-auto w-16 h-16 rounded-2xl bg-vermilion text-paper grid place-items-center rotate-[-4deg]">
                                            <svg viewBox="0 0 24 24" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s7-4.5 7-11a7 7 0 0 0-14 0c0 6.5 7 11 7 11Z"/><circle cx="12" cy="11" r="2.5"/></svg>
                                        </div>
                                        <p class="mt-4 font-display text-2xl font-700"><?= v2_te('Map unavailable') ?></p>
                                        <p class="mt-2 text-ink-soft max-w-sm"><?= v2_te('The coordinates of the venue are not set.') ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="lg:col-span-2 bg-paper border-2 border-ink rounded-[2rem] p-6">
                            <p class="font-mono text-[11px] tracking-[.22em] text-ink-soft"><?= v2_te('Address') ?></p>
                            <h3 class="font-display text-2xl font-700 mt-2"><?= htmlspecialchars($activity['venue']['name'] ?? '') ?></h3>
                            <?php if (! empty($activity['venue']['address'])): ?>
                                <p class="mt-3 text-ink-soft leading-7">
                                    <?= htmlspecialchars($activity['venue']['address']) ?>
                                    <?php if (! empty($activity['venue']['city'])): ?>, <?= htmlspecialchars($activity['venue']['city']) ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (! empty($activity['meeting_point'])): ?>
                                <p class="mt-3 text-sm text-ink-soft"><?= htmlspecialchars($activity['meeting_point']) ?></p>
                            <?php endif; ?>
                            <div class="mt-5 space-y-2 text-sm">
                                <?php if (! empty($activity['venue']['lat']) && ! empty($activity['venue']['lng'])): ?>
                                    <a href="https://maps.google.com/?q=<?= urlencode($activity['venue']['lat'] . ',' . $activity['venue']['lng']) ?>" target="_blank" class="flex items-center justify-between gap-3 px-4 py-3 rounded-2xl bg-paper-2 hover:bg-ink hover:text-paper transition-colors font-700"><?= v2_te('Open in Maps') ?> <span>→</span></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- FAQ -->
            <?php if (! empty($activity['faqs'])): ?>
                <section id="faq" class="scroll-mt-40" x-data="{ openFaq: 0 }">
                    <div class="mb-6">
                        <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= v2_te('Frequently asked questions') ?></p>
                        <h2 class="font-display text-4xl sm:text-5xl font-700 leading-none mt-2"><?= v2_te('Everything you need to know') ?></h2>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($activity['faqs'] as $idx => $faq): ?>
                            <article class="bg-paper border-2 border-ink rounded-2xl overflow-hidden">
                                <button @click="openFaq = openFaq === <?= $idx ?> ? null : <?= $idx ?>" class="w-full flex items-center justify-between gap-4 text-left p-5">
                                    <span class="font-display text-xl font-700 leading-tight"><?= htmlspecialchars($faq['q'] ?? '') ?></span>
                                    <span class="shrink-0 w-9 h-9 rounded-full bg-ink text-paper grid place-items-center transition-transform" :class="openFaq === <?= $idx ?> && 'rotate-45'">+</span>
                                </button>
                                <div x-show="openFaq === <?= $idx ?>" x-collapse>
                                    <div class="px-5 pb-5 text-ink-soft leading-7"><?= htmlspecialchars($faq['a'] ?? '') ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- recommendation rails — 3 separate sections, each only when it has cards -->
            <?php foreach ($rails as $rail): ?>
                <section class="scroll-mt-40">
                    <div class="flex items-end justify-between gap-4 mb-6">
                        <div>
                            <p class="font-mono text-[11px] tracking-[.22em] text-vermilion"><?= htmlspecialchars($rail['kicker']) ?></p>
                            <h2 class="font-display text-3xl sm:text-4xl font-700 leading-tight mt-2"><?= htmlspecialchars($rail['title']) ?></h2>
                        </div>
                        <?php if (! empty($activity['city'])): ?>
                            <a href="/<?= htmlspecialchars($activity['city']['slug']) ?>" class="hidden sm:inline-flex text-vermilion font-700 underline-wobble"><?= v2_te('See everything in {city} →', ['city' => $activity['city']['name']]) ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($rail['cards'] as $rel): ?>
                            <a href="/activity/<?= htmlspecialchars($rel['slug']) ?>"
                               class="ticket ticket-lift bg-paper border-2 border-ink rounded-3xl overflow-hidden group">
                                <div class="h-40 relative overflow-hidden bg-paper-2">
                                    <?php if (! empty($rel['cover_image_url'])): ?>
                                        <img src="<?= htmlspecialchars($rel['cover_image_url']) ?>" alt="<?= htmlspecialchars($rel['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                    <?php else: ?>
                                        <div class="absolute inset-0 grid place-items-center text-ink-soft font-display text-lg p-3 text-center"><?= htmlspecialchars($rel['title']) ?></div>
                                    <?php endif; ?>
                                    <?php if (! empty($rel['category'])): ?>
                                        <span class="absolute top-3 left-3 px-3 py-1 rounded-full bg-paper text-ink text-xs font-700"><?= htmlspecialchars($rel['category']['name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-5">
                                    <h3 class="font-display text-xl font-700 leading-tight"><?= htmlspecialchars($rel['title']) ?></h3>
                                    <p class="mt-2 text-ink-soft text-sm">
                                        <?php if (! empty($rel['city'])): ?><?= htmlspecialchars($rel['city']['name']) ?> ·<?php endif; ?>
                                        <?= (int) $rel['duration_minutes'] ?> min
                                    </p>
                                    <p class="mt-3 font-700 text-vermilion"><?= ! empty($rel['cheapest_price_cents']) ? v2_te('from {price}', ['price' => v2_own_price_label($rel['cheapest_price_cents'], $rel['currency'] ?? null, $rel['cheapest_price_eur_cents'] ?? null)]) : v2_te('book online') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <!-- RIGHT BOOKING SIDEBAR -->
        <aside id="rezerva" class="lg:col-span-4 lg:sticky lg:top-40 scroll-mt-40">
            <div class="ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-ticket" style="--perf:100%">
                <div class="bg-ink text-paper p-6 relative">
                    <div class="absolute inset-0 opacity-10 bg-dotgrid-light"></div>
                    <div class="relative">
                        <p class="font-mono text-[10px] tracking-[.2em] text-paper/60"><?= v2_te('Book online') ?></p>
                        <h2 class="font-display text-4xl font-700 mt-2"><?= v2_te('Choose your tickets') ?></h2>
                        <p class="mt-2 text-paper/65"><?= v2_te('Instant confirmation. QR ticket by email.') ?></p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <!-- Date picker -->
                    <label class="block">
                        <span class="font-700 text-sm"><?= v2_te('Date') ?></span>
                        <input type="date" class="mt-2 w-full bg-paper-2 border-2 border-ink/15 focus:border-ink rounded-2xl px-4 py-3 focus:outline-none"
                               x-model="selectedDate" @change="loadSlots()"
                               :min="minDate" :max="maxDate">
                    </label>

                    <!-- Slot picker -->
                    <div class="mt-5">
                        <p class="font-700 text-sm mb-2"><?= v2_te('Time') ?></p>
                        <div x-show="loadingSlots" class="text-ink-soft text-sm py-3"><?= v2_te('Loading the available times…') ?></div>
                        <div x-show="! loadingSlots && slots.length === 0 && selectedDate" class="text-ink-soft text-sm py-3">
                            <?= v2_te('There are no times available on this day.') ?>
                        </div>
                        <div x-show="! loadingSlots && slots.length > 0" class="grid grid-cols-3 gap-2">
                            <template x-for="slot in slots" :key="slot.start_time">
                                <button type="button"
                                        @click="slot.is_bookable && (selectedSlot = slot.start_time)"
                                        :disabled="! slot.is_bookable"
                                        :class="{
                                            'bg-ink text-paper border-ink': selectedSlot === slot.start_time,
                                            'bg-paper-2 border-ink/10 hover:border-ink': selectedSlot !== slot.start_time && slot.is_bookable,
                                            'bg-paper-2/40 border-ink/10 text-ink-soft cursor-not-allowed line-through': ! slot.is_bookable,
                                        }"
                                        class="border-2 rounded-2xl py-3 text-sm font-700 transition-colors"
                                        x-text="slot.start_time.substring(0,5)">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Variants -->
                    <div class="mt-5" x-show="selectedSlot" x-collapse>
                        <p class="font-700 text-sm mb-2"><?= v2_te('Ticket type') ?></p>
                        <div class="space-y-2">
                            <template x-for="variant in variants" :key="variant.id">
                                <div class="flex items-center justify-between gap-3 bg-paper-2 rounded-2xl p-3 border border-ink/10">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-700 truncate" x-text="variant.name"></p>
                                        <p class="text-xs text-ink-soft" x-text="money(variant.price_cents)"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="decrement(variant.id)" class="w-8 h-8 rounded-full bg-paper border border-ink/20 font-700">−</button>
                                        <span class="w-6 text-center font-700" x-text="quantities[variant.id] || 0"></span>
                                        <button type="button" @click="increment(variant.id)" class="w-8 h-8 rounded-full bg-paper border border-ink/20 font-700">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-5 border-t-2 border-dashed border-ink/15 pt-5">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-ink-soft"><?= v2_te('Subtotal') ?></span>
                                <strong class="font-display text-3xl" x-text="money(totalCents)"></strong>
                            </div>
                            <p class="mt-1 text-xs text-ink-soft" x-text="participantsLabel"></p>

                            <button type="button" @click="submitBooking()"
                                    :disabled="! canSubmit"
                                    :class="canSubmit ? 'bg-vermilion text-paper hover:bg-vermilion-d' : 'bg-paper-2 text-ink-soft cursor-not-allowed'"
                                    class="mt-5 w-full flex items-center justify-center gap-2 px-5 py-4 rounded-full font-700 transition-colors">
                                <?= v2_te('Continue booking') ?>
                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-2xl bg-paper-2 p-3">
                                <p class="font-mono text-[9px] text-ink-soft tracking-widest"><?= v2_te('Duration') ?></p>
                                <p class="font-700"><?= (int) $activity['duration_minutes'] ?>m</p>
                            </div>
                            <div class="rounded-2xl bg-paper-2 p-3">
                                <p class="font-mono text-[9px] text-ink-soft tracking-widest"><?= v2_te('Group') ?></p>
                                <p class="font-700"><?= (int) ($activity['booking_window']['min_participants'] ?? 1) ?>–<?= (int) ($activity['booking_window']['max_participants'] ?? 10) ?></p>
                            </div>
                            <div class="rounded-2xl bg-paper-2 p-3">
                                <p class="font-mono text-[9px] text-ink-soft tracking-widest">QR</p>
                                <p class="font-700"><?= v2_te('Instant') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 bg-paper-2 rounded-3xl border border-ink/10 p-5">
                <p class="font-display text-2xl font-700"><?= v2_te('Want to give this experience as a gift?') ?></p>
                <p class="mt-2 text-ink-soft leading-7"><?= v2_te('Send a gift card valid for this activity or for any other experience on {site}.', ['site' => SITE_NAME]) ?></p>
                <a href="/gift-card" class="mt-4 inline-flex text-vermilion font-700 underline-wobble"><?= v2_te('Buy a gift card →') ?></a>
            </div>
        </aside>
    </div>
</section>

<!-- ============================================================ -->
<!-- MOBILE STICKY CTA                                               -->
<!-- ============================================================ -->
<div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-paper/95 backdrop-blur border-t border-ink/10 p-3">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs text-ink-soft"><?= v2_te('from') ?></p>
            <p class="font-display text-2xl font-700 leading-none"><?= v2_e($pricedFromCents($activity['cheapest_price_cents'] ?? null)) ?></p>
        </div>
        <a href="#rezerva" class="px-6 py-3 rounded-full bg-vermilion text-paper font-700"><?= v2_te('Book') ?></a>
    </div>
</div>

<!-- ============================================================ -->
<!-- GALLERY MODAL                                                   -->
<!-- ============================================================ -->
<div x-show="galleryOpen" x-cloak class="fixed inset-0 z-[70] bg-ink/95 text-paper p-4 sm:p-8" @keydown.escape.window="galleryOpen=false">
    <div class="max-w-6xl mx-auto h-full flex flex-col">
        <div class="flex items-center justify-between gap-4 mb-4">
            <p class="font-mono text-xs tracking-[.2em] text-paper/50"><?= v2_te('Photo gallery') ?></p>
            <button @click="galleryOpen=false" class="w-11 h-11 rounded-full bg-paper text-ink grid place-items-center font-700">×</button>
        </div>
        <div class="relative flex-1 rounded-[2rem] overflow-hidden bg-paper/5 border border-paper/10" x-show="gallery.length > 0">
            <img :src="gallery[galleryIndex]?.src" :alt="gallery[galleryIndex]?.alt" class="absolute inset-0 w-full h-full object-contain">
            <button @click="prevImage()" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-paper text-ink grid place-items-center font-700">←</button>
            <button @click="nextImage()" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-paper text-ink grid place-items-center font-700">→</button>
        </div>
        <div class="mt-4 no-bar flex gap-2 overflow-x-auto">
            <template x-for="(img, index) in gallery" :key="img.src">
                <button @click="galleryIndex=index" :class="galleryIndex===index ? 'ring-2 ring-vermilion' : 'opacity-60'" class="shrink-0 w-24 h-16 rounded-xl overflow-hidden">
                    <img :src="img.src" :alt="img.alt" class="w-full h-full object-cover">
                </button>
            </template>
        </div>
    </div>
</div>

</main>

<!-- ============================================================ -->
<!-- ALPINE COMPONENT                                                -->
<!-- ============================================================ -->
<script>
function activityPage(bootstrap) {
    const today = new Date();
    const todayStr = today.toISOString().substring(0, 10);
    const maxDate = new Date(today);
    maxDate.setDate(maxDate.getDate() + (bootstrap.window.max_advance_days || 60));

    return {
        activityId: bootstrap.activity_id,
        slug: bootstrap.slug,
        title: bootstrap.title,
        coverImage: bootstrap.cover_image,
        venueName: bootstrap.venue_name,
        venueCity: bootstrap.venue_city,
        organizerId: bootstrap.organizer_id,
        durationMinutes: bootstrap.duration_minutes,
        commissionRate: bootstrap.commission_rate || 0,
        commissionMode: bootstrap.commission_mode || 'included',
        variants: bootstrap.variants,
        window: bootstrap.window,
        gallery: bootstrap.gallery || [],
        currency: bootstrap.currency || 'EUR',
        locale: bootstrap.locale || 'en-GB',
        labels: bootstrap.labels || {},

        selectedDate: todayStr,
        selectedSlot: null,
        slots: [],
        loadingSlots: false,
        quantities: {},

        galleryOpen: false,
        galleryIndex: 0,

        minDate: todayStr,
        maxDate: maxDate.toISOString().substring(0, 10),

        init() {
            this.loadSlots();
            this.$watch('galleryOpen', v => document.body.style.overflow = v ? 'hidden' : '');
        },

        async loadSlots() {
            this.selectedSlot = null;
            this.slots = [];
            this.loadingSlots = true;
            try {
                const url = `/api/proxy.php?action=activity.slots&slug=${encodeURIComponent(this.slug)}&date=${encodeURIComponent(this.selectedDate)}`;
                const r = await fetch(url);
                const j = await r.json();
                this.slots = (j?.data?.slots) || [];
            } catch (e) {
                console.error('slots load failed', e);
                this.slots = [];
            } finally {
                this.loadingSlots = false;
            }
        },

        increment(variantId) {
            const variant = this.variants.find(v => v.id === variantId);
            if (! variant) return;
            const current = this.quantities[variantId] || 0;
            const max = Math.max(1, variant.max_per_order || 10);
            const remainingSeats = this.currentSlotRemaining - this.totalSeatsUsed + (current * (variant.capacity_share || 1));
            const maxByCapacity = Math.floor(remainingSeats / (variant.capacity_share || 1));
            this.quantities[variantId] = Math.min(current + 1, max, Math.max(0, maxByCapacity));
        },

        decrement(variantId) {
            const current = this.quantities[variantId] || 0;
            this.quantities[variantId] = Math.max(0, current - 1);
        },

        get currentSlot() { return this.slots.find(s => s.start_time === this.selectedSlot); },
        get currentSlotRemaining() { return this.currentSlot ? (this.currentSlot.capacity_remaining || 0) : 0; },
        get totalSeatsUsed() { return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.capacity_share || 1)), 0); },
        get totalCents() { return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.price_cents || 0)), 0); },

        get participantsLabel() {
            const n = this.totalSeatsUsed;
            if (! n) return this.labels.choose || '';
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            if (n < min) return (this.labels.min || '').replace('{n}', min);
            if (n > max) return (this.labels.max || '').replace('{n}', max);
            return n === 1 ? (this.labels.one || '') : (this.labels.many || '').replace('{n}', n);
        },

        get canSubmit() {
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            return this.selectedSlot
                && this.totalSeatsUsed >= min
                && this.totalSeatsUsed <= max
                && this.totalSeatsUsed <= this.currentSlotRemaining
                && this.totalCents > 0;
        },

        money(cents) {
            const v = (cents || 0) / 100;
            return new Intl.NumberFormat(this.locale, { style: 'currency', currency: this.currency, maximumFractionDigits: Number.isInteger(v) ? 0 : 2 }).format(v);
        },

        openGallery(index) {
            if (this.gallery.length === 0) return;
            this.galleryIndex = index;
            this.galleryOpen = true;
        },
        nextImage() { this.galleryIndex = (this.galleryIndex + 1) % this.gallery.length; },
        prevImage() { this.galleryIndex = (this.galleryIndex - 1 + this.gallery.length) % this.gallery.length; },

        submitBooking() {
            if (! this.canSubmit) return;
            if (typeof BileteOnlineCart === 'undefined' || typeof BileteOnlineCart.addActivityItem !== 'function') {
                // Hard fallback if cart.js isn't loaded yet — page reload usually fixes this
                alert(this.labels.noCart || '');
                return;
            }

            const slot = this.currentSlot;
            if (! slot) return;

            const activityData = {
                id: this.activityId,
                slug: this.slug,
                title: this.title,
                image: this.coverImage,
                venue: this.venueName,
                city: this.venueCity,
                organizer_id: this.organizerId,
                duration_minutes: this.durationMinutes,
                // Carry organizer commission settings through to the cart so
                // calculateItemCommission can apply them — 2% added on top
                // (or whatever's configured) shows up as a separate fee line
                // in the cart + checkout summary instead of being silently
                // missing.
                commission_rate: this.commissionRate,
                commission_mode: this.commissionMode,
                currency: this.currency,
            };

            // Push one cart line per variant that has at least one participant.
            // Same (activity, slot, variant) → one cart line; counts roll up.
            //
            // Only increment `pushed` if addActivityItem actually persisted
            // (returns the saved cart). When its guard rejects (missing id,
            // empty date, etc.) it now returns `null` — we must NOT redirect
            // to /cart in that case, otherwise the user lands on an empty
            // cart page and can't tell why.
            let pushed = 0;
            for (const variant of this.variants) {
                const qty = this.quantities[variant.id] || 0;
                if (qty <= 0) continue;

                const result = BileteOnlineCart.addActivityItem(
                    activityData,
                    {
                        id: variant.id,
                        name: variant.name,
                        price_cents: variant.price_cents,
                        capacity_share: variant.capacity_share || 1,
                        // the basket reads the line's currency from the variant; without it it assumes euro
                        currency: variant.currency || this.currency,
                    },
                    {
                        date: this.selectedDate,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                    },
                    qty
                );
                if (result) pushed++;
            }

            if (pushed === 0) {
                // Either every variant had qty=0 (shouldn't happen — canSubmit
                // would be false) OR addActivityItem rejected the input. In
                // both cases the user needs to see something instead of a
                // silent no-op. The console.warn from addActivityItem already
                // explains WHY for the second case.
                alert(this.labels.notAdded || '');
                return;
            }

            // Redirect to cart so the user can review + continue to checkout.
            window.location.href = bootstrap.cartUrl || '/cart';
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
