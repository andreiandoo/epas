    <!-- Core Scripts -->
    <script>
        // Public config only - sensitive data kept server-side
        window.AMBILET = {
            apiUrl: '/api/proxy.php',
            siteName: '<?= SITE_NAME ?>',
            siteUrl: '<?= SITE_URL ?>',
            locale: '<?= SITE_LOCALE ?>',
            currency: 'RON',
            currencySymbol: 'lei',
            demoMode: <?= DEMO_MODE ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= asset('assets/js/config.js') ?>"></script>
    <?php if (DEMO_MODE): ?>
    <script src="<?= asset('assets/js/demo-data.js') ?>"></script>
    <?php endif; ?>
    <script src="<?= asset('assets/js/utils.js') ?>"></script>
    <script src="<?= asset('assets/js/api.js') ?>"></script>
    <script src="<?= asset('assets/js/auth.js') ?>"></script>
    <script src="<?= asset('assets/js/cart.js') ?>"></script>
    <?php if (empty($skipJsComponents)): ?>
    <script src="<?= asset('assets/js/components/header.js') ?>"></script>
    <?php endif; ?>
    <script src="<?= asset('assets/js/components/notifications.js') ?>"></script>
    <script src="<?= asset('assets/js/components/event-card.js') ?>"></script>

    <!-- Page-specific scripts -->
    <?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>
</body>
</html>
