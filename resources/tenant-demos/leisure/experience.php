<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$exp  = $slug !== '' ? tc_event($slug) : null;
if (! $exp) {
    $first = tc_events(api_get('/tenant-client/events', ['per_page' => 1]));
    $exp = $first[0] ?? null;
}

$expFallbackImg = 'https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=1800&q=90';
$expId    = $exp['id'] ?? null;
$expSlug  = $exp['slug'] ?? $slug;
$expTitle = ($exp && ! empty($exp['title'])) ? $exp['title'] : 'Canopy Run';
$expImg   = $exp ? asset_url($exp['hero_image_url'] ?? $exp['poster_url'] ?? null, $expFallbackImg) : $expFallbackImg;
$expVenue = ($exp && ! empty($exp['venue']['name'])) ? $exp['venue']['name'] : 'Nordvale';
$expTicketTypes = ($exp && ! empty($exp['ticket_types'])) ? $exp['ticket_types'] : [];

// people[] pentru selectorul de participanți: din ticket_types reale (fallback demo)
$expPeople = [];
foreach (array_values($expTicketTypes) as $i => $tt) {
    $expPeople[] = [
        'id'             => 'tt_' . ($tt['id'] ?? $i),
        'ticket_type_id' => $tt['id'] ?? null,
        'label'          => $tt['name'] ?? 'Bilet',
        'caption'        => price_fmt($tt['price'] ?? 0, $tt['currency'] ?? 'lei'),
        'count'          => $i === 0 ? 1 : 0,
        'min'            => 0,
        'price'          => (float) ($tt['price'] ?? 0),
    ];
}
$expPeopleJs = ! empty($expPeople)
    ? json_encode($expPeople, JSON_UNESCAPED_UNICODE)
    : "[
                    { id: 'adult', ticket_type_id: null, label: 'Adult', caption: '18+ ani', count: 2, min: 1, price: 119 },
                    { id: 'teen', ticket_type_id: null, label: 'Junior', caption: '12–17 ani', count: 0, min: 0, price: 99 }
                ]";
