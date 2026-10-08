<?php
/**
 * Organizer activity analytics: /organizator/analytics/{id} (analytics.php?event={id}), v2 design.
 *
 * Inside the v2 organizer shell. One activity over a period (7, 30, 90 days or everything): net revenue, tickets, page
 * views, conversion and the days left, each total with the chosen period beside it and its change against the period
 * before; sales over time with the campaigns marked; estimates for the next days and for the activity's day; ticket type
 * performance; campaigns with ROI (add, edit, delete, copy the UTM parameters); traffic sources and top locations, with a
 * map of Romania; recent sales; goals (add, edit, delete). Export: the period's data as CSV, the report as PDF.
 * org-analytics.js reads /organizer/events/{id}/analytics, /goals, /milestones and /organizer/events through the proxy.
 * Address: /organizator/analytics/{id}?perioada=7z|30z|90z (everything when missing).
 *
 * Fixed on the way:
 * - Export opened /analytics/export, which core does not have, with the session token in the address;
 * - the revenue change set all-time revenue against the previous period alone, "unique" visitors repeated the page views
 *   and the progress bars measured sales against made-up targets (1.5 × sales, 100.000 lei);
 * - the "live" map showed the period's cities as people online "now", on tiles from a third-party library: the map is
 *   drawn here from the Natural Earth outline (includes/v2/map-romania.svg) and says what it counts;
 * - goals read in English ("Revenue Goal", "1,224.00 EUR"), so did campaign types and "2 hours ago";
 * - the chart library came unpinned from a CDN and its page views per day were not measured: charts are drawn here;
 * - the forecast kept counting after the activity and past its capacity; goals and campaigns could not be edited or deleted.
 *
 * Every text goes through v2_t() / v2_te() (org-analytics.js: VQ.t / VQ.n).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$oaEventId = isset($_GET['event']) && is_string($_GET['event']) && ctype_digit($_GET['event']) ? (string) (int) $_GET['event'] : '';
$oaPeriods = ['7z' => v2_t('7 days'), '30z' => v2_t('30 days'), '90z' => v2_t('90 days'), 'tot' => v2_t('All time')];
$oaPeriod = isset($_GET['perioada']) && is_string($_GET['perioada']) && isset($oaPeriods[$_GET['perioada']]) ? $_GET['perioada'] : 'tot';
$oaCurrency = defined('SITE_CURRENCY_SYMBOL') ? SITE_CURRENCY_SYMBOL : '€';

$pageTitle = v2_t('Activity analytics');
$pageDescription = v2_t('Analytics for an activity on Viaqui: sales over time, estimates, tickets, campaigns, traffic sources, locations and goals.');
$canonicalUrl = SITE_URL . '/organizator/analytics' . ($oaEventId !== '' ? '/' . $oaEventId : '');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-analytics.css'];
$v2Scripts = ['organizer.js', 'org-analytics.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** A figure card. $label and $extra are HTML (already translated and escaped). */
$oaStat = function (string $key, string $label, string $icon, string $extra = '') {
    return '<article class="oa-stat" id="oa-st-' . $key . '">'
        . '<div class="oa-stat-top"><span class="oa-stat-ic">' . v2_ic($icon) . '</span><p class="oa-stat-k">' . $label . '</p></div>'
        . '<div class="oa-stat-vrow"><p class="oa-stat-v" id="oa-s-' . $key . '"><span class="org-skel oa-sk"></span></p><span class="oa-stat-tag" id="oa-s-' . $key . '-t"></span></div>'
        . '<p class="oa-stat-p" id="oa-s-' . $key . '-p"></p>'
        . '<div class="oa-stat-m" id="oa-s-' . $key . '-m" hidden><span class="oa-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100"><i></i></span><span class="oa-stat-mp"></span></div>'
        . $extra
        . '<div class="oa-stat-per oa-dep" id="oa-s-' . $key . '-per" hidden></div>'
        . '</article>';
};
$oaX = '<button class="oa-x" type="button" data-close aria-label="' . v2_te('Close') . '">' . v2_ic('x') . '</button>';
$oaMapFile = __DIR__ . '/../includes/v2/map-romania.svg';
$oaMap = is_file($oaMapFile) ? (string) file_get_contents($oaMapFile) : '';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="oa<?= $oaEventId === '' ? ' is-none' : '' ?>" id="oa" data-event="<?= htmlspecialchars($oaEventId, ENT_QUOTES) ?>">
  <header class="oa-head">
    <a class="oa-back" href="/organizator/activities"><?= v2_ic('arrow-left') ?><?= v2_te('Back to activities') ?></a>
    <div class="oa-head-row">
      <div class="oa-head-main">
        <span class="oa-thumb" id="oa-thumb" hidden></span>
        <div class="oa-head-t">
          <p class="org-k"><?= v2_te('Activity analytics') ?></p>
          <h1 class="oa-h" id="oa-title"><?= v2_te('Activity analytics') ?></h1>
          <p class="oa-info" id="oa-info"></p>
        </div>
      </div>
      <div class="oa-actions" id="oa-actions"<?= $oaEventId === '' ? ' hidden' : '' ?>>
        <a class="btn btn-ghost" id="oa-report" href="/organizator/report<?= $oaEventId !== '' ? '/' . $oaEventId : '' ?>"><?= v2_ic('file-text') ?><?= v2_te('Full report') ?></a>
        <div class="oa-menu" id="oa-menu">
          <button class="btn btn-primary" type="button" id="oa-export" aria-haspopup="menu" aria-expanded="false" aria-controls="oa-export-menu" disabled><?= v2_ic('download-simple') ?><span data-label><?= v2_te('Export') ?></span><?= v2_ic('caret-down', 'ic oa-caret') ?></button>
          <div class="oa-menu-list" id="oa-export-menu" role="menu" aria-labelledby="oa-export" hidden>
            <button class="oa-menu-i" type="button" role="menuitem" tabindex="-1" data-export="csv"><?= v2_ic('file-csv') ?><span><b><?= v2_te('Data for the period (CSV)') ?></b><small id="oa-csv-p"><?= v2_te('Days, tickets, traffic and locations') ?></small></span></button>
            <button class="oa-menu-i" type="button" role="menuitem" tabindex="-1" data-export="pdf"><?= v2_ic('file-text') ?><span><b><?= v2_te('Activity report (PDF)') ?></b><small><?= v2_te('Everything sold, from the start') ?></small></span></button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <section class="org-panel oa-bar" aria-label="<?= v2_te('Activity and period') ?>">
    <div class="oa-combo" id="oa-combo">
      <label class="oa-f-l" for="oa-event-q" id="oa-pick-l"><?= v2_te('Activity') ?></label>
      <div class="oa-combo-box">
        <?= v2_ic('magnifying-glass', 'ic oa-combo-ic') ?>
        <input id="oa-event-q" type="text" role="combobox" aria-expanded="false" aria-controls="oa-event-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Search for an activity') ?>">
        <button class="oa-combo-x" type="button" id="oa-event-clear" aria-label="<?= v2_te('Clear the search') ?>" hidden><?= v2_ic('x') ?></button>
        <?= v2_ic('caret-down', 'ic oa-combo-caret') ?>
      </div>
      <ul class="oa-combo-list" id="oa-event-list" role="listbox" aria-labelledby="oa-pick-l" hidden></ul>
    </div>
    <div class="oa-period">
      <span class="oa-f-l" id="oa-period-l"><?= v2_te('Period') ?></span>
      <div class="oa-seg" role="group" aria-labelledby="oa-period-l">
        <?php foreach ($oaPeriods as $oaKey => $oaLabel): ?><button class="oa-seg-b" type="button" data-period="<?= $oaKey ?>" aria-pressed="<?= $oaKey === $oaPeriod ? 'true' : 'false' ?>"><?= v2_e($oaLabel) ?></button><?php endforeach; ?>
      </div>
    </div>
    <div class="oa-bar-end">
      <span class="oa-live" id="oa-live" hidden><i aria-hidden="true"></i><span id="oa-live-n"></span></span>
      <button class="btn btn-ghost oa-map-btn" type="button" id="oa-map-open" disabled><?= v2_ic('map-trifold') ?><?= v2_te('Visitor map') ?></button>
    </div>
    <p class="oa-scope"><?= v2_te('Totals count from the start of sales. The chart, the estimates, the ticket trend, the traffic sources and the locations follow the chosen period.') ?></p>
  </section>

  <section class="org-panel oa-pickall" id="oa-pickall" aria-labelledby="oa-pickall-h" hidden>
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('To begin') ?></p><h2 class="org-panel-h" id="oa-pickall-h"><?= v2_te('Choose an activity') ?></h2><p class="org-panel-p"><?= v2_te('Analytics open for one activity at a time. Search for it above or choose one here.') ?></p></div>
      <a class="org-more" href="/organizator/activities"><?= v2_te('All activities') ?><?= v2_ic('arrow-right') ?></a>
    </div>
    <ul class="oa-evlist" id="oa-evlist"><li><span class="org-skel oa-sk-row"></span></li></ul>
  </section>

  <div class="org-empty oa-none" id="oa-none" hidden></div>

  <div class="oa-body" id="oa-body"<?= $oaEventId === '' ? ' hidden' : '' ?>>
    <section class="oa-stats" aria-labelledby="oa-stats-h">
      <h2 class="sr" id="oa-stats-h"><?= v2_te('At a glance') ?></h2>
      <?= $oaStat('revenue', v2_te('Net revenue'), 'coins', '<button class="oa-stat-link" type="button" id="oa-s-revenue-set" hidden>' . v2_ic('plus') . v2_te('Set a revenue goal') . '</button>') ?>
      <?= $oaStat('tickets', v2_te('Tickets sold'), 'ticket') ?>
      <?= $oaStat('views', v2_te('Views'), 'eye') ?>
      <?= $oaStat('conversion', v2_te('Conversion rate'), 'chart-line-up') ?>
      <?= $oaStat('days', v2_te('Until the activity'), 'calendar-blank') ?>
    </section>

    <div class="oa-grid is-wide">
      <section class="org-panel oa-chart oa-dep" id="oa-chart" aria-labelledby="oa-chart-h">
        <div class="org-panel-head">
          <div><p class="org-k"><?= v2_te('Over time') ?></p><h2 class="org-panel-h" id="oa-chart-h"><?= v2_te('Sales performance') ?></h2><p class="org-panel-p" id="oa-chart-p"></p></div>
          <div class="oa-toggles" role="group" aria-label="<?= v2_te('What the chart shows') ?>">
            <button class="oa-tg is-rev" type="button" data-metric="rev" aria-pressed="true"><i aria-hidden="true"></i><?= v2_te('Net revenue per day') ?></button>
            <button class="oa-tg is-tix" type="button" data-metric="tix" aria-pressed="true"><i aria-hidden="true"></i><?= v2_te('Tickets sold') ?></button>
          </div>
        </div>
        <div class="oa-plot" id="oa-plot" tabindex="0" role="group" aria-roledescription="<?= v2_te('chart') ?>" aria-label="<?= v2_te('Sales chart') ?>">
          <div class="oa-tip" id="oa-tip" hidden></div>
          <p class="oa-plot-msg" id="oa-plot-msg" hidden><?= v2_te('No sales in the chosen period.') ?></p>
        </div>
        <p class="sr" id="oa-plot-live" aria-live="polite"></p>
        <ol class="oa-marks" id="oa-marks" aria-label="<?= v2_te('Campaigns in the chart') ?>" hidden></ol>
        <dl class="oa-totals" id="oa-totals"></dl>
        <p class="oa-chart-note"><?= v2_te('Views are not measured per day, so they only show as a total, above.') ?></p>
      </section>

      <section class="oa-fc oa-dep" id="oa-fc" aria-labelledby="oa-fc-h">
        <div class="oa-fc-head"><span class="oa-fc-ic"><?= v2_ic('trend-up') ?></span><div><h2 class="oa-fc-h" id="oa-fc-h"><?= v2_te('Estimates') ?></h2><p class="oa-fc-p"><?= v2_te('Based on the pace of sales in the chosen period; the last 7 days weigh more.') ?></p></div></div>
        <div class="oa-fc-box"><p class="oa-fc-k" id="oa-fc-next-k"><?= v2_te('Next 7 days') ?></p><dl class="oa-fc-grid"><div><dt><?= v2_te('Estimated net revenue') ?></dt><dd class="is-net" id="oa-fc-rev">—</dd></div><div><dt><?= v2_te('Estimated tickets') ?></dt><dd id="oa-fc-tix">—</dd></div></dl></div>
        <div class="oa-fc-box" id="oa-fc-end"><p class="oa-fc-k" id="oa-fc-end-k"><?= v2_te('On the day of the activity') ?></p><dl class="oa-fc-grid"><div><dt><?= v2_te('Total net revenue') ?></dt><dd class="is-net" id="oa-fc-trev">—</dd></div><div><dt><?= v2_te('Tickets in total') ?></dt><dd id="oa-fc-ttix">—</dd></div></dl></div>
        <p class="oa-fc-note" id="oa-fc-note"></p>
      </section>
    </div>

    <div class="oa-grid is-wide">
      <section class="org-panel oa-dep" id="oa-types-panel" aria-labelledby="oa-types-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Tickets') ?></p><h2 class="org-panel-h" id="oa-types-h"><?= v2_te('Ticket type performance') ?></h2><p class="org-panel-p" id="oa-types-p"></p></div></div>
        <div class="oa-table-wrap">
          <table class="oa-table" id="oa-table">
            <caption class="sr"><?= v2_te('Sales of each ticket type') ?></caption>
            <thead><tr><th scope="col"><?= v2_te('Ticket type') ?></th><th scope="col" class="is-num"><?= v2_te('Price') ?></th><th scope="col" class="is-num"><?= v2_te('Sold') ?></th><th scope="col" class="is-num"><?= v2_te('Revenue') ?></th><th scope="col" class="is-num is-conv"><?= v2_te('Conversion') ?></th><th scope="col" class="is-num is-trend"><?= v2_te('Trend') ?></th></tr></thead>
            <tbody id="oa-types"><tr><td colspan="6"><span class="org-skel oa-sk-row"></span></td></tr></tbody>
            <tfoot id="oa-types-foot"></tfoot>
          </table>
        </div>
      </section>

      <section class="org-panel" aria-labelledby="oa-camp-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Promotion') ?></p><h2 class="org-panel-h" id="oa-camp-h"><?= v2_te('Campaigns and ROI') ?></h2></div><button class="btn btn-ghost oa-add" type="button" id="oa-camp-add" disabled><?= v2_ic('plus') ?><?= v2_te('Add') ?></button></div>
        <div class="oa-cards" id="oa-camps"><span class="org-skel oa-sk-row"></span></div>
      </section>
    </div>

    <div class="oa-grid">
      <section class="org-panel oa-dep" id="oa-traffic-panel" aria-labelledby="oa-traffic-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Where they come from') ?></p><h2 class="org-panel-h" id="oa-traffic-h"><?= v2_te('Traffic sources') ?></h2><p class="org-panel-p" id="oa-traffic-p"></p></div></div>
        <ul class="oa-bars" id="oa-traffic"><li><span class="org-skel oa-sk-row"></span></li></ul>
      </section>
      <section class="org-panel oa-dep" id="oa-loc-panel" aria-labelledby="oa-loc-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Where they are') ?></p><h2 class="org-panel-h" id="oa-loc-h"><?= v2_te('Top locations') ?></h2><p class="org-panel-p" id="oa-loc-p"></p></div><button class="org-more oa-linkbtn" type="button" id="oa-loc-map" disabled><?= v2_ic('map-trifold') ?><?= v2_te('See on the map') ?></button></div>
        <ol class="oa-rank" id="oa-locations"><li><span class="org-skel oa-sk-row"></span></li></ol>
      </section>
    </div>

    <div class="oa-grid is-wide">
      <section class="org-panel" aria-labelledby="oa-sales-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Sales') ?></p><h2 class="org-panel-h" id="oa-sales-h"><?= v2_te('Recent sales') ?></h2><p class="org-panel-p"><?= v2_te('The latest paid orders, with the amount the customer paid.') ?></p></div><a class="org-more" id="oa-all-sales" href="/organizator/vanzari"><?= v2_te('All sales') ?><?= v2_ic('arrow-right') ?></a></div>
        <ul class="oa-sales" id="oa-sales"><li><span class="org-skel oa-sk-row"></span></li></ul>
      </section>
      <section class="org-panel" aria-labelledby="oa-goals-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Targets') ?></p><h2 class="org-panel-h" id="oa-goals-h"><?= v2_te('Goals') ?></h2></div><button class="btn btn-ghost oa-add" type="button" id="oa-goal-add" disabled><?= v2_ic('plus') ?><?= v2_te('Add') ?></button></div>
        <div class="oa-cards" id="oa-goals"><span class="org-skel oa-sk-row"></span></div>
      </section>
    </div>
  </div>

  <dialog class="oa-dialog" id="oa-goal-d" aria-labelledby="oa-goal-h">
    <form class="oa-d-inner" id="oa-goal-form" novalidate>
      <div class="oa-d-head"><h2 class="oa-d-h" id="oa-goal-h"><?= v2_te('New goal') ?></h2><?= $oaX ?></div>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('What you track') ?></span>
        <span class="oa-select"><select id="oa-goal-type" aria-describedby="oa-goal-type-help"><option value="revenue"><?= v2_te('Net revenue') ?></option><option value="tickets"><?= v2_te('Tickets sold') ?></option><option value="visitors"><?= v2_te('Visitors') ?></option><option value="conversion_rate"><?= v2_te('Conversion rate') ?></option></select><?= v2_ic('caret-down') ?></span>
        <span class="oa-help" id="oa-goal-type-help"></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Name of the goal') ?></span>
        <input id="oa-goal-name" type="text" maxlength="255" autocomplete="off" aria-describedby="oa-goal-name-err">
        <span class="oa-err" id="oa-goal-name-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l" id="oa-goal-target-l"><?= v2_te('Target') ?></span>
        <span class="oa-amount"><input id="oa-goal-target" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-goal-target-err"><span id="oa-goal-unit" aria-hidden="true"><?= v2_e($oaCurrency) ?></span></span>
        <span class="oa-err" id="oa-goal-target-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Deadline (optional)') ?></span>
        <input id="oa-goal-deadline" type="date" aria-describedby="oa-goal-deadline-help oa-goal-deadline-err">
        <span class="oa-help" id="oa-goal-deadline-help"></span>
        <span class="oa-err" id="oa-goal-deadline-err" hidden></span>
      </label>
      <div class="oa-form-err" id="oa-goal-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="oa-goal-go"><span data-label><?= v2_te('Add the goal') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="oa-dialog is-small" id="oa-del-d" aria-labelledby="oa-del-h" aria-describedby="oa-del-p">
    <div class="oa-d-inner">
      <h2 class="oa-d-h" id="oa-del-h"><?= v2_te('Delete?') ?></h2>
      <p class="oa-d-p" id="oa-del-p"></p>
      <div class="oa-form-err" id="oa-del-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Never mind') ?></button><button class="btn oa-danger" type="button" id="oa-del-go"><span data-label><?= v2_te('Delete') ?></span></button></div>
    </div>
  </dialog>

  <dialog class="oa-dialog is-wide" id="oa-camp-d" aria-labelledby="oa-campd-h">
    <form class="oa-d-inner" id="oa-camp-form" novalidate>
      <div class="oa-d-head"><h2 class="oa-d-h" id="oa-campd-h"><?= v2_te('New campaign') ?></h2><?= $oaX ?></div>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Name of the campaign') ?></span>
        <input id="oa-camp-title" type="text" maxlength="255" autocomplete="off" placeholder="<?= v2_te('e.g. Facebook ad, September') ?>" aria-describedby="oa-camp-title-err">
        <span class="oa-err" id="oa-camp-title-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Type') ?></span>
        <span class="oa-select"><select id="oa-camp-type" aria-describedby="oa-camp-type-help">
          <optgroup label="<?= v2_te('Paid ads') ?>"><option value="campaign_fb">Facebook Ads</option><option value="campaign_instagram">Instagram Ads</option><option value="campaign_google">Google Ads</option><option value="campaign_tiktok">TikTok Ads</option><option value="campaign_other"><?= v2_te('Influencer or other ads') ?></option></optgroup>
          <optgroup label="<?= v2_te('Other actions') ?>"><option value="email"><?= v2_te('Email campaign') ?></option><option value="price"><?= v2_te('Price change') ?></option><option value="announcement"><?= v2_te('Announcement') ?></option><option value="press"><?= v2_te('Press release') ?></option><option value="lineup"><?= v2_te('New programme or new guests') ?></option><option value="custom"><?= v2_te('Something else') ?></option></optgroup>
        </select><?= v2_ic('caret-down') ?></span>
        <span class="oa-help" id="oa-camp-type-help"></span>
      </label>
      <div class="oa-f-row">
        <label class="oa-f"><span class="oa-f-l"><?= v2_te('Starts on') ?></span><input id="oa-camp-start" type="date" aria-describedby="oa-camp-start-err"><span class="oa-err" id="oa-camp-start-err" hidden></span></label>
        <label class="oa-f"><span class="oa-f-l"><?= v2_te('Ends on (optional)') ?></span><input id="oa-camp-end" type="date" aria-describedby="oa-camp-end-err"><span class="oa-err" id="oa-camp-end-err" hidden></span></label>
      </div>
      <p class="oa-help" id="oa-camp-dates-help"></p>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Budget (optional)') ?></span>
        <span class="oa-amount"><input id="oa-camp-budget" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-budget-help oa-camp-budget-err"><span aria-hidden="true"><?= v2_e($oaCurrency) ?></span></span>
        <span class="oa-help" id="oa-camp-budget-help"><?= v2_te('With a budget, we work out the ROI of the campaign.') ?></span>
        <span class="oa-err" id="oa-camp-budget-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l"><?= v2_te('Description (optional)') ?></span>
        <textarea id="oa-camp-desc" rows="3" maxlength="2000"></textarea>
      </label>
      <fieldset class="oa-fs" id="oa-camp-track">
        <legend><?= v2_te('Tracking in links') ?></legend>
        <label class="oa-f"><span class="oa-f-l"><?= v2_te('Campaign ID on the platform (optional)') ?></span><input id="oa-camp-pid" type="text" maxlength="100" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('e.g. 120215478965421') ?>"></label>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l">utm_source</span><input id="oa-camp-utm_source" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l">utm_medium</span><input id="oa-camp-utm_medium" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l">utm_campaign</span><input id="oa-camp-utm_campaign" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l"><?= v2_te('utm_content (optional)') ?></span><input id="oa-camp-utm_content" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
        </div>
        <p class="oa-help"><?= v2_te('Put the same parameters in the link of the ad, so that sales are attributed to the campaign. What you leave empty we fill in: the source and the medium from the type, the name from the campaign title.') ?></p>
      </fieldset>
      <fieldset class="oa-fs" id="oa-camp-impact" hidden>
        <legend><?= v2_te('Impact (optional)') ?></legend>
        <label class="oa-f"><span class="oa-f-l"><?= v2_te('What it influenced') ?></span><span class="oa-select"><select id="oa-camp-metric"><option value=""><?= v2_te('I prefer not to choose') ?></option><option value="tickets_sold"><?= v2_te('Tickets sold') ?></option><option value="page_views"><?= v2_te('Page views') ?></option><option value="revenue"><?= v2_te('Revenue') ?></option><option value="conversion_rate"><?= v2_te('Conversion rate') ?></option></select><?= v2_ic('caret-down') ?></span></label>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l"><?= v2_te('Value before') ?></span><input id="oa-camp-base" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-base-err"><span class="oa-err" id="oa-camp-base-err" hidden></span></label>
          <label class="oa-f"><span class="oa-f-l"><?= v2_te('Value after') ?></span><input id="oa-camp-post" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-post-err"><span class="oa-err" id="oa-camp-post-err" hidden></span></label>
        </div>
      </fieldset>
      <fieldset class="oa-fs" id="oa-camp-results" hidden>
        <legend><?= v2_te('Results from the platform') ?></legend>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l"><?= v2_te('Impressions') ?></span><input id="oa-camp-impr" type="text" inputmode="numeric" autocomplete="off" aria-describedby="oa-camp-impr-err"><span class="oa-err" id="oa-camp-impr-err" hidden></span></label>
          <label class="oa-f"><span class="oa-f-l"><?= v2_te('Clicks') ?></span><input id="oa-camp-clicks" type="text" inputmode="numeric" autocomplete="off" aria-describedby="oa-camp-clicks-err"><span class="oa-err" id="oa-camp-clicks-err" hidden></span></label>
        </div>
        <p class="oa-help"><?= v2_te('Attributed conversions and revenue are recalculated on their own every 30 minutes, from the visits that came with the UTM parameters of the campaign.') ?></p>
      </fieldset>
      <label class="oa-check" id="oa-camp-active-f" hidden><input type="checkbox" id="oa-camp-active"><span><?= v2_te('The campaign is active') ?></span></label>
      <div class="oa-form-err" id="oa-camp-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="oa-camp-go"><span data-label><?= v2_te('Add the campaign') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="oa-dialog is-map" id="oa-map-d" aria-labelledby="oa-map-h" aria-describedby="oa-map-p">
    <div class="oa-d-inner">
      <div class="oa-d-head"><div><h2 class="oa-d-h" id="oa-map-h"><?= v2_te('Visitor map') ?></h2><p class="oa-d-p" id="oa-map-p"></p></div><?= $oaX ?></div>
      <div class="oa-map-body">
        <figure class="oa-map">
          <svg class="oa-map-svg" id="oa-map-svg" viewBox="0 0 694.7 510" role="img" aria-labelledby="oa-map-cap"><?= $oaMap ?><g id="oa-map-dots"></g></svg>
          <figcaption class="oa-map-cap" id="oa-map-cap"></figcaption>
        </figure>
        <div class="oa-map-side">
          <p class="oa-map-sum" id="oa-map-sum"></p>
          <ol class="oa-rank is-map" id="oa-map-list"></ol>
          <p class="oa-help" id="oa-map-note"></p>
        </div>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
