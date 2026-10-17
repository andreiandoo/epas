<?php
/** Îndemnul final: titlu mare peste imaginea festivalului, numărătoare, buton de bilete. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
$img = $ev['gallery'][0] ?? ($ev['poster_url'] ?? '');
?>
<section class="fx-cta">
    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy" data-parallax="0.1"><?php endif; ?>
    <div class="fx-cta__shade" aria-hidden="true"></div>
    <div class="fx-wrap fx-cta__body" data-reveal>
        <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow fx-eyebrow--light"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h2 class="fx-cta__title"><?= ts_md($s['title'] ?? '') ?></h2>
        <?= fest_countdown('fx-cd--center') ?>
        <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?><a href="/bilete" class="fx-btn fx-btn--lg"><?= e($s['button_label']) ?> <?= FEST_ARROW ?></a><?php endif; ?>
    </div>
</section>
