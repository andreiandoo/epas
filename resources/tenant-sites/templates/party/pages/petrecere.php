<?php
/**
 * /petrecere/{slug}: tema ediției mare peste fotografie, orașul și data, valul de preț în
 * curs, biletele (valuri, pachete de grup, mese VIP pe harta clubului), DJ-ii serii,
 * programul și locația. O ediție trecută își arată albumul foto în locul biletelor.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = pt_event((string) ($_GET['slug'] ?? ''));
if (!$ev) {
    ts_not_found();
}
$past = pt_is_past($ev);
[$code, $label] = pt_status($ev);
$ext = pt_external($ev); // biletele le vinde alt organizator (ex. un festival unde avem scenă)
$extLink = $ext ? '<a href="' . e($ext) . '" target="_blank" rel="noopener" class="pt-btn pt-btn--lg">' . e($label) . ' <span aria-hidden="true">↗</span><span class="sr-only"> (' . te('se deschide într-o filă nouă') . ')</span></a>' : '';
$dp = pt_date_parts($ev);
$v = (array) ($ev['venue'] ?? []);
$addr = trim(implode(', ', array_filter([$v['address'] ?? '', pt_city($ev)])));
$mapUrl = $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($v['name'] ?? '') . ', ' . $addr) : null;
$from = pt_from_price($ev);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
$doors = substr((string) ($ev['door_time'] ?? ''), 0, 5);
$start = substr((string) ($ev['start_time'] ?? ''), 0, 5);
$end = substr((string) ($ev['end_time'] ?? ''), 0, 5);
$theme = pt_theme($ev);
$djs = array_values((array) ($ev['roster'] ?? []));
$gallery = array_values(array_filter((array) ($ev['gallery'] ?? []), 'is_string'));
$wave = null;
foreach (pt_waves($ev) as $w) { if ($w['state'] === 'on') { $wave = $w; break; } }
$others = array_values(array_filter(pt_upcoming(), fn ($x) => $x['slug'] !== $ev['slug']));

$pageTitle = t('{theme} în {city} — bilete, {date}', ['theme' => SITE_NAME . ': ' . $theme, 'city' => pt_city($ev), 'date' => pt_when($ev, false)]);
$pageDescription = ts_plain($ev['short_description'] ?? '') ?: t('Petrecerea {theme} din {city}, {date}, {venue}. Bilete online, valuri de preț și mese VIP.', ['theme' => $theme, 'city' => pt_city($ev), 'date' => pt_when($ev), 'venue' => pt_venue($ev)]);
$pageImage = $ev['poster_url'] ?? $img;
$pageSchema = [
    ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => $djs
        ? array_map(fn ($r) => ['name' => $r['name'], 'type' => 'Person'], $djs)
        : [['name' => SITE_NAME, 'type' => 'PerformingGroup']]]),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Petreceri'), ts_link('events')], [$theme . ' · ' . pt_city($ev), ts_link('event', ['slug' => $ev['slug']])]]),
];
if ($past && $gallery) {
    $pageSchema[] = ['@type' => 'ImageGallery', 'name' => t('Fotografii') . ': ' . pt_show_name($ev), 'image' => array_map('ts_abs_url', $gallery)];
}
$headSolid = false;
$bodyClass = ($past || $ext || $code === 'sold_out') ? '' : 'pt-has-bar';
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-show">
    <div class="pt-show__media" aria-hidden="true">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.12"><?php endif; ?>
        <span class="pt-show__tone"></span>
        <span class="pt-hero__lights" data-pt-lights></span>
    </div>
    <div class="pt-wrap pt-show__body">
        <nav class="pt-crumbs" aria-label="<?= te('Ești aici') ?>"><a href="/"><?= te('Acasă') ?></a> / <a href="<?= e(ts_link('events')) ?>"><?= te('Petreceri') ?></a></nav>
        <p class="pt-eyebrow"><span class="pt-rec" aria-hidden="true"></span><?= e(SITE_NAME) ?> · <?= e(pt_city($ev)) ?></p>
        <h1 class="pt-show__title" data-text="<?= e($theme) ?>"><?= e($theme) ?></h1>
        <p class="pt-show__meta">
            <time datetime="<?= e(substr((string) $ev['start_date'], 0, 10)) ?>"><b><?= e($dp['day']) ?></b> <?= e($dp['month']) ?> <?= e($dp['year']) ?> · <?= e($dp['weekday']) ?></time>
            <span><?= e(pt_venue($ev)) ?><?= $start !== '' ? ' · ' . e($start) : '' ?></span>
        </p>
        <div class="pt-show__cta">
            <?php if ($past): ?>
            <span class="pt-pill"><?= te('Petrecerea a avut loc') ?></span>
            <?php if ($gallery): ?><a href="#poze" class="pt-btn pt-btn--lg"><?= te('Vezi pozele') ?> <?= PT_ARROW ?></a><?php endif; ?>
            <?php elseif ($code === 'sold_out'): ?>
            <span class="pt-pill pt-pill--out"><?= te('Sold out') ?></span>
            <?php elseif ($ext): ?>
            <?= $extLink ?>
            <?php else: ?>
            <a href="#bilete" class="pt-btn pt-btn--lg"><?= te('Alege biletele') ?> <?= PT_ARROW ?></a>
            <?php if ($wave): ?><span class="pt-show__wave"><small><?= e($wave['name']) ?></small><b><?= e(pt_money($wave['price'])) ?></b></span>
            <?php elseif ($from): ?><span class="pt-show__wave"><small><?= te('de la') ?></small><b><?= e(pt_money($from)) ?></b></span><?php endif; ?>
            <?php if ($code === 'low'): ?><span class="pt-pill pt-pill--hot"><?= e($label) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?= $past ? '' : pt_countdown($ev) ?>
    </div>
</section>

<?php if ($past): ?>
<?php if ($gallery): ?>
<section class="pt-section" id="poze" aria-labelledby="pt-pics-title">
    <div class="pt-wrap">
        <div class="pt-head2"><div><p class="pt-eyebrow"><?= te('Galerie') ?></p><h2 class="pt-h2" id="pt-pics-title"><?= te('Cum a fost') ?></h2></div>
            <p class="pt-head2__meta"><?= te('{n} fotografii', ['n' => count($gallery)]) ?></p></div>
        <ul class="pt-album" data-stagger>
            <?php foreach ($gallery as $k => $src): ?>
            <li><button type="button" data-pt-lightbox="<?= e($src) ?>" aria-label="<?= e(t('Mărește fotografia') . ' ' . ($k + 1)) ?>"><img src="<?= e(preg_replace('/w=\d+/', 'w=800', $src)) ?>" alt="<?= e(t('{theme}, {city}: fotografia {n}', ['theme' => $theme, 'city' => pt_city($ev), 'n' => $k + 1])) ?>" loading="lazy"></button></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
<?php elseif ($ext): ?>
<section class="pt-section" id="bilete" aria-labelledby="pt-tix-title">
    <div class="pt-wrap">
        <div class="pt-extbox">
            <div>
                <p class="pt-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="pt-h2" id="pt-tix-title"><?= te('Biletele le vinde organizatorul') ?></h2>
                <p class="pt-lead"><?= te('Seara noastră face parte din programul {venue}. Biletele se cumpără direct de la organizator.', ['venue' => pt_venue($ev)]) ?></p>
            </div>
            <?= $extLink ?>
        </div>
    </div>
</section>
<?php else: ?>
<section class="pt-section" id="bilete" aria-labelledby="pt-tix-title">
    <div class="pt-wrap">
        <div class="pt-head2">
            <div>
                <p class="pt-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="pt-h2" id="pt-tix-title"><?= $code === 'sold_out' ? te('Sold out') : te('Prinde-ți locul pe ring') ?></h2>
            </div>
            <?php if ($code !== 'sold_out'): ?><p class="pt-head2__meta"><?= te('Cu cât cumperi mai devreme, cu atât plătești mai puțin. Biletele vin pe email, cu cod QR.') ?></p><?php endif; ?>
        </div>
        <?php if ($code === 'sold_out'): ?>
        <p class="pt-empty"><?= te('Toate biletele pentru această ediție s-au dus. Abonează-te și afli primul de ediția următoare.') ?></p>
        <p><a href="/#newsletter" class="pt-btn pt-btn--ghost"><?= te('Anunță-mă de edițiile noi') ?> <?= PT_ARROW ?></a></p>
        <?php else: ?>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($djs): ?>
<section class="pt-section pt-section--alt" aria-labelledby="pt-lineup-title">
    <div class="pt-wrap">
        <div class="pt-head2"><div><p class="pt-eyebrow"><?= te('La pupitru') ?></p><h2 class="pt-h2" id="pt-lineup-title"><?= $past ? te('Au mixat') : te('Mixează în seara asta') ?></h2></div></div>
        <ul class="pt-djs pt-djs--sm" data-stagger>
            <?php foreach ($djs as $d): ?>
            <li class="pt-dj">
                <div class="pt-dj__pic"><span class="pt-dj__disc" aria-hidden="true"></span><?php if (!empty($d['photo'])): ?><img src="<?= e($d['photo']) ?>" alt="<?= e($d['name']) ?>" loading="lazy"><?php endif; ?></div>
                <h3><?= e($d['name']) ?></h3>
                <?php if (!empty($d['genres'])): ?><p class="pt-dj__genres"><?= e(implode(' · ', (array) $d['genres'])) ?></p><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="pt-section" aria-labelledby="pt-info-title">
    <div class="pt-wrap pt-info">
        <div>
            <p class="pt-eyebrow"><?= te('Despre seară') ?></p>
            <h2 class="pt-h2" id="pt-info-title"><?= e($theme) ?></h2>
            <div class="pt-prose"><?= (string) ($ev['description'] ?? '') /* HTML redactat în panou */ ?></div>
        </div>
        <dl class="pt-facts">
            <div><dt><?= te('Data') ?></dt><dd><?= e(pt_when($ev, false)) ?></dd></div>
            <?php if ($doors !== '' || $start !== ''): ?><div><dt><?= te('Program') ?></dt><dd><?= $doors !== '' ? te('Intrarea de la {t}', ['t' => $doors]) : te('De la {t}', ['t' => $start]) ?><?= $end !== '' ? ' · ' . te('până la {t}', ['t' => $end]) : '' ?></dd></div><?php endif; ?>
            <div><dt><?= te('Locația') ?></dt><dd><?= e(pt_venue($ev)) ?><?php if ($addr !== ''): ?><br><span><?= e($addr) ?></span><?php endif; ?>
                <?php if ($mapUrl): ?><br><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="pt-link"><?= te('Deschide harta') ?></a><?php endif; ?></dd></div>
            <div><dt><?= te('Acces') ?></dt><dd><?= te('Doar peste 18 ani, cu actul de identitate') ?></dd></div>
            <div><dt><?= te('Bilete') ?></dt><dd><?= $ext ? te('La organizator') . ' · <a href="' . e($ext) . '" target="_blank" rel="noopener" class="pt-link">' . e(parse_url($ext, PHP_URL_HOST)) . '</a>' : te('Online, pe email, cu cod QR') ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($others): ?>
<section class="pt-section pt-section--alt" aria-labelledby="pt-more-title">
    <div class="pt-wrap">
        <div class="pt-head2"><div><p class="pt-eyebrow"><?= te('Urmează') ?></p><h2 class="pt-h2" id="pt-more-title"><?= te('Alte ediții') ?></h2></div>
            <a href="<?= e(ts_link('events')) ?>" class="pt-btn pt-btn--ghost"><?= te('Toate petrecerile') ?> <?= PT_ARROW ?></a></div>
        <ul class="pt-flyers" data-stagger>
            <?php foreach (array_slice($others, 0, 3) as $i => $o): ?><li><?= pt_flyer($o, $i) ?></li><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if (!$past && $code !== 'sold_out' && !$ext): ?>
<div class="pt-mbar" data-pt-mbar>
    <div><small><?= $wave ? e($wave['name']) : te('Bilete de la') ?></small><b><?= $wave ? e(pt_money($wave['price'])) : ($from ? e(pt_money($from)) : '—') ?></b></div>
    <a href="#bilete" class="pt-btn pt-btn--sm"><?= te('Alege biletele') ?></a>
</div>
<?php endif; ?>
<?php include ts_layout('footer');
