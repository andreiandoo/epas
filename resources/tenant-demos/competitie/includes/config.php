<?php
/**
 * competitie.tixello.ro — configurare skin (Federația Română de Karate WUKF).
 *
 * Conexiune la API-ul Tixello (core.tixello.com) pentru un TENANT.
 * Tenantul se rezolvă după domeniul înregistrat în core (?hostname=TENANT_HOST),
 * deci nu există niciun ID hardcodat aici.
 *
 * Secrete/override-uri locale se pun în includes/config.local.php (ne-versionat).
 */

// Override-uri locale (opțional): API_BASE alt mediu, DEBUG, etc.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---- Core API ----
defined('API_BASE')    || define('API_BASE', 'https://core.tixello.com/api');   // baza API
defined('CORE_URL')    || define('CORE_URL', 'https://core.tixello.com');       // pentru asset-uri (/storage/...)
defined('TENANT_HOST') || define('TENANT_HOST', 'competitie.tixello.ro');       // domeniul tenantului în core

// ---- Identitate site (branding) ----
define('SITE_NAME',       'Federația Română de Karate WUKF');
define('SITE_SHORT',      'Karate WUKF');
define('SITE_TAGLINE',    'Bilete la competițiile naționale');
define('SITE_FEDERATION', 'https://www.wukf.ro');

// ---- Comportament ----
defined('API_CACHE_TTL') || define('API_CACHE_TTL', 120);   // secunde, cache fișier pentru GET-uri publice
defined('API_TIMEOUT')   || define('API_TIMEOUT', 15);      // secunde, timeout cURL
defined('DEBUG')         || define('DEBUG', false);         // true = afișează erori API în pagină

// Director cache (creat automat)
if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/cache');
}
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0775, true);
}

// Versiune asset-uri (cache-busting)
define('ASSET_V', (string) (@filemtime(__DIR__ . '/../assets/site.css') ?: 1));
