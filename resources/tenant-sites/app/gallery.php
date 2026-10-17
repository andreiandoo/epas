<?php
/**
 * Galeria demo-urilor de template (demo.tics.ro): linkul de trimis prospecților.
 * Lista vine din core (GET /api/tenant-site/demos, cache SITE_CONFIG_TTL);
 * demo-urile se administrează în core, la Tenants → Demo-uri site.
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

function ts_render_gallery(): void {
    $demos = ts_fetch_demos();
    $groups = [];
    foreach ($demos as $d) {
        $groups[$d['category'] ?? 'Altele'][] = $d;
    }
    $fonts = array_unique(array_filter(array_map(fn ($d) => preg_replace('/[^A-Za-z0-9 ]/', '', $d['fonts']['display'] ?? ''), $demos)));
    $fontHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Syne:wght@600;700;800'
        . implode('', array_map(fn ($f) => '&family=' . str_replace(' ', '+', $f), $fonts)) . '&display=swap';
    $hex = fn ($c, $fallback) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? $c : $fallback;

    header('Cache-Control: public, max-age=60');
    ?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site-uri de bilete gata de lansat — Tixello</title>
<meta name="description" content="Template-uri de site pentru teatre, festivaluri, artiști, competiții și locații de agrement, cu vânzare de bilete inclusă. Vezi fiecare demo live.">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($fontHref) ?>" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
    body{background:#0b0b0d;color:#f3f1ec;font-family:Inter,system-ui,sans-serif}
    .font-head{font-family:Syne,Inter,sans-serif}
    .card{transition:transform .5s cubic-bezier(.2,.7,.2,1),box-shadow .5s}
    .card:hover{transform:translateY(-6px);box-shadow:0 30px 60px -30px rgba(0,0,0,.8)}
    .card:hover .swatch{transform:scale(1.04)}
    .swatch{transition:transform .8s cubic-bezier(.2,.7,.2,1)}
    .grain:after{content:'';position:absolute;inset:0;pointer-events:none;opacity:.07;mix-blend-mode:overlay;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 160 160' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
    @media (prefers-reduced-motion:reduce){.card,.swatch{transition:none}}
</style>
<script defer src="<?= e(TS_MOTION_LIBS['lenis'][0]) ?>"></script>
<?php foreach (TS_MOTION_LIBS['gsap'] as $src): ?><script defer src="<?= e($src) ?>"></script><?php endforeach; ?>
<script defer src="/assets/motion/ts-motion.js"></script>
</head>
<body class="antialiased">
<header class="relative overflow-hidden grain">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(255,92,57,.22),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(94,92,255,.22),transparent_55%)]"></div>
    <div class="relative max-w-7xl mx-auto px-5 sm:px-8 pt-10 pb-24 sm:pb-32">
        <a href="https://tixello.ro" class="font-head text-xl font-bold tracking-tight">tixello</a>
        <p class="mt-20 text-sm uppercase tracking-[.3em] text-white/50" data-reveal>Site + bilete, gata de lansat</p>
        <h1 class="font-head mt-5 text-5xl sm:text-7xl lg:text-8xl font-extrabold leading-[.95] max-w-5xl" data-reveal data-reveal-delay="0.1">Alege cum arată site-ul tău.</h1>
        <p class="mt-8 max-w-2xl text-lg text-white/65" data-reveal data-reveal-delay="0.2">Fiecare template de mai jos e un site complet și funcțional, cu program, pagini de eveniment, hartă de locuri, coș, plată și cont de client. Deschide orice demo și navighează ca un cumpărător.</p>
    </div>
</header>

<main class="max-w-7xl mx-auto px-5 sm:px-8 pb-24">
    <?php if (!$demos): ?>
        <p class="py-24 text-center text-white/50">Demo-urile sunt în pregătire. Revino în curând.</p>
    <?php endif; ?>
    <?php foreach ($groups as $category => $items): ?>
    <section class="pt-16">
        <h2 class="font-head text-sm uppercase tracking-[.3em] text-white/45 mb-8" data-reveal><?= e($category) ?></h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-stagger>
            <?php foreach ($items as $d):
                $c = $d['colors'] ?? [];
                $canvas = $hex($c['canvas'] ?? null, '#111111');
                $primary = $hex($c['primary'] ?? null, '#ff5c39');
                $accent = $hex($c['accent'] ?? null, '#5e5cff');
                $fg = $hex($c['fg'] ?? null, '#ffffff');
                $display = preg_replace('/[^A-Za-z0-9 ]/', '', $d['fonts']['display'] ?? 'Syne');
            ?>
            <a href="<?= e($d['url']) ?>" target="_blank" rel="noopener" class="card group block rounded-3xl overflow-hidden bg-white/[.04] border border-white/10">
                <div class="relative aspect-[4/3] overflow-hidden" style="background:<?= e($canvas) ?>">
                    <?php if (!empty($d['preview_image'])): ?>
                        <img src="<?= e($d['preview_image']) ?>" alt="" loading="lazy" class="swatch absolute inset-0 w-full h-full object-cover">
                    <?php else: ?>
                        <div class="swatch absolute inset-0" style="background:radial-gradient(circle at 25% 20%,<?= e($primary) ?>55,transparent 55%),radial-gradient(circle at 80% 85%,<?= e($accent) ?>88,transparent 60%),<?= e($canvas) ?>"></div>
                        <div class="absolute inset-0 flex flex-col justify-end p-7">
                            <span class="block w-12 h-[3px] mb-5" style="background:<?= e($primary) ?>"></span>
                            <span class="text-4xl leading-tight" style="color:<?= e($fg) ?>;font-family:'<?= e($display) ?>',serif"><?= e($d['title']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 flex gap-1.5">
                        <?php foreach ([$canvas, $primary, $accent] as $sw): ?><span class="w-4 h-4 rounded-full border border-white/30" style="background:<?= e($sw) ?>"></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-head text-xl font-bold"><?= e($d['template_name'] ?? $d['title']) ?></h3>
                        <span class="shrink-0 text-sm text-white/50 group-hover:text-white transition-colors">Deschide →</span>
                    </div>
                    <?php if (!empty($d['description'])): ?><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($d['description']) ?></p><?php endif; ?>
                    <p class="mt-4 text-xs text-white/35"><?= e(parse_url($d['url'], PHP_URL_HOST)) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="mt-28 rounded-3xl border border-white/10 bg-white/[.03] p-8 sm:p-12 grid gap-8 lg:grid-cols-[1.4fr_1fr] items-center" data-reveal>
        <div>
            <h2 class="font-head text-3xl sm:text-4xl font-bold">Ți-a plăcut unul?</h2>
            <p class="mt-4 text-white/60 max-w-xl">Îl punem pe numele tău: logo, culori, texte și secțiuni, pe o adresă proprie sau pe domeniul tău. Vânzarea de bilete merge din prima zi.</p>
        </div>
        <div class="lg:text-right">
            <a href="https://tixello.ro" class="inline-block rounded-full bg-white px-7 py-4 font-semibold text-black hover:bg-white/90 transition-colors">Vreau site-ul meu</a>
        </div>
    </section>
</main>
<footer class="border-t border-white/10 py-10 text-center text-sm text-white/40">Ticketing by <a href="https://tixello.ro" class="text-white/70 hover:text-white">Tixello</a></footer>
</body>
</html>
<?php
    exit;
}
