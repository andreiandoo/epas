<?php
/**
 * COMPONENT: pagination
 * Input:
 *   $current (int), $last (int), $base (string URL, e.g. '/evenimente'),
 *   $param (string, default 'page'), $query (array extra query params)
 * Renders numbered pagination as plain links (SSR-friendly, no JS needed).
 */
$current = (int)($current ?? 1);
$last    = (int)($last ?? 1);
$base    = $base ?? '';
$param   = $param ?? 'page';
$query   = $query ?? [];
if ($last <= 1) return;

$link = function (int $p) use ($base, $param, $query) {
    $q = array_merge($query, [$param => $p]);
    return e($base . '?' . http_build_query($q));
};
// window of pages around current
$from = max(1, $current - 2);
$to   = min($last, $current + 2);
?>
<nav class="kit-pagination" aria-label="paginare">
  <?php if ($current > 1): ?><a href="<?= $link($current - 1) ?>" rel="prev">‹</a><?php endif; ?>
  <?php if ($from > 1): ?><a href="<?= $link(1) ?>">1</a><?php if ($from > 2) echo '<span>…</span>'; ?><?php endif; ?>
  <?php for ($p = $from; $p <= $to; $p++): ?>
    <?php if ($p === $current): ?><span class="is-active"><?= $p ?></span>
    <?php else: ?><a href="<?= $link($p) ?>"><?= $p ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($to < $last): ?><?php if ($to < $last - 1) echo '<span>…</span>'; ?><a href="<?= $link($last) ?>"><?= $last ?></a><?php endif; ?>
  <?php if ($current < $last): ?><a href="<?= $link($current + 1) ?>" rel="next">›</a><?php endif; ?>
</nav>
