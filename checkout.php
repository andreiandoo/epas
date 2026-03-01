<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Checkout';
$pageDescription = 'Finalizează comanda și plătește biletele';
$cssBundle = 'checkout';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200 mt-18 mobile:mt-18">
        <div class="px-4 py-4 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-white bg-green-500 rounded-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span class="text-sm font-medium text-green-600">Coș</span>
                </div>
                <div class="w-12 h-px bg-green-500"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full bg-primary">2</div>
                    <span class="text-sm font-semibold text-primary">Checkout</span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-400 bg-gray-100 border border-gray-200 rounded-full">3</div>
                    <span class="text-sm text-gray-400">Confirmare</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Timer -->
    <div id="timer-bar" class="hidden border-b bg-warning/10 border-warning/20">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-2 text-sm">
                <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-secondary">Finalizează comanda în</span>
                <span id="countdown" class="font-bold countdown text-warning tabular-nums">14:59</span>
                <span class="text-secondary">minute</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl">
        <div class="flex flex-col gap-8 lg:flex-row">
            <!-- Left Column - Checkout Form -->
            <div class="lg:w-2/3">
                <h1 class="mb-6 text-2xl font-bold md:text-3xl text-secondary">Finalizare comandă</h1>

                <!-- Loading State -->
                <div id="checkout-loading" class="space-y-6">
                    <div class="h-48 skeleton rounded-2xl"></div>
                    <div class="h-64 skeleton rounded-2xl"></div>
                    <div class="h-48 skeleton rounded-2xl"></div>
                </div>

                <!-- Checkout Form -->
                <div id="checkout-form" class="hidden">
                    <!-- Buyer Information -->
                    <div class="p-6 mb-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="flex items-center gap-2 text-lg font-bold text-secondary">
                                <span class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-lg bg-primary/10 text-primary">1</span>
                                Datele tale
                            </h2>
                            <!-- Login button for guests -->
                            <button type="button" id="guest-login-btn" onclick="CheckoutPage.showLoginModal()" class="items-center hidden gap-2 px-4 py-2 text-sm font-medium transition-all border-2 rounded-xl text-primary border-primary hover:bg-primary hover:text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                Intră în cont
                            </button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Nume *</label>
                                <input type="text" id="buyer-last-name" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" placeholder="Ex: Popescu" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Prenume *</label>
                                <input type="text" id="buyer-first-name" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" placeholder="Ex: Ion" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Email *</label>
                                <input type="email" id="buyer-email" autocomplete="off" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Confirmă email *</label>
                                <input type="email" id="buyer-email-confirm" autocomplete="new-password" onpaste="return false;" ondrop="return false;" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" required>
                                <p id="email-mismatch-error" class="hidden mt-1 text-sm text-primary">Adresele de email nu coincid</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-secondary">Telefon *</label>
                                <input type="tel" id="buyer-phone" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" required>
                            </div>
                            <!-- Auto-create account checkbox (guests only) -->
                            <div id="create-account-row" class="hidden md:col-span-2">
                                <label class="flex items-center gap-3 cursor-pointer select-none">
                                    <input type="checkbox" id="createAccountCheckbox" class="w-5 h-5 border-2 rounded accent-primary border-border">
                                    <span class="text-sm text-secondary">Creează un cont automat folosind datele de mai sus</span>
                                </label>
                                <p class="mt-1 ml-8 text-xs text-muted">Vei primi parola pe email după finalizarea comenzii</p>
                            </div>
                        </div>
                    </div>

                    <!-- Beneficiaries Section -->
                    <div class="p-6 mb-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="flex items-center gap-2 text-lg font-bold text-secondary">
                                <span class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-lg bg-primary/10 text-primary">2</span>
                                Beneficiari bilete
                            </h2>
                            <span id="beneficiaries-count" class="text-sm text-muted">0 bilete</span>
                        </div>

                        <div class="flex items-center justify-between p-4 mb-4 bg-surface rounded-xl">
                            
                            <div class="flex items-start gap-3" id="allTicketsToEmail">
                                <svg class="w-5 h-5 text-success flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-sm text-muted">
                                    <p class="font-medium text-secondary">Toate biletele vor fi trimise pe emailul tău</p>
                                </div>
                            </div>

                            <!-- Different beneficiaries toggle -->
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="differentBeneficiaries" class="checkbox-custom" onchange="CheckoutPage.toggleBeneficiaries()">
                                <span class="text-sm font-medium text-secondary">Folosește date diferite pentru fiecare bilet</span>
                            </label>
                        </div>

                        <!-- Beneficiaries List (hidden by default) -->
                        <div id="beneficiariesList" class="hidden mt-6 space-y-4"></div>
                    </div>

                    <!-- Ticket Insurance (shown dynamically if enabled) -->
                    <div id="insurance-section" class="hidden mb-6 bg-white">
                        <h2 class="flex items-center gap-2 mb-4 text-lg font-bold text-secondary">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-success/10">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </span>
                            <span id="insurance-label">Taxa de retur</span>
                        </h2>

                        <div class="flex items-start gap-4">
                            <label class="flex items-start flex-1 gap-3 p-4 transition-all border-2 cursor-pointer rounded-xl hover:border-success" id="insurance-option">
                                <input type="checkbox" id="insuranceCheckbox" class="checkbox-custom mt-0.5">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-medium text-secondary" id="insurance-title">Protecție returnare bilete</span>
                                        <span class="font-bold text-success" id="insurance-price">+5.00 lei</span>
                                    </div>
                                    <p class="text-sm text-muted" id="insurance-description">Poți solicita returnarea biletelor în cazul în care evenimentul este amânat sau anulat.</p>
                                    <p class="hidden mt-2 text-xs font-medium text-amber-600" id="insurance-partial-note"></p>
                                    <a href="#" id="insurance-terms-link" class="hidden mt-2 text-xs text-primary hover:underline">Vezi termeni și condiții</a>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="p-6 mb-6 bg-white border rounded-2xl border-border">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="termsCheckbox" class="checkbox-custom mt-0.5" required>
                            <span class="text-sm text-muted">
                                Am citit și sunt de acord cu <a href="/termeni" class="text-primary">Termenii și condițiile</a>,
                                <a href="/confidentialitate" class="text-primary">Politica de confidențialitate</a> și
                                <a href="/retur" class="text-primary">Politica de returnare</a>.
                            </span>
                        </label>

                        <label class="flex items-start gap-3 mt-4 cursor-pointer">
                            <input type="checkbox" id="newsletterCheckbox" class="checkbox-custom mt-0.5">
                            <span class="text-sm text-muted">
                                Doresc să primesc newsletter-ul <?= SITE_NAME ?> cu noutăți și oferte speciale.
                            </span>
                        </label>
                    </div>

                    <!-- Payment Method -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <h2 class="flex items-center gap-2 mb-4 text-lg font-bold text-secondary">
                            <span class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-lg bg-primary/10 text-primary">3</span>
                            Metodă de plată
                        </h2>

                        <div class="space-y-3">
                            <!-- Credit Card - Netopia -->
                            <label class="flex items-center gap-4 p-4 border-2 cursor-pointer payment-option selected border-border rounded-xl">
                                <div class="payment-radio"></div>
                                <div class="flex items-center justify-between flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-12 h-8 rounded bg-gradient-to-r from-blue-600 to-blue-800">
                                            <span class="text-white text-[10px] font-bold">NETOPIA</span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Card bancar</p>
                                            <p class="text-xs text-muted">Visa, Mastercard, Maestro</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/100px-Visa_Inc._logo.svg.png" alt="Visa" class="h-5">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/100px-Mastercard-logo.svg.png" alt="Mastercard" class="h-5">
                                    </div>
                                </div>
                                <input type="radio" name="payment" value="card" class="hidden" checked>
                            </label>

                            <!-- Card Cultural -->
                            <label class="flex items-center gap-4 p-4 border-2 cursor-pointer payment-option border-border rounded-xl">
                                <div class="payment-radio"></div>
                                <div class="flex items-center justify-between flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-12 h-8 rounded bg-gradient-to-r from-purple-600 to-purple-800">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Card Cultural</p>
                                            <p class="text-xs text-muted">Edenred, Sodexo, Up România</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="radio" name="payment" value="card_cultural" class="hidden">
                            </label>
                        </div>

                        <!-- Accepted wallets info (informational only) -->
                        <div class="flex flex-wrap items-center gap-3 px-1 mt-4">
                            <span class="text-xs text-muted">Acceptăm și:</span>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-white border rounded-lg border-border">
                                <svg class="h-3.5" viewBox="0 0 24 24" fill="none">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                <span class="text-[11px] font-medium text-secondary">Google Pay</span>
                            </div>
                            <div class="flex items-center gap-1 px-2.5 py-1 bg-black rounded-lg">
                                <svg class="h-3" viewBox="0 0 24 24" fill="white">
                                    <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                                </svg>
                                <span class="text-[11px] font-medium text-white">Pay</span>
                            </div>
                        </div>

                        <!-- Card Form Info (shown for Card bancar) -->
                        <div id="cardForm" class="p-4 mt-4 bg-surface rounded-xl">
                            <p class="mb-4 text-sm text-muted">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului în siguranță.</p>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plățile sunt procesate securizat
                            </div>
                        </div>

                        <!-- Cultural Card Info (shown for Card Cultural) -->
                        <div id="culturalCardForm" class="hidden p-4 mt-4 rounded-xl bg-purple-50">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="mb-1 text-sm font-medium text-purple-800">Comision adițional Card Cultural</p>
                                    <p class="text-sm text-purple-700">Tranzacțiile cu card cultural au un comision de procesare suplimentar de <strong>4%</strong> din valoarea totală, datorat costurilor mai mari de procesare pentru acest tip de card.</p>
                                    <p class="mt-2 text-xs text-muted">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului cultural în siguranță.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Cart State -->
                <div id="empty-cart" class="hidden p-12 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-24 h-24 mx-auto mb-6 rounded-full bg-surface">
                        <svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-secondary">Coșul tău este gol</h3>
                    <p class="mb-6 text-muted">Adaugă bilete în coș pentru a continua.</p>
                    <a href="/evenimente" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary rounded-xl">
                        Descoperă evenimente
                    </a>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:w-1/3">
                <div id="summary-section" class="sticky hidden top-24">
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-6 border-b border-border">
                            <h2 class="text-xl font-bold text-secondary">Sumar comandă</h2>
                        </div>

                        <div class="p-6">
                            <!-- Event Info -->
                            <div id="event-info" class="flex gap-4 pb-6 mb-6 border-b border-border"></div>

                            <!-- Items Summary -->
                            <div id="items-summary" class="mb-6 space-y-3"></div>

                            <div class="pt-4 space-y-3 border-t border-border">
                                <!-- Dynamic taxes container -->
                                <div id="taxes-container" class="space-y-2">
                                    <!-- Taxes will be rendered dynamically by JS -->
                                </div>
                                
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted">Subtotal (<span id="summary-items">0</span> bilete)</span>
                                    <span id="summary-subtotal" class="font-medium">0.00 lei</span>
                                </div>

                                <!-- Discount Row -->
                                <div id="discount-row" class="flex justify-between hidden p-2 -mx-2 text-sm rounded-lg bg-success/5">
                                    <span class="flex items-center gap-1 text-success">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                        <span id="discount-label">Reducere</span>
                                    </span>
                                    <span id="discount-amount" class="font-medium text-success">-0.00 lei</span>
                                </div>

                                <!-- Insurance Row (shown if selected) -->
                                <div id="insurance-row" class="flex justify-between hidden p-2 -mx-2 text-sm rounded-lg bg-success/5">
                                    <span class="flex items-center gap-1 text-success">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        <span id="insurance-row-label">Taxa de retur</span>
                                    </span>
                                    <span id="insurance-row-amount" class="font-medium text-success">+0.00 lei</span>
                                </div>

                                <!-- Cultural Card Surcharge Row (shown if card cultural selected) -->
                                <div id="cultural-card-row" class="flex justify-between hidden p-2 -mx-2 text-sm rounded-lg bg-purple-50">
                                    <span class="flex items-center gap-1 text-purple-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        Comision card cultural (4%)
                                    </span>
                                    <span id="cultural-card-amount" class="font-medium text-purple-700">+0.00 lei</span>
                                </div>
                            </div>

                            <div class="pt-4 mt-4 border-t border-border">
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-bold text-secondary">Total de plată</span>
                                    <span id="summary-total" class="text-2xl font-bold text-primary">0.00 lei</span>
                                </div>
                                <p id="savings-text" class="flex items-center justify-end hidden gap-1 mt-1 text-sm text-right text-success">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span id="savings-amount">Economisești 0 lei!</span>
                                </p>
                            </div>

                            <!-- Points to Earn -->
                            <div class="flex items-center justify-between p-3 mt-4 bg-surface rounded-xl">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🎁</span>
                                    <span class="text-sm font-medium text-secondary">Vei câștiga:</span>
                                </div>
                                <span id="points-earned" class="font-bold text-accent">0 puncte</span>
                            </div>

                            <button onclick="CheckoutPage.submit()" id="payBtn" class="flex items-center justify-center w-full gap-2 py-4 mt-6 text-lg font-bold text-white btn-primary rounded-xl" disabled>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span id="pay-btn-text">Plătește 0.00 lei</span>
                            </button>

                            <p class="mt-3 text-xs text-center text-muted">
                                Prin plasarea comenzii, confirmi că ai citit și ești de acord cu termenii și condițiile.
                            </p>
                        </div>
                    </div>

                    <!-- Security Badges -->
                    <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                SSL 256-bit
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                PCI DSS
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                3D Secure
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md p-8 mx-4 bg-white shadow-2xl rounded-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-secondary">Conectează-te</h3>
                <button type="button" onclick="CheckoutPage.hideLoginModal()" class="p-2 transition-colors rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <p class="mb-6 text-sm text-muted">Conectează-te pentru a-ți precompletă datele și a finaliza comanda mai rapid.</p>

            <form id="checkout-login-form" onsubmit="return CheckoutPage.handleLogin(event)">
                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="login-email" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" placeholder="email@exemplu.ro" required>
                </div>
                <div class="mb-6">
                    <label class="block mb-2 text-sm font-medium text-secondary">Parola</label>
                    <input type="password" id="login-password" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" placeholder="••••••••" required>
                </div>
                <button type="submit" id="login-submit-btn" class="flex items-center justify-center w-full gap-2 py-3 font-bold text-white btn-primary rounded-xl">
                    <span id="login-btn-text">Conectează-te</span>
                </button>
            </form>

            <div class="flex items-center justify-between mt-4 text-sm">
                <a href="/parola-uitata" target="_blank" class="text-primary hover:underline">Ai uitat parola?</a>
                <a href="/inregistrare" target="_blank" class="text-primary hover:underline">Creează cont</a>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = '<style>
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
    .date-badge { background: linear-gradient(135deg, var(--color-primary, #A51C30) 0%, #8B1728 100%); }
    .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
    .payment-option { transition: all 0.3s ease; }
    .payment-option:hover { border-color: var(--color-primary, #A51C30); }
    .payment-option.selected { border-color: var(--color-primary, #A51C30); background-color: rgba(165, 28, 48, 0.05); }
    .payment-option.selected .payment-radio { border-color: var(--color-primary, #A51C30); background-color: var(--color-primary, #A51C30); }
    .payment-option.selected .payment-radio::after { content: \'\'; position: absolute; inset: 4px; background: white; border-radius: 50%; }
    .payment-radio { position: relative; width: 20px; height: 20px; border: 2px solid #E2E8F0; border-radius: 50%; transition: all 0.2s ease; flex-shrink: 0; }
    .beneficiary-card { transition: all 0.3s ease; }
    .beneficiary-card:hover { border-color: var(--color-primary, #A51C30); }
    .input-field { transition: all 0.2s ease; }
    .input-field:focus { border-color: var(--color-primary, #A51C30); box-shadow: 0 0 0 3px rgba(165, 28, 48, 0.1); }
    .checkbox-custom { appearance: none; width: 20px; height: 20px; border: 2px solid #E2E8F0; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
    .checkbox-custom:checked { background-color: var(--color-primary, #A51C30); border-color: var(--color-primary, #A51C30); background-image: url("data:image/svg+xml,%3Csvg viewBox=\'0 0 16 16\' fill=\'white\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z\'/%3E%3C/svg%3E"); background-size: 100% 100%; }
</style>
<script defer src="' . asset('assets/js/pages/checkout-page.js') . '"></script>';

require_once __DIR__ . '/includes/scripts.php';
?>
