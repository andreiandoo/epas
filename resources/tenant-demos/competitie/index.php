<?php
require_once __DIR__ . '/includes/boot.php';

$events = tc_upcoming();
$next   = $events[0] ?? null;

$activeNav = 'home';
if ($next && !empty($next['poster_url'])) { $pageImage = $next['poster_url']; }
include __DIR__ . '/includes/head.php';
?>
<main>
<?php if ($next):
    $left = ev_days_left($next);
    $time = ev_time($next);
?>
    <section class="hero on-dark">
        <div class="hero__kanji" aria-hidden="true">空手道</div>
        <div class="wrap">
            <div class="hero__grid">
                <div>
                    <span class="eyebrow">Următoarea competiție</span>
                    <h1><?= e($next['title']) ?></h1>
                    <dl class="hero__meta">
                        <div><dt>Când</dt><dd><?= e(ev_date_label($next)) ?><?= $time ? ', de la ' . e($time) : '' ?></dd></div>
                        <div><dt>Unde</dt><dd><?= e(ev_place($next)) ?></dd></div>
                    </dl>
                    <div class="hero__cta">
                        <a class="btn btn--red" href="/competitii/<?= e($next['slug']) ?>">Cumpără bilete</a>
                        <a class="btn btn--ghost" href="/competitii">Tot calendarul</a>
                        <?php if (($next['price_from'] ?? null) !== null): ?>
                            <span class="hero__from">de la <b><?= e(lei($next['price_from'])) ?></b></span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="hero__poster" href="/competitii/<?= e($next['slug']) ?>" aria-label="<?= e($next['title']) ?>">
                    <?= part_poster($next, 'eager') ?>
                    <?php if ($left !== null && $left >= 0): ?>
                        <div class="countdown">
                            <?php if ($left === 0): ?>
                                <b>Azi</b><span>începe competiția</span>
                            <?php else: ?>
                                <b><?= $left ?></b><span><?= $left === 1 ? 'zi rămasă' : ($left < 20 ? 'zile rămase' : 'de zile rămase') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="wrap">
            <div class="section__head">
                <div>
                    <span class="eyebrow">Sezonul competițional</span>
                    <h2>Calendar</h2>
                </div>
                <a class="link" href="/competitii">Vezi toate competițiile</a>
            </div>
            <div class="cal">
                <?php foreach (array_slice($events, 0, 6) as $ev): $d = ev_date_parts($ev); ?>
                <a class="cal__row" href="/competitii/<?= e($ev['slug']) ?>">
                    <div class="datebox"><b><?= e($d['days']) ?></b><span><?= e($d['month'] . ' ' . $d['year']) ?></span></div>
                    <div>
                        <div class="cal__title"><?= e($ev['title']) ?></div>
                        <div class="cal__place"><?= e(ev_place($ev)) ?></div>
                    </div>
                    <div class="cal__side">
                        <?php if (($ev['price_from'] ?? null) !== null): ?>
                            <div class="cal__price">de la <b><?= e(lei($ev['price_from'])) ?></b></div>
                        <?php else: ?>
                            <div class="cal__price">Bilete în curând</div>
                        <?php endif; ?>
                        <span class="btn btn--sm">Bilete</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero on-dark">
        <div class="hero__kanji" aria-hidden="true">空手道</div>
        <div class="wrap">
            <div class="hero__grid">
                <div>
                    <span class="eyebrow"><?= e(SITE_NAME) ?></span>
                    <h1>Calendarul se anunță în curând</h1>
                    <p style="max-width:48ch;color:rgba(255,255,255,.72)">Momentan nu există competiții cu bilete puse în vânzare. Revino în curând sau urmărește anunțurile federației.</p>
                    <div class="hero__cta" style="margin-top:28px">
                        <a class="btn btn--red" href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">Mergi la wukf.ro</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

    <section class="section section--dark on-dark">
        <div class="wrap">
            <div class="section__head">
                <div>
                    <span class="eyebrow">Fără cozi la casă</span>
                    <h2>De la bilet la tribună</h2>
                </div>
            </div>
            <div class="steps">
                <div class="step">
                    <h3>Alegi competiția</h3>
                    <p>Bilete de o zi sau pentru ambele zile, cu tarife separate pentru copii, elevi și familii.</p>
                </div>
                <div class="step">
                    <h3>Plătești cu cardul</h3>
                    <p>Plata durează sub un minut. Nu ai nevoie de cont, doar de o adresă de email.</p>
                </div>
                <div class="step">
                    <h3>Intri cu codul QR</h3>
                    <p>Primești biletele pe email. Le arăți la intrare de pe telefon sau tipărite.</p>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
