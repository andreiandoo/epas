        </div><!-- /embed-app -->
    </main>

    <!-- Footer -->
    <footer style="position:fixed;width:100%;bottom:0;padding:16px;">
        <div style="max-width:1200px;margin:0 auto;display:flex;flex-direction:column;align-items:center;gap:8px;">
            <?php
            // Use organizer's website for terms/privacy, fallback to marketplace
            $orgWebsite = $orgData['data']['social']['website'] ?? '';
            $termsUrl = $orgWebsite ? rtrim($orgWebsite, '/') . '/terms/' : SITE_URL . '/terms';
            $privacyUrl = $orgWebsite ? rtrim($orgWebsite, '/') . '/privacy/' : SITE_URL . '/privacy';
            ?>
            <div style="display:flex;align-items:center;gap:16px;font-size:12px;color:<?= $mutedColor ?>;">
                <a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" style="color:<?= $mutedColor ?>;text-decoration:none;"><?= v2_te('Terms and conditions') ?></a>
                <span style="color:<?= $borderColor ?>;">|</span>
                <a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" style="color:<?= $mutedColor ?>;text-decoration:none;"><?= v2_te('Privacy') ?></a>
            </div>
            <div style="font-size:11px;color:<?= $mutedColor ?>;">
                <?= v2_t('Tickets provided by {site}', ['site' => '<a href="' . SITE_URL . '" target="_blank" rel="noopener" style="font-weight:600;color:' . $mutedColor . ';">' . v2_e(SITE_NAME) . '</a>']) ?>
            </div>
        </div>
    </footer>

    <!-- Embed config for JS -->
    <script>
        window.__EMBED_CONFIG__ = {
            organizerSlug: <?= json_encode($organizerSlug) ?>,
            returnUrl: <?= json_encode($returnUrl) ?>,
            theme: <?= json_encode($theme) ?>,
            accent: <?= json_encode($accentColor) ?>,
            siteUrl: <?= json_encode(SITE_URL) ?>,
            storageUrl: <?= json_encode(STORAGE_URL) ?>,
            baseUrl: '/embed/' + <?= json_encode($organizerSlug) ?>,
        };
    </script>

    <!-- Core scripts (i18n.js first: VQ.t() for every script below) -->
    <?= v2_i18n_script() ?>
    <script src="<?= SITE_URL ?>/assets/v2/js/i18n.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/utils.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/api.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/cart.js"></script>
    <script src="<?= SITE_URL ?>/embed/assets/js/embed-app.js"></script>

    <!-- Update cart badge in header -->
    <script>
        function updateCartBadge() {
            if (typeof EmbedCart === 'undefined') return;
            const count = EmbedCart.getItemCount();
            const $badge = document.getElementById('embed-cart-count');
            if ($badge) {
                $badge.textContent = count;
                $badge.style.display = count > 0 ? '' : 'none';
            }
        }
        window.addEventListener('embed:cart:update', updateCartBadge);
        window.addEventListener('load', () => setTimeout(updateCartBadge, 200));
    </script>
</body>
</html>
