<?php
/** Concertele agenției ca rânduri mari (dată, artiști, oraș și sală, bilete); la hover apare fotografia concertului. */
$limit = max(1, (int) ($s['limit'] ?? 8));
$all = ag_upcoming();
$list = array_slice($all, 0, $limit);
?>
<section class="ag-section ag-tour" id="evenimente" aria-labelledby="ag-tour-title">
    <div class="ag-wrap">
        <div class="ag-head2" data-reveal>
            <div>
                <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? t('Concerte')) ?></p>
                <h2 class="ag-h2" id="ag-tour-title"><?= e($s['title'] ?? t('Evenimente')) ?></h2>
            </div>
            <?php if ($all): ?><p class="ag-head2__meta"><?= te('{n} concerte', ['n' => count($all)]) ?></p><?php endif; ?>
        </div>
        <?php if (!$list): ?>
        <p class="ag-empty"><?= te('Noile date de concert se anunță în curând.') ?></p>
        <?php else: ?>
        <ul class="ag-rows" data-ag-rows data-stagger>
            <?php foreach ($list as $ev): ?><?= ag_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php if (count($all) > $limit || !empty($s['button_label'])): ?>
        <div class="ag-center"><a href="<?= e(ts_link('events')) ?>" class="ag-btn ag-btn--ghost"><?= e($s['button_label'] ?? t('Toate datele')) ?> <?= AG_ARROW ?></a></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="ag-follow" aria-hidden="true" data-ag-follow><img alt=""></div>
</section>
