<?php
/** Videoclipul: imaginea până la click, apoi YouTube (domeniul fără cookies), ca pagina să rămână rapidă. */
$poster = asset_url($s['poster'] ?? '', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1800&q=80');
$id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($s['youtube_id'] ?? ''));
?>
<section class="ar-section ar-video" id="video" aria-labelledby="ar-video-title">
    <div class="ar-wrap">
        <div class="ar-head2" data-reveal>
            <div>
                <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? 'Video') ?></p>
                <h2 class="ar-h2" id="ar-video-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
        </div>
        <div class="ar-video__frame" data-reveal<?= $id !== '' ? ' data-ar-video="' . e($id) . '"' : '' ?>>
            <img src="<?= e($poster) ?>" alt="" loading="lazy">
            <?php if ($id !== ''): ?>
            <button type="button" class="ar-video__play" aria-label="<?= e(t('Pornește videoclipul') . ': ' . ($s['title'] ?? '')) ?>"><?= AR_PLAY ?></button>
            <?php endif; ?>
        </div>
    </div>
</section>
