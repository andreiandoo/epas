<?php
/**
 * COMPONENT: favorite-button (heart toggle)
 * Input: $type ('event'|'artist'), $id
 * Guests: stored in localStorage; logged-in: also synced via proxy 'fav-toggle'.
 */
$type = $type ?? 'event';
$id   = $id ?? 0;
if (!$id) return;
?>
<button type="button" class="kit-fav" x-data="kitFav(<?= e(json_encode($type)) ?>, <?= (int)$id ?>, <?= e(json_encode(['add' => t('fav.add'), 'remove' => t('fav.remove')], JSON_UNESCAPED_UNICODE)) ?>)"
        x-init="init()" @click="toggle()" :class="active ? 'is-active' : ''" :aria-label="active ? L.remove : L.add" :title="active ? L.remove : L.add">
  <span x-text="active ? '♥' : '♡'">♡</span>
</button>
