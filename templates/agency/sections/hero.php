<?php
/**
 * Afișul: o frază mare despre agenție, lângă un mozaic cu artiștii recomandați (fotografiile
 * se schimbă încet, numele lor rulează dedesubt). Rosterul e produsul: totul duce la el.
 */
$roster = ag_roster();
$featured = array_values(array_filter($roster, fn ($a) => !empty($a['featured']) && !empty($a['photo'])));
if (count($featured) < 4) {
    $featured = array_values(array_filter($roster, fn ($a) => !empty($a['photo'])));
}
$tiles = array_slice($featured, 0, 5);
?>
<section class="ag-hero">
    <div class="ag-wrap ag-hero__grid">
        <div class="ag-hero__txt">
            <?php if (!empty($s['eyebrow'])): ?><p class="ag-eyebrow" data-ag-in><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h1 class="ag-hero__title" data-ag-in><?= ts_md($s['title'] ?? SITE_NAME) ?></h1>
            <?php if (!empty($s['text'])): ?><p class="ag-lead" data-ag-in><?= ts_md($s['text']) ?></p><?php endif; ?>
            <div class="ag-hero__actions" data-ag-in>
                <a href="<?= e(ts_link('artists')) ?>" class="ag-btn ag-btn--lg"><?= e($s['button_label'] ?? t('Vezi artiștii')) ?> <?= AG_ARROW ?></a>
                <a href="/booking" class="ag-btn ag-btn--ghost ag-btn--lg"><?= e($s['booking_label'] ?? t('Cere un booking')) ?></a>
            </div>
        </div>
        <?php if ($tiles): ?>
        <div class="ag-mosaic" data-ag-mosaic>
            <?php foreach ($tiles as $i => $a): ?>
            <a class="ag-mosaic__tile ag-mosaic__tile--<?= $i + 1 ?>" href="<?= e(ag_artist_url($a)) ?>">
                <img src="<?= e($a['photo']) ?>" alt="<?= e($a['name']) ?>"<?= $i ? ' loading="lazy"' : ' fetchpriority="high"' ?>>
                <span><b><?= e($a['name']) ?></b><small><?= e(implode(' · ', array_slice((array) ($a['genres'] ?? []), 0, 2))) ?></small></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($roster): ?>
    <div class="ag-names" aria-hidden="true">
        <div class="ag-names__track">
            <?php for ($k = 0; $k < 2; $k++): ?><span><?php foreach ($roster as $a): ?><?= e($a['name']) ?><i>/</i><?php endforeach; ?></span><?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
