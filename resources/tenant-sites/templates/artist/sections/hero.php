<?php
/**
 * Afișul: portretul pe tot ecranul, în tonul albumului (cobalt peste alb-negru), numele
 * uriaș, era (albumul), următorul concert și banda cu orașele turneului.
 */
$img = asset_url($s['image'] ?? '', 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=2000&q=82');
$name = trim((string) ($s['title'] ?? '')) ?: SITE_NAME;
$next = ar_next();
$cities = [];
foreach (ar_upcoming() as $ev) {
    if (($c = ar_city($ev)) !== '') { $cities[$c] = true; }
}
$cities = array_keys($cities);
$words = preg_split('/\s+/u', $name) ?: [$name];
?>
<section class="ar-hero" data-ar-hero>
    <div class="ar-hero__media" aria-hidden="true">
        <img src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.12">
        <span class="ar-hero__tone"></span>
        <span class="ar-hero__grain"></span>
    </div>
    <div class="ar-wrap ar-hero__body">
        <?php if (!empty($s['eyebrow'])): ?><p class="ar-eyebrow ar-eyebrow--light" data-ar-in><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h1 class="ar-hero__name" aria-label="<?= e($name) ?>">
            <?php foreach ($words as $w): ?><span class="ar-hero__word"><span data-ar-word><?= e($w) ?></span></span><?php endforeach; ?>
        </h1>
        <div class="ar-hero__foot" data-ar-in>
            <?php if ($next): ?>
            <a class="ar-hero__next" href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>">
                <small><?= te('Următorul concert') ?></small>
                <b><?= e(ar_city($next)) ?></b>
                <span><?= e(ar_when($next)) ?> · <?= e(ar_venue($next)) ?></span>
            </a>
            <?php endif; ?>
            <div class="ar-hero__actions">
                <a href="<?= e(ts_link('events')) ?>" class="ar-btn ar-btn--light ar-btn--lg"><?= e($s['button_label'] ?? t('Bilete pentru turneu')) ?> <?= AR_ARROW ?></a>
                <?php if (!empty($s['listen_label'])): ?>
                <a href="<?= e(ts_href($s['listen_url'] ?? '#muzica')) ?>" class="ar-btn ar-btn--glass ar-btn--lg"><?= AR_PLAY ?> <?= e($s['listen_label']) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($cities): ?>
    <div class="ar-marquee" aria-hidden="true">
        <div class="ar-marquee__track">
            <?php for ($k = 0; $k < 2; $k++): ?><span><?php foreach ($cities as $c): ?><?= e($c) ?><i>✦</i><?php endforeach; ?></span><?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
