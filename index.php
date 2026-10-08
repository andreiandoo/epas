<?php
require_once __DIR__ . '/includes/boot.php';

$events = tc_upcoming();
// Previzualizare pentru prezentări: /?evenimente=1 arată pagina cu un singur eveniment în calendar
$only = (int) ($_GET['evenimente'] ?? 0);
if ($only > 0) { $events = array_slice($events, 0, $only); }
$next   = $events[0] ?? null;
$rest   = array_slice($events, 1);

// Detaliile complete ale următoarei competiții (descriere + tipuri de bilete) pentru prim-plan
$spot = $next ? (tc_event($next['slug']) ?: $next) : null;
$spotTypes = [];
foreach (($spot['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') === 'active') { $spotTypes[] = $t; }
}
usort($spotTypes, fn ($a, $b) => $a['id'] <=> $b['id']);

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
                <span class="label" data-enter="0"><?= e(SITE_NAME) ?></span>
                <h1 class="hero__title" data-split data-split-now>Karate <em>văzut din</em> <span class="hl">tribună</span></h1>
            </div>
            <div class="hero__foot">
                <div data-enter="0.5">
                    <p class="hero__lead">Biletele la cupele și campionatele naționale ale federației, cumpărate online, direct de pe telefon.</p>
                    <div class="hero__cta">
                        <?php if (count($events) > 1): ?>
                            <?= part_btn('Vezi competițiile', '/competitii') ?>
                        <?php elseif ($next): ?>
                            <?= part_btn('Cumpără bilete', '/competitii/' . $next['slug']) ?>
                        <?php else: ?>
                            <?= part_btn('Mergi la wukf.ro', SITE_FEDERATION) ?>
                        <?php endif; ?>
                        <a class="btn btn--ghost" href="#cum-functioneaza">Cum funcționează</a>
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

<?php if ($spot):
    $spotDays = ev_days_count($spot);
    $lead = trim(strip_tags((string) ($spot['short_description'] ?? '')));
