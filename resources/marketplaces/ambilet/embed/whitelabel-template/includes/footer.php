        </div><!-- /wl-content -->
    </main>

    <footer class="wl-footer">
        <div class="wl-container" style="display:flex;flex-direction:column;align-items:center;gap:8px;">
            <div style="display:flex;align-items:center;gap:16px;font-size:12px;">
                <a href="/terms" style="color:var(--muted);">Termeni și condiții</a>
                <span style="color:var(--border);">|</span>
                <a href="/privacy" style="color:var(--muted);">Confidențialitate</a>
            </div>
            <div style="font-size:11px;color:var(--muted);">
                Bilete oferite prin <a href="<?= htmlspecialchars(MARKETPLACE_URL) ?>" target="_blank" rel="noopener" style="font-weight:600;color:var(--muted);"><?= htmlspecialchars(MARKETPLACE_NAME) ?></a>
            </div>
        </div>
    </footer>

    <script>
        window.__WL_CONFIG__ = {
            orgSlug: <?= json_encode(ORG_SLUG) ?>,
            siteName: <?= json_encode(SITE_NAME) ?>,
            marketplaceUrl: <?= json_encode(MARKETPLACE_URL) ?>,
            storageUrl: <?= json_encode(STORAGE_URL) ?>,
            accentColor: <?= json_encode(ACCENT_COLOR) ?>,
        };
    </script>
    <script src="assets/js/cart.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Update cart badge
        function updateCartBadge() {
            var count = WLCart.getItemCount();
            var badge = document.getElementById('wl-cart-count');
            if (badge) { badge.textContent = count; badge.style.display = count > 0 ? '' : 'none'; }
        }
        window.addEventListener('wl:cart:update', updateCartBadge);
        updateCartBadge();
    </script>
</body>
</html>
