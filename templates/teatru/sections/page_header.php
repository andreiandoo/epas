<?php
$large = ($s['size'] ?? 'small') === 'large';
$img = $s['image'] ?? null;
?>
<?php if ($large || $img): ?>
<section class="relative <?= $large ? 'min-h-[60vh]' : 'min-h-[40vh]' ?> flex items-center justify-center pt-24 pb-12">
    <?php if ($img): ?>
    <div class="absolute inset-0">
        <img src="<?= e($img) ?>" alt="" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/70 to-canvas/40"></div>
    </div>
    <?php endif; ?>
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <?php if (!empty($s['eyebrow'])): ?><p class="text-primary tracking-[0.3em] text-sm mb-6 uppercase"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h1 class="font-display text-5xl sm:text-7xl mb-6"><?= ts_md($s['title'] ?? '') ?></h1>
        <?php if (!empty($s['subtitle'])): ?><p class="text-xl text-fg/70 max-w-2xl mx-auto"><?= ts_md($s['subtitle']) ?></p><?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="pt-32 pb-12 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <?php if (!empty($s['eyebrow'])): ?><p class="text-primary tracking-[0.2em] text-sm mb-3 uppercase"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h1 class="font-display text-5xl lg:text-6xl mb-6"><?= ts_md($s['title'] ?? '') ?></h1>
        <?php if (!empty($s['subtitle'])): ?><p class="text-fg/70 text-lg max-w-2xl"><?= ts_md($s['subtitle']) ?></p><?php endif; ?>
    </div>
</section>
<?php endif; ?>
