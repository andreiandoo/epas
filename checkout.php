<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Finalizare comandă — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[60vh] flex items-center">
    <div class="max-w-lg mx-auto w-full text-center">
        <h1 class="font-display text-4xl mb-4">Finalizare comandă</h1>
        <div class="bg-gold/10 border border-gold/30 rounded-xl p-8 mt-6">
            <p class="text-ivory/80 mb-2">🔒 Plata (card / Netopia) se activează în etapa următoare a implementării.</p>
            <p class="text-warm-gray text-sm">Selecția locurilor pe hartă și rezervarea temporară sunt funcționale în această versiune.</p>
        </div>
        <a href="/schedule.php" class="btn-outline px-8 py-3 rounded-lg inline-block mt-8">Înapoi la program</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
