<?php
require_once __DIR__ . '/_events.php';
$accent = ($s['style'] ?? 'plain') === 'accent';
?>
<section class="<?= $accent ? 'py-20 bg-accent/20' : 'py-24' ?> px-4 lg:px-8">
    <div class="max-w-3xl mx-auto text-center">
        <?php if (!empty($s['ornament'])): ?><div class="divider-ornate mb-6"><span class="text-primary text-2xl">✦</span></div><?php endif; ?>
        <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
        <?php if (!empty($s['title'])): ?><h2 class="font-display text-3xl lg:text-4xl mb-4"><?= ts_md($s['title']) ?></h2><?php endif; ?>
        <?php if (!empty($s['text'])): ?><p class="text-fg/70 mb-8 max-w-2xl mx-auto"><?= ts_md($s['text']) ?></p><?php endif; ?>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <?= teatru_button($s['button_label'] ?? '', $s['button_url'] ?? '', 'btn-primary px-8 py-4 rounded-lg') ?>
            <?= teatru_button($s['button2_label'] ?? '', $s['button2_url'] ?? '', 'btn-outline px-8 py-4 rounded-lg') ?>
        </div>
    </div>
</section>
