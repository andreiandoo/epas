<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Coșul tău';
$pageDescription = 'Finalizează achiziția biletelor tale';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200 mt-18 mobile:mt-18">
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
                <div id="cartPageItems" class="hidden space-y-4"></div>

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
                        <div class="p-3 border-b border-border">
                            <h2 class="text-xl font-bold text-secondary">Sumar comandă</h2>
                        </div>

                        <div class="p-3">
                            <!-- Order Lines -->
                            <div class="mb-3 space-y-3">
                                <!-- Dynamic taxes container (ticket breakdown) -->
                                <div id="taxesContainer" class="space-y-2">
                                    <!-- Taxes will be rendered dynamically by JS -->
                                </div>
                                <div id="discountRow" class="flex justify-between hidden text-sm">
                                    <span class="text-success">Reducere aplicată</span>
                                    <span id="discountAmount" class="font-medium text-success">-0.00 lei</span>
                                </div>
                                <!-- Subtotal - last before total -->
                                <div class="flex justify-between pt-2 text-sm border-t border-border">
                                    <span class="text-muted">Subtotal (<span id="summaryItems">0</span> bilete)</span>
                                    <span id="subtotal" class="font-medium">0.00 lei</span>
                                </div>
                            </div>

                            <!-- Savings -->
                            <div id="savingsRow" class="hidden px-3 py-2 mb-3 bg-success/10 rounded-xl">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span id="savingsText" class="text-sm font-medium text-success">Alegând [numebilet] economisești:</span>
                                        <span id="savings" class="flex-none font-bold text-success">0.00 lei</span>
                                    </div>

                                </div>
                            </div>

                            <!-- Total -->
                            <div class="flex items-center justify-between py-4 border-t border-border">
                                <span class="text-lg font-bold text-secondary">Total de plată</span>
                                <span id="totalPrice" class="text-2xl font-bold text-primary">0.00 lei</span>
                            </div>

                            <!-- Points Earned -->
                            <div class="p-4 mt-2 border bg-gradient-to-r from-accent/10 to-accent/5 rounded-xl border-accent/20">
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
                            <div class="pt-6 mt-3 border-t border-border">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Plată securizată
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Bilete instant
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        Card sau transfer
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
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
                            <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Visa_Inc._logo_%282021%E2%80%93present%29.svg" alt="Visa" class="h-6 opacity-60">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/200px-Mastercard-logo.svg.png" alt="Mastercard" class="h-6 opacity-60">
                            <div class="px-2 py-1 text-xs font-semibold rounded bg-surface text-muted">Apple Pay</div>
                            <div class="px-2 py-1 text-xs font-semibold rounded bg-surface text-muted">Google Pay</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/featured-carousel.php'; ?>

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
        this.loadExistingPromo();
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

    warningShown: false,  // Track if 5-minute warning was shown

    updateCountdown() {
        const remaining = Math.max(0, this.endTime - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        const countdownEl = document.getElementById('countdown');
        const timerBar = document.getElementById('timer-bar');
        countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (remaining <= 0) {
            clearInterval(this.timerInterval);
            countdownEl.textContent = '00:00';
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');

            // Release held seats via API before clearing cart
            this.releaseAllSeats().then(() => {
                AmbiletCart.clear();
                localStorage.removeItem('cart_end_time');
                this.render();
                if (typeof AmbiletNotifications !== 'undefined') {
                    AmbiletNotifications.warning('Timpul de rezervare a expirat. Locurile au fost eliberate.');
                }
            });
        } else if (remaining < 60000) {
            // Less than 1 minute - make it red/urgent
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
            if (timerBar) {
                timerBar.classList.remove('bg-warning/10', 'border-warning/20');
                timerBar.classList.add('bg-red-50', 'border-red-200');
            }
        } else if (remaining <= 5 * 60 * 1000 && !this.warningShown) {
            // 5 minutes remaining - show warning notification
            this.warningShown = true;
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-orange-500');
            if (timerBar) {
                timerBar.classList.add('animate-pulse');
            }
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning('Mai ai doar 5 minute pentru a finaliza comanda! După expirare, locurile vor fi eliberate.');
            }
        }
    },

    /**
     * Release all held seats via API
     */
    async releaseAllSeats() {
        const items = AmbiletCart.getItems();

        for (const item of items) {
            // Check if this item has held seats
            if (item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
                try {
                    console.log('[CartPage] Releasing seats for item:', item.key, item.seat_uids);
                    await AmbiletAPI.delete('/cart/seats', {
                        event_seating_id: item.event_seating_id,
                        seat_uids: item.seat_uids
                    });
                    console.log('[CartPage] Seats released successfully');
                } catch (error) {
                    console.error('[CartPage] Failed to release seats:', error);
                    // Continue even if release fails - cleanup job will handle it
                }
            }
        }
    },

    render() {
        const items = AmbiletCart.getItems();
        console.log('[CartPage] Cart items:', items);
        console.log('[CartPage] First item:', items[0]);
        console.log('[CartPage] Taxes from cart:', items[0]?.event?.taxes);
        console.log('[CartPage] CartPage.taxes:', this.taxes);

        const loading = document.getElementById('cart-loading');
        const container = document.getElementById('cartPageItems');
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

        try {
            console.log('[CartPage] About to render items, count:', items.length);
            console.log('[CartPage] Container element:', container);
            console.log('[CartPage] Container classList before:', container.className);

            const html = items.map((item, index) => {
                console.log('[CartPage] Rendering item', index, ':', item);
                return this.renderCartItem(item, index);
            }).join('');
            console.log('[CartPage] Generated HTML length:', html.length);
            console.log('[CartPage] First 500 chars of HTML:', html.substring(0, 500));
            container.innerHTML = html;
            console.log('[CartPage] Items rendered to container');
            console.log('[CartPage] Container classList after:', container.className);
            console.log('[CartPage] Container children count:', container.children.length);
            console.log('[CartPage] Container display style:', window.getComputedStyle(container).display);
            this.updateSummary();
        } catch (error) {
            console.error('[CartPage] Error rendering items:', error);
        }
    },

    renderCartItem(item, index) {
        // Handle both AmbiletCart format and legacy format
        const itemKey = item.key || index;
        const eventImage = getStorageUrl(item.event?.image || item.event_image);
        const eventTitle = item.event?.title || item.event_title || 'Eveniment';
        const eventDate = item.event?.date || item.event_date || '';
        const venueName = item.event?.venue?.name || item.event?.venue || item.venue_name || '';
        const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
        const ticketDescription = item.ticketType?.description || '';
        const price = item.ticketType?.price || item.price || 0;
        const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
        const quantity = item.quantity || 1;
        const seats = item.seats || [];
        const hasSeats = seats.length > 0 || (item.seat_uids && item.seat_uids.length > 0);
        const eventSlug = item.event?.slug || '';

        // Get per-ticket commission or fall back to event-level
        const commission = AmbiletCart.calculateItemCommission(item);
        const commissionMode = commission.mode || 'included';

        const hasDiscount = originalPrice && originalPrice > price;
        const discountPercent = hasDiscount ? Math.round((1 - price / originalPrice) * 100) : 0;
        const formattedDate = eventDate ? AmbiletUtils.formatDate(eventDate, 'medium') : '';

        // Calculate commission - price is always base price
        let commissionAmount = 0;
        if (commissionMode === 'added_on_top') {
            commissionAmount = commission.amount;
        }
        const totalWithCommission = price + commissionAmount;

        // Build tooltip HTML with price breakdown
        let tooltipHtml = '<p class="pb-2 mb-3 text-sm font-semibold border-b border-white/20">Detalii preț bilet ' + ticketTypeName + '</p>' +
            '<div class="space-y-2 text-xs">' +
                '<div class="flex justify-between"><span class="text-white/70">Preț bilet:</span><span>' + price.toFixed(2) + ' lei</span></div>';

        if (commissionMode === 'added_on_top' && commissionAmount > 0) {
            // Build commission description based on type
            let commissionLabel = 'Taxe procesare';
            if (commission.type === 'percentage') {
                commissionLabel += ' (' + commission.rate + '%)';
            } else if (commission.type === 'fixed') {
                commissionLabel += ' (fix)';
            } else if (commission.type === 'both') {
                commissionLabel += ' (' + commission.rate + '% + ' + commission.fixed.toFixed(2) + ' lei)';
            }
            tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">' + commissionLabel + ':</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                '<div class="flex justify-between pt-2 mt-2 border-t border-white/20"><span class="font-semibold">Total la plată:</span><span class="font-semibold">' + totalWithCommission.toFixed(2) + ' lei</span></div>';
        }

        tooltipHtml += '</div>';

        return '<div class="bg-white border-2 cart-item rounded-2xl border-border" data-item-key="' + itemKey + '" data-index="' + index + '">' +
            '<div class="flex gap-4 p-3">' +
                '<div class="w-24 h-24 overflow-hidden rounded-xl shrink-0">' +
                    '<img src="' + eventImage + '" alt="' + eventTitle + '" class="object-cover w-full h-full" loading="lazy">' +
                '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="flex items-center justify-between">' +
                        '<div class="flex-1 min-w-0">' +
                            '<h3 class="font-semibold truncate text-secondary">' + eventTitle + '</h3>' +
                            '<p class="text-sm text-muted">' +
                                formattedDate +
                                (venueName ? ' • ' + venueName : '') +
                            '</p>' +
                        '</div>' +
                        '<button onclick="CartPage.removeItem(' + index + ')" class="self-start p-2 transition-colors rounded-lg text-muted hover:text-error hover:bg-red-50">' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                    '<div class="flex items-center justify-between mt-3">' +
                        '<div class="relative inline-block tooltip-trigger">' +
                            '<div class="flex items-center gap-2">' +
                                '<span class="inline-flex items-center px-2 py-1 text-sm font-semibold text-secondary">' + ticketTypeName +
                                    (hasDiscount ? ' <span class="discount-badge text-white text-[10px] font-bold py-0.5 px-1.5 rounded-full ml-1">-' + discountPercent + '%</span>' : '') +
                                '</span>' +
                                '<svg class="w-4 h-4 text-muted cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                            '</div>' +
                            (ticketDescription ? '<p class="text-xs text-muted mt-0.5">' + ticketDescription + '</p>' : '') +
                            (seats.length > 0 ? '<p class="mt-1 text-xs text-primary"><svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' + this.formatSeats(seats) + '</p>' : '') +
                            '<div class="absolute left-0 z-10 p-4 mt-2 text-white shadow-xl tooltip top-full w-72 bg-secondary rounded-xl">' + tooltipHtml + '</div>' +
                        '</div>' +
                        (hasSeats ?
                        '<div class="flex items-center gap-2 ml-auto mr-8">' +
                            '<span class="w-8 font-semibold text-center">' + quantity + '</span>' +
                            '<a href="/bilete/' + eventSlug + '" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-primary border border-primary/30 rounded-lg hover:bg-primary/5 transition-colors">' +
                                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                                'Adaugă locuri' +
                            '</a>' +
                        '</div>' :
                        '<div class="flex items-center gap-2 ml-auto mr-8">' +
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
                        '</div>') +
                        '<div class="text-right">' +
                            (hasDiscount ? '<div class="text-sm line-through text-muted">' + AmbiletUtils.formatCurrency(originalPrice * quantity) + '</div>' : '') +
                            '<div class="font-bold text-primary">' + AmbiletUtils.formatCurrency(price * quantity) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    },

    updateQuantity(index, delta) {
        const items = AmbiletCart.getItems();
        if (!items[index]) return;

        const currentQty = items[index].quantity;
        let newQty = currentQty + delta;
        const minQty = items[index].ticketType?.min_per_order || items[index].min_per_order || 1;
        const maxQty = items[index].ticketType?.max_per_order || items[index].max_per_order || items[index].max_quantity || 10;

        if (delta > 0 && newQty > maxQty) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning(`Poți cumpăra maximum ${maxQty} bilete de acest tip`);
            }
            return;
        }

        if (delta < 0 && newQty < minQty && newQty > 0) {
            // Going below min - remove entirely
            this.removeItem(index);
            return;
        }

        if (newQty < 1) {
            this.removeItem(index);
        } else {
            items[index].quantity = newQty;
            AmbiletCart.save(items);
            this.render();
        }
    },

    async removeItem(index) {
        const items = AmbiletCart.getItems();
        const item = items[index];
        const itemEl = document.querySelector(`[data-index="${index}"]`) || document.querySelector(`.cart-item:nth-child(${index + 1})`);

        // Release seats if this item has them
        if (item && item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
            try {
                console.log('[CartPage] Releasing seats for removed item:', item.seat_uids);
                await AmbiletAPI.delete('/cart/seats', {
                    event_seating_id: item.event_seating_id,
                    seat_uids: item.seat_uids
                });
                console.log('[CartPage] Seats released successfully');
            } catch (error) {
                console.error('[CartPage] Failed to release seats:', error);
                // Continue with removal even if API fails
            }
        }

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
        let baseSubtotal = 0;  // Subtotal without commission
        let totalCommission = 0;  // Total commission
        let totalItems = 0;
        let savings = 0;
        const savingsTickets = [];
        let hasAddedOnTopCommission = false;

        // Group items by event
        const eventGroups = {};
        items.forEach(item => {
            const eventId = item.eventId || item.event?.id || 'unknown';
            const eventTitle = item.event?.title || item.event?.name || 'Eveniment';
            const eventDate = item.event?.date || item.event_date || '';
            const venueName = item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '';
            const cityName = item.event?.city?.name || item.event?.city || item.event?.venue?.city || '';

            if (!eventGroups[eventId]) {
                eventGroups[eventId] = {
                    title: eventTitle,
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
            const ticketName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
            const quantity = item.quantity || 1;

            // Calculate per-ticket commission using cart helper
            const commission = AmbiletCart.calculateItemCommission(item);
            let itemCommission = 0;
            if (commission.mode === 'added_on_top') {
                itemCommission = commission.amount;
                hasAddedOnTopCommission = true;
            }

            const lineTotal = price * quantity;
            const commissionTotal = itemCommission * quantity;

            baseSubtotal += lineTotal;
            totalCommission += commissionTotal;
            totalItems += quantity;
            eventGroups[eventId].subtotal += lineTotal;
            eventGroups[eventId].commission += commissionTotal;

            eventGroups[eventId].tickets.push({
                name: ticketName,
                qty: quantity,
                basePrice: price,
                lineTotal: lineTotal,
                commission: commission,
                commissionTotal: commissionTotal,
                hasDiscount: originalPrice && originalPrice > price,
                originalPrice: originalPrice
            });

            // Calculate savings for discounted items
            if (originalPrice && originalPrice > price) {
                const itemSavings = (originalPrice - price) * quantity;
                savings += itemSavings;
                savingsTickets.push(ticketName);
            }
        });

        // Total = base prices + commission (no other taxes)
        const subtotalWithCommission = baseSubtotal + totalCommission;
        let total = subtotalWithCommission - this.discount;
        const points = Math.floor(total / 10);

        // Update DOM
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('summaryItems').textContent = totalItems;
        document.getElementById('subtotal').textContent = AmbiletUtils.formatCurrency(subtotalWithCommission);

        // Render breakdown in taxes container - grouped by event
        const taxesContainer = document.getElementById('taxesContainer');
        if (taxesContainer) {
            let breakdownHtml = '';
            const eventIds = Object.keys(eventGroups);
            const hasMultipleEvents = eventIds.length > 1;

            eventIds.forEach(function(eventId, eventIndex) {
                const group = eventGroups[eventId];

                // Show event title only if multiple events
                if (hasMultipleEvents) {
                    if (eventIndex > 0) {
                        breakdownHtml += '<div class="pt-3 mt-3 border-t border-border"></div>';
                    }
                    // Build event info string: title (date, venue, city)
                    var eventInfo = group.title;
                    var details = [];
                    if (group.date) details.push(AmbiletUtils.formatDate(group.date, 'short'));
                    if (group.venue) details.push(group.venue);
                    if (group.city && group.city !== group.venue) details.push(group.city);
                    if (details.length > 0) {
                        eventInfo += ' <span class="font-normal text-muted">(' + details.join(', ') + ')</span>';
                    }
                    breakdownHtml += '<div class="mb-2 text-sm font-bold text-secondary">' + eventInfo + '</div>';
                }

                // Show each ticket type
                group.tickets.forEach(function(ticket) {
                    breakdownHtml += '<div class="flex justify-between text-sm">' +
                        '<span class="text-muted">' + ticket.qty + 'x ' + ticket.name + '</span>' +
                        '<span class="font-medium">' + AmbiletUtils.formatCurrency(ticket.lineTotal) + '</span>' +
                    '</div>';
                });
            });

            // Show commission as "Taxe procesare" only if on top and has commission
            if (hasAddedOnTopCommission && totalCommission > 0) {
                breakdownHtml += '<div class="flex justify-between pt-3 mt-3 text-sm border-t border-border">' +
                    '<span class="text-muted">Taxe procesare</span>' +
                    '<span class="font-medium">' + AmbiletUtils.formatCurrency(totalCommission) + '</span>' +
                '</div>';
            }

            taxesContainer.innerHTML = breakdownHtml;
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
                savingsTextEl.textContent = `Alegând ${ticketNames} ai economisit:`;
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

    /**
     * Format seat information for display
     */
    formatSeats(seats) {
        if (!seats || seats.length === 0) return '';

        // Group seats by section and row
        const grouped = {};
        seats.forEach(seat => {
            const key = (seat.section || 'Secțiune') + ' - Rând ' + (seat.row || '?');
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(seat.seat || seat.label || '?');
        });

        // Format output
        const parts = [];
        Object.keys(grouped).forEach(key => {
            const seatLabels = grouped[key].join(', ');
            parts.push(key + ': Loc ' + seatLabels);
        });

        return parts.join(' | ');
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

        // Disable button during validation
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.disabled = true; btn.textContent = 'Se verifică...'; }

        const result = await AmbiletCart.applyPromoCode(code);

        if (result.success) {
            const promo = result.promo;
            this.discount = AmbiletCart.getPromoDiscount();
            this.appliedPromo = code;

            const label = promo.type === 'percentage'
                ? `-${promo.value}% reducere`
                : `-${AmbiletUtils.formatCurrency(promo.value)} reducere`;
            messageEl.innerHTML = `✓ Cod aplicat! ${label}`;
            messageEl.className = 'mt-2 text-sm text-success';
            messageEl.classList.remove('hidden');

            document.getElementById('promoCode').disabled = true;
            if (btn) { btn.textContent = 'Aplicat'; btn.disabled = true; }

            this.updateSummary();
        } else {
            messageEl.textContent = '✗ ' + (result.message || 'Cod invalid sau expirat');
            messageEl.className = 'mt-2 text-sm text-primary';
            messageEl.classList.remove('hidden');
            if (btn) { btn.disabled = false; btn.textContent = 'Aplică'; }
        }
    },

    loadExistingPromo() {
        const promo = AmbiletCart.getPromoCode();
        if (!promo) return;
        this.discount = AmbiletCart.getPromoDiscount();
        this.appliedPromo = promo.code;

        const messageEl = document.getElementById('promoMessage');
        if (messageEl) {
            const label = promo.type === 'percentage'
                ? `-${promo.value}% reducere`
                : `-${AmbiletUtils.formatCurrency(promo.value)} reducere`;
            messageEl.innerHTML = `✓ Cod aplicat: ${promo.code} (${label})`;
            messageEl.className = 'mt-2 text-sm text-success';
            messageEl.classList.remove('hidden');
        }
        const input = document.getElementById('promoCode');
        if (input) { input.value = promo.code; input.disabled = true; }
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.textContent = 'Aplicat'; btn.disabled = true; }
    }
};

document.addEventListener('DOMContentLoaded', () => CartPage.init());

// Listen for cart expiration event from cart.js
window.addEventListener('ambilet:cart:expired', () => {
    console.log('[CartPage] Cart expired event received');
    if (CartPage.timerInterval) {
        clearInterval(CartPage.timerInterval);
    }
    localStorage.removeItem('cart_end_time');
    CartPage.render();
});

// Initialize featured carousel
document.addEventListener('DOMContentLoaded', () => FeaturedCarousel.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
