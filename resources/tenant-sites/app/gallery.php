<?php
/**
 * Galeria demo-urilor de template (demo.tics.ro): linkul de trimis prospecților.
 * Lista vine din core (GET /api/tenant-site/demos, cache SITE_CONFIG_TTL);
 * demo-urile se administrează în core, la Tenants → Demo-uri site.
 * Imaginile: templates/{slug}/assets/preview.jpg (captura paginii întregi, 1440 lățime).
 */

function ts_is_gallery_host(string $host): bool {
    return in_array($host, array_map('trim', explode(',', DEMO_GALLERY_HOSTS)), true);
}

function ts_fetch_demos(): array {
    $file = CACHE_ROOT . '/demos.json';
    $cached = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (is_array($cached) && (time() - filemtime($file)) < SITE_CONFIG_TTL) {
        return $cached;
    }
    $r = api_request('GET', API_BASE . '/tenant-site/demos');
    if ($r['success'] && is_array($r['raw']['demos'] ?? null)) {
        @file_put_contents($file, json_encode($r['raw']['demos']), LOCK_EX);
        return $r['raw']['demos'];
    }
    return is_array($cached) ? $cached : [];
}

/** Captura demo-ului: fișierul din template (servit din public/tpl) sau cea trimisă de core. */
function ts_demo_preview(array $d): ?string {
    $slug = basename((string) ($d['template'] ?? ''));
    foreach (['preview.webp', 'preview.jpg'] as $f) {
        if ($slug !== '' && is_file(TS_TEMPLATES . '/' . $slug . '/assets/' . $f)) {
            return '/tpl/' . $slug . '/' . $f . '?v=' . filemtime(TS_TEMPLATES . '/' . $slug . '/assets/' . $f);
        }
    }
    return $d['preview_image'] ?? null;
}

