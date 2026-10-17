<section class="phead">
    <div class="wrap">
        <?php if (!empty($s['eyebrow'])): ?><span class="label" data-enter="0"><?= e($s['eyebrow']) ?></span><?php endif; ?>
        <h1 data-split data-split-now><?= e($s['title'] ?? '') ?></h1>
        <?php if (!empty($s['subtitle'])): ?><p data-enter="0.3"><?= e($s['subtitle']) ?></p><?php endif; ?>
    </div>
</section>
