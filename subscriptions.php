<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Abonamente — ' . SITE_NAME;
$activeNav = 'subscriptions';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

$plans = [
    ['name' => 'Clasic', 'price' => 350, 'code' => 'clasic', 'desc' => '6 spectacole din stagiune, loc rezervat în Staluri.', 'features' => ['Loc rezervat', '6 spectacole', 'Program tipărit inclus']],
    ['name' => 'Premium', 'price' => 600, 'code' => 'premium', 'desc' => 'Toată stagiunea, loc premium în Balcon.', 'features' => ['Loc premium rezervat', 'Toate spectacolele', 'Acces avanpremiere', 'Program tipărit inclus'], 'featured' => true],
    ['name' => 'Familie', 'price' => 900, 'code' => 'familie', 'desc' => '2 locuri alăturate, 8 spectacole din stagiune.', 'features' => ['2 locuri alăturate', '8 spectacole', 'Reducere 20%']],
];
?>
<section class="pt-32 pb-12 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto text-center">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Fidelitate</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-6">Abonamente de stagiune</h1>
        <p class="text-ivory/70 text-lg max-w-2xl mx-auto">Păstrați același loc la toate spectacolele stagiunii. Reduceri pentru elevi, studenți și pensionari.</p>
    </div>
</section>
<section class="pb-24 px-4 lg:px-8">
    <div class="max-w-6xl mx-auto grid md:grid-cols-3 gap-6">
        <?php foreach ($plans as $p): ?>
        <div class="rounded-2xl p-8 border <?= !empty($p['featured']) ? 'border-gold bg-gradient-to-b from-gold/10 to-transparent' : 'border-gold/10 bg-charcoal/40' ?>">
            <?php if (!empty($p['featured'])): ?><p class="text-gold text-xs tracking-widest uppercase mb-2">Recomandat</p><?php endif; ?>
            <h3 class="font-display text-2xl mb-2"><?= e($p['name']) ?></h3>
            <p class="font-display text-4xl text-gold mb-1"><?= $p['price'] ?> <span class="text-lg text-warm-gray">RON</span></p>
            <p class="text-warm-gray text-sm mb-6"><?= e($p['desc']) ?></p>
            <ul class="space-y-2 mb-8 text-sm text-ivory/80">
                <?php foreach ($p['features'] as $f): ?><li class="flex items-center gap-2"><span class="text-gold">✓</span> <?= e($f) ?></li><?php endforeach; ?>
            </ul>
            <a href="/finalizare?plan=<?= e($p['code']) ?>" class="<?= !empty($p['featured']) ? 'btn-gold' : 'btn-outline' ?> w-full py-3 rounded-lg block text-center">Alege abonamentul</a>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="text-center text-warm-gray text-sm mt-10 max-w-2xl mx-auto">Reduceri sociale (elevi, studenți, pensionari) și pentru grupuri organizate. Contactați casieria pentru abonamente personalizate.</p>
</section>
<?php include __DIR__ . '/includes/footer.php';
