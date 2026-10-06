<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared at ' . date('Y-m-d H:i:s');
} else {
    echo 'OPcache not available';
}
