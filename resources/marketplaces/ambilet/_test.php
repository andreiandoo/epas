<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP " . PHP_VERSION . "\n";
echo "DNS for core.tixello.com:\n";
echo gethostbyname('core.tixello.com') . "\n";
echo "DNS for 104.21.89.205 reverse:\n";
echo gethostbyaddr('104.21.89.205') . "\n";

echo "\n--- /up direct ---\n";
$ch = curl_init('https://core.tixello.com/up');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => $f = fopen('php://temp', 'w+'),
]);
$r = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
rewind($f);
$verbose = stream_get_contents($f);
curl_close($ch);

echo "HTTP code: " . $info['http_code'] . "\n";
echo "Total time: " . $info['total_time'] . "s\n";
echo "Connect time: " . $info['connect_time'] . "s\n";
echo "Namelookup time: " . $info['namelookup_time'] . "s\n";
echo "Primary IP: " . $info['primary_ip'] . "\n";
echo "Error: " . $err . "\n";
echo "Verbose:\n" . $verbose . "\n";

echo "\n--- Body (first 200) ---\n";
echo substr((string) $r, 0, 200);
