<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$pageTitle = 'Finalizare comandă — ' . ORG_NAME;
$showBackLink = true;
$backLabel = 'Înapoi';
$bp = BASE_PATH;

// Get organizer commission defaults for checkout summary
$orgData = api_cached('wl_org_' . ORG_SLUG, function () {
    return api_get('/marketplace-events/organizers/' . urlencode(ORG_SLUG));
}, 300);
$commMode = $orgData['data']['commission_mode'] ?? 'included';
$commRate = (float) ($orgData['data']['commission_rate'] ?? 5);

require_once __DIR__ . '/includes/head.php';
?>
<script>
window.__WL_COMMISSION__ = <?= json_encode(['mode' => $commMode, 'rate' => $commRate]) ?>;
</script>

<!-- Steps bar -->
<div class="steps-bar">
  <div class="step done"><div class="step-n">✓</div>Bilete</div>
  <div class="step active"><div class="step-n">2</div>Date contact</div>
  <div class="step"><div class="step-n">3</div>Plată</div>
  <div class="step"><div class="step-n">4</div>Confirmare</div>
</div>

<!-- Empty state -->
<div id="wl-empty" style="display:none;text-align:center;padding:80px 40px;">
  <h2 class="section-title" style="margin-bottom:12px;">Coșul tău este <em>gol</em></h2>
  <p style="color:var(--text-muted);margin-bottom:24px;">Adaugă bilete la un eveniment pentru a continua.</p>
  <a href="<?= $bp ?>/" class="btn-primary" style="width:auto;display:inline-flex;">← Înapoi la spectacole</a>
</div>

<!-- Main content -->
<div class="page-wrap" id="wl-checkout-wrap" style="display:none;">

  <!-- LEFT: CART + FORM -->
  <div class="cart-section">

    <h1 class="page-title">Coșul <em>tău</em></h1>

    <!-- Cart items -->
    <div class="block-label">Biletele selectate</div>
    <div id="wl-cart-blocks"></div>

    <!-- Contact form -->
    <div class="block-label" style="margin-top:32px;">Date de contact</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Prenume</label>
        <input type="text" class="form-input" id="wl-fn" placeholder="Ion">
      </div>
      <div class="form-group">
        <label class="form-label">Nume</label>
        <input type="text" class="form-input" id="wl-ln" placeholder="Popescu">
      </div>
      <div class="form-group full">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" id="wl-em" placeholder="ion.popescu@gmail.com">
        <span class="form-note">Biletele vor fi trimise pe această adresă de email</span>
      </div>
      <div class="form-group">
        <label class="form-label">Telefon</label>
        <input type="tel" class="form-input" id="wl-ph" placeholder="+40 7xx xxx xxx">
      </div>
    </div>

    <!-- Voucher -->
    <div class="block-label" style="margin-top:24px;">Cod voucher</div>
    <div class="voucher-row">
      <input class="voucher-input" type="text" id="wl-promo" placeholder="Introdu codul...">
      <button class="voucher-btn" onclick="WLCheckout.applyPromo()">Aplică</button>
    </div>
    <div id="wl-promo-msg" style="display:none;margin-top:8px;font-size:12px;"></div>

    <!-- Terms -->
    <div style="margin-top:24px;">
      <div class="terms-row" onclick="toggleTerms(this)">
        <div class="terms-check" id="wl-terms-check">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span>Sunt de acord cu <a href="<?= $bp ?>/terms">Termenii și Condițiile</a> și cu <a href="<?= $bp ?>/privacy">Politica de Confidențialitate</a>.</span>
      </div>
    </div>
  </div>

  <!-- RIGHT: ORDER SUMMARY -->
  <div class="order-panel">
    <div class="order-card">
      <div class="order-card-header">Sumar comandă</div>
      <div class="order-card-body" id="wl-order-lines"></div>
      <div class="order-total">
        <div class="order-total-label">Total de plată</div>
        <div class="order-total-amount"><span class="order-total-currency">lei </span><span id="wl-total">0</span></div>
      </div>
    </div>

    <div style="margin-top:20px;display:flex;flex-direction:column;gap:12px;">
      <button class="btn-checkout" id="wl-pay-btn" onclick="WLCheckout.submit()" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Finalizează comanda
      </button>
      <div id="wl-error" style="display:none;padding:12px;background:rgba(224,92,68,0.1);border:1px solid rgba(224,92,68,0.3);border-radius:6px;font-size:13px;color:#e05c44;text-align:center;"></div>
      <div class="security-badges">
        <div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Plată securizată SSL</div>
        <div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>3D Secure</div>
        <div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Bilet instant</div>
      </div>
    </div>
  </div>

</div>

<script>
function toggleTerms(row) {
  var chk = row.querySelector('.terms-check');
  chk.classList.toggle('checked');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="<?= $bp ?>/assets/js/checkout.js"></script>
