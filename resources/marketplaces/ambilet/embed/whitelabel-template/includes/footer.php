<!-- FOOTER -->
<footer style="max-width:1200px;margin:80px auto 0;">
  <div class="footer-brand">
    <?php if (LOGO_URL): ?>
    <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" style="height:28px;opacity:.7;">
    <?php else: ?>
    <?= htmlspecialchars(ORG_NAME) ?>
    <?php endif; ?>
  </div>
  <ul class="footer-links">
    <li><a href="<?= BASE_PATH ?>/terms">Termeni</a></li>
    <li><a href="<?= BASE_PATH ?>/privacy">Confidențialitate</a></li>
  </ul>
  <div class="footer-powered">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--accent);"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
    Bilete prin
    <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>
  </div>
</footer>

<script>var WL_BASE = <?= json_encode(BASE_PATH) ?>;</script>
<script src="<?= BASE_PATH ?>/assets/js/cart.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
  function updateCartBadge() {
    if (typeof WLCart === 'undefined') return;
    var c = WLCart.getItemCount();
    var b = document.getElementById('wl-cart-count');
    if (b) { b.textContent = c; b.style.display = c > 0 ? '' : 'none'; }
  }
  window.addEventListener('wl:cart:update', updateCartBadge);
  updateCartBadge();
</script>
</body>
</html>
