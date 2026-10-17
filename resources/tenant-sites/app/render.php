<?php
/**
 * Ajutoare de randare: layout-ul template-ului, linkuri, text cu mini-markup
 * și paginile construite din secțiuni.
 */

/** Calea unui fișier de layout al template-ului: head | header | footer. */
function ts_layout(string $name): string {
    return TEMPLATE_DIR . '/layout/' . basename($name) . '.php';
}

/**
 * Link către o pagină „standard” a template-ului (events, catalog, event,
 * subscriptions, artists, artist, contact). Paginile comune folosesc adresele
 * standard; router-ul redirecționează spre ale template-ului dacă diferă.
 */
function ts_link(string $key, array $params = []): string {
    $url = manifest('links.' . $key) ?? (TS_STANDARD_LINKS[$key] ?? '/');
    foreach ($params as $k => $v) {
        $url = str_replace('{' . $k . '}', rawurlencode((string) $v), $url);
    }
    return $url;
}

/**
 * Text scurt din panou: *cuvânt* → italic, **cuvânt** → evidențiat (.ts-hl),
 * rând nou → <br>. Totul escapat înainte.
 */
function ts_md(?string $text): string {
    $h = e((string) $text);
    $h = preg_replace('/\*\*(.+?)\*\*/u', '<span class="ts-hl">$1</span>', $h);
    $h = preg_replace('/\*(.+?)\*/u', '<span class="italic">$1</span>', $h);
    return nl2br($h, false);
}

/** Paragrafe din text (un rând gol între ele). */
function ts_paragraphs(?string $text, string $class = ''): string {
    $out = '';
    foreach (preg_split('/\R\s*\R/u', trim((string) $text)) as $p) {
        if (trim($p) !== '') {
            $out .= '<p' . ($class ? ' class="' . e($class) . '"' : '') . '>' . ts_md(trim($p)) . '</p>';
        }
    }
    return $out;
}

/** Link sigur dintr-un câmp din panou (cale internă sau http/https/mailto/tel). */
function ts_href(?string $url, string $fallback = '#'): string {
    $url = trim((string) $url);
    if ($url === '') {
        return $fallback;
    }
    if ($url[0] === '/' || preg_match('#^(https?:|mailto:|tel:)#i', $url)) {
        return $url;
    }
    return '/' . ltrim($url, '/');
}

/** Randează o secțiune a template-ului (templates/X/sections/{type}.php primește $s). */
function ts_section(string $type, array $s): void {
    $file = TEMPLATE_DIR . '/sections/' . basename($type) . '.php';
    if (is_file($file)) {
        (static function () use ($file, $s) { include $file; })();
    }
}

/** O pagină construită din secțiuni (Acasă, Despre, Contact ...). */
function ts_render_sections_page(string $key): void {
    $page = site('pages.' . $key, []);
    $title = trim((string) ($page['title'] ?? ''));
    $title = $title !== '' ? t($title) : '';
    $pageTitle = $key === 'home'
        ? SITE_NAME . ($title !== '' ? ' — ' . $title : '')
        : ($title !== '' ? $title . ' — ' : '') . SITE_NAME;
    $pageDescription = site('branding.description') ?: null;
    $activeNav = $page['path'] ?? '';

    include ts_layout('head');
    include ts_layout('header');
    $defaults = (array) manifest('pages.' . $key . '.sections', []);
    foreach (array_values($page['sections'] ?? []) as $i => $section) {
        $type = (string) ($section['type'] ?? '');
        // Textele implicite ale secțiunii, în limba vizitatorului (vezi i18n.php)
        $def = $defaults[$i] ?? null;
        if (!$def || ($def['type'] ?? '') !== $type) {
            $def = null;
            foreach ($defaults as $d) {
                if (($d['type'] ?? '') === $type) { $def = $d; break; }
            }
        }
        ts_section($type, ts_section_locale((array) ($section['data'] ?? []), $def['data'] ?? null));
    }
    include ts_layout('footer');
}

/** 404 în aspectul site-ului. */
function ts_not_found(): void {
    http_response_code(404);
    $pageTitle = 'Pagină negăsită — ' . SITE_NAME;
    include ts_layout('head');
    include ts_layout('header');
    echo '<section class="min-h-[70vh] flex items-center justify-center px-4 pt-32 pb-20 text-center"><div>'
        . '<p class="font-display text-7xl text-primary mb-4">404</p>'
        . '<h1 class="font-display text-3xl mb-4">Pagina nu există</h1>'
        . '<p class="text-fg/60 mb-8">Poate a fost mutată sau linkul este greșit.</p>'
        . '<a href="/" class="inline-block rounded bg-primary px-6 py-3 font-semibold text-on-primary">Înapoi acasă</a>'
        . '</div></section>';
    include ts_layout('footer');
    exit;
}

/**
 * Pagini de sistem, înainte să știm al cui e site-ul (host necunoscut, site
 * nepublicat, core indisponibil). Neutre, fără tema tenantului.
 */
function ts_system_page(string $kind, int $status, array $vars = []): void {
    http_response_code($status);
    header('Cache-Control: no-store');
    if ($status === 503) {
        header('Retry-After: 300');
    }
    $name = trim((string) ($vars['name'] ?? ''));
    [$title, $text] = match ($kind) {
        'coming-soon' => [$name !== '' ? $name : 'În curând', 'Site-ul este în pregătire. Revino în curând.'],
        'unavailable' => ['Revenim imediat', 'Site-ul nu poate fi afișat acum. Încearcă din nou în câteva minute.'],
        default => ['Site inexistent', 'La această adresă nu există încă un site.'],
    };
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f0f10;color:#f4f1ea;font:16px/1.6 system-ui,sans-serif;text-align:center;padding:24px}'
        . 'h1{font-size:2rem;margin:0 0 .5rem}p{color:#a8a39b;margin:0}small{display:block;margin-top:2.5rem;color:#6b675f;font-size:.8rem}a{color:inherit}</style></head>'
        . '<body><main><h1>' . e($title) . '</h1><p>' . e($text) . '</p><small>Ticketing by <a href="https://tixello.ro">Tixello</a></small></main></body></html>';
    exit;
}
