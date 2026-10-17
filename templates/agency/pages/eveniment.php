<?php
/**
 * /eveniment/{slug}: artiștii și data mare peste fotografia concertului, cine cântă (cu link
 * spre pagina fiecăruia din roster), biletele (harta sălii pentru locurile numerotate,
 * cantități pentru restul), programul serii, locația și celelalte concerte ale agenției.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = ag_event((string) ($_GET['slug'] ?? ''));
if (!$ev) {
    ts_not_found();
}
$past = ag_is_past($ev);
[$code, $label] = ag_status($ev);
$ext = ag_external($ev); // biletele le vinde alt organizator (ex. un festival)
$extLink = $ext ? '<a href="' . e($ext) . '" target="_blank" rel="noopener" class="ag-btn ag-btn--lg">' . e($label) . ' <span aria-hidden="true">↗</span><span class="sr-only"> (' . te('se deschide într-o filă nouă') . ')</span></a>' : '';
$dp = ag_date_parts($ev);
$v = (array) ($ev['venue'] ?? []);
$addr = trim(implode(', ', array_filter([$v['address'] ?? '', ag_city($ev)])));
$mapUrl = $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($v['name'] ?? '') . ', ' . $addr) : null;
$from = ag_from_price($ev);
$img = (string) ($ev['hero_image_url'] ?? $ev['poster_url'] ?? '');
$doors = substr((string) ($ev['door_time'] ?? ''), 0, 5);
$start = substr((string) ($ev['start_time'] ?? ''), 0, 5);
$others = array_values(array_filter(ag_upcoming(), fn ($x) => $x['slug'] !== $ev['slug']));
$lineup = array_values(array_filter((array) ($ev['roster'] ?? []), fn ($r) => !empty($r['slug'])));
$showName = ag_show_name($ev);

$pageTitle = t('{artist} în {city} — bilete, {date}', ['artist' => $showName, 'city' => ag_city($ev), 'date' => ag_when($ev, false)]);
$pageDescription = ts_plain($ev['short_description'] ?? '') ?: t('Bilete la concertul {artist} din {city}, {date}, {venue}.', ['artist' => $showName, 'city' => ag_city($ev), 'date' => ag_when($ev), 'venue' => ag_venue($ev)]);
$pageImage = $ev['poster_url'] ?? $img;
$pageSchema = [
    ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => $lineup
        ? array_map(fn ($r) => ['name' => $r['name'], 'type' => 'MusicGroup', 'url' => ts_link('artist', ['id' => $r['slug']])], $lineup)
        : [['name' => $showName, 'type' => 'MusicGroup']]]),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Evenimente'), ts_link('events')], [$showName, ts_link('event', ['slug' => $ev['slug']])]]),
];
$headSolid = false;
$bodyClass = ($past || $ext) ? '' : 'ag-has-bar';
include ts_layout('head');
include ts_layout('header');
?>
<section class="ag-show">
    <div class="ag-show__media" aria-hidden="true">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" fetchpriority="high" data-parallax="0.1"><?php endif; ?>
        <span class="ag-hero__tone"></span>
    </div>
    <div class="ag-wrap ag-show__body">
        <nav class="ag-crumbs" aria-label="<?= te('Ești aici') ?>"><a href="/"><?= te('Acasă') ?></a> / <a href="<?= e(ts_link('events')) ?>"><?= te('Evenimente') ?></a></nav>
        <p class="ag-eyebrow ag-eyebrow--light"><?= e(SITE_NAME) ?> <?= te('prezintă') ?></p>
        <h1 class="ag-show__title">
            <span class="ag-show__date"><b><?= e($dp['day']) ?></b> <?= e($dp['month']) ?> <?= e($dp['year']) ?> · <?= e(ag_city($ev)) ?></span>
            <span class="ag-show__city"><?= e($showName) ?></span>
        </h1>
        <p class="ag-show__venue"><?= e(ag_venue($ev)) ?><?= $start !== '' ? ' · ' . e($start) : '' ?></p>
        <?php if ($lineup): ?>
        <ul class="ag-lineup" aria-label="<?= te('Cine cântă') ?>">
            <?php foreach ($lineup as $r): ?>
            <li><a href="<?= e(ts_link('artist', ['id' => $r['slug']])) ?>"><?php if (!empty($r['photo'])): ?><img src="<?= e($r['photo']) ?>" alt=""><?php endif; ?><span><?= e($r['name']) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="ag-show__cta">
            <?php if ($past): ?>
            <span class="ag-pill ag-pill--light"><?= te('Concertul a avut loc') ?></span>
            <?php elseif ($code === 'sold_out'): ?>
            <span class="ag-pill ag-pill--out"><?= te('Sold out') ?></span>
            <?php elseif ($ext): ?>
            <?= str_replace('ag-btn ag-btn--lg', 'ag-btn ag-btn--light ag-btn--lg', $extLink) ?>
            <?php else: ?>
            <a href="#bilete" class="ag-btn ag-btn--light ag-btn--lg"><?= te('Alege biletele') ?> <?= AG_ARROW ?></a>
            <?php if ($from): ?><span class="ag-show__from"><?= te('de la') ?> <b><?= e(ag_money($from)) ?></b></span><?php endif; ?>
            <?php if ($code === 'low'): ?><span class="ag-pill ag-pill--hot"><?= e($label) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?= $past ? '' : ag_countdown($ev, 'ag-cd--light') ?>
    </div>
</section>

<?php if (!$past && $ext): ?>
<section class="ag-section" id="bilete" aria-labelledby="ag-tix-title">
    <div class="ag-wrap">
        <div class="ag-extbox">
            <div>
                <p class="ag-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="ag-h2" id="ag-tix-title"><?= te('Biletele le vinde organizatorul') ?></h2>
                <p class="ag-lead"><?= te('Concertul face parte din programul {venue}. Biletele se cumpără direct de la organizator.', ['venue' => ag_venue($ev)]) ?></p>
            </div>
            <?= $extLink ?>
        </div>
    </div>
</section>
<?php elseif (!$past): ?>
<section class="ag-section" id="bilete" aria-labelledby="ag-tix-title">
    <div class="ag-wrap">
        <div class="ag-head2">
            <div>
                <p class="ag-eyebrow"><?= te('Bilete') ?></p>
                <h2 class="ag-h2" id="ag-tix-title"><?= $code === 'sold_out' ? te('Sold out') : te('Alege-ți biletele') ?></h2>
            </div>
            <p class="ag-head2__meta"><?= te('Biletele vin pe email, cu cod QR, imediat după plată.') ?></p>
        </div>
        <?php if ($code === 'sold_out'): ?>
        <p class="ag-empty"><?= te('Toate biletele pentru acest concert s-au vândut. Alege alt concert al agenției.') ?></p>
        <p><a href="/#newsletter" class="ag-btn ag-btn--ghost"><?= te('Anunță-mă de datele noi') ?> <?= AG_ARROW ?></a></p>
        <?php else: ?>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="ag-section ag-section--alt" aria-labelledby="ag-info-title">
    <div class="ag-wrap ag-info">
        <div>
            <p class="ag-eyebrow"><?= te('Despre seară') ?></p>
            <h2 class="ag-h2" id="ag-info-title"><?= e(ag_venue($ev)) ?></h2>
            <div class="ag-prose"><?= (string) ($ev['description'] ?? '') /* HTML redactat în panou */ ?></div>
        </div>
        <dl class="ag-facts">
            <div><dt><?= te('Data') ?></dt><dd><?= e(ag_when($ev, false)) ?></dd></div>
            <?php if ($doors !== ''): ?><div><dt><?= te('Deschiderea porților') ?></dt><dd><?= e($doors) ?></dd></div><?php endif; ?>
            <?php if ($start !== ''): ?><div><dt><?= te('Începe concertul') ?></dt><dd><?= e($start) ?></dd></div><?php endif; ?>
            <div><dt><?= te('Locația') ?></dt><dd><?= e(ag_venue($ev)) ?><?php if ($addr !== ''): ?><br><span><?= e($addr) ?></span><?php endif; ?>
                <?php if ($mapUrl): ?><br><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="ag-link"><?= te('Deschide harta') ?></a><?php endif; ?></dd></div>
            <div><dt><?= te('Bilete') ?></dt><dd><?= $ext ? te('La organizator') . ' · <a href="' . e($ext) . '" target="_blank" rel="noopener" class="ag-link">' . e(parse_url($ext, PHP_URL_HOST)) . '</a>' : te('Online, pe email, cu cod QR') ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($others): ?>
