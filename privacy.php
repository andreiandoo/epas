<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Confidențialitate — ' . SITE_NAME;
// Conținut real din tenant, dacă e configurat
$resp = api_get('/tenant-client/privacy', [], 600);
$content = ($resp['success'] && !empty($resp['data']['content'])) ? $resp['data']['content'] : null;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-8">Politica de confidențialitate</h1>
        <div class="prose prose-invert prose-lg max-w-none text-ivory/70">
            <?php if ($content): echo $content; else: ?>
                <p>Prelucrăm datele dumneavoastră cu caracter personal exclusiv în scopul emiterii biletelor, gestionării comenzilor și comunicării legate de spectacole, în conformitate cu Regulamentul (UE) 2016/679 (GDPR).</p>
                <p>Aveți dreptul de acces, rectificare, ștergere și portabilitate a datelor. Pentru orice solicitare, contactați-ne la adresa de email a instituției.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
