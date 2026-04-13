<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Termeni și condiții — ' . ORG_NAME;
$showBackLink = true;
require_once __DIR__ . '/includes/head.php';
?>
<section class="section" style="max-width:720px;margin:0 auto;">
  <h1 style="font-family:var(--font-display);font-size:32px;font-weight:300;margin-bottom:24px;">Termeni și <em style="font-style:italic;color:var(--accent);">condiții</em></h1>
  <div class="event-desc">
    <p>Termenii și condițiile pentru achiziția de bilete prin acest site sunt reglementate de organizatorul evenimentelor — <strong><?= htmlspecialchars(ORG_NAME) ?></strong> — și de platforma de ticketing <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank" style="color:var(--accent);"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>.</p>
    <p style="margin-top:16px;">Pentru termenii și condițiile complete ale platformei, vizitează <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>/termeni-si-conditii" target="_blank" style="color:var(--accent);">pagina de termeni</a>.</p>
    <p style="margin-top:16px;">Biletele achiziționate sunt supuse politicii de retur a organizatorului.</p>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
