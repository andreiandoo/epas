<?php
/**
 * /pentru-locatii — Landing page for venues / activity operators.
 * Static content + Alpine.js interactivity (feature tabs, FAQ accordion).
 */
require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Pentru locații — vinde bilete online pentru activități pe bilete.online';
$pageDescription = 'Listează-ți locația pe bilete.online și vinde bilete online pentru escape rooms, muzee, parcuri, ateliere, peșteri, rezervații și experiențe locale. Pagini SEO, checkout, QR, scanare, rapoarte și dashboard.';
$pageKeywords    = 'vânzare bilete activități, platformă bilete locații, bilete online escape room, bilete online muzeu, bilete QR activități, sistem ticketing locații, platformă rezervări activități';
$canonicalUrl   = SITE_URL . '/pentru-locatii';
$currentPage    = 'pentru-locatii';
$cssBundle      = 'static';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Pentru locații', 'url' => $canonicalUrl],
];

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'bilete.online pentru locații',
        'serviceType' => 'Platformă de vânzare bilete online pentru activități și locații',
        'provider' => ['@type' => 'Organization', 'name' => 'bilete.online', 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Country', 'name' => 'România'],
        'description' => 'Platformă pentru locații care vând bilete online la activități: pagini SEO, checkout, bilete QR, scanare, dashboard, rapoarte, carduri cadou și puncte bonus.',
        'audience' => [
            '@type' => 'BusinessAudience',
            'audienceType' => 'Locații de agrement, muzee, escape rooms, parcuri, ateliere, operatori de tururi și experiențe',
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => 'Ce tipuri de locații pot folosi bilete.online?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Platforma este potrivită pentru escape rooms, muzee, parcuri de distracții, parcuri de aventură, peșteri, rezervații naturale, ateliere, tururi ghidate, ferme educative și alte activități cu bilete sau rezervări.']],
            ['@type' => 'Question', 'name' => 'Pot vinde mai multe activități din aceeași locație?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da. O locație poate avea o pagină principală și mai multe pagini pentru activități, tururi, pachete, intervale orare sau tipuri de bilete.']],
            ['@type' => 'Question', 'name' => 'Cum se validează biletele la intrare?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Biletele sunt emise cu cod QR și pot fi scanate la intrare din interfața de check-in pentru organizatori sau din aplicația de scanare configurată.']],
            ['@type' => 'Question', 'name' => 'Primesc pagini optimizate SEO?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da. Pagina locației și paginile activităților sunt construite pentru căutări locale, categorii, orașe și intenții precum activități pentru copii, weekend, indoor sau outdoor.']],
            ['@type' => 'Question', 'name' => 'Pot folosi carduri cadou sau puncte bonus?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da, în funcție de configurare. Platforma poate permite carduri cadou, vouchere, puncte bonus, coduri promoționale și campanii.']],
        ],
    ],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<div x-data="venuesPage()">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_12%,rgba(232,69,39,.25),transparent_28%),radial-gradient(circle_at_18%_70%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_54%_42%,rgba(218,154,51,.18),transparent_28%)]" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span aria-hidden="true">/</span>
            <span class="text-ink">Pentru locații</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_.94fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">PLATFORMĂ PENTRU LOCAȚII · SEO · CHECKOUT · QR</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl lg:text-[6.5rem] font-700 leading-[.82]">
                    Transformă activitățile tale în bilete care se vând online.
                </h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    bilete.online ajută locațiile să fie descoperite organic, să vândă bilete rapid și să gestioneze accesul cu QR — fără să construiască de la zero o platformă de ticketing.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#demo" class="rounded-full bg-vermilion text-paper px-6 py-4 font-700 text-lg hover:bg-vermilion-d transition">Solicită demo</a>
                    <a href="#ce-primesti" class="rounded-full border-2 border-ink px-6 py-4 font-700 text-lg hover:bg-ink hover:text-paper transition">Vezi ce primești</a>
                </div>

                <dl class="mt-10 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PAGINI</dt><dd class="font-display text-3xl font-700">SEO</dd></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">ACCES</dt><dd class="font-display text-3xl font-700">QR</dd></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">DATE</dt><dd class="font-display text-3xl font-700">live</dd></div>
                </dl>
            </div>

            <!-- Dashboard mockup + floating cards -->
            <div class="relative min-h-[640px]" aria-hidden="true">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[-2deg] shadow-deep"></div>

                <div class="absolute top-0 right-0 left-0 mx-auto max-w-[540px] rounded-[2rem] border-2 border-ink bg-paper shadow-deep overflow-hidden rotate-[2deg]">
                    <div class="p-5 bg-ink text-paper flex items-center justify-between">
                        <div>
                            <p class="font-mono text-[10px] tracking-[.18em] text-paper/45">ORGANIZER DASHBOARD</p>
                            <h2 class="font-display text-3xl font-700">Mystery Rooms Brașov</h2>
                        </div>
                        <span class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-700">LIVE</span>
                    </div>

                    <div class="p-5 grid grid-cols-3 gap-3 border-b-2 border-dashed border-ink/15">
                        <div class="rounded-2xl bg-paper-2 p-3">
                            <p class="font-mono text-[9px] tracking-[.14em] text-ink-soft">VÂNZĂRI</p>
                            <p class="font-display text-3xl font-700">18.4k</p>
                        </div>
                        <div class="rounded-2xl bg-mint p-3">
                            <p class="font-mono text-[9px] tracking-[.14em] text-ink-soft">BILETE</p>
                            <p class="font-display text-3xl font-700">214</p>
                        </div>
                        <div class="rounded-2xl bg-paper-2 p-3">
                            <p class="font-mono text-[9px] tracking-[.14em] text-ink-soft">SCANATE</p>
                            <p class="font-display text-3xl font-700">38</p>
                        </div>
                    </div>

                    <div class="p-5 space-y-3">
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                            <div class="flex justify-between gap-4"><span class="font-700">Camera 13</span><span>9.200 lei</span></div>
                            <div class="mt-3 h-2 bg-paper rounded-full overflow-hidden"><div class="pulsebar h-full bg-vermilion rounded-full"></div></div>
                        </div>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                            <div class="flex justify-between gap-4"><span class="font-700">Laboratorul 7</span><span>6.140 lei</span></div>
                            <div class="mt-3 h-2 bg-paper rounded-full overflow-hidden"><div class="h-full bg-forest rounded-full w-[54%]"></div></div>
                        </div>
                    </div>
                </div>

                <div class="floaty absolute left-0 bottom-24 w-60 rounded-3xl border-2 border-ink bg-forest text-paper p-5 shadow-deep rotate-[-5deg]">
                    <p class="font-mono text-[10px] tracking-[.18em] text-paper/50">CHECK-IN</p>
                    <div class="relative mt-4 mx-auto w-32 h-32 rounded-2xl border-2 border-paper/40 bg-paper text-ink grid place-items-center overflow-hidden">
                        <span class="font-mono text-xs text-center">QR<br>VALID</span>
                        <span class="scanline absolute left-3 right-3 top-0 h-0.5 bg-vermilion shadow-[0_0_12px_rgba(232,69,39,.85)]"></span>
                    </div>
                </div>

                <div class="floaty absolute right-0 bottom-2 w-68 max-w-[270px] rounded-3xl border-2 border-ink bg-paper p-5 shadow-deep rotate-[5deg]" style="animation-delay:-2.4s">
                    <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">SEO LOCAL</p>
                    <p class="mt-2 font-display text-2xl font-700">/brasov/escape-rooms</p>
                    <p class="mt-2 text-sm text-ink-soft">Pagini pentru oraș, categorie, locație și activități.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- WHO -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="grid lg:grid-cols-[.76fr_1.24fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PENTRU CINE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Nu vinzi doar bilete. Vinzi o experiență care trebuie descoperită.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Platforma este construită pentru activități diferite, cu modele diferite de acces: sloturi orare, bilete simple, pachete, tururi ghidate, acces pe zi, grupuri sau evenimente private.</p>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Escape rooms</h3><p class="mt-2 text-ink-soft">Sloturi orare, capacitate per cameră, beneficiari diferiți, bilete de grup și check-in rapid.</p></article>
            <article class="soft-card p-6 bg-mint"><h3 class="font-display text-3xl font-700">Muzee & expoziții</h3><p class="mt-2 text-ink-soft">Bilete de acces, expoziții temporare, tururi ghidate, copii/adulți, gratuități și program.</p></article>
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Parcuri & agrement</h3><p class="mt-2 text-ink-soft">Pachete, categorii de vârstă, acces pe zi, extra-opțiuni și capacitate.</p></article>
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Peșteri & rezervații</h3><p class="mt-2 text-ink-soft">Tururi, reguli de acces, nivel de dificultate, echipament, ghid și sezonalitate.</p></article>
            <article class="soft-card p-6 bg-ink text-paper"><h3 class="font-display text-3xl font-700">Ateliere & educație</h3><p class="mt-2 text-paper/60">Locuri limitate, vârste recomandate, materiale incluse, grupuri școlare.</p></article>
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Tururi & experiențe</h3><p class="mt-2 text-ink-soft">City walks, tururi gastronomice, tururi istorice, experiențe turistice și private.</p></article>
        </div>
    </div>
