<?php
/** Biletele galei următoare, pe zone. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = gx_next();
if (!$ev) {
    return;
}
?>
<section class="gx-section gx-section--alt" id="bilete">
    <div class="gx-wrap">
        <div class="gx-head2 gx-head2--split" data-reveal>
            <div>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            </div>
            <div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="gx-lead"><?= ts_md($s['lead']) ?></p><?php endif; ?>
                <p class="gx-lead gx-lead--strong"><?= e($ev['title']) ?> · <?= e(gx_when($ev)) ?> · <?= e(gx_place($ev)) ?></p>
            </div>
        </div>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php if (trim((string) ($s['note'] ?? '')) !== ''): ?><p class="gx-note"><?= e($s['note']) ?></p><?php endif; ?>
    </div>
</section>
