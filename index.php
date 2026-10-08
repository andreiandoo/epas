<?php
require_once __DIR__ . '/includes/boot.php';

$events = tc_upcoming();
$next   = $events[0] ?? null;

// Cifrele sezonului, din calendarul real
$cities = array_unique(array_filter(array_map(fn ($e) => $e['venue']['city'] ?? null, $events)));
$prices = array_filter(array_map(fn ($e) => $e['price_from'] ?? null, $events), fn ($p) => $p !== null);
$minPrice = $prices ? min($prices) : null;

// Tarifele, luate din tipurile de bilete ale următoarei competiții
$tiers = [];
if ($next && ($full = tc_event($next['slug']))) {
    foreach (($full['ticket_types'] ?? []) as $t) {
        if (($t['status'] ?? 'active') === 'active') { $tiers[] = $t; }
    }
    usort($tiers, fn ($a, $b) => $a['id'] <=> $b['id']);
}

$activeNav = 'home';
if ($next && !empty($next['poster_url'])) { $pageImage = $next['poster_url']; }
$pageFootScripts = '<script type="module" src="/assets/hero3d.js?v=' . ASSET_V . '"></script>';
include __DIR__ . '/includes/head.php';
?>
<main>
    <section class="hero">
        <canvas class="hero__canvas" data-hero3d aria-hidden="true"></canvas>
        <div class="hero__veil"></div>
        <div class="wrap hero__inner">
            <div>
                <span class="label" data-enter="0">Sezonul competițional <?= date('Y') ?>–<?= date('Y') + 1 ?></span>
                <h1 class="hero__title" data-split data-split-now>Karate <em>văzut din</em> <span class="hl">tribună</span></h1>
            </div>
            <div class="hero__foot">
                <div data-enter="0.5">
                    <p class="hero__lead">Biletele la cupele și campionatele naționale ale Federației Române de Karate WUKF, cumpărate online, direct de pe telefon.</p>
                    <div class="hero__cta">
                        <?= part_btn('Vezi competițiile', '/competitii') ?>
                        <?php if ($next): ?>
                            <a class="btn btn--ghost" href="/competitii/<?= e($next['slug']) ?>">Bilete la următoarea</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($next): ?>
                <a class="next" href="/competitii/<?= e($next['slug']) ?>" data-enter="0.75">
                    <div class="next__poster"><?= part_poster($next, 'eager') ?></div>
                    <div class="next__body">
                        <span class="label label--red">Urmează</span>
                        <h2 class="next__title"><?= e($next['title']) ?></h2>
                        <p class="next__meta"><?= e(ev_date_label($next)) ?><br><?= e(ev_place($next)) ?></p>
                    </div>
                    <div class="next__row">
                        <?= part_countdown($next) ?>
                        <span class="btn btn--sm">Bilete<span class="btn__arrow"><?= ICON_ARROW ?></span></span>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero__scroll" aria-hidden="true"><i></i>Derulează</div>
    </section>

    <div class="marquee" aria-hidden="true">
        <div class="marquee__track">
            <?php for ($i = 0; $i < 2; $i++): foreach (['Kata', 'Kumite', 'Kobudo', 'Echipe', 'Individual', 'Cupa României', 'Campionat Național'] as $w): ?>
                <span><?= $w ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>

