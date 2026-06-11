<?php
/**
 * /cum-functioneaza — How it works landing page.
 * Static content, no API calls.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . "/includes/api.php";

// 30-minute page cache — static / rarely-changing content. Skips POST,
// preview, nocache, and admin sessions (see includes/page-cache.php).
$pageCacheTTL = 1800;
require_once __DIR__ . "/includes/page-cache.php";

$pageTitleRaw = 'Cum funcționează bilete.online — rezervi activități, primești QR, intri rapid';
$pageDescription = 'Află cum funcționează bilete.online: descoperi activități, alegi data și biletele, plătești online, primești QR instant, câștigi puncte bonus și intri rapid la locație.';
$pageKeywords = 'cum funcționează bilete online, bilete QR activități, rezervare activități online, cumpărare bilete online, puncte bonus bilete, protecție bilet, card cadou experiențe';
$canonicalUrl = SITE_URL . '/cum-functioneaza';
$currentPage = 'cum-functioneaza';
$cssBundle = 'static';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Cum funcționează', 'url' => $canonicalUrl],
];

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'Cum cumperi bilete pe bilete.online',
        'description' => 'Pașii principali pentru a cumpăra bilete la activități prin bilete.online.',
        'totalTime' => 'PT2M',
        'step' => [
            ['@type' => 'HowToStep', 'name' => 'Alegi activitatea', 'text' => 'Cauți după oraș, categorie, public, buget sau momentul potrivit.'],
            ['@type' => 'HowToStep', 'name' => 'Selectezi biletele', 'text' => 'Alegi data, ora, tipul de bilet și numărul de participanți.'],
            ['@type' => 'HowToStep', 'name' => 'Plătești online', 'text' => 'Plătești securizat cu cardul, Apple Pay, Google Pay, Revolut Pay sau RoPay, în funcție de metodele disponibile.'],
            ['@type' => 'HowToStep', 'name' => 'Primești QR', 'text' => 'După confirmarea plății, biletele sunt emise electronic și trimise pe email și în cont.'],
            ['@type' => 'HowToStep', 'name' => 'Intri la locație', 'text' => 'La intrare arăți codul QR de pe telefon sau din PDF-ul biletului.'],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => 'Trebuie să printez biletul?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nu. În mod normal poți arăta codul QR de pe telefon. Dacă o locație are reguli speciale, acestea sunt afișate pe pagina activității.']],
            ['@type' => 'Question', 'name' => 'Când primesc biletele?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Biletele sunt emise după confirmarea plății și sunt trimise pe email. Dacă ai cont, le găsești și în zona Biletele mele.']],
            ['@type' => 'Question', 'name' => 'Pot pune nume diferite pe bilete?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da. În checkout poți alege dacă toate biletele au același beneficiar sau dacă fiecare bilet are alt nume.']],
            ['@type' => 'Question', 'name' => 'Pot folosi puncte bonus?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da. Punctele bonus disponibile pot fi folosite în coș sau checkout, conform regulilor programului de loialitate.']],
            ['@type' => 'Question', 'name' => 'Ce este protecția bilet?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Protecția bilet este o opțiune pe care o poți adăuga la checkout pentru flexibilitate suplimentară în cazul în care nu mai poți ajunge.']],
        ],
    ],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_12%,rgba(232,69,39,.25),transparent_28%),radial-gradient(circle_at_12%_70%,rgba(30,74,61,.22),transparent_34%)]" aria-hidden="true"></div>
    <div class="absolute left-0 right-0 bottom-0 h-24 bg-gradient-to-t from-paper to-transparent" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-14 sm:pb-20">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span aria-hidden="true">/</span>
            <span class="text-ink">Cum funcționează</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1.02fr_.98fr] gap-10 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">GHID RAPID · BILETE QR · ACTIVITĂȚI</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl lg:text-[6.8rem] font-700 leading-[.82]">Cauți. Rezervi. Intri cu QR.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">bilete.online îți adună într-un singur loc activități, experiențe și locuri de vizitat. Alegi ce vrei să faci, plătești online, primești biletul instant și mergi direct la intrare.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="/categorii" class="rounded-full bg-vermilion text-paper px-6 py-4 font-700 text-lg hover:bg-vermilion-d transition">Explorează activități</a>
                    <a href="#pasii" class="rounded-full border-2 border-ink px-6 py-4 font-700 text-lg hover:bg-ink hover:text-paper transition">Vezi pașii</a>
                </div>
                <dl class="mt-10 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/70 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">EMITERE</dt><dd class="mt-1 font-display text-3xl font-700">instant</dd></div>
                    <div class="rounded-2xl bg-paper/70 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">ACCES</dt><dd class="mt-1 font-display text-3xl font-700">QR</dd></div>
                    <div class="rounded-2xl bg-paper/70 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">BONUS</dt><dd class="mt-1 font-display text-3xl font-700">puncte</dd></div>
                </dl>
            </div>

            <!-- ticket mockup -->
            <div class="relative min-h-[560px]" aria-hidden="true">
                <div class="absolute inset-x-6 top-8 bottom-0 rounded-[2.2rem] bg-ink rotate-[-2deg] shadow-deep"></div>
                <div class="ticket absolute left-0 right-0 top-0 mx-auto max-w-[510px] bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep rotate-[1.5deg]" style="--perf:68%;--punch:#F4EFE3">
                    <span class="perf hidden sm:block"></span><span class="notch top hidden sm:block"></span><span class="notch bot hidden sm:block"></span>
                    <div class="grid sm:grid-cols-[1fr_170px]">
                        <div class="p-6 sm:p-7">
                            <div class="flex items-center justify-between gap-4">
                                <span class="px-3 py-1 rounded-full bg-vermilion/10 text-vermilion text-xs font-700">BILET VALID</span>
                                <span class="font-mono text-xs text-ink-soft">#BO-2026-004218</span>
                            </div>
                            <h2 class="mt-5 font-display text-4xl font-700 leading-none">Camera 13</h2>
                            <p class="mt-2 text-ink-soft">Escape room · Brașov</p>
                            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                                <div><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft uppercase">Data</dt><dd class="font-700">31 mai 2026</dd></div>
                                <div><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft uppercase">Ora</dt><dd class="font-700">18:30</dd></div>
                                <div><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft uppercase">Beneficiar</dt><dd class="font-700">Andrei N.</dd></div>
                                <div><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft uppercase">Acces</dt><dd class="font-700">QR scan</dd></div>
                            </dl>
                            <div class="mt-7 rounded-2xl bg-mint border border-forest/20 p-4">
                                <p class="font-700 text-forest">+95 puncte bonus</p>
                                <p class="text-sm text-ink-soft">Se adaugă în cont după confirmarea plății.</p>
                            </div>
                        </div>
                        <div class="p-6 bg-paper-2/80 grid place-items-center border-t-2 sm:border-t-0 sm:border-l-2 border-dashed border-ink/15">
                            <div class="relative w-36 h-36 rounded-2xl border-2 border-ink bg-paper grid place-items-center overflow-hidden">
                                <svg viewBox="0 0 120 120" class="w-28 h-28" aria-hidden="true">
                                    <rect width="120" height="120" fill="#F4EFE3"/>
                                    <path d="M8 8h32v32H8zM80 8h32v32H80zM8 80h32v32H8z" fill="#1B1714"/>
                                    <path d="M16 16h16v16H16zM88 16h16v16H88zM16 88h16v16H16z" fill="#F4EFE3"/>
                                    <path d="M54 12h8v8h-8zm12 0h8v20h-8zM52 52h12v12H52zm20 0h8v8h-8zm12 12h24v8H84zm-28 18h8v24h-8zm16 0h12v12H72zm24 12h12v16H96zM44 72h20v8H44z" fill="#1B1714"/>
                                </svg>
                                <span class="scanline absolute left-3 right-3 top-0 h-0.5 bg-vermilion shadow-[0_0_12px_rgba(232,69,39,.85)]"></span>
                            </div>
                            <p class="mt-4 text-xs font-mono text-ink-soft text-center">scanezi la intrare</p>
                        </div>
                    </div>
                </div>
                <div class="floaty absolute -left-2 sm:left-2 bottom-20 w-56 rounded-3xl border-2 border-ink bg-forest text-paper p-5 shadow-deep -rotate-6">
                    <p class="font-mono text-[10px] tracking-[.18em] text-paper/50">CHECKOUT</p>
                    <p class="mt-2 font-display text-3xl font-700">2 minute</p>
                    <p class="mt-1 text-paper/60 text-sm">alegi biletul, plătești, primești QR</p>
                </div>
                <div class="floaty absolute right-0 bottom-3 w-64 rounded-3xl border-2 border-ink bg-paper p-5 shadow-deep rotate-6" style="animation-delay:-2.2s">
                    <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">METODE PLATĂ</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-700">
                        <span class="rounded-full bg-ink text-paper px-3 py-1">Card</span>
                        <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1">Apple Pay</span>
                        <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1">Google Pay</span>
                        <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1">Revolut</span>
                        <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1">RoPay</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MARQUEE -->
<section class="marquee-wrap overflow-hidden border-b-2 border-ink bg-ink text-paper py-3" aria-hidden="true">
    <div class="marquee flex w-[200%] gap-8 font-mono text-xs tracking-[.2em] text-paper/70">
        <div class="flex gap-8 min-w-1/2"><span>ESCAPE ROOMS</span><span>MUZEE</span><span>PARCURI DE DISTRACȚII</span><span>PEȘTERI</span><span>REZERVAȚII</span><span>ATELIERE</span><span>CARDURI CADOU</span><span>PUNCTE BONUS</span></div>
        <div class="flex gap-8 min-w-1/2"><span>ESCAPE ROOMS</span><span>MUZEE</span><span>PARCURI DE DISTRACȚII</span><span>PEȘTERI</span><span>REZERVAȚII</span><span>ATELIERE</span><span>CARDURI CADOU</span><span>PUNCTE BONUS</span></div>
    </div>
</section>

<!-- VALUE PROPS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="grid md:grid-cols-3 gap-4">
        <article class="soft-card p-6">
            <div class="w-12 h-12 rounded-2xl bg-vermilion text-paper grid place-items-center"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></div>
            <h2 class="mt-5 font-display text-3xl font-700">Găsești locuri de făcut, nu doar evenimente.</h2>
            <p class="mt-3 text-ink-soft leading-relaxed">bilete.online este gândit pentru activități: camere de evadare, muzee, parcuri, tururi, natură, ateliere, experiențe pentru copii și multe altele.</p>
        </article>
        <article class="soft-card p-6 bg-mint">
            <div class="w-12 h-12 rounded-2xl bg-forest text-paper grid place-items-center"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-5"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
            <h2 class="mt-5 font-display text-3xl font-700">Știi ce cumperi înainte să ajungi acolo.</h2>
            <p class="mt-3 text-ink-soft leading-relaxed">Fiecare pagină arată ce include biletul, cât durează, pentru cine e potrivit, unde mergi, cum intri și ce reguli trebuie să știi.</p>
        </article>
        <article class="soft-card p-6">
            <div class="w-12 h-12 rounded-2xl bg-ochre text-ink grid place-items-center"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h2 class="mt-5 font-display text-3xl font-700">Primești beneficii la fiecare achiziție.</h2>
            <p class="mt-3 text-ink-soft leading-relaxed">Comenzile pot aduce puncte bonus, cardurile cadou se pot folosi pe platformă, iar protecția bilet îți oferă flexibilitate suplimentară.</p>
        </article>
    </div>
</section>

<!-- 5 STEPS -->
<section id="pasii" class="border-y-2 border-ink bg-paper-2/55">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.75fr_1.25fr] gap-10 items-start">
            <div class="lg:sticky lg:top-28">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">5 PAȘI</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">De la idee la intrare, fără fricțiune.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Procesul este același indiferent dacă alegi un escape room, un muzeu, o peșteră, o rezervație sau un atelier pentru copii.</p>
                <a href="/categorii" class="mt-7 inline-flex rounded-full bg-ink text-paper px-6 py-4 font-700 hover:bg-vermilion transition">Începe cu o categorie</a>
            </div>

            <div class="space-y-5">
                <?php
                $steps = [
                    ['n' => 1, 'bg' => 'bg-vermilion', 'txt' => 'text-paper', 'label' => 'DESCOPERIRE', 'title' => 'Alegi ce vrei să faci.', 'body' => 'Cauți după oraș, categorie, intenție sau context: activități pentru copii, indoor, weekend, sub 50 lei, zile ploioase, cupluri, grupuri sau team building.', 'side_label' => 'EXEMPLE', 'side_html' => '<div class="mt-3 flex flex-wrap gap-2 text-xs font-700"><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">Brașov</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">copii</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">indoor</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">azi</span></div>'],
                    ['n' => 2, 'bg' => 'bg-forest',    'txt' => 'text-paper', 'label' => 'SELECȚIE',    'title' => 'Verifici detaliile și alegi biletele.', 'body' => 'Pe pagina activității vezi descrierea, programul, prețul, locația, durata, vârsta recomandată, regulile, beneficiile incluse și întrebările frecvente.', 'side_label' => 'DECIZII', 'side_html' => '<ul class="mt-3 space-y-2 text-sm text-ink-soft"><li>• dată &amp; oră</li><li>• tip bilet</li><li>• număr persoane</li><li>• beneficii opționale</li></ul>'],
                    ['n' => 3, 'bg' => 'bg-ochre',     'txt' => 'text-ink',   'label' => 'CHECKOUT',    'title' => 'Plătești online și poți personaliza comanda.', 'body' => 'Poți continua ca vizitator, te poți loga în cont sau îți poți crea cont automat. Dacă sunt mai mulți beneficiari, poți pune nume diferite pe biletele cumpărate.', 'side_label' => 'PLATĂ', 'side_html' => '<div class="mt-3 grid grid-cols-1 gap-2 text-xs font-700"><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">Card</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">Apple Pay</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">Google Pay</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">Revolut Pay</span><span class="rounded-full bg-paper px-3 py-1 border border-ink/10">RoPay</span></div>'],
                    ['n' => 4, 'bg' => 'bg-sky',       'txt' => 'text-paper', 'label' => 'EMITERE',     'title' => 'Primești biletele cu QR.', 'body' => 'După confirmarea plății, biletele sunt emise electronic. Le primești pe email, le vezi în cont și le poți descărca PDF sau adăuga în calendar.', 'side_label' => 'AI ACCES LA', 'side_html' => '<ul class="mt-3 space-y-2 text-sm text-ink-soft"><li>• PDF bilet</li><li>• QR code</li><li>• email confirmare</li><li>• calendar</li></ul>'],
                ];
                foreach ($steps as $s):
                ?>
                    <article class="ticket bg-paper border-2 border-ink rounded-3xl overflow-hidden shadow-ticket" style="--perf:82%;--punch:#EBE2CF">
                        <span class="perf hidden md:block"></span><span class="notch top hidden md:block"></span><span class="notch bot hidden md:block"></span>
                        <div class="grid md:grid-cols-[1fr_180px]">
                            <div class="p-6 sm:p-7">
                                <div class="flex items-center gap-4">
                                    <span class="grid place-items-center w-12 h-12 rounded-2xl <?= $s['bg'] ?> <?= $s['txt'] ?> font-display text-3xl font-700"><?= $s['n'] ?></span>
                                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft"><?= $s['label'] ?></p>
                                </div>
                                <h3 class="mt-5 font-display text-4xl font-700"><?= htmlspecialchars($s['title']) ?></h3>
                                <p class="mt-3 text-ink-soft leading-relaxed"><?= htmlspecialchars($s['body']) ?></p>
                            </div>
                            <div class="bg-paper-2/80 p-6 border-t-2 md:border-t-0 md:border-l-2 border-dashed border-ink/15">
                                <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft"><?= $s['side_label'] ?></p>
                                <?= $s['side_html'] ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

                <!-- Step 5: dark ticket variant -->
                <article class="ticket bg-ink text-paper border-2 border-ink rounded-3xl overflow-hidden shadow-ticket" style="--perf:82%;--punch:#EBE2CF">
                    <span class="perf hidden md:block border-paper/30"></span><span class="notch top hidden md:block"></span><span class="notch bot hidden md:block"></span>
                    <div class="grid md:grid-cols-[1fr_180px]">
                        <div class="p-6 sm:p-7">
                            <div class="flex items-center gap-4">
                                <span class="grid place-items-center w-12 h-12 rounded-2xl bg-vermilion text-paper font-display text-3xl font-700">5</span>
                                <p class="font-mono text-xs tracking-[.18em] text-paper/45">ACCES</p>
                            </div>
                            <h3 class="mt-5 font-display text-4xl font-700">Mergi la locație și scanezi biletul.</h3>
                            <p class="mt-3 text-paper/60 leading-relaxed">La intrare arăți codul QR de pe telefon. Locația validează biletul, iar tu intri fără tipărire obligatorie și fără să cauți confirmări prin email.</p>
                        </div>
                        <div class="bg-paper text-ink p-6 border-t-2 md:border-t-0 md:border-l-2 border-dashed border-paper/15 grid place-items-center">
                            <div class="w-28 h-28 rounded-2xl border-2 border-ink bg-paper grid place-items-center font-mono text-xs text-center">QR<br>VALID</div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- FOR WHO / ACTIVITY TYPES -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="max-w-3xl">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PENTRU CINE</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Aceeași platformă, mai multe feluri de a ieși din casă.</h2>
        <p class="mt-5 text-lg text-ink-soft leading-relaxed">Nu toate activitățile se cumpără la fel. Un escape room are sloturi, un muzeu poate avea bilete simple, o peșteră poate avea tururi ghidate, iar un parc de distracții poate avea pachete. Platforma le poate susține pe toate.</p>
    </div>
    <div class="mt-10 grid lg:grid-cols-4 gap-4">
        <?php
        $activities = [
            ['img' => 'https://images.unsplash.com/photo-1517586979036-b7d1e86b3345?auto=format&fit=crop&w=800&q=80', 'alt' => 'Escape room', 'kicker' => 'GRUPURI',  'title' => 'Escape rooms',         'desc' => 'Alegi ora, numărul de persoane și primești biletele pentru toată echipa.'],
            ['img' => 'https://images.unsplash.com/photo-1566127444979-b3d2b654e3d7?auto=format&fit=crop&w=800&q=80', 'alt' => 'Muzeu',       'kicker' => 'CULTURĂ',  'title' => 'Muzee & expoziții',     'desc' => 'Cumperi bilete de acces, tururi ghidate sau expoziții temporare.'],
            ['img' => 'https://images.unsplash.com/photo-1528543606781-2f6e6857f318?auto=format&fit=crop&w=800&q=80', 'alt' => 'Aventură',    'kicker' => 'OUTDOOR',  'title' => 'Parcuri & aventură',    'desc' => 'Alegi pachete, categorii de vârstă, intervale sau bilete de acces.'],
            ['img' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'alt' => 'Peisaj',      'kicker' => 'NATURĂ',   'title' => 'Peșteri & rezervații',  'desc' => 'Vezi program, reguli de acces, dificultate și detalii pentru vizitare.'],
        ];
        foreach ($activities as $a):
        ?>
            <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                <img src="<?= htmlspecialchars($a['img'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($a['alt'], ENT_QUOTES) ?>" class="h-44 w-full object-cover" loading="lazy">
                <div class="p-5">
                    <p class="text-xs font-700 text-vermilion"><?= htmlspecialchars($a['kicker']) ?></p>
                    <h3 class="mt-2 font-display text-2xl font-700"><?= htmlspecialchars($a['title']) ?></h3>
                    <p class="mt-2 text-ink-soft"><?= htmlspecialchars($a['desc']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- CHECKOUT INTELLIGENCE (dark) -->
<section class="bg-ink text-paper border-y-2 border-ink">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">CHECKOUT INTELIGENT</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Mai mult decât „plătește și gata".</h2>
                <p class="mt-5 text-lg text-paper/60 leading-relaxed">Checkout-ul este locul unde comanda devine clară: cine merge, ce bilete se emit, ce taxe se aplică, câte puncte primești și ce opțiuni suplimentare alegi.</p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><h3 class="font-display text-2xl font-700">Beneficiari diferiți</h3><p class="mt-2 text-paper/55">Poți pune alt nume pe fiecare bilet, util pentru grupuri sau cadouri.</p></div>
                    <div class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><h3 class="font-display text-2xl font-700">Cont automat</h3><p class="mt-2 text-paper/55">Poți cumpăra rapid, iar contul se poate crea automat după comandă.</p></div>
                    <div class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><h3 class="font-display text-2xl font-700">Puncte bonus</h3><p class="mt-2 text-paper/55">Vezi ce câștigi și poți folosi punctele disponibile.</p></div>
                    <div class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><h3 class="font-display text-2xl font-700">Protecție bilet</h3><p class="mt-2 text-paper/55">Poți adăuga flexibilitate suplimentară pentru retur, unde este disponibilă.</p></div>
                </div>
            </div>
            <div class="ticket bg-paper text-ink rounded-[2rem] border-2 border-paper overflow-hidden shadow-deep rotate-3" style="--perf:100%">
                <div class="p-6 sm:p-7 border-b-2 border-dashed border-ink/15">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUMAR CHECKOUT</p>
                    <h3 class="mt-2 font-display text-4xl font-700">Comandă clară</h3>
                </div>
                <div class="p-6 sm:p-7 space-y-4 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-ink-soft">Bilete</span><strong>316,00 lei</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-ink-soft">Comision platformă — 2% / bilet</span><strong>6,32 lei</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-ink-soft">Protecție bilet</span><strong>60,00 lei</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-ink-soft">Puncte bonus folosite</span><strong class="text-vermilion">− 8,20 lei</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-ink-soft">Taxă procesare plată</span><strong>5,83 lei</strong></div>
                    <div class="pt-4 border-t border-ink/10 flex justify-between gap-4 text-lg"><span>Total</span><strong class="font-display text-3xl">379,95 lei</strong></div>
                    <div class="rounded-2xl bg-mint border border-forest/20 p-4"><p class="font-700 text-forest">Primești +158 puncte bonus</p><p class="text-sm text-ink-soft">Se adaugă în cont după confirmarea comenzii.</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ACCOUNT -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-10 items-start">
        <div>
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CONT CLIENT</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">După cumpărare, totul rămâne organizat.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Contul clientului nu este doar un login. Este locul unde găsești biletele, comenzile, punctele bonus, cardurile cadou, recenziile și setările tale.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <article class="rounded-3xl border-2 border-ink bg-paper p-6"><h3 class="font-display text-3xl font-700">Biletele mele</h3><p class="mt-2 text-ink-soft">PDF-uri, coduri QR, statusuri, calendar și detalii de acces.</p></article>
            <article class="rounded-3xl border-2 border-ink bg-paper p-6"><h3 class="font-display text-3xl font-700">Comenzile mele</h3><p class="mt-2 text-ink-soft">Istoric, totaluri, comisioane, taxe, chitanțe și statusuri.</p></article>
            <article class="rounded-3xl border-2 border-forest/30 bg-mint p-6"><h3 class="font-display text-3xl font-700">Punctele mele</h3><p class="mt-2 text-ink-soft">Sold disponibil, istoric și conversie în discount.</p></article>
            <article class="rounded-3xl border-2 border-ink bg-ink text-paper p-6"><h3 class="font-display text-3xl font-700">Carduri cadou</h3><p class="mt-2 text-paper/60">Carduri primite, cumpărate, solduri și coduri active.</p></article>
        </div>
    </div>
</section>

<!-- SECONDARY VALUE PROPS -->
<section class="border-y-2 border-ink bg-paper-2/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <div class="grid md:grid-cols-4 gap-4">
            <article class="rounded-3xl bg-paper border-2 border-ink/12 p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">QR</p><h3 class="mt-2 font-display text-2xl font-700">Coduri unice</h3><p class="mt-2 text-sm text-ink-soft">Fiecare bilet are cod propriu, status și validare la intrare.</p></article>
            <article class="rounded-3xl bg-paper border-2 border-ink/12 p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">PAYMENT</p><h3 class="mt-2 font-display text-2xl font-700">Plăți moderne</h3><p class="mt-2 text-sm text-ink-soft">Card și wallet-uri digitale, în funcție de configurarea disponibilă.</p></article>
            <article class="rounded-3xl bg-paper border-2 border-ink/12 p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">SUPPORT</p><h3 class="mt-2 font-display text-2xl font-700">Recuperare comandă</h3><p class="mt-2 text-sm text-ink-soft">Poți recupera biletele cu emailul și numărul comenzii.</p></article>
            <article class="rounded-3xl bg-paper border-2 border-ink/12 p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">SEO</p><h3 class="mt-2 font-display text-2xl font-700">Pagini clare</h3><p class="mt-2 text-sm text-ink-soft">Fiecare activitate are informații utile, nu doar un buton de cumpărare.</p></article>
        </div>
    </div>
</section>

<!-- FOR LOCATIONS CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="ticket bg-ink text-paper rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
        <div class="grid lg:grid-cols-[1fr_.9fr]">
            <div class="p-8 sm:p-10">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">ȘI PENTRU LOCAȚII</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Locațiile primesc o platformă de vânzare, nu doar un formular.</h2>
                <p class="mt-5 text-lg text-paper/60 leading-relaxed">Pentru organizatori și locații, bilete.online înseamnă pagini SEO, bilete QR, checkout, dashboard, scanări, rapoarte, carduri cadou, puncte bonus și posibilitatea de a transforma activitățile în produse ușor de cumpărat.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="/pentru-locatii" class="rounded-full bg-vermilion text-paper px-6 py-4 font-700 hover:bg-vermilion-d transition">Vezi pagina pentru locații</a>
                </div>
            </div>
            <div class="bg-paper text-ink p-8 sm:p-10">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="rounded-3xl bg-paper-2/70 border border-ink/10 p-5"><p class="font-display text-4xl font-700">SEO</p><p class="mt-2 text-ink-soft">Pagini pentru locație, activități, orașe și categorii.</p></div>
                    <div class="rounded-3xl bg-paper-2/70 border border-ink/10 p-5"><p class="font-display text-4xl font-700">QR</p><p class="mt-2 text-ink-soft">Scanare rapidă și statusuri clare pentru bilete.</p></div>
                    <div class="rounded-3xl bg-paper-2/70 border border-ink/10 p-5"><p class="font-display text-4xl font-700">Data</p><p class="mt-2 text-ink-soft">Comenzi, clienți, rapoarte și disponibilitate.</p></div>
                    <div class="rounded-3xl bg-paper-2/70 border border-ink/10 p-5"><p class="font-display text-4xl font-700">Growth</p><p class="mt-2 text-ink-soft">Promoții, carduri cadou, puncte și recenzii.</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="border-y-2 border-ink bg-paper-2/55" x-data="{ open: 0 }">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="text-center max-w-3xl mx-auto">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Întrebări frecvente</h2>
            <p class="mt-5 text-lg text-ink-soft">Cele mai importante lucruri pe care trebuie să le știi înainte să cumperi.</p>
        </div>
        <div class="mt-10 space-y-3">
            <?php
            $faqs = [
                ['q' => 'Trebuie să printez biletul?',                       'a' => 'Nu. În mod normal poți arăta codul QR de pe telefon. Dacă o locație are reguli speciale, acestea sunt afișate pe pagina activității și în bilet.'],
                ['q' => 'Când primesc biletele?',                            'a' => 'După confirmarea plății, biletele sunt emise electronic și trimise pe email. Dacă ai cont, le găsești și în zona „Biletele mele".'],
                ['q' => 'Pot cumpăra pentru altcineva?',                     'a' => 'Da. Poți cumpăra bilete pentru alt beneficiar, poți pune nume diferite pe bilete și poți folosi carduri cadou pentru experiențe.'],
                ['q' => 'Ce se întâmplă dacă plata rămâne în așteptare?',    'a' => 'Dacă plata este în așteptare, comanda nu este pierdută. Biletele se emit automat după confirmarea procesatorului. Dacă plata eșuează, poți relua checkout-ul.'],
                ['q' => 'Cum funcționează punctele bonus?',                  'a' => 'La comenzile eligibile primești puncte bonus. Ele apar în cont și pot fi folosite ca reducere în comenzile viitoare, conform regulamentului programului.'],
            ];
            foreach ($faqs as $i => $f):
            ?>
                <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                    <button type="button" @click="open = open === <?= $i ?> ? null : <?= $i ?>" :aria-expanded="open === <?= $i ?>" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                        <span class="font-display text-2xl sm:text-3xl font-700"><?= htmlspecialchars($f['q']) ?></span>
                        <span class="text-3xl font-700" x-text="open === <?= $i ?> ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === <?= $i ?>" x-collapse x-cloak class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed"><?= htmlspecialchars($f['a']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15 bg-dotgrid-cta" aria-hidden="true"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">READY?</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Găsește ceva de făcut.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Alege orașul, categoria sau contextul potrivit și cumpără biletele online în câteva minute.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/categorii" class="rounded-full bg-paper text-ink px-6 py-4 font-700 text-center hover:bg-ink hover:text-paper transition">Explorează categorii</a>
                <a href="/orase" class="rounded-full border-2 border-paper/60 px-6 py-4 font-700 text-center hover:bg-paper hover:text-ink transition">Alege orașul</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
