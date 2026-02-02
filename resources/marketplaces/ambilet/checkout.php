<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Checkout';
$pageDescription = 'Finalizează comanda și plătește biletele';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200 mt-28 mobile:mt-18">
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
                        <h2 class="flex items-center gap-2 mb-4 text-lg font-bold text-secondary">
                            <span class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-lg bg-primary/10 text-primary">1</span>
                            Datele tale
                        </h2>

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
                                <input type="email" id="buyer-email-confirm" autocomplete="off" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" required>
                                <p id="email-mismatch-error" class="hidden mt-1 text-sm text-primary">Adresele de email nu coincid</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-secondary">Telefon *</label>
                                <input type="tel" id="buyer-phone" class="w-full px-4 py-3 border-2 input-field border-border rounded-xl focus:outline-none" required>
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

                        <div class="flex items-start gap-3 p-4 mb-4 bg-surface rounded-xl">
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

                        <!-- Beneficiaries List (hidden by default) -->
                        <div id="beneficiariesList" class="hidden mt-6 space-y-4"></div>
                    </div>

                    <!-- Payment Method -->
                    <div class="p-6 mb-6 bg-white border rounded-2xl border-border">
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

                            <!-- Google Pay -->
                            <label class="flex items-center gap-4 p-4 border-2 cursor-pointer payment-option border-border rounded-xl">
                                <div class="payment-radio"></div>
                                <div class="flex items-center justify-between flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-12 h-8 bg-white border rounded border-border">
                                            <svg class="h-5" viewBox="0 0 24 24" fill="none">
                                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Google Pay</p>
                                            <p class="text-xs text-muted">Plată rapidă și securizată</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="radio" name="payment" value="googlepay" class="hidden">
                            </label>

                            <!-- Apple Pay -->
                            <label class="flex items-center gap-4 p-4 border-2 cursor-pointer payment-option border-border rounded-xl">
                                <div class="payment-radio"></div>
                                <div class="flex items-center justify-between flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-12 h-8 bg-black rounded">
                                            <svg class="h-4" viewBox="0 0 24 24" fill="white">
                                                <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                                            </svg>
                                            <span class="text-white text-[10px] font-semibold ml-0.5">Pay</span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Apple Pay</p>
                                            <p class="text-xs text-muted">Plată rapidă cu Face ID / Touch ID</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="radio" name="payment" value="applepay" class="hidden">
                            </label>
                        </div>

                        <!-- Card Form Info -->
                        <div id="cardForm" class="p-4 mt-6 bg-surface rounded-xl">
                            <p class="mb-4 text-sm text-muted">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului în siguranță.</p>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plățile sunt procesate securizat
                            </div>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
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

                                <!-- Dynamic taxes container -->
                                <div id="taxes-container" class="space-y-2">
                                    <!-- Taxes will be rendered dynamically by JS -->
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
    totals: { subtotal: 0, tax: 0, discount: 0, total: 0, savings: 0 },
    timerInterval: null,
    endTime: null,

    init() {
        this.items = AmbiletCart.getItems();
        this.loadTaxes();

        if (this.items.length === 0) {
            document.getElementById('checkout-loading').classList.add('hidden');
            document.getElementById('empty-cart').classList.remove('hidden');
            return;
        }

        this.setupTimer();
        this.setupPaymentOptions();
        this.setupTermsCheckbox();
        this.prefillBuyerInfo();
        this.renderBeneficiaries();
        this.renderSummary();

        document.getElementById('checkout-loading').classList.add('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('summary-section').classList.remove('hidden');
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

                const cardForm = document.getElementById('cardForm');
                if (this.querySelector('input').value === 'card') {
                    cardForm.style.display = 'block';
                } else {
                    cardForm.style.display = 'none';
                }
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
        if (user) {
            document.getElementById('buyer-first-name').value = user.first_name || '';
            document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
            document.getElementById('buyer-email').value = user.email || '';
            document.getElementById('buyer-email-confirm').value = user.email || '';
            document.getElementById('buyer-phone').value = user.phone || '';
        }

        // Add email confirmation validation on blur
        const emailConfirm = document.getElementById('buyer-email-confirm');
        emailConfirm.addEventListener('blur', () => this.validateEmailMatch());
        document.getElementById('buyer-email').addEventListener('blur', () => this.validateEmailMatch());
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

        if (checkbox.checked) {
            // Show beneficiaries form
            beneficiariesList.classList.remove('hidden');
        } else {
            // Hide beneficiaries form - use buyer data for all
            beneficiariesList.classList.add('hidden');
        }
    },

    renderSummary() {
        // Get first item for event info
        const firstItem = this.items[0];

        // Handle both AmbiletCart format and legacy format
        const eventImage = firstItem.event?.image || firstItem.event_image || '/assets/images/default-event.png';
        const eventTitle = firstItem.event?.title || firstItem.event_title || 'Eveniment';
        const eventDate = firstItem.event?.date || firstItem.event_date || '';
        const venueName = firstItem.event?.venue?.name || (typeof firstItem.event?.venue === 'string' ? firstItem.event.venue : '') || firstItem.venue_name || '';

        // Event info
        const eventInfo = document.getElementById('event-info');
        eventInfo.innerHTML = `
            <img src="${eventImage}" alt="Event" class="object-cover w-20 h-20 rounded-xl" loading="lazy">
            <div>
                <h3 class="font-bold text-secondary">${eventTitle}</h3>
                <p class="text-sm text-muted">${eventDate ? AmbiletUtils.formatDate(eventDate) : ''}</p>
                <p class="text-sm text-muted">${venueName}</p>
            </div>
        `;

        // Items summary
        const itemsSummary = document.getElementById('items-summary');
        let itemsHtml = '';
        let baseSubtotal = 0;  // Subtotal without commission
        let totalCommission = 0;  // Total commission
        let savings = 0;
        let totalQty = 0;

        // Get commission info from first item
        const commissionRate = this.items[0]?.event?.commission_rate || 5;
        const commissionMode = this.items[0]?.event?.commission_mode || 'included';

        this.items.forEach(item => {
            const price = item.ticketType?.price || item.price || 0;
            const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
            const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
            const qty = item.quantity || 1;

            // Calculate commission for this item
            let itemCommission = 0;
            if (commissionMode === 'added_on_top') {
                itemCommission = price * commissionRate / 100;
            }

            const itemTotal = price * qty;
            baseSubtotal += itemTotal;
            totalCommission += itemCommission * qty;
            totalQty += qty;

            const hasDiscount = originalPrice && originalPrice > price;
            if (hasDiscount) {
                savings += (originalPrice - price) * qty;
            }

            itemsHtml += `
                <div class="flex justify-between text-sm">
                    <span class="text-muted">${qty}x ${ticketTypeName}</span>
                    <div class="text-right">
                        ${hasDiscount ? `<span class="mr-2 text-xs line-through text-muted">${AmbiletUtils.formatCurrency(originalPrice * qty)}</span>` : ''}
                        <span class="font-medium">${AmbiletUtils.formatCurrency(itemTotal)}</span>
                    </div>
                </div>
            `;
        });

        itemsSummary.innerHTML = itemsHtml;

        // Total = base prices + commission (no other taxes)
        const subtotalWithCommission = baseSubtotal + totalCommission;
        const total = subtotalWithCommission;
        const points = Math.floor(total / 10);

        this.totals = { subtotal: subtotalWithCommission, tax: 0, discount: 0, total, savings };

        // Update DOM
        document.getElementById('summary-items').textContent = totalQty;
        document.getElementById('summary-subtotal').textContent = AmbiletUtils.formatCurrency(subtotalWithCommission);

        // Render commission as "Taxe procesare" in taxes container
        const taxesContainer = document.getElementById('taxes-container');
        if (taxesContainer) {
            if (commissionMode === 'added_on_top' && totalCommission > 0) {
                taxesContainer.innerHTML = '<div class="flex justify-between text-sm">' +
                    '<span class="text-muted">Taxe procesare (' + commissionRate + '%)</span>' +
                    '<span class="font-medium">' + AmbiletUtils.formatCurrency(totalCommission) + '</span>' +
                '</div>';
            } else {
                taxesContainer.innerHTML = '';
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

        const beneficiaries = this.getBeneficiaries();
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        const newsletter = document.getElementById('newsletterCheckbox').checked;
        const acceptTerms = document.getElementById('termsCheckbox').checked;

        try {
            // Step 1: Create order via checkout
            const response = await AmbiletAPI.post('/checkout', {
                customer,
                beneficiaries,
                items: this.items,
                payment_method: paymentMethod,
                newsletter,
                accept_terms: acceptTerms
            });

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
                    return_url: window.location.origin + '/thank-you?order=' + order.order_number,
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
                window.location.href = '/thank-you?order=' + order.order_number;
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
