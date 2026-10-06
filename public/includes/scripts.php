    <!-- Core Scripts -->
    <script>
        // Public config only - sensitive data kept server-side
        window.BILETEONLINE = {
            apiUrl: '/api/proxy.php',
            siteName: '<?= SITE_NAME ?>',
            siteUrl: '<?= SITE_URL ?>',
            storageUrl: '<?= STORAGE_URL ?>',
            locale: '<?= SITE_LOCALE ?>',
            currency: 'RON',
            currencySymbol: 'lei'
        };

        // Flatpickr â€” calendar custom DD/MM/YYYY pe orice <input type="date">
        (function () {
            if (document.getElementById('bileteonline-flatpickr-css')) return;
            const css = document.createElement('link');
            css.id = 'bileteonline-flatpickr-css';
            css.rel = 'stylesheet';
            css.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
            document.head.appendChild(css);
            const js = document.createElement('script');
            js.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
            js.onload = () => {
                const ro = document.createElement('script');
                ro.src = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ro.js';
                ro.onload = () => initFlatpickrs();
                document.head.appendChild(ro);
            };
            document.head.appendChild(js);
        })();
        function initFlatpickrs() {
            if (typeof flatpickr === 'undefined') return;
            const apply = () => document.querySelectorAll('input[type="date"]:not(.fp-bound)').forEach((el) => {
                el.classList.add('fp-bound');
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    locale: window.flatpickr?.l10ns?.ro || undefined,
                    allowInput: true,
                });
            });
            apply();
            // Observer pentru elemente noi adÄƒugate dinamic (modale, repeatere)
            const obs = new MutationObserver(() => apply());
            obs.observe(document.body, { childList: true, subtree: true });
        }

        // Helpers globale pentru formatare data DD/MM/YYYY (locale ro)
        window.BileteOnlineFmt = {
            _pad: (n) => String(n).padStart(2, '0'),
            // "2026-05-12" or ISO â†’ "12/05/2026"
            date: function (input) {
                if (!input) return '';
                let d;
                if (input instanceof Date) d = input;
                else if (typeof input === 'string') {
                    // YYYY-MM-DD plain date â†’ parse local
                    if (/^\d{4}-\d{2}-\d{2}$/.test(input)) d = new Date(input + 'T00:00:00');
                    else d = new Date(input);
                } else d = new Date(input);
                if (isNaN(d)) return '';
                return this._pad(d.getDate()) + '/' + this._pad(d.getMonth() + 1) + '/' + d.getFullYear();
            },
            // ISO datetime â†’ "12/05/2026 14:30"
            datetime: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(input);
                if (isNaN(d)) return '';
                return this.date(d) + ' ' + this._pad(d.getHours()) + ':' + this._pad(d.getMinutes());
            },
            // ISO time â†’ "14:30"
            time: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(input);
                if (isNaN(d)) return '';
                return this._pad(d.getHours()) + ':' + this._pad(d.getMinutes());
            },
            // "12 mai 2026"
            longDate: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(typeof input === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(input) ? (input + 'T00:00:00') : input);
                if (isNaN(d)) return '';
                return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' });
            },
        };
    </script>
    <!-- Core JS (config/utils/api/auth) is already loaded by head.php via core-bundle.js.
         Re-loading the individual files here re-declares PHP_CONFIG / BileteOnlineUtils /
         BileteOnlineAPI / BileteOnlineAuth and throws "already declared" SyntaxErrors,
         so it is intentionally NOT repeated. Only the extras head.php does not load go here. -->
    <script defer src="<?= asset('assets/js/utils/data-transformer.js') ?>"></script>
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
    <script defer src="<?= asset('assets/js/components/profile-completion-modal.js') ?>"></script>

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
            <?php if (!empty($trackingMarketplaceEventId)): ?>
            marketplaceEventId: <?= (int) $trackingMarketplaceEventId ?>,
            <?php endif; ?>
            autoTrackPageViews: true,
            autoTrackClicks: true
        });
        <?php if (!empty($trackingMarketplaceEventId)): ?>
        // ViewContent â€” fires after PageView on event detail pages, signals
        // a more specific funnel step to Meta (a user actually viewed an
        // event's detail page, not just any random page). Wrapped in a
        // small delay so it lands right after the auto trackPageView.
        try {
            setTimeout(function () {
                if (window.EPASTracking && typeof EPASTracking.trackViewItem === 'function') {
                    EPASTracking.trackViewItem(
                        <?= (int) $trackingMarketplaceEventId ?>,
                        <?= json_encode($trackingMarketplaceEventName ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                    );
                }
            }, 50);
        } catch (e) {}
        <?php endif; ?>
    });
    </script>
    <?php endif; ?>

    <!-- Tracking Scripts (body) -->
    <?php if (!empty($trackingBodyScripts)) echo $trackingBodyScripts . "\n"; ?>
</body>
</html>
