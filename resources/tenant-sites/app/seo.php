<?php
/**
 * SEO comun tuturor template-urilor: adresa canonică, variantele de limbă (hreflang),
 * Open Graph + Twitter, robots, datele structurate (schema.org JSON-LD) și sitemap.xml.
 *
 * În <head>, după <title> și meta description:
 *   <?= ts_seo_head(['title' => $pageTitle, 'description' => $pageDescription, 'image' => $pageImage, 'schema' => $pageSchema ?? []]) ?>
 *
 * Paginile de eveniment adaugă în $pageSchema rezultatul lui ts_event_schema($ev).
 * Un template își poate adăuga adresele în sitemap din templates/{t}/sitemap.php
 * (fișier care întoarce o listă de căi, ex. ['/gala/furia-14']).
 */

/** Paginile comenzii și ale contului: utile doar vizitatorului, nu motoarelor de căutare. */
const TS_NOINDEX_PATHS = ['/cos', '/finalizare', '/confirmare', '/autentificare', '/inregistrare', '/recuperare-parola', '/parola-noua', '/cont'];

/** https://host — adresa de bază a site-ului curent. */
function ts_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || !preg_match('/^(localhost|127\.|[a-z0-9.-]+\.localhost$)/', ts_host());
    return ($https ? 'https' : 'http') . '://' . ts_host();
}

/** Adresă absolută dintr-o cale sau dintr-un URL deja complet. */
function ts_abs_url(?string $url): ?string {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }
    return ts_base_url() . '/' . ltrim($url, '/');
}

/** Calea curentă, fără parametrii care nu schimbă conținutul. */
function ts_current_path(): string {
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return '/' . trim($path, '/');
}

/** Adresa unei pagini într-o limbă: limba implicită fără parametru, celelalte cu ?lang=. */
function ts_lang_abs_url(string $lang, ?string $path = null): string {
    $path = $path ?? ts_current_path();
    $default = ts_languages()[0];
    return ts_base_url() . ($path === '/' ? '/' : $path) . ($lang === $default ? '' : '?lang=' . $lang);
}

/** Site-ul nu se indexează: previzualizare, demo, sau pagini ale comenzii/contului. */
function ts_noindex(): bool {
    if (SITE_PREVIEW || site('demo')) {
        return true;
    }
    if (http_response_code() >= 400) {
        return true;
    }
    $path = ts_current_path();
    foreach (TS_NOINDEX_PATHS as $p) {
        if ($path === $p || str_starts_with($path, $p . '/')) {
            return true;
        }
    }
    return false;
}

/** Textul simplu dintr-un HTML, scurtat la $max caractere, pentru descrieri. */
function ts_plain(?string $html, int $max = 160): string {
    $s = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($s) <= $max) {
        return $s;
    }
    $cut = mb_substr($s, 0, $max - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, ' ,.;:') . '…';
}

/** Organizația (tenantul) în schema.org; apare pe fiecare pagină. */
function ts_org_schema(): array {
    $f = (array) site('footer', []);
    $same = array_values(array_filter(array_map(fn ($k) => trim((string) ($f[$k] ?? '')), ['facebook', 'instagram', 'tiktok', 'youtube']), fn ($u) => preg_match('#^https?://#i', $u)));
    $org = [
        '@type' => 'Organization',
        '@id' => ts_base_url() . '/#org',
        'name' => SITE_NAME,
        'url' => ts_base_url() . '/',
    ];
    if ($logo = ts_abs_url(site('branding.logo'))) {
        $org['logo'] = $logo;
    }
    if (!empty($f['email'])) {
        $org['email'] = (string) $f['email'];
    }
    if (!empty($f['phone'])) {
        $org['telephone'] = (string) $f['phone'];
    }
    if ($same) {
        $org['sameAs'] = $same;
    }
    return $org;
}

