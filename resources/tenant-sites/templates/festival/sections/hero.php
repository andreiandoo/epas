<?php
/**
 * Hero-ul festivalului: imaginea pe tot ecranul, numele tipografic uriaș, datele și
 * locul, numărătoarea până la deschiderea porților, butoanele și banda cu artiști.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
[$start] = fest_dates($ev);
$year = $start ? date('Y', strtotime($start)) : date('Y');
$title = trim((string) ($s['title'] ?? '')) ?: (string) ($ev['title'] ?? SITE_NAME);
$lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $title))));
if (count($lines) === 1 && str_word_count($lines[0], 0, 'ăâîșțĂÂÎȘȚ0123456789') > 2) {
    // „Echo Valley Festival 2027” → două rânduri echilibrate
    $w = preg_split('/\s+/u', $lines[0]);
    $half = (int) ceil(count($w) / 2);
    $lines = [implode(' ', array_slice($w, 0, $half)), implode(' ', array_slice($w, $half))];
}
$img = trim((string) ($s['image'] ?? '')) ?: ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
$names = array_values(array_unique(array_filter(array_map(fn ($x) => $x['name'] ?? null, fest_lineup()['slots']))));
?>
<section class="fx-hero" data-fx-hero>
    <?php if ($img): ?><img class="fx-hero__img" src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.12"><?php endif; ?>
    <div class="fx-hero__shade" aria-hidden="true"></div>
    <canvas class="fx-hero__embers" data-embers aria-hidden="true"></canvas>
    <div class="fx-wrap fx-hero__body">
        <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow fx-eyebrow--light" data-reveal><?= e(str_replace('{year}', $year, $s['eyebrow'])) ?></p><?php endif; ?>
        <h1 class="fx-hero__title" aria-label="<?= e($title) ?>">
            <?php foreach ($lines as $i => $l): ?><span class="fx-hero__line<?= $i % 2 ? ' fx-hero__line--alt' : '' ?>" aria-hidden="true" data-fx-split><?= e($l) ?></span><?php endforeach; ?>
        </h1>
        <div class="fx-hero__meta" data-reveal data-reveal-delay="0.2">
            <span><?= e(fest_date_range($ev)) ?></span>
            <?php if (fest_place($ev) !== ''): ?><span><?= e(fest_place($ev)) ?></span><?php endif; ?>
        </div>
        <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="fx-hero__lead" data-reveal data-reveal-delay="0.3"><?= ts_md($s['lead']) ?></p><?php endif; ?>
        <div class="fx-hero__actions" data-reveal data-reveal-delay="0.4">
            <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?><a href="/bilete" class="fx-btn fx-btn--lg"><?= e($s['button_label']) ?> <?= FEST_ARROW ?></a><?php endif; ?>
            <?php if (trim((string) ($s['secondary_label'] ?? '')) !== ''): ?><a href="<?= e(ts_href($s['secondary_url'] ?? '', '/lineup')) ?>" class="fx-btn fx-btn--ghost fx-btn--lg"><?= e($s['secondary_label']) ?></a><?php endif; ?>
        </div>
        <?php if (!empty($s['show_countdown'])): ?><div data-reveal data-reveal-delay="0.5"><?= fest_countdown() ?></div><?php endif; ?>
    </div>
    <?php if (!empty($s['show_marquee']) && $names): ?>
    <div class="fx-marquee" aria-hidden="true">
        <div class="fx-marquee__track" data-fx-marquee>
            <?php for ($r = 0; $r < 2; $r++): foreach ($names as $n): ?><span><?= e($n) ?></span><i>✦</i><?php endforeach; endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
