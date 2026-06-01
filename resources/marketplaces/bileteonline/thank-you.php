<?php
/**
 * bilete.online — /multumim (alias /thank-you)
 *
 * Confirmation page after successful payment. PHP scaffolds the DOM
 * containers; thank-you.js fills them from the order API. The DOM contract
 * (element IDs and the global ThankYouPage.* methods) MUST match the ones
 * thank-you.js queries — they are the same IDs ambilet uses (confetti,
 * printingText, ticketsCarousel, ticketsCount, ticketsScroll, scrollIndicators,
 * buyerEmail, orderDetails, eventInfo, ticketsSummary, paymentSummary,
 * cardNumber, pointsEarned, earnedPoints, newPoints, downloadBtn, calendarBtn,
 * shareFb, shareWa).
 *
 * Visually re-skinned to the bilete.online "ticket / paper-grain" style:
 * Fraunces display headings, Hanken Grotesk body, paper / ink palette,
 * vermilion accents, ticket-style dark blocks.
 */

require_once __DIR__ . '/includes/config.php';

$orderRef = $_GET['order'] ?? '';

$pageTitleRaw    = 'Comandă confirmată — ' . SITE_NAME;
$pageDescription = 'Comanda ta a fost confirmată. Descarcă biletele cu QR și verifică detaliile comenzii.';
$canonicalUrl    = SITE_URL . '/multumim';
$noindex         = true;
$currentPage     = 'thank-you';
$cssBundle       = 'checkout';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Confetti container -->
<div class="confetti-container" id="confetti"></div>

<!-- Progress Steps - All Complete -->
<section class="border-b-2 border-ink/10 bg-paper-2/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-center gap-3 font-mono text-xs tracking-wider">
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-forest bg-forest text-paper">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="text-forest font-semibold">Coș</span>
            <span class="w-12 h-px bg-forest"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-forest bg-forest text-paper">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="text-forest font-semibold">Checkout</span>
            <span class="w-12 h-px bg-forest"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-forest bg-forest text-paper">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="text-forest font-semibold">Confirmare</span>
        </div>
        <?php if ($orderRef): ?>
            <p class="mt-2 text-sm text-center text-ink-soft font-mono">Comandă #<?= htmlspecialchars($orderRef) ?></p>
        <?php endif; ?>
    </div>
</section>