<section class="ag-section" aria-labelledby="ag-more-title">
    <div class="ag-wrap">
        <div class="ag-head2"><div><p class="ag-eyebrow"><?= te('Evenimente') ?></p><h2 class="ag-h2" id="ag-more-title"><?= te('Alte concerte') ?></h2></div></div>
        <ul class="ag-rows" data-ag-rows>
            <?php foreach (array_slice($others, 0, 6) as $o): ?><?= ag_row($o) ?><?php endforeach; ?>
        </ul>
        <div class="ag-center"><a href="<?= e(ts_link('events')) ?>" class="ag-btn ag-btn--ghost"><?= te('Toate evenimentele') ?> <?= AG_ARROW ?></a></div>
    </div>
    <div class="ag-follow" aria-hidden="true" data-ag-follow><img alt=""></div>
</section>
<?php endif; ?>

<?php if (!$past && $code !== 'sold_out' && !$ext): ?>
<div class="ag-mbar" data-ag-mbar>
    <div><small><?= te('Bilete de la') ?></small><b><?= $from ? e(ag_money($from)) : '—' ?></b></div>
    <a href="#bilete" class="ag-btn ag-btn--sm"><?= te('Alege biletele') ?></a>
</div>
<?php endif; ?>
<?php include ts_layout('footer');
