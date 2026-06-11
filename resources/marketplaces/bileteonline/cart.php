<?php
/**
 * bilete.online — /cos
 *
 * Cart page. PHP scaffolds the DOM containers; cart-page.js fills them
 * from the marketplace API. The DOM contract (element IDs) MUST match the
 * IDs that cart-page.js queries — they're the same ones ambilet uses
 * (cart-loading, cartPageItems, emptyCart, summary-section, promo-section,
 * countdown, timer-bar, totalItems, subtotal, totalPrice, taxesContainer,
 * discountRow, discountAmount, savingsRow, savings, pointsEarned,
 * promoCode, promoMessage, checkoutBtn).
 *
 * Visually re-skinned to the bilete.online "ticket / paper-grain" style:
 * Fraunces display headings, Hanken Grotesk body, paper / ink palette,
 * vermilion accents. Functionality is unchanged from the ambilet
 * implementation — same JS, same API contract.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Coșul tău — ' . SITE_NAME;
$pageDescription = 'Verifică biletele și activitățile selectate, aplică puncte bonus sau coduri promoționale și continuă spre checkout.';
$canonicalUrl    = SITE_URL . '/cos';
$noindex         = true;
$currentPage     = 'cart';
$cssBundle       = 'checkout';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Progress Steps -->
<section class="border-b-2 border-ink/10 bg-paper-2/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-center gap-3 font-mono text-xs tracking-wider">
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-ink bg-ink text-paper font-bold">1</span>
            <span class="text-ink font-semibold">Coș</span>
            <span class="w-12 h-px bg-ink/20"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-ink/20 text-ink-soft font-bold">2</span>
            <span class="text-ink-soft">Checkout</span>
            <span class="w-12 h-px bg-ink/20"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-ink/20 text-ink-soft font-bold">3</span>
            <span class="text-ink-soft">Confirmare</span>
        </div>
    </div>
</section>

<!-- Reservation Timer -->
<div id="timer-bar" class="border-b border-vermilion/20 bg-vermilion/5 mobile:sticky mobile:top-18 mobile:bg-vermilion mobile:text-paper mobile:z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3">
        <div class="flex items-center justify-center gap-2 text-sm">
            <svg class="w-5 h-5 text-vermilion" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-ink">Biletele sunt rezervate pentru tine încă</span>
            <span id="countdown" class="font-bold countdown text-vermilion tabular-nums font-mono">14:59</span>
            <span class="text-ink">minute</span>
        </div>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

    <!-- Breadcrumb + hero -->
    <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
        <a href="/" class="hover:text-vermilion">Acasă</a>
        <span>/</span>
        <span class="text-ink">Coș</span>
    </nav>

    <div class="mt-6 grid lg:grid-cols-[1fr_380px] gap-8 items-start">

        <!-- LEFT — items -->
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4 mb-7">
                <div>
                    <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PASUL 1 · COȘ</p>
                    <h1 class="mt-4 font-display text-4xl sm:text-5xl font-bold leading-[.95]">Coșul tău</h1>
                    <p class="mt-3 text-ink-soft">Verifică biletele și activitățile, comisioanele, punctele bonus, apoi continuă spre plata securizată.</p>
                </div>
                <span class="text-sm text-ink-soft font-mono"><span id="totalItems">0</span> bilete</span>
            </div>

            <!-- Loading skeleton -->
            <div id="cart-loading" class="space-y-4">
                <div class="h-40 rounded-3xl border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                <div class="h-40 rounded-3xl border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
            </div>

            <!-- Cart items (filled by cart-page.js) -->
            <div id="cartPageItems" class="hidden space-y-4"></div>

            <!-- Empty state -->
            <div id="emptyCart" class="hidden p-12 text-center bg-paper-2/40 border-2 border-dashed border-ink/15 rounded-3xl">
                <div class="mx-auto w-20 h-20 grid place-items-center bg-paper border-2 border-ink rounded-full mb-6 rotate-[-3deg]">
                    <svg class="w-10 h-10 text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <h2 class="font-display text-3xl font-bold">Coșul tău e gol</h2>
                <p class="mt-3 text-ink-soft">Nu ai nicio activitate sau eveniment în coș. Descoperă-le pe cele disponibile.</p>
                <a href="/categorii" class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-full bg-vermilion text-paper font-bold hover:bg-vermilion-d transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Explorează activități
                </a>
            </div>

            <!-- Promo code -->
            <div id="promo-section" class="hidden mt-8 rounded-3xl border-2 border-ink/15 bg-paper-2/60 p-5 sm:p-6">
                <h3 class="font-display text-2xl font-bold">Ai un cod promoțional?</h3>
                <div class="mt-4 flex gap-2">
                    <input id="promoCode" type="text" placeholder="ex. WEEKEND10" class="field flex-1" autocomplete="off">
                    <button onclick="CartPage.applyPromo()" class="px-6 rounded-2xl bg-ink text-paper font-bold hover:bg-vermilion transition" aria-label="Aplică cod">Aplică</button>
                </div>
                <p id="promoMessage" class="hidden mt-2 text-sm"></p>
            </div>
        </div>

        <!-- RIGHT — order summary -->
        <aside class="lg:sticky lg:top-28">
            <div id="summary-section" class="hidden">
                <div class="ticket bg-ink text-paper rounded-3xl overflow-hidden shadow-ticket" style="--perf:100%">
                    <div class="p-6 border-b-2 border-dashed border-paper/20">
                        <p class="font-mono text-xs tracking-[.18em] text-paper/50">SUMAR COMANDĂ</p>
                        <h2 class="mt-2 font-display text-3xl font-bold">Total coș</h2>
                    </div>
                    <div class="p-6 space-y-3 text-sm">

                        <!-- Dynamic breakdown -->
                        <div id="taxesContainer" class="space-y-2"></div>

                        <!-- Discount row -->
                        <div id="discountRow" class="flex justify-between hidden">
                            <span class="text-ochre">Reducere aplicată</span>
                            <strong id="discountAmount" class="text-ochre">-0.00 lei</strong>
                        </div>

                        <!-- Subtotal (last before total) — base prices only -->
                        <div class="flex justify-between pt-3 border-t border-paper/15">
                            <span class="text-paper/60">Subtotal (<span id="summaryItems">0</span> bilete)</span>
                            <strong id="subtotal" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Platform commission (added on top, e.g. 2%) -->
                        <div id="platformCommissionRow" class="flex justify-between hidden">
                            <span class="text-paper/60" id="platformCommissionLabel">Comision ticketing</span>
                            <strong id="platformCommissionAmount" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Payment processing fee preview (Stripe / Netopia) -->
                        <div id="processingFeeRow" class="flex justify-between hidden">
                            <span class="text-paper/60">Taxa procesare card</span>
                            <strong id="processingFeeAmount" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Savings -->
                        <div id="savingsRow" class="hidden px-3 py-2 bg-forest/30 rounded-xl">
                            <div class="flex items-center justify-between gap-2">
                                <span id="savingsText" class="text-sm font-medium text-mint">Economisești:</span>
                                <span id="savings" class="font-bold text-mint">0.00 lei</span>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="flex items-center justify-between pt-4 border-t border-paper/15">
                            <span class="font-display text-xl font-bold">Total de plată</span>
                            <span id="totalPrice" class="font-display text-3xl font-bold">0.00 lei</span>
                        </div>

                        <!-- Points earned -->
                        <div class="p-4 mt-2 border border-ochre/30 bg-ochre/10 rounded-2xl">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 grid place-items-center rounded-full bg-ochre/20">
                                        <span class="text-xl">🎁</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Vei câștiga</p>
                                        <p class="text-xs text-paper/50">1 punct / 10 lei cheltuiți</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span id="pointsEarned" class="text-2xl font-bold text-ochre points-animation">0</span>
                                    <p class="text-xs text-paper/50">puncte</p>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-paper/45">Taxa de procesare card se calculează la checkout, în funcție de metoda de plată.</p>
                    </div>

                    <div class="p-6 bg-paper text-ink">
                        <a id="checkoutBtn" href="/finalizare" class="flex items-center justify-center gap-2 w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold text-lg hover:bg-vermilion-d transition">
                            Continuă spre plată
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="/categorii" class="mt-3 block text-center text-sm font-bold underline-wobble">Mai adaugă activități</a>
                    </div>
                </div>

                <!-- Trust badges + payment methods -->
                <div class="mt-4 rounded-2xl border border-ink/10 bg-paper p-4">
                    <p class="text-xs text-center text-ink-soft mb-3">Metode de plată acceptate</p>
                    <div class="flex items-center justify-center gap-3 flex-wrap">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Visa_Inc._logo_%282021%E2%80%93present%29.svg" alt="Visa" class="h-5 opacity-60">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/200px-Mastercard-logo.svg.png" alt="Mastercard" class="h-5 opacity-60">
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-paper-2 text-ink-soft">Apple Pay</span>
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-paper-2 text-ink-soft">Google Pay</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-ink-soft">
                    <div class="flex items-center justify-center gap-2 p-3 rounded-xl bg-paper-2/60">
                        <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Plată securizată
                    </div>
                    <div class="flex items-center justify-center gap-2 p-3 rounded-xl bg-paper-2/60">
                        <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Bilet QR instant
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<style>
    .points-animation { animation: pointsPulse 0.4s ease; }
    @keyframes pointsPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
    .cart-item { transition: all 0.3s ease; }
    .cart-item:hover { border-color: #E84527; }
    .tooltip { opacity: 0; visibility: hidden; transition: all 0.2s ease; transform: translateY(5px); }
    .tooltip-trigger:hover .tooltip { opacity: 1; visibility: visible; transform: translateY(0); }
</style>

<script defer src="<?= asset('assets/js/pages/cart-page.js') ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