?>
    <!-- Următoarea competiție, în prim-plan: secțiunea care ține pagina și când e un singur eveniment -->
    <section class="sec sec--light">
        <div class="wrap">
            <div class="spot">
                <div class="spot__poster" data-reveal>
                    <a class="poster3d__in" href="/competitii/<?= e($spot['slug']) ?>" data-tilt="8" style="display:block">
                        <?= part_poster($spot) ?>
                        <span class="pcard__glare" aria-hidden="true"></span>
                    </a>
                </div>
                <div>
                    <span class="label" data-reveal><?= e(ev_kind($spot)) ?> · <?= $spotDays > 1 ? $spotDays . ' zile de concurs' : 'o zi de concurs' ?></span>
                    <h2 data-split><?= e($spot['title']) ?></h2>
                    <?php if ($lead !== ''): ?><p class="spot__lead" data-reveal><?= e($lead) ?></p><?php endif; ?>
                    <ul class="spot__facts" data-reveal>
                        <li><?= ICON_CAL ?><?= e(ev_date_label($spot)) ?><?= ev_time($spot) ? ' · ' . e(ev_time($spot)) : '' ?></li>
                        <li><?= ICON_PIN ?><?= e(ev_place($spot)) ?></li>
                    </ul>
                    <?php if ($spotTypes): ?>
                    <ul class="spot__prices" data-reveal>
                        <?php foreach ($spotTypes as $t): ?>
                        <li><span><?= e($t['name']) ?></span><b><?= e(lei($t['price'])) ?></b></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="spot__cta" data-reveal>
                        <?= part_btn(!empty($spot['is_sold_out']) ? 'Vezi competiția' : 'Alege biletele', '/competitii/' . $spot['slug'] . (empty($spot['is_sold_out']) ? '#bilete' : '')) ?>
                        <?= part_countdown($spot) ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (count($rest) >= 4): ?>
    <!-- Sezon plin: galerie orizontală de afișe -->
    <section class="hs" data-hscroll>
        <div class="hs__pin">
            <div class="wrap">
                <div class="sec__head">
                    <div>
                        <span class="label">Calendar</span>
                        <h2 data-split>Mai departe în sezon</h2>
                    </div>
                    <a class="link" href="/competitii">Tot calendarul</a>
                </div>
            </div>
            <div class="hs__track">
                <?php foreach ($rest as $ev): ?>
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
<?php elseif ($rest): ?>
    <!-- Două-trei competiții: o grilă simplă -->
    <section class="sec">
        <div class="wrap">
            <div class="sec__head">
                <div>
                    <span class="label">Calendar</span>
                    <h2 data-split>Mai departe în sezon</h2>
                </div>
                <a class="link" href="/competitii">Tot calendarul</a>
            </div>
            <div class="grid" data-reveal-group>
                <?php foreach ($rest as $ev): ?>
                <div class="grid__cell"><?= part_card($ev) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php elseif ($next): ?>
    <!-- O singură competiție în vânzare -->
    <section class="sec" style="padding-bottom:0">
        <div class="wrap">
            <div class="soon" data-reveal>
                <div>
                    <span class="label">Calendar</span>
                    <h3 style="margin-top:14px">Următoarele competiții se anunță în curând</h3>
                    <p>Deocamdată sunt bilete în vânzare la o singură competiție. Calendarul complet al federației e publicat pe wukf.ro.</p>
                </div>
                <a class="btn btn--ghost" href="<?= e(SITE_FEDERATION) ?>/category/evenimente/nationale/" target="_blank" rel="noopener">Calendarul federației</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="sec">
        <div class="wrap">
            <div class="soon" data-reveal>
                <div>
                    <span class="label">Calendar</span>
                    <h3 style="margin-top:14px">Calendarul se anunță în curând</h3>
                    <p>Momentan nu există competiții cu bilete puse în vânzare. Urmărește anunțurile federației.</p>
                </div>
                <a class="btn btn--ghost" href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">Mergi la wukf.ro</a>
            </div>
        </div>
    </section>
<?php endif; ?>

    <section class="sec">
        <div class="wrap">
            <div class="sec__head">
                <div>
                    <span class="label">Pentru spectatori</span>
                    <h2 data-split>Ce vezi pe tatami</h2>
                </div>
                <p>Trei probe, trei feluri de a urmări karate. Programul exact al fiecărei competiții e anunțat de federație.</p>
            </div>
            <div class="discs" data-reveal-group>
                <div class="disc">
                    <span class="disc__kanji" aria-hidden="true">型</span>
                    <h3>Kata</h3>
                    <p>Succesiuni de tehnici executate fără adversar, individual sau în echipă. Arbitrii notează precizia, ritmul și forța.</p>
                </div>
                <div class="disc">
                    <span class="disc__kanji" aria-hidden="true">組手</span>
                    <h3>Kumite</h3>
                    <p>Lupta dintre doi sportivi, unul cu centură roșie (aka) și unul cu centură albă (shiro). Punctează tehnica dusă curat și controlat.</p>
                </div>
                <div class="disc">
                    <span class="disc__kanji" aria-hidden="true">古武道</span>
                    <h3>Kobudo</h3>
                    <p>Proba cu arme tradiționale din Okinawa, precum bō sau sai, executată tot sub formă de kata.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="sec sec--light" id="cum-functioneaza">
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
                    <h3>Alegi biletele</h3>
                    <p>Pe tipuri de bilet sau direct pe locul din tribună, acolo unde sala are locuri numerotate.</p>
                </div>
                <div class="step">
                    <span class="step__n">PASUL 02</span>
                    <span class="step__ghost" aria-hidden="true">2</span>
                    <h3>Plătești cu cardul</h3>
                    <p>Nu ai nevoie de cont. Biletele îți rămân rezervate cât completezi datele.</p>
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
