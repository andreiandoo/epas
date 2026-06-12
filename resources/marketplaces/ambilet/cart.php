<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/cart-locale.php';
$pageTitle = ct('page_title');
$pageDescription = ct('page_description');
$cssBundle = 'checkout';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Progress Steps -->
    <div class="bg-white border-b border-gray-200 mt-17 mobile:mt-18">
        <div class="px-4 py-4 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full bg-primary">1</div>
                    <span class="text-sm font-semibold text-primary"><?= htmlspecialchars(ct('step_cart')) ?></span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-400 bg-gray-100 border border-gray-200 rounded-full">2</div>
                    <span class="text-sm text-gray-400"><?= htmlspecialchars(ct('step_checkout')) ?></span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-400 bg-gray-100 border border-gray-200 rounded-full">3</div>
                    <span class="text-sm text-gray-400"><?= htmlspecialchars(ct('step_confirm')) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Timer -->
    <div id="timer-bar" class="border-b bg-warning/10 border-warning/20 mobile:sticky mobile:top-18 mobile:bg-warning mobile:z-20">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-2 text-sm">
                <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-secondary"><?= htmlspecialchars(ct('timer_message')) ?></span>
                <span id="countdown" class="font-bold countdown text-warning tabular-nums">14:59</span>
                <span class="text-secondary"><?= htmlspecialchars(ct('timer_minutes')) ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl">
        <div class="flex flex-col gap-8 lg:flex-row">
            <!-- Left Column - Cart Items -->
            <div class="lg:w-2/3">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold md:text-3xl text-secondary"><?= htmlspecialchars(ct('cart_title')) ?></h1>
                    <span class="text-muted"><span id="totalItems">0</span> <?= htmlspecialchars(ct('cart_items_unit')) ?></span>
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
                    <h3 class="mb-2 text-xl font-bold text-secondary"><?= htmlspecialchars(ct('empty_cart_title')) ?></h3>
                    <p class="mb-6 text-muted"><?= htmlspecialchars(ct('empty_cart_text')) ?></p>
                    <a href="/evenimente<?= $cartLocale !== 'ro' ? '?lang=' . urlencode($cartLocale) : '' ?>" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary bg-primary rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <?= htmlspecialchars(ct('discover_events')) ?>
                    </a>
                </div>

                <!-- Promo Code -->
                <div id="promo-section" class="hidden p-5 mt-6 bg-white border rounded-2xl border-border">
                    <h3 class="mb-3 font-semibold text-secondary"><?= htmlspecialchars(ct('promo_title')) ?></h3>
                    <div class="flex gap-3">
                        <input type="text" id="promoCode" placeholder="<?= htmlspecialchars(ct('promo_placeholder')) ?>" class="flex-1 px-4 py-3 transition-all border bg-surface border-border rounded-xl text-secondary placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <button onclick="CartPage.applyPromo()" class="px-6 py-3 font-semibold text-white transition-colors bg-secondary rounded-xl hover:bg-secondary/90" aria-label="<?= htmlspecialchars(ct('promo_apply_aria')) ?>">
                            <?= htmlspecialchars(ct('promo_apply')) ?>
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
                            <h2 class="text-xl font-bold text-secondary"><?= htmlspecialchars(ct('summary_title')) ?></h2>
                        </div>

                        <div class="p-3">
                            <!-- Order Lines -->
                            <div class="mb-3 space-y-3">
                                <!-- Dynamic taxes container (ticket breakdown) -->
                                <div id="taxesContainer" class="space-y-2">
                                    <!-- Taxes will be rendered dynamically by JS -->
                                </div>
                                <div id="discountRow" class="flex justify-between hidden text-sm">
                                    <span class="text-success"><?= htmlspecialchars(ct('discount_applied')) ?></span>
                                    <span id="discountAmount" class="font-medium text-success">-0.00 lei</span>
                                </div>
                                <!-- Subtotal - last before total -->
                                <div class="flex justify-between pt-2 text-sm border-t border-border">
                                    <span class="text-muted"><?= htmlspecialchars(ct('subtotal')) ?> (<span id="summaryItems">0</span> <?= htmlspecialchars(ct('cart_items_unit')) ?>)</span>
                                    <span id="subtotal" class="font-medium">0.00 lei</span>
                                </div>
                            </div>

                            <!-- Savings -->
                            <div id="savingsRow" class="hidden px-3 py-2 mb-3 bg-success/10 rounded-xl">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span id="savingsText" class="text-sm font-medium text-success"><?= htmlspecialchars(ct('savings_prefix')) ?> [numebilet] <?= htmlspecialchars(ct('savings_suffix')) ?></span>
                                        <span id="savings" class="flex-none font-bold text-success">0.00 lei</span>
                                    </div>

                                </div>
                            </div>

                            <!-- Total -->
                            <div class="flex items-center justify-between py-4 border-t border-border">
                                <span class="text-lg font-bold text-secondary"><?= htmlspecialchars(ct('total_to_pay')) ?></span>
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
                                            <p class="text-sm font-medium text-secondary"><?= htmlspecialchars(ct('will_earn')) ?></p>
                                            <p class="text-xs text-muted"><?= htmlspecialchars(ct('points_per_lei')) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span id="pointsEarned" class="text-2xl font-bold text-accent points-animation">0</span>
                                        <p class="text-xs text-muted"><?= htmlspecialchars(ct('points_label')) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkout Button -->
                            <a href="/finalizare<?= $cartLocale !== 'ro' ? '?lang=' . urlencode($cartLocale) : '' ?>" id="checkoutBtn" class="flex items-center justify-center w-full gap-2 py-4 mt-6 text-lg font-bold text-white btn-primary bg-primary rounded-xl">
                                <?= htmlspecialchars(ct('continue_to_payment')) ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>

                            <!-- Trust Badges -->
                            <div class="pt-6 mt-3 border-t border-border">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        <?= htmlspecialchars(ct('secure_payment')) ?>
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?= htmlspecialchars(ct('instant_tickets')) ?>
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        <?= htmlspecialchars(ct('card_or_transfer')) ?>
                                    </div>
                                    <div class="flex items-center justify-center gap-2 text-xs text-muted">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?= htmlspecialchars(ct('guarantee_100')) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                        <p class="mb-3 text-xs text-center text-muted"><?= htmlspecialchars(ct('payment_methods')) ?></p>
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
$scriptsExtra = '<style>
    .tooltip { opacity: 0; visibility: hidden; transition: all 0.2s ease; transform: translateY(5px); }
    .tooltip-trigger:hover .tooltip { opacity: 1; visibility: visible; transform: translateY(0); }
    .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
    .points-animation { animation: pointsPulse 0.4s ease; }
    @keyframes pointsPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
    .remove-btn:hover { background-color: #FEE2E2; color: #DC2626; }
    .cart-item { transition: all 0.3s ease; }
    .cart-item:hover { border-color: var(--color-primary, #A51C30); }
</style>
<script defer src="' . asset('assets/js/pages/cart-page.js') . '"></script>';

require_once __DIR__ . '/includes/scripts.php';
?>
