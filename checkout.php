<?php
/**
 * bilete.online — /finalizare
 *
 * Checkout page. PHP scaffolds the DOM containers; checkout-page.js fills them
 * from the marketplace API. The DOM contract (element IDs and the global
 * CheckoutPage.* methods) MUST match the ones checkout-page.js queries — they
 * are the same IDs ambilet uses (checkout-loading, checkout-form, empty-cart,
 * summary-section, buyer-first-name, buyer-last-name, buyer-email,
 * buyer-email-confirm, buyer-phone, createAccountCheckbox,
 * differentBeneficiaries, beneficiariesList, beneficiaries-count,
 * insurance-section + variants, termsCheckbox, newsletterCheckbox,
 * cultural-card-option / cardForm / culturalCardForm, taxes-container,
 * summary-items, summary-subtotal, discount-row, insurance-row,
 * cultural-card-row, summary-total, savings-text, points-earned, payBtn,
 * pay-btn-text, countdown, timer-bar, login-modal / login-email /
 * login-password / login-submit-btn).
 *
 * Visually re-skinned to the bilete.online "ticket / paper-grain" style:
 * Fraunces display headings, Hanken Grotesk body, paper / ink palette,
 * vermilion accents, ticket-style dark sidebar.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Checkout securizat — ' . SITE_NAME;
$pageDescription = 'Finalizează comanda pentru biletele selectate. Plătești securizat cu cardul, Apple Pay, Google Pay sau alte metode disponibile.';
$canonicalUrl    = SITE_URL . '/finalizare';
$noindex         = true;
$currentPage     = 'checkout';
$cssBundle       = 'checkout';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Progress Steps -->
<section class="border-b-2 border-ink/10 bg-paper-2/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-center gap-3 font-mono text-xs tracking-wider">
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-forest bg-forest text-paper">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="text-forest font-semibold">Coș</span>
            <span class="w-12 h-px bg-forest"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-ink bg-ink text-paper font-bold">2</span>
            <span class="text-ink font-semibold">Checkout</span>
            <span class="w-12 h-px bg-ink/20"></span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-ink/20 text-ink-soft font-bold">3</span>
            <span class="text-ink-soft">Confirmare</span>
        </div>
    </div>
</section>

<!-- Reservation Timer -->
<div id="timer-bar" class="hidden border-b border-vermilion/20 bg-vermilion/5 mobile:sticky mobile:top-18 mobile:bg-vermilion mobile:text-paper mobile:z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3">
        <div class="flex items-center justify-center gap-2 text-sm">
            <svg class="w-5 h-5 text-vermilion" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-ink">Finalizează comanda în</span>
            <span id="countdown" class="font-bold countdown text-vermilion tabular-nums font-mono">14:59</span>
            <span class="text-ink">minute</span>
        </div>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
        <a href="/" class="hover:text-vermilion">Acasă</a>
        <span>/</span>
        <a href="/cos" class="hover:text-vermilion">Coș</a>
        <span>/</span>
        <span class="text-ink">Checkout</span>
    </nav>

    <div class="mt-7 grid lg:grid-cols-[1fr_410px] gap-8 items-start">

        <!-- LEFT — Checkout form -->
        <div>
            <div class="mb-7">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PASUL 2 · CHECKOUT</p>
                <h1 class="mt-4 font-display text-4xl sm:text-5xl font-bold leading-[.95]">Finalizează comanda</h1>
                <p class="mt-3 max-w-2xl text-ink-soft">Completează datele beneficiarilor, alege metoda de plată și primește biletele cu QR pe email instant.</p>
            </div>

            <!-- Loading skeleton -->
            <div id="checkout-loading" class="space-y-4">
                <div class="h-48 rounded-3xl border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                <div class="h-56 rounded-3xl border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                <div class="h-44 rounded-3xl border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
            </div>

            <!-- Checkout Form -->
            <div id="checkout-form" class="hidden space-y-6">

                <!-- 1. Buyer Information -->
                <section class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                    <div class="p-5 sm:p-6 border-b-2 border-dashed border-ink/15 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-3xl font-bold">1. Cont și date de contact</h2>
                            <p class="mt-1 text-ink-soft">Poți continua rapid ca vizitator. Datele sunt folosite doar pentru această comandă.</p>
                        </div>
                        <button type="button" id="guest-login-btn" onclick="CheckoutPage.showLoginModal()" class="hidden items-center gap-2 px-4 py-2 rounded-full border-2 border-ink text-sm font-bold hover:bg-ink hover:text-paper transition" aria-label="Autentificare pentru clienți existenți">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            Am deja cont · Login
                        </button>
                    </div>
                    <div class="p-5 sm:p-6 grid sm:grid-cols-2 gap-4">
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Nume *</span>
                            <input type="text" id="buyer-last-name" class="field" placeholder="ex. Popescu" autocomplete="family-name" required>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Prenume *</span>
                            <input type="text" id="buyer-first-name" class="field" placeholder="ex. Ion" autocomplete="given-name" required>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Email *</span>
                            <input type="email" id="buyer-email" class="field" placeholder="email@exemplu.ro" autocomplete="off" required>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Confirmă email *</span>
                            <input type="email" id="buyer-email-confirm" class="field" placeholder="email@exemplu.ro" autocomplete="new-password" onpaste="return false;" ondrop="return false;" required>
                            <p id="email-mismatch-error" class="hidden mt-1 text-sm font-bold text-vermilion">Adresele de email nu coincid</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="block mb-1.5 text-sm font-bold">Telefon *</span>
                            <input type="tel" id="buyer-phone" class="field" placeholder="07XX XXX XXX" autocomplete="tel" required>
                        </label>

                        <div id="create-account-row" class="hidden sm:col-span-2 flex items-start gap-3 rounded-2xl border-2 border-ink/10 bg-paper-2/60 px-4 py-3">
                            <input type="checkbox" id="createAccountCheckbox" class="mt-0.5 w-5 h-5 accent-vermilion cursor-pointer">
                            <div>
                                <label for="createAccountCheckbox" class="font-bold cursor-pointer">Creează-mi cont automat după comandă</label>
                                <p class="mt-1 text-sm text-ink-soft">Primești parola pe email și poți accesa biletele oricând din contul tău.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. Beneficiaries -->
                <section class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                    <div class="p-5 sm:p-6 border-b-2 border-dashed border-ink/15 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-3xl font-bold">2. Beneficiari bilete</h2>
                            <p class="mt-1 text-ink-soft">Poți pune același nume pe toate biletele sau nume diferite pentru fiecare beneficiar.</p>
                        </div>
                        <span id="beneficiaries-count" class="text-sm font-mono text-ink-soft">0 bilete</span>
                    </div>
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-paper-2/60 border border-ink/10">
                            <div id="allTicketsToEmail" class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-forest flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-sm font-bold">Toate biletele vor fi trimise pe emailul tău</p>
                            </div>
                            <label class="inline-flex items-center gap-3 rounded-full bg-paper border-2 border-ink/15 px-4 py-2 cursor-pointer hover:border-ink transition">
                                <input type="checkbox" id="differentBeneficiaries" class="w-5 h-5 accent-vermilion cursor-pointer" onchange="CheckoutPage.toggleBeneficiaries()">
                                <span class="text-sm font-bold">Folosește date diferite pentru fiecare bilet</span>
                            </label>
                        </div>

                        <!-- Beneficiaries list (filled by JS) -->
                        <div id="beneficiariesList" class="hidden mt-6 space-y-4"></div>
                    </div>
                </section>

                <!-- 3. Ticket Insurance (mint, shown dynamically if enabled) -->
                <section id="insurance-section" class="hidden rounded-3xl border-2 border-forest/40 bg-mint overflow-hidden">
                    <div class="p-5 sm:p-6 border-b-2 border-dashed border-forest/25">
                        <h2 class="font-display text-3xl font-bold" id="insurance-label">3. Protecție bilet</h2>
                        <p class="mt-1 text-ink-soft" id="insurance-description">Adaugă protecție pentru flexibilitate: poți cere retur conform condițiilor pachetului.</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <label class="flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between rounded-2xl bg-paper border-2 border-forest/30 px-5 py-4 cursor-pointer hover:border-forest transition" id="insurance-option">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="insuranceCheckbox" class="mt-1 w-5 h-5 accent-vermilion cursor-pointer">
                                <div>
                                    <p class="font-bold text-lg" id="insurance-title">Protecție returnare bilete</p>
                                    <p class="mt-1 text-sm text-ink-soft">Poți solicita returnarea biletelor în cazul în care evenimentul este amânat sau anulat.</p>
                                    <p class="hidden mt-2 text-xs font-bold text-ochre" id="insurance-partial-note"></p>
                                    <a href="#" id="insurance-terms-link" class="hidden mt-2 text-xs font-bold text-vermilion underline-wobble">Vezi termeni și condiții</a>
                                </div>
                            </div>
                            <span class="font-display text-2xl font-bold text-forest whitespace-nowrap" id="insurance-price">+5.00 lei</span>
                        </label>
                    </div>
                </section>

                <!-- 4. Payment Method -->
                <section class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                    <div class="p-5 sm:p-6 border-b-2 border-dashed border-ink/15">
                        <h2 class="font-display text-3xl font-bold">4. Metodă de plată</h2>
                        <p class="mt-1 text-ink-soft">Plățile cu cardul sunt procesate securizat prin Stripe. 3D Secure, PCI DSS Level 1.</p>
                    </div>
                    <div class="p-5 sm:p-6 space-y-3">
                        <!-- Credit Card - Stripe -->
                        <label class="payment-option selected flex items-center gap-4 p-4 rounded-2xl border-2 border-ink/15 cursor-pointer transition">
                            <span class="payment-radio"></span>
                            <div class="flex items-center justify-between flex-1 gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-14 h-8 rounded bg-gradient-to-r from-[#635bff] to-[#4f46e5]">
                                        <span class="text-paper text-[10px] font-bold tracking-wider">STRIPE</span>
                                    </div>
                                    <div>
                                        <p class="font-bold">Card bancar</p>
                                        <p class="text-xs text-ink-soft">Visa, Mastercard, Maestro, Apple Pay, Google Pay</p>
                                    </div>
                                </div>
                                <div class="hidden sm:flex items-center gap-2">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Visa_Inc._logo_%282021%E2%80%93present%29.svg" alt="Visa" class="h-5 opacity-70">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/200px-Mastercard-logo.svg.png" alt="Mastercard" class="h-5 opacity-70">
                                </div>
                            </div>
                            <input type="radio" name="payment" value="card" class="hidden" checked>
                        </label>

                        <!-- Cultural Card (hidden by default, shown by JS) -->
                        <label id="cultural-card-option" class="payment-option hidden flex items-center gap-4 p-4 rounded-2xl border-2 border-ink/15 cursor-pointer transition">
                            <span class="payment-radio"></span>
                            <div class="flex items-center justify-between flex-1 gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-12 h-8 rounded bg-gradient-to-r from-ochre to-vermilion">
                                        <svg class="w-5 h-5 text-paper" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold">Card Cultural</p>
                                        <p class="text-xs text-ink-soft">Edenred, Sodexo, Up România</p>
                                    </div>
                                </div>
                            </div>
                            <input type="radio" name="payment" value="card_cultural" class="hidden">
                        </label>

                        <!-- Accepted wallets info -->
                        <div class="flex flex-wrap items-center gap-3 pt-3 mt-2 border-t border-ink/10">
                            <span class="text-xs font-mono text-ink-soft tracking-wider">ACCEPTĂM ȘI:</span>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-paper-2/60 border border-ink/10 rounded-lg">
                                <svg class="h-3.5" viewBox="0 0 24 24" fill="none">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                <span class="text-[11px] font-bold">Google Pay</span>
                            </div>
                            <div class="flex items-center gap-1 px-2.5 py-1 bg-ink text-paper rounded-lg">
                                <svg class="h-3" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                                </svg>
                                <span class="text-[11px] font-bold">Pay</span>
                            </div>
                        </div>

                        <!-- Card Form Info -->
                        <div id="cardForm" class="p-4 mt-2 rounded-2xl bg-paper-2/60 border border-ink/10">
                            <p class="mb-3 text-sm text-ink-soft">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului în siguranță.</p>
                            <div class="flex items-center gap-2 text-xs text-ink-soft">
                                <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plățile sunt procesate securizat · SSL 256-bit · 3D Secure
                            </div>
                        </div>

                        <!-- Cultural Card Info -->
                        <div id="culturalCardForm" class="hidden p-4 mt-2 rounded-2xl bg-ochre/10 border border-ochre/30">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 text-ochre flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="mb-1 text-sm font-bold">Comision adițional Card Cultural</p>
                                    <p id="cultural-card-surcharge-text" class="text-sm text-ink-soft">Tranzacțiile cu card cultural au un comision de procesare suplimentar de <strong>4%</strong> din valoarea totală, datorat costurilor mai mari de procesare pentru acest tip de card.</p>
                                    <p class="mt-2 text-xs text-ink-soft">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului cultural în siguranță.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. Terms / Acorduri -->
                <section class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-5 sm:p-6">
                    <h2 class="font-display text-3xl font-bold">5. Acorduri</h2>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="termsCheckbox" class="mt-1 w-5 h-5 accent-vermilion cursor-pointer" required>
                            <span class="text-sm">
                                Am citit și sunt de acord cu
                                <a href="/termeni" class="font-bold text-vermilion underline-wobble">Termenii și condițiile</a>,
                                <a href="/confidentialitate" class="font-bold text-vermilion underline-wobble">Politica de confidențialitate</a> și
                                <a href="/retur" class="font-bold text-vermilion underline-wobble">Politica de returnare</a>.
                            </span>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="newsletterCheckbox" class="mt-1 w-5 h-5 accent-vermilion cursor-pointer">
                            <span class="text-sm">
                                Vreau să primesc recomandări, oferte și activități noi prin newsletter-ul <?= SITE_NAME ?>.
                            </span>
                        </label>
                    </div>
                </section>
            </div>

            <!-- Empty Cart State -->
            <div id="empty-cart" class="hidden p-12 text-center bg-paper-2/40 border-2 border-dashed border-ink/15 rounded-3xl">
                <div class="mx-auto w-20 h-20 grid place-items-center bg-paper border-2 border-ink rounded-full mb-6 rotate-[-3deg]">
                    <svg class="w-10 h-10 text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <h2 class="font-display text-3xl font-bold">Coșul tău e gol</h2>
                <p class="mt-3 text-ink-soft">Nu ai bilete în coș. Descoperă activitățile și evenimentele disponibile.</p>
                <a href="/categorii" class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-full bg-vermilion text-paper font-bold hover:bg-vermilion-d transition">
                    Explorează activități
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

        <!-- RIGHT — Order summary -->
        <aside class="lg:sticky lg:top-28">
            <div id="summary-section" class="hidden">
                <div class="ticket bg-ink text-paper rounded-3xl overflow-hidden shadow-ticket" style="--perf:100%">
                    <div class="p-6 border-b-2 border-dashed border-paper/20">
                        <p class="font-mono text-xs tracking-[.18em] text-paper/50">SUMAR CHECKOUT</p>
                        <h2 class="mt-2 font-display text-3xl font-bold">De plată</h2>
                    </div>
                    <div class="p-6 space-y-3 text-sm">

                        <!-- Event info -->
                        <div id="event-info" class="flex gap-3 pb-4 mb-1 border-b border-paper/15"></div>

                        <!-- Items summary -->
                        <div id="items-summary" class="space-y-2"></div>

                        <!-- Dynamic taxes container -->
                        <div id="taxes-container" class="space-y-2 pt-3 border-t border-paper/15"></div>

                        <!-- Subtotal — base prices only; commission below -->
                        <div class="flex justify-between">
                            <span class="text-paper/60">Subtotal (<span id="summary-items">0</span> bilete)</span>
                            <strong id="summary-subtotal" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Platform commission (added on top, e.g. 2%) -->
                        <div id="platform-commission-row" class="hidden flex justify-between">
                            <span class="text-paper/60" id="platform-commission-label">Comision ticketing</span>
                            <strong id="platform-commission-amount" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Discount row -->
                        <div id="discount-row" class="hidden flex justify-between p-2 -mx-2 rounded-lg bg-ochre/15">
                            <span class="flex items-center gap-1.5 text-ochre">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                <span id="discount-label">Reducere</span>
                            </span>
                            <strong id="discount-amount" class="text-ochre">-0.00 lei</strong>
                        </div>

                        <!-- Insurance row -->
                        <div id="insurance-row" class="hidden flex justify-between p-2 -mx-2 rounded-lg bg-forest/30">
                            <span class="flex items-center gap-1.5 text-mint">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span id="insurance-row-label">Protecție bilet</span>
                            </span>
                            <strong id="insurance-row-amount" class="text-mint">+0.00 lei</strong>
                        </div>

                        <!-- Cultural card surcharge -->
                        <div id="cultural-card-row" class="hidden flex justify-between p-2 -mx-2 rounded-lg bg-ochre/15">
                            <span class="flex items-center gap-1.5 text-ochre">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <span id="cultural-card-surcharge-label">Comision card cultural (4%)</span>
                            </span>
                            <strong id="cultural-card-amount" class="text-ochre">+0.00 lei</strong>
                        </div>

                        <!-- Payment processing fee preview (Stripe / Netopia) -->
                        <div id="processing-fee-row" class="hidden flex justify-between">
                            <span class="text-paper/60" id="processing-fee-label">Comision tranzacționare plată</span>
                            <strong id="processing-fee-amount" class="font-medium">0.00 lei</strong>
                        </div>

                        <!-- Total -->
                        <div class="pt-4 border-t border-paper/15 flex justify-between gap-4 text-lg">
                            <span>Total de plată</span>
                            <strong id="summary-total" class="font-display text-3xl">0.00 lei</strong>
                        </div>
                        <p id="savings-text" class="hidden flex items-center justify-end gap-1 text-sm text-mint">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span id="savings-amount">Economisești 0 lei!</span>
                        </p>

                        <!-- Points to Earn -->
                        <div class="rounded-2xl bg-forest/40 border border-paper/10 p-4 mt-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">🎁</span>
                                    <span class="text-sm font-bold text-ochre">Vei câștiga</span>
                                </div>
                                <span id="points-earned" class="font-display text-2xl font-bold text-ochre">0 puncte</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-paper text-ink">
                        <button onclick="CheckoutPage.submit()" id="payBtn" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold text-lg hover:bg-vermilion-d transition flex items-center justify-center gap-2" disabled aria-label="Finalizează comanda">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span id="pay-btn-text">Plasează comanda · 0.00 lei</span>
                        </button>
                        <p class="mt-3 text-xs text-center text-ink-soft">
                            Prin plasarea comenzii, confirmi că ai citit și ești de acord cu termenii și condițiile.
                        </p>
                    </div>
                </div>

                <!-- Security badges -->
                <div class="mt-4 rounded-2xl border border-ink/10 bg-paper p-4">
                    <div class="flex items-center justify-around gap-3 flex-wrap text-xs text-ink-soft">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            SSL 256-bit
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            PCI DSS
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            3D Secure
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- Login Modal -->
<div id="login-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/60 backdrop-blur-sm">
    <div class="w-full max-w-md p-8 mx-4 bg-paper border-2 border-ink rounded-3xl shadow-ticket">
        <div class="flex items-center justify-between mb-6">
            <h3 class="font-display text-2xl font-bold">Conectează-te</h3>
            <button type="button" onclick="CheckoutPage.hideLoginModal()" class="p-2 rounded-full hover:bg-paper-2 transition" aria-label="Închide modalul de login">
                <svg class="w-5 h-5 text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <p class="mb-6 text-sm text-ink-soft">Conectează-te pentru a-ți precompletă datele și a finaliza comanda mai rapid.</p>

        <form id="checkout-login-form" onsubmit="return CheckoutPage.handleLogin(event)">
            <label class="block mb-4">
                <span class="block mb-1.5 text-sm font-bold">Email</span>
                <input type="email" id="login-email" class="field" placeholder="email@exemplu.ro" required>
            </label>
            <label class="block mb-6">
                <span class="block mb-1.5 text-sm font-bold">Parola</span>
                <input type="password" id="login-password" class="field" placeholder="••••••••" required>
            </label>
            <button type="submit" id="login-submit-btn" class="w-full rounded-full bg-vermilion text-paper py-3 font-bold hover:bg-vermilion-d transition">
                <span id="login-btn-text">Conectează-te</span>
            </button>
        </form>

        <div class="flex items-center justify-between mt-4 text-sm">
            <a href="/parola-uitata" target="_blank" class="font-bold text-vermilion underline-wobble">Ai uitat parola?</a>
            <a href="/inregistrare" target="_blank" class="font-bold text-vermilion underline-wobble">Creează cont</a>
        </div>
    </div>
</div>

<style>
    .btn-primary:disabled,
    #payBtn:disabled { opacity: 0.4; cursor: not-allowed; }
    .payment-option { transition: all 0.25s ease; }
    .payment-option:hover { border-color: rgba(27,23,20,.5); }
    .payment-option.selected { border-color: #1B1714; background-color: rgba(232,69,39,.05); }
    .payment-option.selected .payment-radio { border-color: #E84527; background-color: #E84527; }
    .payment-option.selected .payment-radio::after { content: ''; position: absolute; inset: 4px; background: #F4EFE3; border-radius: 50%; }
    .payment-radio { position: relative; width: 20px; height: 20px; border: 2px solid rgba(27,23,20,.2); border-radius: 50%; transition: all 0.2s ease; flex-shrink: 0; }
    .beneficiary-card { transition: all 0.25s ease; }
    .beneficiary-card:hover { border-color: #1B1714; }
</style>

<script defer src="<?= asset('assets/js/pages/checkout-page.js') ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
