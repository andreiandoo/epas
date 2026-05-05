<?php
// Diagnostic dump for the Cloudflare support ticket.
// Captures verbose curl output + traceroute (if available) for the
// blocked CF edge IPs assigned to core.tixello.com, plus baseline
// connectivity to known-good Cloudflare IPs for comparison.
//
// Delete this file after sending output to CF support.

header('Content-Type: text/plain; charset=utf-8');
ignore_user_abort(true);
set_time_limit(120);

echo "=========================================\n";
echo "  CF Diagnostic — " . date('c') . "\n";
echo "=========================================\n\n";

echo "PHP version:    " . PHP_VERSION . "\n";
echo "Host hostname:  " . gethostname() . "\n";
echo "Source IP seen: " . ($_SERVER['SERVER_ADDR'] ?? '(unknown)') . "\n";
echo "Outbound IP:    ";
$ch = curl_init('https://www.cloudflare.com/cdn-cgi/trace');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$trace = curl_exec($ch);
curl_close($ch);
if (preg_match('/^ip=(.+)$/m', $trace ?? '', $m)) {
    echo trim($m[1]) . "\n";
} else {
    echo "(could not determine)\n";
}
echo "\n";

$targets = [
    'BLOCKED #1 (core.tixello.com edge)' => '104.21.89.205',
    'BLOCKED #2 (core.tixello.com edge)' => '172.67.164.226',
    'CONTROL  (cloudflare.com)'          => '104.16.123.96',
    'CONTROL  (discord.com)'             => '162.159.135.232',
];

foreach ($targets as $label => $ip) {
    echo "=========================================\n";
    echo "  {$label} -> {$ip}:443\n";
    echo "=========================================\n\n";

    // verbose curl -v equivalent
    echo "--- curl -v ---\n";
    $ch = curl_init('https://core.tixello.com/up');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_RESOLVE => ['core.tixello.com:443:' . $ip],
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $f = fopen('php://temp', 'w+'),
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
    ]);
    $r = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    rewind($f);
    $verbose = stream_get_contents($f);
    curl_close($ch);

    echo "result:       HTTP {$info['http_code']}, total={$info['total_time']}s, connect={$info['connect_time']}s\n";
    echo "primary_ip:   " . ($info['primary_ip'] ?? '-') . "\n";
    echo "errno+msg:    {$err}\n";
    echo "verbose:\n{$verbose}\n";

    // raw TCP probe — bypasses TLS/HTTP entirely
    echo "--- raw fsockopen tcp://{$ip}:443 ---\n";
    $start = microtime(true);
    $sock = @fsockopen('tcp://' . $ip, 443, $errno, $errstr, 5);
    $time = round((microtime(true) - $start) * 1000);
    if ($sock) {
        echo "OK  ({$time}ms)\n";
        fclose($sock);
    } else {
        echo "FAIL  errno={$errno}  msg={$errstr}  ({$time}ms)\n";
    }
    echo "\n";
}

echo "=========================================\n";
echo "  Network path (if shell available)\n";
echo "=========================================\n\n";

$shellAvailable = function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')));
if (!$shellAvailable) {
    echo "shell_exec disabled — traceroute not possible from PHP. Ask hosting for traceroute output to:\n";
    echo "  104.21.89.205  (port 443/tcp)\n";
    echo "  172.67.164.226 (port 443/tcp)\n";
} else {
    foreach (['104.21.89.205', '172.67.164.226', '104.16.123.96'] as $ip) {
        echo "--- traceroute -T -p 443 -m 20 -w 2 {$ip} ---\n";
        $cmd = sprintf('traceroute -T -p 443 -m 20 -w 2 %s 2>&1', escapeshellarg($ip));
        $out = @shell_exec($cmd);
        echo (is_string($out) && $out !== '') ? $out : "(no output / command unavailable)\n";
        echo "\n";

        echo "--- tracepath -p 443 {$ip} ---\n";
        $cmd2 = sprintf('tracepath -p 443 %s 2>&1', escapeshellarg($ip));
        $out2 = @shell_exec($cmd2);
        echo (is_string($out2) && $out2 !== '') ? $out2 : "(no output / command unavailable)\n";
        echo "\n";
    }
}

echo "\n=== END ===\n";
