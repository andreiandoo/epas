<?php
require_once TEMPLATE_DIR . '/lib.php';

$slug = preg_replace('/[^a-z0-9\-]/i', '', (string) ($_GET['slug'] ?? ''));
$ev   = $slug !== '' ? tc_event($slug, 60) : null;

if (!$ev) {
    http_response_code(404);
    $pageTitle = 'Competiție negăsită — ' . SITE_NAME;
    include ts_layout('head'); include ts_layout('header'); ?>
    <main>
        <section class="phead phead--slim">
            <div class="wrap">
                <span class="label label--red">Eroare 404</span>
                <h1>Competiția nu a fost găsită</h1>
                <p>E posibil ca pagina să fi fost mutată sau competiția să se fi încheiat.</p>
                <p style="margin-top:28px"><?= part_btn('Vezi calendarul', ts_link('events')) ?></p>
            </div>
        </section>
    </main>
    <?php include ts_layout('footer');
    exit;
}

$place   = ev_place($ev);
$time    = ev_time($ev);
$venue   = $ev['venue'] ?? [];
$soldOut = !empty($ev['is_sold_out']);
$off     = !empty($ev['is_cancelled']);
$days    = ev_days_count($ev);
$poster  = $ev['poster_url'] ?? null;

// Tipurile de bilete, în forma folosită de selector (prețul efectiv = cel redus, dacă există)
$types = [];
foreach (($ev['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') !== 'active') { continue; }
    $full = (float) ($t['price'] ?? 0);
    $sale = isset($t['sale_price']) && $t['sale_price'] !== null ? (float) $t['sale_price'] : null;
    $types[] = [
        'id'        => (int) $t['id'],
        'name'      => (string) $t['name'],
        'desc'      => trim(strip_tags((string) ($t['description'] ?? ''))),
        'price'     => ($sale !== null && $sale > 0 && $sale < $full) ? $sale : $full,
        'full'      => $full,
        'available' => (int) ($t['available'] ?? 0),
    ];
}
usort($types, fn ($a, $b) => $a['id'] <=> $b['id']);   // ordinea din admin
// Sală cu locuri numerotate: biletul se cumpără pe loc, din harta tribunelor
$seated = ev_is_seated($ev);
$canBuy = ($types || $seated) && !$soldOut && !$off;
$fromPrice = $types ? min(array_column($types, 'price')) : ($ev['price_from'] ?? 0);

// Datele despre competiție păstrate în coș (afișate în coș și la plată)
$cartEvent = [
    'id'     => (int) $ev['id'],
    'slug'   => $ev['slug'],
    'title'  => $ev['title'],
    'date'   => ev_date_label($ev),
    'place'  => $place,
    'poster' => $poster,
    'url'    => ts_link('event', ['slug' => $ev['slug']]),
];

$fullAddress = implode(', ', array_unique(array_filter([$venue['name'] ?? '', $venue['address'] ?? '', $venue['city'] ?? ''])));
$mapUrl = !empty($venue['google_maps_url'])
    ? $venue['google_maps_url']
    : ($fullAddress !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($fullAddress) : null);

$activeNav       = 'events';
$pageTitle       = $ev['title'] . ' — bilete | ' . SITE_NAME;
$pageDescription = trim(strip_tags((string) ($ev['short_description'] ?? ''))) ?: ('Bilete la ' . $ev['title'] . ', ' . ev_date_label($ev) . ', ' . $place . '.');
if ($poster) { $pageImage = $poster; }
$bodyClass = $canBuy ? 'has-mobile-bar' : '';

// Date structurate pentru motoarele de căutare (SportsEvent cu bilete, locație, organizator)
$pageSchema = [
    ts_event_schema($ev, ['type' => 'SportsEvent']),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Competiții'), ts_link('events')], [$ev['title'], ts_link('event', ['slug' => $ev['slug']])]]),
];

