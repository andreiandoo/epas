        </div>
    </main>

    <footer class="wl-footer">
        <div class="wl-container" style="display:flex;flex-direction:column;align-items:center;gap:8px;">
            <div style="display:flex;align-items:center;gap:16px;font-size:12px;">
                <a href="<?= BASE_PATH ?>/terms" style="color:var(--muted);">Termeni</a>
                <span style="color:var(--border);">|</span>
                <a href="<?= BASE_PATH ?>/privacy" style="color:var(--muted);">Confidențialitate</a>
            </div>
            <div style="font-size:11px;color:var(--muted);">
                Bilete prin <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank" style="font-weight:600;color:var(--muted);"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>
            </div>
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
