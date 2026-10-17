<?php
/** Artiștii recomandați (bifați în panou) ca un raft de portrete; restul rosterului e la un click. */
$roster = ag_roster();
$list = array_values(array_filter($roster, fn ($a) => !empty($a['featured'])));
if (count($list) < 3) {
    $list = $roster;
}
$list = array_slice($list, 0, 8);
if (!$list) {
    return;
}
?>
<section class="ag-section" aria-labelledby="ag-feat-title">
    <div class="ag-wrap">
        <div class="ag-head2" data-reveal>
            <div>
                <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? t('Rosterul')) ?></p>
                <h2 class="ag-h2" id="ag-feat-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
            <a href="<?= e(ts_link('artists')) ?>" class="ag-btn ag-btn--ghost"><?= e($s['button_label'] ?? t('Tot rosterul')) ?> (<?= count($roster) ?>) <?= AG_ARROW ?></a>
        </div>
        <ul class="ag-cards" data-stagger>
            <?php foreach ($list as $a): ?><li><?= ag_card($a) ?></li><?php endforeach; ?>
        </ul>
    </div>
</section>
