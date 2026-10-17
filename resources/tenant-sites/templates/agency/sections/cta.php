<?php
/** Îndemnul la booking: o frază mare și butonul spre formular. */
?>
<section class="ag-cta" aria-labelledby="ag-cta-title">
    <div class="ag-wrap" data-reveal>
        <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? t('Booking')) ?></p>
        <h2 class="ag-cta__title" id="ag-cta-title"><?= e($s['title'] ?? '') ?></h2>
        <a href="/booking" class="ag-btn ag-btn--lg"><?= e($s['button_label'] ?? t('Cere un booking')) ?> <?= AG_ARROW ?></a>
    </div>
</section>
