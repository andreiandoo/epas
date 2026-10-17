<?php
/** Datele turneului ca rânduri mari (dată, oraș, sală, bilete); la hover apare fotografia concertului. */
$limit = max(1, (int) ($s['limit'] ?? 8));
$all = ar_upcoming();
$list = array_slice($all, 0, $limit);
?>
<section class="ar-section ar-tour" id="turneu" aria-labelledby="ar-tour-title">
    <div class="ar-wrap">
        <div class="ar-head2" data-reveal>
            <div>
                <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? t('Turneu')) ?></p>
                <h2 class="ar-h2" id="ar-tour-title"><?= e($s['title'] ?? t('Turneu')) ?></h2>
            </div>
            <?php if ($all): ?><p class="ar-head2__meta"><?= te('{n} concerte', ['n' => count($all)]) ?></p><?php endif; ?>
        </div>
        <?php if (!$list): ?>
        <p class="ar-empty"><?= te('Noile date de concert se anunță în curând.') ?></p>
        <?php else: ?>
        <ul class="ar-rows" data-ar-rows data-stagger>
            <?php foreach ($list as $ev): ?><?= ar_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php if (count($all) > $limit || !empty($s['button_label'])): ?>
        <div class="ar-center"><a href="<?= e(ts_link('events')) ?>" class="ar-btn ar-btn--ghost"><?= e($s['button_label'] ?? t('Toate datele')) ?> <?= AR_ARROW ?></a></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="ar-follow" aria-hidden="true" data-ar-follow><img alt=""></div>
</section>
