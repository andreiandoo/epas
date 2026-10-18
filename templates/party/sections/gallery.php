<?php
/**
 * Galeria edițiilor trecute: o bandă orizontală fixată pe ecran, derulată cu scroll-ul
 * (GSAP ScrollTrigger, fx.js). Fiecare ediție e o „rolă” de fotografii cu titlul ei;
 * click pe o fotografie o deschide mare. Pe mobil și cu animațiile oprite: bandă care se
 * derulează cu degetul.
 */
$albums = pt_albums(5);
if (!$albums) {
    return;
}
?>
<section class="pt-gal" id="galerie-acasa" aria-labelledby="pt-gal-title" data-pt-reel>
    <div class="pt-wrap pt-gal__head">
        <div>
            <p class="pt-eyebrow"><?= e($s['eyebrow'] ?? t('Galerie')) ?></p>
            <h2 class="pt-h2" id="pt-gal-title"><?= e($s['title'] ?? '') ?></h2>
        </div>
        <a href="/galerie" class="pt-btn pt-btn--ghost"><?= e($s['button_label'] ?? t('Toate albumele')) ?> <?= PT_ARROW ?></a>
    </div>
    <div class="pt-gal__viewport">
        <div class="pt-gal__track" data-pt-track>
            <?php foreach ($albums as $a): $dp = pt_date_parts($a); ?>
            <article class="pt-roll">
                <a class="pt-roll__title" href="<?= e(ts_link('event', ['slug' => $a['slug']])) ?>">
                    <small><?= e($dp['day'] . ' ' . $dp['month'] . ' ' . $dp['year']) ?> · <?= e(pt_city($a)) ?></small>
                    <b><?= e(pt_theme($a)) ?></b>
                    <span><?= te('{n} fotografii', ['n' => count($a['gallery'])]) ?> <?= PT_ARROW ?></span>
                </a>
                <?php foreach (array_slice($a['gallery'], 0, 5) as $k => $src): ?>
                <button type="button" class="pt-roll__shot pt-roll__shot--<?= $k % 3 ?>" data-pt-lightbox="<?= e($src) ?>" aria-label="<?= e(t('Mărește fotografia') . ': ' . pt_theme($a)) ?>">
                    <img src="<?= e(preg_replace('/w=\d+/', 'w=700', $src)) ?>" alt="" loading="lazy">
                </button>
                <?php endforeach; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