<main class="relative overflow-hidden">

    <!-- Hero -->
    <section class="relative border-b-2 border-ink/10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(232,69,39,.18),transparent_32%),radial-gradient(circle_at_20%_70%,rgba(30,74,61,.18),transparent_34%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-14 sm:py-16 text-center">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-forest bg-paper/70">PASUL 3 · COMANDĂ CONFIRMATĂ</p>
            <h1 class="mt-5 font-display text-4xl sm:text-6xl font-bold leading-[.95]">Biletele tale sunt gata.</h1>
            <p id="printingText" class="mt-4 max-w-2xl mx-auto text-lg text-ink-soft">Plata a fost procesată. Biletele se printează acum...</p>
        </div>
    </section>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">

        <!-- Printer Animation -->
        <div class="printer-section">
            <div class="printer-container">
                <div class="printer">
                    <div class="printing-ticket print-ticket-1">
                        <div class="ticket-mini">
                            <div class="ticket-mini-header">
                                <p class="text-[10px] opacity-70 font-mono tracking-wider"><?= SITE_NAME ?></p>
                                <p class="text-xs font-bold">Bilet</p>
                            </div>
                            <div class="ticket-mini-body">
                                <p class="text-[10px] text-ink-soft font-mono">Activitate</p>
                                <p class="text-xs font-bold">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="printer-body">
                        <div class="printer-slot"></div>
                        <div class="printer-light"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Carousel -->
        <div class="mt-6 tickets-carousel" id="ticketsCarousel">
            <div class="mb-4 text-center">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE EMISE</p>
                <h2 class="mt-1 font-display text-3xl font-bold">Biletele tale sunt gata!</h2>
                <p id="ticketsCount" class="mt-1 text-sm text-ink-soft">Se încarcă...</p>
            </div>

            <div class="swipe-hint">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
                Glisează pentru a vedea toate biletele
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </div>

            <div class="tickets-scroll" id="ticketsScroll"></div>

            <div class="scroll-indicators" id="scrollIndicators"></div>
        </div>

        <!-- Email Confirmation -->
        <div class="flex items-center gap-4 p-4 mt-8 bg-paper border-2 border-forest/30 email-animation rounded-2xl">
            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-forest/15 rounded-xl">
                <svg class="w-6 h-6 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <p class="font-bold">Biletele au fost trimise pe email</p>
                <p id="buyerEmail" class="text-sm text-ink-soft font-mono">Se încarcă...</p>
            </div>
            <svg class="flex-shrink-0 w-6 h-6 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>

        <!-- Order Details -->
        <section class="mt-8 overflow-hidden bg-paper border-2 border-ink content-section delay-1 rounded-3xl" id="orderDetails">
            <div class="p-5 sm:p-6 border-b-2 border-dashed border-ink/15">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">DETALII COMANDĂ</p>
                        <h2 class="mt-1 font-display text-3xl font-bold">Sumar</h2>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-forest text-paper text-xs font-bold font-mono tracking-wider">CONFIRMATĂ</span>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <!-- Event Info -->
                <div id="eventInfo" class="flex gap-4 mb-6">
                    <div class="w-20 h-20 bg-paper-2/60 border border-ink/10 rounded-xl animate-pulse"></div>
                    <div class="flex-1 space-y-2">
                        <div class="w-3/4 h-5 rounded bg-paper-2/60 animate-pulse"></div>
                        <div class="w-1/2 h-4 rounded bg-paper-2/60 animate-pulse"></div>
                        <div class="w-2/3 h-4 rounded bg-paper-2/60 animate-pulse"></div>
                    </div>
                </div>

                <!-- Tickets Summary -->
                <div id="ticketsSummary" class="p-4 mb-6 bg-paper-2/60 border border-ink/10 rounded-2xl">
                    <p class="mb-3 font-mono text-xs tracking-[.18em] text-ink-soft">BILETE ACHIZIȚIONATE</p>
                    <div class="h-16 rounded bg-paper animate-pulse"></div>
                </div>

                <!-- Payment Summary -->
                <div class="pt-6 border-t-2 border-dashed border-ink/15">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <p class="mb-3 font-mono text-xs tracking-[.18em] text-ink-soft">METODĂ DE PLATĂ</p>
                            <div class="flex items-center gap-3 p-3 bg-paper-2/60 border border-ink/10 rounded-xl">
                                <!-- Processor badge populated by thank-you.js from
                                     order.payment_processor — falls back to STRIPE
                                     since that's the marketplace default now.
                                     Was hardcoded NETOPIA which contradicted the
                                     "Card bancar (Stripe)" label below. -->
                                <div id="paymentProcessorBadge" class="flex items-center justify-center w-12 h-8 rounded bg-gradient-to-r from-sky to-forest">
                                    <span id="paymentProcessorBadgeText" class="text-paper text-[9px] font-bold tracking-wider">STRIPE</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold">Card bancar</p>
                                    <p id="cardNumber" class="text-xs text-ink-soft font-mono">**** **** **** ****</p>
                                </div>
                            </div>
                        </div>
                        <div id="paymentSummary">
                            <p class="mb-3 font-mono text-xs tracking-[.18em] text-ink-soft">SUMAR PLATĂ</p>
                            <div class="space-y-2 text-sm">
                                <div class="h-4 rounded bg-paper-2/60 animate-pulse"></div>
                                <div class="h-4 rounded bg-paper-2/60 animate-pulse"></div>
                                <div class="h-6 rounded bg-paper-2/60 animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Points Earned -->
                <div id="pointsEarned" class="flex items-center justify-between gap-4 p-4 mt-6 bg-ink text-paper rounded-2xl">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">🎁</span>
                        <div>
                            <p class="font-bold">Ai câștigat puncte!</p>
                            <p class="text-sm text-paper/65">Sold nou: <span id="newPoints" class="font-bold text-ochre">0</span> puncte</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p id="earnedPoints" class="font-display text-3xl font-bold text-ochre">+0</p>
                        <p class="text-xs text-paper/50 font-mono">PUNCTE</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="grid gap-4 mt-8 content-section delay-2 md:grid-cols-2">
            <a href="#" id="downloadBtn" class="flex items-center gap-3 p-4 bg-paper border-2 border-ink/15 rounded-2xl hover:border-ink transition">
                <div class="flex items-center justify-center w-12 h-12 bg-vermilion/10 rounded-xl">
                    <svg class="w-6 h-6 text-vermilion" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-bold">Printează biletele</p>
                    <p class="text-sm text-ink-soft">Printează sau salvează ca PDF</p>
                </div>
            </a>
            <a href="#" id="calendarBtn" class="flex items-center gap-3 p-4 bg-paper border-2 border-ink/15 rounded-2xl hover:border-ink transition">
                <div class="flex items-center justify-center w-12 h-12 bg-forest/10 rounded-xl">
                    <svg class="w-6 h-6 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-bold">Adaugă în calendar</p>
                    <p class="text-sm text-ink-soft">Google Calendar / iCal</p>
                </div>
            </a>
        </div>

        <!-- What's next -->
        <section class="mt-8 rounded-3xl border-2 border-forest/30 bg-mint p-5 sm:p-6 content-section delay-2">
            <p class="font-mono text-xs tracking-[.18em] text-forest">CE URMEAZĂ</p>
            <h2 class="mt-1 font-display text-3xl font-bold">Pașii următori</h2>
            <div class="mt-5 grid md:grid-cols-3 gap-4">
                <div class="rounded-2xl bg-paper border border-ink/10 p-4">
                    <p class="font-mono text-xs text-vermilion font-bold">01</p>
                    <p class="mt-1 font-bold">Verifică emailul</p>
                    <p class="mt-1 text-sm text-ink-soft">Biletele au fost trimise la adresa comenzii.</p>
                </div>
                <div class="rounded-2xl bg-paper border border-ink/10 p-4">
                    <p class="font-mono text-xs text-vermilion font-bold">02</p>
                    <p class="mt-1 font-bold">Adaugă în calendar</p>
                    <p class="mt-1 text-sm text-ink-soft">Primești reminder înainte de activitate.</p>
                </div>
                <div class="rounded-2xl bg-paper border border-ink/10 p-4">
                    <p class="font-mono text-xs text-vermilion font-bold">03</p>
                    <p class="mt-1 font-bold">Arată QR-ul</p>
                    <p class="mt-1 text-sm text-ink-soft">Nu trebuie să printezi biletul.</p>
                </div>
            </div>
        </section>

        <!-- Share -->
        <div class="mt-8 text-center content-section delay-3">
            <p class="mb-4 text-ink-soft">Spune-le și prietenilor!</p>
            <div class="flex items-center justify-center gap-3">
                <a href="#" id="shareFb" class="w-12 h-12 bg-[#1877F2] text-paper rounded-xl flex items-center justify-center hover:scale-110 transition-transform" aria-label="Share Facebook">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" id="shareWa" class="w-12 h-12 bg-[#25D366] text-paper rounded-xl flex items-center justify-center hover:scale-110 transition-transform" aria-label="Share WhatsApp">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <button onclick="ThankYouPage.copyLink()" class="w-12 h-12 bg-paper border-2 border-ink/15 rounded-xl flex items-center justify-center hover:border-ink transition" aria-label="Copy link">
                    <svg class="w-6 h-6 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
            </div>
        </div>

        <!-- Back -->
        <div class="mt-12 text-center content-section delay-4">
            <a href="/" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold rounded-full bg-vermilion text-paper hover:bg-vermilion-d transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Înapoi la pagina principală
            </a>
        </div>
    </div>
</main>

<style>
    /* Confetti */
    .confetti-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 100; overflow: hidden; }
    .confetti { position: absolute; width: 10px; height: 10px; opacity: 0; animation: confetti-fall 4s ease-out forwards; }
    @keyframes confetti-fall {
        0% { opacity: 1; transform: translateY(-100px) rotate(0deg); }
        100% { opacity: 0; transform: translateY(100vh) rotate(720deg); }
    }

    /* Printer animation */
    .printer-section { padding-top: 30px; padding-bottom: 10px; }
    @media (max-width: 768px) { .printer-section { padding-top: 16px; } }
    .printer-container { perspective: 1000px; position: relative; }
    .printer { position: relative; width: 280px; height: 120px; margin: 0 auto; }
    @media (max-width: 768px) { .printer { width: 240px; height: 100px; } }
    .printer-body { position: absolute; bottom: 0; width: 100%; height: 80px; background: linear-gradient(145deg, #2E2820 0%, #1B1714 100%); border-radius: 12px; box-shadow: 0 14px 40px -10px rgba(27,23,20,.4); }
    @media (max-width: 768px) { .printer-body { height: 70px; } }
    .printer-body::before { content: ''; position: absolute; top: 15px; left: 50%; transform: translateX(-50%); width: 180px; height: 8px; background: #0F0C0A; border-radius: 4px; }
    .printer-slot { position: absolute; top: -5px; left: 50%; transform: translateX(-50%); width: 200px; height: 10px; background: #0F0C0A; border-radius: 2px; }
    @media (max-width: 768px) { .printer-slot { width: 160px; } }
    .printer-light { position: absolute; bottom: 15px; right: 20px; width: 10px; height: 10px; background: #DA9A33; border-radius: 50%; animation: blink 1s ease-in-out infinite; box-shadow: 0 0 12px #DA9A33; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }

    /* Printing ticket animation */
    .printing-ticket { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); width: 180px; opacity: 0; z-index: 5; }
    @media (max-width: 768px) { .printing-ticket { width: 150px; } }
    .ticket-mini { background: #F4EFE3; border: 2px solid #1B1714; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 30px rgba(27,23,20,.18); }
    .ticket-mini-header { background: #1B1714; color: #F4EFE3; padding: 8px 12px; }
    .ticket-mini-body { padding: 12px; background: #F4EFE3; color: #1B1714; }

    @keyframes printAndExit {
        0% { opacity: 0; transform: translateX(-50%) translateY(0px); clip-path: inset(100% 0 0 0); }
        15% { opacity: 1; clip-path: inset(70% 0 0 0); }
        30% { clip-path: inset(40% 0 0 0); }
        50% { clip-path: inset(0% 0 0 0); transform: translateX(-50%) translateY(0px); }
        70% { transform: translateX(-50%) translateY(-60px); opacity: 1; }
        100% { transform: translateX(-50%) translateY(-100px); opacity: 0; clip-path: inset(0% 0 0 0); }
    }
    .print-ticket-1 { animation: printAndExit 1.5s ease-out forwards; animation-delay: 0.5s; }

    /* Tickets carousel */
    .tickets-carousel { opacity: 0; transform: translateY(30px); animation: showCarousel 0.8s ease forwards; animation-delay: 2.5s; }
    @keyframes showCarousel { to { opacity: 1; transform: translateY(0); } }

    .tickets-scroll { display: flex; gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 20px 4px; scrollbar-width: none; cursor: grab; }
    .tickets-scroll::-webkit-scrollbar { display: none; }
    .tickets-scroll.dragging { cursor: grabbing; scroll-snap-type: none; user-select: none; }

    .ticket-card { flex-shrink: 0; scroll-snap-align: center; width: 280px; background: #F4EFE3; border: 2px solid #1B1714; border-radius: 20px; overflow: hidden; box-shadow: 0 14px 40px -16px rgba(27,23,20,.35); transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .ticket-card:hover { transform: translateY(-5px); box-shadow: 0 22px 50px -16px rgba(232,69,39,.35); }
    @media (max-width: 768px) { .ticket-card { width: 260px; } }
    .ticket-card-header { background: #1B1714; padding: 14px 18px; color: #F4EFE3; position: relative; }
    .ticket-card-body { padding: 18px; background: #F4EFE3; color: #1B1714; position: relative; }
    .ticket-card-body::before, .ticket-card-body::after { content: ''; position: absolute; top: 0; width: 22px; height: 22px; background: #F4EFE3; border-radius: 50%; transform: translateY(-50%); }
    .ticket-card-body::before { left: -11px; box-shadow: inset 0 0 0 2px #1B1714; clip-path: inset(50% 0 0 0); }
    .ticket-card-body::after { right: -11px; box-shadow: inset 0 0 0 2px #1B1714; clip-path: inset(50% 0 0 0); }
    .ticket-dashed-line { position: absolute; left: 18px; right: 18px; top: 0; border-top: 2px dashed rgba(27,23,20,.2); }

    .ticket-barcode { display: flex; justify-content: center; gap: 2px; margin-top: 14px; padding-top: 14px; border-top: 1px dashed rgba(27,23,20,.2); }
    .barcode-line { width: 2px; background: #1B1714; border-radius: 1px; }

    .scroll-indicators { display: flex; justify-content: center; gap: 8px; margin-top: 16px; }
    .scroll-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(27,23,20,.2); transition: all 0.3s ease; cursor: pointer; }
    .scroll-dot.active { width: 24px; border-radius: 4px; background: #E84527; }

    .swipe-hint { display: none; align-items: center; justify-content: center; gap: 8px; color: #5A4F41; font-size: 13px; margin-bottom: 8px; animation: swipeHint 2s ease-in-out infinite; }
    @media (max-width: 768px) { .swipe-hint { display: flex; } }
    @keyframes swipeHint { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(10px); } }

    /* Content animations */
    .email-animation { opacity: 0; animation: fadeInUp 0.6s ease forwards; animation-delay: 3s; }
    .content-section { opacity: 0; animation: fadeInUp 0.6s ease forwards; }
    .delay-1 { animation-delay: 3.2s; }
    .delay-2 { animation-delay: 3.4s; }
    .delay-3 { animation-delay: 3.6s; }
    .delay-4 { animation-delay: 3.8s; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script defer src="<?= asset('assets/js/pages/thank-you.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof ProfileCompletionModal !== 'undefined') ProfileCompletionModal.triggerAfterPurchase();
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
