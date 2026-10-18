<?php
/** Îndemnul final: titlul mare cu efect de casetă și butonul spre petreceri. */
?>
<section class="pt-cta" aria-labelledby="pt-cta-title">
    <div class="pt-wrap" data-reveal>
        <p class="pt-eyebrow"><?= e($s['eyebrow'] ?? '') ?></p>
        <h2 class="pt-cta__title" id="pt-cta-title" data-text="<?= e($s['title'] ?? '') ?>"><?= e($s['title'] ?? '') ?></h2>
        <a href="<?= e(ts_link('events')) ?>" class="pt-btn pt-btn--lg"><?= e($s['button_label'] ?? t('Alege petrecerea')) ?> <?= PT_ARROW ?></a>
    </div>
</section>
