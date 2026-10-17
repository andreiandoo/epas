<?php
/**
 * Tema: culorile și fonturile alese în core devin variabile CSS, iar Tailwind
 * le folosește prin aceleași nume în toate paginile (template + comune):
 *
 *   canvas   fundalul paginii          surface  secțiuni, carduri
 *   panel    panouri (cont client)     fg       text
 *   muted    text secundar             primary  butoane, accente (+ primary-light)
 *   accent   culoarea secundară (+ accent-light, accent-dark)
 *
 * Clase: bg-canvas, text-fg/70, border-primary/20, from-accent ... ; fonturi:
 * font-display, font-body. Culoarea textului pe primary: text-on-primary.
 */

const TS_DEFAULT_COLORS = [
    'canvas' => '#0A0A0A', 'surface' => '#1A1A1A', 'panel' => '#1D1A17', 'fg' => '#FFFEF2',
    'muted' => '#9A9590', 'primary' => '#D4AF37', 'accent' => '#722F37',
];

/** Axele Google Fonts disponibile pentru fiecare font din panou (o axă inexistentă strică tot CSS-ul). */
const TS_FONT_AXES = [
    'Playfair Display' => 'ital,wght@0,400;0,500;0,600;0,700;1,400;1,500',
    'Lora' => 'ital,wght@0,400;0,500;0,600;0,700;1,400;1,500',
    'Libre Baskerville' => 'ital,wght@0,400;0,700;1,400',
    'Merriweather' => 'ital,wght@0,300;0,400;0,700;1,300;1,400',
    'DM Serif Display' => 'ital@0;1',
    'Lato' => 'ital,wght@0,300;0,400;0,700;1,400',
    'Space Grotesk' => 'wght@300;400;500;600;700',
    'Syne' => 'wght@400;500;600;700',
    'Oswald' => 'wght@300;400;500;600;700',
    'Bebas Neue' => '',
];
const TS_FONT_AXES_DEFAULT = 'ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500';
const TS_SERIF_FONTS = ['Playfair Display', 'Cormorant Garamond', 'Libre Baskerville', 'Lora', 'Merriweather', 'Fraunces', 'DM Serif Display'];

function ts_hex_rgb(string $hex): array {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return [0, 0, 0];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Amestecă o culoare cu alta (pct = cât din a doua). */
function ts_mix(array $a, array $b, float $pct): array {
    return array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $pct), $a, $b);
}

