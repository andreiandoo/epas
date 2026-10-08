<?php
/**
 * viaqui.com v2: the route cards, shared by /routes and the "other routes" block at the bottom
 * of a route page. Expects $routeCards: [[slug, title, lead, emoji, pace, count, km, img]].
 */
$rcCards = $routeCards ?? ($routePage['others'] ?? []);
?>
<ul class="rc-grid">
  <?php foreach ($rcCards as $rcRow): [$cSlug, $cTitle, $cLead, $cEmoji, $cPace, $cCount, $cKm, $cImg] = $rcRow; $cMin = (int) ($rcRow[8] ?? 0); ?>
  <li>
    <a class="rc" href="/routes/<?= v2_e($cSlug) ?>">
      <span class="rc-media">
        <?php if ($cImg): ?><img src="<?= v2_e(v2_thumb($cImg, 640, 400)) ?>" alt="" width="480" height="300" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($cTitle) ?><?php endif; ?>
        <?php $cFlags = function_exists('v2_flag') ? implode('', array_map('v2_flag', (array) ($rcRow[9] ?? []))) : ''; ?>
        <?php if ($cFlags !== ''): ?><span class="rc-flags" aria-hidden="true"><?= $cFlags ?></span>
        <?php else: ?><span class="rc-emoji" aria-hidden="true"><?= am_product_icon_svg(am_product_icon($cEmoji) ?? 'map', 'ic-em') ?></span><?php endif; ?>
      </span>
      <span class="rc-body">
        <span class="rc-title"><?= v2_e($cTitle) ?></span>
        <span class="rc-lead"><?= v2_e($cLead) ?></span>
        <span class="rc-meta">
          <span><?= v2_ic('map-pin') ?><?= v2_e(v2_num((int) $cCount, 'stop', 'stops')) ?></span>
          <span><?= v2_ic('arrow-right') ?><?= v2_te('{n} km', ['n' => v2_thousands((int) $cKm)]) ?></span>
          <span><?= v2_ic('clock') ?><?= $cMin > 0 ? v2_e(v2_hm($cMin)) : v2_e($cPace) ?></span>
        </span>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
