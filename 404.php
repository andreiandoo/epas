<?php
require_once __DIR__ . '/includes/boot.php';

http_response_code(404);
$pageTitle = 'Pagină negăsită — ' . SITE_SHORT;
include __DIR__ . '/includes/head.php';
?>
<main class="wrap">
    <div class="panel empty" style="margin:56px 0 96px">
        <h2>Pagina nu există</h2>
        <p>Adresa e greșită sau pagina a fost mutată.</p>
        <a class="btn btn--red" href="/competitii">Vezi competițiile</a>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
