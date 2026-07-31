<?php
/**
 * COMPONENT: locale-switcher
 * Renders nothing if the site has a single locale. Otherwise a small dropdown
 * that switches locale via ?lang=xx (kept in a cookie), preserving the path.
 */
$locales = kit_locales();
if (count($locales) <= 1) return;
$active = kit_locale();
$path   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
?>
<details class="kit-locale">
  <summary><?= e(strtoupper($active)) ?></summary>
  <div class="kit-locale__menu">
    <?php foreach ($locales as $lc): ?>
      <a href="<?= e($path . '?lang=' . $lc) ?>" class="<?= $lc === $active ? 'is-active' : '' ?>"><?= e(kit_locale_name($lc)) ?></a>
    <?php endforeach; ?>
  </div>
</details>
