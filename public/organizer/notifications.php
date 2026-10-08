<?php
/**
 * Organizer notifications: /organizator/notificari (notifications.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: mark all as read, the figures (total, unread,
 * sales, documents), the type and read filters, the list (icon, title, message, time, type, details link, mark as
 * read, unread dot), the empty state and "load more". org-notifications.js talks to /organizer/notifications*.
 *
 * Fixed on the way:
 * - "Marchează toate ca citite" called /mark-all-read, which core doesn't have (it is /read-all): it never worked;
 * - the figures counted only the 20 loaded notifications: they now come from core's totals;
 * - the time came in English from core and the type filter without diacritics; links from core are kept only when
 *   they point inside the site;
 * - new: delete a notification. Core's DELETE /notifications/clear-read is caught by /notifications/{id}, so clearing
 *   all read notifications stays out until core fixes the route order.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Notifications');
$pageDescription = v2_t('An operator\'s notifications on Viaqui: sales, documents, payouts and services.');
$canonicalUrl = SITE_URL . '/organizator/notificari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-notifications.css'];
$v2Scripts = ['organizer.js', 'org-notifications.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$onStat = function (string $key, string $label, string $icon) {
    return '<article class="on-stat"><span class="on-stat-ic">' . v2_ic($icon) . '</span><div><p class="on-stat-k">' . $label . '</p><p class="on-stat-v" id="on-s-' . $key . '"><span class="org-skel on-sk"></span></p></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('notifications');
?>
<div class="on" id="on">
  <header class="on-head">
    <div>
      <p class="org-k"><?= v2_te('Account') ?></p>
      <h1 class="on-h"><?= v2_te('Notifications') ?></h1>
      <p class="on-lead"><?= v2_te('Keep track of sales and important updates.') ?></p>
    </div>
    <button class="btn btn-ghost" type="button" id="on-all" hidden><?= v2_ic('check') ?><span data-label><?= v2_te('Mark all as read') ?></span></button>
  </header>

  <section class="on-stats" aria-label="<?= v2_te('At a glance') ?>">
    <?= $onStat('total', v2_te('All notifications'), 'bell') ?>
    <?= $onStat('unread', v2_te('Unread'), 'warning-circle') ?>
    <?= $onStat('sales', v2_te('Sales'), 'ticket') ?>
    <?= $onStat('docs', v2_te('Documents'), 'file-text') ?>
  </section>

  <section class="org-panel" aria-labelledby="on-list-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('History') ?></p><h2 class="org-panel-h" id="on-list-h"><?= v2_te('Your notifications') ?></h2><p class="org-panel-p" id="on-list-p"></p></div>
      <div class="on-filters">
        <span class="on-select"><select id="on-type" aria-label="<?= v2_te('Notification type') ?>" aria-controls="on-list"><option value=""><?= v2_te('All types') ?></option></select><?= v2_ic('caret-down') ?></span>
        <span class="on-select"><select id="on-read" aria-label="<?= v2_te('Read or unread') ?>" aria-controls="on-list"><option value=""><?= v2_te('All notifications') ?></option><option value="0"><?= v2_te('Unread') ?></option><option value="1"><?= v2_te('Read') ?></option></select><?= v2_ic('caret-down') ?></span>
      </div>
    </div>
    <ul class="on-list" id="on-list" aria-live="polite"><li class="on-sk-row"><span class="org-skel"></span></li><li class="on-sk-row"><span class="org-skel"></span></li></ul>
    <div class="on-more-row"><button class="btn btn-ghost on-sm" type="button" id="on-more" hidden><span data-label><?= v2_te('Load more') ?></span></button></div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
