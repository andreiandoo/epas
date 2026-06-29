<?php
/**
 * /weekend — the upcoming weekend's events grouped by category.
 * Thin entry; the actual page is rendered by includes/when-events-page.php.
 */
$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';

$dateMode = 'weekend';
require __DIR__ . '/includes/when-events-page.php';
