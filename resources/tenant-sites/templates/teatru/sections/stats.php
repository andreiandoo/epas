<?php
$items = array_slice(array_filter((array) ($s['items'] ?? []), fn ($i) => !empty($i['value'])), 0, 4);
if (!$items) {
    return;
}
$cols = ['', 'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-2 lg:grid-cols-4'][count($items)];
?>
<section class="py-16 px-4 lg:px-8 bg-surface/30">
    <div class="max-w-7xl mx-auto grid <?= $cols ?> gap-8 text-center">
        <?php foreach ($items as $item): ?>
        <div><p class="font-display text-5xl lg:text-7xl text-primary mb-2"><?= e($item['value']) ?></p><p class="text-muted"><?= e($item['label'] ?? '') ?></p></div>
        <?php endforeach; ?>
    </div>
</section>
