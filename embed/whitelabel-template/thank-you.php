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
  <!-- Share -->
  <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:32px;">
    <span style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;">Spune-le și prietenilor</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(MARKETPLACE_URL) ?>" target="_blank" style="width:36px;height:36px;border-radius:50%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
    <a href="https://wa.me/?text=<?= urlencode('Am cumpărat bilete de la ' . ORG_NAME . '! ' . MARKETPLACE_URL) ?>" target="_blank" style="width:36px;height:36px;border-radius:50%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.625-1.467A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg></a>
  </div>
  <div>
    <a href="<?= BASE_PATH ?>/" style="padding:14px 32px;border:1px solid var(--accent);border-radius:var(--radius);color:var(--accent);font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;display:inline-block;transition:background .2s,color .2s;">← Înapoi la spectacole</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
