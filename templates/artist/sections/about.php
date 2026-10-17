<?php
/** Despre artist: fotografia și povestea. */
$img = asset_url($s['image'] ?? '', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=1200&q=80');
?>
<section class="ar-section ar-about" id="despre" aria-labelledby="ar-about-title">
    <div class="ar-wrap ar-about__grid">
        <figure class="ar-about__img" data-reveal><img src="<?= e($img) ?>" alt="<?= e(SITE_NAME) ?>" loading="lazy" data-parallax="0.06"></figure>
        <div data-reveal>
            <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? t('Despre')) ?></p>
            <h2 class="ar-h2" id="ar-about-title"><?= e($s['title'] ?? SITE_NAME) ?></h2>
            <div class="ar-prose"><?= ts_paragraphs($s['text'] ?? '') ?></div>
        </div>
    </div>
</section>
