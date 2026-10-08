<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Confirmarea adresei — ' . SITE_SHORT;
$bodyClass = 'is-light';
$headSolid = true;
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light auth">
    <div class="auth__art">
        <span class="label" style="color:rgba(255,255,255,.7)">Contul meu</span>
        <h2 style="margin-top:18px">Biletele tale, la un loc</h2>
        <p>Toate comenzile pentru competițiile federației, cu codurile QR pregătite pentru intrare.</p>
    </div>
    <div class="auth__form" x-data="verifyEmail">
        <span class="label">Contul meu</span>
        <h1>Confirmarea adresei</h1>
        <p x-show="state === 'busy'">Verificăm linkul…</p>
        <div class="alert alert--ok" x-show="state === 'ok'" x-cloak role="status" x-text="message"></div>
        <div class="alert" x-show="state === 'bad'" x-cloak role="alert" x-text="message"></div>
        <p style="margin-top:24px" x-show="state !== 'busy'" x-cloak><a class="btn btn--block" href="/autentificare">Intră în cont</a></p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
