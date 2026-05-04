<?php
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'cloudflare.com (control)' => '104.16.123.96',
    'discord.com (alt CF site)' => '162.159.135.232',
    'reddit.com (alt CF range)' => '146.75.117.140',
    'core.tixello.com IP 1' => '104.21.89.205',
    'core.tixello.com IP 2' => '172.67.164.226',
    'origin direct' => '89.44.137.26',
];

foreach ($tests as $name => $ip) {
    $start = microtime(true);
    $sock = @fsockopen('tcp://' . $ip, 443, $errno, $errstr, 3);
    $time = round((microtime(true) - $start) * 1000);
    if ($sock) {
        echo "{$name} ({$ip}): OK ({$time}ms)\n";
        fclose($sock);
    } else {
        echo "{$name} ({$ip}): FAIL - {$errstr} ({$errno}) ({$time}ms)\n";
    }
}
