<?php
/**
 * viaqui.com v2: organizer area shell (/organizator/*), the organizer's work space.
 *
 * Replaces includes/organizer-sidebar.php, organizer-topbar.php and organizer-footer.php on the pages ported to v2. A
 * page includes v2/head.php, calls v2_org_start('<key>'), prints its content (it lands inside <main>), calls
 * v2_org_end() and then includes v2/foot.php (cookie consent and scripts). It loads organizer.css + organizer.js, the
 * legacy config / utils / api / auth scripts, and puts v2_account_client_config('organizer') in $v2HeadExtra.
 *
 * Everything the previous organizer chrome had is here: the sections with their badges (activities in progress, open
 * support tickets, "nou" on extra services), the organizer card with logout, the activity search, "Activitate nouă",
 * the notifications list, the account menu, the "account pending" banner and the footer links, status and credit.
 * organizer.js guards the area (a signed-out visitor goes to the organizer login), fills in the organizer, the badges
 * and the notifications, and runs the search, the menus and the phone drawer. Page scripts use window.BO_ORG.
 *
 * Fixed on the way: the notifications list never loaded (the poller was skipped on organizer pages, so the bell always
 * said there was nothing new), the open-tickets badge had nothing filling it, the search only looked through the first
 * page of activities while promising participants too, phones had no search, and the banner kept a stale account
 * status until the next page.
 */
require_once __DIR__ . '/account.php'; // v2_account_client_config()

/**
 * [group id, group label, [[key, url, label, icon, badge]]]; badge: a data-org-badge key, 'nou', or null.
 * viaqui.com sells no events: the operator works on locations, products (access tickets, experiences, packages) and
 * their bookings. The event screens (activities, participants, sales, the leisure "Locație" section, promo codes) stay
 * reachable by address but are no longer in the menu; the desk, the report and the widgets have operator versions.
 */
const V2_ORG_NAV = [
    ['main', '', [
        ['dashboard', '/organizator/panou', 'Dashboard', 'squares-four', null],
        ['am-bookings', '/organizator/rezervari', 'Bookings', 'calendar-blank', null],
        ['am-report', '/organizator/raport', 'Report', 'chart-line-up', null],
        ['finance', '/organizator/sold', 'Balance', 'wallet', null],
    ]],
    ['catalog', 'Venues and products', [
        ['am-locations', '/organizator/locatii', 'My venues', 'map-pin', null],
        ['am-products', '/organizator/produse', 'Products', 'ticket', null],
        ['am-pos', '/organizator/pos', 'Desk & POS', 'scan', null],
    ]],
    ['promo', 'Promotion', [
        ['am-widgets', '/organizator/widget-uri', 'Embed widgets', 'code', null],
        ['services', '/organizator/servicii', 'Extra services', 'lightning', 'nou'],
    ]],
    ['settings', 'Settings', [
        ['billing', '/organizator/facturare', 'Billing', 'receipt', null],
        ['settings', '/organizator/setari', 'Account & company', 'gear-six', null],
        ['team', '/organizator/echipa', 'Account team', 'users-three', null],
        ['support', '/organizator/suport', 'Support tickets', 'headset', 'support'],
        ['help', '/organizator/help', 'Help centre', 'question', null],
    ]],
];

/** The translated label of a V2_ORG_NAV entry (a constant cannot call v2_t). The labels in the constant are plain English. */
function v2_org_nav_label(string $key, string $fallback = ''): string
{
    switch ($key) {
        case 'dashboard': return v2_t('Dashboard');
        case 'am-bookings': return v2_t('Bookings');
        case 'am-report': return v2_t('Report');
        case 'finance': return v2_t('Balance');
        case 'am-locations': return v2_t('My venues');
        case 'am-products': return v2_t('Products');
        case 'am-pos': return v2_t('Desk & POS');
        case 'am-widgets': return v2_t('Embed widgets');
        case 'services': return v2_t('Extra services');
        case 'billing': return v2_t('Billing');
        case 'settings': return v2_t('Account & company');
        case 'team': return v2_t('Account team');
        case 'support': return v2_t('Support tickets');
        case 'help': return v2_t('Help centre');
    }
    return $fallback;
}

/** The translated label of a V2_ORG_NAV group. */
function v2_org_group_label(string $groupId, string $fallback = ''): string
{
    switch ($groupId) {
        case 'catalog': return v2_t('Venues and products');
        case 'promo': return v2_t('Promotion');
        case 'settings': return v2_t('Settings');
    }
    return $fallback;
}

