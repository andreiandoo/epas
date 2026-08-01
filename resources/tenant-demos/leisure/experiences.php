<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$expFallbackImg = 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1400&q=86';
$expEvents = tc_events(api_get('/tenant-client/events', ['per_page' => 24]));
$expItems = [];
foreach (array_values($expEvents) as $i => $ev) {
    $tt = $ev['ticket_types'][0] ?? null;
    $price = (float) ($tt['price'] ?? $ev['price_from'] ?? 0);
    $expItems[] = [
        'id'            => $ev['slug'] ?? ('e' . ($ev['id'] ?? $i)),
        'slug'          => $ev['slug'] ?? '',
        'event_id'      => $ev['id'] ?? null,
        'title'         => $ev['title'] ?? 'Experiență',
        'description'   => $ev['short_description'] ?? $ev['description'] ?? '',
        'image'         => asset_url($ev['hero_image_url'] ?? $ev['poster_url'] ?? null, $expFallbackImg),
        'category'      => 'heights',
        'age'           => 0,
        'duration'      => $ev['category'] ?? 'Experiență',
        'durationValue' => 90,
        'level'         => $ev['category'] ?? 'Nordvale',
        'levelValue'    => 2,
        'height'        => ($ev['venue']['name'] ?? 'Nordvale'),
        'price'         => $price,
        'badge'         => $i === 0 ? 'Recomandat' : '',
        'featured'      => $i === 0,
        'booking'       => $ev['slug'] ?? '',
    ];
}
$expItemsJs = ! empty($expItems) ? json_encode($expItems, JSON_UNESCAPED_UNICODE) : '[]';
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#071d17">
    <title>Experiențe — Nordvale</title>
    <meta name="description" content="Catalogul experiențelor Nordvale — trasee la înălțime, explorări ghidate, activități de familie și aventuri în rezervație.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['DM Sans', 'sans-serif'],
                        display: ['Fraunces', 'serif']
                    },
                    colors: {
                        pine: {
                            950: '#061a15',
                            900: '#09251d',
                            850: '#0d3027',
                            800: '#123b30',
                            700: '#1b5242',
                            600: '#2e6e59'
                        },
                        acid: '#dffc62',
                        ember: '#f27b4a',
                        cream: '#fffdf6',
                        oat: '#f0ecdf',
                        moss: '#b7c9a3',
                        ink: '#16231e',
                        sky: '#b8dce0',
                        stone: '#d7d2c2'
                    },
                    boxShadow: {
                        lift: '0 38px 110px -44px rgba(6,26,21,.72)',
                        card: '0 20px 58px -34px rgba(6,26,21,.48)',
                        acid: '0 18px 54px -24px rgba(223,252,98,.58)',
                        ember: '0 18px 54px -24px rgba(242,123,74,.42)'
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --pine: #09251d;
            --pine-dark: #061a15;
            --acid: #dffc62;
            --ember: #f27b4a;
            --cream: #fffdf6;
            --oat: #f0ecdf;
            --ink: #16231e;
        }

        * { box-sizing: border-box; }
        html { background: var(--oat); }
        body {
            margin: 0;
            overflow-x: hidden;
            background: var(--oat);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            text-rendering: optimizeLegibility;
        }
        [x-cloak] { display: none !important; }
        ::selection { background: var(--acid); color: var(--pine-dark); }

        .safe-top { padding-top: max(12px, env(safe-area-inset-top)); }
        .safe-bottom { padding-bottom: max(16px, env(safe-area-inset-bottom)); }

        .grain::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 8;
            pointer-events: none;
            opacity: .085;
            mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.58'/%3E%3C/svg%3E");
        }

        .topo-dark {
            background-image:
                radial-gradient(circle at 18% 22%, rgba(223,252,98,.13), transparent 20%),
                radial-gradient(circle at 84% 18%, rgba(242,123,74,.11), transparent 17%),
                url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.05' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, auto, 820px 820px;
        }

        .paper-grid {
            background-image:
                linear-gradient(rgba(9,37,29,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(9,37,29,.045) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .nav-shell {
            background: rgba(6,26,21,.8);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 24px 70px -42px rgba(0,0,0,.9);
            backdrop-filter: blur(22px) saturate(130%);
            -webkit-backdrop-filter: blur(22px) saturate(130%);
        }

        .hero-image-mask {
            clip-path: polygon(8% 0, 100% 0, 96% 88%, 74% 100%, 0 94%, 0 10%);
        }

        .outline-word {
            color: transparent;
            -webkit-text-stroke: 1px rgba(255,255,255,.47);
            text-stroke: 1px rgba(255,255,255,.47);
        }

        .route-line {
            fill: none;
            stroke: var(--acid);
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-dasharray: 8 12;
            filter: drop-shadow(0 0 8px rgba(223,252,98,.34));
        }

        .map-node {
            transform-origin: center;
            animation: node-pulse 2.8s ease-in-out infinite;
        }
        @keyframes node-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: .72; }
        }

        .pulse-dot { animation: pulse-dot 2.2s ease-out infinite; }
        @keyframes pulse-dot {
            0% { box-shadow: 0 0 0 0 rgba(223,252,98,.48); }
            70% { box-shadow: 0 0 0 10px rgba(223,252,98,0); }
            100% { box-shadow: 0 0 0 0 rgba(223,252,98,0); }
        }

        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .filter-bar {
            background: rgba(255,253,246,.9);
            border: 1px solid rgba(9,37,29,.1);
            box-shadow: 0 18px 60px -38px rgba(6,26,21,.45);
            backdrop-filter: blur(18px) saturate(120%);
            -webkit-backdrop-filter: blur(18px) saturate(120%);
        }

        .experience-card {
            perspective: 1000px;
            transform-style: preserve-3d;
        }
        .experience-card__surface {
            transform-style: preserve-3d;
            transition: transform .22s ease-out, box-shadow .35s ease, border-color .35s ease;
            will-change: transform;
        }
        .experience-card:hover .experience-card__surface {
            box-shadow: 0 36px 90px -48px rgba(6,26,21,.85);
            border-color: rgba(9,37,29,.24);
        }
        .experience-card img { transition: transform .9s cubic-bezier(.18,.8,.22,1); }
        .experience-card:hover img { transform: scale(1.06); }

        .card-sticker {
            transform: rotate(-4deg);
            box-shadow: 0 12px 28px -18px rgba(6,26,21,.65);
        }

        .magnetic { position: relative; overflow: hidden; }
        .magnetic::after {
            content: '';
            position: absolute;
            inset: -40%;
            background: radial-gradient(circle, rgba(255,255,255,.24), transparent 50%);
            opacity: 0;
            transform: scale(.3);
            transition: opacity .4s ease, transform .5s ease;
            pointer-events: none;
        }
        .magnetic:hover::after { opacity: 1; transform: scale(1); }

        .range-track {
            height: 6px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--acid) var(--progress), rgba(9,37,29,.12) var(--progress));
        }
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            background: transparent;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            margin-top: -9px;
            border-radius: 50%;
            border: 5px solid var(--pine);
            background: var(--acid);
            box-shadow: 0 7px 20px -8px rgba(6,26,21,.8);
        }
        input[type="range"]::-webkit-slider-runnable-track {
            height: 6px;
            border-radius: 999px;
            background: transparent;
        }
        input[type="range"]::-moz-range-thumb {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 5px solid var(--pine);
            background: var(--acid);
        }

        .reveal-ready [data-reveal] { opacity: 0; transform: translateY(34px); }
        .reveal-ready [data-hero-kicker],
        .reveal-ready [data-hero-title],
        .reveal-ready [data-hero-copy],
        .reveal-ready [data-hero-visual] { opacity: 0; }
        .reveal-ready [data-hero-title] { transform: translateY(48px); }
        .reveal-ready [data-hero-copy], .reveal-ready [data-hero-kicker] { transform: translateY(22px); }
        .reveal-ready [data-hero-visual] { transform: translateX(58px) rotate(1.2deg); }

        .progress-line { transform: scaleX(0); transform-origin: left center; }
        .drawer-shadow { box-shadow: -50px 0 120px -60px rgba(6,26,21,.82); }

        .compare-bar {
            background: rgba(6,26,21,.92);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 26px 90px -38px rgba(0,0,0,.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        @media (max-width: 1023px) {
            .hero-image-mask { clip-path: polygon(0 0, 100% 0, 100% 92%, 10% 100%, 0 95%); }
        }

        @media (max-width: 639px) {
            .hero-image-mask { clip-path: none; border-radius: 24px; }
            .experience-card__surface { transform: none !important; }
        }

        @media (hover: none), (pointer: coarse) {
            .experience-card__surface { transform: none !important; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
            .progress-line { transform: scaleX(1); }
            .reveal-ready [data-reveal],
            .reveal-ready [data-hero-kicker],
            .reveal-ready [data-hero-title],
            .reveal-ready [data-hero-copy],
            .reveal-ready [data-hero-visual] { opacity: 1; transform: none; }
        }
    </style>

    <script>
        document.documentElement.classList.add('reveal-ready');
        window.__revealSafety = window.setTimeout(() => {
            document.documentElement.classList.remove('reveal-ready');
        }, 4200);
    </script>
</head>
<body x-data="experiencesPage()" x-init="init()" class="antialiased">

    <div class="fixed inset-x-0 top-0 z-[140] h-[3px]">
        <div id="page-progress" class="progress-line h-full w-full bg-acid"></div>
    </div>

    <?php $nvNav='experiente'; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

    <main>
        <!-- Hero -->
        <section id="top" class="grain topo-dark relative overflow-hidden bg-pine-950 pb-14 pt-28 text-white sm:pb-20 sm:pt-32 lg:min-h-[92svh] lg:pb-20 lg:pt-28">
            <div class="absolute inset-0 bg-gradient-to-b from-pine-950/0 via-pine-950/10 to-pine-950/80"></div>
            <div class="absolute -left-40 top-32 h-[460px] w-[460px] rounded-full border border-acid/10"></div>
            <div class="absolute -right-56 bottom-16 h-[600px] w-[600px] rounded-full border border-white/10"></div>

            <div class="relative z-10 mx-auto grid max-w-[1540px] gap-10 px-4 sm:px-6 lg:grid-cols-[.92fr_1.08fr] lg:items-center lg:gap-14 lg:px-8 xl:px-12">
                <div class="min-w-0 lg:pb-10">
                    <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.74] sm:px-4 sm:text-[10px] sm:tracking-[.24em]">
                        <span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span>
                        <span class="truncate">12 experiențe · 4 niveluri de intensitate</span>
                    </div>

                    <h1 class="font-display text-[clamp(50px,13vw,92px)] font-semibold leading-[.9] tracking-[-.052em] lg:text-[clamp(76px,7.4vw,120px)]">
                        <span data-hero-title class="block">Nu există</span>
                        <span data-hero-title class="block text-acid">o singură</span>
                        <span data-hero-title class="outline-word block italic">aventură.</span>
                    </h1>

                    <p data-hero-copy class="mt-7 max-w-xl text-[15px] leading-7 text-white/[.66] sm:text-lg sm:leading-8">
                        Alege după energie, vârstă, durată sau curaj. De la explorări liniștite în rezervație până la trasee suspendate la 18 metri deasupra solului.
                    </p>

                    <div data-hero-copy class="mt-7 flex flex-wrap gap-3">
                        <a href="#finder" class="magnetic inline-flex min-h-[52px] items-center justify-center gap-2 rounded-full bg-acid px-5 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:px-6 sm:text-base">
                            Găsește experiența potrivită
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                        <a href="#catalog" class="inline-flex min-h-[52px] items-center justify-center rounded-full border border-white/20 px-5 text-sm font-semibold text-white transition hover:bg-white/10 sm:px-6 sm:text-base">Vezi toate experiențele</a>
                    </div>

                    <div data-hero-copy class="mt-9 grid max-w-[540px] grid-cols-3 gap-2.5 sm:gap-3">
                        <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3 sm:px-4 sm:py-4">
                            <strong class="block font-display text-xl text-acid sm:text-2xl">4+</strong>
                            <span class="mt-1 block text-[10px] leading-4 text-white/[.48] sm:text-xs">vârsta minimă</span>
                        </div>
                        <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3 sm:px-4 sm:py-4">
                            <strong class="block font-display text-xl text-acid sm:text-2xl">18 m</strong>
                            <span class="mt-1 block text-[10px] leading-4 text-white/[.48] sm:text-xs">înălțime maximă</span>
                        </div>
                        <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3 sm:px-4 sm:py-4">
                            <strong class="block font-display text-xl text-acid sm:text-2xl">3 km</strong>
                            <span class="mt-1 block text-[10px] leading-4 text-white/[.48] sm:text-xs">cea mai lungă rută</span>
                        </div>
                    </div>
                </div>

                <div data-hero-visual class="relative min-h-[440px] min-w-0 sm:min-h-[590px] lg:min-h-[700px]">
                    <div class="hero-image-mask absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                        <img id="hero-image" src="https://images.unsplash.com/photo-1529665253569-6d01c0eaf7b6?auto=format&fit=crop&w=1600&q=88" alt="Traseu suspendat în pădure" class="h-full w-full object-cover object-center">
                        <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-transparent to-pine-950/5"></div>
                    </div>

                    <svg class="pointer-events-none absolute inset-[6%] z-10 h-[83%] w-[88%]" viewBox="0 0 720 680" fill="none" aria-hidden="true">
                        <path id="experience-route" class="route-line" d="M76 574c72-64 121-38 166-117 49-85 8-157 109-185 78-22 139 27 198-34 38-39 35-97 95-136"/>
                        <circle class="map-node" cx="76" cy="574" r="8" fill="#DFFC62"/>
                        <circle class="map-node" cx="351" cy="272" r="8" fill="#FFFDF6" style="animation-delay:.6s"/>
                        <circle class="map-node" cx="644" cy="102" r="8" fill="#F27B4A" style="animation-delay:1.1s"/>
                        <g id="experience-marker">
                            <circle r="14" fill="#09251D" stroke="#DFFC62" stroke-width="3"/>
                            <path d="m-4 0 3 3 6-7" fill="none" stroke="#DFFC62" stroke-width="2"/>
                        </g>
                    </svg>

                    <div class="absolute bottom-4 left-4 right-4 z-20 rounded-[22px] border border-white/[.14] bg-pine-950/[.74] p-4 backdrop-blur-xl sm:bottom-7 sm:left-7 sm:right-auto sm:w-[310px] sm:p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid">Recomandarea zilei</span>
                                <h2 class="mt-1 font-display text-2xl font-semibold">Canopy Run</h2>
                            </div>
                            <span class="grid h-11 w-11 flex-none place-items-center rounded-full bg-ember text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-[.13em] text-white/[.58]">
                            <span class="rounded-full border border-white/10 px-2.5 py-1.5">90 min</span>
                            <span class="rounded-full border border-white/10 px-2.5 py-1.5">12+ ani</span>
                            <span class="rounded-full border border-white/10 px-2.5 py-1.5">intens</span>
                        </div>
                    </div>

                    <div class="card-sticker absolute right-2 top-9 z-20 rounded-[18px] bg-acid px-4 py-3 text-pine-950 sm:right-3 sm:top-14 sm:px-5 sm:py-4">
                        <span class="block text-[9px] font-bold uppercase tracking-[.18em]">Disponibil azi</span>
                        <strong class="mt-1 block font-display text-2xl">14:30</strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- Finder -->
        <section id="finder" class="paper-grid relative bg-cream px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
            <div class="mx-auto max-w-[1460px]">
                <div class="grid gap-10 lg:grid-cols-[.72fr_1.28fr] lg:items-start xl:gap-16">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Adventure Finder</span>
                        <h2 class="mt-4 max-w-xl font-display text-[clamp(42px,8vw,76px)] font-semibold leading-[.95] tracking-[-.045em] text-pine-950">
                            Spune-ne cum vrei să te simți.
                        </h2>
                        <p class="mt-6 max-w-lg text-base leading-8 text-pine-900/[.62] sm:text-lg">
                            Nu trebuie să cunoști traseele. Ajustează trei lucruri, iar noi îți arătăm experiențele care se potrivesc cel mai bine ritmului tău.
                        </p>
                        <div class="mt-8 hidden rounded-[26px] bg-pine-900 p-6 text-white lg:block">
                            <div class="flex items-center gap-3">
                                <span class="grid h-11 w-11 place-items-center rounded-[15px] bg-acid text-pine-950">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18m9-9H3"/></svg>
                                </span>
                                <div>
                                    <strong class="block">Poți combina experiențe</strong>
                                    <span class="mt-1 block text-sm text-white/[.48]">Pachetele de o zi includ până la 3 activități.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div data-reveal class="overflow-hidden rounded-[30px] border border-pine-900/10 bg-oat shadow-card">
                        <div class="grid gap-0 xl:grid-cols-[.9fr_1.1fr]">
                            <div class="border-b border-pine-900/10 p-5 sm:p-7 xl:border-b-0 xl:border-r xl:p-8">
                                <div class="space-y-8">
                                    <div>
                                        <div class="flex items-end justify-between gap-4">
                                            <label class="font-bold text-pine-950">Nivel de energie</label>
                                            <span class="rounded-full bg-pine-900 px-3 py-1.5 text-xs font-bold text-acid" x-text="energyLabel"></span>
                                        </div>
                                        <div class="range-track mt-5" :style="`--progress:${energy * 25}%`">
                                            <input type="range" min="1" max="4" step="1" x-model.number="energy" aria-label="Nivel de energie">
                                        </div>
                                        <div class="mt-3 flex justify-between text-[10px] font-bold uppercase tracking-[.14em] text-pine-900/[.38]"><span>Liniștit</span><span>Intens</span></div>
                                    </div>

                                    <div>
                                        <div class="flex items-end justify-between gap-4">
                                            <label class="font-bold text-pine-950">Timp disponibil</label>
                                            <span class="rounded-full bg-pine-900 px-3 py-1.5 text-xs font-bold text-acid" x-text="durationLabel"></span>
                                        </div>
                                        <div class="range-track mt-5" :style="`--progress:${(duration - 1) * 33.333}%`">
                                            <input type="range" min="1" max="4" step="1" x-model.number="duration" aria-label="Timp disponibil">
                                        </div>
                                        <div class="mt-3 flex justify-between text-[10px] font-bold uppercase tracking-[.14em] text-pine-900/[.38]"><span>Sub o oră</span><span>O zi</span></div>
                                    </div>

                                    <div>
                                        <label class="font-bold text-pine-950">Cu cine vii?</label>
                                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-2">
                                            <template x-for="group in groups" :key="group.id">
                                                <button @click="selectedGroup = group.id" class="rounded-[18px] border px-3 py-3 text-left text-sm font-bold transition" :class="selectedGroup === group.id ? 'border-pine-900 bg-pine-900 text-white' : 'border-pine-900/10 bg-white text-pine-950 hover:border-pine-900/25'">
                                                    <span class="block text-xl" x-text="group.icon"></span>
                                                    <span class="mt-2 block" x-text="group.label"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="relative min-h-[460px] overflow-hidden bg-pine-900 p-5 text-white sm:p-7 xl:p-8">
                                <div class="absolute inset-0 opacity-35">
                                    <img :src="finderResult.image" :alt="finderResult.title" class="h-full w-full object-cover transition duration-700">
                                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/65 to-pine-950/15"></div>
                                </div>
                                <div class="relative z-10 flex min-h-[420px] flex-col">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.17em] text-acid">Potrivirea ta</span>
                                        <span class="font-display text-3xl font-semibold text-acid" x-text="finderResult.match + '%' "></span>
                                    </div>
                                    <div class="mt-auto">
                                        <div class="mb-4 flex flex-wrap gap-2">
                                            <template x-for="tag in finderResult.tags" :key="tag"><span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/80" x-text="tag"></span></template>
                                        </div>
                                        <h3 class="font-display text-[clamp(38px,7vw,62px)] font-semibold leading-[.94] tracking-[-.04em]" x-text="finderResult.title"></h3>
                                        <p class="mt-4 max-w-lg text-sm leading-7 text-white/[.62] sm:text-base" x-text="finderResult.description"></p>
                                        <div class="mt-6 flex flex-col gap-2.5 min-[430px]:flex-row">
                                            <a href="#catalog" @click="activeCategory = finderResult.category" class="magnetic inline-flex min-h-[50px] items-center justify-center rounded-full bg-acid px-5 text-sm font-bold text-pine-950">Vezi experiența recomandată</a>
                                            <button @click="bookingOpen = true; selectedBookingOption = finderResult.booking" class="inline-flex min-h-[50px] items-center justify-center rounded-full border border-white/20 px-5 text-sm font-bold text-white">Rezervă direct</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Categories panorama -->
        <section id="categorii" class="overflow-hidden bg-pine-900 px-4 py-20 text-white sm:px-6 sm:py-24 lg:px-10 lg:py-28">
            <div class="mx-auto max-w-[1460px]">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div data-reveal>
                        <span class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Patru moduri de a explora</span>
                        <h2 class="mt-4 max-w-3xl font-display text-[clamp(42px,8vw,82px)] font-semibold leading-[.92] tracking-[-.046em]">Alege terenul.<br><span class="outline-word italic">Noi pregătim traseul.</span></h2>
                    </div>
                    <p data-reveal class="max-w-md text-sm leading-7 text-white/[.55] sm:text-base">Fiecare categorie are propriul ritm, propriile reguli și o poveste distinctă. Poți combina mai multe într-o singură zi.</p>
                </div>

                <div class="mt-12 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <template x-for="(category, index) in categories" :key="category.id">
                        <button data-reveal @click="activeCategory = category.id; document.querySelector('#catalog').scrollIntoView({behavior:'smooth'})" class="group relative min-h-[430px] overflow-hidden rounded-[26px] border border-white/10 text-left sm:min-h-[480px]" :class="index === 1 ? 'xl:translate-y-8' : index === 3 ? 'xl:translate-y-14' : ''">
                            <img :src="category.image" :alt="category.label" class="absolute inset-0 h-full w-full object-cover transition duration-[900ms] group-hover:scale-105">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/25 to-pine-950/5"></div>
                            <div class="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                                <div class="flex items-end justify-between gap-4">
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid" x-text="category.eyebrow"></span>
                                        <h3 class="mt-2 font-display text-3xl font-semibold" x-text="category.label"></h3>
                                        <p class="mt-3 max-w-xs text-sm leading-6 text-white/[.58]" x-text="category.description"></p>
                                    </div>
                                    <span class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid text-pine-950 transition duration-500 group-hover:rotate-45">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg>
                                    </span>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </section>

        <!-- Catalog -->
        <section id="catalog" class="relative bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
            <div class="mx-auto max-w-[1460px]">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div data-reveal>
                        <span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Catalogul Nordvale</span>
                        <h2 class="mt-4 max-w-3xl font-display text-[clamp(44px,8vw,80px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Experiențe construite<br><span class="italic text-pine-700">pentru ritmuri diferite.</span></h2>
                    </div>
                    <div data-reveal class="flex items-center gap-3 text-sm text-pine-900/[.5]"><span class="h-px w-10 bg-pine-900/20"></span><span x-text="filteredExperiences.length + ' experiențe afișate'"></span></div>
                </div>

                <div class="filter-bar sticky top-[82px] z-40 mt-10 rounded-[22px] p-3 sm:top-[90px] sm:p-4">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="scrollbar-hide flex gap-2 overflow-x-auto pb-1 xl:pb-0">
                            <button @click="activeCategory = 'all'" class="flex-none whitespace-nowrap rounded-full px-4 py-2.5 text-sm font-bold transition" :class="activeCategory === 'all' ? 'bg-pine-900 text-acid' : 'bg-pine-900/[.055] text-pine-950 hover:bg-pine-900/10'">Toate</button>
                            <template x-for="category in categories" :key="category.id">
                                <button @click="activeCategory = category.id" class="flex-none whitespace-nowrap rounded-full px-4 py-2.5 text-sm font-bold transition" :class="activeCategory === category.id ? 'bg-pine-900 text-acid' : 'bg-pine-900/[.055] text-pine-950 hover:bg-pine-900/10'" x-text="category.label"></button>
                            </template>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                            <div class="relative">
                                <select x-model="ageFilter" class="h-11 w-full appearance-none rounded-full border border-pine-900/10 bg-white pl-4 pr-10 text-sm font-bold text-pine-950 outline-none sm:w-auto">
                                    <option value="all">Orice vârstă</option>
                                    <option value="4">4+ ani</option>
                                    <option value="8">8+ ani</option>
                                    <option value="12">12+ ani</option>
                                </select>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </div>
                            <div class="relative">
                                <select x-model="sortBy" class="h-11 w-full appearance-none rounded-full border border-pine-900/10 bg-white pl-4 pr-10 text-sm font-bold text-pine-950 outline-none sm:w-auto">
                                    <option value="recommended">Recomandate</option>
                                    <option value="easy">Mai ușoare</option>
                                    <option value="hard">Mai intense</option>
                                    <option value="duration">Durată scurtă</option>
                                </select>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-12">
                    <template x-for="(experience, index) in filteredExperiences" :key="experience.id">
                        <article data-reveal class="experience-card min-w-0" :class="experience.featured ? 'xl:col-span-7' : index % 5 === 1 ? 'xl:col-span-5' : 'xl:col-span-4'">
                            <div class="experience-card__surface group relative flex min-h-[500px] flex-col overflow-hidden rounded-[26px] border border-pine-900/10 bg-cream" :class="experience.featured ? 'xl:min-h-[650px]' : 'xl:min-h-[500px]'">
                                <div class="relative flex-1 overflow-hidden">
                                    <img :src="experience.image" :alt="experience.title" class="absolute inset-0 h-full w-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/10 to-transparent"></div>

                                    <div class="absolute left-4 top-4 flex max-w-[calc(100%-32px)] flex-wrap gap-2 sm:left-5 sm:top-5">
                                        <span class="rounded-full bg-acid px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.16em] text-pine-950" x-text="experience.level"></span>
                                        <span x-show="experience.badge" class="rounded-full bg-ember px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.16em] text-white" x-text="experience.badge"></span>
                                    </div>

                                    <button @click="toggleCompare(experience.id)" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full border border-white/20 bg-pine-950/45 text-white backdrop-blur-md transition hover:bg-pine-950" :class="compareIds.includes(experience.id) ? 'bg-acid !text-pine-950 !border-acid' : ''" :aria-label="compareIds.includes(experience.id) ? 'Elimină din comparație' : 'Adaugă la comparație'">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 17h16M7 4v6m10 4v6"/></svg>
                                    </button>

                                    <div class="absolute inset-x-0 bottom-0 p-5 text-white sm:p-6">
                                        <div class="mb-3 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-[.12em] text-white/[.62]">
                                            <span x-text="experience.duration"></span><span>·</span><span x-text="experience.age + '+ ani'"></span><span>·</span><span x-text="experience.height"></span>
                                        </div>
                                        <h3 class="font-display text-[clamp(34px,6vw,58px)] font-semibold leading-[.94] tracking-[-.035em]" x-text="experience.title"></h3>
                                        <p class="mt-3 max-w-xl text-sm leading-6 text-white/[.62]" x-text="experience.description"></p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                    <div>
                                        <span class="block text-xs text-pine-900/[.42]">de la</span>
                                        <strong class="mt-1 block font-display text-3xl font-semibold text-pine-950"><span x-text="experience.price"></span> lei</strong>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 sm:flex">
                                        <a :href="'/experienta?slug=' + encodeURIComponent(experience.slug || experience.id)" class="inline-flex min-h-[46px] items-center justify-center rounded-full border border-pine-900/15 px-4 text-sm font-bold text-pine-950 transition hover:bg-pine-900/5">Detalii</a>
                                        <a :href="'/experienta?slug=' + encodeURIComponent(experience.slug || experience.id)" class="magnetic inline-flex min-h-[46px] items-center justify-center rounded-full bg-pine-900 px-4 text-sm font-bold text-acid">Rezervă</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="filteredExperiences.length === 0" class="mt-10 rounded-[28px] border border-dashed border-pine-900/20 bg-cream p-10 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-pine-900 text-acid">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                    </div>
                    <h3 class="mt-5 font-display text-3xl font-semibold text-pine-950">Nu am găsit o potrivire exactă.</h3>
                    <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-pine-900/[.55]">Relaxează unul dintre filtre sau folosește Adventure Finder pentru o recomandare personalizată.</p>
                    <button @click="resetFilters()" class="mt-6 rounded-full bg-pine-900 px-5 py-3 text-sm font-bold text-acid">Resetează filtrele</button>
                </div>
            </div>
        </section>

        <!-- Guide -->
        <section id="ghid" class="bg-cream px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
            <div class="mx-auto max-w-[1460px]">
                <div class="grid gap-12 xl:grid-cols-[1fr_1.1fr] xl:items-center">
                    <div data-reveal class="relative min-h-[520px] overflow-hidden rounded-[30px] bg-pine-900 sm:min-h-[640px]">
                        <img id="guide-image" src="https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&w=1400&q=86" alt="Pădure văzută de jos" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/15 to-transparent"></div>
                        <div class="absolute bottom-5 left-5 right-5 rounded-[24px] border border-white/15 bg-pine-950/70 p-5 text-white backdrop-blur-xl sm:bottom-7 sm:left-7 sm:right-7 sm:p-6">
                            <div class="flex items-start gap-4">
                                <span class="grid h-12 w-12 flex-none place-items-center rounded-[16px] bg-acid text-pine-950">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/><path d="m12 8 3 4-3 4-3-4 3-4Z"/></svg>
                                </span>
                                <div>
                                    <span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid">Principiul Nordvale</span>
                                    <p class="mt-2 text-sm leading-7 text-white/[.66] sm:text-base">Alege experiența cu un nivel sub limita ta percepută. Vei avea mai mult spațiu să te bucuri de traseu, nu doar să îl termini.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div data-reveal>
                            <span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Cum alegi corect</span>
                            <h2 class="mt-4 max-w-3xl font-display text-[clamp(44px,8vw,78px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Curajul nu se măsoară doar în metri.</h2>
                            <p class="mt-6 max-w-xl text-base leading-8 text-pine-900/[.62] sm:text-lg">Fiecare experiență are patru repere clare. Compară-le înainte de rezervare și alege după cel mai puțin experimentat membru al grupului.</p>
                        </div>

                        <div class="mt-9 divide-y divide-pine-900/10 border-y border-pine-900/10">
                            <template x-for="(criterion, index) in criteria" :key="criterion.title">
                                <div data-reveal class="grid gap-3 py-5 sm:grid-cols-[64px_1fr_auto] sm:items-center sm:gap-5 sm:py-6">
                                    <span class="font-display text-3xl font-semibold text-pine-700" x-text="String(index + 1).padStart(2,'0')"></span>
                                    <div>
                                        <h3 class="font-bold text-pine-950" x-text="criterion.title"></h3>
                                        <p class="mt-1 text-sm leading-6 text-pine-900/[.5]" x-text="criterion.description"></p>
                                    </div>
                                    <span class="hidden rounded-full bg-oat px-3 py-2 text-[10px] font-bold uppercase tracking-[.14em] text-pine-700 sm:inline-flex" x-text="criterion.tip"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative overflow-hidden bg-ember px-4 py-16 text-white sm:px-6 sm:py-20 lg:px-10">
            <div class="absolute inset-0 opacity-15">
                <svg class="h-full w-full" viewBox="0 0 1440 420" preserveAspectRatio="none" fill="none"><path d="M-20 310c233-222 404 50 644-101 213-134 340 70 836-126" stroke="#fff" stroke-width="2" stroke-dasharray="10 16"/></svg>
            </div>
            <div data-reveal class="relative z-10 mx-auto flex max-w-[1460px] flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-950">Încă nu ești sigur?</span>
                    <h2 class="mt-4 max-w-4xl font-display text-[clamp(42px,8vw,82px)] font-semibold leading-[.92] tracking-[-.045em]">Construim o zi întreagă<br><span class="italic text-pine-950">în jurul grupului tău.</span></h2>
                </div>
                <div class="flex flex-col gap-3 min-[430px]:flex-row lg:flex-none">
                    <a href="#finder" class="inline-flex min-h-[54px] items-center justify-center rounded-full bg-pine-950 px-6 text-sm font-bold text-acid">Folosește Adventure Finder</a>
                    <button @click="bookingOpen = true" class="inline-flex min-h-[54px] items-center justify-center rounded-full border border-white/35 px-6 text-sm font-bold text-white">Vorbește cu un ghid</button>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
            <div class="mx-auto grid max-w-[1200px] gap-10 lg:grid-cols-[.7fr_1.3fr] lg:gap-16">
                <div data-reveal>
                    <span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Înainte să alegi</span>
                    <h2 class="mt-4 font-display text-[clamp(42px,8vw,72px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Întrebări care apar des.</h2>
                    <p class="mt-6 text-base leading-8 text-pine-900/[.58]">Detaliile specifice fiecărui traseu apar și pe pagina experienței, înainte de alegerea intervalului.</p>
                </div>
                <div class="divide-y divide-pine-900/10 border-y border-pine-900/10">
                    <template x-for="(faq, index) in faqs" :key="faq.q">
                        <div data-reveal>
                            <button @click="openFaq = openFaq === index ? null : index" class="flex w-full items-center justify-between gap-5 py-5 text-left sm:py-6">
                                <span class="font-bold text-pine-950" x-text="faq.q"></span>
                                <span class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/15 transition" :class="openFaq === index ? 'rotate-45 bg-pine-900 text-acid' : ''">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </button>
                            <div x-show="openFaq === index" x-collapse class="pb-6 pr-12 text-sm leading-7 text-pine-900/[.58]" x-text="faq.a"></div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Compare bar -->
    <div x-cloak x-show="compareIds.length > 0" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-24 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-24 opacity-0" class="fixed bottom-3 left-3 right-3 z-[120] sm:bottom-5 sm:left-1/2 sm:right-auto sm:w-[min(760px,calc(100vw-40px))] sm:-translate-x-1/2">
        <div class="compare-bar safe-bottom rounded-[22px] p-3 text-white sm:p-4">
            <div class="flex items-center gap-3">
                <div class="hidden min-w-0 flex-1 gap-2 sm:flex">
                    <template x-for="id in compareIds" :key="id">
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-[14px] border border-white/10 bg-white/[.06] p-2">
                            <img :src="experienceById(id).image" :alt="experienceById(id).title" class="h-10 w-10 flex-none rounded-[10px] object-cover">
                            <span class="truncate text-xs font-bold" x-text="experienceById(id).title"></span>
                        </div>
                    </template>
                </div>
                <div class="min-w-0 flex-1 sm:hidden">
                    <span class="block text-[9px] font-bold uppercase tracking-[.16em] text-acid">Comparație</span>
                    <strong class="mt-1 block truncate text-sm" x-text="compareIds.length + ' experiențe selectate'"></strong>
                </div>
                <button @click="compareOpen = true" class="flex-none rounded-full bg-acid px-4 py-3 text-xs font-bold text-pine-950 sm:text-sm">Compară</button>
                <button @click="compareIds = []" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-white/15" aria-label="Golește comparația"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>
        </div>
    </div>

    <!-- Compare modal -->
    <div x-cloak x-show="compareOpen" class="fixed inset-0 z-[160]" role="dialog" aria-modal="true" aria-label="Compară experiențele">
        <div x-show="compareOpen" x-transition.opacity @click="compareOpen = false" class="absolute inset-0 bg-pine-950/[.78] backdrop-blur-md"></div>
        <div x-show="compareOpen" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-16 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-16 opacity-0" class="safe-bottom absolute inset-x-3 bottom-3 top-10 overflow-y-auto rounded-[28px] bg-cream sm:inset-x-6 sm:bottom-6 sm:top-16 lg:left-1/2 lg:w-[min(1120px,calc(100vw-48px))] lg:-translate-x-1/2">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-pine-900/10 bg-cream/[.94] px-5 py-4 backdrop-blur-xl sm:px-7">
                <div><span class="text-[9px] font-bold uppercase tracking-[.18em] text-ember">Comparație directă</span><h2 class="mt-1 font-display text-2xl font-semibold text-pine-950 sm:text-3xl">Alege traseul potrivit</h2></div>
                <button @click="compareOpen = false" class="grid h-11 w-11 place-items-center rounded-full border border-pine-900/15" aria-label="Închide"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>
            <div class="overflow-x-auto p-5 sm:p-7">
                <div class="grid min-w-[680px] gap-3" :style="`grid-template-columns: 150px repeat(${compareIds.length}, minmax(170px,1fr))`">
                    <div></div>
                    <template x-for="id in compareIds" :key="'head'+id"><div class="overflow-hidden rounded-[20px] bg-pine-900 text-white"><img :src="experienceById(id).image" :alt="experienceById(id).title" class="h-32 w-full object-cover"><div class="p-4"><h3 class="font-display text-xl font-semibold" x-text="experienceById(id).title"></h3><button @click="bookingOpen = true; compareOpen = false; selectedBookingOption = experienceById(id).booking" class="mt-3 rounded-full bg-acid px-3 py-2 text-xs font-bold text-pine-950">Rezervă</button></div></div></template>
                    <template x-for="row in compareRows" :key="row.key">
                        <template x-for="cell in [{label:row.label}, ...compareIds.map(id => ({value: experienceById(id)[row.key]}))]">
                            <div class="rounded-[16px] border border-pine-900/10 bg-white p-4 text-sm" :class="cell.label ? 'font-bold text-pine-950' : 'text-pine-900/65'" x-text="cell.label || cell.value"></div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking drawer -->
    <div x-cloak x-show="bookingOpen" class="fixed inset-0 z-[150]" role="dialog" aria-modal="true" aria-label="Rezervare rapidă">
        <div x-show="bookingOpen" x-transition.opacity @click="bookingOpen = false" class="absolute inset-0 bg-pine-950/[.72] backdrop-blur-sm"></div>
        <aside x-show="bookingOpen" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-350 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="drawer-shadow safe-bottom absolute right-0 top-0 flex h-full w-full max-w-[520px] flex-col overflow-y-auto bg-cream">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-pine-900/10 bg-cream/[.92] px-4 py-4 backdrop-blur-xl sm:px-6">
                <div><div class="text-[9px] font-bold uppercase tracking-[.18em] text-ember">Rezervare rapidă</div><h2 class="mt-1 font-display text-2xl font-semibold text-pine-950 sm:text-3xl">Construiește vizita</h2></div>
                <button @click="bookingOpen = false" class="grid h-11 w-11 place-items-center rounded-full border border-pine-900/[.12]" aria-label="Închide rezervarea"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>

            <div class="space-y-7 p-4 sm:p-6">
                <div>
                    <div class="mb-3 flex items-center justify-between"><label class="text-sm font-bold text-pine-950">1. Alege experiența</label><span class="text-xs text-pine-700" x-text="bookingOptions.find(x => x.id === selectedBookingOption)?.duration"></span></div>
                    <div class="grid gap-2">
                        <template x-for="option in bookingOptions" :key="option.id"><button @click="selectedBookingOption = option.id" class="flex items-center justify-between gap-4 rounded-[18px] border p-4 text-left transition" :class="selectedBookingOption === option.id ? 'border-pine-950 bg-pine-950 text-white' : 'border-pine-900/10 bg-white text-pine-950'"><span><strong class="block text-sm" x-text="option.name"></strong><span class="mt-1 block text-xs" :class="selectedBookingOption === option.id ? 'text-white/[.48]' : 'text-pine-900/[.48]'" x-text="option.caption"></span></span><strong class="whitespace-nowrap" :class="selectedBookingOption === option.id ? 'text-acid' : ''" x-text="option.price + ' lei'"></strong></button></template>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-bold text-pine-950">2. Data vizitei</label>
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        <template x-for="date in bookingDates" :key="date.id"><button @click="selectedDate = date.id" class="rounded-[16px] border px-2 py-3 text-center transition" :class="selectedDate === date.id ? 'border-ember bg-ember text-white' : 'border-pine-900/10 bg-white text-pine-950'"><span class="block text-[9px] font-bold uppercase tracking-[.12em]" x-text="date.day"></span><strong class="mt-1 block font-display text-xl" x-text="date.number"></strong></button></template>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-bold text-pine-950">3. Interval de intrare</label>
                    <div class="mt-3 grid grid-cols-3 gap-2"><template x-for="slot in timeSlots" :key="slot"><button @click="selectedTime = slot" class="rounded-full border px-3 py-2.5 text-xs font-bold transition" :class="selectedTime === slot ? 'border-pine-950 bg-pine-950 text-acid' : 'border-pine-900/10 bg-white text-pine-950'" x-text="slot"></button></template></div>
                </div>

                <div>
                    <label class="text-sm font-bold text-pine-950">4. Participanți</label>
                    <div class="mt-3 divide-y divide-pine-900/10 rounded-[20px] border border-pine-900/10 bg-white px-4"><template x-for="person in bookingPeople" :key="person.id"><div class="flex items-center justify-between py-4"><div><strong class="block text-sm" x-text="person.label"></strong><span class="mt-0.5 block text-xs text-pine-900/[.45]" x-text="person.caption"></span></div><div class="flex items-center gap-3"><button @click="person.count = Math.max(0, person.count - 1)" class="grid h-9 w-9 place-items-center rounded-full border border-pine-900/[.12]" aria-label="Scade numărul">−</button><strong class="w-4 text-center" x-text="person.count"></strong><button @click="person.count++" class="grid h-9 w-9 place-items-center rounded-full bg-pine-950 text-acid" aria-label="Crește numărul">+</button></div></div></template></div>
                </div>
            </div>

            <div class="sticky bottom-0 mt-auto border-t border-pine-900/10 bg-cream/[.94] p-4 backdrop-blur-xl sm:p-6">
                <div class="mb-4 flex items-end justify-between gap-4"><div><span class="block text-xs text-pine-900/[.45]" x-text="totalGuests + ' participanți' + (selectedTime ? ' · ' + selectedTime : '')"></span><strong class="mt-1 block text-sm">Total estimat</strong></div><div class="font-display text-3xl font-semibold text-pine-950" x-text="bookingTotal + ' lei'"></div></div>
                <button :disabled="totalGuests === 0" class="w-full rounded-full bg-acid px-5 py-4 font-bold text-pine-950 shadow-acid transition disabled:cursor-not-allowed disabled:opacity-45">Continuă către bilete</button>
            </div>
        </aside>
    </div>

    <script>
        function experiencesPage() {
            return {
                scrolled: false,
                menuOpen: false,
                bookingOpen: false,
                compareOpen: false,
                openFaq: null,
                activeCategory: 'all',
                ageFilter: 'all',
                sortBy: 'recommended',
                energy: 3,
                duration: 3,
                selectedGroup: 'friends',
                compareIds: [],
                selectedBookingOption: 'canopy',
                selectedDate: 'sat',
                selectedTime: '10:30',

                menuItems: [
                    { label: 'Experiențe', href: '#catalog' },
                    { label: 'Adventure Finder', href: '#finder' },
                    { label: 'Categorii', href: '#categorii' },
                    { label: 'Cum alegi', href: '#ghid' },
                    { label: 'Întrebări', href: '#faq' }
                ],

                groups: [
                    { id: 'family', icon: '◒', label: 'Familie' },
                    { id: 'friends', icon: '✦', label: 'Prieteni' },
                    { id: 'couple', icon: '∞', label: 'În doi' },
                    { id: 'solo', icon: '↗', label: 'Solo' }
                ],

                finderResults: {
                    family: {
                        1: { title: 'Little Rangers', description: 'Un traseu sigur, vizibil integral de la sol, construit pentru prima experiență de aventură a copiilor.', image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=84', tags: ['4+ ani', '45 min', 'ușor'], match: 96, category: 'family', booking: 'rangers' },
                        2: { title: 'Forest Quest', description: 'O explorare cu indicii, senzori și mici provocări fizice pentru familii care vor să descopere pădurea împreună.', image: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=84', tags: ['6+ ani', '90 min', 'moderat'], match: 94, category: 'family', booking: 'quest' },
                        3: { title: 'Family Expedition', description: 'Combină traseul junior, laboratorul pădurii și o tură ghidată într-un program echilibrat de jumătate de zi.', image: 'https://images.unsplash.com/photo-1504159506876-f8338247a14a?auto=format&fit=crop&w=1200&q=84', tags: ['6+ ani', '3 ore', 'mixt'], match: 98, category: 'family', booking: 'family' },
                        4: { title: 'Family Expedition', description: 'O zi completă în parc, cu activități la sol și la înălțime, ritm flexibil și pauze planificate.', image: 'https://images.unsplash.com/photo-1504159506876-f8338247a14a?auto=format&fit=crop&w=1200&q=84', tags: ['6+ ani', '5 ore', 'mixt'], match: 99, category: 'family', booking: 'family' }
                    },
                    friends: {
                        1: { title: 'Wildlife Walk', description: 'Un tur ghidat în zona protejată, cu observație discretă și povești despre ecosistem.', image: 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1200&q=84', tags: ['8+ ani', '75 min', 'liniștit'], match: 91, category: 'nature', booking: 'wildlife' },
                        2: { title: 'Ridge Trail', description: 'Un traseu dinamic, fără porțiuni extreme, bun pentru un grup cu niveluri diferite de experiență.', image: 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=84', tags: ['10+ ani', '2 ore', 'moderat'], match: 94, category: 'trails', booking: 'ridge' },
                        3: { title: 'Canopy Run', description: 'Poduri suspendate, două tiroliene și secțiuni tehnice progresive, fără să devină un test de anduranță.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1200&q=84', tags: ['12+ ani', '90 min', 'intens'], match: 98, category: 'heights', booking: 'canopy' },
                        4: { title: 'Black Pine Circuit', description: 'Cel mai tehnic traseu Nordvale, pentru grupuri care caută efort, înălțime și o doză serioasă de adrenalină.', image: 'https://images.unsplash.com/photo-1526481280695-3c687fd643ed?auto=format&fit=crop&w=1200&q=84', tags: ['16+ ani', '3 ore', 'expert'], match: 99, category: 'heights', booking: 'blackpine' }
                    },
                    couple: {
                        1: { title: 'Twilight Walk', description: 'O plimbare lentă, ghidată la apus, pe poteci liniștite și puncte de belvedere discrete.', image: 'https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=1200&q=84', tags: ['12+ ani', '75 min', 'liniștit'], match: 97, category: 'nature', booking: 'twilight' },
                        2: { title: 'Ridge Trail', description: 'Un traseu scenic, suficient de activ încât să fie memorabil, dar fără secțiuni extreme.', image: 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=84', tags: ['10+ ani', '2 ore', 'moderat'], match: 94, category: 'trails', booking: 'ridge' },
                        3: { title: 'Canopy Run', description: 'Un traseu intens, cu secțiuni în tandem și două tiroliene lungi printre coroanele copacilor.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1200&q=84', tags: ['12+ ani', '90 min', 'intens'], match: 96, category: 'heights', booking: 'canopy' },
                        4: { title: 'Sunset Expedition', description: 'O combinație de traseu la înălțime și tur la apus, rezervată unui număr mic de participanți.', image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=84', tags: ['14+ ani', '4 ore', 'premium'], match: 98, category: 'heights', booking: 'sunset' }
                    },
                    solo: {
                        1: { title: 'Wildlife Walk', description: 'Explorare ghidată în grup mic, potrivită pentru observație și fotografie de natură.', image: 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1200&q=84', tags: ['8+ ani', '75 min', 'liniștit'], match: 95, category: 'nature', booking: 'wildlife' },
                        2: { title: 'Forest Quest', description: 'O aventură individuală cu hartă, repere și provocări care te poartă prin mai multe zone ale parcului.', image: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=84', tags: ['10+ ani', '90 min', 'moderat'], match: 92, category: 'family', booking: 'quest' },
                        3: { title: 'Canopy Run', description: 'Un traseu progresiv pentru cei care vor să își testeze tehnica și să avanseze în propriul ritm.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1200&q=84', tags: ['12+ ani', '90 min', 'intens'], match: 97, category: 'heights', booking: 'canopy' },
                        4: { title: 'Black Pine Circuit', description: 'Circuit tehnic, cu briefing extins și monitorizare de la sol, destinat celor cu experiență.', image: 'https://images.unsplash.com/photo-1526481280695-3c687fd643ed?auto=format&fit=crop&w=1200&q=84', tags: ['16+ ani', '3 ore', 'expert'], match: 99, category: 'heights', booking: 'blackpine' }
                    }
                },

                categories: [
                    { id: 'heights', label: 'La înălțime', eyebrow: 'poduri · tiroliene', description: 'Trasee suspendate cu dificultate progresivă și sisteme continue de siguranță.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1000&q=84' },
                    { id: 'trails', label: 'Pe traseu', eyebrow: 'poteci · orientare', description: 'Explorări active pe teren mixt, cu repere, diferență de nivel și puncte panoramice.', image: 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1000&q=84' },
                    { id: 'family', label: 'Pentru familie', eyebrow: 'joacă · descoperire', description: 'Experiențe în care copiii și adulții participă împreună, fără presiunea performanței.', image: 'https://images.unsplash.com/photo-1504159506876-f8338247a14a?auto=format&fit=crop&w=1000&q=84' },
                    { id: 'nature', label: 'În rezervație', eyebrow: 'observație · ghidaj', description: 'Tururi lente și discrete, construite în jurul ecosistemului și al poveștilor locului.', image: 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1000&q=84' }
                ],

                experiences: [
                    { id: 'canopy', title: 'Canopy Run', description: 'Traseul emblematic Nordvale: poduri suspendate, două tiroliene și obstacole progresive la 8–14 metri.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1400&q=86', category: 'heights', age: 12, duration: '90 min', durationValue: 90, level: 'Intens', levelValue: 3, height: '14 m', price: 119, badge: 'Cel mai ales', featured: true, booking: 'canopy' },
                    { id: 'ridge', title: 'Ridge Trail', description: 'Potecă pe culme, porțiuni de orientare și două puncte panoramice deasupra văii.', image: 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=86', category: 'trails', age: 10, duration: '2 ore', durationValue: 120, level: 'Moderat', levelValue: 2, height: '420 m D+', price: 79, badge: '', featured: false, booking: 'ridge' },
                    { id: 'rangers', title: 'Little Rangers', description: 'Primul traseu la înălțime, cu obstacole joase, vizibilitate totală de la sol și ghid dedicat.', image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=86', category: 'family', age: 4, duration: '45 min', durationValue: 45, level: 'Ușor', levelValue: 1, height: '1.8 m', price: 49, badge: '4–7 ani', featured: false, booking: 'rangers' },
                    { id: 'wildlife', title: 'Wildlife Walk', description: 'Tur ghidat în rezervație, cu observatoare discrete și interpretarea urmelor animalelor.', image: 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1200&q=86', category: 'nature', age: 8, duration: '75 min', durationValue: 75, level: 'Liniștit', levelValue: 1, height: 'la sol', price: 55, badge: 'Grup mic', featured: false, booking: 'wildlife' },
                    { id: 'blackpine', title: 'Black Pine Circuit', description: 'Secțiuni tehnice, salturi controlate și cea mai lungă tiroliană din parc. Experiență anterioară recomandată.', image: 'https://images.unsplash.com/photo-1526481280695-3c687fd643ed?auto=format&fit=crop&w=1200&q=86', category: 'heights', age: 16, duration: '3 ore', durationValue: 180, level: 'Expert', levelValue: 4, height: '18 m', price: 179, badge: 'Locuri limitate', featured: false, booking: 'blackpine' },
                    { id: 'quest', title: 'Forest Quest', description: 'Hartă, indicii și provocări de echipă pe un traseu circular prin trei ecosisteme diferite.', image: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=86', category: 'family', age: 6, duration: '90 min', durationValue: 90, level: 'Moderat', levelValue: 2, height: 'la sol', price: 65, badge: '', featured: false, booking: 'quest' },
                    { id: 'twilight', title: 'Twilight Walk', description: 'O experiență lentă, programată la apus, cu acces în zone care nu sunt deschise în timpul zilei.', image: 'https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=1200&q=86', category: 'nature', age: 12, duration: '75 min', durationValue: 75, level: 'Liniștit', levelValue: 1, height: 'la sol', price: 89, badge: 'Doar vineri', featured: false, booking: 'twilight' },
                    { id: 'family', title: 'Family Expedition', description: 'Un program de jumătate de zi care combină traseu junior, laborator de natură și explorare ghidată.', image: 'https://images.unsplash.com/photo-1504159506876-f8338247a14a?auto=format&fit=crop&w=1200&q=86', category: 'family', age: 6, duration: '3 ore', durationValue: 180, level: 'Mixt', levelValue: 2, height: 'max. 6 m', price: 89, badge: 'Pachet', featured: false, booking: 'family' },
                    { id: 'summit', title: 'Summit Loop', description: 'Traseu de anduranță pe teren mixt, cu diferență de nivel și acces la cel mai înalt punct al rezervației.', image: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=86', category: 'trails', age: 14, duration: '4 ore', durationValue: 240, level: 'Intens', levelValue: 3, height: '690 m D+', price: 99, badge: '', featured: false, booking: 'summit' },
                    { id: 'sunset', title: 'Sunset Expedition', description: 'Canopy Run urmat de o oprire ghidată la belvedere, cu acces într-un interval rezervat grupurilor mici.', image: 'https://images.unsplash.com/photo-1464278533981-50106e6176b1?auto=format&fit=crop&w=1200&q=86', category: 'heights', age: 14, duration: '4 ore', durationValue: 240, level: 'Premium', levelValue: 3, height: '14 m', price: 229, badge: 'Ediție specială', featured: false, booking: 'sunset' },
                    { id: 'botany', title: 'Secret Botany', description: 'Tur interpretativ dedicat plantelor rare, adaptărilor lor și rolului pe care îl au în rezervație.', image: 'https://images.unsplash.com/photo-1497250681960-ef046c08a56e?auto=format&fit=crop&w=1200&q=86', category: 'nature', age: 10, duration: '2 ore', durationValue: 120, level: 'Liniștit', levelValue: 1, height: 'la sol', price: 69, badge: 'Cu specialist', featured: false, booking: 'botany' },
                    { id: 'night', title: 'Night Orientation', description: 'Orientare cu hartă și lumină frontală pe un traseu controlat, disponibil doar în anumite seri.', image: 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=86', category: 'trails', age: 16, duration: '2.5 ore', durationValue: 150, level: 'Intens', levelValue: 3, height: 'teren mixt', price: 129, badge: 'Nocturn', featured: false, booking: 'night' }
                ],

                criteria: [
                    { title: 'Vârsta minimă', description: 'Este o limită de siguranță, nu doar o recomandare. Unele trasee au și cerință minimă de înălțime.', tip: 'verifică exact' },
                    { title: 'Nivelul de efort', description: 'Ia în calcul rezistența întregului grup, nu doar experiența celui mai curajos participant.', tip: 'alege conservator' },
                    { title: 'Expunerea la înălțime', description: 'Un traseu moderat fizic poate fi dificil pentru cineva care nu se simte confortabil la înălțime.', tip: 'contează percepția' },
                    { title: 'Durata reală', description: 'Adaugă 30–45 de minute pentru briefing, echipare, pauze și deplasarea până la punctul de start.', tip: 'lasă timp' }
                ],

                faqs: [
                    { q: 'Pot schimba experiența după ce ajung în parc?', a: 'Da, în limita locurilor și a intervalelor disponibile. Dacă noua experiență are un preț mai mare, diferența se achită la punctul de acces.' },
                    { q: 'Pot participa dacă nu am mai făcut trasee la înălțime?', a: 'Da. Canopy Run este progresiv și începe cu un briefing complet. Pentru Black Pine Circuit recomandăm experiență anterioară.' },
                    { q: 'Există restricții de greutate sau înălțime?', a: 'Da, anumite instalații au limite tehnice. Acestea sunt afișate pe pagina fiecărei experiențe și verificate la echipare.' },
                    { q: 'Ce se întâmplă dacă vremea se schimbă?', a: 'Activitățile la sol continuă în ploaie ușoară. Traseele la înălțime pot fi suspendate temporar în caz de vânt puternic, descărcări electrice sau furtună.' }
                ],

                compareRows: [
                    { key: 'duration', label: 'Durată' },
                    { key: 'ageLabel', label: 'Vârstă minimă' },
                    { key: 'level', label: 'Intensitate' },
                    { key: 'height', label: 'Expunere' },
                    { key: 'priceLabel', label: 'Preț' }
                ],

                bookingOptions: [
                    { id: 'canopy', name: 'Canopy Run', caption: 'Traseu suspendat emblematic', duration: '90 min', price: 119 },
                    { id: 'ridge', name: 'Ridge Trail', caption: 'Potecă scenică pe culme', duration: '2 ore', price: 79 },
                    { id: 'rangers', name: 'Little Rangers', caption: 'Primul traseu pentru copii', duration: '45 min', price: 49 },
                    { id: 'wildlife', name: 'Wildlife Walk', caption: 'Tur ghidat în rezervație', duration: '75 min', price: 55 },
                    { id: 'blackpine', name: 'Black Pine Circuit', caption: 'Circuit tehnic avansat', duration: '3 ore', price: 179 },
                    { id: 'quest', name: 'Forest Quest', caption: 'Explorare cu indicii', duration: '90 min', price: 65 },
                    { id: 'family', name: 'Family Expedition', caption: 'Pachet de jumătate de zi', duration: '3 ore', price: 89 },
                    { id: 'twilight', name: 'Twilight Walk', caption: 'Tur ghidat la apus', duration: '75 min', price: 89 },
                    { id: 'summit', name: 'Summit Loop', caption: 'Traseu de anduranță', duration: '4 ore', price: 99 },
                    { id: 'sunset', name: 'Sunset Expedition', caption: 'Experiență premium la apus', duration: '4 ore', price: 229 },
                    { id: 'botany', name: 'Secret Botany', caption: 'Tur cu specialist', duration: '2 ore', price: 69 },
                    { id: 'night', name: 'Night Orientation', caption: 'Orientare nocturnă', duration: '2.5 ore', price: 129 }
                ],

                bookingDates: [
                    { id: 'sat', day: 'Sâm', number: '26' },
                    { id: 'sun', day: 'Dum', number: '27' },
                    { id: 'mon', day: 'Lun', number: '28' },
                    { id: 'tue', day: 'Mar', number: '29' }
                ],
                timeSlots: ['09:00', '10:30', '12:00', '14:00', '15:30', '17:00'],
                bookingPeople: [
                    { id: 'adult', label: 'Adult', caption: '18+ ani', count: 2 },
                    { id: 'teen', label: 'Junior', caption: '12–17 ani', count: 0 },
                    { id: 'child', label: 'Copil', caption: '4–11 ani', count: 0 }
                ],

                serverExperiences: <?php echo $expItemsJs; ?>,

                init() {
                    if (Array.isArray(this.serverExperiences) && this.serverExperiences.length) {
                        this.experiences = this.serverExperiences;
                    }
                    this.experiences = this.experiences.map(item => ({
                        ...item,
                        ageLabel: item.age + '+ ani',
                        priceLabel: item.price + ' lei'
                    }));
                    this.scrolled = window.scrollY > 24;
                    window.addEventListener('scroll', () => { this.scrolled = window.scrollY > 24; }, { passive: true });
                    this.$watch('menuOpen', value => document.body.style.overflow = value || this.bookingOpen || this.compareOpen ? 'hidden' : '');
                    this.$watch('bookingOpen', value => document.body.style.overflow = value || this.menuOpen || this.compareOpen ? 'hidden' : '');
                    this.$watch('compareOpen', value => document.body.style.overflow = value || this.menuOpen || this.bookingOpen ? 'hidden' : '');
                },

                get energyLabel() { return ['','Liniștit','Activ','Intens','Expert'][this.energy]; },
                get durationLabel() { return ['','Sub o oră','1–2 ore','Jumătate de zi','O zi'][this.duration]; },
                get finderResult() { return this.finderResults[this.selectedGroup][this.energy]; },
                get filteredExperiences() {
                    let list = this.experiences.filter(item => {
                        const categoryMatch = this.activeCategory === 'all' || item.category === this.activeCategory;
                        const ageMatch = this.ageFilter === 'all' || item.age <= Number(this.ageFilter);
                        return categoryMatch && ageMatch;
                    });
                    if (this.sortBy === 'easy') list = [...list].sort((a,b) => a.levelValue - b.levelValue);
                    if (this.sortBy === 'hard') list = [...list].sort((a,b) => b.levelValue - a.levelValue);
                    if (this.sortBy === 'duration') list = [...list].sort((a,b) => a.durationValue - b.durationValue);
                    return list;
                },
                get totalGuests() { return this.bookingPeople.reduce((sum, item) => sum + item.count, 0); },
                get bookingTotal() {
                    const option = this.bookingOptions.find(item => item.id === this.selectedBookingOption);
                    if (!option) return 0;
                    const adult = this.bookingPeople.find(item => item.id === 'adult')?.count || 0;
                    const teen = this.bookingPeople.find(item => item.id === 'teen')?.count || 0;
                    const child = this.bookingPeople.find(item => item.id === 'child')?.count || 0;
                    return adult * option.price + teen * Math.round(option.price * .82) + child * Math.round(option.price * .64);
                },

                toggleCompare(id) {
                    if (this.compareIds.includes(id)) {
                        this.compareIds = this.compareIds.filter(item => item !== id);
                    } else if (this.compareIds.length < 3) {
                        this.compareIds.push(id);
                    } else {
                        this.compareIds = [...this.compareIds.slice(1), id];
                    }
                },
                experienceById(id) { return this.experiences.find(item => item.id === id) || {}; },
                resetFilters() { this.activeCategory = 'all'; this.ageFilter = 'all'; this.sortBy = 'recommended'; }
            };
        }
    </script>

    <script type="module">
        import { animate, scroll, inView, stagger } from 'https://cdn.jsdelivr.net/npm/motion@12.42.1/+esm';

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.clearTimeout(window.__revealSafety);

        const revealEverything = () => {
            document.documentElement.classList.remove('reveal-ready');
            document.querySelectorAll('[data-reveal], [data-hero-kicker], [data-hero-title], [data-hero-copy], [data-hero-visual]').forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'none';
            });
        };

        if (!reduceMotion) {
            animate('[data-hero-kicker]', { opacity: [0, 1], y: [22, 0] }, { duration: .72, delay: .12, ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-title]', { opacity: [0, 1], y: [48, 0] }, { duration: 1.02, delay: stagger(.11, { startDelay: .2 }), ease: [0.16, 1, 0.3, 1] });
            animate('[data-hero-copy]', { opacity: [0, 1], y: [22, 0] }, { duration: .82, delay: stagger(.08, { startDelay: .58 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-visual]', { opacity: [0, 1], x: [58, 0], rotate: [1.2, 0] }, { duration: 1.16, delay: .42, ease: [0.16, 1, 0.3, 1] });

            document.querySelectorAll('[data-reveal]').forEach((element) => {
                inView(element, () => {
                    animate(element, { opacity: [0, 1], y: [34, 0] }, { duration: .82, ease: [0.22, 1, 0.36, 1] });
                }, { margin: '0px 0px -9% 0px', amount: .12 });
            });

            scroll(animate('#hero-image', { y: ['0%', '11%'], scale: [1.02, 1.1] }), { target: document.querySelector('#top'), offset: ['start start', 'end start'] });
            scroll(animate('#guide-image', { y: ['-4%', '8%'], scale: [1.06, 1.12] }), { target: document.querySelector('#ghid'), offset: ['start end', 'end start'] });

            const route = document.querySelector('#experience-route');
            const marker = document.querySelector('#experience-marker');
            if (window.anime && route && marker) {
                const path = anime.path('#experience-route');
                anime({ targets: '#experience-route', strokeDashoffset: [anime.setDashoffset, -240], easing: 'linear', duration: 17000, loop: true });
                anime({ targets: marker, translateX: path('x'), translateY: path('y'), rotate: path('angle'), easing: 'easeInOutSine', duration: 7200, loop: true, direction: 'alternate' });
            }

            if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
                document.querySelectorAll('.experience-card').forEach(card => {
                    const surface = card.querySelector('.experience-card__surface');
                    card.addEventListener('pointermove', event => {
                        const rect = card.getBoundingClientRect();
                        const x = (event.clientX - rect.left) / rect.width - .5;
                        const y = (event.clientY - rect.top) / rect.height - .5;
                        surface.style.transform = `rotateY(${x * 4.5}deg) rotateX(${-y * 4.5}deg) translateY(-4px)`;
                    });
                    card.addEventListener('pointerleave', () => { surface.style.transform = ''; });
                });
            }
        } else {
            revealEverything();
        }

        scroll(progress => {
            const line = document.querySelector('#page-progress');
            if (line) line.style.transform = `scaleX(${progress})`;
        });

        window.setTimeout(revealEverything, 3200);
    </script>
</body>
</html>
