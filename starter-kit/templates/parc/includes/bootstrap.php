<?php
/** Template bootstrap — locates the (vendored or shared) kit and boots it. */
$__local  = __DIR__ . '/../kit';
$__shared = __DIR__ . '/../../../kit';
define('KIT_DIR', is_dir($__local) ? $__local : $__shared);

require_once KIT_DIR . '/core/config.php';
kit_boot(require __DIR__ . '/../site.config.php');
