<?php
/** Fight card-ul galei următoare. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = gx_next();
if (!$ev) {
    return;
}
$card = gx_card($ev['slug']);
$past = false;
?>
<section class="gx-section" id="fight-card">
    <div class="gx-wrap">
        <div class="gx-head2" data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            <p class="gx-lead"><?= e($ev['title']) ?> · <?= e(gx_when($ev)) ?></p>
        </div>
        <?php include TEMPLATE_DIR . '/layout/card.php'; ?>
    </div>
</section>
