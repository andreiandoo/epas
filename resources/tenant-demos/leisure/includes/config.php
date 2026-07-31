<?php
/**
 * nordvale.tixello.ro — configurare skin (tenant LEISURE).
 *
 * Conexiune la API-ul Tixello (core.tixello.com) pentru un TENANT de tip leisure.
 * Citirile publice (experiențe/evenimente, config) folosesc ?tenant=ID — fără cheie API.
 * Seating / hold-uri folosesc API-ul public /api/public/* prin proxy (vezi api/proxy.php).
 *
 * Secrete/override-uri locale se pun în includes/config.local.php (ne-versionat).
 * IMPORTANT: setează TENANT_ID la id-ul real al tenantului leisure înainte de go-live
 * (recomandat prin includes/config.local.php ca să nu ajungă în repo).
 */

// ---- Core API ----
define('API_BASE',     'https://core.tixello.com/api');   // baza API
define('CORE_URL',     'https://core.tixello.com');       // pentru asset-uri (/storage/...)
define('TENANT_ID',    18);                               // TODO: id-ul tenantului leisure „Nordvale”

// ---- Identitate site (branding) ----
define('SITE_NAME',      'Nordvale');
define('SITE_TAGLINE',   'Wild Park & Forest Reserve');
define('SITE_CITY',      'Nordvale');
define('SITE_LOGO_TEXT', 'N');

// ---- Comportament ----
define('API_CACHE_TTL',  120);   // secunde, cache fișier pentru GET-uri publice
define('API_TIMEOUT',    15);    // secunde, timeout cURL
define('DEBUG',          false); // true = afișează erori API în pagină

// Override-uri locale (opțional): API_BASE alt mediu, TENANT_ID real, DEBUG, etc.
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
