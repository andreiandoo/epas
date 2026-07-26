<?php
/**
 * Template bootstrap — required by every page (first line).
 *
 * Locates the kit and boots it with this site's config. In production the kit
 * is vendored INTO the template folder at deploy time (templates/teatru/kit/),
 * so KIT_DIR resolves locally; in this repo it falls back to the shared
 * starter-kit/kit so the example runs in place.
 */
$__local  = __DIR__ . '/../kit';                 // vendored copy (production)
$__shared = __DIR__ . '/../../../kit';           // shared source (repo/dev)
define('KIT_DIR', is_dir($__local) ? $__local : $__shared);

require_once KIT_DIR . '/core/config.php';
kit_boot(require __DIR__ . '/../site.config.php');
