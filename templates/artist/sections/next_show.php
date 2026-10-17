<?php
/** Următorul concert, mare: data, orașul, sala, numărătoarea, prețul de la care încep biletele. */
$ev = ar_next();
if (!$ev) {
    return;
}
$dp = ar_date_parts($ev);
[$code, $label] = ar_status($ev);
$from = ar_from_price($ev);
$url = ts_link('event', ['slug' => $ev['slug']]);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
?>
<section class="ar-section ar-next" aria-labelledby="ar-next-title">
    <div class="ar-wrap ar-next__grid">
        <div class="ar-next__info" data-reveal>
            <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? t('Următorul concert')) ?></p>
            <div class="ar-next__date"><b><?= e($dp['day']) ?></b><span><?= e($dp['month']) ?><br><?= e($dp['year']) ?></span></div>
            <h2 class="ar-next__city" id="ar-next-title"><?= e(ar_city($ev)) ?></h2>
            <p class="ar-next__venue"><?= e(ar_venue($ev)) ?> · <?= e(ar_when($ev)) ?></p>
            <?= ar_countdown($ev) ?>
            <div class="ar-next__buy">
                <a href="<?= e($url) ?>#bilete" class="ar-btn ar-btn--lg"><?= e($s['button_label'] ?? t('Cumpără bilete')) ?> <?= AR_ARROW ?></a>
                <?php if ($from): ?><span class="ar-next__from"><?= te('de la') ?> <b><?= e(ar_money($from)) ?></b></span><?php endif; ?>
                <?php if ($code === 'low'): ?><span class="ar-pill ar-pill--hot"><?= e($label) ?></span><?php endif; ?>
            </div>
        </div>
        <?php if ($img): ?>
        <a class="ar-next__img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true" data-reveal>
            <img src="<?= e($img) ?>" alt="" loading="lazy" data-parallax="0.08">
        </a>
        <?php endif; ?>
    </div>
</section>
