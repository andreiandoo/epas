<?php
/**
 * teatru.tixello.ro — configurare skin.
 *
 * Conexiune la API-ul Tixello (core.tixello.com) pentru un TENANT.
 * Citirile publice (evenimente, config) folosesc ?tenant=ID — fără cheie API.
 * Seating-ul folosește API-ul public /api/public/* prin proxy (vezi api/proxy.php).
 *
 * Secrete/override-uri locale se pun în includes/config.local.php (ne-versionat).
 */

// ---- Core API ----
define('API_BASE',     'https://core.tixello.com/api');   // baza API
define('CORE_URL',     'https://core.tixello.com');       // pentru asset-uri (/storage/...)
define('TENANT_ID',    17);                               // tenantul acestui teatru

// ---- Identitate site (branding) ----
define('SITE_NAME',      'Teatrul Național');
define('SITE_CITY',      'BUCUREȘTI');
define('SITE_LOGO_TEXT', 'TN');

// ---- Comportament ----
define('API_CACHE_TTL',  120);   // secunde, cache fișier pentru GET-uri publice
define('API_TIMEOUT',    15);    // secunde, timeout cURL
define('DEBUG',          false); // true = afișează erori API în pagină

// Override-uri locale (opțional): API_BASE alt mediu, DEBUG, etc.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Director cache (creat automat)
if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/cache');
}
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0775, true);
}

// Locale UI implicit
if (!defined('SITE_LOCALE')) {
    define('SITE_LOCALE', 'ro');
}
