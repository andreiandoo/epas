<?php
require_once __DIR__ . '/includes/boot.php';

http_response_code(404);
$pageTitle = 'Pagină negăsită — ' . SITE_SHORT;
include __DIR__ . '/includes/head.php';
?>
<main>
    <section class="phead" style="min-height:70vh;display:flex;align-items:center">
        <div class="wrap">
            <span class="label label--red">Eroare 404</span>
            <h1>Pagina nu există</h1>
            <p>Adresa e greșită sau pagina a fost mutată.</p>
            <p style="margin-top:28px"><?= part_btn('Vezi competițiile', '/competitii') ?></p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
