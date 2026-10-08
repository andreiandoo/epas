<?php
require_once __DIR__ . '/includes/boot.php';

$events = tc_upcoming();

// Filtre după felul competiției, doar pentru felurile prezente în calendar
$kinds = [];
foreach ($events as $ev) {
    $key = ev_kind_key($ev);
    $kinds[$key] = ['name' => ev_kind($ev), 'n' => ($kinds[$key]['n'] ?? 0) + 1];
}

$activeNav = 'events';
$pageTitle = 'Competiții și bilete — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>
<main x-data="{ kind: '' }">
    <section class="phead">
        <div class="wrap">
            <span class="label" data-enter="0">Calendar competițional</span>
            <h1 data-split data-split-now>Competiții</h1>
            <p data-enter="0.3"><?= count($events) ?> <?= count($events) === 1 ? 'competiție are' : 'competiții au' ?> bilete în vânzare. Alege una și cumpără online: biletul ajunge pe email, cu cod QR.</p>
            <?php if (count($kinds) > 1): ?>
            <div class="chips" style="margin-top:30px" role="group" aria-label="Filtrează după tip" data-enter="0.45">
                <button type="button" class="chip" :class="kind === '' && 'is-on'" @click="kind = ''">Toate<small><?= count($events) ?></small></button>
                <?php foreach ($kinds as $key => $k): ?>
                <button type="button" class="chip" :class="kind === '<?= e($key) ?>' && 'is-on'" @click="kind = '<?= e($key) ?>'"><?= e($k['name']) ?><small><?= $k['n'] ?></small></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="sec" style="padding-top:56px">
        <div class="wrap">
            <?php if ($events): ?>
            <div class="grid" data-reveal-group>
                <?php foreach ($events as $ev): ?>
                <div class="grid__cell" x-show="kind === '' || kind === '<?= e(ev_kind_key($ev)) ?>'">
                    <?= part_card($ev) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="panel empty">
                <h2>Nicio competiție în vânzare</h2>
                <p>Calendarul următorului sezon se anunță în curând.</p>
                <?= part_btn('Înapoi acasă', '/') ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
