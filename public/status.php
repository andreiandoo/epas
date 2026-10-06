<?php
/**
 * /status — whether viaqui.com works right now, and how it worked over the last 90 days, part by part.
 *
 * Data: GET marketplace-client/status (core, Api\MarketplaceClient\StatusController), from the checks
 * services:check-status makes every 5 minutes; the site itself is fetched from outside. Cached here 2 minutes.
 * A day without checks is shown as "fără date", never as up. No page cache: the page is about now.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$stResp = api_cached('status_page_v1', fn () => api_get('/status'), 120);
$stData = (!empty($stResp['success']) && is_array($stResp['data'] ?? null)) ? $stResp['data'] : null;
$stComponents = array_values(array_filter((array) ($stData['components'] ?? []), 'is_array'));
$stDays = (int) ($stData['days'] ?? 90);

$stDown = array_values(array_filter($stComponents, fn ($c) => ($c['status'] ?? '') === 'down'));
$stUnknown = array_values(array_filter($stComponents, fn ($c) => ($c['status'] ?? '') === 'unknown'));
if (!$stComponents) {
    $stOverall = ['is-unknown', 'Nu putem citi starea acum', 'Încearcă din nou în câteva minute. Dacă ai o problemă cu o comandă, scrie-ne din pagina de contact.'];
} elseif ($stDown) {
    $stOverall = ['is-down', 'Probleme la: ' . implode(', ', array_map(fn ($c) => (string) $c['name'], $stDown)), 'Lucrăm la ele. Comenzile deja plătite și biletele emise rămân valabile.'];
} elseif (count($stUnknown) === count($stComponents)) {
    $stOverall = ['is-unknown', 'Nu avem verificări recente', 'Ultimele verificări sunt mai vechi de 20 de minute.'];
} else {
    $stOverall = ['is-up', 'Toate sistemele funcționale', 'Verificăm fiecare parte la fiecare 5 minute.'];
}

const ST_MONTHS = ['ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sep.', 'oct.', 'nov.', 'dec.'];
function st_day(string $ymd): string
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) ? ((int) $m[3]) . ' ' . ST_MONTHS[(int) $m[2] - 1] . ' ' . $m[1] : $ymd;
}
function st_pct(?float $v): string
{
    if ($v === null) {
        return '—';
    }
    return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%';
}
/** A day's bar: its class and what the tooltip says. */
function st_bar(array $d): array
{
    [$date, $pct] = [$d[0] ?? '', $d[1] ?? null];
    if ($pct === null) {
        return ['is-none', st_day((string) $date) . ': fără date'];
    }
    $cls = $pct >= 100 ? 'is-up' : ($pct >= 95 ? 'is-part' : 'is-down');
    return [$cls, st_day((string) $date) . ': ' . st_pct((float) $pct) . ' disponibil'];
}
$stGenerated = '';
if (!empty($stData['generated_at'])) {
    try {
        $stGenerated = (new DateTimeImmutable((string) $stData['generated_at']))->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('H:i');
    } catch (Exception $e) {
        $stGenerated = '';
    }
}

$pageTitleRaw = 'Starea serviciilor — ' . SITE_NAME;
$pageDescription = 'Starea în timp real a viaqui.com: site-ul, platforma de bilete, plățile și trimiterea biletelor, cu istoricul ultimelor 90 de zile.';
$canonicalUrl = SITE_URL . '/status';
$v2Styles = ['status.css'];
$v2Scripts = [];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="st" aria-labelledby="st-h">
    <div class="st-in">
      <p class="st-k">Stare servicii</p>
      <h1 id="st-h">Cum funcționează viaqui.com acum</h1>

      <div class="st-now <?= v2_e($stOverall[0]) ?>" role="status">
        <span class="st-now-dot" aria-hidden="true"></span>
        <div>
          <b><?= v2_e($stOverall[1]) ?></b>
          <p><?= v2_e($stOverall[2]) ?><?= $stGenerated !== '' ? ' Actualizat la ' . v2_e($stGenerated) . '.' : '' ?></p>
        </div>
      </div>

      <?php if ($stComponents): ?>
      <div class="st-head"><h2>Ultimele <?= (int) $stDays ?> de zile</h2><p class="st-legend"><span><i class="is-up"></i>funcțional</span><span><i class="is-part"></i>întreruperi scurte</span><span><i class="is-down"></i>întrerupere</span><span><i class="is-none"></i>fără date</span></p></div>
      <ul class="st-list">
        <?php foreach ($stComponents as $c):
            $cs = (string) ($c['status'] ?? 'unknown');
            $csTxt = ['up' => 'Funcțional', 'down' => 'Nu funcționează', 'unknown' => 'Fără verificări recente'][$cs] ?? 'Necunoscut';
        ?>
        <li class="st-c">
          <div class="st-c-h">
            <div><h3><?= v2_e((string) ($c['name'] ?? '')) ?></h3><p><?= v2_e((string) ($c['about'] ?? '')) ?></p></div>
            <span class="st-pill is-<?= v2_e($cs) ?>"><?= v2_e($csTxt) ?></span>
          </div>
          <div class="st-bars" role="img" aria-label="<?= v2_e((string) ($c['name'] ?? '')) ?>: <?= v2_e(st_pct(isset($c['uptime']) ? (float) $c['uptime'] : null)) ?> disponibil în ultimele <?= (int) $stDays ?> de zile">
            <?php foreach ((array) ($c['days'] ?? []) as $d): [$bc, $bt] = st_bar((array) $d); ?><i class="<?= $bc ?>" title="<?= v2_e($bt) ?>"></i><?php endforeach; ?>
          </div>
          <div class="st-c-f"><span class="st-ago"><span class="st-90">acum <?= (int) $stDays ?> de zile</span><span class="st-30">acum 30 de zile</span></span><span><b><?= v2_e(st_pct(isset($c['uptime']) ? (float) $c['uptime'] : null)) ?></b> disponibil</span><span>azi</span></div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="st-note">
        <h2>Cum măsurăm</h2>
        <p>La fiecare 5 minute verificăm automat fiecare parte de mai sus; site-ul îl deschidem din afară, exact ca un vizitator. O zi e verde doar dacă toate verificările ei au reușit. Zilele de dinainte de o verificare nouă apar „fără date”, nu verzi.</p>
        <p>Ai o problemă cu o comandă sau cu biletele? <a href="/contact">Scrie-ne</a> sau <a href="/recuperare-comanda">recuperează comanda</a>.</p>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
