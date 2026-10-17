<?php
/**
 * Punctul de intrare al tuturor site-urilor de tenant. Nginx trimite aici
 * orice cerere care nu e un fișier din public/ (try_files ... /index.php).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

ts_serve_static();
ts_boot_site();
ts_dispatch();
