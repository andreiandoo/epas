<?php
/** Îndemnul final, pe culoarea albumului: orașele turneului ca butoane rapide. */
$list = array_slice(array_values(array_filter(ar_upcoming(), fn ($ev) => !in_array(ar_status($ev)[0], ['sold_out', 'external'], true))), 0, 6);
?>
<section class="ar-cta" aria-labelledby="ar-cta-title">
    <div class="ar-wrap" data-reveal>
        <p class="ar-eyebrow ar-eyebrow--light"><?= e($s['eyebrow'] ?? t('Turneu')) ?></p>
        <h2 class="ar-cta__title" id="ar-cta-title"><?= e($s['title'] ?? '') ?></h2>
        <?php if ($list): ?>
        <div class="ar-cta__cities">
            <?php foreach ($list as $ev): ?>
            <a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#bilete"><b><?= e(ar_city($ev)) ?></b><span><?= e(ar_date_parts($ev)['day'] . ' ' . ar_date_parts($ev)['month']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= e(ts_link('events')) ?>" class="ar-btn ar-btn--light ar-btn--lg"><?= e($s['button_label'] ?? t('Alege orașul')) ?> <?= AR_ARROW ?></a>
    </div>
</section>