</section>

<!-- WHAT YOU GET -->
<section id="ce-primesti" class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-4xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CE PRIMEȘTI</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Un stack complet pentru vânzarea activităților tale.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">bilete.online combină pagini publice optimizate, flux de cumpărare, emitere bilete, operațiuni la intrare și instrumente de creștere.</p>
        </div>

        <div class="mt-10 grid lg:grid-cols-3 gap-5">
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">01 · SEO</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">Pagini care pot atrage trafic organic.</h3>
                <p class="mt-4 text-ink-soft leading-relaxed">Pagină de locație, pagini pentru activități, categorii, orașe și intenții precum „activități copii”, „weekend”, „indoor”, „sub 50 lei”.</p>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-ochre">02 · CHECKOUT</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">Cumpărare rapidă, clară, modernă.</h3>
                <p class="mt-4 text-paper/60 leading-relaxed">Card, Apple Pay, Google Pay, Revolut Pay, RoPay, beneficiari diferiți, cont automat, taxe afișate separat și opțiuni comerciale.</p>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">03 · QR</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">Bilete digitale și check-in rapid.</h3>
                <p class="mt-4 text-ink-soft leading-relaxed">Fiecare bilet are cod unic, status, beneficiar și poate fi scanat la intrare pentru control clar al accesului.</p>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">04 · DASHBOARD</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">Comenzi, clienți, scanări și rapoarte.</h3>
                <p class="mt-4 text-ink-soft leading-relaxed">Vezi vânzările, biletele emise, participanții, disponibilitatea, statusurile și performanța activităților.</p>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">05 · GROWTH</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">Promoții, vouchere, carduri cadou.</h3>
                <p class="mt-4 text-ink-soft leading-relaxed">Poți rula coduri promo, campanii sezoniere, carduri cadou, puncte bonus și oferte pentru audiențe specifice.</p>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-mint p-6">
                <p class="font-mono text-xs tracking-[.18em] text-forest">06 · TRUST</p>
                <h3 class="mt-4 font-display text-4xl font-700 leading-none">O experiență mai bună pentru clienți.</h3>
                <p class="mt-4 text-ink-soft leading-relaxed">Clientul vede clar ce cumpără, unde merge, cum intră, ce include biletul și ce se întâmplă după plată.</p>
            </article>
        </div>
    </div>
