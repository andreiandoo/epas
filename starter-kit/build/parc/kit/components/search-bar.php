<?php
/**
 * COMPONENT: search-bar
 * Input: $action (URL, req), $placeholder, $name (query param, default 'q'), $value
 * SSR GET form (works with no JS). A template can enhance it with instant
 * results via KitProxy('events',{q}) if desired.
 */
$action = $action ?? '/cauta';
$name   = $name ?? 'q';
?>
<form class="kit-search" method="get" action="<?= e($action) ?>" role="search">
  <input type="search" name="<?= e($name) ?>" value="<?= e($value ?? '') ?>"
         placeholder="<?= e($placeholder ?? 'Caută evenimente, artiști, locații…') ?>" autocomplete="off">
  <button type="submit" class="kit-btn kit-btn--primary" aria-label="Caută">🔍</button>
</form>