/** What a screen reader hears instead of a bare badge number; organizer.js puts the number in {n}. */
function v2_org_badge_sr(string $badge): string
{
    switch ($badge) {
        case 'events': return v2_t('In progress: {n}');
        case 'support': return v2_t('Open support tickets: {n}');
    }
    return '';
}

function v2_org_start(string $active): void
{
    ?>
<body class="org-page">
<?php readfile(__DIR__ . '/sprite.svg'); readfile(__DIR__ . '/sprite-org.svg'); ?>
<a class="skip" href="#main"><?= v2_te('Skip to content') ?></a>
<div class="org" id="org">
<script>(function () { // folded sidebar before the first paint: the operator's choice, or folded on the product editor
  var o = document.getElementById('org'), f = false;
  try { f = localStorage.getItem('bo_org_fold') === '1'; } catch (e) {}
  if (location.pathname.indexOf('/organizator/produse') === 0 && /[?&](nou|id)=/.test(location.search)) { f = true; document.documentElement.classList.add('org-wz-boot'); }
  if (f) o.classList.add('is-folded');
})();</script>
  <aside class="org-side" id="org-side" aria-label="<?= v2_te('Operator account') ?>">
    <div class="org-side-top">
      <a class="org-brand" href="/" aria-label="<?= v2_te('Viaqui, home page') ?>"><?= v2_brand('brand') ?></a>
      <button class="org-x" type="button" data-org-drawer="close"><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the menu') ?></span></button>
      <span class="org-role"><?= v2_te('Operator') ?></span>
      <button class="org-fold" type="button" data-org-fold aria-expanded="true" aria-controls="org-side" title="<?= v2_te('Collapse the menu') ?>"><?= v2_ic('caret-down') ?><span class="sr"><?= v2_te('Collapse the menu') ?></span></button>
    </div>
    <nav class="org-nav" aria-label="<?= v2_te('Account sections') ?>">
      <?php foreach (V2_ORG_NAV as [$groupId, $groupLabel, $items]): ?>
      <?php $groupLabel = $groupLabel !== '' ? v2_org_group_label($groupId, $groupLabel) : ''; ?>
      <div class="org-group">
        <?php if ($groupLabel !== ''): ?><p class="org-group-t" id="org-g-<?= v2_e($groupId) ?>"><?= v2_e($groupLabel) ?></p><?php endif; ?>
        <ul class="org-links"<?= $groupLabel !== '' ? ' aria-labelledby="org-g-' . v2_e($groupId) . '"' : '' ?>>
          <?php foreach ($items as [$key, $url, $label, $icon, $badge]): ?>
          <?php $label = v2_org_nav_label($key, $label); ?>
          <li><a class="org-link" href="<?= v2_e($url) ?>" title="<?= v2_e($label) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span class="org-link-t"><?= v2_e($label) ?></span><?php if ($badge === 'nou'): ?><span class="org-badge is-new"><?= v2_te('new') ?></span><?php elseif ($badge): ?><span class="org-badge<?= $badge === 'support' ? ' is-warn' : '' ?>" data-org-badge="<?= v2_e($badge) ?>" data-sr="<?= v2_e(v2_org_badge_sr($badge)) ?>" hidden></span><?php endif; ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="org-me">
      <span class="org-avatar" data-org-initials aria-hidden="true">·</span>
      <div class="org-me-t"><p class="org-me-name" data-org-name><?= v2_te('Operator') ?></p></div>
      <button class="org-logout" type="button" data-org-logout title="<?= v2_te('Sign out') ?>"><?= v2_ic('sign-out') ?><span class="sr"><?= v2_te('Sign out') ?></span></button>
    </div>
  </aside>
  <div class="org-scrim" data-org-drawer="close" hidden></div>

  <div class="org-col">
    <header class="org-top" id="org-top">
      <button class="org-ib org-burger" type="button" id="org-burger" data-org-drawer="open" aria-controls="org-side" aria-expanded="false"><?= v2_ic('list') ?><span class="sr"><?= v2_te('Open the menu') ?></span></button>
      <a class="org-top-brand" href="/organizator/panou" aria-label="<?= v2_te('Operator dashboard') ?>"><?= v2_brand('brand') ?></a>
      <div class="org-search" id="org-search" role="search">
        <label class="sr" for="org-q"><?= v2_te('Search your products and venues') ?></label>
        <?= v2_ic('magnifying-glass', 'ic org-search-ic') ?>
        <input id="org-q" type="search" autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="<?= v2_te('Search products or venues…') ?>" role="combobox" aria-expanded="false" aria-controls="org-q-list" aria-autocomplete="list">
        <div class="org-pop org-q-pop" id="org-q-pop" hidden>
          <ul class="org-q-list" id="org-q-list" role="listbox" aria-label="<?= v2_te('Results') ?>"></ul>
          <p class="org-q-msg" id="org-q-msg" role="status"></p>
        </div>
      </div>
      <div class="org-tools">
        <button class="org-ib org-q-open" type="button" id="org-q-open" aria-controls="org-search" aria-expanded="false"><?= v2_ic('magnifying-glass') ?><span class="sr"><?= v2_te('Search products or venues') ?></span></button>
        <a class="btn btn-primary org-new" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?><span><?= v2_te('New product') ?></span></a>
        <div class="org-drop">
          <button class="org-ib" type="button" id="org-bell" aria-expanded="false" aria-controls="org-notif"><?= v2_ic('bell') ?><span class="org-dot" id="org-dot" hidden></span><span class="sr" id="org-bell-t"><?= v2_te('Notifications') ?></span></button>
          <div class="org-pop org-notif" id="org-notif" hidden>
            <div class="org-pop-head"><h2 class="org-pop-h"><?= v2_te('Notifications') ?></h2><span class="org-pop-count" id="org-notif-count"></span></div>
            <div class="org-notif-list" id="org-notif-list"><p class="org-pop-empty"><?= v2_te('Loading notifications…') ?></p></div>
            <a class="org-pop-foot" href="/organizator/notificari"><?= v2_te('See all notifications') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </div>
        <div class="org-drop">
          <button class="org-user" type="button" id="org-user" aria-expanded="false" aria-controls="org-usermenu"><span class="org-avatar is-sm" data-org-initials aria-hidden="true">·</span><?= v2_ic('caret-down') ?><span class="sr"><?= v2_te('Account menu') ?></span></button>
          <div class="org-pop org-usermenu" id="org-usermenu" hidden>
            <div class="org-pop-me"><p class="org-pop-name" data-org-name><?= v2_te('Operator') ?></p><p class="org-pop-mail" data-org-email></p></div>
            <a class="org-mi" href="/organizator/setari"><?= v2_ic('gear-six') ?><?= v2_te('Account settings') ?></a>
            <a class="org-mi" href="/organizator/panou?ghid=1"><?= v2_ic('play') ?><?= v2_te('Quick guide') ?></a>
            <a class="org-mi" href="/organizator/help"><?= v2_ic('question') ?><?= v2_te('Help & support') ?></a>
            <button class="org-mi is-danger" type="button" data-org-logout><?= v2_ic('sign-out') ?><span><?= v2_te('Sign out') ?></span></button>
          </div>
        </div>
      </div>
    </header>
    <div class="org-pending" id="org-pending" hidden>
      <span class="org-pending-ic"><?= v2_ic('warning-circle') ?></span>
      <div class="org-pending-t"><p><b><?= v2_te('Your account is pending') ?></b></p><p><?= v2_te('Complete your profile and upload the required documents (ID and company registration certificate) to activate it.') ?></p></div>
      <a class="btn org-pending-cta" href="/organizator/setari#contract"><?= v2_te('Complete it now') ?></a>
    </div>
    <main class="org-main" id="main" tabindex="-1">
<?php
}

function v2_org_end(): void
{
    ?>
    </main>
    <footer class="org-ftr">
      <nav class="org-ftr-links" aria-label="<?= v2_te('Resources for operators') ?>">
        <a href="/organizator/help"><?= v2_ic('file-text') ?><?= v2_te('Documentation') ?></a>
        <a href="/organizator/apidoc"><?= v2_ic('code') ?><?= v2_te('API') ?></a>
        <a href="/terms"><?= v2_ic('file-text') ?><?= v2_te('Terms') ?></a>
        <a href="/organizator/suport"><?= v2_ic('question') ?><?= v2_te('Support') ?></a>
      </nav>
      <a class="org-ftr-status" href="/status" title="<?= v2_te('Service status in real time and over the last 90 days') ?>"><span aria-hidden="true"></span><?= v2_te('All systems operational') ?></a>
      <p class="org-ftr-copy"><?= v2_t('© {year} Viaqui · operated by {company}', ['year' => date('Y'), 'company' => '<a href="https://tixello.ro" target="_blank" rel="noopener">Tixello</a>']) ?></p>
    </footer>
  </div>
</div>
<p class="org-flash" id="org-flash" role="status" aria-live="polite"></p>
<?php
}
