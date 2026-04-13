<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$orderNumber = $_GET['order'] ?? '';
$pageTitle = 'Comandă confirmată — ' . ORG_NAME;
$showBackLink = true;
$backLabel = 'Înapoi la spectacole';
require_once __DIR__ . '/includes/head.php';
?>

<div style="text-align:center;padding:80px 20px;">
  <div class="success-icon" style="display:inline-grid;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h2 style="font-family:var(--font-display);font-size:44px;font-weight:300;margin-bottom:10px;">Comandă <em style="font-style:italic;color:#5cc87a;">confirmată!</em></h2>
  <p style="font-size:15px;color:var(--text-muted);max-width:420px;margin:0 auto 32px;">
    Biletele tale sunt pe drum. Verifică inbox-ul (și spam-ul, just in case).
  </p>
  <?php if ($orderNumber): ?>
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:16px 24px;display:inline-flex;align-items:center;gap:10px;font-size:13px;color:var(--text-muted);margin-bottom:32px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;color:var(--accent);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    Biletele au fost trimise · Comandă <strong style="color:var(--text);margin-left:4px;"><?= htmlspecialchars($orderNumber) ?></strong>
  </div>
  <?php endif; ?>
  <div>
    <a href="<?= BASE_PATH ?>/" style="padding:14px 32px;border:1px solid var(--accent);border-radius:var(--radius);color:var(--accent);font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;display:inline-block;transition:background .2s,color .2s;">← Înapoi la spectacole</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