$expPriceFrom = ! empty($expTicketTypes) ? price_fmt($expTicketTypes[0]['price'] ?? 0, $expTicketTypes[0]['currency'] ?? 'lei') : '119 lei';
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#071d17">
    <title><?php echo e($expTitle); ?> — Nordvale</title>
    <meta name="description" content="Canopy Run — traseul suspendat emblematic Nordvale, cu 21 de obstacole și trei tiroliene deasupra pădurii.">

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
        button, a { -webkit-tap-highlight-color: transparent; }

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

        .hero-photo {
            clip-path: polygon(6% 0, 100% 0, 96% 88%, 72% 100%, 0 94%, 0 10%);
        }

        .outline-word {
            color: transparent;
            -webkit-text-stroke: 1px rgba(255,255,255,.46);
            text-stroke: 1px rgba(255,255,255,.46);
        }

        .route-line {
            fill: none;
            stroke: var(--acid);
            stroke-width: 2.3;
            stroke-linecap: round;
            stroke-dasharray: 9 13;
            filter: drop-shadow(0 0 8px rgba(223,252,98,.38));
        }

        .route-progress {
            fill: none;
            stroke: var(--acid);
            stroke-width: 5;
            stroke-linecap: round;
            filter: drop-shadow(0 0 8px rgba(223,252,98,.3));
        }

        .route-base {
            fill: none;
            stroke: rgba(255,255,255,.14);
            stroke-width: 5;
            stroke-linecap: round;
        }

        .map-node {
            transform-origin: center;
            animation: node-pulse 2.8s ease-in-out infinite;
        }
        @keyframes node-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.18); opacity: .72; }
        }

        .pulse-dot { animation: pulse-dot 2.2s ease-out infinite; }
        @keyframes pulse-dot {
            0% { box-shadow: 0 0 0 0 rgba(223,252,98,.48); }
            70% { box-shadow: 0 0 0 10px rgba(223,252,98,0); }
            100% { box-shadow: 0 0 0 0 rgba(223,252,98,0); }
        }

        .hero-meter {
            background: linear-gradient(90deg, var(--acid) 68%, rgba(255,255,255,.12) 68%);
        }

        .sticky-booking {
            background: rgba(255,253,246,.92);
            border: 1px solid rgba(9,37,29,.1);
            box-shadow: 0 32px 90px -48px rgba(6,26,21,.62);
            backdrop-filter: blur(22px) saturate(120%);
            -webkit-backdrop-filter: blur(22px) saturate(120%);
        }

        .date-card,
        .time-card,
        .option-card {
            transition: border-color .25s ease, background .25s ease, transform .25s ease, box-shadow .25s ease;
        }
        .date-card:hover,
        .time-card:hover,
        .option-card:hover { transform: translateY(-2px); }
        .date-card.is-active,
        .time-card.is-active,
        .option-card.is-active {
            border-color: var(--pine);
            background: var(--pine);
            color: white;
            box-shadow: 0 16px 38px -26px rgba(6,26,21,.85);
        }

        .segment-card {
            transition: transform .35s ease, border-color .35s ease, box-shadow .35s ease;
        }
        .segment-card.is-active {
            transform: translateY(-6px);
            border-color: rgba(223,252,98,.62);
            box-shadow: 0 24px 58px -38px rgba(223,252,98,.52);
        }

        .gallery-main { clip-path: polygon(0 0, 96% 0, 100% 89%, 88% 100%, 0 96%); }
        .gallery-small-a { clip-path: polygon(6% 0, 100% 0, 96% 100%, 0 92%); }
        .gallery-small-b { clip-path: polygon(0 0, 94% 4%, 100% 90%, 8% 100%); }

        .timeline-line {
            background: linear-gradient(180deg, var(--acid), rgba(9,37,29,.12));
        }

        .tilt-card { perspective: 1000px; transform-style: preserve-3d; }
        .tilt-card__surface {
            transform-style: preserve-3d;
            transition: transform .22s ease-out, box-shadow .35s ease, border-color .35s ease;
            will-change: transform;
        }
        .tilt-card:hover .tilt-card__surface {
            box-shadow: 0 36px 90px -48px rgba(6,26,21,.78);
            border-color: rgba(9,37,29,.24);
        }
        .tilt-card img { transition: transform .9s cubic-bezier(.18,.8,.22,1); }
        .tilt-card:hover img { transform: scale(1.055); }

        .magnetic { position: relative; overflow: hidden; }
        .magnetic::after {
            content: '';
            position: absolute;
            inset: -40%;
            background: radial-gradient(circle, rgba(255,255,255,.25), transparent 50%);
            opacity: 0;
            transform: scale(.3);
            transition: opacity .4s ease, transform .5s ease;
            pointer-events: none;
        }
        .magnetic:hover::after { opacity: 1; transform: scale(1); }

        .reveal-ready [data-reveal] { opacity: 0; transform: translateY(34px); }
        .reveal-ready [data-hero-kicker],
        .reveal-ready [data-hero-title],
        .reveal-ready [data-hero-copy],
        .reveal-ready [data-hero-visual] { opacity: 0; }
        .reveal-ready [data-hero-title] { transform: translateY(54px); }
        .reveal-ready [data-hero-copy], .reveal-ready [data-hero-kicker] { transform: translateY(22px); }
        .reveal-ready [data-hero-visual] { transform: translateX(58px) rotate(1.2deg); }

        .progress-line { transform: scaleX(0); transform-origin: left center; }
        .drawer-shadow { box-shadow: -50px 0 120px -60px rgba(6,26,21,.82); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        @media (max-width: 1023px) {
            .hero-photo { clip-path: polygon(0 0, 100% 0, 100% 94%, 10% 100%, 0 96%); }
        }

        @media (max-width: 639px) {
            .hero-photo,
            .gallery-main,
            .gallery-small-a,
            .gallery-small-b { clip-path: none; border-radius: 24px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
            .reveal-ready [data-reveal],
            .reveal-ready [data-hero-kicker],
            .reveal-ready [data-hero-title],
            .reveal-ready [data-hero-copy],
            .reveal-ready [data-hero-visual] {
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>

    <script>
        document.documentElement.classList.add('reveal-ready');
        window.__revealSafety = window.setTimeout(() => document.documentElement.classList.remove('reveal-ready'), 4200);
    </script>
</head>
<body x-data="experienceDetail()" x-init="init()" class="antialiased">
    <div id="page-progress" class="progress-line fixed left-0 top-0 z-[180] h-[3px] w-full bg-acid"></div>

    <?php $nvNav='experiente'; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

    <main>
        <!-- Hero -->
        <section id="top" class="grain topo-dark relative overflow-hidden bg-pine-950 pb-16 pt-28 text-white sm:pb-20 sm:pt-32 lg:min-h-[94svh] lg:pb-20 lg:pt-28">
            <div class="absolute inset-0 bg-gradient-to-b from-pine-950/0 via-pine-950/10 to-pine-950/90"></div>
            <div class="absolute -left-44 top-28 h-[500px] w-[500px] rounded-full border border-acid/10"></div>
            <div class="absolute -right-56 bottom-10 h-[640px] w-[640px] rounded-full border border-white/10"></div>

            <div class="relative z-10 mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8 xl:px-12">
                <div data-hero-kicker class="mb-5 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[.2em] text-white/[.58] sm:text-xs sm:tracking-[.22em]">
                    <a href="/experiente" class="transition hover:text-acid">Experiențe</a>
                    <span class="text-white/25">/</span>
                    <span class="text-acid">Trasee la înălțime</span>
                </div>

                <div class="grid gap-10 lg:grid-cols-[.88fr_1.12fr] lg:items-center lg:gap-14">
                    <div class="min-w-0 lg:pb-10">
                        <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.76] sm:px-4 sm:text-[10px] sm:tracking-[.24em]">
                            <span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span>
                            <span class="truncate">Experiența emblematică Nordvale</span>
                        </div>

                        <h1 class="font-display text-[clamp(58px,15vw,106px)] font-semibold leading-[.86] tracking-[-.06em] lg:text-[clamp(84px,8.8vw,142px)]">
                            <span data-hero-title class="block">Canopy</span>
                            <span data-hero-title class="outline-word block italic">Run.</span>
                        </h1>

                        <p data-hero-copy class="mt-7 max-w-xl text-[15px] leading-7 text-white/[.68] sm:text-lg sm:leading-8">
                            Un traseu suspendat printre coroanele pinilor, construit pentru momentul în care pădurea încetează să mai fie decor și devine terenul tău de explorare.
                        </p>

                        <div data-hero-copy class="mt-7 flex flex-wrap gap-2.5">
                            <span class="rounded-full border border-white/12 bg-white/[.06] px-3.5 py-2 text-xs font-semibold text-white/[.78]">21 obstacole</span>
                            <span class="rounded-full border border-white/12 bg-white/[.06] px-3.5 py-2 text-xs font-semibold text-white/[.78]">3 tiroliene</span>
                            <span class="rounded-full border border-white/12 bg-white/[.06] px-3.5 py-2 text-xs font-semibold text-white/[.78]">12+ ani</span>
                            <span class="rounded-full border border-white/12 bg-white/[.06] px-3.5 py-2 text-xs font-semibold text-white/[.78]">90 minute</span>
                        </div>

                        <div data-hero-copy class="mt-8 grid max-w-[590px] grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3">
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">Intensitate</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl">3 / 4</strong>
                            </div>
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">Altitudine</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl">18 m</strong>
                            </div>
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">Lungime</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl">640 m</strong>
                            </div>
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">De la</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl">119 lei</strong>
                            </div>
                        </div>

                        <div data-hero-copy class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <button @click="openBooking()" class="magnetic inline-flex min-h-[54px] items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-6 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:text-base">
                                Alege data și ora
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                            <a href="#poveste" class="inline-flex min-h-[54px] items-center justify-center rounded-full border border-white/20 px-6 text-sm font-semibold text-white transition hover:bg-white/10 sm:text-base">Descoperă traseul</a>
                        </div>
                    </div>

                    <div data-hero-visual class="relative min-h-[500px] min-w-0 sm:min-h-[650px] lg:min-h-[730px]">
                        <div class="hero-photo absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                            <img id="hero-image" src="https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=1800&q=88" alt="Participant pe un traseu suspendat între copaci" class="h-full w-full object-cover object-center">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/10 to-transparent"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-pine-950/30 via-transparent to-transparent"></div>
                        </div>

                        <div class="absolute left-4 top-5 z-20 rounded-[20px] border border-white/15 bg-pine-950/70 p-3.5 backdrop-blur-xl sm:left-7 sm:top-8 sm:p-4">
                            <div class="flex items-center gap-2 text-[9px] font-bold uppercase tracking-[.18em] text-acid sm:text-[10px]"><span class="pulse-dot h-2 w-2 rounded-full bg-acid"></span> Traseu activ</div>
                            <p class="mt-2 text-xs text-white/[.62] sm:text-sm">Ultimul acces · 18:00</p>
                        </div>

                        <div class="absolute bottom-5 left-4 right-4 z-20 rounded-[24px] border border-white/15 bg-pine-950/75 p-4 backdrop-blur-xl sm:bottom-8 sm:left-8 sm:right-auto sm:w-[360px] sm:p-5">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <span class="text-[9px] font-bold uppercase tracking-[.2em] text-white/[.42]">Nivel de provocare</span>
                                    <p class="mt-1 font-display text-2xl font-semibold">Activ · tehnic</p>
                                </div>
                                <strong class="font-display text-3xl text-acid">68%</strong>
                            </div>
                            <div class="hero-meter mt-4 h-1.5 rounded-full"></div>
                            <p class="mt-3 text-xs leading-5 text-white/[.54]">Nu necesită experiență anterioară. Este necesară mobilitate bună și confort la înălțime.</p>
                        </div>

                        <svg class="pointer-events-none absolute right-4 top-14 z-20 hidden h-[270px] w-[230px] overflow-visible sm:block lg:right-7 lg:top-16 lg:h-[330px] lg:w-[285px]" viewBox="0 0 285 330" fill="none" aria-hidden="true">
                            <path id="hero-route" class="route-line" d="M28 280C48 230 95 250 104 198c9-50-40-60-10-105 30-44 85-7 112-50 18-28 25-30 51-18"/>
                            <g fill="#DFFC62">
                                <circle cx="28" cy="280" r="5" class="map-node"/>
                                <circle cx="104" cy="198" r="5" class="map-node" style="animation-delay:.5s"/>
                                <circle cx="94" cy="93" r="5" class="map-node" style="animation-delay:1s"/>
                                <circle cx="257" cy="25" r="5" class="map-node" style="animation-delay:1.5s"/>
                            </g>
                            <g id="hero-marker">
                                <circle r="12" fill="#061A15" stroke="#DFFC62" stroke-width="2"/>
                                <path d="m-3-4 8 4-8 4v-8Z" fill="#DFFC62"/>
                            </g>
                        </svg>

                        <div class="absolute -bottom-2 right-4 z-20 rotate-[-3deg] rounded-[18px] bg-ember px-4 py-3 text-pine-950 shadow-ember sm:bottom-4 sm:right-8 sm:px-5 sm:py-4">
                            <span class="block text-[9px] font-bold uppercase tracking-[.18em]">Recomandat</span>
                            <strong class="font-display text-xl sm:text-2xl">pentru 12–55 ani</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick booking / trust strip -->
        <section class="relative z-20 -mt-1 bg-oat px-4 pb-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-3 rounded-[28px] border border-pine-900/10 bg-cream p-3 shadow-card sm:grid-cols-2 sm:p-4 lg:grid-cols-[1.2fr_.8fr_.8fr_.8fr]">
                <div class="flex items-center gap-4 rounded-[20px] bg-pine-900 px-4 py-4 text-white sm:px-5">
                    <span class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid text-pine-950">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 1 1-16 0V5l8-3 8 3v6Z"/><path d="m9 11 2 2 4-4"/></svg>
                    </span>
                    <div><strong class="block text-sm">Sistem continuu de siguranță</strong><span class="mt-1 block text-xs text-white/50">Nu te poți desprinde accidental de cablu</span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <div><strong class="block text-sm">90 minute</strong><span class="text-xs text-pine-900/48">briefing inclus</span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 18h16M6 18l3-8 3 5 3-10 3 13"/></svg>
                    <div><strong class="block text-sm">18 metri</strong><span class="text-xs text-pine-900/48">punct maxim</span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12H3V7a2 2 0 0 1 2-2Z"/></svg>
                    <div><strong class="block text-sm">Rezervare flexibilă</strong><span class="text-xs text-pine-900/48">schimbare cu 24h înainte</span></div>
                </div>
            </div>
        </section>

        <!-- Story + desktop booking -->
        <section id="poveste" class="paper-grid relative overflow-hidden px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-14 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start lg:gap-16 xl:grid-cols-[minmax(0,1fr)_430px]">
                <div class="min-w-0">
                    <div data-reveal class="max-w-3xl">
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Despre experiență</p>
                        <h2 class="mt-4 font-display text-[clamp(42px,8vw,80px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">
                            Pădurea, văzută de la <span class="italic text-pine-600">nivelul coroanelor.</span>
                        </h2>
                        <p class="mt-7 max-w-2xl text-base leading-8 text-pine-900/66 sm:text-lg sm:leading-9">
                            Canopy Run pornește lent, aproape de sol, apoi urcă progresiv prin patru sectoare. Fiecare platformă deschide o perspectivă nouă asupra rezervației, iar ultimele trei tiroliene traversează valea în sensuri diferite.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="feature in features" :key="feature.title">
                            <article data-reveal class="rounded-[24px] border border-pine-900/10 bg-cream p-5 shadow-card sm:p-6">
                                <span class="grid h-11 w-11 place-items-center rounded-full bg-pine-900 text-acid" x-html="feature.icon"></span>
                                <h3 class="mt-5 font-display text-2xl font-semibold text-pine-950" x-text="feature.title"></h3>
                                <p class="mt-3 text-sm leading-6 text-pine-900/58" x-text="feature.copy"></p>
                            </article>
                        </template>
                    </div>

                    <div class="mt-16 grid gap-4 lg:grid-cols-[1.25fr_.75fr]">
                        <div data-reveal class="gallery-main relative min-h-[430px] overflow-hidden bg-pine-900 shadow-lift sm:min-h-[560px]">
                            <img id="story-image" src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=86" alt="Pădure văzută de sus" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950/80 via-transparent to-transparent"></div>
                            <div class="absolute bottom-0 left-0 right-0 p-6 text-white sm:p-8">
                                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-acid">Sectorul 03 · High Canopy</p>
                                <h3 class="mt-2 max-w-md font-display text-3xl font-semibold sm:text-4xl">Aici traseul trece deasupra ravenei nordice.</h3>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <button @click="openGallery(1)" data-reveal class="gallery-small-a group relative min-h-[230px] overflow-hidden text-left sm:min-h-[280px] lg:min-h-0">
                                <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1000&q=84" alt="Tiroliană printre copaci" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                <span class="absolute bottom-5 left-5 rounded-full bg-cream px-3 py-2 text-xs font-bold text-pine-950">Vezi galeria</span>
                            </button>
                            <button @click="openGallery(2)" data-reveal class="gallery-small-b group relative min-h-[230px] overflow-hidden text-left sm:min-h-[280px] lg:min-h-0">
                                <img src="https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1000&q=84" alt="Munți și pădure" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                <span class="absolute bottom-5 left-5 text-xs font-bold uppercase tracking-[.18em] text-white">+ 8 fotografii</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop booking card -->
                <aside class="hidden lg:block">
                    <div class="sticky-booking sticky top-28 rounded-[28px] p-5 xl:p-6">
                        <div class="flex items-start justify-between gap-5">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervă Canopy Run</p>
                                <div class="mt-2 flex items-end gap-2"><strong class="font-display text-4xl text-pine-950"><?php echo e($expPriceFrom); ?></strong><span class="pb-1 text-xs text-pine-900/50">/ bilet</span></div>
                            </div>
                            <span class="rounded-full bg-acid px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.14em] text-pine-950">disponibil</span>
                        </div>

                        <div class="mt-6">
                            <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">1. Data</span><button class="text-xs font-semibold text-pine-700">Iulie 2026</button></div>
                            <div class="grid grid-cols-4 gap-2">
                                <template x-for="date in dates" :key="date.id">
                                    <button @click="selectedDate = date.id" :class="selectedDate === date.id ? 'is-active' : ''" class="date-card rounded-[16px] border border-pine-900/10 px-2 py-3 text-center">
                                        <span class="block text-[9px] font-bold uppercase tracking-[.12em] opacity-55" x-text="date.day"></span>
                                        <strong class="mt-1 block font-display text-xl" x-text="date.number"></strong>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">2. Ora</span><span class="text-[10px] font-semibold text-pine-900/45">6 intervale</span></div>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="slot in timeSlots" :key="slot.time">
                                    <button @click="!slot.soldOut && (selectedTime = slot.time)" :disabled="slot.soldOut" :class="selectedTime === slot.time ? 'is-active' : ''" class="time-card rounded-[14px] border border-pine-900/10 px-2 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-35">
                                        <span x-text="slot.time"></span>
                                        <span x-show="slot.low" class="mt-0.5 block text-[8px] uppercase tracking-[.1em] text-ember">ultimele locuri</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="mt-5 space-y-2.5">
                            <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">3. Participanți</span><span class="text-xs text-pine-900/45" x-text="totalGuests + ' persoane'"></span></div>
                            <template x-for="person in people" :key="person.id">
                                <div class="flex items-center justify-between rounded-[16px] border border-pine-900/10 px-3 py-3">
                                    <div><strong class="block text-sm" x-text="person.label"></strong><span class="text-[10px] text-pine-900/45" x-text="person.caption"></span></div>
                                    <div class="flex items-center gap-2">
                                        <button @click="person.count = Math.max(person.min, person.count - 1)" class="grid h-8 w-8 place-items-center rounded-full border border-pine-900/15" aria-label="Scade numărul"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg></button>
                                        <strong class="w-5 text-center text-sm" x-text="person.count"></strong>
                                        <button @click="person.count = Math.min(8, person.count + 1)" class="grid h-8 w-8 place-items-center rounded-full bg-pine-900 text-acid" aria-label="Crește numărul"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-5 flex items-center justify-between border-t border-pine-900/10 pt-5">
                            <div><span class="block text-[10px] uppercase tracking-[.15em] text-pine-900/45">Total estimat</span><strong class="font-display text-3xl text-pine-950" x-text="bookingTotal + ' lei'"></strong></div>
                            <span class="text-right text-[10px] leading-4 text-pine-900/42">Taxe incluse<br>Plată securizată</span>
                        </div>

                        <button @click="addToCart()" class="magnetic mt-5 inline-flex min-h-[54px] w-full items-center justify-center gap-2 rounded-full bg-pine-900 px-5 font-bold text-white transition hover:-translate-y-1">
                            Continuă rezervarea
                            <svg class="h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                        <p class="mt-3 text-center text-[10px] leading-4 text-pine-900/42">Locurile nu sunt blocate până la continuarea comenzii.</p>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Route anatomy -->
        <section id="ruta" class="grain topo-dark relative overflow-hidden bg-pine-950 px-4 py-20 text-white sm:px-6 sm:py-28 lg:px-8">
            <div class="absolute -left-48 top-10 h-[520px] w-[520px] rounded-full border border-acid/10"></div>
            <div class="relative z-10 mx-auto max-w-[1420px]">
                <div class="grid gap-10 lg:grid-cols-[.72fr_1.28fr] lg:items-end">
                    <div data-reveal>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid sm:text-xs">Anatomia traseului</p>
                        <h2 class="mt-4 max-w-2xl font-display text-[clamp(44px,8vw,82px)] font-semibold leading-[.92] tracking-[-.048em]">Patru sectoare. <span class="outline-word italic">Un singur flux.</span></h2>
                    </div>
                    <p data-reveal class="max-w-2xl text-base leading-8 text-white/[.62] sm:text-lg">Traseul crește controlat în dificultate. Poți abandona în siguranță după primele două sectoare, iar ghizii urmăresc permanent progresul grupului.</p>
                </div>

                <div class="mt-14 grid gap-8 lg:grid-cols-[1.08fr_.92fr] lg:items-stretch">
                    <div data-reveal class="relative min-h-[520px] overflow-hidden rounded-[30px] border border-white/10 bg-pine-900 p-4 sm:min-h-[610px] sm:p-6">
                        <div class="absolute inset-0 opacity-50 paper-grid"></div>
                        <svg class="relative z-10 h-full w-full" viewBox="0 0 760 590" fill="none" aria-label="Harta schematică a traseului">
                            <path class="route-base" d="M76 503C109 431 159 438 204 384c47-57 15-116 80-145 61-28 102 28 160-13 57-41 44-105 109-131 52-21 95-5 136-38"/>
                            <path id="route-progress-path" class="route-progress" d="M76 503C109 431 159 438 204 384c47-57 15-116 80-145 61-28 102 28 160-13 57-41 44-105 109-131 52-21 95-5 136-38"/>

                            <g fill="#DFFC62" stroke="#061A15" stroke-width="6">
                                <circle cx="76" cy="503" r="13"/>
                                <circle cx="204" cy="384" r="13"/>
                                <circle cx="444" cy="226" r="13"/>
                                <circle cx="689" cy="57" r="13"/>
                            </g>
                            <g font-family="DM Sans" font-size="14" font-weight="700" fill="#FFFDF6">
                                <text x="92" y="510">Start</text>
                                <text x="220" y="389">Bridge Lab</text>
                                <text x="460" y="231">High Canopy</text>
                                <text x="584" y="48">Final Flight</text>
                            </g>
                            <g opacity=".55" fill="#FFFDF6" font-family="DM Sans" font-size="11">
                                <text x="94" y="530">4 m</text>
                                <text x="222" y="409">9 m</text>
                                <text x="462" y="251">18 m</text>
                                <text x="646" y="79">12 m</text>
                            </g>
                        </svg>

                        <div class="absolute bottom-5 left-5 right-5 z-20 flex items-center justify-between rounded-[20px] border border-white/10 bg-pine-950/80 p-4 backdrop-blur-xl sm:left-7 sm:right-7">
                            <div><span class="block text-[9px] font-bold uppercase tracking-[.18em] text-acid">Sector selectat</span><strong class="mt-1 block font-display text-xl" x-text="activeSegment.title"></strong></div>
                            <div class="text-right"><strong class="block font-display text-2xl text-acid" x-text="activeSegment.height"></strong><span class="text-[10px] text-white/45">înălțime maximă</span></div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <template x-for="segment in segments" :key="segment.id">
                            <button @click="activeSegmentId = segment.id" :class="activeSegmentId === segment.id ? 'is-active bg-white/[.08]' : 'bg-white/[.045]'" class="segment-card rounded-[24px] border border-white/10 p-5 text-left sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-acid font-display text-xl font-semibold text-pine-950" x-text="segment.number"></span>
                                    <span class="rounded-full border border-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.14em] text-white/55" x-text="segment.level"></span>
                                </div>
                                <h3 class="mt-5 font-display text-2xl font-semibold" x-text="segment.title"></h3>
                                <p class="mt-2 text-sm leading-6 text-white/[.55]" x-text="segment.copy"></p>
                                <div class="mt-5 flex items-center gap-5 text-xs text-white/45"><span x-text="segment.length"></span><span x-text="segment.obstacles"></span></div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Journey timeline -->
        <section class="bg-cream px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto max-w-[1180px]">
                <div data-reveal class="mx-auto max-w-3xl text-center">
                    <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Cum se desfășoară</p>
                    <h2 class="mt-4 font-display text-[clamp(42px,8vw,76px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">De la echipare la <span class="italic text-pine-600">ultima tiroliană.</span></h2>
                </div>

                <div class="relative mt-16">
                    <div class="timeline-line absolute bottom-10 left-[21px] top-10 w-px sm:left-1/2 sm:-translate-x-1/2"></div>
                    <div class="space-y-8 sm:space-y-12">
                        <template x-for="(step, index) in journey" :key="step.title">
                            <article data-reveal class="relative grid gap-5 pl-16 sm:grid-cols-2 sm:gap-16 sm:pl-0">
                                <div :class="index % 2 === 0 ? 'sm:pr-10 sm:text-right' : 'sm:order-2 sm:pl-10'">
                                    <span class="text-[10px] font-bold uppercase tracking-[.18em] text-pine-700" x-text="step.time"></span>
                                    <h3 class="mt-2 font-display text-3xl font-semibold text-pine-950" x-text="step.title"></h3>
                                    <p class="mt-3 text-sm leading-7 text-pine-900/58" x-text="step.copy"></p>
                                </div>
                                <div :class="index % 2 === 0 ? 'sm:order-2 sm:pl-10' : 'sm:pr-10'" class="hidden sm:block"></div>
                                <span class="absolute left-0 top-1 grid h-11 w-11 place-items-center rounded-full border-[7px] border-cream bg-pine-900 font-display text-lg text-acid shadow-card sm:left-1/2 sm:-translate-x-1/2" x-text="index + 1"></span>
                            </article>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Safety requirements -->
        <section id="siguranta" class="paper-grid bg-oat px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-10 lg:grid-cols-[.95fr_1.05fr] lg:items-center lg:gap-16">
                <div data-reveal class="relative min-h-[520px] overflow-hidden rounded-[30px] bg-pine-900 shadow-lift sm:min-h-[650px]">
                    <img id="safety-image" src="https://images.unsplash.com/photo-1486911278844-a81c5267e227?auto=format&fit=crop&w=1500&q=86" alt="Echipament de siguranță pentru traseu" class="absolute inset-0 h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-transparent to-pine-950/15"></div>
                    <div class="absolute bottom-5 left-5 right-5 rounded-[24px] border border-white/15 bg-pine-950/72 p-5 text-white backdrop-blur-xl sm:bottom-8 sm:left-8 sm:right-8 sm:p-6">
                        <div class="flex items-start gap-4">
                            <span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-acid text-pine-950">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 1 1-16 0V5l8-3 8 3v6Z"/><path d="m9 11 2 2 4-4"/></svg>
                            </span>
                            <div><h3 class="font-display text-2xl font-semibold">Echipamentul este inclus.</h3><p class="mt-2 text-sm leading-6 text-white/[.58]">Ham complet, cască, mănuși și sistem inteligent de asigurare. Fiecare set este verificat înaintea fiecărei utilizări.</p></div>
                        </div>
                    </div>
                </div>

                <div>
                    <div data-reveal>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Siguranță și acces</p>
                        <h2 class="mt-4 font-display text-[clamp(42px,7vw,72px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Curajul este opțional. <span class="italic text-pine-600">Regulile nu.</span></h2>
                        <p class="mt-6 max-w-xl text-base leading-8 text-pine-900/64">Canopy Run este operat după un protocol clar: verificare fizică, briefing, test la sol și supraveghere pe traseu.</p>
                    </div>

                    <div class="mt-9 grid gap-3 sm:grid-cols-2">
                        <template x-for="requirement in requirements" :key="requirement.title">
                            <div data-reveal class="rounded-[22px] border border-pine-900/10 bg-cream p-5">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 grid h-7 w-7 flex-none place-items-center rounded-full bg-pine-900 text-acid">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg>
                                    </span>
                                    <div><strong class="block text-sm" x-text="requirement.title"></strong><p class="mt-1 text-xs leading-5 text-pine-900/48" x-text="requirement.copy"></p></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div data-reveal class="mt-6 rounded-[24px] border border-ember/25 bg-ember/10 p-5 sm:p-6">
                        <div class="flex items-start gap-4">
                            <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-ember text-pine-950">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.8 2.9 17a2 2 0 0 0 1.8 3h14.6a2 2 0 0 0 1.8-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>
                            </span>
                            <div><strong class="block text-sm">Accesul poate fi suspendat temporar</strong><p class="mt-1 text-xs leading-5 text-pine-900/58">În caz de vânt puternic, furtună sau descărcări electrice, starturile sunt amânate sau mutate gratuit.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reviews -->
        <section class="grain topo-dark relative overflow-hidden bg-pine-950 px-4 py-20 text-white sm:px-6 sm:py-28 lg:px-8">
            <div class="relative z-10 mx-auto max-w-[1420px]">
                <div class="grid gap-8 lg:grid-cols-[.72fr_1.28fr] lg:items-end">
                    <div data-reveal>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid sm:text-xs">După traseu</p>
                        <h2 class="mt-4 font-display text-[clamp(44px,8vw,80px)] font-semibold leading-[.92] tracking-[-.048em]">Oamenii își amintesc <span class="outline-word italic">ultimul zbor.</span></h2>
                    </div>
                    <div data-reveal class="flex flex-wrap items-center gap-5 lg:justify-end">
                        <div><strong class="font-display text-5xl text-acid">4.9</strong><span class="ml-2 text-sm text-white/45">din 5</span></div>
                        <div class="flex gap-1 text-acid" aria-label="5 stele">★★★★★</div>
                        <span class="text-xs text-white/40">din 386 de evaluări verificate</span>
                    </div>
                </div>

                <div class="mt-12 grid gap-4 lg:grid-cols-3">
                    <template x-for="review in reviews" :key="review.name">
                        <article data-reveal class="rounded-[26px] border border-white/10 bg-white/[.055] p-6 sm:p-7">
                            <div class="flex gap-1 text-xs text-acid">★★★★★</div>
                            <p class="mt-5 font-display text-2xl leading-9" x-text="'„' + review.quote + '”'"></p>
                            <div class="mt-7 flex items-center justify-between border-t border-white/10 pt-5">
                                <div><strong class="block text-sm" x-text="review.name"></strong><span class="text-xs text-white/40" x-text="review.group"></span></div>
                                <span class="rounded-full border border-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.14em] text-white/45">verificat</span>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- Related -->
        <section class="bg-oat px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto max-w-[1420px]">
                <div data-reveal class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Mai departe</p>
                        <h2 class="mt-4 font-display text-[clamp(42px,7vw,72px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Experiențe în același <span class="italic text-pine-600">ritm.</span></h2>
                    </div>
                    <a href="/experiente" class="inline-flex items-center gap-2 text-sm font-bold text-pine-900">Vezi toate experiențele <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <template x-for="item in related" :key="item.title">
                        <a href="#" data-reveal class="tilt-card group block">
                            <article class="tilt-card__surface overflow-hidden rounded-[28px] border border-pine-900/10 bg-cream shadow-card">
                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <img :src="item.image" :alt="item.title" class="h-full w-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                    <span class="absolute left-4 top-4 rounded-full bg-cream px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.14em] text-pine-950" x-text="item.badge"></span>
                                    <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between gap-4 text-white">
                                        <h3 class="font-display text-3xl font-semibold" x-text="item.title"></h3>
                                        <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-acid text-pine-950 transition group-hover:rotate-45"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4 p-5">
                                    <div class="flex gap-4 text-xs text-pine-900/48"><span x-text="item.duration"></span><span x-text="item.age"></span></div>
                                    <strong class="font-display text-xl text-pine-950" x-text="item.price"></strong>
                                </div>
                            </article>
                        </a>
                    </template>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="bg-cream px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1180px] gap-12 lg:grid-cols-[.68fr_1.32fr] lg:gap-16">
                <div data-reveal>
                    <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Înainte să rezervi</p>
                    <h2 class="mt-4 font-display text-[clamp(42px,7vw,68px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Întrebări <span class="italic text-pine-600">firești.</span></h2>
                    <p class="mt-6 text-sm leading-7 text-pine-900/56">Pentru situații medicale, accesibilitate sau grupuri speciale, discută cu echipa înainte de rezervare.</p>
                    <a href="#" class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-pine-900">Contactează echipa <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>

                <div class="divide-y divide-pine-900/10 border-y border-pine-900/10">
                    <template x-for="(item, index) in faqs" :key="item.q">
                        <article data-reveal>
                            <button @click="faqOpen = faqOpen === index ? null : index" class="flex w-full items-center justify-between gap-5 py-5 text-left sm:py-6">
                                <span class="font-display text-xl font-semibold text-pine-950 sm:text-2xl" x-text="item.q"></span>
                                <span class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/15 transition" :class="faqOpen === index ? 'rotate-45 bg-pine-900 text-acid' : ''"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
                            </button>
                            <div x-show="faqOpen === index" x-collapse><p class="max-w-3xl pb-6 pr-12 text-sm leading-7 text-pine-900/58" x-text="item.a"></p></div>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="grain topo-dark relative overflow-hidden bg-pine-950 px-4 py-16 text-white sm:px-6 sm:py-20 lg:px-8">
            <div class="relative z-10 mx-auto flex max-w-[1420px] flex-col gap-8 rounded-[30px] border border-white/10 bg-white/[.045] p-6 sm:p-10 lg:flex-row lg:items-center lg:justify-between lg:p-12">
                <div data-reveal>
                    <p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid sm:text-xs">Canopy Run</p>
                    <h2 class="mt-3 max-w-3xl font-display text-[clamp(38px,6vw,68px)] font-semibold leading-[.94] tracking-[-.045em]">Următoarea platformă începe cu o <span class="outline-word italic">rezervare.</span></h2>
                </div>
                <div data-reveal class="flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
                    <button @click="openBooking()" class="magnetic inline-flex min-h-[56px] items-center justify-center whitespace-nowrap rounded-full bg-acid px-7 font-bold text-pine-950 shadow-acid transition hover:-translate-y-1">Alege data și ora</button>
                    <a href="/experiente" class="inline-flex min-h-[56px] items-center justify-center whitespace-nowrap rounded-full border border-white/20 px-7 font-semibold text-white transition hover:bg-white/10">Înapoi la experiențe</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Mobile sticky CTA -->
    <div class="safe-bottom fixed inset-x-0 bottom-0 z-[110] border-t border-pine-900/10 bg-cream/95 px-3 pb-3 pt-3 shadow-[0_-18px_55px_-35px_rgba(6,26,21,.65)] backdrop-blur-xl lg:hidden">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1 pl-1"><span class="block text-[9px] font-bold uppercase tracking-[.15em] text-pine-900/42"><?php echo e($expTitle); ?> · de la</span><strong class="block truncate font-display text-2xl text-pine-950"><?php echo e($expPriceFrom); ?></strong></div>
            <button @click="openBooking()" class="inline-flex min-h-[50px] flex-none items-center justify-center whitespace-nowrap rounded-full bg-pine-900 px-5 text-sm font-bold text-white"><span>Rezervă</span><svg class="ml-2 h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </div>

    <!-- Booking drawer / mobile bottom sheet -->
    <div x-cloak x-show="bookingOpen" class="fixed inset-0 z-[150]" role="dialog" aria-modal="true" aria-label="Rezervă Canopy Run">
        <div x-show="bookingOpen" x-transition.opacity @click="bookingOpen = false" class="absolute inset-0 bg-pine-950/70 backdrop-blur-sm"></div>
        <aside x-show="bookingOpen" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-full lg:translate-x-full lg:translate-y-0" x-transition:enter-end="translate-y-0 lg:translate-x-0" x-transition:leave="transition duration-350 ease-in" x-transition:leave-start="translate-y-0 lg:translate-x-0" x-transition:leave-end="translate-y-full lg:translate-x-full lg:translate-y-0" class="drawer-shadow safe-bottom absolute bottom-0 left-0 right-0 max-h-[92svh] overflow-y-auto rounded-t-[28px] bg-cream p-4 sm:p-6 lg:bottom-0 lg:left-auto lg:top-0 lg:h-full lg:max-h-none lg:w-[min(92vw,520px)] lg:rounded-none lg:p-8">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-pine-900/15 lg:hidden"></div>
            <div class="flex items-start justify-between gap-5">
                <div><p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervare rapidă</p><h2 class="mt-2 font-display text-3xl font-semibold text-pine-950 sm:text-4xl">Canopy Run</h2><p class="mt-2 text-sm text-pine-900/50">90 min · 12+ ani · traseu activ</p></div>
                <button @click="bookingOpen = false" class="grid h-11 w-11 flex-none place-items-center rounded-full border border-pine-900/15" aria-label="Închide rezervarea"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>

            <div class="mt-7">
                <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">1. Alege data</span><span class="text-xs font-semibold text-pine-700">Iulie 2026</span></div>
                <div class="scrollbar-hide flex gap-2 overflow-x-auto pb-1">
                    <template x-for="date in drawerDates" :key="date.id">
                        <button @click="selectedDate = date.id" :class="selectedDate === date.id ? 'is-active' : ''" class="date-card min-w-[72px] flex-none rounded-[16px] border border-pine-900/10 px-3 py-3 text-center">
                            <span class="block text-[9px] font-bold uppercase tracking-[.12em] opacity-55" x-text="date.day"></span>
                            <strong class="mt-1 block font-display text-xl" x-text="date.number"></strong>
                            <span class="mt-1 block text-[8px] opacity-55" x-text="date.label"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">2. Alege ora</span><span class="text-[10px] text-pine-900/42">start la fiecare 90 min</span></div>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="slot in timeSlots" :key="slot.time">
                        <button @click="!slot.soldOut && (selectedTime = slot.time)" :disabled="slot.soldOut" :class="selectedTime === slot.time ? 'is-active' : ''" class="time-card rounded-[14px] border border-pine-900/10 px-2 py-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-35">
                            <span x-text="slot.time"></span><span x-show="slot.low" class="mt-0.5 block text-[8px] uppercase tracking-[.1em] text-ember">ultimele locuri</span><span x-show="slot.soldOut" class="mt-0.5 block text-[8px] uppercase tracking-[.1em]">complet</span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-6 space-y-2.5">
                <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">3. Participanți</span><span class="text-xs text-pine-900/45" x-text="totalGuests + ' persoane'"></span></div>
                <template x-for="person in people" :key="person.id">
                    <div class="flex items-center justify-between rounded-[17px] border border-pine-900/10 px-4 py-3.5">
                        <div><strong class="block text-sm" x-text="person.label"></strong><span class="text-[10px] text-pine-900/45" x-text="person.caption + ' · ' + person.price + ' lei'"></span></div>
                        <div class="flex items-center gap-2">
                            <button @click="person.count = Math.max(person.min, person.count - 1)" class="grid h-9 w-9 place-items-center rounded-full border border-pine-900/15" aria-label="Scade numărul"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg></button>
                            <strong class="w-5 text-center" x-text="person.count"></strong>
                            <button @click="person.count = Math.min(8, person.count + 1)" class="grid h-9 w-9 place-items-center rounded-full bg-pine-900 text-acid" aria-label="Crește numărul"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 rounded-[22px] bg-pine-900 p-5 text-white">
                <div class="flex items-end justify-between gap-4"><div><span class="block text-[9px] font-bold uppercase tracking-[.15em] text-white/40">Total estimat</span><strong class="mt-1 block font-display text-4xl text-acid" x-text="bookingTotal + ' lei'"></strong></div><div class="text-right text-[10px] leading-4 text-white/42"><span x-text="selectedDateLabel"></span><br><span x-text="selectedTime"></span></div></div>
                <button @click="addToCart()" class="magnetic mt-5 inline-flex min-h-[54px] w-full items-center justify-center gap-2 rounded-full bg-acid px-5 font-bold text-pine-950 transition hover:-translate-y-1">Continuă rezervarea <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
            </div>
        </aside>
    </div>

    <!-- Gallery modal -->
    <div x-cloak x-show="galleryOpen" class="fixed inset-0 z-[160] grid place-items-center bg-pine-950/95 p-3 sm:p-6" role="dialog" aria-modal="true">
        <button @click="galleryOpen = false" class="absolute right-4 top-4 z-20 grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-pine-950/60 text-white backdrop-blur-xl sm:right-7 sm:top-7" aria-label="Închide galeria"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <div class="relative h-[min(78svh,760px)] w-full max-w-[1200px] overflow-hidden rounded-[24px] bg-pine-900">
            <img :src="galleryImages[galleryIndex].src" :alt="galleryImages[galleryIndex].alt" class="h-full w-full object-cover">
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-pine-950/90 to-transparent p-5 pt-24 text-white sm:p-8 sm:pt-32">
                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-acid" x-text="(galleryIndex + 1) + ' / ' + galleryImages.length"></p>
                <h3 class="mt-2 font-display text-3xl font-semibold" x-text="galleryImages[galleryIndex].caption"></h3>
            </div>
            <button @click="galleryIndex = (galleryIndex - 1 + galleryImages.length) % galleryImages.length" class="absolute left-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-cream text-pine-950 sm:left-5" aria-label="Fotografia anterioară"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
            <button @click="galleryIndex = (galleryIndex + 1) % galleryImages.length" class="absolute right-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-cream text-pine-950 sm:right-5" aria-label="Fotografia următoare"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function experienceDetail() {
            return {
                menuOpen: false,
                bookingOpen: false,
                galleryOpen: false,
                galleryIndex: 0,
                faqOpen: 0,
                scrolled: false,
                selectedDate: 'sun',
                selectedTime: '14:00',
                activeSegmentId: 'canopy',

                event: {
                    id: <?php echo json_encode($expId); ?>,
                    slug: <?php echo json_encode($expSlug, JSON_UNESCAPED_UNICODE); ?>,
                    title: <?php echo json_encode($expTitle, JSON_UNESCAPED_UNICODE); ?>,
                    image: <?php echo json_encode($expImg, JSON_UNESCAPED_UNICODE); ?>,
                    venue: <?php echo json_encode($expVenue, JSON_UNESCAPED_UNICODE); ?>
                },

                menuItems: [
                    { label: 'Experiențe', href: '/experiente' },
                    { label: 'Despre traseu', href: '#poveste' },
                    { label: 'Ruta', href: '#ruta' },
                    { label: 'Siguranță', href: '#siguranta' },
                    { label: 'Întrebări', href: '#faq' }
                ],

                features: [
                    { title: 'Progresie naturală', copy: 'Începi la 4 metri și urci treptat. Fiecare sector pregătește corpul și încrederea pentru următorul.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 17 5-5 4 4 7-9"/><path d="M15 7h5v5"/></svg>' },
                    { title: 'Trei zboruri', copy: 'Tirolienele au lungimi diferite, iar finalul traversează ravena nordică pe o linie de 140 de metri.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M7 6l5 5 5-5M12 11v8"/></svg>' },
                    { title: 'Grupuri mici', copy: 'Pornirile sunt limitate la 10 participanți pentru ritm bun și supraveghere reală pe traseu.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>' }
                ],

                dates: [
                    { id: 'sat', day: 'Sâm', number: '26' },
                    { id: 'sun', day: 'Dum', number: '27' },
                    { id: 'mon', day: 'Lun', number: '28' },
                    { id: 'tue', day: 'Mar', number: '29' }
                ],
                drawerDates: [
                    { id: 'sat', day: 'Sâm', number: '26', label: 'azi' },
                    { id: 'sun', day: 'Dum', number: '27', label: 'mâine' },
                    { id: 'mon', day: 'Lun', number: '28', label: 'liber' },
                    { id: 'tue', day: 'Mar', number: '29', label: 'liber' },
                    { id: 'wed', day: 'Mie', number: '30', label: 'liber' },
                    { id: 'thu', day: 'Joi', number: '31', label: 'liber' }
                ],
                timeSlots: [
                    { time: '09:00', soldOut: false, low: false },
                    { time: '10:30', soldOut: true, low: false },
                    { time: '12:00', soldOut: false, low: true },
                    { time: '14:00', soldOut: false, low: false },
                    { time: '15:30', soldOut: false, low: false },
                    { time: '17:00', soldOut: false, low: true }
                ],
                people: <?php echo $expPeopleJs; ?>,

                segments: [
                    { id: 'launch', number: '01', title: 'Launch Deck', level: 'încălzire', copy: 'Testarea echipamentului, poduri joase și primele traversări controlate.', length: '120 m', obstacles: '6 obstacole', height: '4 m' },
                    { id: 'bridge', number: '02', title: 'Bridge Lab', level: 'activ', copy: 'Poduri mobile, bușteni suspendați și primele secțiuni care cer echilibru real.', length: '170 m', obstacles: '7 obstacole', height: '9 m' },
                    { id: 'canopy', number: '03', title: 'High Canopy', level: 'intens', copy: 'Cea mai înaltă zonă a traseului, cu platforme larg deschise și priveliște peste ravenă.', length: '210 m', obstacles: '5 obstacole', height: '18 m' },
                    { id: 'flight', number: '04', title: 'Final Flight', level: 'zbor', copy: 'Trei tiroliene consecutive, inclusiv traversarea finală de 140 de metri.', length: '140 m', obstacles: '3 tiroliene', height: '12 m' }
                ],

                journey: [
                    { time: '00–15 min', title: 'Check-in și echipare', copy: 'Confirmăm rezervarea, greutatea și înălțimea, apoi ajustăm hamul, casca și mănușile.' },
                    { time: '15–25 min', title: 'Briefing la sol', copy: 'Ghidul explică sistemul de siguranță și fiecare participant parcurge un mini-traseu de test.' },
                    { time: '25–75 min', title: 'Patru sectoare', copy: 'Grupul avansează în propriul ritm, cu instructori amplasați în punctele-cheie ale rutei.' },
                    { time: '75–90 min', title: 'Final Flight', copy: 'Ultimele trei tiroliene te readuc la baza traseului, unde predai echipamentul și primești fotografiile.' }
                ],

                requirements: [
                    { title: 'Vârsta minimă: 12 ani', copy: 'Participanții sub 16 ani intră însoțiți de un adult.' },
                    { title: 'Înălțime: minimum 145 cm', copy: 'Necesară pentru accesul corect la sistemele de siguranță.' },
                    { title: 'Greutate: 35–110 kg', copy: 'Limitele sunt stabilite de certificarea echipamentului.' },
                    { title: 'Încălțăminte închisă', copy: 'Pantofi sport sau trekking; sandalele nu sunt permise.' },
                    { title: 'Părul lung trebuie prins', copy: 'Accesoriile libere se depozitează înainte de traseu.' },
                    { title: 'Fără consum de alcool', copy: 'Echipa poate refuza accesul când există suspiciuni de consum.' }
                ],

                reviews: [
                    { quote: 'Primele zece minute m-au speriat. Ultima tiroliană aș fi făcut-o încă de trei ori.', name: 'Irina M.', group: 'vizită în cuplu' },
                    { quote: 'Foarte bine gândită progresia. Copilul nostru de 14 ani s-a simțit în siguranță fără să se plictisească.', name: 'Radu P.', group: 'familie cu adolescent' },
                    { quote: 'Briefing clar, echipament impecabil și un traseu care chiar arată spectaculos, nu doar în fotografii.', name: 'Mara D.', group: 'grup de prieteni' }
                ],

                related: [
                    { title: 'Black Pine Circuit', badge: 'expert', duration: '3 ore', age: '16+ ani', price: '179 lei', image: 'https://images.unsplash.com/photo-1486911278844-a81c5267e227?auto=format&fit=crop&w=1200&q=84' },
                    { title: 'Ridge Trail', badge: 'scenic', duration: '2 ore', age: '10+ ani', price: '79 lei', image: 'https://images.unsplash.com/photo-1464278533981-50106e6176b1?auto=format&fit=crop&w=1200&q=84' },
                    { title: 'Twilight Walk', badge: 'apus', duration: '75 min', age: '8+ ani', price: '89 lei', image: 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1200&q=84' }
                ],

                faqs: [
                    { q: 'Trebuie să am experiență anterioară?', a: 'Nu. Canopy Run este construit pentru începători activi. Briefingul și traseul de test sunt obligatorii înainte de acces.' },
                    { q: 'Pot abandona dacă nu mă simt confortabil?', a: 'Da. Există ieșiri asistate după primele două sectoare. După intrarea în High Canopy, retragerea se face doar cu ajutorul instructorului.' },
                    { q: 'Ce se întâmplă dacă plouă?', a: 'Traseul funcționează în ploaie ușoară. În caz de vânt puternic, furtună sau descărcări electrice, rezervarea se mută gratuit.' },
                    { q: 'Pot lua telefonul pe traseu?', a: 'Doar într-un sistem de prindere sigur. Obiectele ținute în mână sau buzunarele deschise nu sunt permise.' },
                    { q: 'Există fotografii incluse?', a: 'Două fotografii automate sunt incluse. Pachetul complet foto-video poate fi adăugat în checkout.' }
                ],

                galleryImages: [
                    { src: 'https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=1800&q=90', alt: 'Traseu suspendat', caption: 'Primele traversări din Launch Deck' },
                    { src: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1800&q=90', alt: 'Tiroliană printre copaci', caption: 'Zborul final peste ravena nordică' },
                    { src: 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1800&q=90', alt: 'Peisaj montan', caption: 'Priveliștea din High Canopy' },
                    { src: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=90', alt: 'Pădure văzută de sus', caption: 'Coroanele rezervației la apus' }
                ],

                init() {
                    this.scrolled = window.scrollY > 24;
                    window.addEventListener('scroll', () => { this.scrolled = window.scrollY > 24; }, { passive: true });
                    this.$watch('menuOpen', () => this.syncBodyLock());
                    this.$watch('bookingOpen', () => this.syncBodyLock());
                    this.$watch('galleryOpen', () => this.syncBodyLock());
                },
                syncBodyLock() { document.body.style.overflow = this.menuOpen || this.bookingOpen || this.galleryOpen ? 'hidden' : ''; },
                openBooking() { this.bookingOpen = true; },
                addToCart() {
                    const selected = this.people.filter(p => p.count > 0);
                    if (!selected.length) { this.bookingOpen = true; return; }
                    const dateLabel = this.selectedDateLabel || '';
                    const slot = this.selectedTime || '';
                    let cart = { event: { id: this.event.id, title: this.event.title, image: this.event.image, date: dateLabel, venue: this.event.venue }, items: [], subtotal: 0 };
                    // Păstrează itemele existente doar dacă e aceeași experiență
                    try {
                        const raw = localStorage.getItem('nordvale_cart');
                        if (raw) {
                            const ex = JSON.parse(raw);
                            if (ex && ex.event && ex.event.id === this.event.id && Array.isArray(ex.items)) {
                                cart = ex;
                                cart.event = { id: this.event.id, title: this.event.title, image: this.event.image, date: dateLabel, venue: this.event.venue };
                            }
                        }
                    } catch (e) {}
                    selected.forEach(p => {
                        cart.items.push({
                            ticket_type_id: p.ticket_type_id ?? null,
                            title: this.event.title + ' — ' + p.label,
                            date: dateLabel,
                            slot: slot,
                            qty: p.count,
                            unit_price: p.price
                        });
                    });
                    cart.subtotal = cart.items.reduce((s, it) => s + it.qty * it.unit_price, 0);
                    try { localStorage.setItem('nordvale_cart', JSON.stringify(cart)); } catch (e) {}
                    window.location.href = '/cos';
                },
                openGallery(index = 0) { this.galleryIndex = index; this.galleryOpen = true; },
                get activeSegment() { return this.segments.find(item => item.id === this.activeSegmentId) || this.segments[0]; },
                get totalGuests() { return this.people.reduce((sum, person) => sum + person.count, 0); },
                get bookingTotal() { return this.people.reduce((sum, person) => sum + person.count * person.price, 0); },
                get selectedDateLabel() {
                    const date = this.drawerDates.find(item => item.id === this.selectedDate);
                    return date ? `${date.day}, ${date.number} iulie` : '';
                }
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
            animate('[data-hero-kicker]', { opacity: [0, 1], y: [22, 0] }, { duration: .72, delay: stagger(.08, { startDelay: .1 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-title]', { opacity: [0, 1], y: [52, 0] }, { duration: 1.04, delay: stagger(.12, { startDelay: .22 }), ease: [0.16, 1, 0.3, 1] });
            animate('[data-hero-copy]', { opacity: [0, 1], y: [22, 0] }, { duration: .84, delay: stagger(.08, { startDelay: .52 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-visual]', { opacity: [0, 1], x: [58, 0], rotate: [1.2, 0] }, { duration: 1.18, delay: .4, ease: [0.16, 1, 0.3, 1] });

            document.querySelectorAll('[data-reveal]').forEach((element) => {
                inView(element, () => {
                    animate(element, { opacity: [0, 1], y: [34, 0] }, { duration: .82, ease: [0.22, 1, 0.36, 1] });
                }, { margin: '0px 0px -9% 0px', amount: .12 });
            });

            const hero = document.querySelector('#top');
            const story = document.querySelector('#poveste');
            const safety = document.querySelector('#siguranta');
            if (hero) scroll(animate('#hero-image', { y: ['0%', '11%'], scale: [1.02, 1.1] }), { target: hero, offset: ['start start', 'end start'] });
            if (story) scroll(animate('#story-image', { y: ['-4%', '8%'], scale: [1.05, 1.12] }), { target: story, offset: ['start end', 'end start'] });
            if (safety) scroll(animate('#safety-image', { y: ['-5%', '7%'], scale: [1.04, 1.12] }), { target: safety, offset: ['start end', 'end start'] });

            const heroRoute = document.querySelector('#hero-route');
            const heroMarker = document.querySelector('#hero-marker');
            if (window.anime && heroRoute && heroMarker) {
                const path = anime.path('#hero-route');
                anime({ targets: '#hero-route', strokeDashoffset: [anime.setDashoffset, -260], easing: 'linear', duration: 17000, loop: true });
                anime({ targets: heroMarker, translateX: path('x'), translateY: path('y'), rotate: path('angle'), easing: 'easeInOutSine', duration: 7400, loop: true, direction: 'alternate' });
            }

            const routeProgress = document.querySelector('#route-progress-path');
            if (routeProgress && window.anime) {
                routeProgress.style.strokeDasharray = routeProgress.getTotalLength();
                routeProgress.style.strokeDashoffset = routeProgress.getTotalLength();
                inView(routeProgress, () => {
                    anime({ targets: routeProgress, strokeDashoffset: [anime.setDashoffset, 0], easing: 'easeInOutCubic', duration: 2200 });
                }, { amount: .3 });
            }

            if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
                document.querySelectorAll('.tilt-card').forEach(card => {
                    const surface = card.querySelector('.tilt-card__surface');
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

        window.setTimeout(revealEverything, 3400);
    </script>
</body>
</html>
