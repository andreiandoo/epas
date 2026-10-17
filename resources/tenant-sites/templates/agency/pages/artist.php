<?php
/**
 * /artist/{id}: pagina unui artist din roster. Imaginea mare, numele, fraza, genurile,
 * orașul și teritoriile de booking; biografia, citatul de presă, linkurile, videoclipul;
 * concertele lui (cu bilete) și formularul de booking cu artistul deja ales.
 */
require_once TEMPLATE_DIR . '/lib.php';
$a = ag_artist((string) ($_GET['id'] ?? ''));
if (!$a) {
    ts_not_found();
}
$upcoming = (array) ($a['upcoming'] ?? []);
$past = (array) ($a['past'] ?? []);
$links = (array) ($a['links'] ?? []);
$linkLabels = ['spotify' => 'Spotify', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'website' => t('Site')];
$type = in_array($a['kind'] ?? '', ['band', 'duo', 'ensemble'], true) ? 'MusicGroup' : 'Person';

$pageTitle = $a['name'] . ' — ' . t('concerte și booking') . ' | ' . SITE_NAME;
$pageDescription = ($a['tagline'] ?? '') !== '' ? $a['tagline'] . ' ' . t('Concerte, bilete și booking prin {name}.', ['name' => SITE_NAME]) : ts_plain($a['bio'] ?? '');
$pageImage = $a['hero'] ?? $a['photo'] ?? null;
$schemaArtist = array_filter([
    '@type' => $type, 'name' => $a['name'], 'url' => ts_abs_url(ag_artist_url($a)), 'image' => $a['photo'] ?? null,
    'description' => ts_plain($a['bio'] ?? '', 300) ?: null, 'genre' => $a['genres'] ?? null, 'sameAs' => array_values($links) ?: null,
]);
$pageSchema = [$schemaArtist, ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Artiști'), ts_link('artists')], [$a['name'], ag_artist_url($a)]])];
foreach ($upcoming as $ev) {
    $pageSchema[] = ts_event_schema($ev + ['venue' => $ev['venue'] ?? null], ['type' => 'MusicEvent', 'performers' => [['name' => $a['name'], 'type' => $type]]]);
}
$headSolid = false;
include ts_layout('head');
include ts_layout('header');
?>
<section class="ag-artist-hero">
    <div class="ag-artist-hero__media" aria-hidden="true">
        <?php if (!empty($a['hero'])): ?><img src="<?= e($a['hero']) ?>" alt="" fetchpriority="high" data-parallax="0.1"><?php endif; ?>
    </div>
    <div class="ag-wrap ag-artist-hero__body">
        <nav class="ag-crumbs" aria-label="<?= te('Ești aici') ?>"><a href="/"><?= te('Acasă') ?></a> / <a href="<?= e(ts_link('artists')) ?>"><?= te('Artiști') ?></a></nav>
        <p class="ag-eyebrow"><?= e(ag_kind($a['kind'] ?? null)) ?><?php if (!empty($a['genres'])): ?> · <?= e(implode(' · ', $a['genres'])) ?><?php endif; ?></p>
        <h1 class="ag-artist-hero__name"><?= e($a['name']) ?></h1>
        <?php if (!empty($a['tagline'])): ?><p class="ag-artist-hero__tag"><?= e($a['tagline']) ?></p><?php endif; ?>
        <ul class="ag-facts-inline">
            <?php if (!empty($a['city'])): ?><li><?= e(trim(ag_flag($a['country'] ?? null) . ' ' . t((string) $a['city']))) ?></li><?php endif; ?>
            <?php if (!empty($a['listeners'])): ?><li><b><?= e(ag_short_number((int) $a['listeners'])) ?></b> <?= te('ascultători lunari') ?></li><?php endif; ?>
            <?php if ($upcoming): ?><li><b><?= count($upcoming) ?></b> <?= te('concerte programate') ?></li><?php endif; ?>
        </ul>
        <div class="ag-artist-hero__cta">
            <?php if ($upcoming): ?><a href="#concerte" class="ag-btn ag-btn--lg"><?= te('Concerte și bilete') ?> <?= AG_ARROW ?></a><?php endif; ?>
            <?php if (!empty($a['bookable'])): ?><a href="#booking" class="ag-btn ag-btn--ghost ag-btn--lg"><?= te('Cere un booking') ?></a><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($upcoming): ?>
<section class="ag-section" id="concerte" aria-labelledby="ag-up-title">
    <div class="ag-wrap">
        <div class="ag-head2"><div><p class="ag-eyebrow"><?= te('Live') ?></p><h2 class="ag-h2" id="ag-up-title"><?= te('Concerte') ?></h2></div></div>
        <ul class="ag-rows" data-ag-rows>
            <?php foreach ($upcoming as $ev): ?><?= ag_row($ev) ?><?php endforeach; ?>
        </ul>
    </div>
    <div class="ag-follow" aria-hidden="true" data-ag-follow><img alt=""></div>
</section>
<?php endif; ?>

<section class="ag-section ag-section--alt" aria-labelledby="ag-bio-title">
    <div class="ag-wrap ag-bio">
        <div>
            <p class="ag-eyebrow"><?= te('Despre') ?></p>
            <h2 class="ag-h2" id="ag-bio-title"><?= e($a['name']) ?></h2>
            <div class="ag-prose"><?= (string) ($a['bio'] ?? '') /* HTML redactat în panou */ ?></div>
            <?php if ($links): ?>
            <div class="ag-links">
                <?php foreach ($links as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="ag-btn ag-btn--ghost ag-btn--sm"><?= e($linkLabels[$k] ?? ucfirst($k)) ?> ↗</a><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div>
            <?php if (!empty($a['quote']['text'])): ?>
            <blockquote class="ag-quote"><p>„<?= e($a['quote']['text']) ?>”</p><?php if (!empty($a['quote']['source'])): ?><cite>— <?= e($a['quote']['source']) ?></cite><?php endif; ?></blockquote>
            <?php endif; ?>
            <?php if (!empty($a['photo'])): ?><figure class="ag-bio__img"><img src="<?= e($a['photo']) ?>" alt="<?= e($a['name']) ?>" loading="lazy"></figure><?php endif; ?>
        </div>
    </div>
</section>

<?php if (!empty($a['youtube_id'])): ?>
<section class="ag-section ag-video" aria-label="Video">
    <div class="ag-wrap">
        <div class="ag-video__frame" data-ag-video="<?= e($a['youtube_id']) ?>">
            <img src="https://i.ytimg.com/vi/<?= e($a['youtube_id']) ?>/hqdefault.jpg" alt="" loading="lazy">
            <button type="button" class="ag-video__play" aria-label="<?= e(t('Pornește videoclipul') . ': ' . $a['name']) ?>"><?= AG_PLAY ?></button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($a['bookable'])): ?>
<section class="ag-section" id="booking" aria-labelledby="ag-book-title">
    <div class="ag-wrap ag-book">
        <div>
            <p class="ag-eyebrow"><?= te('Booking') ?></p>
            <h2 class="ag-h2" id="ag-book-title"><?= te('{name} la evenimentul tău', ['name' => $a['name']]) ?></h2>
            <p class="ag-lead"><?= te('Spune-ne când, unde și ce fel de eveniment pregătești. Îți răspundem cu disponibilitatea și o ofertă.') ?></p>
            <?php if (!empty($a['territories'])): ?><p class="ag-book__terr"><?= te('Disponibil pentru') ?>: <b><?= e(implode(', ', $a['territories'])) ?></b></p><?php endif; ?>
        </div>
        <?php $bfArtist = (string) $a['slug']; $bfRoster = []; include TEMPLATE_DIR . '/layout/booking-form.php'; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($past): ?>
<section class="ag-section ag-section--alt" aria-labelledby="ag-past-title">
    <div class="ag-wrap">
        <div class="ag-head2"><div><p class="ag-eyebrow"><?= te('Arhivă') ?></p><h2 class="ag-h2" id="ag-past-title"><?= te('Concerte trecute') ?></h2></div></div>
        <ul class="ag-rows ag-rows--past"><?php foreach ($past as $ev): ?><?= ag_row($ev, true) ?><?php endforeach; ?></ul>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
