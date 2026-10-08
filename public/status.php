<?php
/**
 * /status: whether viaqui.com works right now, and how it worked over the last 90 days, part by part.
 *
 * Data: GET marketplace-client/status (core, Api\MarketplaceClient\StatusController), from the checks
 * services:check-status makes every 5 minutes; the site itself is fetched from outside. Cached here 2 minutes.
 * A day without checks is shown as "no data", never as up. No page cache: the page is about now.
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
    $stOverall = ['is-unknown', v2_t('We cannot read the status right now'), v2_t('Try again in a few minutes. If you have a problem with an order, write to us from the contact page.')];
} elseif ($stDown) {
    $stOverall = ['is-down', v2_t('Problems with: {names}', ['names' => implode(', ', array_map(fn ($c) => (string) $c['name'], $stDown))]), v2_t('We are working on them. Orders already paid and tickets already issued stay valid.')];
} elseif (count($stUnknown) === count($stComponents)) {
    $stOverall = ['is-unknown', v2_t('We have no recent checks'), v2_t('The latest checks are more than 20 minutes old.')];
} else {
    $stOverall = ['is-up', v2_t('All systems working'), v2_t('We check every part every 5 minutes.')];
}

const ST_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function st_day(string $ymd): string
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) ? ((int) $m[3]) . ' ' . ST_MONTHS[(int) $m[2] - 1] . ' ' . $m[1] : $ymd;
}
function st_pct(?float $v): string
{
    if ($v === null) {
        return '-';
    }
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%';
}
/** A day's bar: its class and what the tooltip says. */
function st_bar(array $d): array
{
    [$date, $pct] = [$d[0] ?? '', $d[1] ?? null];
    if ($pct === null) {
        return ['is-none', v2_t('{day}: no data', ['day' => st_day((string) $date)])];
    }
    $cls = $pct >= 100 ? 'is-up' : ($pct >= 95 ? 'is-part' : 'is-down');
    return [$cls, v2_t('{day}: {pct} available', ['day' => st_day((string) $date), 'pct' => st_pct((float) $pct)])];
}
$stGenerated = '';
if (!empty($stData['generated_at'])) {
    try {
        $stGenerated = (new DateTimeImmutable((string) $stData['generated_at']))->setTimezone(new DateTimeZone('UTC'))->format('H:i');
    } catch (Exception $e) {
        $stGenerated = '';
    }
}

$pageTitleRaw = v2_t('Service status') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The live status of Viaqui: the site, the ticketing platform, payments and ticket delivery, with the history of the last 90 days.');
$canonicalUrl = SITE_URL . '/status';
$v2Styles = ['status.css'];
$v2Scripts = [];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="st" aria-labelledby="st-h">
    <div class="st-in">
      <p class="st-k"><?= v2_te('Service status') ?></p>
      <h1 id="st-h"><?= v2_te('How Viaqui is working right now') ?></h1>

      <div class="st-now <?= v2_e($stOverall[0]) ?>" role="status">
        <span class="st-now-dot" aria-hidden="true"></span>
        <div>
          <b><?= v2_e($stOverall[1]) ?></b>
          <p><?= v2_e($stOverall[2]) ?><?= $stGenerated !== '' ? ' ' . v2_te('Updated at {time} UTC.', ['time' => $stGenerated]) : '' ?></p>
        </div>
      </div>

      <?php if ($stComponents): ?>
      <div class="st-head"><h2><?= v2_te('Last {days} days', ['days' => (int) $stDays]) ?></h2><p class="st-legend"><span><i class="is-up"></i><?= v2_te('working') ?></span><span><i class="is-part"></i><?= v2_te('short outages') ?></span><span><i class="is-down"></i><?= v2_te('outage') ?></span><span><i class="is-none"></i><?= v2_te('no data') ?></span></p></div>
      <ul class="st-list">
        <?php foreach ($stComponents as $c):
            $cs = (string) ($c['status'] ?? 'unknown');
            $csTxt = ['up' => v2_t('Working'), 'down' => v2_t('Not working'), 'unknown' => v2_t('No recent checks')][$cs] ?? v2_t('Unknown');
        ?>
        <li class="st-c">
          <div class="st-c-h">
            <div><h3><?= v2_e((string) ($c['name'] ?? '')) ?></h3><p><?= v2_e((string) ($c['about'] ?? '')) ?></p></div>
            <span class="st-pill is-<?= v2_e($cs) ?>"><?= v2_e($csTxt) ?></span>
          </div>
          <div class="st-bars" role="img" aria-label="<?= v2_te('{name}: {pct} available in the last {days} days', ['name' => (string) ($c['name'] ?? ''), 'pct' => st_pct(isset($c['uptime']) ? (float) $c['uptime'] : null), 'days' => (int) $stDays]) ?>">
            <?php foreach ((array) ($c['days'] ?? []) as $d): [$bc, $bt] = st_bar((array) $d); ?><i class="<?= $bc ?>" title="<?= v2_e($bt) ?>"></i><?php endforeach; ?>
          </div>
          <div class="st-c-f"><span class="st-ago"><span class="st-90"><?= v2_te('{days} days ago', ['days' => (int) $stDays]) ?></span><span class="st-30"><?= v2_te('{days} days ago', ['days' => 30]) ?></span></span><span><?= v2_t('<b>{pct}</b> available', ['pct' => v2_e(st_pct(isset($c['uptime']) ? (float) $c['uptime'] : null))]) ?></span><span><?= v2_te('today') ?></span></div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="st-note">
        <h2><?= v2_te('How we measure') ?></h2>
        <p><?= v2_te('Every 5 minutes we automatically check each part above. We open the site from outside, just like a visitor. A day is green only if all its checks passed. Days from before a check existed show as “no data”, not green.') ?></p>
        <p><?= v2_t('Have a problem with an order or your tickets? <a href="{contact}">Write to us</a> or <a href="{find}">find your order</a>.', ['contact' => '/contact', 'find' => '/find-order']) ?></p>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
