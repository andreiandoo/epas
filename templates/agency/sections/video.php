<?php
/** Videoclipul: imaginea până la click, apoi YouTube (domeniul fără cookies), ca pagina să rămână rapidă. */
$poster = asset_url($s['poster'] ?? '', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1800&q=80');
$id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($s['youtube_id'] ?? ''));
?>
<section class="ag-section ag-video" id="video" aria-labelledby="ag-video-title">
    <div class="ag-wrap">
        <div class="ag-head2" data-reveal>
            <div>
                <p class="ag-eyebrow"><?= e($s['eyebrow'] ?? 'Video') ?></p>
                <h2 class="ag-h2" id="ag-video-title"><?= e($s['title'] ?? '') ?></h2>
            </div>
        </div>
        <div class="ag-video__frame" data-reveal<?= $id !== '' ? ' data-ag-video="' . e($id) . '"' : '' ?>>
            <img src="<?= e($poster) ?>" alt="" loading="lazy">
            <?php if ($id !== ''): ?>
            <button type="button" class="ag-video__play" aria-label="<?= e(t('Pornește videoclipul') . ': ' . ($s['title'] ?? '')) ?>"><?= AG_PLAY ?></button>
            <?php endif; ?>
        </div>
    </div>
</section>
