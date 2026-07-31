<?php
/**
 * COMPONENT: cookie-consent  (GDPR banner).
 * Rendered once by the public layout when config.cookie_consent is true.
 * Analytics (kit_analytics_config) only load after "Accept" — see kit.js.
 */
?>
<div class="kit-consent" x-data="kitConsent()" x-init="init()" x-show="show" x-cloak>
  <p><?= e(t('consent.text')) ?></p>
  <div class="kit-consent__actions">
    <button class="kit-btn kit-btn--outline" @click="reject()"><?= e(t('consent.reject')) ?></button>
    <button class="kit-btn kit-btn--primary" @click="accept()"><?= e(t('consent.accept')) ?></button>
  </div>
</div>