</section>

<!-- SEO ENGINE -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.95fr_1.05fr] gap-10 items-center">
        <div>
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">SEO ENGINE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Nu depinzi doar de reclame.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Fiecare activitate poate deveni o pagină de vânzare optimizată. Locația ta poate apărea în pagini de oraș, categorie și intenție — nu doar într-o listă generică.</p>
            <div class="mt-7 grid sm:grid-cols-2 gap-3">
                <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4"><p class="font-700">/brasov/escape-rooms</p><p class="text-sm text-ink-soft">oraș + categorie</p></div>
                <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4"><p class="font-700">/brasov/activitati-copii</p><p class="text-sm text-ink-soft">oraș + intenție</p></div>
                <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4"><p class="font-700">/locatii/mystery-rooms</p><p class="text-sm text-ink-soft">pagină locație</p></div>
                <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4"><p class="font-700">/activitati/camera-13</p><p class="text-sm text-ink-soft">pagină activitate</p></div>
            </div>
        </div>

        <div class="ticket bg-ink text-paper rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
            <div class="p-6 border-b-2 border-dashed border-paper/15">
                <p class="font-mono text-xs tracking-[.18em] text-paper/45">ANATOMIA UNEI PAGINI SEO</p>
                <h3 class="mt-2 font-display text-4xl font-700">Activitatea ta devine găsibilă.</h3>
            </div>
            <div class="p-6 space-y-3">
                <div class="rounded-2xl bg-paper/10 p-4"><p class="font-700 text-ochre">Titlu + descriere clare</p><p class="text-sm text-paper/55">Ce este, unde este, pentru cine este.</p></div>
                <div class="rounded-2xl bg-paper/10 p-4"><p class="font-700 text-ochre">Date structurate</p><p class="text-sm text-paper/55">Breadcrumbs, FAQ, local entity, activitate.</p></div>
                <div class="rounded-2xl bg-paper/10 p-4"><p class="font-700 text-ochre">Întrebări practice</p><p class="text-sm text-paper/55">Program, acces, vârstă, durată, reguli, parcare.</p></div>
                <div class="rounded-2xl bg-paper/10 p-4"><p class="font-700 text-ochre">Internal linking</p><p class="text-sm text-paper/55">Orașe, categorii, activități similare, ghiduri.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- OPS FLOW -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">OPERAȚIONAL</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">De la listare la check-in.</h2>
            <p class="mt-5 text-lg text-paper/60 leading-relaxed">Fluxul este construit pentru echipe mici: publici activitatea, vinzi bilete, scanezi la intrare și urmărești rezultatele.</p>
        </div>

        <div class="mt-10 grid md:grid-cols-5 gap-4">
            <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><p class="font-display text-5xl font-700 text-ochre">1</p><h3 class="mt-3 font-display text-2xl font-700">Onboarding</h3><p class="mt-2 text-paper/55">Date locație, activități, bilete, politici.</p></article>
            <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><p class="font-display text-5xl font-700 text-ochre">2</p><h3 class="mt-3 font-display text-2xl font-700">Publicare</h3><p class="mt-2 text-paper/55">Pagini SEO și activități disponibile online.</p></article>
            <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><p class="font-display text-5xl font-700 text-ochre">3</p><h3 class="mt-3 font-display text-2xl font-700">Vânzare</h3><p class="mt-2 text-paper/55">Checkout, plăți, comisioane, bilete QR.</p></article>
            <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><p class="font-display text-5xl font-700 text-ochre">4</p><h3 class="mt-3 font-display text-2xl font-700">Scanare</h3><p class="mt-2 text-paper/55">Validare rapidă la intrare, statusuri clare.</p></article>
            <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><p class="font-display text-5xl font-700 text-ochre">5</p><h3 class="mt-3 font-display text-2xl font-700">Creștere</h3><p class="mt-2 text-paper/55">Rapoarte, recenzii, promoții, campanii.</p></article>
        </div>
    </div>
