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
    <?php if (defined('ORG_ADDRESS') && ORG_ADDRESS): ?>
    <li><span style="color:var(--text-dim);"><?= htmlspecialchars(ORG_ADDRESS) ?></span></li>
    <?php endif; ?>
    <?php if (defined('ORG_PHONE') && ORG_PHONE): ?>
    <li><a href="tel:<?= htmlspecialchars(ORG_PHONE) ?>"><?= htmlspecialchars(ORG_PHONE) ?></a></li>
    <?php endif; ?>
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

  // Cart expiry timer
  (function() {
    var bar = document.getElementById('wl-timer-bar');
    var countdownEl = document.getElementById('wl-timer-countdown');
    if (!bar || !countdownEl) return;

    function tick() {
      var expires = parseInt(localStorage.getItem('wl_cart_expires') || '0', 10);
      if (!expires || typeof WLCart === 'undefined' || WLCart.getItemCount() === 0) {
        bar.style.display = 'none';
        return;
      }

      var remaining = expires - Date.now();
      if (remaining <= 0) {
        // Timer expired — clear cart and redirect
        WLCart.clearCart();
        bar.style.display = 'none';
        window.location.href = (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/';
        return;
      }

      var mins = Math.floor(remaining / 60000);
      var secs = Math.floor((remaining % 60000) / 1000);
      countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
      bar.style.display = '';
    }

    tick();
    setInterval(tick, 1000);
  })();
</script>
</body>
</html>
