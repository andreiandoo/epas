<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Checkout';
$pageDescription = 'Finalizează comanda și plătește biletele';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span class="text-sm font-medium text-green-600">Coș</span>
                </div>
                <div class="w-12 h-px bg-green-500"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">2</div>
                    <span class="text-sm font-semibold text-primary">Checkout</span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-sm font-bold border border-gray-200">3</div>
                    <span class="text-sm text-gray-400">Confirmare</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Column - Checkout Form -->
            <div class="lg:w-2/3">
                <h1 class="text-2xl md:text-3xl font-bold text-secondary mb-6">Finalizare comandă</h1>

                <!-- Loading State -->
                <div id="checkout-loading" class="space-y-6">
                    <div class="skeleton h-48 rounded-2xl"></div>
                    <div class="skeleton h-64 rounded-2xl"></div>
                    <div class="skeleton h-48 rounded-2xl"></div>
                </div>

                <!-- Checkout Form -->
                <div id="checkout-form" class="hidden">
                    <!-- Buyer Information -->
                    <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                        <h2 class="text-lg font-bold text-secondary mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-sm">1</span>
                            Datele tale
                        </h2>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">Nume complet *</label>
                                <input type="text" id="buyer-name" class="input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">Email *</label>
                                <input type="email" id="buyer-email" class="input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">Telefon *</label>
                                <input type="tel" id="buyer-phone" class="input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">CNP (opțional)</label>
                                <input type="text" id="buyer-cnp" placeholder="Pentru facturare" class="input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Beneficiaries Section -->
                    <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-secondary flex items-center gap-2">
                                <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-sm">2</span>
                                Beneficiari bilete
                            </h2>
                            <span id="beneficiaries-count" class="text-sm text-muted">0 bilete</span>
                        </div>

                        <div class="p-4 bg-surface rounded-xl mb-4 flex items-start gap-3">
                            <svg class="w-5 h-5 text-accent flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="text-sm text-muted">
                                <p class="font-medium text-secondary mb-1">Poți adăuga beneficiari diferiți pentru fiecare bilet</p>
                                <p>Fiecare beneficiar va primi biletul pe email. Dacă nu adaugi beneficiari, toate biletele vor fi trimise pe emailul tău.</p>
                            </div>
                        </div>

                        <!-- Same beneficiary toggle -->
                        <label class="flex items-center gap-3 mb-6 cursor-pointer">
                            <input type="checkbox" id="sameBeneficiary" class="checkbox-custom" onchange="CheckoutPage.toggleBeneficiaries()">
                            <span class="text-sm font-medium text-secondary">Folosește datele mele pentru toate biletele</span>
                        </label>

                        <!-- Beneficiaries List -->
                        <div id="beneficiariesList" class="space-y-4"></div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                        <h2 class="text-lg font-bold text-secondary mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-sm">3</span>
                            Metodă de plată
                        </h2>

                        <div class="space-y-3">
                            <!-- Credit Card - Netopia -->
                            <label class="payment-option selected flex items-center gap-4 p-4 border-2 border-border rounded-xl cursor-pointer">
                                <div class="payment-radio"></div>
                                <div class="flex-1 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-8 bg-gradient-to-r from-blue-600 to-blue-800 rounded flex items-center justify-center">
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
                            <label class="payment-option flex items-center gap-4 p-4 border-2 border-border rounded-xl cursor-pointer">
                                <div class="payment-radio"></div>
                                <div class="flex-1 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-8 bg-white border border-border rounded flex items-center justify-center">
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
                            <label class="payment-option flex items-center gap-4 p-4 border-2 border-border rounded-xl cursor-pointer">
                                <div class="payment-radio"></div>
                                <div class="flex-1 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-8 bg-black rounded flex items-center justify-center">
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
                        <div id="cardForm" class="mt-6 p-4 bg-surface rounded-xl">
                            <p class="text-sm text-muted mb-4">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului în siguranță.</p>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plățile sunt procesate securizat
                            </div>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="bg-white rounded-2xl border border-border p-6">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="termsCheckbox" class="checkbox-custom mt-0.5" required>
                            <span class="text-sm text-muted">
                                Am citit și sunt de acord cu <a href="/termeni" class="text-primary hover:underline">Termenii și condițiile</a>,
                                <a href="/confidentialitate" class="text-primary hover:underline">Politica de confidențialitate</a> și
                                <a href="/retur" class="text-primary hover:underline">Politica de returnare</a>.
                            </span>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer mt-4">
                            <input type="checkbox" id="newsletterCheckbox" class="checkbox-custom mt-0.5">
                            <span class="text-sm text-muted">
                                Doresc să primesc newsletter-ul <?= SITE_NAME ?> cu noutăți și oferte speciale.
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Empty Cart State -->
                <div id="empty-cart" class="hidden bg-white rounded-2xl border border-border p-12 text-center">
                    <div class="w-24 h-24 bg-surface rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Coșul tău este gol</h3>
                    <p class="text-muted mb-6">Adaugă bilete în coș pentru a continua.</p>
                    <a href="/" class="btn-primary inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white">
                        Descoperă evenimente
                    </a>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:w-1/3">
                <div id="summary-section" class="hidden sticky top-24">
                    <div class="bg-white rounded-2xl border border-border overflow-hidden">
                        <div class="p-6 border-b border-border">
                            <h2 class="text-xl font-bold text-secondary">Sumar comandă</h2>
                        </div>

                        <div class="p-6">
                            <!-- Event Info -->
                            <div id="event-info" class="flex gap-4 mb-6 pb-6 border-b border-border"></div>

                            <!-- Items Summary -->
                            <div id="items-summary" class="space-y-3 mb-6"></div>

                            <div class="border-t border-border pt-4 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted">Subtotal (<span id="summary-items">0</span> bilete)</span>
                                    <span id="summary-subtotal" class="font-medium">0.00 lei</span>
                                </div>

                                <!-- Discount Row -->
                                <div id="discount-row" class="hidden flex justify-between text-sm p-2 bg-success/5 rounded-lg -mx-2">
                                    <span class="text-success flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                        <span id="discount-label">Reducere</span>
                                    </span>
                                    <span id="discount-amount" class="font-medium text-success">-0.00 lei</span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-muted">Taxa Crucea Roșie (1%)</span>
                                    <span id="summary-tax" class="font-medium">0.00 lei</span>
                                </div>
                            </div>

                            <div class="border-t border-border mt-4 pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-secondary">Total de plată</span>
                                    <span id="summary-total" class="text-2xl font-bold text-primary">0.00 lei</span>
                                </div>
                                <p id="savings-text" class="hidden text-sm text-success mt-1 text-right flex items-center justify-end gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span id="savings-amount">Economisești 0 lei!</span>
                                </p>
                            </div>

                            <!-- Points to Earn -->
                            <div class="mt-4 p-3 bg-surface rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🎁</span>
                                    <span class="text-sm font-medium text-secondary">Vei câștiga:</span>
                                </div>
                                <span id="points-earned" class="font-bold text-accent">0 puncte</span>
                            </div>

                            <button onclick="CheckoutPage.submit()" id="payBtn" class="btn-primary mt-6 w-full py-4 rounded-xl font-bold text-white text-lg flex items-center justify-center gap-2" disabled>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span id="pay-btn-text">Plătește 0.00 lei</span>
                            </button>

                            <p class="text-xs text-muted text-center mt-3">
                                Prin plasarea comenzii, confirmi că ai citit și ești de acord cu termenii și condițiile.
                            </p>
                        </div>
                    </div>

                    <!-- Security Badges -->
                    <div class="mt-4 p-4 bg-white rounded-2xl border border-border">
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
    totals: { subtotal: 0, tax: 0, discount: 0, total: 0, savings: 0 },

    init() {
        this.items = AmbiletCart.getItems();

        if (this.items.length === 0) {
            document.getElementById('checkout-loading').classList.add('hidden');
            document.getElementById('empty-cart').classList.remove('hidden');
            return;
        }

        this.setupPaymentOptions();
        this.setupTermsCheckbox();
        this.prefillBuyerInfo();
        this.renderBeneficiaries();
        this.renderSummary();

        document.getElementById('checkout-loading').classList.add('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('summary-section').classList.remove('hidden');
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

    prefillBuyerInfo() {
        const user = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getUser() : null;
        if (user) {
            document.getElementById('buyer-name').value = user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim();
            document.getElementById('buyer-email').value = user.email || '';
            document.getElementById('buyer-phone').value = user.phone || '';
        }
    },

    renderBeneficiaries() {
        const container = document.getElementById('beneficiariesList');
        let html = '';
        let ticketNum = 0;

        this.items.forEach((item, itemIndex) => {
            for (let i = 0; i < item.quantity; i++) {
                ticketNum++;
                const hasDiscount = item.original_price && item.original_price > item.price;
                const discountPercent = hasDiscount ? Math.round((1 - item.price / item.original_price) * 100) : 0;

                html += `
                    <div class="beneficiary-card border-2 border-border rounded-xl p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="date-badge text-white w-10 h-10 rounded-lg flex items-center justify-center font-bold">${ticketNum}</div>
                                <div>
                                    <p class="font-semibold text-secondary">${item.ticket_type_name || 'Bilet'}</p>
                                    <p class="text-xs text-muted">${item.event_title || 'Eveniment'}</p>
                                </div>
                            </div>
                            ${hasDiscount ? `<span class="px-2 py-1 bg-success/10 text-success text-xs font-semibold rounded">-${discountPercent}%</span>` : ''}
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">Nume beneficiar *</label>
                                <input type="text" placeholder="Nume complet" class="beneficiary-input beneficiary-name input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-secondary mb-2">Email beneficiar *</label>
                                <input type="email" placeholder="email@exemplu.com" class="beneficiary-input beneficiary-email input-field w-full px-4 py-3 border-2 border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
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
        const checkbox = document.getElementById('sameBeneficiary');
        const nameInputs = document.querySelectorAll('.beneficiary-name');
        const emailInputs = document.querySelectorAll('.beneficiary-email');
        const buyerName = document.getElementById('buyer-name').value;
        const buyerEmail = document.getElementById('buyer-email').value;

        if (checkbox.checked) {
            nameInputs.forEach(input => {
                input.disabled = true;
                input.classList.add('bg-surface', 'text-muted');
                input.value = buyerName;
            });
            emailInputs.forEach(input => {
                input.disabled = true;
                input.classList.add('bg-surface', 'text-muted');
                input.value = buyerEmail;
            });
        } else {
            nameInputs.forEach(input => {
                input.disabled = false;
                input.classList.remove('bg-surface', 'text-muted');
                input.value = '';
            });
            emailInputs.forEach(input => {
                input.disabled = false;
                input.classList.remove('bg-surface', 'text-muted');
                input.value = '';
            });
        }
    },

    renderSummary() {
        // Get first item for event info
        const firstItem = this.items[0];

        // Event info
        const eventInfo = document.getElementById('event-info');
        eventInfo.innerHTML = `
            <img src="${firstItem.event_image || '/assets/images/placeholder-event.jpg'}" alt="Event" class="w-20 h-20 rounded-xl object-cover">
            <div>
                <h3 class="font-bold text-secondary">${firstItem.event_title || 'Eveniment'}</h3>
                <p class="text-sm text-muted">${firstItem.event_date ? AmbiletUtils.formatDate(firstItem.event_date) : ''}</p>
                <p class="text-sm text-muted">${firstItem.venue_name || ''}</p>
            </div>
        `;

        // Items summary
        const itemsSummary = document.getElementById('items-summary');
        let itemsHtml = '';
        let subtotal = 0;
        let savings = 0;
        let totalQty = 0;

        this.items.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            totalQty += item.quantity;

            const hasDiscount = item.original_price && item.original_price > item.price;
            if (hasDiscount) {
                savings += (item.original_price - item.price) * item.quantity;
            }

            itemsHtml += `
                <div class="flex justify-between text-sm">
                    <span class="text-muted">${item.quantity}x ${item.ticket_type_name || 'Bilet'}</span>
                    <div class="text-right">
                        ${hasDiscount ? `<span class="text-muted line-through text-xs mr-2">${AmbiletUtils.formatCurrency(item.original_price * item.quantity)}</span>` : ''}
                        <span class="font-medium">${AmbiletUtils.formatCurrency(itemTotal)}</span>
                    </div>
                </div>
            `;
        });

        itemsSummary.innerHTML = itemsHtml;

        // Calculate totals
        const tax = subtotal * 0.01;
        const total = subtotal + tax;
        const points = Math.floor(total / 10);

        this.totals = { subtotal, tax, discount: 0, total, savings };

        // Update DOM
        document.getElementById('summary-items').textContent = totalQty;
        document.getElementById('summary-subtotal').textContent = AmbiletUtils.formatCurrency(subtotal);
        document.getElementById('summary-tax').textContent = AmbiletUtils.formatCurrency(tax);
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
        const buyerName = document.getElementById('buyer-name').value.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();
        const buyerPhone = document.getElementById('buyer-phone').value.trim();

        if (!buyerName || !buyerEmail || !buyerPhone) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Completează toate câmpurile obligatorii');
            }
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
        const sameBeneficiary = document.getElementById('sameBeneficiary').checked;
        const buyerName = document.getElementById('buyer-name').value.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();

        const nameInputs = document.querySelectorAll('.beneficiary-name');
        const emailInputs = document.querySelectorAll('.beneficiary-email');

        nameInputs.forEach((input, idx) => {
            beneficiaries.push({
                name: sameBeneficiary ? buyerName : (input.value.trim() || buyerName),
                email: sameBeneficiary ? buyerEmail : (emailInputs[idx]?.value.trim() || buyerEmail),
                item_index: parseInt(input.dataset.item),
                ticket_index: parseInt(input.dataset.index)
            });
        });

        return beneficiaries;
    },

    async submit() {
        if (!this.validateForm()) return;

        const payBtn = document.getElementById('payBtn');
        const payBtnText = document.getElementById('pay-btn-text');

        payBtn.disabled = true;
        payBtnText.innerHTML = `
            <svg class="w-5 h-5 animate-spin inline mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Se procesează...
        `;

        const buyer = {
            name: document.getElementById('buyer-name').value.trim(),
            email: document.getElementById('buyer-email').value.trim(),
            phone: document.getElementById('buyer-phone').value.trim(),
            cnp: document.getElementById('buyer-cnp').value.trim()
        };

        const beneficiaries = this.getBeneficiaries();
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        const newsletter = document.getElementById('newsletterCheckbox').checked;

        try {
            const response = await AmbiletAPI.post('/checkout', {
                buyer,
                beneficiaries,
                items: this.items,
                payment_method: paymentMethod,
                newsletter
            });

            if (response.success && response.data.payment_url) {
                AmbiletCart.clear();
                localStorage.removeItem('cart_end_time');
                window.location.href = response.data.payment_url;
            } else if (response.success) {
                AmbiletCart.clear();
                localStorage.removeItem('cart_end_time');
                window.location.href = '/thank-you?order=' + response.data.reference;
            } else {
                if (typeof AmbiletNotifications !== 'undefined') {
                    AmbiletNotifications.error(response.message || 'Eroare la procesarea comenzii');
                }
                payBtn.disabled = false;
                payBtnText.textContent = `Plătește ${AmbiletUtils.formatCurrency(this.totals.total)}`;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Eroare la procesare. Încearcă din nou.');
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
