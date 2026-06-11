<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Checkout';
$pageDescription = 'Finalizează comanda și plătește biletele';
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
$scriptsExtra = <<<'SCRIPTS'
<style>
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
    .date-badge { background: linear-gradient(135deg, var(--color-primary, #A51C30) 0%, #8B1728 100%); }
    .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }

    .payment-option { transition: all 0.3s ease; }
    .payment-option:hover { border-color: var(--color-primary, #A51C30); }
    .payment-option.selected { border-color: var(--color-primary, #A51C30); background-color: rgba(165, 28, 48, 0.05); }
    .payment-option.selected .payment-radio { border-color: var(--color-primary, #A51C30); background-color: var(--color-primary, #A51C30); }
    .payment-option.selected .payment-radio::after { content: ''; position: absolute; inset: 4px; background: white; border-radius: 50%; }

    .payment-radio { position: relative; width: 20px; height: 20px; border: 2px solid #E2E8F0; border-radius: 50%; transition: all 0.2s ease; flex-shrink: 0; }

    .beneficiary-card { transition: all 0.3s ease; }
    .beneficiary-card:hover { border-color: var(--color-primary, #A51C30); }

    .input-field { transition: all 0.2s ease; }
    .input-field:focus { border-color: var(--color-primary, #A51C30); box-shadow: 0 0 0 3px rgba(165, 28, 48, 0.1); }

    .checkbox-custom { appearance: none; width: 20px; height: 20px; border: 2px solid #E2E8F0; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
    .checkbox-custom:checked { background-color: var(--color-primary, #A51C30); border-color: var(--color-primary, #A51C30); background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3E%3C/svg%3E"); background-size: 100% 100%; }
</style>

<script>
const CheckoutPage = {
    items: [],
    taxes: [],
    insurance: null,
    insuranceSelected: false,
    culturalCardSurchargeRate: 4, // % extra for cultural card transactions
    totals: { subtotal: 0, tax: 0, discount: 0, insurance: 0, culturalCardSurcharge: 0, total: 0, savings: 0 },
    timerInterval: null,
    endTime: null,

    async init() {
        this.items = AmbiletCart.getItems();
        this.loadTaxes();

        if (this.items.length === 0) {
            document.getElementById('checkout-loading').classList.add('hidden');
            document.getElementById('empty-cart').classList.remove('hidden');
            return;
        }

        // Load checkout features (insurance, etc.)
        await this.loadCheckoutFeatures();

        this.setupTimer();
        this.setupPaymentOptions();
        this.setupTermsCheckbox();
        this.setupInsuranceCheckbox();
        this.prefillBuyerInfo();
        this.renderBeneficiaries();
        this.renderSummary();

        document.getElementById('checkout-loading').classList.add('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('summary-section').classList.remove('hidden');
    },

    async loadCheckoutFeatures() {
        try {
            const response = await AmbiletAPI.get('/checkout.features');
            if (response.success && response.data) {
                // Handle ticket insurance
                if (response.data.ticket_insurance && response.data.ticket_insurance.enabled && response.data.ticket_insurance.show_in_checkout) {
                    this.insurance = response.data.ticket_insurance;

                    // Determine eligible items based on apply_to setting
                    const applyTo = this.insurance.apply_to || 'all';
                    let eligibleItems, ineligibleItems;

                    if (applyTo === 'refundable_only') {
                        // Only refundable tickets qualify
                        eligibleItems = this.items.filter(item => item.ticketType?.is_refundable);
                        ineligibleItems = this.items.filter(item => !item.ticketType?.is_refundable);
                    } else {
                        // All tickets qualify
                        eligibleItems = [...this.items];
                        ineligibleItems = [];
                    }

                    const hasEligible = eligibleItems.length > 0;
                    const isMixed = hasEligible && ineligibleItems.length > 0;

                    // Only show insurance if cart has at least one eligible ticket
                    if (!hasEligible) {
                        this.insurance = null;
                        return;
                    }

                    // Store eligibility info for calculation
                    this.insurance._refundableItems = eligibleItems;
                    this.insurance._isMixed = isMixed;

                    this.setupInsuranceUI();
                }
            }
        } catch (error) {
            console.log('Could not load checkout features:', error);
        }
    },

    setupInsuranceUI() {
        if (!this.insurance) return;

        const section = document.getElementById('insurance-section');
        if (!section) return;

        // Show the insurance section
        section.classList.remove('hidden');

        // Update labels and content
        document.getElementById('insurance-label').textContent = this.insurance.label || 'Taxa de retur';
        document.getElementById('insurance-title').textContent = this.insurance.label || 'Protecție returnare bilete';
        document.getElementById('insurance-description').textContent = this.insurance.description || '';

        // Insurance always applies only to refundable tickets
        const isMixed = this.insurance._isMixed;
        const refundableItems = this.insurance._refundableItems || [];

        // Calculate applicable tickets count (only refundable)
        const applicableTickets = refundableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        const insuranceAmount = this.calculateInsuranceAmount();

        // Show price per ticket info
        if (this.insurance.price_type === 'fixed') {
            const pricePerTicket = this.insurance.price || 0;
            document.getElementById('insurance-price').textContent = AmbiletUtils.formatCurrency(pricePerTicket) + '/bilet';
        } else {
            document.getElementById('insurance-price').textContent = this.insurance.price_percentage + '% din total';
        }

        // Show partial note for mixed carts (some eligible, some not)
        const partialNote = document.getElementById('insurance-partial-note');
        if (partialNote && isMixed) {
            const eligibleNames = refundableItems.map(item => item.ticketType?.name || 'Bilet').join(', ');
            partialNote.textContent = 'Se aplică doar pentru biletele returnabile: ' + eligibleNames;
            partialNote.classList.remove('hidden');
        }

        // Show terms link if available
        if (this.insurance.terms_url) {
            const termsLink = document.getElementById('insurance-terms-link');
            termsLink.href = this.insurance.terms_url;
            termsLink.classList.remove('hidden');
        }

        // Pre-check if configured
        const checkbox = document.getElementById('insuranceCheckbox');
        if (this.insurance.pre_checked) {
            checkbox.checked = true;
            this.insuranceSelected = true;
        }

        // Update row label in summary to show ticket count
        document.getElementById('insurance-row-label').textContent = (this.insurance.label || 'Taxa de retur') + ' (' + applicableTickets + ' bilete)';
    },

    setupInsuranceCheckbox() {
        const checkbox = document.getElementById('insuranceCheckbox');
        if (!checkbox) return;

        checkbox.addEventListener('change', () => {
            this.insuranceSelected = checkbox.checked;

            // Update option styling
            const option = document.getElementById('insurance-option');
            if (option) {
                if (this.insuranceSelected) {
                    option.classList.add('border-success', 'bg-success/5');
                    option.classList.remove('border-border');
                } else {
                    option.classList.remove('border-success', 'bg-success/5');
                    option.classList.add('border-border');
                }
            }

            this.renderSummary();
        });

        // Trigger initial state if pre-checked
        if (this.insuranceSelected) {
            const option = document.getElementById('insurance-option');
            if (option) {
                option.classList.add('border-success', 'bg-success/5');
                option.classList.remove('border-border');
            }
        }
    },

    calculateInsuranceAmount() {
        if (!this.insurance) return 0;

        // Insurance always applies only to refundable tickets
        const applicableItems = this.insurance._refundableItems || [];

        if (applicableItems.length === 0) return 0;

        // Calculate total number of applicable tickets
        const applicableTickets = applicableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        if (this.insurance.price_type === 'percentage') {
            // Calculate based on subtotal of applicable items only
            const subtotal = applicableItems.reduce((sum, item) => {
                const price = item.ticketType?.price || item.price || 0;
                return sum + (price * (item.quantity || 1));
            }, 0);
            return Math.round(subtotal * (this.insurance.price_percentage / 100) * 100) / 100;
        }

        // Fixed price per ticket × number of applicable tickets
        const pricePerTicket = this.insurance.price || 0;
        return Math.round(pricePerTicket * applicableTickets * 100) / 100;
    },

    setupTimer() {
        const savedEndTime = localStorage.getItem('cart_end_time');
        const timerBar = document.getElementById('timer-bar');

        if (savedEndTime && parseInt(savedEndTime) > Date.now()) {
            this.endTime = parseInt(savedEndTime);
            timerBar.classList.remove('hidden');
            this.updateCountdown();
            this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
        } else if (this.items.length > 0) {
            // Start a new timer if cart has items but no saved time
            this.endTime = Date.now() + (15 * 60 * 1000); // 15 minutes
            localStorage.setItem('cart_end_time', this.endTime);
            timerBar.classList.remove('hidden');
            this.updateCountdown();
            this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
        }
    },

    updateCountdown() {
        const remaining = Math.max(0, this.endTime - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        const countdownEl = document.getElementById('countdown');
        if (!countdownEl) return;

        countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (remaining <= 0) {
            clearInterval(this.timerInterval);
            countdownEl.textContent = '00:00';
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
            AmbiletCart.clear();
            localStorage.removeItem('cart_end_time');
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning('Timpul de rezervare a expirat. Biletele au fost eliberate.');
            }
            // Redirect to cart page after short delay
            setTimeout(() => {
                window.location.href = '/cos';
            }, 2000);
        } else if (remaining < 60000) {
            // Less than 1 minute - make it red
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
        }
    },

    setupPaymentOptions() {
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;

                const value = this.querySelector('input').value;
                const cardForm = document.getElementById('cardForm');
                const culturalCardForm = document.getElementById('culturalCardForm');
                cardForm.style.display = value === 'card' ? 'block' : 'none';
                culturalCardForm.style.display = value === 'card_cultural' ? 'block' : 'none';

                // Re-render summary to update cultural card surcharge
                CheckoutPage.renderSummary();
            });
        });
    },

    setupTermsCheckbox() {
        document.getElementById('termsCheckbox').addEventListener('change', function() {
            document.getElementById('payBtn').disabled = !this.checked;
        });
    },

    loadTaxes() {
        // Load ALL taxes from cart items (both included in price and added on top)
        if (this.items.length > 0 && this.items[0].event?.taxes?.length > 0) {
            this.taxes = this.items[0].event.taxes.filter(t => t.is_active !== false);
        } else {
            this.taxes = [];
        }
    },

    prefillBuyerInfo() {
        const user = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getUser() : null;
        const loginBtn = document.getElementById('guest-login-btn');
        const createAccountRow = document.getElementById('create-account-row');

        if (user) {
            // User is logged in - prefill fields
            document.getElementById('buyer-first-name').value = user.first_name || '';
            document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
            document.getElementById('buyer-email').value = user.email || '';
            document.getElementById('buyer-email-confirm').value = user.email || '';
            document.getElementById('buyer-phone').value = user.phone || '';
            // Hide login button and create account checkbox
            if (loginBtn) loginBtn.classList.add('hidden');
            loginBtn?.classList.remove('flex');
            if (createAccountRow) createAccountRow.classList.add('hidden');
        } else {
            // Guest - show login button and create account checkbox
            if (loginBtn) {
                loginBtn.classList.remove('hidden');
                loginBtn.classList.add('flex');
            }
            if (createAccountRow) createAccountRow.classList.remove('hidden');
        }

        // Add email confirmation validation on blur
        const emailConfirm = document.getElementById('buyer-email-confirm');
        emailConfirm.addEventListener('blur', () => this.validateEmailMatch());
        document.getElementById('buyer-email').addEventListener('blur', () => this.validateEmailMatch());
    },

    showLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('login-email').focus();
        }
    },

    hideLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },

    async handleLogin(event) {
        event.preventDefault();

        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const submitBtn = document.getElementById('login-submit-btn');
        const btnText = document.getElementById('login-btn-text');

        // Disable button and show loading
        submitBtn.disabled = true;
        btnText.innerHTML = '<svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Se conectează...';

        try {
            const result = await AmbiletAuth.login(email, password, true);
            if (result.success) {
                AmbiletNotifications.success('Conectare reușită!');
                this.hideLoginModal();

                // Prefill buyer info with new user data
                const user = AmbiletAuth.getUser();
                if (user) {
                    document.getElementById('buyer-first-name').value = user.first_name || '';
                    document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
                    document.getElementById('buyer-email').value = user.email || '';
                    document.getElementById('buyer-email-confirm').value = user.email || '';
                    document.getElementById('buyer-phone').value = user.phone || '';
                }

                // Hide login button and create account checkbox
                const loginBtn = document.getElementById('guest-login-btn');
                if (loginBtn) {
                    loginBtn.classList.add('hidden');
                    loginBtn.classList.remove('flex');
                }
                const createAccountRow = document.getElementById('create-account-row');
                if (createAccountRow) createAccountRow.classList.add('hidden');
            } else {
                AmbiletNotifications.error(result.message || 'Email sau parola incorectă');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la conectare. Încearcă din nou.');
        } finally {
            submitBtn.disabled = false;
            btnText.textContent = 'Conectează-te';
        }

        return false;
    },

    validateEmailMatch() {
        const email = document.getElementById('buyer-email').value.trim();
        const emailConfirm = document.getElementById('buyer-email-confirm').value.trim();
        const errorEl = document.getElementById('email-mismatch-error');
        const confirmInput = document.getElementById('buyer-email-confirm');

        if (emailConfirm && email !== emailConfirm) {
            errorEl.classList.remove('hidden');
            confirmInput.classList.add('border-primary');
            return false;
        } else {
            errorEl.classList.add('hidden');
            confirmInput.classList.remove('border-primary');
            return true;
        }
    },

    renderBeneficiaries() {
        const container = document.getElementById('beneficiariesList');
        let html = '';
        let ticketNum = 0;

        this.items.forEach((item, itemIndex) => {
            const qty = item.quantity || 1;
            for (let i = 0; i < qty; i++) {
                ticketNum++;
                // Handle both AmbiletCart format and legacy format
                const price = item.ticketType?.price || item.price || 0;
                const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
                const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
                const eventTitle = item.event?.title || item.event_title || 'Eveniment';
                const hasDiscount = originalPrice && originalPrice > price;
                const discountPercent = hasDiscount ? Math.round((1 - price / originalPrice) * 100) : 0;

                html += `
                    <div class="p-4 border-2 beneficiary-card border-border rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 font-bold text-white rounded-lg date-badge">${ticketNum}</div>
                                <div>
                                    <p class="font-semibold text-secondary">${ticketTypeName}</p>
                                    <p class="text-xs text-muted">${eventTitle}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Nume beneficiar *</label>
                                <input type="text" placeholder="Nume complet" class="w-full px-4 py-3 border-2 beneficiary-input beneficiary-name input-field border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Email beneficiar *</label>
                                <input type="email" placeholder="email@exemplu.com" class="w-full px-4 py-3 border-2 beneficiary-input beneficiary-email input-field border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        container.innerHTML = html;
        document.getElementById('beneficiaries-count').textContent = `${ticketNum} bilete`;
    },

    toggleBeneficiaries() {
        const checkbox = document.getElementById('differentBeneficiaries');
        const beneficiariesList = document.getElementById('beneficiariesList');
        const allTicketsToEmail = document.getElementById('allTicketsToEmail');

        if (checkbox.checked) {
            // Show beneficiaries form
            beneficiariesList.classList.remove('hidden');
            allTicketsToEmail.classList.add('hidden');
        } else {
            // Hide beneficiaries form - use buyer data for all
            beneficiariesList.classList.add('hidden');
            allTicketsToEmail.classList.remove('hidden');
        }
    },

    renderSummary() {
        // Group items by event
        const eventGroups = {};
        let baseSubtotal = 0;
        let totalCommission = 0;
        let savings = 0;
        let totalQty = 0;
        let hasAddedOnTopCommission = false;

        this.items.forEach(item => {
            const eventId = item.eventId || item.event?.id || 'unknown';
            const eventTitle = item.event?.title || item.event?.name || item.event_title || 'Eveniment';
            const eventImage = item.event?.image || item.event_image || '/assets/images/default-event.png';
            const eventDate = item.event?.date || item.event_date || '';
            const venueName = item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '';

            const cityName = item.event?.city?.name || item.event?.city || item.event?.venue?.city || '';

            if (!eventGroups[eventId]) {
                eventGroups[eventId] = {
                    title: eventTitle,
                    image: eventImage,
                    date: eventDate,
                    venue: venueName,
                    city: cityName,
                    tickets: [],
                    subtotal: 0,
                    commission: 0
                };
            }

            const price = item.ticketType?.price || item.price || 0;
            const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
            const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
            const qty = item.quantity || 1;

            // Calculate per-ticket commission using cart helper
            const commission = AmbiletCart.calculateItemCommission(item);
            let itemCommission = 0;
            if (commission.mode === 'added_on_top') {
                itemCommission = commission.amount;
                hasAddedOnTopCommission = true;
            }

            const itemTotal = price * qty;
            const commissionTotal = itemCommission * qty;

            baseSubtotal += itemTotal;
            totalCommission += commissionTotal;
            totalQty += qty;
            eventGroups[eventId].subtotal += itemTotal;
            eventGroups[eventId].commission += commissionTotal;

            const hasDiscount = originalPrice && originalPrice > price;
            if (hasDiscount) {
                savings += (originalPrice - price) * qty;
            }

            eventGroups[eventId].tickets.push({
                name: ticketTypeName,
                qty: qty,
                price: price,
                lineTotal: itemTotal,
                hasDiscount: hasDiscount,
                originalPrice: originalPrice
            });
        });

        const eventIds = Object.keys(eventGroups);
        const hasMultipleEvents = eventIds.length > 1;

        // Event info - only show for single event
        const eventInfo = document.getElementById('event-info');
        if (hasMultipleEvents) {
            eventInfo.style.display = 'none';
        } else {
            eventInfo.style.display = '';
            const firstGroup = eventGroups[eventIds[0]];
            eventInfo.innerHTML = `
                <img src="${firstGroup.image}" alt="Event" class="object-cover w-20 h-20 rounded-xl" loading="lazy">
                <div>
                    <h3 class="font-bold text-secondary">${firstGroup.title}</h3>
                    <p class="text-sm text-muted">${firstGroup.date ? AmbiletUtils.formatDate(firstGroup.date) : ''}</p>
                    <p class="text-sm text-muted">${firstGroup.venue}</p>
                </div>
            `;
        }

        // Items summary - grouped by event
        const itemsSummary = document.getElementById('items-summary');
        let itemsHtml = '';

        eventIds.forEach((eventId, eventIndex) => {
            const group = eventGroups[eventId];

            // Show event title as header if multiple events
            if (hasMultipleEvents) {
                if (eventIndex > 0) {
                    itemsHtml += '<div class="pt-3 mt-3 border-t border-border"></div>';
                }
                // Build event info string: title (date, venue, city)
                let eventDetails = [];
                if (group.date) eventDetails.push(AmbiletUtils.formatDate(group.date, 'short'));
                if (group.venue) eventDetails.push(group.venue);
                const city = group.city || '';
                if (city && city !== group.venue) eventDetails.push(city);
                const detailsStr = eventDetails.length > 0 ? ` <span class="font-normal text-muted">(${eventDetails.join(', ')})</span>` : '';
                itemsHtml += `<div class="mb-2 text-sm font-bold text-secondary">${group.title}${detailsStr}</div>`;
            }

            // Show tickets for this event
            group.tickets.forEach(ticket => {
                itemsHtml += `
                    <div class="flex justify-between text-sm">
                        <span class="text-muted">${ticket.qty}x ${ticket.name}</span>
                        <div class="text-right">
                            ${ticket.hasDiscount ? `<span class="mr-2 text-xs line-through text-muted">${AmbiletUtils.formatCurrency(ticket.originalPrice * ticket.qty)}</span>` : ''}
                            <span class="font-medium">${AmbiletUtils.formatCurrency(ticket.lineTotal)}</span>
                        </div>
                    </div>
                `;
            });
        });

        itemsSummary.innerHTML = itemsHtml;

        // Calculate insurance if selected
        let insuranceAmount = 0;
        if (this.insuranceSelected && this.insurance) {
            insuranceAmount = this.calculateInsuranceAmount();
        }

        // Promo code discount
        const promoDiscount = AmbiletCart.getPromoDiscount();

        // Total = base prices + commission + insurance - discount
        const subtotalWithCommission = baseSubtotal + totalCommission;
        const baseTotal = Math.max(0, subtotalWithCommission + insuranceAmount - promoDiscount);

        // Cultural card surcharge (applied on entire total including insurance)
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        let culturalCardSurcharge = 0;
        if (paymentMethod === 'card_cultural') {
            culturalCardSurcharge = Math.round(baseTotal * (this.culturalCardSurchargeRate / 100) * 100) / 100;
        }

        const total = baseTotal + culturalCardSurcharge;
        const points = Math.floor(total / 10);

        this.totals = { subtotal: subtotalWithCommission, tax: 0, discount: promoDiscount, insurance: insuranceAmount, culturalCardSurcharge, total, savings };

        // Update DOM
        document.getElementById('summary-items').textContent = totalQty;
        document.getElementById('summary-subtotal').textContent = AmbiletUtils.formatCurrency(subtotalWithCommission);

        // Render commission as "Taxe procesare" in taxes container
        const taxesContainer = document.getElementById('taxes-container');
        if (taxesContainer) {
            if (hasAddedOnTopCommission && totalCommission > 0) {
                taxesContainer.innerHTML = '<div class="flex justify-between text-sm">' +
                    '<span class="text-muted">Taxe procesare</span>' +
                    '<span class="font-medium">' + AmbiletUtils.formatCurrency(totalCommission) + '</span>' +
                '</div>';
            } else {
                taxesContainer.innerHTML = '';
            }
        }

        // Show/hide insurance row
        const insuranceRow = document.getElementById('insurance-row');
        if (insuranceRow) {
            if (this.insuranceSelected && insuranceAmount > 0) {
                insuranceRow.classList.remove('hidden');
                document.getElementById('insurance-row-amount').textContent = '+' + AmbiletUtils.formatCurrency(insuranceAmount);
            } else {
                insuranceRow.classList.add('hidden');
            }
        }

        // Show/hide cultural card surcharge row
        const culturalCardRow = document.getElementById('cultural-card-row');
        if (culturalCardRow) {
            if (culturalCardSurcharge > 0) {
                culturalCardRow.classList.remove('hidden');
                document.getElementById('cultural-card-amount').textContent = '+' + AmbiletUtils.formatCurrency(culturalCardSurcharge);
            } else {
                culturalCardRow.classList.add('hidden');
            }
        }

        // Show/hide discount row (promo code)
        const discountRow = document.getElementById('discount-row');
        if (discountRow) {
            if (promoDiscount > 0) {
                const promo = AmbiletCart.getPromoCode();
                discountRow.classList.remove('hidden');
                document.getElementById('discount-label').textContent = 'Reducere' + (promo ? ' (' + promo.code + ')' : '');
                document.getElementById('discount-amount').textContent = '-' + AmbiletUtils.formatCurrency(promoDiscount);
            } else {
                discountRow.classList.add('hidden');
            }
        }

        document.getElementById('summary-total').textContent = AmbiletUtils.formatCurrency(total);
        document.getElementById('pay-btn-text').textContent = `Plătește ${AmbiletUtils.formatCurrency(total)}`;
        document.getElementById('points-earned').textContent = `${points} puncte`;

        // Savings
        if (savings > 0) {
            document.getElementById('savings-text').classList.remove('hidden');
            document.getElementById('savings-amount').textContent = `Economisești ${AmbiletUtils.formatCurrency(savings)}!`;
        }
    },

    validateForm() {
        const buyerFirstName = document.getElementById('buyer-first-name').value.trim();
        const buyerLastName = document.getElementById('buyer-last-name').value.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();
        const buyerEmailConfirm = document.getElementById('buyer-email-confirm').value.trim();
        const buyerPhone = document.getElementById('buyer-phone').value.trim();

        if (!buyerFirstName || !buyerLastName || !buyerEmail || !buyerEmailConfirm || !buyerPhone) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Completează toate câmpurile obligatorii');
            }
            return false;
        }

        // Validate email match
        if (buyerEmail !== buyerEmailConfirm) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Adresele de email nu coincid');
            }
            document.getElementById('buyer-email-confirm').focus();
            return false;
        }

        if (!document.getElementById('termsCheckbox').checked) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Trebuie să accepți termenii și condițiile');
            }
            return false;
        }

        return true;
    },

    getBeneficiaries() {
        const beneficiaries = [];
        const useDifferentBeneficiaries = document.getElementById('differentBeneficiaries').checked;
        const buyerFirstName = document.getElementById('buyer-first-name').value.trim();
        const buyerLastName = document.getElementById('buyer-last-name').value.trim();
        const buyerName = `${buyerLastName} ${buyerFirstName}`.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();

        // Count total tickets
        let ticketIndex = 0;
        this.items.forEach((item, itemIndex) => {
            const qty = item.quantity || 1;
            for (let i = 0; i < qty; i++) {
                if (useDifferentBeneficiaries) {
                    // Get values from beneficiary form
                    const nameInput = document.querySelector(`.beneficiary-name[data-item="${itemIndex}"][data-index="${i}"]`);
                    const emailInput = document.querySelector(`.beneficiary-email[data-item="${itemIndex}"][data-index="${i}"]`);
                    beneficiaries.push({
                        name: nameInput?.value.trim() || buyerName,
                        email: emailInput?.value.trim() || buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                } else {
                    // Use buyer data for all tickets
                    beneficiaries.push({
                        name: buyerName,
                        email: buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                }
                ticketIndex++;
            }
        });

        return beneficiaries;
    },

    async submit() {
        if (!this.validateForm()) return;

        const payBtn = document.getElementById('payBtn');
        const payBtnText = document.getElementById('pay-btn-text');

        payBtn.disabled = true;
        payBtnText.innerHTML = `
            <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Se procesează...
        `;

        // Build customer data (backend expects 'customer' not 'buyer')
        const customer = {
            first_name: document.getElementById('buyer-first-name').value.trim(),
            last_name: document.getElementById('buyer-last-name').value.trim(),
            email: document.getElementById('buyer-email').value.trim(),
            phone: document.getElementById('buyer-phone').value.trim()
        };

        // Auto-create account if checkbox is checked (guest only)
        const createAccountCheckbox = document.getElementById('createAccountCheckbox');
        if (createAccountCheckbox && createAccountCheckbox.checked) {
            // Generate a random 12-char password
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
            let password = '';
            for (let i = 0; i < 12; i++) password += chars.charAt(Math.floor(Math.random() * chars.length));
            customer.password = password;
        }

        const beneficiaries = this.getBeneficiaries();
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        const newsletter = document.getElementById('newsletterCheckbox').checked;
        const acceptTerms = document.getElementById('termsCheckbox').checked;

        try {
            // Step 1: Create order via checkout
            const checkoutData = {
                customer,
                beneficiaries,
                items: this.items,
                payment_method: paymentMethod,
                newsletter,
                accept_terms: acceptTerms
            };

            // Add promo code if applied
            const promo = AmbiletCart.getPromoCode();
            if (promo && promo.code) {
                checkoutData.promo_code = promo.code;
            }

            // Add ticket insurance if selected
            if (this.insuranceSelected && this.totals.insurance > 0) {
                checkoutData.ticket_insurance = true;
                checkoutData.ticket_insurance_amount = this.totals.insurance;
            }

            // Add cultural card surcharge if applicable
            if (paymentMethod === 'card_cultural' && this.totals.culturalCardSurcharge > 0) {
                checkoutData.cultural_card_surcharge = this.totals.culturalCardSurcharge;
            }

            const response = await AmbiletAPI.post('/checkout', checkoutData);

            if (!response.success) {
                throw new Error(response.message || 'Eroare la procesarea comenzii');
            }

            // Get order from response
            const order = response.data.orders?.[0];
            if (!order) {
                throw new Error('Nu s-a putut crea comanda');
            }

            // Step 2: Check if payment is required
            if (response.data.payment_required && order.total > 0) {
                // Initiate payment
                payBtnText.innerHTML = `
                    <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Se redirecționează către plată...
                `;

                const payResponse = await AmbiletAPI.post(`/orders/${order.id}/pay`, {
                    return_url: window.location.origin + '/multumim?order=' + order.order_number,
                    cancel_url: window.location.origin + '/checkout'
                });

                if (payResponse.success && payResponse.data.payment_url) {
                    AmbiletCart.clear();
                    localStorage.removeItem('cart_end_time');

                    // Check if payment requires POST form submission (e.g., Netopia)
                    if (payResponse.data.method === 'POST' && payResponse.data.form_data) {
                        // Create and submit a form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = payResponse.data.payment_url;

                        for (const [key, value] of Object.entries(payResponse.data.form_data)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        // Standard redirect for other processors
                        window.location.href = payResponse.data.payment_url;
                    }
                } else {
                    throw new Error(payResponse.message || 'Nu s-a putut iniția plata');
                }
            } else {
                // No payment required (free tickets or zero total)
                AmbiletCart.clear();
                localStorage.removeItem('cart_end_time');
                window.location.href = '/multumim?order=' + order.order_number;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error(error.message || 'Eroare la procesare. Încearcă din nou.');
            }
            payBtn.disabled = false;
            payBtnText.textContent = `Plătește ${AmbiletUtils.formatCurrency(this.totals.total)}`;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CheckoutPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
