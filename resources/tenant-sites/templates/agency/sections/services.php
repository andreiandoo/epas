<?php
/** Ce face agenția, numerotat (01–04), ca o listă editorială, nu carduri. */
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => !empty($i['title'])));
if (!$items) {
    return;
}
?>
<section class="ag-section ag-section--alt" aria-labelledby="ag-srv-title">
    <div class="ag-wrap ag-services">
        <div data-reveal>
            <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? t('Ce facem')) ?></p>
            <h2 class="ag-h2" id="ag-srv-title"><?= e($s['title'] ?? '') ?></h2>
        </div>
        <ol class="ag-services__list" data-stagger>
            <?php foreach ($items as $i => $it): ?>
            <li><span><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><div><h3><?= e($it['title']) ?></h3><?php if (!empty($it['text'])): ?><p><?= ts_md($it['text']) ?></p><?php endif; ?></div></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
