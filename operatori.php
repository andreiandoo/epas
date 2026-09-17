<?php
/**
 * Operators catalog: /operatori (v2 design).
 *
 * The companies that sell activities on bilete.online. `/marketplace-events/organizers` gives the operators (name, slug,
 * logo, verified) but counts only core events and has no city or description, so each operator's activities, cities,
 * categories and lowest price come from the `/activities` listing grouped by organizer slug (the same cached pages
 * /operator/{slug} reads), and the description from the operator's profile (tagline / about, cached like on the profile
 * page). Search, city and "verified" filters run over the server-rendered cards (operators.js), so every operator is in
 * the HTML for crawlers.
 *
 * Top to bottom: hero (search, quick filters, a real operator with its first activities), filters + operator cards,
 * what an operator page holds, final CTA for operators.
 *
 * Changed on the way: every card said "România", showed no activity count and the same stock sentence (the organizers
 * endpoint has no city, counts only events and has no description), and the hero card listed "Activitate 1/2/3"
 * placeholders. When the API can't be reached the page no longer shows three invented operators (their links were
 * 404s): it says the list is unavailable, answers 503 and isn't cached.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$osKey = fn (string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($s, [
    'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
    'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
]))), '-');
$osSlugOk = fn ($s): bool => is_string($s) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $s) === 1;
$osShort = function (string $s, int $max = 170): string {
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
    if (mb_strlen($s) <= $max) {
        return $s;
    }
    $cut = mb_substr($s, 0, $max);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false && $space > 80 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
};
$osInitial = fn (string $name): string => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $name), 0, 1)) ?: 'O';

// ------------------------------------------------------------------ operators
$opsResp = api_cached('operators_all', fn () => api_get('/marketplace-events/organizers', ['per_page' => 100, 'sort' => 'name']), 600);
$opsData = $opsResp['data'] ?? [];
$rawOps = is_array($opsData['items'] ?? null) ? $opsData['items']
    : (is_array($opsData['data'] ?? null) ? $opsData['data'] : (is_array($opsData) && array_is_list($opsData) ? $opsData : []));

// Activities per operator, from the listing (the organizers endpoint doesn't list them).
$groups = [];
$activitiesOk = false;
for ($listPage = 1; $listPage <= 4; $listPage++) {
    $listResp = api_cached("operator_activities_p{$listPage}", fn () => api_get('/activities', ['per_page' => 50, 'page' => $listPage]), 600);
    if (!empty($listResp['success'])) {
        $activitiesOk = true;
    }
    foreach ((array) ($listResp['data']['items'] ?? []) as $a) {
        $org = is_array($a) && is_array($a['organizer'] ?? null) ? $a['organizer'] : null;
        if (!$org || !$osSlugOk($org['slug'] ?? null) || !($card = v2_activity($a))) {
            continue;
        }
        $group = &$groups[$org['slug']];
        $group['name'] = $group['name'] ?? navFlatName($org['name'] ?? '');
        $group['activities'][] = $card;
        $citySlug = is_array($a['city'] ?? null) ? (string) ($a['city']['slug'] ?? '') : '';
        if ($card['city'] !== '' && $osSlugOk($citySlug)) {
            $group['cities'][$citySlug] = [$card['city'], ($group['cities'][$citySlug][1] ?? 0) + 1];
        }
        unset($group);
    }
    if ($listPage >= (int) ($listResp['data']['pagination']['last_page'] ?? 1)) {
        break;
    }
}

$operators = [];
foreach ($rawOps as $o) {
    $opName = is_array($o) ? navFlatName($o['name'] ?? '') : '';
    if ($opName === '' || !$osSlugOk($o['slug'] ?? null)) {
        continue;
    }
    $operators[$o['slug']] = ['slug' => $o['slug'], 'name' => $opName, 'logo' => v2_media_url($o['logo'] ?? null), 'verified' => !empty($o['verified'])];
}
// operators the organizers list doesn't return but whose activities are on sale
foreach ($groups as $groupSlug => $group) {
    if (!isset($operators[$groupSlug]) && ($group['name'] ?? '') !== '') {
        $operators[$groupSlug] = ['slug' => (string) $groupSlug, 'name' => $group['name'], 'logo' => null, 'verified' => false];
    }
}
$activityCount = fn (array $op): int => count($groups[$op['slug']]['activities'] ?? []);
uasort($operators, fn ($a, $b) => [$activityCount($b), $osKey($a['name'])] <=> [$activityCount($a), $osKey($b['name'])]);

// Profiles (tagline / about, avatar, cover) for the first 24, fetched together and cached like on /operator/{slug}.
$profileJobs = [];
foreach (array_slice(array_keys($operators), 0, 24) as $profileSlug) {
    $profileJobs[(string) $profileSlug] = ['key' => "operator_profile_{$profileSlug}", 'endpoint' => '/marketplace-events/organizers/' . rawurlencode((string) $profileSlug), 'params' => [], 'ttl' => 300];
}
$profiles = $profileJobs ? api_cached_many($profileJobs) : [];

$list = [];
foreach ($operators as $op) {
    $group = $groups[$op['slug']] ?? [];
    $acts = $group['activities'] ?? [];
    $profile = $profiles[$op['slug']] ?? null;
    $pd = is_array($profile) && !empty($profile['success']) && is_array($profile['data'] ?? null) ? $profile['data'] : [];
    $cities = $group['cities'] ?? [];
    uasort($cities, fn ($a, $b) => $b[1] <=> $a[1]);
    $mainSlug = (string) (array_key_first($cities) ?? '');
    $mainCity = $mainSlug !== '' ? $cities[$mainSlug][0] : '';
    $categories = array_values(array_unique(array_filter(array_column($acts, 'catName'))));
    $prices = array_filter(array_column($acts, 'price'), fn ($p) => $p > 0);
    $cover = v2_media_url(is_string($pd['cover_image'] ?? null) ? $pd['cover_image'] : null);
    foreach ($acts as $act) {
        if ($cover) {
            break;
        }
        $cover = $act['image'] ?: null;
    }
    $location = is_string($pd['location'] ?? null) ? trim($pd['location']) : '';
    $about = $osShort(navFlatName($pd['tagline'] ?? '') ?: navFlatName($pd['about'] ?? ''));
    $count = count($acts);
    $list[] = [
        'slug' => $op['slug'],
        'name' => $op['name'],
        'url' => '/operator/' . $op['slug'],
        'verified' => $op['verified'] || !empty($pd['verified']),
        'logo' => $op['logo'] ?: v2_media_url(is_string($pd['avatar'] ?? null) ? $pd['avatar'] : null),
        'cover' => $cover,
        'city' => $mainCity ?: ($location ?: 'România'),
        'cityUrl' => $mainSlug !== '' ? '/' . $mainSlug : '',
        'cityKeys' => array_map('strval', array_keys($cities)),
        'cityNames' => array_column($cities, 0),
        'count' => $count,
        'categories' => array_slice($categories, 0, 2),
        'priceFrom' => $prices ? min($prices) : 0,
        'activities' => array_slice($acts, 0, 3),
        'description' => $about !== '' ? $about : ($count > 0
            ? 'Operator partener bilete.online cu ' . v2_num($count, 'activitate', 'activități') . ($mainCity !== '' ? ' în ' . $mainCity : '') . '.'
            : 'Operator partener bilete.online cu activități disponibile online.'),
    ];
}

$apiDown = !$list && (empty($opsResp['success']) || !$activitiesOk);
if (empty($opsResp['success']) || !$activitiesOk) {
    $skipPageCache = true; // never keep a degraded list in the page cache
}
if ($apiDown) {
    http_response_code(503);
    header('Retry-After: 120');
}

$cityOptions = [];
foreach ($list as $op) {
    foreach ($op['cityKeys'] as $ci => $cityKey) {
        $cityOptions[$cityKey] = $op['cityNames'][$ci];
    }
}
uasort($cityOptions, fn ($a, $b) => strcmp($osKey($a), $osKey($b)));
$verifiedCount = count(array_filter($list, fn ($op) => $op['verified']));
$lead = $list[0] ?? null;
$searchQuery = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';

// ------------------------------------------------------------------ page
$pageTitleRaw = 'Operatori — companii care vând activități · ' . SITE_NAME;
$pageDescription = 'Descoperă operatorii parteneri bilete.online: companiile care organizează și vând activități, experiențe și tururi. Profil dedicat, activități listate, bilete cu QR pe email.';
$canonicalUrl = SITE_URL . '/operatori';
$noindex = $apiDown;
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => count($list),
        'itemListElement' => array_map(fn ($pos, $op) => [
            '@type' => 'ListItem',
            'position' => $pos + 1,
            'name' => $op['name'],
            'url' => SITE_URL . $op['url'],
        ], array_keys($list), $list),
    ],
]];

$osArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['cities.css', 'operators.css'];
$v2Scripts = ['operators.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="ct-hero os-hero" aria-labelledby="os-h">
    <?= $osArches ?>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div>
        <p class="ct-kicker">Operatori · companii · activități</p>
        <h1 class="ct-h" id="os-h">Cine organizează experiențele.</h1>
        <p class="ct-lead">Operatorii sunt companiile care creează și vând activitățile de pe platformă: escape rooms, tururi ghidate, ateliere, experiențe și multe altele. Fiecare are profil dedicat cu activitățile lui.</p>

        <form class="ct-search" id="os-form" action="/operatori" method="get" role="search">
          <label class="sr" for="os-q">Caută operator</label>
          <input id="os-q" name="q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="Caută: nume operator, oraș..." value="<?= v2_e($searchQuery) ?>">
          <button type="submit" aria-label="Arată operatorii găsiți"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status" id="os-status" role="status"></p>
        <?php if ($list): ?>
        <div class="os-quick" role="group" aria-label="Filtre rapide">
          <button type="button" data-quick="all" aria-pressed="true">Toți operatorii<span><?= count($list) ?></span></button>
          <button type="button" data-quick="verified" aria-pressed="false">Verificați<span><?= $verifiedCount ?></span></button>
        </div>
        <?php endif; ?>
      </div>

      <div class="ct-art" aria-hidden="true">
        <div class="ct-art-card os-graph">
          <p class="kicker">Operator graph</p>
          <p class="ct-art-h">Un operator poate avea mai multe activități.</p>
          <?php if ($lead): ?>
          <div class="os-graph-box">
            <div class="os-graph-op">
              <span class="os-avatar"><?= $lead['logo'] ? v2_photo([$lead['logo'], 0, 0, '']) : v2_e($osInitial($lead['name'])) ?></span>
              <div><b><?= v2_e($lead['name']) ?></b><small><?= v2_e($lead['city']) ?></small></div>
            </div>
            <?php if ($lead['activities']): ?>
            <ul class="os-graph-rows">
              <?php foreach ($lead['activities'] as $act): ?>
              <li><span><?= v2_e($act['title']) ?></span><b><?= $act['price'] > 0 ? 'de la ' . $act['price'] . ' lei' : 'bilete' ?></b></li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <p class="os-graph-empty">Identitate, activități, review-uri și contact, într-o singură pagină.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== FILTERS + OPERATORS ===================== -->
  <section class="sec ct-main" id="lista" aria-labelledby="os-title">
    <div class="wrap ct-layout">
      <aside class="ct-side" aria-label="Filtrează operatorii">
        <div class="ct-filter os-filter">
          <p class="kicker">Filtrare operatori</p>
          <?php if ($list): ?>
          <div class="os-field">
            <label for="os-city">Oraș</label>
            <span class="os-select"><select id="os-city"><option value="all">Toate orașele</option><?php foreach ($cityOptions as $cityKey => $cityName): ?><option value="<?= v2_e($cityKey) ?>"><?= v2_e($cityName) ?></option><?php endforeach; ?></select><?= v2_ic('caret-down') ?></span>
          </div>
          <label class="os-check" for="os-verified"><input type="checkbox" id="os-verified"><span>Doar operatori verificați</span></label>
          <?php endif; ?>
          <div class="ct-note">
            <b>Ești operator?</b>
            <p>Ai activități de vândut? Poți avea pagină dedicată, activități listate, bilete QR și dashboard.</p>
            <a href="/parteneri">Devino partener<?= v2_ic('arrow-right') ?></a>
          </div>
        </div>
      </aside>

      <div>
        <div class="ct-head">
          <div><p class="kicker">Operatori</p><h2 id="os-title" tabindex="-1">Operatori parteneri</h2></div>
          <?php if ($list): ?><p id="os-count" aria-live="polite"><?= count($list) ?> din <?= count($list) ?> operatori</p><?php endif; ?>
        </div>

        <?php if ($apiDown): ?>
        <div class="os-state is-error" role="alert">
          <span class="ct-none-ic"><?= v2_ic('users-three') ?></span>
          <h3>Lista operatorilor nu poate fi încărcată acum.</h3>
          <p>Încearcă din nou în câteva minute. Între timp poți căuta direct activitățile.</p>
          <div class="os-state-cta"><a class="btn btn-primary" href="/operatori">Reîncearcă</a><a class="btn btn-ghost" href="/cauta">Caută activități</a></div>
        </div>
        <?php elseif (!$list): ?>
        <div class="os-state">
          <span class="ct-none-ic"><?= v2_ic('users-three') ?></span>
          <h3>Încă nu avem operatori listați.</h3>
          <p>Primii operatori parteneri apar aici imediat ce își publică activitățile.</p>
          <div class="os-state-cta"><a class="btn btn-primary" href="/parteneri">Devino partener</a></div>
        </div>
        <?php else: ?>
        <ul class="ct-grid os-grid" id="os-grid">
          <?php foreach ($list as $oi => $op): ?>
          <li class="ct-card os-card<?= $oi >= 12 ? ' is-extra' : '' ?>" data-cities="<?= v2_e(implode(' ', $op['cityKeys'])) ?>" data-verified="<?= $op['verified'] ? '1' : '0' ?>" data-q="<?= v2_e(implode(' ', array_merge([$op['name'], $op['city'], $op['description']], $op['cityNames'], $op['categories']))) ?>">
            <a class="ct-top" href="<?= v2_e($op['url']) ?>">
              <span class="ct-media"><?= $op['cover'] ? v2_photo([$op['cover'], 0, 0, '']) : v2_fallback($op['name'], $oi) ?></span>
              <?php if ($op['logo']): ?><span class="os-logo"><?= v2_photo([$op['logo'], 0, 0, '']) ?></span><?php endif; ?>
              <span class="ct-over"><small><?= v2_e($op['city']) ?></small><h3><?= v2_e($op['name']) ?></h3></span>
              <?php if ($op['count'] > 0): ?><span class="ct-badge"><?= v2_e(v2_num($op['count'], 'activitate', 'activități')) ?></span><?php endif; ?>
            </a>
            <div class="ct-body">
              <?php if ($op['verified'] || $op['categories'] || $op['priceFrom'] > 0): ?>
              <ul class="os-meta" aria-label="<?= v2_e($op['name']) ?>: pe scurt">
                <?php if ($op['verified']): ?><li class="os-verified"><?= v2_ic('check-circle') ?>Verificat</li><?php endif; ?>
                <?php foreach ($op['categories'] as $category): ?><li class="os-chip"><?= v2_e($category) ?></li><?php endforeach; ?>
                <?php if ($op['priceFrom'] > 0): ?><li class="os-chip">de la <?= $op['priceFrom'] ?> lei</li><?php endif; ?>
              </ul>
              <?php endif; ?>
              <p><?= v2_e($op['description']) ?></p>
              <ul class="ct-links" aria-label="<?= v2_e($op['name']) ?>: linkuri">
                <?php if ($op['cityUrl'] !== ''): ?><li><a href="<?= v2_e($op['cityUrl']) ?>">Activități în <?= v2_e($op['city']) ?></a></li><?php endif; ?>
                <li><a class="is-main" href="<?= v2_e($op['url']) ?>">Vezi operatorul<?= v2_ic('arrow-right') ?></a></li>
              </ul>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($list) > 12): ?>
        <button class="btn btn-ghost ct-more" type="button" id="os-more" hidden>Arată toți cei <?= v2_e(v2_num(count($list), 'operator', 'operatori')) ?><?= v2_ic('caret-down') ?></button>
        <?php endif; ?>
        <div class="ct-none" id="os-none" hidden>
          <span class="ct-none-ic"><?= v2_ic('users-three') ?></span>
          <p>Niciun operator găsit</p>
          <p class="os-none-sub">Încearcă alt oraș sau șterge filtrele.</p>
          <button class="btn btn-ghost" type="button" id="os-reset">Arată toți operatorii</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== WHAT AN OPERATOR PAGE HOLDS ===================== -->
  <section class="sec ct-hubs os-about" aria-labelledby="os-about-h">
    <div class="wrap ct-hubs-grid">
      <div class="ct-hubs-intro">
        <p class="kicker">Pagină operator</p>
        <h2 id="os-about-h">Un operator e mai mult decât un nume.</h2>
        <p>Pagina unui operator arată cine este, ce activități oferă, unde operează, review-urile clienților și cum poți rezerva.</p>
      </div>
      <ul class="os-features">
        <li class="os-feature"><small>01</small><h3>Identitate</h3><p>Nume, logo, descriere, orașe, încredere.</p></li>
        <li class="os-feature is-mint"><small>02</small><h3>Activități</h3><p>Lista activităților, bilete, prețuri, disponibilitate.</p></li>
        <li class="os-feature"><small>03</small><h3>Review-uri</h3><p>Recenzii verificate de la clienți care au participat.</p></li>
        <li class="os-feature is-deep"><small>04</small><h3>Contact</h3><p>Întrebări, politici, informații pentru grupuri.</p></li>
      </ul>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="ct-final" aria-labelledby="os-final-h">
    <div class="wrap">
      <div class="ct-final-in">
        <?= $osArches ?>
        <div>
          <p class="kicker">Operatori</p>
          <h2 id="os-final-h">Ești operator? Vinde activități online.</h2>
          <p>Pagină dedicată, activități, bilete QR, dashboard, scanner check-in și rapoarte.</p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/parteneri">Devino partener<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/inregistrare-locatie">Solicită cont</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