</section>

<!-- FEATURE TABS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.8fr_1.2fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">MODULE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Alegi ce ai nevoie. Platforma poate crește cu tine.</h2>
        </div>

        <div x-data="{tab:'tickets'}">
            <div class="flex flex-wrap gap-2">
                <button @click="tab='tickets'" :class="tab==='tickets'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full border-2 border-ink/10 px-4 py-2 font-700">Bilete</button>
                <button @click="tab='calendar'" :class="tab==='calendar'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full border-2 border-ink/10 px-4 py-2 font-700">Disponibilitate</button>
                <button @click="tab='growth'" :class="tab==='growth'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full border-2 border-ink/10 px-4 py-2 font-700">Growth</button>
                <button @click="tab='reports'" :class="tab==='reports'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full border-2 border-ink/10 px-4 py-2 font-700">Rapoarte</button>
            </div>

            <div class="mt-5 rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                <div x-show="tab==='tickets'">
                    <h3 class="font-display text-4xl font-700">Tipuri de bilete și pachete</h3>
                    <p class="mt-3 text-ink-soft leading-relaxed">Creezi bilete simple, bilete copil/adult, pachete de grup, bilete cu interval orar, extra-opțiuni sau bilete pentru tururi.</p>
                    <div class="mt-6 grid sm:grid-cols-3 gap-3"><div class="rounded-2xl bg-paper-2 p-4">Adult · 95 lei</div><div class="rounded-2xl bg-paper-2 p-4">Copil · 45 lei</div><div class="rounded-2xl bg-paper-2 p-4">Grup · 340 lei</div></div>
                </div>
                <div x-show="tab==='calendar'" x-cloak>
                    <h3 class="font-display text-4xl font-700">Disponibilitate și sloturi</h3>
                    <p class="mt-3 text-ink-soft leading-relaxed">Controlezi zile, ore, capacitate, închideri, excepții, sezonalitate și intervale cu disponibilitate limitată.</p>
                    <div class="mt-6 grid grid-cols-7 gap-2 text-center text-sm font-700">
                        <template x-for="d in 14"><span class="rounded-2xl bg-paper-2 p-3" :class="d%4===0?'bg-vermilion text-paper':''" x-text="d"></span></template>
                    </div>
                </div>
                <div x-show="tab==='growth'" x-cloak>
                    <h3 class="font-display text-4xl font-700">Promoții, carduri cadou, puncte</h3>
                    <p class="mt-3 text-ink-soft leading-relaxed">Rulezi coduri promo, campanii sezoniere, beneficii prin puncte bonus și eligibilitate pentru carduri cadou sau vouchere.</p>
                    <div class="mt-6 flex flex-wrap gap-2 text-sm font-700"><span class="rounded-full bg-vermilion text-paper px-4 py-2">WEEKEND10</span><span class="rounded-full bg-mint text-forest px-4 py-2">Puncte duble</span><span class="rounded-full bg-paper-2 px-4 py-2">Card cadou</span></div>
                </div>
                <div x-show="tab==='reports'" x-cloak>
                    <h3 class="font-display text-4xl font-700">Rapoarte și date utile</h3>
                    <p class="mt-3 text-ink-soft leading-relaxed">Vezi ce se vinde, când, pentru cine, care activități performează și ce intervale au conversie mai bună.</p>
                    <div class="mt-6 grid sm:grid-cols-3 gap-3"><div class="rounded-2xl bg-paper-2 p-4"><p class="text-sm text-ink-soft">Vânzări</p><p class="font-display text-3xl font-700">18.4k</p></div><div class="rounded-2xl bg-paper-2 p-4"><p class="text-sm text-ink-soft">Comenzi</p><p class="font-display text-3xl font-700">96</p></div><div class="rounded-2xl bg-paper-2 p-4"><p class="text-sm text-ink-soft">Conversie</p><p class="font-display text-3xl font-700">4.2%</p></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PRICING TEASER -->
