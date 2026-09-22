<?php
/**
 * bilete.online v2: the route cards, shared by /trasee and the "other routes" block at the bottom
 * of a route page. Expects $routeCards: [[slug, title, lead, emoji, pace, count, km, img]].
 */
$rcCards = $routeCards ?? ($routePage['others'] ?? []);
?>
<ul class="rc-grid">
  <?php foreach ($rcCards as $rcRow): [$cSlug, $cTitle, $cLead, $cEmoji, $cPace, $cCount, $cKm, $cImg] = $rcRow; $cMin = (int) ($rcRow[8] ?? 0); ?>
  <li>
    <a class="rc" href="/trasee/<?= v2_e($cSlug) ?>">
      <span class="rc-media">
        <?php if ($cImg): ?><img src="<?= v2_e(v2_thumb($cImg, 640, 400)) ?>" alt="" width="480" height="300" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($cTitle) ?><?php endif; ?>
        <span class="rc-emoji" aria-hidden="true"><?= v2_e($cEmoji) ?></span>
      </span>
      <span class="rc-body">
        <span class="rc-title"><?= v2_e($cTitle) ?></span>
        <span class="rc-lead"><?= v2_e($cLead) ?></span>
        <span class="rc-meta">
          <span><?= v2_ic('map-pin') ?><?= (int) $cCount ?> opriri</span>
          <span><?= v2_ic('arrow-right') ?><?= v2_e(v2_thousands((int) $cKm)) ?> km</span>
          <span><?= v2_ic('clock') ?><?= $cMin > 0 ? v2_e(v2_hm($cMin)) : v2_e($cPace) ?></span>
        </span>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
