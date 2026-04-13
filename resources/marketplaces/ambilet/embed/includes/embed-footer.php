    </div><!-- /embed-app -->

    <!-- Powered by badge -->
    <div style="text-align:center; padding: 16px 0 8px; font-size: 11px; color: <?= $mutedColor ?>;">
        Bilete oferite prin <a href="<?= SITE_URL ?>" target="_blank" rel="noopener" style="font-weight:600;">Tixello</a>
    </div>

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

    <!-- Core scripts -->
    <script src="<?= SITE_URL ?>/assets/js/utils.js"></script>
    <script src="<?= SITE_URL ?>/api/proxy.php?action=_noop"></script>
    <script src="<?= SITE_URL ?>/assets/js/api.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/cart.js"></script>
    <script src="<?= SITE_URL ?>/embed/assets/js/embed-app.js"></script>
</body>
</html>
