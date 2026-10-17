<?php
/** Îndemnul final: gala următoare, numărătoare, buton de bilete. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = gx_next();
if (!$ev) {
    return;
}
?>
<section class="gx-cta">
    <?php if (!empty($ev['poster_url'])): ?><img src="<?= e($ev['poster_url']) ?>" alt="" loading="lazy" data-parallax="0.08"><?php endif; ?>
    <div class="gx-cta__shade" aria-hidden="true"></div>
    <div class="gx-wrap gx-cta__body" data-reveal>
        <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h2 class="gx-cta__title"><?= ts_md($s['title'] ?? '') ?></h2>
        <?= gx_countdown($ev, 'gx-cd--center') ?>
        <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?><a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#bilete" class="gx-btn gx-btn--lg"><?= e($s['button_label']) ?> <?= GX_ARROW ?></a><?php endif; ?>
    </div>
</section>
