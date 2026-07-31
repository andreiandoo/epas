<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#071d17">
    <title>Nordvale — Wild Park & Forest Reserve</title>
    <meta name="description" content="Concept homepage Tixello pentru un parc de aventură și o rezervație forestieră.">

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
                        sky: '#b8dce0'
                    },
                    boxShadow: {
                        lift: '0 36px 100px -38px rgba(6,26,21,.58)',
                        card: '0 18px 48px -28px rgba(6,26,21,.46)',
                        acid: '0 18px 54px -24px rgba(223,252,98,.58)'
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
            opacity: .09;
            mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.58'/%3E%3C/svg%3E");
        }

        .topo-dark {
            background-image:
                radial-gradient(circle at 18% 22%, rgba(223,252,98,.14), transparent 20%),
                radial-gradient(circle at 84% 18%, rgba(242,123,74,.12), transparent 17%),
                url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, auto, 820px 820px;
        }

        .paper-grid {
            background-image:
                linear-gradient(rgba(9,37,29,.052) 1px, transparent 1px),
                linear-gradient(90deg, rgba(9,37,29,.052) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .nav-shell {
            background: rgba(6,26,21,.78);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 24px 70px -42px rgba(0,0,0,.9);
            backdrop-filter: blur(22px) saturate(130%);
            -webkit-backdrop-filter: blur(22px) saturate(130%);
        }

        .hero-mask {
            clip-path: polygon(6% 0, 100% 0, 97% 87%, 76% 100%, 0 93%, 0 10%);
        }

        .image-mask-a { clip-path: polygon(0 0, 94% 4%, 100% 90%, 9% 100%, 0 82%); }
        .image-mask-b { clip-path: polygon(7% 0, 100% 0, 95% 94%, 0 100%, 2% 11%); }

        .outline-word {
            color: transparent;
            -webkit-text-stroke: 1px rgba(255,255,255,.46);
            text-stroke: 1px rgba(255,255,255,.46);
        }

        .route-line {
            fill: none;
            stroke: var(--acid);
            stroke-width: 2.4;
            stroke-linecap: round;
            stroke-dasharray: 9 13;
            filter: drop-shadow(0 0 8px rgba(223,252,98,.38));
        }

        .glow-orbit {
            position: absolute;
            border: 1px solid rgba(223,252,98,.22);
            border-radius: 999px;
            pointer-events: none;
        }

        .kinetic-word {
            display: inline-block;
            will-change: transform;
        }

        .experience-card {
            transform-style: preserve-3d;
            perspective: 900px;
        }
        .experience-card__inner {
            transition: transform .22s ease-out, box-shadow .35s ease;
            will-change: transform;
        }
        .experience-card:hover .experience-card__inner { box-shadow: 0 38px 90px -42px rgba(0,0,0,.75); }
        .experience-card img { transition: transform .9s cubic-bezier(.18,.8,.22,1); }
        .experience-card:hover img { transform: scale(1.06); }

        .marquee {
            overflow: hidden;
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }
        .marquee-track {
            display: flex;
            width: max-content;
            animation: marquee 36s linear infinite;
        }
        @keyframes marquee { to { transform: translateX(-50%); } }

        .pulse-dot { animation: pulse-dot 2.2s ease-out infinite; }
        @keyframes pulse-dot {
            0% { box-shadow: 0 0 0 0 rgba(223,252,98,.48); }
            70% { box-shadow: 0 0 0 10px rgba(223,252,98,0); }
            100% { box-shadow: 0 0 0 0 rgba(223,252,98,0); }
        }

        .reveal-ready [data-reveal] { opacity: 0; transform: translateY(34px); }
        .reveal-ready [data-hero-kicker],
        .reveal-ready [data-hero-title],
        .reveal-ready [data-hero-copy],
        .reveal-ready [data-hero-cta],
        .reveal-ready [data-hero-visual] { opacity: 0; }
        .reveal-ready [data-hero-title] { transform: translateY(54px); }
        .reveal-ready [data-hero-copy], .reveal-ready [data-hero-cta], .reveal-ready [data-hero-kicker] { transform: translateY(22px); }
        .reveal-ready [data-hero-visual] { transform: translateX(62px) rotate(1.5deg); }

        .intro-screen {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: grid;
            place-items: center;
            background: var(--pine-dark);
            color: white;
            transition: opacity .45s ease, visibility .45s ease;
        }
        .intro-screen.is-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .intro-line { transform-origin: left center; }

        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .drawer-shadow { box-shadow: -50px 0 120px -60px rgba(6,26,21,.82); }
        .progress-line { transform: scaleX(0); transform-origin: left center; }

        @media (max-width: 1023px) {
            .hero-mask { clip-path: polygon(0 0, 100% 0, 100% 93%, 9% 100%, 0 95%); }
        }

        @media (max-width: 639px) {
            .hero-mask,
            .image-mask-a,
            .image-mask-b { clip-path: none; border-radius: 24px; }
            .marquee { mask-image: none; -webkit-mask-image: none; }
        }

        @media (hover: none), (pointer: coarse) {
            .experience-card__inner { transform: none !important; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
            .intro-screen { display: none; }
            .progress-line { transform: scaleX(1); }
            .reveal-ready [data-reveal],
            .reveal-ready [data-hero-kicker],
            .reveal-ready [data-hero-title],
            .reveal-ready [data-hero-copy],
            .reveal-ready [data-hero-cta],
            .reveal-ready [data-hero-visual] { opacity: 1; transform: none; }
        }
    </style>

    <script>
        document.documentElement.classList.add('reveal-ready');
        window.__introSafety = window.setTimeout(() => {
            document.querySelector('[data-intro]')?.classList.add('is-hidden');
            document.documentElement.classList.remove('reveal-ready');
        }, 4800);
    </script>
</head>
<body x-data="nordvaleV3()" x-init="init()" class="antialiased">

    <!-- Intro -->
    <div data-intro class="intro-screen grain" aria-hidden="true">
        <div class="relative z-10 w-[min(78vw,480px)]">
            <div class="mb-6 flex items-center justify-between text-[10px] font-bold uppercase tracking-[.3em] text-white/[.45]">
                <span>48.263° N</span>
                <span>27.805° E</span>
            </div>
            <div class="overflow-hidden">
                <div data-intro-word class="font-display text-[clamp(48px,10vw,104px)] font-semibold leading-none">Nordvale</div>
            </div>
            <div class="intro-line mt-5 h-px bg-acid"></div>
            <div data-intro-copy class="mt-4 text-xs font-semibold uppercase tracking-[.24em] text-white/[.55]">wild park · forest reserve</div>
        </div>
    </div>

    <!-- Scroll progress -->
    <div class="fixed inset-x-0 top-0 z-[140] h-[3px]">
        <div id="page-progress" class="progress-line h-full w-full bg-acid"></div>
    </div>

    <!-- Header -->
    <header class="safe-top fixed inset-x-0 top-0 z-[100] px-2.5 sm:px-4 lg:px-6">
        <nav class="mx-auto mt-2 flex min-h-[62px] max-w-[1540px] items-center justify-between gap-2 rounded-[20px] px-3 py-2.5 transition-all duration-500 sm:gap-4 sm:px-4 lg:mt-4 lg:px-5"
             :class="scrolled ? 'nav-shell' : 'border border-white/0 bg-transparent'">
            <a href="#top" class="flex min-w-0 items-center gap-2.5 text-white sm:gap-3" aria-label="Nordvale homepage">
                <span class="grid h-10 w-10 flex-none place-items-center rounded-[14px] border border-white/20 bg-white/10 sm:h-11 sm:w-11">
                    <svg viewBox="0 0 48 48" class="h-7 w-7 sm:h-8 sm:w-8" fill="none" aria-hidden="true">
                        <path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/>
                        <path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/>
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-display text-[19px] font-semibold leading-none sm:text-[21px]">Nordvale</span>
                    <span class="mt-1 hidden truncate text-[7px] font-bold uppercase tracking-[.25em] text-white/[.52] min-[410px]:block sm:text-[8px]">wild park · forest reserve</span>
                </span>
            </a>

            <div class="hidden items-center gap-7 xl:flex">
                <a href="#experiente" class="text-sm font-semibold text-white/70 transition hover:text-acid">Experiențe</a>
                <a href="#planner" class="text-sm font-semibold text-white/70 transition hover:text-acid">Planifică</a>
                <a href="#program" class="text-sm font-semibold text-white/70 transition hover:text-acid">Program</a>
                <a href="#rezervatie" class="text-sm font-semibold text-white/70 transition hover:text-acid">Rezervația</a>
                <a href="#abonamente" class="text-sm font-semibold text-white/70 transition hover:text-acid">Abonamente</a>
            </div>

            <div class="flex flex-none items-center gap-1.5 sm:gap-2">
                <a :href="accountHref" class="hidden whitespace-nowrap rounded-full border border-white/[.15] px-4 py-2.5 text-sm font-semibold text-white/[.82] transition hover:bg-white/10 lg:inline-flex" x-text="accountLabel">Contul meu</a>
                <button @click="bookingOpen = true" class="inline-flex flex-none items-center justify-center gap-1.5 whitespace-nowrap rounded-full bg-acid px-3 py-2.5 text-[12px] font-bold text-pine-950 shadow-acid transition hover:-translate-y-0.5 sm:gap-2 sm:px-4 sm:text-sm lg:px-5">
                    <svg class="h-3.5 w-3.5 flex-none sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H18l3 3v9.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-10Z"/><path d="M8 6V3m8 3V3M3 11h18"/></svg>
                    <span>Bilete</span>
                </button>
                <button @click="menuOpen = true" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-white/[.15] text-white sm:h-11 sm:w-11 xl:hidden" aria-label="Deschide meniul">
                    <svg class="h-[18px] w-[18px] sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 17h16"/></svg>
                </button>
            </div>
        </nav>
    </header>

    <!-- Mobile menu -->
    <div x-cloak x-show="menuOpen" class="fixed inset-0 z-[130]" role="dialog" aria-modal="true">
        <div x-show="menuOpen" x-transition.opacity @click="menuOpen = false" class="absolute inset-0 bg-pine-950/[.72] backdrop-blur-sm"></div>
        <aside x-show="menuOpen" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-350 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="safe-bottom absolute right-0 top-0 flex h-full w-[min(88vw,430px)] flex-col bg-cream p-5 sm:p-7">
            <div class="flex items-center justify-between">
                <span class="font-display text-2xl font-semibold text-pine-950">Nordvale</span>
                <button @click="menuOpen = false" class="grid h-11 w-11 place-items-center rounded-full border border-pine-900/[.15]" aria-label="Închide meniul">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="mt-12 space-y-1">
                <template x-for="item in menuItems" :key="item.href">
                    <a :href="item.href" @click="menuOpen = false" class="group flex items-center justify-between border-b border-pine-900/10 py-4 font-display text-[28px] font-semibold text-pine-950">
                        <span x-text="item.label"></span>
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-pine-900 text-acid transition group-hover:rotate-45">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg>
                        </span>
                    </a>
                </template>
            </div>
            <div class="mt-auto rounded-[22px] bg-pine-900 p-5 text-white">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[.18em] text-acid"><span class="pulse-dot h-2 w-2 rounded-full bg-acid"></span> Parc deschis</div>
                <p class="mt-3 text-sm text-white/[.65]">Astăzi · 09:00–20:00</p>
                <button @click="menuOpen = false; bookingOpen = true" class="mt-5 w-full rounded-full bg-acid px-5 py-3.5 font-bold text-pine-950">Rezervă acum</button>
            </div>
        </aside>
    </div>

    <main>
        <!-- Hero -->
        <section id="top" class="grain topo-dark relative min-h-[100svh] overflow-hidden bg-pine-950 text-white">
            <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(6,26,21,.08),rgba(6,26,21,.5))]"></div>
            <div class="glow-orbit -left-[140px] top-[20%] h-[420px] w-[420px]"></div>
            <div class="glow-orbit -right-[180px] bottom-[5%] h-[520px] w-[520px]"></div>

            <div class="relative z-10 mx-auto grid min-h-[100svh] max-w-[1540px] gap-10 px-4 pb-10 pt-28 sm:px-6 sm:pt-32 lg:grid-cols-[.92fr_1.08fr] lg:items-center lg:gap-14 lg:px-8 lg:pb-14 lg:pt-28 xl:px-12">
                <div class="min-w-0 lg:pb-10">
                    <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.74] sm:px-4 sm:text-[10px] sm:tracking-[.24em]">
                        <span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span>
                        <span class="truncate">Rezervație vie · aventură controlată</span>
                    </div>

                    <h1 class="font-display text-[clamp(50px,13.5vw,94px)] font-semibold leading-[.88] tracking-[-.055em] lg:text-[clamp(72px,7.6vw,126px)]">
                        <span data-hero-title class="block">Mai aproape</span>
                        <span data-hero-title class="block text-acid">de pădure.</span>
                        <span data-hero-title class="outline-word block italic">Mai viu.</span>
                    </h1>

                    <p data-hero-copy class="mt-7 max-w-xl text-[15px] leading-7 text-white/[.66] sm:text-lg sm:leading-8">
                        Trasee suspendate, poteci de explorare și întâlniri ghidate cu ecosistemul. O zi construită în jurul ritmului tău, la doar câteva minute de oraș.
                    </p>

                    <div data-hero-cta class="mt-7 grid max-w-[520px] grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:gap-3">
                        <button @click="bookingOpen = true" class="group inline-flex min-h-[52px] items-center justify-center gap-2 rounded-full bg-acid px-4 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:px-6 sm:text-base">
                            Alege vizita
                            <svg class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                        <a href="#experiente" class="inline-flex min-h-[52px] items-center justify-center rounded-full border border-white/20 px-4 text-sm font-semibold text-white transition hover:bg-white/10 sm:px-6 sm:text-base">Descoperă parcul</a>
                    </div>

                    <div data-hero-copy class="mt-8 flex flex-wrap gap-x-5 gap-y-3 text-xs font-semibold text-white/[.56] sm:text-sm">
                        <span class="flex items-center gap-2"><svg class="h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg> Acces instant pe telefon</span>
                        <span class="flex items-center gap-2"><svg class="h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg> Echipament inclus</span>
                    </div>
                </div>

                <div data-hero-visual class="relative min-h-[430px] min-w-0 sm:min-h-[560px] lg:min-h-[700px]">
                    <div class="hero-mask absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                        <img id="hero-bg" src="https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1600&q=86" alt="Traseu de aventură printre copaci" class="h-full w-full object-cover object-center">
                        <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/10 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-pine-950/[.35] via-transparent to-transparent"></div>
                    </div>

                    <!-- Animated route -->
                    <svg class="pointer-events-none absolute inset-[7%] z-10 h-[78%] w-[86%]" viewBox="0 0 700 660" fill="none" aria-hidden="true">
                        <path id="hero-route" class="route-line" d="M80 545C145 495 103 414 195 382c86-30 144 45 215-4 58-40 23-108 92-147 53-29 109 4 136-77"/>
                        <circle cx="80" cy="545" r="7" fill="#DFFC62"/>
                        <circle cx="638" cy="154" r="7" fill="#F27B4A"/>
                        <g id="route-marker">
                            <circle r="13" fill="#09251D" stroke="#DFFC62" stroke-width="3"/>
                            <circle r="4" fill="#DFFC62"/>
                        </g>
                    </svg>

                    <div class="absolute bottom-4 left-4 z-20 max-w-[calc(100%-32px)] rounded-[20px] border border-white/[.15] bg-pine-950/[.78] p-4 text-white shadow-card backdrop-blur-xl sm:bottom-7 sm:left-7 sm:max-w-[310px] sm:p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-[9px] font-bold uppercase tracking-[.2em] text-acid">Traseul zilei</div>
                                <div class="mt-1 font-display text-xl font-semibold sm:text-2xl">Canopy North</div>
                            </div>
                            <div class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid text-pine-950">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3 border-t border-white/10 pt-4 text-xs">
                            <div><span class="block text-white/[.45]">Durată</span><strong class="mt-1 block">90 min</strong></div>
                            <div><span class="block text-white/[.45]">Nivel</span><strong class="mt-1 block">Mediu</strong></div>
                            <div><span class="block text-white/[.45]">Locuri</span><strong class="mt-1 block text-acid">12 libere</strong></div>
                        </div>
                    </div>

                    <div class="absolute right-3 top-5 z-20 hidden rotate-3 rounded-[18px] bg-cream p-4 text-pine-950 shadow-card sm:block lg:right-[-14px] lg:top-[12%]">
                        <div class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-700">Astăzi în parc</div>
                        <div class="mt-2 flex items-end gap-2"><span class="font-display text-4xl font-semibold">24°</span><span class="pb-1 text-xs text-pine-700">senin</span></div>
                        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-pine-900/10"><div class="h-full w-[72%] rounded-full bg-ember"></div></div>
                        <div class="mt-2 text-[10px] text-pine-700">Condiții excelente pe trasee</div>
                    </div>

                    <div class="absolute left-[-8px] top-[18%] z-20 hidden items-center gap-2 rounded-full border border-white/[.15] bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[.17em] text-white backdrop-blur-lg md:flex">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-acid"></span> Live capacity 64%
                    </div>
                </div>
            </div>

            <div class="relative z-10 border-t border-white/10 bg-pine-950/[.35] backdrop-blur-sm">
                <div class="mx-auto grid max-w-[1540px] grid-cols-2 divide-x divide-white/10 px-4 sm:grid-cols-4 sm:px-6 lg:px-12">
                    <template x-for="stat in stats" :key="stat.label">
                        <div class="px-3 py-5 sm:px-6 sm:py-6">
                            <div class="font-display text-2xl font-semibold text-white sm:text-3xl"><span class="js-counter" :data-value="stat.value" x-text="stat.prefix + '0' + stat.suffix"></span></div>
                            <div class="mt-1 text-[10px] font-bold uppercase tracking-[.16em] text-white/[.42] sm:text-xs" x-text="stat.label"></div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- Moving field note -->
        <section class="marquee border-y border-pine-900/10 bg-acid py-3 text-pine-950">
            <div class="marquee-track items-center text-[10px] font-bold uppercase tracking-[.22em] sm:text-xs">
                <template x-for="repeat in 2" :key="repeat">
                    <div class="flex items-center">
                        <template x-for="item in marqueeItems" :key="repeat + item">
                            <div class="flex items-center gap-5 px-5 sm:gap-8 sm:px-8"><span x-text="item"></span><span class="h-1.5 w-1.5 rounded-full bg-ember"></span></div>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        <!-- Planner -->
        <section id="planner" class="paper-grid bg-cream py-20 sm:py-28 lg:py-36">
            <div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
                <div data-reveal class="grid gap-8 lg:grid-cols-[.78fr_1.22fr] lg:items-end">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Planificator inteligent</div>
                        <h2 class="mt-4 max-w-3xl font-display text-[clamp(42px,9vw,86px)] font-semibold leading-[.95] tracking-[-.045em] text-pine-950 lg:text-[clamp(56px,6vw,94px)]">Spune-ne cum vrei să te simți.</h2>
                    </div>
                    <p class="max-w-xl text-base leading-7 text-pine-900/[.62] sm:text-lg sm:leading-8 lg:justify-self-end">Nu toate zilele de aventură trebuie să arate la fel. Alege ritmul, iar noi îți propunem traseul, intervalul și biletul potrivit.</p>
                </div>

                <div class="mt-12 grid gap-5 lg:mt-16 lg:grid-cols-[.72fr_1.28fr]">
                    <div data-reveal class="rounded-[28px] bg-pine-950 p-5 text-white sm:p-7 lg:p-8">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-[.2em] text-white/[.45]">01 · Ritmul zilei</span>
                            <span class="rounded-full border border-white/[.12] px-3 py-1 text-[9px] font-bold uppercase tracking-[.16em] text-acid">1 minut</span>
                        </div>
                        <div class="mt-7 space-y-2.5">
                            <template x-for="mood in moods" :key="mood.id">
                                <button @click="selectedMood = mood.id" class="group flex w-full items-center gap-4 rounded-[18px] border p-3.5 text-left transition sm:p-4" :class="selectedMood === mood.id ? 'border-acid bg-acid text-pine-950' : 'border-white/10 bg-white/[.035] text-white hover:border-white/25'">
                                    <span class="grid h-10 w-10 flex-none place-items-center rounded-full" :class="selectedMood === mood.id ? 'bg-pine-950 text-acid' : 'bg-white/[.08] text-acid'" x-html="mood.icon"></span>
                                    <span class="min-w-0 flex-1"><strong class="block truncate text-sm sm:text-base" x-text="mood.name"></strong><span class="mt-0.5 block truncate text-xs" :class="selectedMood === mood.id ? 'text-pine-900/[.65]' : 'text-white/[.45]'" x-text="mood.caption"></span></span>
                                    <svg class="h-4 w-4 flex-none transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div data-reveal class="relative overflow-hidden rounded-[28px] bg-oat p-5 sm:p-8 lg:p-10">
                        <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full border border-pine-900/10"></div>
                        <div class="absolute -right-7 -top-7 h-32 w-32 rounded-full border border-pine-900/10"></div>
                        <div class="relative z-10 grid h-full gap-8 md:grid-cols-[1fr_.9fr] md:items-end">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Recomandarea Nordvale</span>
                                <h3 class="mt-4 font-display text-4xl font-semibold leading-tight text-pine-950 sm:text-5xl" x-text="activeMood.title"></h3>
                                <p class="mt-4 max-w-lg text-sm leading-7 text-pine-900/[.62] sm:text-base" x-text="activeMood.description"></p>
                                <div class="mt-7 flex flex-wrap gap-2">
                                    <template x-for="tag in activeMood.tags" :key="tag"><span class="rounded-full border border-pine-900/[.12] bg-cream px-3 py-2 text-[10px] font-bold uppercase tracking-[.13em] text-pine-800" x-text="tag"></span></template>
                                </div>
                                <button @click="bookingOpen = true; selectedBookingOption = activeMood.booking" class="mt-8 inline-flex items-center gap-2 rounded-full bg-pine-950 px-5 py-3.5 text-sm font-bold text-white transition hover:-translate-y-1">Rezervă recomandarea <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                            </div>
                            <div class="relative min-h-[290px] overflow-hidden rounded-[22px] sm:min-h-[360px]">
                                <img :src="activeMood.image" :alt="activeMood.title" class="absolute inset-0 h-full w-full object-cover transition duration-700">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/[.65] via-transparent to-transparent"></div>
                                <div class="absolute bottom-4 left-4 right-4 rounded-[16px] border border-white/[.15] bg-pine-950/[.65] p-4 text-white backdrop-blur-lg">
                                    <div class="flex items-center justify-between"><span class="text-xs text-white/[.55]">Durată recomandată</span><strong x-text="activeMood.duration"></strong></div>
                                    <div class="mt-2 flex items-center justify-between"><span class="text-xs text-white/[.55]">De la</span><strong class="text-acid" x-text="activeMood.price + ' lei'"></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Experiences -->
        <section id="experiente" class="relative overflow-hidden bg-pine-950 py-20 text-white sm:py-28 lg:py-36">
            <div class="absolute inset-0 topo-dark opacity-70"></div>
            <div class="relative z-10 mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
                <div data-reveal class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Patru feluri de a intra în pădure</div>
                        <h2 class="mt-4 max-w-4xl font-display text-[clamp(44px,10vw,88px)] font-semibold leading-[.94] tracking-[-.045em]">Aventura nu vine într-o singură formă.</h2>
                    </div>
                    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide lg:max-w-[460px] lg:flex-wrap lg:justify-end">
                        <template x-for="category in categories" :key="category.id">
                            <button @click="activeCategory = category.id" class="flex-none whitespace-nowrap rounded-full border px-4 py-2.5 text-xs font-bold transition" :class="activeCategory === category.id ? 'border-acid bg-acid text-pine-950' : 'border-white/[.14] text-white/[.62] hover:border-white/[.35] hover:text-white'" x-text="category.label"></button>
                        </template>
                    </div>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 xl:grid-cols-12">
                    <template x-for="(item, index) in filteredExperiences" :key="item.id">
                        <article data-reveal class="experience-card min-w-0" :class="index === 0 ? 'xl:col-span-7' : index === 1 ? 'xl:col-span-5' : 'xl:col-span-4'">
                            <a href="#" class="experience-card__inner group relative block min-h-[420px] overflow-hidden rounded-[24px] border border-white/10 sm:min-h-[470px]" :class="index < 2 ? 'xl:min-h-[610px]' : 'xl:min-h-[470px]'">
                                <img :src="item.image" :alt="item.title" class="absolute inset-0 h-full w-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/[.12] to-transparent"></div>
                                <div class="absolute left-4 top-4 flex items-center gap-2 sm:left-5 sm:top-5">
                                    <span class="rounded-full bg-cream px-3 py-2 text-[9px] font-bold uppercase tracking-[.16em] text-pine-950" x-text="item.level"></span>
                                    <span class="rounded-full border border-white/[.18] bg-pine-950/[.38] px-3 py-2 text-[9px] font-bold uppercase tracking-[.16em] text-white backdrop-blur" x-text="item.duration"></span>
                                </div>
                                <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-7">
                                    <div class="text-[9px] font-bold uppercase tracking-[.2em] text-acid" x-text="item.eyebrow"></div>
                                    <div class="mt-2 flex items-end justify-between gap-4">
                                        <div class="min-w-0"><h3 class="font-display text-3xl font-semibold sm:text-4xl" x-text="item.title"></h3><p class="mt-2 line-clamp-2 max-w-lg text-sm leading-6 text-white/[.62]" x-text="item.copy"></p></div>
                                        <span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-acid text-pine-950 transition group-hover:rotate-45 sm:h-14 sm:w-14"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- Day rhythm -->
        <section id="program" class="bg-oat py-20 sm:py-28 lg:py-36">
            <div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-[.9fr_1.1fr] lg:gap-16">
                    <div data-reveal class="lg:sticky lg:top-28 lg:self-start">
                        <div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Ritmul unei zile</div>
                        <h2 class="mt-4 font-display text-[clamp(44px,10vw,82px)] font-semibold leading-[.95] tracking-[-.045em] text-pine-950">De dimineață până la ultimul pod.</h2>
                        <p class="mt-6 max-w-lg text-base leading-7 text-pine-900/[.62] sm:text-lg sm:leading-8">Programul nu este doar o listă de ore. Este felul în care parcul se schimbă pe parcursul zilei.</p>

                        <div class="mt-9 overflow-hidden rounded-[26px] bg-pine-950 p-2 shadow-lift sm:p-3">
                            <div class="relative min-h-[340px] overflow-hidden rounded-[20px] sm:min-h-[440px] lg:min-h-[520px]">
                                <template x-for="moment in dayMoments" :key="moment.id">
                                    <img x-show="activeMoment === moment.id" x-transition.opacity.duration.500ms :src="moment.image" :alt="moment.title" class="absolute inset-0 h-full w-full object-cover">
                                </template>
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/[.72] via-transparent to-transparent"></div>
                                <div class="absolute bottom-4 left-4 right-4 rounded-[18px] border border-white/[.12] bg-pine-950/[.62] p-4 text-white backdrop-blur-lg sm:bottom-5 sm:left-5 sm:right-5 sm:p-5">
                                    <div class="text-[9px] font-bold uppercase tracking-[.18em] text-acid" x-text="activeDayMoment.time"></div>
                                    <div class="mt-1 font-display text-2xl font-semibold sm:text-3xl" x-text="activeDayMoment.title"></div>
                                    <div class="mt-2 text-xs text-white/[.55] sm:text-sm" x-text="activeDayMoment.caption"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div data-reveal class="border-t border-pine-900/[.12]">
                        <template x-for="(moment, index) in dayMoments" :key="moment.id">
                            <button @mouseenter="activeMoment = moment.id" @focus="activeMoment = moment.id" @click="activeMoment = moment.id" class="group grid w-full grid-cols-[58px_1fr_auto] gap-3 border-b border-pine-900/[.12] py-6 text-left sm:grid-cols-[90px_1fr_auto] sm:gap-6 sm:py-8" :class="activeMoment === moment.id ? 'text-pine-950' : 'text-pine-900/[.46]'">
                                <div class="font-display text-xl font-semibold sm:text-2xl" x-text="moment.time"></div>
                                <div class="min-w-0"><h3 class="font-display text-2xl font-semibold sm:text-4xl" x-text="moment.title"></h3><p class="mt-2 line-clamp-2 max-w-xl text-xs leading-5 sm:text-sm sm:leading-6" x-text="moment.description"></p></div>
                                <div class="grid h-10 w-10 place-items-center rounded-full border transition sm:h-12 sm:w-12" :class="activeMoment === moment.id ? 'rotate-45 border-pine-950 bg-pine-950 text-acid' : 'border-pine-900/[.12] group-hover:border-pine-900/30'">
                                    <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reserve story -->
        <section id="rezervatie" class="relative overflow-hidden bg-cream py-20 sm:py-28 lg:py-40">
            <div class="absolute inset-0 paper-grid opacity-70"></div>
            <div class="relative z-10 mx-auto grid max-w-[1460px] gap-14 px-4 sm:px-6 lg:grid-cols-12 lg:items-center lg:px-10">
                <div data-reveal class="lg:col-span-5 lg:pr-10">
                    <div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Mai mult decât agrement</div>
                    <h2 class="mt-4 font-display text-[clamp(44px,10vw,84px)] font-semibold leading-[.94] tracking-[-.045em] text-pine-950">Pădurea nu este decorul. Este gazda.</h2>
                    <p class="mt-6 text-base leading-8 text-pine-900/[.64] sm:text-lg">Doar o parte din suprafața Nordvale este accesibilă vizitatorilor. Restul rămâne zonă de regenerare, monitorizare și protecție.</p>
                    <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4">
                        <div class="rounded-[20px] bg-oat p-4 sm:p-5"><div class="font-display text-3xl font-semibold text-pine-950 sm:text-4xl">34 ha</div><div class="mt-2 text-xs text-pine-900/[.52]">zonă protejată</div></div>
                        <div class="rounded-[20px] bg-pine-950 p-4 text-white sm:p-5"><div class="font-display text-3xl font-semibold text-acid sm:text-4xl">1 / 3</div><div class="mt-2 text-xs text-white/[.52]">din bilet merge în conservare</div></div>
                    </div>
                    <a href="#" class="mt-8 inline-flex items-center gap-2 border-b border-pine-950 pb-1 text-sm font-bold text-pine-950">Descoperă proiectul de conservare <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></a>
                </div>

                <div class="relative min-h-[520px] sm:min-h-[680px] lg:col-span-7 lg:min-h-[760px]">
                    <div data-reveal class="image-mask-a absolute left-0 top-0 h-[72%] w-[78%] overflow-hidden shadow-lift">
                        <img id="reserve-image-a" src="https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1400&q=86" alt="Pădure protejată" class="h-full w-full object-cover">
                    </div>
                    <div data-reveal class="image-mask-b absolute bottom-0 right-0 h-[55%] w-[58%] overflow-hidden border-[8px] border-cream shadow-lift sm:border-[12px]">
                        <img id="reserve-image-b" src="https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1000&q=86" alt="Potecă prin pădure" class="h-full w-full object-cover">
                    </div>
                    <div data-reveal class="absolute bottom-[14%] left-[4%] z-20 max-w-[230px] -rotate-3 bg-acid p-4 shadow-card sm:max-w-[280px] sm:p-6">
                        <div class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-700">Jurnal de teren · 071</div>
                        <p class="mt-3 font-display text-xl font-semibold leading-snug text-pine-950 sm:text-2xl">„În această dimineață, urmele de cerb au traversat poteca albastră.”</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Passes -->
        <section id="abonamente" class="relative overflow-hidden bg-pine-950 py-20 text-white sm:py-28 lg:py-36">
            <div class="absolute inset-0 topo-dark opacity-60"></div>
            <div class="relative z-10 mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
                <div data-reveal class="grid gap-7 lg:grid-cols-[1fr_.72fr] lg:items-end">
                    <div><div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Bilete și pass-uri</div><h2 class="mt-4 max-w-4xl font-display text-[clamp(44px,10vw,88px)] font-semibold leading-[.94] tracking-[-.045em]">Revii o dată. Sau faci pădurea parte din an.</h2></div>
                    <p class="max-w-xl text-base leading-7 text-white/[.58] sm:text-lg sm:leading-8 lg:justify-self-end">Prețuri clare, fără costuri ascunse. Accesul pe trasee include instructajul și echipamentul standard.</p>
                </div>

                <div class="mt-12 grid gap-4 md:grid-cols-3">
                    <template x-for="pass in passes" :key="pass.id">
                        <article data-reveal class="relative flex min-h-[470px] flex-col overflow-hidden rounded-[26px] border p-5 sm:p-7" :class="pass.featured ? 'border-acid bg-acid text-pine-950' : 'border-white/[.12] bg-white/[.045] text-white'">
                            <div class="flex items-center justify-between gap-3"><span class="text-[9px] font-bold uppercase tracking-[.18em]" :class="pass.featured ? 'text-pine-700' : 'text-white/[.45]'" x-text="pass.eyebrow"></span><span x-show="pass.featured" class="rounded-full bg-pine-950 px-3 py-1.5 text-[8px] font-bold uppercase tracking-[.15em] text-acid">Cel mai ales</span></div>
                            <h3 class="mt-5 font-display text-3xl font-semibold sm:text-4xl" x-text="pass.name"></h3>
                            <p class="mt-3 min-h-[48px] text-sm leading-6" :class="pass.featured ? 'text-pine-900/[.65]' : 'text-white/[.55]'" x-text="pass.description"></p>
                            <div class="mt-7 flex items-end gap-2"><span class="font-display text-5xl font-semibold sm:text-6xl" x-text="pass.price"></span><span class="pb-2 text-sm" x-text="pass.unit"></span></div>
                            <div class="mt-7 space-y-3">
                                <template x-for="benefit in pass.benefits" :key="benefit"><div class="flex items-start gap-3 text-sm"><svg class="mt-0.5 h-4 w-4 flex-none" :class="pass.featured ? 'text-pine-950' : 'text-acid'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg><span x-text="benefit"></span></div></template>
                            </div>
                            <button @click="bookingOpen = true; selectedBookingOption = pass.booking" class="mt-auto w-full rounded-full px-5 py-3.5 text-sm font-bold transition hover:-translate-y-1" :class="pass.featured ? 'bg-pine-950 text-white' : 'bg-cream text-pine-950'">Alege acest pass</button>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- FAQ + CTA -->
        <section class="bg-cream py-20 sm:py-28 lg:py-36">
            <div class="mx-auto max-w-[1260px] px-4 sm:px-6 lg:px-10">
                <div class="grid gap-12 lg:grid-cols-[.72fr_1.28fr] lg:gap-20">
                    <div data-reveal><div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember">Înainte să pornești</div><h2 class="mt-4 font-display text-[clamp(42px,9vw,72px)] font-semibold leading-[.96] tracking-[-.04em] text-pine-950">Lucrurile mici care fac ziua simplă.</h2><p class="mt-6 text-base leading-7 text-pine-900/60">Biletul este pe telefon, echipamentul te așteaptă, iar traseele sunt supravegheate continuu.</p></div>
                    <div data-reveal class="border-t border-pine-900/[.12]">
                        <template x-for="faq in faqs" :key="faq.q">
                            <div class="border-b border-pine-900/[.12]">
                                <button @click="openFaq = openFaq === faq.q ? null : faq.q" class="flex w-full items-center justify-between gap-5 py-5 text-left sm:py-7"><span class="font-display text-xl font-semibold text-pine-950 sm:text-2xl" x-text="faq.q"></span><span class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/[.12] transition" :class="openFaq === faq.q ? 'rotate-45 bg-pine-950 text-acid' : ''"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span></button>
                                <div x-show="openFaq === faq.q" x-collapse><p class="max-w-2xl pb-6 text-sm leading-7 text-pine-900/[.62] sm:text-base" x-text="faq.a"></p></div>
                            </div>
                        </template>
                    </div>
                </div>

                <div data-reveal class="grain relative mt-20 overflow-hidden rounded-[30px] bg-pine-950 text-white sm:mt-28">
                    <img src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1800&q=86" alt="Pădure luminată" class="absolute inset-0 h-full w-full object-cover opacity-42">
                    <div class="absolute inset-0 bg-gradient-to-r from-pine-950 via-pine-950/80 to-pine-950/25"></div>
                    <div class="relative z-10 max-w-3xl px-5 py-14 sm:px-10 sm:py-20 lg:px-16 lg:py-24">
                        <div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Următoarea ieșire începe aici</div>
                        <h2 class="mt-4 font-display text-[clamp(42px,10vw,82px)] font-semibold leading-[.94] tracking-[-.045em]">O zi în aer liber. Fără improvizație.</h2>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row"><button @click="bookingOpen = true" class="rounded-full bg-acid px-6 py-4 font-bold text-pine-950 transition hover:-translate-y-1">Alege biletele</button><a href="#planner" class="rounded-full border border-white/20 px-6 py-4 text-center font-semibold text-white transition hover:bg-white/10">Planifică ziua</a></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-pine-950 px-4 pb-8 pt-16 text-white sm:px-6 sm:pt-20 lg:px-10">
        <div class="mx-auto max-w-[1460px]">
            <div class="grid gap-12 border-b border-white/10 pb-14 lg:grid-cols-[1.25fr_.75fr_.75fr_.75fr]">
                <div><div class="font-display text-4xl font-semibold">Nordvale</div><p class="mt-4 max-w-sm text-sm leading-7 text-white/[.48]">Parc de aventură și rezervație forestieră. Concept demonstrativ pentru un tenant Tixello leisure.</p><div class="mt-6 flex gap-2"><a href="#" class="grid h-10 w-10 place-items-center rounded-full border border-white/[.12] text-xs">IG</a><a href="#" class="grid h-10 w-10 place-items-center rounded-full border border-white/[.12] text-xs">FB</a><a href="#" class="grid h-10 w-10 place-items-center rounded-full border border-white/[.12] text-xs">YT</a></div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.2em] text-acid">Explorează</div><div class="mt-5 space-y-3 text-sm text-white/[.58]"><a href="#experiente" class="block hover:text-white">Experiențe</a><a href="#program" class="block hover:text-white">Program</a><a href="#rezervatie" class="block hover:text-white">Rezervația</a><a href="#abonamente" class="block hover:text-white">Abonamente</a></div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.2em] text-acid">Vizită</div><div class="mt-5 space-y-3 text-sm text-white/[.58]"><a href="/planifica" class="block hover:text-white">Planificator</a><a href="/despre" class="block hover:text-white">Acces și parcare</a><a href="/grupuri" class="block hover:text-white">Grupuri</a><a href="/contact" class="block hover:text-white">Contact</a></div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.2em] text-acid">Astăzi</div><div class="mt-5 text-sm text-white/[.58]"><p>09:00–20:00</p><p class="mt-2">Ultima intrare: 18:30</p><button @click="bookingOpen = true" class="mt-5 rounded-full bg-acid px-4 py-3 font-bold text-pine-950">Rezervă intervalul</button></div></div>
            </div>
            <div class="flex flex-col gap-4 py-6 text-xs text-white/[.38] sm:flex-row sm:items-center sm:justify-between"><p>© <?= date('Y') ?> Nordvale. Date demonstrative.</p><div class="flex flex-wrap gap-5"><a href="/termeni" class="hover:text-white">Termeni</a><a href="/confidentialitate" class="hover:text-white">Confidențialitate</a><span>Ticketing by <a href="https://tixello.ro" class="text-acid font-semibold">tixello</a></span></div></div>
        </div>
    </footer>

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
                <button :disabled="totalGuests === 0" @click="window.location.href='/bilete'" class="w-full rounded-full bg-acid px-5 py-4 font-bold text-pine-950 shadow-acid transition disabled:cursor-not-allowed disabled:opacity-45">Continuă către bilete</button>
            </div>
        </aside>
    </div>

    <script>
        function nordvaleV3() {
            return {
                scrolled: false,
                menuOpen: false,
                bookingOpen: false,
                accountHref: '/autentificare',
                accountLabel: 'Contul meu',
                openFaq: null,
                selectedMood: 'adventure',
                activeCategory: 'all',
                activeMoment: 'morning',
                selectedBookingOption: 'day',
                selectedDate: 'sat',
                selectedTime: '10:30',

                menuItems: [
                    { label: 'Experiențe', href: '#experiente' },
                    { label: 'Planifică vizita', href: '#planner' },
                    { label: 'Program', href: '#program' },
                    { label: 'Rezervația', href: '#rezervatie' },
                    { label: 'Abonamente', href: '#abonamente' }
                ],
                stats: [
                    { value: 11, prefix: '', suffix: '', label: 'trasee în pădure' },
                    { value: 34, prefix: '', suffix: ' ha', label: 'zonă protejată' },
                    { value: 780, prefix: '', suffix: ' m', label: 'cel mai lung circuit' },
                    { value: 96, prefix: '', suffix: '%', label: 'vizitatori mulțumiți' }
                ],
                marqueeItems: ['Acces digital', 'Echipament inclus', 'Parcare gratuită', 'Trasee pentru familii', 'Ghidaj în rezervație', 'Locuri limitate pe interval'],
                categories: [
                    { id: 'all', label: 'Toate' },
                    { id: 'height', label: 'La înălțime' },
                    { id: 'family', label: 'În familie' },
                    { id: 'nature', label: 'Natură' }
                ],
                moods: [
                    { id: 'slow', name: 'Liniște & explorare', caption: 'Poteci, observare și ritm lent', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V8m0 0C9 8 6 6 6 3c3 0 6 2 6 5Zm0 4c3 0 6-2 6-5-3 0-6 2-6 5Z"/></svg>', title: 'Forest Quiet Route', description: 'O combinație de poteci senzoriale, observatoare și o plimbare ghidată prin zona umedă a rezervației.', tags: ['ritm lent', 'fără echipament', 'toate vârstele'], duration: '2–3 ore', price: 55, image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1000&q=84', booking: 'reserve' },
                    { id: 'adventure', name: 'Adrenalină controlată', caption: 'Poduri, tiroliene și provocări', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 19 7-14 4 8 3-5 4 11H3Z"/></svg>', title: 'Canopy Full Day', description: 'Acces de o zi la circuitele suspendate, tiroliana mare și zona de antrenament, cu echipament și instructaj incluse.', tags: ['6 trasee', 'echipament inclus', '12+ ani'], duration: '4–6 ore', price: 119, image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1000&q=84', booking: 'day' },
                    { id: 'family', name: 'Zi în familie', caption: 'Activități potrivite împreună', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M2 21v-2a6 6 0 0 1 12 0v2m0-4.5a5 5 0 0 1 8 4V21"/></svg>', title: 'Family Expedition', description: 'Traseu junior, atelier de orientare, mini-safari și o zonă de picnic rezervată pentru întreaga familie.', tags: ['copii 4+', 'ritm flexibil', 'picnic'], duration: '3–5 ore', price: 89, image: 'https://images.unsplash.com/photo-1504151932400-72d4384f04b3?auto=format&fit=crop&w=1000&q=84', booking: 'family' }
                ],
                experiences: [
                    { id: 1, category: 'height', level: 'Mediu', duration: '90 min', eyebrow: 'Circuit suspendat', title: 'Canopy North', copy: 'Poduri, plase și tiroliene construite în coronamentul pădurii.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1400&q=84' },
                    { id: 2, category: 'family', level: '4+ ani', duration: '60 min', eyebrow: 'Expediție junior', title: 'Little Rangers', copy: 'Un traseu de orientare, joc și descoperire pentru exploratorii mici.', image: 'https://images.unsplash.com/photo-1504151932400-72d4384f04b3?auto=format&fit=crop&w=1200&q=84' },
                    { id: 3, category: 'nature', level: 'Ușor', duration: '75 min', eyebrow: 'Tur ghidat', title: 'Wetland Walk', copy: 'Observatoare ascunse și povești despre viața din zona umedă.', image: 'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1000&q=84' },
                    { id: 4, category: 'height', level: 'Avansat', duration: '45 min', eyebrow: 'Tiroliană', title: 'The Long Flight', copy: 'Cea mai lungă traversare din parc, la 26 de metri de sol.', image: 'https://images.unsplash.com/photo-1500534623283-312aade485b7?auto=format&fit=crop&w=1000&q=84' },
                    { id: 5, category: 'family', level: 'Toate vârstele', duration: 'liber', eyebrow: 'Relaxare', title: 'Forest Basecamp', copy: 'Hamac, picnic, cafea și spațiu de joacă natural.', image: 'https://images.unsplash.com/photo-1475483768296-6163e08872a1?auto=format&fit=crop&w=1000&q=84' }
                ],
                dayMoments: [
                    { id: 'morning', time: '09:00', title: 'Pădurea se deschide', caption: 'Lumină moale, poteci libere, aer rece.', description: 'Cele mai liniștite ore pentru tururi ghidate și traseele albastre.', image: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=84' },
                    { id: 'midday', time: '11:30', title: 'Canopy în plin ritm', caption: 'Toate traseele și atelierele sunt active.', description: 'Intervalul ideal pentru circuitele suspendate și tiroliana principală.', image: 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1200&q=84' },
                    { id: 'afternoon', time: '15:00', title: 'Expediția de după-amiază', caption: 'Umbra se întoarce pe poteci.', description: 'Tururi de familie, activități junior și pauze la basecamp.', image: 'https://images.unsplash.com/photo-1504151932400-72d4384f04b3?auto=format&fit=crop&w=1200&q=84' },
                    { id: 'evening', time: '18:30', title: 'Ultima traversare', caption: 'Lumina cade printre brazi.', description: 'Ultimul slot pentru tiroliană și circuitul panoramic.', image: 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=84' }
                ],
                passes: [
                    { id: 'reserve', eyebrow: 'Vizită relaxată', name: 'Reserve Pass', description: 'Pentru poteci, observatoare și activități la sol.', price: '55', unit: 'lei / persoană', benefits: ['Acces în rezervație', 'Tur ghidat la alegere', 'Zone de picnic'], booking: 'reserve', featured: false },
                    { id: 'day', eyebrow: 'Zi completă', name: 'Adventure Day', description: 'Acces complet la experiențele de aventură ale parcului.', price: '119', unit: 'lei / persoană', benefits: ['Toate traseele deschise', 'Echipament și instructaj', 'O tiroliană mare'], booking: 'day', featured: true },
                    { id: 'season', eyebrow: 'Pentru cei care revin', name: 'Forest Season', description: 'Intrări nelimitate și acces prioritar pe tot sezonul.', price: '499', unit: 'lei / sezon', benefits: ['Acces nelimitat', 'Rezervare prioritară', '10% pentru invitați'], booking: 'season', featured: false }
                ],
                faqs: [
                    { q: 'Trebuie să rezerv în avans?', a: 'Recomandăm rezervarea intervalului, mai ales în weekend. Capacitatea pe trasee este limitată pentru siguranță și confort.' },
                    { q: 'Ce se întâmplă dacă plouă?', a: 'Parcul rămâne deschis în ploaie ușoară. În caz de furtună sau vânt puternic, traseele la înălțime se suspendă, iar biletele pot fi reprogramate.' },
                    { q: 'Echipamentul este inclus?', a: 'Da. Hamul, casca și sistemul de siguranță sunt incluse în biletul pentru trasee. Îți recomandăm încălțăminte sport și haine comode.' },
                    { q: 'Există activități pentru copii mici?', a: 'Da. Little Rangers, potecile senzoriale și Forest Basecamp sunt potrivite de la 4 ani, cu supravegherea unui adult.' }
                ],
                bookingOptions: [
                    { id: 'reserve', name: 'Reserve Pass', caption: 'Poteci și tururi la sol', duration: '2–3 ore', price: 55 },
                    { id: 'day', name: 'Adventure Day', caption: 'Acces complet o zi', duration: '4–6 ore', price: 119 },
                    { id: 'family', name: 'Family Expedition', caption: 'Pachet de familie', duration: '3–5 ore', price: 89 },
                    { id: 'season', name: 'Forest Season', caption: 'Abonament de sezon', duration: 'sezon', price: 499 }
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

                init() {
                    this.scrolled = window.scrollY > 24;
                    window.addEventListener('scroll', () => { this.scrolled = window.scrollY > 24; }, { passive: true });
                    try {
                        const a = JSON.parse(localStorage.getItem('nordvale_auth') || 'null');
                        if (a && a.token) {
                            this.accountHref = '/cont';
                            const u = a.user || {};
                            const fn = (u.first_name || (u.name || '').trim().split(/\s+/)[0] || '').trim();
                            this.accountLabel = fn ? ('Salut, ' + fn) : 'Contul meu';
                        }
                    } catch (e) {}
                    this.$watch('menuOpen', value => document.body.style.overflow = value || this.bookingOpen ? 'hidden' : '');
                    this.$watch('bookingOpen', value => document.body.style.overflow = value || this.menuOpen ? 'hidden' : '');
                },
                get activeMood() { return this.moods.find(item => item.id === this.selectedMood) || this.moods[1]; },
                get filteredExperiences() { return this.activeCategory === 'all' ? this.experiences : this.experiences.filter(item => item.category === this.activeCategory); },
                get activeDayMoment() { return this.dayMoments.find(item => item.id === this.activeMoment) || this.dayMoments[0]; },
                get totalGuests() { return this.bookingPeople.reduce((sum, item) => sum + item.count, 0); },
                get bookingTotal() {
                    const option = this.bookingOptions.find(item => item.id === this.selectedBookingOption);
                    if (!option) return 0;
                    const adult = this.bookingPeople.find(item => item.id === 'adult')?.count || 0;
                    const teen = this.bookingPeople.find(item => item.id === 'teen')?.count || 0;
                    const child = this.bookingPeople.find(item => item.id === 'child')?.count || 0;
                    return adult * option.price + teen * Math.round(option.price * .82) + child * Math.round(option.price * .64);
                }
            };
        }
    </script>

    <script type="module">
        import { animate, scroll, inView, stagger } from 'https://cdn.jsdelivr.net/npm/motion@12.42.1/+esm';

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const intro = document.querySelector('[data-intro]');
        window.clearTimeout(window.__introSafety);

        const revealEverything = () => {
            document.documentElement.classList.remove('reveal-ready');
            document.querySelectorAll('[data-reveal], [data-hero-kicker], [data-hero-title], [data-hero-copy], [data-hero-cta], [data-hero-visual]').forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'none';
            });
        };

        if (!reduceMotion) {
            // Intro sequence with Anime.js, then Motion takes over.
            if (window.anime && intro) {
                anime.timeline({ easing: 'easeOutExpo' })
                    .add({ targets: '[data-intro-word]', translateY: ['110%', '0%'], duration: 900, delay: 120 })
                    .add({ targets: '.intro-line', scaleX: [0, 1], duration: 650 }, '-=500')
                    .add({ targets: '[data-intro-copy]', opacity: [0, 1], translateY: [12, 0], duration: 520 }, '-=380')
                    .add({ targets: intro, opacity: [1, 0], duration: 520, delay: 360, complete: () => intro.classList.add('is-hidden') });
            } else {
                intro?.classList.add('is-hidden');
            }

            animate('[data-hero-kicker]', { opacity: [0, 1], y: [22, 0] }, { duration: .72, delay: .78, ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-title]', { opacity: [0, 1], y: [54, 0] }, { duration: 1.05, delay: stagger(.11, { startDelay: .88 }), ease: [0.16, 1, 0.3, 1] });
            animate('[data-hero-copy]', { opacity: [0, 1], y: [22, 0] }, { duration: .82, delay: stagger(.08, { startDelay: 1.28 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-cta]', { opacity: [0, 1], y: [22, 0] }, { duration: .82, delay: 1.42, ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-visual]', { opacity: [0, 1], x: [62, 0], rotate: [1.5, 0] }, { duration: 1.18, delay: 1.02, ease: [0.16, 1, 0.3, 1] });

            document.querySelectorAll('[data-reveal]').forEach((element) => {
                inView(element, () => {
                    animate(element, { opacity: [0, 1], y: [34, 0] }, { duration: .82, ease: [0.22, 1, 0.36, 1] });
                }, { margin: '0px 0px -9% 0px', amount: .12 });
            });

            scroll(animate('#hero-bg', { y: ['0%', '12%'], scale: [1.02, 1.11] }), { target: document.querySelector('#top'), offset: ['start start', 'end start'] });
            scroll(animate('#reserve-image-a', { y: ['-3%', '8%'] }), { target: document.querySelector('#rezervatie'), offset: ['start end', 'end start'] });
            scroll(animate('#reserve-image-b', { y: ['5%', '-7%'] }), { target: document.querySelector('#rezervatie'), offset: ['start end', 'end start'] });

            // Anime.js route marker and dash animation.
            const route = document.querySelector('#hero-route');
            const marker = document.querySelector('#route-marker');
            if (window.anime && route && marker) {
                const path = anime.path('#hero-route');
                anime({ targets: '#hero-route', strokeDashoffset: [anime.setDashoffset, -260], easing: 'linear', duration: 18000, loop: true });
                anime({ targets: marker, translateX: path('x'), translateY: path('y'), rotate: path('angle'), easing: 'easeInOutSine', duration: 7600, loop: true, direction: 'alternate' });
            }

            // Count up when stats become visible.
            const statsBlock = document.querySelector('.js-counter')?.closest('div[class*="grid"]');
            if (statsBlock && window.anime) {
                inView(statsBlock, () => {
                    document.querySelectorAll('.js-counter').forEach(el => {
                        const target = Number(el.dataset.value || 0);
                        const text = el.textContent;
                        const prefix = text.match(/^\D*/)?.[0] || '';
                        const suffix = text.match(/\D*$/)?.[0] || '';
                        const counter = { value: 0 };
                        anime({ targets: counter, value: target, round: 1, duration: 1600, easing: 'easeOutExpo', update: () => { el.textContent = `${prefix}${counter.value}${suffix}`; } });
                    });
                }, { amount: .4 });
            }

            // Desktop-only subtle tilt.
            if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
                document.querySelectorAll('.experience-card').forEach(card => {
                    const inner = card.querySelector('.experience-card__inner');
                    card.addEventListener('pointermove', event => {
                        const rect = card.getBoundingClientRect();
                        const x = (event.clientX - rect.left) / rect.width - .5;
                        const y = (event.clientY - rect.top) / rect.height - .5;
                        inner.style.transform = `rotateY(${x * 5}deg) rotateX(${-y * 5}deg) translateY(-4px)`;
                    });
                    card.addEventListener('pointerleave', () => { inner.style.transform = ''; });
                });
            }
        } else {
            intro?.classList.add('is-hidden');
            revealEverything();
        }

        scroll(progress => {
            const line = document.querySelector('#page-progress');
            if (line) line.style.transform = `scaleX(${progress})`;
        });

        // Failsafe if a CDN script is blocked.
        window.setTimeout(() => {
            intro?.classList.add('is-hidden');
            revealEverything();
        }, 3500);
    </script>
</body>
</html>
