<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Coșul tău';
$pageDescription = 'Finalizează achiziția biletelor tale';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200 mt-28 mobile:mt-20">
        <div class="px-4 py-4 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full bg-primary">1</div>
                    <span class="text-sm font-semibold text-primary">Coș</span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-400 bg-gray-100 border border-gray-200 rounded-full">2</div>
                    <span class="text-sm text-gray-400">Checkout</span>
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
    <div id="timer-bar" class="border-b bg-warning/10 border-warning/20">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-2 text-sm">
                <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-secondary">Biletele sunt rezervate pentru tine încă</span>
                <span id="countdown" class="font-bold countdown text-warning tabular-nums">14:59</span>
                <span class="text-secondary">minute</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl">
        <div class="flex flex-col gap-8 lg:flex-row">
            <!-- Left Column - Cart Items -->
            <div class="lg:w-2/3">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold md:text-3xl text-secondary">Coșul tău</h1>
                    <span class="text-muted"><span id="totalItems">0</span> bilete</span>
                </div>

                <!-- Cart Items Loading -->
                <div id="cart-loading" class="space-y-4">
                    <div class="h-40 skeleton rounded-2xl"></div>
                    <div class="h-40 skeleton rounded-2xl"></div>
                </div>

                <!-- Cart Items Container -->
                <div id="cartItems" class="hidden space-y-4"></div>

                <!-- Empty Cart State -->
                <div id="emptyCart" class="hidden p-12 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-24 h-24 mx-auto mb-6 rounded-full bg-surface">
                        <svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-secondary">Coșul tău este gol</h3>
                    <p class="mb-6 text-muted">Nu ai niciun bilet în coș. Explorează evenimentele noastre!</p>
                    <a href="/evenimente" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Descoperă evenimente
                    </a>
                </div>

                <!-- Promo Code -->
                <div id="promo-section" class="hidden p-5 mt-6 bg-white border rounded-2xl border-border">
                    <h3 class="mb-3 font-semibold text-secondary">Ai un cod promoțional?</h3>
                    <div class="flex gap-3">
                        <input type="text" id="promoCode" placeholder="Introdu codul aici" class="flex-1 px-4 py-3 transition-all border bg-surface border-border rounded-xl text-secondary placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <button onclick="CartPage.applyPromo()" class="px-6 py-3 font-semibold text-white transition-colors bg-secondary rounded-xl hover:bg-secondary/90">
                            Aplică
                        </button>
                    </div>
                    <p id="promoMessage" class="hidden mt-2 text-sm"></p>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:w-1/3">
                <div id="summary-section" class="hidden sticky top-[140px]">
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-6 border-b border-border">
                            <h2 class="text-xl font-bold text-secondary">Sumar comandă</h2>
                        </div>

                        <div class="p-6">
                            <!-- Order Lines -->
                            <div class="mb-6 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted">Subtotal (<span id="summaryItems">0</span> bilete)</span>
                                    <span id="subtotal" class="font-medium">0.00 lei</span>
                                </div>
                                <!-- Dynamic taxes container -->
                                <div id="taxesContainer" class="space-y-2">
                                    <!-- Taxes will be rendered dynamically by JS -->
                                </div>
                                <div id="discountRow" class="flex justify-between hidden text-sm">
                                    <span class="text-success">Reducere aplicată</span>
                                    <span id="discountAmount" class="font-medium text-success">-0.00 lei</span>
                                </div>
                            </div>

                            <!-- Savings -->
                            <div id="savingsRow" class="hidden p-3 mb-6 bg-success/10 rounded-xl">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg text-success">🎉</span>
                                        <span id="savingsText" class="text-sm font-medium text-success">Alegând [numebilet] economisești:</span>
                                    </div>
                                    <span id="savings" class="font-bold text-success">0.00 lei</span>
                                </div>
                            </div>

                            <!-- Total -->
                            <div class="flex items-center justify-between py-4 border-t border-border">
                                <span class="text-lg font-bold text-secondary">Total de plată</span>
                                <span id="totalPrice" class="text-2xl font-bold text-primary">0.00 lei</span>
                            </div>

                            <!-- Points Earned -->
                            <div class="p-4 mt-4 border bg-gradient-to-r from-accent/10 to-accent/5 rounded-xl border-accent/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-accent/20">
                                            <span class="text-xl">🎁</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-secondary">Vei câștiga</p>
                                            <p class="text-xs text-muted">1 punct / 10 lei cheltuiți</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span id="pointsEarned" class="text-2xl font-bold text-accent points-animation">0</span>
                                        <p class="text-xs text-muted">puncte</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkout Button -->
                            <a href="/finalizare" id="checkoutBtn" class="flex items-center justify-center w-full gap-2 py-4 mt-6 text-lg font-bold text-white btn-primary rounded-xl">
                                Continuă spre plată
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>

                            <!-- Trust Badges -->
                            <div class="pt-6 mt-6 border-t border-border">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Plată securizată
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Bilete instant
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        Card sau transfer
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Garanție 100%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                        <p class="mb-3 text-xs text-center text-muted">Metode de plată acceptate</p>
                        <div class="flex items-center justify-center gap-4">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/200px-Visa_Inc._logo.svg.png" alt="Visa" class="h-6 opacity-60">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/200px-Mastercard-logo.svg.png" alt="Mastercard" class="h-6 opacity-60">
                            <div class="px-2 py-1 text-xs font-semibold rounded bg-surface text-muted">Apple Pay</div>
                            <div class="px-2 py-1 text-xs font-semibold rounded bg-surface text-muted">Google Pay</div>
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
    .tooltip {
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        transform: translateY(5px);
    }
    .tooltip-trigger:hover .tooltip {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
    .points-animation { animation: pointsPulse 0.4s ease; }
    @keyframes pointsPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
    .remove-btn:hover { background-color: #FEE2E2; color: #DC2626; }
    .cart-item { transition: all 0.3s ease; }
    .cart-item:hover { border-color: var(--color-primary, #A51C30); }
</style>

<script>
const CartPage = {
    timerInterval: null,
    endTime: null,
    appliedPromo: null,
    discount: 0,
    taxes: [], // Dynamic taxes from API/config

    async init() {
        await this.loadTaxes();
        this.setupTimer();
        this.render();
    },

    /**
     * Load taxes from cart items or use defaults from config
     */
    async loadTaxes() {
        // First, try to get taxes from cart items (stored when adding to cart)
        const items = AmbiletCart.getItems();
        if (items.length > 0 && items[0].event?.taxes?.length > 0) {
            // Show ALL taxes (both included in price and added on top)
            this.taxes = items[0].event.taxes.filter(t => t.is_active !== false);
            return;
        }

        try {
            // Try to load taxes from API
            if (typeof AmbiletAPI !== 'undefined') {
                const response = await AmbiletAPI.get('/config/taxes');
                if (response.success && response.data?.taxes) {
                    this.taxes = response.data.taxes;
                    return;
                }
            }
        } catch (e) {
            console.log('Using default taxes from config');
        }

        // Fallback - no hardcoded taxes, they come from DB via cart items
        this.taxes = [];
    },

    setupTimer() {
        const savedEndTime = localStorage.getItem('cart_end_time');
        const items = AmbiletCart.getItems();

        if (items.length === 0) {
            localStorage.removeItem('cart_end_time');
            document.getElementById('timer-bar').classList.add('hidden');
            return;
        }

        if (savedEndTime && parseInt(savedEndTime) > Date.now()) {
            this.endTime = parseInt(savedEndTime);
        } else {
            this.endTime = Date.now() + (15 * 60 * 1000); // 15 minutes
            localStorage.setItem('cart_end_time', this.endTime);
        }

        this.updateCountdown();
        this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
    },

    updateCountdown() {
        const remaining = Math.max(0, this.endTime - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        const countdownEl = document.getElementById('countdown');
        countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (remaining <= 0) {
            clearInterval(this.timerInterval);
            countdownEl.textContent = '00:00';
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
            AmbiletCart.clear();
            localStorage.removeItem('cart_end_time');
            this.render();
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning('Timpul de rezervare a expirat. Biletele au fost eliberate.');
            }
        } else if (remaining < 60000) {
            // Less than 1 minute - make it red
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
        }
    },

    render() {
        const items = AmbiletCart.getItems();
        const loading = document.getElementById('cart-loading');
        const container = document.getElementById('cartItems');
        const emptyState = document.getElementById('emptyCart');
        const summarySection = document.getElementById('summary-section');
        const promoSection = document.getElementById('promo-section');
        const timerBar = document.getElementById('timer-bar');

        loading.classList.add('hidden');

        if (items.length === 0) {
            container.classList.add('hidden');
            summarySection.classList.add('hidden');
            promoSection.classList.add('hidden');
            timerBar.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.classList.remove('hidden');
        summarySection.classList.remove('hidden');
        promoSection.classList.remove('hidden');
        timerBar.classList.remove('hidden');

        container.innerHTML = items.map((item, index) => this.renderCartItem(item, index)).join('');
        this.updateSummary();
    },

    renderCartItem(item, index) {
        // Handle both AmbiletCart format and legacy format
        const itemKey = item.key || index;
        const eventImage = item.event?.image || item.event_image || (typeof AMBILET_CONFIG !== 'undefined' ? AMBILET_CONFIG.PLACEHOLDER_EVENT : '/assets/images/placeholder-event.jpg');
        const eventTitle = item.event?.title || item.event_title || 'Eveniment';
        const eventDate = item.event?.date || item.event_date || '';
        const venueName = item.event?.venue || item.venue_name || '';
        const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
        const price = item.ticketType?.price || item.price || 0;
        const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
        const quantity = item.quantity || 1;

        const hasDiscount = originalPrice && originalPrice > price;
        const formattedDate = eventDate ? AmbiletUtils.formatDate(eventDate, 'medium') : '';

        return '<div class="bg-white border-2 cart-item rounded-2xl border-border" data-item-key="' + itemKey + '" data-index="' + index + '">' +
            '<div class="flex gap-4 p-6">' +
                '<div class="w-24 h-24 overflow-hidden rounded-xl shrink-0">' +
                    '<img src="' + eventImage + '" alt="' + eventTitle + '" class="object-cover w-full h-full">' +
                '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<h3 class="font-semibold truncate text-secondary">' + eventTitle + '</h3>' +
                    '<p class="text-sm text-muted">' +
                        formattedDate +
                        (venueName ? ' • ' + venueName : '') +
                    '</p>' +
                    '<div class="mt-2">' +
                        '<span class="inline-flex items-center px-2 py-1 text-sm font-medium rounded bg-surface">' +
                            ticketTypeName +
                        '</span>' +
                    '</div>' +
                    '<div class="flex items-center justify-between mt-3">' +
                        '<div class="flex items-center gap-2">' +
                            '<button onclick="CartPage.updateQuantity(' + index + ', -1)" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>' +
                                '</svg>' +
                            '</button>' +
                            '<span class="w-8 font-semibold text-center">' + quantity + '</span>' +
                            '<button onclick="CartPage.updateQuantity(' + index + ', 1)" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>' +
                                '</svg>' +
                            '</button>' +
                        '</div>' +
                        '<div class="text-right">' +
                            '<div class="font-bold text-primary">' + AmbiletUtils.formatCurrency(price * quantity) + '</div>' +
                            (hasDiscount ? '<div class="text-sm line-through text-muted">' + AmbiletUtils.formatCurrency(originalPrice * quantity) + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<button onclick="CartPage.removeItem(' + index + ')" class="self-start p-2 transition-colors rounded-lg text-muted hover:text-error hover:bg-red-50">' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                    '</svg>' +
                '</button>' +
            '</div>' +
        '</div>';
    },

    updateQuantity(index, delta) {
        const items = AmbiletCart.getItems();
        if (!items[index]) return;

        const newQty = items[index].quantity + delta;
        const maxQty = items[index].max_quantity || 10;

        if (newQty >= 1 && newQty <= maxQty) {
            items[index].quantity = newQty;
            AmbiletCart.save(items);
            this.render();
        } else if (newQty < 1) {
            this.removeItem(index);
        } else if (newQty > maxQty) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning(`Poți cumpăra maximum ${maxQty} bilete de acest tip`);
            }
        }
    },

    removeItem(index) {
        const items = AmbiletCart.getItems();
        const itemEl = document.querySelector(`[data-index="${index}"]`) || document.querySelector(`.cart-item:nth-child(${index + 1})`);

        if (itemEl) {
            itemEl.style.opacity = '0';
            itemEl.style.transform = 'translateX(-20px)';
            itemEl.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                items.splice(index, 1);
                AmbiletCart.save(items);
                this.render();

                if (items.length === 0) {
                    localStorage.removeItem('cart_end_time');
                    if (this.timerInterval) {
                        clearInterval(this.timerInterval);
                    }
                }
            }, 300);
        }
    },

    updateSummary() {
        const items = AmbiletCart.getItems();
        let subtotal = 0;
        let totalItems = 0;
        let savings = 0;
        const savingsTickets = []; // Track which tickets have discounts

        items.forEach(item => {
            // Handle both AmbiletCart format and legacy format
            const price = item.ticketType?.price || item.price || 0;
            const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
            const ticketName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
            const quantity = item.quantity || 1;

            subtotal += price * quantity;
            totalItems += quantity;

            // Calculate savings for discounted items
            if (originalPrice && originalPrice > price) {
                const itemSavings = (originalPrice - price) * quantity;
                savings += itemSavings;
                savingsTickets.push(ticketName);
            }
        });

        // Calculate taxes dynamically
        let totalTaxes = 0;
        const taxBreakdown = [];

        this.taxes.forEach(tax => {
            if (!tax.is_active) return;
            let taxAmount = 0;
            if (tax.value_type === 'percent') {
                taxAmount = subtotal * (tax.value / 100);
            } else if (tax.value_type === 'fixed') {
                taxAmount = tax.value * totalItems;
            }
            totalTaxes += taxAmount;
            taxBreakdown.push({ name: tax.name, amount: taxAmount, value: tax.value, value_type: tax.value_type });
        });

        let total = subtotal + totalTaxes - this.discount;
        const points = Math.floor(total / 10);

        // Update DOM
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('summaryItems').textContent = totalItems;
        document.getElementById('subtotal').textContent = AmbiletUtils.formatCurrency(subtotal);

        // Render taxes dynamically
        const taxesContainer = document.getElementById('taxesContainer');
        if (taxesContainer) {
            if (taxBreakdown.length > 0) {
                taxesContainer.innerHTML = taxBreakdown.map(function(tax) {
                    const rateLabel = tax.value_type === 'percent' ? '(' + tax.value + '%)' : '';
                    return '<div class="flex justify-between text-sm">' +
                        '<div class="flex items-center gap-1">' +
                            '<span class="text-muted">' + tax.name + ' ' + rateLabel + '</span>' +
                            '<div class="relative tooltip-trigger">' +
                                '<svg class="w-4 h-4 text-muted cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                                '<div class="absolute right-0 z-10 w-56 p-3 mt-2 text-xs text-white shadow-xl tooltip top-full bg-secondary rounded-xl">' +
                                    'Această taxă este obligatorie conform legislației în vigoare și se adaugă la prețul biletelor.' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<span class="font-medium">' + AmbiletUtils.formatCurrency(tax.amount) + '</span>' +
                    '</div>';
                }).join('');
            } else {
                taxesContainer.innerHTML = '';
            }
        }

        document.getElementById('totalPrice').textContent = AmbiletUtils.formatCurrency(total);

        // Discount row
        if (this.discount > 0) {
            document.getElementById('discountRow').classList.remove('hidden');
            document.getElementById('discountAmount').textContent = `-${AmbiletUtils.formatCurrency(this.discount)}`;
        } else {
            document.getElementById('discountRow').classList.add('hidden');
        }

        // Savings row with ticket name
        if (savings > 0) {
            document.getElementById('savingsRow').classList.remove('hidden');
            document.getElementById('savings').textContent = AmbiletUtils.formatCurrency(savings);

            // Update the savings text to include ticket name(s)
            const savingsTextEl = document.getElementById('savingsText');
            if (savingsTextEl && savingsTickets.length > 0) {
                const ticketNames = [...new Set(savingsTickets)].join(', ');
                savingsTextEl.textContent = `Alegând ${ticketNames} economisești:`;
            }
        } else {
            document.getElementById('savingsRow').classList.add('hidden');
        }

        // Points animation
        const pointsEl = document.getElementById('pointsEarned');
        pointsEl.textContent = points;
        pointsEl.classList.remove('points-animation');
        void pointsEl.offsetWidth; // Force reflow
        pointsEl.classList.add('points-animation');
    },

    async applyPromo() {
        const code = document.getElementById('promoCode').value.trim().toUpperCase();
        const messageEl = document.getElementById('promoMessage');

        if (!code) {
            messageEl.textContent = 'Te rugăm să introduci un cod promoțional';
            messageEl.className = 'mt-2 text-sm text-muted';
            messageEl.classList.remove('hidden');
            return;
        }

        // TODO: Validate promo code via API
        // For now, demo codes
        const validCodes = {
            'ROCK2024': 0.10,
            'WELCOME10': 0.10,
            'VIP20': 0.20
        };

        if (validCodes[code]) {
            const items = AmbiletCart.getItems();
            const subtotal = items.reduce((sum, item) => {
                const price = item.ticketType?.price || item.price || 0;
                const qty = item.quantity || 1;
                return sum + (price * qty);
            }, 0);
            this.discount = subtotal * validCodes[code];
            this.appliedPromo = code;

            messageEl.innerHTML = `✓ Cod aplicat! -${Math.round(validCodes[code] * 100)}% reducere`;
            messageEl.className = 'mt-2 text-sm text-success';
            messageEl.classList.remove('hidden');

            document.getElementById('promoCode').disabled = true;
            document.querySelector('#promo-section button').textContent = 'Aplicat';
            document.querySelector('#promo-section button').disabled = true;

            this.updateSummary();
        } else {
            messageEl.textContent = '✗ Cod invalid sau expirat';
            messageEl.className = 'mt-2 text-sm text-primary';
            messageEl.classList.remove('hidden');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CartPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
