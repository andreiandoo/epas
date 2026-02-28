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
    <script defer src="<?= asset('assets/js/config.js') ?>"></script>
    <!-- Utilities -->
    <script defer src="<?= asset('assets/js/utils.js') ?>"></script>
    <script defer src="<?= asset('assets/js/utils/data-transformer.js') ?>"></script>

    <!-- Core -->
    <script defer src="<?= asset('assets/js/api.js') ?>"></script>
    <script defer src="<?= asset('assets/js/auth.js') ?>"></script>
    <script defer src="<?= asset('assets/js/cart.js') ?>"></script>

    <!-- Components -->
    <?php if (empty($skipJsComponents)): ?>
    <script defer src="<?= asset('assets/js/components/header.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/search.js') ?>"></script>
    <?php endif; ?>
    <script defer src="<?= asset('assets/js/components/notifications.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/notification-sound.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/notification-poller.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/event-card.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/pagination.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/empty-state.js') ?>"></script>
    <script defer src="<?= asset('assets/js/components/featured-carousel.js') ?>"></script>

    <!-- Page-specific scripts -->
    <?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>

    <!-- EPAS Tracking -->
    <script defer src="<?= asset('assets/js/tracking.js') ?>"></script>
    <?php
    // Get marketplace client ID from cached config
    if (!isset($trackingClientId)) {
        require_once __DIR__ . '/api.php';
        $configData = api_cached('client_config', fn() => api_get('/config'), 3600);
        $trackingClientId = $configData['data']['client']['id'] ?? null;
    }
    if ($trackingClientId): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        EPASTracking.init({
            apiUrl: '/api/tracking.php',
            marketplaceClientId: <?= (int) $trackingClientId ?>,
            autoTrackPageViews: true,
            autoTrackClicks: true
        });
    });
    </script>
    <?php endif; ?>

    <!-- Tracking Scripts (body) -->
    <?php if (!empty($trackingBodyScripts)) echo $trackingBodyScripts . "\n"; ?>
</body>
</html>
