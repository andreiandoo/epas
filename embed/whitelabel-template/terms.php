<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Termeni și condiții — ' . ORG_NAME;
require_once __DIR__ . '/includes/head.php';
?>
<h1 style="font-size:22px;font-weight:700;margin-bottom:16px;">Termeni și condiții</h1>
<div style="font-size:14px;color:var(--muted);line-height:1.7;">
    <p>Termenii și condițiile pentru achiziția de bilete prin acest site sunt reglementate de organizatorul evenimentelor — <strong><?= htmlspecialchars(ORG_NAME) ?></strong> — și de platforma de ticketing <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>.</p>
    <p style="margin-top:12px;">Pentru termenii și condițiile complete ale platformei de ticketing, vizitează <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>/termeni-si-conditii" target="_blank"><?= htmlspecialchars(MARKETPLACE_URL) ?>/termeni-si-conditii</a>.</p>
    <p style="margin-top:12px;">Biletele achiziționate sunt supuse politicii de retur a organizatorului. Pentru detalii, contactează organizatorul.</p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
