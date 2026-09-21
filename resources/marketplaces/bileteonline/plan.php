<?php
/**
 * /plan — the trip planner: say where and how long, get a day-by-day itinerary built out of the
 * attraction catalogue, then move it around until it is yours.
 *
 * Everything runs in the browser against the same pin dataset the map uses (assets/v2/js/plan.js):
 * no request per change, no server state, and the plan lives in the URL and in localStorage, so a
 * link is a plan. The map is EPMap in route mode, one day at a time.
 *
 * The planner is deterministic on purpose — no model writes these itineraries. The catalogue has
 * no opening hours, so durations are per-type estimates from includes/v2/plan-config.php and the
 * page says so where it matters.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/plan-config.php';
require_once __DIR__ . '/includes/v2/map-routes.php';
require_once __DIR__ . '/includes/v2/nav.php';

$mapData = v2_map_data();
$summary = v2_map_summary();
if (!$mapData || !$summary) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Starting points offered on the first screen: the cities with the most to see.
$startCities = array_slice($summary['cities'] ?? [], 0, 12);
$regions = $summary['regions'] ?? [];

// A few routes as ready-made alternatives to building from scratch.
$routeCards = [];
foreach (MAP_ROUTES as $rSlug => $r) {
    $rd = $summary['routes'][$rSlug] ?? null;
    if (!$rd) {
        continue;
    }
    $rImg = '';
    foreach ($rd['stops'] as $st) {
        if ($st[9] !== '') {
            $rImg = $st[9];
            break;
        }
    }
    $routeCards[] = [$rSlug, $r['title'], $r['lead'], $r['emoji'], $r['pace'], $rd['count'], $rd['km'], $rImg, (int) ($rd['road']['min'] ?? 0)];
}
shuffle($routeCards);
$routeCards = array_slice($routeCards, 0, 3);

$v2Styles  = ['map.css', 'map-page.css', 'routes.css', 'plan.css'];
$v2Scripts = ['map.js', 'plan.js'];
$v2ClientData = [
    'plan' => [
        'dataUrl'   => $mapData['url'],
        'cartoKey'  => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'durations' => PLAN_DURATIONS,
        'paces'     => PLAN_PACES,
        'interests' => PLAN_INTERESTS,
        'travel'    => PLAN_TRAVEL,
        'cities'    => array_map(fn ($c) => [$c[0], $c[1], $c[4]], $summary['cities'] ?? []),
        'regions'   => array_map(fn ($r) => [$r[0], $r[1]], $regions),
        'total'     => (int) $summary['total'],
    ],
];

$pageTitleRaw    = 'Planificator de călătorie — itinerariu pe zile, cu hartă | bilete.online';
$pageDescription = 'Spune unde mergi și pe câte zile, iar planificatorul îți face itinerariul din cele '
    . v2_thousands((int) $summary['total']) . ' de atracții de pe hartă: opriri pe zile, ordinea vizitării, timpi și navigare.';
$canonicalUrl    = SITE_URL . '/plan';

$breadcrumbs = [['Acasă', '/'], ['Hartă', '/harta'], ['Planificator', '/plan']];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'Planificator de călătorie bilete.online',
    'applicationCategory' => 'TravelApplication',
    'operatingSystem' => 'Web',
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'RON'],
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $breadcrumbs,
        array_keys($breadcrumbs)
    ),
]];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== START ============================== -->
  <section class="pl-start" id="pl-start" aria-labelledby="pl-h">
    <div class="wrap pl-start-in">
      <nav class="crumbs" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
          <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
          <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <h1 id="pl-h">Hai să mergem <em>undeva</em></h1>
      <p class="pl-lead">Spune unde ajungi și pe câte zile. Îți fac itinerariul din cele <?= v2_e(v2_thousands((int) $summary['total'])) ?> de atracții de pe hartă, pe zile și în ordinea în care se leagă pe drum — apoi îl muți cum vrei.</p>

      <form class="pl-form" id="pl-form" novalidate>
        <div class="pl-field pl-field-where">
          <label for="pl-where">Unde mergi?</label>
          <div class="pl-auto">
            <input id="pl-where" type="text" autocomplete="off" placeholder="Un oraș sau o regiune — Brașov, Bucovina…" aria-describedby="pl-where-hint">
            <ul class="pl-sugg" id="pl-sugg" role="listbox" hidden></ul>
          </div>
          <p class="pl-hint" id="pl-where-hint">Caută după oraș sau regiune.</p>
        </div>

        <div class="pl-field">
          <label for="pl-days">Câte zile?</label>
          <div class="pl-stepper">
            <button class="pl-step-btn" type="button" data-days="-1" aria-label="O zi mai puțin">−</button>
            <input id="pl-days" type="number" min="1" max="7" value="2" inputmode="numeric">
            <button class="pl-step-btn" type="button" data-days="1" aria-label="O zi în plus">+</button>
          </div>
        </div>

        <div class="pl-field">
          <label for="pl-from">Din ce zi? <span class="pl-opt">(opțional)</span></label>
          <input id="pl-from" type="date">
        </div>

        <fieldset class="pl-field pl-field-wide">
          <legend>Ce te interesează?</legend>
          <div class="pl-chips" id="pl-interests">
            <?php foreach (PLAN_INTERESTS as $key => [$label, $emoji, $types]): ?>
            <button class="pl-chip" type="button" data-interest="<?= v2_e($key) ?>" aria-pressed="false"><span aria-hidden="true"><?= v2_e($emoji) ?></span><?= v2_e($label) ?></button>
            <?php endforeach; ?>
          </div>
          <p class="pl-hint">Lasă-le nebifate și iau de toate.</p>
        </fieldset>

        <fieldset class="pl-field pl-field-wide">
          <legend>În ce ritm?</legend>
          <div class="pl-chips" id="pl-pace">
            <?php foreach (PLAN_PACES as $key => [$label, $minutes, $note]): ?>
            <button class="pl-chip pl-chip-pace" type="button" data-pace="<?= v2_e($key) ?>" aria-pressed="<?= $key === 'normal' ? 'true' : 'false' ?>"><b><?= v2_e($label) ?></b><small><?= v2_e($note) ?></small></button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="pl-actions">
          <button class="btn btn-primary pl-go" type="submit">Fă-mi planul<?= v2_ic('arrow-right') ?></button>
          <p class="pl-note">Nimic nu pleacă de pe telefonul tău: planul stă în adresa paginii și în browser.</p>
        </div>
      </form>

      <div class="pl-quick">
        <p class="pl-quick-h">Sau pornește dintr-un oraș:</p>
        <ul class="mp-chips">
          <?php foreach ($startCities as [$cSlug, $cName, $cCounty, $cRegion, $cCount]): ?>
          <li><button class="mp-chip" type="button" data-start-city="<?= v2_e($cSlug) ?>"><?= v2_e($cName) ?><b><?= v2_e(v2_thousands((int) $cCount)) ?></b></button></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ============================== PLAN (built in the browser) ============================== -->
  <section class="pl-plan" id="pl-plan" hidden aria-labelledby="pl-plan-h">
    <h2 class="sr" id="pl-plan-h">Planul tău</h2>
    <div class="wrap">
      <header class="pl-bar" id="pl-bar"></header>
      <div class="pl-cols">
        <div class="pl-days" id="pl-days-list"></div>
        <aside class="pl-map-col">
          <div class="mp-frame pl-map-frame">
            <div data-epm-root data-epm-config="<?= v2_e(json_encode([
                'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
                'urlState' => false,
                'fixed'    => true,
                'title'    => 'Planul tău',
                'base'     => '/atractie/',
                'routeStops' => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
          </div>
          <p class="rp-note" id="pl-map-note"></p>
        </aside>
      </div>
    </div>
  </section>

  <!-- ============================== ALTERNATIVE: READY-MADE ROUTES ============================== -->
  <section class="sec" aria-labelledby="pl-routes-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="pl-routes-h">Sau ia un traseu gata făcut</h2>
        <a class="sec-link" href="/trasee">Toate traseele<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>

  <section class="sec" aria-labelledby="pl-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="pl-about-h" class="sr">Despre planificator</h2>
        <p>Planificatorul nu inventează locuri: ia atracțiile reale din catalog, le filtrează după ce te interesează, le grupează pe zile astfel încât fiecare zi să stea într-o zonă, și le pune în ordinea care scurtează drumul. Nu scrie nimic un model de limbaj — de-aia nu-ți va spune niciodată despre un loc ceva ce nu e în catalog.</p>
        <p>Timpii de vizitare sunt <strong>estimări pe tip de obiectiv</strong>: un castel 90 de minute, un muzeu 75, o biserică 30. Catalogul nu are încă programul de vizitare al fiecărui loc, așa că verifică orele înainte de drum. Timpii de mers sunt calculați din distanța în linie dreaptă, corectată pentru drumurile reale.</p>
        <p>Planul rămâne al tău: mută opriri între zile, scoate ce nu-ți place, adaugă altceva. Ce ai schimbat nu se pierde când regenerez restul. Link-ul din bara de adrese conține tot planul, deci îl poți trimite cuiva sau salva la favorite.</p>
      </div>
      <div class="mp-faq">
        <details open><summary>De unde știți cât stau la fiecare loc?</summary><p>Nu știm — sunt estimări pe tip de obiectiv, afișate ca atare. Le poți schimba pentru fiecare oprire în parte.</p></details>
        <details><summary>Pot cumpăra biletele de aici?</summary><p>Deocamdată nu direct din plan. Unde locul vinde bilete prin bilete.online, pagina lui are butonul de rezervare, iar oprirea din plan duce acolo.</p></details>
        <details><summary>Se salvează planul?</summary><p>Da, în browserul tău și în adresa paginii. Dacă golești datele browserului, link-ul rămâne valabil.</p></details>
        <details><summary>Merge fără internet?</summary><p>Nu, dar poți tipări planul sau îl poți deschide în Google Maps zi cu zi, ca să-l ai offline acolo.</p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
