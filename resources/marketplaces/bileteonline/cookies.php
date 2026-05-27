<?php
/**
 * bilete.online — /cookies
 *
 * Cookie policy + entry point for the preferences modal. The actual
 * banner + modal lives globally in includes/cookie-consent.php (auto-
 * included from footer). This page exists so the policy is crawlable
 * + linkable from the banner / footer.
 *
 * The "Schimbă preferințele" button just resets the saved consent so
 * the global Alpine component shows the banner again on next paint —
 * works without any extra wiring.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Politica de cookies — ' . SITE_NAME;
$pageDescription = 'Cum folosește bilete.online cookies pentru funcționarea platformei, analytics, personalizare și marketing. Cum îți gestionezi preferințele.';
$canonicalUrl    = SITE_URL . '/cookies';
$currentPage     = 'cookies';
$cssBundle       = 'static';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main>

<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.18),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.18),transparent_34%)]"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-12 sm:pb-16">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Cookies</span>
        </nav>
        <p class="mt-8 stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">POLITICĂ COOKIES</p>
        <h1 class="mt-6 font-display text-5xl sm:text-7xl font-bold leading-[.85]">Cum folosim cookies pe bilete.online</h1>
        <p class="mt-6 max-w-3xl text-lg sm:text-xl text-ink-soft leading-relaxed">
            Folosim cookies pentru funcționarea platformei (coș, checkout, login) și, doar cu acordul tău, pentru analytics, personalizare și marketing.
            Poți schimba alegerea oricând din banner sau din butonul de mai jos.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <button onclick="(function(){ try{ localStorage.removeItem('bo_cookie_consent_v1'); }catch(e){} location.reload(); })()" class="rounded-full bg-vermilion text-paper px-6 py-3.5 font-bold hover:bg-vermilion-d transition">Schimbă preferințele</button>
            <a href="/confidentialitate" class="rounded-full border-2 border-ink px-6 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Politica de confidențialitate</a>
        </div>
    </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
    <div class="grid lg:grid-cols-4 gap-5">
        <article class="rounded-3xl border-2 border-ink bg-paper p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">NECESARE</p>
            <h2 class="mt-3 font-display text-3xl font-bold">Esențiale</h2>
            <p class="mt-2 text-ink-soft">Coș, checkout, login, sesiune, securitate, memorarea consimțământului și măsurare de audiență first-party strict agregată. Mereu active.</p>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">MĂSURARE</p>
            <h2 class="mt-3 font-display text-3xl font-bold">Analytics</h2>
            <p class="mt-2 text-ink-soft">Instrumente terțe (Google Analytics), conversii și erori. Pornite doar cu acordul tău.</p>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-mint p-6">
            <p class="font-mono text-xs tracking-[.18em] text-forest">RECOMANDĂRI</p>
            <h2 class="mt-3 font-display text-3xl font-bold">Personalizare</h2>
            <p class="mt-2 text-ink-soft">Recomandări după oraș/categorii vizitate, filtre preferate. Local, fără reclame externe.</p>
        </article>
        <article class="rounded-3xl border-2 border-ink bg-ink text-paper p-6">
            <p class="font-mono text-xs tracking-[.18em] text-ochre">CAMPANII</p>
            <h2 class="mt-3 font-display text-3xl font-bold">Marketing</h2>
            <p class="mt-2 text-paper/60">Pixeli Meta / Google / TikTok pentru campanii și remarketing. Doar după accept explicit.</p>
        </article>
    </div>

    <div class="mt-12 prose-venue max-w-4xl text-lg leading-relaxed text-ink-soft">
        <h2 class="font-display text-4xl font-bold text-ink">Ce sunt cookies?</h2>
        <p>Cookies sunt fișiere mici stocate de browser pentru a-ți face experiența online predictibilă: coșul tău rămâne plin, sesiunea de login persistă, preferințele se țin minte.</p>

        <h2 class="font-display text-4xl font-bold text-ink mt-10">Categoriile noastre de cookies</h2>
        <p><strong>Esențiale</strong> — necesare pentru funcționarea platformei (coș, checkout, login, securitate) plus măsurarea de audiență first-party, strict agregată: fără partajare cu terți, fără urmărire cross-site, cu IP anonimizat și retenție limitată. Conform ghidului CNIL privind audience measurement, această măsurare poate fi exceptată de la consimțământ, așa că rămâne mereu activă. Aceste cookies nu pot fi dezactivate.</p>
        <p><strong>Analytics</strong> — instrumente terțe (de ex. Google Analytics), conversii și rapoarte avansate. Se activează doar cu acordul tău.</p>
        <p><strong>Personalizare</strong> — folosim preferințele tale (orașul, categoriile vizitate) pentru recomandări mai relevante. Opțional.</p>
        <p><strong>Marketing</strong> — pixeli pentru campanii Meta, Google Ads, TikTok și audiențe personalizate. Doar cu accept explicit.</p>

        <h2 class="font-display text-4xl font-bold text-ink mt-10">Cum îți gestionezi alegerea</h2>
        <p>La prima vizită apare un banner unde poți alege: <strong>Acceptă toate</strong>, <strong>Refuză opționale</strong> sau <strong>Personalizează</strong>.</p>
        <p>Poți schimba oricând setările din butonul plutitor 🍪 (stânga-jos pe orice pagină) sau din butonul „Schimbă preferințele" din partea de sus a acestei pagini.</p>

        <h2 class="font-display text-4xl font-bold text-ink mt-10">Drepturile tale</h2>
        <p>Conform GDPR, ai dreptul să fii informat despre prelucrarea datelor tale, să ai acces la ele, să le rectifici, să le ștergi sau să te opui prelucrării. Pentru orice solicitare GDPR, scrie-ne pe <a href="/contact">pagina de contact</a> sau consultă <a href="/confidentialitate">Politica de confidențialitate</a>.</p>
    </div>

    <div class="mt-12 rounded-3xl border-2 border-ink bg-vermilion text-paper p-8 flex flex-wrap items-center justify-between gap-5">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-paper/65">SCHIMBĂ ORICÂND</p>
            <h2 class="mt-2 font-display text-4xl font-bold">Vrei să-ți modifici preferințele?</h2>
        </div>
        <button onclick="(function(){ try{ localStorage.removeItem('bo_cookie_consent_v1'); }catch(e){} location.reload(); })()" class="rounded-full bg-paper text-ink px-6 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Deschide bannerul</button>
    </div>
</section>

</main>

<style>
    .prose-venue h2 { margin-top: 1.75rem; margin-bottom: .8rem; }
    .prose-venue p { margin-bottom: 1rem; }
    .prose-venue a { color: #E84527; font-weight: 700; background-image: linear-gradient(currentColor,currentColor); background-size: 100% 2px; background-repeat: no-repeat; background-position: 0 100%; }
    .prose-venue a:hover { background-size: 0 2px; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
