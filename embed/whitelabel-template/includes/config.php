<?php
/**
 * Whitelabel Site Configuration
 * Generated for: {{ORG_NAME}}
 */

define('WL_ROOT', dirname(__DIR__));

// API Configuration — connects to Tixello Core
define('API_BASE_URL', '{{API_BASE_URL}}');
define('STORAGE_URL', '{{STORAGE_URL}}');
define('API_KEY', '{{API_KEY}}');

// Organizer
define('ORG_SLUG', '{{ORG_SLUG}}');
define('ORG_NAME', '{{ORG_NAME}}');

// Site Configuration
define('SITE_NAME', '{{SITE_NAME}}');
define('MARKETPLACE_NAME', '{{MARKETPLACE_NAME}}');
define('MARKETPLACE_URL', '{{MARKETPLACE_URL}}');

// Branding
define('LOGO_URL', '{{LOGO_URL}}');
define('BG_IMAGE_URL', '{{BG_IMAGE_URL}}');
define('ACCENT_COLOR', '{{ACCENT_COLOR}}');
define('HERO_IMAGE_URL', '{{HERO_IMAGE_URL}}');
define('HOME_TITLE', '{{HOME_TITLE}}');
define('HOME_SUBTITLE', '{{HOME_SUBTITLE}}');
define('ORG_ADDRESS', '{{ORG_ADDRESS}}');
define('ORG_PHONE', '{{ORG_PHONE}}');
define('WIDGET_TERMS', '{{WIDGET_TERMS}}');
define('WIDGET_PRIVACY', '{{WIDGET_PRIVACY}}');
define('THEME', '{{THEME}}');

// Base path — set this to the subfolder where the site is installed.
// Examples: '' for root, '/tickets' for a subfolder.
// Auto-detect from script path:
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
define('BASE_PATH', $scriptDir === '/' || $scriptDir === '\\' ? '' : $scriptDir);
