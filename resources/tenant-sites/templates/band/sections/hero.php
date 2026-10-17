<?php
/**
 * Afișul: globul cu orașele turneului (three.js, încărcat după conținut; fx.js), numele
 * trupei, misiunea în curs (albumul) și următoarea aterizare cu numărătoare. Pe telefoane
 * slabe, fără WebGL sau cu animațiile oprite rămâne imaginea de rezervă.
 */
$img = asset_url($s['image'] ?? '', 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=2000&q=80');
$name = trim((string) ($s['title'] ?? '')) ?: SITE_NAME;
$next = bd_next();
$points = [];
foreach (bd_upcoming() as $ev) {
    $v = (array) ($ev['venue'] ?? []);
    if (!isset($v['latitude'], $v['longitude']) || $v['latitude'] === null) {
        continue;
    }
    [$code] = bd_status($ev);
    $dp = bd_date_parts($ev);
    $points[] = [
        'lat' => (float) $v['latitude'], 'lng' => (float) $v['longitude'], 'city' => bd_city($ev),
        'date' => $dp['day'] . ' ' . $dp['month'], 'url' => ts_link('event', ['slug' => $ev['slug']]), 'status' => $code,
    ];
}
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<section class="bd-hero" data-bd-hero>
    <div class="bd-hero__sky" aria-hidden="true" style="background-image:url('<?= e($img) ?>')"></div>
    <div class="bd-globe" data-bd-globe="<?= e(json_encode($points, $flags)) ?>" role="img" aria-label="<?= e(t('Globul turneului: {n} orașe', ['n' => count($points)])) ?>">
        <div class="bd-globe__labels" aria-hidden="true"></div>
    </div>
    <div class="bd-wrap bd-hero__body">
        <div class="bd-hero__main">
            <?php if (!empty($s['eyebrow'])): ?><p class="bd-eyebrow" data-bd-in><span class="bd-dot" aria-hidden="true"></span><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h1 class="bd-hero__name" data-bd-in><?= e($name) ?></h1>
            <?php if (!empty($s['lead'])): ?><p class="bd-hero__lead" data-bd-in><?= ts_md($s['lead']) ?></p><?php endif; ?>
            <div class="bd-hero__actions" data-bd-in>
                <a href="<?= e(ts_link('events')) ?>" class="bd-btn bd-btn--lg"><?= e($s['button_label'] ?? t('Bilete pentru turneu')) ?> <?= BD_ARROW ?></a>
                <?php if (!empty($s['listen_label'])): ?>
                <a href="<?= e(ts_href($s['listen_url'] ?? '#discografie')) ?>" class="bd-btn bd-btn--ghost bd-btn--lg"><?= BD_PLAY ?> <?= e($s['listen_label']) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($next): ?>
        <a class="bd-hero__next" href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>" data-bd-in>
            <small><?= te('Următoarea aterizare') ?></small>
            <b><?= e(bd_city($next)) ?></b>
            <span><?= e(bd_when($next)) ?></span>
            <span><?= e(bd_venue($next)) ?></span>
            <?= bd_countdown($next, 'bd-cd--mini') ?>
        </a>
        <?php endif; ?>
    </div>
</section>
