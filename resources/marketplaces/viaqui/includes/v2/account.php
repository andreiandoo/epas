<?php
/**
 * viaqui.com v2 — customer account shell for /cont/*.
 *
 * Replaces includes/client-sidebar-v2.php on v2 pages. A page calls v2_account_start('<key>') right after the header
 * and v2_account_end() before the footer; its content goes in between (inside .acc-main). The page also loads
 * account.css + account.js (and the legacy config/utils/api/auth scripts).
 *
 * Desktop: a sticky sidebar with the user card, the sections with badges, logout and a tip. Phones: the same
 * sections as a scrolling bar above the content (the old mobile drawer had no button that opened it, so phones had no
 * account navigation). account.js fills in name / initials / email from the session and the badges.
 */

// The labels here are plain English; v2_account_nav_label() gives the translated one where a link is printed.
const V2_ACCOUNT_NAV = [
    ['dashboard', '/account', 'Overview', 'user-circle'],
    ['tickets', '/account/tickets', 'My tickets', 'ticket'],
    ['orders', '/account/orders', 'My orders', 'shopping-cart-simple'],
    ['points', '/account/points', 'My points', 'coins'],
    ['recommendations', '/cont/recomandari', 'Recommendations', 'star'], // no English address yet
    ['reviews', '/account/reviews', 'My reviews', 'check-circle'],
    ['support', '/account/support', 'Support tickets', 'headset'],
    ['settings', '/account/settings', 'Settings', 'lock-simple'],
];

/** The translated label of a V2_ACCOUNT_NAV section (a constant cannot call v2_t). */
function v2_account_nav_label(string $key, string $fallback = ''): string
{
    switch ($key) {
        case 'dashboard': return v2_t('Overview');
        case 'tickets': return v2_t('My tickets');
        case 'orders': return v2_t('My orders');
        case 'points': return v2_t('My points');
        case 'recommendations': return v2_t('Recommendations');
        case 'reviews': return v2_t('My reviews');
        case 'support': return v2_t('Support tickets');
        case 'settings': return v2_t('Settings');
    }
    return $fallback;
}

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
            . 'if (!ok) { document.documentElement.style.visibility = \'hidden\'; try { sessionStorage.setItem(\'bileteonline_redirect_after_login\', location.href); } catch (e) {} location.replace(\'/login?ca=venue\'); } })();</script>'
        : 'var type = localStorage.getItem(\'bileteonline_user_type\'); ok = !!localStorage.getItem(\'bileteonline_customer_token\') && (!type || type === \'customer\'); } catch (e) {} '
            . 'if (!ok) { document.documentElement.style.visibility = \'hidden\'; location.replace(\'/login?redirect=\' + encodeURIComponent(location.pathname + location.search + location.hash)); } })();</script>';

    return '<script>(function () { var ok = false; try { ' . $guard
        . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
}

function v2_account_start(string $active): void
{
    ?>
<div class="acc">
  <aside class="acc-side" aria-label="<?= v2_te('My account') ?>">
    <div class="acc-user">
      <span class="acc-avatar" data-acc-initials aria-hidden="true">?</span>
      <div class="acc-user-t"><p class="acc-name" data-acc-name><?= v2_te('Customer') ?></p><p class="acc-email" data-acc-email>—</p></div>
    </div>
    <nav class="acc-nav" aria-label="<?= v2_te('Account sections') ?>">
      <?php foreach (V2_ACCOUNT_NAV as [$key, $url, $label, $icon]): ?>
      <a class="acc-link" href="<?= v2_e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span class="acc-link-t"><?= v2_e(v2_account_nav_label($key, $label)) ?></span><?php if ($key === 'recommendations'): ?><span class="acc-badge is-new"><?= v2_te('new') ?></span><?php else: ?><span class="acc-badge" data-acc-badge="<?= v2_e($key) ?>" hidden></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <button class="acc-logout" type="button" data-acc-logout><?= v2_ic('arrow-left') ?><span><?= v2_te('Sign out') ?></span></button>
    <div class="acc-tip">
      <b><?= v2_te('Tip') ?></b>
      <p><?= v2_te('Turn on push notifications to get a reminder before your activities.') ?></p>
    </div>
  </aside>

  <nav class="acc-mnav" aria-label="<?= v2_te('Account sections') ?>">
    <div class="acc-mnav-track">
      <?php foreach (V2_ACCOUNT_NAV as [$key, $url, $label, $icon]): ?>
      <a class="acc-chip" href="<?= v2_e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= v2_ic($icon) ?><span><?= v2_e(v2_account_nav_label($key, $label)) ?></span></a>
      <?php endforeach; ?>
      <button class="acc-chip is-logout" type="button" data-acc-logout><?= v2_ic('arrow-left') ?><span><?= v2_te('Sign out') ?></span></button>
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