<section class="border-y-2 border-ink bg-paper-2/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div class="grid lg:grid-cols-[1fr_1fr] gap-8 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">MODEL COMERCIAL</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Costuri clare, fără infrastructură construită de la zero.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Modelul poate include comision per bilet, servicii opționale sau pachete de promovare. Ideea este simplă: plătești pentru infrastructură care vinde, nu pentru promisiuni vagi.</p>
                <a href="/pricing-locatii" class="mt-7 inline-flex rounded-full bg-ink text-paper px-6 py-4 font-700 hover:bg-vermilion transition">Vezi pricing locații</a>
            </div>
            <div class="grid sm:grid-cols-3 gap-4">
                <article class="rounded-3xl border-2 border-ink bg-paper p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">START</p><h3 class="mt-3 font-display text-3xl font-700">Listare</h3><p class="mt-2 text-ink-soft">Pagini, checkout, bilete QR.</p></article>
                <article class="rounded-3xl border-2 border-vermilion bg-ink text-paper p-5"><p class="font-mono text-xs tracking-[.18em] text-ochre">GROWTH</p><h3 class="mt-3 font-display text-3xl font-700">Promovare</h3><p class="mt-2 text-paper/60">SEO, campanii, vizibilitate.</p></article>
                <article class="rounded-3xl border-2 border-ink bg-paper p-5"><p class="font-mono text-xs tracking-[.18em] text-vermilion">PRO</p><h3 class="mt-3 font-display text-3xl font-700">Operațional</h3><p class="mt-2 text-ink-soft">Rapoarte, staff, integrări.</p></article>
            </div>
        </div>
    </div>
