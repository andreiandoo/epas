<?php
/**
 * Categories catalog: /categorii (v2 design).
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
$intentHubs = [
    ['Timp', 'Activități azi', 'Pentru decizii rapide și activități disponibile imediat.', 'activitati-azi'],
    ['Timp', 'Activități weekend', 'Idei pentru weekend: familie, grupuri, cupluri.', 'activitati-weekend'],
    ['Vreme', 'Zile ploioase', 'Indoor: muzee, escape rooms, ateliere și expoziții.', 'activitati-zile-ploioase'],
    ['Vreme', 'Zile caniculare', 'Activități răcoroase, indoor sau de seară.', 'activitati-zile-caniculare'],
    ['Buget', 'Sub 50 lei', 'Experiențe accesibile, potrivite pentru ieșiri spontane.', 'activitati-sub-50-lei'],
    ['Public', 'Activități pentru copii', 'Idei pentru copii și familie: muzee, ateliere, parcuri.', 'activitati-copii'],
    ['Public', 'Activități pentru cupluri', 'Experiențe pentru doi: tururi, ateliere, date nights.', 'activitati-cuplu'],
    ['Ocazie', 'Zi de naștere', 'Idei pentru grupuri, copii, cupluri și cadouri.', 'activitati-zi-de-nastere'],
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
    ['Care este diferența dintre categorie și intenție?', 'Categoria descrie ce este activitatea: escape room, muzeu, parc, atelier. Intenția descrie de ce o cauți: pentru copii, pentru weekend, când plouă, sub 50 lei sau pentru o zi de naștere.'],
    ['O activitate poate apărea în mai multe pagini?', 'Da. O activitate are o categorie principală, dar poate apărea în pagini după public, oraș, vreme, buget sau ocazie.'],
    ['Cum aleg rapid o activitate potrivită?', 'Începe cu orașul, apoi alege contextul: copii, indoor, outdoor, azi, weekend sau buget. Dacă știi exact ce vrei, mergi direct la categoria principală.'],
    ['De ce sunt importante paginile oraș + categorie?', 'Utilizatorii caută local: escape rooms Brașov, muzee București, activități copii Cluj. Aceste combinații ajută la SEO și la descoperire rapidă.'],
    ['Pot cumpăra bilete direct din categorie?', 'Da. Paginile de categorie afișează activitățile disponibile, prețurile și butoanele directe către pagina activității sau coș.'],
];

// ------------------------------------------------------------------ page
$searchQuery = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$supportEmail = defined('SUPPORT_EMAIL') ? (string) SUPPORT_EMAIL : '';

$pageTitleRaw = 'Toate categoriile de activități — ' . SITE_NAME;
$pageDescription = 'Explorează toate categoriile de activități disponibile pe bilete.online: escape rooms, muzee, parcuri de distracții, parcuri de aventură, natură, peșteri, ateliere, copii, familie, cupluri și grupuri.';
$canonicalUrl = SITE_URL . '/categorii';
$ogImage = v2_asset('img/cat-escape-rooms.webp');
$structuredData = [];
if ($categories) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitleRaw,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'inLanguage' => 'ro-RO',
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
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Categorii', 'item' => $canonicalUrl],
    ],
];

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
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Categorii</span></nav>
        <p class="ct-kicker">Toate categoriile · activități · bilete online</p>
        <h1 class="ct-h" id="ct-h">Ce vrei să faci?</h1>
        <p class="ct-lead">Explorează activități după categorie, public, vreme, buget sau ocazie. De la escape rooms și muzee până la parcuri de aventură, peșteri, rezervații, ateliere și experiențe pentru familie.</p>

        <form class="ct-search" id="ct-form" action="/categorii" method="get" role="search">
          <label class="sr" for="ct-q">Caută categorii</label>
          <input id="ct-q" name="q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="Caută: escape room, muzeu, copii, indoor, weekend..." value="<?= v2_e($searchQuery) ?>">
          <button type="submit" aria-label="Arată categoriile găsite"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status" id="ct-status" role="status"></p>
        <ul class="ct-chips" aria-label="Caută după intenție">
          <?php foreach ($liveHubs as [, $hubTitle, , $hubSlug]): ?><li><a href="/<?= v2_e($hubSlug) ?>"><?= v2_e($hubTitle) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <?php if ($categories): ?>
      <div class="ct-art" aria-hidden="true">
        <div class="ct-art-card">
          <p class="kicker">Category map</p>
          <p class="ct-art-h">Dintr-o idee vagă într-o activitate concretă.</p>
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
        <div><p class="kicker">Rezultate</p><h2 id="ct-title" tabindex="-1">Categorii principale</h2></div>
        <?php if ($categories): ?><p id="ct-count" aria-live="polite"><?= count($categories) ?> din <?= count($categories) ?> categorii afișate</p><?php endif; ?>
      </div>

      <?php if (!$categories): ?>
      <div class="ct-none">
        <span class="ct-none-ic"><?= v2_ic('list') ?></span>
        <p>Categoriile încă nu sunt configurate.</p>
        <?php if ($supportEmail !== ''): ?><p class="cg-none-mail">Reveniți în curând sau scrie-ne la <a href="mailto:<?= v2_e($supportEmail) ?>"><?= v2_e($supportEmail) ?></a>.</p><?php endif; ?>
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
            <span class="cg-badges"><span>Categorie</span><span><?= $cat['count'] > 0 ? v2_e(v2_num($cat['count'], 'activitate', 'activități')) : 'în curând' ?></span></span>
          </a>
          <div class="cg-body">
            <h3><a href="<?= v2_e($cat['href']) ?>"><?= v2_e($cat['name']) ?></a></h3>
            <?php if ($cat['desc'] !== ''): ?><p class="cg-desc"><?= v2_e($cat['desc']) ?></p><?php endif; ?>
            <?php if ($cat['subs']): ?>
            <ul class="cg-subs" aria-label="Subcategorii <?= v2_e($cat['name']) ?>">
              <?php foreach (array_slice($cat['subs'], 0, 6) as $sub): ?><li><a href="<?= v2_e($sub['href']) ?>"><?= v2_e($sub['name']) ?></a></li><?php endforeach; ?>
              <?php if (count($cat['subs']) > 6): ?><li><a class="is-more" href="<?= v2_e($cat['href']) ?>" aria-label="Încă <?= count($cat['subs']) - 6 ?> subcategorii în <?= v2_e($cat['name']) ?>">+<?= count($cat['subs']) - 6 ?></a></li><?php endif; ?>
            </ul>
            <?php endif; ?>
            <div class="cg-foot"><a href="<?= v2_e($cat['href']) ?>">Vezi categoria<?= v2_ic('arrow-right') ?></a></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="ct-none" id="ct-none" hidden>
        <span class="ct-none-ic"><?= v2_ic('magnifying-glass') ?></span>
        <p>Nicio categorie nu se potrivește căutării.</p>
        <button class="btn btn-ghost" type="button" id="ct-reset">Arată toate categoriile</button>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== TAXONOMY ===================== -->
  <section class="sec cg-tax" aria-labelledby="cg-tax-h">
    <div class="wrap">
      <div class="cg-tax-intro">
        <p class="kicker">Taxonomie</p>
        <h2 id="cg-tax-h">Cum sunt organizate activitățile.</h2>
        <p>O activitate are o categorie reală, dar și un public, un context, un buget și o vreme — toate folosite pentru a o găsi rapid.</p>
      </div>
      <ul class="cg-tax-grid">
        <?php foreach ([
            ['Category', 'Ce este activitatea?', ['Escape room', 'Muzeu / expoziție', 'Parc de aventură', 'Peșteră / natură', 'Atelier creativ'], false],
            ['Audience', 'Pentru cine este?', ['Copii', 'Familie', 'Cupluri', 'Grupuri', 'Corporate'], true],
            ['Context', 'Când / de ce o alegi?', ['Weekend', 'Azi / mâine', 'Zi ploioasă', 'Sub 50 lei', 'Zi de naștere'], false],
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
        <p class="kicker">Huburi SEO</p>
        <h2 id="ct-hubs-h">Caută după intenție, nu doar după tip.</h2>
        <p>Mulți utilizatori nu știu exact ce categorie vor. Caută „ceva pentru copii”, „ce facem azi”, „activități când plouă” sau „ceva ieftin”.</p>
      </div>
      <ul class="ct-hub-list">
        <?php foreach ($liveHubs as [$hubKicker, $hubTitle, $hubText, $hubSlug]): ?>
        <li><a class="ct-hub" href="/<?= v2_e($hubSlug) ?>"><small><?= v2_e($hubKicker) ?></small><b><?= v2_e($hubTitle) ?></b><span><?= v2_e($hubText) ?></span><?= v2_ic('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== CATEGORIES × CITIES ===================== -->
  <section class="sec cg-local" aria-labelledby="cg-local-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap cg-local-grid">
      <div>
        <p class="kicker">SEO local</p>
        <h2 id="cg-local-h">Categorii × Orașe.</h2>
        <p>Combinațiile generează pagini relevante pentru căutări locale: „escape rooms Brașov”, „muzee Cluj”, „activități copii București”.</p>
        <a class="btn btn-light" href="/orase">Vezi toate orașele<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php if ($exampleCities): ?>
      <div class="cg-links">
        <div class="cg-links-top"><small>Exemple</small><h3>Linkuri interne</h3></div>
        <ul>
          <?php foreach ($exampleCities as $city): ?>
          <li><a href="<?= v2_e($city['href']) ?>"><strong><?= v2_e($city['href']) ?></strong><span>activități în <?= v2_e($city['name']) ?></span><?= v2_ic('arrow-right') ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec ct-faq" aria-labelledby="ct-faq-h">
    <div class="wrap ct-faq-grid">
      <div><p class="kicker">FAQ</p><h2 id="ct-faq-h">Cum alegi categoria potrivită?</h2></div>
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
          <p class="kicker">Descoperă</p>
          <h2 id="ct-final-h">Alege categoria. Găsește activitatea.</h2>
          <p>Începe cu un tip sau cu o intenție: copii, weekend, indoor, outdoor, buget sau oraș.</p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/orase">Alege orașul<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/activitati-azi">Activități azi</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
