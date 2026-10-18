<?php
/** Edițiile următoare ca afișe (flyer): tema mare, orașul, data, valul de preț în curs. */
$limit = max(1, (int) ($s['limit'] ?? 6));
$all = pt_upcoming();
$list = array_slice($all, 0, $limit);
?>
<section class="pt-section" id="petreceri" aria-labelledby="pt-ed-title">
    <div class="pt-wrap">
        <div class="pt-head2" data-reveal>
            <div>
                <p class="pt-eyebrow"><?= e($s['eyebrow'] ?? t('Urmează')) ?></p>
                <h2 class="pt-h2" id="pt-ed-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
            <a href="<?= e(ts_link('events')) ?>" class="pt-btn pt-btn--ghost"><?= e($s['button_label'] ?? t('Toate petrecerile')) ?> <?= PT_ARROW ?></a>
        </div>
        <?php if (!$list): ?>
        <p class="pt-empty"><?= te('Edițiile noi se anunță în curând.') ?></p>
        <?php else: ?>
        <ul class="pt-flyers" data-stagger>
            <?php foreach ($list as $i => $ev): ?><li><?= pt_flyer($ev, $i) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>