</section>

<!-- DEMO FORM -->
<section id="demo" class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">SOLICITĂ DEMO</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Hai să vedem cum ar arăta locația ta pe bilete.online.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Trimite câteva detalii despre locație și activități. Răspunsul ideal îți arată ce pagini ar trebui create, ce tipuri de bilete se potrivesc și ce oportunități SEO ai.</p>
            <div class="mt-7 rounded-3xl bg-mint border border-forest/20 p-5">
                <p class="font-700 text-forest">Ce poți pregăti înainte:</p>
                <ul class="mt-2 text-sm text-ink-soft space-y-1">
                    <li>• numele locației și orașul;</li>
                    <li>• tipurile de activități;</li>
                    <li>• prețuri și capacitate;</li>
                    <li>• program și reguli de acces.</li>
                </ul>
            </div>
        </div>

        <form class="rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket" action="/api/contact-locatii.php" method="post">
            <div class="grid sm:grid-cols-2 gap-4">
                <label><span class="block mb-1.5 text-sm font-700">Nume contact</span><input name="contact_name" class="field" placeholder="Nume și prenume" required></label>
                <label><span class="block mb-1.5 text-sm font-700">Email</span><input name="email" class="field" type="email" placeholder="email@locatie.ro" required></label>
                <label><span class="block mb-1.5 text-sm font-700">Telefon</span><input name="phone" class="field" type="tel" placeholder="+40..."></label>
                <label><span class="block mb-1.5 text-sm font-700">Rol</span><select name="role" class="field"><option>Owner / Administrator</option><option>Marketing</option><option>Operațional</option><option>Alt rol</option></select></label>
                <label><span class="block mb-1.5 text-sm font-700">Nume locație</span><input name="venue_name" class="field" placeholder="ex. Mystery Rooms Brașov" required></label>
                <label><span class="block mb-1.5 text-sm font-700">Oraș</span><input name="city" class="field" placeholder="ex. Brașov"></label>
                <label><span class="block mb-1.5 text-sm font-700">Tip locație</span><select name="venue_type" class="field" x-model="venueType"><option>Escape room</option><option>Muzeu / expoziție</option><option>Parc de distracții</option><option>Parc de aventură</option><option>Peșteră / rezervație</option><option>Atelier / educație</option><option>Tururi / experiențe</option><option>Altceva</option></select></label>
                <label><span class="block mb-1.5 text-sm font-700">Câte activități vinzi?</span><select name="activities_count" class="field"><option>1 activitate</option><option>2-5 activități</option><option>6-15 activități</option><option>15+ activități</option></select></label>
                <label class="sm:col-span-2"><span class="block mb-1.5 text-sm font-700">Ce vrei să vinzi online?</span><textarea name="message" class="field min-h-36" placeholder="Descrie activitățile, tipurile de bilete, programul și ce probleme ai acum cu vânzarea sau rezervările."></textarea></label>
                <label class="sm:col-span-2 flex items-start gap-3"><input type="checkbox" name="consent" required class="mt-1 w-5 h-5 accent-vermilion"><span>Accept să fiu contactat pentru o discuție despre listarea locației pe bilete.online.</span></label>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="mt-6 rounded-full bg-vermilion text-paper px-6 py-4 font-700 hover:bg-vermilion-d transition">Trimite solicitarea</button>
        </form>
    </div>
