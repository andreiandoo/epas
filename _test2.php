<?php
header('Content-Type: text/plain; charset=utf-8');

// Test 1: control connection to cloudflare.com (any CF IP that should always work)
echo "--- Test cloudflare.com (neutru) ---\n";
$ch = curl_init('https://www.cloudflare.com/cdn-cgi/trace');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => $f1 = fopen('php://temp', 'w+'),
]);
$r1 = curl_exec($ch);
$info1 = curl_getinfo($ch);
rewind($f1);
echo "HTTP: {$info1['http_code']} time: {$info1['total_time']}s\n";
echo "verbose:\n" . stream_get_contents($f1) . "\n";
echo "body: " . substr((string) $r1, 0, 200) . "\n\n";
curl_close($ch);

// Test 2: direct origin with Host header (bypass DNS, hit IP)
echo "--- Test direct origin 89.44.137.26 ---\n";
$ch = curl_init('https://89.44.137.26/up');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Host: core.tixello.com'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => $f2 = fopen('php://temp', 'w+'),
]);
$r2 = curl_exec($ch);
$info2 = curl_getinfo($ch);
rewind($f2);
echo "HTTP: {$info2['http_code']} time: {$info2['total_time']}s\n";
echo "verbose:\n" . stream_get_contents($f2) . "\n";