<?php if ($events): ?>
    <section class="sec">
        <div class="wrap">
            <div class="stats" data-reveal-group>
                <div class="stat"><b data-count="<?= count($events) ?>"><?= count($events) ?></b><span>competiții în calendar</span></div>
                <div class="stat"><b data-count="<?= count($cities) ?>"><?= count($cities) ?></b><span>orașe gazdă</span></div>
                <div class="stat"><b><span data-count="<?= (int) $minPrice ?>"><?= (int) $minPrice ?></span><small>lei</small></b><span>cel mai mic preț de bilet</span></div>
                <div class="stat"><b><span data-count="100">100</span><small>%</small></b><span>bilete electronice, cu cod QR</span></div>
            </div>
        </div>
    </section>

    <section class="hs" data-hscroll>
        <div class="hs__pin">
            <div class="wrap">
                <div class="sec__head">
                    <div>
                        <span class="label">Calendar</span>
                        <h2 data-split>Următoarele competiții</h2>
                    </div>
                    <a class="link" href="/competitii">Tot calendarul</a>
                </div>
            </div>
            <div class="hs__track">
                <?php foreach ($events as $ev): ?>
                    <?= part_card($ev) ?>
                <?php endforeach; ?>
                <div class="hs__end">
                    <span class="label">Tot sezonul</span>
                    <h3>Vezi toate competițiile</h3>
                    <?= part_btn('Calendar complet', '/competitii', 'btn--light') ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($tiers): ?>
    <section class="sec sec--light">
        <div class="wrap">
            <div class="sec__head">
                <div>
                    <span class="label">Tarife</span>
                    <h2 data-split>Un bilet pentru fiecare</h2>
                </div>
                <p>Prețurile de mai jos sunt cele de la <?= e($next['title']) ?>. Tarifele se pot schimba de la o competiție la alta.</p>
            </div>
            <div class="tiers" data-reveal-group>
                <?php foreach ($tiers as $i => $t): $hot = stripos($t['name'], 'ambele') !== false; ?>
                <div class="tier<?= $hot ? ' tier--hot' : '' ?>">
                    <?php if ($hot): ?><span class="tier__flag">Ambele zile</span><?php endif; ?>
                    <div class="tier__price"><?= e(rtrim(rtrim(number_format((float) $t['price'], 2, ',', '.'), '0'), ',')) ?><small>lei</small></div>
                    <div class="tier__name"><?= e($t['name']) ?></div>
                    <?php if (!empty($t['description'])): ?><p class="tier__desc"><?= e(strip_tags($t['description'])) ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="margin-top:36px" data-reveal><?= part_btn('Alege biletele', '/competitii/' . $next['slug'] . '#bilete') ?></p>
        </div>
    </section>
<?php endif; ?>

    <section class="sec">
        <div class="wrap">
            <div class="sec__head">
                <div>
                    <span class="label">Fără cozi la casă</span>
                    <h2 data-split>De la bilet la tribună</h2>
                </div>
            </div>
            <div class="steps" data-reveal-group>
                <div class="step">
                    <span class="step__n">PASUL 01</span>
                    <span class="step__ghost" aria-hidden="true">1</span>
                    <h3>Alegi competiția</h3>
                    <p>Bilete de o zi sau pentru ambele zile, cu tarife separate pentru copii, elevi și familii.</p>
                </div>
                <div class="step">
                    <span class="step__n">PASUL 02</span>
                    <span class="step__ghost" aria-hidden="true">2</span>
                    <h3>Plătești cu cardul</h3>
                    <p>Nu ai nevoie de cont. Biletele îți rămân rezervate 15 minute, cât completezi datele.</p>
                </div>
                <div class="step">
                    <span class="step__n">PASUL 03</span>
                    <span class="step__ghost" aria-hidden="true">3</span>
                    <h3>Intri cu codul QR</h3>
                    <p>Primești biletele pe email. Le arăți la intrare de pe telefon sau tipărite.</p>
                </div>
            </div>
        </div>
    </section>

<?php if ($next): ?>
    <section class="cta">
        <div class="cta__ghost" data-drift="14" aria-hidden="true">Hajime</div>
        <div class="wrap">
            <h2 data-split><?= e($next['title']) ?></h2>
            <p data-reveal><?= e(ev_date_label($next)) ?> · <?= e(ev_place($next)) ?></p>
            <div data-reveal><?= part_btn('Cumpără bilete', '/competitii/' . $next['slug'], 'btn--light') ?></div>
        </div>
    </section>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
