    <!-- Core Scripts -->
    <script>
        
        window.AMBILET = {
            apiUrl: '/api/proxy.php',
            siteName: '<?= SITE_NAME ?>',
            siteUrl: '<?= SITE_URL ?>',
            storageUrl: '<?= STORAGE_URL ?>',
            locale: '<?= SITE_LOCALE ?>',
            currency: 'RON',
            currencySymbol: 'lei',
            cartoKey: '<?= defined('CARTO_API_KEY') ? CARTO_API_KEY : '' ?>'
        };

        
        
        
        window.AmbiletTileLayer = function (style) {
            const key = (window.AMBILET && window.AMBILET.cartoKey) || '';
            const osm = () => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            });
            if (!key) return osm();

            const layer = L.tileLayer('https://basemaps.cartocdn.com/' + style + '/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(key), {
                attribution: '&copy; OpenStreetMap, &copy; CARTO',
                maxZoom: 20,
            });
            
            
            
            let swapped = false;
            layer.on('tileerror', function () {
                if (swapped) return;
                swapped = true;
                const map = layer._map;
                if (!map) return;
                map.removeLayer(layer);
                osm().addTo(map);
            });
            return layer;
        };

        
        (function () {
            if (document.getElementById('ambilet-flatpickr-css')) return;
            const css = document.createElement('link');
            css.id = 'ambilet-flatpickr-css';
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
            const apply = () => {
                document.querySelectorAll('input[type="date"]:not(.fp-bound)').forEach((el) => {
                    el.classList.add('fp-bound');
                    flatpickr(el, {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd/m/Y',
                        locale: window.flatpickr?.l10ns?.ro || undefined,
                        allowInput: true,
                    });
                });
                
                
                document.querySelectorAll('input[data-datetime]:not(.fp-bound)').forEach((el) => {
                    el.classList.add('fp-bound');
                    flatpickr(el, {
                        enableTime: true,
                        time_24hr: true,
                        dateFormat: 'Y-m-d\\TH:i',
                        altInput: true,
                        altFormat: 'd/m/Y H:i',
                        locale: window.flatpickr?.l10ns?.ro || undefined,
                        allowInput: true,
                    });
                });
            };
            apply();
            
            const obs = new MutationObserver(() => apply());
            obs.observe(document.body, { childList: true, subtree: true });
        }

        
        window.AmbiletFmt = {
            _pad: (n) => String(n).padStart(2, '0'),
            
            date: function (input) {
                if (!input) return '';
                let d;
                if (input instanceof Date) d = input;
                else if (typeof input === 'string') {
                    
                    if (/^\d{4}-\d{2}-\d{2}$/.test(input)) d = new Date(input + 'T00:00:00');
                    else d = new Date(input);
                } else d = new Date(input);
                if (isNaN(d)) return '';
                return this._pad(d.getDate()) + '/' + this._pad(d.getMonth() + 1) + '/' + d.getFullYear();
            },
            
            datetime: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(input);
                if (isNaN(d)) return '';
                return this.date(d) + ' ' + this._pad(d.getHours()) + ':' + this._pad(d.getMinutes());
            },
            
            time: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(input);
                if (isNaN(d)) return '';
                return this._pad(d.getHours()) + ':' + this._pad(d.getMinutes());
            },
            
            longDate: function (input) {
                if (!input) return '';
                const d = input instanceof Date ? input : new Date(typeof input === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(input) ? (input + 'T00:00:00') : input);
                if (isNaN(d)) return '';
                return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' });
            },
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
    <script defer src="<?= asset('assets/js/components/profile-completion-modal.js') ?>"></script>

    <?php
        // TEMPORARY rollout gate: load the live-chat widget ONLY on /contact
        // while it is being tested, before enabling it site-wide. To go
        // site-wide later, delete this if/endif wrapper and keep the <script>.
        $epChatPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $epChatOnContact = ($epChatPath === '/contact')
            || (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'contact.php');
        if ($epChatOnContact):
    ?>
    <!-- TEMPORARY (testing): hide back-to-top on /contact so it doesn't overlap
         the chat bubble. Remove together with the rollout gate. -->
    <style>.back-to-top{display:none !important;}</style>
    <!-- Live Chat widget (live-chat microservice; self-hides if inactive) -->
    <script defer src="<?= asset('assets/js/components/chat-widget.js') ?>"></script>
    <?php endif; ?>

    <!-- Page-specific scripts -->
    <?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>

    <!-- EPAS Tracking -->
    <script defer src="<?= asset('assets/js/tracking.js') ?>"></script>

    <!-- Newsletter attribution capture (`nl=` URL param → localStorage) -->
    <script defer src="<?= asset('assets/js/newsletter-attribution.js') ?>"></script>
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
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
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
