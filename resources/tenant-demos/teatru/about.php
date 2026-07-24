<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Despre noi — ' . SITE_NAME;
$activeNav = 'about';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-12 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Instituție</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-8"><?= e(SITE_NAME) ?></h1>
        <div class="prose prose-invert prose-lg max-w-none text-ivory/70 space-y-4">
            <p>De peste un secol, <?= e(SITE_NAME) ?> aduce pe scenă spectacole de referință ale dramaturgiei românești și universale, într-o continuă căutare a excelenței artistice.</p>
            <p>Repertoriul nostru îmbină clasicii cu producții contemporane îndrăznețe, susținute de o trupă de actori consacrați și tinere talente. Vă invităm să faceți parte din publicul nostru — stagiune după stagiune.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-6 mt-12">
            <div class="bg-charcoal/40 rounded-xl p-6 text-center"><p class="font-display text-4xl text-gold mb-1">100+</p><p class="text-warm-gray text-sm">ani de tradiție</p></div>
            <div class="bg-charcoal/40 rounded-xl p-6 text-center"><p class="font-display text-4xl text-gold mb-1">30+</p><p class="text-warm-gray text-sm">spectacole în repertoriu</p></div>
            <div class="bg-charcoal/40 rounded-xl p-6 text-center"><p class="font-display text-4xl text-gold mb-1">80+</p><p class="text-warm-gray text-sm">artiști</p></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
