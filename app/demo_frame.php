<?php
/**
 * Bara de demo (ca la ThemeForest): pe host-urile de demo (demo-*.tics.ro), o
 * vizită directă primește o bară sus (înapoi la galerie, alte demo-uri,
 * desktop / tabletă / telefon, „Vreau acest site”) și site-ul într-un iframe
 * dedesubt. Așa bara nu intră peste meniul fix al niciunui template.
 *
 * Paginile din iframe sunt site-ul normal: se recunosc după Sec-Fetch-Dest:
 * iframe (sau ?_frame=1) și anunță părintele la fiecare navigare, ca adresa din
 * bara browserului să rămână cea reală.
 */

/** Cerere de pagină venită direct în browser (nu din iframe, nu API / fișier). */
function ts_demo_wants_frame(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || isset($_GET['_frame']) || isset($_GET['ts_shot'])) {
        return false;
    }
    // „Închide bara”: ?_nobar=1 o ține închisă cât e deschis browserul
    if (isset($_GET['_nobar'])) {
        setcookie('ts_nobar', '1', ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        return false;
    }
    if (!empty($_COOKIE['ts_nobar'])) {
        return false;
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (preg_match('#^/(api|assets|tpl)/|^/(robots\.txt|sitemap\.xml|favicon\.(svg|ico))$#', $path)) {
        return false;
    }
    $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? 'document'));
    return $dest === 'document';
}

/** Pagină din iframe-ul demo-ului: anunță părintele unde a ajuns. */
function ts_demo_inject_frame_script(): void {
    ob_start(function (string $html): string {
        $script = '<script>(function(){if(window.parent===window)return;'
            . 'var u=location.pathname+location.search.replace(/([?&])_frame=1&?/,"$1").replace(/[?&]$/,"")+location.hash;'
            . 'window.parent.postMessage({tsDemo:1,url:u,title:document.title},location.origin);})();</script>';
        $pos = strripos($html, '</body>');
        return $pos === false ? $html : substr($html, 0, $pos) . $script . substr($html, $pos);
    });
}

/** Captură pentru galerie (?ts_shot=1): fără bara de cookies și fără bandă de previzualizare. */
function ts_demo_inject_shot_style(): void {
    ob_start(function (string $html): string {
        $style = '<style>.cookiebar,[x-data="cookieBar"]{display:none!important}</style>'
            . '<script>try{localStorage.setItem("site_consent",JSON.stringify({v:1,analytics:false,marketing:false}))}catch(e){}</script>';
        $pos = stripos($html, '</head>');
        return $pos === false ? $html : substr($html, 0, $pos) . $style . substr($html, $pos);
    });
}

