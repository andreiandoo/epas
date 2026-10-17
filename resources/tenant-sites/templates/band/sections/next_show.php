<?php
/** Următorul concert, mare: data, orașul, sala, numărătoarea, prețul de la care încep biletele. */
$ev = bd_next();
if (!$ev) {
    return;
}
$dp = bd_date_parts($ev);
[$code, $label] = bd_status($ev);
$from = bd_from_price($ev);
$url = ts_link('event', ['slug' => $ev['slug']]);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
?>
<section class="bd-section bd-next" aria-labelledby="bd-next-title">
    <div class="bd-wrap bd-next__grid">
        <div class="bd-next__info" data-reveal>
            <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? t('Următorul concert')) ?></p>
            <div class="bd-next__date"><b><?= e($dp['day']) ?></b><span><?= e($dp['month']) ?><br><?= e($dp['year']) ?></span></div>
            <h2 class="bd-next__city" id="bd-next-title"><?= e(bd_city($ev)) ?></h2>
            <p class="bd-next__venue"><?= e(bd_venue($ev)) ?> · <?= e(bd_when($ev)) ?></p>
            <?= bd_countdown($ev) ?>
            <div class="bd-next__buy">
                <a href="<?= e($url) ?>#bilete" class="bd-btn bd-btn--lg"><?= e($s['button_label'] ?? t('Cumpără bilete')) ?> <?= BD_ARROW ?></a>
                <?php if ($from): ?><span class="bd-next__from"><?= te('de la') ?> <b><?= e(bd_money($from)) ?></b></span><?php endif; ?>
                <?php if ($code === 'low'): ?><span class="bd-pill bd-pill--hot"><?= e($label) ?></span><?php endif; ?>
            </div>
        </div>
        <?php if ($img): ?>
        <a class="bd-next__img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true" data-reveal>
            <img src="<?= e($img) ?>" alt="" loading="lazy" data-parallax="0.08">
        </a>
        <?php endif; ?>
    </div>
</section>
