<?php
require_once __DIR__ . '/_events.php';
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => !empty($i['title'])));
if (!$items) {
    return;
}
?>
<section class="py-24 px-4 lg:px-8 bg-gradient-to-b from-accent/10 to-transparent">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
            <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl"><?= ts_md($s['title']) ?></h2><?php endif; ?>
        </div>
        <div class="grid md:grid-cols-2 <?= count($items) > 2 ? 'lg:grid-cols-3' : '' ?> gap-8">
            <?php foreach ($items as $item): $facts = array_slice(array_filter((array) ($item['facts'] ?? []), fn ($f) => !empty($f['value'])), 0, 3); ?>
            <div class="bg-surface rounded-xl overflow-hidden">
                <?php if (!empty($item['image'])): ?><img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>" loading="lazy" class="w-full h-64 object-cover"><?php endif; ?>
                <div class="p-8">
                    <h3 class="font-display text-2xl mb-4"><?= e($item['title']) ?></h3>
                    <?php if (!empty($item['text'])): ?><p class="text-fg/70 <?= $facts ? 'mb-6' : '' ?>"><?= ts_md($item['text']) ?></p><?php endif; ?>
                    <?php if ($facts): ?>
                    <div class="grid grid-cols-<?= count($facts) ?> gap-4 text-center">
                        <?php foreach ($facts as $f): ?><div><p class="font-display text-2xl text-primary"><?= e($f['value']) ?></p><p class="text-sm text-muted"><?= e($f['label'] ?? '') ?></p></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
