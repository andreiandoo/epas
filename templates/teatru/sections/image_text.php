<?php
require_once __DIR__ . '/_events.php';
$left = ($s['image_position'] ?? 'right') === 'left';
$img = $s['image'] ?? null;
?>
<section class="py-24 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto grid <?= $img ? 'lg:grid-cols-2' : '' ?> gap-16 items-center">
        <div class="<?= $left ? 'lg:order-2' : '' ?>">
            <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
            <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl lg:text-5xl mb-6"><?= ts_md($s['title']) ?></h2><?php endif; ?>
            <div class="space-y-6 text-fg/70 text-lg leading-relaxed"><?= ts_paragraphs($s['text'] ?? '') ?></div>
            <?php if ($btn = teatru_button($s['button_label'] ?? '', $s['button_url'] ?? '', 'btn-primary px-6 py-3 rounded inline-block')): ?><div class="mt-8"><?= $btn ?></div><?php endif; ?>
        </div>
        <?php if ($img): ?>
        <div class="relative <?= $left ? 'lg:order-1' : '' ?>">
            <div class="absolute -inset-4 bg-gradient-to-r from-accent/20 to-primary/20 blur-3xl opacity-30"></div>
            <img src="<?= e($img) ?>" alt="" loading="lazy" class="relative rounded-xl border border-primary/20 w-full">
        </div>
        <?php endif; ?>
    </div>
</section>