include ts_layout('head');
include ts_layout('header');
?>
<?php if ($seated && $canBuy): ?>
<main x-data="{}">
<?php else: ?>
<main x-data='ticketPicker(<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($types, JSON_UNESCAPED_UNICODE)) ?>)'>
<?php endif; ?>
    <section class="ev-hero">
        <?php if ($poster): ?><div class="ev-hero__bg" style="background-image:url('<?= e($poster) ?>')" data-parallax="6"></div><?php endif; ?>
        <div class="wrap">
            <div class="ev-hero__grid">
                <div>
                    <nav class="crumbs" aria-label="Ești aici" data-enter="0"><a href="/">Acasă</a> / <a href="<?= e(ts_link('events')) ?>">Competiții</a></nav>
                    <p style="margin-top:22px" data-enter="0.1">
                        <span class="label<?= $off ? ' label--red' : '' ?>"><?= $off ? 'Competiție anulată' : e(ev_kind($ev)) . ' · ' . ($days > 1 ? $days . ' zile de concurs' : 'o zi de concurs') ?></span>
                    </p>
                    <h1 data-split data-split-now><?= e($ev['title']) ?></h1>
                    <dl class="facts" data-enter="0.45">
                        <div>
                            <i><?= ICON_CAL ?></i>
                            <div><dt>Data</dt><dd><?= e(ev_date_label($ev)) ?><?= $time ? ' · ' . e($time) : '' ?></dd></div>
                        </div>
                        <div>
                            <i><?= ICON_PIN ?></i>
                            <div><dt>Locația</dt><dd><?= e($place) ?></dd></div>
                        </div>
                    </dl>
                    <?php if (!$off): ?><div data-enter="0.6"><?= part_countdown($ev) ?></div><?php endif; ?>
                </div>
                <div class="poster3d" data-enter="0.3">
                    <div class="poster3d__in" data-tilt="9">
                        <?= part_poster($ev, 'eager') ?>
                        <span class="pcard__glare" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="wrap">
        <div class="ev-body">
            <article>
                <?php if ($seated && $canBuy): ?>
                <section class="seatmap" id="bilete">
                    <div class="seatmap__head">
                        <div>
                            <span class="label">Locuri numerotate</span>
                            <h2>Alege locurile</h2>
                        </div>
                    </div>
                    <?php // Harta comună (public/assets/shared/seatmap.js): zoom, blocare loc, coș ?>
                    <div data-ts-seatmap-inline="<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>"></div>
                </section>
                <?php endif; ?>

                <div class="prose" data-reveal>
                    <h2>Despre competiție</h2>
                    <?php if (!empty($ev['description'])): ?>
                        <?= $ev['description'] /* HTML redactat în admin */ ?>
                    <?php else: ?>
                        <p><?= e($pageDescription) ?></p>
                    <?php endif; ?>
                </div>

                <ul class="infolist" data-reveal>
                    <li><span>Data</span><div><?= e(ev_date_label($ev)) ?><?= $time ? ', acces public de la ' . e($time) : '' ?></div></li>
                    <li><span>Locația</span><div>
                        <?= e($fullAddress) ?>
                        <?php if ($mapUrl): ?><br><a class="link" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">Deschide harta</a><?php endif; ?>
                    </div></li>
                    <li><span>Bilete</span><div>Electronice, trimise pe email imediat după plată. Fiecare bilet are un cod QR unic, scanat la intrare.</div></li>
                    <li><span>Organizator</span><div><?= e(SITE_NAME) ?></div></li>
                </ul>
            </article>

            <aside class="ev-buy"<?= ($seated && $canBuy) ? '' : ' id="bilete"' ?>>
                <div class="buy" data-reveal>
                    <?php if ($seated && $canBuy): ?>
                    <div class="buy__head"><h2>Locurile tale</h2><span>Locuri numerotate</span></div>
                        <div class="tt"><div class="tt__desc" style="margin:0">Alege locurile direct pe harta sălii. Fiecare loc ales îți rămâne blocat 15 minute, cât termini comanda.</div></div>
                        <div class="buy__foot">
                            <div class="total"><span>Locuri de la</span><b><?= e(lei($fromPrice)) ?></b></div>
                            <a class="btn btn--block" href="#bilete">Alege pe hartă<span class="btn__arrow"><?= ICON_ARROW ?></span></a>
                            <ul class="trust">
                                <li><?= ICON_CHECK ?>Biletul e valabil pentru locul ales</li>
                                <li><?= ICON_CHECK ?>Locurile rămân blocate 15 minute</li>
                                <li><?= ICON_CHECK ?>Maximum 10 locuri pe comandă</li>
                            </ul>
                        </div>
                    <?php elseif ($canBuy): ?>
                    <div class="buy__head"><h2>Bilete</h2><span>Acces general</span></div>
                        <template x-for="t in types" :key="t.id">
                            <div class="tt" :class="qty[t.id] > 0 && 'is-picked'">
                                <div>
                                    <div class="tt__name" x-text="t.name"></div>
                                    <div class="tt__desc" x-show="t.desc" x-text="t.desc"></div>
                                    <div class="tt__price">
                                        <span x-text="lei(t.price)"></span><s x-show="t.full > t.price" x-text="lei(t.full)"></s>
                                    </div>
                                </div>
                                <div class="qty">
                                    <button type="button" @click="dec(t)" :disabled="qty[t.id] === 0" :aria-label="'Scade ' + t.name">−</button>
                                    <output x-text="qty[t.id]"></output>
                                    <button type="button" @click="inc(t)" :disabled="qty[t.id] >= limit(t)" :aria-label="'Adaugă ' + t.name">+</button>
                                </div>
                            </div>
                        </template>
                        <div class="buy__foot">
                            <div class="total"><span x-text="count ? ticketsLabel(count) : 'Total'"></span><b data-total x-text="lei(total)"></b></div>
                            <button type="button" class="btn btn--block" :class="count === 0 && 'is-off'" @click="save()">
                                Continuă spre coș<span class="btn__arrow"><?= ICON_ARROW ?></span>
                            </button>
                            <ul class="trust">
                                <li><?= ICON_CHECK ?>Biletele ajung pe email imediat după plată</li>
                                <li><?= ICON_CHECK ?>Rezervate 15 minute cât finalizezi comanda</li>
                                <li><?= ICON_CHECK ?>Maximum 10 bilete de același tip pe comandă</li>
                            </ul>
                        </div>
                    <?php else: ?>
                    <div class="buy__head"><h2>Bilete</h2><span><?= $seated ? 'Locuri numerotate' : 'Acces general' ?></span></div>
                        <div class="buy__foot">
                            <p style="font-weight:800;font-size:19px;margin-bottom:8px">
                                <?= $off ? 'Competiția a fost anulată.' : ($soldOut ? 'Biletele s-au epuizat.' : 'Biletele nu sunt încă în vânzare.') ?>
                            </p>
                            <p class="fine" style="margin:0 0 18px">Urmărește calendarul pentru celelalte competiții ale sezonului.</p>
                            <?= part_btn('Vezi calendarul', ts_link('events'), 'btn--block') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <?php if ($canBuy): ?>
    <div class="mobile-bar">
        <?php if ($seated): ?>
        <div>
            <small>Locuri de la</small>
            <b><?= e(lei($fromPrice)) ?></b>
        </div>
        <a class="btn btn--sm" href="#bilete">Alege locuri</a>
        <?php else: ?>
        <div>
            <small x-text="count ? ticketsLabel(count) : 'Bilete de la'"></small>
            <b x-text="count ? lei(total) : '<?= e(lei($fromPrice)) ?>'"></b>
        </div>
        <a class="btn btn--sm" href="#bilete" x-show="count === 0">Alege bilete</a>
        <button type="button" class="btn btn--sm" x-show="count > 0" x-cloak @click="save()">Spre coș</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php include ts_layout('footer');
