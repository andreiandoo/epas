    <!-- Core Scripts -->
    <script>
        // Pass PHP config to JavaScript
        window.AMBILET_CONFIG = {
            API_BASE_URL: '<?= API_BASE_URL ?>',
            API_KEY: '<?= API_KEY ?>',
            SITE_NAME: '<?= SITE_NAME ?>',
            SITE_URL: '<?= SITE_URL ?>'
        };
    </script>
    <script src="<?= asset('assets/js/config.js') ?>"></script>
    <script src="<?= asset('assets/js/utils.js') ?>"></script>
    <script src="<?= asset('assets/js/api.js') ?>"></script>
    <script src="<?= asset('assets/js/auth.js') ?>"></script>
    <script src="<?= asset('assets/js/cart.js') ?>"></script>
    <script src="<?= asset('assets/js/components/header.js') ?>"></script>
    <script src="<?= asset('assets/js/components/footer.js') ?>"></script>
    <script src="<?= asset('assets/js/components/notifications.js') ?>"></script>
    <script src="<?= asset('assets/js/components/event-card.js') ?>"></script>

    <!-- Page-specific scripts -->
    <?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>
</body>
</html>
