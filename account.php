<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Contul meu — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[60vh] flex items-center">
    <div class="max-w-md mx-auto w-full text-center">
        <h1 class="font-display text-4xl mb-4">Contul meu</h1>
        <p class="text-warm-gray mb-8">Autentifică-te pentru a vedea biletele și abonamentele tale.</p>
        <a href="/autentificare" class="btn-gold px-8 py-3 rounded-lg inline-block">Autentificare</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
