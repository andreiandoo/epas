<?php
/** Îndemnul final, pe culoarea albumului: orașele turneului ca butoane rapide. */
$list = array_slice(array_values(array_filter(bd_upcoming(), fn ($ev) => !in_array(bd_status($ev)[0], ['sold_out', 'external'], true))), 0, 6);
?>
<section class="bd-cta" aria-labelledby="bd-cta-title">
    <div class="bd-wrap" data-reveal>
        <p class="bd-eyebrow bd-eyebrow--light"><?= e($s['eyebrow'] ?? t('Turneu')) ?></p>
        <h2 class="bd-cta__title" id="bd-cta-title"><?= e($s['title'] ?? '') ?></h2>
        <?php if ($list): ?>
        <div class="bd-cta__cities">
            <?php foreach ($list as $ev): ?>
            <a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#bilete"><b><?= e(bd_city($ev)) ?></b><span><?= e(bd_date_parts($ev)['day'] . ' ' . bd_date_parts($ev)['month']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= e(ts_link('events')) ?>" class="bd-btn bd-btn--light bd-btn--lg"><?= e($s['button_label'] ?? t('Alege orașul')) ?> <?= BD_ARROW ?></a>
    </div>
</section>
