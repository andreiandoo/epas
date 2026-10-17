<?php
require_once __DIR__ . '/_events.php';
$center = ($s['align'] ?? 'center') === 'center';
?>
<section class="py-20 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto <?= $center ? 'text-center' : '' ?>" data-reveal>
        <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
        <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl mb-6"><?= ts_md($s['title']) ?></h2><?php endif; ?>
        <div class="space-y-5 text-fg/70 text-lg leading-relaxed"><?= ts_paragraphs($s['text'] ?? '') ?></div>
    </div>
</section>
