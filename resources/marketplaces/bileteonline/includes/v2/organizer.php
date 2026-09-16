<?php
/**
 * bilete.online v2: organizer area shell (/organizator/*), the organizer's work space.
 *
 * Replaces includes/organizer-sidebar.php, organizer-topbar.php and organizer-footer.php on the pages ported to v2. A
 * page includes v2/head.php, calls v2_org_start('<key>'), prints its content (it lands inside <main>), calls
 * v2_org_end() and then includes v2/foot.php (cookie consent and scripts). It loads organizer.css + organizer.js, the
 * legacy config / utils / api / auth scripts, and puts v2_account_client_config() in $v2HeadExtra.
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

/** [group id, group label, [[key, url, label, icon, badge]]]; badge: a data-org-badge key, 'nou', or null. */
const V2_ORG_NAV = [
    ['main', '', [
        ['dashboard', '/organizator/panou', 'Dashboard', 'squares-four', null],
        ['events', '/organizator/activities', 'Activități', 'calendar-blank', 'events'],
        ['participants', '/organizator/participanti', 'Participanți', 'users-three', null],
        ['sales', '/organizator/vanzari', 'Vânzări', 'shopping-cart-simple', null],
        ['finance', '/organizator/sold', 'Sold', 'wallet', null],
        ['documents', '/organizator/documente', 'Documente', 'file-text', null],
    ]],
    ['venue', 'Locație', [
        ['venue-live', '/organizator/locatie/live', 'Dashboard live', 'lightning', 'nou'],
        ['venue-participants', '/organizator/locatie/participanti', 'Participanți', 'users-three', null],
        ['pos', '/organizator/pos', 'Casă & POS', 'scan', null],
    ]],
    ['marketing', 'Marketing', [
        ['services', '/organizator/servicii', 'Servicii extra', 'lightning', 'nou'],
        ['promo', '/organizator/promo', 'Coduri promoționale', 'tag', null],
        ['widgets', '/organizator/widget-uri', 'Widget-uri embed', 'code', null],
    ]],
    ['settings', 'Setări', [
        ['billing', '/organizator/facturare', 'Facturare', 'receipt', null],
        ['settings', '/organizator/setari', 'Cont & companie', 'gear-six', null],
        ['support', '/organizator/suport', 'Tichete suport', 'headset', 'support'],
        ['help', '/organizator/help', 'Centru de ajutor', 'question', null],
    ]],
];

/** What a screen reader hears after a badge number. */
const V2_ORG_BADGE_SR = ['events' => ' în derulare', 'support' => ' deschise'];

