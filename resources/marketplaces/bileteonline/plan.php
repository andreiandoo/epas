<?php
/**
 * /plan — the trip planner: say where and how long, get a day-by-day itinerary built out of the
 * attraction catalogue, then move it around until it is yours.
 *
 * Everything runs in the browser against the same pin dataset the map uses (assets/v2/js/plan.js):
 * no request per change, no server state, and the plan lives in the URL and in localStorage, so a
 * link is a plan. The map is EPMap in its bare flavour: the page draws the list and the controls,
 * and whatever the list has under its heading is what the map shows.
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
require_once __DIR__ . '/includes/v2/map-roads.php';
require_once __DIR__ . '/includes/v2/product-icons.php';
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

// The roads of /trasee, for /plan?drum=<slug>: just what the planner needs to open one as a plan.
$roadsForPlan = [];
foreach (v2_map_roads() as $rdSlug => $rd) {
    $def = MAP_ROADS[$rdSlug] ?? null;
    if (!$def || !empty($def['osm'])) {
        continue;      // a long-distance route is not a day; it stays on /trasee
    }
    $roadsForPlan[$rdSlug] = [
        'title' => $def['title'], 'mode' => $def['modes'][0], 'from' => $def['from'], 'to' => $def['to'],
        'a' => $rd['a'], 'b' => $rd['b'], 'stops' => array_column($rd['stops'], 0),
    ];
}

$v2HeaderOverlay = true;   // the page opens on a dark band
$v2Styles  = ['map.css', 'map-page.css', 'routes.css', 'plan.css'];
$v2Scripts = ['map.js', 'plan.js'];
$v2ClientData = [
    'plan' => [
        'dataUrl'   => $mapData['url'],
        'cartoKey'  => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'durations' => PLAN_DURATIONS,
        'paces'     => PLAN_PACES,
        'interests' => PLAN_INTERESTS,
        'company'   => PLAN_COMPANY,
        'bookables' => $summary['bookables'] ?? [],
        'travel'    => PLAN_TRAVEL,
        'swap'      => PLAN_SWAP,
        'budgets'   => PLAN_NIGHT_BUDGETS,
        'stops'     => PLAN_STOP_PRESETS,
        'minutes'   => PLAN_STOP_MINUTES,
        'cities'    => array_map(fn ($c) => [$c[0], $c[1], $c[4]], $summary['cities'] ?? []),
        'regions'   => array_map(fn ($r) => [$r[0], $r[1]], $regions),
        'total'     => (int) $summary['total'],
        'party'     => PLAN_PARTY,
        'stay22'    => PLAN_STAY22,
        'modes'     => PLAN_MODES,
        'roads'     => $roadsForPlan,
    ],
];

$pageTitleRaw    = 'Planificator de călătorie — itinerariu pe zile, cu hartă | bilete.online';
$pageDescription = 'Spune unde mergi, pe câte zile și cu ce — mașină, motocicletă sau bicicletă — iar planificatorul îți face itinerariul din cele '
    . v2_thousands((int) $summary['total']) . ' de atracții de pe hartă: opriri pe zile, harta care urmărește lista, timpi și navigare.';
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
<?php require __DIR__ . '/includes/v2/plan-icons.php'; ?>
<main id="main" tabindex="-1">
  <!-- ============================== START ============================== -->
  <section class="pl-start plb" id="pl-start" data-mode="car" aria-labelledby="pl-h">
    <div class="plb-hero">
      <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="pl-h">Hai să mergem <em>undeva</em></h1>
        <p class="pl-lead">Spune de unde pleci, unde ajungi și cu ce. Îți fac itinerariul din cele <?= v2_e(v2_thousands((int) $summary['total'])) ?> de atracții de pe hartă și din experiențele și locațiile care se pot rezerva — pe zile, în ordinea în care se leagă pe drum. Apoi îl muți cum vrei.</p>
      </div>
    </div>
    <div id="hdr-sentinel" aria-hidden="true"></div>

    <div class="wrap plb-wrap">
      <form class="pl-form plb-card" id="pl-form" novalidate>
        <div class="plb-g">
          <span class="plb-l" id="pl-mode-l">Cu ce mergi?</span>
          <div class="plb-seg" id="pl-mode" role="group" aria-labelledby="pl-mode-l">
            <span class="plb-seg-thumb" aria-hidden="true"></span>
            <?php foreach (PLAN_MODES as $mKey => [$mLabel, $mIcon]): ?>
            <button type="button" data-mode="<?= v2_e($mKey) ?>" aria-pressed="<?= $mKey === 'car' ? 'true' : 'false' ?>"><?= v2_ic($mIcon) ?><?= v2_e($mLabel) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="plb-g">
          <div class="plb-rt">
            <div class="plb-rt-row">
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-from-place">De unde pleci?</label>
                <input id="pl-from-place" type="text" autocomplete="off" placeholder="Orașul din care pornești" required aria-describedby="pl-from-hint">
                <ul class="pl-sugg" id="pl-sugg-from" role="listbox" hidden></ul>
              </div>
            </div>
            <div class="plb-rt-row is-to">
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-where">Unde mergi?</label>
                <input id="pl-where" type="text" autocomplete="off" placeholder="Un oraș sau o regiune — Brașov, Bucovina…" aria-describedby="pl-where-hint">
                <ul class="pl-sugg" id="pl-sugg" role="listbox" hidden></ul>
              </div>
            </div>
            <div class="plb-rt-row is-back" id="pl-back-row" hidden>
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-back">Unde te întorci?</label>
                <input id="pl-back" type="text" autocomplete="off" placeholder="Ultima zi se închide aici">
                <ul class="pl-sugg" id="pl-sugg-back" role="listbox" hidden></ul>
              </div>
            </div>
          </div>
          <p class="pl-hint"><span id="pl-from-hint">De la plecare se măsoară drumul.</span> <span id="pl-where-hint">Destinația se caută după oraș sau regiune.</span></p>
          <button class="plb-link" type="button" id="pl-back-toggle" aria-expanded="false" aria-controls="pl-back-row">Mă întorc în alt loc</button>
        </div>

        <div class="plb-tiles">
          <div class="plb-tile">
            <label for="pl-days">Câte zile?</label>
            <div class="pl-stepper">
              <button class="pl-step-btn" type="button" data-days="-1" aria-label="O zi mai puțin">−</button>
              <input id="pl-days" type="number" min="1" max="7" value="2" inputmode="numeric">
              <button class="pl-step-btn" type="button" data-days="1" aria-label="O zi în plus">+</button>
            </div>
          </div>
          <div class="plb-tile">
            <label for="pl-from">Din ce zi? <span class="pl-opt">(opțional)</span></label>
            <input id="pl-from" type="date">
          </div>
          <fieldset class="plb-tile plb-tile-wide">
            <legend>Câți sunteți?</legend>
            <div class="pl-party">
              <div class="pl-party-one">
                <div class="pl-stepper">
                  <button class="pl-step-btn" type="button" data-party="adults" data-step="-1" aria-label="Un adult mai puțin">−</button>
                  <input id="pl-adults" type="number" min="1" max="<?= (int) PLAN_PARTY['adults_max'] ?>" value="<?= (int) PLAN_PARTY['adults'] ?>" inputmode="numeric" aria-label="Adulți">
                  <button class="pl-step-btn" type="button" data-party="adults" data-step="1" aria-label="Un adult în plus">+</button>
                </div>
                <label for="pl-adults">adulți</label>
              </div>
              <div class="pl-party-one">
                <div class="pl-stepper">
                  <button class="pl-step-btn" type="button" data-party="children" data-step="-1" aria-label="Un copil mai puțin">−</button>
                  <input id="pl-children" type="number" min="0" max="<?= (int) PLAN_PARTY['children_max'] ?>" value="<?= (int) PLAN_PARTY['children'] ?>" inputmode="numeric" aria-label="Copii">
                  <button class="pl-step-btn" type="button" data-party="children" data-step="1" aria-label="Un copil în plus">+</button>
                </div>
                <label for="pl-children">copii</label>
              </div>
            </div>
          </fieldset>
        </div>

        <details class="plb-prefs" id="pl-prefs">
          <summary><span><b>Preferințe</b><small id="pl-prefs-sum"></small></span><?= v2_ic('caret-down') ?></summary>
          <div class="plb-prefs-in">
            <fieldset class="plb-g">
              <legend class="plb-l">Cu cine mergi?</legend>
              <div class="pl-chips" id="pl-company">
                <?php foreach (PLAN_COMPANY as $key => [$label, $emoji, $budget, $weights]): ?>
                <button class="pl-chip" type="button" data-company="<?= v2_e($key) ?>" aria-pressed="false"><span aria-hidden="true"><?= am_product_icon_svg($emoji, 'ic-em') ?></span><?= v2_e($label) ?></button>
                <?php endforeach; ?>
              </div>
              <p class="pl-hint">Înclină recomandările spre ce li se potrivește. E o ponderare pe tipuri de locuri, nu o etichetă pusă fiecărui obiectiv.</p>
            </fieldset>
            <fieldset class="plb-g">
              <legend class="plb-l">Ce te interesează?</legend>
              <div class="pl-chips" id="pl-interests">
                <?php foreach (PLAN_INTERESTS as $key => [$label, $emoji, $types]): ?>
                <button class="pl-chip" type="button" data-interest="<?= v2_e($key) ?>" aria-pressed="false"><span aria-hidden="true"><?= am_product_icon_svg($emoji, 'ic-em') ?></span><?= v2_e($label) ?></button>
                <?php endforeach; ?>
              </div>
              <p class="pl-hint">Lasă-le nebifate și iau de toate.</p>
            </fieldset>
            <fieldset class="plb-g">
              <legend class="plb-l">În ce ritm?</legend>
              <div class="pl-chips plb-paces" id="pl-pace">
                <?php foreach (PLAN_PACES as $key => [$label, $minutes, $note]): ?>
                <button class="pl-chip pl-chip-pace" type="button" data-pace="<?= v2_e($key) ?>" aria-pressed="<?= $key === 'normal' ? 'true' : 'false' ?>"><b><?= v2_e($label) ?></b><small><?= v2_e($note) ?></small></button>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <p class="pl-hint">Numărul de persoane contează la căutarea de cazare, pentru nopțile dintre zile. Pe motocicletă planul caută priveliști și lasă drumuri mai lungi între opriri; pe bicicletă rămâne aproape și măsoară drumul pe rețeaua de biciclete.</p>
          </div>
        </details>

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

  <!-- ============================== PLAN (built in the browser) ==============================
       One map, one list. The map holds its place — the top of a phone, the right of a desk — and the
       list scrolls beside it; what the list has under its heading is what the map shows. -->
  <section class="pl-plan plx" id="pl-plan" hidden data-mode="car" aria-labelledby="pl-plan-h">
    <h2 class="sr" id="pl-plan-h">Planul tău</h2>
    <p class="sr" id="pl-live" role="status" aria-live="polite"></p>

    <div class="plx-map" id="plx-map">
      <div class="plx-frame pl-map-frame">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode([
            'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
            'urlState' => false,
            'fixed'    => true,
            'bare'     => true,
            'title'    => 'Planul tău',
            'base'     => '/atractie/',
            'routeStops' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
      </div>

      <div class="plx-top" id="plx-top">
        <div class="plx-bar">
          <button class="plx-ib" type="button" id="plx-back" aria-label="Schimbă datele planului"><?= v2_ic('arrow-left') ?></button>
          <div class="plx-title"><b id="plx-t"></b><span id="plx-s"></span></div>
          <span class="plx-mode" id="plx-mode"></span>
          <button class="plx-ib" type="button" id="plx-menu-b" aria-label="Ce poți face cu planul" aria-expanded="false" aria-controls="plx-menu"><?= v2_ic('pl-dots') ?></button>
        </div>
        <div class="plx-days" id="plx-days" role="group" aria-label="Zilele planului"></div>
      </div>
      <div class="plx-menu" id="plx-menu" hidden></div>

      <div class="plx-prof" id="plx-prof" hidden>
        <div class="plx-prof-h"><span class="plx-prof-a"></span><span class="plx-prof-b"></span></div>
        <svg viewBox="0 0 300 44" preserveAspectRatio="none" aria-hidden="true"><path class="plx-prof-area"/><path class="plx-prof-line"/><line class="plx-prof-x" y1="0" y2="44"/></svg>
      </div>

      <div class="plx-ctl">
        <button type="button" class="plx-zoom" data-plx="in" aria-label="Mărește harta"><?= v2_ic('plus') ?></button>
        <button type="button" class="plx-zoom" data-plx="out" aria-label="Micșorează harta"><?= v2_ic('pl-minus') ?></button>
        <button type="button" class="plx-size" data-plx="size" aria-label="Hartă mai mare sau mai mică"><?= v2_ic('pl-size') ?></button>
        <button type="button" data-plx="fit" aria-label="Încadrează din nou"><?= v2_ic('pl-fit') ?></button>
      </div>

      <button class="plx-stay-btn" type="button" id="plx-stay-btn" hidden><?= v2_ic('pl-bed') ?><span>Arată cazări disponibile</span></button>

      <div class="plx-stay" id="plx-stay" hidden>
        <div class="plx-stay-head">
          <button class="plx-stay-x" type="button" id="plx-stay-x"><?= v2_ic('arrow-left') ?>Înapoi la traseu</button>
          <a class="plx-stay-out" id="plx-stay-out" href="<?= v2_e(PLAN_STAY22['link']) ?>" target="_blank" rel="noopener nofollow sponsored">Deschide lista pe Stay22<?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="plx-stay-body" id="pl-pane-stay"></div>
      </div>
    </div>

    <div class="plx-sheet">
      <div class="plx-grab" id="plx-grab" role="separator" aria-orientation="horizontal" aria-label="Trage ca să schimbi cât din ecran ocupă harta" tabindex="0"></div>
      <div class="plx-list" id="plx-list">
        <header class="pl-bar" id="pl-bar"></header>
        <div class="pl-days" id="pl-days-list"></div>
        <footer class="plx-end">
          <h3 class="plx-end-h">Asta e tot drumul</h3>
          <div class="plx-end-acts" id="plx-end-acts"></div>
          <p class="rp-note" id="pl-map-note"></p>
          <p class="plx-end-p">Ce ai mutat, adăugat sau înlocuit rămâne la locul lui când regenerezi restul. Linkul din bara de adrese conține tot planul.</p>
        </footer>
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
        <p>Timpii de vizitare sunt <strong>estimări pe tip de obiectiv</strong>: un castel 90 de minute, un muzeu 75, o biserică 30. Catalogul nu are încă programul de vizitare al fiecărui loc, așa că verifică orele înainte de drum. Kilometrii și timpii de mers sunt calculați pe șosea; unde drumul nu poate fi calculat, vezi o estimare și e marcată ca atare.</p>
        <p>Harta rămâne mereu pe ecran și urmărește lista: ziua la care ai ajuns, apoi oprirea și vecinele ei, apoi orașul în care dormi. Planul rămâne al tău: trage opririle unde vrei, în zi sau în altă zi, scoate ce nu-ți place, adaugă altceva. Dacă o oprire nu ți se potrivește, din meniul ei <em>⋮</em> alegi <em>Înlocuiește</em> și îți pun pe loc câteva locuri din apropierea ei, alese după aceleași interese și aceeași companie cu care ți-am făcut planul; cel ales intră exact pe poziția celui vechi. Poți pune și opriri de-ale tale între cele propuse — o masă, o cafea, o pauză, cazarea — fie la oprirea dinainte, fie fără loc anume, fie în alt loc ales de tine; ziua se recalculează în jurul lor. Ce ai schimbat nu se pierde când regenerez restul. Link-ul din bara de adrese conține tot planul, deci îl poți trimite cuiva sau salva la favorite.</p>
      </div>
      <div class="mp-faq">
        <details open><summary>De unde știți cât stau la fiecare loc?</summary><p>Nu știm — sunt estimări pe tip de obiectiv, afișate ca atare. Le poți schimba pentru fiecare oprire în parte.</p></details>
        <details><summary>Nu-mi place o oprire. Pot pune altceva în locul ei?</summary><p>Da. Din meniul <em>⋮</em> al opririi alegi <em>Înlocuiește</em> și îți deschid sub ea o listă scurtă de locuri din apropiere — cu poza, tipul, orașul, la câți kilometri sunt și cât durează vizita — alese după aceleași interese și aceeași companie cu care e făcut planul. Nu-ți propun ceva ce ai deja în plan, iar dacă ceva a fost scos mai devreme ți-o spun. Alegi unul și intră fix pe poziția celui vechi: orele, kilometrii și harta se recalculează, iar locul înlocuit nu mai revine la regenerare. Dacă vrei altceva anume, cauți în aceeași casetă, oriunde în catalog.</p></details>
        <details><summary>Pot adăuga o pauză sau o masă?</summary><p>Da, oriunde în zi: butonul <em>Oprire de-a ta</em> din capul zilei o pune la final, iar din meniul <em>⋮</em> al unei opriri o pui imediat după ea. Alegi cât ține și dacă rămâne la oprirea dinainte, fără loc anume, sau în alt loc — iar drumul și orele se recalculează.</p></details>
        <details><summary>Cum e cu cazarea?</summary><p>Între zile îți propun un oraș în care să dormi, ales ca să scurteze și seara, și dimineața următoare — îl poți schimba sau îl poți scoate. Când ajungi cu lista la o noapte, harta se mută pe orașul ei, iar butonul <em>Arată cazări</em> de pe hartă deschide lista chiar acolo, în locul hărții. Lista vine de la Stay22, care compară Booking, Airbnb și altele, și se încarcă abia când o ceri. Dacă rezervi, primim un comision — prețul tău nu crește. Pentru ultima noapte nu-ți propun nimic: în ziua aia te întorci acasă.</p></details>
        <details><summary>Merge și pentru motocicletă sau bicicletă?</summary><p>Da. Alegi cu ce mergi și planul se schimbă: pe motocicletă caută priveliști și lasă drumuri mai lungi între opriri, pe bicicletă rămâne aproape și măsoară drumul pe rețeaua de biciclete. La amândouă vezi profilul de altitudine al zilei, legat de hartă. Drumurile cunoscute — Transfăgărășan, Transalpina, Clisura Dunării și altele — sunt pe <a href="/trasee">/trasee</a> și se deschid de acolo direct ca plan.</p></details>
        <details><summary>Pot cumpăra biletele de aici?</summary><p>Deocamdată nu direct din plan. Unde locul vinde bilete prin bilete.online, pagina lui are butonul de rezervare, iar oprirea din plan duce acolo.</p></details>
        <details><summary>Se salvează planul?</summary><p>Da, în browserul tău și în adresa paginii. Dacă golești datele browserului, link-ul rămâne valabil.</p></details>
        <details><summary>Merge fără internet?</summary><p>Nu, dar poți tipări planul sau îl poți deschide în Google Maps zi cu zi, ca să-l ai offline acolo.</p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
