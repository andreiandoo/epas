<?php
/** /galerie: albumele edițiilor trecute, cele mai noi primele; fiecare fotografie se deschide mare. */
require_once TEMPLATE_DIR . '/lib.php';
$albums = pt_albums(12);
$pageTitle = t('Galerie') . ' — ' . SITE_NAME;
$pageDescription = t('Fotografii de la edițiile trecute {name}: cum arată o seară cu noi, oraș cu oraș.', ['name' => SITE_NAME]);
$pageImage = $albums[0]['gallery'][0] ?? null;
$pageSchema = [ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Galerie'), '/galerie']])];
foreach ($albums as $a) {
    $pageSchema[] = ['@type' => 'ImageGallery', 'name' => pt_show_name($a), 'url' => ts_abs_url(ts_link('event', ['slug' => $a['slug']])), 'image' => array_map('ts_abs_url', array_values($a['gallery']))];
}
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-page-head">
    <div class="pt-wrap">
        <p class="pt-eyebrow"><span class="pt-rec" aria-hidden="true"></span><?= e(SITE_NAME) ?></p>
        <h1 class="pt-h1"><?= te('Galerie') ?></h1>
        <p class="pt-lead"><?= $albums ? te('Cum a fost la ultimele {n} ediții.', ['n' => count($albums)]) : te('Primele albume apar după următoarea ediție.') ?></p>
    </div>
</section>
<?php foreach ($albums as $a): $dp = pt_date_parts($a); $theme = pt_theme($a); ?>
<section class="pt-section pt-section--tight pt-albumsec" aria-labelledby="pt-al-<?= (int) $a['id'] ?>">
    <div class="pt-wrap">
        <div class="pt-head2">
            <div>
                <p class="pt-eyebrow"><?= e($dp['day'] . ' ' . $dp['month'] . ' ' . $dp['year']) ?> · <?= e(pt_city($a)) ?> · <?= e(pt_venue($a)) ?></p>
                <h2 class="pt-h2" id="pt-al-<?= (int) $a['id'] ?>"><a href="<?= e(ts_link('event', ['slug' => $a['slug']])) ?>"><?= e($theme) ?></a></h2>
            </div>
            <p class="pt-head2__meta"><?= te('{n} fotografii', ['n' => count($a['gallery'])]) ?></p>
        </div>
        <ul class="pt-album" data-stagger>
            <?php foreach (array_values($a['gallery']) as $k => $src): ?>
            <li><button type="button" data-pt-lightbox="<?= e($src) ?>" aria-label="<?= e(t('Mărește fotografia') . ' ' . ($k + 1)) ?>"><img src="<?= e(preg_replace('/w=\d+/', 'w=800', $src)) ?>" alt="<?= e(t('{theme}, {city}: fotografia {n}', ['theme' => $theme, 'city' => pt_city($a), 'n' => $k + 1])) ?>" loading="lazy"></button></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endforeach; ?>
<section class="pt-section pt-section--alt">
    <div class="pt-wrap pt-center">
        <h2 class="pt-h2"><?= te('Următoarea poză poate fi cu tine') ?></h2>
        <p style="margin-top:20px"><a href="<?= e(ts_link('events')) ?>" class="pt-btn pt-btn--lg"><?= te('Vezi petrecerile') ?> <?= PT_ARROW ?></a></p>
    </div>
</section>
<?php include ts_layout('footer');