/** Ora de început cu fusul orar al României, din data (ISO) și ora (HH:MM) ale evenimentului. */
function ts_iso_datetime(?string $date, ?string $time = null): ?string {
    $date = substr((string) $date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    try {
        $tz = new DateTimeZone('Europe/Bucharest');
        if ($time && preg_match('/^\d{1,2}:\d{2}/', $time)) {
            return (new DateTimeImmutable($date . ' ' . substr($time, 0, 5), $tz))->format('c');
        }
        return $date;
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Un eveniment în schema.org (Event), din forma API-ului tenant-client: nume, date,
 * locație, imagine, organizator, artiști și oferta de bilete (prețuri, disponibilitate).
 * $o: url (adresa paginii), type (MusicEvent, SportsEvent, TheaterEvent, Festival …),
 * performers ([nume …] sau [['name' => , 'type' => 'Person|PerformingGroup']]).
 */
function ts_event_schema(array $ev, array $o = []): array {
    $url = ts_abs_url($o['url'] ?? (isset($ev['slug']) ? ts_link('event', ['slug' => $ev['slug']]) : ts_current_path()));
    $start = ts_iso_datetime($ev['start_date'] ?? $ev['event_date'] ?? null, $ev['start_time'] ?? null);
    $s = [
        '@type' => $o['type'] ?? 'Event',
        'name' => (string) ($ev['title'] ?? ''),
        'url' => $url,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'organizer' => ['@id' => ts_base_url() . '/#org'],
    ];
    if ($start) {
        $s['startDate'] = $start;
    }
    if ($end = ts_iso_datetime($ev['end_date'] ?? null, $ev['end_time'] ?? null)) {
        $s['endDate'] = $end;
    }
    if (!empty($ev['cancelled_at']) || ($ev['status'] ?? '') === 'cancelled' || !empty($ev['is_cancelled'])) {
        $s['eventStatus'] = 'https://schema.org/EventCancelled';
    } elseif (!empty($ev['postponed_at']) || !empty($ev['is_postponed'])) {
        $s['eventStatus'] = 'https://schema.org/EventPostponed';
    }
    $desc = ts_plain($ev['short_description'] ?? '') ?: ts_plain($ev['description'] ?? '', 300);
    if ($desc !== '') {
        $s['description'] = $desc;
    }
    $images = array_values(array_unique(array_filter(array_map('ts_abs_url', [$ev['poster_url'] ?? null, $ev['hero_image_url'] ?? null, $ev['image'] ?? null]))));
    if ($images) {
        $s['image'] = $images;
    }
    $v = $ev['venue'] ?? null;
    if (is_array($v) && !empty($v['name'])) {
        $place = ['@type' => 'Place', 'name' => (string) $v['name']];
        $addr = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $v['address'] ?? null, 'addressLocality' => $v['city'] ?? null, 'addressCountry' => $v['country'] ?? 'RO']);
        $place['address'] = $addr;
        if (!empty($v['latitude']) && !empty($v['longitude'])) {
            $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $v['latitude'], 'longitude' => (float) $v['longitude']];
        }
        $s['location'] = $place;
    }
    $performers = [];
    foreach ((array) ($o['performers'] ?? []) as $p) {
        $p = is_array($p) ? $p : ['name' => $p];
        if (!empty($p['name']) && $p['name'] !== 'TBA') {
            $performers[] = ['@type' => $p['type'] ?? 'Person', 'name' => (string) $p['name']] + (!empty($p['url']) ? ['url' => ts_abs_url($p['url'])] : []);
        }
    }
    if ($performers) {
        $s['performer'] = $performers;
    }
    $types = array_values(array_filter((array) ($ev['ticket_types'] ?? []), fn ($tt) => ($tt['status'] ?? 'active') === 'active' && empty($tt['is_parking'])));
    if ($types) {
        $prices = array_map(fn ($tt) => (float) (($tt['sale_price'] ?? null) ?: ($tt['price'] ?? 0)), $types);
        $available = array_sum(array_map(fn ($tt) => max(0, (int) ($tt['available'] ?? 0)), $types)) > 0;
        $past = $start && strtotime(substr($start, 0, 10) . ' 23:59') < time();
        $s['offers'] = [
            '@type' => 'AggregateOffer',
            'url' => $url,
            'priceCurrency' => (string) ($types[0]['currency'] ?? 'RON'),
            'lowPrice' => min($prices),
            'highPrice' => max($prices),
            'offerCount' => count($types),
            'availability' => 'https://schema.org/' . ($past ? 'Discontinued' : ($available ? 'InStock' : 'SoldOut')),
        ];
    }
    return $s;
}

