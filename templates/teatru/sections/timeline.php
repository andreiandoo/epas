<?php
require_once __DIR__ . '/_events.php';
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => !empty($i['title']) || !empty($i['text'])));
if (!$items) {
    return;
}
?>
<section class="py-24 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
            <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
            <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl"><?= ts_md($s['title']) ?></h2><?php endif; ?>
        </div>
        <div class="space-y-8">
            <?php foreach ($items as $i => $item): ?>
            <div class="timeline-item <?= $i < count($items) - 1 ? 'pb-8' : '' ?>">
                <?php if (!empty($item['label'])): ?><p class="text-primary font-display text-2xl mb-2"><?= e($item['label']) ?></p><?php endif; ?>
                <?php if (!empty($item['title'])): ?><h3 class="font-display text-xl mb-2"><?= e($item['title']) ?></h3><?php endif; ?>
                <?php if (!empty($item['text'])): ?><p class="text-fg/70"><?= ts_md($item['text']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
