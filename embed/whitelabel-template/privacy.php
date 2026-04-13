<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Confidențialitate — ' . ORG_NAME;
require_once __DIR__ . '/includes/head.php';
?>
<h1 style="font-size:22px;font-weight:700;margin-bottom:16px;">Politica de confidențialitate</h1>
<div style="font-size:14px;color:var(--muted);line-height:1.7;">
    <p>Datele personale colectate în procesul de achiziție (nume, email, telefon) sunt procesate de:</p>
    <ul style="margin:12px 0;padding-left:20px;">
        <li><strong><?= htmlspecialchars(ORG_NAME) ?></strong> — organizatorul evenimentelor</li>
        <li><strong><?= htmlspecialchars(MARKETPLACE_NAME) ?></strong> — platforma de ticketing (<a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank"><?= htmlspecialchars(MARKETPLACE_URL) ?></a>)</li>
    </ul>
    <p>Datele sunt utilizate exclusiv pentru procesarea comenzii, emiterea biletelor și comunicări legate de eveniment.</p>
    <p style="margin-top:12px;">Pentru politica completă de confidențialitate a platformei, vizitează <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>/confidentialitate" target="_blank"><?= htmlspecialchars(MARKETPLACE_URL) ?>/confidentialitate</a>.</p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
