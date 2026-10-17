<?php
/** Despre agenție: textul și câteva cifre mari. */
$stats = array_values(array_filter((array) ($s['stats'] ?? []), fn ($x) => !empty($x['value'])));
?>
<section class="ag-section" id="despre" aria-labelledby="ag-about-title">
    <div class="ag-wrap ag-about">
        <div data-reveal>
            <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? t('Despre noi')) ?></p>
            <h2 class="ag-h2" id="ag-about-title"><?= e($s['title'] ?? SITE_NAME) ?></h2>
            <div class="ag-prose"><?= ts_paragraphs($s['text'] ?? '') ?></div>
        </div>
        <?php if ($stats): ?>
        <dl class="ag-stats" data-stagger>
            <?php foreach ($stats as $x): ?><div><dt><?= e($x['label'] ?? '') ?></dt><dd><?= e($x['value']) ?></dd></div><?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </div>
</section>
