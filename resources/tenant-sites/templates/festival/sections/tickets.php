<?php
/** Biletele pe prima pagină: titlul secțiunii + componenta comună din layout/tickets.php. */
require_once TEMPLATE_DIR . '/lib.php';
?>
<section class="fx-section fx-section--alt" id="bilete">
    <div class="fx-wrap">
        <div class="fx-head2 fx-head2--split" data-reveal>
            <div>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            </div>
            <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="fx-lead"><?= ts_md($s['lead']) ?></p><?php endif; ?>
        </div>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php if (trim((string) ($s['note'] ?? '')) !== ''): ?><p class="fx-note"><?= e($s['note']) ?></p><?php endif; ?>
    </div>
</section>