function v2_org_start(string $active): void
{
    ?>
<body class="org-page">
<?php readfile(__DIR__ . '/sprite.svg'); readfile(__DIR__ . '/sprite-org.svg'); ?>
<a class="skip" href="#main">Sari la conținut</a>
<div class="org" id="org">
  <aside class="org-side" id="org-side" aria-label="Contul de organizator">
    <div class="org-side-top">
      <a class="org-brand" href="/" aria-label="bilete.online, pagina principală"><?= v2_brand('brand') ?></a>
      <button class="org-x" type="button" data-org-drawer="close"><?= v2_ic('x') ?><span class="sr">Închide meniul</span></button>
      <span class="org-role">Organizator</span>
    </div>
    <nav class="org-nav" aria-label="Secțiuni organizator">
      <?php foreach (V2_ORG_NAV as [$groupId, $groupLabel, $items]): ?>
      <div class="org-group">
        <?php if ($groupLabel !== ''): ?><p class="org-group-t" id="org-g-<?= v2_e($groupId) ?>"><?= v2_e($groupLabel) ?></p><?php endif; ?>
        <ul class="org-links"<?= $groupLabel !== '' ? ' aria-labelledby="org-g-' . v2_e($groupId) . '"' : '' ?>>
          <?php foreach ($items as [$key, $url, $label, $icon, $badge]): ?>
          <li><a class="org-link" href="<?= v2_e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span class="org-link-t"><?= v2_e($label) ?></span><?php if ($badge === 'nou'): ?><span class="org-badge is-new">nou</span><?php elseif ($badge): ?><span class="org-badge<?= $badge === 'support' ? ' is-warn' : '' ?>" data-org-badge="<?= v2_e($badge) ?>" data-sr="<?= v2_e(V2_ORG_BADGE_SR[$badge] ?? '') ?>" hidden></span><?php endif; ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="org-me">
      <span class="org-avatar" data-org-initials aria-hidden="true">·</span>
      <div class="org-me-t"><p class="org-me-name" data-org-name>Organizator</p><p class="org-me-plan" data-org-plan>—</p></div>
      <button class="org-logout" type="button" data-org-logout title="Deconectare"><?= v2_ic('sign-out') ?><span class="sr">Deconectare</span></button>
    </div>
  </aside>
  <div class="org-scrim" data-org-drawer="close" hidden></div>

  <div class="org-col">
    <header class="org-top" id="org-top">
      <button class="org-ib org-burger" type="button" id="org-burger" data-org-drawer="open" aria-controls="org-side" aria-expanded="false"><?= v2_ic('list') ?><span class="sr">Deschide meniul</span></button>
      <a class="org-top-brand" href="/organizator/panou" aria-label="Panou organizator"><?= v2_brand('brand') ?></a>
      <div class="org-search" id="org-search" role="search">
        <label class="sr" for="org-q">Caută în activitățile tale</label>
        <?= v2_ic('magnifying-glass', 'ic org-search-ic') ?>
        <input id="org-q" type="search" autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="Caută activitățile tale…" role="combobox" aria-expanded="false" aria-controls="org-q-list" aria-autocomplete="list">
        <div class="org-pop org-q-pop" id="org-q-pop" hidden>
          <ul class="org-q-list" id="org-q-list" role="listbox" aria-label="Activitățile găsite"></ul>
          <p class="org-q-msg" id="org-q-msg" role="status"></p>
        </div>
      </div>
      <div class="org-tools">
        <button class="org-ib org-q-open" type="button" id="org-q-open" aria-controls="org-search" aria-expanded="false"><?= v2_ic('magnifying-glass') ?><span class="sr">Caută activități</span></button>
        <a class="btn btn-primary org-new" href="/organizator/activities?action=create"><?= v2_ic('plus') ?><span>Activitate nouă</span></a>
        <div class="org-drop">
          <button class="org-ib" type="button" id="org-bell" aria-expanded="false" aria-controls="org-notif"><?= v2_ic('bell') ?><span class="org-dot" id="org-dot" hidden></span><span class="sr" id="org-bell-t">Notificări</span></button>
          <div class="org-pop org-notif" id="org-notif" hidden>
            <div class="org-pop-head"><h2 class="org-pop-h">Notificări</h2><span class="org-pop-count" id="org-notif-count"></span></div>
            <div class="org-notif-list" id="org-notif-list"><p class="org-pop-empty">Se încarcă notificările…</p></div>
            <a class="org-pop-foot" href="/organizator/notificari">Vezi toate notificările<?= v2_ic('arrow-right') ?></a>
          </div>
        </div>
        <div class="org-drop">
          <button class="org-user" type="button" id="org-user" aria-expanded="false" aria-controls="org-usermenu"><span class="org-avatar is-sm" data-org-initials aria-hidden="true">·</span><?= v2_ic('caret-down') ?><span class="sr">Meniul contului</span></button>
          <div class="org-pop org-usermenu" id="org-usermenu" hidden>
            <div class="org-pop-me"><p class="org-pop-name" data-org-name>Organizator</p><p class="org-pop-mail" data-org-email></p></div>
            <a class="org-mi" href="/organizator/setari"><?= v2_ic('gear-six') ?>Setări cont</a>
            <a class="org-mi" href="/organizator/help"><?= v2_ic('question') ?>Ajutor & suport</a>
            <button class="org-mi is-danger" type="button" data-org-logout><?= v2_ic('sign-out') ?><span>Deconectare</span></button>
          </div>
        </div>
      </div>
    </header>
    <div class="org-pending" id="org-pending" hidden>
      <span class="org-pending-ic"><?= v2_ic('warning-circle') ?></span>
      <div class="org-pending-t"><p><b>Contul tău este în așteptare</b></p><p>Completează profilul și încarcă documentele necesare (CI/CUI) pentru a-l activa.</p></div>
      <a class="btn org-pending-cta" href="/organizator/setari#contract">Completează acum</a>
    </div>
    <main class="org-main" id="main" tabindex="-1">
<?php
}

function v2_org_end(): void
{
    ?>
    </main>
    <footer class="org-ftr">
      <nav class="org-ftr-links" aria-label="Resurse pentru organizatori">
        <a href="/organizator/help"><?= v2_ic('file-text') ?>Documentație</a>
        <a href="/organizator/apidoc"><?= v2_ic('code') ?>API</a>
        <a href="/termeni"><?= v2_ic('file-text') ?>Termeni</a>
        <a href="/organizator/suport"><?= v2_ic('question') ?>Suport</a>
      </nav>
      <p class="org-ftr-status"><span aria-hidden="true"></span>Toate sistemele funcționale</p>
      <p class="org-ftr-copy">© <?= date('Y') ?> bilete.online · operat de <a href="https://tixello.ro" target="_blank" rel="noopener">Tixello</a></p>
    </footer>
  </div>
</div>
<p class="org-flash" id="org-flash" role="status" aria-live="polite"></p>
<?php
}
