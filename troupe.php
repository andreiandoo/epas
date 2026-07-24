<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Trupa — ' . SITE_NAME;
$activeNav = 'troupe';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Artiștii noștri</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-6">Trupa</h1>
        <p class="text-ivory/70 text-lg max-w-2xl mb-12">Actori consacrați și tinere talente care dau viață scenei de peste un secol.</p>
        <div class="bg-charcoal/40 rounded-xl p-10 text-center">
            <p class="text-warm-gray">Distribuția fiecărui spectacol este disponibilă pe pagina spectacolului.</p>
            <a href="/repertoire.php" class="btn-gold px-8 py-3 rounded-lg inline-block mt-6">Vezi repertoriul</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
