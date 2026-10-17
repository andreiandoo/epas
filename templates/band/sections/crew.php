<?php
/** Echipajul: membrii trupei ca ecusoane de misiune (fotografie, nume, instrument, câteva cuvinte). */
$members = array_values(array_filter((array) ($s['members'] ?? []), fn ($m) => !empty($m['name'])));
if (!$members) {
    return;
}
?>
<section class="bd-section bd-crew" id="echipaj" aria-labelledby="bd-crew-title">
    <div class="bd-wrap">
        <div class="bd-head2" data-reveal>
            <div>
                <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? t('Echipaj')) ?></p>
                <h2 class="bd-h2" id="bd-crew-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
            <p class="bd-head2__meta bd-mono"><?= te('{n} la bord', ['n' => count($members)]) ?></p>
        </div>
        <ul class="bd-crew__list" data-stagger>
            <?php foreach ($members as $i => $m): $img = asset_url($m['image'] ?? '', null); ?>
            <li class="bd-badge" data-bd-tilt>
                <div class="bd-badge__pic">
                    <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($m['name']) ?>" loading="lazy"><?php endif; ?>
                    <span class="bd-badge__no bd-mono">CREW <?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="bd-badge__txt">
                    <h3><?= e($m['name']) ?></h3>
                    <?php if (!empty($m['role'])): ?><p class="bd-badge__role bd-mono"><?= e($m['role']) ?></p><?php endif; ?>
                    <?php if (!empty($m['bio'])): ?><p><?= ts_md($m['bio']) ?></p><?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
