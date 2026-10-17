<?php
/**
 * Site-ul cererii curente, după host: tenantul, template-ul și personalizarea,
 * din core (GET /api/tenant-site/config). Configurația publicată stă în cache
 * SITE_CONFIG_TTL secunde (și se servește veche dacă core nu răspunde);
 * previzualizarea (?preview=TOKEN, ținut apoi într-un cookie) nu se cachează.
 */

function ts_host(): string {
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    return preg_match('/^[a-z0-9.-]{1,253}$/', $host) ? $host : '';
}

/** Tokenul de previzualizare: din ?preview= (îl ține minte în cookie) sau din cookie. ?preview=0 îl șterge. */
function ts_preview_token(): string {
    if (isset($_GET['preview'])) {
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['preview']);
        $keep = $token !== '' && $token !== '0';
        setcookie('ts_preview', $keep ? $token : '', [
            'expires' => $keep ? 0 : time() - 3600,
            'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        return $keep ? $token : '';
    }
    return preg_replace('/[^A-Za-z0-9]/', '', (string) ($_COOKIE['ts_preview'] ?? ''));
}

/**
 * Configurația din core. Întoarce ['status' => 200|404|0, 'data' => array].
 */
function ts_fetch_site_config(string $host, string $preview): array {
    $url = API_BASE . '/tenant-site/config?' . http_build_query(array_filter(['host' => $host, 'preview' => $preview]));

    if ($preview !== '') {
        $r = api_request('GET', $url);
        return ['status' => $r['status'], 'data' => $r['raw'] ?? []];
    }

    $file = CACHE_ROOT . '/site_' . md5($host) . '.json';
    $cached = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (is_array($cached) && (time() - filemtime($file)) < SITE_CONFIG_TTL) {
        return $cached;
    }

    $r = api_request('GET', $url);
    if ($r['status'] === 200 || $r['status'] === 404) {
        $entry = ['status' => $r['status'], 'data' => $r['raw'] ?? []];
        @file_put_contents($file, json_encode($entry), LOCK_EX);
        return $entry;
    }

    // core indisponibil: ultima copie bună, oricât de veche
    return is_array($cached) ? $cached : ['status' => 0, 'data' => []];
}

/** Pornește site-ul: definește constantele folosite de pagini sau oprește cu o pagină de sistem. */
function ts_boot_site(): void {
    $host = ts_host();
    if ($host === '') {
        ts_system_page('unknown', 400);
    }
    if (ts_is_gallery_host($host)) {
        ts_render_gallery();
    }

    $preview = ts_preview_token();
    $res = ts_fetch_site_config($host, $preview);
    $cfg = $res['data'];

    if ($res['status'] !== 200 || empty($cfg['tenant_id'])) {
        $error = $cfg['error'] ?? '';
        if ($error === 'not_published') {
            ts_system_page('coming-soon', 503, ['name' => $cfg['name'] ?? '']);
        }
        if ($res['status'] === 0) {
            ts_system_page('unavailable', 503);
        }
        ts_system_page('unknown', 404);
    }

    $template = (string) ($cfg['template'] ?? '');
    $tplDir = TS_TEMPLATES . '/' . basename($template);
    $manifest = is_file($tplDir . '/template.json') ? json_decode((string) file_get_contents($tplDir . '/template.json'), true) : null;
    if (!is_array($manifest)) {
        ts_system_page('unavailable', 503);
    }

    $GLOBALS['TS_SITE'] = $cfg;
    $GLOBALS['TS_MANIFEST'] = $manifest;

    define('TENANT_ID', (int) $cfg['tenant_id']);
    define('TEMPLATE', basename($template));
    define('TEMPLATE_DIR', $tplDir);
    define('SITE_PREVIEW', !empty($cfg['preview']));
    define('SITE_NAME', (string) ($cfg['branding']['name'] ?? ''));
    define('SITE_LOGO_TEXT', (string) ($cfg['branding']['short_name'] ?? ''));
    define('SITE_CITY', (string) ($cfg['branding']['tagline'] ?? ''));

    // Cache-ul API separat pe tenant (și separat pentru previzualizare).
    define('CACHE_DIR', CACHE_ROOT . '/t' . TENANT_ID);
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0775, true);
    }
}

/** Citește o valoare din configurația site-ului cu cheie „a.b.c”. */
function site(?string $key = null, $default = null) {
    $v = $GLOBALS['TS_SITE'] ?? [];
    if ($key === null) {
        return $v;
    }
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            return $default;
        }
        $v = $v[$part];
    }
    return $v ?? $default;
}

function manifest(?string $key = null, $default = null) {
    $v = $GLOBALS['TS_MANIFEST'] ?? [];
    if ($key === null) {
        return $v;
    }
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            return $default;
        }
        $v = $v[$part];
    }
    return $v ?? $default;
}
