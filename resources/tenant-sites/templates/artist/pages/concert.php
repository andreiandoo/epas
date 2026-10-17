<?php
/**
 * /concert/{slug}: data și orașul mare peste fotografia concertului, biletele (harta sălii
 * pentru locurile numerotate, cantități pentru restul), programul serii, locația și
 * celelalte date din turneu. Un concert trecut își păstrează pagina, fără bilete.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = ar_event((string) ($_GET['slug'] ?? ''));
if (!$ev) {
    ts_not_found();
}
$past = ar_is_past($ev);
[$code, $label] = ar_status($ev);
$dp = ar_date_parts($ev);
$v = (array) ($ev['venue'] ?? []);
$addr = trim(implode(', ', array_filter([$v['address'] ?? '', ar_city($ev)])));
$mapUrl = $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($v['name'] ?? '') . ', ' . $addr) : null;
$from = ar_from_price($ev);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
$doors = substr((string) ($ev['door_time'] ?? ''), 0, 5);
$start = substr((string) ($ev['start_time'] ?? ''), 0, 5);
$others = array_values(array_filter(ar_upcoming(), fn ($x) => $x['slug'] !== $ev['slug']));

$pageTitle = t('{artist} în {city} — bilete, {date}', ['artist' => SITE_NAME, 'city' => ar_city($ev), 'date' => ar_when($ev, false)]);
$pageDescription = ts_plain($ev['short_description'] ?? '') ?: t('Bilete la concertul {artist} din {city}, {date}, {venue}.', ['artist' => SITE_NAME, 'city' => ar_city($ev), 'date' => ar_when($ev), 'venue' => ar_venue($ev)]);
$pageImage = $ev['poster_url'] ?? $img;
$pageSchema = [
    ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => [['name' => SITE_NAME, 'type' => 'MusicGroup', 'url' => '/']]]),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Turneu'), ts_link('events')], [ar_city($ev), ts_link('event', ['slug' => $ev['slug']])]]),
];
$headSolid = false;
$bodyClass = $past ? '' : 'ar-has-bar';
include ts_layout('head');
include ts_layout('header');
?>
<section class="ar-show">
    <div class="ar-show__media" aria-hidden="true">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.1"><?php endif; ?>
        <span class="ar-hero__tone"></span>
    </div>
    <div class="ar-wrap ar-show__body">
        <nav class="ar-crumbs" aria-label="<?= te('Ești aici') ?>"><a href="/"><?= te('Acasă') ?></a> / <a href="<?= e(ts_link('events')) ?>"><?= te('Turneu') ?></a></nav>
        <p class="ar-eyebrow ar-eyebrow--light"><?= e((string) ($ev['subtitle'] ?? '') ?: SITE_NAME) ?></p>
        <h1 class="ar-show__title">
            <span class="ar-show__date"><b><?= e($dp['day']) ?></b> <?= e($dp['month']) ?> <?= e($dp['year']) ?></span>
            <span class="ar-show__city"><?= e(ar_city($ev)) ?></span>
        </h1>
        <p class="ar-show__venue"><?= e(ar_venue($ev)) ?><?= $start !== '' ? ' · ' . e($start) : '' ?></p>
        <div class="ar-show__cta">
            <?php if ($past): ?>
            <span class="ar-pill ar-pill--light"><?= te('Concertul a avut loc') ?></span>
            <?php elseif ($code === 'sold_out'): ?>
            <span class="ar-pill ar-pill--out"><?= te('Sold out') ?></span>
            <?php else: ?>
            <a href="#bilete" class="ar-btn ar-btn--light ar-btn--lg"><?= te('Alege biletele') ?> <?= AR_ARROW ?></a>
            <?php if ($from): ?><span class="ar-show__from"><?= te('de la') ?> <b><?= e(ar_money($from)) ?></b></span><?php endif; ?>
            <?php if ($code === 'low'): ?><span class="ar-pill ar-pill--hot"><?= e($label) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?= $past ? '' : ar_countdown($ev, 'ar-cd--light') ?>
    </div>
</section>

<?php if (!$past): ?>
<section class="ar-section" id="bilete" aria-labelledby="ar-tix-title">
    <div class="ar-wrap">
        <div class="ar-head2">
            <div>
                <p class="ar-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="ar-h2" id="ar-tix-title"><?= $code === 'sold_out' ? te('Sold out') : te('Alege-ți biletele') ?></h2>
            </div>
            <p class="ar-head2__meta"><?= te('Biletele vin pe email, cu cod QR, imediat după plată.') ?></p>
        </div>
        <?php if ($code === 'sold_out'): ?>
        <p class="ar-empty"><?= te('Toate biletele pentru acest concert s-au vândut. Alege alt oraș din turneu.') ?></p>
        <?php else: ?>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="ar-section ar-section--alt" aria-labelledby="ar-info-title">
    <div class="ar-wrap ar-info">
        <div>
            <p class="ar-eyebrow"><?= te('Despre seară') ?></p>
            <h2 class="ar-h2" id="ar-info-title"><?= e(ar_venue($ev)) ?></h2>
            <div class="ar-prose"><?= (string) ($ev['description'] ?? '') /* HTML redactat în panou */ ?></div>
        </div>
        <dl class="ar-facts">
            <div><dt><?= te('Data') ?></dt><dd><?= e(ar_when($ev, false)) ?></dd></div>
            <?php if ($doors !== ''): ?><div><dt><?= te('Deschiderea porților') ?></dt><dd><?= e($doors) ?></dd></div><?php endif; ?>
            <?php if ($start !== ''): ?><div><dt><?= te('Începe concertul') ?></dt><dd><?= e($start) ?></dd></div><?php endif; ?>
            <div><dt><?= te('Locația') ?></dt><dd><?= e(ar_venue($ev)) ?><?php if ($addr !== ''): ?><br><span><?= e($addr) ?></span><?php endif; ?>
                <?php if ($mapUrl): ?><br><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="ar-link"><?= te('Deschide harta') ?></a><?php endif; ?></dd></div>
            <div><dt><?= te('Bilete') ?></dt><dd><?= te('Online, pe email, cu cod QR') ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($others): ?>
<section class="ar-section" aria-labelledby="ar-more-title">
    <div class="ar-wrap">
        <div class="ar-head2"><div><p class="ar-eyebrow"><?= te('Turneu') ?></p><h2 class="ar-h2" id="ar-more-title"><?= te('Alte orașe') ?></h2></div></div>
        <ul class="ar-rows" data-ar-rows>
            <?php foreach (array_slice($others, 0, 6) as $o): ?><?= ar_row($o) ?><?php endforeach; ?>
        </ul>
        <div class="ar-center"><a href="<?= e(ts_link('events')) ?>" class="ar-btn ar-btn--ghost"><?= te('Toate datele') ?> <?= AR_ARROW ?></a></div>
    </div>
    <div class="ar-follow" aria-hidden="true" data-ar-follow><img alt=""></div>
</section>
<?php endif; ?>

<?php if (!$past && $code !== 'sold_out'): ?>
<div class="ar-mbar" data-ar-mbar>
    <div><small><?= te('Bilete de la') ?></small><b><?= $from ? e(ar_money($from)) : '—' ?></b></div>
    <a href="#bilete" class="ar-btn ar-btn--sm"><?= te('Alege biletele') ?></a>
</div>
<?php endif; ?>
<?php include ts_layout('footer');
