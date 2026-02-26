<?php
$cacheFile = __DIR__ . '/includes/cache/event-categories.json';
if (file_exists($cacheFile)) {
    unlink($cacheFile);
    echo "Cache event-categories.json DELETED. ✓\n";
} else {
    echo "Cache file not found (already clean).\n";
}

// Also clear nav-counts cache
$navCache = __DIR__ . '/includes/cache/nav-counts.json';
if (file_exists($navCache)) {
    unlink($navCache);
    echo "Cache nav-counts.json DELETED. ✓\n";
}

echo "\nDone. Refresh homepage to fetch fresh data from API.";
?>