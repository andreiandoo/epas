<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$orderNumber = $_GET['order'] ?? '';
$pageTitle = 'Mulțumim — ' . ORG_NAME;
require_once __DIR__ . '/includes/head.php';
?>

<div style="text-align:center;padding:40px 20px;">
    <div style="width:64px;height:64px;margin:0 auto 16px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;">
        <svg width="32" height="32" style="color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 style="font-size:24px;font-weight:700;">Mulțumim pentru comandă!</h1>
    <p style="margin:12px auto 0;font-size:15px;color:var(--muted);max-width:400px;">
        Comanda ta a fost înregistrată cu succes. Vei primi biletele pe email în câteva minute.
    </p>
    <?php if ($orderNumber): ?>
    <div style="margin:24px auto 0;padding:12px 20px;background:var(--card);border:1px solid var(--border);border-radius:10px;display:inline-block;">
        <span style="font-size:13px;color:var(--muted);">Număr comandă:</span>
        <span style="font-size:16px;font-weight:700;margin-left:8px;"><?= htmlspecialchars($orderNumber) ?></span>
    </div>
    <?php endif; ?>
    <div style="margin-top:32px;">
        <a href="/" class="wl-btn">Înapoi la evenimente</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
