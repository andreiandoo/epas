<?php
/**
 * "Înapoi la <site-ul operatorului>" after a purchase made through the booking widget (embed/locatie.php).
 *
 * The widget page sees which site frames it (the Referer of the iframe request). When that site is one the operator
 * allowed (the frame-ancestors list), it gets a signed token with the address to return to; the token travels with
 * the cart to /finalizare (#bo-return=…), cart.js keeps it for the tab, and the thank-you page asks
 * api/embed-return.php whether it is genuine before showing the button. The signature (HMAC with a key derived from
 * the server-only API key) means a crafted link cannot make the page offer a way "back" to a site of someone else's
 * choosing. Tokens are valid for 7 days.
 *
 * Requires config.php.
 */

const BO_RETURN_TTL = 604800;

function bo_return_b64(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function bo_return_unb64(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/'), true);
}

function bo_return_key(): string
{
    return hash('sha256', 'bo-embed-return|' . API_KEY, true);
}

/** True when $origin (scheme://host[:port]) is on the list, "https://*.site.ro" covering its subdomains. */
function bo_return_allowed(string $origin, array $allowed): bool
{
    foreach ($allowed as $a) {
        if ($a === $origin) {
            return true;
        }
        if (preg_match('#^(https?)://\*\.(.+)$#', $a, $m)) {
            $scheme = parse_url($origin, PHP_URL_SCHEME);
            $host = (string) parse_url($origin, PHP_URL_HOST);
            if ($scheme === $m[1] && substr($host, -strlen('.' . $m[2])) === '.' . $m[2]) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Token for the page framing the widget, or null (no Referer, bilete.online itself, or a site the operator did not
 * allow). Only the address without query or fragment is kept.
 */
function bo_return_token(array $allowedOrigins): ?string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $p = $ref !== '' ? parse_url($ref) : false;
    if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['http', 'https'], true)) {
        return null;
    }
    $origin = strtolower($p['scheme'] . '://' . $p['host']) . (!empty($p['port']) ? ':' . (int) $p['port'] : '');
    if ($origin === strtolower(rtrim(SITE_URL, '/')) || !bo_return_allowed($origin, $allowedOrigins)) {
        return null;
    }
    $path = (string) ($p['path'] ?? '/');
    if ($path === '' || strlen($path) > 300 || !preg_match('#^/[^\s<>"]*$#', $path)) {
        $path = '/';
    }
    $payload = bo_return_b64(json_encode(['u' => $origin . $path, 't' => time()], JSON_UNESCAPED_SLASHES));
    return $payload . '.' . bo_return_b64(hash_hmac('sha256', $payload, bo_return_key(), true));
}

/** ['url' => …, 'name' => host without www.] for a genuine, unexpired token; null otherwise. */
function bo_return_verify(string $token): ?array
{
    if (strlen($token) > 800 || !preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $token, $m)) {
        return null;
    }
    $expected = bo_return_b64(hash_hmac('sha256', $m[1], bo_return_key(), true));
    if (!hash_equals($expected, $m[2])) {
        return null;
    }
    $data = json_decode(bo_return_unb64($m[1]), true);
    $url = is_array($data) ? (string) ($data['u'] ?? '') : '';
    $t = is_array($data) ? (int) ($data['t'] ?? 0) : 0;
    if ($url === '' || $t <= 0 || time() - $t > BO_RETURN_TTL || !preg_match('#^https?://#', $url)) {
        return null;
    }
    $host = (string) parse_url($url, PHP_URL_HOST);
    return ['url' => $url, 'name' => preg_replace('/^www\./', '', $host)];
}