/** Firul de navigare (BreadcrumbList) din perechi [nume, cale]. */
function ts_breadcrumb_schema(array $items): array {
    $list = [];
    foreach (array_values($items) as $i => [$name, $path]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string) $name, 'item' => ts_abs_url($path)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/**
 * Etichetele SEO din <head> (fără <title> și meta description, pe care le scrie layout-ul):
 * canonical, hreflang (+x-default), robots, Open Graph, Twitter și JSON-LD.
 */
function ts_seo_head(array $o = []): string {
    $title = (string) ($o['title'] ?? SITE_NAME);
    $desc = ts_plain($o['description'] ?? '', 200);
    $image = ts_abs_url($o['image'] ?? null) ?: ts_abs_url(site('branding.logo'));
    $path = ts_current_path();
    $langs = ts_languages();
    $canonical = $o['canonical'] ?? ts_lang_abs_url(ts_locale(), $path);
    $noindex = ts_noindex() || !empty($o['noindex']);
    $ogLocale = ['ro' => 'ro_RO', 'en' => 'en_GB'];

    $h = [];
    $h[] = '<meta name="robots" content="' . ($noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large,max-snippet:-1') . '">';
    if (!$noindex) {
        $h[] = '<link rel="canonical" href="' . e($canonical) . '">';
        if (count($langs) > 1) {
            foreach ($langs as $l) {
                $h[] = '<link rel="alternate" hreflang="' . $l . '" href="' . e(ts_lang_abs_url($l, $path)) . '">';
            }
            $h[] = '<link rel="alternate" hreflang="x-default" href="' . e(ts_lang_abs_url($langs[0], $path)) . '">';
        }
    }
    $h[] = '<meta property="og:type" content="' . e($o['og_type'] ?? 'website') . '">';
    $h[] = '<meta property="og:site_name" content="' . e(SITE_NAME) . '">';
    $h[] = '<meta property="og:title" content="' . e($title) . '">';
    if ($desc !== '') {
        $h[] = '<meta property="og:description" content="' . e($desc) . '">';
    }
    $h[] = '<meta property="og:url" content="' . e($canonical) . '">';
    $h[] = '<meta property="og:locale" content="' . ($ogLocale[ts_locale()] ?? 'ro_RO') . '">';
    foreach ($langs as $l) {
        if ($l !== ts_locale()) {
            $h[] = '<meta property="og:locale:alternate" content="' . ($ogLocale[$l] ?? $l) . '">';
        }
    }
    if ($image) {
        $h[] = '<meta property="og:image" content="' . e($image) . '">';
        $h[] = '<meta property="og:image:alt" content="' . e($title) . '">';
    }
    $h[] = '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">';
    $h[] = '<meta name="twitter:title" content="' . e($title) . '">';
    if ($desc !== '') {
        $h[] = '<meta name="twitter:description" content="' . e($desc) . '">';
    }
    if ($image) {
        $h[] = '<meta name="twitter:image" content="' . e($image) . '">';
    }

    // Date structurate: organizația + site-ul pe toate paginile, plus ce adaugă pagina
    $graph = [ts_org_schema()];
    if ($path === '/') {
        $graph[] = ['@type' => 'WebSite', '@id' => ts_base_url() . '/#website', 'url' => ts_base_url() . '/', 'name' => SITE_NAME,
            'inLanguage' => $langs, 'publisher' => ['@id' => ts_base_url() . '/#org']];
    }
    foreach ((array) ($o['schema'] ?? []) as $node) {
        if (is_array($node) && $node) {
            $graph[] = $node;
        }
    }
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    $h[] = '<script type="application/ld+json">' . $json . '</script>';
    return implode("\n    ", $h);
}

/** /sitemap.xml: paginile site-ului, paginile fixe ale template-ului și evenimentele, în toate limbile. */
function ts_sitemap(): void {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $paths = ['/'];
    foreach ((array) site('pages', []) as $page) {
        if (!empty($page['path'])) {
            $paths[] = (string) $page['path'];
        }
    }
    foreach (array_keys((array) manifest('routes', [])) as $route) {
        if (!str_contains($route, '{')) {
            $paths[] = $route;
        }
    }
    // Evenimentele viitoare, dacă template-ul are pagină de eveniment
    if (manifest('links.event')) {
        $resp = api_get('/tenant-client/events', ['per_page' => 100]);
        foreach (tc_events($resp) as $ev) {
            if (!empty($ev['slug'])) {
                $paths[] = ts_link('event', ['slug' => $ev['slug']]);
            }
        }
    }
    // Adresele proprii ale template-ului (ex. galele trecute, fișele de artist)
    if (is_file(TEMPLATE_DIR . '/sitemap.php')) {
        $extra = (static fn () => include TEMPLATE_DIR . '/sitemap.php')();
        foreach ((array) $extra as $p) {
            $paths[] = (string) $p;
        }
    }
    foreach (['/termeni', '/confidentialitate'] as $p) {
        $paths[] = $p;
    }
    $paths = array_values(array_unique(array_filter($paths, fn ($p) => $p !== '' && $p[0] === '/' && !str_contains($p, '#'))));
    $langs = ts_languages();

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    if (!(SITE_PREVIEW || site('demo'))) {
        foreach ($paths as $p) {
            foreach ($langs as $l) {
                echo '  <url><loc>' . e(ts_lang_abs_url($l, $p)) . '</loc>';
                if (count($langs) > 1) {
                    foreach ($langs as $alt) {
                        echo '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . e(ts_lang_abs_url($alt, $p)) . '"/>';
                    }
                }
                echo '<changefreq>' . ($p === '/' ? 'daily' : 'weekly') . '</changefreq><priority>' . ($p === '/' ? '1.0' : '0.7') . '</priority></url>' . "\n";
            }
        }
    }
    echo '</urlset>';
    exit;
}
