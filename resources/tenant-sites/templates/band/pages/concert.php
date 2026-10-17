<?php
/**
 * /concert/{slug}: data și orașul mare peste fotografia concertului, biletele (harta sălii
 * pentru locurile numerotate, cantități pentru restul), programul serii, locația și
 * celelalte date din turneu. Un concert trecut își păstrează pagina, fără bilete.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = bd_event((string) ($_GET['slug'] ?? ''));
if (!$ev) {
    ts_not_found();
}
$past = bd_is_past($ev);
[$code, $label] = bd_status($ev);
$ext = bd_external($ev); // biletele le vinde alt organizator (ex. un festival)
$extLink = $ext ? '<a href="' . e($ext) . '" target="_blank" rel="noopener" class="bd-btn bd-btn--lg">' . e($label) . ' <span aria-hidden="true">↗</span><span class="sr-only"> (' . te('se deschide într-o filă nouă') . ')</span></a>' : '';
$dp = bd_date_parts($ev);
$v = (array) ($ev['venue'] ?? []);
$addr = trim(implode(', ', array_filter([$v['address'] ?? '', bd_city($ev)])));
$mapUrl = $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($v['name'] ?? '') . ', ' . $addr) : null;
$from = bd_from_price($ev);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
$doors = substr((string) ($ev['door_time'] ?? ''), 0, 5);
$start = substr((string) ($ev['start_time'] ?? ''), 0, 5);
$others = array_values(array_filter(bd_upcoming(), fn ($x) => $x['slug'] !== $ev['slug']));

$pageTitle = t('{artist} în {city} — bilete, {date}', ['artist' => SITE_NAME, 'city' => bd_city($ev), 'date' => bd_when($ev, false)]);
$pageDescription = ts_plain($ev['short_description'] ?? '') ?: t('Bilete la concertul {artist} din {city}, {date}, {venue}.', ['artist' => SITE_NAME, 'city' => bd_city($ev), 'date' => bd_when($ev), 'venue' => bd_venue($ev)]);
$pageImage = $ev['poster_url'] ?? $img;
$pageSchema = [
    ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => [['name' => SITE_NAME, 'type' => 'MusicGroup', 'url' => '/']]]),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Turneu'), ts_link('events')], [bd_city($ev), ts_link('event', ['slug' => $ev['slug']])]]),
];
$headSolid = false;
$bodyClass = ($past || $ext) ? '' : 'bd-has-bar';
include ts_layout('head');
include ts_layout('header');
?>
<section class="bd-show">
    <div class="bd-show__media" aria-hidden="true">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.1"><?php endif; ?>
        <span class="bd-hero__tone"></span>
    </div>
    <div class="bd-wrap bd-show__body">
        <nav class="bd-crumbs" aria-label="<?= te('Ești aici') ?>"><a href="/"><?= te('Acasă') ?></a> / <a href="<?= e(ts_link('events')) ?>"><?= te('Turneu') ?></a></nav>
        <p class="bd-eyebrow bd-eyebrow--light"><?= e((string) ($ev['subtitle'] ?? '') ?: SITE_NAME) ?></p>
        <h1 class="bd-show__title">
            <span class="bd-show__date"><b><?= e($dp['day']) ?></b> <?= e($dp['month']) ?> <?= e($dp['year']) ?></span>
            <span class="bd-show__city"><?= e(bd_city($ev)) ?></span>
        </h1>
        <p class="bd-show__venue"><?= e(bd_venue($ev)) ?><?= $start !== '' ? ' · ' . e($start) : '' ?></p>
        <div class="bd-show__cta">
            <?php if ($past): ?>
            <span class="bd-pill bd-pill--light"><?= te('Concertul a avut loc') ?></span>
            <?php elseif ($code === 'sold_out'): ?>
            <span class="bd-pill bd-pill--out"><?= te('Sold out') ?></span>
            <?php elseif ($ext): ?>
            <?= str_replace('bd-btn bd-btn--lg', 'bd-btn bd-btn--light bd-btn--lg', $extLink) ?>
            <?php else: ?>
            <a href="#bilete" class="bd-btn bd-btn--light bd-btn--lg"><?= te('Alege biletele') ?> <?= BD_ARROW ?></a>
            <?php if ($from): ?><span class="bd-show__from"><?= te('de la') ?> <b><?= e(bd_money($from)) ?></b></span><?php endif; ?>
            <?php if ($code === 'low'): ?><span class="bd-pill bd-pill--hot"><?= e($label) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?= $past ? '' : bd_countdown($ev, 'bd-cd--light') ?>
    </div>
</section>

<?php if (!$past && $ext): ?>
<section class="bd-section" id="bilete" aria-labelledby="bd-tix-title">
    <div class="bd-wrap">
        <div class="bd-extbox">
            <div>
                <p class="bd-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="bd-h2" id="bd-tix-title"><?= te('Biletele le vinde organizatorul') ?></h2>
                <p class="bd-lead"><?= te('Concertul face parte din programul {venue}. Biletele se cumpără direct de la organizator.', ['venue' => bd_venue($ev)]) ?></p>
            </div>
            <?= $extLink ?>
        </div>
    </div>
</section>
<?php elseif (!$past): ?>
<section class="bd-section" id="bilete" aria-labelledby="bd-tix-title">
    <div class="bd-wrap">
        <div class="bd-head2">
            <div>
                <p class="bd-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="bd-h2" id="bd-tix-title"><?= $code === 'sold_out' ? te('Sold out') : te('Alege-ți biletele') ?></h2>
            </div>
            <p class="bd-head2__meta"><?= te('Biletele vin pe email, cu cod QR, imediat după plată.') ?></p>
        </div>
        <?php if ($code === 'sold_out'): ?>
        <p class="bd-empty"><?= te('Toate biletele pentru acest concert s-au vândut. Alege alt oraș din turneu.') ?></p>
        <p><a href="/#newsletter" class="bd-btn bd-btn--ghost"><?= te('Anunță-mă de datele noi') ?> <?= BD_ARROW ?></a></p>
        <?php else: ?>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="bd-section bd-section--alt" aria-labelledby="bd-info-title">
    <div class="bd-wrap bd-info">
        <div>
            <p class="bd-eyebrow"><?= te('Despre seară') ?></p>
            <h2 class="bd-h2" id="bd-info-title"><?= e(bd_venue($ev)) ?></h2>
            <div class="bd-prose"><?= (string) ($ev['description'] ?? '') /* HTML redactat în panou */ ?></div>
        </div>
        <dl class="bd-facts">
            <div><dt><?= te('Data') ?></dt><dd><?= e(bd_when($ev, false)) ?></dd></div>
            <?php if ($doors !== ''): ?><div><dt><?= te('Deschiderea porților') ?></dt><dd><?= e($doors) ?></dd></div><?php endif; ?>
            <?php if ($start !== ''): ?><div><dt><?= te('Începe concertul') ?></dt><dd><?= e($start) ?></dd></div><?php endif; ?>
            <div><dt><?= te('Locația') ?></dt><dd><?= e(bd_venue($ev)) ?><?php if ($addr !== ''): ?><br><span><?= e($addr) ?></span><?php endif; ?>
                <?php if ($mapUrl): ?><br><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="bd-link"><?= te('Deschide harta') ?></a><?php endif; ?></dd></div>
            <div><dt><?= te('Bilete') ?></dt><dd><?= $ext ? te('La organizator') . ' · <a href="' . e($ext) . '" target="_blank" rel="noopener" class="bd-link">' . e(parse_url($ext, PHP_URL_HOST)) . '</a>' : te('Online, pe email, cu cod QR') ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($others): ?>
<section class="bd-section" aria-labelledby="bd-more-title">
    <div class="bd-wrap">
        <div class="bd-head2"><div><p class="bd-eyebrow"><?= te('Turneu') ?></p><h2 class="bd-h2" id="bd-more-title"><?= te('Alte orașe') ?></h2></div></div>
        <ul class="bd-rows" data-bd-rows>
            <?php foreach (array_slice($others, 0, 6) as $o): ?><?= bd_row($o) ?><?php endforeach; ?>
        </ul>
        <div class="bd-center"><a href="<?= e(ts_link('events')) ?>" class="bd-btn bd-btn--ghost"><?= te('Toate datele') ?> <?= BD_ARROW ?></a></div>
    </div>
    <div class="bd-follow" aria-hidden="true" data-bd-follow><img alt=""></div>
</section>
<?php endif; ?>

<?php if (!$past && $code !== 'sold_out' && !$ext): ?>
<div class="bd-mbar" data-bd-mbar>
    <div><small><?= te('Bilete de la') ?></small><b><?= $from ? e(bd_money($from)) : '—' ?></b></div>
    <a href="#bilete" class="bd-btn bd-btn--sm"><?= te('Alege biletele') ?></a>
</div>
<?php endif; ?>
<?php include ts_layout('footer');
