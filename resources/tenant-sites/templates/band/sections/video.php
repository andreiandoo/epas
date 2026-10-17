<?php
/** Videoclipul: imaginea până la click, apoi YouTube (domeniul fără cookies), ca pagina să rămână rapidă. */
$poster = asset_url($s['poster'] ?? '', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1800&q=80');
$id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($s['youtube_id'] ?? ''));
?>
<section class="bd-section bd-video" id="video" aria-labelledby="bd-video-title">
    <div class="bd-wrap">
        <div class="bd-head2" data-reveal>
            <div>
                <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? 'Video') ?></p>
                <h2 class="bd-h2" id="bd-video-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
        </div>
        <div class="bd-video__frame" data-reveal<?= $id !== '' ? ' data-bd-video="' . e($id) . '"' : '' ?>>
            <img src="<?= e($poster) ?>" alt="" loading="lazy">
            <?php if ($id !== ''): ?>
            <button type="button" class="bd-video__play" aria-label="<?= e(t('Pornește videoclipul') . ': ' . ($s['title'] ?? '')) ?>"><?= BD_PLAY ?></button>
            <?php endif; ?>
        </div>
    </div>
</section>
