<?php
require_once __DIR__ . '/_events.php';
$img = !empty($s['image']) ? $s['image'] : teatru_ev_img(teatru_featured());
?>
<section class="relative min-h-screen flex items-center justify-center overflow-hidden curtain-texture">
    <div class="absolute inset-0">
        <img src="<?= e($img) ?>" alt="" class="w-full h-full object-cover opacity-40 scale-110" data-parallax="0.12">
        <div class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/80 to-canvas/40"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-accent/20 via-transparent to-accent/20"></div>
    </div>
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto pt-20" data-reveal>
        <?php if (!empty($s['eyebrow'])): ?><p class="text-primary tracking-[0.3em] text-sm mb-6 uppercase"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h1 class="font-display text-5xl sm:text-7xl lg:text-8xl mb-6 leading-tight"><?= ts_md($s['title'] ?? SITE_NAME) ?></h1>
        <?php if (!empty($s['subtitle'])): ?><p class="text-xl text-fg/70 max-w-2xl mx-auto mb-10"><?= ts_md($s['subtitle']) ?></p><?php endif; ?>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <?= teatru_button($s['button1_label'] ?? '', $s['button1_url'] ?? '', 'btn-primary px-8 py-4 rounded text-lg') ?>
            <?= teatru_button($s['button2_label'] ?? '', $s['button2_url'] ?? '', 'btn-outline px-8 py-4 rounded text-lg') ?>
        </div>
    </div>
    <?php if (!empty($s['show_scroll'])): ?>
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-primary/50">
        <span class="text-xs tracking-widest uppercase">Descoperă</span>
        <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
    </div>
    <?php endif; ?>
</section>
