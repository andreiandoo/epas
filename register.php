<?php
/**
 * bilete.online — /inregistrare
 *
 * Thin wrapper that drops into login.php with mode preselected to
 * `register`. Same unified shell handles all 4 auth flows.
 */
$_GET['mode'] = 'register';
require __DIR__ . '/login.php';
