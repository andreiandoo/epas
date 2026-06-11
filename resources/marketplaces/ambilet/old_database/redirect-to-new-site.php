<?php
/**
 * Plugin Name: Redirect to New Site (ambilet.ro)
 * Description: 301 redirects all old.ambilet.ro traffic to ambilet.ro, except excluded events, wp-admin, and logged-in admins.
 * Version: 1.0
 *
 * INSTALLATION: Upload to wp-content/mu-plugins/redirect-to-new-site.php
 */

add_action('template_redirect', function () {

    // ========== CONFIGURATION ==========

    // Target domain (new site)
    $new_domain = 'https://ambilet.ro';

    // Event slugs (post_name) that stay on old.ambilet.ro
    // Add/remove slugs as needed — these events + their tickets remain purchasable here
    $excluded_event_slugs = [
        'capra-cu-trei-iezi-clubul-taranului-la-mama-846354',
        'catalin-stepa-aurelian-epuras-porto-arte-840164',
        'matemagica-biblioteca-gheorghe-sincai-oradea-858545',
        'noi-vrem-sa-fim-trimbulinzi-timisoara-849613',
        'pinocchio-qfeel-pitesti-848282',
        'pinocchio-qfeel-ramnicu-valcea-848620',
        'rapunzel-teatru-copii-qfeel-salaluceafarul-845570',
        'rapunzel-teatru-copii-qfeel-sala-luceafarul-785205',
        'trio-tangata-jazz-tango-bucuresti-832785',
        'phoenix-filarmonica-banatul-timisoara-822982',
        'ssssst-silent-comedy-serban-borda-oradea-855277',
        'profu-zapacit-baia-mare-815008',
        'when-violin-meets-guitar-metavivaldi-galati-827968',
        'cantec-soptit-alba-iulia-827580',
        'adrian-ilie-friends-porto-arte-859936',
        'povestea-celor-trei-purcelusi-clubul-taranului-la-mama-846401',
        'rapunzel-qfeel-ploiesti-854125',
        'when-violin-meets-guitar-metavivaldi-galati-792886',
        'when-violin-meets-guitar-metavivaldi-ora-16-galati-808702',
        'cantec-soptit-bucuresti-827586',
        'secretul-printesei-qfeel-sala-luceafarul-857542',
        'scufita-rosie-qfeel-sala-luceafarul-785182',
        'when-violin-meets-guitar-metavivaldi-calarasi-799586',
        'cantec-soptit-tg-mures-826940',
        'cantec-soptit-reghin-826946',
        'ursul-pacalit-de-vulpe-clubul-taranului-la-mama-mtr-846439',
        'ariciul-si-testoasa-uriasa-clubul-taranului-la-mama-846505',
        'motanul-incaltat-clubul-taranului-la-mama-846563',
        'pinocchio-qfeel-campina-844073',
        'peter-pan-qfeel-ora-10-sala-luceafarul-857491',
        'peter-pan-qfeel-ora-12-sala-luceafarul-857587',
        'rapunzel-qfeel-ramnicu-valcea-848662',
        'rapunzel-qfeel-campulung-muscel-848703',
        'rapunzel-by-qfeel-pitesti-846118',
        'rapunzel-by-qfeel-ora-10-iasi-848709',
        'rapunzel-qfeel-ora-12-iasi-848716',
        'ursul-pacalit-de-vulpe-clubul-taranului-la-mama-846452',
        'cei3purcelusi-byqfeel-sala-lucefarul-785197',
        'rapunzel-qfeel-sala-luceafarul-846191',
        'rapunzel-qfeel-constanta-848802',
        'when-violin-meets-guitar-metavivaldi-barlad-822053',
        'tom-degetel-clubul-taranului-la-mama-846534',
        'when-violin-meets-guitar-metavivaldi-focsani-822248',
        'when-violin-meets-guitar-metavivaldi-iasi-792149',
        'povestea-celor-trei-purcelusi-clubul-taranului-la-mama-846490',
        'magic-fairytale-clubul-taranului-la-mama-846547',
        'rapunzel-by-qfeel-campina-846208',
        'trio-tangata-targu-jiu-848520',
        'scufita-rosie-qfeel-sala-luceafarul-848408',
        'rapunzel-qfeel-constanta-846252',
        'when-violin-meets-guitar-metavivaldi-targoviste-855292',
        'when-violin-metavivaldi-ateneul-roman-769872',
        'evolutie-turneu-leo-de-la-rosiori-bucuresti-801452'
    ];

    // Custom path redirects: old path => new path on ambilet.ro
    // Use for pages whose URL changed between old and new site
    $custom_redirects = [
        '/concerte'          => '/bilete-concerte',
        '/festivalmoto/'         => '/festival-moto',
        '/copii/'         => '/evenimente-copii',
        '/evenimente-anterioare/'         => '/evenimente-trecute',
        '/intrebarifrecvente/'         => '/intrebari',
        '/termeni-si-conditii/'         => '/termeni',
        '/politica-de-confidentialitate/'         => '/confidentialitate',
        '/politica-de-utilizare-cookies/'         => '/cookies',
    ];

    // ========== SKIP RULES ==========

    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // 1. Never redirect WordPress core paths
    if (
        strpos($uri, '/wp-admin') !== false ||
        strpos($uri, '/wp-login') !== false ||
        strpos($uri, '/wp-cron') !== false ||
        strpos($uri, '/wp-json') !== false ||
        strpos($uri, 'admin-ajax.php') !== false ||
        strpos($uri, 'wc-api') !== false ||
        strpos($uri, '/wp-content/') !== false
    ) {
        return;
    }

    // 1.5. Never redirect ticket download requests (old WP emails contain these links)
    if (isset($_GET['download_ticket']) || strpos($uri, 'download_ticket') !== false) {
        return;
    }

    // 2. Never redirect POST requests (forms, checkout, AJAX)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }

    // 3. Never redirect logged-in admins (can browse everything)
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }

    // 4. Keep WooCommerce checkout/cart/account pages functional
    //    (needed so excluded events' purchase flow works end-to-end)
    $keep_paths = [
        '/cos',
        '/cart',
        '/checkout',
        '/finalizare',
        '/my-account',
        '/cont',
        '/multumim',
        '/thank-you',
        '/order-received',
        '/order-pay',
        '/cont',
        '/login',
        '/cont/rapoarte-evenimente',
        '/tickets-report'
    ];
    foreach ($keep_paths as $path) {
        if (strpos($uri, $path) !== false) {
            return;
        }
    }

    // 5. Check if current page is an excluded Tickera event
    if (!empty($excluded_event_slugs) && is_singular('tc_events')) {
        $current_slug = get_post_field('post_name', get_queried_object_id());
        if (in_array($current_slug, $excluded_event_slugs, true)) {
            return;
        }
    }

    // 6. Check if current page is a WooCommerce product linked to an excluded event
    if (!empty($excluded_event_slugs) && is_singular('product')) {
        $product_id = get_queried_object_id();
        $linked_event = get_post_meta($product_id, '_tc_event_id', true);
        if ($linked_event) {
            $event_slug = get_post_field('post_name', $linked_event);
            if (in_array($event_slug, $excluded_event_slugs, true)) {
                return;
            }
        }
    }

    // ========== REDIRECT ==========

    // Check custom redirects first (path mapping between old and new site)
    $uri_no_query = strtok($uri, '?');
    $uri_trimmed = rtrim($uri_no_query, '/');
    if (isset($custom_redirects[$uri_no_query]) || isset($custom_redirects[$uri_trimmed])) {
        $target_path = $custom_redirects[$uri_no_query] ?? $custom_redirects[$uri_trimmed];
        wp_redirect($new_domain . $target_path, 301);
        exit;
    }

    // Default: 301 redirect preserving full URI path + query string
    $new_url = $new_domain . $uri;
    wp_redirect($new_url, 301);
    exit;

}, 1); // Priority 1 = run very early in template_redirect