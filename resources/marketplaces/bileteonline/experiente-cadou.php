<?php
/**
 * Gift experience finder: /experiente-cadou (v2 design).
 *
 * A calculator over every published activity: who the gift is for, how many people, the budget, what they like,
 * where and indoors or out. gift-finder.js scores the activities on those answers (hard limits first: city, setting,
 * kid-friendly for a child, room for the group, far over budget; then the matches, each said in words), lets the
 * visitor pick one or more, estimates what the group would pay (from the ticket variants) and suggests the gift card
 * value that covers it. "Continuă la cardul
 * cadou" stores the pick for this browser and opens /card-cadou#cumpara, whose configurator starts from it.
 *
 * Buying stays where it is today on /card-cadou: core's gift card endpoints are not safe to expose yet (see the
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
usort($items, fn ($a, $b) => [(int) $b['featured'], $a['title']] <=> [(int) $a['featured'], $b['title']]);
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
    ['partener', 'Partener / parteneră', 'heart', 2],
    ['prieten', 'Prieten / prietenă', 'users-three', 2],
    ['copil', 'Un copil', 'star', 2],
    ['familie', 'Familie', 'users-three', 4],
    ['echipa', 'Colegi / echipă', 'buildings', 6],
    ['parinti', 'Părinți', 'heart', 2],
];
// What they like: activity categories and interests that count as a match.
$likes = [
    ['mister', 'Mister & enigme', ['escape-rooms'], ['mister']],
    ['aventura', 'Aventură & adrenalină', ['parcuri-de-aventura', 'parcuri-de-distractii'], ['aventura', 'adrenalina']],
    ['cultura', 'Cultură & istorie', ['muzee-expozitii', 'cultura-arta', 'tururi-experiente-turistice'], ['cultura-istorie']],
    ['creativ', 'Ateliere creative', ['ateliere-experiente-creative'], ['arta', 'fotografie']],
    ['natura', 'Natură & aer liber', ['natura-outdoor', 'acvarii-zoo-animale'], ['natura-outdoor']],
    ['invatare', 'Să descopere ceva nou', ['educatie-invatare-experientiala', 'muzee-expozitii'], ['educational']],
    ['relaxare', 'Relaxare', [], ['wellness']],
    ['gustari', 'Gastronomie', [], ['gastronomie']],
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
$budgets = [[0, 'Oricât'], [100, 'Până la 100 lei'], [250, 'Până la 250 lei'], [500, 'Până la 500 lei'], [1000, 'Până la 1.000 lei']];
$amounts = [50, 100, 150, 250, 500, 1000]; // the values the gift card configurator offers

$faqs = [
    ['Destinatarul trebuie să aleagă experiența recomandată?', 'Nu. Cardul cadou are o valoare, nu o activitate fixă: experiențele alese aici ajung în mesajul cardului ca sugestie, iar destinatarul poate folosi valoarea la orice activitate eligibilă de pe bilete.online.'],
    ['Cum se calculează valoarea sugerată?', 'Pentru fiecare experiență aleasă calculăm cât ar plăti grupul cu cele mai avantajoase bilete disponibile (inclusiv cele pentru mai multe persoane sau pentru copii), adunăm sumele și propunem cea mai mică valoare de card care le acoperă. Prețul final depinde de data, ora și biletul ales de destinatar.'],
    ['Ce se întâmplă dacă experiența costă mai puțin decât cardul?', 'Diferența poate rămâne disponibilă pe card până la expirare, conform regulamentului cardului cadou.'],
    ['De unde vin recomandările?', 'Din activitățile publicate acum pe bilete.online: prețul, orașul, câte persoane intră într-un interval, dacă e potrivită pentru copii, în interior sau în aer liber, plus categoria și interesele marcate de organizator.'],
];

// ------------------------------------------------------------------ SEO
$pageTitleRaw = 'Experiențe cadou: calculatorul de cadouri — ' . SITE_NAME;
$pageDescription = 'Spune-ne pentru cine e cadoul, bugetul și ce îi place: îți recomandăm experiențe de dăruit' . ($count ? ' dintre cele ' . v2_num($count, 'activitate', 'activități') . ' de pe bilete.online' : '') . ' și valoarea potrivită a cardului cadou.';
$canonicalUrl = SITE_URL . '/experiente-cadou';
$ogImage = v2_asset('img/cat-familie-copii.webp');
$breadcrumbs = [['Acasă', '/'], ['Card cadou', '/card-cadou'], ['Experiențe cadou', '/experiente-cadou']];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'Calculator de experiențe cadou',
    'url' => $canonicalUrl,
    'applicationCategory' => 'LifestyleApplication',
    'operatingSystem' => 'Any',
    'inLanguage' => 'ro-RO',
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'RON'],
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]], $breadcrumbs, array_keys($breadcrumbs)),
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
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcHref]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcHref) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="gf-kicker"><span class="gf-kicker-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>Calculator de cadouri</p>
        <h1 class="gf-h" id="gf-h">Găsește experiența potrivită de dăruit.</h1>
        <p class="gf-lead">Spune-ne pentru cine e cadoul: îți arătăm experiențele care i se potrivesc și calculăm valoarea cardului cadou care le acoperă.</p>
        <?php if ($count): ?>
        <ul class="gf-facts" aria-label="Pe scurt">
          <li><?= v2_e(v2_num($count, 'experiență', 'experiențe')) ?></li>
          <?php if (count($cities) > 1): ?><li>în <?= v2_e(v2_num(count($cities), 'oraș', 'orașe')) ?></li><?php endif; ?>
          <?php if ($fromLei !== null): ?><li>de la <?= v2_e(v2_thousands($fromLei)) ?> lei / pers.</li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="gf-cta">
          <a class="btn btn-light" href="#calculator">Începe<?= v2_ic('arrow-right') ?></a>
          <a class="gf-link" href="/card-cadou#cumpara">Știi deja valoarea? Direct la cardul cadou<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
      <ol class="gf-steps" aria-label="Cum funcționează">
        <li><span>1</span><div><b>Spui pentru cine e</b><small>Buget, câte persoane, ce îi place, unde.</small></div></li>
        <li><span>2</span><div><b>Alegi experiențele</b><small>Fiecare recomandare spune de ce se potrivește.</small></div></li>
        <li><span>3</span><div><b>Trimiți cardul cadou</b><small>Cu valoarea calculată și experiențele în mesaj.</small></div></li>
      </ol>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== CALCULATOR ===================== -->
  <section class="gf-main" id="calculator" aria-labelledby="gf-calc-h">
    <div class="wrap gf-layout">
      <form class="gf-form" id="gf-form" aria-labelledby="gf-calc-h" novalidate>
        <div class="gf-form-head">
          <h2 id="gf-calc-h">Pentru cine e cadoul?</h2>
          <button class="link-btn gf-reset" type="button" id="gf-reset">Resetează</button>
        </div>

        <fieldset class="gf-q">
          <legend>Cine îl primește</legend>
          <div class="gf-chips" data-q="who">
            <?php foreach ($who as [$key, $label, $icon, $people]): ?>
            <button type="button" data-value="<?= v2_e($key) ?>" aria-pressed="false"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="gf-q">
          <legend>Câte persoane merg</legend>
          <div class="gf-stepper">
            <button type="button" id="gf-less" aria-label="Mai puține persoane">−</button>
            <output id="gf-people" aria-live="polite">2</output>
            <button type="button" id="gf-more" aria-label="Mai multe persoane"><?= v2_ic('plus') ?></button>
            <small id="gf-people-note">persoane, cu tot cu cel care primește</small>
          </div>
        </fieldset>

        <fieldset class="gf-q">
          <legend>Buget total</legend>
          <div class="gf-chips is-compact" data-q="budget">
            <?php foreach ($budgets as $bi => [$value, $label]): ?>
            <button type="button" data-value="<?= $value ?>" aria-pressed="<?= $bi === 0 ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php if ($likes): ?>
        <fieldset class="gf-q">
          <legend>Ce îi place <small>(poți alege mai multe)</small></legend>
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
            <label class="gf-label" for="gf-city">Unde</label>
            <select class="select" id="gf-city">
              <option value="">Oriunde</option>
              <?php foreach ($cities as $slug => $c): ?><option value="<?= v2_e($slug) ?>"><?= v2_e($c['name']) ?> (<?= $c['n'] ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="gf-q">
            <label class="gf-label" for="gf-setting">Unde se desfășoară</label>
            <select class="select" id="gf-setting">
              <option value="">Indiferent</option>
              <option value="indoor">În interior</option>
              <option value="outdoor">În aer liber</option>
            </select>
          </div>
        </div>
      </form>

      <div class="gf-results">
        <div class="gf-results-head">
          <div><p class="kicker">Recomandări</p><h2 id="gf-res-h" tabindex="-1">Experiențe de dăruit</h2></div>
          <p class="gf-count" id="gf-count" aria-live="polite"><?= v2_e(v2_num($count, 'experiență', 'experiențe')) ?></p>
        </div>
        <ol class="gf-list" id="gf-list" aria-labelledby="gf-res-h">
          <?php foreach ($items as $i => $it): ?>
          <li class="gf-item" data-slug="<?= v2_e($it['slug']) ?>">
            <a class="gf-media" href="<?= v2_e($it['href']) ?>" tabindex="-1" aria-hidden="true"><?= $it['image'] ? v2_photo([$it['image'], 0, 0, '']) : v2_fallback($it['title'], $i) ?></a>
            <div class="gf-body">
              <p class="gf-match" data-match hidden></p>
              <p class="gf-cat"><?= v2_e(implode(' · ', array_filter([$it['catName'], $it['city']]))) ?></p>
              <h3><a href="<?= v2_e($it['href']) ?>"><?= v2_e($it['title']) ?></a></h3>
              <?php if ($it['sub'] !== ''): ?><p class="gf-sub"><?= v2_e($it['sub']) ?></p><?php endif; ?>
              <ul class="gf-why" data-why></ul>
            </div>
            <div class="gf-side">
              <p class="gf-price"><?php if ($it['cents'] === null): ?><b>Preț la cerere</b><?php elseif ($it['cents'] === 0): ?><b>Gratuit</b><?php else: ?><small>de la</small><b><?= v2_e(v2_thousands((int) round($it['cents'] / 100))) ?> lei</b><small>/ pers.</small><?php endif; ?></p>
              <p class="gf-total" data-total hidden></p>
              <button class="btn btn-ghost gf-pick" type="button" data-pick aria-pressed="false"><?= v2_ic('plus', 'ic gf-ic-add') ?><?= v2_ic('check', 'ic gf-ic-on') ?><span>Adaugă la cadou</span></button>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
        <div class="gf-none" id="gf-none" <?= $items ? 'hidden' : '' ?>>
          <span class="gf-none-ic"><?= v2_ic('gift') ?></span>
          <h3><?= $items ? 'Nicio experiență nu se potrivește tuturor criteriilor.' : 'Încă nu sunt experiențe publicate.' ?></h3>
          <p><?= $items ? 'Renunță la unul dintre criterii sau alege direct un card cadou: destinatarul își alege singur experiența.' : 'Un card cadou rămâne cea mai sigură alegere: destinatarul își alege singur experiența.' ?></p>
          <div class="gf-none-cta">
            <?php if ($items): ?>
            <button class="btn btn-ghost" type="button" data-relax="city">Oriunde în țară</button>
            <button class="btn btn-ghost" type="button" data-relax="budget">Fără limită de buget</button>
            <button class="btn btn-ghost" type="button" data-relax="likes">Orice îi place</button>
            <?php endif; ?>
            <a class="btn btn-primary" href="/card-cadou#cumpara">Card cadou fără experiență aleasă<?= v2_ic('arrow-right') ?></a>
          </div>
        </div>
      </div>
    </div>

    <!-- the pick: a bar at the bottom of the screen once something is chosen -->
    <aside class="gf-tray" id="gf-tray" aria-labelledby="gf-tray-h" hidden>
      <div class="wrap gf-tray-in">
        <div class="gf-tray-sum">
          <h2 class="gf-tray-h" id="gf-tray-h">Cadoul tău</h2>
          <p id="gf-tray-text" aria-live="polite"></p>
          <ul class="gf-tray-list" id="gf-tray-list"></ul>
        </div>
        <div class="gf-tray-go">
          <p class="gf-tray-value"><small>Card cadou sugerat</small><b id="gf-tray-value">—</b></p>
          <a class="btn btn-primary" id="gf-go" href="/card-cadou#cumpara">Continuă la cardul cadou<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </aside>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec gf-faq" aria-labelledby="gf-faq-h">
    <div class="wrap gf-faq-grid">
      <div><p class="kicker">FAQ</p><h2 id="gf-faq-h">Despre experiențele cadou</h2></div>
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
          <p class="kicker">Nu știi ce să alegi?</p>
          <h2 id="gf-final-h">Lasă-i pe ei să aleagă.</h2>
          <p>Un card cadou fără experiență fixată se poate folosi la orice activitate eligibilă de pe bilete.online.</p>
        </div>
        <a class="btn btn-light" href="/card-cadou#cumpara">Card cadou<?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
<script type="application/json" id="gf-data"><?= json_encode($gfData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
