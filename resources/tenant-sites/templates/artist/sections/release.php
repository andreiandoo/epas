<?php
/** Albumul: coperta cu vinilul care iese din ea la derulare, piesele și linkurile de ascultare. */
$cover = asset_url($s['cover'] ?? '', 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1200&q=82');
$tracks = array_values(array_filter((array) ($s['tracks'] ?? []), fn ($t) => !empty($t['title'])));
$links = array_filter(['Spotify' => $s['spotify_url'] ?? '', 'Apple Music' => $s['apple_url'] ?? '', 'YouTube Music' => $s['youtube_url'] ?? '']);
?>
<section class="ar-section ar-release" id="muzica" aria-labelledby="ar-rel-title">
    <div class="ar-wrap ar-release__grid">
        <div class="ar-release__art" data-ar-vinyl>
            <span class="ar-vinyl" aria-hidden="true"><span></span></span>
            <img class="ar-release__cover" src="<?= e($cover) ?>" alt="<?= e(t('Coperta albumului {title}', ['title' => (string) ($s['title'] ?? '')])) ?>" loading="lazy">
        </div>
        <div class="ar-release__txt" data-reveal>
            <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? t('Albumul nou')) ?><?php if (!empty($s['year'])): ?> · <?= e($s['year']) ?><?php endif; ?></p>
            <h2 class="ar-h2 ar-h2--xl" id="ar-rel-title"><?= e($s['title'] ?? '') ?></h2>
            <?php if (!empty($s['text'])): ?><p class="ar-lead"><?= ts_md($s['text']) ?></p><?php endif; ?>
            <?php if ($links): ?>
            <div class="ar-listen">
                <?php foreach ($links as $label => $url): ?>
                <a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener" class="ar-btn ar-btn--ghost ar-btn--sm"><?= AR_PLAY ?> <?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($tracks): ?>
            <ol class="ar-tracks">
                <?php foreach ($tracks as $i => $t): ?>
                <li><span><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><b><?= e($t['title']) ?></b><?php if (!empty($t['length'])): ?><time><?= e($t['length']) ?></time><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
    </div>
</section>
