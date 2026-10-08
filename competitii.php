<?php
require_once __DIR__ . '/includes/boot.php';

$events = tc_upcoming();

// Categoriile prezente efectiv în calendar (pentru filtre)
$cats = [];
foreach ($events as $ev) {
    if (!empty($ev['category']['slug'])) { $cats[$ev['category']['slug']] = $ev['category']['name']; }
}

$activeNav = 'events';
$pageTitle = 'Competiții și bilete — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>
<main x-data="{ cat: '' }">
    <div class="wrap">
        <div class="page-head">
            <span class="eyebrow">Calendar competițional</span>
            <h1>Competiții</h1>
        </div>

        <?php if (count($cats) > 1): ?>
        <div class="chips" style="margin-top:22px" role="group" aria-label="Filtrează după tip">
            <button type="button" class="chip" :class="cat === '' && 'is-on'" @click="cat = ''">Toate</button>
            <?php foreach ($cats as $slug => $name): ?>
            <button type="button" class="chip" :class="cat === '<?= e($slug) ?>' && 'is-on'" @click="cat = '<?= e($slug) ?>'"><?= e($name) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section class="section--tight" style="padding-bottom:80px">
            <?php if ($events): ?>
            <div class="grid">
                <?php foreach ($events as $ev): ?>
                <div x-show="cat === '' || cat === '<?= e($ev['category']['slug'] ?? '') ?>'" class="grid__cell">
                    <?= part_card($ev) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="panel empty">
                <h2>Nicio competiție în vânzare</h2>
                <p>Calendarul următorului sezon se anunță în curând.</p>
                <a class="btn" href="/">Înapoi acasă</a>
            </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
