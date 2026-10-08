<?php
/**
 * Categories catalog: /categories (v2 design).
 *
 * Parent categories from the shell's cached `/events/categories` (local WebP photos where the site has them) with
 * all their subcategories. The search filters the server-rendered cards (categories.js). Intent hubs link only to
 * intents the API knows, checked with the same cached call city-intent.php makes, so a hub never leads to a 404.
 *
 * Top to bottom: hero (search, intent chips, category map), category cards, taxonomy explainer, intent hubs,
 * categories × cities, FAQ, final CTA. Hero, search, hubs, FAQ and CTA styles come from cities.css.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// ------------------------------------------------------------------ categories + all their subcategories
$rawParents = [];
$rawChildren = [];
foreach ((array) ($v2NavData('cats')['categories'] ?? []) as $raw) {
    if (!is_array($raw) || empty($raw['slug'])) {
        continue;
    }
    if (empty($raw['parent_id'])) {
        $rawParents[$raw['slug']] = $raw;
    } else {
        $rawChildren[$raw['parent_id']][] = $raw;
    }
}

// Activity counts: the categories endpoint reports none, but every activity in the listing names its category (a
// parent or one of its subcategories). Same cached listing pages as the operator profile.
$parentOf = [];
foreach ($rawParents as $parentSlug => $raw) {
    $parentOf[$parentSlug] = $parentSlug;
    foreach ($rawChildren[$raw['id'] ?? 0] ?? [] as $child) {
        if (!empty($child['slug'])) {
            $parentOf[$child['slug']] = $parentSlug;
        }
    }
}
$activityCounts = [];
for ($listPage = 1; $listPage <= 4; $listPage++) {
    $listResp = api_cached("operator_activities_p{$listPage}", fn () => api_get('/activities', ['per_page' => 50, 'page' => $listPage]), 600);
    foreach ((array) ($listResp['data']['items'] ?? []) as $a) {
        $activityCat = is_array($a) && is_array($a['category'] ?? null) ? (string) ($a['category']['slug'] ?? '') : '';
        if (isset($parentOf[$activityCat])) {
            $activityCounts[$parentOf[$activityCat]] = ($activityCounts[$parentOf[$activityCat]] ?? 0) + 1;
        }
    }
    if ($listPage >= (int) ($listResp['data']['pagination']['last_page'] ?? 1)) {
        break;
    }
}

$defaultEmojis = ['🎫', '🎡', '🖼️', '🧗', '🌲', '🎨', '🎭', '🎪', '🏛️', '🌳'];
$categories = [];
foreach ($V2NAV['categories'] as $ci => $cat) {
    $raw = $rawParents[$cat['slug']] ?? [];
    $subs = [];
    foreach ($rawChildren[$raw['id'] ?? 0] ?? [] as $child) {
        $childName = navFlatName($child['name'] ?? '') ?: (string) ($child['slug'] ?? '');
        if ($childName !== '' && !empty($child['slug'])) {
            $child['parent_slug'] = $cat['slug'];
            $subs[] = ['name' => $childName, 'href' => '/' . bo_short_category_slug($child)];
        }
    }
    $categories[] = [
        'name' => $cat['name'] ?: $cat['slug'],
        'slug' => $cat['slug'],
        'href' => $cat['href'],
        'desc' => navFlatName($raw['description'] ?? '') ?: $cat['desc'],
        'image' => $cat['image'],
        'srcset' => $cat['srcset'],
        'emoji' => (string) ($raw['icon_emoji'] ?? '') ?: $defaultEmojis[$ci % count($defaultEmojis)],
        'count' => max($cat['count'], $activityCounts[$cat['slug']] ?? 0),
        'subs' => array_slice($subs, 0, 12),
        'sort' => (int) ($raw['sort_order'] ?? 0),
    ];
}
usort($categories, fn ($a, $b) => $a['sort'] <=> $b['sort']);

// ------------------------------------------------------------------ intent hubs that exist
// [kicker, title, text, the intent's slug in the API, the page's address]. Four intents have an English address in
// .htaccess; the other four are still served under the API slug.
$intentHubs = [
    [v2_t('Time'), v2_t('Things to do today'), v2_t('For quick decisions and activities you can book right away.'), 'activitati-azi', '/activitati-azi'],
    [v2_t('Time'), v2_t('Weekend ideas'), v2_t('Ideas for the weekend: families, groups, couples.'), 'activitati-weekend', '/weekend-ideas'],
    [v2_t('Weather'), v2_t('Rainy days'), v2_t('Indoors: museums, escape rooms, workshops and exhibitions.'), 'activitati-zile-ploioase', '/rainy-days'],
    [v2_t('Weather'), v2_t('Hot days'), v2_t('Cool places, indoor activities and things to do in the evening.'), 'activitati-zile-caniculare', '/activitati-zile-caniculare'],
    [v2_t('Budget'), v2_t('On a budget'), v2_t('Affordable experiences, good for a spontaneous outing.'), 'activitati-sub-50-lei', '/activitati-sub-50-lei'],
    [v2_t('Who'), v2_t('With kids'), v2_t('Ideas for children and families: museums, workshops, parks.'), 'activitati-copii', '/with-kids'],
    [v2_t('Who'), v2_t('For couples'), v2_t('Experiences for two: tours, workshops, date nights.'), 'activitati-cuplu', '/for-couples'],
    [v2_t('Occasion'), v2_t('Birthdays'), v2_t('Ideas for groups, children, couples and gifts.'), 'activitati-zi-de-nastere', '/activitati-zi-de-nastere'],
];
$hubJobs = [];
foreach ($intentHubs as [, , , $hubSlug]) {
    // same cache entry as the global intent page (city-intent.php)
    $hubJobs[$hubSlug] = ['key' => 'intent_' . $hubSlug . '_global_p1', 'endpoint' => '/intents/' . urlencode($hubSlug) . '/events', 'params' => ['page' => 1, 'per_page' => 24], 'ttl' => 300];
}
$hubChecks = api_cached_many($hubJobs);
$liveHubs = array_values(array_filter($intentHubs, fn ($hub) => !empty($hubChecks[$hub[3]]['success'])));
if (!$liveHubs) {
    $liveHubs = $intentHubs; // nothing answered: the API is down, not every intent gone
}

$exampleCities = array_slice($V2NAV['citiesList'], 0, 4);
$faqs = [
    [v2_t('What is the difference between a category and an intent?'), v2_t('The category says what the activity is: escape room, museum, park, workshop. The intent says why you are looking for it: for children, for the weekend, for a rainy day, on a budget or for a birthday.')],
    [v2_t('Can an activity appear on more than one page?'), v2_t('Yes. An activity has one main category, but it can also appear on pages by audience, city, weather, budget or occasion.')],
    [v2_t('How do I quickly choose the right activity?'), v2_t('Start with the city, then pick the context: kids, indoor, outdoor, today, weekend or budget. If you know exactly what you want, go straight to the main category.')],
    [v2_t('Why do city and category pages matter?'), v2_t('People search locally: escape rooms in Lisbon, museums in Vienna, things to do with kids in Prague. These pages take you straight to what is on in one place.')],
    [v2_t('Can I buy tickets straight from a category?'), v2_t('Yes. Category pages show the activities available, their prices and direct buttons to the activity page or the basket.')],
];

// ------------------------------------------------------------------ page
$searchQuery = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$supportEmail = defined('SUPPORT_EMAIL') ? (string) SUPPORT_EMAIL : '';

$pageTitleRaw = v2_t('All activity categories') . ' | ' . SITE_NAME;
$pageDescription = v2_t('Browse every category of activities on Viaqui: escape rooms, museums, amusement parks, adventure parks, nature, caves, workshops, and ideas for children, families, couples and groups.');
$canonicalUrl = SITE_URL . '/categories';
$ogImage = v2_asset('img/cat-escape-rooms.webp');
$structuredData = [];
if ($categories) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitleRaw,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'inLanguage' => v2_locale(),
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($categories),
            'itemListElement' => array_map(fn ($pos, $cat) => [
                '@type' => 'ListItem',
                'position' => $pos + 1,
                'name' => $cat['name'],
                'url' => SITE_URL . $cat['href'],
            ], array_keys($categories), $categories),
        ],
    ];
}
$cgArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['cities.css', 'categories.css'];
$v2Scripts = ['categories.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="ct-hero" aria-labelledby="ct-h">
    <?= $cgArches ?>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div>
        <p class="ct-kicker"><?= v2_te('All categories · activities · online tickets') ?></p>
        <h1 class="ct-h" id="ct-h"><?= v2_te('What would you like to do?') ?></h1>
        <p class="ct-lead"><?= v2_te('Browse activities by category, audience, weather, budget or occasion. From escape rooms and museums to adventure parks, caves, nature reserves, workshops and family experiences.') ?></p>

        <form class="ct-search" id="ct-form" action="/categories" method="get" role="search">
          <label class="sr" for="ct-q"><?= v2_te('Search categories') ?></label>
          <input id="ct-q" name="q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="<?= v2_te('Search: escape room, museum, kids, indoor, weekend…') ?>" value="<?= v2_e($searchQuery) ?>">
          <button type="submit" aria-label="<?= v2_te('Show the categories found') ?>"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status" id="ct-status" role="status"></p>
        <ul class="ct-chips" aria-label="<?= v2_te('Search by intent') ?>">
          <?php foreach ($liveHubs as [, $hubTitle, , , $hubHref]): ?><li><a href="<?= v2_e($hubHref) ?>"><?= v2_e($hubTitle) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <?php if ($categories): ?>
      <div class="ct-art" aria-hidden="true">
        <div class="ct-art-card">
          <p class="kicker"><?= v2_te('Category map') ?></p>
          <p class="ct-art-h"><?= v2_te('From a vague idea to an actual activity.') ?></p>
          <div class="cg-map">
            <?php foreach (array_slice($categories, 0, 4) as $cat): ?>
            <a class="cg-tile" href="<?= v2_e($cat['href']) ?>" tabindex="-1"><span><?= v2_e($cat['emoji']) ?></span><b><?= v2_e($cat['name']) ?></b></a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== CATEGORY GRID ===================== -->
  <section class="sec cg-main" id="lista" aria-labelledby="ct-title">
    <div class="wrap">
      <div class="ct-head">
        <div><p class="kicker"><?= v2_te('Results') ?></p><h2 id="ct-title" tabindex="-1"><?= v2_te('Main categories') ?></h2></div>
        <?php if ($categories): ?><p id="ct-count" aria-live="polite"><?= v2_te('{shown} of {total} categories shown', ['shown' => count($categories), 'total' => count($categories)]) ?></p><?php endif; ?>
      </div>

      <?php if (!$categories): ?>
      <div class="ct-none">
        <span class="ct-none-ic"><?= v2_ic('list') ?></span>
        <p><?= v2_te('The categories are not set up yet.') ?></p>
        <?php if ($supportEmail !== ''): ?><p class="cg-none-mail"><?= v2_t('Come back soon, or write to us at <a href="mailto:{email}">{email}</a>.', ['email' => v2_e($supportEmail)]) ?></p><?php endif; ?>
      </div>
      <?php else: ?>
      <ul class="cg-grid" id="cg-grid">
        <?php foreach ($categories as $ci => $cat): ?>
        <li class="cg-card" data-q="<?= v2_e(implode(' ', array_merge([$cat['name'], $cat['desc']], array_column($cat['subs'], 'name')))) ?>">
          <a class="cg-top" href="<?= v2_e($cat['href']) ?>" tabindex="-1" aria-hidden="true">
            <?php if ($cat['image']): ?>
            <?= v2_photo([$cat['image'], 640, 800, ''], $cat['srcset'] ? ' srcset="' . v2_e($cat['srcset']) . '" sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 92vw"' : '') ?>
            <?php else: ?>
            <span class="cg-emoji"><?= v2_e($cat['emoji']) ?></span>
            <?php endif; ?>
            <span class="cg-badges"><span><?= v2_te('Category') ?></span><span><?= $cat['count'] > 0 ? v2_e(v2_num($cat['count'], 'activity', 'activities')) : v2_te('coming soon') ?></span></span>
          </a>
          <div class="cg-body">
            <h3><a href="<?= v2_e($cat['href']) ?>"><?= v2_e($cat['name']) ?></a></h3>
            <?php if ($cat['desc'] !== ''): ?><p class="cg-desc"><?= v2_e($cat['desc']) ?></p><?php endif; ?>
            <?php if ($cat['subs']): ?>
            <ul class="cg-subs" aria-label="<?= v2_te('Subcategories of {category}', ['category' => $cat['name']]) ?>">
              <?php foreach (array_slice($cat['subs'], 0, 6) as $sub): ?><li><a href="<?= v2_e($sub['href']) ?>"><?= v2_e($sub['name']) ?></a></li><?php endforeach; ?>
              <?php if (count($cat['subs']) > 6): ?><li><a class="is-more" href="<?= v2_e($cat['href']) ?>" aria-label="<?= v2_te('{n} more subcategories in {category}', ['n' => count($cat['subs']) - 6, 'category' => $cat['name']]) ?>">+<?= count($cat['subs']) - 6 ?></a></li><?php endif; ?>
            </ul>
            <?php endif; ?>
            <div class="cg-foot"><a href="<?= v2_e($cat['href']) ?>"><?= v2_te('View category') ?><?= v2_ic('arrow-right') ?></a></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="ct-none" id="ct-none" hidden>
        <span class="ct-none-ic"><?= v2_ic('magnifying-glass') ?></span>
        <p><?= v2_te('No category matches your search.') ?></p>
        <button class="btn btn-ghost" type="button" id="ct-reset"><?= v2_te('Show all categories') ?></button>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== TAXONOMY ===================== -->
  <section class="sec cg-tax" aria-labelledby="cg-tax-h">
    <div class="wrap">
      <div class="cg-tax-intro">
        <p class="kicker"><?= v2_te('How it is organised') ?></p>
        <h2 id="cg-tax-h"><?= v2_te('How activities are organised.') ?></h2>
        <p><?= v2_te('An activity has a category, and also an audience, a context, a budget and a kind of weather. All of them help you find it quickly.') ?></p>
      </div>
      <ul class="cg-tax-grid">
        <?php foreach ([
            [v2_t('Category'), v2_t('What is the activity?'), [v2_t('Escape room'), v2_t('Museum / exhibition'), v2_t('Adventure park'), v2_t('Cave / nature'), v2_t('Creative workshop')], false],
            [v2_t('Audience'), v2_t('Who is it for?'), [v2_t('Children'), v2_t('Families'), v2_t('Couples'), v2_t('Groups'), v2_t('Companies')], true],
            [v2_t('Context'), v2_t('When or why do you pick it?'), [v2_t('Weekend'), v2_t('Today / tomorrow'), v2_t('Rainy day'), v2_t('On a budget'), v2_t('Birthday')], false],
        ] as [$taxKicker, $taxTitle, $taxItems, $taxDark]): ?>
        <li class="cg-tax-card<?= $taxDark ? ' is-dark' : '' ?>">
          <small><?= v2_e($taxKicker) ?></small>
          <h3><?= v2_e($taxTitle) ?></h3>
          <ul><?php foreach ($taxItems as $taxItem): ?><li><?= v2_ic('check') ?><?= v2_e($taxItem) ?></li><?php endforeach; ?></ul>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== INTENT HUBS ===================== -->
  <section class="sec ct-hubs cg-hubs" aria-labelledby="ct-hubs-h">
    <div class="wrap ct-hubs-grid">
      <div class="ct-hubs-intro">
        <p class="kicker"><?= v2_te('By intent') ?></p>
        <h2 id="ct-hubs-h"><?= v2_te('Search by intent, not only by type.') ?></h2>
        <p><?= v2_te('Many people do not know which category they want. They look for “something for the kids”, “what shall we do today”, “things to do when it rains” or “something cheap”.') ?></p>
      </div>
      <ul class="ct-hub-list">
        <?php foreach ($liveHubs as [$hubKicker, $hubTitle, $hubText, , $hubHref]): ?>
        <li><a class="ct-hub" href="<?= v2_e($hubHref) ?>"><small><?= v2_e($hubKicker) ?></small><b><?= v2_e($hubTitle) ?></b><span><?= v2_e($hubText) ?></span><?= v2_ic('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== CATEGORIES × CITIES ===================== -->
  <section class="sec cg-local" aria-labelledby="cg-local-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap cg-local-grid">
      <div>
        <p class="kicker"><?= v2_te('Near you') ?></p>
        <h2 id="cg-local-h"><?= v2_te('Categories × Cities.') ?></h2>
        <p><?= v2_te('Each combination has its own page for local searches: “escape rooms in Lisbon”, “museums in Vienna”, “things to do with kids in Prague”.') ?></p>
        <a class="btn btn-light" href="/cities"><?= v2_te('See all cities') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <?php if ($exampleCities): ?>
      <div class="cg-links">
        <div class="cg-links-top"><small><?= v2_te('Examples') ?></small><h3><?= v2_te('City pages') ?></h3></div>
        <ul>
          <?php foreach ($exampleCities as $city): ?>
          <li><a href="<?= v2_e($city['href']) ?>"><strong><?= v2_e($city['href']) ?></strong><span><?= v2_te('things to do in {city}', ['city' => $city['name']]) ?></span><?= v2_ic('arrow-right') ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec ct-faq" aria-labelledby="ct-faq-h">
    <div class="wrap ct-faq-grid">
      <div><p class="kicker"><?= v2_te('FAQ') ?></p><h2 id="ct-faq-h"><?= v2_te('How do you choose the right category?') ?></h2></div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="ct-final" aria-labelledby="ct-final-h">
    <div class="wrap">
      <div class="ct-final-in">
        <?= $cgArches ?>
        <div>
          <p class="kicker"><?= v2_te('Discover') ?></p>
          <h2 id="ct-final-h"><?= v2_te('Pick the category. Find the activity.') ?></h2>
          <p><?= v2_te('Start with a type or with an intent: kids, weekend, indoor, outdoor, budget or city.') ?></p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/cities"><?= v2_te('Choose a city') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/activitati-azi"><?= v2_te('Things to do today') ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
