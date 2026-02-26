<?php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';

echo "=== NAV-CACHE DIAGNOSTIC ===\n\n";

// 1. Check cache file
$cacheFile = __DIR__ . '/includes/cache/event-categories.json';
echo "1. Cache file: $cacheFile\n";
if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    $content = file_get_contents($cacheFile);
    $data = json_decode($content, true);
    echo "   EXISTS - Age: {$age}s, Size: " . strlen($content) . " bytes\n";
    echo "   JSON valid: " . ($data !== null ? 'YES' : 'NO') . "\n";
    echo "   Items count: " . (is_array($data) ? count($data) : 'N/A') . "\n";
    if (is_array($data) && count($data) > 0) {
        foreach ($data as $cat) {
            echo "   - " . ($cat['name'] ?? '?') . " (slug: " . ($cat['slug'] ?? '?') . ", count: " . ($cat['count'] ?? 0) . ")\n";
        }
    }
    echo "\n";
} else {
    echo "   NOT FOUND\n\n";
}

// 2. Test API call with curl
echo "2. Testing API call (curl)...\n";
$apiUrl = API_BASE_URL . '/event-categories';
echo "   URL: $apiUrl\n";

if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "   curl available: YES\n";
    echo "   HTTP code: $httpCode\n";
    if ($error) echo "   Error: $error\n";
    if ($response) {
        $json = json_decode($response, true);
        echo "   Response valid JSON: " . ($json !== null ? 'YES' : 'NO') . "\n";
        echo "   success: " . ($json['success'] ?? 'N/A') . "\n";
        if (isset($json['data']['categories'])) {
            echo "   Categories from API: " . count($json['data']['categories']) . "\n";
            foreach ($json['data']['categories'] as $cat) {
                echo "   - " . ($cat['name'] ?? '?') . " (slug: " . ($cat['slug'] ?? '?') . ", events: " . ($cat['event_count'] ?? 0) . ")\n";
            }
        } else {
            echo "   Response (first 500 chars): " . substr($response, 0, 500) . "\n";
        }
    }
} else {
    echo "   curl available: NO (will use file_get_contents)\n";

    // Test file_get_contents
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "X-API-Key: " . API_KEY . "\r\nAccept: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $response = @file_get_contents($apiUrl, false, $context);
    echo "   file_get_contents result: " . ($response !== false ? strlen($response) . ' bytes' : 'FAILED') . "\n";
    if ($response) {
        $json = json_decode($response, true);
        echo "   Categories: " . (isset($json['data']['categories']) ? count($json['data']['categories']) : 'N/A') . "\n";
    }
}

echo "\n";

// 3. Check nav-cache.php version
echo "3. Checking nav-cache.php version...\n";
$navCacheContent = file_get_contents(__DIR__ . '/includes/nav-cache.php');
echo "   Has curl_init check: " . (strpos($navCacheContent, 'curl_init') !== false ? 'YES (new version)' : 'NO (old version!)') . "\n";
echo "   Has 'false' return: " . (strpos($navCacheContent, 'return false;') !== false ? 'YES (new version)' : 'NO (old version!)') . "\n";
echo "   Has '=== null': " . (strpos($navCacheContent, '$data === null') !== false ? 'YES (new version)' : 'NO (old version!)') . "\n";

echo "\n";

// 4. Actions
if (isset($_GET['clear'])) {
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
        echo "4. Cache CLEARED! Refresh any page to test.\n";
    } else {
        echo "4. Cache already clear.\n";
    }

    // Also clear nav-counts
    $navCountsFile = __DIR__ . '/includes/cache/nav-counts.json';
    if (file_exists($navCountsFile)) {
        unlink($navCountsFile);
        echo "   nav-counts.json also cleared.\n";
    }
} else {
    echo "4. Add ?clear to URL to clear cache.\n";
}

// 5. Test full getEventCategories flow
echo "\n5. Testing getEventCategories() full flow...\n";
require_once __DIR__ . '/includes/nav-cache.php';
$result = getEventCategories();
echo "   Result count: " . count($result) . "\n";
if (count($result) > 0) {
    $isDemo = ($result[0]['slug'] ?? '') === 'concerte' && ($result[0]['count'] ?? 0) === 156;
    echo "   Is demo data: " . ($isDemo ? 'YES (PROBLEM!)' : 'NO (real data)') . "\n";
    foreach ($result as $cat) {
        echo "   - " . ($cat['name'] ?? '?') . " (slug: " . ($cat['slug'] ?? '?') . ", count: " . ($cat['count'] ?? 0) . ")\n";
    }
}
