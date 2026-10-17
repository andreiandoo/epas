<?php
/** Discografia ca misiuni: albumul nou mare, celelalte ca ecusoane rotunde de misiune. */
$releases = array_values(array_filter((array) ($s['releases'] ?? []), fn ($r) => !empty($r['title'])));
if (!$releases) {
    return;
}
$main = array_shift($releases);
$cover = asset_url($main['cover'] ?? '', null);
?>
<section class="bd-section bd-missions" id="discografie" aria-labelledby="bd-mis-title">
    <div class="bd-wrap">
        <div class="bd-head2" data-reveal>
            <div>
                <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? t('Discografie')) ?></p>
                <h2 class="bd-h2" id="bd-mis-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
        </div>
        <article class="bd-mission bd-mission--main" data-reveal>
            <div class="bd-mission__art">
                <span class="bd-orbit" aria-hidden="true"><i></i></span>
                <?php if ($cover): ?><img src="<?= e($cover) ?>" alt="<?= e(t('Coperta albumului {title}', ['title' => (string) $main['title']])) ?>" loading="lazy"><?php endif; ?>
            </div>
            <div>
                <p class="bd-mono bd-mission__meta"><?= e(trim(($main['type'] ?? '') . ' · ' . ($main['year'] ?? ''), ' ·')) ?></p>
                <h3 class="bd-mission__title"><?= e($main['title']) ?></h3>
                <?php if (!empty($main['text'])): ?><p class="bd-lead"><?= ts_md($main['text']) ?></p><?php endif; ?>
                <?php if (!empty($main['url'])): ?><a href="<?= e(ts_href($main['url'])) ?>" target="_blank" rel="noopener" class="bd-btn" style="margin-top:26px"><?= BD_PLAY ?> <?= te('Ascultă albumul') ?></a><?php endif; ?>
            </div>
        </article>
        <?php if ($releases): ?>
        <ul class="bd-patches" data-stagger>
            <?php foreach ($releases as $r): $c = asset_url($r['cover'] ?? '', null); ?>
            <li>
                <a class="bd-patch" href="<?= e(ts_href($r['url'] ?? '#discografie')) ?>"<?= !empty($r['url']) ? ' target="_blank" rel="noopener"' : '' ?>>
                    <span class="bd-patch__img"><?php if ($c): ?><img src="<?= e($c) ?>" alt="" loading="lazy"><?php endif; ?></span>
                    <span><b><?= e($r['title']) ?></b><small class="bd-mono"><?= e(trim(($r['type'] ?? '') . ' · ' . ($r['year'] ?? ''), ' ·')) ?></small><?php if (!empty($r['text'])): ?><em><?= e($r['text']) ?></em><?php endif; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>
