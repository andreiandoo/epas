<?php
/**
 * viaqui.com v2: the cards of the roads (includes/v2/map-roads.php), shared by /trasee and the
 * "other roads" block of a road page. A road has no photo of its own, so the card shows what a
 * rider reads first: the profile of the climb.
 *
 * Expects $roadCards: [[slug, title, ref, from, to, lead, modes, km, max, up, z, n]] — n is the
 * number the same road wears on the overview map, or 0 where there is no map.
 */
require_once __DIR__ . '/map-roads.php';
$rdCards = $roadCards ?? [];
?>
<ul class="rd-grid">
  <?php foreach ($rdCards as [$rSlug, $rTitle, $rRef, $rFrom, $rTo, $rLead, $rModes, $rKm, $rMax, $rUp, $rZ, $rN]): [$rLine, $rArea] = v2_profile_paths($rZ, 300, 56); ?>
  <li data-modes="<?= v2_e(implode(' ', $rModes)) ?>">
    <a class="rd" href="/trasee/<?= v2_e($rSlug) ?>" data-mode="<?= v2_e($rModes[0]) ?>"<?= $rN ? ' data-road="' . (int) $rN . '"' : '' ?>>
      <span class="rd-top">
        <?php if ($rN): ?><span class="rd-n" aria-hidden="true"><?= (int) $rN ?></span><?php endif; ?>
        <span class="rd-title"><?= v2_e($rTitle) ?></span>
        <?php if ($rRef !== ''): ?><span class="rd-ref"><?= v2_e($rRef) ?></span><?php endif; ?>
      </span>
      <span class="rd-ends"><?= v2_e($rFrom) ?> – <?= v2_e($rTo) ?></span>
      <?php if ($rLine !== ''): ?>
      <svg class="rd-prof" viewBox="0 0 300 56" preserveAspectRatio="none" aria-hidden="true"><path class="rd-prof-a" d="<?= v2_e($rArea) ?>"/><path class="rd-prof-l" d="<?= v2_e($rLine) ?>"/></svg>
      <?php endif; ?>
      <span class="rd-lead"><?= v2_e($rLead) ?></span>
      <?php if (!empty(MAP_ROADS[$rSlug]['osm'])): ?><span class="rd-src">sursa traseului: OpenStreetMap<?= !empty(MAP_ROADS[$rSlug]['by']) ? ' · ' . v2_e(MAP_ROADS[$rSlug]['by'][0]) : '' ?></span><?php endif; ?>
      <span class="rd-meta">
        <span><?= v2_ic('pl-road') ?><?= v2_e(v2_thousands((int) $rKm)) ?> km</span>
        <?php if ($rMax > 0): ?><span><?= v2_ic('pl-mountains') ?>max <?= v2_e(v2_thousands((int) $rMax)) ?> m</span><?php endif; ?>
        <?php if ($rUp > 0): ?><span>↑ <?= v2_e(v2_thousands((int) $rUp)) ?> m</span><?php endif; ?>
        <span class="rd-modes"><?php foreach ($rModes as $rm): ?><span title="<?= v2_e(MAP_ROAD_MODES[$rm][0] ?? '') ?>"><?= v2_ic(MAP_ROAD_MODES[$rm][1] ?? 'pl-road') ?><span class="sr"><?= v2_e(MAP_ROAD_MODES[$rm][0] ?? '') ?></span></span><?php endforeach; ?></span>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
