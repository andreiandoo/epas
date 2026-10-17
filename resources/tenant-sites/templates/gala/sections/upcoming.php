<?php
/** Celelalte gale viitoare (fără cea din hero): afiș, main event, dată, loc. */
require_once TEMPLATE_DIR . '/lib.php';
$next = gx_next();
$list = array_values(array_filter(gx_upcoming(), fn ($e) => !$next || $e['slug'] !== $next['slug']));
if (!$list) {
    return;
}
?>
<section class="gx-section">
    <div class="gx-wrap">
        <div class="gx-head2" data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
        </div>
        <div class="gx-events" data-stagger>
            <?php foreach (array_slice($list, 0, 4) as $e): include TEMPLATE_DIR . '/layout/event-card.php'; endforeach; ?>
        </div>
    </div>
</section>
