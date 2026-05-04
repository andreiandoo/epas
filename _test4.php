<?php
// Distinguish "Cloudflare zone-level L4 block" vs "ambilet.ro hosting blocks
// specific CF prefixes". Probes IPs from the SAME /20 / /16 ranges as the
// blocked core.tixello.com IPs, but at unrelated addresses inside the prefix.
//
// Interpretation:
//   - If most IPs in 104.21.0.0/20 and 172.67.0.0/16 give 'Connection refused'
//     -> hosting blocks the prefix.
//   - If only 104.21.89.205 / 172.67.164.226 (the ones DNS returns for
//     core.tixello.com) give 'Connection refused' but other IPs in the same
//     /20 work -> Cloudflare zone-level L4 block.
//
// Each probe is a 3s TCP-only connect attempt (no TLS/HTTP).

header('Content-Type: text/plain; charset=utf-8');

$probes = [
    // === Zone "control" group: should work if hosting is fine ===
    ['Control: cloudflare.com',         '104.16.123.96',  'control'],
    ['Control: discord.com',            '162.159.135.232','control'],
    ['Control: reddit.com',             '146.75.117.140', 'control'],

    // === Same /20 as 104.21.89.205 (which fails) ===
    ['Same /20 (104.21.0.1)',           '104.21.0.1',     '104.21.0.0/20'],
    ['Same /20 (104.21.32.1)',          '104.21.32.1',    '104.21.0.0/20'],
    ['Same /20 (104.21.64.1)',          '104.21.64.1',    '104.21.0.0/20'],
    ['Same /20 (104.21.128.1)',         '104.21.128.1',   '104.21.0.0/20'],
    ['Same /20 (104.21.200.1)',         '104.21.200.1',   '104.21.0.0/20'],

    // === Other CF /20 ranges nearby, NOT used for tixello ===
    ['CF 104.18.x',                     '104.18.0.1',     '104.18.0.0/20'],
    ['CF 104.20.x',                     '104.20.0.1',     '104.20.0.0/20'],
    ['CF 104.22.x',                     '104.22.0.1',     '104.22.0.0/20'],
    ['CF 104.24.x',                     '104.24.0.1',     '104.24.0.0/20'],

    // === Same /16 as 172.67.164.226 (which fails) ===
    ['Same /16 (172.67.0.1)',           '172.67.0.1',     '172.67.0.0/16'],
    ['Same /16 (172.67.50.1)',          '172.67.50.1',    '172.67.0.0/16'],
    ['Same /16 (172.67.100.1)',         '172.67.100.1',   '172.67.0.0/16'],
    ['Same /16 (172.67.200.1)',         '172.67.200.1',   '172.67.0.0/16'],

    // === Other CF /16 ranges ===
    ['CF 172.64.x',                     '172.64.0.1',     '172.64.0.0/16'],
    ['CF 172.66.x',                     '172.66.0.1',     '172.66.0.0/16'],
    ['CF 172.68.x',                     '172.68.0.1',     '172.68.0.0/16'],

    // === The exact IPs returned for core.tixello.com (known to fail) ===
    ['core.tixello.com #1 (KNOWN FAIL)','104.21.89.205',  'core'],
    ['core.tixello.com #2 (KNOWN FAIL)','172.67.164.226', 'core'],

    // === Origin direct (known to fail with No route to host) ===
    ['Origin direct',                   '89.44.137.26',   'origin'],
];

$prefixStats = [];

foreach ($probes as [$name, $ip, $prefix]) {
    $start = microtime(true);
    $sock = @fsockopen('tcp://' . $ip, 443, $errno, $errstr, 3);
    $time = round((microtime(true) - $start) * 1000);
    if ($sock) {
        echo str_pad($name, 38) . " {$ip}: OK ({$time}ms)\n";
        fclose($sock);
        $prefixStats[$prefix]['ok'] = ($prefixStats[$prefix]['ok'] ?? 0) + 1;
    } else {
        echo str_pad($name, 38) . " {$ip}: FAIL [{$errno}] {$errstr} ({$time}ms)\n";
        $prefixStats[$prefix]['fail'] = ($prefixStats[$prefix]['fail'] ?? 0) + 1;
    }
}

echo "\n=== SUMMARY BY PREFIX ===\n";
foreach ($prefixStats as $prefix => $stats) {
    $ok = $stats['ok'] ?? 0;
    $fail = $stats['fail'] ?? 0;
    echo str_pad($prefix, 25) . " OK: {$ok}  FAIL: {$fail}\n";
}

echo "\n=== INTERPRETATION HINT ===\n";
echo "If 104.21.0.0/20 and 172.67.0.0/16 are mostly FAIL but other CF prefixes are OK\n";
echo "  => ambilet.ro hosting blocks these specific prefixes outbound.\n";
echo "If 104.21.0.0/20 and 172.67.0.0/16 are mostly OK except for the core IPs\n";
echo "  => Cloudflare has an L4 block at the zone level for the source IP.\n";
