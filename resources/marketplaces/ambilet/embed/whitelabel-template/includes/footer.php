<!-- FOOTER -->
<footer>
  <div class="footer-brand"><?= htmlspecialchars(ORG_NAME) ?></div>
  <ul class="footer-links">
    <li><a href="<?= BASE_PATH ?>/terms">Termeni și condiții</a></li>
    <li><a href="<?= BASE_PATH ?>/privacy">Confidențialitate</a></li>
  </ul>
  <div class="footer-powered">
    Biletele prin
    <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>
    · Powered by
    <a href="https://tixello.com" target="_blank">Tixello</a>
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
