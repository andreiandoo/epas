<?php
/**
 * COMPONENT: auth-widget  (login link vs avatar+name)
 * Input: $login_url, $account_url
 * Alpine; reads the auth blob from localStorage[window.KIT.authKey].
 */
$login   = $login_url   ?? '/autentificare';
$account = $account_url ?? '/cont';
?>
<div class="kit-auth" x-data="kitAuthWidget()" x-init="init()" x-cloak>
  <a x-show="!user" href="<?= e($login) ?>" class="kit-site-nav__link">Contul meu</a>
  <a x-show="user" href="<?= e($account) ?>" class="kit-auth__chip">
    <span class="kit-auth__avatar" x-text="initials">··</span>
    <span x-text="firstName" class="kit-auth__name"></span>
  </a>
</div>
