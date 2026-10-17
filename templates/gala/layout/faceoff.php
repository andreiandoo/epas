<?php
/**
 * Afișul galei: main event-ul față în față peste imaginea galei, numele galei,
 * data, locul, numărătoarea și butoanele. Variabile: $ev, $card, $h1 (bool),
 * $eyebrow, $buyLabel, $cardLabel (opționale, din secțiunea hero), $past (fără bilete).
 */
$main = null;
foreach ($card as $b) {
    if ($b['section'] === 'main_event') { $main = $b; break; }
}
$main = $main ?? ($card[0] ?? null);
$h = !empty($h1) ? 'h1' : 'h2';
$img = $ev['hero_image_url'] ?? $ev['poster_url'] ?? '';
$title = (string) ($ev['title'] ?? '');
$num = preg_match('/^(.*?)(:\s*)(.+)$/u', $title, $m) ? [$m[1], $m[3]] : [$title, ''];
?>
<section class="gx-hero" data-gx-hero>
    <?php if ($img): ?><img class="gx-hero__bg" src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.1"><?php endif; ?>
    <div class="gx-hero__shade" aria-hidden="true"></div>
    <div class="gx-hero__sweep" aria-hidden="true"></div>
    <div class="gx-wrap gx-hero__body">
        <p class="gx-eyebrow" data-reveal><?= e($eyebrow ?? t('Următoarea gală')) ?> · <?= e(gx_when($ev)) ?></p>
        <<?= $h ?> class="gx-hero__title" data-reveal>
            <span><?= e($num[0]) ?></span>
            <?php if ($num[1] !== ''): ?><small><?= e($num[1]) ?></small><?php endif; ?>
        </<?= $h ?>>
        <?php if ($main): ?>
        <div class="gx-faceoff" data-gx-faceoff>
            <?php foreach (['a' => 'red', 'b' => 'blue'] as $k => $corner): $f = $main[$k]; [$first, $last] = gx_name_parts((string) $f['name']); ?>
            <div class="gx-faceoff__f gx-faceoff__f--<?= $corner ?>" data-gx-side="<?= $corner ?>">
                <?= gx_portrait($f, $corner, 'gx-pic--hero') ?>
                <div class="gx-faceoff__txt">
                    <?php if (!empty($f['nickname'])): ?><small><?= e(gx_nick($f['nickname'])) ?></small><?php endif; ?>
                    <span><?= e($first) ?></span><b><?= e($last) ?></b>
                    <em><?= e(trim(gx_flag($f['country'] ?? null) . ' ' . gx_record($f))) ?><?= !empty($f['is_champion']) ? ' · ' . te('Campion') : '' ?></em>
                </div>
            </div>
            <?php if ($k === 'a'): ?>
            <div class="gx-faceoff__vs">
                <b>VS</b>
                <?php if ($main['title_fight']): ?><span class="gx-belt"><?= GX_BELT ?><?= e($main['title_name'] ?: t('Meci pentru centură')) ?></span><?php endif; ?>
                <small><?= e($main['weight_class'] ?? '') ?></small>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="gx-hero__foot" data-reveal data-reveal-delay="0.2">
            <div>
                <p class="gx-hero__place"><?= e(gx_place($ev)) ?></p>
                <?= gx_countdown($ev) ?>
            </div>
            <div class="gx-hero__actions">
                <?php if (empty($past)): ?>
                <a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#bilete" class="gx-btn gx-btn--lg"><?= e($buyLabel ?? t('Cumpără bilete')) ?> <?= GX_ARROW ?></a>
                <a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#fight-card" class="gx-btn gx-btn--ghost gx-btn--lg"><?= e($cardLabel ?? t('Vezi fight card-ul')) ?></a>
                <?php else: ?>
                <a href="<?= e(ts_link('event', ['slug' => $ev['slug']])) ?>#fight-card" class="gx-btn gx-btn--lg"><?= te('Rezultate') ?> <?= GX_ARROW ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
