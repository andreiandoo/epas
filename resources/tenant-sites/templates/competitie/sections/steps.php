<?php
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => !empty($i['title'])));
if (!$items) {
    return;
}
?>
<section class="sec" id="<?= e(($s['anchor'] ?? '') !== '' ? $s['anchor'] : 'cum-functioneaza') ?>">
    <div class="wrap">
        <div class="sec__head">
            <div>
                <?php if (!empty($s['eyebrow'])): ?><span class="label"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                <?php if (!empty($s['title'])): ?><h2 data-split><?= e($s['title']) ?></h2><?php endif; ?>
            </div>
        </div>
        <div class="steps" data-reveal-group>
            <?php foreach ($items as $i => $item): ?>
            <div class="step">
                <span class="step__n">PASUL <?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span class="step__ghost" aria-hidden="true"><?= $i + 1 ?></span>
                <h3><?= e($item['title']) ?></h3>
                <?php if (!empty($item['text'])): ?><p><?= e($item['text']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
