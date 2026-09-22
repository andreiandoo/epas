<?php
/**
 * Booking widget of a location (activities module) for the operator's own site: /embed/locatie/{slug}, in an iframe.
 *
 * The location page's booking block alone (dates, tickets, experiences, packages, the total; booking.js in embed mode)
 * under a slim header. ?produs={id} keeps one product (plus the access tickets it needs). Payment happens on
 * bilete.online: the iframe cannot reach this site's storage from another site, so booking.js opens
 * /finalizare#bo-import=… in a new tab and cart.js adds the lines there.
 *
 * Framing: the organizer's widget_enabled and embed_domains (GET /marketplace-events/organizers/{slug}, the same
 * settings as the event widget); frame-ancestors = bilete.online + those domains (bare and www.). No tracking and no
 * cookie banner inside the frame. The height goes to the parent page as {type: 'bo-embed-height', height}.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';
require_once dirname(__DIR__) . '/includes/nav-helpers.php';
require_once dirname(__DIR__) . '/includes/v2/helpers.php';
require_once dirname(__DIR__) . '/includes/v2/am-labels.php';

function emb_stop(int $code, string $title, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . v2_e($title) . '</title><style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1B1F1D;background:#F7F6F2}div{max-width:520px;margin:40px auto;padding:24px;border:1px solid #E4E2DA;border-radius:16px;background:#fff}h1{margin:0 0 6px;font-size:1.125rem}p{margin:0;color:#5F6461}</style></head>'
        . '<body><div><h1>' . v2_e($title) . '</h1><p>' . v2_e($text) . '</p></div></body></html>';
    exit;
}

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    emb_stop(404, 'Locația nu există', 'Verifică adresa widget-ului în contul de operator.');
}

$amLocation = api_cached("am_location_{$slug}", fn () => api_get('/activities-module/locations/' . $slug), 60);
$location = (!empty($amLocation['success']) && is_array($amLocation['data'] ?? null) && ($amLocation['data']['slug'] ?? '') === $slug) ? $amLocation['data'] : null;
if (!$location) {
    emb_stop(404, 'Locația nu există', 'Locația nu e publicată pe bilete.online sau adresa widget-ului e greșită.');
}

$orgSlug = is_array($location['organizer'] ?? null) ? (string) ($location['organizer']['slug'] ?? '') : '';
$org = $orgSlug !== '' ? api_cached('embed_am_org_' . $orgSlug, fn () => api_get('/marketplace-events/organizers/' . urlencode($orgSlug)), 60) : null;
$orgData = is_array($org['data'] ?? null) ? $org['data'] : [];
if (empty($orgData['widget_enabled'])) {
    emb_stop(403, 'Widget-ul nu e activ', 'Widget-urile embed nu sunt activate pentru acest operator. Biletele se pot cumpăra pe bilete.online.');
}

// Who may frame the widget: bilete.online and the operator's domains (site.ro also allows www.site.ro and back).
$ancestors = [rtrim(SITE_URL, '/')];
foreach ((array) ($orgData['embed_domains'] ?? []) as $d) {
    $d = strtolower(trim((string) $d));
    $scheme = preg_match('#^http://#', $d) ? 'http' : 'https';
    $host = preg_replace('#^https?://#', '', $d);
    $host = preg_replace('#[/?\#].*$#', '', $host);
    if (!preg_match('/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}(:\d+)?$/', $host)) {
        continue;
    }
    $hosts = [$host];
    if (strncmp($host, '*.', 2) !== 0) {
        $hosts[] = strncmp($host, 'www.', 4) === 0 ? substr($host, 4) : 'www.' . $host;
    }
    foreach ($hosts as $h) {
        if (!in_array($scheme . '://' . $h, $ancestors, true)) {
            $ancestors[] = $scheme . '://' . $h;
        }
    }
}
header('Content-Security-Policy: frame-ancestors ' . implode(' ', $ancestors));
header('X-Robots-Tag: noindex');

$lcName     = navFlatName($location['name'] ?? '') ?: 'Locație';
$lcCity     = is_array($location['city'] ?? null) ? $location['city'] : [];
$lcCityName = navFlatName($lcCity['name'] ?? '');
$lcCover    = v2_media_url($location['cover_image'] ?? null) ?? '';
$lcProducts = array_values(array_filter((array) ($location['products'] ?? []), fn ($p) => is_array($p) && !empty($p['variants'])));

// One product only (?produs=id), with the access tickets it cannot be bought without.
$onlyId = (int) ($_GET['produs'] ?? 0);
$only = $onlyId ? array_values(array_filter($lcProducts, fn ($p) => (int) ($p['id'] ?? 0) === $onlyId)) : [];
if ($only) {
    $needsAccess = ($only[0]['access_requirement'] ?? 'none') !== 'none';
    $lcProducts = array_values(array_filter($lcProducts, fn ($p) => (int) $p['id'] === $onlyId || ($needsAccess && ($p['type'] ?? '') === 'access')));
}
$heading = $only ? (navFlatName($only[0]['title'] ?? '') ?: $lcName) : 'Bilete și experiențe';

$bookingProducts = array_map(fn ($p) => [
    'id' => $p['id'], 'slug' => $p['slug'] ?? null, 'type' => $p['type'] ?? 'access', 'title' => navFlatName($p['title'] ?? ''),
    'subtitle' => $p['subtitle'] ?? null, 'short_description' => $p['short_description'] ?? null, 'icon' => $p['icon'] ?? null,
    'image' => v2_media_url($p['image'] ?? null), 'booking_mode' => $p['booking_mode'] ?? 'day', 'capacity_mode' => $p['capacity_mode'] ?? null,
    'duration_minutes' => $p['duration_minutes'] ?? 0, 'unit_label' => $p['unit_label'] ?? null, 'usage_terms' => $p['usage_terms'] ?? null,
    'display_category' => $p['display_category'] ?? null, 'access_requirement' => $p['access_requirement'] ?? 'none',
    'requires_vehicle_info' => !empty($p['requires_vehicle_info']), 'included_items' => $p['included_items'] ?? [],
    'age_min' => $p['age_min'] ?? null, 'age_max' => $p['age_max'] ?? null, 'commission' => $p['commission'] ?? null,
    'variants' => $p['variants'] ?? [], 'addons' => $p['addons'] ?? [], 'components' => $p['components'] ?? [],
], $lcProducts);

$lcTz = new DateTimeZone('Europe/Bucharest');
$clientData = ['booking' => [
    'mode' => 'location',
    'embed' => true,
    'site_url' => rtrim(SITE_URL, '/'),
    'location' => ['slug' => $slug, 'name' => $lcName, 'city' => $lcCityName ?: null, 'image' => $lcCover ?: null],
    'product_slug' => null,
    'products' => $bookingProducts,
    'categories' => array_values((array) ($location['display_categories'] ?? [])),
    'today' => (new DateTimeImmutable('now', $lcTz))->format('Y-m-d'),
    'max_days' => max(1, (int) ($location['max_advance_days'] ?? 0) ?: 90),
    'focus_product_id' => null,
]];
?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= v2_e($lcName) ?> · bilete.online</title>
<link rel="canonical" href="<?= v2_e(SITE_URL . '/locatie/' . $slug) ?>">
<link rel="preconnect" href="<?= v2_e(CORE_URL) ?>" crossorigin>
<link rel="preload" href="/assets/v2/fonts/Satoshi-Variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= v2_asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= v2_asset('css/location.css') ?>">
<link rel="stylesheet" href="<?= v2_asset('css/embed-am.css') ?>">
<base target="_blank">
</head>
<body class="emb">
<?php readfile(dirname(__DIR__) . '/includes/v2/sprite.svg'); ?>
<div class="emb-in" id="emb">
  <header class="emb-head">
    <div class="emb-id">
      <?php if ($lcCover): ?><img class="emb-img" src="<?= v2_e($lcCover) ?>" alt="" width="48" height="48" loading="lazy"><?php endif; ?>
      <div><p class="emb-name"><?= v2_e($lcName) ?></p><?php if ($lcCityName !== ''): ?><p class="emb-city"><?= v2_e($lcCityName) ?></p><?php endif; ?></div>
    </div>
    <a class="emb-by" href="<?= v2_e(SITE_URL . '/locatie/' . $slug) ?>" rel="noopener">Bilete oficiale prin <b>bilete.online</b></a>
  </header>

  <?php if (!$lcProducts): ?>
  <p class="emb-empty">Momentan nu sunt bilete de vânzare aici. Revino în curând.</p>
  <?php else: ?>
  <section class="bkx-sec" id="bilete" aria-labelledby="bkx-h">
    <div class="bkx-grid" id="bkx">
      <div>
        <div class="bkx-head">
          <div><h2 id="bkx-h"><?= v2_e($heading) ?></h2></div>
          <button class="bkx-link" type="button" id="bkx-cal-toggle" aria-expanded="false" aria-controls="bkx-cal">Altă dată</button>
        </div>
        <ul class="bkx-days" id="bkx-days" aria-label="Alege ziua vizitei"></ul>
        <div class="bkx-cal" id="bkx-cal" hidden>
          <div class="bkx-cal-head">
            <button class="rail-btn" type="button" id="bkx-cal-prev" aria-label="Luna anterioară"><?= v2_ic('arrow-left') ?></button>
            <p id="bkx-cal-title" aria-live="polite"></p>
            <button class="rail-btn" type="button" id="bkx-cal-next" aria-label="Luna următoare"><?= v2_ic('arrow-right') ?></button>
          </div>
          <div class="bkx-cal-dow" aria-hidden="true"><span>L</span><span>Ma</span><span>Mi</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
          <div class="bkx-cal-grid" id="bkx-cal-grid"></div>
        </div>
        <p class="bkx-hours" id="bkx-hours" aria-live="polite"></p>
        <div class="bkx-tabs" id="bkx-tabs" role="group" aria-label="Categorii de bilete" hidden></div>
        <div class="bkx-list" id="bkx-list"></div>
      </div>

      <aside class="bkx-side" aria-label="Rezervarea ta">
        <div class="bkx-sum" id="bkx-sum" hidden>
          <h3>Rezervarea ta</h3>
          <ul class="bkx-lines" id="bkx-lines"></ul>
          <div class="bkx-row"><span>Subtotal</span><strong id="bkx-sub">0 lei</strong></div>
          <div class="bkx-row" id="bkx-fee-row" hidden><span id="bkx-fee-label">Comision ticketing</span><strong id="bkx-fee">0 lei</strong></div>
          <div class="bkx-row bkx-total"><span>Total</span><strong id="bkx-total">0 lei</strong></div>
          <p class="bkx-err" id="bkx-err" role="alert" hidden></p>
          <div class="bkx-cta">
            <button class="btn btn-primary" type="button" id="bkx-go" disabled>Continuă spre plată<?= v2_ic('arrow-right') ?></button>
            <button class="btn btn-ghost" type="button" id="bkx-cart" disabled hidden>Adaugă în coș</button>
          </div>
          <p class="bkx-small"><span id="bkx-card-note" hidden>Comisionul de tranzacționare a plății se calculează în checkout, în funcție de metoda de plată aleasă. </span>Plata se face pe bilete.online, într-o filă nouă. Biletele ajung pe email imediat după plată.</p>
        </div>
        <p class="emb-safe"><?= v2_ic('lock-simple') ?><span>Plată securizată cu cardul. Operator: <?= v2_e(navFlatName($orgData['name'] ?? '') ?: $lcName) ?></span></p>
      </aside>
    </div>
    <div class="bkx-bar" id="bkx-bar" hidden>
      <div><b id="bkx-bar-total">0 lei</b><span id="bkx-bar-count"></span></div>
      <button class="btn btn-primary" type="button" id="bkx-bar-go">Vezi rezervarea</button>
    </div>
  </section>
  <?php endif; ?>
</div>
<script type="application/json" id="v2-data"><?= json_encode($clientData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
  if (window.parent === window) return;
  var box = document.getElementById('emb'), last = 0;
  function post() {
    var h = Math.ceil(box.getBoundingClientRect().height);
    if (h && h !== last) { last = h; window.parent.postMessage({ type: 'bo-embed-height', height: h }, '*'); }
  }
  if ('ResizeObserver' in window) new ResizeObserver(post).observe(box);
  window.addEventListener('load', post);
  setInterval(post, 1500);
})();
</script>
<script defer src="<?= asset('assets/js/config.js') ?>"></script>
<script defer src="<?= asset('assets/js/cart.js') ?>"></script>
<script defer src="<?= v2_asset('js/booking.js') ?>"></script>
</body>
</html>
