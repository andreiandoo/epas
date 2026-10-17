<?php
/**
 * Configurarea serverului de site-uri. Nimic de aici nu ține de un tenant
 * anume: tenantul, template-ul și personalizarea vin din core, după host.
 *
 * Override-uri locale (alt core, DEBUG): config.local.php în rădăcina
 * proiectului (lângă public/), nu în git.
 */

if (is_file(TS_ROOT . '/config.local.php')) {
    require TS_ROOT . '/config.local.php';
}

defined('API_BASE')      || define('API_BASE', 'https://core.tixello.com/api');
defined('CORE_URL')      || define('CORE_URL', 'https://core.tixello.com');
defined('API_TIMEOUT')   || define('API_TIMEOUT', 15);
defined('API_CACHE_TTL') || define('API_CACHE_TTL', 120);   // secunde, GET-uri publice
defined('SITE_CONFIG_TTL') || define('SITE_CONFIG_TTL', 60); // secunde, configurația site-ului
defined('DEBUG')         || define('DEBUG', false);
defined('CACHE_ROOT')    || define('CACHE_ROOT', TS_ROOT . '/cache');
defined('SITE_LOCALE')   || define('SITE_LOCALE', 'ro');
// Host-urile galeriei de demo-uri (listă separată prin virgulă)
defined('DEMO_GALLERY_HOSTS') || define('DEMO_GALLERY_HOSTS', 'demo.tics.ro');
// Unde duce „Vreau acest site” din bara demo-urilor și din galerie
defined('DEMO_CTA_URL') || define('DEMO_CTA_URL', 'https://tixello.ro');

if (!is_dir(CACHE_ROOT)) {
    @mkdir(CACHE_ROOT, 0775, true);
}

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