function ts_luminance(array $rgb): float {
    [$r, $g, $b] = array_map(function ($c) {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, $rgb);
    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function ts_colors(): array {
    $c = array_merge(TS_DEFAULT_COLORS, manifest('theme.colors', []), array_filter((array) site('theme.colors', [])));
    $rgb = array_map('ts_hex_rgb', $c);
    $white = [255, 255, 255];
    $black = [0, 0, 0];
    $rgb['primary-light'] = ts_mix($rgb['primary'], $white, 0.25);
    $rgb['accent-light'] = ts_mix($rgb['accent'], $white, 0.15);
    $rgb['accent-dark'] = ts_mix($rgb['accent'], $black, 0.25);
    $rgb['on-primary'] = ts_luminance($rgb['primary']) > 0.35 ? $rgb['canvas'] : $white;
    if (ts_luminance($rgb['primary']) > 0.35 && ts_luminance($rgb['canvas']) > 0.35) {
        $rgb['on-primary'] = [17, 17, 17];
    }
    return $rgb;
}

function ts_fonts(): array {
    $f = array_merge(['display' => 'Playfair Display', 'body' => 'Lato'], manifest('theme.fonts', []), array_filter((array) site('theme.fonts', [])));
    return [
        'display' => preg_replace('/[^A-Za-z0-9 ]/', '', $f['display']),
        'body' => preg_replace('/[^A-Za-z0-9 ]/', '', $f['body']),
    ];
}

function ts_font_stack(string $font): string {
    return "'" . $font . "', " . (in_array($font, TS_SERIF_FONTS, true) ? 'serif' : 'sans-serif');
}

/** <link>-urile Google Fonts pentru fonturile alese. */
function ts_fonts_head(): string {
    $families = [];
    foreach (array_unique(array_values(ts_fonts())) as $font) {
        $axes = array_key_exists($font, TS_FONT_AXES) ? TS_FONT_AXES[$font] : TS_FONT_AXES_DEFAULT;
        $families[] = 'family=' . str_replace(' ', '+', $font) . ($axes !== '' ? ':' . $axes : '');
    }
    $href = 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap';
    return '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="' . e($href) . '" rel="stylesheet">';
}

/** Variabilele CSS ale temei. */
function ts_theme_css(): string {
    $css = ':root{';
    foreach (ts_colors() as $name => $rgb) {
        $css .= '--c-' . $name . ':' . implode(' ', $rgb) . ';';
    }
    $fonts = ts_fonts();
    $css .= '--f-display:' . ts_font_stack($fonts['display']) . ';--f-body:' . ts_font_stack($fonts['body']) . ';}';
    $css .= 'body{background:rgb(var(--c-canvas));color:rgb(var(--c-fg));font-family:var(--f-body)}';
    $css .= '.font-display{font-family:var(--f-display)}.font-body{font-family:var(--f-body)}';
    $css .= '.ts-hl{background:linear-gradient(135deg,rgb(var(--c-primary)) 0%,rgb(var(--c-primary-light)) 50%,rgb(var(--c-primary)) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}';
    // Componentele folosite de paginile comune (coș, checkout, autentificare);
    // un template le poate redefini în <head>, după ts_head_assets().
    $css .= '.btn-primary{background:linear-gradient(135deg,rgb(var(--c-primary)) 0%,rgb(var(--c-primary-light)) 50%,rgb(var(--c-primary)) 100%);color:rgb(var(--c-on-primary));font-weight:600;transition:all .3s ease}'
        . '.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 30px -10px rgb(var(--c-primary) / .5)}'
        . '.btn-accent{background:rgb(var(--c-accent));color:rgb(var(--c-fg));transition:all .3s ease}.btn-accent:hover{background:rgb(var(--c-accent-light));transform:translateY(-2px)}'
        . '.btn-outline{border:1px solid rgb(var(--c-primary));color:rgb(var(--c-primary));transition:all .3s ease}.btn-outline:hover{background:rgb(var(--c-primary));color:rgb(var(--c-on-primary))}'
        . '.text-primary-gradient{background:linear-gradient(135deg,rgb(var(--c-primary)) 0%,rgb(var(--c-primary-light)) 50%,rgb(var(--c-primary)) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}'
        . '.show-card{background:linear-gradient(180deg,rgb(var(--c-surface)) 0%,rgb(var(--c-canvas)) 100%);border:1px solid rgb(var(--c-primary) / .1);transition:all .4s ease}.show-card:hover{border-color:rgb(var(--c-primary) / .3);transform:translateY(-4px)}'
        . '.ornament{display:inline-block;width:60px;height:1px;background:linear-gradient(90deg,transparent,rgb(var(--c-primary)),transparent)}'
        . '.divider-ornate{display:flex;align-items:center;gap:1rem}.divider-ornate::before,.divider-ornate::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,transparent,rgb(var(--c-primary) / .3),transparent)}';
    $css .= '::selection{background:rgb(var(--c-primary));color:rgb(var(--c-on-primary))}[x-cloak]{display:none!important}';
    return $css;
}

/** Tailwind (Play CDN) + Alpine, cu culorile temei; de inclus în <head> de orice layout. */
function ts_head_assets(): string {
    $colors = [];
    foreach (array_keys(ts_colors()) as $name) {
        $colors[$name] = 'rgb(var(--c-' . $name . ') / <alpha-value>)';
    }
    // culorile fixe ale categoriilor (zona de cont)
    $colors += ['drama' => '#8B2234', 'revue' => '#B77B24', 'imaginario' => '#467A74'];
    $config = [
        'theme' => ['extend' => [
            'colors' => $colors,
            'fontFamily' => ['display' => ['var(--f-display)'], 'body' => ['var(--f-body)']],
        ]],
    ];
    $hex = sprintf('#%02x%02x%02x', ...ts_colors()['canvas']);

    return ts_fonts_head()
        . '<meta name="theme-color" content="' . $hex . '">'
        . '<script src="https://cdn.tailwindcss.com"></script>'
        . '<script>(function(){var c=' . json_encode($config, JSON_UNESCAPED_SLASHES) . ';'
        // scala completă de opacitate, ca modificatorii /N (ex. text-fg/48) să existe pe Play CDN
        . 'var op={};for(var i=0;i<=100;i++){op[i]=(i/100).toString();}c.theme.extend.opacity=op;'
        . 'c.theme.extend.borderColor={DEFAULT:"rgb(var(--c-fg) / 0.1)"};c.theme.extend.divideColor={DEFAULT:"rgb(var(--c-fg) / 0.1)"};'
        . 'tailwind.config=c;})();</script>'
        . ts_commerce_assets()
        . '<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.14.1/dist/cdn.min.js"></script>'
        . '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>'
        . '<style>' . ts_theme_css() . '</style>'
        . ts_motion_assets();
}

/**
 * Comerțul comun (coș, cod de reducere, timer, alegere bilete/locuri, plată):
 * public/assets/shared/commerce.js, înaintea lui Alpine ca să-și înregistreze componentele.
 */
function ts_commerce_assets(): string {
    $file = TS_ROOT . '/public/assets/shared/commerce.js';
    $cfg = ['api' => API_BASE, 'host' => ts_host(), 'eventsUrl' => ts_link('events')];
    return '<script>window.TS_CFG=' . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';</script>'
        . '<script defer src="/assets/shared/commerce.js?v=' . (is_file($file) ? filemtime($file) : 1) . '"></script>';
}

/** Versiunile fixate ale bibliotecilor de animație (UI_RESOURCES.md: niciodată „latest”). */
const TS_MOTION_LIBS = [
    'lenis' => ['https://cdn.jsdelivr.net/npm/lenis@1.3.26/dist/lenis.min.js'],
    'gsap' => [
        'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js',
        'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/ScrollTrigger.min.js',
    ],
];

/**
 * Animațiile cerute de manifest (`"motion": ["lenis", "gsap"]`) + modulul comun
 * public/assets/motion/ts-motion.js. Niciodată pe paginile comune (coș,
 * checkout, cont): acolo contează doar viteza.
 */
function ts_motion_assets(): string {
    $wanted = array_values(array_intersect((array) manifest('motion', []), array_keys(TS_MOTION_LIBS)));
    if (!$wanted || defined('TS_SHARED_PAGE')) {
        return '';
    }
    $html = '<style>html.lenis,html.lenis body{height:auto}.lenis.lenis-smooth{scroll-behavior:auto!important}'
        . '.lenis.lenis-stopped{overflow:hidden}</style>';
    foreach ($wanted as $lib) {
        foreach (TS_MOTION_LIBS[$lib] as $src) {
            $html .= '<script defer src="' . e($src) . '"></script>';
        }
    }
    $file = TS_ROOT . '/public/assets/motion/ts-motion.js';
    $v = is_file($file) ? filemtime($file) : 1;
    return $html . '<script defer src="/assets/motion/ts-motion.js?v=' . $v . '"></script>';
}

/** URL pentru un fișier al template-ului (templates/{slug}/assets → public/tpl/{slug}). */
function ts_asset(string $path): string {
    $path = ltrim($path, '/');
    $file = TEMPLATE_DIR . '/assets/' . $path;
    return '/tpl/' . TEMPLATE . '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
