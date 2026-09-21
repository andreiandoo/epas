<?php
/**
 * /trasee — the index of the editorial routes: twelve itineraries built out of the attraction
 * catalogue, each one an ordered list of real places (includes/v2/map-routes.php).
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/map-routes.php';
require_once __DIR__ . '/includes/v2/nav.php';

$summary = v2_map_summary();
if (!$summary || empty($summary['routes'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$routeCards = [];
$stopsTotal = 0;
$kmTotal = 0;
foreach (MAP_ROUTES as $slug => $r) {
    $d = $summary['routes'][$slug] ?? null;
    if (!$d) {
        continue;
    }
    $img = '';
    foreach ($d['stops'] as $s) {
        if ($s[9] !== '') {
            $img = $s[9];
            break;
        }
    }
    $routeCards[] = [$slug, $r['title'], $r['lead'], $r['emoji'], $r['pace'], $d['count'], $d['km'], $img];
    $stopsTotal += $d['count'];
    $kmTotal += $d['km'];
}

$v2Styles = ['map-page.css', 'routes.css'];

$pageTitleRaw    = 'Trasee turistice în România — ' . count($routeCards) . ' itinerarii cu hartă | bilete.online';
$pageDescription = count($routeCards) . ' trasee prin România, de la castelele Transilvaniei la cetățile Dobrogei: '
    . $stopsTotal . ' de opriri reale, fiecare cu hartă, ordinea vizitării și navigare.';
$canonicalUrl    = SITE_URL . '/trasee';
$ogImage         = $routeCards[0][7] ?? (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [['Acasă', '/'], ['Hartă', '/harta'], ['Trasee', '/trasee']];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Trasee turistice în România',
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
], [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Trasee',
    'numberOfItems' => count($routeCards),
    'itemListElement' => array_map(
        fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/trasee/' . $c[0], 'name' => $c[1]],
        $routeCards,
        array_keys($routeCards)
    ),
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
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h">Trasee <em>prin România</em></h1>
        <p class="mph-lead">Itinerarii gata făcute, din locurile care sunt deja pe hartă: ordinea vizitării, distanțele între opriri și un buton care deschide tot drumul în Google Maps.</p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($routeCards) ?></b> trasee</li>
        <li><b><?= v2_e(v2_thousands($stopsTotal)) ?></b> opriri</li>
        <li><b><?= v2_e(v2_thousands($kmTotal)) ?></b> km</li>
      </ul>
    </div>
  </section>

  <section class="sec" aria-labelledby="rx-h">
    <div class="wrap">
      <h2 class="sr" id="rx-h">Toate traseele</h2>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>

  <section class="sec" aria-labelledby="rx-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rx-about-h" class="sr">Despre trasee</h2>
        <p>Un traseu e o listă de opriri în ordinea în care se leagă pe drum. Fiecare oprire e o atracție reală din catalog, cu pagina ei, iar harta arată punctele numerotate și unite printr-o linie, ca să vezi dintr-o privire cum se desfășoară drumul.</p>
        <p>Distanțele sunt măsurate în linie dreaptă între opriri — pe șosea ies mai mari, mai ales în zonele de munte. Butonul de navigare trimite tot traseul în Google Maps, cu opririle ca puncte intermediare, deci kilometrajul real și timpul îl vezi acolo.</p>
        <p>Traseele sunt sugestii, nu programe. Le poți parcurge invers, le poți rupe în două zile sau poți lua doar ce îți iese în drum. Dacă vrei să pornești de la un loc anume și să vezi ce e în jurul lui, harta întreagă e pe <a href="/harta">/harta</a>.</p>
      </div>
      <div class="mp-faq">
        <details open><summary>De unde vin opririle?</summary><p>Din catalogul de atracții al bilete.online. Fiecare oprire are pagină proprie, coordonate verificate și apare pe harta generală.</p></details>
        <details><summary>Se plătește intrarea?</summary><p>Depinde de obiectiv. O parte au acces liber, altele au bilet stabilit de administrator. Unde se vinde bilet prin bilete.online, apare pe pagina obiectivului.</p></details>
        <details><summary>Pot vedea traseul pe telefon?</summary><p>Da. Harta ocupă tot ecranul, iar lista opririlor urcă de jos. Butonul de navigare deschide traseul direct în aplicația de hărți.</p></details>
        <details><summary>Adăugați trasee noi?</summary><p>Da, pe măsură ce intră locuri noi în catalog. Dacă ai o propunere de traseu, scrie-ne la <?= v2_e(SUPPORT_EMAIL) ?>.</p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
