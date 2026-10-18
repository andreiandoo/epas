<?php
/**
 * Afișul: globul disco 3D (three.js, din oglinzi instanțiate, încărcat după conținut; fx.js)
 * care aruncă pete de lumină pe pagină, titlul mare cu efect de casetă/VHS, ediția din
 * afiș cu numărătoarea și starea valului de preț. Pe telefoane slabe, fără WebGL sau cu
 * animațiile oprite rămâne imaginea de rezervă.
 */
$img = asset_url($s['image'] ?? '', 'https://images.unsplash.com/photo-1496337589254-7e19d01cec44?auto=format&fit=crop&w=2000&q=80');
$next = pt_next();
$waves = $next ? pt_waves($next) : [];
$cur = null;
foreach ($waves as $w) { if ($w['state'] === 'on') { $cur = $w; break; } }
?>
<section class="pt-hero" data-pt-hero>
    <div class="pt-hero__fallback" aria-hidden="true" style="background-image:url('<?= e($img) ?>')"></div>
    <div class="pt-hero__lights" aria-hidden="true" data-pt-lights></div>
    <div class="pt-ball" data-pt-ball aria-hidden="true"></div>
    <div class="pt-wrap pt-hero__body">
        <div class="pt-hero__main">
            <?php if (!empty($s['eyebrow'])): ?><p class="pt-eyebrow" data-pt-in><span class="pt-rec" aria-hidden="true"></span><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h1 class="pt-hero__title" data-pt-in data-text="<?= e($s['title'] ?? SITE_NAME) ?>"><?= e($s['title'] ?? SITE_NAME) ?></h1>
            <?php if (!empty($s['text'])): ?><p class="pt-lead" data-pt-in><?= ts_md($s['text']) ?></p><?php endif; ?>
            <div class="pt-hero__actions" data-pt-in>
                <a href="<?= e(ts_link('events')) ?>" class="pt-btn pt-btn--lg"><?= e($s['button_label'] ?? t('Vezi petrecerile')) ?> <?= PT_ARROW ?></a>
            </div>
        </div>
        <?php if ($next): $dp = pt_date_parts($next); ?>
        <a class="pt-tape" href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>" data-pt-in>
            <span class="pt-tape__label">
                <small><?= te('Următoarea ediție') ?></small>
                <b><?= e(pt_theme($next)) ?></b>
                <span><?= e($dp['day'] . ' ' . $dp['month']) ?> · <?= e(pt_city($next)) ?> · <?= e(pt_venue($next)) ?></span>
            </span>
            <span class="pt-tape__reels" aria-hidden="true"><i></i><i></i></span>
            <?php if ($cur): ?><span class="pt-tape__wave"><?= e($cur['name']) ?> · <?= e(pt_money($cur['price'])) ?><?= $cur['left'] !== null && (int) $cur['left'] <= 30 ? ' · ' . te('ultimele {n}', ['n' => (int) $cur['left']]) : '' ?></span><?php endif; ?>
            <?= pt_countdown($next, 'pt-cd--mini') ?>
        </a>
        <?php endif; ?>
    </div>
</section>
