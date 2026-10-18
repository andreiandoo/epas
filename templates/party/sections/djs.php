<?php
/** DJ-ii rezidenți: portret cu disc care se rotește la hover, genurile și o frază. */
$djs = pt_djs();
if (!$djs) {
    return;
}
?>
<section class="pt-section pt-section--alt" id="dj" aria-labelledby="pt-dj-title">
    <div class="pt-wrap">
        <div class="pt-head2" data-reveal>
            <div>
                <p class="pt-eyebrow"><?= e($s['eyebrow'] ?? t('La pupitru')) ?></p>
                <h2 class="pt-h2" id="pt-dj-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
        </div>
        <ul class="pt-djs" data-stagger>
            <?php foreach ($djs as $d): ?>
            <li class="pt-dj">
                <div class="pt-dj__pic">
                    <span class="pt-dj__disc" aria-hidden="true"></span>
                    <?php if (!empty($d['photo'])): ?><img src="<?= e($d['photo']) ?>" alt="<?= e($d['name']) ?>" loading="lazy"><?php endif; ?>
                </div>
                <h3><?= e($d['name']) ?></h3>
                <?php if (!empty($d['genres'])): ?><p class="pt-dj__genres"><?= e(implode(' · ', $d['genres'])) ?></p><?php endif; ?>
                <?php if (!empty($d['tagline'])): ?><p><?= e($d['tagline']) ?></p><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
