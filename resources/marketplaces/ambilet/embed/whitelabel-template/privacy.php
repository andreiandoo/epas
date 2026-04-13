<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Confidențialitate — ' . ORG_NAME;
$showBackLink = true;
require_once __DIR__ . '/includes/head.php';
?>
<section class="section" style="max-width:720px;margin:0 auto;">
  <h1 style="font-family:var(--font-display);font-size:32px;font-weight:300;margin-bottom:24px;">Politica de <em style="font-style:italic;color:var(--accent);">confidențialitate</em></h1>
  <div class="event-desc">
    <p>Datele personale colectate (nume, email, telefon) sunt procesate de:</p>
    <ul style="margin:16px 0;padding-left:24px;list-style:disc;">
      <li><strong><?= htmlspecialchars(ORG_NAME) ?></strong> — organizatorul evenimentelor</li>
      <li><strong><?= htmlspecialchars(MARKETPLACE_NAME) ?></strong> — platforma de ticketing</li>
    </ul>
    <p>Datele sunt utilizate exclusiv pentru procesarea comenzii, emiterea biletelor și comunicări legate de eveniment.</p>
    <p style="margin-top:16px;">Pentru politica completă, vizitează <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>/confidentialitate" target="_blank" style="color:var(--accent);">pagina de confidențialitate</a>.</p>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