function ts_render_gallery(): void {
    $demos = ts_fetch_demos();
    $cats = [];
    foreach ($demos as $d) {
        $c = $d['category'] ?? 'Altele';
        $cats[$c] = ($cats[$c] ?? 0) + 1;
    }
    $heroShots = array_values(array_filter(array_map('ts_demo_preview', $demos)));
    $cta = DEMO_CTA_URL;

    header('Cache-Control: public, max-age=60');
    ?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Template-uri de site cu bilete · Tixello</title>
<meta name="description" content="Site-uri complete pentru teatre, competiții, festivaluri și locații: program, pagini de eveniment, coș, plată și cont de client. Vezi fiecare demo live.">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<?php if ($heroShots): ?><link rel="preload" as="image" href="<?= e($heroShots[0]) ?>"><?php endif; ?>
<style>
    :root{
        --bg:#f6f6f4; --surface:#ffffff; --ink:#151619; --ink-2:#55575c; --ink-3:#8b8d91; --line:#e3e3df;
        --accent:#e8492a; --accent-ink:#ffffff; --shadow:30 32 36; --r:14px;
    }
    @media (prefers-color-scheme:dark){
        :root{ --bg:#111214; --surface:#18191c; --ink:#f1f1ee; --ink-2:#b4b5b1; --ink-3:#86878a; --line:#2a2b2f; --shadow:0 0 0; }
    }
    *{box-sizing:border-box} html{scroll-behavior:smooth}
    body{margin:0;background:var(--bg);color:var(--ink);font:400 16px/1.55 Geist,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
    a{color:inherit}
    .wrap{max-width:1280px;margin:0 auto;padding:0 24px}
    /* nav */
    .nav{height:68px;display:flex;align-items:center;gap:28px}
    .logo{font-weight:700;font-size:22px;letter-spacing:-.04em;text-decoration:none}
    .nav-links{display:flex;gap:24px;margin-left:auto;align-items:center}
    .nav-links a{text-decoration:none;color:var(--ink-2);font-weight:500}
    .nav-links a:hover{color:var(--ink)}
    .btn{display:inline-flex;align-items:center;gap:8px;font-weight:600;text-decoration:none;border-radius:10px;padding:12px 18px;white-space:nowrap;transition:transform .15s,background .2s}
    .btn:active{transform:translateY(1px)}
    .btn-accent{background:var(--accent);color:var(--accent-ink)}
    .btn-accent:hover{background:#d63f21}
    .btn-line{border:1px solid var(--line);background:var(--surface);color:var(--ink)}
    .btn-line:hover{border-color:var(--ink-3)}
    /* hero */
    .hero{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:56px;align-items:center;padding:56px 0 88px}
    .hero h1{font-size:clamp(40px,5.2vw,68px);line-height:1.02;letter-spacing:-.045em;font-weight:700;margin:0 0 22px;max-width:11ch}
    .hero p{font-size:19px;color:var(--ink-2);max-width:42ch;margin:0 0 32px}
    .hero-cta{display:flex;gap:12px;flex-wrap:wrap}
    .shots{position:relative;aspect-ratio:16/12}
    .shot{position:absolute;border-radius:var(--r);overflow:hidden;background:var(--surface);border:1px solid var(--line);box-shadow:0 30px 80px -30px rgb(var(--shadow) / .35)}
    .shot .chrome{height:28px;display:flex;align-items:center;gap:6px;padding:0 12px;border-bottom:1px solid var(--line);background:var(--surface)}
    .shot .chrome i{width:9px;height:9px;border-radius:50%;background:var(--line);display:block}
    .shot img{display:block;width:100%;height:calc(100% - 28px);object-fit:cover;object-position:top}
    .shot-a{inset:0 0 12% 10%}
    .shot-b{left:0;bottom:0;width:46%;height:56%}
    .shot-ph{position:absolute;inset:0;border-radius:var(--r);background:linear-gradient(135deg,var(--surface),var(--line))}
    /* lista */
    .list-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:20px;padding-top:24px;border-top:1px solid var(--line)}
    .list-head h2{font-size:clamp(28px,3vw,40px);letter-spacing:-.035em;margin:0;font-weight:700}
    .chips{display:flex;flex-wrap:wrap;gap:8px}
    .chip{border:1px solid var(--line);background:transparent;color:var(--ink-2);font:500 14px Geist,system-ui,sans-serif;padding:8px 14px;border-radius:999px;cursor:pointer}
    .chip:hover{color:var(--ink);border-color:var(--ink-3)}
    .chip.on{background:var(--ink);border-color:var(--ink);color:var(--bg)}
    .chip small{color:inherit;opacity:.6;margin-left:6px}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:40px 32px;padding:36px 0 96px}
    .card{display:block;text-decoration:none}
    .frame{position:relative;aspect-ratio:16/10;border-radius:var(--r);overflow:hidden;border:1px solid var(--line);background:var(--surface);box-shadow:0 20px 50px -30px rgb(var(--shadow) / .3)}
    .frame img{position:absolute;inset:0 0 auto 0;width:100%;height:auto;transition:transform 6s cubic-bezier(.4,0,.2,1);will-change:transform}
    .card:hover .frame img,.card:focus-visible .frame img{transform:translateY(calc(-100% + var(--frame-h,400px)))}
    .frame-ph{position:absolute;inset:0;display:flex;align-items:flex-end;padding:28px}
    .frame-ph b{font-size:34px;letter-spacing:-.03em;line-height:1.05}
    .meta{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 2px 0}
    .meta h3{margin:0;font-size:20px;letter-spacing:-.02em;font-weight:600}
    .meta p{margin:6px 0 0;color:var(--ink-2);font-size:15px;max-width:52ch}
    .meta .cat{color:var(--ink-3);font-size:14px;margin-top:2px}
    .open{display:inline-flex;align-items:center;gap:6px;font-weight:600;color:var(--accent);white-space:nowrap;padding-top:2px}
    .swatches{display:flex;gap:5px;margin-top:12px}
    .swatches span{width:16px;height:16px;border-radius:50%;border:1px solid var(--line)}
    /* inclus */
    .incl{display:grid;grid-template-columns:minmax(0,4fr) minmax(0,8fr);gap:56px;padding:88px 0;border-top:1px solid var(--line)}
    .incl h2{font-size:clamp(28px,3vw,40px);letter-spacing:-.035em;margin:0;line-height:1.08;font-weight:700}
    .feats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:30px 36px}
    .feat i{font-size:26px;color:var(--accent)}
    .feat h3{margin:12px 0 6px;font-size:17px;font-weight:600;letter-spacing:-.01em}
    .feat p{margin:0;color:var(--ink-2);font-size:15px}
    .end{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:24px;padding:48px;border-radius:calc(var(--r) + 6px);background:var(--ink);color:var(--bg);margin-bottom:64px}
    .end h2{margin:0;font-size:clamp(26px,2.6vw,34px);letter-spacing:-.03em;font-weight:700}
    .end p{margin:8px 0 0;opacity:.7;max-width:48ch}
    footer{padding:28px 0 48px;color:var(--ink-3);font-size:14px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
    /* apariție */
    .rv{opacity:0;transform:translateY(18px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1)}
    .rv.in{opacity:1;transform:none}
    @media (max-width:1023px){
        .hero{grid-template-columns:1fr;gap:40px;padding-top:32px}
        .grid{grid-template-columns:1fr}
        .incl{grid-template-columns:1fr;gap:32px}
    }
    @media (max-width:640px){
        .wrap{padding:0 16px}
        .nav-links a.hide-sm{display:none}
        .feats{grid-template-columns:1fr}
        .end{padding:28px}
    }
    @media (prefers-reduced-motion:reduce){
        html{scroll-behavior:auto}
        .rv{opacity:1;transform:none;transition:none}
        .frame img{transition:none}.card:hover .frame img{transform:none}
    }
</style>
</head>
<body>
<div class="wrap">
    <nav class="nav">
        <a href="/" class="logo">tixello</a>
        <div class="nav-links">
            <a href="#template-uri" class="hide-sm">Template-uri</a>
            <a href="#inclus" class="hide-sm">Ce primești</a>
            <a href="<?= e($cta) ?>" class="btn btn-accent" target="_blank" rel="noopener">Vreau un site</a>
        </div>
    </nav>

    <section class="hero">
        <div>
            <h1 class="rv">Site-ul tău de bilete, gata de lansat.</h1>
            <p class="rv">Alege un template, pune-ți logo-ul și culorile. Vânzarea de bilete merge din prima zi.</p>
            <div class="hero-cta rv">
                <a href="#template-uri" class="btn btn-accent">Vezi template-urile <i class="ph ph-arrow-down"></i></a>
            </div>
        </div>
        <div class="shots rv" aria-hidden="true">
            <?php if (count($heroShots) >= 2): ?>
                <div class="shot shot-a"><div class="chrome"><i></i><i></i><i></i></div><img src="<?= e($heroShots[0]) ?>" alt=""></div>
                <div class="shot shot-b"><div class="chrome"><i></i><i></i><i></i></div><img src="<?= e($heroShots[1]) ?>" alt=""></div>
            <?php elseif ($heroShots): ?>
                <div class="shot" style="inset:0"><div class="chrome"><i></i><i></i><i></i></div><img src="<?= e($heroShots[0]) ?>" alt=""></div>
            <?php else: ?>
                <div class="shot-ph"></div>
            <?php endif; ?>
        </div>
    </section>

    <section id="template-uri">
        <div class="list-head">
            <h2>Template-uri</h2>
            <?php if (count($cats) > 1): ?>
            <div class="chips" role="group" aria-label="Filtrează după categorie">
                <button type="button" class="chip on" data-cat="">Toate<small><?= count($demos) ?></small></button>
                <?php foreach ($cats as $c => $n): ?><button type="button" class="chip" data-cat="<?= e($c) ?>"><?= e($c) ?><small><?= $n ?></small></button><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!$demos): ?>
            <p style="padding:64px 0;color:var(--ink-2)">Demo-urile sunt în pregătire. Revino în curând.</p>
        <?php endif; ?>
        <div class="grid">
            <?php foreach ($demos as $d):
                $shot = ts_demo_preview($d);
                $c = (array) ($d['colors'] ?? []);
                $hex = fn ($k, $f) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($c[$k] ?? '')) ? $c[$k] : $f;
            ?>
            <a class="card rv" href="<?= e($d['url']) ?>" data-cat="<?= e($d['category'] ?? 'Altele') ?>">
                <div class="frame">
                    <?php if ($shot): ?>
                        <img src="<?= e($shot) ?>" alt="Pagina principală a template-ului <?= e($d['template_name'] ?? $d['title']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="frame-ph" style="background:<?= e($hex('canvas', '#222')) ?>;color:<?= e($hex('fg', '#fff')) ?>"><b><?= e($d['template_name'] ?? $d['title']) ?></b></div>
                    <?php endif; ?>
                </div>
                <div class="meta">
                    <div>
                        <h3><?= e($d['template_name'] ?? $d['title']) ?></h3>
                        <div class="cat"><?= e($d['category'] ?? '') ?></div>
                        <?php if (!empty($d['description'])): ?><p><?= e($d['description']) ?></p><?php endif; ?>
                        <div class="swatches" aria-hidden="true"><?php foreach (['canvas', 'primary', 'accent'] as $k): ?><span style="background:<?= e($hex($k, '#ccc')) ?>"></span><?php endforeach; ?></div>
                    </div>
                    <span class="open">Vezi demo <i class="ph ph-arrow-up-right"></i></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="incl" id="inclus">
        <h2 class="rv">Inclus în fiecare template.</h2>
        <div class="feats">
            <div class="feat rv"><i class="ph ph-ticket"></i><h3>Bilete pe tipuri sau pe locuri</h3><p>Acces general, hartă de sală cu locuri numerotate, abonamente.</p></div>
            <div class="feat rv"><i class="ph ph-credit-card"></i><h3>Coș și plată cu cardul</h3><p>Coduri de reducere, rezervare pe timp limitat, bilete PDF cu cod QR.</p></div>
            <div class="feat rv"><i class="ph ph-user-circle"></i><h3>Cont de client</h3><p>Biletele, comenzile, favoritele și punctele de fidelitate, într-un loc.</p></div>
            <div class="feat rv"><i class="ph ph-paint-brush"></i><h3>Personalizat pe brandul tău</h3><p>Logo, culori, fonturi, meniu și secțiunile fiecărei pagini.</p></div>
            <div class="feat rv"><i class="ph ph-globe"></i><h3>Adresă proprie</h3><p>Pe un subdomeniu Tixello sau pe domeniul tău, cu certificat SSL.</p></div>
            <div class="feat rv"><i class="ph ph-device-mobile"></i><h3>Gândit pentru telefon</h3><p>Majoritatea biletelor se cumpără de pe mobil. Fiecare pas e făcut pentru asta.</p></div>
        </div>
    </section>

    <section class="end rv">
        <div>
            <h2>Ți-a plăcut unul?</h2>
            <p>Îl configurăm cu numele, logo-ul și evenimentele tale, pe adresa ta.</p>
        </div>
        <a href="<?= e($cta) ?>" class="btn btn-accent" target="_blank" rel="noopener">Vreau un site</a>
    </section>

    <footer>
        <span>© <?= date('Y') ?> Tixello</span>
        <span>Site-urile demo folosesc plăți de test. Biletele emise nu sunt valabile.</span>
    </footer>
</div>
<script>
(function () {
    // Filtru pe categorii
    var chips = document.querySelectorAll('.chip');
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            var cat = chip.getAttribute('data-cat');
            chips.forEach(function (c) { c.classList.toggle('on', c === chip); });
            document.querySelectorAll('.card').forEach(function (card) {
                card.style.display = !cat || card.getAttribute('data-cat') === cat ? '' : 'none';
            });
        });
    });
    // Captura derulează la hover cât e de înaltă pagina
    function fit() {
        document.querySelectorAll('.frame').forEach(function (f) { f.style.setProperty('--frame-h', f.clientHeight + 'px'); });
    }
    window.addEventListener('resize', fit); window.addEventListener('load', fit); fit();
    // Apariții la scroll
    var els = document.querySelectorAll('.rv');
    if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
        els.forEach(function (el) { el.classList.add('in'); }); return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    els.forEach(function (el, i) { el.style.transitionDelay = (i < 4 ? i * 70 : 0) + 'ms'; io.observe(el); });
})();
</script>
</body>
</html>
<?php
    exit;
}
