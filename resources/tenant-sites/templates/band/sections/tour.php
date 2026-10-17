<?php
/** Datele turneului ca rânduri mari (dată, oraș, sală, bilete); la hover apare fotografia concertului. */
$limit = max(1, (int) ($s['limit'] ?? 8));
$all = bd_upcoming();
$list = array_slice($all, 0, $limit);
?>
<section class="bd-section bd-tour" id="turneu" aria-labelledby="bd-tour-title">
    <div class="bd-wrap">
        <div class="bd-head2" data-reveal>
            <div>
                <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? t('Turneu')) ?></p>
                <h2 class="bd-h2" id="bd-tour-title"><?= e($s['title'] ?? t('Turneu')) ?></h2>
            </div>
            <?php if ($all): ?><p class="bd-head2__meta"><?= te('{n} concerte', ['n' => count($all)]) ?></p><?php endif; ?>
        </div>
        <?php if (!$list): ?>
        <p class="bd-empty"><?= te('Noile date de concert se anunță în curând.') ?></p>
        <?php else: ?>
        <ul class="bd-rows" data-bd-rows data-stagger>
            <?php foreach ($list as $ev): ?><?= bd_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php if (count($all) > $limit || !empty($s['button_label'])): ?>
        <div class="bd-center"><a href="<?= e(ts_link('events')) ?>" class="bd-btn bd-btn--ghost"><?= e($s['button_label'] ?? t('Toate datele')) ?> <?= BD_ARROW ?></a></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="bd-follow" aria-hidden="true" data-bd-follow><img alt=""></div>
</section>
