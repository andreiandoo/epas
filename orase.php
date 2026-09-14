<?php
/**
 * Cities catalog: /orase (v2 design).
 *
 * Every city the site features (the shell's cached `/locations/cities/featured`, with the provisional local photos),
 * most activities first; a curated static list keeps the page useful if the API returns none. Search and the region
 * filter run over the server-rendered cards (cities.js), so every city is in the HTML for crawlers.
 *
 * Top to bottom: hero (search with live suggestions, popular cities, discovery card), region filter + city cards,
 * city × intent hubs, A–Z index, FAQ, final CTA.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$ctKey = fn (string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($s, [
    'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
    'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
]))), '-');

// ------------------------------------------------------------------ cities
$rawCities = [];
foreach ((array) ($v2NavData('cities')['cities'] ?? []) as $raw) {
    if (is_array($raw) && !empty($raw['slug'])) {
        $rawCities[$raw['slug']] = $raw;
    }
}

$cities = [];
foreach ($V2NAV['citiesList'] as $city) {
    $raw = $rawCities[$city['slug']] ?? [];
    $region = $city['region'] !== '' ? $city['region'] : 'România';
    $cities[] = [
        'name' => $city['name'],
        'slug' => $city['slug'],
        'url' => $city['href'],
        'region' => $region,
        'regionKey' => $ctKey($region),
        'county' => is_array($raw['county'] ?? null) ? navFlatName($raw['county']['name'] ?? '') : '',
        'count' => $city['count'],
        'photo' => $city['photo'],
        'description' => navFlatName($raw['description'] ?? '') ?: 'Activități, experiențe și locuri de vizitat în ' . $city['name'] . '.',
    ];
}

// Static fallback so the page is never empty.
if (!$cities) {
    foreach ([
        ['București', 'bucuresti', 'Muntenia', 'Cel mai mare hub pentru muzee, escape rooms, indoor și evenimente speciale.'],
        ['Brașov', 'brasov', 'Transilvania', 'Activități urbane, natură și aventură.'],
        ['Cluj-Napoca', 'cluj-napoca', 'Transilvania', 'Activități pentru studenți, familii și grupuri.'],
        ['Iași', 'iasi', 'Moldova', 'Cultural, educativ și experiențe locale în nord-est.'],
        ['Timișoara', 'timisoara', 'Banat', 'Experiențe urbane, culturale și de weekend.'],
        ['Constanța', 'constanta', 'Dobrogea', 'Activități de litoral și sezoniere.'],
        ['Sibiu', 'sibiu', 'Transilvania', 'Cetatea + experiențe culturale și outdoor.'],
        ['Oradea', 'oradea', 'Crișana', 'Activități culturale și familie în vestul țării.'],
    ] as [$fbName, $fbSlug, $fbRegion, $fbDesc]) {
        $local = V2_CITY_PHOTOS[$fbSlug] ?? null;
        $cities[] = [
            'name' => $fbName, 'slug' => $fbSlug, 'url' => '/' . $fbSlug, 'region' => $fbRegion, 'regionKey' => $ctKey($fbRegion),
            'county' => '', 'count' => 0, 'description' => $fbDesc,
            'photo' => $local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null,
        ];
    }
}

$quickCities = array_slice($cities, 0, 6);
$topCities = array_slice($cities, 0, 3);

// Region filter: every region that has cities on the page, in the site's usual region order.
$regionCounts = array_count_values(array_column($cities, 'region'));
$regionOrder = array_flip(V2_REGIONS);
uksort($regionCounts, fn ($a, $b) => [$regionOrder[$a] ?? 99, $a] <=> [$regionOrder[$b] ?? 99, $b]);

// A–Z index, diacritics folded for grouping.
$alphabet = [];
foreach ($cities as $city) {
    $letter = strtoupper(substr($ctKey(mb_substr($city['name'], 0, 1)) ?: '#', 0, 1));
    $alphabet[$letter][] = $city;
}
ksort($alphabet);
foreach ($alphabet as &$letterCities) {
    usort($letterCities, fn ($a, $b) => strcmp($ctKey($a['name']), $ctKey($b['name'])));
}
unset($letterCities);

// City × intent hubs, anchored on the city with the most activities.
$topSlug = $cities[0]['slug'];
$topName = $cities[0]['name'];
$hubs = [
    ['Copii', "Activități copii în {$topName}", 'Muzee interactive, parcuri, ateliere și experiențe pentru familie.', "/{$topSlug}/activitati-copii"],
    ['Weekend', "Weekend în {$topName}", 'Idei rapide pentru weekend — copii, grupuri, cupluri.', "/{$topSlug}/activitati-weekend"],
    ['Indoor', "Indoor în {$topName}", 'Activități pentru zile reci sau ploioase.', "/{$topSlug}/activitati-indoor"],
    ['Buget', "Sub 50 lei în {$topName}", 'Activități accesibile, fără să golești portofelul.', "/{$topSlug}/activitati-sub-50-lei"],
    ['Escape', "Escape rooms în {$topName}", 'Mister, puzzle-uri și provocări contra cronometru.', "/{$topSlug}?category=escape-rooms#activitati"],
    ['Muzee', "Muzee în {$topName}", 'Artă, istorie, știință și expoziții temporare.', "/{$topSlug}?category=muzee-expozitii#activitati"],
];

$faqs = [
    ['Cum sunt selectate orașele afișate?', 'Afișăm orașele cu activități listate plus o curatare manuală pentru hub-urile populare. Pe măsură ce apar locații noi într-un oraș, acesta urcă automat în listă.'],
    ['Ce găsesc pe pagina unui oraș?', 'Activități populare, categorii locale (escape rooms, muzee, parcuri), opțiuni pentru copii, weekend, indoor și ghiduri editoriale.'],
    ['Cum funcționează combinațiile oraș + categorie?', 'Pagini ca /brasov/escape-rooms sau /cluj-napoca/activitati-copii sunt construite automat din taxonomia comună a platformei.'],
    ['Pot vedea activități aproape de mine?', 'Da, există o pagină dedicată /activitati-aproape-de-mine bazată pe locația GPS dacă o permiți, altfel folosește orașul tău preferat.'],
];

// ------------------------------------------------------------------ page
$searchQuery = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$activitiesOf = fn (int $n): string => $n > 0 ? v2_num($n, 'activitate', 'activități') : 'descoperă';

$pageTitleRaw = 'Toate orașele — bilete.online';
$pageDescription = 'Explorează activități în orașe din România: București, Brașov, Cluj, Iași, Timișoara, Constanța, Sibiu și multe altele. Bilete pentru escape rooms, muzee, parcuri și experiențe locale.';
$canonicalUrl = SITE_URL . '/orase';
$ogImage = v2_asset('img/dest-brasov.webp');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => count($cities),
        'itemListElement' => array_map(fn ($pos, $city) => [
            '@type' => 'ListItem',
            'position' => $pos + 1,
            'name' => $city['name'],
            'url' => SITE_URL . $city['url'],
        ], array_keys($cities), $cities),
    ],
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Orașe', 'item' => $canonicalUrl],
    ],
]];

$ctArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['cities.css'];
$v2Scripts = ['cities.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="ct-hero" aria-labelledby="ct-h">
    <?= $ctArches ?>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div>
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Orașe</span></nav>
        <p class="ct-kicker">Orașe · activități locale · experiențe</p>
        <h1 class="ct-h" id="ct-h">Alege orașul. Găsește ce ai de făcut.</h1>
        <p class="ct-lead">Explorează activități locale: escape rooms, muzee, parcuri, natură, ateliere, tururi și experiențe pentru copii, familie sau grupuri.</p>

        <form class="ct-search" id="ct-form" action="/orase" method="get" role="search">
          <label class="sr" for="ct-q">Caută oraș</label>
          <input id="ct-q" name="q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="Caută: București, Brașov, Cluj, Iași..." value="<?= v2_e($searchQuery) ?>">
          <button type="submit" aria-label="Arată orașele găsite"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status" id="ct-status" role="status"></p>
        <ul class="ct-chips" id="ct-quick" aria-label="Orașe populare">
          <?php foreach ($quickCities as $city): ?><li><a href="<?= v2_e($city['url']) ?>"><?= v2_e($city['name']) ?></a></li><?php endforeach; ?>
        </ul>
        <ul class="ct-chips" id="ct-suggest" aria-label="Orașe găsite" hidden></ul>
      </div>

      <div class="ct-art" aria-hidden="true">
        <div class="ct-art-card">
          <p class="kicker">Local discovery</p>
          <p class="ct-art-h">Orașul devine hub.</p>
          <div class="ct-map">
            <svg viewBox="0 0 500 300" preserveAspectRatio="none" focusable="false">
              <path class="ct-map-grid" d="M0 60H500M0 120H500M0 180H500M0 240H500M100 0V300M200 0V300M300 0V300M400 0V300"/>
              <path class="ct-map-route" d="M80 220 C150 90, 255 260, 330 115 S450 95, 430 220"/>
              <circle cx="80" cy="220" r="10" fill="#E43A33"/><circle cx="190" cy="118" r="10" fill="#1E5B48"/><circle cx="330" cy="115" r="10" fill="#F2A900"/><circle cx="430" cy="220" r="10" fill="#2D6CCD"/>
            </svg>
            <?php foreach ($topCities as $city): ?>
            <a class="ct-pin" href="<?= v2_e($city['url']) ?>" tabindex="-1"><b><?= v2_e($city['name']) ?></b><small><?= v2_e($activitiesOf($city['count'])) ?></small></a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== REGIONS + CITIES ===================== -->
  <section class="sec ct-main" id="lista" aria-labelledby="ct-title">
    <div class="wrap ct-layout">
      <aside class="ct-side" aria-label="Filtrează după regiune">
        <div class="ct-filter">
          <p class="kicker">Regiuni</p>
          <ul class="ct-regions">
            <li><button type="button" data-region="all" data-label="Toate orașele" aria-pressed="true">Toate regiunile<span><?= count($cities) ?></span></button></li>
            <?php foreach ($regionCounts as $regionName => $regionCount): ?>
            <li><button type="button" data-region="<?= v2_e($ctKey($regionName)) ?>" data-label="<?= v2_e($regionName) ?>" aria-pressed="false"><?= v2_e($regionName) ?><span><?= $regionCount ?></span></button></li>
            <?php endforeach; ?>
          </ul>
          <div class="ct-note">
            <b>Structură locală</b>
            <p>Fiecare oraș are pagini pentru categorii, public, buget, vreme și timp: azi, weekend, copii, indoor, sub 50 lei.</p>
          </div>
        </div>
      </aside>

      <div>
        <div class="ct-head">
          <div><p class="kicker">Orașe</p><h2 id="ct-title" tabindex="-1">Toate orașele</h2></div>
          <p id="ct-count" aria-live="polite"><?= count($cities) ?> din <?= count($cities) ?> orașe</p>
        </div>
        <ul class="ct-grid" id="ct-grid">
          <?php foreach ($cities as $ci => $city): ?>
          <li class="ct-card<?= $ci >= 12 ? ' is-extra' : '' ?>" data-region-key="<?= v2_e($city['regionKey']) ?>" data-county="<?= v2_e($city['county'] !== $city['name'] ? $city['county'] : '') ?>" data-q="<?= v2_e(implode(' ', [$city['name'], $city['region'], $city['county'], $city['description']])) ?>">
            <a class="ct-top" href="<?= v2_e($city['url']) ?>">
              <span class="ct-media"><?= $city['photo'] ? v2_photo([$city['photo'][0], 0, 0, '']) : v2_fallback($city['name'], $ci) ?></span>
              <span class="ct-over"><small><?= v2_e($city['region']) ?></small><h3><?= v2_e($city['name']) ?></h3></span>
              <?php if ($city['count'] > 0): ?><span class="ct-badge"><?= v2_e(v2_num($city['count'], 'activitate', 'activități')) ?></span><?php endif; ?>
            </a>
            <div class="ct-body">
              <p><?= v2_e($city['description']) ?></p>
              <ul class="ct-links" aria-label="<?= v2_e($city['name']) ?>: pagini locale">
                <li><a class="is-kids" href="<?= v2_e($city['url']) ?>/activitati-copii">Copii</a></li>
                <li><a href="<?= v2_e($city['url']) ?>/activitati-weekend">Weekend</a></li>
                <li><a href="<?= v2_e($city['url']) ?>/activitati-indoor">Indoor</a></li>
                <li><a class="is-main" href="<?= v2_e($city['url']) ?>">Vezi orașul<?= v2_ic('arrow-right') ?></a></li>
              </ul>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($cities) > 12): ?>
        <button class="btn btn-ghost ct-more" type="button" id="ct-more" hidden>Arată toate cele <?= v2_e(v2_num(count($cities), 'oraș', 'orașe')) ?><?= v2_ic('caret-down') ?></button>
        <?php endif; ?>
        <div class="ct-none" id="ct-none" hidden>
          <span class="ct-none-ic"><?= v2_ic('map-pin') ?></span>
          <p>Niciun oraș nu se potrivește căutării.</p>
          <button class="btn btn-ghost" type="button" id="ct-reset">Arată toate orașele</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== CITY × INTENT HUBS ===================== -->
  <section class="sec ct-hubs" aria-labelledby="ct-hubs-h">
    <div class="wrap ct-hubs-grid">
      <div class="ct-hubs-intro">
        <p class="kicker">City + intent</p>
        <h2 id="ct-hubs-h">Cele mai bune pagini locale nu sunt doar „orașe”.</h2>
        <p>Un utilizator caută mai specific: copii în Brașov, escape rooms în București, indoor în Cluj, ce să faci azi în Iași.</p>
      </div>
      <ul class="ct-hub-list">
        <?php foreach ($hubs as [$hubKicker, $hubTitle, $hubText, $hubUrl]): ?>
        <li><a class="ct-hub" href="<?= v2_e($hubUrl) ?>"><small><?= v2_e($hubKicker) ?></small><b><?= v2_e($hubTitle) ?></b><span><?= v2_e($hubText) ?></span><?= v2_ic('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== A–Z INDEX ===================== -->
  <section class="sec ct-az" aria-labelledby="ct-az-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap">
      <div class="ct-az-intro">
        <p class="kicker">Index orașe</p>
        <h2 id="ct-az-h">Index curat pentru crawl și utilizatori.</h2>
        <p>Pe lângă cardurile vizuale, structură textuală scalabilă pentru linkuri interne.</p>
      </div>
      <ul class="ct-letters">
        <?php foreach ($alphabet as $letter => $letterCities): ?>
        <li class="ct-letter">
          <h3><?= v2_e($letter) ?></h3>
          <ul><?php foreach ($letterCities as $city): ?><li><a href="<?= v2_e($city['url']) ?>"><?= v2_e($city['name']) ?></a></li><?php endforeach; ?></ul>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec ct-faq" aria-labelledby="ct-faq-h">
    <div class="wrap ct-faq-grid">
      <div><p class="kicker">FAQ</p><h2 id="ct-faq-h">Cum aleg orașul potrivit?</h2></div>
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
        <?= $ctArches ?>
        <div>
          <p class="kicker">Local discovery</p>
          <h2 id="ct-final-h">Începe cu orașul. Apoi alege experiența.</h2>
          <p>Fiecare oraș poate fi un hub pentru activități, categorii, locații, ghiduri și pagini SEO locale.</p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/categorii">Vezi categorii<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/operatori">Vezi locații</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
