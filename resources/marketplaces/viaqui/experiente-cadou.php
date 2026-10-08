<?php
/**
 * Gift experience finder: /gift-experiences (v2 design).
 *
 * A calculator over every published activity: who the gift is for, how many people, the budget, what they like,
 * where and indoors or out. gift-finder.js scores the activities on those answers (hard limits first: city, setting,
 * kid-friendly for a child, room for the group, far over budget; then the matches, each said in words), lets the
 * visitor pick one or more, estimates what the group would pay (from the ticket variants) and suggests the gift card
 * value that covers it. "Continue to the gift
 * card" stores the pick for this browser and opens /gift-card#cumpara, whose configurator starts from it.
 *
 * Buying stays where it is today on /gift-card: core's gift card endpoints are not safe to expose yet (see the
 * sprint-4 notes), so the configurator turns the pick into a prefilled request on the contact page.
 *
 * Top to bottom: compact hero (facts, three steps), calculator (criteria, results, the pick), FAQ, final CTA.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/promoted.php';

// ------------------------------------------------------------------ every published activity (same cache as the region page)
$gfFirst = api_cached_many(['p1' => ['key' => 'v2_all_activities_p1', 'endpoint' => '/activities', 'params' => ['per_page' => 50, 'page' => 1], 'ttl' => 300]]);
$gfPages = [$gfFirst['p1'] ?? []];
$gfLast = min(6, (int) ($gfFirst['p1']['data']['pagination']['last_page'] ?? 1));
if ($gfLast > 1) {
    $gfJobs = [];
    for ($p = 2; $p <= $gfLast; $p++) {
        $gfJobs['p' . $p] = ['key' => 'v2_all_activities_p' . $p, 'endpoint' => '/activities', 'params' => ['per_page' => 50, 'page' => $p], 'ttl' => 300];
    }
    $gfPages = array_merge($gfPages, array_values(api_cached_many($gfJobs)));
}
$items = [];
$cities = [];
foreach ($gfPages as $gfPage) {
    foreach ((array) ($gfPage['data']['items'] ?? []) as $a) {
        if (!is_array($a) || !($n = v2_activity($a))) {
            continue;
        }
        $flags = is_array($a['flags'] ?? null) ? $a['flags'] : [];
        $citySlug = (string) ($a['city']['slug'] ?? '');
        $items[] = [
            'slug' => $n['slug'],
            'title' => $n['title'],
            'href' => $n['href'],
            'city' => $n['city'],
            'citySlug' => $citySlug,
            'cat' => $n['cat'],
            'catName' => $n['catName'],
            'cents' => isset($a['cheapest_price_cents']) ? (int) $a['cheapest_price_cents'] : null,
            'minutes' => (int) ($a['duration_minutes'] ?? 0),
            'dur' => $n['dur'],
            'cap' => (int) ($a['capacity_per_slot'] ?? 0),
            'image' => $n['image'],
            'sub' => navFlatName($a['subtitle'] ?? '') ?: navFlatName($a['short_description'] ?? ''),
            'indoor' => !empty($flags['is_indoor']),
            'outdoor' => !empty($flags['is_outdoor']),
            'kid' => !empty($flags['is_kid_friendly']),
            'accessible' => !empty($flags['is_accessible']),
            'featured' => !empty($flags['is_featured']),
            'promoted' => $n['promoted'],
            'interests' => array_values(array_filter(array_map(fn ($i) => is_array($i) ? (string) ($i['slug'] ?? '') : '', (array) ($a['interests'] ?? [])))),
            'travelers' => array_values(array_filter(array_map(fn ($t) => is_array($t) ? (string) ($t['slug'] ?? '') : '', (array) ($a['traveler_types'] ?? [])))),
        ];
        if ($citySlug !== '' && $n['city'] !== '') {
            $cities[$citySlug] = ['name' => $n['city'], 'n' => ($cities[$citySlug]['n'] ?? 0) + 1];
        }
    }
}
uasort($cities, fn ($a, $b) => [$b['n'], $a['name']] <=> [$a['n'], $b['name']]);
$count = count($items);

// Ticket variants (price, places each covers, order limits, ages) turn "price × people" into what a group would
// really pay. The list has none, so the details of the first 40 activities are read in parallel, cached for an hour;
// any other activity falls back to its lowest price per person.
usort($items, fn ($a, $b) => [(int) $b['promoted'], (int) $b['featured'], $a['title']] <=> [(int) $a['promoted'], (int) $a['featured'], $b['title']]);
$gfDetailJobs = [];
foreach (array_slice($items, 0, 40) as $it) {
    $gfDetailJobs[$it['slug']] = ['key' => 'v2_gift_variants_' . $it['slug'], 'endpoint' => '/activities/' . rawurlencode($it['slug']), 'params' => [], 'ttl' => 3600];
}
$gfDetails = $gfDetailJobs ? api_cached_many($gfDetailJobs) : [];
foreach ($items as &$it) {
    $variants = $gfDetails[$it['slug']]['data']['activity']['variants'] ?? $gfDetails[$it['slug']]['data']['variants'] ?? [];
    $it['v'] = [];
    foreach ((array) $variants as $v) {
        if (is_array($v) && isset($v['price_cents'])) {
            $it['v'][] = [(int) $v['price_cents'], max(1, (int) ($v['capacity_share'] ?? 1)), (int) ($v['min_per_order'] ?? 0), (int) ($v['max_per_order'] ?? 0),
                isset($v['min_age']) ? (int) $v['min_age'] : null, isset($v['max_age']) ? (int) $v['max_age'] : null];
        }
    }
}
unset($it);
$priced = array_filter(array_column($items, 'cents'), fn ($c) => $c !== null && $c > 0);
$fromLei = $priced ? (int) round(min($priced) / 100) : null;

// ------------------------------------------------------------------ the questions
$who = [
    ['partener', v2_t('Partner'), 'heart', 2],
    ['prieten', v2_t('Friend'), 'users-three', 2],
    ['copil', v2_t('A child'), 'star', 2],
    ['familie', v2_t('Family'), 'users-three', 4],
    ['echipa', v2_t('Colleagues / team'), 'buildings', 6],
    ['parinti', v2_t('Parents'), 'heart', 2],
];
// What they like: activity categories and interests that count as a match.
$likes = [
    ['mister', v2_t('Mystery & puzzles'), ['escape-rooms'], ['mister']],
    ['aventura', v2_t('Adventure & adrenaline'), ['parcuri-de-aventura', 'parcuri-de-distractii'], ['aventura', 'adrenalina']],
    ['cultura', v2_t('Culture & history'), ['muzee-expozitii', 'cultura-arta', 'tururi-experiente-turistice'], ['cultura-istorie']],
    ['creativ', v2_t('Creative workshops'), ['ateliere-experiente-creative'], ['arta', 'fotografie']],
    ['natura', v2_t('Nature & outdoors'), ['natura-outdoor', 'acvarii-zoo-animale'], ['natura-outdoor']],
    ['invatare', v2_t('Discovering something new'), ['educatie-invatare-experientiala', 'muzee-expozitii'], ['educational']],
    ['relaxare', v2_t('Relaxation'), [], ['wellness']],
    ['gustari', v2_t('Food & drink'), [], ['gastronomie']],
];
// only the tastes some activity answers, so no chip leads nowhere
$likes = array_values(array_filter($likes, function ($l) use ($items) {
    foreach ($items as $it) {
        if (in_array($it['cat'], $l[2], true) || array_intersect($it['interests'], $l[3]) || ($l[0] === 'natura' && $it['outdoor'])) {
            return true;
        }
    }
    return false;
}));
$budgets = [[0, v2_t('Any budget')], [100, v2_t('Up to {amount}', ['amount' => v2_money(100)])], [250, v2_t('Up to {amount}', ['amount' => v2_money(250)])], [500, v2_t('Up to {amount}', ['amount' => v2_money(500)])], [1000, v2_t('Up to {amount}', ['amount' => v2_money(1000)])]];
$amounts = [50, 100, 150, 250, 500, 1000]; // the values the gift card configurator offers

$faqs = [
    [v2_t('Does the recipient have to choose the recommended experience?'), v2_t('No. The gift card has a value, not a fixed activity: the experiences chosen here go into the card message as a suggestion, and the recipient can use the value for any eligible activity on viaqui.com.')],
    [v2_t('How is the suggested value worked out?'), v2_t('For each chosen experience we work out what the group would pay with the best value tickets available (including those for several people or for children), add up the amounts and suggest the lowest card value that covers them. The final price depends on the date, the time and the ticket the recipient chooses.')],
    [v2_t('What happens if the experience costs less than the card?'), v2_t('The difference can stay available on the card until it expires, according to the gift card rules.')],
    [v2_t('Where do the recommendations come from?'), v2_t('From the activities published on viaqui.com right now: the price, the city, how many people fit in a time slot, whether it suits children, indoors or outdoors, plus the category and the interests set by the organiser.')],
];

// ------------------------------------------------------------------ SEO
$pageTitle = v2_t('Gift experiences: the gift calculator');
$pageDescription = $count
    ? v2_t('Tell us who the gift is for, the budget and what they like: we recommend experiences to give from the {activities} on viaqui.com, and the right gift card value.', ['activities' => v2_num($count, 'activity', 'activities')])
    : v2_t('Tell us who the gift is for, the budget and what they like: we recommend experiences to give and the right gift card value.');
$canonicalUrl = SITE_URL . '/gift-experiences';
$ogImage = v2_asset('img/cat-familie-copii.webp');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => v2_t('Gift experience calculator'),
    'url' => $canonicalUrl,
    'applicationCategory' => 'LifestyleApplication',
    'operatingSystem' => 'Any',
    'inLanguage' => v2_locale(),
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => SITE_CURRENCY],
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
]];

$gfArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['gift-finder.css'];
$v2Scripts = ['gift-finder.js'];
$v2HeaderOverlay = true;

// the finder's data: every activity, the tastes, the card values (no HTML inside, read with JSON.parse)
$gfData = [
    'items' => $items,
    'cities' => array_map(fn ($slug, $c) => ['slug' => $slug, 'name' => $c['name']], array_keys($cities), $cities),
    'likes' => array_map(fn ($l) => ['key' => $l[0], 'label' => $l[1], 'cats' => $l[2], 'interests' => $l[3]], $likes),
    'who' => array_map(fn ($w) => ['key' => $w[0], 'label' => $w[1], 'people' => $w[3]], $who),
    'amounts' => $amounts,
];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="gf-hero" aria-labelledby="gf-h">
    <?= $gfArches ?>
    <svg class="gf-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="gf-hero-in">
      <div>
        <p class="gf-kicker"><span class="gf-kicker-ic" aria-hidden="true"><?= v2_ic('gift') ?></span><?= v2_te('Gift calculator') ?></p>
        <h1 class="gf-h" id="gf-h"><?= v2_te('Find the right experience to give.') ?></h1>
        <p class="gf-lead"><?= v2_te('Tell us who the gift is for: we show you the experiences that suit them and work out the gift card value that covers them.') ?></p>
        <?php if ($count): ?>
        <ul class="gf-facts" aria-label="<?= v2_te('In short') ?>">
          <li><?= v2_e(v2_num($count, 'experience', 'experiences')) ?></li>
          <?php if (count($cities) > 1): ?><li><?= v2_te('in {cities}', ['cities' => v2_num(count($cities), 'city', 'cities')]) ?></li><?php endif; ?>
          <?php if ($fromLei !== null): ?><li><?= v2_te('from {price} per person', ['price' => v2_money($fromLei)]) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="gf-cta">
          <a class="btn btn-light" href="#calculator"><?= v2_te('Start') ?><?= v2_ic('arrow-right') ?></a>
          <a class="gf-link" href="/gift-card#cumpara"><?= v2_te('Already know the value? Go straight to the gift card') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
      <ol class="gf-steps" aria-label="<?= v2_te('How it works') ?>">
        <li><span>1</span><div><b><?= v2_te('Say who it is for') ?></b><small><?= v2_te('Budget, how many people, what they like, where.') ?></small></div></li>
        <li><span>2</span><div><b><?= v2_te('Choose the experiences') ?></b><small><?= v2_te('Each recommendation says why it fits.') ?></small></div></li>
        <li><span>3</span><div><b><?= v2_te('Send the gift card') ?></b><small><?= v2_te('With the value worked out and the experiences in the message.') ?></small></div></li>
      </ol>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== CALCULATOR ===================== -->
  <section class="gf-main" id="calculator" aria-labelledby="gf-calc-h">
    <div class="wrap gf-layout">
      <div class="gf-panel">
      <form class="gf-form" id="gf-form" aria-labelledby="gf-calc-h" novalidate>
        <div class="gf-form-head">
          <h2 id="gf-calc-h"><?= v2_te('Who is the gift for?') ?></h2>
          <button class="link-btn gf-reset" type="button" id="gf-reset"><?= v2_te('Reset') ?></button>
        </div>

        <fieldset class="gf-q">
          <legend><?= v2_te('Who receives it') ?></legend>
          <div class="gf-chips" data-q="who">
            <?php foreach ($who as [$key, $label, $icon, $people]): ?>
            <button type="button" data-value="<?= v2_e($key) ?>" aria-pressed="false"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="gf-q">
          <legend><?= v2_te('How many people are going') ?></legend>
          <div class="gf-stepper">
            <button type="button" id="gf-less" aria-label="<?= v2_te('Fewer people') ?>">−</button>
            <output id="gf-people" aria-live="polite">2</output>
            <button type="button" id="gf-more" aria-label="<?= v2_te('More people') ?>"><?= v2_ic('plus') ?></button>
            <small id="gf-people-note"><?= v2_te('people, including the recipient') ?></small>
          </div>
        </fieldset>

        <fieldset class="gf-q">
          <legend><?= v2_te('Total budget') ?></legend>
          <div class="gf-chips is-compact" data-q="budget">
            <?php foreach ($budgets as $bi => [$value, $label]): ?>
            <button type="button" data-value="<?= $value ?>" aria-pressed="<?= $bi === 0 ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php if ($likes): ?>
        <fieldset class="gf-q">
          <legend><?= v2_t('What they like <small>(you can pick several)</small>') ?></legend>
          <div class="gf-chips is-compact" data-q="likes" data-multi>
            <?php foreach ($likes as [$key, $label]): ?>
            <button type="button" data-value="<?= v2_e($key) ?>" aria-pressed="false"><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endif; ?>

        <div class="gf-q-row">
          <?php if (count($cities) > 1): ?>
          <div class="gf-q">
            <label class="gf-label" for="gf-city"><?= v2_te('Where') ?></label>
            <select class="select" id="gf-city">
              <option value=""><?= v2_te('Anywhere') ?></option>
              <?php foreach ($cities as $slug => $c): ?><option value="<?= v2_e($slug) ?>"><?= v2_e($c['name']) ?> (<?= $c['n'] ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="gf-q">
            <label class="gf-label" for="gf-setting"><?= v2_te('Indoors or outdoors') ?></label>
            <select class="select" id="gf-setting">
              <option value=""><?= v2_te('Either') ?></option>
              <option value="indoor"><?= v2_te('Indoors') ?></option>
              <option value="outdoor"><?= v2_te('Outdoors') ?></option>
            </select>
          </div>
        </div>
      </form>
      <span class="gf-thumb" id="gf-thumb" aria-hidden="true"></span>
      </div>

      <div class="gf-results">
        <div class="gf-results-head">
          <div><p class="kicker"><?= v2_te('Recommendations') ?></p><h2 id="gf-res-h" tabindex="-1"><?= v2_te('Experiences to give') ?></h2></div>
          <p class="gf-count" id="gf-count" aria-live="polite"><?= v2_e(v2_num($count, 'experience', 'experiences')) ?></p>
        </div>
        <ol class="gf-list" id="gf-list" aria-labelledby="gf-res-h">
          <?php foreach ($items as $i => $it): ?>
          <li class="gf-item" data-slug="<?= v2_e($it['slug']) ?>">
            <a class="gf-media" href="<?= v2_e($it['href']) ?>" tabindex="-1" aria-hidden="true"><?= $it['image'] ? v2_photo([$it['image'], 0, 0, '']) : v2_fallback($it['title'], $i) ?><?= !empty($it['promoted']) ? v2_promoted_tag() : '' ?></a>
            <div class="gf-body">
              <p class="gf-match" data-match hidden></p>
              <p class="gf-cat"><?= v2_e(implode(' · ', array_filter([$it['catName'], $it['city']]))) ?></p>
              <h3><a href="<?= v2_e($it['href']) ?>"><?= v2_e($it['title']) ?></a></h3>
              <?php if ($it['sub'] !== ''): ?><p class="gf-sub"><?= v2_e($it['sub']) ?></p><?php endif; ?>
              <ul class="gf-why" data-why></ul>
            </div>
            <div class="gf-side">
              <p class="gf-price"><?php if ($it['cents'] === null): ?><b><?= v2_te('Price on request') ?></b><?php elseif ($it['cents'] === 0): ?><b><?= v2_te('Free') ?></b><?php else: ?><?= v2_t('<small>from</small><b>{price}</b><small>per person</small>', ['price' => v2_e(v2_money((int) round($it['cents'] / 100)))]) ?><?php endif; ?></p>
              <p class="gf-total" data-total hidden></p>
              <button class="btn btn-ghost gf-pick" type="button" data-pick aria-pressed="false"><?= v2_ic('plus', 'ic gf-ic-add') ?><?= v2_ic('check', 'ic gf-ic-on') ?><span><?= v2_te('Add to the gift') ?></span></button>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
        <div class="gf-none" id="gf-none" <?= $items ? 'hidden' : '' ?>>
          <span class="gf-none-ic"><?= v2_ic('gift') ?></span>
          <h3><?= $items ? v2_te('No experience matches all the criteria.') : v2_te('No experiences are published yet.') ?></h3>
          <p><?= $items ? v2_te('Drop one of the criteria or go straight for a gift card: the recipient chooses the experience themselves.') : v2_te('A gift card is still the safest choice: the recipient chooses the experience themselves.') ?></p>
          <div class="gf-none-cta">
            <?php if ($items): ?>
            <button class="btn btn-ghost" type="button" data-relax="city"><?= v2_te('Anywhere') ?></button>
            <button class="btn btn-ghost" type="button" data-relax="budget"><?= v2_te('No budget limit') ?></button>
            <button class="btn btn-ghost" type="button" data-relax="likes"><?= v2_te('Anything they like') ?></button>
            <?php endif; ?>
            <a class="btn btn-primary" href="/gift-card#cumpara"><?= v2_te('Gift card with no experience chosen') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </div>
      </div>
    </div>

    <!-- the pick: a bar at the bottom of the screen once something is chosen -->
    <aside class="gf-tray" id="gf-tray" aria-labelledby="gf-tray-h" hidden>
      <div class="wrap gf-tray-in">
        <div class="gf-tray-sum">
          <h2 class="gf-tray-h" id="gf-tray-h"><?= v2_te('Your gift') ?></h2>
          <p id="gf-tray-text" aria-live="polite"></p>
          <ul class="gf-tray-list" id="gf-tray-list"></ul>
        </div>
        <div class="gf-tray-go">
          <p class="gf-tray-value"><small><?= v2_te('Suggested gift card') ?></small><b id="gf-tray-value">—</b></p>
          <a class="btn btn-primary" id="gf-go" href="/gift-card#cumpara"><?= v2_te('Continue to the gift card') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </aside>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec gf-faq" aria-labelledby="gf-faq-h">
    <div class="wrap gf-faq-grid">
      <div><p class="kicker"><?= v2_te('FAQ') ?></p><h2 id="gf-faq-h"><?= v2_te('About gift experiences') ?></h2></div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="gf-final" aria-labelledby="gf-final-h">
    <div class="wrap">
      <div class="gf-final-in">
        <?= $gfArches ?>
        <div>
          <p class="kicker"><?= v2_te('Not sure what to choose?') ?></p>
          <h2 id="gf-final-h"><?= v2_te('Let them choose.') ?></h2>
          <p><?= v2_te('A gift card with no fixed experience can be used for any eligible activity on viaqui.com.') ?></p>
        </div>
        <a class="btn btn-light" href="/gift-card#cumpara"><?= v2_te('Gift card') ?><?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
<script type="application/json" id="gf-data"><?= json_encode($gfData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
