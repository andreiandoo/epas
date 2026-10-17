<?php
/**
 * Pornirea unei cereri: configurare, site-ul după host, apoi router-ul.
 * Singurul punct de intrare este public/index.php.
 */

define('TS_ROOT', dirname(__DIR__));
define('TS_APP', __DIR__);
define('TS_SHARED', TS_ROOT . '/shared');
define('TS_TEMPLATES', TS_ROOT . '/templates');

require TS_APP . '/config.php';
require TS_APP . '/api.php';
require TS_APP . '/site.php';
require TS_APP . '/i18n.php';
require TS_APP . '/theme.php';
require TS_APP . '/render.php';
require TS_APP . '/gallery.php';
require TS_APP . '/demo_frame.php';
require TS_SHARED . '/partials/commerce.php';
require TS_SHARED . '/partials/cookiebar.php';
require TS_APP . '/router.php';