</section>

<!-- FAQ -->
<section class="border-t-2 border-ink bg-paper-2/60" x-data="{open:0}">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="text-center max-w-3xl mx-auto">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Întrebări frecvente</h2>
        </div>

        <div class="mt-10 space-y-3">
            <template x-for="(faq,index) in faqs" :key="faq.q">
                <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                    <button @click="open=open===index?null:index" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                        <span class="font-display text-2xl sm:text-3xl font-700" x-text="faq.q"></span>
                        <span class="text-3xl font-700" x-text="open===index?'−':'+'"></span>
                    </button>
                    <div x-show="open===index" x-collapse class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed" x-text="faq.a"></div>
                </article>
            </template>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15 bg-dotgrid-cta" aria-hidden="true"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">READY TO LIST?</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Locația ta poate deveni următoarea activitate descoperită online.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Dacă ai o activitate pe care oamenii ar trebui să o descopere, bilete.online poate fi infrastructura care o vinde.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="#demo" class="rounded-full bg-paper text-ink px-6 py-4 font-700 text-center hover:bg-ink hover:text-paper transition">Solicită demo</a>
                <a href="/pricing-locatii" class="rounded-full border-2 border-paper/60 px-6 py-4 font-700 text-center hover:bg-paper hover:text-ink transition">Vezi pricing</a>
            </div>
        </div>
    </div>
</section>

</div>

<script>
function venuesPage() {
    return {
        venueType: 'Escape room',
        faqs: [
            { q: 'Ce tipuri de locații pot folosi platforma?', a: 'Platforma este potrivită pentru escape rooms, muzee, expoziții, parcuri de distracții, parcuri de aventură, peșteri, rezervații naturale, ateliere, tururi ghidate, ferme educative și alte experiențe care vând bilete sau rezervări.' },
            { q: 'Pot avea mai multe activități în aceeași locație?', a: 'Da. O locație poate avea o pagină principală și mai multe pagini pentru activități, camere, tururi, pachete sau tipuri de acces.' },
            { q: 'Cum se validează biletele?', a: 'Fiecare bilet este emis cu un cod QR unic. La intrare, personalul locației îl scanează din interfața de check-in, iar sistemul afișează statusul biletului.' },
            { q: 'Mă ajută cu SEO?', a: 'Da. Platforma este gândită pentru pagini indexabile: locație, activități, orașe, categorii și pagini de intenție precum activități pentru copii, weekend, indoor sau outdoor.' },
            { q: 'Pot crea promoții sau coduri de reducere?', a: 'Da, platforma poate include coduri promoționale, campanii sezoniere, vouchere, puncte bonus și carduri cadou, în funcție de configurare.' },
            { q: 'Ce se întâmplă după ce primesc o comandă?', a: 'Comanda apare în dashboard, biletele sunt emise automat, clientul primește confirmarea, iar tu poți vedea participanții și valida biletele la intrare.' }
        ]
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