function ts_render_demo_frame(): void {
    $cfg = site();
    $demos = ts_fetch_demos();
    $host = ts_host();
    $current = null;
    foreach ($demos as $d) {
        if (parse_url($d['url'] ?? '', PHP_URL_HOST) === $host) {
            $current = $d;
        }
    }
    $title = $current['template_name'] ?? (manifest('name') ?: SITE_NAME);
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $src = $uri . (str_contains($uri, '?') ? '&' : '?') . '_frame=1';
    $galleryHost = trim(explode(',', DEMO_GALLERY_HOSTS)[0]);
    $gallery = 'https://' . $galleryHost . '/';

    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    ?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · demo Tixello</title>
<link rel="icon" href="/favicon.svg">
<script>
// Browser vechi fără Sec-Fetch-Dest: dacă bara ajunge în iframe, încarcă site-ul direct.
if (window.top !== window.self) { location.replace(<?= json_encode($src) ?>); }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<style>
    :root{--bar:#111214;--line:rgba(255,255,255,.09);--fg:#f2f2ef;--mute:#9b9c98;--accent:#f2542d}
    *{box-sizing:border-box}html,body{margin:0;height:100%;background:#1b1c1f}
    body{font:500 14px/1.2 Geist,system-ui,sans-serif;color:var(--fg);display:flex;flex-direction:column;overflow:hidden}
    .bar{height:56px;flex:none;display:flex;align-items:center;gap:12px;padding:0 12px 0 16px;background:var(--bar);border-bottom:1px solid var(--line);position:relative;z-index:2}
    .brand{font-weight:600;letter-spacing:-.02em;font-size:17px;color:var(--fg);text-decoration:none;margin-right:6px}
    .back{display:inline-flex;align-items:center;gap:6px;color:var(--mute);text-decoration:none;padding:8px 10px;border-radius:8px}
    .back:hover{color:var(--fg);background:rgba(255,255,255,.06)}
    .sep{width:1px;height:24px;background:var(--line)}
    .pick{position:relative}
    .pick button{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid var(--line);color:var(--fg);font:inherit;padding:8px 12px;border-radius:8px;cursor:pointer;max-width:42vw}
    .pick button span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .pick button:hover{background:rgba(255,255,255,.1)}
    .menu{position:absolute;top:calc(100% + 8px);left:0;min-width:300px;max-height:70vh;overflow:auto;background:#1a1b1e;border:1px solid var(--line);border-radius:12px;padding:6px;box-shadow:0 24px 60px rgba(0,0,0,.45);display:none}
    .menu.open{display:block}
    .menu a{display:flex;flex-direction:column;gap:3px;padding:10px 12px;border-radius:8px;color:var(--fg);text-decoration:none}
    .menu a small{color:var(--mute);font-weight:400}
    .menu a:hover,.menu a.on{background:rgba(255,255,255,.07)}
    .grow{flex:1}
    .devices{display:flex;gap:2px;padding:3px;border:1px solid var(--line);border-radius:9px}
    .devices button{width:34px;height:30px;border:0;border-radius:6px;background:transparent;color:var(--mute);font-size:18px;cursor:pointer;display:grid;place-items:center}
    .devices button:hover{color:var(--fg)}.devices button.on{background:rgba(255,255,255,.1);color:var(--fg)}
    .cta{display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#fff;text-decoration:none;font-weight:600;padding:10px 16px;border-radius:8px;white-space:nowrap;transition:transform .15s}
    .cta:active{transform:translateY(1px)}
    .close{width:38px;height:38px;border:0;border-radius:8px;background:transparent;color:var(--mute);font-size:20px;cursor:pointer;display:grid;place-items:center;text-decoration:none}
    .close:hover{color:var(--fg);background:rgba(255,255,255,.06)}
    .stage{flex:1;min-height:0;display:flex;justify-content:center;background:#1b1c1f}
    iframe{border:0;width:100%;height:100%;background:#fff;transition:width .35s cubic-bezier(.2,.7,.2,1)}
    .stage.tablet iframe{width:820px;max-width:100%;margin:16px 0 0;height:calc(100% - 16px);border-radius:14px 14px 0 0}
    .stage.mobile iframe{width:390px;max-width:100%;margin:16px 0 0;height:calc(100% - 16px);border-radius:22px 22px 0 0}
    @media (max-width:860px){.devices,.sep,.back span{display:none}.brand{display:none}.cta span{display:none}.cta{padding:10px 12px}}
    @media (prefers-reduced-motion:reduce){iframe{transition:none}}
</style>
</head>
<body>
<header class="bar">
    <a class="brand" href="<?= e($gallery) ?>">tixello</a>
    <a class="back" href="<?= e($gallery) ?>"><i class="ph ph-squares-four"></i><span>Toate template-urile</span></a>
    <div class="sep"></div>
    <div class="pick">
        <button type="button" id="pickBtn" aria-haspopup="true" aria-expanded="false"><span><?= e($title) ?></span><i class="ph ph-caret-down"></i></button>
        <?php if ($demos): ?>
        <div class="menu" id="pickMenu" role="menu">
            <?php foreach ($demos as $d): $on = parse_url($d['url'] ?? '', PHP_URL_HOST) === $host; ?>
            <a role="menuitem" href="<?= e($d['url']) ?>" class="<?= $on ? 'on' : '' ?>"><?= e($d['template_name'] ?? $d['title']) ?><small><?= e($d['category'] ?? '') ?></small></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="grow"></div>
    <div class="devices" role="group" aria-label="Previzualizare">
        <button type="button" class="on" data-device="desktop" aria-label="Desktop"><i class="ph ph-desktop"></i></button>
        <button type="button" data-device="tablet" aria-label="Tabletă"><i class="ph ph-device-tablet"></i></button>
        <button type="button" data-device="mobile" aria-label="Telefon"><i class="ph ph-device-mobile"></i></button>
    </div>
    <a class="cta" href="<?= e(DEMO_CTA_URL) ?>" target="_blank" rel="noopener"><i class="ph ph-rocket-launch"></i><span>Vreau acest site</span></a>
    <a class="close" id="closeBar" href="<?= e($uri . (str_contains($uri, '?') ? '&' : '?') . '_nobar=1') ?>" aria-label="Închide bara" title="Închide bara"><i class="ph ph-x"></i></a>
</header>
<main class="stage" id="stage">
    <iframe id="site" src="<?= e($src) ?>" title="<?= e($title) ?>" allow="payment; fullscreen"></iframe>
</main>
<script>
(function () {
    var btn = document.getElementById('pickBtn'), menu = document.getElementById('pickMenu');
    if (btn && menu) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); var o = menu.classList.toggle('open'); btn.setAttribute('aria-expanded', o); });
        document.addEventListener('click', function () { menu.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); });
    }
    var stage = document.getElementById('stage');
    document.querySelectorAll('[data-device]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('[data-device]').forEach(function (x) { x.classList.toggle('on', x === b); });
            stage.className = 'stage ' + b.getAttribute('data-device');
        });
    });
    // Adresa și titlul urmăresc pagina din iframe; „Închide bara” duce la pagina curentă.
    var close = document.getElementById('closeBar');
    window.addEventListener('message', function (ev) {
        if (ev.origin !== location.origin || !ev.data || !ev.data.tsDemo) { return; }
        try { history.replaceState(null, '', ev.data.url); } catch (e) {}
        if (ev.data.title) { document.title = ev.data.title + ' · demo Tixello'; }
        close.href = ev.data.url + (ev.data.url.indexOf('?') === -1 ? '?' : '&') + '_nobar=1';
    });
})();
</script>
</body>
</html>
<?php
    exit;
}
