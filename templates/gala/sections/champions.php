<?php
/** Campionii en titre (luptătorii cu centură), cu record și categorie. */
require_once TEMPLATE_DIR . '/lib.php';
$champs = array_values(array_filter(gx_fighters(), fn ($f) => !empty($f['is_champion'])));
if (!$champs) {
    return;
}
?>
<section class="gx-section gx-champs">
    <div class="gx-wrap">
        <div class="gx-head2 gx-head2--split" data-reveal>
            <div>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            </div>
            <a href="/luptatori" class="gx-link"><?= te('Toți luptătorii') ?> →</a>
        </div>
        <div class="gx-champs__grid" data-stagger>
            <?php foreach ($champs as $f): [$first, $last] = gx_name_parts($f['name']); ?>
            <a href="/luptatori#f<?= (int) $f['id'] ?>" class="gx-champ">
                <?= gx_portrait($f, 'gold', 'gx-pic--card') ?>
                <span class="gx-champ__txt">
                    <span class="gx-belt"><?= GX_BELT ?><?= e($f['weight_class'] ?? '') ?></span>
                    <span class="gx-champ__name"><span><?= e($first) ?></span><b><?= e($last) ?></b></span>
                    <small><?= e(trim(gx_flag($f['country'] ?? null) . ' ' . gx_record($f))) ?></small>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
