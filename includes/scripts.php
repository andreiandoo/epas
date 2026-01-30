    <!-- Core Scripts -->
    <script>
        // Public config only - sensitive data kept server-side
        window.AMBILET = {
            apiUrl: '/api/proxy.php',
            siteName: '<?= SITE_NAME ?>',
            siteUrl: '<?= SITE_URL ?>',
            storageUrl: '<?= STORAGE_URL ?>',
            locale: '<?= SITE_LOCALE ?>',
            currency: 'RON',
            currencySymbol: 'lei'
        };
    </script>
    <script src="<?= asset('assets/js/config.js') ?>"></script>
    <!-- Utilities -->
    <script src="<?= asset('assets/js/utils.js') ?>"></script>
    <script src="<?= asset('assets/js/utils/data-transformer.js') ?>"></script>

    <!-- Core -->
    <script src="<?= asset('assets/js/api.js') ?>"></script>
    <script src="<?= asset('assets/js/auth.js') ?>"></script>
    <script src="<?= asset('assets/js/cart.js') ?>"></script>

    <!-- Components -->
    <?php if (empty($skipJsComponents)): ?>
    <script src="<?= asset('assets/js/components/header.js') ?>"></script>
    <?php endif; ?>
    <script src="<?= asset('assets/js/components/notifications.js') ?>"></script>
    <script src="<?= asset('assets/js/components/notification-sound.js') ?>"></script>
    <script src="<?= asset('assets/js/components/notification-poller.js') ?>"></script>
    <script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
    <script src="<?= asset('assets/js/components/pagination.js') ?>"></script>
    <script src="<?= asset('assets/js/components/empty-state.js') ?>"></script>
    <script src="<?= asset('assets/js/components/featured-carousel.js') ?>"></script>

    <!-- Page-specific scripts -->
    <?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>
</body>
</html>
