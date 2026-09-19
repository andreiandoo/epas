<?php
/**
 * bilete.online v2 — customer account shell for /cont/*.
 *
 * Replaces includes/client-sidebar-v2.php on v2 pages. A page calls v2_account_start('<key>') right after the header
 * and v2_account_end() before the footer; its content goes in between (inside .acc-main). The page also loads
 * account.css + account.js (and the legacy config/utils/api/auth scripts).
 *
 * Desktop: a sticky sidebar with the user card, the sections with badges, logout and a tip. Phones: the same
 * sections as a scrolling bar above the content (the old mobile drawer had no button that opened it, so phones had no
 * account navigation). account.js fills in name / initials / email from the session and the badges.
 */

const V2_ACCOUNT_NAV = [
    ['dashboard', '/cont', 'Dashboard', 'user-circle'],
    ['tickets', '/cont/bilete', 'Biletele mele', 'ticket'],
    ['orders', '/cont/comenzi', 'Comenzile mele', 'shopping-cart-simple'],
    ['points', '/cont/puncte', 'Punctele mele', 'coins'],
    ['recommendations', '/cont/recomandari', 'Recomandări', 'star'],
    ['reviews', '/cont/recenzii', 'Recenziile mele', 'check-circle'],
    ['support', '/cont/tichete-support', 'Tichete support', 'headset'],
    ['settings', '/cont/setari', 'Setări', 'lock-simple'],
];

/**
 * The window.BILETEONLINE settings the legacy api.js / auth.js read; account pages put it in $v2HeadExtra.
 * It starts with the login guard, so a visitor never sees the account shell signed out. $session says whose area it
 * is: 'customer' (the /cont pages) needs a customer session and goes to /autentificare?redirect=<this page>;
 * 'organizer' (the /organizator pages) needs an organizer session, or an admin's _admin_token handoff that auth.js
 * turns into one, and goes to the venue login, which brings the organizer back to this page (auth.js keeps it for
 * the tab). The organizer pages used to get the customer guard, so a signed-in organizer bounced between the login
 * and the dashboard.
 */
function v2_account_client_config(string $session = 'customer'): string
{
    $guard = $session === 'organizer'
        ? 'ok = /[?&]_admin_token=/.test(location.search) || (!!localStorage.getItem(\'bileteonline_organizer_token\') && localStorage.getItem(\'bileteonline_user_type\') === \'organizer\'); } catch (e) {} '
            . 'if (!ok) { document.documentElement.style.visibility = \'hidden\'; try { sessionStorage.setItem(\'bileteonline_redirect_after_login\', location.href); } catch (e) {} location.replace(\'/autentificare?ca=venue\'); } })();</script>'
        : 'var type = localStorage.getItem(\'bileteonline_user_type\'); ok = !!localStorage.getItem(\'bileteonline_customer_token\') && (!type || type === \'customer\'); } catch (e) {} '
            . 'if (!ok) { document.documentElement.style.visibility = \'hidden\'; location.replace(\'/autentificare?redirect=\' + encodeURIComponent(location.pathname + location.search + location.hash)); } })();</script>';

    return '<script>(function () { var ok = false; try { ' . $guard
        . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => 'RON',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
}

function v2_account_start(string $active): void
{
    ?>
<div class="acc">
  <aside class="acc-side" aria-label="Contul meu">
    <div class="acc-user">
      <span class="acc-avatar" data-acc-initials aria-hidden="true">?</span>
      <div class="acc-user-t"><p class="acc-name" data-acc-name>Client</p><p class="acc-email" data-acc-email>—</p></div>
    </div>
    <nav class="acc-nav" aria-label="Secțiuni cont">
      <?php foreach (V2_ACCOUNT_NAV as [$key, $url, $label, $icon]): ?>
      <a class="acc-link" href="<?= v2_e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span class="acc-link-t"><?= v2_e($label) ?></span><?php if ($key === 'recommendations'): ?><span class="acc-badge is-new">nou</span><?php else: ?><span class="acc-badge" data-acc-badge="<?= v2_e($key) ?>" hidden></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <button class="acc-logout" type="button" data-acc-logout><?= v2_ic('arrow-left') ?><span>Deconectare</span></button>
    <div class="acc-tip">
      <b>Tip</b>
      <p>Activează notificările push pentru a primi reminder înainte de evenimentele tale.</p>
    </div>
  </aside>

  <nav class="acc-mnav" aria-label="Secțiuni cont">
    <div class="acc-mnav-track">
      <?php foreach (V2_ACCOUNT_NAV as [$key, $url, $label, $icon]): ?>
      <a class="acc-chip" href="<?= v2_e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span><?= v2_e($label) ?></span></a>
      <?php endforeach; ?>
      <button class="acc-chip is-logout" type="button" data-acc-logout><?= v2_ic('arrow-left') ?><span>Deconectare</span></button>
    </div>
  </nav>

  <div class="acc-main" id="acc-main">
<?php
}

function v2_account_end(): void
{
    ?>
  </div>
</div>
<?php
}
